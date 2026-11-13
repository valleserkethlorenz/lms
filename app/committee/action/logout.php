<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['logout'])) {
    http_response_code(405);
    echo json_encode(['status' => 405, 'msg' => 'Method not allowed.']);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
requireJsonCsrf();

if (!empty($_SESSION['lms_id'])) {
    auditLog($conn, 'Logout', currentUserName() . ' logged out');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

echo json_encode(['status' => 201, 'msg' => 'You have been logged out successfully.']);
exit;
