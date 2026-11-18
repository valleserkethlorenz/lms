<?php
/**
 * Tests for the shared report document renderer.
 * Located in: includes/reportDocument.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ReportDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/reportDocument.php';
    }

    public function testColumnLabelsAreHumanReadable(): void
    {
        $this->assertSame('Registration Status', reportDocumentColumnLabel('Registration_Status'));
        $this->assertSame('Points For', reportDocumentColumnLabel('Points_For'));
        $this->assertSame('Team', reportDocumentColumnLabel('Team'));
    }

    public function testDocumentContainsTitleFiltersAndRows(): void
    {
        $html = buildReportDocumentHtml([
            'title'   => 'League Standings',
            'filters' => ['League' => 'Mayor Cup', 'Sport' => 'Basketball'],
            'rows'    => [
                ['Rank' => 1, 'Team' => 'Haya Team', 'Points' => 3],
                ['Rank' => 2, 'Team' => 'Alfie Team', 'Points' => 3],
            ],
        ], ['generated_by' => 'Organizer One']);

        $this->assertStringContainsString('League Standings', $html);
        $this->assertStringContainsString('Mayor Cup', $html);
        $this->assertStringContainsString('Basketball', $html);
        $this->assertStringContainsString('Haya Team', $html);
        $this->assertStringContainsString('Total records', $html);
        $this->assertStringContainsString('Organizer One', $html);
    }

    public function testDocumentEscapesUntrustedValues(): void
    {
        $html = buildReportDocumentHtml([
            'title'   => '<img src=x onerror=alert(1)>',
            'filters' => ['League' => '<b>Injected</b>'],
            'rows'    => [
                ['Team' => '<script>alert(1)</script>', 'Note' => '"><svg onload=alert(1)>'],
            ],
        ]);

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>Injected</b>', $html);
        $this->assertStringNotContainsString('<svg onload=', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testEmptyReportShowsNoRecordsMessage(): void
    {
        $html = buildReportDocumentHtml([
            'title'   => 'Team Registrations',
            'filters' => [],
            'rows'    => [],
        ]);

        $this->assertStringContainsString('No matching records found.', $html);
        $this->assertStringContainsString('Total records', $html);
        $this->assertStringContainsString('None applied', $html);
    }

    public function testSignatoriesAreRendered(): void
    {
        $html = buildReportDocumentHtml([
            'title' => 'Summary',
            'rows'  => [['League' => 'Cup']],
        ], [
            'signatories' => ['Prepared by' => 'Organizer One', 'Approved by' => ''],
        ]);

        $this->assertStringContainsString('Prepared by', $html);
        $this->assertStringContainsString('Organizer One', $html);
        $this->assertStringContainsString('Approved by', $html);
    }

    public function testMissingLogoDoesNotAddAnImage(): void
    {
        $html = buildReportDocumentHtml([
            'title' => 'Summary',
            'rows'  => [['League' => 'Cup']],
        ], ['logo_path' => __DIR__ . '/does-not-exist.png']);

        $this->assertStringNotContainsString('data:image/', $html);
    }
}
