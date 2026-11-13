<?php
/**
 * Rate Limiter Helper
 * Uses session-based tracking for rate limiting.
 *
 * Usage:
 *   require_once __DIR__ . '/rateLimiter.php';
 *   $limiter = new RateLimiter('login', 5, 300); // 5 attempts per 300 seconds
 *   if (!$limiter->check($identifier)) { ... }
 *   $limiter->record($identifier);
 */

class RateLimiter
{
    private string $namespace;
    private int $maxAttempts;
    private int $windowSeconds;

    /**
     * @param string $namespace  A unique key for this rate limit (e.g. 'login', 'password_reset')
     * @param int    $maxAttempts Maximum attempts allowed within the window
     * @param int    $windowSeconds Time window in seconds
     */
    public function __construct(string $namespace, int $maxAttempts, int $windowSeconds)
    {
        $this->namespace = $namespace;
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Check if the identifier has exceeded the rate limit.
     * Returns true if allowed, false if blocked.
     */
    public function check(string $identifier): bool
    {
        $key = $this->buildKey($identifier);
        $this->prune($key);
        $attempts = $_SESSION['rate_limit'][$key] ?? [];
        return count($attempts) < $this->maxAttempts;
    }

    /**
     * Record a new attempt for the identifier.
     */
    public function record(string $identifier): void
    {
        $key = $this->buildKey($identifier);
        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = [];
        }
        $_SESSION['rate_limit'][$key][] = time();
    }

    /**
     * Get the number of remaining attempts.
     */
    public function remainingAttempts(string $identifier): int
    {
        $key = $this->buildKey($identifier);
        $this->prune($key);
        $attempts = $_SESSION['rate_limit'][$key] ?? [];
        return max(0, $this->maxAttempts - count($attempts));
    }

    /**
     * Get seconds until the rate limit resets.
     */
    public function retryAfter(string $identifier): int
    {
        $key = $this->buildKey($identifier);
        $attempts = $_SESSION['rate_limit'][$key] ?? [];
        if (empty($attempts)) return 0;
        $oldest = min($attempts);
        $expiresAt = $oldest + $this->windowSeconds;
        return max(0, $expiresAt - time());
    }

    /**
     * Clear all attempts for an identifier (e.g., after successful login).
     */
    public function clear(string $identifier): void
    {
        $key = $this->buildKey($identifier);
        unset($_SESSION['rate_limit'][$key]);
    }

    private function buildKey(string $identifier): string
    {
        return $this->namespace . ':' . hash('sha256', $identifier);
    }

    private function prune(string $key): void
    {
        if (!isset($_SESSION['rate_limit'][$key])) return;
        $cutoff = time() - $this->windowSeconds;
        $_SESSION['rate_limit'][$key] = array_values(
            array_filter($_SESSION['rate_limit'][$key], fn($ts) => $ts > $cutoff)
        );
        if (empty($_SESSION['rate_limit'][$key])) {
            unset($_SESSION['rate_limit'][$key]);
        }
    }
}

/**
 * Convenience function: get client IP address.
 */
if (!function_exists('getClientIp')) {
    function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
