<?php
/**
 * Scheduling Helper Functions
 * Handles automated scheduling, conflict detection, and match management
 */

/**
 * Get all approved teams for a league
 */
function getApprovedTeams($conn, $leagueId) {
    $sql = "SELECT id, team_name, progression_status FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'approved' AND progression_status <> 'Eliminated' ORDER BY team_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leagueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $teams = [];
    while ($row = $result->fetch_assoc()) {
        $teams[] = $row;
    }
    $stmt->close();
    return $teams;
}

/**
 * Generate all possible match combinations (Round Robin)
 */
function generateRoundRobinMatches($teams) {
    $matches = [];
    $teamCount = count($teams);
    
    for ($i = 0; $i < $teamCount; $i++) {
        for ($j = $i + 1; $j < $teamCount; $j++) {
            $matches[] = [
                'team_a_id' => $teams[$i]['id'],
                'team_a_name' => $teams[$i]['team_name'],
                'team_b_id' => $teams[$j]['id'],
                'team_b_name' => $teams[$j]['team_name'],
                'round' => 1
            ];
        }
    }
    
    return $matches;
}

/**
 * Get standings for a specific group
 */
function getGroupStandings($conn, $leagueId, $groupNum) {
    $sql = "SELECT t.id, t.team_name,
            COUNT(CASE WHEN (s.team_a = t.id AND s.a_score > s.b_score) OR (s.team_b = t.id AND s.b_score > s.a_score) THEN 1 END) as wins,
            COUNT(CASE WHEN s.a_score = s.b_score AND (s.team_a = t.id OR s.team_b = t.id) THEN 1 END) as draws,
            COUNT(CASE WHEN (s.team_a = t.id AND s.a_score < s.b_score) OR (s.team_b = t.id AND s.b_score < s.a_score) THEN 1 END) as losses,
            SUM(CASE WHEN s.team_a = t.id THEN s.a_score WHEN s.team_b = t.id THEN s.b_score ELSE 0 END) as points_for,
            SUM(CASE WHEN s.team_a = t.id THEN s.b_score WHEN s.team_b = t.id THEN s.a_score ELSE 0 END) as points_against
            FROM tbl_team t
            LEFT JOIN tbl_schedule s ON (s.team_a = t.id OR s.team_b = t.id) AND s.league_id = ? AND s.stage = 'group' AND s.group_stage = ? AND s.status = 'Completed'
            WHERE t.league_id = ? AND LOWER(t.status) = 'approved'
            AND t.id IN (SELECT DISTINCT team_a FROM tbl_schedule WHERE league_id = ? AND stage = 'group' AND group_stage = ?
                         UNION SELECT DISTINCT team_b FROM tbl_schedule WHERE league_id = ? AND stage = 'group' AND group_stage = ?)
            GROUP BY t.id
            ORDER BY (wins * 3 + draws) DESC, (points_for - points_against) DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiiii", $leagueId, $groupNum, $leagueId, $leagueId, $groupNum, $leagueId, $groupNum);
    $stmt->execute();
    $result = $stmt->get_result();
    $standings = [];
    
    while ($row = $result->fetch_assoc()) {
        $standings[] = $row;
    }
    $stmt->close();
    
    return $standings;
}



/**
 * Standings for a group stored in tbl_league_group — the model used by the
 * organizer Standings page and by matches tagged with league_group_id.
 * Only published, completed matches count so suggestions match the table.
 */
function getGroupStandingsByLeagueGroup(mysqli $conn, int $leagueId, int $groupId): array {
    $sql = "SELECT t.id, t.team_name,
            COUNT(s.id) as played,
            COALESCE(SUM(CASE WHEN CAST(s.winner AS UNSIGNED)=t.id THEN 1 ELSE 0 END),0) as wins,
            COALESCE(SUM(CASE WHEN CAST(s.winner AS UNSIGNED)=0 THEN 1 ELSE 0 END),0) as draws,
            COALESCE(SUM(CASE WHEN CAST(s.winner AS UNSIGNED)>0 AND CAST(s.winner AS UNSIGNED)<>t.id THEN 1 ELSE 0 END),0) as losses,
            COALESCE(SUM(CASE WHEN s.team_a=t.id THEN s.a_score ELSE s.b_score END),0) as points_for,
            COALESCE(SUM(CASE WHEN s.team_a=t.id THEN s.b_score ELSE s.a_score END),0) as points_against
            FROM tbl_team t
            LEFT JOIN tbl_schedule s ON s.league_id=? AND (s.team_a=t.id OR s.team_b=t.id) AND s.league_group_id=?
                AND LOWER(s.status)='completed' AND s.published_at IS NOT NULL AND (s.is_archived=0 OR s.is_archived IS NULL)
            WHERE t.league_id=? AND LOWER(t.status)='approved'
              AND t.id IN (SELECT team_id FROM tbl_league_group_team WHERE group_id=?)
            GROUP BY t.id,t.team_name
            ORDER BY wins DESC, points_for DESC, points_against ASC, t.team_name";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('iiii', $leagueId, $groupId, $leagueId, $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $standings = [];
    while ($row = $result->fetch_assoc()) $standings[] = $row;
    $stmt->close();
    return $standings;
}

/**
 * Resolve which grouping model a league uses for progression/qualifier suggestions.
 * Prefers tbl_league_group (Standings page model) and falls back to the legacy
 * stage='group' / group_stage tagging for leagues that were never grouped there.
 *
 * @return array<int,array{league_group_id:?int,group_no:int,label:string}>
 */
function getProgressionGroups(mysqli $conn, int $leagueId): array {
    $groupingOn = false;
    $stmt = $conn->prepare("SELECT grouping_enabled FROM tbl_league WHERE id=?");
    if ($stmt) {
        $stmt->bind_param('i', $leagueId); $stmt->execute();
        $groupingOn = (int)($stmt->get_result()->fetch_assoc()['grouping_enabled'] ?? 0) === 1;
        $stmt->close();
    }

    if ($groupingOn) {
        $groups = [];
        $stmt = $conn->prepare("SELECT id,name FROM tbl_league_group WHERE league_id=? ORDER BY display_order,name");
        if ($stmt) {
            $stmt->bind_param('i', $leagueId); $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $groups[] = ['league_group_id' => (int)$row['id'], 'group_no' => 0, 'label' => $row['name']];
            }
            $stmt->close();
        }
        if (!empty($groups)) return $groups;
    }

    $legacy = [];
    $stmt = $conn->prepare("SELECT DISTINCT group_stage FROM tbl_schedule WHERE league_id=? AND stage='group' AND group_stage IS NOT NULL ORDER BY group_stage");
    if ($stmt) {
        $stmt->bind_param('i', $leagueId); $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $num = (int)$row['group_stage'];
            $legacy[] = ['league_group_id' => null, 'group_no' => $num, 'label' => 'Group ' . $num];
        }
        $stmt->close();
    }
    return $legacy;
}

/**
 * Standings for one group descriptor returned by getProgressionGroups().
 */
function getProgressionGroupStandings(mysqli $conn, int $leagueId, array $group): array {
    if (!empty($group['league_group_id'])) {
        return getGroupStandingsByLeagueGroup($conn, $leagueId, (int)$group['league_group_id']);
    }
    $groupNo = (int)($group['group_no'] ?? 0);
    if ($groupNo <= 0) return [];
    return getGroupStandings($conn, $leagueId, $groupNo);
}

/**
 * Check for scheduling conflicts
 */
function checkScheduleConflicts($conn, $teamId, $date, $time, $duration = 60, $excludeScheduleId = null) {
    $conflicts = [];
    
    // Convert time to datetime for comparison
    $matchStart = strtotime("$date $time");
    $matchEnd = $matchStart + ($duration * 60);
    
    // Check team conflicts (same team playing at overlapping times)
    $sql = "SELECT s.id, s.date, s.time, t_a.team_name as team_a_name, t_b.team_name as team_b_name
            FROM tbl_schedule s
            JOIN tbl_team t_a ON s.team_a = t_a.id
            JOIN tbl_team t_b ON s.team_b = t_b.id
            WHERE (s.team_a = ? OR s.team_b = ?)
            AND s.status IN ('Upcoming', 'Scheduled')
            AND s.date = ?";
    
    if ($excludeScheduleId) {
        $sql .= " AND s.id != ?";
    }
    
    $stmt = $conn->prepare($sql);
    
    if ($excludeScheduleId) {
        $stmt->bind_param("iisi", $teamId, $teamId, $date, $excludeScheduleId);
    } else {
        $stmt->bind_param("iis", $teamId, $teamId, $date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $existingStart = strtotime($row['date'] . ' ' . $row['time']);
        $existingEnd = $existingStart + (60 * 60); // Assume 60 min matches
        
        // Check for overlap
        if (($matchStart < $existingEnd) && ($matchEnd > $existingStart)) {
            $conflicts[] = [
                'type' => 'team_conflict',
                'schedule_id' => $row['id'],
                'message' => 'Team already scheduled at this time',
                'existing_match' => $row['team_a_name'] . ' vs ' . $row['team_b_name']
            ];
        }
    }
    
    $stmt->close();
    return $conflicts;
}

/**
 * Check venue conflicts
 */
function checkVenueConflicts($conn, $venue, $date, $time, $duration = 60, $excludeScheduleId = null) {
    $conflicts = [];
    
    if (!$venue) {
        return $conflicts; // No venue specified, skip check
    }
    
    $matchStart = strtotime("$date $time");
    $matchEnd = $matchStart + ($duration * 60);
    
    $sql = "SELECT s.id, s.date, s.time, t_a.team_name as team_a_name, t_b.team_name as team_b_name
            FROM tbl_schedule s
            JOIN tbl_team t_a ON s.team_a = t_a.id
            JOIN tbl_team t_b ON s.team_b = t_b.id
            WHERE s.venue = ?
            AND s.status IN ('Upcoming', 'Scheduled')
            AND s.date = ?";
    
    if ($excludeScheduleId) {
        $sql .= " AND s.id != ?";
    }
    
    $stmt = $conn->prepare($sql);
    
    if ($excludeScheduleId) {
        $stmt->bind_param("ssi", $venue, $date, $excludeScheduleId);
    } else {
        $stmt->bind_param("ss", $venue, $date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $existingStart = strtotime($row['date'] . ' ' . $row['time']);
        $existingEnd = $existingStart + (60 * 60);
        
        if (($matchStart < $existingEnd) && ($matchEnd > $existingStart)) {
            $conflicts[] = [
                'type' => 'venue_conflict',
                'schedule_id' => $row['id'],
                'message' => 'Venue already booked at this time',
                'existing_match' => $row['team_a_name'] . ' vs ' . $row['team_b_name']
            ];
        }
    }
    
    $stmt->close();
    return $conflicts;
}

/**
 * Ensure optional tournament scheduling columns exist before generated inserts.
 */
function ensureScheduleTournamentColumns($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }

    $columns = [
        'bracket' => "ALTER TABLE tbl_schedule ADD COLUMN bracket VARCHAR(50) DEFAULT NULL AFTER conflict_reason",
        'group_stage' => "ALTER TABLE tbl_schedule ADD COLUMN group_stage INT DEFAULT NULL AFTER bracket",
        'stage' => "ALTER TABLE tbl_schedule ADD COLUMN stage VARCHAR(50) DEFAULT NULL AFTER group_stage",
        // Bulk forfeit decision record (§10): a Team's whole schedule may only be
        // voided on the Technical Committee's decision, so the reference is
        // stamped on every affected match.
        'forfeit_decision_ref' => "ALTER TABLE tbl_schedule ADD COLUMN forfeit_decision_ref VARCHAR(120) DEFAULT NULL",
        'forfeit_team_id' => "ALTER TABLE tbl_schedule ADD COLUMN forfeit_team_id INT DEFAULT NULL",
        'forfeit_applied_by' => "ALTER TABLE tbl_schedule ADD COLUMN forfeit_applied_by INT DEFAULT NULL",
        'forfeit_applied_at' => "ALTER TABLE tbl_schedule ADD COLUMN forfeit_applied_at DATETIME DEFAULT NULL"
    ];

    $existing = [];
    $result = $conn->query("SHOW COLUMNS FROM tbl_schedule");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing[$row['Field']] = true;
        }
        $result->close();
    }

    foreach ($columns as $column => $sql) {
        if (!isset($existing[$column])) {
            $conn->query($sql);
        }
    }

    $checked = true;
}

/**
 * Auto-schedule matches with conflict detection
 */
function autoScheduleMatches($conn, $leagueId, $matches, $startDate, $venue = null) {
    $league = [];
    $sql = "SELECT * FROM tbl_scheduling_config WHERE league_id = ?";
    $stmt = $conn->prepare($sql);
    $config = null;

    if ($stmt) {
        $stmt->bind_param("i", $leagueId);
        $stmt->execute();
        $result = $stmt->get_result();
        $config = $result->fetch_assoc();
        $stmt->close();
    }
    
    if (!$config) {
        // Use defaults if no config exists
        $config = [
            'match_duration_minutes' => 60,
            'rest_period_minutes' => 15,
            'earliest_start_time' => '08:00:00',
            'latest_end_time' => '22:00:00',
            'max_matches_per_day' => 10
        ];
    }
    
    $scheduledMatches = [];
    $currentDate = strtotime($startDate);
    $matchesPerDay = 0;
    $currentTime = strtotime($config['earliest_start_time']);
    $latestTime = strtotime($config['latest_end_time']);
    $matchDuration = $config['match_duration_minutes'];
    $restPeriod = $config['rest_period_minutes'];
    
    foreach ($matches as $match) {
        // Check if we need to move to next day
        if ($matchesPerDay >= $config['max_matches_per_day']) {
            $currentDate = strtotime('+1 day', $currentDate);
            $currentTime = strtotime($config['earliest_start_time']);
            $matchesPerDay = 0;
        }
        
        $dateStr = date('Y-m-d', $currentDate);
        $timeStr = date('H:i:s', $currentTime);
        
        // Check for conflicts
        $teamConflicts = checkScheduleConflicts($conn, $match['team_a_id'], $dateStr, $timeStr, $matchDuration);
        $teamBConflicts = checkScheduleConflicts($conn, $match['team_b_id'], $dateStr, $timeStr, $matchDuration);
        $venueConflicts = $venue ? checkVenueConflicts($conn, $venue, $dateStr, $timeStr, $matchDuration) : [];
        
        $allConflicts = array_merge($teamConflicts, $teamBConflicts, $venueConflicts);
        
        $scheduledMatches[] = [
            'team_a_id' => $match['team_a_id'],
            'team_b_id' => $match['team_b_id'],
            'date' => $dateStr,
            'time' => $timeStr,
            'venue' => $venue,
            'round' => $match['round'] ?? 1,
            'bracket' => $match['bracket'] ?? null,
            'group' => $match['group'] ?? null,
            'stage' => $match['stage'] ?? null,
            'league_group_id' => $match['league_group_id'] ?? null,
            'conflicts' => $allConflicts,
            'has_conflict' => count($allConflicts) > 0
        ];
        
        // Move to next time slot
        $currentTime = strtotime("+{$matchDuration} minutes", $currentTime);
        $currentTime = strtotime("+{$restPeriod} minutes", $currentTime);
        
        // If time exceeds latest time, move to next day
        if ($currentTime > $latestTime) {
            $currentDate = strtotime('+1 day', $currentDate);
            $currentTime = strtotime($config['earliest_start_time']);
            $matchesPerDay = 0;
        } else {
            $matchesPerDay++;
        }
    }
    
    return $scheduledMatches;
}

/**
 * Save scheduled matches to database
 */
function saveScheduledMatches($conn, $leagueId, $scheduledMatches, $isAutomated = true, $committeeUserId = null) {
    ensureScheduleTournamentColumns($conn);

    $sql = "INSERT INTO tbl_schedule (league_id,league_group_id,team_a,team_b,date,time,venue,round,status,is_automated,conflict_detected,conflict_reason,bracket,group_stage,stage,committee_user_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [
            'saved' => 0,
            'conflicts' => 0,
            'total' => count($scheduledMatches),
            'error' => $conn->error
        ];
    }

    $savedCount = 0;
    $conflictCount = 0;
    $scheduleIds = [];
    
    foreach ($scheduledMatches as $match) {
        $status = 'Draft';
        $conflictReason = $match['has_conflict'] ? json_encode($match['conflicts']) : null;
        $round = $match['round'] ?? 1;
        $bracket = $match['bracket'] ?? null;
        $group = $match['group'] ?? null;
        $stage = $match['stage'] ?? null;
        // Optional link to tbl_league_group so group standings / progression read
        // from the same grouping model the organizer manages on the Standings page.
        $leagueGroupId = isset($match['league_group_id']) ? (int)$match['league_group_id'] : 0;
        if ($leagueGroupId <= 0) $leagueGroupId = null;
        
        $stmt->bind_param(
            "iiiisssisiissisi",
            $leagueId,
            $leagueGroupId,
            $match['team_a_id'],
            $match['team_b_id'],
            $match['date'],
            $match['time'],
            $match['venue'],
            $round,
            $status,
            $isAutomated,
            $match['has_conflict'],
            $conflictReason,
            $bracket,
            $group,
            $stage,
            $committeeUserId
        );
        
        if ($stmt->execute()) {
            $savedCount++;
            $scheduleIds[] = $stmt->insert_id;
            if ($match['has_conflict']) {
                $conflictCount++;
            }
        }
    }
    
    $stmt->close();
    
    return [
        'saved' => $savedCount,
        'conflicts' => $conflictCount,
        'total' => count($scheduledMatches),
        'schedule_ids' => $scheduleIds
    ];
}

/**
 * Get schedule for a league with team details.
 *
 * @param bool $includeByes when true, the one-team "BYE" rows of a generated
 *        bracket are returned too. The organizer board uses this so every
 *        bracket slot is visible (otherwise a 10-team knockout seems to have
 *        only the two preliminary matches); byes still never count as matches.
 */
function getLeagueSchedule($conn, $leagueId, $status = null, $publishedOnly = false, bool $includeByes = false) {
    $sql = "SELECT s.*, 
            t_a.team_name as team_a_name, t_a.team_image as team_a_image,
            t_b.team_name as team_b_name, t_b.team_image as team_b_image,
            CONCAT(COALESCE(c.fname,''),' ',COALESCE(c.lname,'')) AS committee_name,
            lg.name AS group_name,
            st.name AS stage_name, st.stage_type, st.stage_format,
            st.display_order AS stage_order,
            sg.name AS stage_group_name
            FROM tbl_schedule s
            LEFT JOIN tbl_team t_a ON s.team_a = t_a.id
            LEFT JOIN tbl_team t_b ON s.team_b = t_b.id
            LEFT JOIN tbl_user c ON c.id=s.committee_user_id
            LEFT JOIN tbl_league_group lg ON lg.id=s.league_group_id
            LEFT JOIN tbl_league_stage st ON st.id=s.stage_id
            LEFT JOIN tbl_league_stage_group sg ON sg.id=s.stage_group_id
            WHERE s.league_id = ? AND (s.is_archived=0 OR s.is_archived IS NULL)";
    if (!$includeByes) {
        // Every completed one-team row is a bye/auto-advance, including the
        // rows the old graph engine collapsed without stamping seed_b = 'BYE'.
        $sql .= " AND NOT (LOWER(s.status) = 'completed'
                           AND (s.team_a = 0 OR s.team_b = 0)
                           AND NOT (s.team_a = 0 AND s.team_b = 0))";
    }
    if ($publishedOnly) $sql .= " AND s.published_at IS NOT NULL AND (s.is_archived=0 OR s.is_archived IS NULL)";
    
    if ($status) {
        $sql .= " AND s.status = ?";
    }
    
    $sql .= " ORDER BY s.date ASC, s.time ASC";
    
    $stmt = $conn->prepare($sql);
    
    if ($status) {
        $stmt->bind_param("is", $leagueId, $status);
    } else {
        $stmt->bind_param("i", $leagueId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $schedule = [];
    
    while ($row = $result->fetch_assoc()) {
        $schedule[] = $row;
    }
    
    $stmt->close();
    return $schedule;
}

/**
 * Full public bracket tree for a league.
 *
 * The public fixture list is published-only, but a generated bracket is a draw:
 * its later rounds stay useful (and readable) before every match is published.
 * This query therefore returns every non-archived bracket-stage row, including
 * drafts and byes. Unpublished rows keep their teams/seeds so the draw is
 * complete, while dates, venues, scores and winners stay private.
 *
 * Placement stages are intentionally excluded: Battle for Third is not part of
 * the bracket tree.
 */
function getLeagueBracketSchedule($conn, $leagueId) {
    $sql = "SELECT s.id, s.league_id, s.league_group_id, s.stage_id, s.stage_group_id,
            s.team_a, s.team_b, s.a_score, s.b_score, s.date, s.time, s.venue,
            s.round, s.status, s.winner, s.published_at, s.bracket,
            s.seed_a, s.seed_b, s.win_type, s.bracket_side, s.node_key, s.stage,
            t_a.team_name as team_a_name,
            t_b.team_name as team_b_name,
            lg.name AS group_name,
            st.name AS stage_name, st.stage_type, st.stage_format,
            st.display_order AS stage_order,
            sg.name AS stage_group_name
            FROM tbl_schedule s
            LEFT JOIN tbl_team t_a ON s.team_a = t_a.id
            LEFT JOIN tbl_team t_b ON s.team_b = t_b.id
            LEFT JOIN tbl_league_group lg ON lg.id=s.league_group_id
            LEFT JOIN tbl_league_stage st ON st.id=s.stage_id
            LEFT JOIN tbl_league_stage_group sg ON sg.id=s.stage_group_id
            WHERE s.league_id = ?
              AND (s.is_archived=0 OR s.is_archived IS NULL)
              AND (st.stage_type IS NULL OR st.stage_type <> 'placement')
              AND (
                  st.stage_type = 'knockout'
                  -- A legacy stage row may predate the type backfill.
                  OR ((st.stage_type IS NULL OR st.stage_type = '')
                      AND st.stage_format IN ('single_elimination', 'double_elimination'))
                  OR (st.id IS NULL AND s.bracket = 'knockout')
              )
            ORDER BY COALESCE(st.display_order, 0), s.stage_id, s.round, s.id";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $leagueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $bracket = [];

    while ($row = $result->fetch_assoc()) {
        $isPublished = $row['published_at'] !== null && $row['published_at'] !== '';

        if (!$isPublished) {
            // The draw (team names + seed labels) is public; fixture details and
            // results are not until the organizer publishes the match.
            $row['date'] = null;
            $row['time'] = null;
            $row['venue'] = null;
            $row['a_score'] = null;
            $row['b_score'] = null;
            $row['winner'] = 0;
        }

        $row['is_published'] = $isPublished;
        $bracket[] = $row;
    }

    $stmt->close();
    return $bracket;
}

/**
 * Get upcoming matches for a team
 */
function getTeamUpcomingMatches($conn, $teamId, $limit = 5) {
    $sql = "SELECT s.*, 
            t_a.team_name as team_a_name, t_a.team_image as team_a_image,
            t_b.team_name as team_b_name, t_b.team_image as team_b_image,
            l.league_name
            FROM tbl_schedule s
            JOIN tbl_team t_a ON s.team_a = t_a.id
            JOIN tbl_team t_b ON s.team_b = t_b.id
            JOIN tbl_league l ON s.league_id = l.id
            WHERE (s.team_a = ? OR s.team_b = ?)
            AND s.status IN ('Upcoming', 'Scheduled')
            AND s.date >= CURDATE()
            ORDER BY s.date ASC, s.time ASC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $teamId, $teamId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $matches = [];
    
    while ($row = $result->fetch_assoc()) {
        $matches[] = $row;
    }
    
    $stmt->close();
    return $matches;
}

/**
 * Update match result
 */
function updateMatchResult($conn, $scheduleId, $teamAScore, $teamBScore, $winnerId = null) {
    $sql = "UPDATE tbl_schedule SET a_score = ?, b_score = ?, winner = ?, status = 'Completed' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $teamAScore, $teamBScore, $winnerId, $scheduleId);
    
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    }
    
    $stmt->close();
    return false;
}

/**
 * Normalize a schedule edit and report which fields actually changed.
 *
 * The organizer edit form always posts the date, time, venue and committee, so
 * the presence of a POST value cannot decide whether a reschedule happened.
 * This helper compares the posted values against the stored row (normalising
 * date/time formats) and also resolves the final team pair so the caller can
 * notify only the representatives whose match really changed.
 *
 * @param array $current  Stored row fields: date, time, venue, team_a, team_b, committee_user_id.
 * @param array $incoming Posted fields: date, time, venue, team_a_id, team_b_id, committee_user_id.
 *
 * @return array<string,mixed>
 */
function scheduleChangeSet(array $current, array $incoming): array
{
    $normalizeDate = static function ($value): string {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : $value;
    };
    $normalizeTime = static function ($value): string {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        return $timestamp ? date('H:i:s', $timestamp) : $value;
    };
    $normalizeText = static function ($value): string {
        return trim((string) ($value ?? ''));
    };
    $toInt = static function ($value): int {
        return max(0, (int) $value);
    };

    $oldDate        = $normalizeDate($current['date'] ?? '');
    $oldTime        = $normalizeTime($current['time'] ?? '');
    $oldVenue       = $normalizeText($current['venue'] ?? '');
    $oldTeamA       = $toInt($current['team_a'] ?? 0);
    $oldTeamB       = $toInt($current['team_b'] ?? 0);
    $oldCommitteeId = $toInt($current['committee_user_id'] ?? 0);

    // An empty/missing date or time means "keep the current value"; the update
    // handler only writes those columns when a value was posted.
    $incomingDate = $incoming['date'] ?? null;
    $newDate = ($incomingDate !== null && trim((string) $incomingDate) !== '')
        ? $normalizeDate($incomingDate)
        : $oldDate;

    $incomingTime = $incoming['time'] ?? null;
    $newTime = ($incomingTime !== null && trim((string) $incomingTime) !== '')
        ? $normalizeTime($incomingTime)
        : $oldTime;

    // Venue is always written when the field is present, including an empty
    // value, because the edit form posts the selected utility venue.
    $newVenue = array_key_exists('venue', $incoming) && $incoming['venue'] !== null
        ? $normalizeText($incoming['venue'])
        : $oldVenue;

    $incomingTeamA = $toInt($incoming['team_a_id'] ?? 0);
    $incomingTeamB = $toInt($incoming['team_b_id'] ?? 0);
    $finalTeamA    = $incomingTeamA > 0 ? $incomingTeamA : $oldTeamA;
    $finalTeamB    = $incomingTeamB > 0 ? $incomingTeamB : $oldTeamB;

    // The update handler always writes committee_user_id; 0 means "unassigned".
    $finalCommitteeId = $toInt($incoming['committee_user_id'] ?? 0);

    $oldTeams = array_values(array_unique(array_filter([$oldTeamA, $oldTeamB], static function ($id) {
        return $id > 0;
    })));
    $newTeams = array_values(array_unique(array_filter([$finalTeamA, $finalTeamB], static function ($id) {
        return $id > 0;
    })));

    $dateChanged       = $newDate !== $oldDate;
    $timeChanged       = $newTime !== $oldTime;
    $venueChanged      = $newVenue !== $oldVenue;
    $teamsChanged      = $finalTeamA !== $oldTeamA || $finalTeamB !== $oldTeamB;
    $committeeChanged  = $finalCommitteeId !== $oldCommitteeId;

    return [
        'old_date'           => $oldDate,
        'old_time'           => $oldTime,
        'old_venue'          => $oldVenue,
        'old_team_a'         => $oldTeamA,
        'old_team_b'         => $oldTeamB,
        'old_committee_id'   => $oldCommitteeId,
        'new_date'           => $newDate,
        'new_time'           => $newTime,
        'new_venue'          => $newVenue,
        'final_team_a'       => $finalTeamA,
        'final_team_b'       => $finalTeamB,
        'final_committee_id' => $finalCommitteeId,
        'date_changed'       => $dateChanged,
        'time_changed'       => $timeChanged,
        'venue_changed'      => $venueChanged,
        'teams_changed'      => $teamsChanged,
        'committee_changed'  => $committeeChanged,
        'schedule_changed'   => $dateChanged || $timeChanged || $venueChanged || $teamsChanged,
        'added_teams'        => array_values(array_diff($newTeams, $oldTeams)),
        'removed_teams'      => array_values(array_diff($oldTeams, $newTeams)),
        'kept_teams'         => array_values(array_intersect($oldTeams, $newTeams)),
    ];
}

/**
 * Resolve scheduling conflict
 */
function resolveConflict($conn, $scheduleId, $newDate, $newTime, $newVenue = null) {
    $sql = "UPDATE tbl_schedule SET date = ?, time = ?, venue = ?, conflict_detected = 0, conflict_reason = NULL, status = 'Scheduled' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $newDate, $newTime, $newVenue, $scheduleId);
    
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    }
    
    $stmt->close();
    return false;
}

/**
 * Check if a venue name is an active utility venue (or empty/null for no venue).
 */
if (!function_exists('isActiveUtilityVenue')) {
    function isActiveUtilityVenue(mysqli $conn, ?string $venue): bool {
        $venue = trim((string)$venue);
        if ($venue === '') return true;
        $stmt = $conn->prepare("SELECT 1 FROM tbl_utility WHERE category='venue' AND is_active=1 AND name=? LIMIT 1");
        $stmt->bind_param('s', $venue); $stmt->execute();
        $valid = (bool)$stmt->get_result()->fetch_row(); $stmt->close();
        return $valid;
    }
}

?>