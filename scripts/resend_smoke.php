<?php
/**
 * Resend delivery smoke test
 * ==========================
 * Sends one email through the configured transport (Resend when
 * RESEND_API_KEY is set) and prints the result.
 *
 * Usage (from the lms/ directory):
 *   php scripts/resend_smoke.php [recipient]
 *
 * Default recipient: delivered@resend.dev (Resend's sandbox address; the
 * API confirms delivery but no inbox receives the message).
 */
require_once __DIR__ . '/../config/emailService.php';

$to = $argv[1] ?? (getenv('RESEND_TEST_TO') ?: 'delivered@resend.dev');

if (!isResendConfigured()) {
    fwrite(STDERR, "RESEND_API_KEY is not set.\n");
    fwrite(STDERR, "Paste the new key into config/resend.local.php (gitignored) or set the RESEND_API_KEY environment variable, then run this script again.\n");
    exit(1);
}

echo 'From: ' . RESEND_FROM_NAME . ' <' . RESEND_FROM_EMAIL . '>' . PHP_EOL;
echo 'To:   ' . $to . PHP_EOL;

$result = sendLmsEmail(
    $to,
    'LMS Resend smoke test',
    'it works!',
    '',
    '<p>it works!</p>'
);

echo 'Result: ' . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(!empty($result['success']) ? 0 : 1);
