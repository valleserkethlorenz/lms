<?php
/**
 * Tests for the single Setup state source (includes/leagueSetupState.php).
 *
 * The page used to derive its step flags in three places; these tests pin the
 * one derivation that is left: which steps are visible, which are done, and
 * which capabilities the chosen structure actually has.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/leagueSetupState.php';

class LeagueSetupStateTest extends TestCase
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

    public function testEmptyLeagueStartsAtFormatAndMayChooseAStructure(): void
    {
        $state = leagueSetupState(['approved_teams' => 8]);

        $this->assertSame('format', $state['active_step']);
        $this->assertSame(['format', 'teams', 'matches'], $state['step_keys']);
        $this->assertSame(0, $state['percent']);
        $this->assertTrue($state['capabilities']['choose_structure']);
        $this->assertFalse($state['capabilities']['generate']);
        $this->assertFalse($state['capabilities']['promote']);
        $this->assertFalse($state['capabilities']['create_playoffs']);
        $this->assertFalse($state['capabilities']['place_teams']);
    }

    public function testGroupStageLeagueGetsTheTeamsStep(): void
    {
        $state = leagueSetupState([
            'stages'              => [self::stage(1, 'group', [0])],
            'approved_teams'      => 8,
            'structure_key'       => 'round_robin',
            'structure_is_preset' => true,
        ]);

        $this->assertSame(['format', 'teams', 'matches'], $state['step_keys']);
        $this->assertTrue($state['done']['format']);
        $this->assertFalse($state['done']['teams']);
        $this->assertSame('teams', $state['active_step']);
        $this->assertTrue($state['capabilities']['place_teams']);
        $this->assertSame('Round robin', $state['structure']['title']);
    }

    public function testBracketOnlyLeagueSkipsTheTeamsStep(): void
    {
        $state = leagueSetupState([
            'stages'              => [self::stage(1, 'knockout')],
            'approved_teams'      => 8,
            'structure_key'       => 'single_elimination',
            'structure_is_preset' => true,
        ]);

        $this->assertSame(['format', 'matches'], $state['step_keys']);
        $this->assertSame('matches', $state['active_step']);
        $this->assertFalse($state['capabilities']['place_teams']);
        $this->assertTrue($state['capabilities']['generate']);
    }

    public function testBracketOnlyProgressCountsOnlyVisibleSteps(): void
    {
        $state = leagueSetupState([
            'stages'         => [self::stage(1, 'knockout')],
            'stage_stats'    => self::stats([1 => 4]),
            'approved_teams' => 8,
            'matches'        => 4,
        ]);

        $this->assertSame(['format', 'matches'], $state['step_keys']);
        $this->assertSame(100, $state['percent']);
        $this->assertSame(2, $state['done_count']);
    }

    public function testStructureIsLockedOnceMatchesExist(): void
    {
        $state = leagueSetupState([
            'stages'         => [self::stage(1, 'group', [8])],
            'stage_stats'    => self::stats([1 => 4]),
            'approved_teams' => 8,
            'matches'        => 4,
        ]);

        $this->assertFalse($state['capabilities']['choose_structure']);
        $this->assertTrue($state['done']['matches']);
        $this->assertSame('matches', $state['active_step']);

        /* An explicit ?step=teams link still opens Teams — the page never
           blocks a revisit, it only decides where an unaddressed page lands. */
        $revisit = leagueSetupState([
            'stages'         => [self::stage(1, 'group', [8])],
            'matches'        => 4,
            'requested_step' => 'teams',
        ]);
        $this->assertSame('teams', $revisit['active_step']);
    }

    public function testCreatePlayoffsNeedsAFinishedSingleGroupRoundRobin(): void
    {
        $running = leagueSetupState([
            'stages'         => [self::stage(1, 'group', [8])],
            'stage_stats'    => self::stats([1 => 4], [1 => 2]),
            'approved_teams' => 8,
            'matches'        => 4,
        ]);
        $this->assertFalse($running['capabilities']['create_playoffs']);

        $finished = leagueSetupState([
            'stages'         => [self::stage(1, 'group', [8])],
            'stage_stats'    => self::stats([1 => 4], [1 => 4]),
            'approved_teams' => 8,
            'matches'        => 4,
        ]);
        $this->assertTrue($finished['capabilities']['create_playoffs']);

        $startedKnockout = leagueSetupState([
            'stages'         => [self::stage(1, 'group', [8]), self::stage(2, 'knockout', [4])],
            'stage_stats'    => self::stats([1 => 4, 2 => 2], [1 => 4]),
            'approved_teams' => 8,
            'matches'        => 6,
        ]);
        $this->assertFalse($startedKnockout['capabilities']['create_playoffs']);
    }

    public function testPromotionNeedsASecondStage(): void
    {
        $one = leagueSetupState(['stages' => [self::stage(1, 'group', [8])], 'matches' => 4]);
        $this->assertFalse($one['capabilities']['promote']);

        $two = leagueSetupState([
            'stages'  => [self::stage(1, 'group', [8]), self::stage(2, 'knockout')],
            'matches' => 4,
        ]);
        $this->assertTrue($two['capabilities']['promote']);
    }

    public function testLegacyStepLinksMapToTheVisibleSteps(): void
    {
        $legacy = leagueSetupState([
            'stages'         => [self::stage(1, 'group', [8])],
            'stage_stats'    => self::stats([1 => 4]),
            'approved_teams' => 8,
            'matches'        => 4,
            'requested_step' => 'finish',
        ]);
        $this->assertSame('matches', $legacy['active_step']);

        $oldStructure = leagueSetupState(['requested_step' => 'structure']);
        $this->assertSame('format', $oldStructure['active_step']);

        $unknown = leagueSetupState(['requested_step' => 'nonsense']);
        $this->assertContains($unknown['active_step'], ['format', 'teams', 'matches']);
    }

    public function testCustomSkeletonIsReportedAsCustom(): void
    {
        $state = leagueSetupState([
            'stages'        => [self::stage(1, 'group', [8])],
            'structure_key' => null,
        ]);

        $this->assertSame('Custom structure', $state['structure']['title']);
        $this->assertFalse($state['structure']['is_preset']);
    }

}
