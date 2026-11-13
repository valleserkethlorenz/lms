<?php
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../config/passwordSecurity.php';
require_once __DIR__ . '/../../../config/securityLogger.php';
require_once __DIR__ . '/../../../config/apiSecurity.php';
requireJsonRole([2]);
requireActiveAccount($conn, true);
enforceRequestSizeLimit(1 * 1024 * 1024); // 1MB
addApiSecurityHeaders();
requireJsonCsrf();

$userId = currentUserId();

$fname   = trim($_POST['fname'] ?? '');
$lname   = trim($_POST['lname'] ?? '');
$mname   = trim($_POST['mname'] ?? '');
$suffix  = trim($_POST['suffix'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$newPass = $_POST['new_password'] ?? '';
$confPass = $_POST['confirm_password'] ?? '';

if (!$fname || !$lname || !$email || !$phone) {
    echo json_encode(['status' => 400, 'msg' => 'Please fill in all required fields.']);
    exit;
}

if ($newPass) {
    if ($newPass !== $confPass) {
        echo json_encode(['status' => 400, 'msg' => 'Passwords do not match.']);
        exit;
    }
    // Use enhanced password validation
    $pwdCheck = validatePasswordEnhanced($conn, (int)$userId, $newPass);
    if (!$pwdCheck['valid']) {
        echo json_encode(['status' => 400, 'msg' => $pwdCheck['msg']]);
        exit;
    }
    $hashed = password_hash($newPass, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE tbl_user SET fname=?, lname=?, mname=?, suffix=?, email=?, phone=?, pass=? WHERE id=?");
    $stmt->bind_param('sssssssi', $fname, $lname, $mname, $suffix, $email, $phone, $hashed, $userId);
    if ($stmt->execute()) {
        recordPasswordChange($conn, (int)$userId, $hashed);
        SecurityLogger::auth('Password Changed', "TR user ID $userId changed their password");
    }
} else {
    $stmt = $conn->prepare("UPDATE tbl_user SET fname=?, lname=?, mname=?, suffix=?, email=?, phone=? WHERE id=?");
    $stmt->bind_param('ssssssi', $fname, $lname, $mname, $suffix, $email, $phone, $userId);
}

if ($stmt->execute()) {
    // Update session username
    $_SESSION['lms_username'] = $fname . ' ' . $lname;
    SecurityLogger::auth('Account Updated', "TR user ID $userId updated account information");
    echo json_encode(['status' => 200, 'msg' => 'Account updated successfully.']);
} else {
    echo json_encode(['status' => 500, 'msg' => 'Failed to update account.']);
}
$stmt->close();
