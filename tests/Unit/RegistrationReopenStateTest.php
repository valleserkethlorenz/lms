<?php
/**
 * Tests for resolveRegistrationReopenState() in includes/registrationGate.php.
 *
 * The helper is shared by the league overview button (app/organizer/league_info.php)
 * and the reopen endpoint (app/organizer/action/leagueAction.php), so both must
 * agree on when a manually closed registration window may be reopened.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class RegistrationReopenStateTest extends TestCase
{
    /** A published league whose registration was manually closed mid-window. */
    private function league(array $overrides = []): array
    {
        return array_merge([
            'id'                       => 42,
            'publication_status'       => 'Published',
            'is_archived'              => 0,
            'end_date'                 => '2026-08-31',
            'regis_start'              => '2026-06-01',
            'regis_end'                => '2026-06-30',
            'registration_window_open' => 0,
            'max_team'                 => 8,
        ], $overrides);
    }

    public function testHelperIsAvailable(): void
    {
        $this->assertTrue(function_exists('resolveRegistrationReopenState'));
    }

    public function testReopenAllowedMidWindowWhenNotFull(): void
    {
        $state = resolveRegistrationReopenState($this->league(), 4, '2026-06-15');

        $this->assertTrue($state['allowed']);
        $this->assertSame('', $state['reason']);
    }

    public function testReopenAllowedBeforeTheStartDate(): void
    {
        // The window will simply open on its scheduled start date.
        $state = resolveRegistrationReopenState($this->league(), 0, '2026-05-20');

        $this->assertTrue($state['allowed']);
    }

    public function testReopenRejectedWhenAlreadyOpen(): void
    {
        $state = resolveRegistrationReopenState(
            $this->league(['registration_window_open' => 1]),
            4,
            '2026-06-15'
        );

        $this->assertFalse($state['allowed']);
        $this->assertSame('Registration is already open.', $state['reason']);
    }

    public function testReopenRejectedWhenAllSlotsAreFilled(): void
    {
        $state = resolveRegistrationReopenState($this->league(['max_team' => 8]), 8, '2026-06-15');

        $this->assertFalse($state['allowed']);
        $this->assertSame('All team slots are filled.', $state['reason']);
    }

    public function testReopenRejectedAfterRegistrationEnd(): void
    {
        $state = resolveRegistrationReopenState($this->league(), 4, '2026-07-01');

        $this->assertFalse($state['allowed']);
        $this->assertStringContainsString('registration end date has passed', $state['reason']);
    }

    public function testReopenRejectedWhenLeagueHasEnded(): void
    {
        $state = resolveRegistrationReopenState(
            $this->league(['end_date' => '2026-06-10']),
            4,
            '2026-06-15'
        );

        $this->assertFalse($state['allowed']);
        $this->assertSame('This league has already ended.', $state['reason']);
    }

    public function testReopenRejectedForDraftLeague(): void
    {
        $state = resolveRegistrationReopenState(
            $this->league(['publication_status' => 'Draft']),
            4,
            '2026-06-15'
        );

        $this->assertFalse($state['allowed']);
        $this->assertStringContainsString('Publish the league', $state['reason']);
    }

    public function testReopenRejectedForArchivedLeague(): void
    {
        $state = resolveRegistrationReopenState(
            $this->league(['is_archived' => 1]),
            4,
            '2026-06-15'
        );

        $this->assertFalse($state['allowed']);
        $this->assertSame('Archived leagues cannot accept registrations.', $state['reason']);
    }

    public function testReopenRejectedWhenRegistrationDatesAreMissing(): void
    {
        $state = resolveRegistrationReopenState(
            $this->league(['regis_start' => '', 'regis_end' => '']),
            4,
            '2026-06-15'
        );

        $this->assertFalse($state['allowed']);
        $this->assertStringContainsString('valid registration start and end dates', $state['reason']);
    }
}
