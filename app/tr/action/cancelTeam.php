<?php
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
requireJsonRole([2]);
requireActiveAccount($conn, true);
/** @var mysqli $conn */

// Validate CSRF from JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'msg' => 'Invalid JSON input']);
    exit;
}
$submittedCsrf = (string)($input['csrf_token'] ?? '');
$expectedCsrf = $_SESSION['csrf_token'] ?? '';
if (!is_string($expectedCsrf) || $expectedCsrf === '' || $submittedCsrf === '' || !hash_equals($expectedCsrf, $submittedCsrf)) {
    http_response_code(419);
    echo json_encode(['status' => 419, 'msg' => 'Your form session expired. Refresh the page and try again.']);
    exit;
}

$userId = currentUserId();

$teamId = intval($input['team_id'] ?? 0);

if (!$teamId) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'msg' => 'Invalid team ID']);
    exit;
}

// Verify team belongs to logged-in user
$verify = "SELECT id, status FROM tbl_team WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $verify);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 500, 'msg' => 'Database error']);
    exit;
}

mysqli_stmt_bind_param($stmt, 'ii', $teamId, $userId);
if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    echo json_encode(['status' => 500, 'msg' => 'Query failed']);
    mysqli_stmt_close($stmt);
    exit;
}

$result = mysqli_stmt_get_result($stmt);
$team = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$team) {
    http_response_code(404);
    echo json_encode(['status' => 404, 'msg' => 'Team not found']);
    exit;
}

// Check if status is pending (case-insensitive)
if (strtolower($team['status']) !== 'pending') {
    http_response_code(400);
    echo json_encode(['status' => 400, 'msg' => 'Only pending registrations can be cancelled. Current status: ' . $team['status']]);
    exit;
}

// Update team status to 'Cancelled'
$update = "UPDATE tbl_team SET status = 'Cancelled' WHERE id = ?";
$ustmt = mysqli_prepare($conn, $update);

if (!$ustmt) {
    http_response_code(500);
    echo json_encode(['status' => 500, 'msg' => 'Update failed']);
    exit;
}

mysqli_stmt_bind_param($ustmt, 'i', $teamId);

if (mysqli_stmt_execute($ustmt)) {
    mysqli_stmt_close($ustmt);
    http_response_code(200);
    echo json_encode(['status' => 200, 'msg' => 'Registration cancelled successfully']);
} else {
    mysqli_stmt_close($ustmt);
    http_response_code(500);
    echo json_encode(['status' => 500, 'msg' => 'Failed to update status']);
}
