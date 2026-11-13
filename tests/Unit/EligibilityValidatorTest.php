<?php
/**
 * Tests for the simplified roster validation rules.
 *
 * Team composition (regulars / Liberos / officials) is intentionally not
 * enforced at registration time. Every submitted roster row counts as a
 * player, and the free-text role is stored for display only.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/eligibilityValidator.php';

class EligibilityValidatorTest extends TestCase
{
    private function names(int $count): array
    {
        $names = [];
        for ($i = 1; $i <= $count; $i++) {
            $names[] = 'Player ' . $i;
        }
        return $names;
    }

    public function testLeague27RosterWithinConfiguredRangeIsValid(): void
    {
        $league = ['min_player' => 6, 'max_player' => 12];
        $result = validatePlayerCount($this->names(6), $league);

        $this->assertTrue($result['valid']);
    }

    public function testFiveRowRosterWithManagerRoleIsValid(): void
    {
        // The role label is not part of the count. A five-row roster remains five players
        // even when one row says Manager or Coach.
        $league = ['min_player' => 5, 'max_player' => 15];
        $result = validatePlayerCount($this->names(5), $league);

        $this->assertTrue($result['valid']);
    }

    public function testRosterBelowMinimumIsRejected(): void
    {
        $league = ['min_player' => 6, 'max_player' => 12];
        $result = validatePlayerCount($this->names(5), $league);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Minimum 6', $result['message']);
        $this->assertStringContainsString('5 player', $result['message']);
    }

    public function testRosterAboveMaximumIsRejected(): void
    {
        $league = ['min_player' => 6, 'max_player' => 12];
        $result = validatePlayerCount($this->names(13), $league);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Maximum 12', $result['message']);
    }

    public function testZeroMaximumMeansUnlimited(): void
    {
        $league = ['min_player' => 5, 'max_player' => 0];
        $result = validatePlayerCount($this->names(20), $league);

        $this->assertTrue($result['valid']);
    }

    public function testFixedRosterSizeReportsAnExactRequirement(): void
    {
        // Pickleball doubles: min and max are both 2, so only a 2-player roster passes.
        $league = ['min_player' => 2, 'max_player' => 2];

        $exact = validatePlayerCount($this->names(2), $league);
        $this->assertTrue($exact['valid']);

        $tooFew = validatePlayerCount($this->names(1), $league);
        $this->assertFalse($tooFew['valid']);
        $this->assertStringContainsString('Exactly 2 players required', $tooFew['message']);

        $tooMany = validatePlayerCount($this->names(3), $league);
        $this->assertFalse($tooMany['valid']);
        $this->assertStringContainsString('Exactly 2 players required', $tooMany['message']);
    }

    public function testAgeRulesApplyToEveryRosterRow(): void
    {
        $league = ['min_age' => 16, 'max_age' => 50];
        $result = validatePlayerAges([20, 70], $league);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Player 2', $result['message']);
    }

    public function testValidAgesPass(): void
    {
        $league = ['min_age' => 16, 'max_age' => 50];
        $result = validatePlayerAges([20, 30], $league);

        $this->assertTrue($result['valid']);
    }

    public function testLeagueCategoryIsStillEnforced(): void
    {
        $result = validateLeagueCategory(['Male', 'Male'], ['category' => 'Female']);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Female-only', $result['message']);
    }

    public function testCompositionHelpersAreNoLongerDefined(): void
    {
        $this->assertFalse(function_exists('validateRosterComposition'));
        $this->assertFalse(function_exists('validateRosterWithOfficials'));
        $this->assertFalse(function_exists('countRosterRoles'));
        $this->assertFalse(function_exists('isOfficialsRole'));
        $this->assertFalse(function_exists('teamHasOfficial'));
    }
}
