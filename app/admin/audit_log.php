<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../includes/summaryStrip.php';
requireRole([3]);
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();

$logRows = [];
$allActions = [];
$allRoles   = [];
$totalRecords = 0;
$perPage = 50;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

$logCheck = $conn->query("SHOW TABLES LIKE 'tbl_audit_log'");
if ($logCheck && $logCheck->num_rows > 0) {
    // Get all unique actions and roles for filter dropdowns
    $fRes = $conn->query("SELECT DISTINCT action FROM tbl_audit_log WHERE LOWER(user_role) != 'team representative' AND action IS NOT NULL AND action != '' ORDER BY action");
    if ($fRes) while ($f = $fRes->fetch_assoc()) $allActions[] = $f['action'];

    $fRes2 = $conn->query("SELECT DISTINCT user_role FROM tbl_audit_log WHERE LOWER(user_role) != 'team representative' AND user_role IS NOT NULL AND user_role != '' ORDER BY user_role");
    if ($fRes2) while ($f = $fRes2->fetch_assoc()) $allRoles[] = $f['user_role'];

    // Count total non-team-representative rows
    $cntRes = $conn->query("SELECT COUNT(*) AS c FROM tbl_audit_log WHERE LOWER(user_role) != 'team representative'");
    $totalRecords = ($cntRes && $row = $cntRes->fetch_assoc()) ? (int)$row['c'] : 0;

    $currentPage = min($currentPage, max(1, (int)ceil($totalRecords / $perPage)));

    // Fetch current page
    $offset = ($currentPage - 1) * $perPage;
    $res = $conn->query("SELECT * FROM tbl_audit_log WHERE LOWER(user_role) != 'team representative' ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    if ($res) while ($l = $res->fetch_assoc()) $logRows[] = $l;
}
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$currentPage = min($currentPage, $totalPages);
$startEntry = ($totalRecords > 0) ? ($currentPage - 1) * $perPage + 1 : 0;
$endEntry   = min($currentPage * $perPage, $totalRecords);

/**
 * Build a pagination URL from the current query string.
 *
 * @param array<string, mixed> $params Query parameters to keep
 * @param int                  $page   Target page number
 */
function buildPageUrl(array $params, int $page): string {
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

include 'includes/header.php';
?>
<?php include 'includes/users-styles.php'; ?>
<?php include_once __DIR__ . '/../../includes/summary-strip-styles.php'; ?>
<body id="page-top" class="body admin-users admin-audit">
<?php include 'includes/bar.php'; ?>
<?php include 'includes/audit-log-styles.php'; ?>
<div class="container-fluid" style="padding-top:20px;">
<div class="row p-3">
<div class="col-12">
    
    <?php renderSummaryStrip([
        ['label' => 'Recorded activity', 'value' => number_format($totalRecords), 'note' => 'Entries in this log'],
        ['label' => 'Action types',      'value' => count($allActions),           'note' => 'Distinct recorded actions'],
        ['label' => 'User roles',        'value' => count($allRoles),             'note' => 'Represented in this log'],
    ], 'Audit log totals'); ?>
    <div class="section-head">
        <div><span class="users-eyebrow">Activity register</span><h2 class="section-title">System activity</h2></div>
        <span class="section-meta">Newest entries first</span>
    </div>
    <?php if (count($logRows) > 0): ?>
    <div class="al-filter-bar al-animate">
        <div class="row g-2 align-items-center">
            <div class="col-auto flex-grow-1" style="min-width:180px;">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="logSearch" aria-label="Search activity on this page" class="form-control" placeholder="Search this page by user, role, or action..." oninput="filterLog()">
                </div>
            </div>
            <div class="col-auto" style="min-width:150px;">
                <select id="roleFilter" aria-label="Filter by role" class="form-select" onchange="filterLog()">
                    <option value="">All Roles</option>
                    <?php foreach($allRoles as $r): ?>
                    <option value="<?= htmlspecialchars(strtolower($r)) ?>"><?= htmlspecialchars($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto" style="min-width:150px;">
                <select id="actionFilter" aria-label="Filter by action" class="form-select" onchange="filterLog()">
                    <option value="">All Actions</option>
                    <?php foreach($allActions as $a): ?>
                    <option value="<?= htmlspecialchars(strtolower($a)) ?>"><?= htmlspecialchars(ucfirst($a)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto" style="min-width:150px;">
                <input type="date" id="dateFilter" aria-label="Filter by date" class="form-control" onchange="filterLog()" title="Filter by date">
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="button" class="al-reset-btn" onclick="resetFilters()" title="Reset all filters">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset filters
                </button>
                <button type="button" class="al-export-btn" onclick="exportCSV()" title="Export to CSV">
                    <i class="bi bi-download"></i> Export CSV
                </button>
            </div>
        </div>
        <div class="d-flex align-items-center justify-content-between mt-2">
            <div class="al-results-info" id="resultsInfo" aria-live="polite">Showing <strong><?= $startEntry ?>–<?= $endEntry ?></strong> of <strong><?= number_format($totalRecords) ?></strong> entries</div>
        </div>
    </div>
    <div class="al-table-wrap al-animate">
        <div class="audit-table-heading"><span>Activity records</span><small>Filters and CSV export apply to this page of records.</small></div>
        <div class="table-responsive">
            <table class="table al-table" id="logTable">
                <thead>
                    <tr>
                        <th style="width:60px;text-align:center;">#</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logRows as $i => $log):
                    $action = strtolower($log['action'] ?? '');
                    $actionKey = '';
                    foreach (['create','update','delete','login','logout','restore','archive','reset','deactivate','activate','assign','submit','edit','add'] as $k) {
                        if (str_contains($action, $k)) { $actionKey = $k; break; }
                    }
                    $colorMap = [
                        'create'=>'#d1fae5','update'=>'#dbeafe','delete'=>'#fee2e2','login'=>'#ede9fe',
                        'logout'=>'#fef3c7','restore'=>'#cffafe','archive'=>'#e0e7ff','reset'=>'#fce7f3',
                        'activate'=>'#d1fae5','deactivate'=>'#fee2e2','assign'=>'#dbeafe','submit'=>'#d1fae5',
                        'edit'=>'#dbeafe','add'=>'#d1fae5'
                    ];
                    $textMap = [
                        'create'=>'#065f46','update'=>'#1e40af','delete'=>'#991b1b','login'=>'#5b21b6',
                        'logout'=>'#92400e','restore'=>'#155e75','archive'=>'#3730a3','reset'=>'#9d174d',
                        'activate'=>'#065f46','deactivate'=>'#991b1b','assign'=>'#1e40af','submit'=>'#065f46',
                        'edit'=>'#1e40af','add'=>'#065f46'
                    ];
                    $iconMap = [
                        'create'=>'bi-plus-circle-fill','update'=>'bi-pencil-fill','delete'=>'bi-trash-fill',
                        'login'=>'bi-box-arrow-in-right','logout'=>'bi-box-arrow-right','restore'=>'bi-arrow-counterclockwise',
                        'archive'=>'bi-archive-fill','reset'=>'bi-key-fill','activate'=>'bi-check-circle-fill',
                        'deactivate'=>'bi-x-circle-fill','assign'=>'bi-person-check-fill','submit'=>'bi-send-fill',
                        'edit'=>'bi-pencil-fill','add'=>'bi-plus-circle-fill'
                    ];
                    $bg   = $colorMap[$actionKey] ?? '#f3f4f6';
                    $tc   = $textMap[$actionKey]  ?? '#374151';
                    $icon = $iconMap[$actionKey]  ?? 'bi-circle';
                    $dt   = strtotime($log['created_at']);
                    $roleLower = strtolower($log['user_role'] ?? '');
                    if (strpos($roleLower, 'organizer') !== false) $roleClass = 'al-role-organizer';
                    elseif (strpos($roleLower, 'committee') !== false) $roleClass = 'al-role-committee';
                    elseif (strpos($roleLower, 'admin') !== false) $roleClass = 'al-role-admin';
                    else $roleClass = 'al-role-default';
                    $nameParts = preg_split('/\s+/', trim($log['user_name'] ?? '?'));
                    $initials = strtoupper(substr($nameParts[0], 0, 1)) . (isset($nameParts[1]) ? strtoupper(substr($nameParts[1], 0, 1)) : '');
                ?>
                <tr data-action="<?= htmlspecialchars($action) ?>"
                    data-role="<?= htmlspecialchars($roleLower) ?>"
                    data-date="<?= date('Y-m-d', $dt) ?>"
                    data-search="<?= htmlspecialchars(strtolower(($log['user_name']??'').' '.($log['user_role']??'').' '.($log['action']??''))) ?>">
                    <td style="text-align:center;"><span class="al-row-num"><?= $startEntry + $i ?></span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="al-user-avatar"><?= $initials ?></div>
                            <span class="al-user-name"><?= htmlspecialchars($log['user_name'] ?? '—') ?></span>
                        </div>
                    </td>
                    <td><span class="al-role-badge <?= $roleClass ?>"><?= htmlspecialchars($log['user_role'] ?? '—') ?></span></td>
                    <td>
                        <span class="al-action-badge" style="background:<?= $bg ?>;color:<?= $tc ?>;">
                            <i class="bi <?= $icon ?>" style="font-size:11px;"></i>
                            <?= htmlspecialchars($log['action'] ?? '—') ?>
                        </span>
                    </td>
                    <td>
                        <div class="al-date"><?= date('M d, Y', $dt) ?></div>
                        <div class="al-time"><i class="bi bi-clock" style="font-size:11px;"></i><?= date('h:i A', $dt) ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr id="noResultsRow" style="display:none;">
                    <td colspan="5" class="al-no-results">
                        <i class="bi bi-search"></i>
                        No matching records found. Try adjusting your filters.
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="al-pagination al-animate">
        <div class="al-pagination-info">
            Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong>
            <span class="text-muted mx-2">|</span>
            Showing <strong><?= $startEntry ?>–<?= $endEntry ?></strong> of <strong><?= number_format($totalRecords) ?></strong> entries
        </div>
        <div class="al-pagination-nav">
            <?php
            $qParams = $_GET;
            $range = 2;
            $pages = [];
            for ($p = 1; $p <= $totalPages; $p++) {
                if ($p == 1 || $p == $totalPages || ($p >= $currentPage - $range && $p <= $currentPage + $range)) {
                    $pages[] = $p;
                }
            }
            $prevShown = 0;
            ?>
            <?php if ($currentPage > 1): ?>
                <a href="<?= buildPageUrl($qParams, $currentPage - 1) ?>" class="al-page-prev"><i class="bi bi-chevron-left"></i> Prev</a>
            <?php else: ?>
                <span class="al-page-disabled al-page-prev"><i class="bi bi-chevron-left"></i> Prev</span>
            <?php endif; ?>

            <?php foreach ($pages as $p): ?>
                <?php if ($p - $prevShown > 1): ?>
                    <span class="al-page-ellipsis">…</span>
                <?php endif; ?>
                <?php if ($p == $currentPage): ?>
                    <span class="al-page-active"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= buildPageUrl($qParams, $p) ?>"><?= $p ?></a>
                <?php endif; ?>
                <?php $prevShown = $p; ?>
            <?php endforeach; ?>

            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= buildPageUrl($qParams, $currentPage + 1) ?>" class="al-page-next">Next <i class="bi bi-chevron-right"></i></a>
            <?php else: ?>
                <span class="al-page-disabled al-page-next">Next <i class="bi bi-chevron-right"></i></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="al-table-wrap al-animate">
        <div class="al-empty">
            <div class="al-empty-icon"><i class="bi bi-journal-x"></i></div>
            <div class="al-empty-title">No Activity Logged Yet</div>
            <div class="al-empty-sub">System activities and user actions will appear here once recorded.</div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
</div>
<script>
function filterLog() {
    var role   = document.getElementById('roleFilter').value.toLowerCase();
    var action = document.getElementById('actionFilter').value.toLowerCase();
    var q      = document.getElementById('logSearch').value.toLowerCase();
    var date   = document.getElementById('dateFilter').value;
    var rows   = document.querySelectorAll('#logTable tbody tr[data-search]');
    var visible = 0;
    rows.forEach(function(row) {
        var mr = !role   || row.dataset.role === role;
        var ma = !action || row.dataset.action === action;
        var mq = !q      || row.dataset.search.indexOf(q) !== -1;
        var md = !date   || row.dataset.date === date;
        var show = mr && ma && mq && md;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    var total = rows.length;
    var totalPages = <?= $totalPages ?>;
    var totalRecords = <?= $totalRecords ?>;
    var pageStart = <?= $startEntry ?>;
    var pageEnd = <?= $endEntry ?>;
    var info = 'Showing <strong>' + visible + '</strong> of <strong>' + total + '</strong> on this page';
    if (totalPages > 1) info += ' <span style="color:#d1d5db;">|</span> <strong>' + totalRecords.toLocaleString() + '</strong> total';
    document.getElementById('resultsInfo').innerHTML = info;
    var noRes = document.getElementById('noResultsRow');
    if (noRes) noRes.style.display = visible === 0 ? '' : 'none';
}
function resetFilters() {
    document.getElementById('roleFilter').value = '';
    document.getElementById('actionFilter').value = '';
    document.getElementById('logSearch').value = '';
    document.getElementById('dateFilter').value = '';
    filterLog();
}
function exportCSV() {
    var rows = document.querySelectorAll('#logTable tbody tr[data-search]');
    var csv = 'No,User,Role,Action,Date,Time\n';
    var idx = 0;
    rows.forEach(function(row) {
        if (row.style.display === 'none') return;
        idx++;
        var cells = row.querySelectorAll('td');
        var name  = cells[1] ? (cells[1].querySelector('.al-user-name') || {}).textContent || '' : '';
        var role  = cells[2] ? cells[2].textContent.trim() : '';
        var act   = cells[3] ? cells[3].textContent.trim() : '';
        var dt    = cells[4] ? (cells[4].querySelector('.al-date') || {}).textContent || '' : '';
        var tm    = cells[4] ? (cells[4].querySelector('.al-time') || {}).textContent || '' : '';
        csv += idx + ',"' + name.trim() + '","' + role + '","' + act + '","' + dt.trim() + '","' + tm.trim() + '"\n';
    });
    var blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement('a');
    a.href = url;
    a.download = 'audit_log_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>
<?php include 'includes/footer.php'; ?>
</body>
</html>