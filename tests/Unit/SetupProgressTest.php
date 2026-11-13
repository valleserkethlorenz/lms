<?php
/**
 * Tests for the Setup progress model (includes/setupProgress.php).
 *
 * The model feeds the progress bar and the five milestone cards. It must read
 * only the counters it is given: no database, no stored state.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/setupProgress.php';

class SetupProgressTest extends TestCase
{
    /** @param array<int,int> $groupTeams teams per group */
    private static function stage(int $id, string $type, array $groupTeams = []): array
    {
        $groups = [];
        foreach ($groupTeams as $index => $teamCount) {
            $teams = [];
            for ($i = 0; $i < $teamCount; $i++) $teams[] = ['team_id' => $id * 100 + $index * 10 + $i];
            $groups[] = ['id' => $id * 10 + $index, 'name' => 'Group ' . chr(65 + $index), 'teams' => $teams];
        }
        return ['id' => $id, 'name' => 'Stage ' . $id, 'stage_type' => $type, 'groups' => $groups];
    }

    /** @param array<int,int> $matches stage id => total matches */
    private static function stats(array $matches, array $completed = []): array
    {
        $stats = [];
        foreach ($matches as $stageId => $total) {
            $done = (int) ($completed[$stageId] ?? 0);
            $stats[$stageId] = ['matches' => $total, 'completed' => $done, 'complete' => $total > 0 && $done >= $total];
        }
        return $stats;
    }

    public function testEmptyLeagueStartsAtTheStructure(): void
    {
        $model = setupProgressModel([]);

        $this->assertSame(0, $model['percent']);
        $this->assertSame('structure', $model['open_step']);
        $this->assertSame(5, count($model['steps']));
        $this->assertSame('structure', $model['steps'][0]['key']);
        $this->assertSame('is-current', $model['steps'][0]['state']);
        $this->assertSame(0, $model['completed']);
        $this->assertFalse($model['done']);
        $this->assertSame('Choose the competition structure', $model['next']['label']);
    }

    public function testStageWithoutGroupsLeavesTheStagesStepCurrent(): void
    {
        $model = setupProgressModel(['stages' => [self::stage(1, 'group')]]);

        $this->assertSame(15, $model['percent']);
        $this->assertSame('stages', $model['open_step']);
        $this->assertSame('is-done', $model['steps'][0]['state']);
        $this->assertSame('is-current', $model['steps'][1]['state']);
        $this->assertStringContainsString('Create the groups', $model['next']['label']);
    }

    public function testReadyStageWaitsForApprovedTeams(): void
    {
        $model = setupProgressModel([
            'stages' => [self::stage(1, 'group', [0])],
            'approved_teams' => 0,
        ]);

        $this->assertSame(35, $model['percent']);
        $this->assertSame('teams', $model['open_step']);
        $this->assertSame('Approve the teams that registered', $model['next']['label']);
    }

    public function testPlacingTeamsMovesTheBar(): void
    {
        $partlyPlaced = setupProgressModel([
            'stages' => [self::stage(1, 'group', [2, 0])],
            'approved_teams' => 4,
            'assigned_teams' => 2,
        ]);
        $this->assertSame(45, $partlyPlaced['percent']);
        $this->assertSame(2, $partlyPlaced['placed_teams']);
        $this->assertSame('teams', $partlyPlaced['open_step']);
        $this->assertSame('is-current', $partlyPlaced['steps'][2]['state']);

        $fullyPlaced = setupProgressModel([
            'stages' => [self::stage(1, 'group', [2, 2])],
            'approved_teams' => 4,
            'assigned_teams' => 4,
        ]);
        $this->assertSame(55, $fullyPlaced['percent']);
        $this->assertSame('matches', $fullyPlaced['open_step']);
    }

    public function testGeneratedMatchesAndPartialResults(): void
    {
        $model = setupProgressModel([
            'stages' => [self::stage(1, 'group', [2, 2])],
            'stage_stats' => self::stats([1 => 6], [1 => 3]),
            'approved_teams' => 4,
            'matches' => 6,
        ]);

        $this->assertSame(90, $model['percent']);
        $this->assertSame('finish', $model['open_step']);
        $this->assertSame(3, $model['completed_matches']);
        $this->assertSame(6, $model['played']);
        $this->assertFalse($model['done']);
    }

    public function testFullyPlayedTournamentIsDone(): void
    {
        $model = setupProgressModel([
            'stages' => [self::stage(1, 'group', [2, 2])],
            'stage_stats' => self::stats([1 => 6], [1 => 6]),
            'approved_teams' => 4,
            'matches' => 6,
        ]);

        $this->assertSame(100, $model['percent']);
        $this->assertTrue($model['done']);
        $this->assertSame(5, $model['completed']);
        foreach ($model['steps'] as $step) {
            $this->assertSame('is-done', $step['state']);
        }
        $this->assertSame('Open standings', $model['next']['cta']);
    }

    public function testBracketStageCountsTeamsWithoutGroups(): void
    {
        $model = setupProgressModel([
            'stages' => [self::stage(1, 'knockout')],
            'approved_teams' => 8,
        ]);

        $this->assertSame(55, $model['percent']);
        $this->assertSame(8, $model['placed_teams']);
        $this->assertSame('matches', $model['open_step']);
        $this->assertStringContainsString('Stage 1', $model['next']['label']);
    }

    public function testMultiStageLeagueScoresMatchCoverage(): void
    {
        $model = setupProgressModel([
            'stages' => [self::stage(1, 'group', [2, 2]), self::stage(2, 'knockout')],
            'stage_stats' => self::stats([1 => 6], [1 => 6]),
            'approved_teams' => 4,
            'matches' => 6,
        ]);

        // 15 structure + 20 stages + 20 teams + 12.5 match coverage + 20 results.
        $this->assertSame(88, $model['percent']);
        $this->assertFalse($model['done'], 'The bracket stage has no matches yet');
        $this->assertSame('finish', $model['open_step']);
    }

    public function testStageTypesThatNeedGroups(): void
    {
        $this->assertTrue(setupProgressStageNeedsGroups('group'));
        $this->assertTrue(setupProgressStageNeedsGroups('crossover'));
        $this->assertFalse(setupProgressStageNeedsGroups('knockout'));
        $this->assertFalse(setupProgressStageNeedsGroups('placement'));
    }

    public function testTaskAwareNextActionsOverrideTheGenericResultBanner(): void
    {
        $advance = setupProgressModel([
            'stages' => [self::stage(1, 'group', [2, 2]), self::stage(2, 'knockout')],
            'stage_stats' => self::stats([1 => 6], [1 => 6]),
            'approved_teams' => 4,
            'matches' => 6,
            'task' => 'advance',
            'next_stage_name' => 'Knockout',
        ]);
        $this->assertSame('Advance the qualifiers into Knockout', $advance['next']['label']);
        $this->assertSame('Advance qualifiers', $advance['next']['cta']);

        $advanceNext = setupProgressModel([
            'stages' => [self::stage(1, 'group', [2, 2])],
            'stage_stats' => self::stats([1 => 6], [1 => 6]),
            'approved_teams' => 4,
            'matches' => 6,
            'task' => 'advance_and_next',
        ]);
        $this->assertSame(100, $advanceNext['percent']);
        $this->assertSame('Set up the next stage', $advanceNext['next']['cta']);

        $place = setupProgressModel([
            'stages' => [self::stage(1, 'knockout'), self::stage(2, 'group', [0, 0])],
            'stage_stats' => self::stats([1 => 3], [1 => 3]),
            'approved_teams' => 4,
            'matches' => 3,
            'task' => 'place_teams',
            'active_stage_name' => 'Group Stage',
        ]);
        $this->assertSame('Place the teams in Group Stage', $place['next']['label']);
        $this->assertSame('teams', $place['next']['step']);
    }

    public function testSwissRoundRemainsTheNextActionUntilThePlanFinishes(): void
    {
        $stage = self::stage(1, 'group', [8]);
        $stage['stage_format'] = 'swiss';

        $model = setupProgressModel([
            'stages' => [$stage],
            'stage_stats' => self::stats([1 => 3], [1 => 3]),
            'approved_teams' => 8,
            'assigned_teams' => 8,
            'matches' => 3,
            'task' => 'generate_swiss_round',
            'active_stage_name' => 'Stage 1',
        ]);

        $this->assertSame('matches', $model['next']['step']);
        $this->assertStringContainsString('next Swiss round', $model['next']['label']);
        $this->assertNotSame('Setup complete — the tournament has been played', $model['next']['label']);
    }
}
