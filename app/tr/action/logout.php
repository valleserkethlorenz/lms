<?php
// ===== USE THE SAME SESSION NAME AS LOGIN =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== CLEAR SESSION VARIABLES =====
$_SESSION = [];

// ===== DELETE SESSION COOKIE =====
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), // cookie name ('slt')
        '',             // empty value
        time() - 42000, // past timestamp to expire
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// ===== DESTROY SESSION =====
session_destroy();

// ===== RETURN JSON RESPONSE =====
header('Content-Type: application/json');
echo json_encode([
    'status' => 201,
    'msg' => 'You have been logged out successfully.'
]);
exit;
