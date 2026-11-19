# Password Reset (Team Representative OTP)

`login.php` → **Forgot password** starts a two-step, OTP-based reset for
**Team Representative** (`role = 2`) accounts.

```
Step 1  Request code
─────────────────────
registered email
   ↓
active Team Representative?
   ├─ no  → "No Team Representative account found" (no code sent)
   └─ yes → send 6-digit code (10 min)
                ↓
Step 2  Verify code
───────────────────
enter code  →  session grant (15 min)  →  reset-password.php
```

The account email (`tbl_user.email`) is the destination. It is the verified
login identity; the `tbl_team.email` contact field is not used for recovery.

## Why OTP instead of a reset link?

The original pages called `generateCsrfToken()` / `validateCsrfToken()`, which
did not exist, so PHP raised an uncaught `Error` and `config/errorHandler.php`
rendered the generic “Something went wrong” page. The OTP flow also removes the
dependency on a hard-coded `/lms/` reset URL and avoids link forwarding.

## Files

| File | Role |
|---|---|
| `forgot-password.php` | Step 1 / Step 2 markup, CSRF token, resend cooldown |
| `reset-password.php` | New-password form behind a session grant (legacy tokens still accepted) |
| `config/passwordReset.php` | OTP lifecycle, reset grant, password update, legacy token helpers |
| `config/emailVerification.php` | Shared 6-digit code engine (`tbl_email_verification`) |
| `config/emailService.php` | Resend → SMTP → `mail()` transport |
| `config/auth_guard.php` | `csrfToken()` and `validateCsrfToken()` |
| `database/migrations/042_email_verification.sql` | Shared verification-code table |
| `tests/Unit/PasswordResetTest.php` | OTP, role, grant and email tests |
| `tests/Unit/AuthGuardTest.php` | CSRF validation tests |

No new migration is required. `ensureEmailVerificationTable()` creates the
shared table automatically if it is missing.

## Rules and limits

| Control | Value |
|---|---|
| Eligible roles | `role = '2'` (Team Representative) and `stat = '1'` |
| Eligibility check | `tbl_user` lookup before any code row or email is created |
| Code | 6 digits from `random_int()`, stored as a `password_hash()` |
| Code expiry | 10 minutes (`PASSWORD_RESET_OTP_EXPIRY`) |
| Wrong attempts | 5; the code is burned after the fifth failure |
| Resend cooldown | 60 seconds (`PASSWORD_RESET_OTP_RESEND_COOLDOWN`) |
| Codes per email | 3 per hour (shared verification-code helper) |
| Send requests | 5/hour per email+IP, 10/hour per IP (`RateLimiter`) |
| Verify attempts | 10 per 15 minutes per email+IP (`RateLimiter`) |
| Reset grant | 15 minutes (`PASSWORD_RESET_GRANT_TTL`), session-bound |
| Password rules | Existing `validatePasswordEnhanced()`: complexity, common-password list, history |
| CSRF | Every POST validates the session token |

Every POST uses POST/redirect/GET, so refreshing the page cannot resend a code.

Step 1 checks `tbl_user` **before** creating a code. If the address does not
belong to an active Team Representative (`role = '2'`, `stat = '1'`), the page
shows “No Team Representative account found”, creates no verification row, and
sends no email. Only eligible accounts reach the OTP step.

This intentionally reveals whether an email belongs to an active Team
Representative account. The session rate limits reduce mass probing, but they
do not fully prevent account enumeration; see the security notes below.

Legacy link tokens (`tbl_password_reset`) are still validated by
`reset-password.php` if one already exists, but the current UI never sends new
link emails.

## Configuration

The OTP email uses the same transport as registration (`config/emailService.php`):

```
RESEND_API_KEY=re_xxxxxxxxx
RESEND_FROM_EMAIL=verify@your-verified-domain.tld
RESEND_FROM_NAME=CSDP LMS
```

Local helpers (never enable these in production):

```
RESEND_TEST_TO=delivered@resend.dev   # redirect all reset mail
PASSWORD_RESET_DEV_LOG=1              # write codes to logs/password_reset.log
EMAIL_VERIFICATION_DEV_LOG=1          # already used by registration; also logs reset codes
```

The code is never written to a log unless one of the dev flags is enabled.

## Testing

```
C:\xampp\php\php.exe vendor\phpunit\phpunit\phpunit --filter "PasswordResetTest|AuthGuardTest|EmailVerificationTest"
```

### Automated smoke tests

```bat
:: Database lifecycle — creates and removes a temporary account; sends no email
C:\xampp\php\php.exe scripts\password_reset_db_smoke.php

:: HTTP pages — start the built-in server in another terminal first
C:\xampp\php\php.exe -S 127.0.0.1:8099 -t .
C:\xampp\php\php.exe scripts\password_reset_smoke.php http://127.0.0.1:8099
```

The HTTP smoke test uses a non-existent email to verify the no-account message,
the email-step CSRF handling, and the unverified reset page without delivering
mail. The existing-account OTP lifecycle is covered by the database smoke test.

### Manual flow

1. Open `forgot-password.php`.
2. Enter the email of an active Team Representative account.
3. Read the 6-digit code from the inbox (or `logs/password_reset.log`).
4. Enter the code and verify it.
5. Choose a new password (8+ characters, upper/lower, number, special character;
   it must not be a recently used password).
6. Sign in with the new password.

## Troubleshooting

| Symptom | Check |
|---|---|
| “Something went wrong” | Check `logs/php_errors.log`. The old failure was `Call to undefined function generateCsrfToken()`. |
| Code never arrives | Spam folder; `RESEND_API_KEY`; verified Resend domain; `RESEND_TEST_TO`; dev log. |
| “No Team Representative account found” | The email must belong to an active `role = '2'` account (`stat = '1'`). Check the address or contact the administrator. |
| “Please wait N second(s)” | The 60-second resend cooldown or hourly rate limit is active. |
| “Your verification has expired” | The 15-minute reset grant is session-bound; verify the code in the same browser. |
| Reset form does not appear | The OTP step was not completed or the grant expired; request a new code. |

## Security notes

- OTPs are stored as hashes; the plaintext is only sent by email.
- Step 1 deliberately discloses whether an email belongs to an active Team
  Representative account. This is a usability/security tradeoff; keep the
  session rate limits enabled, and consider CAPTCHA if enumeration becomes a
  concern.
- The reset grant is bound to the browser session, the user ID, and
  `sha256(normalized email)`, and is consumed after a successful reset.
- Active sessions are not revoked after a reset because the schema has no
  session registry; the new password applies to the next login.
