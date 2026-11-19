<?php
/**
 * Password Reset Helper
 * Provides the legacy link reset and the Team Representative OTP reset flow.
 *
 * Requires: dbconnect.php, securityLogger.php, rateLimiter.php, auth_guard.php,
 *           emailVerification.php, emailService.php
 */

require_once __DIR__ . '/securityLogger.php';
require_once __DIR__ . '/emailVerification.php';

/** Token validity duration in seconds (1 hour) */
const PASSWORD_RESET_EXPIRY = 3600;

/** Maximum reset requests per email per hour */
const PASSWORD_RESET_RATE_LIMIT = 3;

/** Minimum token length in bytes (before hex encoding) */
const PASSWORD_RESET_TOKEN_BYTES = 32;

/** OTP validity duration in seconds (10 minutes). */
const PASSWORD_RESET_OTP_EXPIRY = 600;

/** Minimum seconds between two OTP requests for the same email. */
const PASSWORD_RESET_OTP_RESEND_COOLDOWN = 60;

/** Maximum wrong OTP attempts before the code is burned. */
const PASSWORD_RESET_OTP_MAX_ATTEMPTS = 5;

/** Maximum OTP requests per email per hour. */
const PASSWORD_RESET_OTP_RATE_LIMIT = 3;

/** How long a verified browser may set a new password (15 minutes). */
const PASSWORD_RESET_GRANT_TTL = 900;

/**
 * Roles allowed to use the self-service OTP reset.
 * Team Representative = 2. Other roles reset through the Admin panel.
 */
const PASSWORD_RESET_ALLOWED_ROLES = ['2'];

/**
 * Ensure the password reset table exists.
 * Creates it if missing (auto-provisioning).
 */
function ensurePasswordResetTable(mysqli $conn): bool
{
    $check = $conn->query("SHOW TABLES LIKE 'tbl_password_reset'");
    if ($check && $check->num_rows > 0) return true;
    
    $sql = "CREATE TABLE IF NOT EXISTS tbl_password_reset (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(128) NOT NULL,
        token_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_password_reset_hash (token_hash),
        INDEX idx_password_reset_user (user_id),
        INDEX idx_password_reset_expires (expires_at),
        CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES tbl_user(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    return (bool)$conn->query($sql);
}

/**
 * Check rate limit for password reset requests.
 */
function canRequestPasswordReset(mysqli $conn, string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - PASSWORD_RESET_EXPIRY);
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM tbl_password_reset pr 
         JOIN tbl_user u ON u.id = pr.user_id 
         WHERE u.email = ? AND pr.created_at >= ?"
    );
    $stmt->bind_param('ss', $email, $since);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();
    return $count < PASSWORD_RESET_RATE_LIMIT;
}

/**
 * Generate a secure password reset token and store it.
 * Returns the plaintext token or false on failure.
 */
function generatePasswordResetToken(mysqli $conn, int $userId, string $ip = '', string $ua = ''): string|false
{
    ensurePasswordResetTable($conn);
    
    $token = bin2hex(random_bytes(PASSWORD_RESET_TOKEN_BYTES));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);
    
    $stmt = $conn->prepare(
        "INSERT INTO tbl_password_reset (user_id, token, token_hash, expires_at, ip_address, user_agent) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isssss', $userId, $token, $tokenHash, $expiresAt, $ip, $ua);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        SecurityLogger::auth('Password Reset Requested', "Token generated for user_id=$userId", ['ip' => $ip]);
        return $token;
    }
    return false;
}

/**
 * Validate a password reset token.
 * Returns the user_id if valid, or false if expired/used/invalid.
 */
function validatePasswordResetToken(mysqli $conn, string $token): int|false
{
    ensurePasswordResetTable($conn);
    $tokenHash = hash('sha256', $token);
    $now = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare(
        "SELECT user_id, expires_at, used FROM tbl_password_reset 
         WHERE token_hash = ? ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$row) return false;
    if ((int)$row['used'] === 1) return false;
    if (strtotime($row['expires_at']) < strtotime($now)) return false;
    
    return (int)$row['user_id'];
}

/**
 * Mark a reset token as used and update the user's password.
 */
function completePasswordReset(mysqli $conn, string $token, int $userId, string $newPasswordHash): bool
{
    $tokenHash = hash('sha256', $token);
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "UPDATE tbl_password_reset SET used = 1 WHERE token_hash = ? AND user_id = ? AND used = 0"
        );
        $stmt->bind_param('si', $tokenHash, $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        if ($affected === 0) { $conn->rollback(); return false; }
        
        // Invalidate all other tokens for this user
        $stmt = $conn->prepare("UPDATE tbl_password_reset SET used = 1 WHERE user_id = ? AND used = 0");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        
        // Update password
        $stmt = $conn->prepare("UPDATE tbl_user SET pass = ?, pass_stat = 1 WHERE id = ?");
        $stmt->bind_param('si', $newPasswordHash, $userId);
        $stmt->execute();
        $stmt->close();
        
        if (function_exists('recordPasswordChange')) {
            recordPasswordChange($conn, $userId, $newPasswordHash);
        }
        
        $conn->commit();
        SecurityLogger::auth('Password Reset Completed', "Password changed via reset for user_id=$userId");
        return true;
    } catch (\Exception $e) {
        $conn->rollback();
        SecurityLogger::log('SYSTEM', 'Password reset failed: ' . $e->getMessage(), [], 'CRITICAL');
        return false;
    }
}

/**
 * Clean up expired tokens older than given days.
 */
function cleanupExpiredResetTokens(mysqli $conn, int $olderThanDays = 7): int
{
    ensurePasswordResetTable($conn);
    $cutoff = date('Y-m-d H:i:s', time() - ($olderThanDays * 86400));
    $stmt = $conn->prepare("DELETE FROM tbl_password_reset WHERE expires_at < ?");
    $stmt->bind_param('s', $cutoff);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    return $deleted;
}

/**
 * Build the reset URL for a given token.
 */
function buildResetUrl(string $token): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$protocol://$host/lms/reset-password.php?token=" . urlencode($token);
}

/**
 * Attempt to send a password reset email.
 * Uses PHPMailer SMTP when configured, falls back to PHP mail().
 */
function sendPasswordResetEmail(mysqli $conn, string $email, string $token, string $userName): bool
{
    $resetUrl = buildResetUrl($token);
    
    SecurityLogger::auth(
        'Password Reset Email',
        "Reset link generated for " . substr($email, 0, 3) . '***',
        ['reset_url_hash' => hash('sha256', $resetUrl)]
    );
    
    $plainBody  = "Hello $userName,\n\n";
    $plainBody .= "You requested a password reset for your LMS account.\n\n";
    $plainBody .= "Click the link below to reset your password:\n$resetUrl\n\n";
    $plainBody .= "This link will expire in 1 hour.\n\n";
    $plainBody .= "If you did not request this reset, please ignore this email.\n\n";
    $plainBody .= "— LMS Security Team";
    
    // Use the email service if available
    if (file_exists(__DIR__ . '/emailService.php')) {
        require_once __DIR__ . '/emailService.php';
        $htmlBody = buildHtmlEmail(
            'Password Reset Request',
            "Hello $userName,\n\nYou requested a password reset for your LMS account.\n\nThis link will expire in 1 hour.\n\nIf you did not request this reset, please ignore this email.\n\n— LMS Security Team",
            $resetUrl,
            'Reset My Password'
        );
        $result = sendLmsEmail($email, 'LMS - Password Reset Request', $plainBody, $userName, $htmlBody);
        return $result['success'];
    }
    
    // Raw fallback (should not be reached if emailService.php exists)
    $to = $email;
    $subject = 'LMS - Password Reset Request';
    $headers = "From: noreply@lms.local\r\nReply-To: noreply@lms.local\r\nX-Mailer: LMS/1.0";
    @mail($to, $subject, $plainBody, $headers);
    return true;
}

/* -------------------------------------------------------------------------
   OTP-based reset (Team Representatives)
   ------------------------------------------------------------------------- */

/**
 * Check whether a role may use the self-service password reset.
 */
function passwordResetRoleAllowed(string $role): bool
{
    return in_array((string) $role, PASSWORD_RESET_ALLOWED_ROLES, true);
}

/**
 * Find an active account eligible for the OTP reset by email.
 * The email is matched case-insensitively by the database collation.
 */
function findPasswordResetUser(mysqli $conn, string $email): array|false
{
    $email = normalizeVerificationEmail($email);
    if ($email === '') return false;

    $stmt = $conn->prepare(
        "SELECT id, fname, lname, email, role
         FROM tbl_user
         WHERE email = ? AND stat = '1'
         ORDER BY id ASC"
    );
    if (!$stmt) return false;

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = false;
    while ($row = $result->fetch_assoc()) {
        if (passwordResetRoleAllowed((string) $row['role'])) {
            $user = $row;
            break;
        }
    }
    $stmt->close();
    return $user;
}

/**
 * Load an active, eligible account by ID.
 */
function findPasswordResetUserById(mysqli $conn, int $userId): array|false
{
    if ($userId <= 0) return false;

    $stmt = $conn->prepare(
        "SELECT id, fname, lname, email, role, stat
         FROM tbl_user
         WHERE id = ?
         LIMIT 1"
    );
    if (!$stmt) return false;

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) return false;
    if ((string) ($user['stat'] ?? '') !== '1') return false;
    if (!passwordResetRoleAllowed((string) ($user['role'] ?? ''))) return false;

    return $user;
}

/**
 * Check the resend cooldown and hourly OTP rate limit for an email.
 *
 * @return array{allowed:bool,retry_after:int,reason:string}
 */
function canRequestPasswordResetOtp(mysqli $conn, string $email): array
{
    return canSendVerificationCode(
        $conn,
        normalizeVerificationEmail($email),
        null,
        PASSWORD_RESET_OTP_RESEND_COOLDOWN,
        PASSWORD_RESET_OTP_RATE_LIMIT
    );
}

/**
 * Create a new 6-digit reset code for the email.
 * Any previous active code for the address is invalidated.
 *
 * @return array{ok:bool,id?:int,code?:string,expires_in?:int,error?:string}
 */
function createPasswordResetOtp(mysqli $conn, string $email, string $ip = '', string $ua = ''): array
{
    return createEmailVerification(
        $conn,
        normalizeVerificationEmail($email),
        $ip,
        $ua,
        PASSWORD_RESET_OTP_EXPIRY
    );
}

/**
 * Build the password-reset OTP email content.
 *
 * @return array{subject:string,text:string,html:string}
 */
function buildPasswordResetOtpEmail(string $code, int $expiresIn = PASSWORD_RESET_OTP_EXPIRY): array
{
    $minutes = max(1, (int) round($expiresIn / 60));
    $subject = 'Your CSDP LMS password reset code';
    $text = "Hello,\n\n"
        . "Your password reset code is: {$code}\n\n"
        . "Enter this code on the password reset page to choose a new password. "
        . "The code expires in {$minutes} minutes.\n\n"
        . "If you did not request a password reset, you can safely ignore this email. "
        . "Your password will not change.\n\n"
        . "— CSDP League Management System";

    return [
        'subject' => $subject,
        'text'    => $text,
        'html'    => buildHtmlEmail('Reset your password', $text),
    ];
}

/**
 * Send the reset code, with optional local test/log helpers.
 *
 * @return array{success:bool,method?:string,error?:?string}
 */
function sendPasswordResetOtpEmail(string $email, string $code, string $name = ''): array
{
    $email = normalizeVerificationEmail($email);
    $built = buildPasswordResetOtpEmail($code);
    $recipient = $email;
    $subject = $built['subject'];

    $testTo = (string) (getenv('RESEND_TEST_TO') ?: '');
    if ($testTo !== '' && isValidVerificationEmail($testTo)) {
        $recipient = normalizeVerificationEmail($testTo);
        $subject = '[TEST -> ' . $email . '] ' . $subject;
    }

    if (getenv('PASSWORD_RESET_DEV_LOG') === '1' || getenv('EMAIL_VERIFICATION_DEV_LOG') === '1') {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
        @file_put_contents(
            $logDir . '/password_reset.log',
            '[' . date('Y-m-d H:i:s') . '] ' . maskVerificationEmail($email) . ' code=' . $code . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    $result = sendLmsEmail($recipient, $subject, $built['text'], $name, $built['html']);
    if (empty($result['success'])) {
        SecurityLogger::log(
            'SYSTEM',
            'Password reset code email failed for ' . maskVerificationEmail($email),
            ['error' => $result['error'] ?? ''],
            'WARNING'
        );
    } else {
        SecurityLogger::auth('Password Reset Code Sent', 'Code sent to ' . maskVerificationEmail($email));
    }
    return $result;
}

/**
 * Verify a submitted reset code and create the session grant.
 *
 * @return array{ok:bool,msg:string,reason:string,user_id?:int,remaining_attempts?:int}
 */
function verifyPasswordResetOtp(mysqli $conn, string $email, string $code): array
{
    $email = normalizeVerificationEmail($email);
    $result = verifyEmailVerificationCode($conn, $email, $code, PASSWORD_RESET_OTP_MAX_ATTEMPTS);

    if (empty($result['ok'])) {
        SecurityLogger::auth(
            'Password Reset OTP Failed',
            maskVerificationEmail($email) . ' — ' . ($result['reason'] ?? 'unknown')
        );
        return [
            'ok'                 => false,
            'msg'                => 'That code is invalid or has expired. Please check it and try again.',
            'reason'             => (string) ($result['reason'] ?? 'invalid'),
            'remaining_attempts' => $result['remaining_attempts'] ?? null,
        ];
    }

    // A code is single-use for this flow.
    consumeVerifiedEmail($conn, $email);

    $user = findPasswordResetUser($conn, $email);
    if (!$user) {
        SecurityLogger::auth(
            'Password Reset OTP Failed',
            'No eligible account for ' . maskVerificationEmail($email)
        );
        return [
            'ok'     => false,
            'msg'    => 'This account is not eligible for password reset.',
            'reason' => 'not_eligible',
        ];
    }

    markPasswordResetGrant((int) $user['id'], $email);
    SecurityLogger::auth('Password Reset OTP Verified', 'OTP verified for user_id=' . (int) $user['id']);

    return [
        'ok'      => true,
        'msg'     => 'Code verified. Choose a new password.',
        'reason'  => 'verified',
        'user_id' => (int) $user['id'],
    ];
}

/**
 * Store the short-lived proof that this browser verified the reset code.
 */
function markPasswordResetGrant(int $userId, string $email): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || $userId <= 0) return;

    $_SESSION['pwd_reset_grant'] = [
        'user_id'    => $userId,
        'email_hash' => hash('sha256', normalizeVerificationEmail($email)),
        'issued_at'  => time(),
        'expires_at' => time() + PASSWORD_RESET_GRANT_TTL,
    ];
}

/**
 * Return the active reset grant, or null when missing/expired.
 */
function getPasswordResetGrant(?int $userId = null): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) return null;

    $grant = $_SESSION['pwd_reset_grant'] ?? null;
    if (!is_array($grant)) return null;

    $grantUserId = (int) ($grant['user_id'] ?? 0);
    $emailHash   = (string) ($grant['email_hash'] ?? '');
    $expiresAt   = (int) ($grant['expires_at'] ?? 0);

    if ($grantUserId <= 0 || $emailHash === '' || $expiresAt < time()) {
        unset($_SESSION['pwd_reset_grant']);
        return null;
    }
    if ($userId !== null && $grantUserId !== $userId) return null;

    return $grant;
}

/** Clear only the grant (e.g. after it expires or is used). */
function clearPasswordResetGrant(): void
{
    unset($_SESSION['pwd_reset_grant']);
}

/** Clear every piece of the OTP reset flow from the session. */
function clearPasswordResetFlow(): void
{
    unset(
        $_SESSION['pwd_reset_pending_email'],
        $_SESSION['pwd_reset_otp_sent_at'],
        $_SESSION['pwd_reset_otp_expires_at'],
        $_SESSION['pwd_reset_grant']
    );
}

/**
 * Update a verified user's password and invalidate any outstanding link tokens.
 */
function completePasswordResetForUser(mysqli $conn, int $userId, string $newPasswordHash): bool
{
    if ($userId <= 0) return false;

    $user = findPasswordResetUserById($conn, $userId);
    if (!$user) return false;

    ensurePasswordResetTable($conn);
    $conn->begin_transaction();
    try {
        // Any emailed link tokens for this account are no longer valid.
        $stmt = $conn->prepare("UPDATE tbl_password_reset SET used = 1 WHERE user_id = ? AND used = 0");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE tbl_user SET pass = ?, pass_stat = 1 WHERE id = ?");
        $stmt->bind_param('si', $newPasswordHash, $userId);
        $stmt->execute();
        $updated = $stmt->affected_rows;
        $stmt->close();

        if ($updated < 1) {
            $conn->rollback();
            return false;
        }

        if (function_exists('recordPasswordChange')) {
            recordPasswordChange($conn, $userId, $newPasswordHash);
        }

        $conn->commit();
        SecurityLogger::auth('Password Reset Completed', "Password changed via OTP reset for user_id=$userId");
        return true;
    } catch (\Throwable $e) {
        $conn->rollback();
        SecurityLogger::log('SYSTEM', 'Password reset failed: ' . $e->getMessage(), [], 'CRITICAL');
        return false;
    }
}
