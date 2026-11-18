<?php
session_start();
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../config/reportQuery.php';
require_once __DIR__ . '/../../../config/apiSecurity.php';
require_once __DIR__ . '/../../../includes/reportExport.php';
requireJsonRole([1]);
requireActiveAccount($conn, true);
enforceRequestSizeLimit(1 * 1024 * 1024); // 1MB
addApiSecurityHeaders();
header('Content-Type: application/json');

$userId = currentUserId();

try {
    $filters = reportFiltersFromRequest($_GET);
    $report  = buildReportQuery($conn, $userId, $filters, ['row_limit' => 5000]);
} catch (Throwable $e) {
    reportSendErrorResponse($e, true, 'reports.php');
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$filterSummary = [];
foreach ($report['filters'] as $label => $value) {
    $filterSummary[] = $label . ': ' . $value;
}
reportAuditSafely(
    $conn,
    'Generate League Report',
    'Generated ' . $report['title'] . ' (' . $report['row_count'] . ' rows'
        . ($report['truncated'] ? ', truncated' : '') . ')'
        . ($filterSummary ? ' — ' . implode(' | ', $filterSummary) : '')
);

try {
    echo json_encode([
        'status'       => 200,
        'title'        => $report['title'],
        'type'         => $report['type'],
        'filters'      => $report['filters'],
        'generated_at' => $report['generated_at'],
        'row_count'    => $report['row_count'],
        'truncated'    => $report['truncated'],
        'rows'         => $report['rows'],
    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    reportSendErrorResponse(new ReportExecutionException('Unable to encode the selected report.'), true, 'reports.php');
}
