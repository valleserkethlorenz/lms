<?php
/**
 * Tests for Password Reset functions
 * Located in: config/passwordReset.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PasswordResetTest extends TestCase
{
    /**
     * Test token generation produces a valid hex string of correct length
     */
    public function testTokenFormat(): void
    {
        // Simulate token generation (64 hex chars = 32 bytes)
        $token = bin2hex(random_bytes(32));
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    /**
     * Test token hash is consistent SHA-256
     */
    public function testTokenHashing(): void
    {
        $token = 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890';
        $hash = hash('sha256', $token);
        $this->assertEquals(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $hash);
        // Same input produces same hash
        $this->assertEquals($hash, hash('sha256', $token));
    }

    /**
     * Test different tokens produce different hashes
     */
    public function testUniqueHashes(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));
        $this->assertNotEquals(hash('sha256', $token1), hash('sha256', $token2));
    }

    /**
     * Test reset URL building
     */
    public function testBuildResetUrl(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = '';
        $url = buildResetUrl('testtoken123');
        $this->assertStringContainsString('reset-password.php', $url);
        $this->assertStringContainsString('token=testtoken123', $url);
    }

    /**
     * Test reset URL with HTTPS
     */
    public function testBuildResetUrlHttps(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTPS'] = 'on';
        $url = buildResetUrl('mytoken');
        $this->assertStringStartsWith('https://', $url);
        $_SERVER['HTTPS'] = ''; // Reset
    }

    /**
     * Test expiry constant is reasonable (1 hour)
     */
    public function testExpiryDuration(): void
    {
        $this->assertEquals(3600, PASSWORD_RESET_EXPIRY);
    }

    /**
     * Test rate limit constant
     */
    public function testRateLimitConstant(): void
    {
        $this->assertEquals(3, PASSWORD_RESET_RATE_LIMIT);
    }

    /**
     * Test token bytes constant
     */
    public function testTokenBytesConstant(): void
    {
        $this->assertEquals(32, PASSWORD_RESET_TOKEN_BYTES);
    }

    /**
     * Test that token URL encodes special characters
     */
    public function testTokenUrlEncoding(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = '';
        $tokenWithPlus = 'abc+def/ghi=';
        $url = buildResetUrl($tokenWithPlus);
        $this->assertStringContainsString(urlencode($tokenWithPlus), $url);
    }
}
