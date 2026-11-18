+<?php
/**
 * Organizer · Notifications
 *
 * Full inbox behind the "See all" link of the dashboard notification panel.
 * Lists every notification for the signed-in organizer newest first, supports
 * an All / Unread filter and pagination, and reuses the shared notification
 * endpoint for the read / mark-all-read writes.
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../includes/notificationHelper.php';
requireRole([1]);
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();

// ── Filter + pagination ──────────────────────────────────────────────────
$filter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));
if (!in_array($filter, ['all', 'unread'], true)) {
    $filter = 'all';
}
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$totalCount  = 0;
$unreadTotal = 0;
$countStmt = $conn->prepare(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread
       FROM tbl_notification
      WHERE user_id = ?"
);
if ($countStmt) {
    $countStmt->bind_param('i', $userId);
    $countStmt->execute();
    $counts = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();
    $totalCount  = (int) ($counts['total'] ?? 0);
    $unreadTotal = (int) ($counts['unread'] ?? 0);
}

$visibleTotal = $filter === 'unread' ? $unreadTotal : $totalCount;
$totalPages   = max(1, (int) ceil($visibleTotal / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// ── Rows for the current page ────────────────────────────────────────────
$notifications = [];
$sql = "SELECT id, type, title, message, related_id, is_read, created
          FROM tbl_notification
         WHERE user_id = ?" . ($filter === 'unread' ? " AND is_read = 0" : '') . "
         ORDER BY created DESC, id DESC
         LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('iii', $userId, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
}

// Icon + accent tone per type (mirrors the dashboard panel and the bell).
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
    'league_full'              => ['bi-people-fill', 'team'],
];

// Short label shown next to the timestamp.
$notifCategory = static function (string $type): string {
    if (strpos($type, 'team') === 0) {
        return 'Team';
    }
    if (strpos($type, 'league') === 0) {
        return 'League';
    }
    if ($type === 'announcement' || $type === 'registration_closing') {
        return 'Announcement';
    }
    if (preg_match('/^(schedule|upcoming|committee|match|round|group|tournament)/', $type)) {
        return 'Schedule';
    }
    return 'Update';
};

// Relative timestamp — same thresholds as relativeTime() in the bell popover.
$notifTime = static function (string $created): string {
    $ts = strtotime($created);
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

// Link builders that keep the active filter when paging.
$pageUrl = static function (int $target) use ($filter): string {
    $params = ['page' => $target];
    if ($filter !== 'all') {
        $params['filter'] = $filter;
    }
    return 'notification.php?' . http_build_query($params);
};
$filterUrl = static function (string $value): string {
    return $value === 'unread' ? 'notification.php?filter=unread' : 'notification.php';
};

include 'includes/header.php';
?>

<body id="page-top" class="body">
<?php include 'includes/bar.php'; ?>
<?php include 'includes/dashboard-styles.php'; ?>
<style>
  .nf-page { padding: 20px 24px 45px; }
  .nf-panel { overflow: hidden; }
  .nf-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 14px; flex-wrap: wrap;
    padding: 20px 24px 16px; border-bottom: 1px solid #eef1f7;
  }
  .nf-tools { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .nf-tabs { display: inline-flex; padding: 3px; border: 1px solid #e4e9f2; border-radius: 999px; background: #f4f6fb; }
  .nf-tab {
    display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;
    border-radius: 999px; font-size: 11.5px; font-weight: 600; color: #5b6577;
    text-decoration: none !important; transition: background .16s ease, color .16s ease;
  }
  .nf-tab:hover { color: #09173c; }
  .nf-tab.is-active { background: #09173c; color: #fff; }
  .nf-tab-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; padding: 1px 6px; border-radius: 999px;
    background: #e4e9f2; color: #4b5563; font-size: 9.5px; font-weight: 700;
  }
  .nf-tab.is-active .nf-tab-count { background: rgba(255,255,255,.18); color: #fff; }
  .nf-mark-all {
    display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px;
    border: 1px solid #e4e9f2; border-radius: 8px; background: #fff;
    font-family: inherit; font-size: 11.5px; font-weight: 600; color: #4b5563;
    cursor: pointer; transition: background .16s ease, border-color .16s ease, color .16s ease;
  }
  .nf-mark-all:hover:not(:disabled) { background: #09173c; border-color: #09173c; color: #fff; }
  .nf-mark-all:disabled { opacity: .55; cursor: default; }
  .nf-list { list-style: none; margin: 0; padding: 0; }
  .nf-item {
    position: relative; display: grid;
    grid-template-columns: 42px minmax(0,1fr) auto;
    gap: 14px; align-items: flex-start;
    padding: 16px 24px; border-bottom: 1px solid #eef1f7;
    transition: background .16s ease;
  }
  .nf-item:last-child { border-bottom: 0; }
  .nf-item:hover { background: #f8faff; }
  .nf-item.is-unread { background: #f5f8ff; }
  .nf-item.is-unread:hover { background: #eef4ff; }
  .nf-item.is-unread::before {
    content: ""; position: absolute; left: 0; top: 12px; bottom: 12px; width: 3px;
    border-radius: 0 3px 3px 0; background: #2f6fed;
  }
  .nf-icon { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 12px; font-size: 16px; flex: 0 0 auto; }
  .nf-icon.is-team { background: #f3e8ff; color: #7c3aed; }
  .nf-icon.is-schedule { background: #e7f8ef; color: #0f7a4e; }
  .nf-icon.is-announcement { background: #fff4e0; color: #a16207; }
  .nf-icon.is-default { background: #eef1f6; color: #5b6577; }
  .nf-body { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
  .nf-main { display: flex; flex-direction: column; gap: 3px; min-width: 0; color: inherit; text-decoration: none !important; }
  .nf-main:hover .nf-title { color: #2f6fed; }
  .nf-title { font-size: 14px; font-weight: 600; line-height: 1.35; color: #09173c; transition: color .15s ease; }
  .nf-item:not(.is-unread) .nf-title { color: #4b5563; font-weight: 500; }
  .nf-msg { font-size: 12.5px; line-height: 1.55; color: #6f7a8d; }
  .nf-meta {
    display: flex; align-items: center; gap: 7px;
    font-size: 10px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #98a1b2;
  }
  .nf-type { padding: 2px 7px; border-radius: 999px; background: #f4f6fb; color: #64708a; font-size: 9.5px; }
  .nf-dot { color: #cbd3e1; }
  .nf-actions { display: flex; align-items: center; gap: 8px; padding-top: 4px; }
  .nf-mark {
    background: none; border: none; padding: 0; margin: 0; font-family: inherit;
    font-size: 10px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
    color: #2f6fed; cursor: pointer; white-space: nowrap;
  }
  .nf-mark:hover { text-decoration: underline; }
  .nf-mark:disabled { opacity: .55; cursor: default; }
  .nf-foot {
    display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
    padding: 14px 24px; border-top: 1px solid #eef1f7; background: #fbfcfe;
  }
  .nf-summary { font-size: 11.5px; color: #75809a; }
  .nf-pager { display: inline-flex; align-items: center; gap: 6px; }
  .nf-page-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 32px; height: 32px; padding: 0 10px;
    border: 1px solid #e4e9f2; border-radius: 8px; background: #fff;
    font-size: 12px; font-weight: 600; color: #4b5563; text-decoration: none !important;
    transition: border-color .16s ease, color .16s ease, background .16s ease;
  }
  .nf-page-btn:hover { border-color: #09173c; color: #09173c; }
  .nf-page-btn.is-active { background: #09173c; border-color: #09173c; color: #fff; }
  .nf-page-btn.is-disabled { opacity: .45; pointer-events: none; }
  .nf-empty { padding: 56px 24px; }
  .nf-empty-link {
    display: inline-flex; align-items: center; gap: 7px; margin-top: 14px; padding: 9px 16px;
    border-radius: 8px; background: #09173c; color: #fff !important;
    font-size: 12.5px; font-weight: 600; transition: background .2s ease;
  }
  .nf-empty-link:hover { background: #1a3a7c; }
  @media (max-width: 767.98px) {
    .nf-page { padding: 15px; }
    .nf-head, .nf-foot { padding-left: 18px; padding-right: 18px; }
    .nf-item { grid-template-columns: 36px minmax(0,1fr); padding: 14px 18px; }
    .nf-icon { width: 36px; height: 36px; font-size: 14px; }
    .nf-actions { grid-column: 2; padding-top: 0; }
    .nf-item.is-unread::before { top: 10px; bottom: 10px; }
  }
  @media (prefers-reduced-motion: reduce) {
    .nf-item, .nf-tab, .nf-mark-all, .nf-page-btn, .nf-main .nf-title { transition: none !important; }
  }
  /* ---------- Breadcrumb (above the hero, mirrors .lp-crumbs) -------------- */
  .nf-crumbs { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; font-size: 12.5px; color: #75809a; }
  .nf-crumbs a { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px 4px 8px; border-radius: 8px; color: #09173c; font-weight: 600; text-decoration: none !important; transition: background .15s ease, color .15s ease; }
  .nf-crumbs a:hover { background: #e8edf7; color: #2f6fed; }
  .nf-crumbs a i { font-size: 12px; }
  .nf-crumbs .nf-crumb-sep { font-size: 12px; color: #94a3b8; }
  .nf-crumbs .nf-crumb-current { max-width: 52ch; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #09173c; font-weight: 600; }
</style>

<div class="container-fluid nf-page">
    <div class="row p-3">
        <div class="card cards" style="background:transparent;box-shadow:none;border:none;">
            <div class="card-body p-0">

                <!-- Breadcrumb -->
                <nav class="nf-crumbs dash-animate" aria-label="Breadcrumb">
                    <a href="dashboard.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Dashboard</a>
                    <i class="bi bi-chevron-right nf-crumb-sep" aria-hidden="true"></i>
                    <span class="nf-crumb-current" aria-current="page">Notifications</span>
                </nav>

                <!-- Page Banner -->
                <?php
                $heroEyebrow  = 'Organizer / Inbox';
                $heroTitle    = 'Notifications';
                $heroSubtitle = 'Team registrations, schedule changes and league announcements that need your attention.';
                $heroIcon     = 'bi-bell-fill';
                $heroActions  = '<a class="page-header-btn" href="dashboard.php"><i class="bi bi-arrow-left" aria-hidden="true"></i>Back to dashboard</a>';
                include 'includes/page-hero.php';
                ?>

                <section class="activity-section dash-animate dash-animate-d2 nf-panel" aria-labelledby="nfTitle">
                    <div class="nf-head">
                        <div>
                            <span class="section-eyebrow">Your inbox</span>
                            <h2 class="section-title" id="nfTitle">
                                <?= $filter === 'unread' ? 'Unread notifications' : 'All notifications' ?>
                                <span class="activity-badge-count" id="nfCount"><?= (int) $visibleTotal ?> total</span>
                            </h2>
                        </div>
                        <div class="nf-tools">
                            <div class="nf-tabs" role="group" aria-label="Filter notifications">
                                <a class="nf-tab<?= $filter === 'all' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($filterUrl('all')) ?>">
                                    All <span class="nf-tab-count"><?= (int) $totalCount ?></span>
                                </a>
                                <a class="nf-tab<?= $filter === 'unread' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($filterUrl('unread')) ?>">
                                    Unread <span class="nf-tab-count"><?= (int) $unreadTotal ?></span>
                                </a>
                            </div>
                            <?php if ($unreadTotal > 0): ?>
                            <button type="button" class="nf-mark-all" id="nfMarkAll">
                                <i class="bi bi-check2-all" aria-hidden="true"></i>Mark all read
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($notifications)): ?>
                    <ul class="nf-list" id="nfList">
                        <?php foreach ($notifications as $notifRow):
                            $notifType = (string) $notifRow['type'];
                            [$notifIconClass, $notifTone] = $notifIcons[$notifType] ?? ['bi-bell-fill', 'default'];
                            $notifHref = organizerNotificationUrl($conn, $notifType, (int) ($notifRow['related_id'] ?? 0));
                            $notifIsRead = (int) $notifRow['is_read'] === 1;
                            $notifCreated = (string) $notifRow['created'];
                            $notifFullTime = strtotime($notifCreated) ? date('M j, Y g:i A', strtotime($notifCreated)) : $notifCreated;
                        ?>
                        <li class="nf-item<?= $notifIsRead ? '' : ' is-unread' ?>" data-notif-id="<?= (int) $notifRow['id'] ?>">
                            <span class="nf-icon is-<?= htmlspecialchars($notifTone) ?>" aria-hidden="true">
                                <i class="bi <?= htmlspecialchars($notifIconClass) ?>"></i>
                            </span>
                            <div class="nf-body">
                                <a class="nf-main" href="<?= htmlspecialchars($notifHref) ?>">
                                    <span class="nf-title"><?= htmlspecialchars((string) $notifRow['title']) ?></span>
                                    <span class="nf-msg"><?= htmlspecialchars((string) $notifRow['message']) ?></span>
                                </a>
                                <span class="nf-meta">
                                    <span class="nf-type"><?= htmlspecialchars($notifCategory($notifType)) ?></span>
                                    <span class="nf-dot" aria-hidden="true">&middot;</span>
                                    <span class="nf-time" title="<?= htmlspecialchars($notifFullTime, ENT_QUOTES) ?>"><?= htmlspecialchars($notifTime($notifCreated)) ?></span>
                                </span>
                            </div>
                            <div class="nf-actions">
                                <?php if (!$notifIsRead): ?>
                                <button type="button" class="nf-mark" data-notif-id="<?= (int) $notifRow['id'] ?>">Mark read</button>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <div class="activity-empty nf-empty">
                        <i class="bi bi-bell-slash" aria-hidden="true"></i>
                        <p>
                            <?= $filter === 'unread'
                                ? 'No unread notifications.'
                                : 'No notifications yet. Registration updates, schedule changes and announcements will land here.' ?>
                        </p>
                        
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($notifications) || $totalPages > 1): ?>
                    <div class="nf-foot">
                        <span class="nf-summary">
                            Showing <?= $visibleTotal > 0 ? (int) ($offset + 1) : 0 ?>&ndash;<?= (int) min($offset + $perPage, $visibleTotal) ?> of <?= (int) $visibleTotal ?>
                        </span>
                        <?php if ($totalPages > 1):
                            $windowStart = max(1, $page - 2);
                            $windowEnd   = min($totalPages, $windowStart + 4);
                            $windowStart = max(1, $windowEnd - 4);
                        ?>
                        <nav class="nf-pager" aria-label="Notification pagination">
                            <a class="nf-page-btn<?= $page <= 1 ? ' is-disabled' : '' ?>"
                               href="<?= htmlspecialchars($pageUrl(max(1, $page - 1))) ?>"
                               <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                               aria-label="Previous page"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                            <?php if ($windowStart > 1): ?>
                            <a class="nf-page-btn" href="<?= htmlspecialchars($pageUrl(1)) ?>">1</a>
                            <?php if ($windowStart > 2): ?><span class="nf-summary" aria-hidden="true">&hellip;</span><?php endif; ?>
                            <?php endif; ?>
                            <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
                            <a class="nf-page-btn<?= $i === $page ? ' is-active' : '' ?>"
                               href="<?= htmlspecialchars($pageUrl($i)) ?>"
                               <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($windowEnd < $totalPages): ?>
                            <?php if ($windowEnd < $totalPages - 1): ?><span class="nf-summary" aria-hidden="true">&hellip;</span><?php endif; ?>
                            <a class="nf-page-btn" href="<?= htmlspecialchars($pageUrl($totalPages)) ?>"><?= $totalPages ?></a>
                            <?php endif; ?>
                            <a class="nf-page-btn<?= $page >= $totalPages ? ' is-disabled' : '' ?>"
                               href="<?= htmlspecialchars($pageUrl(min($totalPages, $page + 1))) ?>"
                               <?= $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                               aria-label="Next page"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                        </nav>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </section>

            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
(function () {
    var actionUrl = '../../includes/notificationAction.php';
    var csrfToken = <?= json_encode(csrfToken()) ?>;
    var badge = document.querySelector('.role-notification-badge');

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

    function currentBadgeCount() {
        if (!badge) return 0;
        return parseInt(badge.textContent, 10) || 0;
    }

    function updateBadge(next) {
        if (!badge) return;
        next = Math.max(0, next);
        if (next > 0) {
            badge.textContent = next > 99 ? '99+' : String(next);
            badge.style.display = '';
        } else {
            badge.textContent = '';
            badge.style.display = 'none';
        }
    }

    function applyReadState(row) {
        if (!row) return;
        row.classList.remove('is-unread');
        var btn = row.querySelector('.nf-mark');
        if (btn) btn.remove();
    }

    // "Mark read" on a single row.
    document.querySelectorAll('.nf-mark').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.nf-item');
            var id = btn.getAttribute('data-notif-id');
            btn.disabled = true;
            applyReadState(row);
            updateBadge(currentBadgeCount() - 1);
            if (id) post({ action: 'mark_read', notification_id: id });
        });
    });

    // Opening a notification is the same intent as marking it read. keepalive
    // lets the write finish while the browser follows the link.
    document.querySelectorAll('.nf-main').forEach(function (link) {
        link.addEventListener('click', function () {
            var row = link.closest('.nf-item');
            if (!row || !row.classList.contains('is-unread')) return;
            var id = row.getAttribute('data-notif-id');
            applyReadState(row);
            updateBadge(currentBadgeCount() - 1);
            if (id) post({ action: 'mark_read', notification_id: id });
        });
    });

    var markAllBtn = document.getElementById('nfMarkAll');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            markAllBtn.disabled = true;
            post({ action: 'mark_all_read' }).then(function (res) {
                if (!res || res.status !== 200) {
                    markAllBtn.disabled = false;
                    return;
                }
                document.querySelectorAll('.nf-item.is-unread').forEach(applyReadState);
                updateBadge(0);
                // Reload so the tabs, counts and list stay server-truthful.
                window.location.reload();
            });
        });
    }
})();
</script>
</body>
</html>



