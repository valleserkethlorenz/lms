<?php
/**
 * Tests for the league slot rules shared by every surface that reports
 * "slots full" — the hero banner, the public directory, the league status and
 * the registration gate.
 *
 * Located in: config/leagueStatus.php
 *   - countLeagueApprovedTeams()
 *   - leagueSlotState()
 *   - getLeagueStatus()
 *
 * Regression guard: a slot belongs to an APPROVED team. Applications that are
 * still Pending review used to consume slots, so a league read "Slots full"
 * (and flipped to Ongoing, closing registration) before the organizer had
 * approved a single team.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class LeagueSlotStateTest extends TestCase
{
    /** A published league whose registration window spans today. */
    private function openLeague(array $overrides = []): array
    {
        return array_merge([
            'id'                       => 77,
            'start_date'               => date('Y-m-d', strtotime('-10 days')),
            'end_date'                 => date('Y-m-d', strtotime('+30 days')),
            'regis_start'              => date('Y-m-d', strtotime('-5 days')),
            'regis_end'                => date('Y-m-d', strtotime('+5 days')),
            'max_team'                 => 8,
            'registration_window_open' => 1,
        ], $overrides);
    }

    /* ---------------------------------------------------------------------
       Slot math
       ------------------------------------------------------------------ */

    public function testSlotHelpersAreAvailable(): void
    {
        $this->assertTrue(function_exists('countLeagueApprovedTeams'));
        $this->assertTrue(function_exists('leagueSlotState'));
    }

    public function testEmptyLeagueHasEverySlotOpen(): void
    {
        $state = leagueSlotState(0, 8);

        $this->assertFalse($state['slotsFull']);
        $this->assertSame(8, $state['slotsLeft']);
        $this->assertSame(0, $state['fillPercentage']);
    }

    public function testPartiallyApprovedLeagueStillHasSlots(): void
    {
        $state = leagueSlotState(3, 8);

        $this->assertFalse($state['slotsFull']);
        $this->assertSame(5, $state['slotsLeft']);
        $this->assertSame(38, $state['fillPercentage']);
    }

    public function testApprovedTeamsFillTheLeagueAtTheLimit(): void
    {
        $state = leagueSlotState(8, 8);

        $this->assertTrue($state['slotsFull']);
        $this->assertSame(0, $state['slotsLeft']);
        $this->assertSame(100, $state['fillPercentage']);
    }

    public function testExtraApprovedTeamsNeverReportMoreThanFull(): void
    {
        $state = leagueSlotState(11, 8);

        $this->assertTrue($state['slotsFull']);
        $this->assertSame(0, $state['slotsLeft']);
        $this->assertSame(100, $state['fillPercentage']);
    }

    public function testLeagueWithoutACapacityIsNeverFull(): void
    {
        $state = leagueSlotState(0, 0);

        $this->assertFalse($state['slotsFull']);
        $this->assertSame(0, $state['slotsLeft']);
        $this->assertSame(0, $state['fillPercentage']);
    }

    public function testCountingWithoutAConnectionReturnsZero(): void
    {
        $this->assertSame(0, countLeagueApprovedTeams(null, 5));
        $this->assertSame(0, countLeagueApprovedTeams(null, 0));
    }

    /* ---------------------------------------------------------------------
       League status (slots come from approved teams only)
       ------------------------------------------------------------------ */

    public function testZeroApprovedTeamsKeepsAnOpenLeagueOpen(): void
    {
        // Eight Pending applications exist, but the organizer approved nobody:
        // the league must stay Open so other teams can still register.
        $league = $this->openLeague(['current_teams' => 0]);

        $this->assertSame('Open', getLeagueStatus($league));
    }

    public function testApprovedTeamsFillingEverySlotClosesTheLeague(): void
    {
        $league = $this->openLeague(['current_teams' => 8]);

        $this->assertSame('Ongoing', getLeagueStatus($league));
    }

    public function testPartiallyApprovedLeagueStaysOpen(): void
    {
        $league = $this->openLeague(['current_teams' => 7]);

        $this->assertSame('Open', getLeagueStatus($league));
    }

    /* ---------------------------------------------------------------------
       Registration gate
       ------------------------------------------------------------------ */

    public function testPendingApplicationsDoNotCloseRegistration(): void
    {
        $slotState = leagueSlotState(0, 8);
        $state     = resolveRegistrationState(true, false, $slotState['slotsFull'], false);

        $this->assertSame('open', $state);
    }

    public function testApprovedTeamsClosingEverySlotBlocksRegistration(): void
    {
        $slotState = leagueSlotState(8, 8);
        $state     = resolveRegistrationState(true, false, $slotState['slotsFull'], false);

        $this->assertSame('full', $state);
    }
}
