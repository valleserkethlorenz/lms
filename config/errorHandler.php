<?php
/**
 * Error Handler - Prevents information disclosure
 * 
 * Usage: require_once __DIR__ . '/errorHandler.php';
 *        initializeErrorHandler();
 * 
 * This file:
 * - Disables display_errors in production
 * - Logs errors securely without exposing internals
 * - Provides user-friendly error messages
 * - Catches fatal errors gracefully
 */

/**
 * Initialize the secure error handler.
 */
function initializeErrorHandler(): void
{
    // Disable displaying errors to users (prevents information disclosure)
    ini_set('display_errors', '0');
    
    // Enable error logging to file
    ini_set('log_errors', '1');
    
    // Set error log path (outside webroot if possible)
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    ini_set('error_log', $logDir . '/php_errors.log');
    
    // Set error reporting level (report all, but don't display)
    error_reporting(E_ALL);
    
    // Disable exposing PHP version in headers
    ini_set('expose_php', '0');
    
    // Register custom error handler
    set_error_handler('secureErrorHandler');
    
    // Register shutdown function for fatal errors
    register_shutdown_function('secureShutdownHandler');
    
    // Set custom exception handler
    set_exception_handler('secureExceptionHandler');
}

/**
 * Custom error handler that logs securely.
 */
function secureErrorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
{
    // Don't handle errors suppressed with @
    if (error_reporting() === 0) {
        return false;
    }
    
    $errorTypes = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_NOTICE => 'Notice',
        E_DEPRECATED => 'Deprecated',
        E_STRICT => 'Strict Standards',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_USER_DEPRECATED => 'User Deprecated',
    ];
    
    $type = $errorTypes[$errno] ?? 'Unknown Error';
    
    // Log the error with full details (internal only)
    error_log(sprintf(
        '[%s] %s: %s in %s on line %d',
        date('Y-m-d H:i:s'),
        $type,
        $errstr,
        basename($errfile),
        $errline
    ));
    
    // Return true to prevent PHP's internal error handler
    return true;
}

/**
 * Handle uncaught exceptions.
 */
function secureExceptionHandler(Throwable $exception): void
{
    // Log the full exception details internally
    error_log(sprintf(
        '[%s] Uncaught %s: %s in %s:%d' . PHP_EOL . 'Stack trace: %s',
        date('Y-m-d H:i:s'),
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    ));
    
    // Send user-friendly response
    if (!headers_sent()) {
        http_response_code(500);
        
        // Check if this is an AJAX/JSON request
        $contentType = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        
        if (stripos($contentType, 'application/json') !== false || $requestedWith === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 500,
                'msg' => 'An unexpected error occurred. Please try again later.'
            ]);
        } else {
            // HTML response for regular page requests
            echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
            echo '<div style="text-align:center;padding:50px;font-family:sans-serif;">';
            echo '<h2>Something went wrong</h2>';
            echo '<p>We\'re experiencing technical difficulties. Please try again later.</p>';
            echo '<p><a href="/lms/index.php">Return to Home</a></p>';
            echo '</div></body></html>';
        }
    }
}

/**
 * Handle fatal errors on shutdown.
 */
function secureShutdownHandler(): void
{
    $error = error_get_last();
    
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        // Log the fatal error
        error_log(sprintf(
            '[%s] FATAL: %s in %s on line %d',
            date('Y-m-d H:i:s'),
            $error['message'],
            basename($error['file']),
            $error['line']
        ));
        
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send user-friendly response
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 500,
                'msg' => 'A critical error occurred. Please try again later.'
            ]);
        }
    }
}

/**
 * Get a user-friendly error message for common error codes.
 */
function getUserFriendlyError(int $code): string
{
    $messages = [
        400 => 'The request was invalid. Please check your input and try again.',
        401 => 'You need to log in to access this page.',
        403 => 'You do not have permission to access this resource.',
        404 => 'The page you are looking for could not be found.',
        405 => 'This method is not allowed for this request.',
        419 => 'Your session has expired. Please refresh the page and try again.',
        429 => 'Too many requests. Please wait a moment and try again.',
        500 => 'An unexpected error occurred. Please try again later.',
        503 => 'The service is temporarily unavailable. Please try again later.',
    ];
    
    return $messages[$code] ?? 'An error occurred.';
}
