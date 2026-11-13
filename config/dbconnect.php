<?php
date_default_timezone_set('Asia/Manila');

// Initialize error handler to prevent information disclosure
if (file_exists(__DIR__ . '/errorHandler.php')) {
    require_once __DIR__ . '/errorHandler.php';
    initializeErrorHandler();
}

$jawsdbUrl = getenv('JAWSDB_URL');

if ($jawsdbUrl) {
    // Production / Heroku JawsDB connection
    $dbparts = parse_url($jawsdbUrl);
    $server = $dbparts['host'];
    $username = $dbparts['user'];
    $password = $dbparts['pass'] ?? '';
    $database = ltrim($dbparts['path'], '/');
    $port = $dbparts['port'] ?? 3306;
} else {
    // Local XAMPP connection
    $server = "localhost";
    $username = "root";
    $password = "";
    $database = "lms";
    $port = 3306;
}

/** @var mysqli $conn */
$conn = mysqli_connect($server, $username, $password, $database, $port);
if (!$conn) {
    error_log('[DB] Connection failed: ' . mysqli_connect_error());
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['status' => 503, 'msg' => 'The service is temporarily unavailable. Please try again later.']);
    exit;
}

// Ensure UTF-8 encoding and Philippine timezone on MySQL session
mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "SET time_zone = '+08:00'");
?>
