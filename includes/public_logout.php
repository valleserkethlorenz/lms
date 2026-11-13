<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('LMS_SESSION');
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$submittedToken = (string)($_POST['csrf_token'] ?? '');
$expectedToken = (string)($_SESSION['public_logout_token'] ?? '');
if ($submittedToken === '' || $expectedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
    http_response_code(419);
    exit('Your session expired. Return to the previous page and try again.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: ../index.php');
exit;
