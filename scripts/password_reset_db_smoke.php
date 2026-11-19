<?php
/**
 * Password reset database smoke test (no email is sent).
 *
 * Usage: php scripts/password_reset_db_smoke.php
 *
 * Creates a temporary Team Representative account, runs OTP verification and
 * the password update, then removes the account and its verification rows.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/dbconnect.php';
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/passwordReset.php';
require_once __DIR__ . '/../config/passwordSecurity.php';

$failures = 0;

function dbCheck(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    if (!$ok) $failures++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . ($detail !== '' ? ' - ' . $detail : '') . PHP_EOL;
}

$email = 'reset-smoke-' . time() . '@example.invalid';
$oldPassword = 'OldPass123!';
$newPassword = 'NewPass456!';
$userId = 0;

try {
    $oldHash = password_hash($oldPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        "INSERT INTO tbl_user (res_id, fname, lname, mname, suffix, phone, email, pass, role, stat, pass_stat)
         VALUES ('', 'Reset', 'Smoke', '', '', '00000000009', ?, ?, '2', '1', 1)"
    );
    $stmt->bind_param('ss', $email, $oldHash);
    dbCheck('Create temporary Team Representative', $stmt->execute());
    $userId = (int) $conn->insert_id;
    $stmt->close();
    dbCheck('Temporary user ID', $userId > 0, 'id=' . $userId);

    $user = findPasswordResetUser($conn, $email);
    dbCheck('Eligible Team Representative found', is_array($user) && (int) $user['id'] === $userId);

    $created = createPasswordResetOtp($conn, $email, '127.0.0.1', 'smoke-test');
    dbCheck('OTP created', !empty($created['ok']) && !empty($created['code']), $created['error'] ?? '');

    $code = (string) ($created['code'] ?? '');
    $verified = verifyPasswordResetOtp($conn, $email, $code);
    dbCheck('OTP verified', !empty($verified['ok']), $verified['msg'] ?? '');
    $grant = getPasswordResetGrant();
    dbCheck('Reset grant created', is_array($grant) && (int) $grant['user_id'] === $userId);

    $reset = completePasswordResetForUser($conn, $userId, password_hash($newPassword, PASSWORD_DEFAULT));
    dbCheck('Password updated', $reset);

    $stmt = $conn->prepare("SELECT pass FROM tbl_user WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    dbCheck('New password verifies', $row && password_verify($newPassword, (string) $row['pass']));
    dbCheck('Old password no longer verifies', $row && !password_verify($oldPassword, (string) $row['pass']));
} catch (Throwable $e) {
    dbCheck('Unhandled error', false, $e->getMessage());
} finally {
    clearPasswordResetFlow();

    if ($userId > 0) {
        $stmt = $conn->prepare("DELETE FROM tbl_email_verification WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("DELETE FROM tbl_password_reset WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }

        $historyTable = $conn->query("SHOW TABLES LIKE 'tbl_password_history'");
        if ($historyTable && $historyTable->num_rows > 0) {
            $stmt = $conn->prepare("DELETE FROM tbl_password_history WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();
            }
        }

        $stmt = $conn->prepare("DELETE FROM tbl_user WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo PHP_EOL . ($failures === 0 ? 'All password-reset database checks passed.' : $failures . ' check(s) failed.') . PHP_EOL;
}

exit($failures === 0 ? 0 : 1);
