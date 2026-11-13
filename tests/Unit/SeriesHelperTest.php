<?php
/**
 * Tests for the series (best-of) rules — includes/seriesHelper.php.
 *
 * Covers the manual's requirements:
 *   - winsRequired = floor(bestOf / 2) + 1,
 *   - the series ends immediately once the threshold is reached (BO3 2-0, BO3
 *     2-1, BO5 3-2),
 *   - a game cannot end level, an extra game after the decision is impossible,
 *   - an incomplete series is rejected,
 *   - Tournament > Stage > Round > Series > Game vocabulary.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/seriesHelper.php';

class SeriesHelperTest extends TestCase
{
    public function testWinsRequiredFollowsTheFormula(): void
    {
        $this->assertSame(1, seriesWinsRequired(1), 'BO1 needs 1 win');
        $this->assertSame(2, seriesWinsRequired(3), 'BO3 needs 2 wins');
        $this->assertSame(3, seriesWinsRequired(5), 'BO5 needs 3 wins');
        $this->assertSame(4, seriesWinsRequired(7), 'BO7 needs 4 wins');
    }

    public function testOnlyOddSeriesLengthsAreSupported(): void
    {
        $this->assertSame([1, 3, 5, 7], seriesSupportedLengths());
        foreach ([1, 3, 5, 7] as $bestOf) {
            $this->assertTrue(seriesValidLength($bestOf), "BO$bestOf must be supported");
        }
        foreach ([0, 2, 4, 6, 8, 9, -3] as $bestOf) {
            $this->assertFalse(seriesValidLength($bestOf), "$bestOf must be rejected");
        }
    }

    public function testNormalizeLengthFallsBackForBadInput(): void
    {
        $this->assertSame(3, seriesNormalizeLength('3'));
        $this->assertSame(1, seriesNormalizeLength(2), 'even numbers are not supported');
        $this->assertSame(1, seriesNormalizeLength(''), 'empty input falls back');
        $this->assertSame(5, seriesNormalizeLength(0, 5), 'fallback is used for zero');
    }

    public function testLabels(): void
    {
        $this->assertSame('Single game', seriesLabel(1));
        $this->assertSame('BO3', seriesLabel(3));
        $this->assertSame('BO5', seriesLabel(5));
        $this->assertSame('BO7', seriesLabel(7));
    }

    /** §15 scenario 4 — a BO3 ending 2-0 is finished after two games. */
    public function testBo3EndsAtTwoGamesToNil(): void
    {
        $games = seriesGamesFromPicks(['a', 'a']);
        $this->assertSame([[1, 0], [1, 0]], $games);
        $this->assertNull(validateSeriesGames($games, 3));

        $progress = seriesProgress($games, 3);
        $this->assertSame(2, $progress['games_a']);
        $this->assertSame(0, $progress['games_b']);
        $this->assertSame('a', $progress['winner']);
        $this->assertTrue($progress['complete']);
        $this->assertSame(0, $progress['remaining'], 'no third game is required');
        $this->assertSame(0, $progress['next_game']);
    }

    /** §15 scenario 5 — a BO3 ending 2-1 needs the third game. */
    public function testBo3EndsAtTwoGamesToOne(): void
    {
        $games = seriesGamesFromPicks(['a', 'b', 'a']);
        $this->assertNull(validateSeriesGames($games, 3));

        $progress = seriesProgress($games, 3);
        $this->assertSame(2, $progress['games_a']);
        $this->assertSame(1, $progress['games_b']);
        $this->assertSame('a', $progress['winner']);
        $this->assertTrue($progress['complete']);
    }

    public function testBo3StaysActiveAtOneAll(): void
    {
        $games = seriesGamesFromPicks(['a', 'b']);
        $progress = seriesProgress($games, 3);

        $this->assertSame('', $progress['winner']);
        $this->assertFalse($progress['complete']);
        $this->assertSame(1, $progress['remaining']);
        $this->assertSame(3, $progress['next_game'], 'game 3 is still required');
        $this->assertNotNull(validateSeriesGames($games, 3), 'an incomplete series is rejected');
    }

    /** §15 scenario 6 — a BO5 Grand Final ending 3-2. */
    public function testBo5GrandFinalEndingThreeTwo(): void
    {
        $games = seriesGamesFromPicks(['a', 'b', 'a', 'b', 'a']);
        $this->assertNull(validateSeriesGames($games, 5));

        $progress = seriesProgress($games, 5);
        $this->assertSame(3, $progress['games_a']);
        $this->assertSame(2, $progress['games_b']);
        $this->assertSame('a', $progress['winner']);
        $this->assertTrue($progress['complete']);
        $this->assertSame(0, $progress['remaining']);
    }

    public function testBo5StaysActiveAtTwoAll(): void
    {
        $progress = seriesProgress(seriesGamesFromPicks(['a', 'b', 'a', 'b']), 5);

        $this->assertFalse($progress['complete']);
        $this->assertSame(1, $progress['remaining']);
        $this->assertSame(5, $progress['next_game']);
    }

    public function testValidateRejectsUnnecessaryGameAfterTheSeriesIsDecided(): void
    {
        // BO3 already won 2-0, so a third game is an impossible submission.
        $error = validateSeriesGames([[1, 0], [1, 0], [1, 0]], 3);

        $this->assertNotNull($error);
        $this->assertStringContainsString('already decided the series', $error);
    }

    public function testValidateRejectsAnIncompleteSeries(): void
    {
        $error = validateSeriesGames(seriesGamesFromPicks(['a', 'b']), 3);

        $this->assertNotNull($error);
        $this->assertStringContainsString('incomplete', $error);
        $this->assertStringContainsString('1-1', $error);
    }

    public function testValidateRejectsALevelGame(): void
    {
        $error = validateSeriesGames([[1, 1]], 3);

        $this->assertNotNull($error);
        $this->assertStringContainsString('cannot end level', $error);
    }

    public function testValidateRejectsTooManyGames(): void
    {
        $error = validateSeriesGames([[1, 0], [1, 0], [1, 0], [1, 0]], 3);

        $this->assertNotNull($error);
        $this->assertStringContainsString('cannot have 4 games', $error);
    }

    public function testValidateRejectsNegativeAndEmptyInput(): void
    {
        $this->assertNotNull(validateSeriesGames([[-1, 0]], 3));
        $this->assertNotNull(validateSeriesGames([], 3));
    }

    public function testValidateCanSpeakInSetsForVolleyball(): void
    {
        $error = validateSeriesGames([[25, 25]], 3, 'set');

        $this->assertNotNull($error);
        $this->assertStringContainsString('a set cannot end level', $error);
    }

    public function testGamesFromPicksKeepsThePlayedOrderAndDropsBlanks(): void
    {
        $this->assertSame([[1, 0], [0, 1]], seriesGamesFromPicks(['a', 'b', '']));
        $this->assertSame([[1, 0], [0, 1]], seriesGamesFromPicks(['a', '', 'b']));
        $this->assertSame([], seriesGamesFromPicks(['', '']));
        $this->assertSame([], seriesGamesFromPicks([]));
    }

    public function testRoundLabelsNameTheBracketLevel(): void
    {
        $this->assertSame('Final', seriesRoundLabel(1, 1));
        $this->assertSame('Semifinals', seriesRoundLabel(1, 2));
        $this->assertSame('Final', seriesRoundLabel(2, 2));
        $this->assertSame('Quarterfinals', seriesRoundLabel(1, 3));
        $this->assertSame('Semifinals', seriesRoundLabel(2, 3));
        $this->assertSame('Final', seriesRoundLabel(3, 3));
        $this->assertSame('Round of 16', seriesRoundLabel(1, 4));
        $this->assertSame('Quarterfinals', seriesRoundLabel(2, 4));
        $this->assertSame('Round 1', seriesRoundLabel(1, 5));
    }

    public function testStageStatusIsDerivedFromMatchesOnly(): void
    {
        $this->assertSame('draft', seriesStageStatus(0, 0, 0));
        $this->assertSame('ready', seriesStageStatus(0, 0, 8));
        $this->assertSame('in_progress', seriesStageStatus(10, 4));
        $this->assertSame('completed', seriesStageStatus(10, 10));
    }

    public function testStageStatusLabelsAreReadableText(): void
    {
        $this->assertSame('Not started', seriesStageStatusLabel('draft'));
        $this->assertSame('Ready', seriesStageStatusLabel('ready'));
        $this->assertSame('In progress', seriesStageStatusLabel('in_progress'));
        $this->assertSame('Completed', seriesStageStatusLabel('completed'));
        $this->assertSame('Not started', seriesStageStatusLabel('nonsense'));
    }
}
