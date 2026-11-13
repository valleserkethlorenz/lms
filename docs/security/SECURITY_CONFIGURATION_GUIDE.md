# Security Configuration Guide - Steps 13-19

## Overview
This document covers security remediation steps 13-19 for the LMS, including session security, error handling, password security, logging, API security, and configuration hardening.

---

## Step 13: Session Security Hardening

### Implementation
- **File:** `lms/config/sessionHandler.php`
- **Features:**
  - Secure session cookie settings (HttpOnly, SameSite=Lax, Secure in HTTPS)
  - Session timeout (30 minutes of inactivity)
  - Session ID regeneration (every 5 minutes)
  - Session fingerprinting (IP + User-Agent binding)
  - Secure session destruction

### Usage
```php
require_once __DIR__ . '/config/sessionHandler.php';
initializeSecureSession();
```

---

## Step 14: Error Handling & Information Disclosure Prevention

### Implementation
- **File:** `lms/config/errorHandler.php`
- **Features:**
  - Disables `display_errors` in production
  - Logs errors to `lms/logs/php_errors.log`
  - User-friendly error messages (no stack traces exposed)
  - Catches fatal errors gracefully
  - JSON responses for API errors

### Usage
```php
require_once __DIR__ . '/config/errorHandler.php';
initializeErrorHandler();
```

---

## Step 15: Enhanced Password Security

### Implementation
- **File:** `lms/config/passwordSecurity.php`
- **Features:**
  - Password history tracking (last 5 passwords)
  - Common password detection
  - Sequential pattern detection
  - Account lockout after failed attempts

### Database Tables Required
Run `database/migration_security_enhancements.sql` to create:
- `tbl_password_history` - Stores password change history
- `tbl_login_attempts` - Tracks failed login attempts
- `tbl_security_events` - Security event log storage

### Usage
```php
require_once __DIR__ . '/config/passwordSecurity.php';
$result = validatePasswordEnhanced($conn, $userId, $newPassword);
if (!$result['valid']) { echo $result['msg']; }
```

---

## Step 16: Security Logging & Monitoring

### Implementation
- **File:** `lms/config/securityLogger.php`
- **Features:**
  - Centralized security event logging
  - Log levels: INFO, WARNING, CRITICAL, ALERT
  - Categories: AUTH, CSRF, UPLOAD, ACCESS, INPUT, SESSION, SYSTEM, RATE_LIMIT, SQL_INJECTION, XSS
  - Daily log rotation

### Usage
```php
require_once __DIR__ . '/config/securityLogger.php';
SecurityLogger::auth('Login Success', 'User logged in', ['role' => 1]);
SecurityLogger::csrfViolation('Invalid CSRF token');
SecurityLogger::rateLimitExceeded('login', '192.168.1.1');
SecurityLogger::suspiciousInput('SQL_INJECTION', 'SQL injection attempt');
```

---

## Step 17: API Security Hardening

### Implementation
- **File:** `lms/config/apiSecurity.php`
- **Features:**
  - Enforce POST method for API endpoints
  - Validate JSON request content type
  - Request size limits
  - Required field validation
  - Security headers for API responses

### Usage
```php
require_once __DIR__ . '/config/apiSecurity.php';
enforcePostMethod();
enforceJsonRequest();
enforceRequestSizeLimit(10485760);
requireFields(['email', 'password']);
jsonResponse(200, 'Success', ['data' => $data]);
```

---

## Step 18: Input Validation Integration

### Implementation
- **File:** `lms/config/inputValidator.php` (created in Step 10)
- **Features:**
  - Email, name, phone, ID, date validation
  - File upload validation
  - XSS pattern detection

### Usage
```php
require_once __DIR__ . '/config/inputValidator.php';
$validator = new InputValidator();
if (!$validator->isValidEmail($email)) { /* handle error */ }
if ($validator->containsXss($input)) { /* block input */ }
```

---

## Step 19: Security Configuration Documentation

### PHP Configuration Recommendations (php.ini)
```ini
display_errors = Off
log_errors = On
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Lax"
session.use_strict_mode = 1
session.gc_maxlifetime = 1800
upload_max_filesize = 10M
post_max_size = 12M
expose_php = Off
```

### File Permissions
```bash
chmod 750 lms/config/
chmod 750 lms/logs/
chmod 750 lms/storage/private/
chmod 755 lms/uploads/
chmod 640 lms/config/*.php
chmod 640 lms/logs/*.log
```

### Database Maintenance
```sql
-- Clean old login attempts (older than 30 days)
DELETE FROM tbl_login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
-- Clean old security events (older than 90 days)
DELETE FROM tbl_security_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### Security Checklist
- [x] CSRF protection on all forms
- [x] Rate limiting on login and password changes
- [x] Prepared statements for all SQL queries
- [x] Input validation and sanitization
- [x] Secure session handling
- [x] Error handling without information disclosure
- [x] Password complexity requirements
- [x] Password history tracking
- [x] Security event logging
- [x] File upload validation
- [x] Security headers (CSP, X-Frame-Options, etc.)
- [x] Directory browsing disabled
- [x] Sensitive file protection
- [x] Audit logging for critical actions

---

## Incident Response

### If a security breach is suspected:
1. Review security logs: `lms/logs/security/`
2. Check audit logs in database: `tbl_audit_log`
3. Identify affected users/accounts
4. Disable compromised accounts
5. Patch vulnerability
6. Reset affected passwords
7. Notify affected users

---

**Last Updated:** 2026-05-29  
**Version:** 1.0  
**Implemented Steps:** 13-19
