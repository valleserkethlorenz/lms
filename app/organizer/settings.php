<?php
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../config/passwordSecurity.php';
require_once __DIR__ . '/../../config/securityLogger.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();
requireRole([1]);
requireActiveAccount($conn);
$activeTab = $_GET['tab'] ?? 'accounts';
if (!in_array($activeTab, ['accounts'], true)) $activeTab = 'accounts';

// Handle account update
$msg = '';

// Generate CSRF token if not exists
if (empty($_SESSION['organizer_settings_csrf_token'])) {
    $_SESSION['organizer_settings_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['organizer_settings_csrf_token'], $csrf)) {
        SecurityLogger::csrfViolation('Invalid CSRF token on organizer settings');
        $msg = '<div class="alert alert-danger">The form session expired. Refresh the page and try again.</div>';
    } else {
if (isset($_POST['update_account'])) {
    $newFname = trim($_POST['fname']);
    $newLname = trim($_POST['lname']);
    $newEmail = trim($_POST['email']);
    $stmt = $conn->prepare("UPDATE tbl_user SET fname=?, lname=?, email=? WHERE id=?");
    $stmt->bind_param('sssi', $newFname, $newLname, $newEmail, $userId);
    $stmt->execute(); $stmt->close();
    SecurityLogger::auth('Profile Updated', "Organizer user ID $userId updated profile information");
    $msg = '<div class="alert alert-success">Account updated successfully.</div>';
}
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    $stmt = $conn->prepare("SELECT pass FROM tbl_user WHERE id=?");
    $stmt->bind_param('i', $userId); $stmt->execute();
    $stmt->bind_result($hash); $stmt->fetch(); $stmt->close();
    if (!password_verify($current, $hash)) {
        SecurityLogger::auth('Password Change Failed', "Wrong current password for organizer user ID $userId");
        $msg = '<div class="alert alert-danger">Current password is incorrect.</div>';
    } elseif ($new !== $confirm) {
        $msg = '<div class="alert alert-danger">New passwords do not match.</div>';
    } else {
        // Use enhanced password validation
        $pwdCheck = validatePasswordEnhanced($conn, $userId, $new);
        if (!$pwdCheck['valid']) {
            $msg = '<div class="alert alert-danger">' . htmlspecialchars($pwdCheck['msg']) . '</div>';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE tbl_user SET pass=? WHERE id=?");
            $stmt->bind_param('si', $newHash, $userId); $stmt->execute(); $stmt->close();
            recordPasswordChange($conn, $userId, $newHash);
            SecurityLogger::auth('Password Changed', "Organizer user ID $userId changed their password");
            $msg = '<div class="alert alert-success">Password changed successfully.</div>';
        }
    }
}
    } // end CSRF else
}

// Fetch current user info
$stmt = $conn->prepare("SELECT fname, lname, email FROM tbl_user WHERE id=?");
$stmt->bind_param('i', $userId); $stmt->execute();
$stmt->bind_result($dbFname, $dbLname, $dbEmail); $stmt->fetch(); $stmt->close();
?>
<?php include 'includes/header.php'; ?>

<body id="page-top" class="body organizer-accounts">
<?php include 'includes/bar.php'; ?>
<?php include 'includes/dashboard-styles.php'; ?>

<?php include 'includes/account-page-styles.php'; ?>

<div class="container-fluid" style="padding-top:20px;min-height:800px;">
    <div class="row p-3">
        <div class="card cards" style="background:transparent;box-shadow:none;border:none;">
            <div class="card-body p-0">

             

                <!-- Alert Messages -->
                <?php if ($msg): ?>
                    <?php
                        $alertClass = str_contains($msg, 'alert-success') ? 'settings-alert-success' : 'settings-alert-danger';
                        $alertIcon  = str_contains($msg, 'alert-success') ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
                        $alertText  = strip_tags($msg);
                    ?>
                    <div data-lms-toast-source>
                        <div class="settings-alert <?= $alertClass ?> dash-animate dash-animate-d1" role="status">
                            <i class="bi <?= $alertIcon ?>" style="font-size:18px;"></i>
                            <?= htmlspecialchars($alertText) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Accounts Tab -->
                <?php if ($activeTab === 'accounts'): ?>
                

                <div class="row g-3 dash-animate dash-animate-d3">
                    <div class="col-md-6">
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <div style="width:36px;height:36px;border-radius:3px;background:#e6efff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="bi bi-person-fill" style="font-size:1.1rem;color:#1e40af;"></i>
                                </div>
                                <div>
                                    <span class="section-eyebrow">01 / Profile</span><h2 class="section-title">Personal information</h2>
                                    <div style="font-size:11px;color:#9ca3af;margin-top:1px;">Update your personal details</div>
                                </div>
                            </div>
                            <div class="settings-card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['organizer_settings_csrf_token']) ?>">
                                    <div class="mb-3">
                                        <label class="settings-field-label" for="account-fname">First Name</label>
                                        <input type="text" name="fname" id="account-fname" autocomplete="given-name" class="settings-field-input" value="<?= htmlspecialchars((string)$dbFname) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="settings-field-label" for="account-lname">Last Name</label>
                                        <input type="text" name="lname" id="account-lname" autocomplete="family-name" class="settings-field-input" value="<?= htmlspecialchars((string)$dbLname) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="settings-field-label" for="account-email">Email Address</label>
                                        <input type="email" name="email" id="account-email" autocomplete="email" class="settings-field-input" value="<?= htmlspecialchars((string)$dbEmail) ?>" required>
                                    </div>
                                    <button type="submit" name="update_account" class="settings-save-btn">
                                        <i class="bi bi-check2-circle"></i> Save Changes
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <div style="width:36px;height:36px;border-radius:3px;background:#fff3d9;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="bi bi-shield-lock-fill" style="font-size:1.1rem;color:#92400e;"></i>
                                </div>
                                <div>
                                    <span class="section-eyebrow">02 / Security</span><h2 class="section-title">Change password</h2>
                                    <div style="font-size:11px;color:#9ca3af;margin-top:1px;">Secure your account with a strong password</div>
                                </div>
                            </div>
                            <div class="settings-card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['organizer_settings_csrf_token']) ?>">
                                    <div class="mb-3">
                                        <label class="settings-field-label" for="account-current_password">Current Password</label>
                                        <input type="password" name="current_password" id="account-current_password" autocomplete="current-password" class="settings-field-input" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="settings-field-label" for="account-new_password">New Password</label>
                                        <input type="password" name="new_password" id="account-new_password" autocomplete="new-password" class="settings-field-input" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="settings-field-label" for="account-confirm_password">Confirm New Password</label>
                                        <input type="password" name="confirm_password" id="account-confirm_password" autocomplete="new-password" class="settings-field-input" required>
                                    </div>
                                    <button type="submit" name="change_password" class="settings-pwd-btn">
                                        <i class="bi bi-shield-lock"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

    <!-- Archive Tab -->
    <?php elseif ($activeTab === 'archive'):
        $archived = $conn->prepare("SELECT id,league_name,sport,max_team,start_date,end_date,stat FROM tbl_league WHERE is_archived=1 AND created_by=? ORDER BY end_date DESC");
        $archived->bind_param('i', $userId); $archived->execute();
        $archivedResult = $archived->get_result();
        $archivedRows = [];
        while ($r = $archivedResult->fetch_assoc()) $archivedRows[] = $r;
        $archived->close();
        $totalArchived = count($archivedRows);
        $sports = array_unique(array_column($archivedRows, 'sport'));
    ?>
    <style>
        .archive-stat-card { border-radius:10px; padding:18px 22px; color:#fff; display:flex; align-items:center; gap:14px; }
        .archive-stat-card .stat-icon { font-size:2rem; opacity:.85; }
        .archive-stat-card .stat-val { font-size:1.8rem; font-weight:700; line-height:1; }
        .archive-stat-card .stat-lbl { font-size:11px; letter-spacing:1.5px; text-transform:uppercase; opacity:.85; }
        .archive-table thead th { background:#09173c; color:#fff; font-size:12px; letter-spacing:1px; text-transform:uppercase; vertical-align:middle; }
        .archive-table tbody tr:hover { background:#f0f4ff; }
        .archive-table td { vertical-align:middle; font-size:14px; }
        .badge-sport { background:#e8f0fe; color:#09173c; font-weight:600; font-size:11px; border-radius:4px; padding:3px 10px; }
        .btn-restore { background:#09173c; color:#fff; border:none; border-radius:8px; font-size:12px; font-weight:600; padding:4px 12px; }
        .btn-restore:hover { background:#1a2f6e; color:#fff; }
        .btn-del { background:#fff; color:#dc3545; border:1.5px solid #dc3545; border-radius:8px; font-size:12px; font-weight:600; padding:4px 12px; }
        .btn-del:hover { background:#dc3545; color:#fff; }
        .empty-archive { text-align:center; padding:60px 20px; color:#aaa; }
        .empty-archive i { font-size:4rem; margin-bottom:12px; display:block; }
        .archive-search { max-width:280px; }
        .archive-action-cell { display:flex; gap:6px; justify-content:center; flex-wrap:nowrap; }
    </style>

    

    <!-- Table Card -->
    <div class="card cards p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <span class="fw-bold" style="color:#09173c;"><i class="bi bi-archive me-1"></i>Archived Leagues</span>
            <div class="d-flex gap-2">
                <select id="sportFilter" class="form-select form-select-sm" style="max-width:160px;" onchange="filterSport(this.value)">
                    <option value="all">All Sports</option>
                    <?php foreach($sports as $sp): ?>
                    <option value="<?= htmlspecialchars(strtolower($sp)) ?>"><?= htmlspecialchars(ucfirst($sp)) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="archiveSearch" class="form-control form-control-sm archive-search" placeholder="&#128269; Search league..." oninput="searchArchive(this.value)">
            </div>
        </div>

        <?php if ($totalArchived > 0): ?>
        <div class="table-responsive">
            <table class="table archive-table table-bordered mb-0" id="archiveTable">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>League Name</th>
                        <th>Sport</th>
                        <th class="text-center">Teams</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width:140px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($archivedRows as $i => $row):
                    $start = date('M d, Y', strtotime($row['start_date']));
                    $end   = date('M d, Y', strtotime($row['end_date']));
                    $stat  = htmlspecialchars($row['stat'] ?? 'Archived');
                    $statColor = match(strtolower($stat)) {
                        'completed','done' => 'success',
                        'ongoing','active' => 'primary',
                        'upcoming'         => 'warning',
                        default            => 'secondary'
                    };
                ?>
                <tr data-sport="<?= htmlspecialchars(strtolower($row['sport'])) ?>" data-name="<?= htmlspecialchars(strtolower($row['league_name'])) ?>">
                    <td><?= $i+1 ?></td>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($row['league_name']) ?></div>
                    </td>
                    <td><span class="badge-sport"><?= htmlspecialchars(ucfirst($row['sport'])) ?></span></td>
                    <td class="text-center"><?= intval($row['max_team']) ?></td>
                    <td><?= $start ?></td>
                    <td><?= $end ?></td>
                    <td class="text-center"><span class="badge bg-<?= $statColor ?> px-2"><?= $stat ?></span></td>
                    <td class="text-center">
                        <div class="archive-action-cell">
                            <button class="btn-restore" onclick="restoreLeague(<?= $row['id'] ?>, '<?= htmlspecialchars($row['league_name'], ENT_QUOTES) ?>')"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-archive">
            <i class="bi bi-archive"></i>
            <div class="fw-bold mb-1" style="font-size:18px;color:#555;">No Archived Leagues</div>
            <div class="text-muted small">Leagues you archive will appear here and can be restored.</div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function filterSport(sport) {
        const q = document.getElementById('archiveSearch').value.toLowerCase();
        document.querySelectorAll('#archiveTable tbody tr').forEach(row => {
            const matchSport = (sport === 'all' || row.dataset.sport === sport);
            const matchSearch = row.dataset.name.includes(q);
            row.style.display = (matchSport && matchSearch) ? '' : 'none';
        });
    }
    function searchArchive(val) {
        const sport = document.getElementById('sportFilter').value;
        const q = val.toLowerCase();
        document.querySelectorAll('#archiveTable tbody tr').forEach(row => {
            const matchSport = (sport === 'all' || row.dataset.sport === sport);
            const matchSearch = row.dataset.name.includes(q);
            row.style.display = (matchSport && matchSearch) ? '' : 'none';
        });
    }
    function restoreLeague(id, name) {
        Swal.fire({
            title: 'Restore League?',
            html: `<b>${name}</b> will be moved back to active leagues.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#09173c',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-arrow-counterclockwise me-1"></i>Restore'
        }).then(r => {
            if (!r.isConfirmed) return;
            $.post('action/leagueAction.php', { restoreLeague: true, league_id: id }, function(res) {
                try { res = JSON.parse(res); } catch(e) { Swal.fire('Error','Invalid response','error'); return; }
                if (res.status === 201) {
                    Swal.fire({ icon:'success', title:'Restored!', text: res.msg, timer:1500, showConfirmButton:false }).then(() => location.reload());
                } else { Swal.fire('Error', res.msg, 'error'); }
            });
        });
    }
    function deleteLeague(id, name) {
        Swal.fire({
            title: 'Permanently Delete?',
            html: `<b>${name}</b> will be <span class="text-danger fw-bold">permanently deleted</span> and cannot be recovered.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-trash3 me-1"></i>Delete Permanently'
        }).then(r => {
            if (!r.isConfirmed) return;
            $.post('action/leagueAction.php', { deleteLeague: true, league_id: id }, function(res) {
                try { res = JSON.parse(res); } catch(e) { Swal.fire('Error','Invalid response','error'); return; }
                if (res.status === 201) {
                    Swal.fire({ icon:'success', title:'Deleted!', text: res.msg, timer:1500, showConfirmButton:false }).then(() => location.reload());
                } else { Swal.fire('Error', res.msg, 'error'); }
            });
        });
    }
    </script>

    <!-- Utility Tab -->
    <?php elseif ($activeTab === 'utility'): ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-tools" style="font-size:3rem; opacity:0.4;"></i>
        <p class="mt-3">Coming soon</p>
    </div>

    <!-- Audit Log Tab -->
    <?php elseif ($activeTab === 'auditlog'):
        $logResult = $conn->query("SHOW TABLES LIKE 'tbl_audit_log'");
        $logTableExists = ($logResult && $logResult->num_rows > 0);
        $logRows = [];
        if ($logTableExists) {
            $logStmt = $conn->prepare("SELECT * FROM tbl_audit_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 200");
            $logStmt->bind_param('i', $userId);
            $logStmt->execute();
            $logRes = $logStmt->get_result();
            if ($logRes) while ($l = $logRes->fetch_assoc()) $logRows[] = $l;
            $logStmt->close();
        }
        $totalLogs  = count($logRows);
        $allActions = array_unique(array_filter(array_column($logRows, 'action')));
    ?>
    <style>
        .audit-table thead th { background:#09173c; color:#fff; font-size:12px; letter-spacing:1px; text-transform:uppercase; vertical-align:middle; }
        .audit-table tbody tr:hover { background:#f0f4ff; }
        .audit-table td { vertical-align:middle; font-size:13px; }
        .audit-user-badge { display:inline-flex; align-items:center; gap:6px; background:#e8f0fe; color:#09173c; border-radius:4px; padding:3px 10px; font-size:12px; font-weight:600; }
        .audit-user-badge i { font-size:13px; }
        .audit-action-badge { border-radius:6px; padding:3px 10px; font-size:11px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; }
        .audit-action-create  { background:#d1fae5; color:#065f46; }
        .audit-action-update  { background:#dbeafe; color:#1e40af; }
        .audit-action-delete  { background:#fee2e2; color:#991b1b; }
        .audit-action-login   { background:#ede9fe; color:#5b21b6; }
        .audit-action-logout  { background:#fef3c7; color:#92400e; }
        .audit-action-restore { background:#cffafe; color:#155e75; }
        .audit-action-archive { background:#e0e7ff; color:#3730a3; }
        .audit-action-default { background:#f3f4f6; color:#374151; }
        .audit-time { font-size:12px; color:#6b7280; white-space:nowrap; }
        .audit-date-chip { display:inline-block; background:#09173c; color:#fff; border-radius:6px; font-size:11px; font-weight:600; padding:2px 8px; margin-bottom:2px; }
        .audit-empty { text-align:center; padding:60px 20px; color:#aaa; }
        .audit-empty i { font-size:3.5rem; display:block; margin-bottom:12px; }
        .audit-search { max-width:240px; }
    </style>

    <?php if (!$logTableExists): ?>
    <div class="card cards p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div style="width:48px;height:48px;border-radius:10px;background:#e0e7ff;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-journal-text" style="font-size:1.5rem;color:#3730a3;"></i>
            </div>
            <div>
                <div class="fw-bold" style="color:#09173c;font-size:16px;">Audit Log Not Set Up</div>
                <div class="text-muted small">The audit log table doesn't exist yet. Run the SQL below to enable it.</div>
            </div>
        </div>
        <pre class="bg-light rounded p-3 small mb-0" style="border-left:4px solid #09173c;">CREATE TABLE tbl_audit_log (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_name VARCHAR(100),
  action VARCHAR(100),
  details TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);</pre>
    </div>
    <?php else: ?>

    <!-- Log Table Card -->
    <div class="card cards p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <span class="fw-bold" style="color:#09173c;"><i class="bi bi-journal-text me-1"></i>My Activity Log <span class="text-muted fw-normal small">(last 200 entries)</span></span>
            <div class="d-flex gap-2">
                <select id="auditActionFilter" class="form-select form-select-sm" style="max-width:160px;" onchange="filterAudit()">
                    <option value="">All Actions</option>
                    <?php foreach($allActions as $act): ?>
                    <option value="<?= htmlspecialchars(strtolower($act)) ?>"><?= htmlspecialchars(ucfirst($act)) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="auditSearch" class="form-control form-control-sm audit-search" placeholder="&#128269; Search..." oninput="filterAudit()">
            </div>
        </div>

        <?php if ($totalLogs > 0): ?>
        <div class="table-responsive">
            <table class="table audit-table table-bordered mb-0" id="auditTable">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th style="width:150px;">Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logRows as $i => $log):
                    $action    = strtolower($log['action'] ?? '');
                    $actionKey = '';
                    foreach (['create','update','delete','login','logout','restore','archive'] as $k) {
                        if (str_contains($action, $k)) { $actionKey = $k; break; }
                    }
                    $actionClass = $actionKey ? "audit-action-{$actionKey}" : 'audit-action-default';
                    $dt = strtotime($log['created_at']);
                ?>
                <tr data-action="<?= htmlspecialchars($action) ?>" data-search="<?= htmlspecialchars(strtolower(($log['action'] ?? '').' '.($log['details'] ?? ''))) ?>">
                    <td class="text-muted"><?= $i+1 ?></td>
                    <td><span class="audit-action-badge <?= $actionClass ?>"><?= htmlspecialchars($log['action'] ?? '—') ?></span></td>
                    <td><?= htmlspecialchars($log['details'] ?? '—') ?></td>
                    <td>
                        <div class="audit-date-chip"><?= date('M d, Y', $dt) ?></div>
                        <div class="audit-time"><i class="bi bi-clock me-1"></i><?= date('h:i A', $dt) ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="audit-empty">
            <i class="bi bi-journal-x"></i>
            <div class="fw-bold mb-1" style="font-size:17px;color:#555;">No Activity Yet</div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function filterAudit() {
        const action = document.getElementById('auditActionFilter').value.toLowerCase();
        const q = document.getElementById('auditSearch').value.toLowerCase();
        document.querySelectorAll('#auditTable tbody tr').forEach(row => {
            const matchAction = !action || row.dataset.action.includes(action);
            const matchSearch = !q || row.dataset.search.includes(q);
            row.style.display = (matchAction && matchSearch) ? '' : 'none';
        });
    }
    </script>
    <?php endif; ?>
    <?php endif; ?>

                </div><!-- /.card-body -->
        </div>
        <!-- /.card -->
    </div>
    <!-- /.row -->
</div>
<!-- /.container-fluid -->

</body>
<?php include 'includes/footer.php'; ?>
