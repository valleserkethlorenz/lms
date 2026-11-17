<?php
/**
 * Tests for the bracket stage catalog that feeds both bracket views.
 * Located in: includes/stageHelper.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/leagueStructure.php';

class BracketStageCatalogTest extends TestCase
{
    /**
     * Every preset that owns a knockout stage must expose it even before its
     * fixtures exist. Presets without a bracket must expose nothing.
     */
    public function testPresetBracketStagesAreCatalogued(): void
    {
        $presets = leagueStructurePresets();

        $expected = [
            'round_robin'          => [],
            'swiss_system'         => [],
            'single_elimination'   => ['Bracket' => 'single_elimination'],
            'double_elimination'   => ['Double Elimination' => 'double_elimination'],
            'round_robin_playoffs' => ['Playoffs' => 'single_elimination'],
            'groups_knockout'      => ['Knockout' => 'single_elimination'],
            'pools_playoffs'       => ['Playoffs' => 'single_elimination'],
            'custom'               => [],
        ];

        foreach ($presets as $key => $preset) {
            $this->assertArrayHasKey($key, $expected, "$key is missing from the catalog expectations");

            $catalog = leagueBracketStageCatalog($preset['stages'] ?? []);
            $actual = [];
            foreach ($catalog as $stage) {
                $actual[$stage['name']] = $stage['format'];
            }
            $this->assertSame($expected[$key], $actual, "$key bracket stages drifted");
        }
    }

    public function testPlayoffPresetsKeepTheirQualifierGroupInTheCatalog(): void
    {
        $presets = leagueStructurePresets();

        $roundRobinPlayoffs = leagueBracketStageCatalog($presets['round_robin_playoffs']['stages']);
        $this->assertCount(1, $roundRobinPlayoffs);
        $this->assertSame(['Qualifiers'], $roundRobinPlayoffs[0]['groups']);

        $groupsKnockout = leagueBracketStageCatalog($presets['groups_knockout']['stages']);
        $this->assertCount(1, $groupsKnockout);
        $this->assertSame(['Qualifiers'], $groupsKnockout[0]['groups']);

        $poolsPlayoffs = leagueBracketStageCatalog($presets['pools_playoffs']['stages']);
        $this->assertCount(1, $poolsPlayoffs);
        $this->assertSame([], $poolsPlayoffs[0]['groups'], 'The playoff stage is filled by promotion');
    }

    public function testCatalogEstimatesBracketEntrantsFromThePrecedingStage(): void
    {
        $presets = leagueStructurePresets();

        $cases = [
            'single_elimination'   => 8,
            'double_elimination'   => 8,
            'round_robin_playoffs' => 4,
            'groups_knockout'      => 4,
            'pools_playoffs'       => 8,
        ];

        foreach ($cases as $key => $expectedEntries) {
            $catalog = leagueBracketStageCatalog($presets[$key]['stages'], 8);
            $this->assertCount(1, $catalog, "$key must expose one bracket stage");
            $this->assertSame(
                $expectedEntries,
                $catalog[0]['entries'],
                "$key must size its bracket from the preceding stage or the approved teams"
            );
        }
    }

    public function testPlacementAndTableStagesAreNotBracketStages(): void
    {
        $catalog = leagueBracketStageCatalog([
            ['id' => 4, 'name' => 'League Table', 'stage_type' => 'group', 'stage_format' => 'round_robin', 'display_order' => 1, 'groups' => []],
            ['id' => 7, 'name' => 'Playoffs', 'stage_type' => 'knockout', 'stage_format' => 'single_elimination', 'display_order' => 2, 'groups' => []],
            ['id' => 9, 'name' => 'Battle for Third', 'stage_type' => 'placement', 'stage_format' => 'placement', 'display_order' => 3, 'groups' => []],
        ]);

        $this->assertSame(['Playoffs'], array_column($catalog, 'name'));
        $this->assertSame(['single_elimination'], array_column($catalog, 'format'));
    }

    public function testCatalogUsesStageOrderAndClimbsTheTeamCount(): void
    {
        $catalog = leagueBracketStageCatalog([
            [
                'id' => 12, 'name' => 'Playoffs', 'stage_type' => 'knockout',
                'stage_format' => 'double_elimination', 'display_order' => 2,
                'qualifiers_per_group' => 4,
                'groups' => [
                    ['name' => 'A Pool', 'team_count' => 4],
                    ['name' => 'B Pool', 'team_count' => 3],
                ],
            ],
            [
                'id' => 5, 'name' => 'Knockout', 'stage_type' => 'knockout',
                'stage_format' => 'single_elimination', 'display_order' => 1, 'groups' => [],
            ],
        ]);

        $this->assertSame(['Knockout', 'Playoffs'], array_column($catalog, 'name'));
        $this->assertSame(7, $catalog[1]['team_count']);
        $this->assertSame(7, $catalog[1]['entries'], 'Promoted teams size the drawn bracket');
        $this->assertSame(0, $catalog[0]['entries'], 'An empty first bracket has no entrants yet');
        $this->assertSame(['A Pool', 'B Pool'], $catalog[1]['groups']);
        $this->assertSame(4, $catalog[1]['qualifiers']);
    }

    public function testCatalogCarriesTheBracketResetSetting(): void
    {
        $catalog = leagueBracketStageCatalog([
            [
                'id' => 3, 'name' => 'Double Elimination', 'stage_type' => 'knockout',
                'stage_format' => 'double_elimination', 'display_order' => 1,
                'settings_json' => '{"bracket_reset":1}', 'groups' => [],
            ],
        ]);

        $this->assertCount(1, $catalog);
        $this->assertTrue($catalog[0]['bracket_reset']);
    }

    public function testEffectiveFormatDecidesWhetherAStageIsABracket(): void
    {
        $catalog = leagueBracketStageCatalog([
            // The stored column disagrees with the type: the engine reads it as
            // round robin, so it must not appear in the bracket catalog.
            ['id' => 1, 'name' => 'Mismatched table', 'stage_type' => 'group', 'stage_format' => 'single_elimination', 'display_order' => 1, 'groups' => []],
            // Legacy knockout rows may still carry the column default.
            ['id' => 2, 'name' => 'Legacy bracket', 'stage_type' => 'knockout', 'stage_format' => 'round_robin', 'display_order' => 2, 'groups' => []],
        ]);

        $this->assertSame(['Legacy bracket'], array_column($catalog, 'name'));
        $this->assertSame('single_elimination', $catalog[0]['format']);
    }
}
