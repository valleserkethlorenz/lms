<?php

use PHPUnit\Framework\TestCase;

class EmailServiceTest extends TestCase
{
    public function testIsSmtpConfiguredReturnsFalseWhenEmpty(): void
    {
        // By default in tests, SMTP_HOST and SMTP_USER are empty
        $this->assertFalse(isSmtpConfigured());
    }

    public function testSendLmsEmailReturnsArrayStructure(): void
    {
        // Without SMTP configured, it should attempt mail() and return the expected structure
        $result = sendLmsEmail('test@example.com', 'Test Subject', 'Test body', 'Test User');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('method', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertContains($result['method'], ['smtp', 'mail']);
    }

    public function testSendLmsEmailReturnsBoolSuccess(): void
    {
        $result = sendLmsEmail('test@example.com', 'Subject', 'Body');
        $this->assertIsBool($result['success']);
    }

    public function testBuildHtmlEmailContainsTitle(): void
    {
        $html = buildHtmlEmail('Test Title', 'Test body content');
        $this->assertStringContainsString('Test Title', $html);
        $this->assertStringContainsString('Test body content', $html);
    }

    public function testBuildHtmlEmailContainsActionUrl(): void
    {
        $html = buildHtmlEmail('Reset Password', 'Click below', 'https://example.com/reset', 'Reset Now');
        $this->assertStringContainsString('https://example.com/reset', $html);
        $this->assertStringContainsString('Reset Now', $html);
        $this->assertStringContainsString('<a href=', $html);
    }

    public function testBuildHtmlEmailWithoutActionUrl(): void
    {
        $html = buildHtmlEmail('Info', 'Just a notification');
        $this->assertStringNotContainsString('<a href=', $html);
    }

    public function testBuildHtmlEmailEscapesHtmlEntities(): void
    {
        $html = buildHtmlEmail('<script>alert("xss")</script>', '<img onerror="evil()">');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testBuildHtmlEmailContainsFooter(): void
    {
        $html = buildHtmlEmail('Title', 'Body');
        $this->assertStringContainsString('League Management System', $html);
        $this->assertStringContainsString(date('Y'), $html);
    }

    public function testBuildHtmlEmailIsValidHtmlStructure(): void
    {
        $html = buildHtmlEmail('Title', 'Body');
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html>', $html);
        $this->assertStringContainsString('</html>', $html);
        $this->assertStringContainsString('charset="UTF-8"', $html);
    }

    public function testSendLmsEmailWithHtmlBody(): void
    {
        $html = buildHtmlEmail('Test', 'Content');
        $result = sendLmsEmail('user@test.com', 'Subject', 'Plain text', 'User', $html);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('method', $result);
    }

    public function testSmtpConstantsAreDefined(): void
    {
        $this->assertTrue(defined('SMTP_HOST'));
        $this->assertTrue(defined('SMTP_PORT'));
        $this->assertTrue(defined('SMTP_USER'));
        $this->assertTrue(defined('SMTP_PASS'));
        $this->assertTrue(defined('SMTP_ENCRYPTION'));
        $this->assertTrue(defined('SMTP_FROM_EMAIL'));
        $this->assertTrue(defined('SMTP_FROM_NAME'));
    }

    public function testSmtpPortIsInteger(): void
    {
        $this->assertIsInt(SMTP_PORT);
    }
}