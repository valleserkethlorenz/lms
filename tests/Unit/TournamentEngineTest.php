<?php
/**
 * Tests for the tournament format engine (bracket graphs, Swiss, round robin).
 * Located in: includes/tournamentEngine.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/tournamentEngine.php';

class TournamentEngineTest extends TestCase
{
    /* ---------------------------------------------------------------------
     * Single elimination
     * ------------------------------------------------------------------ */

    public function testSingleEliminationBuildsOneNodePerMatch(): void
    {
        foreach ([2 => 1, 3 => 3, 4 => 3, 5 => 7, 8 => 7, 16 => 15] as $teams => $matches) {
            $plan = singleEliminationPlan($teams);
            $this->assertCount($matches, $plan, "$teams teams need $matches bracket matches");
        }
    }

    public function testSingleEliminationFinalHasNoWinnerTarget(): void
    {
        $plan  = singleEliminationPlan(8);
        $final = $plan['W-R3-M1'];

        $this->assertNull($final['next'], 'The final is the last node');
        $this->assertSame('Final', $final['label']);
        $this->assertSame('Semifinal', $plan['W-R2-M1']['label']);
    }

    public function testSingleEliminationByesAreEmptyFirstRoundSlots(): void
    {
        $plan = singleEliminationPlan(6);

        $this->assertSame(['type' => 'seed', 'seed' => 1], $plan['W-R1-M1']['slots']['a']);
        $this->assertSame(['type' => 'bye'], $plan['W-R1-M1']['slots']['b'], 'Seed 1 gets a bye');

        $seeds = [];
        foreach ($plan as $node) {
            if ($node['round'] !== 1) continue;
            foreach (['a', 'b'] as $slot) {
                if (($node['slots'][$slot]['type'] ?? '') === 'seed') $seeds[] = $node['slots'][$slot]['seed'];
            }
        }
        sort($seeds);
        $this->assertSame(range(1, 6), $seeds, 'Every seed appears exactly once');
    }

    public function testEveryWinnerTargetExists(): void
    {
        foreach ([2, 3, 5, 8, 12, 16] as $teams) {
            $plan = singleEliminationPlan($teams);
            foreach ($plan as $key => $node) {
                if ($node['next'] === null) continue;
                $this->assertArrayHasKey($node['next']['node'], $plan, "$key points at a missing node");
                $this->assertContains($node['next']['slot'], ['a', 'b']);
            }
        }
    }

    /* ---------------------------------------------------------------------
     * Double elimination
     * ------------------------------------------------------------------ */

    public function testDoubleEliminationAddsLosersBracketAndGrandFinal(): void
    {
        $plan = doubleEliminationPlan(8);

        $winners = array_filter($plan, static fn(array $n) => $n['side'] === 'winners');
        $losers  = array_filter($plan, static fn(array $n) => $n['side'] === 'losers');

        $this->assertCount(7, $winners, '8 teams need 7 winners-bracket matches');
        $this->assertCount(6, $losers, '8 teams need 6 losers-bracket matches');
        $this->assertArrayHasKey('GF-M1', $plan);
        $this->assertCount(14, $plan, '13 matches plus the grand final');

        $this->assertSame('W-R3-M1', $plan['GF-M1']['slots']['a']['node']);
        $this->assertSame('L-R4-M1', $plan['GF-M1']['slots']['b']['node']);
    }

    public function testDoubleEliminationDropsEveryLoserIntoTheLosersBracket(): void
    {
        $plan = doubleEliminationPlan(16);

        foreach ($plan as $key => $node) {
            if ($node['side'] !== 'winners') continue;
            $this->assertNotNull($node['loser_next'], "$key must send its loser somewhere");

            if ($node['loser_next'] !== null) {
                $this->assertArrayHasKey($node['loser_next']['node'], $plan, "$key drops its loser into a missing node");
                $this->assertSame('losers', $plan[$node['loser_next']['node']]['side'], 'Winners-bracket losers drop into the losers bracket');
            }
        }

        // The last losers round feeds the grand final.
        $this->assertSame('winner', $plan['GF-M1']['slots']['b']['type']);
        $this->assertSame('GF-M1', $plan['L-R6-M1']['next']['node'], 'The last losers round feeds the grand final');
    }

    public function testDoubleEliminationTwoTeamsUseTheFinalDirectly(): void
    {
        $plan = doubleEliminationPlan(2);

        $this->assertCount(2, $plan);
        $this->assertSame('GF-M1', $plan['W-R1-M1']['next']['node']);
        $this->assertSame('GF-M1', $plan['W-R1-M1']['loser_next']['node']);
        $this->assertSame('loser', $plan['GF-M1']['slots']['b']['type']);
    }

    public function testDoubleEliminationLosersRoundsHalveEveryMinorRound(): void
    {
        foreach ([4, 8, 16, 32] as $teams) {
            $plan = doubleEliminationPlan($teams);
            $counts = [];
            foreach ($plan as $node) {
                if ($node['side'] !== 'losers') continue;
                $counts[$node['round']] = ($counts[$node['round']] ?? 0) + 1;
            }
            ksort($counts);

            $this->assertSame(
                max(0, 2 * (bracketRoundCount($teams) - 1)),
                count($counts),
                "Losers rounds for $teams teams"
            );

            // Minor rounds (1, 3, 5 ...) halve the previous major round.
            foreach ($counts as $round => $count) {
                if ($round > 1 && $round % 2 === 1) {
                    $this->assertSame(intdiv($counts[$round - 2], 2), $count, "Losers round $round must halve");
                }
            }
        }
    }

    public function testBracketPlanForFormatPicksTheRightBuilder(): void
    {
        $this->assertSame([], bracketPlanForFormat('round_robin', 8));
        $this->assertSame(
            array_keys(singleEliminationPlan(8)),
            array_keys(bracketPlanForFormat('single_elimination', 8))
        );
        $this->assertSame(
            array_keys(doubleEliminationPlan(8)),
            array_keys(bracketPlanForFormat('double_elimination', 8))
        );
    }

    /**
     * Play an 8-team double-elimination bracket to the end in memory. The
     * stronger (lower) seed always wins, so the champion stays unbeaten and
     * every other team must collect exactly two losses before going out.
     */
    public function testDoubleEliminationGraphPlaysToAChampion(): void
    {
        $plan   = doubleEliminationPlan(8);
        $teams  = range(1, 8);
        $state  = [];
        $losses = array_fill_keys($teams, 0);

        foreach ($plan as $key => $node) {
            foreach (['a', 'b'] as $slot) {
                if (($node['slots'][$slot]['type'] ?? '') === 'seed') {
                    $state[$key][$slot] = $teams[$node['slots'][$slot]['seed'] - 1];
                }
            }
        }

        $progress = true;
        while ($progress) {
            $progress = false;
            foreach ($plan as $key => $node) {
                if (!empty($state[$key]['done'])) continue;
                $a = (int)($state[$key]['a'] ?? 0);
                $b = (int)($state[$key]['b'] ?? 0);
                if ($a <= 0 || $b <= 0) continue;

                $winner = min($a, $b);
                $loser  = max($a, $b);
                $losses[$loser]++;

                $state[$key]['done']   = true;
                $state[$key]['winner'] = $winner;

                if ($node['next'] !== null && isset($plan[$node['next']['node']])) {
                    $state[$node['next']['node']][$node['next']['slot']] = $winner;
                }
                if ($node['loser_next'] !== null && isset($plan[$node['loser_next']['node']])) {
                    $state[$node['loser_next']['node']][$node['loser_next']['slot']] = $loser;
                }
                $progress = true;
            }
        }

        $played = count(array_filter($state, static fn(array $node) => !empty($node['done'])));
        $this->assertSame(14, $played, '8 teams play 13 matches plus the grand final');
        $this->assertSame(1, $state['GF-M1']['winner'], 'The best seed wins the grand final');
        $this->assertSame(0, $losses[1], 'The champion never lost');

        for ($team = 2; $team <= 8; $team++) {
            $this->assertSame(2, $losses[$team], "Team $team is out after exactly two losses");
        }
    }

    /* ---------------------------------------------------------------------
     * Round robin
     * ------------------------------------------------------------------ */

    public function testRoundRobinRoundsCoverEveryPairExactlyOnce(): void
    {
        foreach ([4, 5, 6, 8] as $teams) {
            $ids = range(1, $teams);
            $rounds = roundRobinRounds($ids);

            $pairs = [];
            foreach ($rounds as $round => $roundPairs) {
                $seen = [];
                foreach ($roundPairs as [$a, $b]) {
                    $this->assertNotContains($a, $seen, "Team $a plays twice in round $round");
                    $this->assertNotContains($b, $seen, "Team $b plays twice in round $round");
                    $seen[] = $a;
                    $seen[] = $b;
                    $key = min($a, $b) . '-' . max($a, $b);
                    $pairs[$key] = ($pairs[$key] ?? 0) + 1;
                }
            }

            $expected = (int)($teams * ($teams - 1) / 2);
            $this->assertCount($expected, $pairs, "$teams teams need $expected different pairings");
            $this->assertSame([1], array_values(array_unique(array_values($pairs))), 'No pairing repeats');
            $this->assertCount($teams % 2 === 0 ? $teams - 1 : $teams, $rounds, 'One bye per round for odd counts');
        }
    }

    /* ---------------------------------------------------------------------
     * Swiss
     * ------------------------------------------------------------------ */

    public function testSwissFirstRoundPairsTopHalfAgainstBottomHalf(): void
    {
        $first = swissFirstRound([1, 2, 3, 4, 5, 6]);

        $this->assertSame([
            ['a' => 1, 'b' => 4],
            ['a' => 2, 'b' => 5],
            ['a' => 3, 'b' => 6],
        ], $first['pairs']);
        $this->assertNull($first['bye']);
    }

    public function testSwissOddTeamCountGivesTheLowestSeedTheBye(): void
    {
        $first = swissFirstRound([1, 2, 3, 4, 5]);

        $this->assertSame(5, $first['bye']);
        $this->assertCount(2, $first['pairs']);
    }

    public function testSwissPairsEqualRecordsFirst(): void
    {
        $result = swissPairings([
            ['team_id' => 1, 'points' => 6],
            ['team_id' => 2, 'points' => 6],
            ['team_id' => 3, 'points' => 3],
            ['team_id' => 4, 'points' => 3],
            ['team_id' => 5, 'points' => 0],
            ['team_id' => 6, 'points' => 0],
        ]);

        $this->assertSame([
            ['a' => 1, 'b' => 2],
            ['a' => 3, 'b' => 4],
            ['a' => 5, 'b' => 6],
        ], $result['pairs']);
        $this->assertNull($result['bye']);
    }

    public function testSwissAvoidsRematchesWhenAnAlternativeExists(): void
    {
        $result = swissPairings(
            [
                ['team_id' => 1, 'points' => 3],
                ['team_id' => 2, 'points' => 3],
                ['team_id' => 3, 'points' => 3],
                ['team_id' => 4, 'points' => 3],
            ],
            [['a' => 1, 'b' => 3], ['a' => 2, 'b' => 4]]
        );

        $pairs = array_map(static fn(array $p) => min($p['a'], $p['b']) . '-' . max($p['a'], $p['b']), $result['pairs']);
        $this->assertCount(2, $pairs);
        $this->assertNotContains('1-3', $pairs);
        $this->assertNotContains('2-4', $pairs);
    }

    public function testSwissGivesTheByeToATeamThatHasNotHadOne(): void
    {
        $result = swissPairings([
            ['team_id' => 1, 'points' => 3],
            ['team_id' => 2, 'points' => 3],
            ['team_id' => 3, 'points' => 0],
            ['team_id' => 4, 'points' => 0, 'had_bye' => true],
            ['team_id' => 5, 'points' => 0],
        ]);

        $this->assertSame(5, $result['bye'], 'Team 4 already had a bye, so team 5 takes this one');
        $this->assertCount(2, $result['pairs']);
    }

    public function testSwissRoundCountFollowsTheLogRule(): void
    {
        $this->assertSame(1, swissRoundCount(2));
        $this->assertSame(3, swissRoundCount(5));
        $this->assertSame(3, swissRoundCount(8));
        $this->assertSame(4, swissRoundCount(9));
        $this->assertSame(5, swissRoundCount(20));
    }

    /* ---------------------------------------------------------------------
     * Format registry
     * ------------------------------------------------------------------ */

    public function testFormatRegistryCoversTheFourCompetitionFormats(): void
    {
        $formats = tournamentFormatOptions();

        $this->assertArrayHasKey('round_robin', $formats);
        $this->assertArrayHasKey('swiss', $formats);
        $this->assertArrayHasKey('single_elimination', $formats);
        $this->assertArrayHasKey('double_elimination', $formats);
        $this->assertArrayNotHasKey('placement', $formats, 'Placement is not a selectable competition format');
    }

    public function testStageFormatFallsBackToTheStageType(): void
    {
        $this->assertSame('single_elimination', stageFormat(['stage_type' => 'knockout']));
        $this->assertSame('round_robin', stageFormat(['stage_type' => 'group']));
        $this->assertSame('placement', stageFormat(['stage_type' => 'placement']));
        $this->assertSame('swiss', stageFormat(['stage_type' => 'group', 'stage_format' => 'swiss']));
        $this->assertSame('double_elimination', stageFormat(['stage_type' => 'knockout', 'stage_format' => 'double_elimination']));

        // A legacy row that still carries the column default (round_robin) must
        // not turn a knockout stage into a table.
        $this->assertSame(
            'single_elimination',
            stageFormat(['stage_type' => 'knockout', 'stage_format' => 'round_robin']),
            'A knockout row keeps its bracket format whatever the default column says'
        );
        // A crossover pool is a group-style stage, so round robin stays valid.
        $this->assertSame('round_robin', stageFormat(['stage_type' => 'crossover', 'stage_format' => 'round_robin']));
    }

    public function testFormatHelpersKnowWhichStagesUseBrackets(): void
    {
        $this->assertTrue(stageFormatUsesBracket('single_elimination'));
        $this->assertTrue(stageFormatUsesBracket('double_elimination'));
        $this->assertFalse(stageFormatUsesBracket('round_robin'));

        $this->assertTrue(stageFormatUsesTable('round_robin'));
        $this->assertTrue(stageFormatUsesTable('swiss'));
        $this->assertFalse(stageFormatUsesTable('double_elimination'));

        $this->assertSame('knockout', stageTypeForFormat('double_elimination'));
        $this->assertSame('group', stageTypeForFormat('swiss'));
    }

    /* ---------------------------------------------------------------------
     * Advance Teams seeding board
     * ------------------------------------------------------------------ */

    /** @return array{0:array,1:array} source groups + their standings */
    private static function twoGroupTable(): array
    {
        $groups = [
            ['id' => 11, 'name' => 'Group A'],
            ['id' => 12, 'name' => 'Group B'],
        ];
        $standings = [
            [
                ['id' => 1, 'team_name' => 'A1', 'points' => 9, 'difference' => 8],
                ['id' => 2, 'team_name' => 'A2', 'points' => 6, 'difference' => 2],
                ['id' => 3, 'team_name' => 'A3', 'points' => 3, 'difference' => -10],
            ],
            [
                ['id' => 4, 'team_name' => 'B1', 'points' => 9, 'difference' => 7],
                ['id' => 5, 'team_name' => 'B2', 'points' => 6, 'difference' => 1],
                ['id' => 6, 'team_name' => 'B3', 'points' => 3, 'difference' => -11],
            ],
        ];
        return [$groups, $standings];
    }

    public function testSlotPlanTakesTopNOfEveryGroup(): void
    {
        [$groups, $standings] = self::twoGroupTable();

        $slots = stageSlotPlan($groups, $standings, 'top_per_group', 2);

        $this->assertSame(['G1-R1', 'G1-R2', 'G2-R1', 'G2-R2'], array_column($slots, 'code'));
        $this->assertSame([1, 2, 4, 5], array_column($slots, 'team_id'));
        $this->assertSame('Group A · 1st', $slots[0]['label']);
    }

    public function testSlotPlanCombinesTheTableForTopOverall(): void
    {
        [$groups, $standings] = self::twoGroupTable();

        $slots = stageSlotPlan($groups, $standings, 'top_overall', 3);

        $this->assertSame(['O-R1', 'O-R2', 'O-R3'], array_column($slots, 'code'));
        $this->assertSame([1, 4, 2], array_column($slots, 'team_id'), 'Points, then difference decide the combined table');
    }

    public function testSlotPlanTakesTheBottomTeamsForAConsolationStage(): void
    {
        [$groups, $standings] = self::twoGroupTable();

        $slots = stageSlotPlan($groups, $standings, 'bottom_consolation', 2);

        $this->assertSame(['B-R1', 'B-R2'], array_column($slots, 'code'));
        $this->assertSame([6, 3], array_column($slots, 'team_id'), 'Bottom first, worst-last');
        $this->assertSame(6, $slots[0]['rank']);
        $this->assertSame(5, $slots[1]['rank']);
    }
}



