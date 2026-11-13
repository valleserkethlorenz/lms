<?php
/**
 * Tests for the roster size labels.
 *
 * A fixed roster (min_player === max_player, e.g. Pickleball doubles) must
 * never be rendered as "2–2"; it reads "Exactly 2 players" instead.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/rosterSize.php';

class RosterSizeTest extends TestCase
{
    public function testFixedRosterIsLabelledExactly(): void
    {
        $this->assertTrue(isFixedRosterSize(2, 2));
        $this->assertSame('Exactly 2 players', rosterSizeLabel(2, 2));
        $this->assertSame('2', rosterSizeShortLabel(2, 2));
        $this->assertSame('exactly 2 players', rosterSizeRequirementText(2, 2));
    }

    public function testRangeRosterKeepsDashLabel(): void
    {
        $this->assertFalse(isFixedRosterSize(5, 15));
        $this->assertSame('5–15 players', rosterSizeLabel(5, 15));
        $this->assertSame('5–15', rosterSizeShortLabel(5, 15));
        $this->assertSame('5 to 15 players', rosterSizeRequirementText(5, 15));
    }

    public function testZeroMaximumMeansUnlimited(): void
    {
        $this->assertFalse(isFixedRosterSize(5, 0));
        $this->assertSame('At least 5 players', rosterSizeLabel(5, 0));
        $this->assertSame('5+', rosterSizeShortLabel(5, 0));
        $this->assertSame('at least 5 players', rosterSizeRequirementText(5, 0));
    }

    public function testSinglePlayerFixedRosterUsesSingularUnit(): void
    {
        $this->assertSame('Exactly 1 player', rosterSizeLabel(1, 1, 'player'));
        $this->assertSame('exactly 1 player', rosterSizeRequirementText(1, 1));
    }

    public function testMaximumBelowMinimumIsClamped(): void
    {
        $this->assertSame(
            ['min' => 6, 'max' => 6, 'fixed' => true, 'unlimited' => false],
            normalizeRosterBounds(6, 4)
        );
    }

    public function testBlankBoundsFallBackToOneMinimum(): void
    {
        $this->assertSame(1, normalizeRosterBounds(null, null)['min']);
    }
}
