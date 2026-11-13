<?php
/**
 * Tests for the league creation "Structure" presets.
 * Located in: includes/leagueStructure.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/leagueStructure.php';

class LeagueStructureTest extends TestCase
{
    public function testEveryStructureOptionHasAPreset(): void
    {
        $presets = leagueStructurePresets();

        $this->assertSame(
            ['round_robin', 'swiss_system', 'single_elimination', 'double_elimination', 'round_robin_playoffs', 'groups_knockout', 'pools_playoffs', 'custom'],
            array_keys($presets),
            'The structure options and the presets must expose the same structures'
        );

        foreach ($presets as $key => $preset) {
            $this->assertIsArray($preset['stages'], "$key must declare its stages");
        }
    }

    public function testStructureOptionsMatchThePresets(): void
    {
        $this->assertSame(
            array_keys(leagueStructurePresets()),
            array_keys(structureOptions()),
            'Every preset needs a label and vice versa'
        );
    }

    public function testStructureCardsDescribeEveryPresetExceptCustom(): void
    {
        $cards   = structureCards();
        $presets = leagueStructurePresets();

        $this->assertSame(
            ['round_robin', 'swiss_system', 'single_elimination', 'double_elimination', 'round_robin_playoffs', 'groups_knockout', 'pools_playoffs'],
            array_keys($cards),
            'The Setup page offers one card per preset; building stages by hand is a link, not a card'
        );

        foreach ($cards as $key => $card) {
            $this->assertArrayHasKey($key, $presets, "$key has no preset to apply");
            $this->assertNotSame('', trim($card['title']), "$key needs a card title");
            $this->assertNotSame('', trim($card['text']), "$key needs card copy");
            $this->assertLessThanOrEqual(
                14,
                str_word_count($card['text']),
                "$key card copy must stay to one short line"
            );
        }
    }

    public function testPresetsCarryNoFormatKeys(): void
    {
        foreach (leagueStructurePresets() as $key => $preset) {
            $this->assertArrayNotHasKey('format', $preset, "$key must not carry a format label");
            $this->assertArrayNotHasKey('format_code', $preset, "$key must not carry a format code");
        }
    }

    public function testPresetsDoNotBranchOnSport(): void
    {
        foreach (leagueStructurePresets() as $key => $preset) {
            $this->assertArrayNotHasKey('sport', $preset, "$key must not depend on the sport");
            $this->assertArrayNotHasKey('sports', $preset, "$key must not depend on the sport");
        }
    }

    public function testEveryStageUsesAKnownStageType(): void
    {
        $knownTypes = array_keys(stageTypeLabels());

        foreach (leagueStructurePresets() as $key => $preset) {
            foreach ($preset['stages'] as $stage) {
                $this->assertContains($stage['type'], $knownTypes, "$key uses an unknown stage type");
                $this->assertNotSame('', $stage['name'], "$key has an unnamed stage");
            }
        }
    }

    public function testRoundRobinSeedsOneGroupStage(): void
    {
        $preset = leagueStructurePresets()['round_robin'];

        $this->assertCount(1, $preset['stages']);
        $this->assertSame('group', $preset['stages'][0]['type']);
        $this->assertSame(['Group A'], $preset['stages'][0]['groups']);
    }

    public function testRoundRobinPlayoffsSeedsGroupAndKnockoutStages(): void
    {
        $preset = leagueStructurePresets()['round_robin_playoffs'];

        $this->assertCount(2, $preset['stages']);
        $this->assertSame('group', $preset['stages'][0]['type']);
        $this->assertSame(['Group A'], $preset['stages'][0]['groups']);
        $this->assertSame(4, $preset['stages'][0]['qualifiers'], 'Top 4 advance by default');
        $this->assertSame('knockout', $preset['stages'][1]['type']);
        $this->assertSame(['Qualifiers'], $preset['stages'][1]['groups'], 'The playoff stage is filled by the promotion step');
    }

    public function testCoreFormatsAreAvailableAsStructures(): void
    {
        $presets = leagueStructurePresets();

        $this->assertSame('group', $presets['round_robin']['stages'][0]['type']);
        $this->assertSame('round_robin', $presets['round_robin']['stages'][0]['format']);

        $this->assertSame('group', $presets['swiss_system']['stages'][0]['type']);
        $this->assertSame('swiss', $presets['swiss_system']['stages'][0]['format']);

        // Single elimination is a seeded stage bracket (byes supported).
        $single = $presets['single_elimination']['stages'];
        $this->assertCount(1, $single);
        $this->assertSame('knockout', $single[0]['type']);
        $this->assertSame('single_elimination', $single[0]['format']);
        $this->assertSame([], $single[0]['groups']);

        // Double elimination is a winners + losers + grand final stage.
        $double = $presets['double_elimination']['stages'];
        $this->assertCount(1, $double);
        $this->assertSame('knockout', $double[0]['type']);
        $this->assertSame('double_elimination', $double[0]['format']);
        $this->assertSame([], $double[0]['groups']);
    }

    public function testPoolsPlayoffsDeclaresPromotionQualifiers(): void
    {
        $stages = leagueStructurePresets()['pools_playoffs']['stages'];

        $this->assertSame(2, $stages[0]['qualifiers'], 'Top 2 of each bracket feed the pools');
        $this->assertSame(4, $stages[1]['qualifiers'], 'Top 4 of each pool feed the playoffs');
    }

    public function testGroupsKnockoutSeedsGroupAndKnockoutStages(): void
    {
        $stages = leagueStructurePresets()['groups_knockout']['stages'];

        $this->assertCount(2, $stages);
        $this->assertSame('group', $stages[0]['type']);
        $this->assertSame(['Group A', 'Group B'], $stages[0]['groups']);
        $this->assertSame(2, $stages[0]['qualifiers'], 'Top 2 per group advance by default');
        $this->assertSame('knockout', $stages[1]['type']);
        $this->assertSame(['Qualifiers'], $stages[1]['groups'], 'Both groups feed one qualifier pool');
        $this->assertSame('all', $stages[1]['sources'], 'The qualifier pool takes every group');
    }

    public function testEveryEditableSettingTargetsARealPresetStage(): void
    {
        $presets = leagueStructurePresets();

        foreach (leagueStructureEditableSettings() as $key => $fields) {
            $this->assertArrayHasKey($key, $presets, "$key has editable settings but no preset");
            $this->assertNotEmpty($fields, "$key declares an empty settings list");

            foreach ($fields as $field) {
                $this->assertArrayHasKey($field['stage'], $presets[$key]['stages'], "$key setting points at a missing stage");
                $this->assertContains($field['field'], ['groups', 'qualifiers', 'rounds']);
                $this->assertGreaterThanOrEqual($field['min'], $field['default']);
                $this->assertLessThanOrEqual($field['max'], $field['default']);
            }
        }
    }

    public function testEditableSettingsUseThePresetDefaults(): void
    {
        foreach (leagueStructureEditableSettings() as $key => $fields) {
            $defaults = leagueStructureDefaultSettings($key);
            $stages = leagueStructurePresets()[$key]['stages'];

            foreach ($fields as $field) {
                if ($field['field'] === 'qualifiers') {
                    $expected = (int) ($stages[$field['stage']]['qualifiers'] ?? $field['default']);
                } elseif ($field['field'] === 'rounds') {
                    $expected = (int) ($stages[$field['stage']]['rounds'] ?? $field['default']);
                } else {
                    $expected = count($stages[$field['stage']]['groups'] ?? []) ?: (int) $field['default'];
                }
                $this->assertSame($expected, $defaults[$field['stage']][$field['field']], "$key stage {$field['stage']} {$field['field']} default drifted");
            }
        }
    }

    public function testSanitizeSettingsClampsValuesAndKeepsDefaults(): void
    {
        $settings = leagueStructureSanitizeSettings('pools_playoffs', [
            0 => ['qualifiers' => 99],
            1 => ['qualifiers' => 0],
        ]);

        $this->assertSame(64, $settings[0]['qualifiers'], 'Values above the maximum are clamped');
        $this->assertSame(1, $settings[1]['qualifiers'], 'Values below the minimum are clamped');

        $defaults = leagueStructureDefaultSettings('groups_knockout');
        $this->assertSame(2, $defaults[0]['qualifiers']);
    }

    public function testRecommendedGroupSetupKeepsGroupsBalanced(): void
    {
        $cases = [
            4  => [2, [2, 2], 1, 2, 0],
            6  => [2, [3, 3], 2, 4, 0],
            8  => [2, [4, 4], 2, 4, 0],
            10 => [2, [5, 5], 2, 4, 0],
            12 => [3, [4, 4, 4], 2, 6, 2],
            14 => [4, [4, 4, 3, 3], 2, 8, 0],
            16 => [4, [4, 4, 4, 4], 2, 8, 0],
            20 => [4, [5, 5, 5, 5], 2, 8, 0],
            24 => [6, [4, 4, 4, 4, 4, 4], 2, 12, 4],
            32 => [8, [4, 4, 4, 4, 4, 4, 4, 4], 2, 16, 0],
        ];

        foreach ($cases as $teams => [$groups, $sizes, $qualifiers, $total, $byes]) {
            $recommendation = recommendedGroupSetup($teams);

            $this->assertSame($groups, $recommendation['groups'], "$teams teams should use $groups groups");
            $this->assertSame($sizes, $recommendation['group_sizes'], "$teams teams should be split into the expected sizes");
            $this->assertSame($qualifiers, $recommendation['qualifiers'], "$teams teams should use the expected cut-off");
            $this->assertSame($total, $recommendation['total_qualifiers']);
            $this->assertSame($byes, $recommendation['byes']);
            $this->assertSame('', $recommendation['warning']);
        }
    }

    public function testRecommendedGroupSetupWarnsForVerySmallFields(): void
    {
        $recommendation = recommendedGroupSetup(3);

        $this->assertSame(2, $recommendation['groups']);
        $this->assertSame(1, $recommendation['qualifiers']);
        $this->assertNotSame('', $recommendation['warning']);
    }

    public function testGroupCountSettingClampsAndDefaultsToTwo(): void
    {
        $this->assertSame(2, leagueStructureDefaultSettings('groups_knockout')[0]['groups']);

        $clamped = leagueStructureSanitizeSettings('groups_knockout', [
            0 => ['groups' => 99, 'qualifiers' => 3],
        ]);
        $this->assertSame(8, $clamped[0]['groups']);
        $this->assertSame(3, $clamped[0]['qualifiers']);

        $minimum = leagueStructureSanitizeSettings('groups_knockout', [
            0 => ['groups' => 0],
        ]);
        $this->assertSame(2, $minimum[0]['groups']);
    }

    public function testDetectLeagueStructureRecognisesDynamicGroupCounts(): void
    {
        foreach ([3, 4, 5, 8] as $groups) {
            $this->assertSame('groups_knockout', detectLeagueStructure(
                self::skeleton([
                    ['type' => 'group', 'groups' => $groups],
                    ['type' => 'knockout', 'groups' => 1],
                ])
            ), "A $groups-group knockout skeleton must still be recognised");
        }

        $this->assertSame('groups_knockout', detectLeagueStructure(
            self::skeleton([
                ['type' => 'group', 'groups' => 5],
                ['type' => 'knockout', 'groups' => 2],
                ['type' => 'placement', 'groups' => 0],
            ])
        ), 'A trailing placement stage must not hide the preset');
    }

    public function testPoolsPlayoffsSeedsTheAnp2026Skeleton(): void
    {
        $stages = leagueStructurePresets()['pools_playoffs']['stages'];

        $this->assertCount(3, $stages);
        $this->assertSame(['Bracket A', 'Bracket B', 'Bracket C', 'Bracket D'], $stages[0]['groups']);
        $this->assertSame('crossover', $stages[1]['type']);
        $this->assertTrue($stages[1]['carries']);
        $this->assertSame([[0, 3], [1, 2]], $stages[1]['sources']);
        $this->assertSame('knockout', $stages[2]['type']);
    }

    /** Stage rows shaped like getLeagueStages() + their 'groups' key. */
    private static function skeleton(array $stages): array
    {
        $rows = [];
        foreach ($stages as $index => $stage) {
            $groups = [];
            for ($i = 0; $i < (int) ($stage['groups'] ?? 0); $i++) $groups[] = ['id' => $index * 10 + $i + 1];
            $rows[] = [
                'id'           => $index + 1,
                'stage_type'   => $stage['type'],
                'stage_format' => $stage['format'] ?? null,
                'groups'       => $groups,
            ];
        }
        return $rows;
    }

    public function testDetectLeagueStructureRecognisesEachPreset(): void
    {
        $this->assertSame('custom', detectLeagueStructure([]), 'No stages at all is the custom preset');

        $this->assertSame('round_robin', detectLeagueStructure(
            self::skeleton([['type' => 'group', 'groups' => 1]])
        ));

        $this->assertSame('round_robin_playoffs', detectLeagueStructure(
            self::skeleton([
                ['type' => 'group', 'groups' => 1],
                ['type' => 'knockout', 'groups' => 1],
            ])
        ));

        $this->assertSame('single_elimination', detectLeagueStructure(
            self::skeleton([['type' => 'knockout', 'groups' => 0]])
        ));

        $this->assertSame('double_elimination', detectLeagueStructure(
            self::skeleton([['type' => 'knockout', 'groups' => 0, 'format' => 'double_elimination']])
        ));

        $this->assertSame('swiss_system', detectLeagueStructure(
            self::skeleton([['type' => 'group', 'groups' => 1, 'format' => 'swiss']])
        ));

        $this->assertSame('groups_knockout', detectLeagueStructure(
            self::skeleton([
                ['type' => 'group', 'groups' => 2],
                ['type' => 'knockout', 'groups' => 0],
            ])
        ));

        $this->assertSame('pools_playoffs', detectLeagueStructure(
            self::skeleton([
                ['type' => 'group', 'groups' => 4],
                ['type' => 'crossover', 'groups' => 2],
                ['type' => 'knockout', 'groups' => 0],
            ])
        ));
    }

    public function testDetectLeagueStructureReportsHandEditedSkeletons(): void
    {
        $this->assertNull(detectLeagueStructure(
            self::skeleton([
                ['type' => 'group', 'groups' => 3],
                ['type' => 'knockout', 'groups' => 0, 'format' => 'double_elimination'],
            ])
        ), 'A skeleton no preset produces must be reported as unknown');

        $this->assertNull(detectLeagueStructure(
            self::skeleton([
                ['type' => 'group', 'groups' => 3],
                ['type' => 'crossover', 'groups' => 1],
                ['type' => 'knockout', 'groups' => 0],
            ])
        ));
    }

    public function testPromotionGroupsDoNotHideThePresetShape(): void
    {
        // Auto-promotion creates "A Pool"/"B Pool" groups inside the target
        // knockout stage. The skeleton must still be recognised as the preset.
        $this->assertSame('groups_knockout', detectLeagueStructure(
            self::skeleton([
                ['type' => 'group', 'groups' => 2],
                ['type' => 'knockout', 'groups' => 2],
            ])
        ));

        $this->assertSame('pools_playoffs', detectLeagueStructure(
            self::skeleton([
                ['type' => 'group', 'groups' => 4],
                ['type' => 'crossover', 'groups' => 2],
                ['type' => 'knockout', 'groups' => 2],
            ])
        ));
    }

    public function testStructurePresetCanBeAppliedOrChangedWhileNoMatchesExist(): void
    {
        foreach (['round_robin', 'swiss_system', 'single_elimination', 'double_elimination', 'round_robin_playoffs', 'groups_knockout', 'pools_playoffs'] as $key) {
            $this->assertNull(
                structurePresetApplyError($key, 0),
                "$key must be applicable (and replaceable) while the league has no matches"
            );
        }
    }

    public function testStructurePresetIsLockedOnceMatchesExist(): void
    {
        $error = structurePresetApplyError('groups_knockout', 3);

        $this->assertIsString($error);
        $this->assertStringContainsString('already has matches', $error);
    }

    public function testStructurePresetGuardRejectsUnknownAndCustomKeys(): void
    {
        $this->assertStringContainsString('built-in', (string) structurePresetApplyError('', 0));
        $this->assertStringContainsString('built-in', (string) structurePresetApplyError('triple_elimination', 0));
        $this->assertStringContainsString('custom structure', (string) structurePresetApplyError('custom', 0));
    }

    public function testEveryPresetStageDeclaresAKnownFormat(): void
    {
        $known = array_keys(tournamentFormats());

        foreach (leagueStructurePresets() as $key => $preset) {
            foreach (($preset['stages'] ?? []) as $stage) {
                $this->assertArrayHasKey('format', $stage, "$key stage {$stage['name']} must declare its format");
                $this->assertContains($stage['format'], $known, "$key uses an unknown format");

                // A table format is a group stage (or a crossover pool, which is
                // a group stage fed by earlier groups); a bracket format is knockout.
                $expectedType = stageTypeForFormat($stage['format']);
                if ($expectedType === 'group' && $stage['type'] === 'crossover') $expectedType = 'crossover';
                $this->assertSame($expectedType, $stage['type'], "$key stage {$stage['name']} must use the stage type of its format");
            }
        }
    }
}
