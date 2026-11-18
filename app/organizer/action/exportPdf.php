<?php
session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../config/reportQuery.php';
require_once __DIR__ . '/../../../config/apiSecurity.php';
require_once __DIR__ . '/../../../includes/reportDocument.php';
require_once __DIR__ . '/../../../includes/reportExport.php';
requireJsonRole([1]);
requireActiveAccount($conn, true);
enforceRequestSizeLimit(2 * 1024 * 1024); // 2MB

use Dompdf\Dompdf;
use Dompdf\Options;

$userId   = currentUserId();
$userName = currentUserName();

try {
    $filters = reportFiltersFromRequest($_GET);
} catch (Throwable $e) {
    reportSendErrorResponse($e, reportWantsJson(), '../reports.php');
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $report = buildReportQuery($conn, $userId, $filters);
} catch (Throwable $e) {
    reportSendErrorResponse($e, reportWantsJson(), '../reports.php');
}

if (empty($report['rows'])) {
    reportSendNoDataResponse(reportWantsJson(), '../reports.php');
}

try {
    $leagueName = (string) ($report['league']['league_name'] ?? '');

    $html = buildReportDocumentHtml($report, [
        'subtitle'     => $leagueName !== '' ? $leagueName : 'All Owned Leagues',
        'generated_by' => $userName,
        'logo_path'    => dirname(__DIR__, 3) . '/assets/images/logo.jpg',
        'signatories'  => ['Prepared by' => $userName, 'Approved by' => ''],
    ]);

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $pdf = $dompdf->output();

    reportAuditSafely(
        $conn,
        'Export PDF Report',
        'Exported ' . $report['title'] . ' to PDF (' . $report['row_count'] . ' rows)'
    );

    $filename = reportDownloadFilename($report['title'], 'pdf', $leagueName);
} catch (Throwable $e) {
    error_log('[REPORT] PDF export failed: ' . get_class($e) . ': ' . $e->getMessage());
    reportSendErrorResponse(
        new ReportExecutionException('Unable to build the PDF export.'),
        reportWantsJson(),
        '../reports.php'
    );
}

reportSendDownloadHeaders($filename, 'application/pdf');
echo $pdf;
exit;
