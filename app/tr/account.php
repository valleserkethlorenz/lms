<?php
require_once __DIR__ . '/../../config/dbconnect.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/auth_guard.php';
requireRole([2]);
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();

$loggin = true;
$today  = date("M/d/Y");

// Fetch current user info
$stmt = $conn->prepare("SELECT fname, lname, mname, suffix, phone, email FROM tbl_user WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

include 'includes/header.php';
?>
<?php include 'includes/account-page-styles.php'; ?>
<body id="page-top" class="body tr-account">
    <?php include 'includes/bar.php'; ?>
    <div class="account-page">
        <div class="account-shell">
            
            <div class="account-layout">
                <aside class="account-sidebar">
                    <section class="account-identity"><div class="account-avatar"><i class="bi bi-person-badge" aria-hidden="true"></i></div><span class="account-eyebrow">Your account</span><h3><?= htmlspecialchars(trim(implode(' ', array_filter([$user['fname'], $user['mname'], $user['lname'], $user['suffix']])))) ?></h3><span class="account-role">Team representative</span><p><?= htmlspecialchars($user['email']) ?></p></section>
                    <section class="account-help"><h3><i class="bi bi-info-circle" aria-hidden="true"></i>Keeping your details current</h3><p>Use an email address and phone number where league organizers can reach you.</p><div><i class="bi bi-shield-lock" aria-hidden="true"></i><span>To update only your profile, leave both password fields blank.</span></div></section>
                </aside>
            <form id="accountForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <!-- Profile Information Section -->
                <div class="form-section dash-animate dash-animate-d1">
                    <div class="form-section-header">
                        <div class="form-section-icon profile"><i class="bi bi-person-vcard-fill"></i></div>
                        <div>
                            <span class="section-eyebrow">01 / Profile</span>
                            <h2 class="section-title">Profile Information</h2>
                            <div class="form-section-sub">Your personal details and contact info</div>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="acct-form-label" for="account_fname">First Name<sup class="text-danger">*</sup></label>
                                <div class="acct-input-group">
                                    <i class="bi bi-person acct-input-icon"></i>
                                    <input type="text" name="fname" id="account_fname" class="acct-form-control" value="<?= htmlspecialchars($user['fname']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="acct-form-label" for="account_mname">Middle Name</label>
                                <div class="acct-input-group">
                                    <i class="bi bi-person acct-input-icon"></i>
                                    <input type="text" name="mname" id="account_mname" class="acct-form-control" value="<?= htmlspecialchars($user['mname']) ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="acct-form-label" for="account_suffix">Suffix</label>
                                <input type="text" name="suffix" id="account_suffix" class="acct-form-control" value="<?= htmlspecialchars($user['suffix']) ?>" placeholder="e.g. Jr., III">
                            </div>
                            <div class="col-md-12">
                                <label class="acct-form-label" for="account_lname">Last Name<sup class="text-danger">*</sup></label>
                                <div class="acct-input-group">
                                    <i class="bi bi-person acct-input-icon"></i>
                                    <input type="text" name="lname" id="account_lname" class="acct-form-control" value="<?= htmlspecialchars($user['lname']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="acct-form-label" for="account_email">Email Address<sup class="text-danger">*</sup></label>
                                <div class="acct-input-group">
                                    <i class="bi bi-envelope acct-input-icon"></i>
                                    <input type="email" name="email" id="account_email" class="acct-form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="acct-form-label" for="account_phone">Phone Number<sup class="text-danger">*</sup></label>
                                <div class="acct-input-group">
                                    <i class="bi bi-telephone acct-input-icon"></i>
                                    <input type="text" name="phone" id="account_phone" class="acct-form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Password Section -->
                <div class="form-section dash-animate dash-animate-d2">
                    <div class="form-section-header">
                        <div class="form-section-icon password"><i class="bi bi-shield-lock-fill"></i></div>
                        <div>
                            <span class="section-eyebrow">02 / Security</span>
                            <h2 class="section-title">Change Password</h2>
                            <div class="form-section-sub">Leave both fields blank to keep your current password</div>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="acct-form-label" for="account_new_password">New Password</label>
                                <div class="acct-input-group">
                                    <i class="bi bi-lock acct-input-icon"></i>
                                    <input autocomplete="new-password" type="password" name="new_password" id="account_new_password" class="acct-form-control" placeholder="Enter new password">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="acct-form-label" for="account_confirm_password">Confirm Password</label>
                                <div class="acct-input-group">
                                    <i class="bi bi-lock-fill acct-input-icon"></i>
                                    <input autocomplete="new-password" type="password" name="confirm_password" id="account_confirm_password" class="acct-form-control" placeholder="Re-enter new password">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="account-save-bar">
                    <span><span class="text-danger">*</span> Required fields</span><button type="submit" class="acct-save-btn">
                        <i class="bi bi-check-circle"></i> Save Changes
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>

    <script>
        document.getElementById('accountForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const newPass    = this.new_password.value;
            const confirmPass = this.confirm_password.value;

            if (newPass !== confirmPass) {
                Swal.fire({ icon: 'error', title: 'Password Mismatch', text: 'New password and confirm password do not match.' });
                return;
            }

            const formData = new FormData(this);
            fetch('action/accountAction.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 200) {
                    Swal.fire({ icon: 'success', title: 'Saved!', text: data.msg, timer: 2000 })
                    .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.msg });
                }
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong.' }));
        });
    </script>
</body>
</html>
