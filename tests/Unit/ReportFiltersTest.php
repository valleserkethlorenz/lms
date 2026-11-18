<?php
/**
 * Tests for the report filter helpers.
 * Located in: config/reportFilters.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ReportFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../config/reportFilters.php';
    }

    public function testDefaultsAreNormalised(): void
    {
        $filters = reportFiltersFromRequest([]);

        $this->assertSame('registrations', $filters['report_type']);
        $this->assertSame(0, $filters['league_id']);
        $this->assertSame('', $filters['sport']);
        $this->assertSame('', $filters['from_date']);
        $this->assertSame('', $filters['to_date']);
    }

    public function testValuesAreTrimmedAndCast(): void
    {
        $filters = reportFiltersFromRequest([
            'report_type'  => '  Results ',
            'league_id'    => ' 54 ',
            'sport'        => ' Basketball ',
            'division'     => ' Open ',
            'category'     => ' Male ',
            'league_status' => ' Ongoing ',
            'from_date'    => ' 2026-11-01 ',
            'to_date'      => ' 2026-11-19 ',
        ]);

        $this->assertSame('results', $filters['report_type']);
        $this->assertSame(54, $filters['league_id']);
        $this->assertSame('Basketball', $filters['sport']);
        $this->assertSame('Open', $filters['division']);
        $this->assertSame('Male', $filters['category']);
        $this->assertSame('Ongoing', $filters['league_status']);
        $this->assertSame('2026-11-01', $filters['from_date']);
        $this->assertSame('2026-11-19', $filters['to_date']);
    }

    public function testArrayValuedFilterIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        reportFiltersFromRequest(['report_type' => ['registrations']]);
    }

    public function testInvalidLeagueIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        reportFiltersFromRequest(['league_id' => 'abc']);
    }

    public function testNegativeLeagueIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        reportFiltersFromRequest(['league_id' => '-3']);
    }

    public function testValidDateRangeIsAccepted(): void
    {
        validateReportFilters([
            'report_type'   => 'results',
            'league_id'     => 0,
            'sport'         => '',
            'division'      => '',
            'category'      => '',
            'league_status' => '',
            'from_date'     => '2026-11-01',
            'to_date'       => '2026-11-19',
        ]);

        $this->assertTrue(true);
    }

    public function testInvalidDateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        validateReportFilters(['report_type' => 'results', 'from_date' => '2026-02-30']);
    }

    public function testFromDateLaterThanToDateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        validateReportFilters([
            'report_type' => 'results',
            'from_date'   => '2026-11-20',
            'to_date'     => '2026-11-01',
        ]);
    }

    public function testDateFiltersAreRejectedForStandings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Date filters are not available for this report type.');
        validateReportFilters(['report_type' => 'standings', 'from_date' => '2026-11-01']);
    }

    public function testDateFiltersAreRejectedForParticipation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        validateReportFilters(['report_type' => 'participation', 'to_date' => '2026-11-01']);
    }

    public function testInvalidReportTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        validateReportFilters(['report_type' => 'anything']);
    }

    public function testInvalidLeagueStatusIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        validateReportFilters(['report_type' => 'summary', 'league_status' => 'Deleted']);
    }

    public function testDisplayUsesSelectedLeagueNameAndAttributes(): void
    {
        $display = reportFiltersForDisplay(
            ['league_status' => '', 'sport' => '', 'division' => '', 'category' => '', 'from_date' => '', 'to_date' => ''],
            [
                'league_name'   => 'Mayor Basketball Cup 2026',
                'sport'         => 'Basketball',
                'division'      => 'Open',
                'category'      => 'Male',
                'report_status' => 'Ongoing',
            ]
        );

        $this->assertSame('Mayor Basketball Cup 2026', $display['League']);
        $this->assertSame('Basketball', $display['Sport']);
        $this->assertSame('Open', $display['Division']);
        $this->assertSame('Male', $display['Category']);
        $this->assertSame('Ongoing', $display['League status']);
    }

    public function testDisplayOmitsEmptyValues(): void
    {
        $display = reportFiltersForDisplay([
            'league_status' => '',
            'sport'         => '',
            'division'      => '',
            'category'      => '',
            'from_date'     => '',
            'to_date'       => '',
        ]);

        $this->assertSame(['League' => 'All leagues'], $display);
    }

    public function testEveryReportTypeHasATitle(): void
    {
        $types = ['registrations', 'approved_teams', 'rosters', 'schedules', 'results', 'standings', 'participation', 'summary'];
        $titles = reportTypeTitles();

        foreach ($types as $type) {
            $this->assertArrayHasKey($type, $titles);
            $this->assertNotSame('', $titles[$type]);
        }
    }
}
