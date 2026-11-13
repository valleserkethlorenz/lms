<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/config/dbconnect.php';
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/config/passwordReset.php';
require_once __DIR__ . '/config/passwordSecurity.php';

$csrfToken = generateCsrfToken();
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$message = '';
$messageType = '';
$validToken = false;
$userId = false;

if (!empty($token)) {
    $userId = validatePasswordResetToken($conn, $token);
    $validToken = ($userId !== false);
    if (!$validToken) { $message = 'This reset link is invalid, expired, or already used.'; $messageType = 'danger'; }
} else { $message = 'No reset token provided.'; $messageType = 'warning'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token) && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $userId = validatePasswordResetToken($conn, $token);
    if ($userId === false) { $message = 'This reset link is no longer valid.'; $messageType = 'danger'; $validToken = false; }
    elseif (($_POST['new_password'] ?? '') !== ($_POST['confirm_password'] ?? '')) { $message = 'Passwords do not match.'; $messageType = 'warning'; $validToken = true; }
    else {
        $v = validatePasswordEnhanced($conn, $userId, $_POST['new_password'] ?? '');
        if (!$v['valid']) { $message = $v['msg']; $messageType = 'warning'; $validToken = true; }
        elseif (completePasswordReset($conn, $token, $userId, password_hash($_POST['new_password'], PASSWORD_DEFAULT))) {
            $message = 'Password reset successful! You can now log in.'; $messageType = 'success'; $validToken = false;
        } else { $message = 'Something went wrong.'; $messageType = 'danger'; $validToken = true; }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $message = 'Invalid request.'; $messageType = 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LMS - Reset Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--p:#09173c}
body{background:#ffffff;min-height:100vh;display:flex;justify-content:center;align-items:center;font-family:system-ui,Arial}
.rc{background:#fff;border-radius:12px;max-width:440px;width:100%;box-shadow:0 10px 30px rgba(17,23,60,.12);overflow:hidden}
.rh{background:var(--p);padding:24px 20px;text-align:center}
.rh .ic{width:60px;height:60px;border-radius:50%;background:#fff;margin:0 auto;display:flex;align-items:center;justify-content:center}
.rh .ic i{font-size:28px;color:var(--p)}.rh h5{color:#fff;margin:12px 0 0;font-size:17px}
.rh p{color:rgba(255,255,255,.75);font-size:13px;margin:4px 0 0}
.rb{padding:24px 20px}
.br{background:var(--p);border:none;color:#fff;padding:10px 20px;border-radius:8px;width:100%;font-size:14px;cursor:pointer}
.br:hover{background:#0d2257;color:#fff}
.ml{font-size:13px;color:#64748b}.ml:hover{color:var(--p)}
</style>
</head>
<body>
<div class="rc"><div class="rh"><div class="ic"><i class="bi bi-shield-lock"></i></div><h5>Reset Your Password</h5><p>Choose a strong new password</p></div>
<div class="rb">
<?php if($message): ?><div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" style="font-size:13px"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if($validToken): ?>
<form method="POST" action="" id="rf">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
<input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
<div class="mb-3"><label class="form-label" style="font-size:13px;color:#334155">New Password</label>
<div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span><input type="password" class="form-control" id="np" name="new_password" required minlength="8"></div>
<small class="text-muted">8+ chars, upper/lower, number, special char</small></div>
<div class="mb-3"><label class="form-label" style="font-size:13px;color:#334155">Confirm Password</label>
<div class="input-group"><span class="input-group-text"><i class="bi bi-lock-fill"></i></span><input type="password" class="form-control" id="cp" name="confirm_password" required minlength="8"></div></div>
<button type="submit" class="btn br mb-3"><i class="bi bi-check-circle me-1"></i> Reset Password</button>
</form>
<?php elseif($messageType==='success'): ?>
<div class="text-center"><a href="login.php" class="btn br"><i class="bi bi-box-arrow-in-right me-1"></i> Go to Login</a></div>
<?php else: ?>
<div class="text-center"><a href="forgot-password.php" class="btn br"><i class="bi bi-arrow-repeat me-1"></i> Request New Link</a></div>
<?php endif; ?>
<div class="text-center mt-3"><a class="ml" href="login.php"><i class="bi bi-arrow-left me-1"></i> Back to Login</a></div>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('rf')?.addEventListener('submit',function(e){
var p=document.getElementById('np').value,c=document.getElementById('cp').value;
if(p!==c){e.preventDefault();alert('Passwords do not match.');return}
if(!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9])/.test(p)){e.preventDefault();alert('Password needs uppercase, lowercase, number, special char.')}
});
</script></body></html>
