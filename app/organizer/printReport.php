<?php
/**
 * Printable Report View (Organizer)
 * ==================================
 * Renders the official, print-ready report document for the filters selected on
 * reports.php. Opened by the "Print" button; accepts ?auto=1 to open the browser
 * print dialog automatically.
 *
 * Rows come from buildReportQuery(), the same source the on-screen table and the
 * PDF/Excel exports use, so all outputs agree.
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../config/reportQuery.php';
require_once __DIR__ . '/../../includes/reportDocument.php';

requireRole([1]);
requireActiveAccount($conn);

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');

$userId   = currentUserId();
$type     = trim($_GET['report_type'] ?? 'registrations');
$leagueId = (int)($_GET['league_id'] ?? 0);
$sport    = trim($_GET['sport'] ?? '');
$division = trim($_GET['division'] ?? '');
$category = trim($_GET['category'] ?? '');
$status   = trim($_GET['league_status'] ?? '');
$from     = trim($_GET['from_date'] ?? '');
$to       = trim($_GET['to_date'] ?? '');
$auto     = ($_GET['auto'] ?? '') === '1';

$report = null;
$error  = '';
try {
    $report = buildReportQuery($conn, $userId, $type, $leagueId, $sport, $division, $category, $status, $from, $to);
} catch (InvalidArgumentException $e) {
    $error = $e->getMessage();
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}

if ($error !== '') {
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Report error</title></head>'
        . '<body style="font-family:Segoe UI,Arial,sans-serif;padding:40px;color:#1f2937;">'
        . '<h2>Unable to build the report</h2><p>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><a href="reports.php">Back to Reports</a></p></body></html>';
    exit;
}

// League name for the letterhead (ownership was already enforced by buildReportQuery).
$leagueName = '';
if ($leagueId > 0) {
    $leagueStmt = $conn->prepare("SELECT league_name FROM tbl_league WHERE id=? AND created_by=? LIMIT 1");
    $leagueStmt->bind_param('ii', $leagueId, $userId);
    $leagueStmt->execute();
    $leagueRow = $leagueStmt->get_result()->fetch_assoc();
    $leagueStmt->close();
    $leagueName = (string)($leagueRow['league_name'] ?? '');
}

auditLog($conn, 'Print Report', 'Opened printable report: ' . $report['title'] . ($leagueId ? " (league ID $leagueId)" : ' (all owned leagues)'));

$document = buildReportDocumentHtml($report, [
    'subtitle'     => $leagueName !== '' ? $leagueName : 'All Owned Leagues',
    'generated_by' => currentUserName(),
    'logo_path'    => dirname(__DIR__, 2) . '/assets/images/logo.jpg',
    'signatories'  => ['Prepared by' => currentUserName(), 'Approved by' => ''],
]);

// Toolbar (hidden when printing) and optional auto-print.
$toolbar = '<div class="print-toolbar no-print"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>';
$document = str_replace('<body>', '<body>' . $toolbar, $document);
if ($auto) {
    $document = str_replace('</body>', '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},400);});</script></body>', $document);
}

echo $document;
