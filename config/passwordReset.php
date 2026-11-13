<?php
/**
 * Password Reset Helper
 * Provides token generation, validation, and reset functionality.
 * 
 * Requires: dbconnect.php, securityLogger.php, rateLimiter.php, auth_guard.php
 */

require_once __DIR__ . '/securityLogger.php';

/** Token validity duration in seconds (1 hour) */
const PASSWORD_RESET_EXPIRY = 3600;

/** Maximum reset requests per email per hour */
const PASSWORD_RESET_RATE_LIMIT = 3;

/** Minimum token length in bytes (before hex encoding) */
const PASSWORD_RESET_TOKEN_BYTES = 32;

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
