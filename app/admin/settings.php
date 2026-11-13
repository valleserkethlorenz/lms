<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../config/rateLimiter.php';
require_once __DIR__ . '/../../config/passwordSecurity.php';
require_once __DIR__ . '/../../config/securityLogger.php';
requireRole([3]);
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();
$msg = '';

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changePassword'])) {
    // Rate limit password change attempts: 5 attempts per 15 minutes per user
    $pwdLimiter = new RateLimiter('admin_password_change', 5, 900);
    $userKey = 'user_' . $userId;
    if (!$pwdLimiter->check($userKey)) {
        $retryAfter = $pwdLimiter->retryAfter($userKey);
        $minutes = ceil($retryAfter / 60);
        $msg = '<div class="alert alert-danger">Too many password change attempts. Please try again in ' . $minutes . ' minute(s).</div>';
    } else {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (!hash_equals($_SESSION['admin_csrf_token'], $csrf)) {
        SecurityLogger::csrfViolation('Invalid CSRF token on admin password change');
        $msg = '<div class="alert alert-danger">The form session expired. Refresh the page and try again.</div>';
    } elseif ($newPassword !== $confirmPassword) {
        $msg = '<div class="alert alert-danger">The new passwords do not match.</div>';
    } else {
        // Use enhanced password validation (complexity + common passwords + history)
        $pwdCheck = validatePasswordEnhanced($conn, $userId, $newPassword);
        if (!$pwdCheck['valid']) {
            $msg = '<div class="alert alert-danger">' . htmlspecialchars($pwdCheck['msg']) . '</div>';
        } else {
        $stmt = $conn->prepare("SELECT pass FROM tbl_user WHERE id=? AND role='3' AND stat='1' LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$account || !password_verify($currentPassword, $account['pass'])) {
            $pwdLimiter->record($userKey);
            SecurityLogger::auth('Password Change Failed', "Wrong current password for admin user ID $userId");
            $remaining = $pwdLimiter->remainingAttempts($userKey);
            $msg = '<div class="alert alert-danger">The current password is incorrect.';
            if ($remaining <= 3) {
                $msg .= ' (' . $remaining . ' attempt(s) remaining)';
            }
            $msg .= '</div>';
        } elseif (password_verify($newPassword, $account['pass'])) {
            $msg = '<div class="alert alert-danger">The new password must be different from the current password.</div>';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE tbl_user SET pass=?, pass_stat=1 WHERE id=? AND role='3'");
            $stmt->bind_param('si', $newHash, $userId);
            if ($stmt->execute() && $stmt->affected_rows === 1) {
                // Record password in history
                recordPasswordChange($conn, $userId, $newHash);
                $pwdLimiter->clear($userKey);
                auditLog($conn, 'Change Own Password', 'Admin changed their account password.');
                SecurityLogger::auth('Password Changed', "Admin user ID $userId changed their password");
                $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
                $msg = '<div class="alert alert-success">Password changed successfully.</div>';
            } else {
                $msg = '<div class="alert alert-danger">Unable to change the password.</div>';
            }
            $stmt->close();
        }
        }
    }
    }
}

include 'includes/header.php';
?>
<?php include 'includes/users-styles.php'; ?>
<?php include 'includes/settings-styles.php'; ?>
<body id="page-top" class="body admin-users admin-settings">
<?php include 'includes/bar.php'; ?>
<div class="container-fluid" style="padding-top:20px;">
    <div class="row p-3"><div class="col-12">
        
        <div role="status" data-lms-toast-source><?= $msg ?></div>
        <div class="section-head">
            <div><span class="users-eyebrow">Account</span><h2 class="section-title">Password &amp; security</h2></div>
            <span class="section-meta">Administrator account</span>
        </div>
        <div class="settings-layout">
            <aside class="settings-sidebar">
                <section class="settings-identity"><div class="settings-avatar"><i class="bi bi-person-badge" aria-hidden="true"></i></div><span class="users-eyebrow">Signed in as</span><h3><?= htmlspecialchars($full) ?></h3><span class="settings-role"><i class="bi bi-shield-check" aria-hidden="true"></i>Administrator</span></section>
                <section class="settings-guide"><h3>Password checklist</h3><p>Use a unique password for this account.</p><ul class="password-checklist" id="passwordChecklist">
                    <?php foreach (['length'=>'At least 8 characters', 'upper'=>'An uppercase letter', 'lower'=>'A lowercase letter', 'number'=>'A number', 'special'=>'A special character', 'match'=>'Confirmation matches'] as $rule => $label): ?>
                    <li data-rule="<?= $rule ?>"><i class="bi bi-circle" aria-hidden="true"></i><span><?= $label ?></span><span class="visually-hidden rule-state">: Not met</span></li>
                    <?php endforeach; ?>
                    </ul><p id="passwordProgress" role="status" aria-live="polite" aria-atomic="true">Start typing your new password.</p><div class="settings-tip"><i class="bi bi-key" aria-hidden="true"></i><span>Choose a password you have not used before.</span></div></section>
            </aside>
            <section class="settings-panel" aria-labelledby="passwordHeading"><div class="settings-panel-heading"><span class="settings-section-icon"><i class="bi bi-lock" aria-hidden="true"></i></span><div><span class="users-eyebrow">Account security</span><h3 id="passwordHeading">Change password</h3><p>Enter your current password, then choose a new one.</p></div></div>
            <form method="POST" class="settings-password-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="currentPassword">Current Password <span class="settings-required">*</span></label>
                            <div class="password-field">
                                <input type="password" id="currentPassword" name="current_password" class="form-control" required autocomplete="current-password">
                                <button class="password-toggle" type="button" data-target="currentPassword" aria-label="Show current password" aria-pressed="false">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="newPassword">New Password <span class="settings-required">*</span></label>
                            <div class="password-field">
                                <input type="password" id="newPassword" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
                                <button class="password-toggle" type="button" data-target="newPassword" aria-label="Show new password" aria-pressed="false">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="confirmPassword">Confirm New Password <span class="settings-required">*</span></label>
                            <div class="password-field">
                                <input type="password" id="confirmPassword" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
                                <button class="password-toggle" type="button" data-target="confirmPassword" aria-label="Show password confirmation" aria-pressed="false">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="settings-form-footer"><span><i class="bi bi-lock me-1" aria-hidden="true"></i>Confirm your current password to save</span><button type="submit" name="changePassword" class="btn settings-save">
                            <i class="bi bi-key-fill me-1"></i>Update password
                        </button></div>
                    </form>
            </section>
        </div>
    </div></div>
</div>
<script>
document.querySelectorAll('.password-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        const input = document.getElementById(button.dataset.target);
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.setAttribute('aria-pressed', showing ? 'false' : 'true');
        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        button.querySelector('i').className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});
const newPasswordField = document.getElementById('newPassword');
const confirmPasswordField = document.getElementById('confirmPassword');
function updatePasswordChecklist() {
    const value = newPasswordField.value;
    const checks = {
        length: value.length >= 8,
        upper: /[A-Z]/.test(value),
        lower: /[a-z]/.test(value),
        number: /[0-9]/.test(value),
        special: /[^A-Za-z0-9]/.test(value),
        match: value.length > 0 && value === confirmPasswordField.value
    };
    document.querySelectorAll('#passwordChecklist [data-rule]').forEach(item => {
        const met = checks[item.dataset.rule];
        item.classList.toggle('is-met', met);
        item.querySelector('i').className = met ? 'bi bi-check-circle-fill' : 'bi bi-circle';
        item.querySelector('.rule-state').textContent = met ? ': Met' : ': Not met';
    });
    const count = Object.values(checks).filter(Boolean).length;
    document.getElementById('passwordProgress').textContent = !value && !confirmPasswordField.value
        ? 'Start typing your new password.'
        : count === 6 ? 'All checklist requirements met.' : count + ' of 6 requirements met.';
}
[newPasswordField, confirmPasswordField].forEach(input => {
    input.addEventListener('input', updatePasswordChecklist);
    input.addEventListener('change', updatePasswordChecklist);
});
window.addEventListener('pageshow', updatePasswordChecklist);
updatePasswordChecklist();
</script>
<?php include 'includes/footer.php'; ?>
</body>
</html>
