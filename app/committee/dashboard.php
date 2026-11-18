<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../includes/livestreamHelper.php';
require_once __DIR__ . '/../../includes/notificationHelper.php';
requireRole([4]);
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();

$today = date('Y-m-d');

// Daily reminders (fallback for installations without the cron script). The
// per-notification dedupe checks make this safe to overlap with
// scripts/run_daily_notifications.php.
$cmTodayKey = 'committee_daily_notif_' . date('Y-m-d');
if (empty($_SESSION[$cmTodayKey])) {
    try {
        notifyCommitteeUpcomingMatchToday($conn);
        notifyCommitteePendingResults($conn);
    } catch (Throwable $e) {
        error_log('[notification] Committee daily reminders failed: ' . $e->getMessage());
    }
    $_SESSION[$cmTodayKey] = true;
}

// ── Assignment counts ────────────────────────────────────────────────────
$upcoming = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE committee_user_id=? AND published_at IS NOT NULL AND status NOT IN ('Completed','Cancelled') AND date >= ?");
$upcoming->bind_param('is', $userId, $today); $upcoming->execute();
$upcoming_count = (int) ($upcoming->get_result()->fetch_assoc()['c'] ?? 0);
$upcoming->close();

$completed = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE committee_user_id=? AND published_at IS NOT NULL AND status='Completed'");
$completed->bind_param('i', $userId); $completed->execute();
$completed_count = (int) ($completed->get_result()->fetch_assoc()['c'] ?? 0);
$completed->close();

$pending_result = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE committee_user_id=? AND published_at IS NOT NULL AND status='Completed' AND (a_score IS NULL OR a_score=0 AND b_score=0)");
$pending_result->bind_param('i', $userId); $pending_result->execute();
$pending_result_count = (int) ($pending_result->get_result()->fetch_assoc()['c'] ?? 0);
$pending_result->close();

// Live now: matches currently streaming
$liveNow = [];
$liveStmt = $conn->prepare(
    "SELECT s.id, s.livestream_url, s.livestream_status, s.date, s.time, s.status,
            l.league_name,
            ta.team_name AS team_a_name, tb.team_name AS team_b_name
     FROM tbl_schedule s
     JOIN tbl_league l ON l.id=s.league_id
     JOIN tbl_team ta ON ta.id=s.team_a
     JOIN tbl_team tb ON tb.id=s.team_b
     WHERE s.committee_user_id=?
       AND s.published_at IS NOT NULL
       AND s.livestream_status='Live'
       AND s.livestream_url IS NOT NULL AND s.livestream_url != ''
     ORDER BY s.date, s.time"
);
$liveStmt->bind_param('i', $userId);
$liveStmt->execute();
$liveResult = $liveStmt->get_result();
while ($row = $liveResult->fetch_assoc()) $liveNow[] = $row;
$liveStmt->close();
$liveNowCount = count($liveNow);

// Published matches assigned to this committee member for the dashboard calendar.
$calendarEvents = [];
$calendarStmt = $conn->prepare(
    "SELECT s.id, s.date, s.time, s.venue, s.status, s.livestream_url, s.livestream_status,
            l.league_name,
            ta.team_name AS team_a_name, tb.team_name AS team_b_name
     FROM tbl_schedule s
     JOIN tbl_league l ON l.id=s.league_id
     JOIN tbl_team ta ON ta.id=s.team_a
     JOIN tbl_team tb ON tb.id=s.team_b
     WHERE s.committee_user_id=?
       AND s.published_at IS NOT NULL
       AND (s.is_archived=0 OR s.is_archived IS NULL)
       AND (l.is_archived=0 OR l.is_archived IS NULL)
     ORDER BY s.date, s.time"
);
$calendarStmt->bind_param('i', $userId);
$calendarStmt->execute();
$calendarResult = $calendarStmt->get_result();
while ($match = $calendarResult->fetch_assoc()) {
    $status = strtolower((string) $match['status']);
    $color = match ($status) {
        'completed' => '#64748b',
        'cancelled' => '#dc2626',
        'postponed' => '#f97316',
        default => '#2563eb',
    };
    $calendarEvents[] = [
        'id' => (string) $match['id'],
        'title' => (strtolower($match['livestream_status'] ?? '') === 'live' ? '🔴 LIVE: ' : '') . $match['team_a_name'] . ' vs ' . $match['team_b_name'],
        'start' => $match['date'] . 'T' . substr((string) $match['time'], 0, 8),
        'color' => strtolower($match['livestream_status'] ?? '') === 'live' ? '#dc2626' : $color,
        'url' => 'matches.php?id=' . (int) $match['id'],
        'extendedProps' => [
            'league' => $match['league_name'],
            'venue' => $match['venue'] ?: 'To be announced',
            'status' => $match['status'],
            'livestream_status' => $match['livestream_status'] ?? '',
            'livestream_url' => $match['livestream_url'] ?? '',
        ],
    ];
}
$calendarStmt->close();

// Next assigned fixtures for the dashboard table.
$assignedRows = [];
$assignedStmt = $conn->prepare(
    "SELECT s.id, l.league_name, ta.team_name AS team_a_name, tb.team_name AS team_b_name,
            s.date, s.time, s.venue, s.status
     FROM tbl_schedule s
     JOIN tbl_league l ON l.id = s.league_id
     JOIN tbl_team ta ON ta.id = s.team_a
     JOIN tbl_team tb ON tb.id = s.team_b
     WHERE s.committee_user_id = ? AND s.published_at IS NOT NULL AND s.status NOT IN ('Completed','Cancelled')
     ORDER BY s.date ASC, s.time ASC LIMIT 10"
);
$assignedStmt->bind_param('i', $userId); $assignedStmt->execute();
$assignedResult = $assignedStmt->get_result();
while ($row = $assignedResult->fetch_assoc()) $assignedRows[] = $row;
$assignedStmt->close();

// ── Presentation helpers ─────────────────────────────────────────────────
$hour = (int) date('G');
if ($hour < 12) $greeting = 'Good Morning';
elseif ($hour < 17) $greeting = 'Good Afternoon';
else $greeting = 'Good Evening';
$todayTs   = time();
$firstName = explode(' ', trim((string) $full))[0] ?: 'Committee';

// Schedule status → badge class + label (same pill language as the admin dashboard).
$statusBadge = function ($status): array {
    $text = trim((string) $status);
    $key  = strtolower($text);
    return match ($key) {
        'completed' => ['is-completed', 'Completed'],
        'cancelled' => ['is-cancelled', 'Cancelled'],
        'postponed' => ['is-postponed', 'Postponed'],
        'live'      => ['is-live', 'Live'],
        default     => ['is-scheduled', $text !== '' ? ucfirst($text) : 'Scheduled'],
    };
};

// ── Inbox: the three most recent notifications (read + unread).
// The full history lives on notification.php behind the panel's "See all".
$notifRows = [];
$notifStmt = $conn->prepare(
    "SELECT id, type, title, message, related_id, is_read, created
       FROM tbl_notification
      WHERE user_id = ?
      ORDER BY created DESC
      LIMIT 3"
);
if ($notifStmt) {
    $notifStmt->bind_param('i', $userId);
    $notifStmt->execute();
    $notifResult = $notifStmt->get_result();
    while ($notifRow = $notifResult->fetch_assoc()) {
        $notifRows[] = $notifRow;
    }
    $notifStmt->close();
}

// Icon + accent tone per type (mirrors the bell popover and notification.php).
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
    'result_reminder'          => ['bi-clipboard2-check-fill', 'schedule'],
    'round_complete'           => ['bi-diagram-3-fill', 'schedule'],
    'group_stage_complete'     => ['bi-diagram-3-fill', 'schedule'],
    'tournament_complete'      => ['bi-trophy-fill', 'schedule'],
    'registration_closing'     => ['bi-clock-fill', 'announcement'],
    'announcement'             => ['bi-megaphone-fill', 'announcement'],
];

// Relative timestamp — same thresholds as relativeTime() in the bell popover.
$notifTime = function (string $created): string {
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

include 'includes/header.php';
?>
<body id="page-top" class="body">
<?php include 'includes/bar.php'; ?>
<?php include 'includes/dashboard-styles.php'; ?>
<?php include 'includes/dashboard-home-styles.php'; ?>

<div class="container-fluid" style="padding-top:20px;">
    <div class="row p-3">
        <div class="cm-home w-100">

            <!-- ── Hero deck ─────────────────────────────────────── -->
            <section class="cm-deck dash-animate" aria-label="Committee overview">
                <span class="cm-court" aria-hidden="true"></span>
                <div class="cm-deck-copy">
                    <h1 class="cm-greeting"><?= htmlspecialchars($greeting) ?>, <span><?= htmlspecialchars($firstName) ?></span>.</h1>
                    <p class="cm-deck-sub">Your assigned matches, live streams and pending results in one place.</p>
                </div>
                <div class="cm-deck-side">
                    <div class="cm-datecard" aria-hidden="true">
                        <span class="cm-date-dow"><?= date('D', $todayTs) ?></span>
                        <span class="cm-date-day"><?= date('j', $todayTs) ?></span>
                        <span class="cm-date-my"><?= date('M Y', $todayTs) ?></span>
                    </div>
                    <div class="cm-clock">
                        <span class="cm-clock-label"><span class="cm-live-dot" aria-hidden="true"></span>Local time</span>
                        <span class="cm-clock-time" id="cmClockTime">--:--:--</span>
                    </div>
                </div>
            </section>

            <!-- ── Metric strip ──────────────────────────────────── -->
            <div class="cm-section-head dash-animate dash-animate-d1">
                <span class="cm-section-title">Assignment statistics</span>
                <span class="cm-live"><span class="cm-live-dot" aria-hidden="true"></span>Live counts</span>
            </div>
            <div class="cm-board dash-animate dash-animate-d1">
                <?php
                $cards = [
                    ['icon' => 'bi-calendar2-check-fill', 'label' => 'Upcoming matches', 'val' => $upcoming_count,       'note' => 'Published assignments', 'accent' => '#09173c', 'href' => 'matches.php'],
                    ['icon' => 'bi-check-circle-fill',    'label' => 'Completed',        'val' => $completed_count,      'note' => 'Finalised fixtures',    'accent' => '#137a4e', 'href' => 'matches.php'],
                    ['icon' => 'bi-hourglass-split',      'label' => 'Awaiting results', 'val' => $pending_result_count, 'note' => 'Scores to submit',      'accent' => '#b26a12', 'href' => 'matches.php'],
                    ['icon' => 'bi-broadcast',            'label' => 'Live now',         'val' => $liveNowCount,         'note' => 'Streaming matches',     'accent' => '#dc2626', 'href' => null],
                ];
                foreach ($cards as $index => $card): ?>
                    <?php if ($card['href']): ?>
                    <a class="cm-metric" href="<?= htmlspecialchars($card['href']) ?>" style="--cm-accent:<?= $card['accent'] ?>">
                    <?php else: ?>
                    <div class="cm-metric" style="--cm-accent:<?= $card['accent'] ?>">
                    <?php endif; ?>
                        <span class="cm-metric-index" aria-hidden="true"><?= sprintf('%02d', $index + 1) ?></span>
                        <span class="cm-metric-label"><i class="bi <?= $card['icon'] ?>" aria-hidden="true"></i><?= htmlspecialchars($card['label']) ?></span>
                        <span class="cm-metric-value" data-count="<?= (int) $card['val'] ?>"><?= (int) $card['val'] ?></span>
                        <span class="cm-metric-note"><?= htmlspecialchars($card['note']) ?></span>
                        <?php if ($card['href']): ?><i class="bi bi-arrow-up-right cm-metric-go" aria-hidden="true"></i><?php endif; ?>
                    <?php if ($card['href']): ?></a><?php else: ?></div><?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- ── Calendar | live now + assigned matches ─────────── -->
            <div class="cm-grid">
                <div class="cm-col">
                    <section class="cm-panel is-calendar dash-animate dash-animate-d3" aria-labelledby="cmCalendarTitle">
                        <div class="cm-panel-head">
                            <div>
                                <span class="cm-panel-eyebrow">Schedule</span>
                                <h2 class="cm-panel-title" id="cmCalendarTitle">My assigned matches</h2>
                            </div>
                            <div class="cm-legend" aria-label="Match status legend">
                                <span><i class="cm-legend-dot" style="background:#2563eb"></i>Scheduled</span>
                                <span><i class="cm-legend-dot" style="background:#f97316"></i>Postponed</span>
                                <span><i class="cm-legend-dot" style="background:#64748b"></i>Completed</span>
                                <span><i class="cm-legend-dot" style="background:#dc2626"></i>Cancelled</span>
                            </div>
                        </div>
                        <div class="cm-cal">
                            <?php if ($calendarEvents): ?>
                                <div id="committeeMatchCalendar"></div>
                            <?php else: ?>
                                <div class="cm-empty">
                                    <i class="bi bi-calendar-x" aria-hidden="true"></i>
                                    <span class="cm-empty-title">No assigned matches yet</span>
                                    <span class="cm-empty-sub">Published matches assigned to you will appear on this calendar.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="cm-col">
                    <?php if ($liveNowCount > 0): ?>
                    <section class="cm-panel dash-animate dash-animate-d2" aria-labelledby="cmLiveTitle">
                        <div class="cm-panel-head">
                            <div>
                                <span class="cm-panel-eyebrow">Streaming</span>
                                <h2 class="cm-panel-title" id="cmLiveTitle">Live now</h2>
                            </div>
                            <span class="cm-panel-count"><?= $liveNowCount ?> live</span>
                        </div>
                        <div class="cm-stream-list">
                            <?php foreach ($liveNow as $live): ?>
                            <article class="cm-stream">
                                <div class="cm-stream-top">
                                    <span class="cm-stream-flag"><span class="cm-stream-dot" aria-hidden="true"></span>Live</span>
                                    <span class="cm-stream-league"><?= htmlspecialchars($live['league_name']) ?></span>
                                </div>
                                <h3 class="cm-stream-match">
                                    <?= htmlspecialchars($live['team_a_name']) ?>
                                    <span>vs</span>
                                    <?= htmlspecialchars($live['team_b_name']) ?>
                                </h3>
                                <span class="cm-stream-meta">
                                    <i class="bi bi-clock" aria-hidden="true"></i><?= date('M d, Y • g:i A', strtotime($live['date'] . ' ' . $live['time'])) ?>
                                </span>
                                <div class="cm-stream-actions">
                                    <a class="cm-panel-link" href="matches.php?id=<?= (int) $live['id'] ?>"><i class="bi bi-gear" aria-hidden="true"></i>Manage</a>
                                    <a class="cm-panel-link is-accent" href="../../live.php?id=<?= (int) $live['id'] ?>" target="_blank" rel="noopener"><i class="bi bi-eye" aria-hidden="true"></i>Watch stream</a>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php $notifUnread = (int) ($_bar_unread ?? 0); ?>
                    <section class="cm-panel dash-animate dash-animate-d4" aria-labelledby="cmNotifTitle">
                        <div class="cm-panel-head">
                            <div>
                                <span class="cm-panel-eyebrow">Inbox</span>
                                <h2 class="cm-panel-title" id="cmNotifTitle">Notifications</h2>
                            </div>
                            <div class="cm-notif-tools">
                                <?php if ($notifUnread > 0): ?>
                                    <span class="cm-panel-count"><?= $notifUnread > 99 ? '99+' : $notifUnread ?> unread</span>
                                    <button type="button" class="cm-panel-link" id="cmNotifMarkAll">Mark all read</button>
                                <?php else: ?>
                                    <span class="cm-panel-count">All caught up</span>
                                <?php endif; ?>
                                <a class="cm-panel-link is-accent" href="notification.php">See all <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        </div>
                        <?php if (!empty($notifRows)): ?>
                        <div class="cm-notif-list">
                            <?php foreach ($notifRows as $notifRow):
                                $notifType = (string) $notifRow['type'];
                                [$notifIconClass, $notifTone] = $notifIcons[$notifType] ?? ['bi-bell-fill', 'default'];
                                $notifRowHref = committeeNotificationUrl($notifType, (int) ($notifRow['related_id'] ?? 0));
                                $notifIsRead = (int) $notifRow['is_read'] === 1;
                            ?>
                            <a class="cm-notif<?= $notifIsRead ? '' : ' is-unread' ?>" href="<?= htmlspecialchars($notifRowHref) ?>" data-notif-id="<?= (int) $notifRow['id'] ?>">
                                <span class="cm-notif-icon is-<?= htmlspecialchars($notifTone) ?>" aria-hidden="true"><i class="bi <?= htmlspecialchars($notifIconClass) ?>"></i></span>
                                <span class="cm-notif-body">
                                    <span class="cm-notif-title"><?= htmlspecialchars((string) $notifRow['title']) ?></span>
                                    <span class="cm-notif-msg"><?= htmlspecialchars((string) $notifRow['message']) ?></span>
                                    <span class="cm-notif-time"><?= htmlspecialchars($notifTime((string) $notifRow['created'])) ?></span>
                                </span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="cm-empty">
                            <i class="bi bi-bell-slash" aria-hidden="true"></i>
                            <span class="cm-empty-title">You're all caught up</span>
                            <span class="cm-empty-sub">Match assignments, schedule changes and result reminders land here.</span>
                        </div>
                        <?php endif; ?>
                    </section>

                    <section class="cm-panel dash-animate dash-animate-d4" aria-labelledby="cmUpcomingTitle">
                        <div class="cm-panel-head">
                            <div>
                                <span class="cm-panel-eyebrow">Assignments</span>
                                <h2 class="cm-panel-title" id="cmUpcomingTitle">Upcoming matches</h2>
                            </div>
                            <a class="cm-panel-link is-accent" href="matches.php">All matches <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                        </div>
                        <?php if (!empty($assignedRows)): ?>
                        <div class="cm-table-wrap">
                            <table class="cm-table">
                                <thead>
                                    <tr>
                                        <th>Match</th>
                                        <th>Schedule</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($assignedRows as $row):
                                    [$badgeClass, $badgeLabel] = $statusBadge($row['status'] ?? '');
                                    $venue = trim((string) ($row['venue'] ?? ''));
                                ?>
                                    <tr>
                                        <td class="cm-cell-match">
                                            <span class="cm-match">
                                                <span class="cm-user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr((string) $row['team_a_name'], 0, 1))) ?></span>
                                                <span class="cm-match-copy">
                                                    <span class="cm-match-teams"><?= htmlspecialchars($row['team_a_name']) ?> vs <?= htmlspecialchars($row['team_b_name']) ?></span>
                                                    <span class="cm-match-league"><?= htmlspecialchars($row['league_name']) ?></span>
                                                </span>
                                            </span>
                                        </td>
                                        <td class="cm-cell-schedule">
                                            <strong><?= date('M d, Y', strtotime($row['date'])) ?></strong>
                                            <?= date('g:i A', strtotime($row['time'])) ?>
                                            <span class="cm-sched-venue"><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= $venue !== '' ? htmlspecialchars($venue) : 'To be announced' ?></span>
                                        </td>
                                        <td class="cm-cell-status"><span class="cm-badge <?= $badgeClass ?>"><?= htmlspecialchars($badgeLabel) ?></span></td>
                                        <td class="cm-cell-action"><a class="cm-panel-link" href="matches.php?id=<?= (int) $row['id'] ?>">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="cm-empty">
                            <i class="bi bi-calendar2-event" aria-hidden="true"></i>
                            <span class="cm-empty-title">No upcoming matches</span>
                            <span class="cm-empty-sub">Fixtures assigned to you will appear here once the organizer publishes the schedule.</span>
                        </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
/* Inbox panel: mark notifications read against the shared endpoint. */
(function () {
    var actionUrl = '../../includes/notificationAction.php';
    var csrfToken = <?= json_encode(csrfToken()) ?>;
    var badge = document.querySelector('.role-notification-badge');
    var pill = document.querySelector('.cm-notif-tools .cm-panel-count');
    var markAllBtn = document.getElementById('cmNotifMarkAll');

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

    function syncBadge(decrement) {
        if (!badge) return;
        var current = parseInt(badge.textContent, 10) || 0;
        var next = Math.max(0, current - decrement);
        if (next > 0) {
            badge.textContent = next > 99 ? '99+' : String(next);
            badge.style.display = '';
        } else {
            badge.textContent = '';
            badge.style.display = 'none';
        }
        return next;
    }

    function stepPill() {
        if (!pill) return;
        var left = parseInt(pill.textContent, 10);
        if (isNaN(left)) return;
        left = Math.max(0, left - 1);
        pill.textContent = left > 0 ? (left > 99 ? '99+' : String(left)) + ' unread' : 'All caught up';
    }

    document.querySelectorAll('.cm-notif.is-unread').forEach(function (item) {
        item.addEventListener('click', function () {
            if (!item.classList.contains('is-unread')) return;
            var id = item.getAttribute('data-notif-id');
            item.classList.remove('is-unread');
            syncBadge(1);
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
                document.querySelectorAll('.cm-notif.is-unread').forEach(function (item) {
                    item.classList.remove('is-unread');
                });
                if (pill) pill.textContent = 'All caught up';
                if (badge) {
                    badge.textContent = '';
                    badge.style.display = 'none';
                }
                markAllBtn.remove();
            });
        });
    }
})();
</script>
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/fullcalendar/6.1.10/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Live clock ──────────────────────────────────────────────────────
    function updateClock() {
        var el = document.getElementById('cmClockTime');
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
    document.querySelectorAll('.cm-metric-value[data-count]').forEach(function (el) {
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

    // ── Assigned-match calendar ─────────────────────────────────────────
    const calendarElement = document.getElementById('committeeMatchCalendar');
    if (!calendarElement || typeof FullCalendar === 'undefined') return;

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;'
    })[character]);
    const calendar = new FullCalendar.Calendar(calendarElement, {
        initialView: window.innerWidth < 768 ? 'listMonth' : 'dayGridMonth',
        height: 'auto',
        dayMaxEvents: true,
        nowIndicator: true,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth'
        },
        buttonText: { today:'Today', month:'Month', list:'Agenda' },
        events: <?= json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            const event = info.event;
            const details = event.extendedProps;
            const dateText = event.start.toLocaleString([], { dateStyle:'long', timeStyle:'short' });
            Swal.fire({
                title: escapeHtml(event.title),
                html: '<div class="text-start">' +
                    '<strong>League:</strong> ' + escapeHtml(details.league) + '<br>' +
                    '<strong>Date:</strong> ' + escapeHtml(dateText) + '<br>' +
                    '<strong>Venue:</strong> ' + escapeHtml(details.venue) + '<br>' +
                    '<strong>Status:</strong> ' + escapeHtml(details.status) + '</div>',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'View match',
                cancelButtonText: 'Close',
                confirmButtonColor: '#09173c'
            }).then(result => {
                if (result.isConfirmed && event.url) window.location.href = event.url;
            });
        }
    });
    calendar.render();
});
</script>
</body></html>
