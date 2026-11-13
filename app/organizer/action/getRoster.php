<?php
/**
 * Public roster endpoint (read-only).
 *
 * Powers the roster modal on the public Participants page (participant.php).
 * Only players of APPROVED teams that belong to a PUBLISHED, non-archived
 * league are returned, so unpublished or private registrations can never be
 * inspected through this endpoint. No write operations are handled here.
 */
require_once __DIR__ . '/../../../config/dbconnect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 405, 'msg' => 'Method not allowed']);
    exit;
}

$team_id = intval($_POST['team_id'] ?? 0);

if ($team_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'msg' => 'Invalid team ID']);
    exit;
}

// tbl_player.team_id is stored as varchar(255), hence the CAST in the join.
$sql = "SELECT p.name AS player_name, p.jersey_number, p.role AS position, p.gender
        FROM tbl_player p
        JOIN tbl_team t ON t.id = CAST(p.team_id AS UNSIGNED)
        JOIN tbl_league l ON l.id = t.league_id
        WHERE t.id = ?
          AND LOWER(t.status) = 'approved'
          AND l.publication_status = 'Published'
          AND (l.is_archived = 0 OR l.is_archived IS NULL)
          AND p.eligibility_status = 'Eligible'
        ORDER BY p.jersey_number ASC";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 500, 'msg' => 'Database error']);
    exit;
}

$stmt->bind_param('i', $team_id);
$stmt->execute();
$result = $stmt->get_result();

$players = [];
while ($row = $result->fetch_assoc()) {
    $players[] = $row;
}
$stmt->close();

echo json_encode([
    'status' => 200,
    'players' => $players
]);
?>
