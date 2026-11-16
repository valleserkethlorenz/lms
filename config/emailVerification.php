<?php
/**
 * Email Verification Helper
 *
 * Implements the 6-digit code flow used by the registration page:
 *   Step 1  request a code for an email address
 *   Step 2  submit the code and receive a short-lived session proof
 *   Step 3  the account is created only when that proof is present
 *
 * Requires: dbconnect.php, securityLogger.php, emailService.php
 */

require_once __DIR__ . '/securityLogger.php';
require_once __DIR__ . '/emailService.php';

/** Code validity duration in seconds (10 minutes). */
const EMAIL_VERIFICATION_EXPIRY = 600;

/** Minimum seconds between two code requests for the same email. */
const EMAIL_VERIFICATION_RESEND_COOLDOWN = 60;

/** Maximum wrong-code attempts before the code is burned. */
const EMAIL_VERIFICATION_MAX_ATTEMPTS = 5;

/** Maximum code requests per email per hour. */
const EMAIL_VERIFICATION_RATE_LIMIT = 3;

/** How long a browser may use the "email verified" proof (30 minutes). */
const EMAIL_VERIFICATION_PROOF_TTL = 1800;

/**
 * Ensure the email verification table exists.
 * Creates it if missing (auto-provisioning, mirrors passwordReset.php).
 */
function ensureEmailVerificationTable(mysqli $conn): bool
{
    $check = $conn->query("SHOW TABLES LIKE 'tbl_email_verification'");
    if ($check && $check->num_rows > 0) return true;

    $sql = "CREATE TABLE IF NOT EXISTS tbl_email_verification (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        verified TINYINT(1) NOT NULL DEFAULT 0,
        verified_at DATETIME DEFAULT NULL,
        consumed TINYINT(1) NOT NULL DEFAULT 0,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_verification_email (email),
        INDEX idx_email_verification_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    return (bool) $conn->query($sql);
}

/** Normalize an email address for storage and lookups. */
function normalizeVerificationEmail(string $email): string
{
    return strtolower(trim($email));
}

/** Validate an email address. */
function isValidVerificationEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** A verification code is exactly six digits. */
function isValidVerificationCode(string $code): bool
{
    return preg_match('/^\d{6}$/', $code) === 1;
}

/** Generate a cryptographically-random 6-digit code. */
function generateVerificationCode(): string
{
    return (string) random_int(100000, 999999);
}

/** Mask an email for security logs. */
function maskVerificationEmail(string $email): string
{
    $email = normalizeVerificationEmail($email);
    $at = strpos($email, '@');
    if ($at === false) return '***';
    $local = substr($email, 0, $at);
    $domain = substr($email, $at + 1);
    return substr($local, 0, 2) . '***@' . $domain;
}

/**
 * Check whether a new code may be sent for this email right now.
 *
 * @return array{allowed:bool,retry_after:int,reason:string}
 */
function canSendVerificationCode(mysqli $conn, string $email, ?int $now = null): array
{
    ensureEmailVerificationTable($conn);
    $email = normalizeVerificationEmail($email);
    $now = $now ?? time();

    $stmt = $conn->prepare(
        "SELECT created_at FROM tbl_email_verification WHERE email = ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $last = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($last && !empty($last['created_at'])) {
        $lastTs = strtotime((string) $last['created_at']);
        if ($lastTs !== false) {
            $retry = ($lastTs + EMAIL_VERIFICATION_RESEND_COOLDOWN) - $now;
            if ($retry > 0) {
                return ['allowed' => false, 'retry_after' => $retry, 'reason' => 'cooldown'];
            }
        }
    }

    $since = date('Y-m-d H:i:s', $now - 3600);
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt, MIN(created_at) AS oldest
         FROM tbl_email_verification
         WHERE email = ? AND created_at >= ?"
    );
    $stmt->bind_param('ss', $email, $since);
    $stmt->execute();
    $window = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int) ($window['cnt'] ?? 0) >= EMAIL_VERIFICATION_RATE_LIMIT) {
        $oldestTs = strtotime((string) ($window['oldest'] ?? ''));
        $retry = $oldestTs !== false ? max(1, ($oldestTs + 3600) - $now) : 3600;
        return ['allowed' => false, 'retry_after' => $retry, 'reason' => 'rate_limit'];
    }

    return ['allowed' => true, 'retry_after' => 0, 'reason' => ''];
}

/**
 * Create a new code for the email and invalidate any previous active codes.
 *
 * @return array{ok:bool,id?:int,code?:string,expires_in?:int,error?:string}
 */
function createEmailVerification(mysqli $conn, string $email, string $ip = '', string $ua = ''): array
{
    ensureEmailVerificationTable($conn);
    $email = normalizeVerificationEmail($email);

    $code = generateVerificationCode();
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + EMAIL_VERIFICATION_EXPIRY);
    $ip = substr($ip, 0, 45);
    $ua = mb_substr($ua, 0, 500);

    // One active code per email: burn earlier unused codes.
    $stmt = $conn->prepare("UPDATE tbl_email_verification SET consumed = 1 WHERE email = ? AND consumed = 0");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        "INSERT INTO tbl_email_verification (email, code_hash, expires_at, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssss', $email, $hash, $expiresAt, $ip, $ua);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        SecurityLogger::log('SYSTEM', 'Could not store email verification code for ' . maskVerificationEmail($email), ['error' => $error], 'WARNING');
        return ['ok' => false, 'error' => 'Could not create a verification code.'];
    }
    $id = (int) $conn->insert_id;
    $stmt->close();

    return [
        'ok'         => true,
        'id'         => $id,
        'code'       => $code,
        'expires_in' => EMAIL_VERIFICATION_EXPIRY,
    ];
}

/**
 * Verify a submitted code.
 *
 * @return array{ok:bool,msg:string,reason:string,remaining_attempts?:int}
 */
function verifyEmailVerificationCode(mysqli $conn, string $email, string $code): array
{
    ensureEmailVerificationTable($conn);
    $email = normalizeVerificationEmail($email);
    $code = preg_replace('/\D/', '', $code);

    $stmt = $conn->prepare(
        "SELECT id, code_hash, expires_at, attempts
         FROM tbl_email_verification
         WHERE email = ? AND consumed = 0
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'msg' => 'Send a verification code first.', 'reason' => 'no_code'];
    }

    $rowId = (int) $row['id'];
    $expiresTs = strtotime((string) $row['expires_at']);
    if ($expiresTs === false || $expiresTs < time()) {
        $burn = $conn->prepare("UPDATE tbl_email_verification SET consumed = 1 WHERE id = ?");
        $burn->bind_param('i', $rowId);
        $burn->execute();
        $burn->close();
        return ['ok' => false, 'msg' => 'That code has expired. Send a new one.', 'reason' => 'expired'];
    }

    $attempts = (int) $row['attempts'];
    if ($attempts >= EMAIL_VERIFICATION_MAX_ATTEMPTS) {
        $burn = $conn->prepare("UPDATE tbl_email_verification SET consumed = 1 WHERE id = ?");
        $burn->bind_param('i', $rowId);
        $burn->execute();
        $burn->close();
        return ['ok' => false, 'msg' => 'Too many incorrect attempts. Send a new code.', 'reason' => 'too_many'];
    }

    if (!password_verify($code, (string) $row['code_hash'])) {
        $attempts++;
        if ($attempts >= EMAIL_VERIFICATION_MAX_ATTEMPTS) {
            $upd = $conn->prepare("UPDATE tbl_email_verification SET attempts = ?, consumed = 1 WHERE id = ?");
            $upd->bind_param('ii', $attempts, $rowId);
            $upd->execute();
            $upd->close();
            return ['ok' => false, 'msg' => 'Too many incorrect attempts. Send a new code.', 'reason' => 'too_many', 'remaining_attempts' => 0];
        }
        $upd = $conn->prepare("UPDATE tbl_email_verification SET attempts = ? WHERE id = ?");
        $upd->bind_param('ii', $attempts, $rowId);
        $upd->execute();
        $upd->close();
        return [
            'ok'                 => false,
            'msg'                => 'That code is incorrect. Please check it and try again.',
            'reason'             => 'invalid',
            'remaining_attempts' => max(0, EMAIL_VERIFICATION_MAX_ATTEMPTS - $attempts),
        ];
    }

    $upd = $conn->prepare("UPDATE tbl_email_verification SET verified = 1, verified_at = NOW() WHERE id = ?");
    $upd->bind_param('i', $rowId);
    $upd->execute();
    $upd->close();

    return ['ok' => true, 'msg' => 'Email verified.', 'reason' => 'verified'];
}

/** Mark a verified code as used after the account is created. */
function consumeVerifiedEmail(mysqli $conn, string $email): bool
{
    ensureEmailVerificationTable($conn);
    $email = normalizeVerificationEmail($email);
    $stmt = $conn->prepare("UPDATE tbl_email_verification SET consumed = 1 WHERE email = ? AND verified = 1 AND consumed = 0");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $consumed = $conn->affected_rows > 0;
    $stmt->close();
    return $consumed;
}

/** Invalidate any active codes for an email (for example after a failed send). */
function invalidateEmailVerifications(mysqli $conn, string $email): void
{
    ensureEmailVerificationTable($conn);
    $email = normalizeVerificationEmail($email);
    $stmt = $conn->prepare("UPDATE tbl_email_verification SET consumed = 1 WHERE email = ? AND consumed = 0");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
}

/** Delete expired verification rows. */
function cleanupExpiredEmailVerifications(mysqli $conn, int $olderThanDays = 1): void
{
    ensureEmailVerificationTable($conn);
    $cutoff = date('Y-m-d H:i:s', time() - max(0, $olderThanDays) * 86400);
    $stmt = $conn->prepare("DELETE FROM tbl_email_verification WHERE expires_at < ?");
    $stmt->bind_param('s', $cutoff);
    $stmt->execute();
    $stmt->close();
}

/** Store the short-lived proof that this browser verified the email. */
function markEmailVerifiedInSession(string $email): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $_SESSION['email_verification'] = [
        'email_hash' => hash('sha256', normalizeVerificationEmail($email)),
        'issued_at'  => time(),
        'expires_at' => time() + EMAIL_VERIFICATION_PROOF_TTL,
    ];
}

/** Check the session proof for the given email. */
function isEmailVerifiedInSession(string $email): bool
{
    $proof = $_SESSION['email_verification'] ?? null;
    if (!is_array($proof)) return false;
    if ((int) ($proof['expires_at'] ?? 0) < time()) {
        unset($_SESSION['email_verification']);
        return false;
    }
    $expected = hash('sha256', normalizeVerificationEmail($email));
    return isset($proof['email_hash'])
        && is_string($proof['email_hash'])
        && hash_equals($proof['email_hash'], $expected);
}

/** Clear the proof after the account is created or the email changes. */
function clearEmailVerificationSession(): void
{
    unset($_SESSION['email_verification']);
}

/** Build the verification email content. */
function buildVerificationEmail(string $code, int $expiresIn = EMAIL_VERIFICATION_EXPIRY): array
{
    $minutes = max(1, (int) round($expiresIn / 60));
    $subject = 'Your CSDP LMS verification code';
    $text = "Hello,\n\nYour verification code is: {$code}\n\nEnter this code on the registration page to verify your email address. The code expires in {$minutes} minutes.\n\nIf you did not request this code, you can safely ignore this email.\n\n— CSDP League Management System";
    return [
        'subject' => $subject,
        'text'    => $text,
        'html'    => buildHtmlEmail('Verify your email', $text),
    ];
}

/** Send the verification code, with optional local test/log helpers. */
function sendVerificationCodeEmail(string $email, string $code, string $name = ''): array
{
    $email = normalizeVerificationEmail($email);
    $built = buildVerificationEmail($code);
    $recipient = $email;
    $subject = $built['subject'];

    $testTo = (string) (getenv('RESEND_TEST_TO') ?: '');
    if ($testTo !== '' && isValidVerificationEmail($testTo)) {
        $recipient = normalizeVerificationEmail($testTo);
        $subject = '[TEST -> ' . $email . '] ' . $subject;
    }

    if (getenv('EMAIL_VERIFICATION_DEV_LOG') === '1') {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
        @file_put_contents(
            $logDir . '/email_verification.log',
            '[' . date('Y-m-d H:i:s') . '] ' . maskVerificationEmail($email) . ' code=' . $code . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    $result = sendLmsEmail($recipient, $subject, $built['text'], $name, $built['html']);
    if (empty($result['success'])) {
        SecurityLogger::log('SYSTEM', 'Verification email failed for ' . maskVerificationEmail($email), ['error' => $result['error'] ?? ''], 'WARNING');
    }
    return $result;
}
