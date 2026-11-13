<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
requireRole([3]);
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();

// ── Workspace counts ─────────────────────────────────────────────────────
$total_users    = (int) ($conn->query("SELECT COUNT(*) AS c FROM tbl_user WHERE role != '3'")->fetch_assoc()['c'] ?? 0);
$total_org      = (int) ($conn->query("SELECT COUNT(*) AS c FROM tbl_user WHERE role='1'")->fetch_assoc()['c'] ?? 0);
$total_comm     = (int) ($conn->query("SELECT COUNT(*) AS c FROM tbl_user WHERE role='4'")->fetch_assoc()['c'] ?? 0);
$total_tr       = (int) ($conn->query("SELECT COUNT(*) AS c FROM tbl_user WHERE role='2'")->fetch_assoc()['c'] ?? 0);
$total_leagues  = (int) ($conn->query("SELECT COUNT(*) AS c FROM tbl_league WHERE is_archived=0 OR is_archived IS NULL")->fetch_assoc()['c'] ?? 0);
$total_archived = (int) ($conn->query("SELECT COUNT(*) AS c FROM tbl_league WHERE is_archived=1")->fetch_assoc()['c'] ?? 0);

// ── Inbox: this admin's latest notifications (same source as the bell) ────
$adminNotifs = [];
$unreadCount = 0;
$notifStmt = $conn->prepare(
    "SELECT id, type, title, message, is_read, created
       FROM tbl_notification
      WHERE user_id = ?
      ORDER BY created DESC
      LIMIT 5"
);
if ($notifStmt) {
    $notifStmt->bind_param('i', $userId);
    $notifStmt->execute();
    $notifRes = $notifStmt->get_result();
    while ($notifRow = $notifRes->fetch_assoc()) $adminNotifs[] = $notifRow;
    $notifStmt->close();
}
$countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_notification WHERE user_id = ? AND is_read = 0");
if ($countStmt) {
    $countStmt->bind_param('i', $userId);
    $countStmt->execute();
    $unreadCount = (int) ($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $countStmt->close();
}

// ── Recent accounts (latest 5, non-admin) ────────────────────────────────
$recentUsers = [];
$ures = $conn->query("SELECT id, fname, lname, role, created FROM tbl_user WHERE role != '3' ORDER BY created DESC LIMIT 5");
if ($ures) {
    while ($urow = $ures->fetch_assoc()) $recentUsers[] = $urow;
}

// ── Audit trail (latest 10, team-rep noise filtered out) ─────────────────
$logRows = [];
$logCheck = $conn->query("SHOW TABLES LIKE 'tbl_audit_log'");
$hasLogTable = ($logCheck && $logCheck->num_rows > 0);
if ($hasLogTable) {
    $logs = $conn->query("SELECT * FROM tbl_audit_log ORDER BY created_at DESC LIMIT 10");
    if ($logs) {
        while ($l = $logs->fetch_assoc()) {
            if (strtolower($l['user_role'] ?? '') === 'team representative') continue;
            $logRows[] = $l;
        }
    }
}

// ── Presentation helpers ─────────────────────────────────────────────────
// Icon + accent tone per notification type (mirrors the bell popover).
$notifIcons = [
    'team_registration'        => ['bi-people-fill', 'team'],
    'team_cancelled'           => ['bi-person-x-fill', 'team'],
    'team_approved'            => ['bi-check-circle-fill', 'team'],
    'team_rejected'            => ['bi-x-circle-fill', 'team'],
    'team_correction'          => ['bi-pencil-square', 'team'],
    'team_correction_reminder' => ['bi-alarm', 'team'],
    'team_correction_expired'  => ['bi-hourglass-bottom', 'team'],
    'schedule_updated'         => ['bi-calendar-check-fill', 'schedule'],
    'schedule_update'          => ['bi-calendar-check-fill', 'schedule'],
    'upcoming_match'           => ['bi-stopwatch-fill', 'schedule'],
    'committee_assignment'     => ['bi-person-badge-fill', 'schedule'],
    'match_result'             => ['bi-trophy-fill', 'schedule'],
    'round_complete'           => ['bi-diagram-3-fill', 'schedule'],
    'group_stage_complete'     => ['bi-diagram-3-fill', 'schedule'],
    'tournament_complete'      => ['bi-trophy-fill', 'schedule'],
    'registration_closing'     => ['bi-clock-fill', 'announcement'],
    'announcement'             => ['bi-megaphone-fill', 'announcement'],
];

// Relative timestamp — same thresholds as the bell popover.
$relTime = function ($created): string {
    $ts = strtotime((string) $created);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 0) return date('M j', $ts);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800) return 'Yesterday';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j', $ts);
};

// Role → badge class + label, for both numeric ids and audit-log role strings.
$roleBadge = function ($role): array {
    if (is_numeric($role)) {
        switch ((int) $role) {
            case 1: return ['is-organizer', 'Organizer'];
            case 2: return ['is-tr', 'Team rep'];
            case 4: return ['is-committee', 'Committee'];
            case 3: return ['is-default', 'Admin'];
        }
    }
    $text = strtolower(trim((string) $role));
    if ($text === '') return ['is-default', 'User'];
    if (strpos($text, 'organizer') !== false) return ['is-organizer', 'Organizer'];
    if (strpos($text, 'committee') !== false) return ['is-committee', 'Committee'];
    if (strpos($text, 'representative') !== false) return ['is-tr', 'Team rep'];
    if (strpos($text, 'admin') !== false) return ['is-default', 'Admin'];
    return ['is-default', ucfirst($text)];
};

// Greeting based on time of day
$hour = (int)date('G');
if ($hour < 12) $greeting = 'Good Morning';
elseif ($hour < 17) $greeting = 'Good Afternoon';
else $greeting = 'Good Evening';
$todayDate = date('l, F j, Y');
$todayTs   = time();

include 'includes/header.php';
?>
<body id="page-top" class="body">
<?php include 'includes/bar.php'; ?>
<?php include 'includes/dashboard-styles.php'; ?>
<?php include 'includes/dashboard-home-styles.php'; ?>

<div class="container-fluid" style="padding-top:20px;">
    <div class="row p-3">
        <div class="ad-home w-100">

            <!-- ── Hero deck ─────────────────────────────────────── -->
            <?php
            $heroEyebrow  = 'Administration / Workspace overview';
            $heroTitle    = htmlspecialchars($greeting) . ', <span>' . htmlspecialchars(explode(' ', trim((string) $full))[0] ?: 'Admin') . '</span>.';
            $heroSubtitle = 'Accounts, leagues and the audit trail across the whole workspace.';
            $heroIcon     = 'bi-speedometer2';
            $heroLabel    = 'Admin overview';
            $heroSide     = '<div class="ad-datecard" aria-hidden="true">'
                . '<span class="ad-date-dow">' . date('D', $todayTs) . '</span>'
                . '<span class="ad-date-day">' . date('j', $todayTs) . '</span>'
                . '<span class="ad-date-my">' . date('M Y', $todayTs) . '</span>'
                . '</div>'
                . '<div class="ad-clock">'
                . '<span class="ad-clock-label"><span class="ad-live-dot" aria-hidden="true"></span>Local time</span>'
                . '<span class="ad-clock-time" id="clockTime">--:--:--</span>'
                . '</div>';
            include 'includes/page-hero.php';
            ?>

            <!-- ── Metric strip ──────────────────────────────────── -->
            <div class="section-head dash-animate dash-animate-d1">
                <div>
                    <span class="users-eyebrow">Overview</span>
                    <h2 class="section-title">System statistics</h2>
                </div>
                <span class="section-meta"><span class="section-live-dot" aria-hidden="true"></span>Live counts</span>
            </div>
            <div class="ad-board dash-animate dash-animate-d1">
                <?php
                $cards = [
                    ['icon' => 'bi-people-fill',            'label' => 'Total users',    'val' => $total_users,    'note' => 'Non-admin accounts', 'accent' => '#09173c', 'href' => 'users.php'],
                    ['icon' => 'bi-person-badge-fill',      'label' => 'Organizers',     'val' => $total_org,      'note' => 'League owners',      'accent' => '#2f6fed', 'href' => 'users.php'],
                    ['icon' => 'bi-clipboard2-check-fill',  'label' => 'Committees',     'val' => $total_comm,     'note' => 'Match officials',    'accent' => '#137a4e', 'href' => 'users.php'],
                    ['icon' => 'bi-person-lines-fill',      'label' => 'Team reps',      'val' => $total_tr,       'note' => 'Team contacts',      'accent' => '#b26a12', 'href' => 'users.php'],
                    ['icon' => 'bi-trophy-fill',            'label' => 'Active leagues', 'val' => $total_leagues,  'note' => 'Not archived',       'accent' => '#7c3aed', 'href' => null],
                    ['icon' => 'bi-archive-fill',           'label' => 'Archived',       'val' => $total_archived, 'note' => 'Past seasons',       'accent' => '#64708a', 'href' => 'archive.php'],
                ];
                foreach ($cards as $index => $card): ?>
                    <?php if ($card['href']): ?>
                    <a class="ad-metric" href="<?= htmlspecialchars($card['href']) ?>" style="--ad-accent:<?= $card['accent'] ?>">
                    <?php else: ?>
                    <div class="ad-metric" style="--ad-accent:<?= $card['accent'] ?>">
                    <?php endif; ?>
                        <span class="ad-metric-index" aria-hidden="true"><?= sprintf('%02d', $index + 1) ?></span>
                        <span class="ad-metric-label"><i class="bi <?= $card['icon'] ?>" aria-hidden="true"></i><?= htmlspecialchars($card['label']) ?></span>
                        <span class="ad-metric-value" data-count="<?= (int) $card['val'] ?>"><?= (int) $card['val'] ?></span>
                        <span class="ad-metric-note"><?= htmlspecialchars($card['note']) ?></span>
                        <?php if ($card['href']): ?><i class="bi bi-arrow-up-right ad-metric-go" aria-hidden="true"></i><?php endif; ?>
                    <?php if ($card['href']): ?></a><?php else: ?></div><?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- ── Inbox + accounts | audit trail ────────────────── -->
            <div class="ad-grid">
                <div class="ad-col">

                    <section class="ad-panel dash-animate dash-animate-d3" aria-labelledby="adAccountsTitle">
                        <div class="ad-panel-head">
                            <div>
                                <span class="ad-panel-eyebrow">Accounts</span>
                                <h2 class="ad-panel-title" id="adAccountsTitle">New sign-ups</h2>
                            </div>
                            <a class="ad-panel-link is-accent" href="users.php">Manage <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                        </div>
                        <?php if (!empty($recentUsers)): ?>
                        <div class="ad-user-list">
                            <?php foreach ($recentUsers as $u):
                                [$badgeClass, $badgeLabel] = $roleBadge($u['role'] ?? '');
                                $uName = trim(($u['fname'] ?? '') . ' ' . ($u['lname'] ?? ''));
                                if ($uName === '') $uName = 'Unnamed account';
                            ?>
                            <div class="ad-user">
                                <span class="ad-user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($uName, 0, 1))) ?></span>
                                <span class="ad-user-copy">
                                    <span class="ad-user-name"><?= htmlspecialchars($uName) ?></span>
                                    <span class="ad-user-meta"><?= htmlspecialchars($relTime($u['created'] ?? '')) ?></span>
                                </span>
                                <span class="ad-badge <?= $badgeClass ?>"><?= htmlspecialchars($badgeLabel) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="ad-empty">
                            <i class="bi bi-people" aria-hidden="true"></i>
                            <span class="ad-empty-title">No accounts yet</span>
                            <span class="ad-empty-sub">Organizers, committees and team reps will appear here.</span>
                        </div>
                        <?php endif; ?>
                    </section>

                </div>

                <div class="ad-col">
                    <section class="ad-panel dash-animate dash-animate-d4" aria-labelledby="adAuditTitle">
                        <div class="ad-panel-head">
                            <div>
                                <span class="ad-panel-eyebrow">Audit trail</span>
                                <h2 class="ad-panel-title" id="adAuditTitle">Latest activity</h2>
                            </div>
                            <a class="ad-panel-link is-accent" href="audit_log.php">Full log <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                        </div>
                        <?php if (!empty($logRows)): ?>
                        <div class="ad-table-wrap">
                            <table class="ad-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Action</th>
                                        <th>When</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($logRows as $l):
                                    [$logBadge, $logRoleLabel] = $roleBadge($l['user_role'] ?? '');
                                    $logUser = trim((string) ($l['user_name'] ?? '')) ?: 'System';
                                ?>
                                    <tr>
                                        <td>
                                            <span class="ad-cell-user">
                                                <span class="ad-user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($logUser, 0, 1))) ?></span>
                                                <span><?= htmlspecialchars($logUser) ?></span>
                                            </span>
                                        </td>
                                        <td><span class="ad-badge <?= $logBadge ?>"><?= htmlspecialchars($logRoleLabel) ?></span></td>
                                        <td class="ad-cell-action" title="<?= htmlspecialchars((string) ($l['action'] ?? '')) ?>"><?= htmlspecialchars((string) ($l['action'] ?? '—')) ?></td>
                                        <td class="ad-cell-date"><?= date('M d, Y g:i A', strtotime((string) $l['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="ad-empty">
                            <i class="bi bi-journal-text" aria-hidden="true"></i>
                            <span class="ad-empty-title">No activity recorded</span>
                            <span class="ad-empty-sub">Audit entries appear here as users work in the system.</span>
                        </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Live clock ──────────────────────────────────────────────────────
    function updateClock() {
        var el = document.getElementById('clockTime');
        if (!el) return;
        var n = new Date(), h = n.getHours(),
            m = String(n.getMinutes()).padStart(2, '0'),
            s = String(n.getSeconds()).padStart(2, '0'),
            ap = h >= 12 ? 'PM' : 'AM';
        el.textContent = (h % 12 || 12) + ':' + m + ':' + s + ' ' + ap;
    }
    updateClock();
    setInterval(updateClock, 1000);

    // ── Animated count-up on the metric strip ───────────────────────────
    document.querySelectorAll('.ad-metric-value[data-count]').forEach(function (el) {
        var t = parseInt(el.getAttribute('data-count'), 10);
        if (isNaN(t) || t === 0) return;
        var d = 1200, st = performance.now();
        el.textContent = '0';
        function step(ts) {
            var p = Math.min((ts - st) / d, 1), e = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(t * e);
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    });

    // ── Inbox: mark notifications read through the shared endpoint ──────
    var actionUrl = '../../includes/notificationAction.php';
    var csrfToken = <?= json_encode(csrfToken()) ?>;
    var pill = document.querySelector('.ad-notif-tools .ad-panel-count');
    var markAllBtn = document.getElementById('notifMarkAll');

    function post(payload) {
        payload.csrf_token = csrfToken;
        return fetch(actionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams(payload).toString(),
            credentials: 'same-origin',
            keepalive: true
        }).then(function (res) { return res.json(); }).catch(function () { return null; });
    }

    function stepPill() {
        if (!pill) return;
        var left = parseInt(pill.textContent, 10);
        if (isNaN(left)) return;
        left = Math.max(0, left - 1);
        pill.textContent = left > 0 ? (left > 99 ? '99+' : String(left)) + ' unread' : 'All caught up';
    }

    document.querySelectorAll('.ad-notif-mark').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.ad-notif');
            var id = btn.getAttribute('data-mark-read');
            btn.remove();
            if (row) row.classList.remove('is-unread');
            stepPill();
            if (id) post({ action: 'mark_read', notification_id: id });
        });
    });

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            markAllBtn.disabled = true;
            post({ action: 'mark_all_read' }).then(function (res) {
                if (!res || res.status !== 200) {
                    markAllBtn.disabled = false;
                    return;
                }
                document.querySelectorAll('.ad-notif.is-unread').forEach(function (row) {
                    row.classList.remove('is-unread');
                });
                document.querySelectorAll('.ad-notif-mark').forEach(function (btn) { btn.remove(); });
                if (pill) pill.textContent = 'All caught up';
                markAllBtn.remove();
            });
        });
    }
});
</script>
<?php include 'includes/footer.php'; ?>
</body>
</html>

