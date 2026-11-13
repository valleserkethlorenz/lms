<?php
/**
 * Tests for the team-registration rules shared by the public league hero
 * (includes/league_header.php) and the registration page
 * (app/tr/register_team.php).
 *
 * Located in: includes/registrationGate.php
 *   - resolveRegistrationWindow()
 *   - resolveRegistrationState()
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class TeamRegistrationStateTest extends TestCase
{
    /** A league whose registration window spans the reference date below. */
    private function league(array $overrides = []): array
    {
        return array_merge([
            'id'                       => 42,
            'regis_start'              => '2026-06-01',
            'regis_end'                => '2026-06-30',
            'registration_window_open' => 1,
            'max_team'                 => 8,
        ], $overrides);
    }

    private function now(string $dateTime = '2026-06-15 12:00:00'): int
    {
        return (int) strtotime($dateTime);
    }

    /* ---------------------------------------------------------------------
       Window math
       ------------------------------------------------------------------ */

    public function testGateFunctionsAreAvailable(): void
    {
        $this->assertTrue(function_exists('getRegistrationGate'));
        $this->assertTrue(function_exists('resolveRegistrationWindow'));
        $this->assertTrue(function_exists('resolveRegistrationState'));
    }

    public function testWindowIsOpenInsideTheRange(): void
    {
        $window = resolveRegistrationWindow($this->league(), 'Open', false, $this->now());

        $this->assertTrue($window['regOpen']);
        $this->assertFalse($window['registrationUpcoming']);
        // The label is only rendered for disabled states, so it stays neutral here.
        $this->assertSame('Registration Closed', $window['registrationLabel']);
    }

    public function testWindowIsUpcomingBeforeTheStart(): void
    {
        $window = resolveRegistrationWindow(
            $this->league(['regis_start' => '2026-07-01', 'regis_end' => '2026-07-31']),
            'Upcoming',
            false,
            $this->now()
        );

        $this->assertFalse($window['regOpen']);
        $this->assertTrue($window['registrationUpcoming']);
        $this->assertSame('Opens ' . date('M j, Y', strtotime('2026-07-01')), $window['registrationLabel']);
    }

    public function testUpcomingStopsAtTheStartMoment(): void
    {
        $window = resolveRegistrationWindow(
            $this->league(['regis_start' => '2026-06-15', 'regis_end' => '2026-06-30']),
            'Open',
            false,
            $this->now('2026-06-15 00:00:00')
        );

        $this->assertFalse($window['registrationUpcoming']);
        $this->assertTrue($window['regOpen']);
    }

    public function testWindowIsClosedAfterTheEnd(): void
    {
        $window = resolveRegistrationWindow(
            $this->league(['regis_start' => '2026-05-01', 'regis_end' => '2026-05-31']),
            'Ongoing',
            false,
            $this->now()
        );

        $this->assertFalse($window['regOpen']);
        $this->assertFalse($window['registrationUpcoming']);
        $this->assertSame('Registration Closed', $window['registrationLabel']);
    }

    public function testDateOnlyEndDateIsInclusiveOfTheWholeDay(): void
    {
        $window = resolveRegistrationWindow(
            $this->league(['regis_end' => '2026-06-15']),
            'Open',
            false,
            $this->now('2026-06-15 23:00:00')
        );

        $this->assertTrue($window['regOpen']);
    }

    public function testDateOnlyEndDateExcludesTheFollowingDay(): void
    {
        $window = resolveRegistrationWindow(
            $this->league(['regis_end' => '2026-06-15']),
            'Ongoing',
            false,
            $this->now('2026-06-16 00:00:01')
        );

        $this->assertFalse($window['regOpen']);
    }

    public function testManuallyClosedWindowIsNotOpen(): void
    {
        $window = resolveRegistrationWindow(
            $this->league(['registration_window_open' => 0]),
            'Ongoing',
            false,
            $this->now()
        );

        $this->assertFalse($window['regOpen']);
        $this->assertSame('Registration Closed', $window['registrationLabel']);
    }

    public function testLeagueStatusMustAlsoBeOpen(): void
    {
        $window = resolveRegistrationWindow($this->league(), 'Completed', false, $this->now());

        $this->assertFalse($window['regOpen']);
    }

    public function testFullLeagueKeepsTheWindowOpenButChangesTheLabel(): void
    {
        $window = resolveRegistrationWindow($this->league(), 'Open', true, $this->now());

        $this->assertTrue($window['regOpen']);
        $this->assertSame('Registration Full', $window['registrationLabel']);
    }

    public function testMissingDatesNeverOpenRegistration(): void
    {
        $window = resolveRegistrationWindow($this->league(['regis_start' => '', 'regis_end' => '']), 'Upcoming', false, $this->now());

        $this->assertFalse($window['regOpen']);
        $this->assertFalse($window['registrationUpcoming']);
        $this->assertSame('Registration Closed', $window['registrationLabel']);
    }

    public function testWindowSummaryExplainsAnOpenWindow(): void
    {
        $summary = resolveRegistrationWindowSummary($this->league([
            'publication_status' => 'Published',
        ]), 2, '2026-06-15');

        $this->assertSame('Open', $summary['status']);
        $this->assertTrue($summary['isOpen']);
        $this->assertSame('Teams can apply now.', $summary['note']);
    }

    public function testWindowSummaryExplainsWhyRegistrationIsUnavailable(): void
    {
        $closed = resolveRegistrationWindowSummary($this->league([
            'publication_status' => 'Published',
            'registration_window_open' => 0,
        ]), 0, '2026-06-15');
        $upcoming = resolveRegistrationWindowSummary($this->league([
            'publication_status' => 'Published',
            'regis_start' => '2026-07-01',
            'regis_end' => '2026-07-31',
        ]), 0, '2026-06-15');
        $full = resolveRegistrationWindowSummary($this->league([
            'publication_status' => 'Published',
        ]), 8, '2026-06-15');
        $ended = resolveRegistrationWindowSummary($this->league([
            'publication_status' => 'Published',
        ]), 0, '2026-07-01');

        $this->assertSame('Manually closed', $closed['status']);
        $this->assertSame('Upcoming', $upcoming['status']);
        $this->assertSame('Full', $full['status']);
        $this->assertSame('Ended', $ended['status']);
        $this->assertFalse($closed['isOpen']);
        $this->assertFalse($upcoming['isOpen']);
        $this->assertFalse($full['isOpen']);
        $this->assertFalse($ended['isOpen']);
    }

    public function testUnpublishedLeagueIsNotReportedAsAvailable(): void
    {
        $summary = resolveRegistrationWindowSummary($this->league(), 0, '2026-06-15');

        $this->assertSame('Not published', $summary['status']);
        $this->assertFalse($summary['isOpen']);
    }

    /* ---------------------------------------------------------------------
       State resolution (what the hero and the page render)
       ------------------------------------------------------------------ */

    public function testStateOpenWhenEligible(): void
    {
        $this->assertSame('open', resolveRegistrationState(true, false, false, false));
    }

    public function testStateAlreadyRegistered(): void
    {
        $this->assertSame('already_registered', resolveRegistrationState(true, false, false, true));
    }

    public function testStateFullWhenSlotsAreTaken(): void
    {
        $this->assertSame('full', resolveRegistrationState(true, false, true, false));
    }

    public function testFullOutranksAnExistingApplication(): void
    {
        $this->assertSame('full', resolveRegistrationState(true, false, true, true));
    }

    public function testStateClosedWhenWindowIsNotOpen(): void
    {
        $this->assertSame('closed', resolveRegistrationState(false, false, false, false));
    }

    public function testClosedOutranksAnExistingApplication(): void
    {
        $this->assertSame('closed', resolveRegistrationState(false, false, false, true));
    }

    public function testUpcomingOutranksEverythingElse(): void
    {
        $this->assertSame('upcoming', resolveRegistrationState(false, true, true, true));
    }

    /**
     * End-to-end state: window math + state resolution, the way
     * getRegistrationGate() combines them.
     */
    public function testWindowAndStateCombineForAClosedLeague(): void
    {
        $window = resolveRegistrationWindow(
            $this->league(['regis_start' => '2026-05-01', 'regis_end' => '2026-05-31']),
            'Ongoing',
            false,
            $this->now()
        );

        $state = resolveRegistrationState($window['regOpen'], $window['registrationUpcoming'], false, true);

        $this->assertSame('closed', $state);
    }

    public function testWindowAndStateCombineForAFullLeague(): void
    {
        $window = resolveRegistrationWindow($this->league(), 'Open', true, $this->now());

        $state = resolveRegistrationState($window['regOpen'], $window['registrationUpcoming'], true, false);

        $this->assertSame('full', $state);
    }
}
