<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../includes/storageService.php';
require_once __DIR__ . '/../../includes/profileImage.php';
require_once __DIR__ . '/../../includes/summaryStrip.php';
requireRole([3]);
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();

$msg = '';
$createError = false;
$createFormData = [];

// Generate CSRF token if not exists
if (empty($_SESSION['admin_users_csrf_token'])) {
    $_SESSION['admin_users_csrf_token'] = bin2hex(random_bytes(32));
}

// Handle create account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['createUser'])) {
    // Validate CSRF token
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['admin_users_csrf_token'], $csrf)) {
        $msg = '<div class="alert alert-danger">The form session expired. Refresh the page and try again.</div>';
    } else {
    $fname  = trim($_POST['fname'] ?? '');
    $mname  = trim($_POST['mname'] ?? '');
    $lname  = trim($_POST['lname'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $dob    = trim($_POST['date_of_birth'] ?? '') ?: null;
    $email  = trim($_POST['email'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $yearsExp = ($_POST['years_experience'] ?? '') !== '' ? intval($_POST['years_experience']) : null;
    $credentials = trim($_POST['credentials'] ?? '');
    $role   = intval($_POST['role'] ?? 0);
    $pass   = $_POST['pass'] ?? '';
    $cpass  = $_POST['confirm_pass'] ?? '';
    // Store form data for re-populating modal on error
    $createFormData = [
        'fname' => $fname, 'mname' => $mname, 'lname' => $lname, 'suffix' => $suffix,
        'gender' => $gender, 'dob' => $dob ?? '', 'email' => $email, 'phone' => $phone,
        'address' => $address, 'years_experience' => $yearsExp, 'credentials' => $credentials, 'role' => $role
    ];

    if (!in_array($role, [1, 4])) {
        $msg = '<div class="alert alert-danger">Invalid role. Admin can only create Organizer or Committee accounts.</div>';
    } elseif (!$fname || !$lname || !$email || !$phone || !$pass) {
        $msg = '<div class="alert alert-danger">All required fields must be filled.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = '<div class="alert alert-danger">Invalid email address.</div>';
    } elseif ($pass !== $cpass) {
        $msg = '<div class="alert alert-danger">Passwords do not match.</div>';
    } else {
        $pv = validatePasswordComplexity($pass);
        if (!$pv['valid']) {
            $msg = '<div class="alert alert-danger">' . htmlspecialchars($pv['msg']) . '</div>';
        } else {
        $chk = $conn->prepare("SELECT id FROM tbl_user WHERE email=?");
        $chk->bind_param('s', $email); $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $msg = '<div class="alert alert-danger">Email already exists.</div>';
        } else {
            // Handle profile image upload
            $profileImagePath = null;
            if (isset($_FILES['profile_image']) && is_array($_FILES['profile_image'])
                && (int)($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $profileImagePath = profileImageStoreUpload($_FILES['profile_image'], 0, dirname(__DIR__, 2));
                } catch (\Throwable $e) {
                    $msg = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            
            if (empty($msg)) {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $resId = '';
                $stmt2 = $conn->prepare("INSERT INTO tbl_user (res_id,fname,lname,mname,suffix,gender,date_of_birth,phone,address,years_experience,credentials,email,pass,role,stat,pass_stat,profile_image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,0,?)");
                $stmt2->bind_param('sssssssssisssis', $resId, $fname, $lname, $mname, $suffix, $gender, $dob, $phone, $address, $yearsExp, $credentials, $email, $hash, $role, $profileImagePath);
                if ($stmt2->execute()) {
                    $roleName = $role == 1 ? 'Organizer' : 'Committee';
                    auditLog($conn, 'Create Account', "Created $roleName account for $fname $lname ($email)");
                    $msg = '<div class="alert alert-success">Account created successfully.</div>';
                } else {
                    // The insert failed: remove the newly stored image so it does not orphan.
                    if ($profileImagePath !== null) {
                        profileImageDeleteStored($profileImagePath, dirname(__DIR__, 2));
                    }
                    $msg = '<div class="alert alert-danger">Error: ' . htmlspecialchars($stmt2->error) . '</div>';
                }
                $stmt2->close();
            }
        }
        $chk->close();
        }
    }
    // Mark create error so modal reopens on page load
    if ($msg && strpos($msg, 'alert-danger') !== false) {
        $createError = true;
    }
    }
}
// Handle edit account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editUser'])) {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['admin_users_csrf_token'], $csrf)) {
        $msg = '<div class="alert alert-danger">The form session expired. Refresh the page and try again.</div>';
    } else {
    $uid   = intval($_POST['uid']);
    $fname = trim($_POST['fname'] ?? '');
    $mname = trim($_POST['mname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $dob    = trim($_POST['date_of_birth'] ?? '') ?: null;
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $yearsExp = ($_POST['years_experience'] ?? '') !== '' ? intval($_POST['years_experience']) : null;
    $credentials = trim($_POST['credentials'] ?? '');
    $role  = intval($_POST['role'] ?? 0);
    $stat  = intval($_POST['stat'] ?? 1);
    if (!in_array($role, [1, 2, 4], true) || !in_array($stat, [0, 1], true)) {
        $msg = '<div class="alert alert-danger">Invalid role.</div>';
    } elseif (!$fname || !$lname || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$phone) {
        $msg = '<div class="alert alert-danger">Enter valid account details.</div>';
    } else {
        // Handle profile image upload
        $profileImagePath = null;
        $uploadImage = false;
        $previousPhoto = '';
        if (isset($_FILES['profile_image']) && is_array($_FILES['profile_image'])
            && (int)($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $oldImgStmt = $conn->prepare("SELECT profile_image FROM tbl_user WHERE id=?");
            $oldImgStmt->bind_param('i', $uid);
            $oldImgStmt->execute();
            $oldImgResult = $oldImgStmt->get_result()->fetch_assoc();
            $oldImgStmt->close();
            $previousPhoto = (string)($oldImgResult['profile_image'] ?? '');

            try {
                $profileImagePath = profileImageStoreUpload($_FILES['profile_image'], (int)$uid, dirname(__DIR__, 2));
                $uploadImage = true;
            } catch (\Throwable $e) {
                $msg = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        
        if (empty($msg)) {
            if ($uploadImage) {
                $stmt = $conn->prepare("UPDATE tbl_user SET fname=?,lname=?,mname=?,suffix=?,gender=?,date_of_birth=?,phone=?,address=?,years_experience=?,credentials=?,email=?,role=?,stat=?,profile_image=? WHERE id=? AND role!='3' AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM tbl_user WHERE email=? AND id!=?) duplicate_email)");
                $stmt->bind_param('ssssssssissiisisi', $fname, $lname, $mname, $suffix, $gender, $dob, $phone, $address, $yearsExp, $credentials, $email, $role, $stat, $profileImagePath, $uid, $email, $uid);
            } else {
                $stmt = $conn->prepare("UPDATE tbl_user SET fname=?,lname=?,mname=?,suffix=?,gender=?,date_of_birth=?,phone=?,address=?,years_experience=?,credentials=?,email=?,role=?,stat=? WHERE id=? AND role!='3' AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM tbl_user WHERE email=? AND id!=?) duplicate_email)");
                $stmt->bind_param('ssssssssissiiisi', $fname, $lname, $mname, $suffix, $gender, $dob, $phone, $address, $yearsExp, $credentials, $email, $role, $stat, $uid, $email, $uid);
            }
            if ($stmt->execute()) {
                // Remove the replaced photo once the database is updated.
                if ($uploadImage && $previousPhoto !== '' && $previousPhoto !== (string)$profileImagePath) {
                    profileImageDeleteStored($previousPhoto, dirname(__DIR__, 2));
                }
                auditLog($conn, 'Edit Account', "Updated account ID $uid: $fname $lname, role=$role, stat=$stat");
                $msg = '<div class="alert alert-success">Account updated.</div>';
            } else {
                // The update was rejected: drop the newly stored image.
                if ($uploadImage && $profileImagePath !== null) {
                    profileImageDeleteStored($profileImagePath, dirname(__DIR__, 2));
                }
            }
            $stmt->close();
        }
    }
    }
}
// Handle reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resetPassword'])) {
    // Validate CSRF token
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['admin_users_csrf_token'], $csrf)) {
        $msg = '<div class="alert alert-danger">The form session expired. Refresh the page and try again.</div>';
    } else {
    $uid     = intval($_POST['uid']);
    $newPass = $_POST['new_pass'] ?? '';
    $pv = validatePasswordComplexity($newPass);
    if (!$pv['valid']) {
        $msg = '<div class="alert alert-danger">' . htmlspecialchars($pv['msg']) . '</div>';
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE tbl_user SET pass=?, pass_stat=0 WHERE id=? AND role!='3'");
        $stmt->bind_param('si', $hash, $uid);
        if ($stmt->execute()) {
            auditLog($conn, 'Reset Password', "Reset password for user ID $uid");
            $msg = '<div class="alert alert-success">Password reset successfully.</div>';
        }
        $stmt->close();
    }
    }
}
// Handle toggle active/inactive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggleStat'])) {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['admin_users_csrf_token'], $csrf)) {
        $msg = '<div class="alert alert-danger">The form session expired. Refresh the page and try again.</div>';
    } else {
    $uid     = intval($_POST['uid']);
    $newStat = intval($_POST['new_stat']);
    $stmt = $conn->prepare("UPDATE tbl_user SET stat=? WHERE id=? AND role != '3'");
    $stmt->bind_param('ii', $newStat, $uid);
    if ($stmt->execute()) {
        $action = $newStat == 1 ? 'Activate Account' : 'Deactivate Account';
        auditLog($conn, $action, "Set stat=$newStat for user ID $uid");
        $msg = '<div class="alert alert-success">Account status updated.</div>';
    }
    $stmt->close();
    }
}

// Fetch all non-admin users
$users = [];
$res = $conn->query("SELECT id,fname,lname,mname,suffix,gender,date_of_birth,email,phone,address,years_experience,credentials,role,stat,created,profile_image FROM tbl_user WHERE role != '3' ORDER BY created DESC");
if ($res) while ($r = $res->fetch_assoc()) {
    // Resolve the stored photo once so every admin view uses the same value and
    // stale paths (missing files) fall back to initials instead of a broken image.
    $r['profile_image_src'] = profileImageWebPath($r['profile_image'] ?? '');
    $users[] = $r;
}

include 'includes/header.php';
?>
<?php include 'includes/users-styles.php'; ?>
<?php include_once __DIR__ . '/../../includes/summary-strip-styles.php'; ?>
<body id="page-top" class="body admin-users admin-accounts">
<?php include 'includes/bar.php'; ?>
<div class="container-fluid" style="padding-top:20px;">
<div class="row p-3">
<div class="card cards users-shell">
<div class="card-body">
    <?php 
    // Compute stat counts
    $countAll  = count($users);
    $countOrg  = count(array_filter($users, fn($u) => (int)$u['role'] === 1));
    $countComm = count(array_filter($users, fn($u) => (int)$u['role'] === 4));
    $countTR   = count(array_filter($users, fn($u) => (int)$u['role'] === 2));
    ?>

    

    

    <div role="status" data-lms-toast-source><?= $msg ?></div>

    <?php renderSummaryStrip([
        ['label' => 'Total accounts',       'value' => $countAll,  'note' => 'Across all roles'],
        ['label' => 'Organizers',           'value' => $countOrg,  'note' => 'League administration'],
        ['label' => 'Committee',            'value' => $countComm, 'note' => 'Match operations'],
        ['label' => 'Team representatives', 'value' => $countTR,   'note' => 'Team management'],
    ], 'Account totals'); ?>
    <div class="section-head">
        <div><span class="users-eyebrow">Directory</span><h2 class="section-title">Manage accounts</h2></div>
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="page-header-btn users-create-account-btn" data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="bi bi-person-plus-fill" aria-hidden="true"></i>Create Account
            </button>
            
        </div>
    </div>
    <!-- Filter Toolbar -->
    <div class="users-filter-bar">
        <div class="filter-group">
            <select aria-label="Filter by role" id="filterRole" class="form-select" style="width:auto;min-width:150px;">
                <option value="all">All Roles</option>
                <option value="1">Organizer</option>
                <option value="4">Committee</option>
                <option value="2">Team Representative</option>
            </select>
            <select aria-label="Filter by status" id="filterStatus" class="form-select" style="width:auto;min-width:140px;">
                <option value="all">All Status</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <div class="search-wrap">
                <i class="bi bi-search search-icon"></i>
                <input type="text" aria-label="Search accounts" id="searchInput" class="form-control" placeholder="Search by name, email, or phone...">
            </div>
        </div>
        <button type="button" class="users-clear users-clear-accounts" onclick="clearAccountFilters()"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset filters</button>
    </div>
    <div id="usersNoResults" class="users-no-results" hidden>No accounts match these filters. Try another name or reset the filters.</div>
    <?php
    $accountGroups = [
        1 => ['title' => 'Organizer Accounts', 'role' => 'Organizer', 'icon' => 'bi-person-workspace', 'headerClass' => 'role-header-organizer'],
        4 => ['title' => 'Committee Accounts', 'role' => 'Committee', 'icon' => 'bi-people-fill', 'headerClass' => 'role-header-committee'],
        2 => ['title' => 'Team Representative Accounts', 'role' => 'Team Representative', 'icon' => 'bi-person-badge-fill', 'headerClass' => 'role-header-teamrep'],
    ];
    foreach ($accountGroups as $roleId => $group):
        $roleUsers = array_values(array_filter($users, static fn($user) => (int)$user['role'] === $roleId));
    ?>
    <div class="card role-group-card" data-role="<?= $roleId ?>">
        <div class="role-table-label <?= $group['headerClass'] ?>">
            <h5><i class="bi <?= $group['icon'] ?> me-2"></i><?= $group['title'] ?></h5>
            <span class="badge"><?= count($roleUsers) ?> user<?= count($roleUsers) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table users-table mb-0">
                    <colgroup>
                        <col class="account-col-index">
                        <col class="account-col-user">
                        <col class="account-col-email">
                        <col class="account-col-phone">
                        <col class="account-col-status">
                        <col class="account-col-created">
                        <col class="account-col-actions">
                    </colgroup>
                    <thead>
                        <tr><th>#</th><th>User</th><th>Email</th><th>Phone</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$roleUsers): ?>
                        <tr><td colspan="7" class="users-empty-state">
                            <div class="empty-icon"><i class="bi <?= $group['icon'] ?>"></i></div>
                            <div class="empty-text">No <?= strtolower($group['role']) ?> accounts found</div>
                        </td></tr>
                    <?php else: foreach ($roleUsers as $i => $u):
                        $fullName = trim($u['fname'] . ' ' . ($u['mname'] ? $u['mname'] . ' ' : '') . $u['lname'] . ' ' . ($u['suffix'] ?? ''));
                        $initials = strtoupper(mb_substr($u['fname'], 0, 1) . mb_substr($u['lname'], 0, 1));
                        $isActive = $u['stat'] == 1;
                        $statBadge = $isActive
                            ? '<span class="status-badge active"><span class="status-dot"></span>Active</span>'
                            : '<span class="status-badge inactive"><span class="status-dot"></span>Inactive</span>';
                        $avatarHtml = !empty($u['profile_image_src'])
                            ? '<div class="user-avatar-sm"><img src="' . htmlspecialchars($u['profile_image_src']) . '" alt=""></div>'
                            : '<div class="user-avatar-sm"><span class="avatar-initials">' . $initials . '</span></div>';
                    ?>
                        <tr>
                            <td style="color:#94a3b8;font-size:12px;font-weight:500;"><?= $i+1 ?></td>
                            <td>
                                <div class="user-name-cell">
                                    <?= $avatarHtml ?>
                                    <div>
                                        <div class="user-name-text text-capitalize"><?= htmlspecialchars($fullName) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:13px;"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="phone-cell"><?= htmlspecialchars($u['phone']) ?></td>
                            <td><?= $statBadge ?></td>
                            <td class="date-cell"><?= date('M d, Y', strtotime($u['created'])) ?></td>
                            <td>
                                <div class="action-btn-group">
                                    <button class="action-btn view-btn" title="View profile" onclick="viewProfile(<?= $u['id'] ?>)">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                    <?php if ($u['role'] != 2): ?>
                                    <button class="action-btn edit-btn" title="Edit account" onclick='openEdit(<?= json_encode($u) ?>)'>
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($isActive): ?>
                                    <button class="action-btn toggle-off" title="Deactivate" onclick="toggleStat(<?= $u['id'] ?>,0,'<?= htmlspecialchars(addslashes($fullName)) ?>')">
                                        <i class="bi bi-toggle-on"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="action-btn toggle-on" title="Activate" onclick="toggleStat(<?= $u['id'] ?>,1,'<?= htmlspecialchars(addslashes($fullName)) ?>')">
                                        <i class="bi bi-toggle-off"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</div>
</div>
</div>



<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:#09173c;border:none;padding:20px 28px;">
                <div>
                    <h5 class="modal-title" style="color:#fff;font-weight:500;letter-spacing:0.5px;margin:0;">Create New Account</h5>
                    <small style="color:rgba(255,255,255,0.6);font-size:12px;">Fill in the details below to create a new user account</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_users_csrf_token']) ?>">
                <div class="modal-body" style="padding:24px 28px;background:#f8f9fc;max-height:calc(100vh - 250px);overflow-y:auto;">
                    <!-- Profile Image -->
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <div id="createImgPreview" style="width:96px;height:96px;border-radius:50%;overflow:hidden;background:#e9ecef;display:flex;align-items:center;justify-content:center;border:3px solid #09173c;box-shadow:0 4px 12px rgba(9,23,60,0.15);">
                                <i class="bi bi-person-fill" style="font-size:2.5rem;color:#09173c;"></i>
                            </div>
                            <label for="createProfileImg" style="position:absolute;bottom:0;right:0;width:32px;height:32px;background:#09173c;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.2);">
                                <i class="bi bi-camera-fill" style="color:#fff;font-size:14px;"></i>
                            </label>
                            <input type="file" name="profile_image" id="createProfileImg" accept="image/jpeg,image/png,image/gif" style="display:none;" onchange="previewImg(this, 'createImgPreview')">
                        </div>
                        <div style="font-size:11px;color:#6c757d;margin-top:6px;">JPG, PNG or GIF · Max 2MB</div>
                    </div>
                    <!-- Section 1: Personal Information -->
                    <div class="mb-4" style="background:#fff;border-radius:4px;padding:20px;border-top:4px solid #09173c;box-shadow:0 2px 8px rgba(9,23,60,0.08);">
                        <div class="d-flex align-items-center mb-3 pb-2" style="border-bottom:2px solid #e3e6f0;">
                            <div style="width:40px;height:40px;background:#09173c;border-radius:4px;display:flex;align-items:center;justify-content:center;margin-right:12px;">
                                <i class="bi bi-person-fill" style="color:#fff;font-size:18px;"></i>
                            </div>
                            <div>
                                <h6 style="margin:0;font-weight:500;color:#09173c;font-size:13px;">Personal Information</h6>
                                
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" style="font-size:12px;color:#495057;">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="fname" id="createFname" class="form-control" required placeholder="Juan" value="<?= htmlspecialchars($createFormData['fname'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" style="font-size:12px;color:#495057;">Middle Initials</label>
                                <input type="text" name="mname" id="createMname" class="form-control form-control-sm" placeholder="M.I." maxlength="2" value="<?= htmlspecialchars($createFormData['mname'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" style="font-size:12px;color:#495057;">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="lname" id="createLname" class="form-control" required placeholder="Dela Cruz" value="<?= htmlspecialchars($createFormData['lname'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" style="font-size:12px;color:#495057;">Suffix</label>
                                <select name="suffix" id="createSuffix" class="form-select form-select-sm">
                                    <option value="">None</option>
                                    <option value="Jr." <?= ($createFormData['suffix'] ?? '') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                                    <option value="Sr." <?= ($createFormData['suffix'] ?? '') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                                    <option value="II" <?= ($createFormData['suffix'] ?? '') === 'II' ? 'selected' : '' ?>>II</option>
                                    <option value="III" <?= ($createFormData['suffix'] ?? '') === 'III' ? 'selected' : '' ?>>III</option>
                                    <option value="IV" <?= ($createFormData['suffix'] ?? '') === 'IV' ? 'selected' : '' ?>>IV</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" style="font-size:12px;color:#495057;">Gender <span class="text-danger">*</span></label>
                                <select name="gender" id="createGender" class="form-select" required>
                                    <option value="">Select...</option>
                                    <option value="Male" <?= ($createFormData['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($createFormData['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" style="font-size:12px;color:#495057;">Date of Birth</label>
                                <input type="date" name="date_of_birth" id="createDob" class="form-control" value="<?= htmlspecialchars($createFormData['dob'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" style="font-size:12px;color:#495057;">Address</label>
                                <textarea name="address" id="createAddress" class="form-control" rows="1" placeholder="Street, Barangay, City"><?= htmlspecialchars($createFormData['address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    <!-- Section 2: Contact Information -->
                    <div class="mb-4" style="background:#fff;border-radius:4px;padding:20px;border-top:4px solid #198754;box-shadow:0 2px 8px rgba(25,135,84,0.08);">
                        <div class="d-flex align-items-center mb-3 pb-2" style="border-bottom:2px solid #e3e6f0;">
                            <div style="width:40px;height:40px;background:#198754;border-radius:4px;display:flex;align-items:center;justify-content:center;margin-right:12px;">
                                <i class="bi bi-envelope-fill" style="color:#fff;font-size:18px;"></i>
                            </div>
                            <div>
                                <h6 style="margin:0;font-weight:500;color:#198754;font-size:13px;">Contact Information</h6>
                                
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:12px;color:#495057;">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background:#f0f4ff;border-color:#dee2e6;"><i class="bi bi-envelope" style="color:#09173c;font-size:14px;"></i></span>
                                    <input type="email" name="email" id="createEmail" class="form-control" required placeholder="user@example.com" value="<?= htmlspecialchars($createFormData['email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:12px;color:#495057;">Phone Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background:#f0f4ff;border-color:#dee2e6;"><i class="bi bi-telephone" style="color:#09173c;font-size:14px;"></i></span>
                                    <input type="text" name="phone" id="createPhone" class="form-control" placeholder="09XX-XXX-XXXX" required value="<?= htmlspecialchars($createFormData['phone'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Section 3: Experience -->
                    <div class="mb-4" style="background:#fff;border-radius:4px;padding:20px;border-top:4px solid #6f42c1;box-shadow:0 2px 8px rgba(111,66,193,0.08);">
                        <div class="d-flex align-items-center mb-3 pb-2" style="border-bottom:2px solid #e3e6f0;">
                            <div style="width:40px;height:40px;background:#6f42c1;border-radius:4px;display:flex;align-items:center;justify-content:center;margin-right:12px;">
                                <i class="bi bi-award-fill" style="color:#fff;font-size:18px;"></i>
                            </div>
                            <div>
                                <h6 style="margin:0;font-weight:500;color:#6f42c1;font-size:13px;">Experience</h6>
                                
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" style="font-size:12px;color:#495057;">Credentials / Qualifications</label>
                                <textarea name="credentials" id="createCredentials" class="form-control" rows="3" placeholder="e.g. Licensed referee, Certified coach, Tournament management experience..."><?= htmlspecialchars($createFormData['credentials'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    <!-- Section 4: Account Setup -->
                    <div class="mb-2" style="background:#fff;border-radius:4px;padding:20px;border-top:4px solid #fd7e14;box-shadow:0 2px 8px rgba(253,126,20,0.08);">
                        <div class="d-flex align-items-center mb-3 pb-2" style="border-bottom:2px solid #e3e6f0;">
                            <div style="width:40px;height:40px;background:#fd7e14;border-radius:4px;display:flex;align-items:center;justify-content:center;margin-right:12px;">
                                <i class="bi bi-shield-lock-fill" style="color:#fff;font-size:18px;"></i>
                            </div>
                            <div>
                                <h6 style="margin:0;font-weight:500;color:#fd7e14;font-size:13px;">Account Setup</h6>
                                
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:12px;color:#495057;">Role <span class="text-danger">*</span></label>
                                <select name="role" id="createRole" class="form-select" required>
                                    <option value="1" <?= (int)($createFormData['role'] ?? 1) === 1 ? 'selected' : '' ?>>Organizer</option>
                                    <option value="4" <?= (int)($createFormData['role'] ?? 1) === 4 ? 'selected' : '' ?>>Committee</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:12px;color:#495057;">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background:#fff0f0;border-color:#dee2e6;"><i class="bi bi-lock-fill" style="color:#dc3545;font-size:14px;"></i></span>
                                    <input type="password" name="pass" id="createPass" class="form-control" required minlength="8" placeholder="Min. 8 characters" autocomplete="new-password">
                                    <button type="button" class="input-group-text toggle-pass-btn" data-target="createPass" style="background:#fff0f0;border-color:#dee2e6;cursor:pointer;" tabindex="-1"><i class="bi bi-eye-slash" style="color:#6c757d;font-size:14px;"></i></button>
                                </div>
                                <small class="text-muted">8+ chars: uppercase, lowercase, number, special char</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:12px;color:#495057;">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background:#fff0f0;border-color:#dee2e6;"><i class="bi bi-lock-fill" style="color:#dc3545;font-size:14px;"></i></span>
                                    <input type="password" name="confirm_pass" id="createConfirmPass" class="form-control" required minlength="8" placeholder="Re-enter password" autocomplete="new-password">
                                    <button type="button" class="input-group-text toggle-pass-btn" data-target="createConfirmPass" style="background:#fff0f0;border-color:#dee2e6;cursor:pointer;" tabindex="-1"><i class="bi bi-eye-slash" style="color:#6c757d;font-size:14px;"></i></button>
                                </div>
                                <small id="createPassMatchMsg" class="text-muted" style="display:none;"></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e3e6f0;background:#fff;padding:16px 28px;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border:1px solid #dee2e6;font-weight:600;color:#495057;">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                    <button type="submit" name="createUser" class="btn text-white" style="background:#09173c;font-weight:600;border:none;">
                        <i class="bi bi-person-plus-fill me-1"></i>Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editAccountTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content edit-account-dialog">
            <div class="modal-header">
                <div>
                    <span class="edit-eyebrow">Account directory</span><h5 class="modal-title" id="editAccountTitle">Edit account</h5>
                    <small>Update the user account details below</small>
                </div>
                <button type="button" class="btn-close btn-close-white" aria-label="Close edit account" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_users_csrf_token']) ?>">
                <input type="hidden" name="uid" id="editUid">
                <div class="modal-body">
                    <div class="edit-profile-banner">
                        <div id="editImgPreview"><i class="bi bi-person-fill" aria-hidden="true"></i></div>
                        <div class="edit-profile-copy"><span class="edit-eyebrow">Editing account</span><h6 id="editAccountName"></h6><div class="edit-photo-controls"><button type="button" class="edit-photo-button" onclick="document.getElementById('editProfileImg').click()"><i class="bi bi-camera me-1" aria-hidden="true"></i>Change photo</button><span id="editPhotoFilename" aria-live="polite">JPG, PNG or GIF ? Max 2MB</span></div><input type="file" name="profile_image" id="editProfileImg" accept="image/jpeg,image/png,image/gif" hidden onchange="previewImg(this, 'editImgPreview'); document.getElementById('editPhotoFilename').textContent = this.files.length ? this.files[0].name : 'JPG, PNG or GIF ? Max 2MB';" aria-label="Choose profile photo"></div>
                    </div>
                    <p class="edit-required-note"><span>*</span> Required fields</p>
                    <!-- Section 1: Personal Information -->
                    <div class="edit-form-section">
                        <div class="edit-section-heading">
                            <div class="edit-section-icon"><i class="bi bi-person-fill"></i>
                            </div>
                            <div>
                                <h6>Personal Information</h6>
                                <small>Name, gender, date of birth, and address</small>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="editFname">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="fname" id="editFname" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="editMname">Middle Initials</label>
                                <input type="text" name="mname" id="editMname" class="form-control form-control-sm" maxlength="2">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="editLname">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="lname" id="editLname" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="editSuffix">Suffix</label>
                                <select name="suffix" id="editSuffix" class="form-select form-select-sm">
                                    <option value="">None</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="II">II</option>
                                    <option value="III">III</option>
                                    <option value="IV">IV</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="editGender">Gender</label>
                                <select name="gender" id="editGender" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="editDob">Date of Birth</label>
                                <input type="date" name="date_of_birth" id="editDob" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="editAddress">Address</label>
                                <input type="text" name="address" id="editAddress" class="form-control">
                            </div>
                        </div>
                    </div>
                    <!-- Section 2: Contact Information -->
                    <div class="edit-form-section">
                        <div class="edit-section-heading">
                            <div class="edit-section-icon"><i class="bi bi-envelope-fill"></i>
                            </div>
                            <div>
                                <h6>Contact Information</h6>
                                <small>Email address and phone number</small>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="editEmail">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" id="editEmail" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="editPhone">Phone Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input type="text" name="phone" id="editPhone" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Section 3: Experience -->
                    <div class="edit-form-section">
                        <div class="edit-section-heading">
                            <div class="edit-section-icon"><i class="bi bi-award-fill"></i>
                            </div>
                            <div>
                                <h6>Experience</h6>
                                <small>Credentials and qualifications</small>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="editCredentials">Credentials / Qualifications</label>
                                <textarea name="credentials" id="editCredentials" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <!-- Section 4: Account Setup -->
                    <div class="edit-form-section">
                        <div class="edit-section-heading">
                            <div class="edit-section-icon"><i class="bi bi-shield-lock-fill"></i>
                            </div>
                            <div>
                                <h6>Account Setup</h6>
                                <small>Account role and access status</small>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="editRole">Role <span class="text-danger">*</span></label>
                                <select name="role" id="editRole" class="form-select" required>
                                    <option value="1">Organizer</option>
                                    <option value="2">Team Representative</option>
                                    <option value="4">Committee</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="editStat">Status</label>
                                <select name="stat" id="editStat" class="form-select">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                    <button type="submit" name="editUser" class="btn edit-save">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Profile Modal -->
<div class="modal fade" id="viewProfileModal" tabindex="-1" aria-labelledby="viewProfileTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content profile-dialog">
            <div class="modal-header">
                <div>
                    <span class="profile-eyebrow">Account directory</span>
                    <h5 class="modal-title" id="viewProfileTitle">User profile</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close profile"></button>
            </div>
            <div class="modal-body" id="viewProfileBody"></div>
            <div class="modal-footer">
                <span class="profile-footer-note"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>Account information</span>
                <button type="button" class="btn profile-close" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title"><i class="bi bi-key-fill me-2"></i>Reset Password</h5>
                    <span class="modal-subtitle">Set a new password for this user account</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_users_csrf_token']) ?>">
                <input type="hidden" name="uid" id="resetUid">
                <div class="modal-body">
                    <p>Resetting password for: <strong id="resetName"></strong></p>
                    <div class="mb-2"><label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" name="new_pass" id="resetNewPass" class="form-control" required minlength="8" autocomplete="new-password">
                            <button type="button" class="input-group-text toggle-pass-btn" data-target="resetNewPass" style="cursor:pointer;" tabindex="-1"><i class="bi bi-eye-slash" style="color:#6c757d;font-size:14px;"></i></button>
                        </div>
                    </div>
                    <small class="text-muted">8+ chars: uppercase, lowercase, number, special char</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="resetPassword" class="btn btn-danger">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// All users data for view profile (loaded from PHP)
var allUsers = <?= json_encode($users) ?>;

function openEdit(u) {
    document.getElementById('editProfileImg').value = '';
    document.getElementById('editPhotoFilename').textContent = 'JPG, PNG or GIF ? Max 2MB';
    document.getElementById('editAccountName').textContent = [u.fname, u.mname, u.lname, u.suffix].filter(Boolean).join(' ');
    document.getElementById('editUid').value = u.id;
    document.getElementById('editFname').value = u.fname || '';
    document.getElementById('editMname').value = u.mname || '';
    document.getElementById('editLname').value = u.lname || '';
    document.getElementById('editSuffix').value = u.suffix || '';
    document.getElementById('editGender').value = u.gender || '';
    document.getElementById('editDob').value = u.date_of_birth || '';
    document.getElementById('editEmail').value = u.email || '';
    document.getElementById('editPhone').value = u.phone || '';
    document.getElementById('editAddress').value = u.address || '';
    document.getElementById('editCredentials').value = u.credentials || '';
    document.getElementById('editRole').value = u.role;
    document.getElementById('editStat').value = u.stat;
    // Show current profile image if exists
    var preview = document.getElementById('editImgPreview');
    if (u.profile_image_src) {
        preview.innerHTML = '<img src="' + esc(u.profile_image_src) + '" style="width:100%;height:100%;object-fit:cover;">';
    } else {
        preview.innerHTML = '<i class="bi bi-person-fill" style="font-size:2.5rem;color:#09173c;"></i>';
    }
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function viewProfile(id) {
    const u = allUsers.find(x => x.id == id);
    if (!u) return;
    const roleLabels = {1:'Organizer', 2:'Team Representative', 4:'Committee'};
    const fullName = [u.fname, u.mname, u.lname, u.suffix].filter(Boolean).join(' ');
    const roleName = roleLabels[u.role] || 'User';
    const firstChar = value => Array.from(String(value || '').trim())[0] || '';
    const initials = (firstChar(u.fname) + firstChar(u.lname)).toUpperCase();
    const active = Number(u.stat) === 1;
    const isBlank = value => value === null || value === undefined || String(value).trim() === '';
    const longDate = value => {
        if (isBlank(value)) return '';
        const date = new Date(String(value).replace(' ', 'T'));
        return isNaN(date.getTime()) ? '' : date.toLocaleDateString('en-US', {year:'numeric', month:'long', day:'numeric'});
    };
    const text = value => isBlank(value) ? '<span class="profile-empty">Not provided</span>' : esc(String(value));
    const experience = () => {
        if (isBlank(u.years_experience)) return text('');
        const years = Number(u.years_experience);
        if (years <= 0) return 'No prior experience';
        return years + (years === 1 ? ' year' : ' years');
    };
    const row = (label, content) => `<div class="profile-row"><dt>${esc(label)}</dt><dd>${content}</dd></div>`;
    const avatar = u.profile_image_src
        ? `<img src="${esc(u.profile_image_src)}" alt="${esc(fullName)} profile photo">`
        : `<span>${esc(initials || '?')}</span>`;
    const emailValue = isBlank(u.email)
        ? text('')
        : `<a class="profile-link" href="mailto:${esc(u.email)}">${esc(u.email)}</a>`;
    const phoneValue = isBlank(u.phone)
        ? text('')
        : `<a class="profile-link" href="tel:${esc(String(u.phone).replace(/[^+0-9]/g, ''))}">${esc(u.phone)}</a>`;

    document.querySelector('#viewProfileModal .profile-dialog').setAttribute('data-role', Number(u.role));
    document.getElementById('viewProfileBody').innerHTML = `
        <div class="profile-sheet">
            <aside class="profile-side" aria-label="Account summary">
                <span class="profile-side-label">Account overview</span>
                <div class="profile-avatar">${avatar}</div>
                <h2 class="profile-name">${esc(fullName)}</h2>
                <div class="profile-tags">
                    <span class="profile-tag profile-tag-role"><i class="bi bi-person-badge" aria-hidden="true"></i>${esc(roleName)}</span>
                    <span class="profile-tag profile-tag-status ${active ? 'is-active' : 'is-inactive'}"><span class="profile-tag-dot"></span>${active ? 'Active' : 'Inactive'}</span>
                </div>
                <dl class="profile-facts">
                    <div><dt>Account ID</dt><dd>#${esc(u.id)}</dd></div>
                    <div><dt>Created</dt><dd>${esc(longDate(u.created) || 'Not available')}</dd></div>
                </dl>
            </aside>
            <div class="profile-main">
                <section class="profile-group">
                    <h3 class="profile-group-title"><i class="bi bi-person" aria-hidden="true"></i>Personal information</h3>
                    <dl class="profile-rows">
                        ${row('Gender', text(u.gender))}
                        ${row('Date of birth', text(longDate(u.date_of_birth)))}
                        ${row('Address', text(u.address))}
                    </dl>
                </section>
                <section class="profile-group">
                    <h3 class="profile-group-title"><i class="bi bi-envelope" aria-hidden="true"></i>Contact information</h3>
                    <dl class="profile-rows">
                        ${row('Email address', emailValue)}
                        ${row('Phone number', phoneValue)}
                    </dl>
                </section>
                <section class="profile-group">
                    <h3 class="profile-group-title"><i class="bi bi-award" aria-hidden="true"></i>Experience &amp; credentials</h3>
                    <dl class="profile-rows">
                        ${row('Years of experience', experience())}
                        ${row('Credentials', text(u.credentials))}
                    </dl>
                </section>
            </div>
        </div>`;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('viewProfileModal')).show();
}

function esc(s) {
    return String(s === null || s === undefined ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function previewImg(input, previewId) {
    var preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        if (input.files[0].size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB.');
            input.value = '';
            return;
        }
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function checkPasswordStrength(password) {
    const bar = document.getElementById('passwordBar');
    const hint = document.getElementById('passwordHint');
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    const widths = ['0%', '20%', '40%', '60%', '80%', '100%'];
    const colors = ['#e5e7eb', '#ef4444', '#f59e0b', '#eab308', '#84cc16', '#22c55e'];
    const hints = ['Minimum 8 characters', 'Very weak', 'Weak', 'Fair', 'Strong', 'Very strong'];
    
    bar.style.width = widths[strength];
    bar.style.background = colors[strength];
    hint.textContent = hints[strength];
    hint.style.color = colors[strength];
}
function openReset(id,name) {
    document.getElementById('resetUid').value=id;
    document.getElementById('resetName').textContent=name;
    new bootstrap.Modal(document.getElementById('resetModal')).show();
}
function toggleStat(id,newStat,name) {
    const action = newStat==1 ? 'activate' : 'deactivate';
    Swal.fire({
        title: `${action.charAt(0).toUpperCase()+action.slice(1)} account?`,
        html: `<b>${name}</b> will be ${action}d.`,
        icon:'question', showCancelButton:true,
        confirmButtonColor:'#09173c', cancelButtonColor:'#6c757d',
        confirmButtonText:'Yes'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post('',{csrf_token:'<?= $_SESSION['admin_users_csrf_token'] ?>',toggleStat:true,uid:id,new_stat:newStat},function(){location.reload()});
    });
}
// Toggle password visibility for all toggle buttons
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.toggle-pass-btn');
    if (!btn) return;
    var targetId = btn.getAttribute('data-target');
    var input = document.getElementById(targetId);
    if (!input) return;
    var icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    }
});

// Client-side password match check for create form
function checkCreatePassMatch() {
    var pass = document.getElementById('createPass').value;
    var cpass = document.getElementById('createConfirmPass').value;
    var msg = document.getElementById('createPassMatchMsg');
    if (!pass || !cpass) { msg.style.display = 'none'; return; }
    msg.style.display = 'block';
    if (pass === cpass) {
        msg.textContent = '✓ Passwords match';
        msg.style.color = '#16a34a';
    } else {
        msg.textContent = '✗ Passwords do not match';
        msg.style.color = '#dc3545';
    }
}

// Initialize visibility on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for filters
    document.getElementById('filterRole').addEventListener('change', applyFilters);
    document.getElementById('filterStatus').addEventListener('change', applyFilters);
    document.getElementById('searchInput').addEventListener('input', applyFilters);

    // Password match live check
    var cp = document.getElementById('createPass');
    var ccp = document.getElementById('createConfirmPass');
    if (cp) cp.addEventListener('input', checkCreatePassMatch);
    if (ccp) ccp.addEventListener('input', checkCreatePassMatch);

    // Client-side create form validation before submit
    var createForm = document.querySelector('#createModal form');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            var pass = document.getElementById('createPass').value;
            var cpass = document.getElementById('createConfirmPass').value;
            if (pass !== cpass) {
                e.preventDefault();
                var msg = document.getElementById('createPassMatchMsg');
                msg.style.display = 'block';
                msg.textContent = '✗ Passwords do not match';
                msg.style.color = '#dc3545';
                document.getElementById('createConfirmPass').focus();
                return false;
            }
        });
    }

    <?php if ($createError): ?>
    // Auto-reopen create modal on error
    var createModal = new bootstrap.Modal(document.getElementById('createModal'));
    createModal.show();
    <?php endif; ?>
});

function applyFilters() {
    const roleFilter = document.getElementById('filterRole').value;
    const statusFilter = document.getElementById('filterStatus').value;
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    // Get all table sections
    const tableSections = document.querySelectorAll('.card.role-group-card');
    
    let totalVisible = 0;
    tableSections.forEach(section => {
        const rows = section.querySelectorAll('tbody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            // Skip "No users found" row
            if (row.querySelector('td[colspan]')) return;
            
            const cells = row.querySelectorAll('td');
            if (cells.length < 7) return;
            
            const nameEl = cells[1].querySelector('.user-name-text');
            const emailSub = cells[1].querySelector('.user-email-sub');
            const name = nameEl ? nameEl.textContent.toLowerCase() : '';
            const orgName = emailSub ? emailSub.textContent.toLowerCase() : '';
            const email = cells[2].textContent.toLowerCase();
            const phone = cells[3].textContent.toLowerCase();
            const statusBadge = cells[4].querySelector('.status-badge');
            const status = statusBadge ? statusBadge.textContent.trim().toLowerCase() : '';
            
            // Determine role from parent section header
            const sectionHeader = section.querySelector('.role-table-label');
            let rowRole = '';
            if (sectionHeader) {
                if (sectionHeader.classList.contains('role-header-organizer')) rowRole = '1';
                else if (sectionHeader.classList.contains('role-header-committee')) rowRole = '4';
                else if (sectionHeader.classList.contains('role-header-teamrep')) rowRole = '2';
            }
            
            // Apply filters
            const roleMatch = roleFilter === 'all' || rowRole === roleFilter;
            const statusMatch = statusFilter === 'all' || 
                               (statusFilter === '1' && status === 'active') ||
                               (statusFilter === '0' && status === 'inactive');
            const searchMatch = !searchTerm || 
                               name.includes(searchTerm) || 
                               email.includes(searchTerm) || 
                               phone.includes(searchTerm) ||
                               orgName.includes(searchTerm);
            
            if (roleMatch && statusMatch && searchMatch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        totalVisible += visibleCount;
        const header = section.querySelector('.role-table-label');
        const sectionRole = header.classList.contains('role-header-organizer') ? '1' : header.classList.contains('role-header-committee') ? '4' : '2';
        const filtering = searchTerm.trim() !== '' || statusFilter !== 'all';
        section.style.display = ((roleFilter !== 'all' && roleFilter !== sectionRole) || (filtering && visibleCount === 0)) ? 'none' : '';
        const badge = header.querySelector('.badge');
        if (badge) badge.textContent = visibleCount + ' user' + (visibleCount !== 1 ? 's' : '');
    });
    document.getElementById('usersResultCount').textContent = totalVisible + ' account' + (totalVisible !== 1 ? 's' : '');
    document.getElementById('usersNoResults').hidden = totalVisible !== 0;
}
function clearAccountFilters() {
    document.getElementById('filterRole').value = 'all';
    document.getElementById('filterStatus').value = 'all';
    document.getElementById('searchInput').value = '';
    applyFilters();
}
</script>
<?php include 'includes/footer.php'; ?>
</body></html>
