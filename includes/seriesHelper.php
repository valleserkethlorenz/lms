<?php
/**
 * Series (best-of) helper
 * -----------------------
 * A series is ONE matchup played as BO1, BO3, BO5 or BO7. The series and its
 * individual games are already modelled by the existing tables:
 *
 *   series  -> one tbl_schedule row      (best_of, sets_a, sets_b, winner)
 *   game    -> one tbl_match_set row     (set_no, a_points, b_points)
 *
 * Because a series row only receives a `winner` when the series is decided, the
 * existing progression engine (progressStageMatch -> eliminate loser / fill next
 * round) already advances a team only after the WHOLE series is over. A team is
 * never eliminated for losing one game of a BO3.
 *
 * This file is intentionally PURE (no database access) so every rule can be
 * unit-tested: see tests/Unit/SeriesHelperTest.php.
 *
 * Nothing here is sport-specific: Volleyball passes `sets`, esports pass `games`.
 */

if (!function_exists('seriesSupportedLengths')) {
    /** Series lengths the LMS supports (odd only). @return int[] */
    function seriesSupportedLengths(): array
    {
        return [1, 3, 5, 7];
    }
}

if (!function_exists('seriesValidLength')) {
    /** Is $bestOf a supported series length (BO1/BO3/BO5/BO7)? */
    function seriesValidLength(int $bestOf): bool
    {
        return in_array($bestOf, seriesSupportedLengths(), true);
    }
}

if (!function_exists('seriesWinsRequired')) {
    /**
     * Games/sets needed to win a series: floor(bestOf / 2) + 1.
     * BO1 -> 1, BO3 -> 2, BO5 -> 3, BO7 -> 4.
     */
    function seriesWinsRequired(int $bestOf): int
    {
        return intdiv(max(1, $bestOf), 2) + 1;
    }
}

if (!function_exists('seriesNormalizeLength')) {
    /**
     * Coerce user input / a database value into a supported series length.
     * Empty, zero, negative or unsupported values fall back to $fallback.
     */
    function seriesNormalizeLength($value, int $fallback = 1): int
    {
        $bestOf = (int) $value;
        return seriesValidLength($bestOf) ? $bestOf : max(1, $fallback);
    }
}

if (!function_exists('seriesLabel')) {
    /** Short label for a series length: 'Single game', 'BO3', 'BO5', 'BO7'. */
    function seriesLabel(int $bestOf): string
    {
        $bestOf = max(1, $bestOf);
        return $bestOf <= 1 ? 'Single game' : 'BO' . $bestOf;
    }
}

if (!function_exists('seriesGameWinner')) {
    /**
     * Which side won one game. 'a', 'b' or '' when the game was not decisive.
     * A game can never end level — see validateSeriesGames().
     *
     * @param int $a game unit won by side A (e.g. 1 for a win, or set points)
     * @param int $b game unit won by side B
     */
    function seriesGameWinner(int $a, int $b): string
    {
        if ($a > $b) return 'a';
        if ($b > $a) return 'b';
        return '';
    }
}

if (!function_exists('seriesGamesFromPicks')) {
    /**
     * Turn a per-game winner selection into the [[a, b], ...] pairs the rest of
     * the LMS already stores in tbl_match_set (1-0 / 0-1 per game).
     *
     * Unplayed games are dropped, so ['a', 'b', ''] never creates a phantom
     * third game and the order of the played games is preserved.
     *
     * @param array<int,string> $picks 'a' | 'b' | '' (per game, in order)
     * @return array<int,array{0:int,1:int}>
     */
    function seriesGamesFromPicks(array $picks): array
    {
        $games = [];
        foreach (array_values($picks) as $pick) {
            $side = strtolower(trim((string) $pick));
            if ($side === 'a') {
                $games[] = [1, 0];
            } elseif ($side === 'b') {
                $games[] = [0, 1];
            }
            // '' (or anything unrecognised) means "this game was not played".
        }
        return $games;
    }
}

if (!function_exists('seriesProgress')) {
    /**
     * Running score of a series.
     *
     * @param array<int,array{0:int,1:int}> $games played games in order
     * @return array{games_a:int,games_b:int,played:int,required:int,best_of:int,
     *               winner:string,complete:bool,remaining:int,next_game:int}
     */
    function seriesProgress(array $games, int $bestOf): array
    {
        $bestOf   = max(1, $bestOf);
        $required = seriesWinsRequired($bestOf);

        $gamesA = 0;
        $gamesB = 0;
        foreach ($games as $pair) {
            $a = (int) ($pair[0] ?? 0);
            $b = (int) ($pair[1] ?? 0);
            if ($a > $b) {
                $gamesA++;
            } elseif ($b > $a) {
                $gamesB++;
            }
        }

        $winner   = $gamesA >= $required ? 'a' : ($gamesB >= $required ? 'b' : '');
        $played   = count($games);
        $complete = ($winner !== '');

        return [
            'games_a'   => $gamesA,
            'games_b'   => $gamesB,
            'played'    => $played,
            'required'  => $required,
            'best_of'   => $bestOf,
            'winner'    => $winner,
            'complete'  => $complete,
            'remaining' => $complete ? 0 : max(0, $bestOf - $played),
            'next_game' => $complete ? 0 : $played + 1,
        ];
    }
}

if (!function_exists('seriesNextGameNo')) {
    /** Number of the next game that still has to be played (0 = series is over). */
    function seriesNextGameNo(array $games, int $bestOf): int
    {
        return (int) seriesProgress($games, $bestOf)['next_game'];
    }
}

if (!function_exists('validateSeriesGames')) {
    /**
     * Validate a whole best-of series before it is written.
     *
     * Rejects:
     *  - a missing series,
     *  - more games than the format allows,
     *  - a level game,
     *  - a game recorded after the series was already decided,
     *  - an incomplete series (nobody reached the winning threshold).
     *
     * @param array<int,array{0:int,1:int}> $games played games in order
     * @param string $unit 'game' or 'set' — used in the message only
     * @return string|null error message, or null when the series is valid
     */
    function validateSeriesGames(array $games, int $bestOf, string $unit = 'game'): ?string
    {
        $bestOf   = max(1, $bestOf);
        $unit     = ($unit === 'set') ? 'set' : 'game';
        $required = seriesWinsRequired($bestOf);

        if (empty($games)) {
            return 'Enter the ' . $unit . ' results.';
        }
        if (count($games) > $bestOf) {
            return 'A best-of-' . $bestOf . ' series cannot have ' . count($games) . ' ' . $unit . 's.';
        }

        $gamesA = 0;
        $gamesB = 0;
        $total  = count(array_values($games));

        foreach (array_values($games) as $index => $pair) {
            $no = $index + 1;
            $a  = (int) ($pair[0] ?? 0);
            $b  = (int) ($pair[1] ?? 0);

            if ($a < 0 || $b < 0) {
                return ucfirst($unit) . ' ' . $no . ': the result cannot be negative.';
            }
            if ($a === $b) {
                return ucfirst($unit) . ' ' . $no . ': a ' . $unit . ' cannot end level (' . $a . '-' . $b . ').';
            }

            if ($a > $b) {
                $gamesA++;
            } else {
                $gamesB++;
            }

            // The series ends the moment the threshold is reached, so any game
            // decided AFTER that point is an impossible submission.
            if ($no < $total && ($gamesA >= $required || $gamesB >= $required)) {
                return ucfirst($unit) . ' ' . $no . ' already decided the series — remove the extra ' . $unit . '(s).';
            }
        }

        if ($gamesA < $required && $gamesB < $required) {
            return 'The series is incomplete: a best-of-' . $bestOf . ' needs ' . $required
                . ' ' . $unit . ' win(s) — currently ' . $gamesA . '-' . $gamesB . '.';
        }

        return null;
    }
}

if (!function_exists('seriesRoundLabel')) {
    /**
     * Human name of a bracket round. This is the "Round" level between a Stage
     * and a Series, so the UI never has to invent a new stage per bracket level.
     *
     * @param int $round       1-based round number (tbl_schedule.round)
     * @param int $totalRounds highest round in the stage
     */
    function seriesRoundLabel(int $round, int $totalRounds): string
    {
        $round       = max(1, $round);
        $totalRounds = max(1, $totalRounds);

        if ($round >= $totalRounds) return 'Final';

        $behind = $totalRounds - $round;
        if ($behind === 1) return 'Semifinals';
        if ($behind === 2) return 'Quarterfinals';
        if ($behind === 3) return 'Round of 16';
        return 'Round ' . $round;
    }
}

if (!function_exists('seriesStageStatus')) {
    /**
     * Stage state derived from persisted data only — no extra status column.
     *  - draft       : no teams placed and no matches yet
     *  - ready       : teams placed, nothing generated yet
     *  - in_progress : matches exist, results still missing
     *  - completed   : every playable match has an official result
     *
     * @return string draft|ready|in_progress|completed
     */
    function seriesStageStatus(int $matches, int $completed, int $teams = 0): string
    {
        if ($matches <= 0) return $teams > 0 ? 'ready' : 'draft';
        return $completed >= $matches ? 'completed' : 'in_progress';
    }
}

if (!function_exists('seriesStageStatusLabel')) {
    /** Plain-language label for a stage state (never rely on colour alone). */
    function seriesStageStatusLabel(string $status): string
    {
        $labels = [
            'draft'       => 'Not started',
            'ready'       => 'Ready',
            'in_progress' => 'In progress',
            'completed'   => 'Completed',
        ];
        return $labels[$status] ?? 'Not started';
    }
}


