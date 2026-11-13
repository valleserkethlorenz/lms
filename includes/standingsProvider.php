<?php
/**
 * Canonical league standings provider
 * -----------------------------------
 * Every standings surface (organizer standings, public standings, reports and
 * the per-stage tables) reads from here, so points, tiebreaks and the sport
 * profile are applied identically everywhere.  Before this file existed the
 * ranking SQL was duplicated in four places and three of them ignored the
 * league's configured scoring rules.
 *
 * Scoring is done per match, which is what the Volleyball set-line system
 * (2-0 = 3 pts, 2-1 = 2, 1-2 = 1, 0-2 = 0) requires.
 */

require_once __DIR__ . '/../config/sportRules.php';
require_once __DIR__ . '/../config/scoringRules.php';

if (!function_exists('getLeagueStandings')) {
    /**
     * @param array $options
     *        scope_sql     extra match filter (already parenthesised by the caller)
     *        scope_params  bound values for scope_sql placeholders
     *        team_ids      restrict to these team ids (stage tables)
     *        rules         pre-resolved scoring rules (optional)
     *        profile       pre-resolved sport profile (optional)
     *        group_by      'none' | 'legacy'
     *        group_filter  legacy group id
     *        h2h           pre-computed head-to-head map (optional)
     * @return array ranked rows with points / difference / quotient / ratios
     */
    function getLeagueStandings($conn, int $leagueId, array $options = []): array
    {
        $rules    = $options['rules']   ?? getLeagueScoringRules($conn, $leagueId);
        $profile  = $options['profile'] ?? getLeagueSportProfile($conn, $leagueId);
        $usesSets = sportProfileUsesSets($profile);
        $setMode  = (($rules['mode'] ?? 'simple') === 'set_line');
        $groupBy  = ($options['group_by'] ?? 'none') === 'legacy' ? 'legacy' : 'none';
        $groupFilter = isset($options['group_filter']) ? (int)$options['group_filter'] : null;

        /* ---------------- teams ---------------- */
        $teams = [];
        $teamSql = "SELECT t.id, t.team_name, t.team_image, t.progression_status";
        if ($groupBy === 'legacy') $teamSql .= ", g.id AS group_id, g.name AS group_name, g.display_order AS group_order";
        $teamSql .= " FROM tbl_team t";
        if ($groupBy === 'legacy') {
            $teamSql .= " LEFT JOIN tbl_league_group_team gt ON gt.team_id = t.id
                          LEFT JOIN tbl_league_group g ON g.id = gt.group_id AND g.league_id = t.league_id";
        }
        $teamSql .= " WHERE t.league_id = ? AND LOWER(t.status) = 'approved'";
        $teamParams = [$leagueId];
        $teamTypes  = 'i';
        if ($groupFilter !== null && $groupBy === 'legacy') { $teamSql .= " AND g.id = ?"; $teamParams[] = $groupFilter; $teamTypes .= 'i'; }
        if (!empty($options['team_ids']) && is_array($options['team_ids'])) {
            $ids = array_values(array_filter(array_map('intval', $options['team_ids'])));
            if (empty($ids)) return [];
            $teamSql .= " AND t.id IN (" . implode(',', $ids) . ")";
        }
        $teamSql .= $groupBy === 'legacy'
            ? " ORDER BY COALESCE(g.display_order,999), COALESCE(g.name,'Ungrouped'), t.team_name"
            : " ORDER BY t.team_name";

        $teamStmt = $conn->prepare($teamSql);
        if (!$teamStmt) return [];
        $teamStmt->bind_param($teamTypes, ...$teamParams);
        $teamStmt->execute();
        $teamRes = $teamStmt->get_result();
        $rows = [];
        while ($team = $teamRes->fetch_assoc()) {
            $id = (int)$team['id'];
            $rows[$id] = [
                'id'                => $id,
                'team_name'         => $team['team_name'],
                'team_image'        => $team['team_image'],
                'progression_status'=> $team['progression_status'],
                'group_id'          => isset($team['group_id']) ? (int)$team['group_id'] : null,
                'group_name'        => $team['group_name'] ?? null,
                'group_order'       => isset($team['group_order']) ? (int)$team['group_order'] : 999,
                'played'            => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
                'forfeit_wins'      => 0, 'forfeit_losses' => 0, 'double_forfeits' => 0,
                'points_for'        => 0, 'points_against' => 0,
                'sets_won'          => 0, 'sets_lost' => 0,
                'points_won'        => 0, 'points_lost' => 0,
            ];
        }
        $teamStmt->close();
        if (empty($rows)) return [];

        if ($setMode) {
            foreach ($rows as &$initialRow) { $initialRow['points'] = 0; }
            unset($initialRow);
        }

        /* ---------------- matches ---------------- */
        $scopeSql = trim((string)($options['scope_sql'] ?? ''));
        $matchSql = "SELECT s.id, s.team_a, s.team_b, s.winner, s.a_score, s.b_score, s.sets_a, s.sets_b,
                            s.a_points, s.b_points, s.penalty_a, s.penalty_b, s.win_type, s.best_of
                     FROM tbl_schedule s
                     WHERE s.league_id = ? AND LOWER(s.status) = 'completed' AND s.published_at IS NOT NULL
                       AND (s.is_archived = 0 OR s.is_archived IS NULL)";
        $matchParams = [$leagueId];
        $matchTypes  = 'i';
        if ($scopeSql !== '') {
            $matchSql .= " AND (" . $scopeSql . ")";
            foreach ((array)($options['scope_params'] ?? []) as $param) {
                $matchParams[] = is_int($param) ? (int)$param : (string)$param;
                $matchTypes   .= is_int($param) ? 'i' : 's';
            }
        }

        $matchStmt = $conn->prepare($matchSql);
        if ($matchStmt) {
            $matchStmt->bind_param($matchTypes, ...$matchParams);
            $matchStmt->execute();
            $matchRes = $matchStmt->get_result();
            while ($m = $matchRes->fetch_assoc()) {
                $winType  = (string)$m['win_type'];
                $winner   = (int)$m['winner'];
                $sideA = [
                    'id' => (int)$m['team_a'],
                    'units' => (int)$m['a_score'], 'unitsAgainst' => (int)$m['b_score'],
                    'sets' => (int)($m['sets_a'] ?? $m['a_score']), 'setsAgainst' => (int)($m['sets_b'] ?? $m['b_score']),
                    'points' => $usesSets ? (int)($m['a_points'] ?? 0) : (int)$m['a_score'],
                    'pointsAgainst' => $usesSets ? (int)($m['b_points'] ?? 0) : (int)$m['b_score'],
                    'penalty' => (int)$m['penalty_a'], 'penaltyAgainst' => (int)$m['penalty_b'],
                ];
                $sideB = [
                    'id' => (int)$m['team_b'],
                    'units' => (int)$m['b_score'], 'unitsAgainst' => (int)$m['a_score'],
                    'sets' => (int)($m['sets_b'] ?? $m['b_score']), 'setsAgainst' => (int)($m['sets_a'] ?? $m['a_score']),
                    'points' => $usesSets ? (int)($m['b_points'] ?? 0) : (int)$m['b_score'],
                    'pointsAgainst' => $usesSets ? (int)($m['a_points'] ?? 0) : (int)$m['a_score'],
                    'penalty' => (int)$m['penalty_b'], 'penaltyAgainst' => (int)$m['penalty_a'],
                ];

                foreach ([$sideA, $sideB] as $side) {
                    if (!isset($rows[$side['id']])) continue;
                    $row = &$rows[$side['id']];
                    $isWinner    = ($winner === $side['id']);
                    $isDefaulted = (($winType === 'Forfeit' || $winType === 'Walkover') && !$isWinner);

                    $row['played']++;
                    if ($winType === 'DoubleForfeit') {
                        $row['losses']++;
                        $row['double_forfeits']++;
                    } elseif ($isWinner) {
                        $row[(($winType === 'Forfeit' || $winType === 'Walkover') ? 'forfeit_wins' : 'wins')]++;
                    } elseif ($winner === 0) {
                        $row['draws']++;
                    } elseif ($winType === 'Forfeit' || $winType === 'Walkover') {
                        $row['forfeit_losses']++;
                    } else {
                        $row['losses']++;
                    }

                    $row['points_for']     += $side['points'] + $side['penalty'];
                    $row['points_against'] += $side['pointsAgainst'] + $side['penaltyAgainst'];

                    if ($usesSets) {
                        $row['sets_won']   += $side['sets'];
                        $row['sets_lost']  += $side['setsAgainst'];
                        $row['points_won'] += $side['points'];
                        $row['points_lost']+= $side['pointsAgainst'];
                    }

                    if ($setMode) {
                        $matchBestOf = (int)($m['best_of'] ?? 0);
                        if ($matchBestOf <= 0) $matchBestOf = max(1, (int)($profile['best_of'] ?? 3));
                        $matchRules = $rules;
                        $matchRules['set_win_target'] = sportProfileWinningSets($profile, $matchBestOf);
                        $row['points'] += awardSetLinePoints([
                            'sets_won'  => $usesSets ? $side['sets'] : 0,
                            'sets_lost' => $usesSets ? $side['setsAgainst'] : 0,
                            'win_type'  => $winType,
                            'defaulted' => $isDefaulted,
                        ], $matchRules);
                    }
                    unset($row);
                }
            }
            $matchStmt->close();
        }

        /* ---------------- rank ---------------- */
        $h2h = $options['h2h'] ?? getHeadToHeadWins($conn, $leagueId, $scopeSql !== '' ? $scopeSql : '1=1', $scopeSql !== '' ? (array)($options['scope_params'] ?? []) : []);
        return sortStandings(array_values($rows), $rules, $h2h);
    }
}
