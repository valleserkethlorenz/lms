<?php
/**
 * Security Logger - Centralized security event logging
 * 
 * Usage: require_once __DIR__ . '/securityLogger.php';
 *        SecurityLogger::log('BRUTE_FORCE', 'Multiple failed login attempts', ['ip' => $ip]);
 */

require_once __DIR__ . '/rateLimiter.php';

class SecurityLogger
{
    private const LEVELS = ['INFO' => 0, 'WARNING' => 1, 'CRITICAL' => 2, 'ALERT' => 3];
    private const CATEGORIES = ['AUTH', 'CSRF', 'UPLOAD', 'ACCESS', 'INPUT', 'SESSION', 'SYSTEM', 'RATE_LIMIT', 'SQL_INJECTION', 'XSS'];
    
    public static function log(string $category, string $message, array $context = [], string $level = 'INFO'): void
    {
        if (!in_array($category, self::CATEGORIES, true)) $category = 'SYSTEM';
        if (!isset(self::LEVELS[$level])) $level = 'INFO';
        
        $logDir = dirname(__DIR__) . '/logs/security';
        if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
        
        $logFile = $logDir . '/security_' . date('Y-m-d') . '.log';
        $entry = self::formatEntry($category, $message, $context, $level);
        @file_put_contents($logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
        
        if (self::LEVELS[$level] >= self::LEVELS['CRITICAL']) {
            error_log("[SECURITY][$level][$category] $message");
        }
    }
    
    public static function auth(string $action, string $details, array $context = []): void
    {
        self::log('AUTH', "$action: $details", $context, 'INFO');
    }
    
    public static function csrfViolation(string $details, array $context = []): void
    {
        self::log('CSRF', $details, $context, 'WARNING');
    }
    
    public static function rateLimitExceeded(string $endpoint, string $identifier, array $context = []): void
    {
        self::log('RATE_LIMIT', "Rate limit exceeded on $endpoint for $identifier", $context, 'WARNING');
    }
    
    public static function suspiciousInput(string $type, string $details, array $context = []): void
    {
        $level = in_array($type, ['SQL_INJECTION', 'XSS']) ? 'CRITICAL' : 'WARNING';
        self::log($type, $details, $context, $level);
    }
    
    public static function unauthorizedAccess(string $details, array $context = []): void
    {
        self::log('ACCESS', $details, $context, 'WARNING');
    }
    
    public static function fileUpload(string $action, string $details, array $context = []): void
    {
        self::log('UPLOAD', "$action: $details", $context, 'INFO');
    }
    
    public static function sessionEvent(string $action, string $details, array $context = []): void
    {
        self::log('SESSION', "$action: $details", $context, 'INFO');
    }
    
    private static function formatEntry(string $category, string $message, array $context, string $level): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $ip = getClientIp();
        $userId = $_SESSION['lms_id'] ?? 'anonymous';
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        return sprintf('[%s] [%s] [%s] [IP:%s] [User:%s] %s%s',
            $timestamp, $level, $category, $ip, $userId, $message,
            $contextStr !== '' ? " | Context: $contextStr" : '');
    }
    
    public static function getRecentEvents(int $limit = 100, ?string $level = null): array
    {
        $logDir = dirname(__DIR__) . '/logs/security';
        if (!is_dir($logDir)) return [];
        $logFile = $logDir . '/security_' . date('Y-m-d') . '.log';
        if (!is_file($logFile)) return [];
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return [];
        $lines = array_reverse($lines);
        $events = [];
        foreach ($lines as $line) {
            if ($level !== null && strpos($line, "[$level]") === false) continue;
            $events[] = $line;
            if (count($events) >= $limit) break;
        }
        return $events;
    }
    
    public static function getTodayStats(): array
    {
        $events = self::getRecentEvents(10000);
        $stats = ['total' => count($events), 'by_level' => [], 'by_category' => []];
        foreach ($events as $event) {
            if (preg_match('/\[(INFO|WARNING|CRITICAL|ALERT)\]/', $event, $m)) {
                $stats['by_level'][$m[1]] = ($stats['by_level'][$m[1]] ?? 0) + 1;
            }
            if (preg_match('/\[(AUTH|CSRF|UPLOAD|ACCESS|INPUT|SESSION|SYSTEM|RATE_LIMIT|SQL_INJECTION|XSS)\]/', $event, $m)) {
                $stats['by_category'][$m[1]] = ($stats['by_category'][$m[1]] ?? 0) + 1;
            }
        }
        return $stats;
    }
}
