<?php
/**
 * Tests for InputValidator class
 * Located in: config/inputValidator.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class InputValidatorTest extends TestCase
{
    private InputValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new InputValidator();
    }

    // ==================== Email Tests ====================

    public function testValidEmail(): void
    {
        $result = $this->validator->email('test@example.com');
        $this->assertEquals('test@example.com', $result);
    }

    public function testEmailTrimming(): void
    {
        $result = $this->validator->email('  test@example.com  ');
        $this->assertEquals('test@example.com', $result);
    }

    public function testEmptyEmailThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email is required');
        $this->validator->email('');
    }

    public function testInvalidEmailThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email');
        $this->validator->email('not-an-email');
    }

    // ==================== Name Tests ====================

    public function testValidName(): void
    {
        $result = $this->validator->name('John');
        $this->assertEquals('John', $result);
    }

    public function testEmptyNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name is required');
        $this->validator->name('');
    }

    public function testNameTooShortThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least');
        $this->validator->name('A', 2, 100);
    }

    // ==================== Phone Tests ====================

    public function testValidPhone(): void
    {
        $result = $this->validator->phone('+639171234567');
        $this->assertEquals('+639171234567', $result);
    }

    public function testEmptyPhoneThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Phone number is required');
        $this->validator->phone('');
    }

    // ==================== ID Tests ====================

    public function testValidId(): void
    {
        $result = $this->validator->id(42);
        $this->assertEquals(42, $result);
    }

    public function testNullIdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ID is required');
        $this->validator->id(null);
    }

    public function testZeroIdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ID');
        $this->validator->id(0);
    }

    // ==================== Date Tests ====================

    public function testValidDate(): void
    {
        $result = $this->validator->date('2025-01-15');
        $this->assertEquals('2025-01-15', $result);
    }

    public function testInvalidDateFormatThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Date format');
        $this->validator->date('01-15-2025');
    }

    // ==================== Text Tests ====================

    public function testValidText(): void
    {
        $result = $this->validator->text('Hello World');
        $this->assertEquals('Hello World', $result);
    }

    public function testTextEscapesHtml(): void
    {
        $result = $this->validator->text('<script>alert("xss")</script>');
        $this->assertStringNotContainsString('<script>', $result);
    }

    // ==================== InList Tests ====================

    public function testInListValid(): void
    {
        $result = $this->validator->inList('admin', ['admin', 'user', 'guest']);
        $this->assertEquals('admin', $result);
    }

    public function testInListInvalidThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Value');
        $this->validator->inList('hacker', ['admin', 'user', 'guest']);
    }

    // ==================== Bool Tests ====================

    public function testBoolTrue(): void
    {
        $this->assertTrue($this->validator->bool('true'));
        $this->assertTrue($this->validator->bool('1'));
    }

    public function testBoolFalse(): void
    {
        $this->assertFalse($this->validator->bool('false'));
        $this->assertFalse($this->validator->bool('0'));
    }
}