<?php
/**
 * Password Security Helper
 * Provides password history checking, breach detection, and security utilities.
 */

/**
 * Check if a password was recently used (password history).
 * Returns true if the password is NEW (not in history), false if reused.
 */
function checkPasswordHistory(mysqli $conn, int $userId, string $newPassword): bool
{
    $checkTable = $conn->query("SHOW TABLES LIKE 'tbl_password_history'");
    if (!$checkTable || $checkTable->num_rows === 0) return true;
    
    $stmt = $conn->prepare("SELECT password_hash FROM tbl_password_history WHERE user_id = ? ORDER BY changed_at DESC LIMIT 5");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (password_verify($newPassword, $row['password_hash'])) {
            $stmt->close();
            return false;
        }
    }
    $stmt->close();
    return true;
}

/**
 * Record a password change in history.
 */
function recordPasswordChange(mysqli $conn, int $userId, string $newPasswordHash): void
{
    $checkTable = $conn->query("SHOW TABLES LIKE 'tbl_password_history'");
    if (!$checkTable || $checkTable->num_rows === 0) return;
    
    $stmt = $conn->prepare("INSERT INTO tbl_password_history (user_id, password_hash, changed_at) VALUES (?, ?, NOW())");
    $stmt->bind_param('is', $userId, $newPasswordHash);
    $stmt->execute();
    $stmt->close();
}

/**
 * Check password against common weak passwords list.
 */
function checkCommonPasswords(string $password): bool
{
    $commonPasswords = [
        'password', 'password1', 'password123', '12345678', '123456789',
        'qwerty123', 'letmein1', 'welcome1', 'admin123', 'monkey123',
        'dragon12', 'master12', 'abc12345', 'mustang1', 'access14',
        'shadow12', 'michael1', 'football1', 'baseball1', 'superman1',
        'trustno1', 'passw0rd', 'p@ssword', 'p@ssw0rd', 'changeme1',
        'iloveyou1', 'sunshine1', 'princess1', 'qwertyui', 'asdfghjk',
    ];
    $lowerPassword = strtolower($password);
    foreach ($commonPasswords as $common) {
        if ($lowerPassword === $common || $lowerPassword === strtolower($common)) return false;
    }
    if (preg_match('/^(0123456789|1234567890|abcdefgh|qwertyuiop|asdfghjkl)/', $lowerPassword)) return false;
    if (preg_match('/^(.)\1{7,}$/', $password)) return false;
    return true;
}

/**
 * Enhanced password validation with all security checks.
 */
function validatePasswordEnhanced(mysqli $conn, int $userId, string $password): array
{
    $basic = validatePasswordComplexity($password);
    if (!$basic['valid']) return $basic;
    if (!checkCommonPasswords($password)) {
        return ['valid' => false, 'msg' => 'This password is too common. Please choose a more unique password.'];
    }
    if ($userId > 0 && !checkPasswordHistory($conn, $userId, $password)) {
        return ['valid' => false, 'msg' => 'This password was recently used. Please choose a different password.'];
    }
    return ['valid' => true];
}
