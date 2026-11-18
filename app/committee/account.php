<?php
require_once __DIR__ . '/../../config/dbconnect.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../includes/profileImage.php';
requireRole([4]);
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();

// Fetch current user info
$stmt = $conn->prepare("SELECT fname, lname, mname, suffix, phone, email, profile_image FROM tbl_user WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$profileImageStored = trim((string)($user['profile_image'] ?? ''));
$profileImageSrc = profileImageWebPath($profileImageStored);
$profileImageMissing = $profileImageStored !== '' && $profileImageSrc === '' && !profileImageIsRemote($profileImageStored);

include 'includes/header.php';
?>
<?php include 'includes/account-page-styles.php'; ?>
<body id="page-top" class="body committee-account-page">
    <?php include 'includes/bar.php'; ?>
    <div class="account-page">
        <div class="account-shell">

            <div class="account-layout">
                <aside class="account-sidebar">
                    <section class="account-identity">
                        <div class="account-avatar<?= $profileImageSrc !== '' ? ' has-image' : '' ?>">
                            <?php if ($profileImageSrc !== ''): ?>
                                <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="Your profile photo">
                            <?php else: ?>
                                <i class="bi bi-person-badge" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <span class="account-eyebrow">Your account</span>
                        <h3><?= htmlspecialchars(trim(implode(' ', array_filter([$user['fname'], $user['mname'], $user['lname'], $user['suffix']])))) ?></h3>
                        <span class="account-role">Committee</span>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                    </section>
                    <section class="account-help">
                        <h3><i class="bi bi-info-circle" aria-hidden="true"></i>Keeping your details current</h3>
                        <p>Use an email address and phone number the league organizers can reach you on.</p>
                        <div><i class="bi bi-shield-lock" aria-hidden="true"></i><span>To update only your profile, leave both password fields blank.</span></div>
                    </section>
                </aside>
                <form id="committeeAccountForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <!-- Profile Information Section -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <div class="form-section-icon profile"><i class="bi bi-person-vcard-fill"></i></div>
                            <div>
                                <span class="section-eyebrow">01 / Profile</span>
                                <h2 class="section-title">Profile Information</h2>
                                <div class="form-section-sub">Your personal details and contact info</div>
                            </div>
                        </div>
                        <div class="form-section-body">
                            <div class="acct-photo-field">
                                <div class="acct-photo-preview<?= $profileImageSrc !== '' ? ' has-image' : '' ?>" id="accountPhotoPreview">
                                    <?php if ($profileImageSrc !== ''): ?>
                                        <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="Current profile photo">
                                    <?php else: ?>
                                        <i class="bi bi-person-fill" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="acct-photo-copy">
                                    <span class="acct-form-label">Profile Photo</span>
                                    <p class="acct-photo-help<?= $profileImageMissing ? ' is-warning' : '' ?>" id="accountPhotoNote"><?= $profileImageMissing ? 'Your saved photo is no longer available. Upload a new one.' : 'JPG, PNG or GIF. Maximum 2 MB.' ?></p>
                                    <div class="acct-photo-actions">
                                        <button type="button" class="acct-photo-btn" id="accountPhotoChoose">
                                            <i class="bi bi-camera" aria-hidden="true"></i> Change Photo
                                        </button>
                                        <button type="button" class="acct-photo-remove<?= $profileImageSrc !== '' ? '' : ' d-none' ?>" id="accountPhotoRemove">
                                            <i class="bi bi-trash3" aria-hidden="true"></i> Remove
                                        </button>
                                    </div>
                                    <input type="file" name="profile_image" id="account_profile_image" accept="image/jpeg,image/png,image/gif" hidden>
                                    <input type="hidden" name="remove_profile_image" id="account_remove_profile_image" value="0">
                                </div>
                            </div>
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
                    <div class="form-section">
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
    </div>
    </div>
    <?php include 'includes/footer.php'; ?>

    <script>
        document.getElementById('committeeAccountForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const newPass     = this.new_password.value;
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
    <script>
        (function () {
            const photoInput = document.getElementById('account_profile_image');
            const removeInput = document.getElementById('account_remove_profile_image');
            const preview = document.getElementById('accountPhotoPreview');
            const chooseButton = document.getElementById('accountPhotoChoose');
            const removeButton = document.getElementById('accountPhotoRemove');
            const note = document.getElementById('accountPhotoNote');
            if (!photoInput || !preview || !chooseButton || !removeButton) return;

            const fallbackIcon = '<i class="bi bi-person-fill" aria-hidden="true"></i>';
            let objectUrl = '';

            chooseButton.addEventListener('click', function () {
                photoInput.click();
            });

            photoInput.addEventListener('change', function () {
                if (!this.files || !this.files.length) return;
                const file = this.files[0];

                if (['image/jpeg', 'image/png', 'image/gif'].indexOf(file.type) === -1) {
                    Swal.fire({ icon: 'error', title: 'Unsupported photo', text: 'Choose a JPG, PNG, or GIF image.' });
                    this.value = '';
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({ icon: 'error', title: 'Photo too large', text: 'Choose an image smaller than 2 MB.' });
                    this.value = '';
                    return;
                }

                if (objectUrl) URL.revokeObjectURL(objectUrl);
                objectUrl = URL.createObjectURL(file);
                preview.classList.add('has-image');
                preview.innerHTML = '<img src="' + objectUrl + '" alt="Selected profile photo">';
                removeInput.value = '0';
                removeButton.classList.remove('d-none');
                note.textContent = file.name;
            });

            removeButton.addEventListener('click', function () {
                photoInput.value = '';
                removeInput.value = '1';
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = '';
                }
                preview.classList.remove('has-image');
                preview.innerHTML = fallbackIcon;
                removeButton.classList.add('d-none');
                note.textContent = 'Your current photo will be removed when you save.';
            });
        })();
    </script>
</body>
</html>

