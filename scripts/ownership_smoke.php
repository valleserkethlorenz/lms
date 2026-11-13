<?php
/**
 * Organizer-ownership smoke check
 * ================================
 * Verifies the rules that keep each organizer's leagues private:
 *   1. every league has an owner (created_by) so no league is unmanageable;
 *   2. organizerOwnsLeague() accepts the creator and rejects another organizer;
 *   3. the public roster query (app/organizer/action/getRoster.php) prepares;
 *   4. the protest ownership guard (action/schedulingAction.php) prepares.
 *
 * Usage (from the lms/ directory):  php scripts/ownership_smoke.php
 * Exit code 1 means a check reported an unexpected value.
 */
require_once __DIR__ . '/../config/dbconnect.php';
require_once __DIR__ . '/../config/auth_guard.php';

$failures = 0;

function row(string $label, $value, $expected = true, bool $check = false): void {
    global $failures;
    $text = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
    echo $label . ': ' . $text . PHP_EOL;
    if ($check && $value !== $expected) {
        $failures++;
        echo '  ^ expected ' . var_export($expected, true) . PHP_EOL;
    }
}

// 1. Leagues without an owner cannot be managed by any organizer.
$r = $conn->query("SELECT COUNT(*) AS c FROM tbl_league WHERE created_by IS NULL");
$unowned = (int)($r->fetch_assoc()['c'] ?? 0);
row('leagues_without_owner', $unowned, 0, true);

// 2. Ownership helper: the creator may manage, another organizer may not.
$league = $conn->query("SELECT l.id, l.created_by FROM tbl_league l WHERE l.created_by IS NOT NULL ORDER BY l.id LIMIT 1")->fetch_assoc();
if ($league) {
    $leagueId = (int)$league['id'];
    $owner    = (int)$league['created_by'];
    row('sample_league', $leagueId);
    row('owner_can_manage', organizerOwnsLeague($conn, $leagueId, $owner, true), true, true);

    $other = $conn->query("SELECT id FROM tbl_user WHERE role=1 AND id<>$owner ORDER BY id LIMIT 1")->fetch_assoc();
    if ($other) {
        row('other_organizer_can_manage', organizerOwnsLeague($conn, $leagueId, (int)$other['id'], true), false, true);
    } else {
        row('other_organizer_can_manage', 'skipped: only one organizer account exists');
    }
} else {
    row('owner_can_manage', 'skipped: no owned leagues in this database');
}

// 3. Public roster query used by getRoster.php must prepare and execute.
$team = $conn->query("SELECT t.id FROM tbl_team t ORDER BY t.id LIMIT 1")->fetch_assoc();
$teamId = $team ? (int)$team['id'] : 0;
$rosterSql = "SELECT p.name AS player_name, p.jersey_number, p.role AS position, p.gender
              FROM tbl_player p
              JOIN tbl_team t ON t.id = CAST(p.team_id AS UNSIGNED)
              JOIN tbl_league l ON l.id = t.league_id
              WHERE t.id = ?
                AND LOWER(t.status) = 'approved'
                AND l.publication_status = 'Published'
                AND (l.is_archived = 0 OR l.is_archived IS NULL)
                AND p.eligibility_status = 'Eligible'
              ORDER BY p.jersey_number ASC";
$stmt = $conn->prepare($rosterSql);
row('roster_query_prepares', (bool)$stmt, true, true);
if ($stmt) {
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    row('roster_rows_for_team_' . $teamId, $stmt->get_result()->num_rows);
    $stmt->close();
}

// 4. Protest ownership guard must prepare (league resolved through the protest).
$stmt = $conn->prepare("SELECT 1 FROM tbl_result_protest p JOIN tbl_league l ON l.id = p.league_id WHERE p.id = ? AND l.created_by = ? LIMIT 1");
row('protest_guard_prepares', (bool)$stmt, true, true);
if ($stmt) $stmt->close();

echo $failures === 0 ? PHP_EOL . 'All ownership checks passed.' . PHP_EOL : PHP_EOL . "$failures check(s) failed." . PHP_EOL;
exit($failures === 0 ? 0 : 1);
