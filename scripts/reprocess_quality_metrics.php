<?php
/**
 * Batch Quality Metrics Reprocessing Script
 * ==========================================
 * CLI script to backfill quality_metrics for existing documents that were
 * uploaded before the metrics persistence feature was added.
 *
 * Usage:
 *   php scripts/reprocess_quality_metrics.php              # Process all missing
 *   php scripts/reprocess_quality_metrics.php --dry-run    # Preview without saving
 *   php scripts/reprocess_quality_metrics.php --limit=50   # Process first 50 only
 *   php scripts/reprocess_quality_metrics.php --verbose    # Show detailed output
 *
 * Exit codes:
 *   0 = Success
 *   1 = Error (database connection, etc.)
 *   2 = Partial failure (some documents could not be processed)
 */

declare(strict_types=1);

// ── CLI-only guard ──
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

// ── Parse command-line arguments ──
$options = getopt('', ['dry-run', 'limit:', 'verbose', 'help', 'force']);

if (isset($options['help'])) {
    echo "Batch Quality Metrics Reprocessing Script\n";
    echo "==========================================\n";
    echo "Re-analyzes documents and stores quality metrics in the database.\n\n";
    echo "Options:\n";
    echo "  --dry-run   Preview changes without saving to database\n";
    echo "  --limit=N   Process at most N documents\n";
    echo "  --verbose   Show detailed metrics for each document\n";
    echo "  --force     Re-process documents that already have metrics\n";
    echo "  --help      Show this help message\n";
    exit(0);
}

$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);
$force = isset($options['force']);
$limit = isset($options['limit']) ? (int)$options['limit'] : PHP_INT_MAX;

echo "============================================================\n";
echo "Batch Quality Metrics Reprocessing\n";
echo "============================================================\n";
if ($dryRun) echo "MODE: Dry run (no database changes)\n";
if ($force) echo "MODE: Force re-processing (including existing metrics)\n";
echo "============================================================\n\n";

// ── Load dependencies ──
require_once __DIR__ . '/../config/dbconnect.php';
require_once __DIR__ . '/../includes/documentValidator.php';
require_once __DIR__ . '/../includes/documentStorage.php';

// $conn is already initialized by dbconnect.php
if (!$conn || $conn->connect_error) {
    fwrite(STDERR, "Database connection failed: " . ($conn->connect_error ?? 'unknown') . "\n");
    exit(1);
}
$conn->set_charset('utf8mb4');


// ── Query documents needing reprocessing ──
$whereClause = $force ? '1=1' : 'quality_metrics IS NULL';
$query = "SELECT d.id, d.stored_name, d.original_name, d.quality_status,
                 u.name AS document_type, t.team_name, l.league_name
          FROM tbl_submitted_document d
          JOIN tbl_team t ON t.id = d.team_id
          JOIN tbl_league l ON l.id = t.league_id
          LEFT JOIN tbl_league_document_requirement r ON r.id = d.requirement_id
          LEFT JOIN tbl_utility u ON u.id = r.document_type_id
          WHERE $whereClause
          ORDER BY d.id ASC";

if ($limit < PHP_INT_MAX) {
    $query .= " LIMIT " . intval($limit);
}

$result = $conn->query($query);
if (!$result) {
    fwrite(STDERR, "Query failed: {$conn->error}\n");
    $conn->close();
    exit(1);
}

$documents = [];
while ($row = $result->fetch_assoc()) {
    $documents[] = $row;
}
$result->free();

$totalCount = count($documents);
echo "Found $totalCount document(s) to process.\n\n";

if ($totalCount === 0) {
    echo "Nothing to do. All documents already have quality metrics.\n";
    $conn->close();
    exit(0);
}

// ── Process each document ──
$stats = ['processed' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0];
$updateStmt = $conn->prepare("UPDATE tbl_submitted_document SET quality_metrics = ?, quality_status = ? WHERE id = ?");

foreach ($documents as $i => $doc) {
    $num = $i + 1;
    $docId = (int)$doc['id'];
    $storedName = $doc['stored_name'];
    $originalName = $doc['original_name'];
    
    echo "[$num/$totalCount] Document #$docId: $originalName";
    
    // Locate the file
    $filePath = documentStoredPath($storedName);
    if (!$filePath) {
        echo " [SKIPPED: file not found]\n";
        $stats['skipped']++;
        continue;
    }
    
    // Run quality check (with the configured document type so OCR can verify content)
    $quality = checkDocumentQuality($filePath, (string)($doc['document_type'] ?? ''));
    $status = $quality['status'];
    
    if ($status === 'unavailable') {
        echo " [FAILED: {$quality['reason']}]\n";
        $stats['failed']++;
        continue;
    }
    
    // Build metrics JSON (same schema as the registration flow)
    $pagesMetrics = $quality['details']['pages'] ?? [];
    $metricsJson = documentQualityMetricsPayload($quality);
    
    $dbStatus = $status === 'clear' ? 'Clear' : 'Flagged';
    
    if ($verbose) {
        echo "\n  Status: $status";
        echo "\n  DB Status: $dbStatus";
        if (!empty($pagesMetrics)) {
            $p = $pagesMetrics[0];
            $ocr = is_array($p['ocr'] ?? null) ? $p['ocr'] : [];
            echo "\n  Page 1: rel_sharpness=" . ($p['rel_sharpness'] ?? '-')
                . ", worst_tile=" . ($p['rel_sharp_p10'] ?? '-')
                . ", contrast=" . ($p['contrast'] ?? '-')
                . ", text_height=" . ($p['text_height'] ?? '-')
                . ", ocr_chars=" . ($ocr['chars'] ?? 0)
                . ", ocr_confidence=" . ($ocr['mean_confidence'] ?? 0);
        }
        echo "\n  Metrics: $metricsJson\n";
    }
    
    // Save to database (unless dry-run)
    if ($dryRun) {
        echo " [DRY-RUN: would update to $dbStatus]\n";
        $stats['processed']++;
    } else {
        $updateStmt->bind_param('ssi', $metricsJson, $dbStatus, $docId);
        if ($updateStmt->execute()) {
            echo " [UPDATED: $dbStatus]\n";
            $stats['updated']++;
        } else {
            echo " [FAILED: DB update error]\n";
            $stats['failed']++;
        }
    }
}

$updateStmt->close();
$conn->close();

// ── Summary ──
echo "\n============================================================\n";
echo "Reprocessing Summary\n";
echo "============================================================\n";
echo "Total documents:  $totalCount\n";
echo "Processed:        {$stats['processed']}\n";
echo "Updated in DB:    {$stats['updated']}\n";
echo "Skipped:          {$stats['skipped']}\n";
echo "Failed:           {$stats['failed']}\n";
echo "============================================================\n";

if ($stats['failed'] > 0) {
    exit(2);
}
exit(0);