<?php
require_once __DIR__ . '/../config/dbconnect.php';
require_once __DIR__ . '/../config/rateLimiter.php';
require_once __DIR__ . '/../config/securityLogger.php';
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/captcha.php';
require_once __DIR__ . '/../config/emailVerification.php';

function isMobileDevice()
{
    return preg_match('/iPhone|Android.+Mobile|Windows Phone/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    /* ---------------------------------------------------------------------
       Registration step 1 — send a 6-digit verification code.
       --------------------------------------------------------------------- */
    if (isset($_POST['sendEmailCode'])) {
        requireJsonCsrf();

        $email = normalizeVerificationEmail($_POST['email'] ?? '');
        if (!isValidVerificationEmail($email)) {
            echo json_encode(['status' => 401, 'msg' => 'Enter a valid email address.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM tbl_user WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $alreadyRegistered = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($alreadyRegistered) {
            echo json_encode(['status' => 401, 'msg' => 'This email is already registered. Please sign in instead.']);
            exit;
        }

        $clientIp = getClientIp();
        $limiterKey = $email . '|' . $clientIp;
        $sendLimiter = new RateLimiter('email_verify_send', 5, 3600);
        if (!$sendLimiter->check($limiterKey)) {
            SecurityLogger::rateLimitExceeded('email_verify_send', maskVerificationEmail($email));
            echo json_encode(['status' => 429, 'msg' => 'Too many verification code requests. Please try again later.']);
            exit;
        }

        $ipLimiter = new RateLimiter('email_verify_ip', 10, 3600);
        if (!$ipLimiter->check($clientIp)) {
            SecurityLogger::rateLimitExceeded('email_verify_ip', $clientIp);
            echo json_encode(['status' => 429, 'msg' => 'Too many verification code requests from this network. Please try again later.']);
            exit;
        }

        $window = canSendVerificationCode($conn, $email);
        if (!$window['allowed']) {
            echo json_encode([
                'status'      => 429,
                'msg'         => 'Please wait before requesting another code.',
                'retry_after' => (int) $window['retry_after'],
            ]);
            exit;
        }

        // Opportunistic housekeeping: remove verification rows older than a day.
        cleanupExpiredEmailVerifications($conn, 1);

        $created = createEmailVerification($conn, $email, $clientIp, $_SERVER['HTTP_USER_AGENT'] ?? '');
        if (empty($created['ok'])) {
            echo json_encode(['status' => 401, 'msg' => 'We could not create a verification code. Please try again.']);
            exit;
        }

        $sent = sendVerificationCodeEmail($email, (string) $created['code']);
        if (empty($sent['success'])) {
            invalidateEmailVerifications($conn, $email);
            SecurityLogger::log('SYSTEM', 'Verification code email failed for ' . maskVerificationEmail($email), [
                'error' => $sent['error'] ?? '',
            ], 'WARNING');
            echo json_encode(['status' => 502, 'msg' => 'We could not send the verification code. Please try again shortly.']);
            exit;
        }

        $sendLimiter->record($limiterKey);
        $ipLimiter->record($clientIp);
        SecurityLogger::auth('Email Verification Code Sent', 'Code sent to ' . maskVerificationEmail($email));
        echo json_encode([
            'status'     => 201,
            'msg'        => 'Verification code sent. Check your inbox and spam folder.',
            'cooldown'   => EMAIL_VERIFICATION_RESEND_COOLDOWN,
            'expires_in' => (int) $created['expires_in'],
        ]);
        exit;
    }

    /* ---------------------------------------------------------------------
       Registration step 2 — confirm the code and mark the email verified.
       --------------------------------------------------------------------- */
    if (isset($_POST['verifyEmailCode'])) {
        requireJsonCsrf();

        $email = normalizeVerificationEmail($_POST['email'] ?? '');
        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
        if (!isValidVerificationEmail($email) || !isValidVerificationCode($code)) {
            echo json_encode(['status' => 401, 'msg' => 'Enter the 6-digit code from your email.']);
            exit;
        }

        $clientIp = getClientIp();
        $limiterKey = $email . '|' . $clientIp;
        $verifyLimiter = new RateLimiter('email_verify_check', 10, 900);
        if (!$verifyLimiter->check($limiterKey)) {
            SecurityLogger::rateLimitExceeded('email_verify_check', maskVerificationEmail($email));
            echo json_encode(['status' => 429, 'msg' => 'Too many verification attempts. Please wait and try again.']);
            exit;
        }

        $result = verifyEmailVerificationCode($conn, $email, $code);
        $verifyLimiter->record($limiterKey);

        if (empty($result['ok'])) {
            SecurityLogger::auth('Email Verification Failed', maskVerificationEmail($email) . ' — ' . ($result['reason'] ?? 'unknown'));
            echo json_encode([
                'status'             => 401,
                'msg'                => $result['msg'],
                'reason'             => $result['reason'] ?? 'invalid',
                'remaining_attempts' => $result['remaining_attempts'] ?? null,
            ]);
            exit;
        }

        $verifyLimiter->clear($limiterKey);
        markEmailVerifiedInSession($email);
        SecurityLogger::auth('Email Verified', maskVerificationEmail($email));
        echo json_encode(['status' => 201, 'msg' => 'Email verified. Continue with your application.']);
        exit;
    }

    if (isset($_POST['signUp'])) {
        requireJsonCsrf();

        $email = normalizeVerificationEmail($_POST["email"] ?? '');
        if (!isEmailVerifiedInSession($email)) {
            echo json_encode(['status' => 401, 'msg' => 'Please verify your email address before creating your account.']);
            exit;
        }

        // CAPTCHA verification
        $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
        if (!verifyRecaptcha($recaptchaToken)) {
            echo json_encode(['status'=>401,'msg'=>'CAPTCHA verification failed. Please try again.']);
            exit;
        }

        $fname = trim($_POST["fname"] ?? '');
        $lname = trim($_POST["lname"] ?? '');
        $phone = trim($_POST["phone"] ?? '');
        $pass = $_POST["pass1"] ?? '';
        $pass2 = $_POST["pass2"] ?? '';
        $suffix = !empty($_POST['suffix'] ?? '') ? trim($_POST['suffix']) : '';
        $mname = !empty($_POST['mname'] ?? '') ? trim($_POST['mname']) : '';
        $pv = validatePasswordComplexity($pass);
        if (!$pv['valid']) { echo json_encode(['status'=>401,'msg'=>$pv['msg']]); exit; }
        $stmt = $conn->prepare("SELECT id FROM tbl_user WHERE fname = ? AND lname = ?");
        $stmt->bind_param('ss', $fname, $lname); $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) { $stmt->close(); echo json_encode(['status'=>401,'msg'=>'Users already have an account']); exit; }
        $stmt->close();
        $stmt = $conn->prepare("SELECT id FROM tbl_user WHERE phone = ?");
        $stmt->bind_param('s', $phone); $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) { $stmt->close(); echo json_encode(['status'=>401,'msg'=>'Phone already registered']); exit; }
        $stmt->close();
        $stmt = $conn->prepare("SELECT id FROM tbl_user WHERE email = ?");
        $stmt->bind_param('s', $email); $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) { $stmt->close(); echo json_encode(['status'=>401,'msg'=>'Email already exist']); exit; }
        $stmt->close();
        if ($pass === $pass2) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $resId = ''; $role = '2'; $stat = '1';
            $stmt = $conn->prepare("INSERT INTO tbl_user (res_id,fname,lname,mname,suffix,phone,email,pass,role,stat,pass_stat) VALUES (?,?,?,?,?,?,?,?,?,?,0)");
            $stmt->bind_param('ssssssssss', $resId, $fname, $lname, $mname, $suffix, $phone, $email, $hash, $role, $stat);
            if ($stmt->execute()) {
                $stmt->close();
                if (!consumeVerifiedEmail($conn, $email)) {
                    SecurityLogger::log('SYSTEM', 'Verified email row was already consumed for ' . maskVerificationEmail($email), [], 'WARNING');
                }
                clearEmailVerificationSession();
                SecurityLogger::auth('Account Created', 'New team representative account for ' . maskVerificationEmail($email));
                echo json_encode(['status'=>201,'msg'=>'Account created successfully']);
                exit;
            } else {
                $stmt->close();
                echo json_encode(['status'=>401,'msg'=>'Something went wrong']);
                exit;
            }
        } else { echo json_encode(['status'=>401,'msg'=>"Password doesn't match"]); exit; }
    }
    if (isset($_POST['loginBtn'])) {
        // CAPTCHA verification
        $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
        if (!verifyRecaptcha($recaptchaToken)) {
            echo json_encode(['status'=>401,'msg'=>'CAPTCHA verification failed. Please try again.']);
            exit;
        }

        // Session-based rate limiting: 5 attempts per 5 minutes
        $loginLimiter = new RateLimiter('login_ip', 5, 300);
        $clientIp = getClientIp();
        if (!$loginLimiter->check($clientIp)) {
            $retryAfter = $loginLimiter->retryAfter($clientIp);
            $minutes = ceil($retryAfter / 60);
            echo json_encode(['status'=>429,'msg'=>"Too many login attempts. Please try again in {$minutes} minute(s)."]);
            exit;
        }
        $time = time() - 30;
        $ip_address = getClientIp();
        $stmt = $conn->prepare("SELECT COUNT(*) AS total_count FROM tbl_attempts WHERE try_time > ? AND ip_address = ?");
        $stmt->bind_param('is', $time, $ip_address); $stmt->execute();
        $check_login_row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        $total_count = $check_login_row['total_count'] ?? 0;
        if ($total_count >= 3) { echo json_encode(['status'=>401,'msg'=>'Please login after 30 seconds']); exit; }
        $username = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $stat = '1';
        $stmt = $conn->prepare("SELECT id, fname, lname, pass, role FROM tbl_user WHERE email = ? AND stat = ?");
        $stmt->bind_param('ss', $username, $stat); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($row && password_verify($password, $row['pass'])) {
            $userType = (int)$row['role'];
            $stmt = $conn->prepare("DELETE FROM tbl_attempts WHERE ip_address = ?");
            $stmt->bind_param('s', $ip_address); $stmt->execute(); $stmt->close();
            $loginLimiter->clear($clientIp);
            // Use secure session handler for consistency with auth_guard.php
            if (file_exists(__DIR__ . '/../config/sessionHandler.php')) {
                require_once __DIR__ . '/../config/sessionHandler.php';
                if (function_exists('initializeSecureSession') && session_status() !== PHP_SESSION_ACTIVE) {
                    initializeSecureSession();
                }
            }
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            $_SESSION['loggin'] = true;
            $_SESSION['loggin_stat'] = "true";
            $_SESSION['lms_id'] = $row['id'];
            $_SESSION['lms_username'] = $row['fname'] . ' ' . $row['lname'];
            $_SESSION['lms_role'] = $userType;
            if (function_exists('generateSessionFingerprint')) {
                $_SESSION['fingerprint'] = generateSessionFingerprint();
            }
            $uname = $row['fname'] . ' ' . $row['lname'];
            $act = 'Login'; $det = 'User logged in successfully';
            $stmt = $conn->prepare("INSERT INTO tbl_audit_log (user_name, user_id, action, details) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('siss', $uname, $row['id'], $act, $det);
            $stmt->execute(); $stmt->close();
            SecurityLogger::auth('Login Success', "User $uname (ID {$row['id']}) logged in", ['role' => $userType]);
            if ($userType == 1) { $redirect = "app/organizer/dashboard.php"; }
            else if ($userType == 2) { $redirect = "app/tr/dashboard.php"; }
            else if ($userType == 3) { $redirect = "app/admin/dashboard.php"; }
            else if ($userType == 4) { $redirect = "app/committee/dashboard.php"; }
            else { $redirect = "login.php"; }
            echo json_encode(['status'=>200,'msg'=>'Welcome! You have logged in successfully.','redirect'=>$redirect]);
            exit;
        } else {
            $try_time = time();
            $stmt = $conn->prepare("INSERT INTO tbl_attempts (ip_address, try_time) VALUES (?, ?)");
            $stmt->bind_param('si', $ip_address, $try_time); $stmt->execute(); $stmt->close();
            // Record failed attempt in session rate limiter
            $loginLimiter->record($clientIp);
            $remaining = $loginLimiter->remainingAttempts($clientIp);
            SecurityLogger::auth('Login Failed', "Failed login attempt for email: " . substr($username, 0, 3) . '***', ['ip' => $clientIp]);
            $msg = 'Invalid email or password';
            if ($remaining <= 2) {
                $msg .= " ($remaining attempt(s) remaining)";
            }
            echo json_encode(['status'=>401,'msg'=>$msg]);
            exit;
        }
    }
    if (isset($_POST['register'])) {
        http_response_code(410);
        echo json_encode(['status' => 410, 'msg' => 'This registration endpoint has been retired. Refresh the league page and try again.']);
        exit;
    }
    if (isset($_POST['loginModal'])) {
        // CAPTCHA verification
        $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
        if (!verifyRecaptcha($recaptchaToken)) {
            echo json_encode(['status'=>401,'msg'=>'CAPTCHA verification failed. Please try again.']);
            exit;
        }

        // Session-based rate limiting for modal login: 5 attempts per 5 minutes
        $modalLimiter = new RateLimiter('login_modal_ip', 5, 300);
        $clientIp = getClientIp();
        if (!$modalLimiter->check($clientIp)) {
            $retryAfter = $modalLimiter->retryAfter($clientIp);
            $minutes = ceil($retryAfter / 60);
            echo json_encode(['status'=>429,'msg'=>"Too many login attempts. Please try again in {$minutes} minute(s)."]);
            exit;
        }
        $time = time() - 30;
        $ip_address = getClientIp();
        $stmt = $conn->prepare("SELECT COUNT(*) AS total_count FROM tbl_attempts WHERE try_time > ? AND ip_address = ?");
        $stmt->bind_param('is', $time, $ip_address); $stmt->execute();
        $check_login_row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        $total_count = $check_login_row['total_count'] ?? 0;
        if ($total_count >= 3) { echo json_encode(['status'=>401,'msg'=>'Please login after 30 seconds']); exit; }
        $username = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $stat = '1';
        $stmt = $conn->prepare("SELECT id, fname, lname, pass, role FROM tbl_user WHERE email = ? AND stat = ?");
        $stmt->bind_param('ss', $username, $stat); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($row && password_verify($password, $row['pass'])) {
            $userType = (int)$row['role'];
            if ($userType !== 2) {
                http_response_code(403);
                SecurityLogger::unauthorizedAccess("Non-TR login attempt via modal by user ID {$row['id']}", ['user_id' => (int)$row['id']]);
                echo json_encode(['status'=>403,'msg'=>'Only Team Representative accounts can register a team. Please use the main login page for your assigned role.']);
                exit;
            }
            $stmt = $conn->prepare("DELETE FROM tbl_attempts WHERE ip_address = ?");
            $stmt->bind_param('s', $ip_address); $stmt->execute(); $stmt->close();
            $modalLimiter->clear($clientIp);
            // Use secure session handler for consistency with auth_guard.php
            if (file_exists(__DIR__ . '/../config/sessionHandler.php')) {
                require_once __DIR__ . '/../config/sessionHandler.php';
                if (function_exists('initializeSecureSession') && session_status() !== PHP_SESSION_ACTIVE) {
                    initializeSecureSession();
                }
            }
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            $_SESSION['loggin'] = true;
            $_SESSION['loggin_stat'] = "true";
            $_SESSION['lms_id'] = $row['id'];
            $_SESSION['lms_username'] = $row['fname'] . ' ' . $row['lname'];
            $_SESSION['lms_role'] = $userType;
            if (function_exists('generateSessionFingerprint')) {
                $_SESSION['fingerprint'] = generateSessionFingerprint();
            }
            SecurityLogger::auth('Login Success', "User {$row['fname']} {$row['lname']} (ID {$row['id']}) logged in via modal", ['role' => $userType]);
            echo json_encode(['status'=>200,'msg'=>'Login successful']);
            exit;
        } else {
            $try_time = time();
            $stmt = $conn->prepare("INSERT INTO tbl_attempts (ip_address, try_time) VALUES (?, ?)");
            $stmt->bind_param('si', $ip_address, $try_time); $stmt->execute(); $stmt->close();
            $modalLimiter->record($clientIp);
            $remaining = $modalLimiter->remainingAttempts($clientIp);
            $msg = 'Invalid email or password';
            if ($remaining <= 2) {
                $msg .= " ($remaining attempt(s) remaining)";
            }
            echo json_encode(['status'=>401,'msg'=>$msg]);
            exit;
        }
    }
}
