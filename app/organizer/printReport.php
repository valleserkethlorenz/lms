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
require_once __DIR__ . '/../../includes/reportExport.php';

requireRole([1]);
requireActiveAccount($conn);

$userId   = currentUserId();
$userName = currentUserName();
$autoRaw  = $_GET['auto'] ?? '';
$auto     = is_string($autoRaw) && $autoRaw === '1';

try {
    $filters = reportFiltersFromRequest($_GET);
} catch (Throwable $e) {
    reportSendErrorResponse($e, reportWantsJson(), 'reports.php');
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $report = buildReportQuery($conn, $userId, $filters);
} catch (Throwable $e) {
    reportSendErrorResponse($e, reportWantsJson(), 'reports.php');
}

$leagueId   = (int) $filters['league_id'];
$leagueName = (string) ($report['league']['league_name'] ?? '');

reportAuditSafely(
    $conn,
    'Print Report',
    'Opened printable report: ' . $report['title']
        . ($leagueId ? " (league ID $leagueId)" : ' (all owned leagues)')
);

$document = buildReportDocumentHtml($report, [
    'subtitle'     => $leagueName !== '' ? $leagueName : 'All Owned Leagues',
    'generated_by' => $userName,
    'logo_path'    => dirname(__DIR__, 2) . '/assets/images/logo.jpg',
    'signatories'  => ['Prepared by' => $userName, 'Approved by' => ''],
]);

// Toolbar (hidden when printing) and optional auto-print.
$toolbar = '<div class="print-toolbar no-print"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>';
$document = str_replace('<body>', '<body>' . $toolbar, $document);
if ($auto) {
    $document = str_replace('</body>', '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},400);});</script></body>', $document);
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
echo $document;
