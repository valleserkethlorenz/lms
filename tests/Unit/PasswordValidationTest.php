<?php
/**
 * Tests for validatePasswordComplexity() function
 * Located in: config/auth_guard.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PasswordValidationTest extends TestCase
{
    /**
     * Test that a valid password passes all checks
     */
    public function testValidPassword(): void
    {
        $result = validatePasswordComplexity('MyStrongPass123!');
        
        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('valid', $result);
    }

    /**
     * Test password too short (less than 8 characters)
     */
    public function testPasswordTooShort(): void
    {
        $result = validatePasswordComplexity('Short1!');
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('8 characters', $result['msg']);
    }

    /**
     * Test password without uppercase letter
     */
    public function testPasswordWithoutUppercase(): void
    {
        $result = validatePasswordComplexity('alllowercase123!');
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('uppercase', $result['msg']);
    }

    /**
     * Test password without lowercase letter
     */
    public function testPasswordWithoutLowercase(): void
    {
        $result = validatePasswordComplexity('ALLUPPERCASE123!');
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('lowercase', $result['msg']);
    }

    /**
     * Test password without number
     */
    public function testPasswordWithoutNumber(): void
    {
        $result = validatePasswordComplexity('NoNumbersHere!');
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('number', $result['msg']);
    }

    /**
     * Test password without special character
     */
    public function testPasswordWithoutSpecialChar(): void
    {
        $result = validatePasswordComplexity('NoSpecialChar123');
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('special character', $result['msg']);
    }

    /**
     * Test minimum length password (exactly 8 characters)
     */
    public function testMinimumLengthPassword(): void
    {
        $result = validatePasswordComplexity('Abc1!@#$');
        
        $this->assertTrue($result['valid']);
    }

    /**
     * Test password with 7 characters (just below minimum)
     */
    public function testPasswordJustBelowMinimum(): void
    {
        $result = validatePasswordComplexity('Ab1!def');
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('8 characters', $result['msg']);
    }

    /**
     * Test very long valid password
     */
    public function testVeryLongValidPassword(): void
    {
        $result = validatePasswordComplexity('ThisIsAVeryLongPassword123!@#$%^&*()');
        
        $this->assertTrue($result['valid']);
    }

    /**
     * Test password with only special characters and numbers (no letters)
     */
    public function testPasswordWithOnlySpecialCharsAndNumbers(): void
    {
        $result = validatePasswordComplexity('123456789012!@#');
        
        $this->assertFalse($result['valid']);
        // Should fail because no uppercase or lowercase letters
    }

    /**
     * Test empty password
     */
    public function testEmptyPassword(): void
    {
        $result = validatePasswordComplexity('');
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('8 characters', $result['msg']);
    }

    /**
     * Test password with spaces
     */
    public function testPasswordWithSpaces(): void
    {
        $result = validatePasswordComplexity('My Pass Word 123!');
        
        $this->assertTrue($result['valid']);
    }

    /**
     * Data provider for various valid passwords
     */
    public static function validPasswordProvider(): array
    {
        return [
            'standard valid' => ['MyPassword123!'],
            'with symbols' => ['Test@Pass#2024'],
            'with unicode' => ['Pässwörd123!'],
            'long password' => ['VeryLongPasswordWithNumbers123!'],
            'minimum valid' => ['Abcd12!@'],
        ];
    }

    /**
     * @dataProvider validPasswordProvider
     */
    public function testVariousValidPasswords(string $password): void
    {
        $result = validatePasswordComplexity($password);
        $this->assertTrue($result['valid'], "Password '$password' should be valid");
    }

    /**
     * Data provider for various invalid passwords
     */
    public static function invalidPasswordProvider(): array
    {
        return [
            'too short' => ['Ab1!'],
            'no uppercase' => ['alllowercase123!'],
            'no lowercase' => ['ALLUPPERCASE123!'],
            'no number' => ['NoNumbersHere!'],
            'no special char' => ['NoSpecialChar123'],
            'only numbers' => ['123456789012'],
            'only letters' => ['OnlyLettersHere'],
        ];
    }

    /**
     * @dataProvider invalidPasswordProvider
     */
    public function testVariousInvalidPasswords(string $password): void
    {
        $result = validatePasswordComplexity($password);
        $this->assertFalse($result['valid'], "Password '$password' should be invalid");
        $this->assertArrayHasKey('msg', $result);
    }
}