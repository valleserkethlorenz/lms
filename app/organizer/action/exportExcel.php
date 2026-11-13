<?php
session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../config/reportQuery.php';
requireJsonRole([1]);
requireActiveAccount($conn, true);
enforceRequestSizeLimit(2 * 1024 * 1024); // 2MB

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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

// Build spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(substr($title, 0, 31)); // Excel limit

$headers = array_keys($rows[0]);
$lastColLetter = Coordinate::stringFromColumnIndex(count($headers));

// Title row
$sheet->setCellValue('A1', $title);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getFont()->getColor()->setARGB('FF09173C');
$sheet->mergeCells('A1:' . $lastColLetter . '1');

// Metadata row
$meta = 'Generated: ' . $report['generated_at'];
foreach ($report['filters'] as $k => $v) $meta .= " | $k: $v";
$sheet->setCellValue('A2', $meta);
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF6B7280');
$sheet->mergeCells('A2:' . $lastColLetter . '2');

// Header row
$colIndex = 1;
foreach ($headers as $header) {
    $sheet->getCell([$colIndex, 4])->setValue(ucwords(str_replace('_', ' ', $header)));
    $colIndex++;
}

// Style header row
$headerRange = 'A4:' . $lastColLetter . '4';
$sheet->getStyle($headerRange)->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF09173C']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
]);

// Data rows
$rowIndex = 5;
foreach ($rows as $row) {
    $colIndex = 1;
    foreach ($row as $value) {
        $sheet->getCell([$colIndex, $rowIndex])->setValue($value);
        $colIndex++;
    }
    $rowIndex++;
}

// Auto-size columns
for ($c = 1; $c <= count($headers); $c++) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
}

// Freeze header
$sheet->freezePane('A5');

// Audit log
auditLog($conn, 'Export Excel Report', "Exported $title to XLSX (" . count($rows) . ' rows)');

// Output
$filename = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title)) . '-' . date('Ymd-His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;