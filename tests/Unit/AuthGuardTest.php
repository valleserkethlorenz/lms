<?php
/**
 * Tests for Auth Guard helper functions
 * Located in: config/auth_guard.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class AuthGuardTest extends TestCase
{
    /**
     * Test role name mapping
     */
    public function testRoleNames(): void
    {
        $this->assertEquals('Organizer', roleName(1));
        $this->assertEquals('Team Representative', roleName(2));
        $this->assertEquals('Admin', roleName(3));
        $this->assertEquals('Committee', roleName(4));
        $this->assertEquals('Unknown', roleName(99));
        $this->assertEquals('Unknown', roleName(0));
    }

    /**
     * Test CSRF token generation
     */
    public function testCsrfTokenGeneration(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['csrf_token']);
        
        $token = csrfToken();
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    /**
     * Test CSRF token persistence within session
     */
    public function testCsrfTokenPersistence(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['csrf_token']);
        
        $token1 = csrfToken();
        $token2 = csrfToken();
        $this->assertEquals($token1, $token2);
    }

    /**
     * Test CSRF token regeneration after clearing
     */
    public function testCsrfTokenRegeneration(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $token1 = csrfToken();
        unset($_SESSION['csrf_token']);
        $token2 = csrfToken();
        
        $this->assertNotEquals($token1, $token2);
    }

    /**
     * Test currentUserId returns 0 when not logged in
     */
    public function testCurrentUserIdWhenNotLoggedIn(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['lms_id']);
        
        $this->assertEquals(0, currentUserId());
    }

    /**
     * Test currentRole returns 0 when not logged in
     */
    public function testCurrentRoleWhenNotLoggedIn(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['lms_role']);
        
        $this->assertEquals(0, currentRole());
    }

    /**
     * Test currentUserName returns empty when not logged in
     */
    public function testCurrentUserNameWhenNotLoggedIn(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['lms_username']);
        
        $this->assertEquals('', currentUserName());
    }

    /**
     * Test currentUserId with logged in user
     */
    public function testCurrentUserIdWhenLoggedIn(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['lms_id'] = 42;
        
        $this->assertEquals(42, currentUserId());
        
        unset($_SESSION['lms_id']);
    }

    /**
     * Test currentRole with logged in user
     */
    public function testCurrentRoleWhenLoggedIn(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['lms_role'] = 1;
        
        $this->assertEquals(1, currentRole());
        
        unset($_SESSION['lms_role']);
    }

    /**
     * Test currentUserName with logged in user
     */
    public function testCurrentUserNameWhenLoggedIn(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['lms_username'] = 'John Doe';
        
        $this->assertEquals('John Doe', currentUserName());
        
        unset($_SESSION['lms_username']);
    }

    /**
     * Test validatePasswordComplexity function exists and works
     */
    public function testValidatePasswordComplexityExists(): void
    {
        $this->assertTrue(function_exists('validatePasswordComplexity'));
        
        $result = validatePasswordComplexity('ValidPass123!');
        $this->assertTrue($result['valid']);
    }

    /**
     * Test validateCsrfToken accepts the session token.
     */
    public function testValidateCsrfTokenAcceptsSessionToken(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['csrf_token']);

        $token = csrfToken();

        $this->assertTrue(validateCsrfToken($token));
    }

    /**
     * Test validateCsrfToken rejects wrong and empty tokens.
     */
    public function testValidateCsrfTokenRejectsWrongOrEmptyTokens(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['csrf_token']);

        $token = csrfToken();

        $this->assertFalse(validateCsrfToken(''));
        $this->assertFalse(validateCsrfToken('not-the-token'));
        $this->assertFalse(validateCsrfToken($token . 'x'));
    }
}