<?php
session_start();
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../includes/reportExport.php';
requireRole([3]);
requireActiveAccount($conn);

$leagueId = intval($_GET['league_id'] ?? 0);
$format = $_GET['format'] ?? 'csv';

if (!$leagueId || !in_array($format, ['csv', 'pdf'])) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

$stmt = $conn->prepare("SELECT l.*, CONCAT(u.fname, ' ', u.lname) AS organizer_name FROM tbl_league l LEFT JOIN tbl_user u ON u.id = l.created_by WHERE l.id = ? AND l.is_archived = 1");
$stmt->bind_param('i', $leagueId);
$stmt->execute();
$league = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$league) {
    http_response_code(404);
    echo 'League not found or not archived.';
    exit;
}

$teamStmt = $conn->prepare("SELECT team_name, team_repre, email, phone, status, progression_status FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'approved' ORDER BY team_name");
$teamStmt->bind_param('i', $leagueId);
$teamStmt->execute();
$teams = $teamStmt->get_result();
$teamStmt->close();

$playerStmt = $conn->prepare("SELECT p.name AS full_name, p.eligibility_status, t.team_name FROM tbl_player p JOIN tbl_team t ON t.id = p.team_id WHERE t.league_id = ? AND p.eligibility_status = 'Eligible' ORDER BY t.team_name, p.name");
$playerStmt->bind_param('i', $leagueId);
$playerStmt->execute();
$players = $playerStmt->get_result();
$playerStmt->close();

auditLog($conn, 'Export Archive', "Exported archived league: {$league['league_name']} (ID $leagueId) as $format");

if ($format === 'csv') {
    $filename = preg_replace('/[^a-z0-9]+/i', '-', strtolower($league['league_name'])) . '-archive-' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['LEAGUE SUMMARY']);
    fputcsv($output, ['League Name', reportCsvValue($league['league_name'])]);
    fputcsv($output, ['Sport', reportCsvValue($league['sport'])]);
    fputcsv($output, ['Division', reportCsvValue($league['division'])]);
    fputcsv($output, ['Category', reportCsvValue($league['category'])]);
    fputcsv($output, ['Max Teams', reportCsvValue($league['max_team'])]);
    fputcsv($output, ['Start Date', reportCsvValue($league['start_date'])]);
    fputcsv($output, ['End Date', reportCsvValue($league['end_date'])]);
    fputcsv($output, ['Organizer', reportCsvValue($league['organizer_name'] ?? 'Unknown')]);
    fputcsv($output, ['Archived On', reportCsvValue($league['archived_at'])]);
    fputcsv($output, ['Status', reportCsvValue($league['stat'] ?? 'Archived')]);
    fputcsv($output, []);
    fputcsv($output, ['APPROVED TEAMS']);
    fputcsv($output, ['Team Name', 'Representative', 'Email', 'Phone', 'Status', 'Progression']);
    while ($team = $teams->fetch_assoc()) {
        fputcsv($output, array_map('reportCsvValue', [$team['team_name'], $team['team_repre'], $team['email'], $team['phone'], $team['status'], $team['progression_status']]));
    }
    fputcsv($output, []);
    fputcsv($output, ['ROSTER PLAYERS']);
    fputcsv($output, ['Team', 'Player Name', 'Eligibility']);
    while ($player = $players->fetch_assoc()) {
        fputcsv($output, array_map('reportCsvValue', [$player['team_name'], $player['full_name'], $player['eligibility_status']]));
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($league['league_name']) ?> - Archive Export</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px; color: #333; line-height: 1.6; }
        h1 { color: #09173c; margin-bottom: 10px; font-size: 24px; }
        h2 { color: #09173c; margin: 30px 0 15px; font-size: 18px; border-bottom: 2px solid #09173c; padding-bottom: 5px; }
        .meta { font-size: 12px; color: #666; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px; }
        th, td { padding: 8px 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #09173c; color: #fff; font-weight: 600; }
        tr:nth-child(even) { background: #f9f9f9; }
        .summary-table { width: 50%; }
        .summary-table th { background: #f5f5f5; color: #333; width: 35%; }
        @media print { body { padding: 20px; } .no-print { display: none; } }
        .print-btn { background: #09173c; color: #fff; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; margin-bottom: 20px; }
        .print-btn:hover { background: #1a3a7c; }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print / Save as PDF</button>
    <h1><?= htmlspecialchars($league['league_name']) ?></h1>
    <div class="meta">Exported: <?= date('F d, Y \a\t g:i A') ?> | Archived: <?= date('F d, Y', strtotime($league['archived_at'])) ?></div>
    <h2>League Summary</h2>
    <table class="summary-table">
        <tr><th>League Name</th><td><?= htmlspecialchars($league['league_name']) ?></td></tr>
        <tr><th>Sport</th><td><?= htmlspecialchars($league['sport']) ?></td></tr>
        <tr><th>Division</th><td><?= htmlspecialchars($league['division']) ?></td></tr>
        <tr><th>Category</th><td><?= htmlspecialchars($league['category']) ?></td></tr>
        <tr><th>Max Teams</th><td><?= htmlspecialchars($league['max_team']) ?></td></tr>
        <tr><th>Start Date</th><td><?= date('F d, Y', strtotime($league['start_date'])) ?></td></tr>
        <tr><th>End Date</th><td><?= date('F d, Y', strtotime($league['end_date'])) ?></td></tr>
        <tr><th>Organizer</th><td><?= htmlspecialchars($league['organizer_name'] ?? 'Unknown') ?></td></tr>
        <tr><th>Status</th><td><?= htmlspecialchars($league['stat'] ?? 'Archived') ?></td></tr>
    </table>
    <h2>Approved Teams</h2>
    <?php $teams->data_seek(0); if ($teams->num_rows > 0): ?>
    <table>
        <thead><tr><th>#</th><th>Team Name</th><th>Representative</th><th>Email</th><th>Phone</th><th>Progression</th></tr></thead>
        <tbody>
            <?php $i = 1; while ($team = $teams->fetch_assoc()): ?>
            <tr><td><?= $i++ ?></td><td><?= htmlspecialchars($team['team_name']) ?></td><td><?= htmlspecialchars($team['team_repre']) ?></td><td><?= htmlspecialchars($team['email']) ?></td><td><?= htmlspecialchars($team['phone']) ?></td><td><?= htmlspecialchars($team['progression_status']) ?></td></tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?><p>No approved teams found.</p><?php endif; ?>
    <h2>Roster Players</h2>
    <?php $players->data_seek(0); if ($players->num_rows > 0): ?>
    <table>
        <thead><tr><th>#</th><th>Team</th><th>Player Name</th><th>Eligibility</th></tr></thead>
        <tbody>
            <?php $i = 1; while ($player = $players->fetch_assoc()): ?>
            <tr><td><?= $i++ ?></td><td><?= htmlspecialchars($player['team_name']) ?></td><td><?= htmlspecialchars($player['full_name']) ?></td><td><?= htmlspecialchars($player['eligibility_status']) ?></td></tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?><p>No roster players found.</p><?php endif; ?>
    <script>window.onload = function() { setTimeout(() => window.print(), 500); };</script>
</body>
</html>
