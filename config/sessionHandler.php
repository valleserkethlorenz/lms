<?php
/**
 * Session Security Handler
 * Implements secure session configuration and management.
 * 
 * Usage: require_once __DIR__ . '/sessionHandler.php';
 *        initializeSecureSession();
 */

/**
 * Initialize a secure session with hardened settings.
 */
function initializeSecureSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {

    // Determine if connection is HTTPS
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? null) == 443
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    // Session cookie security settings
    session_set_cookie_params([
        'lifetime' => 0, // Session cookie (expires on browser close)
        'path' => '/',
        'domain' => '', // Current domain only
        'secure' => $isSecure, // HTTPS only in production
        'httponly' => true, // Prevent JavaScript access
        'samesite' => 'Lax' // CSRF protection
    ]);

    // Session name (change from default PHPSESSID)
    session_name('LMS_SESSION');

    // Start session
    if (!session_start()) {
        error_log('[SESSION] Failed to start secure session');
        return;
    }
    }

    // Idle timeout. A team registration is long — five players plus one
    // document each — and phone browsers suspend timers while the camera or
    // photo picker is open, so a representative can pass 30 minutes without a
    // request and then be logged out at submit. Representatives get a longer
    // window; every other role keeps the original 30 minutes.
    $timeout = ((int) ($_SESSION['lms_role'] ?? 0) === 2) ? 7200 : 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        // Session expired
        session_unset();
        session_destroy();
        // Start new session
        session_start();
    }
    $_SESSION['last_activity'] = time();

    // Regenerate session ID periodically (every 5 minutes)
    $regenerateInterval = 300; // 5 minutes
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > $regenerateInterval) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Regenerate session ID after privilege change (login, role change).
 */
function regenerateSessionAfterLogin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // Store necessary session data
    $sessionData = $_SESSION;
    
    // Regenerate session ID
    session_regenerate_id(true);
    
    // Restore session data
    $_SESSION = $sessionData;
    $_SESSION['created'] = time();
    $_SESSION['last_activity'] = time();
    
    // Set session fingerprint (bind to IP and User-Agent)
    $_SESSION['fingerprint'] = generateSessionFingerprint();
}

/**
 * Generate a session fingerprint for additional security.
 */
function generateSessionFingerprint(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $ip . $userAgent . 'lms_session_secret');
}

/**
 * Validate session fingerprint to detect session hijacking.
 */
function validateSessionFingerprint(): bool
{
    if (!isset($_SESSION['fingerprint'])) {
        return true; // No fingerprint set yet
    }
    return hash_equals($_SESSION['fingerprint'], generateSessionFingerprint());
}

/**
 * Destroy session completely and securely.
 */
function destroySecureSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    
    // Unset all session variables
    $_SESSION = [];
    
    // Delete session cookie
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
    
    // Destroy session
    session_destroy();
}

/**
 * Check if session is valid and not expired.
 */
function isSessionValid(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    // Check if session has required authentication data
    if (!isset($_SESSION['lms_id']) || !isset($_SESSION['lms_role'])) {
        return false;
    }
    
    // Check session timeout
    $timeout = 1800; // 30 minutes
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        return false;
    }
    
    return true;
}
