<?php
/**
 * Tests for getLeagueStatus() function
 * Located in: config/leagueStatus.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class LeagueStatusTest extends TestCase
{
    /**
     * Test upcoming league (today before registration start)
     */
    public function testUpcomingLeague(): void
    {
        $futureStart = date('Y-m-d', strtotime('+30 days'));
        $futureEnd = date('Y-m-d', strtotime('+90 days'));
        $regisStart = date('Y-m-d', strtotime('+20 days'));
        $regisEnd = date('Y-m-d', strtotime('+25 days'));

        $status = getLeagueStatus($futureStart, $futureEnd, $regisStart, $regisEnd);
        $this->assertEquals('Upcoming', $status);
    }

    /**
     * Test open league (registration period active)
     */
    public function testOpenLeague(): void
    {
        $pastStart = date('Y-m-d', strtotime('-10 days'));
        $futureEnd = date('Y-m-d', strtotime('+60 days'));
        $regisStart = date('Y-m-d', strtotime('-5 days'));
        $regisEnd = date('Y-m-d', strtotime('+5 days'));

        $league = [
            'start_date' => $pastStart,
            'end_date' => $futureEnd,
            'regis_start' => $regisStart,
            'regis_end' => $regisEnd,
            'max_team' => 100,
            'registration_window_open' => 1,
        ];

        $status = getLeagueStatus($league);
        $this->assertEquals('Open', $status);
    }

    /**
     * Test ongoing league (after registration ended)
     */
    public function testOngoingAfterRegistration(): void
    {
        $pastStart = date('Y-m-d', strtotime('-10 days'));
        $futureEnd = date('Y-m-d', strtotime('+60 days'));
        $regisStart = date('Y-m-d', strtotime('-20 days'));
        $regisEnd = date('Y-m-d', strtotime('-5 days'));

        $league = [
            'start_date' => $pastStart,
            'end_date' => $futureEnd,
            'regis_start' => $regisStart,
            'regis_end' => $regisEnd,
            'max_team' => 100,
            'registration_window_open' => 1,
        ];

        $status = getLeagueStatus($league);
        $this->assertEquals('Ongoing', $status);
    }

    /**
     * Test completed league (past end date)
     */
    public function testCompletedLeague(): void
    {
        $pastStart = date('Y-m-d', strtotime('-90 days'));
        $pastEnd = date('Y-m-d', strtotime('-10 days'));
        $regisStart = date('Y-m-d', strtotime('-100 days'));
        $regisEnd = date('Y-m-d', strtotime('-95 days'));

        $status = getLeagueStatus($pastStart, $pastEnd, $regisStart, $regisEnd);
        $this->assertEquals('Completed', $status);
    }

    /**
     * Test league status label helper
     */
    public function testLeagueStatusLabel(): void
    {
        $this->assertEquals('OPEN', getLeagueStatusLabel('Open'));
        $this->assertEquals('ONGOING', getLeagueStatusLabel('Ongoing'));
        $this->assertEquals('UPCOMING', getLeagueStatusLabel('Upcoming'));
        $this->assertEquals('COMPLETED', getLeagueStatusLabel('Completed'));
    }

    /**
     * Test league status CSS class helper
     */
    public function testLeagueStatusClass(): void
    {
        $this->assertEquals('league-status-open', getLeagueStatusClass('Open'));
        $this->assertEquals('league-status-ongoing', getLeagueStatusClass('Ongoing'));
        $this->assertEquals('league-status-upcoming', getLeagueStatusClass('Upcoming'));
        $this->assertEquals('league-status-ended', getLeagueStatusClass('Completed'));
    }

    /**
     * Test league status badge HTML
     */
    public function testLeagueStatusBadge(): void
    {
        $badge = getLeagueStatusBadge('Open');
        $this->assertStringContainsString('OPEN', $badge);
        $this->assertStringContainsString('league-status-open', $badge);
        $this->assertStringContainsString('<span', $badge);
    }

    /**
     * Test league status badge class for organizer
     */
    public function testLeagueStatusBadgeClass(): void
    {
        $this->assertEquals('text-bg-success', getLeagueStatusBadgeClass('Open'));
        $this->assertEquals('text-bg-primary', getLeagueStatusBadgeClass('Ongoing'));
        $this->assertEquals('text-bg-warning', getLeagueStatusBadgeClass('Upcoming'));
        $this->assertEquals('text-bg-secondary', getLeagueStatusBadgeClass('Completed'));
    }

    /**
     * Test league status display text
     */
    public function testLeagueStatusDisplay(): void
    {
        $this->assertEquals('Open', getLeagueStatusDisplay('Open'));
        $this->assertEquals('Ongoing', getLeagueStatusDisplay('Ongoing'));
        $this->assertEquals('Upcoming', getLeagueStatusDisplay('Upcoming'));
        $this->assertEquals('Completed', getLeagueStatusDisplay('Completed'));
    }

    /**
     * Test registration closed manually (registration_window_open = 0)
     */
    public function testRegistrationClosedManually(): void
    {
        $pastStart = date('Y-m-d', strtotime('-10 days'));
        $futureEnd = date('Y-m-d', strtotime('+60 days'));
        $regisStart = date('Y-m-d', strtotime('-5 days'));
        $regisEnd = date('Y-m-d', strtotime('+5 days'));

        $league = [
            'start_date' => $pastStart,
            'end_date' => $futureEnd,
            'regis_start' => $regisStart,
            'regis_end' => $regisEnd,
            'max_team' => 100,
            'registration_window_open' => 0,
        ];

        $status = getLeagueStatus($league);
        $this->assertEquals('Ongoing', $status);
    }
}