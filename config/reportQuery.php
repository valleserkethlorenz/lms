<?php
/**
 * Report Query Builder - Shared logic for all report endpoints.
 *
 * All report types use this single builder so the on-screen table, Excel,
 * CSV, PDF and print views always agree. Standings deliberately delegates to
 * includes/standingsProvider.php, the canonical standings implementation used
 * by the organizer and public standings pages.
 */
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/reportFilters.php';
require_once __DIR__ . '/leagueStatus.php';
require_once __DIR__ . '/../includes/standingsProvider.php';

if (!class_exists('ReportAccessDeniedException')) {
    class ReportAccessDeniedException extends RuntimeException {}
}

if (!class_exists('ReportExecutionException')) {
    class ReportExecutionException extends RuntimeException {}
}

if (!function_exists('reportFetchAll')) {
    /**
     * Prepare, bind, execute and fetch a report query. Database errors are
     * logged privately and surfaced as a generic execution failure so SQL
     * details never reach the browser.
     *
     * @return array<int,array<string,mixed>>
     * @throws ReportExecutionException
     */
    function reportFetchAll(mysqli $conn, string $sql, string $bindTypes = '', array $params = []): array
    {
        try {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException($conn->error ?: 'Unable to prepare the selected report.');
            }
            if ($bindTypes !== '') {
                $stmt->bind_param($bindTypes, ...$params);
            }
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error ?: 'Unable to execute the selected report.');
            }
            $result = $stmt->get_result();
            if (!($result instanceof mysqli_result)) {
                throw new RuntimeException('Unable to read the selected report.');
            }
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
            return $rows;
        } catch (ReportExecutionException $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('[REPORT] Query failed: ' . get_class($e) . ': ' . $e->getMessage());
            throw new ReportExecutionException('Unable to generate the selected report.');
        }
    }
}

if (!function_exists('reportAppendDateRange')) {
    /**
     * Append a sargable date range to a report query.
     *
     * @param string $column     Qualified column name (hard-coded by callers).
     * @param bool   $dateColumn True for DATE columns, false for DATETIME columns.
     */
    function reportAppendDateRange(string &$sql, array &$params, string &$types, string $column, string $from, string $to, bool $dateColumn): void
    {
        if ($from !== '') {
            $sql .= " AND $column >= ?";
            $params[] = $from;
            $types .= 's';
        }
        if ($to !== '') {
            if ($dateColumn) {
                $sql .= " AND $column <= ?";
                $params[] = $to;
            } else {
                $sql .= " AND $column < DATE_ADD(?, INTERVAL 1 DAY)";
                $params[] = $to;
            }
            $types .= 's';
        }
    }
}

if (!function_exists('buildReportQuery')) {
    /**
     * Build one report.
     *
     * @param array $filters Normalised filters from reportFiltersFromRequest().
     * @param array $options row_limit: 0 = unlimited; any positive value caps the rows.
     * @return array{rows:array,title:string,filters:array,generated_at:string,type:string,row_count:int,truncated:bool,league:?array,leagues:array}
     * @throws InvalidArgumentException|ReportAccessDeniedException|ReportExecutionException
     */
    function buildReportQuery(mysqli $conn, int $userId, array $filters, array $options = []): array
    {
        $filters = array_merge([
            'report_type'   => 'registrations',
            'league_id'     => 0,
            'sport'         => '',
            'division'      => '',
            'category'      => '',
            'league_status' => '',
            'from_date'     => '',
            'to_date'       => '',
        ], $filters);

        validateReportFilters($filters);

        $type       = (string) $filters['report_type'];
        $title      = reportTypeTitles()[$type];
        $leagueId   = (int) $filters['league_id'];
        $from       = (string) $filters['from_date'];
        $to         = (string) $filters['to_date'];
        $rowLimit   = max(0, (int) ($options['row_limit'] ?? 0));

        if ($leagueId > 0 && !organizerOwnsLeague($conn, $leagueId, $userId)) {
            if (class_exists('SecurityLogger')) {
                SecurityLogger::unauthorizedAccess("Organizer $userId attempted to report on league $leagueId without ownership");
            }
            throw new ReportAccessDeniedException('You may only report on leagues you own.');
        }

        /* ---------------- leagues + canonical status ---------------- */
        $leagueSql = "SELECT l.id,l.league_name,l.sport,l.division,l.category,l.start_date,l.end_date,
                             l.regis_start,l.regis_end,l.max_team,
                             COALESCE(l.registration_window_open,1) AS registration_window_open,
                             l.publication_status,l.grouping_enabled,l.min_player,l.max_player,
                             (SELECT COUNT(*) FROM tbl_team tx
                               WHERE tx.league_id=l.id AND LOWER(tx.status)='approved') AS current_teams
                        FROM tbl_league l
                       WHERE l.created_by=? AND (l.is_archived=0 OR l.is_archived IS NULL)";
        $leagueParams = [$userId];
        $leagueTypes  = 'i';
        if ($leagueId > 0) {
            $leagueSql .= ' AND l.id=?';
            $leagueParams[] = $leagueId;
            $leagueTypes .= 'i';
        }
        foreach (['l.sport' => 'sport', 'l.division' => 'division', 'l.category' => 'category'] as $column => $key) {
            // A selected league already defines its sport/division/category;
            // ignoring stale hidden values keeps direct API calls predictable.
            if ($leagueId === 0 && (string) $filters[$key] !== '') {
                $leagueSql .= " AND $column=?";
                $leagueParams[] = (string) $filters[$key];
                $leagueTypes .= 's';
            }
        }
        $leagueSql .= ' ORDER BY l.league_name';

        $allLeagues = reportFetchAll($conn, $leagueSql, $leagueTypes, $leagueParams);
        foreach ($allLeagues as &$leagueRow) {
            $leagueRow['report_status'] = (string) ($leagueRow['publication_status'] ?? '') === 'Draft'
                ? 'Draft'
                : getLeagueStatus($leagueRow);
        }
        unset($leagueRow);

        $selectedLeague = null;
        if ($leagueId > 0) {
            foreach ($allLeagues as $leagueRow) {
                if ((int) $leagueRow['id'] === $leagueId) {
                    $selectedLeague = $leagueRow;
                    break;
                }
            }
        }

        $leagues = $allLeagues;
        $statusFilter = (string) $filters['league_status'];
        if ($statusFilter !== '') {
            $leagues = array_values(array_filter(
                $leagues,
                static fn(array $row): bool => (string) ($row['report_status'] ?? '') === $statusFilter
            ));
        }

        $filtersDisplay = reportFiltersForDisplay($filters, $selectedLeague);
        $makeResult = static function (array $rows, bool $truncated) use ($type, $title, $filtersDisplay, $selectedLeague, $leagues): array {
            return [
                'rows'         => $rows,
                'title'        => $title,
                'filters'      => $filtersDisplay,
                'generated_at' => date('Y-m-d H:i:s'),
                'type'         => $type,
                'row_count'    => count($rows),
                'truncated'    => $truncated,
                'league'       => $selectedLeague,
                'leagues'      => $leagues,
            ];
        };

        if (!$leagues) {
            return $makeResult([], false);
        }



        $leagueIds   = array_map(static fn(array $row): int => (int) $row['id'], $leagues);
        $placeholders = implode(',', array_fill(0, count($leagueIds), '?'));
        $base        = "l.created_by=? AND l.id IN ($placeholders)";
        $baseParams  = array_merge([$userId], $leagueIds);
        $baseTypes   = 'i' . str_repeat('i', count($leagueIds));

        $rows      = [];
        $truncated = false;
        $sql       = '';
        $params    = $baseParams;
        $types     = $baseTypes;

        switch ($type) {
            case 'registrations':
                $sql = "SELECT l.league_name AS League,t.team_name AS Team,t.team_repre AS Representative,
                               t.email AS Email,t.phone AS Phone,t.status AS Registration_Status,
                               t.progression_status AS Progression,DATE(t.created) AS Submitted
                          FROM tbl_team t
                          JOIN tbl_league l ON l.id=t.league_id
                         WHERE $base";
                reportAppendDateRange($sql, $params, $types, 't.created', $from, $to, false);
                $sql .= ' ORDER BY l.league_name,t.created,t.team_name';
                break;

            case 'approved_teams':
                $sql = "SELECT l.league_name AS League,l.sport AS Sport,l.division AS Division,
                               l.category AS Category,t.team_name AS Team,t.team_repre AS Representative,
                               t.progression_status AS Progression,
                               DATE(COALESCE(t.reviewed_at,t.created)) AS Approved_On
                          FROM tbl_team t
                          JOIN tbl_league l ON l.id=t.league_id
                         WHERE $base AND LOWER(t.status)='approved'";
                reportAppendDateRange($sql, $params, $types, 'COALESCE(t.reviewed_at,t.created)', $from, $to, false);
                $sql .= ' ORDER BY l.league_name,t.team_name';
                break;

            case 'rosters':
                $sql = "SELECT l.league_name AS League,t.team_name AS Team,t.team_repre AS Representative,
                               COALESCE(p.name,'(no players)') AS Player,p.role AS Role,
                               p.jersey_number AS Jersey_No,p.gender AS Gender,
                               COALESCE(p.birthdate,'') AS Birthdate,p.barangay AS Barangay,
                               p.eligibility_status AS Eligibility,
                               (CASE WHEN p.document IS NOT NULL AND p.document<>'' THEN 1 ELSE 0 END
                                + (SELECT COUNT(*) FROM tbl_submitted_document d WHERE d.player_id=p.id)) AS Documents,
                               DATE(COALESCE(p.created,t.created)) AS Added
                          FROM tbl_team t
                          JOIN tbl_league l ON l.id=t.league_id
                          LEFT JOIN tbl_player p ON p.team_id=t.id
                         WHERE $base AND LOWER(t.status)='approved'";
                reportAppendDateRange($sql, $params, $types, 'COALESCE(p.created,t.created)', $from, $to, false);
                $sql .= ' ORDER BY l.league_name,t.team_name,p.name';
                break;

            case 'schedules':
                $sql = "SELECT l.league_name AS League,s.date AS Match_Date,s.time AS Start_Time,
                               ta.team_name AS Team_A,tb.team_name AS Team_B,
                               COALESCE(s.venue,'TBD') AS Venue,s.round AS Round,
                               s.match_number AS Match_No,s.status AS Status,
                               CASE WHEN LOWER(s.status)='completed' THEN s.a_score END AS A_Score,
                               CASE WHEN LOWER(s.status)='completed' THEN s.b_score END AS B_Score
                          FROM tbl_schedule s
                          JOIN tbl_league l ON l.id=s.league_id
                          LEFT JOIN tbl_team ta ON ta.id=s.team_a
                          LEFT JOIN tbl_team tb ON tb.id=s.team_b
                         WHERE $base AND s.published_at IS NOT NULL
                           AND (s.is_archived=0 OR s.is_archived IS NULL)
                           AND s.team_a>0 AND s.team_b>0";
                reportAppendDateRange($sql, $params, $types, 's.date', $from, $to, true);
                $sql .= ' ORDER BY l.league_name,s.date,s.time,ta.team_name';
                break;

            case 'results':
                $sql = "SELECT l.league_name AS League,s.date AS Played_On,
                               ta.team_name AS Team_A,tb.team_name AS Team_B,
                               s.a_score AS A_Score,s.b_score AS B_Score,
                               s.sets_a AS Sets_A,s.sets_b AS Sets_B,
                               s.a_points AS Points_A,s.b_points AS Points_B,
                               CASE WHEN CAST(s.winner AS UNSIGNED)>0
                                         THEN COALESCE(tw.team_name,'Unknown')
                                    WHEN s.win_type='DoubleForfeit' THEN 'Double forfeit'
                                    WHEN s.win_type<>'Regular' THEN 'No winner'
                                    ELSE 'Draw' END AS Winner,
                               s.win_type AS Win_Type,s.result_status AS Result_Status,
                               COALESCE(s.result_note,'') AS Note
                          FROM tbl_schedule s
                          JOIN tbl_league l ON l.id=s.league_id
                          LEFT JOIN tbl_team ta ON ta.id=s.team_a
                          LEFT JOIN tbl_team tb ON tb.id=s.team_b
                          LEFT JOIN tbl_team tw ON tw.id=CAST(s.winner AS UNSIGNED)
                         WHERE $base AND LOWER(s.status)='completed'
                           AND s.published_at IS NOT NULL
                           AND (s.is_archived=0 OR s.is_archived IS NULL)
                           AND s.team_a>0 AND s.team_b>0";
                reportAppendDateRange($sql, $params, $types, 's.date', $from, $to, true);
                $sql .= ' ORDER BY l.league_name,s.date DESC';
                break;

            case 'standings':
                foreach ($leagues as $standingsLeague) {
                    $ranking = getLeagueStandingsWithGroups($conn, (int) $standingsLeague['id']);
                    foreach ($ranking as $rankRow) {
                        if ($rowLimit > 0 && count($rows) >= $rowLimit) {
                            $truncated = true;
                            break 2;
                        }
                        $rows[] = [
                            'League'          => (string) $standingsLeague['league_name'],
                            'Group'           => (int) ($standingsLeague['grouping_enabled'] ?? 0) === 1
                                && (string) ($rankRow['group_name'] ?? '') !== ''
                                ? (string) $rankRow['group_name']
                                : 'Overall',
                            'Rank'            => (int) ($rankRow['rank'] ?? 0),
                            'Team'            => (string) ($rankRow['team_name'] ?? ''),
                            'Played'          => (int) ($rankRow['played'] ?? 0),
                            'Wins'            => (int) ($rankRow['wins'] ?? 0),
                            'Draws'           => (int) ($rankRow['draws'] ?? 0),
                            'Losses'          => (int) ($rankRow['losses'] ?? 0),
                            'Forfeit_Wins'    => (int) ($rankRow['forfeit_wins'] ?? 0),
                            'Forfeit_Losses'  => (int) ($rankRow['forfeit_losses'] ?? 0),
                            'Double_Forfeits' => (int) ($rankRow['double_forfeits'] ?? 0),
                            'Points_For'      => (int) ($rankRow['points_for'] ?? 0),
                            'Points_Against'  => (int) ($rankRow['points_against'] ?? 0),
                            'Difference'      => (int) ($rankRow['difference'] ?? 0),
                            'Quotient'        => number_format((float) ($rankRow['quotient'] ?? 0), 4, '.', ''),
                            'Sets_Won'        => (int) ($rankRow['sets_won'] ?? 0),
                            'Sets_Lost'       => (int) ($rankRow['sets_lost'] ?? 0),
                            'Set_Ratio'       => number_format((float) ($rankRow['set_ratio'] ?? 0), 4, '.', ''),
                            'Points'          => (int) ($rankRow['points'] ?? 0),
                            'Progression'     => (string) ($rankRow['progression_status'] ?? ''),
                        ];
                    }
                }
                break;

            case 'participation':
                $sql = "SELECT l.league_name AS League,t.team_name AS Team,t.team_repre AS Representative,
                               COUNT(DISTINCT p.id) AS Total_Roster,
                               COUNT(DISTINCT CASE WHEN LOWER(p.eligibility_status)='eligible' THEN p.id END) AS Eligible_Players,
                               COUNT(DISTINCT CASE WHEN s.team_a>0 AND s.team_b>0 THEN s.id END) AS Matches_Played,
                               CASE WHEN COUNT(DISTINCT p.id) < l.min_player THEN 'Below minimum'
                                    WHEN l.max_player <> '' AND COUNT(DISTINCT p.id) > CAST(l.max_player AS UNSIGNED) THEN 'Above maximum'
                                    ELSE 'OK' END AS Roster_Check,
                               t.progression_status AS Progression
                          FROM tbl_team t
                          JOIN tbl_league l ON l.id=t.league_id
                          LEFT JOIN tbl_player p ON p.team_id=t.id
                          LEFT JOIN tbl_schedule s ON s.league_id=l.id
                               AND (s.team_a=t.id OR s.team_b=t.id)
                               AND LOWER(s.status)='completed'
                               AND s.published_at IS NOT NULL
                               AND (s.is_archived=0 OR s.is_archived IS NULL)
                         WHERE $base AND LOWER(t.status)='approved'
                         GROUP BY l.id,l.league_name,l.min_player,l.max_player,
                                  t.id,t.team_name,t.team_repre,t.progression_status
                         ORDER BY l.league_name,t.team_name";
                break;

            case 'summary':
                $sql = "SELECT l.id AS __league_id,l.league_name AS League,l.sport AS Sport,
                               l.division AS Division,l.category AS Category,
                               (SELECT COUNT(*) FROM tbl_team t WHERE t.league_id=l.id) AS Registrations,
                               (SELECT COUNT(*) FROM tbl_team t WHERE t.league_id=l.id AND LOWER(t.status)='approved') AS Approved_Teams,
                               (SELECT COUNT(*) FROM tbl_player p JOIN tbl_team t ON t.id=p.team_id
                                 WHERE t.league_id=l.id AND LOWER(t.status)='approved') AS Players,
                               (SELECT COUNT(*) FROM tbl_player p JOIN tbl_team t ON t.id=p.team_id
                                 WHERE t.league_id=l.id AND LOWER(t.status)='approved'
                                   AND LOWER(p.eligibility_status)='eligible') AS Eligible_Players,
                               (SELECT COUNT(*) FROM tbl_schedule s
                                 WHERE s.league_id=l.id AND s.published_at IS NOT NULL
                                   AND (s.is_archived=0 OR s.is_archived IS NULL)
                                   AND s.team_a>0 AND s.team_b>0) AS Published_Matches,
                               (SELECT COUNT(*) FROM tbl_schedule s
                                 WHERE s.league_id=l.id AND LOWER(s.status)='completed'
                                   AND s.published_at IS NOT NULL
                                   AND (s.is_archived=0 OR s.is_archived IS NULL)
                                   AND s.team_a>0 AND s.team_b>0) AS Completed_Matches,
                               l.start_date AS Start_Date,l.end_date AS End_Date
                          FROM tbl_league l
                         WHERE $base";
                reportAppendDateRange($sql, $params, $types, 'l.start_date', $from, $to, true);
                $sql .= ' ORDER BY l.start_date DESC,l.league_name';
                break;
        }

        if ($type !== 'standings') {
            if ($sql === '') {
                throw new ReportExecutionException('Unable to build the selected report.');
            }
            if ($rowLimit > 0) {
                $sql .= ' LIMIT ' . ($rowLimit + 1);
            }
            $rows = reportFetchAll($conn, $sql, $types, $params);
            if ($rowLimit > 0 && count($rows) > $rowLimit) {
                $rows = array_slice($rows, 0, $rowLimit);
                $truncated = true;
            }
        }

        if ($type === 'summary') {
            $statusById = [];
            foreach ($leagues as $leagueRow) {
                $statusById[(int) $leagueRow['id']] = (string) ($leagueRow['report_status'] ?? '');
            }
            foreach ($rows as &$summaryRow) {
                $summaryRow['League_Status'] = $statusById[(int) ($summaryRow['__league_id'] ?? 0)] ?? '';
                unset($summaryRow['__league_id']);
            }
            unset($summaryRow);
        }

        return $makeResult($rows, $truncated);
    }
}
