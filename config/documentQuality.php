<?php
/**
 * Document quality checker policy.
 *
 * The analysis itself lives in scripts/check_blur.py + scripts/quality/*.
 * Everything here controls how strict the PHP layer is about the verdict it
 * receives, so an organizer can tune policy without touching code.
 *
 * Environment variables (all optional):
 *   LMS_DOCUMENT_OCR                 auto|on|off  (default auto)
 *   LMS_DOCUMENT_BLOCK_UNAVAILABLE   1 = reject uploads while the checker is down
 *   LMS_DOCUMENT_BLOCK_FLAGGED       1 = reject borderline documents instead of
 *                                        sending them to manual review
 *   LMS_DOCUMENT_OCR_PAGES           max pages per document that get OCR'd
 *   LMS_DOCUMENT_SESSION             0 = start a new checker process per batch
 *                                        instead of one warm process per request
 *   LMS_DOCUMENT_SYNC_BUDGET         seconds the checker may spend inside one
 *                                        HTTP request (default: 12 on Heroku,
 *                                        unlimited locally). Documents that are
 *                                        not reached in time are accepted for
 *                                        manual review instead of failing the
 *                                        registration.
 */

$env = static function (string $name, string $default): string {
    $value = getenv($name);
    return ($value === false || trim($value) === '') ? $default : trim($value);
};

return [
    // 'auto' runs OCR when an engine is installed, 'on' requires it, 'off' skips it.
    'ocr' => in_array(strtolower($env('LMS_DOCUMENT_OCR', 'auto')), ['auto', 'on', 'off'], true)
        ? strtolower($env('LMS_DOCUMENT_OCR', 'auto'))
        : 'auto',

    // Fail closed: refuse documents while the checker/OCR is unavailable.
    'block_when_unavailable' => $env('LMS_DOCUMENT_BLOCK_UNAVAILABLE', '0') === '1',

    // Also block "flagged" (borderline) documents instead of accepting them for review.
    'block_when_flagged' => $env('LMS_DOCUMENT_BLOCK_FLAGGED', '0') === '1',

    // OCR is the expensive phase (~4 s per page). Documents are usually one page,
    // so the checker OCRs page 1 and only reads page 2 when that page was
    // illegible or lacked the expected content. This is the hard cap.
    'max_ocr_pages' => max(0, (int) $env('LMS_DOCUMENT_OCR_PAGES', '2')),

    // How many documents of one submission are checked at the same time.
    // Keep this at 1. The OCR runtime already uses every core, so extra worker
    // processes only oversubscribe the CPU. Measured on a 12-core Windows box:
    // 4 pages 15.8 s (1) vs 25.9 s (3); 6 pages 21.5 s (1) vs 52.6 s (3).
    'parallel_documents' => max(1, min(4, (int) $env('LMS_DOCUMENT_PARALLEL', '1'))),

    // Re-use the verdict for a byte-identical upload (same file + document type +
    // thresholds). Resubmitting the same photo then costs milliseconds instead of
    // a full OCR pass. Set LMS_DOCUMENT_CACHE=0 to disable.
    'cache_verdicts' => $env('LMS_DOCUMENT_CACHE', '1') !== '0',
    'cache_ttl_days' => max(1, (int) $env('LMS_DOCUMENT_CACHE_DAYS', '30')),

    // Whole-batch budget for the Python process (interpreter start + all files).
    'timeout_base' => 45,
    'timeout_per_extra_document' => 25,
    'timeout_max' => 300,

    // Verdicts that block a registration outright.
    'blocking_statuses' => ['blurry', 'unreadable', 'invalid'],
];
