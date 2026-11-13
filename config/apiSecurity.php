<?php
/**
 * API Security Middleware
 * Provides security functions for JSON API endpoints.
 * 
 * Usage: require_once __DIR__ . '/apiSecurity.php';
 *        enforceJsonRequest();
 *        enforcePostMethod();
 */

/**
 * Enforce that the request is a POST request.
 */
function enforcePostMethod(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: application/json');
        echo json_encode(['status' => 405, 'msg' => 'Method not allowed. Use POST.']);
        exit;
    }
}

/**
 * Enforce that the request accepts JSON.
 */
function enforceJsonRequest(): void
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    
    $isJson = stripos($accept, 'application/json') !== false
        || stripos($contentType, 'application/json') !== false
        || stripos($contentType, 'multipart/form-data') !== false
        || $requestedWith === 'XMLHttpRequest';
    
    if (!$isJson) {
        http_response_code(406);
        header('Content-Type: application/json');
        echo json_encode(['status' => 406, 'msg' => 'This endpoint only accepts JSON or form-data requests.']);
        exit;
    }
}

/**
 * Send a JSON response and exit.
 */
function jsonResponse(int $status, string $message, array $extra = []): void
{
    if ($status >= 400) {
        http_response_code($status);
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'msg' => $message], $extra));
    exit;
}

/**
 * Validate and sanitize a JSON input value.
 */
function getJsonInput(string $key, string $type = 'string', $default = null)
{
    $value = $_POST[$key] ?? $default;
    
    if ($value === null) {
        return $default;
    }
    
    switch ($type) {
        case 'int':
        case 'integer':
            return filter_var($value, FILTER_VALIDATE_INT);
        case 'float':
        case 'double':
            return filter_var($value, FILTER_VALIDATE_FLOAT);
        case 'email':
            return filter_var($value, FILTER_VALIDATE_EMAIL);
        case 'url':
            return filter_var($value, FILTER_VALIDATE_URL);
        case 'bool':
        case 'boolean':
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        case 'string':
        default:
            return is_string($value) ? trim($value) : (string)$value;
    }
}

/**
 * Enforce request size limit.
 */
function enforceRequestSizeLimit(int $maxBytes = 10485760): void
{
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $maxBytes) {
        http_response_code(413);
        header('Content-Type: application/json');
        echo json_encode(['status' => 413, 'msg' => 'Request too large.']);
        exit;
    }
}

/**
 * Validate that required fields are present.
 */
function requireFields(array $fields): void
{
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($_POST[$field]) || (is_string($_POST[$field]) && trim($_POST[$field]) === '')) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        jsonResponse(400, 'Missing required fields: ' . implode(', ', $missing));
    }
}

/**
 * Add security headers for JSON API responses.
 */
function addApiSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}
