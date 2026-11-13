<?php
/**
 * Tests for API Security functions
 * Located in: config/apiSecurity.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ApiSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_POST = [];
        $_SERVER = [];
    }

    public function testGetJsonInputString(): void
    {
        $_POST['name'] = '  John Doe  ';
        $result = getJsonInput('name', 'string');
        $this->assertEquals('John Doe', $result);
    }

    public function testGetJsonInputInt(): void
    {
        $_POST['age'] = '25';
        $result = getJsonInput('age', 'int');
        $this->assertEquals(25, $result);
    }

    public function testGetJsonInputInvalidInt(): void
    {
        $_POST['age'] = 'not-a-number';
        $result = getJsonInput('age', 'int');
        $this->assertFalse($result);
    }

    public function testGetJsonInputFloat(): void
    {
        $_POST['price'] = '19.99';
        $result = getJsonInput('price', 'float');
        $this->assertEquals(19.99, $result);
    }

    public function testGetJsonInputEmail(): void
    {
        $_POST['email'] = 'test@example.com';
        $result = getJsonInput('email', 'email');
        $this->assertEquals('test@example.com', $result);
    }

    public function testGetJsonInputInvalidEmail(): void
    {
        $_POST['email'] = 'not-an-email';
        $result = getJsonInput('email', 'email');
        $this->assertFalse($result);
    }

    public function testGetJsonInputBool(): void
    {
        $_POST['active'] = 'true';
        $result = getJsonInput('active', 'bool');
        $this->assertTrue($result);
    }

    public function testGetJsonInputDefault(): void
    {
        $result = getJsonInput('missing', 'string', 'default_value');
        $this->assertEquals('default_value', $result);
    }

    public function testGetJsonInputNull(): void
    {
        $result = getJsonInput('missing', 'string');
        $this->assertNull($result);
    }

    public function testRequireFieldsSuccess(): void
    {
        $_POST['name'] = 'John';
        $_POST['email'] = 'john@example.com';
        
        // Should not throw or exit
        requireFields(['name', 'email']);
        $this->assertTrue(true); // If we get here, validation passed
    }

    // Note: Tests for requireFields() with missing/empty fields are skipped
    // because the function calls exit() which terminates the test process.
    // These should be tested via integration tests instead.
}