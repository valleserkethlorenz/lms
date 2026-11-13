<?php
/**
 * Tests for Password Security functions
 * Located in: config/passwordSecurity.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PasswordSecurityTest extends TestCase
{
    /**
     * Test that common passwords are rejected
     */
    public function testCommonPasswordsRejected(): void
    {
        $this->assertFalse(checkCommonPasswords('password'));
        $this->assertFalse(checkCommonPasswords('password123'));
        $this->assertFalse(checkCommonPasswords('12345678'));
        $this->assertFalse(checkCommonPasswords('qwerty123'));
        $this->assertFalse(checkCommonPasswords('letmein1'));
        $this->assertFalse(checkCommonPasswords('admin123'));
    }

    /**
     * Test that common passwords are case-insensitive
     */
    public function testCommonPasswordsCaseInsensitive(): void
    {
        $this->assertFalse(checkCommonPasswords('PASSWORD'));
        $this->assertFalse(checkCommonPasswords('Password1'));
        $this->assertFalse(checkCommonPasswords('PASSWORD123'));
    }

    /**
     * Test that sequential patterns are rejected
     */
    public function testSequentialPatternsRejected(): void
    {
        $this->assertFalse(checkCommonPasswords('0123456789'));
        $this->assertFalse(checkCommonPasswords('1234567890'));
        $this->assertFalse(checkCommonPasswords('abcdefgh'));
        $this->assertFalse(checkCommonPasswords('qwertyuiop'));
    }

    /**
     * Test that repeated characters are rejected
     */
    public function testRepeatedCharactersRejected(): void
    {
        $this->assertFalse(checkCommonPasswords('aaaaaaaa'));
        $this->assertFalse(checkCommonPasswords('11111111'));
        $this->assertFalse(checkCommonPasswords('xxxxxxxx'));
    }

    /**
     * Test that unique passwords pass
     */
    public function testUniquePasswordsPass(): void
    {
        $this->assertTrue(checkCommonPasswords('MyUniqueP@ss2024'));
        $this->assertTrue(checkCommonPasswords('Tr0ub4dor&3xK9'));
        $this->assertTrue(checkCommonPasswords('C0mpl3x!Pass#2024'));
    }

    /**
     * Data provider for common passwords
     */
    public static function commonPasswordProvider(): array
    {
        return [
            ['password'],
            ['password1'],
            ['password123'],
            ['12345678'],
            ['123456789'],
            ['qwerty123'],
            ['letmein1'],
            ['welcome1'],
            ['admin123'],
            ['monkey123'],
        ];
    }

    /**
     * @dataProvider commonPasswordProvider
     */
    public function testVariousCommonPasswords(string $password): void
    {
        $this->assertFalse(checkCommonPasswords($password), "Password '$password' should be detected as common");
    }

    /**
     * Data provider for unique passwords
     */
    public static function uniquePasswordProvider(): array
    {
        return [
            ['MyUniqueP@ss2024'],
            ['Tr0ub4dor&3xK9'],
            ['C0mpl3x!Pass#2024'],
            ['S3cur3P@ssw0rd!'],
            ['L3agu3M@n@g3r2024'],
        ];
    }

    /**
     * @dataProvider uniquePasswordProvider
     */
    public function testVariousUniquePasswords(string $password): void
    {
        $this->assertTrue(checkCommonPasswords($password), "Password '$password' should be detected as unique");
    }
}