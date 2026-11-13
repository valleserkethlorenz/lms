<?php
/**
 * Document checker health probe (CLI).
 *
 * Usage:
 *   php scripts/document_checker_health.php            # report only
 *   php scripts/document_checker_health.php --self-test # also analyse a sample page
 *
 * Exit code 0 = checker healthy, 1 = checker unavailable/degraded, 2 = self-test failed.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../includes/documentValidator.php';

$selfTest = in_array('--self-test', $argv ?? [], true);

$config = documentQualityConfig();
$python = findDocumentCheckerPython();
$capabilities = documentCheckerCachedCapabilities();
$poppler = null;
foreach (['C:\\poppler\\poppler-26.09.0\\Library\\bin', 'C:\\poppler\\Library\\bin',
          'C:\\Program Files\\poppler\\Library\\bin', 'C:\\Program Files (x86)\\poppler\\Library\\bin'] as $candidate) {
    if (is_file($candidate . DIRECTORY_SEPARATOR . 'pdftoppm.exe')) { $poppler = $candidate; break; }
}

echo "Document quality checker\n";
echo str_repeat('=', 60), "\n";
echo 'Python interpreter : ', $python ?: 'NOT FOUND', "\n";
echo 'Probe cached at    : ', $capabilities ? date('Y-m-d H:i:s', (int) ($capabilities['checked_at'] ?? 0)) : 'never', "\n";
echo 'OpenCV             : ', $capabilities['cv2'] ?? 'unknown', "\n";
echo 'NumPy              : ', $capabilities['numpy'] ?? 'unknown', "\n";
echo 'OCR engine         : ', ($capabilities['ocr_available'] ?? false)
    ? (($capabilities['ocr'] ?? '?') . ' (ready)')
    : ('UNAVAILABLE - ' . ($capabilities['ocr_detail'] ?? 'not probed')), "\n";
echo 'Poppler (PDF)      : ', (getenv('POPPLER_PATH') ?: ($poppler ?: 'not found on PATH')), "\n";
echo 'OCR mode           : ', $config['ocr'], "\n";
echo 'OCR page limit     : ', (int) $config['max_ocr_pages'], "\n";
echo 'Block if down      : ', $config['block_when_unavailable'] ? 'yes' : 'no', "\n";
echo 'Block if flagged   : ', $config['block_when_flagged'] ? 'yes' : 'no', "\n";
echo 'Blocking verdicts  : ', implode(', ', (array) $config['blocking_statuses']), "\n";

$healthy = $python !== null && $capabilities !== null && !empty($capabilities['ok']);
echo "\nStatus             : ", $healthy ? 'OK' : 'DEGRADED (documents will be accepted unchecked!)', "\n";
if (!$healthy) {
    exit(1);
}

if (!$selfTest) {
    echo "\nRun with --self-test to analyse a generated sample page.\n";
    exit(0);
}

// The self-test needs a file inside the private document directory, because
// checkDocumentQuality() refuses paths outside it (same rule as uploads).
$fixture = dirname(__DIR__) . '/tests/fixtures/sample_page.jpg';
if (!is_file($fixture)) {
    echo "Self-test fixture missing: $fixture\n";
    exit(2);
}
$directory = documentStorageDirectory();
$target = $directory . DIRECTORY_SEPARATOR . 'self-test-' . bin2hex(random_bytes(6)) . '.jpg';
if (!@copy($fixture, $target)) {
    echo "Cannot copy the fixture into the private document directory.\n";
    exit(2);
}

$result = checkDocumentQuality($target, 'Birth Certificate');
@unlink($target);

echo "\nSelf-test result   : ", $result['status'], "\n";
echo 'Reason             : ', $result['reason'], "\n";
$page = $result['details']['pages'][0] ?? [];
if ($page) {
    echo 'Sharpness (rel)    : ', $page['rel_sharpness'] ?? '?',
        ' (worst tile ', $page['rel_sharp_p10'] ?? '?', ")\n";
    echo 'Contrast / ink     : ', $page['contrast'] ?? '?', ' / ', $page['ink_ratio'] ?? '?', "\n";
    echo 'OCR chars / conf   : ', $page['ocr']['chars'] ?? 0, ' / ', $page['ocr']['mean_confidence'] ?? 0, "\n";
}

$pass = in_array($result['status'], ['clear', 'flagged'], true);
echo "Self-test          : ", $pass ? "PASS\n" : "FAIL (a clean sample page should not be rejected)\n";
exit($pass ? 0 : 2);
