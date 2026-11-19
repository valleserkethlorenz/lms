<?php
/**
 * Forgot Password Page — Team Representative OTP flow
 *
 * Step 1  request a 6-digit code for the registered account email
 * Step 2  verify that code; reset-password.php then allows a new password
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/config/dbconnect.php';
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/config/passwordReset.php';

if (currentUserId()) {
    $role = currentRole();
    if ($role == 1) header('Location: app/organizer/dashboard.php');
    elseif ($role == 2) header('Location: app/tr/dashboard.php');
    elseif ($role == 3) header('Location: app/admin/dashboard.php');
    elseif ($role == 4) header('Location: app/committee/dashboard.php');
    exit;
}

$csrfToken = csrfToken();

// Flash messages use POST/redirect/GET so a refresh cannot resend a code.
$flash = $_SESSION['pwd_reset_flash'] ?? null;
unset($_SESSION['pwd_reset_flash']);
$message = is_array($flash) ? (string) ($flash['message'] ?? '') : '';
$messageType = is_array($flash) ? (string) ($flash['type'] ?? 'info') : '';
$lastEmail = is_array($flash) ? (string) ($flash['email'] ?? '') : '';

$pendingEmail = (string) ($_SESSION['pwd_reset_pending_email'] ?? '');
$step = $pendingEmail !== '' ? 'otp' : 'email';
$resendRemaining = 0;
if ($step === 'otp') {
    $sentAt = (int) ($_SESSION['pwd_reset_otp_sent_at'] ?? 0);
    $resendRemaining = max(0, ($sentAt + PASSWORD_RESET_OTP_RESEND_COOLDOWN) - time());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['pwd_reset_flash'] = ['type' => 'danger', 'message' => 'Invalid request. Please refresh the page and try again.'];
        header('Location: forgot-password.php');
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'change_email') {
        clearPasswordResetFlow();
        header('Location: forgot-password.php');
        exit;
    }

    if ($action === 'request_code' || $action === 'resend_code') {
        $email = $action === 'resend_code'
            ? normalizeVerificationEmail($pendingEmail)
            : normalizeVerificationEmail($_POST['email'] ?? '');

        if (!isValidVerificationEmail($email)) {
            $_SESSION['pwd_reset_flash'] = [
                'type' => 'warning',
                'message' => 'Please enter a valid email address.',
                'email' => $email,
            ];
            header('Location: forgot-password.php');
            exit;
        }

        $clientIp = getClientIp();
        $limiterKey = $email . '|' . $clientIp;

        $sendLimiter = new RateLimiter('password_reset_send', 5, 3600);
        if (!$sendLimiter->check($limiterKey)) {
            SecurityLogger::rateLimitExceeded('password_reset_send', maskVerificationEmail($email));
            $_SESSION['pwd_reset_flash'] = ['type' => 'warning', 'message' => 'Too many reset requests. Please try again later.'];
            header('Location: forgot-password.php');
            exit;
        }

        $ipLimiter = new RateLimiter('password_reset_ip', 10, 3600);
        if (!$ipLimiter->check($clientIp)) {
            SecurityLogger::rateLimitExceeded('password_reset_ip', $clientIp);
            $_SESSION['pwd_reset_flash'] = ['type' => 'warning', 'message' => 'Too many reset requests from this network. Please try again later.'];
            header('Location: forgot-password.php');
            exit;
        }

        // Every valid submission consumes a slot, including addresses that
        // do not belong to an eligible account.
        $sendLimiter->record($limiterKey);
        $ipLimiter->record($clientIp);

        // Check for an active Team Representative before creating a code.
        // No OTP row is created and no email is sent for any other address.
        $user = findPasswordResetUser($conn, $email);
        if (!$user) {
            clearPasswordResetFlow();
            $_SESSION['pwd_reset_flash'] = [
                'type' => 'warning',
                'message' => 'No Team Representative account found for that email address. Please check the email or contact the administrator.',
                'email' => $email,
            ];
            header('Location: forgot-password.php');
            exit;
        }

        $window = canRequestPasswordResetOtp($conn, $email);
        if (!$window['allowed']) {
            $retry = max(1, (int) ($window['retry_after'] ?? PASSWORD_RESET_OTP_RESEND_COOLDOWN));
            $_SESSION['pwd_reset_flash'] = [
                'type' => 'warning',
                'message' => 'Please wait ' . $retry . ' second(s) before requesting another code.',
                'email' => $email,
            ];
            header('Location: forgot-password.php');
            exit;
        }

        cleanupExpiredEmailVerifications($conn, 1);
        $created = createPasswordResetOtp($conn, $email, $clientIp, $_SERVER['HTTP_USER_AGENT'] ?? '');
        if (empty($created['ok'])) {
            $_SESSION['pwd_reset_flash'] = ['type' => 'danger', 'message' => 'We could not create a reset code. Please try again.'];
            header('Location: forgot-password.php');
            exit;
        }

        $sent = sendPasswordResetOtpEmail($email, (string) $created['code'], (string) $user['fname']);
        if (empty($sent['success'])) {
            invalidateEmailVerifications($conn, $email);
            $_SESSION['pwd_reset_flash'] = ['type' => 'danger', 'message' => 'We could not send the reset code. Please try again later.'];
            header('Location: forgot-password.php');
            exit;
        }

        // Only a successfully sent code advances to the OTP step.
        $_SESSION['pwd_reset_pending_email'] = $email;
        $_SESSION['pwd_reset_otp_sent_at'] = time();
        $_SESSION['pwd_reset_otp_expires_at'] = time() + PASSWORD_RESET_OTP_EXPIRY;
        $_SESSION['pwd_reset_flash'] = [
            'type' => 'success',
            'message' => 'A 6-digit code has been sent. Check your inbox and spam folder.',
        ];
        header('Location: forgot-password.php');
        exit;
    }

    if ($action === 'verify_code') {
        if ($pendingEmail === '') {
            $_SESSION['pwd_reset_flash'] = ['type' => 'warning', 'message' => 'Your reset session expired. Enter your email again.'];
            header('Location: forgot-password.php');
            exit;
        }

        $clientIp = getClientIp();
        $limiterKey = $pendingEmail . '|' . $clientIp;
        $verifyLimiter = new RateLimiter('password_reset_verify', 10, 900);
        if (!$verifyLimiter->check($limiterKey)) {
            SecurityLogger::rateLimitExceeded('password_reset_verify', maskVerificationEmail($pendingEmail));
            $_SESSION['pwd_reset_flash'] = ['type' => 'warning', 'message' => 'Too many verification attempts. Please wait and try again.'];
            header('Location: forgot-password.php');
            exit;
        }

        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
        if (!isValidVerificationCode($code)) {
            $_SESSION['pwd_reset_flash'] = ['type' => 'warning', 'message' => 'Enter the 6-digit code from your email.'];
            header('Location: forgot-password.php');
            exit;
        }

        $result = verifyPasswordResetOtp($conn, $pendingEmail, $code);
        $verifyLimiter->record($limiterKey);

        if (empty($result['ok'])) {
            $_SESSION['pwd_reset_flash'] = [
                'type' => 'danger',
                'message' => (string) ($result['msg'] ?? 'That code is invalid or has expired.'),
            ];
            header('Location: forgot-password.php');
            exit;
        }

        // The grant carries the verified identity; the pending address is done.
        unset(
            $_SESSION['pwd_reset_pending_email'],
            $_SESSION['pwd_reset_otp_sent_at'],
            $_SESSION['pwd_reset_otp_expires_at']
        );
        header('Location: reset-password.php');
        exit;
    }

    $_SESSION['pwd_reset_flash'] = ['type' => 'danger', 'message' => 'Invalid request. Please try again.'];
    header('Location: forgot-password.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - Forgot Password</title>
    <link href="assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--primary:#09173c}
        body{background:#ffffff;min-height:100vh;display:flex;justify-content:center;align-items:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial}
        .rc{background:#fff;border-radius:12px;max-width:440px;width:100%;box-shadow:0 10px 30px rgba(17,23,60,.12);overflow:hidden}
        .rc-h{background:var(--primary);padding:24px 20px;text-align:center}
        .rc-h .ic{width:60px;height:60px;border-radius:50%;background:#fff;margin:0 auto;display:flex;align-items:center;justify-content:center}
        .rc-h .ic i{font-size:28px;color:var(--primary)}
        .rc-h h5{color:#fff;margin:12px 0 0;font-size:17px}
        .rc-h p{color:rgba(255,255,255,.75);font-size:13px;margin:4px 0 0}
        .rc-b{padding:24px 20px}
        .btn-r{background:var(--primary);border:none;color:#fff;padding:10px 20px;border-radius:8px;width:100%;font-size:14px;cursor:pointer}
        .btn-r:hover{background:#0d2257;color:#fff}
        .login-secondary-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:8px}
        .login-secondary-link{display:flex;align-items:center;justify-content:center;gap:7px;min-width:0;min-height:42px;padding:8px 10px;border:1px solid #dfe6ef;border-radius:8px;background:#f8fafc;color:#53617a;font-size:12px;font-weight:600;line-height:1.2;text-align:center;text-decoration:none}
        .login-secondary-link:hover{border-color:#cbd9ee;background:#eef4ff;color:var(--primary);text-decoration:none}
        .login-secondary-link i{flex:0 0 auto;font-size:13px}
        .otp-input{letter-spacing:.45em;text-align:center;font-weight:700;font-size:18px}
        .policy-note{font-size:12.5px;line-height:1.55;color:#64748b;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin-bottom:16px}
        .btn-resend{border:1px solid #dfe6ef;background:#f8fafc;color:#53617a;padding:9px 16px;border-radius:8px;width:100%;font-size:13px;font-weight:600;cursor:pointer}
        .btn-resend:hover:not(:disabled){border-color:#cbd9ee;background:#eef4ff;color:var(--primary)}
        .btn-resend:disabled{opacity:.65;cursor:not-allowed}
        .btn-plain{border:none;background:none;color:#64748b;font-size:13px;width:100%;padding:6px;cursor:pointer}
        .btn-plain:hover{color:var(--primary)}
    </style>
</head>
<body>
<div class="rc">
    <div class="rc-h">
        <div class="ic"><i class="bi <?php echo $step === 'otp' ? 'bi-shield-check' : 'bi-key'; ?>"></i></div>
        <h5><?php echo $step === 'otp' ? 'Enter Verification Code' : 'Forgot Your Password?'; ?></h5>
        <p><?php echo $step === 'otp' ? 'Enter the 6-digit code sent to your email' : 'Reset your password here.'; ?></p>
    </div>
    <div class="rc-b">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show" style="font-size:13px">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($step === 'email'): ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="request_code">
            <div class="mb-3">
                <label class="form-label" for="email" style="font-size:13px;color:#334155">Team Representative Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="Enter your registered email" required autofocus
                           value="<?php echo htmlspecialchars($lastEmail !== '' ? $lastEmail : ($_POST['email'] ?? '')); ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-r mb-3"><i class="bi bi-send me-1"></i> Send Verification Code</button>
        </form>
        <?php else: ?>
        <p class="text-muted" style="font-size:12.5px;line-height:1.55">
            A 6-digit code was sent to <strong><?php echo htmlspecialchars($pendingEmail); ?></strong>
            if it belongs to an active Team Representative account.
        </p>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="verify_code">
            <div class="mb-3">
                <label class="form-label" for="otpCode" style="font-size:13px;color:#334155">Verification Code</label>
                <input type="text" class="form-control otp-input" id="otpCode" name="code"
                       inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                       pattern="\d{6}" placeholder="000000" required autofocus>
                <small class="text-muted">The code expires in 10 minutes.</small>
            </div>
            <button type="submit" class="btn btn-r mb-2"><i class="bi bi-shield-check me-1"></i> Verify Code</button>
        </form>
        <form method="POST" action="" class="mb-2">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="resend_code">
            <button type="submit" class="btn-resend" id="resendBtn" <?php echo $resendRemaining > 0 ? 'disabled' : ''; ?>>
                <i class="bi bi-arrow-repeat me-1"></i>
                <span id="resendLabel"><?php echo $resendRemaining > 0 ? 'Resend in ' . (int) $resendRemaining . 's' : 'Resend code'; ?></span>
            </button>
        </form>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="change_email">
            <button type="submit" class="btn-plain"><i class="bi bi-pencil me-1"></i> Use a different email</button>
        </form>
        <?php endif; ?>

        <div class="login-secondary-actions">
            <a class="login-secondary-link" href="login.php"><i class="bi bi-arrow-left"></i><span>Back to Login</span></a>
            <a class="login-secondary-link" href="index.php"><i class="bi bi-house-door"></i><span>Back to Home</span></a>
        </div>
    </div>
</div>
<script src="assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<?php if ($step === 'otp'): ?>
<script>
(function () {
    var codeInput = document.getElementById('otpCode');
    if (codeInput) {
        codeInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    var resendBtn = document.getElementById('resendBtn');
    var resendLabel = document.getElementById('resendLabel');
    var remaining = <?php echo (int) $resendRemaining; ?>;
    if (resendBtn && resendLabel && remaining > 0) {
        var timer = setInterval(function () {
            remaining--;
            if (remaining <= 0) {
                clearInterval(timer);
                resendBtn.disabled = false;
                resendLabel.textContent = 'Resend code';
                return;
            }
            resendLabel.textContent = 'Resend in ' + remaining + 's';
        }, 1000);
    }
})();
</script>
<?php endif; ?>
</body>
</html>
