<?php
/**
 * Forgot Password Page
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/config/dbconnect.php';
require_once __DIR__ . '/config/auth_guard.php';

if (currentUserId()) {
    $role = currentRole();
    if ($role == 1) header('Location: app/organizer/dashboard.php');
    elseif ($role == 2) header('Location: app/tr/dashboard.php');
    elseif ($role == 3) header('Location: app/admin/dashboard.php');
    elseif ($role == 4) header('Location: app/committee/dashboard.php');
    exit;
}

$csrfToken = generateCsrfToken();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'danger';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'warning';
        } else {
            require_once __DIR__ . '/config/passwordReset.php';
            if (!canRequestPasswordReset($conn, $email)) {
                $message = 'Too many reset requests. Please try again later.';
                $messageType = 'warning';
            } else {
                $stmt = $conn->prepare("SELECT id, fname FROM tbl_user WHERE email = ? AND stat = '1'");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($user) {
                    $token = generatePasswordResetToken($conn, (int)$user['id'], getClientIp(), $_SERVER['HTTP_USER_AGENT'] ?? '');
                    if ($token) sendPasswordResetEmail($conn, $email, $token, htmlspecialchars($user['fname']));
                }
                $message = 'If an account with that email exists, a reset link has been sent. Check your inbox and spam folder.';
                $messageType = 'success';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - Forgot Password</title>
    <link href="assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--primary:#09173c}
        body{background:#ffffff;min-height:100vh;display:flex;justify-content:center;align-items:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial}
        .rc{background:#fff;border-radius:12px;max-width:440px;width:100%;box-shadow:0 10px 30px rgba(17,23,60,.12);overflow:hidden}
        .rc-h{background:var(--primary);padding:24px 20px;text-align:center}
        .rc-h .ic{width:60px;height:60px;border-radius:50%;background:#fff;margin:0 auto;display:flex;align-items:center;justify-content:center}
        .rc-h .ic i{font-size:28px;color:var(--primary)}
        .rc-h h5{color:#fff;margin:12px 0 0;font-size:17px}
        .rc-h p{color:rgba(255,255,255,.75);font-size:13px;margin:4px 0 0}
        .rc-b{padding:24px 20px}
        .btn-r{background:var(--primary);border:none;color:#fff;padding:10px 20px;border-radius:8px;width:100%;font-size:14px;cursor:pointer}
        .btn-r:hover{background:#0d2257;color:#fff}
        .ml{font-size:13px;color:#64748b}.ml:hover{color:var(--primary)}
    </style>
</head>
<body>
<div class="rc">
    <div class="rc-h">
        <div class="ic"><i class="bi bi-key"></i></div>
        <h5>Forgot Your Password?</h5>
        <p>Enter your email and we'll send you a reset link</p>
    </div>
    <div class="rc-b">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" style="font-size:13px">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <div class="mb-3">
                <label class="form-label" for="email" style="font-size:13px;color:#334155">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="Enter your registered email" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-r mb-3"><i class="bi bi-send me-1"></i> Send Reset Link</button>
        </form>
        <div class="text-center"><a class="ml" href="login.php"><i class="bi bi-arrow-left me-1"></i> Back to Login</a></div>
    </div>
</div>
<script src="assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
