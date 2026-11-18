<?php
/**
 * Tournament Progression Helper
 * Manages advancement through tournament stages
 */

require_once __DIR__ . '/stageHelper.php';

/**
 * Get current tournament stage status
 */
function getTournamentStatus($conn, $leagueId) {
    $sql = "SELECT DISTINCT stage FROM tbl_schedule WHERE league_id = ? ORDER BY stage";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leagueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stages = [];
    
    while ($row = $result->fetch_assoc()) {
        $stages[] = $row['stage'];
    }
    $stmt->close();
    
    return $stages;
}

/**
 * Check if group stage is complete
 */
function isGroupStageComplete($conn, $leagueId) {
    $sql = "SELECT COUNT(*) as total, 
            COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed
            FROM tbl_schedule 
            WHERE league_id = ? AND stage = 'group'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leagueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['total'] > 0 && $row['total'] === $row['completed'];
}

/**
 * Get tournament bracket structure
 */
function getTournamentBracket($conn, $leagueId) {
    $sql = "SELECT bracket, round, COUNT(*) as match_count,
            COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_count
            FROM tbl_schedule 
            WHERE league_id = ? AND bracket IS NOT NULL
            GROUP BY bracket, round
            ORDER BY bracket, round";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leagueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $bracket = [];
    
    while ($row = $result->fetch_assoc()) {
        $bracket[] = $row;
    }
    $stmt->close();
    
    return $bracket;
}

/**
 * Get tournament groups structure
 */
function getTournamentGroups($conn, $leagueId) {
    $sql = "SELECT group_stage as group_num, COUNT(*) as match_count,
            COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_count
            FROM tbl_schedule 
            WHERE league_id = ? AND stage = 'group'
            GROUP BY group_stage
            ORDER BY group_stage";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leagueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = [];
    
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }
    $stmt->close();
    
    return $groups;
}

/**
 * ============================================================
 * MATCH PROGRESSION ENGINE
 * Called after every completed match result.
 * ============================================================
 */

/**
 * Main entry point: progress the tournament after a match result.
 */
function progressMatchResult($conn, $leagueId, $scheduleId, $winnerId, $committeeId = null) {
    $actions = [];

    $lgStmt = $conn->prepare("SELECT league_name FROM tbl_league WHERE id = ?");
    $lgStmt->bind_param("i", $leagueId);
    $lgStmt->execute();
    $league = $lgStmt->get_result()->fetch_assoc();
    $lgStmt->close();
    if (!$league) return ['error' => 'League not found'];

    $leagueName = $league['league_name'] ?? '';

    $mStmt = $conn->prepare("SELECT round, bracket, stage, league_group_id, stage_id, stage_group_id, best_of, win_type, result_status, team_a, team_b, winner, a_score, b_score
                             FROM tbl_schedule WHERE id = ?");
    $mStmt->bind_param("i", $scheduleId);
    $mStmt->execute();
    $match = $mStmt->get_result()->fetch_assoc();
    $mStmt->close();
    if (!$match) return ['error' => 'Match not found'];

    // Track rows created during this call so a corrected result can undo them.
    $maxIdBefore = lastScheduleId($conn, $leagueId);

    /* Bracket-graph matches (single & double elimination drawn by the
       tournament engine) carry a node key. Their winner/loser links decide where
       each team goes next, so they run through the engine instead of the
       round-number progression below. Leagues whose matches predate migration
       041 have no node key and keep the legacy behaviour. */
    if (ensureTournamentEngineSchema($conn)) {
        $graphStmt = $conn->prepare("SELECT bracket_side, node_key, next_node_key, next_node_slot,
                                            loser_next_node_key, loser_next_node_slot
                                       FROM tbl_schedule WHERE id = ?");
        if ($graphStmt) {
            $graphStmt->bind_param('i', $scheduleId);
            $graphStmt->execute();
            $graphRow = $graphStmt->get_result()->fetch_assoc();
            $graphStmt->close();
            if ($graphRow) $match = array_merge($match, $graphRow);
        }
    }

    // A double forfeit puts both teams at fault: no winner and no automatic advance —
    // the Tournament Organizer decides how the pairing proceeds.
    if (($match['win_type'] ?? 'Regular') === 'DoubleForfeit') {
        $actions = [
            'double_forfeit' => true,
            'message'        => 'Double forfeit recorded — no automatic advance. The organizer must decide how this pairing proceeds.',
        ];
        if (function_exists('auditLog')) auditLog($conn, 'Double Forfeit', "Match ID $scheduleId ended in a double forfeit (league ID $leagueId)");
        notifyProgression($conn, $leagueId, $leagueName, $scheduleId, $actions);
        return $actions;
    }

    if (!empty($match['stage_id'])) {
        if (!empty($match['node_key'])) {
            $actions = progressBracketStageMatch($conn, $leagueId, $scheduleId, $winnerId, $match);
        } else {
            $actions = progressStageMatch($conn, $leagueId, $scheduleId, $winnerId, $match);
        }
        $actions['created_matches'] = logCreatedMatches($conn, $leagueId, $scheduleId, $maxIdBefore);
        notifyProgression($conn, $leagueId, $leagueName, $scheduleId, $actions);
        return $actions;
    }

    // No stage: a plain round-robin league. The result only feeds the standings.
    $actions = ['message' => 'No progression needed'];
    notifyProgression($conn, $leagueId, $leagueName, $scheduleId, $actions);
    return $actions;
}

/* ============================================================================
 * Result correction support (protests / reopened results)
 * ========================================================================== */

/** Highest schedule id of a league (used to spot engine-created rows). */
function lastScheduleId(mysqli $conn, int $leagueId): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(id),0) AS max_id FROM tbl_schedule WHERE league_id = ?");
    if (!$stmt) return 0;
    $stmt->bind_param('i', $leagueId); $stmt->execute();
    $maxId = (int)($stmt->get_result()->fetch_assoc()['max_id'] ?? 0);
    $stmt->close();
    return $maxId;
}

/**
 * Log every bracket row the engine created since $maxIdBefore so a corrected result
 * can remove them again.
 *
 * @return int[] created schedule ids
 */
function logCreatedMatches(mysqli $conn, int $leagueId, int $scheduleId, int $maxIdBefore): array {
    $created = [];
    $stmt = $conn->prepare("SELECT id FROM tbl_schedule WHERE league_id = ? AND id > ? ORDER BY id");
    if ($stmt) {
        $stmt->bind_param('ii', $leagueId, $maxIdBefore);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $created[] = (int)$row['id'];
        $stmt->close();
    }
    if (!empty($created)) logProgression($conn, $leagueId, $scheduleId, 'create_matches', null, null, null, $created);
    return $created;
}

/**
 * Undo the progression effects of one match: restore team statuses and drop the
 * bracket rows the engine created from that result. Used when an organizer reopens
 * a completed match for correction (protest upheld).
 *
 * @return array{teams:int[],removed_matches:int[]}
 */
function reverseProgression(mysqli $conn, int $leagueId, int $scheduleId): array {
    $result = ['teams' => [], 'removed_matches' => [], 'cleared_matches' => []];

    $stmt = $conn->prepare("SELECT * FROM tbl_progression_log WHERE league_id = ? AND schedule_id = ? AND action IN ('eliminate','champion','create_matches','fill_matches') ORDER BY id DESC");
    if (!$stmt) return $result;
    $stmt->bind_param('ii', $leagueId, $scheduleId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $teamId = (int)($row['team_id'] ?? 0);
        if ($teamId > 0) {
            $restore = ($row['before_status'] ?? '') !== '' ? $row['before_status'] : 'Undecided';
            $upd = $conn->prepare("UPDATE tbl_team SET progression_status = ?, progression_decided_at = NULL WHERE id = ?");
            $upd->bind_param('si', $restore, $teamId);
            $upd->execute();
            $upd->close();
            $result['teams'][] = $teamId;
        }
        if (empty($row['created_schedule_ids'])) continue;

        $ids = array_filter(array_map('intval', explode(',', (string)$row['created_schedule_ids'])));
        foreach ($ids as $relatedId) {
            if ($relatedId <= 0) continue;
            if ($row['action'] === 'fill_matches') {
                // Empty the placeholders this result had filled (never touch a played match).
                $clear = $conn->prepare("UPDATE tbl_schedule SET team_a = 0, team_b = 0, winner = 0, a_score = 0, b_score = 0, status = 'Upcoming' WHERE id = ? AND league_id = ? AND LOWER(status) <> 'completed'");
                $clear->bind_param('ii', $relatedId, $leagueId);
                $clear->execute();
                $clear->close();
                $result['cleared_matches'][] = $relatedId;
            } else {
                // A reset final removed by a correction must also release GF-M1's
                // winner/loser links, or the graph would keep pointing at a gone row.
                $metaStmt = $conn->prepare("SELECT stage_id, node_key FROM tbl_schedule WHERE id = ? AND league_id = ?");
                if ($metaStmt) {
                    $metaStmt->bind_param('ii', $relatedId, $leagueId);
                    $metaStmt->execute();
                    $relatedMeta = $metaStmt->get_result()->fetch_assoc();
                    $metaStmt->close();
                    if ($relatedMeta && (string)$relatedMeta['node_key'] === 'GF-M2'
                        && function_exists('setBracketResetLinks')) {
                        $gf1Stmt = $conn->prepare("SELECT id FROM tbl_schedule
                                                     WHERE league_id = ? AND stage_id = ? AND node_key = 'GF-M1'
                                                     ORDER BY id DESC LIMIT 1");
                        if ($gf1Stmt) {
                            $relatedStageId = (int)$relatedMeta['stage_id'];
                            $gf1Stmt->bind_param('ii', $leagueId, $relatedStageId);
                            $gf1Stmt->execute();
                            $gf1Row = $gf1Stmt->get_result()->fetch_assoc();
                            $gf1Stmt->close();
                            if ($gf1Row) setBracketResetLinks($conn, (int)$gf1Row['id'], false);
                        }
                    }
                }

                $del = $conn->prepare("DELETE FROM tbl_schedule WHERE id = ? AND league_id = ?");
                $del->bind_param('ii', $relatedId, $leagueId);
                $del->execute();
                $del->close();
                $result['removed_matches'][] = $relatedId;
            }
        }
    }

    $result['teams'] = array_values(array_unique($result['teams']));
    $result['removed_matches'] = array_values(array_unique($result['removed_matches']));
    $result['cleared_matches'] = array_values(array_unique($result['cleared_matches'] ?? []));
    logProgression($conn, $leagueId, $scheduleId, 'reversed', null, null, null, $result['removed_matches']);
    return $result;
}

function markTeamProgression($conn, $teamId, $status) {
    $stmt = $conn->prepare("UPDATE tbl_team SET progression_status=?, progression_decided_at=NOW() WHERE id=?");
    $stmt->bind_param("si", $status, $teamId); $stmt->execute(); $stmt->close();
}

function notifyProgression($conn, $leagueId, $leagueName, $scheduleId, $actions) {
    if (empty($actions) || isset($actions['error'])) return;
    $lgStmt = $conn->prepare("SELECT created_by FROM tbl_league WHERE id = ?");
    $lgStmt->bind_param("i", $leagueId); $lgStmt->execute();
    $lgRow = $lgStmt->get_result()->fetch_assoc(); $lgStmt->close();
    $orgId = $lgRow['created_by'] ?? null;
    if (!$orgId || !function_exists('createNotification')) return;

    if (!empty($actions['tournament_complete']) && !empty($actions['champion'])) {
        $cs = $conn->prepare("SELECT team_name FROM tbl_team WHERE id = ?");
        $cs->bind_param("i", $actions['champion']); $cs->execute();
        $cr = $cs->get_result()->fetch_assoc(); $cs->close();
        $championName = $cr['team_name'] ?? 'Unknown';
        createNotification($conn, $orgId, 'tournament_complete', "Tournament Complete!",
            $championName . " is the champion of \"$leagueName\"!", $leagueId);
        if (function_exists('notifyTeamRepresentatives')) {
            try {
                notifyTeamRepresentatives($conn, [(int) $actions['champion']], 'tournament_complete', 'Tournament Champion!',
                    $championName . " won \"$leagueName\"!", $leagueId);
            } catch (Throwable $e) {
                error_log('[notification] Champion notice failed: ' . $e->getMessage());
            }
        }
        return;
    }
    $rc = $actions['round_complete'] ?? $actions['stage_round_complete'] ?? $actions['winners_round_complete'] ?? $actions['losers_round_complete'] ?? $actions['knockout_round_complete'] ?? null;
    if ($rc !== null) {
        $br = isset($actions['winners_round_complete']) ? 'Winners' : (isset($actions['losers_round_complete']) ? 'Losers' : (isset($actions['knockout_round_complete']) ? 'Knockout' : ''));
        if (!empty($actions['bracket_side'])) {
            $br = $actions['bracket_side'] === 'losers' ? 'Losers' : ($actions['bracket_side'] === 'grand_final' ? 'Grand Final' : 'Winners');
        }
        $label = $br ? "$br Round $rc" : "Round $rc";
        createNotification($conn, $orgId, 'round_complete', "Round Complete",
            "$label in \"$leagueName\" is complete. Next round ready.", $leagueId);
    }
    if (!empty($actions['advanced']) && function_exists('notifyTeamRepresentatives')) {
        try {
            notifyTeamRepresentatives($conn, $actions['advanced'], 'round_complete', 'Team Advanced',
                "Your team advanced to the next round in \"$leagueName\".", $leagueId);
        } catch (Throwable $e) {
            error_log('[notification] Advancement notice failed: ' . $e->getMessage());
        }
    }
    if (!empty($actions['eliminated']) && function_exists('notifyTeamRepresentatives')) {
        try {
            notifyTeamRepresentatives($conn, $actions['eliminated'], 'round_complete', 'Team Eliminated',
                "Your team was eliminated from \"$leagueName\".", $leagueId);
        } catch (Throwable $e) {
            error_log('[notification] Elimination notice failed: ' . $e->getMessage());
        }
    }
    if (!empty($actions['ready_for_next_round'])) {
        createNotification($conn, $orgId, 'round_complete', "Swiss Round {$actions['round_complete']} Complete",
            "All matches done in \"$leagueName\". Generate next Swiss round.", $leagueId);
    }
    if (!empty($actions['group_stage_all_complete'])) {
        createNotification($conn, $orgId, 'group_stage_complete', "Group Stage Complete",
            "All group matches done in \"$leagueName\". Generate knockout bracket.", $leagueId);
    }
}

/**
 * Suggest qualifiers based on current standings.
 * Returns suggested teams per group with borderline highlights.
 */
function suggestQualifiers($conn, $leagueId, $qualifiersPerGroup = 1) {
    require_once __DIR__ . '/schedulingHelper.php';
    $suggestions = [];

    // Single grouping model: tbl_league_group when the organizer builds groups on
    // the Standings page, otherwise the legacy stage='group' tagging.
    foreach (getProgressionGroups($conn, $leagueId) as $group) {
        $standings = getProgressionGroupStandings($conn, $leagueId, $group);
        if (!empty($standings)) {
            $suggestions[] = _buildSuggestionPool($standings, (int)($group['group_no'] ?? 0), $qualifiersPerGroup, $group['label'] ?? null);
        }
    }

    if (empty($suggestions)) {
        $standings = getLeagueStandings($conn, $leagueId);
        if (!empty($standings)) $suggestions[] = _buildSuggestionPool($standings, 0, $qualifiersPerGroup, 'All Teams');
        return $suggestions;
    }
    return $suggestions;
}

function _buildSuggestionPool($standings, $groupNum, $qualifiersPerGroup, $label = null) {
    $cutoffIndex = min($qualifiersPerGroup, count($standings)) - 1;
    $cutoffPoints = ($cutoffIndex >= 0) ? ((int)$standings[$cutoffIndex]['wins'] * 3 + (int)$standings[$cutoffIndex]['draws']) : 0;
    $poolLabel = $label !== null && $label !== '' ? $label : ($groupNum > 0 ? 'Group ' . $groupNum : 'All Teams');
    $pool = ['group' => $groupNum, 'label' => $poolLabel, 'total_teams' => count($standings), 'qualifiers_requested' => $qualifiersPerGroup, 'suggested' => [], 'borderline' => [], 'not_qualified' => []];
    foreach ($standings as $index => $team) {
        $teamPoints = (int)$team['wins'] * 3 + (int)$team['draws'];
        $td = ['team_id' => (int)$team['id'], 'team_name' => $team['team_name'], 'rank' => $index + 1, 'points' => $teamPoints, 'wins' => (int)$team['wins'], 'draws' => (int)$team['draws'], 'losses' => (int)$team['losses'], 'points_for' => (int)$team['points_for'], 'points_against' => (int)$team['points_against'], 'differential' => (int)$team['points_for'] - (int)$team['points_against']];
        if ($index < $qualifiersPerGroup) { $td['is_borderline'] = ($teamPoints === $cutoffPoints && $index > 0); $pool['suggested'][] = $td; }
        elseif ($teamPoints === $cutoffPoints) { $td['is_borderline'] = true; $pool['borderline'][] = $td; }
        else { $pool['not_qualified'][] = $td; }
    }
    return $pool;
}

/**
 * Get teams with confirmed progression status
 */
function getConfirmedQualifiers($conn, $leagueId) {
    $stmt = $conn->prepare("SELECT id, team_name, progression_status, progression_decided_by, progression_decided_at FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'approved' AND progression_status = 'Qualified' ORDER BY progression_decided_at ASC");
    $stmt->bind_param("i", $leagueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $qualifiers = [];
    while ($row = $result->fetch_assoc()) $qualifiers[] = $row;
    $stmt->close();
    return $qualifiers;
}

/**
 * Bulk set progression status. Teams in $qualifiedTeamIds become Qualified, rest become Eliminated.
 */
function bulkSetProgression($conn, $leagueId, $qualifiedTeamIds, $organizerId) {
    $qualifiedTeamIds = array_map('intval', $qualifiedTeamIds);
    // Reset all to Undecided first
    $resetStmt = $conn->prepare("UPDATE tbl_team SET progression_status = 'Undecided', progression_decided_by = NULL, progression_decided_at = NULL WHERE league_id = ? AND LOWER(status) = 'approved'");
    $resetStmt->bind_param("i", $leagueId);
    $resetStmt->execute();
    $resetStmt->close();
    // Mark qualified
    if (!empty($qualifiedTeamIds)) {
        $ph = implode(',', array_fill(0, count($qualifiedTeamIds), '?'));
        $qualStmt = $conn->prepare("UPDATE tbl_team SET progression_status = 'Qualified', progression_decided_by = ?, progression_decided_at = NOW() WHERE id IN ($ph) AND league_id = ? AND LOWER(status) = 'approved'");
        if ($qualStmt) {
            $bp = array_merge([$organizerId], $qualifiedTeamIds, [$leagueId]);
            $bt = 'i' . str_repeat('i', count($qualifiedTeamIds)) . 'i';
            $qualStmt->bind_param($bt, ...$bp);
            $qualStmt->execute();
            $qualStmt->close();
        }
        // Mark rest as Eliminated
        $elimStmt = $conn->prepare("UPDATE tbl_team SET progression_status = 'Eliminated', progression_decided_by = ?, progression_decided_at = NOW() WHERE league_id = ? AND LOWER(status) = 'approved' AND id NOT IN ($ph)");
        if ($elimStmt) {
            $bp2 = array_merge([$organizerId, $leagueId], $qualifiedTeamIds);
            $bt2 = 'ii' . str_repeat('i', count($qualifiedTeamIds));
            $elimStmt->bind_param($bt2, ...$bp2);
            $elimStmt->execute();
            $elimStmt->close();
        }
    } else {
        $elimAll = $conn->prepare("UPDATE tbl_team SET progression_status = 'Eliminated', progression_decided_by = ?, progression_decided_at = NOW() WHERE league_id = ? AND LOWER(status) = 'approved'");
        $elimAll->bind_param("ii", $organizerId, $leagueId);
        $elimAll->execute();
        $elimAll->close();
    }
}

/**
 * Clear progression decisions for every approved team of a league.
 * Returns the number of rows reset.
 */
function resetProgression($conn, $leagueId) {
    $stmt = $conn->prepare("UPDATE tbl_team SET progression_status = 'Undecided', progression_decided_by = NULL, progression_decided_at = NULL WHERE league_id = ? AND LOWER(status) = 'approved'");
    $stmt->bind_param("i", $leagueId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

function getProgressionSummary($conn, $leagueId) {
    $lgStmt = $conn->prepare("SELECT league_name, grouping_enabled FROM tbl_league WHERE id = ?");
    $lgStmt->bind_param("i", $leagueId); $lgStmt->execute();
    $league = $lgStmt->get_result()->fetch_assoc(); $lgStmt->close();
    if (!$league) return null;

    $summary = [
        'league_name'         => $league['league_name'],
        'grouping_enabled'    => (int)($league['grouping_enabled'] ?? 0),
        'tournament_complete' => false,
        'champion'            => null,
        'total_matches'       => 0,
        'completed_matches'   => 0,
        'stages'              => [],
    ];

    // Overall match counts (bye rows are not matches)
    $mc = $conn->prepare("SELECT COUNT(*) as total, COUNT(CASE WHEN LOWER(status)='completed' THEN 1 END) as completed
                          FROM tbl_schedule
                          WHERE league_id=? AND (is_archived=0 OR is_archived IS NULL)
                            AND NOT (LOWER(status) = 'completed'
                                     AND (team_a = 0 OR team_b = 0)
                                     AND NOT (team_a = 0 AND team_b = 0))");
    $mc->bind_param("i", $leagueId); $mc->execute();
    $mr = $mc->get_result()->fetch_assoc(); $mc->close();
    $summary['total_matches'] = (int)$mr['total'];
    $summary['completed_matches'] = (int)$mr['completed'];

    // Champion
    $cs = $conn->prepare("SELECT id, team_name FROM tbl_team WHERE league_id=? AND progression_status='Qualified' LIMIT 1");
    $cs->bind_param("i", $leagueId); $cs->execute();
    $champ = $cs->get_result()->fetch_assoc(); $cs->close();
    if ($champ) { $summary['tournament_complete'] = true; $summary['champion'] = $champ; }

    // Stage status (the Setup → Matches step shows the detail)
    foreach (getLeagueStages($conn, $leagueId) as $stage) {
        $summary['stages'][] = [
            'id'       => (int)$stage['id'],
            'name'     => $stage['name'],
            'type'     => $stage['stage_type'],
            'complete' => isStageComplete($conn, $leagueId, (int)$stage['id']),
        ];
    }

    return $summary;
}

/* ============================================================================
 * Stage-based progression (ANP2026: brackets -> crossover/pools -> playoffs)
 * ========================================================================== */

/** Append an entry to tbl_progression_log so a corrected result can be reversed. */
function logProgression(mysqli $conn, int $leagueId, int $scheduleId, string $action, ?int $teamId = null, ?string $beforeStatus = null, ?string $afterStatus = null, array $createdScheduleIds = []): void {
    $created = !empty($createdScheduleIds) ? implode(',', array_map('intval', $createdScheduleIds)) : null;
    $stmt = $conn->prepare("INSERT INTO tbl_progression_log (league_id,schedule_id,action,team_id,before_status,after_status,created_schedule_ids) VALUES (?,?,?,?,?,?,?)");
    if (!$stmt) return;
    $stmt->bind_param('iisisss', $leagueId, $scheduleId, $action, $teamId, $beforeStatus, $afterStatus, $created);
    $stmt->execute();
    $stmt->close();
}

/** Highest round number used inside a stage. */
function getStageMaxRound(mysqli $conn, int $leagueId, int $stageId): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(round),0) AS max_round FROM tbl_schedule WHERE league_id = ? AND stage_id = ? AND (is_archived = 0 OR is_archived IS NULL)");
    if (!$stmt) return 0;
    $stmt->bind_param('ii', $leagueId, $stageId);
    $stmt->execute();
    $max = (int)($stmt->get_result()->fetch_assoc()['max_round'] ?? 0);
    $stmt->close();
    return $max;
}

/** Every playable match of the stage has been completed. */
function isStageComplete(mysqli $conn, int $leagueId, int $stageId): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total, COUNT(CASE WHEN LOWER(status) = 'completed' THEN 1 END) AS completed
                            FROM tbl_schedule
                            WHERE league_id = ? AND stage_id = ? AND team_a > 0 AND team_b > 0
                              AND (is_archived = 0 OR is_archived IS NULL)");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $leagueId, $stageId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && (int)$row['total'] > 0 && (int)$row['total'] === (int)$row['completed'];
}

/** Create the next round of a stage directly from its winners (no placeholders). */
function createStageRoundFromWinners(mysqli $conn, int $leagueId, int $stageId, int $round, array $winners): int {
    $stage = getStage($conn, $stageId);
    $tag   = in_array($stage['stage_type'] ?? 'knockout', ['knockout', 'placement'], true) ? $stage['stage_type'] : 'knockout';
    $created = 0;
    for ($i = 0; $i + 1 < count($winners); $i += 2) {
        if (createStageMatch($conn, $leagueId, $stageId, null, $round, (int)$winners[$i], (int)$winners[$i + 1], null, null, $tag) > 0) $created++;
    }
    return $created;
}

/**
 * Progress one match of a stage-based league.
 *
 * group / crossover : no elimination — the table decides who advances; the stage
 *                     only reports round and stage completion.
 * knockout          : loser eliminated, next round filled from the winners, and the
 *                     semifinal losers feed the Battle for Third. The champion is
 *                     declared when the final (last round) is decided.
 * placement         : records the third-place result.
 */
function progressStageMatch(mysqli $conn, int $leagueId, int $scheduleId, int $winnerId, array $match): array {
    $stageId = (int)$match['stage_id'];
    $round   = (int)($match['round'] ?? 1);
    $teamA   = (int)$match['team_a'];
    $teamB   = (int)$match['team_b'];
    $stage   = getStage($conn, $stageId);

    if (!$stage) return ['message' => 'Stage not found'];
    $type    = $stage['stage_type'];
    $actions = ['stage_id' => $stageId, 'stage_type' => $type, 'eliminated' => [], 'advanced' => []];

    if ($type === 'group' || $type === 'crossover') {
        if (isStageRoundComplete($conn, $leagueId, $stageId, $round)) {
            $actions['stage_round_complete'] = $round;
        }
        if (isStageComplete($conn, $leagueId, $stageId)) {
            /* A Swiss stage that still has planned rounds left is only "round
               complete": the organizer generates the next round instead of
               moving to the next stage. */
            $format  = stageFormat($stage);
            $planned = (int)($stage['rounds_planned'] ?? 0);
            $played  = getStageMaxRound($conn, $leagueId, $stageId);
            if ($format === 'swiss' && $planned > 0 && $played < $planned) {
                $actions['swiss_rounds_remaining'] = $planned - $played;
                $actions['ready_for_next_round']   = true;
                $actions['round_complete']         = $played;
                return $actions;
            }
            $actions['stage_complete'] = $stageId;
            $actions['ready_for_next_stage'] = true;
            // Structure presets can carry qualifiers_per_group: promote them into
            // the next stage automatically. Manual assignments always win.
            $promoted = autoPromoteStageQualifiers($conn, $leagueId, $stageId);
            if ($promoted !== null) $actions['auto_promoted'] = $promoted;
        }
        return $actions;
    }

    if ($type === 'placement') {
        if ($winnerId > 0) {
            $loser = ($winnerId === $teamA) ? $teamB : (($winnerId === $teamB) ? $teamA : 0);
            $actions['third_place'] = $winnerId;
            if ($loser > 0) {
                markTeamProgression($conn, $loser, 'Eliminated');
                logProgression($conn, $leagueId, $scheduleId, 'eliminate', $loser, null, 'Eliminated');
                $actions['eliminated'][] = $loser;
            }
        }
        return $actions;
    }

    /* knockout */
    $loser = ($winnerId === $teamA) ? $teamB : (($winnerId === $teamB) ? $teamA : 0);
    if ($loser > 0) {
        markTeamProgression($conn, $loser, 'Eliminated');
        logProgression($conn, $leagueId, $scheduleId, 'eliminate', $loser, null, 'Eliminated');
        $actions['eliminated'][] = $loser;
    }

    // Single elimination must advance the winner immediately, not only when the
    // whole round is finished. Filling the exact slot also repairs any earlier
    // completed match that missed its advance.
    $slotWrites = syncLegacyBracketStage($conn, $leagueId, $stageId);
    if ($winnerId > 0) $actions['advanced'][] = $winnerId;
    if ($slotWrites > 0) $actions['next_round_filled'] = $slotWrites;

    if (!isStageRoundComplete($conn, $leagueId, $stageId, $round)) return $actions;

    $winners     = getStageRoundWinners($conn, $leagueId, $stageId, $round);
    $totalRounds = getStageMaxRound($conn, $leagueId, $stageId);
    $actions['stage_round_complete'] = $round;
    $actions['winners'] = $winners;

    if ($round >= $totalRounds) {
        if (count($winners) === 1) {
            markTeamProgression($conn, $winners[0], 'Qualified');
            logProgression($conn, $leagueId, $scheduleId, 'champion', $winners[0], null, 'Qualified');
            $actions['champion'] = $winners[0];
            $actions['tournament_complete'] = true;
        } else {
            $actions['stage_complete'] = $stageId;
            $actions['ready_for_next_stage'] = true;
        }
        return $actions;
    }

    $nextRound = $round + 1;
    $nextRows  = getStageRoundMatches($conn, $leagueId, $stageId, $nextRound);
    if (!empty($nextRows)) {
        // Placeholder rows already exist: make sure every completed winner has
        // its exact slot (normally a no-op because the sync above did it).
        $filled = syncStageRoundAdvancement($conn, $leagueId, $stageId, $round);
    } else {
        // Older stages have no placeholder round: fall back to creating it.
        $filled = fillStageRoundFromWinners($conn, $leagueId, $stageId, $nextRound, $winners, $scheduleId);
        if ($filled === 0) {
            $filled = createStageRoundFromWinners($conn, $leagueId, $stageId, $nextRound, $winners);
        }
    }
    $actions['advanced'] = $winners;
    $actions['next_round_filled'] = max((int)($actions['next_round_filled'] ?? 0), $filled);

    // Semifinal losers fill the Battle for Third
    if ($round === $totalRounds - 1) {
        $losers = getStageRoundLosers($conn, $leagueId, $stageId, $round);
        if (count($losers) >= 2) {
            $placement = ensurePlacementStage($conn, $leagueId);
            if ($placement) {
                $placementStageId = (int)$placement['id'];
                if (fillPlacementFromLosers($conn, $leagueId, $placementStageId, $losers) === 0) {
                    createStageMatch($conn, $leagueId, $placementStageId, null, 1, (int)$losers[0], (int)$losers[1], 'L SF1', 'L SF2', 'placement');
                }
                $actions['placement_ready'] = true;
            }
        }
    }
    return $actions;
}

?>
