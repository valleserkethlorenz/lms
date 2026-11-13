<?php
/**
 * Tests for RateLimiter class
 * Located in: config/rateLimiter.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        // Initialize a session for testing
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        // Clear rate limit data
        $_SESSION['rate_limit'] = [];
    }

    protected function tearDown(): void
    {
        $_SESSION['rate_limit'] = [];
    }

    public function testCheckAllowsWithinLimit(): void
    {
        $limiter = new RateLimiter('test', 3, 60);
        $this->assertTrue($limiter->check('user1'));
    }

    public function testRecordAndCheck(): void
    {
        $limiter = new RateLimiter('test', 2, 60);
        
        $this->assertTrue($limiter->check('user1'));
        $limiter->record('user1');
        $this->assertTrue($limiter->check('user1'));
        $limiter->record('user1');
        $this->assertFalse($limiter->check('user1'));
    }

    public function testRemainingAttempts(): void
    {
        $limiter = new RateLimiter('test', 5, 60);
        
        $this->assertEquals(5, $limiter->remainingAttempts('user1'));
        $limiter->record('user1');
        $this->assertEquals(4, $limiter->remainingAttempts('user1'));
        $limiter->record('user1');
        $this->assertEquals(3, $limiter->remainingAttempts('user1'));
    }

    public function testRetryAfter(): void
    {
        $limiter = new RateLimiter('test', 2, 60);
        
        $this->assertEquals(0, $limiter->retryAfter('user1'));
        $limiter->record('user1');
        $retryAfter = $limiter->retryAfter('user1');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    public function testClear(): void
    {
        $limiter = new RateLimiter('test', 2, 60);
        
        $limiter->record('user1');
        $limiter->record('user1');
        $this->assertFalse($limiter->check('user1'));
        
        $limiter->clear('user1');
        $this->assertTrue($limiter->check('user1'));
        $this->assertEquals(2, $limiter->remainingAttempts('user1'));
    }

    public function testDifferentNamespacesAreIndependent(): void
    {
        $loginLimiter = new RateLimiter('login', 2, 60);
        $resetLimiter = new RateLimiter('reset', 2, 60);
        
        $loginLimiter->record('user1');
        $loginLimiter->record('user1');
        
        $this->assertFalse($loginLimiter->check('user1'));
        $this->assertTrue($resetLimiter->check('user1'));
    }

    public function testDifferentUsersAreIndependent(): void
    {
        $limiter = new RateLimiter('test', 2, 60);
        
        $limiter->record('user1');
        $limiter->record('user1');
        
        $this->assertFalse($limiter->check('user1'));
        $this->assertTrue($limiter->check('user2'));
    }

    public function testGetClientIp(): void
    {
        // Test with REMOTE_ADDR
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        unset($_SERVER['HTTP_CLIENT_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        
        $ip = getClientIp();
        $this->assertEquals('192.168.1.1', $ip);
    }

    public function testGetClientIpWithClientIp(): void
    {
        $_SERVER['HTTP_CLIENT_IP'] = '10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        
        $ip = getClientIp();
        $this->assertEquals('10.0.0.1', $ip);
        
        unset($_SERVER['HTTP_CLIENT_IP']);
    }

    public function testGetClientIpWithForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '172.16.0.1, 192.168.1.1';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        unset($_SERVER['HTTP_CLIENT_IP']);
        
        $ip = getClientIp();
        $this->assertEquals('172.16.0.1', $ip);
        
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }
}