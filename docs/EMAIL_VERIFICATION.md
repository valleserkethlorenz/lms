# Email Verification (Registration OTP)

The registration page (`register.php`) is a two-step flow:

```
Step 1  Verify email                  Step 2  Application
─────────────────────                 ───────────────────
email address  →  Send code           personal details
6-digit code   →  Verify & continue   password + CAPTCHA
                                      Create Account
```

The account is only created when the browser session carries a short-lived
proof that the posted email address was verified. The 6-digit code is a
one-time secret; the client-side wizard is only a UX layer.

## Files

| File | Role |
|---|---|
| `register.php` | Step 1 / Step 2 markup, stepper, CSRF token |
| `assets/js/register-wizard.js` | Send/verify AJAX, countdown, step transitions |
| `includes/login_action.php` | `sendEmailCode`, `verifyEmailCode`, gated `signUp` |
| `config/emailVerification.php` | Code lifecycle, session proof, email builder |
| `config/emailService.php` | Resend → SMTP → `mail()` transport |
| `database/migrations/042_email_verification.sql` | `tbl_email_verification` |
| `scripts/resend_smoke.php` | One-shot Resend delivery check |
| `tests/Unit/EmailVerificationTest.php` | Unit tests |

## Configuration

Set these as real environment variables (Apache `SetEnv`, Windows environment,
or Heroku config). Never commit the API key.

**Local XAMPP shortcut:** paste the key into `config/resend.local.php`
(gitignored). Environment variables take precedence when they are set.

```
RESEND_API_KEY=re_xxxxxxxxx
RESEND_FROM_EMAIL=verify@your-verified-domain.tld
RESEND_FROM_NAME=CSDP LMS
```

The sending domain must be **verified in Resend** (SPF/DKIM DNS records). If
it is not, Resend returns an error and the endpoint reports that the code could
not be sent.

Optional local helpers:

```
RESEND_TEST_TO=delivered@resend.dev   # redirect verification mail
EMAIL_VERIFICATION_DEV_LOG=1          # write the code to logs/email_verification.log
```

`delivered@resend.dev` is Resend's sandbox recipient: the API succeeds but no
inbox receives the message. Use `EMAIL_VERIFICATION_DEV_LOG=1` (or your own
address as `RESEND_TEST_TO`) while developing. Never enable the dev log in
production; `logs/.htaccess` already blocks web access to the folder.

## API contract

All three actions are POST requests to `includes/login_action.php` and require
the `csrf_token` hidden field from the registration form.

| Action | Fields | Success | Common errors |
|---|---|---|---|
| `sendEmailCode` | `sendEmailCode=1`, `email`, `csrf_token` | `{status:201, cooldown, expires_in}` | `401` invalid/already registered, `429` cooldown/rate limit, `502` email delivery failed |
| `verifyEmailCode` | `verifyEmailCode=1`, `email`, `code`, `csrf_token` | `{status:201}` + session proof | `401` invalid/expired/too many attempts, `429` limiter |
| `signUp` | existing form + `signUp=1` | `{status:201}` and account created | `401` email not verified, CAPTCHA failure, duplicate data |

## Rules

- Code: 6 digits from `random_int()`, stored as a `password_hash()`.
- Expiry: 10 minutes; 5 wrong attempts burn the code.
- One active code per email; a resend invalidates the previous code.
- Resend cooldown: 60 seconds; max 3 codes per email per hour.
- Session proof: `sha256(email)` + 30-minute TTL; consumed once on signup.
- Every send/verify/failure is written to `SecurityLogger` with a masked email.
- The code is never written to logs unless `EMAIL_VERIFICATION_DEV_LOG=1`.

## Testing

```
# Unit tests
php vendor/phpunit/phpunit/phpunit --filter EmailVerificationTest

# Resend delivery (after exporting RESEND_API_KEY)
php scripts/resend_smoke.php
```

Manual flow: open `register.php` → enter the email → Send code → read the code
from the inbox (or `logs/email_verification.log`) → Verify & continue → fill
the application → Create Account → sign in.

## Troubleshooting

| Symptom | Check |
|---|---|
| "We could not send the verification code" | `RESEND_API_KEY` set? Domain verified? Check `logs/security/` for the provider error. |
| Code never arrives | Spam folder; `RESEND_FROM_EMAIL` domain; Resend dashboard Logs. |
| "Please verify your email address before creating your account" | The session proof expired or the email field was changed after verification. Verify again. |
| `419` response | Refresh the page so a fresh CSRF token is rendered. |
