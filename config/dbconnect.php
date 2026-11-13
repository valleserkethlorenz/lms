<?php
date_default_timezone_set('Asia/Manila');

// Initialize error handler to prevent information disclosure
if (file_exists(__DIR__ . '/errorHandler.php')) {
    require_once __DIR__ . '/errorHandler.php';
    initializeErrorHandler();
}

$server = "localhost";
$username = "root";
$password = "";
$database = "lms";

/** @var mysqli $conn */
$conn = mysqli_connect($server, $username, $password, $database);
if (!$conn) {
    error_log('[DB] Connection failed: ' . mysqli_connect_error());
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['status' => 503, 'msg' => 'The service is temporarily unavailable. Please try again later.']);
    exit;
}

?>
