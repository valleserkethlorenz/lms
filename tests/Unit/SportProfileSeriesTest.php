<?php
/**
 * Regression lock for config/sportRules.php after adding result_unit 'games'.
 *
 * Route A of the series redesign adds an esports ("games") result unit WITHOUT
 * touching sportProfileUsesSets(). These tests assert that:
 *   - Volleyball (sets) behaves exactly as before,
 *   - points-based sports (Basketball etc.) behave exactly as before,
 *   - Mobile Legends gets games + a series, but no point target.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/sportRules.php';

class SportProfileSeriesTest extends TestCase
{
    /**
     * The profile layer without a database: defaults + the built-in preset
     * (exactly what getSportProfile() merges before tbl_utility / league overrides).
     */
    private static function profile(string $sport): array
    {
        $presets = sportProfilePresets();
        $profile = sportProfileDefaults();
        $profile['sport'] = $sport;
        if (isset($presets[$sport])) {
            $profile = sportProfileMerge($profile, $presets[$sport]);
        }
        return $profile;
    }

    public function testPointsBasedSportKeepsItsLegacyBehaviour(): void
    {
        $basketball = self::profile('basketball');

        $this->assertFalse(sportProfileUsesSets($basketball));
        $this->assertFalse(sportProfileUsesGames($basketball), 'points sports are not series sports');
        $this->assertFalse(sportProfileUsesSeries($basketball));
        $this->assertSame('Point', sportProfileSeriesUnit($basketball));
        $this->assertSame('Point', sportProfileLabels($basketball)['unit']);
        $this->assertSame(1, sportProfileSeriesLength($basketball), 'a single game by default');
    }

    public function testPointsSportForfeitLineStaysTheLegacyTwenty(): void
    {
        $line = sportProfileForfeitLine(self::profile('basketball'), 1, 'a');

        $this->assertSame(1, $line['a_sets']);
        $this->assertSame(0, $line['b_sets']);
        $this->assertSame([], $line['sets'], 'no per-set rows for a points sport');
        $this->assertSame(20, $line['legacy_points']);
    }

    public function testVolleyballStillUsesSets(): void
    {
        $volleyball = self::profile('volleyball');

        $this->assertTrue(sportProfileUsesSets($volleyball));
        $this->assertFalse(sportProfileUsesGames($volleyball), 'volleyball is not a games sport');
        $this->assertTrue(sportProfileUsesSeries($volleyball));
        $this->assertSame('Set', sportProfileSeriesUnit($volleyball));
        $this->assertSame('Set', sportProfileLabels($volleyball)['unit']);
        $this->assertSame(25, (int) $volleyball['set_target']);
    }

    public function testVolleyballForfeitLineIsUnchanged(): void
    {
        $volleyball = self::profile('volleyball');

        $bo3 = sportProfileForfeitLine($volleyball, 3, 'a');
        $this->assertSame(2, $bo3['a_sets']);
        $this->assertSame([[25, 0], [25, 0]], $bo3['sets']);

        $bo5 = sportProfileForfeitLine($volleyball, 5, 'b');
        $this->assertSame(3, $bo5['b_sets']);
        $this->assertSame([[0, 25], [0, 25], [0, 25]], $bo5['sets']);
    }

    public function testVolleyballSetValidationIsUnchanged(): void
    {
        $volleyball = self::profile('volleyball');

        $this->assertNull(sportProfileValidateSet($volleyball, 1, 3, 25, 20), '25-20 is a valid set');
        $this->assertStringContainsString('must reach 25', (string) sportProfileValidateSet($volleyball, 1, 3, 24, 20));
        $this->assertStringContainsString('must lead by 2', (string) sportProfileValidateSet($volleyball, 1, 3, 25, 24));
        $this->assertStringContainsString('cannot end level', (string) sportProfileValidateSet($volleyball, 1, 3, 25, 25));
    }

    public function testMobileLegendsIsAGamesSeries(): void
    {
        $ml = self::profile('mobile legends');

        $this->assertTrue(sportProfileUsesGames($ml));
        $this->assertFalse(sportProfileUsesSets($ml), 'games must not leak into the sets path');
        $this->assertTrue(sportProfileUsesSeries($ml));
        $this->assertSame('Game', sportProfileSeriesUnit($ml));
        $this->assertSame(3, (int) $ml['best_of'], 'BO3 by default');
        $this->assertSame(5, (int) $ml['final_best_of'], 'Grand Final is BO5');
        $this->assertSame(0, (int) $ml['allow_draw'], 'a series cannot be a draw');
        $this->assertSame('set_line', $ml['scoring_mode'], 'the series line still earns 3-2-1-0 points');
    }

    public function testMobileLegendsLabelsSpeakGames(): void
    {
        $labels = sportProfileLabels(self::profile('mobile legends'));

        $this->assertSame('Game', $labels['unit']);
        $this->assertSame('Games', $labels['unit_plural']);
        $this->assertSame('Games won', $labels['score_a']);
        $this->assertSame('Best of 3', $labels['series']);
    }

    public function testMobileLegendsGameValidationOnlyRejectsLevelGames(): void
    {
        $ml = self::profile('mobile legends');

        // No point target and no win-by margin: 1-0 and 2-0 are both fine.
        $this->assertNull(sportProfileValidateSet($ml, 1, 3, 1, 0));
        $this->assertNull(sportProfileValidateSet($ml, 2, 3, 2, 0));

        $level = (string) sportProfileValidateSet($ml, 1, 3, 1, 1);
        $this->assertStringContainsString('a game cannot end level', $level);
        $this->assertStringNotContainsString('must reach', $level, 'games have no target score');
    }

    public function testMobileLegendsForfeitLineAwardsTheThreshold(): void
    {
        $ml = self::profile('mobile legends');

        $bo3 = sportProfileForfeitLine($ml, 3, 'a');
        $this->assertSame(2, $bo3['a_sets'], 'BO3 forfeit is 2-0');
        $this->assertSame([[1, 0], [1, 0]], $bo3['sets'], 'one row per awarded game, never 0-25');
        $this->assertSame(0, $bo3['legacy_points']);

        $bo5 = sportProfileForfeitLine($ml, 5, 'b');
        $this->assertSame(3, $bo5['b_sets'], 'BO5 forfeit is 0-3');
        $this->assertSame([[0, 1], [0, 1], [0, 1]], $bo5['sets']);
    }
}
