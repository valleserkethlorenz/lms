<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
requireRole([3]);
requireJsonCsrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    $name = currentUserName();
    auditLog($conn, 'Logout', "$name logged out");
    session_destroy();
    echo json_encode(['status' => 201, 'msg' => 'Logged out']);
    exit;
}
