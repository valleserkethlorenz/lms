<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
/** @var mysqli $conn */
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../config/teamRegistrationStatus.php';
require_once __DIR__ . '/../../includes/teamCorrectionDeadline.php';

requireRole([2]);
requireActiveAccount($conn);
$userId = currentUserId();
$full = currentUserName();
$userType = currentRole();

function getDashboardTeamStats(mysqli $conn, int $userId): array {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS my_teams,
                SUM(CASE WHEN LOWER(t.status)='pending' THEN 1 ELSE 0 END) AS reg_pending,
                SUM(CASE WHEN LOWER(t.status)='approved' THEN 1 ELSE 0 END) AS reg_approved,
                SUM(CASE WHEN LOWER(t.status)='rejected' THEN 1 ELSE 0 END) AS reg_rejected
         FROM tbl_team t
         JOIN tbl_league l ON l.id=t.league_id
         WHERE t.user_id=?
           AND l.publication_status='Published'
           AND (l.is_archived=0 OR l.is_archived IS NULL)
           AND LOWER(t.status) NOT IN ('cancelled','canceled','expired')"
    );

    if (!$stmt) {
        return [
            'my_teams' => 0,
            'reg_pending' => 0,
            'reg_approved' => 0,
            'reg_rejected' => 0,
        ];
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'my_teams' => (int) ($row['my_teams'] ?? 0),
        'reg_pending' => (int) ($row['reg_pending'] ?? 0),
        'reg_approved' => (int) ($row['reg_approved'] ?? 0),
        'reg_rejected' => (int) ($row['reg_rejected'] ?? 0),
    ];
}

if ($userType == 2) {
    // Lightweight endpoint used by the dashboard cards for live counts.
    if (isset($_GET['live_stats'])) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $liveStats = getDashboardTeamStats($conn, (int) $userId);

        echo json_encode([
            'success' => true,
            'stats' => [
                'my_teams' => (int) ($liveStats['my_teams'] ?? 0),
                'reg_pending' => (int) ($liveStats['reg_pending'] ?? 0),
                'reg_approved' => (int) ($liveStats['reg_approved'] ?? 0),
                'reg_rejected' => (int) ($liveStats['reg_rejected'] ?? 0),
            ],
            'updated_at' => date(DATE_ATOM),
        ]);
        exit;
    }

    require_once __DIR__ . '/../../includes/notificationHelper.php';
    expireTeamCorrectionDeadlines($conn);

    // Run daily notifications once per day per user (stored in session). They
    // are a convenience, so a database hiccup while building them must never
    // stop the representative's dashboard from rendering.
    $todayKey = 'daily_notif_' . date('Y-m-d');
    if (empty($_SESSION[$todayKey])) {
        try {
            notifyUpcomingMatchToday($conn);
            notifyRegistrationClosingSoon($conn, 3);
            notifyTeamCorrectionDeadlines($conn, (int)$userId);
        } catch (Throwable $e) {
            error_log('[notification] Daily reminders failed: ' . $e->getMessage());
        }
        $_SESSION[$todayKey] = true;
    }
    $loggin = true;
    $time = strtotime("-10 year", time());
    $date = date("Y-m-d", $time);

    $today = date("M/d/Y");

    // Greeting based on time of day
    $hour = (int)date('G');
    if ($hour < 12) $greeting = 'Good Morning';
    elseif ($hour < 17) $greeting = 'Good Afternoon';
    else $greeting = 'Good Evening';
    $todayDate = date('l, F j, Y');

    include 'includes/header.php';
    $pending = array();

    $count = 0;
    $pending = '0';

    // Team representative dashboard counts
    $dashboardStats = getDashboardTeamStats($conn, (int) $userId);
    $my_teams = $dashboardStats['my_teams'];
    $reg_pending = $dashboardStats['reg_pending'];
    $reg_approved = $dashboardStats['reg_approved'];
    $reg_rejected = $dashboardStats['reg_rejected'];

    // ── My registrations: the list rendered by the "Team registrations" panel.
    $teamRows = [];
    $teamStmt = $conn->prepare(
        "SELECT t.id AS team_id, t.team_name, t.status, l.league_name, l.sport
           FROM tbl_team t
           INNER JOIN tbl_league l ON l.id = t.league_id
          WHERE t.user_id = ?
          ORDER BY t.id DESC"
    );
    if ($teamStmt) {
        $teamStmt->bind_param('i', $userId);
        $teamStmt->execute();
        $teamResult = $teamStmt->get_result();
        while ($teamRow = $teamResult->fetch_assoc()) {
            $teamRows[] = $teamRow;
        }
        $teamStmt->close();
    }

    // Status pill tone for each registration state (mirrors the TR badges).
    $teamStatusKey = static function ($status): string {
        $key = strtolower(trim((string) $status));
        if ($key === 'correction requested' || $key === 'needs correction') $key = 'correction';
        if ($key === 'canceled') $key = 'cancelled';
        return in_array($key, ['approved', 'pending', 'rejected', 'correction', 'cancelled', 'expired'], true) ? $key : 'default';
    };
    $teamStatusAccent = [
        'approved' => '#137a4e', 'pending' => '#b26a12', 'rejected' => '#b42335',
        'correction' => '#6941c6', 'cancelled' => '#64708a', 'expired' => '#98a1b2',
        'default' => '#2f6fed',
    ];

    // ── Inbox: the three most recent notifications (same rows as the bell).
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

    // Icon + accent tone per type (mirrors getIcon()/getIconColor() in the bell popover).
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
    // ── Season timeline: league milestones for joined leagues + my published matches.
    $calendarEvents = [];
    $calendarDateIsValid = static function ($value) {
        $date = DateTime::createFromFormat('Y-m-d', (string) $value);
        return $date && $date->format('Y-m-d') === $value;
    };

    $timelineStmt = $conn->prepare(
        "SELECT DISTINCT l.id, l.league_name, l.regis_start, l.regis_end, l.start_date, l.end_date
         FROM tbl_team t
         JOIN tbl_league l ON l.id=t.league_id
         WHERE t.user_id=? AND (l.is_archived=0 OR l.is_archived IS NULL)
         ORDER BY l.start_date, l.league_name"
    );
    $timelineStmt->bind_param('i', $userId);
    $timelineStmt->execute();
    $timelineResult = $timelineStmt->get_result();
    $milestones = [
        'regis_start' => ['Registration opens', 'registration'],
        'regis_end'   => ['Registration closes', 'registration'],
        'start_date'  => ['League starts', 'league'],
        'end_date'    => ['League ends', 'league'],
    ];
    while ($league = $timelineResult->fetch_assoc()) {
        foreach ($milestones as $field => [$label, $type]) {
            $eventDate = (string) ($league[$field] ?? '');
            if (!$calendarDateIsValid($eventDate)) continue;
            $calendarEvents[] = [
                'date'   => $eventDate,
                'title'  => $league['league_name'] . ' — ' . $label,
                'type'   => $type,
                'kind'   => 'League timeline',
                'league' => $league['league_name'],
                'detail' => $label,
                'time'   => '',
                'url'    => 'league.php',
            ];
        }
    }
    $timelineStmt->close();

    $matchStmt = $conn->prepare(
        "SELECT DISTINCT s.id, s.date, s.time, s.venue, s.status, l.league_name,
                ta.team_name AS team_a_name, tb.team_name AS team_b_name
         FROM tbl_schedule s
         JOIN tbl_league l ON l.id=s.league_id
         JOIN tbl_team ta ON ta.id=s.team_a
         JOIN tbl_team tb ON tb.id=s.team_b
         WHERE (ta.user_id=? OR tb.user_id=?)
           AND s.published_at IS NOT NULL
           AND (s.is_archived=0 OR s.is_archived IS NULL)
           AND LOWER(s.status)<>'cancelled'
         ORDER BY s.date, s.time"
    );
    $matchStmt->bind_param('ii', $userId, $userId);
    $matchStmt->execute();
    $matchResult = $matchStmt->get_result();
    while ($match = $matchResult->fetch_assoc()) {
        $eventDate = (string) ($match['date'] ?? '');
        if (!$calendarDateIsValid($eventDate)) continue;
        $rawTime = preg_match('/^\d{2}:\d{2}/', (string) $match['time']) ? substr((string) $match['time'], 0, 5) : '';
        $calendarEvents[] = [
            'date'   => $eventDate,
            'title'  => $match['team_a_name'] . ' vs ' . $match['team_b_name'],
            'type'   => 'match',
            'kind'   => 'Match',
            'league' => $match['league_name'],
            'detail' => $match['team_a_name'] . ' vs ' . $match['team_b_name'],
            'time'   => $rawTime,
            'venue'  => $match['venue'] ?: 'To be announced',
            'status' => $match['status'],
            'url'    => 'league.php',
        ];
    }
    $matchStmt->close();




?>
    <?php include 'includes/dashboard-styles.php'; ?>
    <?php include 'includes/dashboard-home-styles.php'; ?>

    <body id="page-top" class="body">
        <?php include 'includes/bar.php'; ?>
        <div class="container-fluid" style="padding-top:20px;">
            <div class="row p-3">
                <div class="tr-home">

                    <!-- ── Hero deck ─────────────────────────────────────── -->
                    <?php
                    $heroEyebrow  = 'Team representative / Season desk';
                    $heroTitle    = htmlspecialchars($greeting) . ', <span>' . htmlspecialchars(explode(' ', trim((string) $full))[0] ?: 'Team Representative') . '</span>.';
                    $heroSubtitle = 'Your teams, registration progress and league dates in one place.';
                    $heroIcon     = 'bi-speedometer2';
                    $heroLabel    = 'Team representative overview';
                    $heroSide     = '<div class="tr-datecard" aria-hidden="true">'
                        . '<span class="tr-date-dow">' . date('D') . '</span>'
                        . '<span class="tr-date-day">' . date('j') . '</span>'
                        . '<span class="tr-date-my">' . date('M Y') . '</span>'
                        . '</div>'
                        . '<div class="tr-clock">'
                        . '<span class="tr-clock-label"><span class="tr-live-dot" aria-hidden="true"></span>Local time</span>'
                        . '<span class="tr-clock-time" id="clockTime">--:--:--</span>'
                        . '</div>';
                    include 'includes/page-hero.php';
                    ?>

                    <!-- ── Unfinished registrations (device drafts) ────────── -->
                    <?php include 'includes/registration-drafts-panel.php'; ?>

                    <!-- ── Scoreboard strip ──────────────────────────────── -->
                    <div class="section-head dash-animate dash-animate-d1">
                        <div>
                            <span class="section-eyebrow">Overview</span>
                            <h2 class="section-title">Registration at a glance</h2>
                        </div>
                        <span class="section-meta"><span class="section-live-dot" aria-hidden="true"></span>Live stats</span>
                    </div>
                    <div class="tr-board dash-animate dash-animate-d1">
                        <a class="tr-metric" href="league.php" style="--tr-accent:#09173c">
                            <span class="tr-metric-index" aria-hidden="true">01</span>
                            <span class="tr-metric-label"><i class="bi bi-people" aria-hidden="true"></i>My teams</span>
                            <span class="tr-metric-value" id="statMyTeams" data-count="<?= (int) $my_teams ?>"><?= (int) $my_teams ?></span>
                            <span class="tr-metric-note">In your workspace</span>
                            <i class="bi bi-arrow-up-right tr-metric-go" aria-hidden="true"></i>
                        </a>
                        <a class="tr-metric" href="league.php" style="--tr-accent:#b26a12">
                            <span class="tr-metric-index" aria-hidden="true">02</span>
                            <span class="tr-metric-label"><i class="bi bi-hourglass-split" aria-hidden="true"></i>Pending</span>
                            <span class="tr-metric-value" id="statPending" data-count="<?= (int) $reg_pending ?>"><?= (int) $reg_pending ?></span>
                            <span class="tr-metric-note">Awaiting your review</span>
                            <i class="bi bi-arrow-up-right tr-metric-go" aria-hidden="true"></i>
                        </a>
                        <a class="tr-metric" href="league.php" style="--tr-accent:#137a4e">
                            <span class="tr-metric-index" aria-hidden="true">03</span>
                            <span class="tr-metric-label"><i class="bi bi-check-circle" aria-hidden="true"></i>Approved</span>
                            <span class="tr-metric-value" id="statApproved" data-count="<?= (int) $reg_approved ?>"><?= (int) $reg_approved ?></span>
                            <span class="tr-metric-note">Registration confirmed</span>
                            <i class="bi bi-arrow-up-right tr-metric-go" aria-hidden="true"></i>
                        </a>
                        <a class="tr-metric" href="league.php" style="--tr-accent:#b42335">
                            <span class="tr-metric-index" aria-hidden="true">04</span>
                            <span class="tr-metric-label"><i class="bi bi-x-circle" aria-hidden="true"></i>Rejected</span>
                            <span class="tr-metric-value" id="statRejected" data-count="<?= (int) $reg_rejected ?>"><?= (int) $reg_rejected ?></span>
                            <span class="tr-metric-note">Not approved</span>
                            <i class="bi bi-arrow-up-right tr-metric-go" aria-hidden="true"></i>
                        </a>
                    </div>

                    <!-- ── Registrations + inbox | season timeline ───────── -->
                    <div class="tr-grid dashboard-layout-row">
                        <div class="tr-col">
                            <section class="tr-panel dash-animate dash-animate-d3" aria-labelledby="trTeamsTitle">
                                <div class="tr-panel-head">
                                    <div>
                                        <span class="tr-panel-eyebrow">My teams</span>
                                        <h2 class="tr-panel-title" id="trTeamsTitle">Team registrations</h2>
                                    </div>
                                    <a href="league.php" class="tr-panel-link is-accent">Manage <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                                </div>
                                <?php if (!empty($teamRows)): ?>
                                <div class="tr-reg-list">
                                    <?php foreach ($teamRows as $index => $teamRow):
                                        $statusKey = $teamStatusKey($teamRow['status'] ?? '');
                                        $statusLabel = ucwords(trim((string) ($teamRow['status'] ?? ''))) ?: 'Pending';
                                    ?>
                                    <a class="tr-reg" href="view_team.php?id=<?= (int) $teamRow['team_id'] ?>" style="--tr-accent:<?= htmlspecialchars($teamStatusAccent[$statusKey] ?? '#2f6fed') ?>">
                                        <span class="tr-reg-idx" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                        <span class="tr-reg-main">
                                            <span class="tr-reg-name"><?= htmlspecialchars((string) $teamRow['team_name']) ?></span>
                                            <span class="tr-reg-meta">
                                                <span><?= htmlspecialchars((string) $teamRow['league_name']) ?></span>
                                                <span class="tr-sep" aria-hidden="true"></span>
                                                <span><?= htmlspecialchars((string) $teamRow['sport']) ?></span>
                                            </span>
                                        </span>
                                        <span class="tr-status is-<?= htmlspecialchars($statusKey) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="tr-empty">
                                    <i class="bi bi-people" aria-hidden="true"></i>
                                    <span class="tr-empty-title">No team registrations yet</span>
                                    <span class="tr-empty-sub">Browse the open leagues and register your first team to track it here.</span>
                                    <a class="tr-empty-cta" href="browse_leagues.php"><i class="bi bi-plus-lg" aria-hidden="true"></i>Browse leagues</a>
                                </div>
                                <?php endif; ?>
                            </section>

                            <?php $notifUnread = (int) ($_bar_unread ?? 0); ?>
                            <section class="tr-panel dash-animate dash-animate-d4" aria-labelledby="trNotifTitle">
                                <div class="tr-panel-head">
                                    <div>
                                        <span class="tr-panel-eyebrow">Inbox</span>
                                        <h2 class="tr-panel-title" id="trNotifTitle">Notifications</h2>
                                    </div>
                                    <div class="tr-notif-tools">
                                        <?php if ($notifUnread > 0): ?>
                                            <span class="tr-panel-count"><?= $notifUnread > 99 ? '99+' : $notifUnread ?> unread</span>
                                            <button type="button" class="tr-panel-link tr-notif-readall" id="notifMarkAll">Mark all read</button>
                                        <?php else: ?>
                                            <span class="tr-panel-count">All caught up</span>
                                        <?php endif; ?>
                                        <a class="tr-panel-link is-accent tr-notif-seeall" href="notification.php">See all <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                    </div>
                                </div>
                                <?php if (!empty($notifRows)): ?>
                                <div class="tr-notif-list">
                                    <?php foreach ($notifRows as $notifRow):
                                        $notifType = (string) $notifRow['type'];
                                        [$notifIconClass, $notifTone] = $notifIcons[$notifType] ?? ['bi-bell-fill', 'default'];
                                        $notifHref = teamRepNotificationUrl($conn, $notifType, (int) ($notifRow['related_id'] ?? 0), (string) $notifRow['title']);
                                        $notifIsRead = (int) $notifRow['is_read'] === 1;
                                    ?>
                                    <a class="tr-notif<?= $notifIsRead ? '' : ' is-unread' ?>" href="<?= htmlspecialchars($notifHref) ?>" data-notif-id="<?= (int) $notifRow['id'] ?>">
                                        <span class="tr-notif-icon is-<?= $notifTone ?>" aria-hidden="true"><i class="bi <?= $notifIconClass ?>"></i></span>
                                        <span class="tr-notif-body">
                                            <span class="tr-notif-title"><?= htmlspecialchars((string) $notifRow['title']) ?></span>
                                            <span class="tr-notif-msg"><?= htmlspecialchars((string) $notifRow['message']) ?></span>
                                            <span class="tr-notif-time"><?= htmlspecialchars($notifTime((string) $notifRow['created'])) ?></span>
                                        </span>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="tr-empty">
                                    <i class="bi bi-bell-slash" aria-hidden="true"></i>
                                    <span class="tr-empty-title">You're all caught up</span>
                                    <span class="tr-empty-sub">Registration updates, schedule changes and announcements land here.</span>
                                </div>
                                <?php endif; ?>
                            </section>
                        </div>

                        <div class="tr-col tr-col-calendar">
                            <section class="tr-panel tr-cal dash-animate dash-animate-d4" aria-label="My league calendar">
                                <div class="tr-cal-head">
                                    <div>
                                        <span class="tr-panel-eyebrow">Season timeline</span>
                                        <div class="tr-cal-month" id="calendarMonth">&nbsp;</div>
                                    </div>
                                    <div class="tr-cal-tools">
                                        <div class="tr-cal-legend" aria-hidden="true">
                                            <span class="tr-legend-item"><span class="tr-legend-dot is-reg"></span>Registration</span>
                                            <span class="tr-legend-item"><span class="tr-legend-dot is-lg"></span>League</span>
                                            <span class="tr-legend-item"><span class="tr-legend-dot is-match"></span>Match</span>
                                        </div>
                                        <div class="tr-cal-nav">
                                            <button type="button" class="tr-navbtn" id="calendarPrev" aria-label="Previous month"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                                            <button type="button" class="tr-todaybtn" id="calendarToday">Today</button>
                                            <button type="button" class="tr-navbtn" id="calendarNext" aria-label="Next month"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                                            <button type="button" class="tr-navbtn calendar-expand-btn" id="calendarExpand" aria-label="Expand calendar" title="Expand calendar"><i class="bi bi-arrows-fullscreen" aria-hidden="true"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="calendar-weekdays" aria-hidden="true">
                                    <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                                </div>
                                <div class="calendar-grid" id="calendarGrid" aria-label="League milestones and matches by day"></div>
                            </section>
                        </div>
                    </div><!-- /.tr-grid -->
                </div><!-- /.tr-home -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->

        <?php include 'includes/footer.php'; ?>

        <script>
            // Live clock in the hero deck.
            (function updateLiveClock() {
                const clockEl = document.getElementById('clockTime');
                function tick() {
                    const now = new Date();
                    const h = now.getHours();
                    const m = String(now.getMinutes()).padStart(2, '0');
                    const s = String(now.getSeconds()).padStart(2, '0');
                    const ampm = h >= 12 ? 'PM' : 'AM';
                    const h12 = h % 12 || 12;
                    if (clockEl) clockEl.textContent = `${h12}:${m}:${s} ${ampm}`;
                }
                tick();
                setInterval(tick, 1000);
            })();

            // Animated count-up on the metric strip.
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.tr-metric-value[data-count]').forEach(function (el) {
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
            });
        </script>
        <script>
            // Live counts for the metric strip (same endpoint the page renders from).
            (function enableLiveDashboardCards() {
                const statElements = {
                    my_teams: document.getElementById('statMyTeams'),
                    reg_pending: document.getElementById('statPending'),
                    reg_approved: document.getElementById('statApproved'),
                    reg_rejected: document.getElementById('statRejected')
                };
                let requestInProgress = false;

                const statsUrl = new URL('dashboard.php', window.location.href);
                statsUrl.searchParams.set('live_stats', '1');

                async function refreshDashboardStats() {
                    if (requestInProgress || document.hidden) return;
                    requestInProgress = true;
                    try {
                        const response = await fetch(statsUrl.toString(), {
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: { 'Accept': 'application/json' }
                        });
                        if (!response.ok) throw new Error('Unable to load dashboard statistics.');
                        const payload = await response.json();
                        if (!payload || !payload.success || !payload.stats) return;

                        Object.keys(statElements).forEach(key => {
                            const element = statElements[key];
                            if (!element || typeof payload.stats[key] === 'undefined') return;
                            const nextValue = String(payload.stats[key]);
                            if (element.textContent.trim() !== nextValue) {
                                element.textContent = nextValue;
                                if (typeof element.animate === 'function') {
                                    element.animate(
                                        [{ transform: 'scale(1)', color: '#2f6fed' }, { transform: 'scale(1.12)' }, { transform: 'scale(1)', color: '#09173c' }],
                                        { duration: 450, easing: 'ease-out' }
                                    );
                                }
                            }
                        });
                    } catch (error) {
                        console.warn(error.message);
                    } finally {
                        requestInProgress = false;
                    }
                }

                refreshDashboardStats();
                window.setInterval(refreshDashboardStats, 10000);
                document.addEventListener('visibilitychange', function () {
                    if (!document.hidden) refreshDashboardStats();
                });
            })();
        </script>

        <script>
            // Planner-style calendar: league milestones + my published matches.
            (() => {
                const events = <?= json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                const grid = document.getElementById('calendarGrid');
                const monthLabel = document.getElementById('calendarMonth');
                if (!grid || !monthLabel) return;

                let displayedMonth = new Date();
                displayedMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth(), 1);

                const key = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
                const escapeCalendarText = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));

                function showEvent(event) {
                    const rows = ['<div class="text-start"><strong>' + escapeCalendarText(event.title) + '</strong>'];
                    if (event.league) rows.push('<span>' + escapeCalendarText(event.league) + '</span>');
                    if (event.venue) rows.push('<span>Venue: ' + escapeCalendarText(event.venue) + '</span>');
                    rows.push('</div>');
                    Swal.fire({
                        title: escapeCalendarText(event.kind || 'League timeline'),
                        html: rows.join('<br>'),
                        icon: 'info',
                        confirmButtonColor: '#09173c',
                        showCancelButton: true,
                        confirmButtonText: 'Open application',
                        cancelButtonText: 'Close'
                    }).then(result => {
                        if (result.isConfirmed && event.url) location.href = event.url;
                    });
                }

                function renderCalendar() {
                    grid.innerHTML = '';
                    monthLabel.textContent = displayedMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
                    const first = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth(), 1);
                    const cursor = new Date(first);
                    cursor.setDate(cursor.getDate() - first.getDay());
                    const todayKey = key(new Date());
                    for (let day = 0; day < 42; day++) {
                        const date = new Date(cursor);
                        date.setDate(cursor.getDate() + day);
                        const cell = document.createElement('div');
                        cell.className = 'calendar-day' + (date.getMonth() !== displayedMonth.getMonth() ? ' is-outside' : '') + (key(date) === todayKey ? ' is-today' : '');
                        cell.innerHTML = `<span class="calendar-date">${date.getDate()}</span>`;
                        const dayEvents = events.filter(event => event.date === key(date));
                        dayEvents.slice(0, 3).forEach(event => {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = `calendar-event event-${event.type || 'league'}`;
                            button.textContent = (event.time ? event.time + ' ' : '') + event.title;
                            button.title = button.textContent;
                            button.addEventListener('click', () => showEvent(event));
                            cell.appendChild(button);
                        });
                        if (dayEvents.length > 3) {
                            const more = document.createElement('div');
                            more.className = 'calendar-more';
                            more.textContent = `+${dayEvents.length - 3} more`;
                            cell.appendChild(more);
                        }
                        grid.appendChild(cell);
                    }
                }

                const prevBtn = document.getElementById('calendarPrev');
                const nextBtn = document.getElementById('calendarNext');
                const todayBtn = document.getElementById('calendarToday');
                if (prevBtn) prevBtn.addEventListener('click', () => { displayedMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth() - 1, 1); renderCalendar(); });
                if (nextBtn) nextBtn.addEventListener('click', () => { displayedMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth() + 1, 1); renderCalendar(); });
                if (todayBtn) todayBtn.addEventListener('click', () => { const now = new Date(); displayedMonth = new Date(now.getFullYear(), now.getMonth(), 1); renderCalendar(); });
                renderCalendar();

                // Expand / collapse toggle
                const expandBtn = document.getElementById('calendarExpand');
                const layoutRow = document.querySelector('.dashboard-layout-row');
                const calendarEl = document.querySelector('.tr-cal');
                if (expandBtn && layoutRow && calendarEl) {
                    expandBtn.addEventListener('click', function () {
                        const isExpanded = calendarEl.classList.toggle('is-expanded');
                        layoutRow.classList.toggle('expanded', isExpanded);
                        const icon = expandBtn.querySelector('i');
                        if (icon) icon.className = isExpanded ? 'bi bi-arrows-angle-contract' : 'bi bi-arrows-fullscreen';
                        expandBtn.title = isExpanded ? 'Collapse calendar' : 'Expand calendar';
                        expandBtn.setAttribute('aria-label', expandBtn.title);
                    });
                }
            })();
        </script>

        <script>
            /* Inbox panel: mark notifications read against the shared endpoint. */
            (function () {
                var actionUrl = '../../includes/notificationAction.php';
                var csrfToken = <?= json_encode(csrfToken()) ?>;
                var badge = document.querySelector('.role-notification-badge');
                var pill = document.querySelector('.tr-notif-tools .tr-panel-count');
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

                document.querySelectorAll('.tr-notif.is-unread').forEach(function (item) {
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
                            document.querySelectorAll('.tr-notif.is-unread').forEach(function (item) {
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
    </body>
    </html>
<?php
} else {
    header("location: /lms/index.php");
}
?>

