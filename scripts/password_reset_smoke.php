<?php
/**
 * Password reset HTTP smoke test.
 *
 * Usage:
 *   php -S 127.0.0.1:8099 -t .
 *   php scripts/password_reset_smoke.php http://127.0.0.1:8099
 *
 * The test uses a non-existent email, so no real OTP email is sent. It checks
 * the two-step page, CSRF handling, the OTP failure path, and the unverified
 * reset page.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
$cookieJar = tempnam(sys_get_temp_dir(), 'lms_pwd_reset_');
if ($cookieJar === false) {
    $cookieJar = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lms_pwd_reset_cookies.txt';
}
$failures = 0;

function smokeGet(string $url, string $cookieJar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body, 'error' => $error];
}

function smokePost(string $url, array $fields, string $cookieJar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body, 'error' => $error];
}

function smokeCheck(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    if (!$ok) $failures++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . ($detail !== '' ? ' - ' . $detail : '') . PHP_EOL;
}

$page = smokeGet($base . '/forgot-password.php', $cookieJar);
smokeCheck('GET forgot-password.php', $page['status'] === 200 && $page['error'] === '', 'status=' . $page['status'] . ' ' . $page['error']);
smokeCheck('Step 1 identifies the Team Representative reset', strpos($page['body'], 'Team Representative') !== false);
smokeCheck('Step 1 is not the error page', strpos($page['body'], 'Something went wrong') === false);
smokeCheck(
    'Back to Login uses the login secondary button style',
    strpos($page['body'], 'login-secondary-link') !== false && strpos($page['body'], 'Back to Login') !== false
);
smokeCheck(
    'Back to Home uses the login secondary button style',
    strpos($page['body'], 'login-secondary-link') !== false && strpos($page['body'], 'Back to Home') !== false
);

if (preg_match('/name="csrf_token" value="([^"]+)"/', $page['body'], $m)) {
    smokeCheck('CSRF token rendered', true);

    $first = smokePost($base . '/forgot-password.php', [
        'csrf_token' => $m[1],
        'action'     => 'request_code',
        'email'      => 'nobody-test@example.invalid',
    ], $cookieJar);
    smokeCheck('POST request_code', $first['status'] === 200 && $first['error'] === '', 'status=' . $first['status'] . ' ' . $first['error']);
    smokeCheck('Unknown email shows the no-account message', strpos($first['body'], 'No Team Representative account found') !== false);
    smokeCheck('Unknown email stays on step 1', strpos($first['body'], 'Enter Verification Code') === false);
    smokeCheck('Unknown email is prefilled in the form', strpos($first['body'], 'nobody-test@example.invalid') !== false);
    smokeCheck('POST request_code is not the error page', strpos($first['body'], 'Something went wrong') === false);

    if (preg_match('/name="csrf_token" value="([^"]+)"/', $first['body'], $m2)) {
        $second = smokePost($base . '/forgot-password.php', [
            'csrf_token' => $m2[1],
            'action'     => 'verify_code',
            'code'       => '123456',
        ], $cookieJar);
        smokeCheck('POST verify_code without a pending email fails safely', $second['status'] === 200 && $second['error'] === '', 'status=' . $second['status'] . ' ' . $second['error']);
        smokeCheck('Missing pending email shows the expired-session message', strpos($second['body'], 'Your reset session expired') !== false);
        smokeCheck('Missing pending email stays on step 1', strpos($second['body'], 'Enter Verification Code') === false);
        smokeCheck('POST verify_code is not the error page', strpos($second['body'], 'Something went wrong') === false);
    } else {
        smokeCheck('Step 1 renders a CSRF token', false);
    }
} else {
    smokeCheck('CSRF token rendered', false);
}

$reset = smokeGet($base . '/reset-password.php', $cookieJar);
smokeCheck('GET reset-password.php', $reset['status'] === 200 && $reset['error'] === '', 'status=' . $reset['status'] . ' ' . $reset['error']);
smokeCheck('Reset page requires a new code when unverified', strpos($reset['body'], 'Request New Code') !== false);
smokeCheck('Reset page is not the error page', strpos($reset['body'], 'Something went wrong') === false);

@unlink($cookieJar);
echo PHP_EOL . ($failures === 0 ? 'All password-reset smoke checks passed.' : $failures . ' check(s) failed.') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
