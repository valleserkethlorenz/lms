<?php
/**
 * Regression tests for the submit-path registration window.
 *
 * The POST endpoint (includes/registerTeam.php) used to validate the window
 * with its own DateTime() math. That parsed a date-only regis_end as 00:00 and
 * rejected every application submitted on the closing day, while the league
 * hero and app/tr/register_team.php (includes/registrationGate.php) kept the
 * form open until 23:59:59 — the representative saw "Registration has ended.
 * Registration closed on <end date> 12:00 AM" for a league the UI still
 * showed as open.
 *
 * Located in: includes/eligibilityValidator.php -> validateRegistrationWindow()
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/eligibilityValidator.php';

class RegistrationWindowGateTest extends TestCase
{
    /**
     * A published league whose registration closes on 2026-06-15 (date-only,
     * exactly what the organizer's <input type="date"> stores).
     */
    private function league(array $overrides = []): array
    {
        return array_merge([
            'id'                       => 42,
            'publication_status'       => 'Published',
            'is_archived'              => 0,
            'regis_start'              => '2026-06-01',
            'regis_end'                => '2026-06-15',
            'registration_window_open' => 1,
            'start_date'               => '2026-07-01',
            'end_date'                 => '2026-08-31',
            'min_player'               => 5,
            'max_player'               => 10,
            'max_team'                 => 8,
        ], $overrides);
    }

    private function ts(string $dateTime): int
    {
        return (int) strtotime($dateTime);
    }

    /* ---------------------------------------------------------------------
       The bug: the closing day was rejected from 00:00 onwards
       ------------------------------------------------------------------ */

    public function testClosingDayAcceptsSubmissionsAllDay(): void
    {
        foreach (['00:00:01', '09:30:00', '12:00:00', '18:45:00', '23:59:59'] as $time) {
            $check = validateRegistrationWindow($this->league(), $this->ts('2026-06-15 ' . $time));

            $this->assertTrue($check['valid'], "A submission at $time on the closing day must be accepted.");
            $this->assertSame('open', $check['state']);
        }
    }

    public function testTheFollowingDayIsRejected(): void
    {
        $check = validateRegistrationWindow($this->league(), $this->ts('2026-06-16 00:00:01'));

        $this->assertFalse($check['valid']);
        $this->assertSame('closed', $check['state']);
        // The message names the last day the window was open, not midnight of it.
        $this->assertStringContainsString('Jun 15, 2026', $check['message']);
        $this->assertStringNotContainsString('12:00 AM', $check['message']);
    }

    /* ---------------------------------------------------------------------
       The other states keep their previous behaviour
       ------------------------------------------------------------------ */

    public function testUpcomingWindowIsRejected(): void
    {
        $check = validateRegistrationWindow($this->league(), $this->ts('2026-05-20 10:00:00'));

        $this->assertFalse($check['valid']);
        $this->assertSame('upcoming', $check['state']);
        $this->assertStringContainsString('Jun 01, 2026', $check['message']);
    }

    public function testManuallyClosedWindowIsRejected(): void
    {
        $check = validateRegistrationWindow(
            $this->league(['registration_window_open' => 0]),
            $this->ts('2026-06-10 10:00:00')
        );

        $this->assertFalse($check['valid']);
        $this->assertSame('closed', $check['state']);
        $this->assertStringContainsString('closed by the organizer', $check['message']);
    }

    public function testCompletedLeagueIsRejectedEvenInsideTheDates(): void
    {
        $check = validateRegistrationWindow(
            $this->league(['start_date' => '2026-01-01', 'end_date' => '2026-05-31']),
            $this->ts('2026-06-10 10:00:00')
        );

        $this->assertFalse($check['valid']);
        $this->assertSame('closed', $check['state']);
    }

    public function testMissingDatesNeverOpenRegistration(): void
    {
        $check = validateRegistrationWindow(
            $this->league(['regis_start' => '', 'regis_end' => '']),
            $this->ts('2026-06-10 10:00:00')
        );

        $this->assertFalse($check['valid']);
        $this->assertSame('closed', $check['state']);
    }

    /* ---------------------------------------------------------------------
       The POST answer and the shared gate can never disagree
       ------------------------------------------------------------------ */

    public function testPostGateMatchesTheSharedGateOnEveryRelevantDay(): void
    {
        $league = $this->league();

        foreach ([
            '2026-05-20 12:00:00', // before the window
            '2026-06-01 00:00:00', // first second
            '2026-06-10 12:00:00', // middle
            '2026-06-15 23:59:59', // last second
            '2026-06-16 00:00:01', // just after
            '2026-08-31 12:00:00', // league end date
        ] as $moment) {
            $nowTs = $this->ts($moment);
            $window = resolveRegistrationWindow(
                $league,
                getLeagueStatus($league, null, null, null, date('Y-m-d', $nowTs)),
                false,
                $nowTs
            );
            $check = validateRegistrationWindow($league, $nowTs);

            $this->assertSame($window['regOpen'], $check['valid'], "The POST gate disagrees with the hero on $moment.");
        }
    }
}
