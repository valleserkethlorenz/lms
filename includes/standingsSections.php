<?php
/**
 * Stage-aware standings sections
 * ------------------------------
 * The public and organizer standings pages both read this builder instead of
 * assembling their own SQL. It turns the league's competition structure into a
 * list of display sections:
 *
 *   round robin / Swiss   -> one table per stage group (or one stage table)
 *   single / double elim. -> bracket sides grouped by round
 *   placement             -> placement match cards
 *   leagues without stages -> the legacy group / overall tables (unchanged)
 *
 * Every ranking delegates to includes/standingsProvider.php, so points,
 * tiebreaks, forfeits and set-based scoring stay canonical on every surface.
 */

require_once __DIR__ . '/../config/scoringRules.php';
require_once __DIR__ . '/standingsProvider.php';
require_once __DIR__ . '/stageHelper.php';
require_once __DIR__ . '/schedulingHelper.php';

if (!function_exists('standingsStageMatchSummary')) {
    /**
     * Published match totals for one stage.
     *
     * @return array{total:int,completed:int,complete:bool}
     */
    function standingsStageMatchSummary(mysqli $conn, int $leagueId, int $stageId, bool $publishedOnly = true): array
    {
        $summary = ['total' => 0, 'completed' => 0, 'complete' => false];
        $sql = "SELECT COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END), 0) AS completed
            FROM tbl_schedule
            WHERE league_id = ? AND stage_id = ?
              AND (is_archived = 0 OR is_archived IS NULL)
              AND team_a > 0 AND team_b > 0";
        if ($publishedOnly) $sql .= " AND published_at IS NOT NULL";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return $summary;
        $stmt->bind_param('ii', $leagueId, $stageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        $summary['total']     = (int)($row['total'] ?? 0);
        $summary['completed'] = (int)($row['completed'] ?? 0);
        $summary['complete']  = $summary['total'] > 0 && $summary['completed'] >= $summary['total'];
        return $summary;
    }
}

if (!function_exists('standingsBracketMatchSummary')) {
    /**
     * Match totals for a bracket / placement stage.
     *
     * Unlike a table stage, a bracket's later rounds are often drafts whose
     * teams are still TBD (0 v 0). They must count so an unfinished draw never
     * looks complete. Completed one-team rows are byes and are ignored.
     *
     * @return array{total:int,completed:int,complete:bool}
     */
    function standingsBracketMatchSummary(mysqli $conn, int $leagueId, int $stageId): array
    {
        $summary = ['total' => 0, 'completed' => 0, 'complete' => false];
        $sql = "SELECT COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END), 0) AS completed
            FROM tbl_schedule
            WHERE league_id = ? AND stage_id = ?
              AND (is_archived = 0 OR is_archived IS NULL)
              AND NOT (LOWER(status) = 'completed'
                       AND ((team_a = 0 AND team_b > 0) OR (team_b = 0 AND team_a > 0)))";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return $summary;
        $stmt->bind_param('ii', $leagueId, $stageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        $summary['total']     = (int)($row['total'] ?? 0);
        $summary['completed'] = (int)($row['completed'] ?? 0);
        $summary['complete']  = $summary['total'] > 0 && $summary['completed'] >= $summary['total'];
        return $summary;
    }
}

if (!function_exists('standingsStageMaxRound')) {
    /** Highest round number generated in a stage (drafts included). */
    function standingsStageMaxRound(mysqli $conn, int $leagueId, int $stageId): int
    {
        $stmt = $conn->prepare("SELECT COALESCE(MAX(round), 0) AS max_round
            FROM tbl_schedule
            WHERE league_id = ? AND stage_id = ?
              AND (is_archived = 0 OR is_archived IS NULL)");
        if (!$stmt) return 0;
        $stmt->bind_param('ii', $leagueId, $stageId);
        $stmt->execute();
        $max = (int)($stmt->get_result()->fetch_assoc()['max_round'] ?? 0);
        $stmt->close();
        return $max;
    }
}

if (!function_exists('standingsStageStatus')) {
    /**
     * Human status for a stage header.
     *
     * @return array{value:string,label:string,class:string}
     */
    function standingsStageStatus(array $stage, array $summary): array
    {
        $raw = strtolower(trim((string)($stage['stage_status'] ?? 'Active')));
        if ($raw === 'complete' || !empty($summary['complete'])) {
            return ['value' => 'Complete', 'label' => 'Complete', 'class' => 'is-complete'];
        }
        if ($raw === 'locked') {
            return ['value' => 'Locked', 'label' => 'Locked', 'class' => 'is-locked'];
        }
        if ($raw === 'pending') {
            return ['value' => 'Pending', 'label' => 'Pending', 'class' => 'is-pending'];
        }
        return ['value' => 'Active', 'label' => 'In progress', 'class' => 'is-active'];
    }
}

if (!function_exists('standingsStageCaption')) {
    /**
     * One-line description of a stage (format details plus match progress).
     */
    function standingsStageCaption(array $stage, string $format, int $groupCount, array $summary, int $entries, int $currentRound): string
    {
        $parts = [];
        if ($format === 'swiss') {
            $planned = (int)($stage['rounds_planned'] ?? 0);
            if ($planned > 0) {
                $round = max(1, min($planned, $currentRound > 0 ? $currentRound : 1));
                $parts[] = 'Round ' . $round . ' of ' . $planned;
            } else {
                $parts[] = 'Swiss system';
            }
        } elseif (stageFormatUsesBracket($format)) {
            $parts[] = $entries > 0
                ? $entries . ' entrant' . ($entries === 1 ? '' : 's')
                : 'Knockout bracket';
        } elseif ($format !== 'placement') {
            if ($groupCount > 0) {
                $parts[] = $groupCount . ' group' . ($groupCount === 1 ? '' : 's');
            }
            $qualifiers = (int)($stage['qualifiers_per_group'] ?? 0);
            if ($qualifiers > 0) {
                $parts[] = 'Top ' . $qualifiers . ' advance' . ($groupCount > 1 ? ' per group' : '');
            }
        }

        if ((int)($stage['carries_previous_results'] ?? 0) === 1) {
            $parts[] = 'Includes earlier results';
        }
        if ((int)($summary['total'] ?? 0) > 0) {
            $parts[] = (int)$summary['completed'] . ' of ' . (int)$summary['total'] . ' matches played';
        }

        return implode(' · ', array_values(array_filter($parts, static fn($part) => trim((string)$part) !== '')));
    }
}

if (!function_exists('standingsBracketSides')) {
    /**
     * Group bracket matches into display sides and rounds.
     *
     * @param array<int,array> $matches rows from getLeagueBracketSchedule()
     * @return array<int,array{key:string,label:string,match_count:int,rounds:array}>
     */
    function standingsBracketSides(array $matches): array
    {
        $sideOrder = ['winners', 'losers', 'grand_final', 'consolation'];
        $sideLabels = [
            'winners'     => 'Winners bracket',
            'losers'      => 'Losers bracket',
            'grand_final' => 'Grand final',
            'consolation' => 'Consolation',
        ];

        $grouped = [];
        foreach ($matches as $match) {
            $side = strtolower(trim((string)($match['bracket_side'] ?? '')));
            if (!in_array($side, $sideOrder, true)) {
                // Legacy knockout rows only carry bracket = 'knockout'.
                $side = 'winners';
            }
            $round = (int)($match['round'] ?? 1);
            if ($round <= 0) $round = 1;
            $grouped[$side][$round][] = $match;
        }

        $sides = [];
        foreach ($sideOrder as $side) {
            if (empty($grouped[$side])) continue;
            $roundBuckets = $grouped[$side];
            ksort($roundBuckets);
            $totalRounds = count($roundBuckets);
            $rounds = [];
            $index = 0;
            foreach ($roundBuckets as $roundNumber => $roundMatches) {
                $index++;
                usort($roundMatches, static function (array $a, array $b): int {
                    $keyA = (string)($a['node_key'] ?? '');
                    $keyB = (string)($b['node_key'] ?? '');
                    if ($keyA !== '' && $keyB !== '' && $keyA !== $keyB) {
                        return strnatcasecmp($keyA, $keyB);
                    }
                    return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
                });
                $label = bracketRoundLabel($index, $totalRounds, $side);
                if ($side === 'consolation') $label = 'Consolation ' . $label;
                $rounds[] = [
                    'round'   => (int)$roundNumber,
                    'label'   => $label,
                    'matches' => $roundMatches,
                ];
            }
            $sides[] = [
                'key'         => $side,
                'label'       => $sideLabels[$side],
                'match_count' => array_sum(array_map('count', $roundBuckets)),
                'rounds'      => $rounds,
            ];
        }

        return $sides;
    }
}

if (!function_exists('standingsPlacementMatches')) {
    /**
     * Every non-archived match of a placement stage. Unpublished rows keep their
     * draw (team names / seeds) but hide fixture details and results.
     *
     * @return array<int,array>
     */
    function standingsPlacementMatches(mysqli $conn, int $leagueId, int $stageId): array
    {
        $sql = "SELECT s.id, s.stage_id, s.stage_group_id, s.round, s.team_a, s.team_b,
                       s.a_score, s.b_score, s.sets_a, s.sets_b, s.a_points, s.b_points,
                       s.winner, s.win_type, s.status, s.published_at, s.date, s.time, s.venue,
                       s.seed_a, s.seed_b,
                       t_a.team_name AS team_a_name, t_b.team_name AS team_b_name,
                       sg.name AS stage_group_name
                FROM tbl_schedule s
                LEFT JOIN tbl_team t_a ON t_a.id = s.team_a
                LEFT JOIN tbl_team t_b ON t_b.id = s.team_b
                LEFT JOIN tbl_league_stage_group sg ON sg.id = s.stage_group_id
                WHERE s.league_id = ? AND s.stage_id = ?
                  AND (s.is_archived = 0 OR s.is_archived IS NULL)
                ORDER BY s.round, s.date, s.time, s.id";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('ii', $leagueId, $stageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $matches = [];
        while ($row = $result->fetch_assoc()) {
            $isPublished = $row['published_at'] !== null && $row['published_at'] !== '';
            if (!$isPublished) {
                $row['date'] = null;
                $row['time'] = null;
                $row['venue'] = null;
                $row['a_score'] = null;
                $row['b_score'] = null;
                $row['winner'] = 0;
            }
            $row['is_published'] = $isPublished;
            $matches[] = $row;
        }
        $stmt->close();
        return $matches;
    }
}

if (!function_exists('standingsUntaggedMatchCount')) {
    /** Published, completed matches that are not attached to any stage. */
    function standingsUntaggedMatchCount(mysqli $conn, int $leagueId, bool $excludeLegacyBracket = true): int
    {
        $sql = "SELECT COUNT(*) AS c
                FROM tbl_schedule
                WHERE league_id = ?
                  AND (stage_id IS NULL OR stage_id = 0)
                  AND LOWER(status) = 'completed'
                  AND published_at IS NOT NULL
                  AND (is_archived = 0 OR is_archived IS NULL)
                  AND team_a > 0 AND team_b > 0";
        if ($excludeLegacyBracket) {
            $sql .= " AND (bracket IS NULL OR bracket <> 'knockout')";
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) return 0;
        $stmt->bind_param('i', $leagueId);
        $stmt->execute();
        $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();
        return $count;
    }
}

if (!function_exists('standingsBracketMatchSummaryFromRows')) {
    /**
     * Same rule as standingsBracketMatchSummary(), for already-loaded rows
     * (legacy brackets have no stage_id to query).
     *
     * @return array{total:int,completed:int,complete:bool}
     */
    function standingsBracketMatchSummaryFromRows(array $matches): array
    {
        $total = 0;
        $completed = 0;
        foreach ($matches as $match) {
            $status = strtolower((string)($match['status'] ?? ''));
            $teamA  = (int)($match['team_a'] ?? 0);
            $teamB  = (int)($match['team_b'] ?? 0);
            $isCompletedBye = $status === 'completed'
                && (($teamA === 0 && $teamB > 0) || ($teamB === 0 && $teamA > 0));
            if ($isCompletedBye) continue;
            $total++;
            if ($status === 'completed') $completed++;
        }
        return ['total' => $total, 'completed' => $completed, 'complete' => $total > 0 && $completed >= $total];
    }
}

if (!function_exists('standingsTeamIdsFromMatches')) {
    /**
     * Distinct team ids that appear in a set of match rows.
     *
     * @return array<int,bool>
     */
    function standingsTeamIdsFromMatches(array $matches): array
    {
        $ids = [];
        foreach ($matches as $match) {
            $teamA = (int)($match['team_a'] ?? 0);
            $teamB = (int)($match['team_b'] ?? 0);
            if ($teamA > 0) $ids[$teamA] = true;
            if ($teamB > 0) $ids[$teamB] = true;
        }
        return $ids;
    }
}

if (!function_exists('standingsLegacySections')) {
    /**
     * Standings for a league that has no active stage structure. This preserves
     * the pre-stage behavior (per-group tables or one overall table) and adds a
     * bracket section for legacy knockout rows.
     *
     * @return array<int,array>
     */
    function standingsLegacySections(mysqli $conn, int $leagueId, array $rules, array $profile): array
    {
        $sections = [];
        $groupingEnabled = false;
        $stmt = $conn->prepare("SELECT grouping_enabled FROM tbl_league WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $leagueId);
            $stmt->execute();
            $groupingEnabled = (int)($stmt->get_result()->fetch_assoc()['grouping_enabled'] ?? 0) === 1;
            $stmt->close();
        }

        $groups = [];
        if ($groupingEnabled) {
            $stmt = $conn->prepare("SELECT id, name, display_order FROM tbl_league_group WHERE league_id = ? ORDER BY display_order, name");
            if ($stmt) {
                $stmt->bind_param('i', $leagueId);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) $groups[] = $row;
                $stmt->close();
            }
        }
        $groupOptions = [];
        foreach ($groups as $group) {
            $groupOptions[] = ['id' => (int)$group['id'], 'name' => (string)$group['name']];
        }

        $legacyBracket = [];
        foreach (getLeagueBracketSchedule($conn, $leagueId) as $match) {
            if ((int)($match['stage_id'] ?? 0) === 0) $legacyBracket[] = $match;
        }
        $hasLegacyBracket = !empty($legacyBracket);
        $excludeBracket = $hasLegacyBracket ? "(s.bracket IS NULL OR s.bracket <> 'knockout')" : '';

        $tableSection = static function (string $name, array $rows, ?int $groupId, string $groupName, array $groupOptions, bool $isLegacyGroup = false) : array {
            return [
                'kind'             => 'table',
                'stage_id'         => 0,
                'stage_name'       => $name,
                'stage_format'     => 'round_robin',
                'format_label'     => $isLegacyGroup ? 'Group' : 'Overall',
                'format_short'     => $isLegacyGroup ? 'Group' : 'Overall',
                'format_icon'      => 'bi-table',
                'stage_type'       => 'group',
                'stage_status'     => 'Active',
                'status_label'     => '',
                'status_class'     => 'is-neutral',
                'caption'          => '',
                'team_count'       => count($rows),
                'tables'           => [[
                    'group_id'    => $groupId,
                    'group_name'  => $groupName,
                    'group_order' => 0,
                    'sources'     => [],
                    'rows'        => $rows,
                ]],
                'groups'           => $groupOptions,
                'sides'            => [],
                'matches'          => [],
                'has_draw'         => !empty($rows),
                'entries'          => 0,
                'match_total'      => 0,
                'match_completed'  => 0,
                'qualifiers'       => 0,
                'carries_previous' => false,
                'stage_order'      => 0,
                'is_legacy_group'  => $isLegacyGroup,
                'is_overall'       => !$isLegacyGroup,
            ];
        };

        if ($groups) {
            $allScope = $excludeBracket !== '' ? $excludeBracket : '1=1';
            $allRankings = getLeagueStandings($conn, $leagueId, [
                'rules'     => $rules,
                'profile'   => $profile,
                'group_by'  => 'legacy',
                'scope_sql' => $allScope,
            ]);
            $seen = [];
            foreach ($groups as $group) {
                $groupId = (int)$group['id'];
                $scope = 's.league_group_id = ?';
                if ($excludeBracket !== '') $scope .= ' AND ' . $excludeBracket;
                $rows = getLeagueStandings($conn, $leagueId, [
                    'rules'        => $rules,
                    'profile'      => $profile,
                    'group_by'     => 'legacy',
                    'group_filter' => $groupId,
                    'scope_sql'    => $scope,
                    'scope_params' => [$groupId],
                ]);
                foreach ($rows as $row) $seen[(int)$row['id']] = true;
                $sections[] = $tableSection((string)$group['name'], $rows, $groupId, '', $groupOptions, true);
            }

            $leftoverIds = [];
            foreach ($allRankings as $row) {
                if (!isset($seen[(int)$row['id']])) $leftoverIds[] = (int)$row['id'];
            }
            if ($leftoverIds) {
                $rows = getLeagueStandings($conn, $leagueId, [
                    'rules'     => $rules,
                    'profile'   => $profile,
                    'group_by'  => 'legacy',
                    'team_ids'  => $leftoverIds,
                    'scope_sql' => '1=0',
                ]);
                foreach ($rows as &$row) $row['group_name'] = $row['group_name'] ?: 'Ungrouped';
                unset($row);
                $sections[] = $tableSection('Ungrouped', $rows, 0, '', $groupOptions, true);
            }
        } else {
            $rows = getLeagueStandings($conn, $leagueId, [
                'rules'     => $rules,
                'profile'   => $profile,
                'group_by'  => 'legacy',
                'scope_sql' => $excludeBracket !== '' ? $excludeBracket : '1=1',
            ]);
            if ($rows) $sections[] = $tableSection('League Rankings', $rows, null, '', [], false);
        }

        if ($hasLegacyBracket) {
            $summary = standingsBracketMatchSummaryFromRows($legacyBracket);
            $status = standingsStageStatus(['stage_status' => 'Active'], $summary);
            $teamIds = standingsTeamIdsFromMatches($legacyBracket);
            $sections[] = [
                'kind'              => 'bracket',
                'stage_id'          => 0,
                'stage_name'        => 'Knockout bracket',
                'stage_format'      => 'single_elimination',
                'format_label'      => 'Knockout',
                'format_short'      => 'Knockout',
                'format_icon'       => 'bi-diagram-3-fill',
                'stage_type'        => 'knockout',
                'stage_status'      => $status['value'],
                'status_label'      => $status['label'],
                'status_class'      => $status['class'],
                'caption'           => standingsStageCaption(['stage_status' => 'Active'], 'single_elimination', 0, $summary, count($teamIds), 0),
                'team_count'        => count($teamIds),
                'tables'            => [],
                'groups'            => [],
                'sides'             => standingsBracketSides($legacyBracket),
                'matches'           => $legacyBracket,
                'has_draw'          => true,
                'entries'           => count($teamIds),
                'match_total'       => $summary['total'],
                'match_completed'   => $summary['completed'],
                'qualifiers'        => 0,
                'carries_previous'  => false,
                'stage_order'       => 0,
                'is_legacy_group'   => false,
                'is_overall'        => false,
                'is_legacy_bracket' => true,
            ];
        }

        return $sections;
    }
}

if (!function_exists('getLeagueStandingsSections')) {
    /**
     * Build every standings section a league needs.
     *
     * @return array{mode:string,uses_sets:bool,profile:array,rules:array,stage_count:int,team_count:int,sections:array,untagged_count:int}
     */
    function getLeagueStandingsSections(mysqli $conn, int $leagueId): array
    {
        $rules    = getLeagueScoringRules($conn, $leagueId);
        $profile  = getLeagueSportProfile($conn, $leagueId);
        $usesSets = sportProfileUsesSets($profile);
        $stages   = getLeagueStages($conn, $leagueId);

        $teamCount = 0;
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'approved'");
        if ($stmt) {
            $stmt->bind_param('i', $leagueId);
            $stmt->execute();
            $teamCount = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();
        }

        if (empty($stages)) {
            return [
                'mode'           => 'legacy',
                'uses_sets'      => $usesSets,
                'profile'        => $profile,
                'rules'          => $rules,
                'stage_count'    => 0,
                'team_count'     => $teamCount,
                'sections'       => standingsLegacySections($conn, $leagueId, $rules, $profile),
                'untagged_count' => 0,
            ];
        }

        /* Stage groups are loaded once: the group list doubles as the source-name
           map for carry-over pools and as the organizer's move select. */
        $stageGroups = [];
        $stageGroupNames = [];
        foreach ($stages as $stage) {
            $stageId = (int)$stage['id'];
            $groups  = getStageGroups($conn, $stageId);
            $stageGroups[$stageId] = $groups;
            foreach ($groups as $group) $stageGroupNames[(int)$group['id']] = (string)$group['name'];
        }

        $catalog = [];
        foreach (getLeagueBracketStageCatalog($conn, $leagueId) as $item) {
            $itemId = (int)($item['id'] ?? 0);
            if ($itemId > 0) $catalog[$itemId] = $item;
        }

        $bracketByStage = [];
        $legacyBracketMatches = [];
        foreach (getLeagueBracketSchedule($conn, $leagueId) as $match) {
            $stageId = (int)($match['stage_id'] ?? 0);
            if ($stageId > 0) $bracketByStage[$stageId][] = $match;
            else $legacyBracketMatches[] = $match;
        }

        $sections = [];
        $formats  = tournamentFormats();

        foreach ($stages as $stage) {
            $stageId = (int)$stage['id'];
            $format  = stageFormat($stage);
            $formatMeta = $formats[$format] ?? [
                'label' => ucfirst(str_replace('_', ' ', $format)),
                'short' => ucfirst(str_replace('_', ' ', $format)),
                'type'  => (string)($stage['stage_type'] ?? 'group'),
            ];
            $groups = $stageGroups[$stageId] ?? [];
            $groupOptions = [];
            foreach ($groups as $group) {
                $groupOptions[] = ['id' => (int)$group['id'], 'name' => (string)$group['name']];
            }

            /* Table progress counts the whole fixture list (drafts included)
               so a stage with unplayed draft fixtures never reads complete.
               Bracket/placement branches recompute with their bye-aware rule. */
            $summary      = standingsStageMatchSummary($conn, $leagueId, $stageId, false);
            $status       = standingsStageStatus($stage, $summary);
            $currentRound = $format === 'swiss' ? standingsStageMaxRound($conn, $leagueId, $stageId) : 0;
            $entries      = (int)($catalog[$stageId]['entries'] ?? 0);

            $section = [
                'kind'             => 'table',
                'stage_id'         => $stageId,
                'stage_name'       => (string)$stage['name'],
                'stage_format'     => $format,
                'format_label'     => (string)($formatMeta['label'] ?? $format),
                'format_short'     => (string)($formatMeta['short'] ?? $format),
                'format_icon'      => 'bi-table',
                'stage_type'       => (string)($stage['stage_type'] ?? 'group'),
                'stage_status'     => $status['value'],
                'status_label'     => $status['label'],
                'status_class'     => $status['class'],
                'caption'          => '',
                'team_count'       => 0,
                'tables'           => [],
                'groups'           => $groupOptions,
                'sides'            => [],
                'matches'          => [],
                'has_draw'         => false,
                'entries'          => $entries,
                'match_total'      => $summary['total'],
                'match_completed'  => $summary['completed'],
                'qualifiers'       => (int)($stage['qualifiers_per_group'] ?? 0),
                'carries_previous' => (int)($stage['carries_previous_results'] ?? 0) === 1,
                'stage_order'      => (int)($stage['display_order'] ?? 0),
                'stage_has_matches'=> false,
                'is_legacy_group'  => false,
                'is_overall'       => false,
            ];

            if (stageFormatUsesBracket($format)) {
                $matches = $bracketByStage[$stageId] ?? [];
                $teamIds = standingsTeamIdsFromMatches($matches);
                /* A bracket is not complete while later rounds are still drafts:
                   count every playable node, not only the published ones. */
                $bracketSummary = standingsBracketMatchSummary($conn, $leagueId, $stageId);
                $bracketStatus  = standingsStageStatus($stage, $bracketSummary);
                $section['kind']            = 'bracket';
                $section['format_icon']     = 'bi-diagram-3-fill';
                $section['sides']           = standingsBracketSides($matches);
                $section['matches']         = $matches;
                $section['has_draw']        = !empty($matches);
                $section['team_count']      = count($teamIds) > 0 ? count($teamIds) : $entries;
                $section['stage_status']    = $bracketStatus['value'];
                $section['status_label']    = $bracketStatus['label'];
                $section['status_class']    = $bracketStatus['class'];
                $section['match_total']       = $bracketSummary['total'];
                $section['match_completed']   = $bracketSummary['completed'];
                $section['stage_has_matches'] = $bracketSummary['total'] > 0;
                $captionEntries               = count($teamIds) > 0 ? count($teamIds) : $entries;
                $section['caption']         = standingsStageCaption($stage, $format, count($groups), $bracketSummary, $captionEntries, $currentRound);
                $sections[] = $section;
                continue;
            }

            if ($format === 'placement') {
                $matches = standingsPlacementMatches($conn, $leagueId, $stageId);
                $teamIds = standingsTeamIdsFromMatches($matches);
                $placementSummary = standingsBracketMatchSummary($conn, $leagueId, $stageId);
                $placementStatus  = standingsStageStatus($stage, $placementSummary);
                $section['kind']            = 'placement';
                $section['format_icon']     = 'bi-award-fill';
                $section['matches']         = $matches;
                $section['has_draw']        = !empty($matches);
                $section['team_count']      = count($teamIds);
                $section['stage_status']    = $placementStatus['value'];
                $section['status_label']    = $placementStatus['label'];
                $section['status_class']    = $placementStatus['class'];
                $section['match_total']       = $placementSummary['total'];
                $section['match_completed']   = $placementSummary['completed'];
                $section['stage_has_matches'] = $placementSummary['total'] > 0;
                $section['caption']           = standingsStageCaption($stage, $format, count($groups), $placementSummary, count($teamIds), $currentRound);
                $sections[] = $section;
                continue;
            }

            /* Round robin / Swiss: one table per stage group, or one stage
               table when the stage has no groups. */
            $tables = [];
            $stageTeamCount = 0;
            if ($groups) {
                foreach ($groups as $group) {
                    $groupId = (int)$group['id'];
                    $rows = getStageGroupStandings($conn, $leagueId, $groupId, $rules);
                    $stageTeamCount += count($rows);
                    $sourceNames = [];
                    foreach (getStageGroupSources($conn, $groupId) as $sourceId) {
                        $sourceNames[] = $stageGroupNames[$sourceId] ?? ('Group #' . $sourceId);
                    }
                    $tables[] = [
                        'group_id'    => $groupId,
                        'group_name'  => (string)$group['name'],
                        'group_order' => (int)($group['display_order'] ?? 0),
                        'sources'     => $sourceNames,
                        'rows'        => $rows,
                    ];
                }
            } else {
                $rows = getStageStandings($conn, $leagueId, $stageId, $rules);
                $stageTeamCount = count($rows);
                $tables[] = [
                    'group_id'    => null,
                    'group_name'  => null,
                    'group_order' => 0,
                    'sources'     => [],
                    'rows'        => $rows,
                ];
            }

            $section['kind']             = 'table';
            $section['tables']           = $tables;
            $section['team_count']       = $stageTeamCount;
            $section['has_draw']         = $stageTeamCount > 0;
            $section['stage_has_matches']= (int)$summary['total'] > 0;
            if (count($tables) === 1 && !empty($tables[0]['group_name'])) {
                $section['group_name'] = $tables[0]['group_name'];
            }
            $section['caption'] = standingsStageCaption($stage, $format, count($groups), $summary, $entries, $currentRound);
            $sections[] = $section;
        }

        if ($legacyBracketMatches) {
            $bracketSummary = standingsBracketMatchSummaryFromRows($legacyBracketMatches);
            $bracketStatus = standingsStageStatus(['stage_status' => 'Active'], $bracketSummary);
            $bracketTeamIds = standingsTeamIdsFromMatches($legacyBracketMatches);
            $sections[] = [
                'kind'              => 'bracket',
                'stage_id'          => 0,
                'stage_name'        => 'Knockout bracket',
                'stage_format'      => 'single_elimination',
                'format_label'      => 'Knockout',
                'format_short'      => 'Knockout',
                'format_icon'       => 'bi-diagram-3-fill',
                'stage_type'        => 'knockout',
                'stage_status'      => $bracketStatus['value'],
                'status_label'      => $bracketStatus['label'],
                'status_class'      => $bracketStatus['class'],
                'caption'           => standingsStageCaption(['stage_status' => 'Active'], 'single_elimination', 0, $bracketSummary, count($bracketTeamIds), 0),
                'team_count'        => count($bracketTeamIds),
                'tables'            => [],
                'groups'            => [],
                'sides'             => standingsBracketSides($legacyBracketMatches),
                'matches'           => $legacyBracketMatches,
                'has_draw'          => true,
                'entries'           => count($bracketTeamIds),
                'match_total'       => $bracketSummary['total'],
                'match_completed'   => $bracketSummary['completed'],
                'qualifiers'        => 0,
                'carries_previous'  => false,
                'stage_order'       => 9998,
                'is_legacy_group'   => false,
                'is_overall'        => false,
                'is_legacy_bracket' => true,
            ];
        }

        $untaggedCount = standingsUntaggedMatchCount($conn, $leagueId, true);
        if ($untaggedCount > 0) {
            $scope = "(s.stage_id IS NULL OR s.stage_id = 0) AND (s.bracket IS NULL OR s.bracket <> 'knockout')";
            $rows = getLeagueStandings($conn, $leagueId, [
                'rules'     => $rules,
                'profile'   => $profile,
                'group_by'  => 'legacy',
                'scope_sql' => $scope,
            ]);
            if ($rows) {
                $sections[] = [
                    'kind'             => 'table',
                    'stage_id'         => 0,
                    'stage_name'       => 'Overall standings',
                    'stage_format'     => 'overall',
                    'format_label'     => 'Untagged matches',
                    'format_short'     => 'Overall',
                    'format_icon'      => 'bi-bar-chart-line-fill',
                    'stage_type'       => 'group',
                    'stage_status'     => 'Active',
                    'status_label'     => '',
                    'status_class'     => 'is-neutral',
                    'caption'          => $untaggedCount . ' published match' . ($untaggedCount === 1 ? '' : 'es') . ' not assigned to a stage',
                    'team_count'       => count($rows),
                    'tables'           => [[
                        'group_id'    => null,
                        'group_name'  => null,
                        'group_order' => 0,
                        'sources'     => [],
                        'rows'        => $rows,
                    ]],
                    'groups'           => [],
                    'sides'            => [],
                    'matches'          => [],
                    'has_draw'         => true,
                    'entries'          => 0,
                    'match_total'      => $untaggedCount,
                    'match_completed'  => $untaggedCount,
                    'qualifiers'       => 0,
                    'carries_previous' => false,
                    'stage_order'      => 9999,
                    'is_legacy_group'  => false,
                    'is_overall'       => true,
                ];
            }
        }

        return [
            'mode'           => 'stages',
            'uses_sets'      => $usesSets,
            'profile'        => $profile,
            'rules'          => $rules,
            'stage_count'    => count($stages),
            'team_count'     => $teamCount,
            'sections'       => $sections,
            'untagged_count' => $untaggedCount,
        ];
    }
}
