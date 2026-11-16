<?php
/**
 * Email verification DB smoke test
 * ================================
 * Exercises the tbl_email_verification lifecycle without sending email:
 * create -> wrong code -> correct code -> session proof -> consume, plus
 * expiry and attempt-limit behaviour.
 *
 * Usage (from the lms/ directory):  php scripts/email_verification_smoke.php
 * Exit code 1 means a check failed.
 */
require_once __DIR__ . '/../config/dbconnect.php';
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/emailVerification.php';

$failures = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $failures;
    echo ($condition ? 'PASS' : 'FAIL') . '  ' . $label . ($detail !== '' ? '  (' . $detail . ')' : '') . PHP_EOL;
    if (!$condition) $failures++;
}

$email = 'verify-smoke-' . time() . '-' . random_int(1000, 9999) . '@example.com';
ensureEmailVerificationTable($conn);

// Clean slate for the unique address.
$del = $conn->prepare('DELETE FROM tbl_email_verification WHERE email = ?');
$del->bind_param('s', $email);
$del->execute();
$del->close();

// 1. Allowed to send; create a code.
$window = canSendVerificationCode($conn, $email);
check('can send the first code', $window['allowed'] === true, json_encode($window));

$created = createEmailVerification($conn, $email, '127.0.0.1', 'smoke-test');
check('code created', !empty($created['ok']), $created['error'] ?? '');
$code = (string) ($created['code'] ?? '');

// 2. Immediate resend is blocked by the cooldown.
$window = canSendVerificationCode($conn, $email);
check('resend cooldown enforced', $window['allowed'] === false && $window['reason'] === 'cooldown', json_encode($window));

// 3. Wrong code is rejected.
$wrong = $code === '000000' ? '111111' : '000000';
$result = verifyEmailVerificationCode($conn, $email, $wrong);
check('wrong code rejected', $result['ok'] === false && $result['reason'] === 'invalid', json_encode($result));

// 4. Correct code is accepted.
$result = verifyEmailVerificationCode($conn, $email, $code);
check('correct code accepted', $result['ok'] === true, json_encode($result));

// 5. Session proof is bound to the email.
markEmailVerifiedInSession($email);
check('session proof accepts the email', isEmailVerifiedInSession($email));
check('session proof rejects another email', !isEmailVerifiedInSession('other-' . $email));
clearEmailVerificationSession();

// 6. Consume is single-use.
check('consume succeeds', consumeVerifiedEmail($conn, $email));
check('second consume fails', !consumeVerifiedEmail($conn, $email));

// 7. Expired code is rejected.
$created = createEmailVerification($conn, $email, '127.0.0.1', 'smoke-test');
$past = date('Y-m-d H:i:s', time() - 60);
$upd = $conn->prepare('UPDATE tbl_email_verification SET expires_at = ? WHERE id = ?');
$upd->bind_param('si', $past, $created['id']);
$upd->execute();
$upd->close();
$result = verifyEmailVerificationCode($conn, $email, (string) $created['code']);
check('expired code rejected', $result['ok'] === false && $result['reason'] === 'expired', json_encode($result));

// 8. Five wrong attempts burn the code.
$created = createEmailVerification($conn, $email, '127.0.0.1', 'smoke-test');
$wrong = $created['code'] === '000000' ? '111111' : '000000';
$last = null;
for ($i = 0; $i < EMAIL_VERIFICATION_MAX_ATTEMPTS; $i++) {
    $last = verifyEmailVerificationCode($conn, $email, $wrong);
}
check('attempt limit burns the code', $last !== null && $last['reason'] === 'too_many', json_encode($last));
$result = verifyEmailVerificationCode($conn, $email, (string) $created['code']);
check('burned code cannot be used', $result['ok'] === false && $result['reason'] === 'no_code', json_encode($result));

// Cleanup the smoke rows.
$del = $conn->prepare('DELETE FROM tbl_email_verification WHERE email = ?');
$del->bind_param('s', $email);
$del->execute();
$del->close();

echo PHP_EOL . ($failures === 0 ? 'ALL CHECKS PASSED' : $failures . ' CHECK(S) FAILED') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
