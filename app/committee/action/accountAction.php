<?php
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../config/passwordSecurity.php';
require_once __DIR__ . '/../../../config/securityLogger.php';
require_once __DIR__ . '/../../../config/apiSecurity.php';
require_once __DIR__ . '/../../../includes/profileImage.php';
requireJsonRole([4]);
requireActiveAccount($conn, true);
enforceRequestSizeLimit(3 * 1024 * 1024); // profile photo (2MB) + form fields
addApiSecurityHeaders();
requireJsonCsrf();

$userId = currentUserId();
$projectRoot = dirname(__DIR__, 3);

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

// Current photo, so a replacement can clean up the previous file afterwards.
$currentPhoto = null;
$photoStmt = $conn->prepare("SELECT profile_image FROM tbl_user WHERE id=?");
$photoStmt->bind_param('i', $userId);
$photoStmt->execute();
$photoStmt->bind_result($currentPhoto);
$photoStmt->fetch();
$photoStmt->close();
$currentPhoto = (string)($currentPhoto ?? '');

// Validate the password first: once a photo is stored every later exit must
// either save it or delete it.
$passwordChanged = false;
$hashed = null;

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
    $passwordChanged = true;
}

$removePhoto = (string)($_POST['remove_profile_image'] ?? '') === '1';
$uploadsPhoto = isset($_FILES['profile_image']) && is_array($_FILES['profile_image'])
    && (int)($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

$newPhoto = null;
$photoChanged = false;

if ($uploadsPhoto) {
    try {
        $newPhoto = profileImageStoreUpload($_FILES['profile_image'], (int)$userId, $projectRoot);
        $photoChanged = true;
    } catch (Throwable $e) {
        echo json_encode(['status' => 400, 'msg' => $e->getMessage()]);
        exit;
    }
} elseif ($removePhoto && $currentPhoto !== '') {
    $photoChanged = true;
}

$finalPhoto = $photoChanged ? $newPhoto : $currentPhoto;

if ($passwordChanged) {
    $stmt = $conn->prepare("UPDATE tbl_user SET fname=?, lname=?, mname=?, suffix=?, email=?, phone=?, pass=?, profile_image=? WHERE id=?");
    $stmt->bind_param('ssssssssi', $fname, $lname, $mname, $suffix, $email, $phone, $hashed, $finalPhoto, $userId);
} else {
    $stmt = $conn->prepare("UPDATE tbl_user SET fname=?, lname=?, mname=?, suffix=?, email=?, phone=?, profile_image=? WHERE id=?");
    $stmt->bind_param('sssssssi', $fname, $lname, $mname, $suffix, $email, $phone, $finalPhoto, $userId);
}

if ($stmt->execute()) {
    // Remove the replaced/removed photo once the database is updated.
    if ($photoChanged && $currentPhoto !== '' && $currentPhoto !== (string)$finalPhoto) {
        profileImageDeleteStored($currentPhoto, $projectRoot);
    }
    if ($passwordChanged) {
        recordPasswordChange($conn, (int)$userId, $hashed);
        SecurityLogger::auth('Password Changed', "Committee user ID $userId changed their password");
    }
    // Update session username
    $_SESSION['lms_username'] = $fname . ' ' . $lname;
    SecurityLogger::auth('Account Updated', "Committee user ID $userId updated account information");
    echo json_encode([
        'status' => 200,
        'msg' => 'Account updated successfully.',
        'profile_image' => profileImageWebPath($finalPhoto),
    ]);
} else {
    // The database rejected the save: drop the new file so it does not orphan.
    if ($photoChanged && $newPhoto !== null) {
        profileImageDeleteStored($newPhoto, $projectRoot);
    }
    echo json_encode(['status' => 500, 'msg' => 'Failed to update account.']);
}
$stmt->close();
