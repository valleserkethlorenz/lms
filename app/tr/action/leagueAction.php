<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../config/apiSecurity.php';
require_once __DIR__ . '/../../../config/inputValidator.php';
requireJsonRole([2]);
requireActiveAccount($conn, true);
requireJsonCsrf();
enforceRequestSizeLimit(10 * 1024 * 1024); // 10MB (allows document uploads)
addApiSecurityHeaders();
date_default_timezone_set('Asia/Manila');

$created = date("Y-m-d H:i:s");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = '';


    if (isset($_POST['regisLeague'])) {
        http_response_code(403);
        echo json_encode(['status'=>403,'msg'=>'Only Organizers may create leagues.']);
        exit;
    }
}