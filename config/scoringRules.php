<?php
/**
 * League scoring rules
 * --------------------
 * Standings points and the tiebreak order are per league so different
 * sports/tournaments rank teams their own way, e.g.:
 *
 *   Basketball (ANP2026): win = 2, loss = 1, forfeit = 0
 *                         tiebreaks: head-to-head, quotient, difference, points for
 *   Pickleball (legacy) : win = 3, loss = 0
 *
 * The columns live on tbl_league (database/migrations/034_anp2026_stages_and_rules.sql) and every
 * value falls back to a safe default, so the helper also works before the migration.
 */

if (!file_exists(__DIR__ . '/sportRules.php')) { /* optional */ } else { require_once __DIR__ . '/sportRules.php'; }

/** Tiebreak keys that may be configured, in preferential order. */
function allowedTiebreaks(): array {
    return ['head_to_head', 'set_ratio', 'point_ratio', 'quotient', 'difference',
            'set_difference', 'points_for', 'points_against', 'wins', 'name'];
}

/**
 * Read the scoring rules for a league.
 *
 * @return array{win:int,draw:int,loss:int,forfeit:int,tiebreaks:string[],order_raw:string}
 */
function getLeagueScoringRules(mysqli $conn, int $leagueId): array {
    static $cache = [];
    if (isset($cache[$leagueId])) return $cache[$leagueId];

    $rules = [
        'win'       => 3,
        'draw'      => 1,
        'loss'      => 0,
        'forfeit'   => 0,
        'mode'      => 'simple',
        'tiebreaks' => ['head_to_head', 'quotient', 'difference', 'points_for', 'name'],
        'order_raw' => 'head_to_head,quotient,difference,points_for,name',
    ];

    $stmt = @$conn->prepare("SELECT sport, scoring_win_points, scoring_draw_points, scoring_loss_points, scoring_forfeit_points, tiebreak_order, scoring_win_clean_points, scoring_win_close_points, scoring_loss_close_points FROM tbl_league WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $leagueId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $rules['win']     = (int)($row['scoring_win_points'] ?? $rules['win']);
            $rules['draw']    = (int)($row['scoring_draw_points'] ?? $rules['draw']);
            $rules['loss']    = (int)($row['scoring_loss_points'] ?? $rules['loss']);
            $rules['forfeit'] = (int)($row['scoring_forfeit_points'] ?? $rules['forfeit']);
            $raw = trim((string)($row['tiebreak_order'] ?? ''));
            if ($raw !== '') {
                $rules['order_raw'] = $raw;
                $keys = [];
                foreach (explode(',', $raw) as $key) {
                    $key = strtolower(trim($key));
                    if (in_array($key, allowedTiebreaks(), true)) $keys[] = $key;
                }
                if (!empty($keys)) $rules['tiebreaks'] = $keys;
            }

            // Set-based sports (Volleyball): 2-0 = 3, 2-1 = 2, 1-2 = 1, 0-2 = 0.
            $profile = function_exists('getSportProfile')
                ? getSportProfile($conn, (string)($row['sport'] ?? ''), $leagueId)
                : null;
            if ($profile && ($profile['scoring_mode'] ?? '') === 'set_line') {
                $rules['mode']           = 'set_line';
                $rules['set_win_target'] = max(1, (int)ceil(max(1, (int)$profile['best_of']) / 2));
                $rules['sf_win_clean']   = (int)($row['scoring_win_clean_points'] ?? 3);
                $rules['sf_win_close']   = (int)($row['scoring_win_close_points'] ?? 2);
                $rules['sf_loss_close']  = (int)($row['scoring_loss_close_points'] ?? 1);

                // ANP2026 §6.3: a tie in points is broken first by matches won;
                // the FIVB order continues with the set and then point ratio.
                if ($raw === '' || $raw === 'head_to_head,quotient,difference,points_for,name') {
                    $rules['tiebreaks'] = ['wins', 'set_ratio', 'point_ratio', 'head_to_head', 'name'];
                    $rules['order_raw'] = implode(',', $rules['tiebreaks']);
                }
            }
        }
    }

    $cache[$leagueId] = $rules;
    return $rules;
}

/**
 * Competition points for ONE match of a set-based sport (Volleyball ANP2026 §6.2).
 *
 *   win 2-0 (winner lost no set)      -> sf_win_clean   (3)
 *   win 2-1 (winner lost one set)     -> sf_win_close   (2)
 *   loss 1-2 (loser won target-1 sets)-> sf_loss_close  (1)
 *   loss 0-2 / forfeit / default      -> 0
 *
 * The official forfeit line is always a clean sweep (2-0 / 3-0), so the winner
 * of a forfeit automatically scores the clean-win points while the defaulting
 * team scores zero, exactly as §10 requires.
 *
 * @param array $metrics sets_won, sets_lost, win_type
 */
function awardSetLinePoints(array $metrics, array $rules): int
{
    $winType    = (string)($metrics['win_type'] ?? 'Regular');
    $setsWon    = (int)($metrics['sets_won'] ?? 0);
    $setsLost   = (int)($metrics['sets_lost'] ?? 0);

    // Double forfeit: both teams at fault -> nobody scores.
    if ($winType === 'DoubleForfeit') return 0;

    // A team that defaulted/withdrew scores nothing, whatever the line says.
    if (($metrics['defaulted'] ?? false)) return (int)$rules['forfeit'];

    $needed = max(1, (int)($rules['set_win_target'] ?? 2));
    if ($setsWon >= $needed) {
        return $setsLost === 0 ? (int)$rules['sf_win_clean'] : (int)$rules['sf_win_close'];
    }
    if ($setsWon === $needed - 1 && $setsLost >= $needed) {
        return (int)$rules['sf_loss_close'];
    }
    return 0;
}

/**
 * Points earned by one team row.
 *
 * A forfeit/walkover win still counts as a win; the team that loses by forfeit
 * receives `scoring_forfeit_points` (0 in ANP2026). Both sides of a double
 * forfeit receive forfeit points and no win.
 *
 * @param array $row wins, draws, losses, forfeit_wins, forfeit_losses, double_forfeits
 */
function computeTeamPoints(array $row, array $rules): int {
    $wins           = (int)($row['wins'] ?? 0);
    $draws          = (int)($row['draws'] ?? 0);
    $losses         = (int)($row['losses'] ?? 0);
    $forfeitWins    = (int)($row['forfeit_wins'] ?? 0);
    $forfeitLosses  = (int)($row['forfeit_losses'] ?? 0);
    $doubleForfeits = (int)($row['double_forfeits'] ?? 0);

    return ($wins + $forfeitWins) * (int)$rules['win']
         + $draws * (int)$rules['draw']
         + $losses * (int)$rules['loss']
         + ($forfeitLosses + $doubleForfeits) * (int)$rules['forfeit'];
}

/** Human-readable label for a configured tiebreak key. */
function tiebreakLabel(string $key): string {
    return match ($key) {
        'head_to_head'   => 'head-to-head',
        'quotient'       => 'quotient (PF/PA)',
        'difference'     => 'point difference',
        'points_for'     => 'points for',
        'points_against' => 'points against',
        'wins'           => 'wins',
        'name'           => 'team name',
        default          => $key,
    };
}

/**
 * Head-to-head win counts for a set of matches.
 * Returns [winnerTeamId => [loserTeamId => wins]] including forfeit/walkover wins.
 *
 * @param string $matchFilterSql extra condition on alias s, e.g. "s.stage_group_id = ?"
 * @param array  $matchParams    bound values for the placeholders in $matchFilterSql
 */
function getHeadToHeadWins(mysqli $conn, int $leagueId, string $matchFilterSql, array $matchParams = []): array {
    $sql = "SELECT s.team_a, s.team_b, s.winner
            FROM tbl_schedule s
            WHERE s.league_id = ? AND ($matchFilterSql)
              AND LOWER(s.status) = 'completed' AND s.published_at IS NOT NULL
              AND (s.is_archived = 0 OR s.is_archived IS NULL)
              AND CAST(s.winner AS UNSIGNED) > 0";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $types  = 'i' . str_repeat('i', count($matchParams));
    $params = array_merge([$leagueId], array_values($matchParams));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $h2h = [];
    while ($row = $res->fetch_assoc()) {
        $a = (int)$row['team_a'];
        $b = (int)$row['team_b'];
        $w = (int)$row['winner'];
        $loser = ($w === $a) ? $b : (($w === $b) ? $a : 0);
        if ($loser <= 0) continue;
        if (!isset($h2h[$w])) $h2h[$w] = [];
        $h2h[$w][$loser] = ($h2h[$w][$loser] ?? 0) + 1;
    }
    $stmt->close();
    return $h2h;
}

/**
 * Head-to-head comparison between two teams.
 * @return int negative when $a should rank above $b
 */
function headToHeadCompare(array $h2h, int $a, int $b): int {
    $aWins = (int)($h2h[$a][$b] ?? 0);
    $bWins = (int)($h2h[$b][$a] ?? 0);
    if ($aWins === $bWins) return 0;
    return $bWins <=> $aWins;
}

/**
 * Sort standings rows using the league's points + tiebreak configuration.
 * Adds `points`, `difference`, `quotient` and `rank` to every row.
 */
function sortStandings(array $rows, array $rules, array $h2h = []): array {
    foreach ($rows as &$row) {
        $pf = (int)($row['points_for'] ?? 0);
        $pa = (int)($row['points_against'] ?? 0);
        // Set-based providers score every match themselves and pass `points` in.
        if (!array_key_exists('points', $row) || $row['points'] === null) {
            $row['points'] = computeTeamPoints($row, $rules);
        }
        $row['difference'] = $pf - $pa;
        $row['quotient']   = $pa > 0 ? round($pf / $pa, 4) : (float)$pf;

        // Set / point ratios (Volleyball ANP2026 §6.2-6.3)
        $setsWon  = (int)($row['sets_won'] ?? 0);
        $setsLost = (int)($row['sets_lost'] ?? 0);
        $ptsWon   = (int)($row['points_won'] ?? 0) ?: $pf;
        $ptsLost  = (int)($row['points_lost'] ?? 0) ?: $pa;
        $row['set_ratio']      = $setsLost > 0 ? round($setsWon / $setsLost, 4) : (float)$setsWon;
        $row['set_difference'] = $setsWon - $setsLost;
        $row['point_ratio']    = $ptsLost > 0 ? round($ptsWon / $ptsLost, 4) : (float)$ptsWon;
    }
    unset($row);

    usort($rows, function (array $a, array $b) use ($rules, $h2h): int {
        if ((int)$a['points'] !== (int)$b['points']) return (int)$b['points'] <=> (int)$a['points'];

        foreach ($rules['tiebreaks'] as $key) {
            $cmp = 0;
            switch ($key) {
                case 'head_to_head':
                    $cmp = headToHeadCompare($h2h, (int)$a['id'], (int)$b['id']);
                    break;
                case 'quotient':
                    $cmp = $b['quotient'] <=> $a['quotient'];
                    break;
                case 'set_ratio':
                    $cmp = $b['set_ratio'] <=> $a['set_ratio'];
                    break;
                case 'point_ratio':
                    $cmp = $b['point_ratio'] <=> $a['point_ratio'];
                    break;
                case 'set_difference':
                    $cmp = (int)$b['set_difference'] <=> (int)$a['set_difference'];
                    break;
                case 'sets_won':
                    $cmp = (int)$b['sets_won'] <=> (int)$a['sets_won'];
                    break;
                case 'difference':
                    $cmp = (int)$b['difference'] <=> (int)$a['difference'];
                    break;
                case 'points_for':
                    $cmp = (int)$b['points_for'] <=> (int)$a['points_for'];
                    break;
                case 'points_against':
                    $cmp = (int)$a['points_against'] <=> (int)$b['points_against'];
                    break;
                case 'wins':
                    $cmp = (int)$b['wins'] <=> (int)$a['wins'];
                    break;
            }
            if ($cmp !== 0) return $cmp;
        }
        return strcasecmp((string)$a['team_name'], (string)$b['team_name']);
    });

    foreach ($rows as $index => &$row) $row['rank'] = $index + 1;
    unset($row);
    return $rows;
}

