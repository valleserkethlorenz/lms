<?php
/**
 * Report Query Builder - Shared logic for all report endpoints.
 *
 * NOTE: organizerOwnsLeague() used to be declared in this file as well, which
 * collided with the canonical definition in config/auth_guard.php and caused a
 * fatal "Cannot redeclare organizerOwnsLeague()" error on every report endpoint
 * (reportAction.php, exportExcel.php, exportPdf.php). The single source of truth
 * now lives in auth_guard.php, which is required below so this file works
 * regardless of include order.
 */
require_once __DIR__ . '/auth_guard.php';

function buildReportQuery(mysqli $conn, int $userId, string $type, int $leagueId, string $sport, string $division, string $category, string $leagueStatus, string $from, string $to): array
{
    $allowedTypes = ['registrations','approved_teams','rosters','schedules','results','standings','participation','summary'];
    $allowedStatuses = ['','Draft','Upcoming','Open','Ongoing','Completed'];
    if (!in_array($type, $allowedTypes, true)) throw new InvalidArgumentException('Invalid report type.');
    if (!in_array($leagueStatus, $allowedStatuses, true)) throw new InvalidArgumentException('Invalid league status.');
    foreach (['From date' => $from, 'To date' => $to] as $label => $date) {
        if ($date !== '') {
            $parsed = DateTime::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException("$label is invalid.");
        }
    }
    if ($from !== '' && $to !== '' && $from > $to) throw new InvalidArgumentException('From date cannot be later than To date.');
    if ($leagueId && !organizerOwnsLeague($conn, $leagueId, $userId)) throw new RuntimeException('Unauthorized league access.');

    // Slots are filled by APPROVED teams only, matching getLeagueStatus():
    // Pending applications must never make a league look Ongoing in reports.
    $statusExpression = "CASE WHEN l.publication_status='Draft' THEN 'Draft' WHEN CURDATE()>STR_TO_DATE(l.end_date,'%Y-%m-%d') THEN 'Completed' WHEN CURDATE()<STR_TO_DATE(l.regis_start,'%Y-%m-%d') THEN 'Upcoming' WHEN CURDATE() BETWEEN STR_TO_DATE(l.regis_start,'%Y-%m-%d') AND STR_TO_DATE(l.regis_end,'%Y-%m-%d') AND COALESCE(l.registration_window_open,1)=1 AND (SELECT COUNT(*) FROM tbl_team tx WHERE tx.league_id=l.id AND LOWER(tx.status)='approved')<CAST(l.max_team AS UNSIGNED) THEN 'Open' ELSE 'Ongoing' END";
    $where = ["l.created_by=?", "(l.is_archived=0 OR l.is_archived IS NULL)"];
    $params = [$userId]; $bindTypes = 'i';
    foreach ([['l.id', $leagueId, 'i'], ['l.sport', $sport, 's'], ['l.division', $division, 's'], ['l.category', $category, 's']] as [$column, $value, $bindType]) {
        if ($value !== '' && $value !== 0) { $where[] = "$column=?"; $params[] = $value; $bindTypes .= $bindType; }
    }
    if ($leagueStatus !== '') { $where[] = "($statusExpression)=?"; $params[] = $leagueStatus; $bindTypes .= 's'; }
    $base = implode(' AND ', $where);
    $addDates = function (string &$sql, string $column) use (&$params, &$bindTypes, $from, $to) {
        if ($from !== '') { $sql .= " AND DATE($column)>=?"; $params[] = $from; $bindTypes .= 's'; }
        if ($to !== '') { $sql .= " AND DATE($column)<=?"; $params[] = $to; $bindTypes .= 's'; }
    };

    $sql = '';
    switch ($type) {
        case 'registrations':
            $sql = "SELECT l.league_name AS League,t.team_name AS Team,t.team_repre AS Representative,t.email AS Email,t.phone AS Phone,t.status AS Registration_Status,t.progression_status AS Progression,DATE(t.created) AS Submitted FROM tbl_team t JOIN tbl_league l ON l.id=t.league_id WHERE $base";
            $addDates($sql, 't.created'); $sql .= ' ORDER BY l.league_name,t.created,t.team_name'; break;
        case 'approved_teams':
            $sql = "SELECT l.league_name AS League,l.sport AS Sport,l.division AS Division,l.category AS Category,t.team_name AS Team,t.team_repre AS Representative,t.progression_status AS Progression,DATE(t.reviewed_at) AS Approved_On FROM tbl_team t JOIN tbl_league l ON l.id=t.league_id WHERE $base AND LOWER(t.status)='approved'";
            $addDates($sql, 'COALESCE(t.reviewed_at,t.created)'); $sql .= ' ORDER BY l.league_name,t.team_name'; break;
        case 'rosters':
            $sql = "SELECT l.league_name AS League,t.team_name AS Team,p.name AS Player,p.eligibility_status AS Eligibility,p.document AS Doc_Type,p.created AS Added FROM tbl_team t JOIN tbl_league l ON l.id=t.league_id LEFT JOIN tbl_player p ON p.team_id=t.id WHERE $base AND LOWER(t.status)='approved'";
            $addDates($sql, 'COALESCE(p.created,t.created)'); $sql .= ' ORDER BY l.league_name,t.team_name,p.name'; break;
        case 'schedules':
            $sql = "SELECT l.league_name AS League,ta.team_name AS Home,tb.team_name AS Away,COALESCE(s.venue,'TBD') AS Venue,s.date AS Match_Date,s.a_score AS Home_Score,s.b_score AS Away_Score,s.status AS Status FROM tbl_schedule s JOIN tbl_league l ON l.id=s.league_id LEFT JOIN tbl_team ta ON ta.id=s.team_a LEFT JOIN tbl_team tb ON tb.id=s.team_b WHERE $base AND s.published_at IS NOT NULL AND (s.is_archived=0 OR s.is_archived IS NULL)";
            $addDates($sql, 's.date'); $sql .= ' ORDER BY l.league_name,s.date,ta.team_name'; break;
        case 'results':
            $sql = "SELECT l.league_name AS League,ta.team_name AS Home,tb.team_name AS Away,s.a_score AS Home_Score,s.b_score AS Away_Score,CASE WHEN s.a_score>s.b_score THEN ta.team_name WHEN s.b_score>s.a_score THEN tb.team_name ELSE 'Draw' END AS Winner,s.date AS Played_On FROM tbl_schedule s JOIN tbl_league l ON l.id=s.league_id LEFT JOIN tbl_team ta ON ta.id=s.team_a LEFT JOIN tbl_team tb ON tb.id=s.team_b WHERE $base AND LOWER(s.status)='completed' AND s.published_at IS NOT NULL AND (s.is_archived=0 OR s.is_archived IS NULL)";
            $addDates($sql, 's.date'); $sql .= ' ORDER BY l.league_name,s.date DESC'; break;
        case 'standings':
            $sql = "SELECT l.league_name AS League,COALESCE(g.name,'Overall') AS Group_Name,t.team_name AS Team,COALESCE(SUM(CASE WHEN CAST(s.winner AS UNSIGNED)>0 AND CAST(s.winner AS UNSIGNED)=t.id THEN 1 ELSE 0 END),0) AS Wins,COALESCE(SUM(CASE WHEN CAST(s.winner AS UNSIGNED)>0 AND CAST(s.winner AS UNSIGNED)<>t.id THEN 1 ELSE 0 END),0) AS Losses,COALESCE(SUM(CASE WHEN CAST(s.winner AS UNSIGNED)=0 THEN 1 ELSE 0 END),0) AS Draws,COALESCE(SUM(CASE WHEN s.team_a=t.id THEN s.a_score ELSE s.b_score END),0) AS Points_For,COALESCE(SUM(CASE WHEN s.team_a=t.id THEN s.b_score ELSE s.a_score END),0) AS Points_Against,COALESCE(SUM(CASE WHEN s.team_a=t.id THEN s.a_score-s.b_score ELSE s.b_score-s.a_score END),0) AS Difference,t.progression_status AS Progression FROM tbl_team t JOIN tbl_league l ON l.id=t.league_id LEFT JOIN tbl_league_group_team gt ON gt.team_id=t.id LEFT JOIN tbl_league_group g ON g.id=gt.group_id AND g.league_id=l.id LEFT JOIN tbl_schedule s ON s.league_id=l.id AND (s.team_a=t.id OR s.team_b=t.id) AND (l.grouping_enabled=0 OR s.league_group_id=g.id) AND LOWER(s.status)='completed' AND s.published_at IS NOT NULL AND (s.is_archived=0 OR s.is_archived IS NULL) WHERE $base AND LOWER(t.status)='approved' GROUP BY l.id,l.league_name,g.id,g.name,t.id,t.team_name,t.progression_status ORDER BY l.league_name,COALESCE(g.display_order,999),Wins DESC,Points_For DESC,Points_Against,t.team_name"; break;
        case 'participation':
            $sql = "SELECT l.league_name AS League,t.team_name AS Team,t.team_repre AS Representative,COUNT(DISTINCT p.id) AS Roster_Size,COUNT(DISTINCT s.id) AS Matches_Played,t.progression_status AS Progression FROM tbl_team t JOIN tbl_league l ON l.id=t.league_id LEFT JOIN tbl_player p ON p.team_id=t.id AND p.eligibility_status='Eligible' LEFT JOIN tbl_schedule s ON s.league_id=l.id AND (s.team_a=t.id OR s.team_b=t.id) AND LOWER(s.status)='completed' AND s.published_at IS NOT NULL AND (s.is_archived=0 OR s.is_archived IS NULL) WHERE $base AND LOWER(t.status)='approved' GROUP BY l.id,l.league_name,t.id,t.team_name,t.team_repre,t.progression_status ORDER BY l.league_name,t.team_name"; break;
        case 'summary':
            $sql = "SELECT l.league_name AS League,l.sport AS Sport,l.division AS Division,l.category AS Category,($statusExpression) AS League_Status,(SELECT COUNT(*) FROM tbl_team t WHERE t.league_id=l.id) AS Registrations,(SELECT COUNT(*) FROM tbl_team t WHERE t.league_id=l.id AND LOWER(t.status)='approved') AS Approved_Teams,(SELECT COUNT(*) FROM tbl_schedule s WHERE s.league_id=l.id AND s.published_at IS NOT NULL AND (s.is_archived=0 OR s.is_archived IS NULL)) AS Published_Matches,(SELECT COUNT(*) FROM tbl_schedule s WHERE s.league_id=l.id AND LOWER(s.status)='completed' AND s.published_at IS NOT NULL AND (s.is_archived=0 OR s.is_archived IS NULL)) AS Completed_Matches,l.start_date AS Start_Date,l.end_date AS End_Date FROM tbl_league l WHERE $base";
            $addDates($sql, 'l.start_date'); $sql .= ' ORDER BY l.start_date DESC,l.league_name'; break;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('Unable to prepare the selected report.');
    $stmt->bind_param($bindTypes, ...$params);
    if (!$stmt->execute()) throw new RuntimeException('Unable to generate the selected report.');
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();

    $titles = ['registrations'=>'Team Registrations','approved_teams'=>'Approved Teams','rosters'=>'Team Rosters','schedules'=>'Published Match Schedules','results'=>'Official Match Results','standings'=>'League Standings','participation'=>'Participation','summary'=>'League Summary'];
    $filters = array_filter(['League'=>$leagueId?'Selected league':'All leagues','Sport'=>$sport,'Division'=>$division,'Category'=>$category,'League status'=>$leagueStatus,'From'=>$from,'To'=>$to], fn($v) => $v !== '');

    return [
        'rows' => $rows,
        'title' => $titles[$type] ?? 'Report',
        'filters' => $filters,
        'generated_at' => date('Y-m-d H:i:s'),
        'type' => $type,
    ];
}
