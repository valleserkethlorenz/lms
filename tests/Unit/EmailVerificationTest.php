<?php
/**
 * Tests for the registration email-verification helpers
 * Located in: config/emailVerification.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class EmailVerificationTest extends TestCase
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
        unset($_SESSION['email_verification']);
    }

    public function testExpiryAndAttemptConstants(): void
    {
        $this->assertSame(600, EMAIL_VERIFICATION_EXPIRY);
        $this->assertSame(60, EMAIL_VERIFICATION_RESEND_COOLDOWN);
        $this->assertSame(5, EMAIL_VERIFICATION_MAX_ATTEMPTS);
        $this->assertSame(3, EMAIL_VERIFICATION_RATE_LIMIT);
        $this->assertSame(1800, EMAIL_VERIFICATION_PROOF_TTL);
    }

    public function testGeneratedCodeIsSixDigits(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $code = generateVerificationCode();
            $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
            $this->assertGreaterThanOrEqual(100000, (int) $code);
            $this->assertLessThanOrEqual(999999, (int) $code);
            $this->assertTrue(isValidVerificationCode($code));
        }
    }

    public function testCodeValidationRejectsMalformedValues(): void
    {
        $this->assertTrue(isValidVerificationCode('000000'));
        $this->assertTrue(isValidVerificationCode('123456'));
        $this->assertFalse(isValidVerificationCode('12345'));
        $this->assertFalse(isValidVerificationCode('1234567'));
        $this->assertFalse(isValidVerificationCode('abcdef'));
        $this->assertFalse(isValidVerificationCode('12 345'));
        $this->assertFalse(isValidVerificationCode(''));
    }

    public function testEmailNormalization(): void
    {
        $this->assertSame('user@example.com', normalizeVerificationEmail('  User@Example.COM '));
        $this->assertSame('a@b.test', normalizeVerificationEmail('A@B.TEST'));
    }

    public function testEmailValidation(): void
    {
        $this->assertTrue(isValidVerificationEmail('user@example.com'));
        $this->assertTrue(isValidVerificationEmail('user.name+tag@gmail.com'));
        $this->assertFalse(isValidVerificationEmail('not-an-email'));
        $this->assertFalse(isValidVerificationEmail('user@'));
        $this->assertFalse(isValidVerificationEmail(''));
    }

    public function testEmailMasking(): void
    {
        $this->assertSame('jo***@example.com', maskVerificationEmail('john.doe@example.com'));
        $this->assertSame('x***@y.com', maskVerificationEmail('x@y.com'));
        $this->assertSame('***', maskVerificationEmail('invalid'));
    }

    public function testVerificationEmailContainsCode(): void
    {
        $built = buildVerificationEmail('123456');
        $this->assertArrayHasKey('subject', $built);
        $this->assertArrayHasKey('text', $built);
        $this->assertArrayHasKey('html', $built);
        $this->assertStringContainsString('123456', $built['text']);
        $this->assertStringContainsString('123456', $built['html']);
        $this->assertStringContainsString('Verify your email', $built['html']);
        $this->assertStringContainsString('10 minutes', $built['text']);
    }

    public function testSessionProofAcceptsTheSameEmail(): void
    {
        markEmailVerifiedInSession('User@Example.com');
        $this->assertTrue(isEmailVerifiedInSession('user@example.com'));
        $this->assertTrue(isEmailVerifiedInSession('USER@EXAMPLE.COM'));
    }

    public function testSessionProofRejectsAnotherEmail(): void
    {
        markEmailVerifiedInSession('user@example.com');
        $this->assertFalse(isEmailVerifiedInSession('other@example.com'));
    }

    public function testSessionProofExpires(): void
    {
        markEmailVerifiedInSession('user@example.com');
        $_SESSION['email_verification']['expires_at'] = time() - 1;
        $this->assertFalse(isEmailVerifiedInSession('user@example.com'));
        $this->assertArrayNotHasKey('email_verification', $_SESSION);
    }

    public function testClearSessionProof(): void
    {
        markEmailVerifiedInSession('user@example.com');
        clearEmailVerificationSession();
        $this->assertFalse(isEmailVerifiedInSession('user@example.com'));
    }
}
