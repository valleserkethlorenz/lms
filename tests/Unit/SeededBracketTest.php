<?php
/**
 * Tests for the seeded single-elimination bracket helpers.
 * Located in: includes/stageHelper.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stageHelper.php';

class SeededBracketTest extends TestCase
{
    public function testPowerOfTwoBracketsPairTopAgainstBottom(): void
    {
        $this->assertSame([[1, 2]], seededBracketPairs(2));
        $this->assertSame([[1, 4], [2, 3]], seededBracketPairs(4));

        $this->assertSame(
            [[1, 8], [4, 5], [2, 7], [3, 6]],
            seededBracketPairs(8)
        );

        $pairs16 = seededBracketPairs(16);
        $this->assertCount(8, $pairs16);
        $this->assertSame([1, 16], $pairs16[0]);
        $this->assertSame([8, 9], $pairs16[1]);
    }

    public function testByesAreMarkedWithZero(): void
    {
        $this->assertSame([[1, 0], [2, 3]], seededBracketPairs(3));
        $this->assertSame([[1, 0], [4, 5], [2, 0], [3, 0]], seededBracketPairs(5));
        $this->assertSame([[1, 0], [4, 5], [2, 0], [3, 6]], seededBracketPairs(6));
        $this->assertSame([[1, 0], [4, 5], [2, 7], [3, 6]], seededBracketPairs(7));
    }

    public function testEverySeedAppearsExactlyOnce(): void
    {
        foreach ([2, 3, 4, 5, 6, 7, 8, 9, 12, 16] as $teamCount) {
            $seeds = [];
            foreach (seededBracketPairs($teamCount) as $pair) {
                foreach ($pair as $seed) {
                    if ($seed > 0) $seeds[] = $seed;
                }
            }
            sort($seeds);
            $this->assertSame(range(1, $teamCount), $seeds, "Seeds are incomplete for $teamCount teams");
        }
    }

    public function testRoundSizesFeedTheNextRound(): void
    {
        $this->assertSame([1], seededBracketRoundSizes(2));
        $this->assertSame([2, 1], seededBracketRoundSizes(3));
        $this->assertSame([2, 1], seededBracketRoundSizes(4));
        $this->assertSame([4, 2, 1], seededBracketRoundSizes(5));
        $this->assertSame([4, 2, 1], seededBracketRoundSizes(6));
        $this->assertSame([4, 2, 1], seededBracketRoundSizes(8));
        $this->assertSame([8, 4, 2, 1], seededBracketRoundSizes(16));
    }

    public function testRoundOneAlwaysSuppliesTheNextRound(): void
    {
        foreach ([2, 3, 4, 5, 6, 7, 8, 9, 12, 16] as $teamCount) {
            $roundSizes = seededBracketRoundSizes($teamCount);
            $this->assertCount($roundSizes[0], seededBracketPairs($teamCount), "Round 1 row count for $teamCount teams");
            if (count($roundSizes) > 1) {
                $this->assertSame($roundSizes[0], $roundSizes[1] * 2, "Round 1 must feed round 2 for $teamCount teams");
            }
        }
    }

    public function testInvalidTeamCountsReturnNoBracket(): void
    {
        $this->assertSame([], seededBracketPairs(0));
        $this->assertSame([], seededBracketPairs(1));
        $this->assertSame([], seededBracketRoundSizes(1));
    }
}
