<?php
/**
 * PHPUnit Bootstrap File
 * Loads all necessary config files for testing
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load config files in dependency order
require_once __DIR__ . '/../config/errorHandler.php';
require_once __DIR__ . '/../config/rateLimiter.php';
require_once __DIR__ . '/../config/securityLogger.php';
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/inputValidator.php';
require_once __DIR__ . '/../config/sessionHandler.php';
require_once __DIR__ . '/../config/passwordSecurity.php';
require_once __DIR__ . '/../config/apiSecurity.php';
require_once __DIR__ . '/../config/leagueStatus.php';
require_once __DIR__ . '/../includes/registrationGate.php';
require_once __DIR__ . '/../config/teamRegistrationStatus.php';
require_once __DIR__ . '/../config/passwordReset.php';
require_once __DIR__ . '/../config/emailService.php';

// Initialize error handler for tests
if (function_exists('initializeErrorHandler')) {
    initializeErrorHandler();
}