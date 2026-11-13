<?php
session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../config/reportQuery.php';
require_once __DIR__ . '/../../../includes/reportDocument.php';
requireJsonRole([1]);
requireActiveAccount($conn, true);
enforceRequestSizeLimit(2 * 1024 * 1024); // 2MB

use Dompdf\Dompdf;
use Dompdf\Options;

$userId = currentUserId();
$type = trim($_GET['report_type'] ?? 'registrations');
$leagueId = (int)($_GET['league_id'] ?? 0);
$sport = trim($_GET['sport'] ?? '');
$division = trim($_GET['division'] ?? '');
$category = trim($_GET['category'] ?? '');
$leagueStatus = trim($_GET['league_status'] ?? '');
$from = trim($_GET['from_date'] ?? '');
$to = trim($_GET['to_date'] ?? '');

try {
    $report = buildReportQuery($conn, $userId, $type, $leagueId, $sport, $division, $category, $leagueStatus, $from, $to);
} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 400, 'msg' => $e->getMessage()]);
    exit;
} catch (\RuntimeException $e) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 403, 'msg' => $e->getMessage()]);
    exit;
}

$rows = $report['rows'];
$title = $report['title'];

if (empty($rows)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['status' => 404, 'msg' => 'No data to export.']);
    exit;
}

// Build the document with the shared renderer so the PDF matches the printed
// sheet (same letterhead, filter summary and signature block).
$leagueName = '';
if ($leagueId > 0) {
    $leagueStmt = $conn->prepare("SELECT league_name FROM tbl_league WHERE id=? AND created_by=? LIMIT 1");
    $leagueStmt->bind_param('ii', $leagueId, $userId);
    $leagueStmt->execute();
    $leagueRow = $leagueStmt->get_result()->fetch_assoc();
    $leagueStmt->close();
    $leagueName = (string)($leagueRow['league_name'] ?? '');
}
$html = buildReportDocumentHtml($report, [
    'subtitle'     => $leagueName !== '' ? $leagueName : 'All Owned Leagues',
    'generated_by' => currentUserName(),
    'logo_path'    => dirname(__DIR__, 3) . '/assets/images/logo.jpg',
    'signatories'  => ['Prepared by' => currentUserName(), 'Approved by' => ''],
]);

// Generate PDF
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Audit log
auditLog($conn, 'Export PDF Report', "Exported $title to PDF (" . count($rows) . ' rows)');

// Output
$filename = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title)) . '-' . date('Ymd-His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
