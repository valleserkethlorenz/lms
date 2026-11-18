<?php
/**
 * Tests for the report export helpers.
 * Located in: includes/reportExport.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ReportExportTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/reportExport.php';
    }

    public function testFormulaValuesArePrefixedForCsv(): void
    {
        $this->assertSame("'=1+1", reportCsvValue('=1+1'));
        $this->assertSame("'+1", reportCsvValue('+1'));
        $this->assertSame("'@SUM(A1)", reportCsvValue('@SUM(A1)'));
        $this->assertSame("'=HYPERLINK(\"http://evil\")", reportCsvValue('=HYPERLINK("http://evil")'));
    }

    public function testGenuineNumbersPassThroughCsvUnchanged(): void
    {
        $this->assertSame('0', reportCsvValue('0'));
        $this->assertSame('-12.5', reportCsvValue('-12.5'));
        $this->assertSame('42', reportCsvValue('42'));
    }

    public function testNonFormulaTextPassesThroughCsv(): void
    {
        $this->assertSame('Haya Team', reportCsvValue('Haya Team'));
        $this->assertSame("'007", reportCsvValue('007'));
        $this->assertSame('', reportCsvValue(null));
    }

    public function testControlCharactersAreRemoved(): void
    {
        $this->assertSame('ab', reportCsvValue("a\x00b"));
    }

    public function testSpreadsheetCellTyping(): void
    {
        $this->assertSame('numeric', reportSpreadsheetCellType('12'));
        $this->assertSame('numeric', reportSpreadsheetCellType('-4.5'));
        $this->assertSame('string', reportSpreadsheetCellType('=1+1'));
        $this->assertSame('string', reportSpreadsheetCellType('+1'));
        $this->assertSame('string', reportSpreadsheetCellType('007'));
    }

    public function testNumericDetectionRejectsFormulaAndLeadingZero(): void
    {
        $this->assertTrue(reportIsNumericCell('0'));
        $this->assertTrue(reportIsNumericCell('-12.5'));
        $this->assertFalse(reportIsNumericCell('+1'));
        $this->assertFalse(reportIsNumericCell('1e3'));
        $this->assertFalse(reportIsNumericCell('00'));
    }

    public function testFormulaCharacterDetection(): void
    {
        $this->assertTrue(reportStartsWithFormulaChar('=1'));
        $this->assertTrue(reportStartsWithFormulaChar('@cmd'));
        $this->assertTrue(reportStartsWithFormulaChar("\tvalue"));
        $this->assertFalse(reportStartsWithFormulaChar('Team A'));
        $this->assertFalse(reportStartsWithFormulaChar(''));
    }

    public function testSlugIsSafe(): void
    {
        $this->assertSame('mayor-s-basketball-cup-2026', reportSlug("Mayor's Basketball Cup 2026"));
        $this->assertSame('report', reportSlug('   '));
    }

    public function testDownloadFilenameHasExtensionAndTimestamp(): void
    {
        $name = reportDownloadFilename('Team Rosters', 'xlsx', "Mayor's Cup");

        $this->assertMatchesRegularExpression('/^team-rosters-mayor-s-cup-\d{8}-\d{6}\.xlsx$/', $name);
    }

    public function testDownloadFilenameRejectsUnknownExtension(): void
    {
        $name = reportDownloadFilename('Summary', 'exe');

        $this->assertStringEndsWith('.dat', $name);
    }
}
