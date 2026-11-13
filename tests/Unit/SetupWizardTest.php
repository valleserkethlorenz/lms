<?php
/**
 * Tests for the Setup wizard state machine (includes/setupWizard.php).
 * The wizard decides which single task the Setup page shows.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/setupWizard.php';

class SetupWizardTest extends TestCase
{
    /** @param array<int,int> $groupTeams teams per group */
    private static function stage(int $id, string $type, array $groupTeams = [], ?int $qualifiers = null): array
    {
        $groups = [];
        foreach ($groupTeams as $index => $teamCount) {
            $teams = [];
            for ($i = 0; $i < $teamCount; $i++) $teams[] = ['team_id' => $id * 100 + $index * 10 + $i];
            $groups[] = ['id' => $id * 10 + $index, 'name' => 'Group ' . chr(65 + $index), 'teams' => $teams];
        }
        return [
            'id' => $id, 'name' => 'Stage ' . $id, 'stage_type' => $type,
            'qualifiers_per_group' => $qualifiers, 'groups' => $groups,
        ];
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

    public function testEmptyLeagueAsksForTheStructure(): void
    {
        $task = setupNextTask([], []);

        $this->assertSame('choose_structure', $task['task']);
        $this->assertNull($task['stage']);
        $this->assertSame(0, $task['total']);
    }

    public function testStageWithoutGroupsAsksForGroups(): void
    {
        foreach (['group', 'crossover'] as $type) {
            $task = setupNextTask([self::stage(1, $type)], []);
            $this->assertSame('add_groups', $task['task'], "$type stage must ask for its groups");
        }
    }

    public function testKnockoutStageNeedsNoGroups(): void
    {
        $task = setupNextTask([self::stage(1, 'knockout')], []);

        $this->assertSame('generate', $task['task']);
        $this->assertSame(1, $task['stage_id']);
    }

    public function testFirstStageIsAutoSeededBeforeGenerating(): void
    {
        $task = setupNextTask([self::stage(1, 'group', [0, 0])], []);

        $this->assertSame('generate', $task['task']);
        $this->assertTrue($task['auto_seeds'], 'The first draw seeds the approved teams');
    }

    public function testFinishedGroupStageOffersToAdvanceIntoAnEmptyNextStage(): void
    {
        $stages = [
            self::stage(1, 'group', [5, 5]),
            self::stage(2, 'knockout', [0, 0]),
        ];

        $task = setupNextTask($stages, self::stats([1 => 6], [1 => 6]));

        $this->assertSame('advance', $task['task']);
        $this->assertSame(1, $task['stage_id']);
    }

    public function testNextStageNeedsTeamsWhenNothingCanBePromoted(): void
    {
        $stages = [
            self::stage(1, 'knockout'),            // seeded bracket, no table to rank
            self::stage(2, 'group', [0, 0]),
        ];

        $task = setupNextTask($stages, self::stats([1 => 3], [1 => 3]));

        $this->assertSame('place_teams', $task['task']);
        $this->assertSame(2, $task['stage_id']);
    }

    public function testStageWithTeamsAndNoMatchesIsReadyToGenerate(): void
    {
        $task = setupNextTask([self::stage(1, 'group', [4, 4])], []);

        $this->assertSame('generate', $task['task']);
        $this->assertFalse($task['auto_seeds']);
    }

    public function testStageInProgressWaitsForResults(): void
    {
        $task = setupNextTask([self::stage(1, 'group', [4, 4])], self::stats([1 => 12], [1 => 7]));

        $this->assertSame('await_results', $task['task']);
        $this->assertSame(12, $task['matches']);
        $this->assertSame(7, $task['completed']);
    }

    public function testFinishedStageAdvancesIntoAnExistingNextStage(): void
    {
        $stages = [
            self::stage(1, 'group', [4, 4], 2),
            self::stage(2, 'knockout'),
        ];

        $task = setupNextTask($stages, self::stats([1 => 6], [1 => 6]));

        $this->assertSame('advance', $task['task']);
        $this->assertSame(1, $task['stage_id']);
        $this->assertSame(2, (int) $task['next_stage']['id']);
    }

    public function testFinishedStageWithoutNextStageOffersToCreateOne(): void
    {
        $task = setupNextTask([self::stage(1, 'group', [4, 4])], self::stats([1 => 6], [1 => 6]));

        $this->assertSame('advance_and_next', $task['task']);
    }

    public function testAlreadyAdvancedStageMovesOnToTheNextStage(): void
    {
        $stages = [
            self::stage(1, 'group', [4, 4], 2),
            self::stage(2, 'knockout', [4, 4]),
        ];

        $task = setupNextTask($stages, self::stats([1 => 6], [1 => 6]));

        $this->assertSame('generate', $task['task']);
        $this->assertSame(2, $task['stage_id']);
    }

    public function testEverythingPlayedIsDone(): void
    {
        $stages = [
            self::stage(1, 'group', [4, 4], 2),
            self::stage(2, 'knockout', [4, 4]),
        ];
        $stats = self::stats([1 => 6, 2 => 3], [1 => 6, 2 => 3]);

        $task = setupNextTask($stages, $stats);

        $this->assertSame('done', $task['task']);
    }

    public function testDefaultGroupNamesAreLabelled(): void
    {
        $this->assertSame(['Group A', 'Group B', 'Group C'], defaultStageGroupNames(3));
        $this->assertSame([], defaultStageGroupNames(0));
        $this->assertSame(['Pool 1', 'Pool 2'], defaultStageGroupNames(2, 'crossover'));
    }

    public function testPatternChoicesFollowTheStageType(): void
    {
        $this->assertSame(['round_robin_groups'], stagePatternChoices('group', 2, 2));
        $this->assertSame(['round_robin_groups'], stagePatternChoices('crossover', 2, 2), 'Two pools play a round robin inside each pool');
        $this->assertSame(['crossover_cross'], stagePatternChoices('crossover', 4, 2), 'Four brackets cross A/D and B/C');
        $this->assertSame(['seeded_bracket'], stagePatternChoices('knockout', 0, 1));
        $this->assertSame(['seeded_bracket'], stagePatternChoices('knockout', 1, 2), 'One pool of qualifiers uses a seeded bracket');
        $this->assertSame([], stagePatternChoices('knockout', 0, 2), 'A later knockout stage needs its qualifiers first');
        $this->assertContains('playoff_bracket', stagePatternChoices('knockout', 2, 3));
    }

    public function testSwissStageAsksForTheNextRoundUntilThePlanIsReached(): void
    {
        $stage = self::stage(1, 'group', [4, 4]);
        $stage['stage_format'] = 'swiss';
        $stage['rounds_planned'] = 5;
        $stage['current_round'] = 2;
        $stage['rounds_total'] = 5;

        $task = setupNextTask([$stage], self::stats([1 => 2], [1 => 2]));
        $this->assertSame('generate_swiss_round', $task['task']);
        $this->assertSame(3, (int) $task['round']);

        $stage['current_round'] = 5;
        $task = setupNextTask([$stage], self::stats([1 => 2], [1 => 2]));
        $this->assertSame('advance_and_next', $task['task'], 'The last planned round finishes the Swiss stage');
    }

    public function testSuggestedNextStageFollowsTheCurrentType(): void
    {
        $this->assertSame('knockout', suggestNextStageType('group')['type']);
        $this->assertSame('knockout', suggestNextStageType('crossover')['type']);
        $this->assertSame('placement', suggestNextStageType('knockout')['type']);
        $this->assertNotSame('', suggestNextStageType('group')['name']);
    }
}
