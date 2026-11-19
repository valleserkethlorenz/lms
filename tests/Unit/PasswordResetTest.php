<?php
/**
 * Tests for Password Reset functions
 * Located in: config/passwordReset.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        clearPasswordResetFlow();
    }

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

    /**
     * Test the OTP/grant constants are the documented values.
     */
    public function testOtpConstants(): void
    {
        $this->assertSame(600, PASSWORD_RESET_OTP_EXPIRY);
        $this->assertSame(60, PASSWORD_RESET_OTP_RESEND_COOLDOWN);
        $this->assertSame(5, PASSWORD_RESET_OTP_MAX_ATTEMPTS);
        $this->assertSame(3, PASSWORD_RESET_OTP_RATE_LIMIT);
        $this->assertSame(900, PASSWORD_RESET_GRANT_TTL);
    }

    /**
     * Test only Team Representative accounts are eligible.
     */
    public function testOnlyTeamRepresentativesAreEligible(): void
    {
        $this->assertSame(['2'], PASSWORD_RESET_ALLOWED_ROLES);
        $this->assertTrue(passwordResetRoleAllowed('2'));
        $this->assertFalse(passwordResetRoleAllowed('1'));
        $this->assertFalse(passwordResetRoleAllowed('3'));
        $this->assertFalse(passwordResetRoleAllowed('4'));
        $this->assertFalse(passwordResetRoleAllowed(''));
    }

    /**
     * Test the reset OTP email contains the code and expiry.
     */
    public function testOtpEmailContainsCodeAndExpiry(): void
    {
        $built = buildPasswordResetOtpEmail('123456');

        $this->assertArrayHasKey('subject', $built);
        $this->assertArrayHasKey('text', $built);
        $this->assertArrayHasKey('html', $built);
        $this->assertStringContainsString('123456', $built['text']);
        $this->assertStringContainsString('123456', $built['html']);
        $this->assertStringContainsString('10 minutes', $built['text']);
        $this->assertStringContainsString('password reset', strtolower($built['subject']));
    }

    /**
     * Test the session grant round-trip and user binding.
     */
    public function testGrantRoundTrip(): void
    {
        markPasswordResetGrant(42, 'Rep@Example.com');

        $grant = getPasswordResetGrant();
        $this->assertNotNull($grant);
        $this->assertSame(42, (int) $grant['user_id']);
        $this->assertSame(hash('sha256', 'rep@example.com'), $grant['email_hash']);
        $this->assertNotNull(getPasswordResetGrant(42));
        $this->assertNull(getPasswordResetGrant(7));
    }

    /**
     * Test an expired grant is rejected and removed.
     */
    public function testGrantExpires(): void
    {
        markPasswordResetGrant(42, 'rep@example.com');
        $_SESSION['pwd_reset_grant']['expires_at'] = time() - 1;

        $this->assertNull(getPasswordResetGrant());
        $this->assertArrayNotHasKey('pwd_reset_grant', $_SESSION);
    }

    /**
     * Test the whole OTP flow is cleared from the session.
     */
    public function testClearPasswordResetFlow(): void
    {
        markPasswordResetGrant(42, 'rep@example.com');
        $_SESSION['pwd_reset_pending_email'] = 'rep@example.com';
        $_SESSION['pwd_reset_otp_sent_at'] = time();

        clearPasswordResetFlow();

        $this->assertNull(getPasswordResetGrant());
        $this->assertArrayNotHasKey('pwd_reset_pending_email', $_SESSION);
        $this->assertArrayNotHasKey('pwd_reset_otp_sent_at', $_SESSION);
    }
}
