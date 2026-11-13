<?php
/**
 * Sport profiles
 * --------------
 * Every sport-specific behaviour (result unit, ranking mode, best-of, set
 * targets, forfeit score lines, jersey range, roster composition, protest
 * window) is described by a single profile array.
 *
 * COMPATIBILITY CONTRACT
 *   The hard-coded defaults below are exactly the behaviour that existed
 *   before volleyball support was added: points-based results, a single game
 *   (best-of 1), a 20-0 forfeit line, jerseys 0-999, no roster composition
 *   rules, draws allowed and a 24-hour protest window.  A league only leaves
 *   that behaviour when its sport profile (or a league override) says so, so
 *   Basketball / Pickleball / Soccer / Takraw / ML / Badminton / DOTA are
 *   unaffected.
 *
 * Profiles are stored on tbl_utility (category = 'sport') so the Admin screen
 * can manage them, and may be overridden per league on tbl_league.
 *
 * Manual: Volleyball Guidelines ANP2026 (see database/migrations/035_volleyball_anp2026.sql)
 */

if (!function_exists('sportProfileDefaults')) {
    /** Behaviour that applies when nothing else is configured (= pre-volleyball). */
    function sportProfileDefaults(): array
    {
        return [
            'sport'                  => '',
            'result_unit'            => 'points',   // points | sets
            'scoring_mode'           => 'simple',   // simple | set_line
            'best_of'                => 1,          // 1 = single game
            'set_target'             => 0,          // points per set (sets mode)
            'deciding_target'        => 0,          // points in the deciding set
            'win_by'                 => 0,          // minimum winning margin
            'allow_draw'             => 1,
            'forfeit_sets'           => 0,          // sets awarded to the winner
            'forfeit_sets_loser'     => 0,
            'forfeit_set_points'     => 0,          // points per forfeited set
            'forfeit_points'         => 20,         // legacy 20-0 line
            'jersey_min'             => 0,
            'jersey_max'             => 999,
            'roster_regular'         => 0,          // 0 = no composition rule
            'libero_min'             => 0,
            'libero_max'             => 0,
            'require_official'       => 0,
            'protest_window_minutes' => 1440,       // 24 hours
        ];
    }
}

if (!function_exists('sportProfilePresets')) {
    /** Known sport profiles, keyed by lowercase sport name. */
    function sportProfilePresets(): array
    {
        return [
            // ANP2026 Volleyball: sets to 25/15, BO3 (BO5 final), 3-2-1-0 points,
            // 0-25 forfeit sets, jerseys 1-99, 30-minute protest window.
            // Roster-composition fields (roster_regular / libero_* / require_official)
            // are retained for reference only; team registration no longer blocks on them.
            'volleyball' => [
                'result_unit'            => 'sets',
                'scoring_mode'           => 'set_line',
                'best_of'                => 3,
                'set_target'             => 25,
                'deciding_target'        => 15,
                'win_by'                 => 2,
                'allow_draw'             => 0,
                'forfeit_sets'           => 2,   // BO3 default; BO5 uses 3
                'forfeit_sets_loser'     => 0,
                'forfeit_set_points'     => 25,
                'forfeit_points'         => 0,
                'jersey_min'             => 1,
                'jersey_max'             => 99,
                'roster_regular'         => 12,
                'libero_min'             => 1,
                'libero_max'             => 2,
                'require_official'       => 1,
                'protest_window_minutes' => 30,
                // F1 (semifinals A1vB2 / B1vA2 -> championship + bronze) is the default;
                // F2 (A1vB1 final, A2vB2 bronze) stays selectable per league.
                'finals_pattern'         => 'semis_final_bronze',
            ],
            // Basketball stays on the defaults on purpose: the ANP2026 basketball
            // behaviour (single game, 20-0 forfeit, 24h protest) is the legacy path.
            'basketball' => [],
            // Esports / Mobile Legends: a series of GAMES (not sets, not points).
            // result_unit 'games' means "this is a best-of series, but a game has
            // no point target" — see sportProfileUsesGames(). scoring_mode stays
            // 'set_line' so the series line earns 3-2-1-0 competition points.
            // set_target/win_by stay 0, so sportProfileValidateSet() only enforces
            // "a game cannot end level".
            'mobile legends' => [
                'result_unit'        => 'games',
                'scoring_mode'       => 'set_line',
                'best_of'            => 3,
                'final_best_of'      => 5,   // Grand Final BO5, everything else BO3
                'set_target'         => 0,
                'deciding_target'    => 0,
                'win_by'             => 0,
                'allow_draw'         => 0,
                'forfeit_sets'       => 2,
                'forfeit_sets_loser' => 0,
                'forfeit_set_points' => 0,   // no "0-25" rows for games
                'forfeit_points'     => 0,
            ],
        ];
    }
}

if (!function_exists('sportProfileDbColumns')) {
    /** Profile columns read from tbl_utility (category = 'sport'). */
    function sportProfileDbColumns(): array
    {
        return ['result_unit', 'scoring_mode', 'best_of', 'set_target', 'deciding_target',
                'win_by', 'jersey_min', 'jersey_max', 'roster_regular', 'libero_min',
                'libero_max', 'require_official', 'protest_window_minutes', 'allow_draw',
                'default_min_players', 'default_max_players'];
    }
}

if (!function_exists('sportProfileMerge')) {
    /** Overlay $overrides (non-null values only) and coerce types. */
    function sportProfileMerge(array $profile, array $overrides): array
    {
        $intKeys = ['best_of','set_target','deciding_target','win_by','forfeit_sets',
                    'forfeit_sets_loser','forfeit_set_points','forfeit_points','jersey_min',
                    'jersey_max','roster_regular','libero_min','libero_max','require_official',
                    'protest_window_minutes','allow_draw','final_best_of','default_min_players',
                    'default_max_players'];
        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '') continue;
            $profile[$key] = in_array($key, $intKeys, true) ? (int)$value : (string)$value;
        }
        return $profile;
    }
}

if (!function_exists('getSportProfile')) {
    /** Resolve the profile for a sport, optionally with one league's overrides. */
    function getSportProfile(mysqli $conn, string $sport, ?int $leagueId = null): array
    {
        static $cache = [];
        $sportKey = strtolower(trim($sport));
        $cacheKey = $sportKey . '#' . (int)$leagueId;
        if (isset($cache[$cacheKey])) return $cache[$cacheKey];

        $profile = sportProfileDefaults();
        $profile['sport'] = $sportKey;

        $presets = sportProfilePresets();
        if (isset($presets[$sportKey])) $profile = sportProfileMerge($profile, $presets[$sportKey]);

        // Admin-managed utility row
        $columns = implode(', ', array_map(fn($c) => '`' . $c . '`', sportProfileDbColumns()));
        $stmt = @$conn->prepare("SELECT $columns FROM tbl_utility WHERE category = 'sport' AND name = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $sport);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) $profile = sportProfileMerge($profile, $row);
        }

        if ($leagueId) {
            $lg = $conn->prepare("SELECT finals_pattern, protest_window_minutes, scoring_win_clean_points,
                                         scoring_win_close_points, scoring_loss_close_points
                                  FROM tbl_league WHERE id = ? LIMIT 1");
            if ($lg) {
                $lg->bind_param('i', $leagueId);
                $lg->execute();
                $lrow = $lg->get_result()->fetch_assoc();
                $lg->close();
                if ($lrow) {
                    $profile = sportProfileMerge($profile, [
                        'finals_pattern'         => $lrow['finals_pattern'] ?? null,
                        'protest_window_minutes' => $lrow['protest_window_minutes'] ?? null,
                        'win_clean_points'       => $lrow['scoring_win_clean_points'] ?? null,
                        'win_close_points'       => $lrow['scoring_win_close_points'] ?? null,
                        'loss_close_points'      => $lrow['scoring_loss_close_points'] ?? null,
                    ]);
                }
            }
        }

        // Derived: the championship match may be longer than the rest (Volleyball: BO5).
        $profile['final_best_of'] = (int)($profile['final_best_of'] ?? 0);
        if ($profile['final_best_of'] <= 0) {
            $profile['final_best_of'] = $sportKey === 'volleyball'
                ? max(3, (int)$profile['best_of'] + 2)
                : (int)$profile['best_of'];
        }

        $cache[$cacheKey] = $profile;
        return $profile;
    }
}

if (!function_exists('getLeagueSportProfile')) {
    /** Profile for a league (its sport + league overrides). */
    function getLeagueSportProfile(mysqli $conn, int $leagueId): array
    {
        static $sports = [];
        if (!isset($sports[$leagueId])) {
            $sports[$leagueId] = '';
            $stmt = $conn->prepare("SELECT sport FROM tbl_league WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $leagueId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $sports[$leagueId] = (string)($row['sport'] ?? '');
            }
        }
        return getSportProfile($conn, $sports[$leagueId], $leagueId);
    }
}

if (!function_exists('sportProfileUsesSets')) {
    function sportProfileUsesSets(array $profile): bool
    {
        return ($profile['result_unit'] ?? 'points') === 'sets';
    }
}

if (!function_exists('sportProfileUsesGames')) {
    /**
     * Game-based series (esports): the matchup is BO1/BO3/BO5/BO7 of GAMES, and
     * each game has a winner but no point target (unlike a volleyball set).
     *
     * Deliberately a SEPARATE predicate from sportProfileUsesSets() so the
     * Volleyball path (and every points-based sport) keeps its exact behaviour.
     */
    function sportProfileUsesGames(array $profile): bool
    {
        return ($profile['result_unit'] ?? 'points') === 'games';
    }
}

if (!function_exists('sportProfileUsesSeries')) {
    /**
     * Does a matchup of this sport consist of more than one unit (sets or games)?
     * This is the "is a series" question, kept apart from the "does a unit have a
     * point target" question answered by sportProfileUsesSets().
     */
    function sportProfileUsesSeries(array $profile): bool
    {
        return sportProfileUsesSets($profile) || sportProfileUsesGames($profile);
    }
}

if (!function_exists('sportProfileSeriesUnit')) {
    /** Name of the unit inside a series: 'Set', 'Game' or 'Point'. */
    function sportProfileSeriesUnit(array $profile): string
    {
        if (sportProfileUsesSets($profile)) return 'Set';
        if (sportProfileUsesGames($profile)) return 'Game';
        return 'Point';
    }
}


if (!function_exists('sportProfileLabels')) {
    /** UI wording for a profile (keeps every other sport on today's labels). */
    function sportProfileLabels(array $profile): array
    {
        if (sportProfileUsesSets($profile)) {
            return [
                'unit'        => 'Set',
                'unit_plural' => 'Sets',
                'score_a'     => 'Sets won',
                'series'      => 'Best of ' . max(1, (int)$profile['best_of']),
                'sets_won'    => 'SW',
                'sets_lost'   => 'SL',
                'set_ratio'   => 'Set Ratio',
                'points_won'  => 'PW',
                'points_lost' => 'PL',
                'point_ratio' => 'Pts Ratio',
            ];
        }
        if (sportProfileUsesGames($profile)) {
            return [
                'unit'        => 'Game',
                'unit_plural' => 'Games',
                'score_a'     => 'Games won',
                'series'      => 'Best of ' . max(1, (int)$profile['best_of']),
                'sets_won'    => 'GW',
                'sets_lost'   => 'GL',
                'set_ratio'   => 'Game Ratio',
                'points_won'  => 'PF',
                'points_lost' => 'PA',
                'point_ratio' => 'Quotient',
            ];
        }
        return [
            'unit'        => 'Point',
            'unit_plural' => 'Points',
            'score_a'     => 'Score',
            'series'      => '',
            'sets_won'    => 'W',
            'sets_lost'   => 'L',
            'set_ratio'   => 'Diff',
            'points_won'  => 'PF',
            'points_lost' => 'PA',
            'point_ratio' => 'Quotient',
        ];
    }
}

if (!function_exists('sportProfileSeriesLength')) {
    /** Best-of for a match; $isFinal uses the championship length (Volleyball: 5). */
    function sportProfileSeriesLength(array $profile, bool $isFinal = false): int
    {
        $bestOf = (int)($profile['best_of'] ?? 1);
        if ($isFinal) $bestOf = max($bestOf, (int)($profile['final_best_of'] ?? $bestOf));
        return max(1, $bestOf);
    }
}

if (!function_exists('sportProfileWinningSets')) {
    /** Sets needed to win a best-of series (BO3 -> 2, BO5 -> 3). */
    function sportProfileWinningSets(array $profile, int $bestOf): int
    {
        return max(1, (int)ceil(max(1, $bestOf) / 2));
    }
}

if (!function_exists('sportProfileSetTarget')) {
    /** Target points for a given set number (the deciding set is shorter). */
    function sportProfileSetTarget(array $profile, int $setNo, int $bestOf): int
    {
        $deciding = ($setNo >= max(1, $bestOf));
        if ($deciding && (int)$profile['deciding_target'] > 0) return (int)$profile['deciding_target'];
        return (int)$profile['set_target'];
    }
}

if (!function_exists('sportProfileValidateSet')) {
    /**
     * Validate one set score against the profile.
     * @return string|null error message, or null when the set is valid
     */
    function sportProfileValidateSet(array $profile, int $setNo, int $bestOf, int $a, int $b): ?string
    {
        // Unit rules only apply to series-based sports (sets or games); a
        // points-based sport keeps its own (legacy) score validation and may
        // still record a draw.
        if (!sportProfileUsesSeries($profile)) return null;

        // Games carry no point target, so only the "cannot end level" rule below
        // applies to them (set_target / win_by are 0 in the games preset).
        $unit  = sportProfileUsesGames($profile) ? 'game' : 'set';
        $Unit  = ucfirst($unit);
        $quant = $unit === 'set' ? 'points' : 'result';

        if ($a < 0 || $b < 0) return "{$Unit} {$setNo}: {$quant} cannot be negative.";
        if ($a === $b) return "{$Unit} {$setNo}: a {$unit} cannot end level ({$a}-{$b}).";

        $target = sportProfileSetTarget($profile, $setNo, $bestOf);
        $winBy  = max(1, (int)$profile['win_by']);
        $winner = max($a, $b);
        $loser  = min($a, $b);

        if ($target > 0 && $winner < $target) {
            return "Set {$setNo}: the winner must reach {$target} points.";
        }
        if ($winBy > 1 && ($winner - $loser) < $winBy) {
            return "Set {$setNo}: the winner must lead by {$winBy} points (got " . ($winner - $loser) . ').';
        }
        return null;
    }
}

if (!function_exists('sportProfileForfeitLine')) {
    /**
     * Official forfeit line for a best-of series.
     * Volleyball (sets): BO3 -> 2-0 (0-25, 0-25); BO5 -> 3-0 (0-25, 0-25, 0-25).
     * Games (esports):   BO3 -> 2-0 (1-0, 1-0); BO5 -> 3-0 (1-0, 1-0, 1-0).
     * Every other sport keeps the legacy 20-0 single-result line.
     *
     * @return array{a_sets:int,b_sets:int,sets:array<int,array{0:int,1:int}>,legacy_points:int}
     */
    function sportProfileForfeitLine(array $profile, int $bestOf, string $winnerSide): array
    {
        $bestOf     = max(1, $bestOf);
        $winnerSide = ($winnerSide === 'b') ? 'b' : 'a';

        if (!sportProfileUsesSeries($profile)) {
            $points = max(0, (int)$profile['forfeit_points']);
            return [
                'a_sets'        => $winnerSide === 'a' ? 1 : 0,
                'b_sets'        => $winnerSide === 'b' ? 1 : 0,
                'sets'          => [],
                'legacy_points' => $points,
            ];
        }

        $needed    = sportProfileWinningSets($profile, $bestOf);

        // Games: the awarded line is the winning threshold (BO3 -> 2-0), one
        // 1-0 / 0-1 row per awarded game. There are no point targets, so the
        // forfeit_set_points value is irrelevant here.
        if (sportProfileUsesGames($profile)) {
            $gameRows = [];
            for ($i = 0; $i < $needed; $i++) {
                $gameRows[] = $winnerSide === 'a' ? [1, 0] : [0, 1];
            }
            return [
                'a_sets'        => $winnerSide === 'a' ? $needed : 0,
                'b_sets'        => $winnerSide === 'b' ? $needed : 0,
                'sets'          => $gameRows,
                'legacy_points' => 0,
            ];
        }

        $setPoints = max(0, (int)$profile['forfeit_set_points']);
        $sets      = [];
        for ($i = 0; $i < $needed; $i++) {
            $sets[] = $winnerSide === 'a' ? [$setPoints, 0] : [0, $setPoints];
        }
        return [
            'a_sets'        => $winnerSide === 'a' ? $needed : 0,
            'b_sets'        => $winnerSide === 'b' ? $needed : 0,
            'sets'          => $sets,
            'legacy_points' => 0,
        ];
    }
}



