<?php
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/leagueStatus.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../includes/notificationHelper.php';
/** @var mysqli $conn */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$userId = currentUserId();
$full = currentUserName();
$userType = currentRole();
requireRole([1]);
requireActiveAccount($conn);
$organizerId = (int)$userId;
    // Lightweight endpoint used by the organizer's live summary cards.
    if (isset($_GET['live_stats'])) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $statsStmt = $conn->prepare(
            "SELECT
                (SELECT COUNT(*) FROM tbl_league l WHERE l.created_by=? AND (l.is_archived=0 OR l.is_archived IS NULL)) AS total_leagues,
                (SELECT COUNT(*) FROM tbl_league l WHERE l.created_by=? AND (l.is_archived=0 OR l.is_archived IS NULL) AND l.start_date<=CURDATE() AND l.end_date>=CURDATE()) AS ongoing_leagues,
                (SELECT COUNT(*) FROM tbl_team t JOIN tbl_league l ON l.id=t.league_id WHERE l.created_by=? AND LOWER(t.status)='pending') AS pending_teams,
                (SELECT COUNT(*) FROM tbl_league l WHERE l.created_by=? AND (l.is_archived=0 OR l.is_archived IS NULL) AND l.start_date>CURDATE()) AS upcoming_leagues"
        );
        $statsStmt->bind_param('iiii', $organizerId, $organizerId, $organizerId, $organizerId);
        $statsStmt->execute();
        $liveStats = $statsStmt->get_result()->fetch_assoc();
        $statsStmt->close();

        echo json_encode([
            'success' => true,
            'stats' => [
                'total_leagues' => (int) ($liveStats['total_leagues'] ?? 0),
                'ongoing_leagues' => (int) ($liveStats['ongoing_leagues'] ?? 0),
                'pending_teams' => (int) ($liveStats['pending_teams'] ?? 0),
                'upcoming_leagues' => (int) ($liveStats['upcoming_leagues'] ?? 0),
            ],
            'updated_at' => date(DATE_ATOM),
        ]);
        exit;
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

    // Dashboard counts (all using prepared statements)
    $today_date = date('Y-m-d');

    $totalStmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_league WHERE created_by=? AND (is_archived=0 OR is_archived IS NULL)");
    $totalStmt->bind_param('i', $organizerId); $totalStmt->execute();
    $total_leagues = intval($totalStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $totalStmt->close();

    $ongoingStmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_league WHERE created_by=? AND (is_archived=0 OR is_archived IS NULL) AND start_date <= ? AND end_date >= ?");
    $ongoingStmt->bind_param('iss', $organizerId, $today_date, $today_date); $ongoingStmt->execute();
    $ongoing_leagues = intval($ongoingStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $ongoingStmt->close();

    $pendingStmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_team t JOIN tbl_league l ON l.id=t.league_id WHERE l.created_by=? AND LOWER(t.status)='pending'");
    $pendingStmt->bind_param('i', $organizerId); $pendingStmt->execute();
    $pending_leagues = intval($pendingStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $pendingStmt->close();

    $incomingStmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_league WHERE created_by=? AND (is_archived=0 OR is_archived IS NULL) AND start_date > ?");
    $incomingStmt->bind_param('is', $organizerId, $today_date); $incomingStmt->execute();
    $incoming_leagues = intval($incomingStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $incomingStmt->close();

    // Read-only dashboard calendar showing league timeline milestones only.
    $calendarEvents = [];
    $calendarLeagueStmt = $conn->prepare("SELECT id,league_name,regis_start,regis_end,start_date,end_date FROM tbl_league WHERE created_by=? AND (is_archived=0 OR is_archived IS NULL)");
    $calendarLeagueStmt->bind_param('i', $organizerId);
    $calendarLeagueStmt->execute();
    $calendarLeagueResult = $calendarLeagueStmt->get_result();
    while ($calendarLeague = $calendarLeagueResult->fetch_assoc()) {
        $leagueName = $calendarLeague['league_name'];
        $leagueUrl = 'league_info.php?id=' . (int)$calendarLeague['id'];
        foreach ([
            ['regis_start', 'Registration opens', 'registration', $calendarLeague['regis_start'] ?? ''],
            ['regis_end', 'Registration closes', 'registration', $calendarLeague['regis_end'] ?? ''],
            ['start_date', 'League starts', 'league', $calendarLeague['start_date'] ?? ''],
            ['end_date', 'League ends', 'league', $calendarLeague['end_date'] ?? ''],
        ] as [$field, $label, $type, $eventDate]) {
            if (!empty($eventDate)) {
                $calendarEvents[] = ['date'=>$eventDate, 'title'=>$label . ': ' . $leagueName, 'type'=>$type, 'url'=>$leagueUrl];
            }
        }
    }
    $calendarLeagueStmt->close();

    // ── League desk: one read for the cards + one grouped read for roster sizes.
    $teamCounts = [];
    $teamCountStmt = $conn->prepare(
        "SELECT t.league_id, COUNT(*) AS total
           FROM tbl_team t
           INNER JOIN tbl_league l ON l.id = t.league_id
          WHERE l.created_by = ? AND LOWER(t.status) <> 'rejected'
          GROUP BY t.league_id"
    );
    if ($teamCountStmt) {
        $teamCountStmt->bind_param('i', $organizerId);
        $teamCountStmt->execute();
        $teamCountResult = $teamCountStmt->get_result();
        while ($teamCountRow = $teamCountResult->fetch_assoc()) {
            $teamCounts[(int) $teamCountRow['league_id']] = (int) $teamCountRow['total'];
        }
        $teamCountStmt->close();
    }

    $todayTs = strtotime($today_date);
    $leagueRows = [];
    $leagueListStmt = $conn->prepare(
        "SELECT id, league_name, sport, max_team, start_date, end_date,
                regis_start, regis_end, registration_window_open, publication_status
           FROM tbl_league
          WHERE created_by = ? AND (is_archived = 0 OR is_archived IS NULL)"
    );
    if ($leagueListStmt) {
        $leagueListStmt->bind_param('i', $organizerId);
        $leagueListStmt->execute();
        $leagueListResult = $leagueListStmt->get_result();
        while ($league = $leagueListResult->fetch_assoc()) {
            $isDraft = strcasecmp((string) ($league['publication_status'] ?? 'Published'), 'Draft') === 0;
            $statusKey = $isDraft ? 'draft' : strtolower(getLeagueStatus($league));
            $startTs = !empty($league['start_date']) ? strtotime($league['start_date']) : null;
            $endTs = !empty($league['end_date']) ? strtotime($league['end_date']) : null;

            // Where today sits on the league's timeline (0–100%).
            $progress = 0;
            if ($startTs && $endTs && $endTs > $startTs) {
                $progress = (int) round((($todayTs - $startTs) / ($endTs - $startTs)) * 100);
            } elseif ($endTs && $todayTs >= $endTs) {
                $progress = 100;
            }
            $progress = max(0, min(100, $progress));

            $accentMap = [
                'open' => '#137a4e', 'ongoing' => '#2f6fed', 'upcoming' => '#b26a12',
                'completed' => '#94a3b8', 'draft' => '#94a3b8',
            ];
            $rankMap = ['ongoing' => 0, 'open' => 1, 'upcoming' => 2, 'draft' => 3, 'completed' => 4];

            $leagueRows[] = [
                'id' => (int) $league['id'],
                'name' => $league['league_name'],
                'sport' => $league['sport'],
                'max_team' => (int) $league['max_team'],
                'teams' => $teamCounts[(int) $league['id']] ?? 0,
                'start' => $startTs,
                'end' => $endTs,
                'status' => ucfirst($statusKey),
                'status_key' => $statusKey,
                'accent' => $accentMap[$statusKey] ?? '#2f6fed',
                'progress' => $progress,
                'is_draft' => $isDraft,
                'href' => ($isDraft ? 'edit_league.php?id=' : 'league_info.php?id=') . (int) $league['id'],
                'rank' => $rankMap[$statusKey] ?? 5,
            ];
        }
        $leagueListStmt->close();
    }
    usort($leagueRows, function ($a, $b) {
        if ($a['rank'] !== $b['rank']) return $a['rank'] <=> $b['rank'];
        return ($a['start'] ?? 0) <=> ($b['start'] ?? 0);
    });

    // ── Inbox: the three most recent notifications (read + unread).
    // The same rows the topbar bell lists; the full history lives on
    // notification.php behind the panel's "See all" link.
    $notifRows = [];
    $notifStmt = $conn->prepare(
        "SELECT id, type, title, message, related_id, is_read, created
           FROM tbl_notification
          WHERE user_id = ?
          ORDER BY created DESC
          LIMIT 3"
    );
    if ($notifStmt) {
        $notifStmt->bind_param('i', $organizerId);
        $notifStmt->execute();
        $notifResult = $notifStmt->get_result();
        while ($notifRow = $notifResult->fetch_assoc()) {
            $notifRows[] = $notifRow;
        }
        $notifStmt->close();
    }

    // Notification links are resolved per row further down via
    // organizerNotificationUrl(), which maps the stored related_id (team /
    // schedule / announcement) to the league id the organizer pages expect.

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
        'league_full'              => ['bi-people-fill', 'team'],
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





?>
    <?php include 'includes/dashboard-styles.php'; ?>
    <?php include 'includes/dashboard-home-styles.php'; ?>


    <body id="page-top" class="body">
        <?php
        include 'includes/bar.php';


        ?>
        <div class="container-fluid" style="padding-top:20px;">
            <div class="row p-3">
                <div class="og-home">

                    <!-- ── Hero deck ─────────────────────────────────────── -->
                    <?php
                    $heroEyebrow  = 'Organizer / Season desk';
                    $heroTitle    = htmlspecialchars($greeting) . ', <span>' . htmlspecialchars(explode(' ', trim((string) $full))[0] ?: 'Organizer') . '</span>.';
                    $heroSubtitle = 'A quick read on your leagues, registrations and the road ahead.';
                    $heroIcon     = 'bi-speedometer2';
                    $heroLabel    = 'Organizer overview';
                    $heroSide     = '<div class="og-datecard" aria-hidden="true">'
                        . '<span class="og-date-dow">' . date('D', $todayTs) . '</span>'
                        . '<span class="og-date-day">' . date('j', $todayTs) . '</span>'
                        . '<span class="og-date-my">' . date('M Y', $todayTs) . '</span>'
                        . '</div>'
                        . '<div class="og-clock">'
                        . '<span class="og-clock-label"><span class="og-live-dot" aria-hidden="true"></span>Local time</span>'
                        . '<span class="og-clock-time" id="clockTime">--:--:--</span>'
                        . '</div>';
                    include 'includes/page-hero.php';
                    ?>

                    <!-- ── Scoreboard strip ──────────────────────────────── -->
                    <div class="section-head dash-animate dash-animate-d1">
                        <div>
                            <span class="section-eyebrow">Overview</span>
                            <h2 class="section-title">Season at a glance</h2>
                        </div>
                        <span class="section-meta"><span class="section-live-dot" aria-hidden="true"></span>Live stats</span>
                    </div>
                    <div class="og-board dash-animate dash-animate-d1">
                        <a class="og-metric" href="league.php" style="--og-accent:#09173c">
                            <span class="og-metric-index" aria-hidden="true">01</span>
                            <span class="og-metric-label"><i class="bi bi-trophy" aria-hidden="true"></i>Total leagues</span>
                            <span class="og-metric-value" id="statTotalLeagues" data-count="<?= (int) $total_leagues ?>"><?= (int) $total_leagues ?></span>
                            <span class="og-metric-note">In your workspace</span>
                            <i class="bi bi-arrow-up-right og-metric-go" aria-hidden="true"></i>
                        </a>
                        <a class="og-metric" href="league.php" style="--og-accent:#137a4e">
                            <span class="og-metric-index" aria-hidden="true">02</span>
                            <span class="og-metric-label"><i class="bi bi-broadcast" aria-hidden="true"></i>Ongoing leagues</span>
                            <span class="og-metric-value" id="statOngoingLeagues" data-count="<?= (int) $ongoing_leagues ?>"><?= (int) $ongoing_leagues ?></span>
                            <span class="og-metric-note">Running today</span>
                            <i class="bi bi-arrow-up-right og-metric-go" aria-hidden="true"></i>
                        </a>
                        <a class="og-metric" href="league.php" style="--og-accent:#b26a12">
                            <span class="og-metric-index" aria-hidden="true">03</span>
                            <span class="og-metric-label"><i class="bi bi-hourglass-split" aria-hidden="true"></i>Pending teams</span>
                            <span class="og-metric-value" id="statPendingTeams" data-count="<?= (int) $pending_leagues ?>"><?= (int) $pending_leagues ?></span>
                            <span class="og-metric-note">Awaiting your review</span>
                            <i class="bi bi-arrow-up-right og-metric-go" aria-hidden="true"></i>
                        </a>
                        <a class="og-metric" href="league.php" style="--og-accent:#2f6fed">
                            <span class="og-metric-index" aria-hidden="true">04</span>
                            <span class="og-metric-label"><i class="bi bi-calendar-event" aria-hidden="true"></i>Upcoming leagues</span>
                            <span class="og-metric-value" id="statUpcomingLeagues" data-count="<?= (int) $incoming_leagues ?>"><?= (int) $incoming_leagues ?></span>
                            <span class="og-metric-note">Not started yet</span>
                            <i class="bi bi-arrow-up-right og-metric-go" aria-hidden="true"></i>
                        </a>
                    </div>


                    <!-- ── League desk + season timeline ─────────────────── -->
                    <div class="og-grid dashboard-layout-row">
                        <div class="og-col-leagues og-stack">
                            <section class="og-panel dash-animate dash-animate-d3" aria-labelledby="ogLeaguesTitle">
                                <div class="og-panel-head">
                                    <div>
                                        <span class="og-panel-eyebrow">League desk</span>
                                        <h2 class="og-panel-title" id="ogLeaguesTitle">Your leagues</h2>
                                    </div>
                                    <a href="league.php" class="og-panel-link is-accent">Manage <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                                </div>
                                <?php if (!empty($leagueRows)): ?>
                                <div class="og-league-list">
                                    <?php foreach ($leagueRows as $index => $leagueRow): ?>
                                    <a class="og-league" href="<?= htmlspecialchars($leagueRow['href']) ?>" style="--og-accent:<?= htmlspecialchars($leagueRow['accent']) ?>">
                                        <span class="og-league-idx" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                        <span class="og-league-main">
                                            <span class="og-league-name"><?= htmlspecialchars($leagueRow['name']) ?></span>
                                            <span class="og-league-meta">
                                                <span class="og-sport"><?= htmlspecialchars((string) $leagueRow['sport']) ?></span>
                                                <span class="og-sep" aria-hidden="true"></span>
                                                <span><?= (int) $leagueRow['teams'] ?><?= (int) $leagueRow['max_team'] > 0 ? '/' . (int) $leagueRow['max_team'] : '' ?> teams</span>
                                                <?php if ($leagueRow['start'] && $leagueRow['end']): ?>
                                                <span class="og-sep" aria-hidden="true"></span>
                                                <span><?= date('M j', $leagueRow['start']) ?> – <?= date('M j', $leagueRow['end']) ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="og-track" aria-hidden="true"><span class="og-track-fill" style="width:<?= (int) $leagueRow['progress'] ?>%"></span></span>
                                        </span>
                                        <span class="og-status is-<?= htmlspecialchars($leagueRow['status_key']) ?>"><?= htmlspecialchars($leagueRow['status']) ?></span>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="og-empty">
                                    <i class="bi bi-flag" aria-hidden="true"></i>
                                    <span class="og-empty-title">No leagues in your desk yet</span>
                                    <span class="og-empty-sub">Create a league to start tracking registrations, schedules and milestones here.</span>
                                    <a class="og-empty-cta" href="league.php"><i class="bi bi-plus-lg" aria-hidden="true"></i>Create a league</a>
                                </div>
                                <?php endif; ?>
                            </section>

                            <?php $notifUnread = (int) ($_bar_unread ?? 0); ?>
                            <section class="og-panel dash-animate dash-animate-d4" aria-labelledby="ogNotifTitle">
                                <div class="og-panel-head">
                                    <div>
                                        <span class="og-panel-eyebrow">Inbox</span>
                                        <h2 class="og-panel-title" id="ogNotifTitle">Notifications</h2>
                                    </div>
                                    <div class="og-notif-tools">
                                        <?php if ($notifUnread > 0): ?>
                                            <span class="og-panel-count"><?= $notifUnread > 99 ? '99+' : $notifUnread ?> unread</span>
                                            <button type="button" class="og-panel-link og-notif-readall" id="notifMarkAll">Mark all read</button>
                                        <?php else: ?>
                                            <span class="og-panel-count">All caught up</span>
                                        <?php endif; ?>
                                        <a class="og-panel-link is-accent og-notif-seeall" href="notification.php">See all <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                    </div>
                                </div>
                                <?php if (!empty($notifRows)): ?>
                                <div class="og-notif-list">
                                    <?php foreach ($notifRows as $notifRow):
                                        $notifType = (string) $notifRow['type'];
                                        [$notifIconClass, $notifTone] = $notifIcons[$notifType] ?? ['bi-bell-fill', 'default'];
                                        $notifHref = organizerNotificationUrl($conn, $notifType, (int) ($notifRow['related_id'] ?? 0));
                                        $notifIsRead = (int) $notifRow['is_read'] === 1;
                                    ?>
                                    <a class="og-notif<?= $notifIsRead ? '' : ' is-unread' ?>" href="<?= htmlspecialchars($notifHref) ?>" data-notif-id="<?= (int) $notifRow['id'] ?>">
                                        <span class="og-notif-icon is-<?= $notifTone ?>" aria-hidden="true"><i class="bi <?= $notifIconClass ?>"></i></span>
                                        <span class="og-notif-body">
                                            <span class="og-notif-title"><?= htmlspecialchars((string) $notifRow['title']) ?></span>
                                            <span class="og-notif-msg"><?= htmlspecialchars((string) $notifRow['message']) ?></span>
                                            <span class="og-notif-time"><?= htmlspecialchars($notifTime((string) $notifRow['created'])) ?></span>
                                        </span>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="og-empty">
                                    <i class="bi bi-bell-slash" aria-hidden="true"></i>
                                    <span class="og-empty-title">You're all caught up</span>
                                    
                                    
                                </div>
                                <?php endif; ?>
                            </section>
                        </div>

                        <div class="og-col-calendar">
                            <section class="og-panel og-cal organizer-calendar dash-animate dash-animate-d4" aria-label="Organizer calendar">
                                <div class="og-cal-head">
                                    <div>
                                        <span class="og-panel-eyebrow">Season timeline</span>
                                        <div class="og-cal-month" id="calendarMonth">&nbsp;</div>
                                    </div>
                                    <div class="og-cal-tools">
                                        <div class="og-cal-legend" aria-hidden="true">
                                            <span class="og-legend-item"><span class="og-legend-dot is-reg"></span>Registration</span>
                                            <span class="og-legend-item"><span class="og-legend-dot is-lg"></span>League</span>
                                        </div>
                                        <div class="og-cal-nav">
                                            <button type="button" class="og-navbtn" id="calendarPrev" aria-label="Previous month"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                                            <button type="button" class="og-todaybtn" id="calendarToday">Today</button>
                                            <button type="button" class="og-navbtn" id="calendarNext" aria-label="Next month"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                                            <button type="button" class="og-navbtn calendar-expand-btn" id="calendarExpand" aria-label="Expand calendar" title="Expand calendar"><i class="bi bi-arrows-fullscreen" aria-hidden="true"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="calendar-weekdays" aria-hidden="true">
                                    <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                                </div>
                                <div class="calendar-grid" id="calendarGrid" aria-label="League milestones by day"></div>
                            </section>
                        </div>
                    </div><!-- /.og-grid -->
                </div><!-- /.og-home -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->






                    <?php
                    include 'includes/footer.php'; ?>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Live Clock
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

                        // Animated Count-Up
                        document.querySelectorAll('.og-metric-value[data-count]').forEach(function(el) {
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
                        (() => {
                            const statElements = {
                                total_leagues: document.getElementById('statTotalLeagues'),
                                ongoing_leagues: document.getElementById('statOngoingLeagues'),
                                pending_teams: document.getElementById('statPendingTeams'),
                                upcoming_leagues: document.getElementById('statUpcomingLeagues')
                            };
                            let requestInProgress = false;

                            async function refreshOrganizerStats() {
                                if (requestInProgress || document.hidden) return;
                                requestInProgress = true;
                                try {
                                    const response = await fetch('dashboard.php?live_stats=1', {
                                        credentials: 'same-origin',
                                        cache: 'no-store',
                                        headers: { 'Accept': 'application/json' }
                                    });
                                    if (!response.ok) throw new Error('Unable to load organizer statistics.');
                                    const payload = await response.json();
                                    if (!payload.success || !payload.stats) return;

                                    Object.keys(statElements).forEach(key => {
                                        const element = statElements[key];
                                        if (!element || typeof payload.stats[key] === 'undefined') return;
                                        const nextValue = String(payload.stats[key]);
                                        if (element.textContent.trim() !== nextValue) {
                                            element.textContent = nextValue;
                                            element.animate(
                                                [{ transform:'scale(1)', color:'#2563eb' }, { transform:'scale(1.12)' }, { transform:'scale(1)', color:'#09173c' }],
                                                { duration:450, easing:'ease-out' }
                                            );
                                        }
                                    });
                                } catch (error) {
                                    console.warn(error.message);
                                } finally {
                                    requestInProgress = false;
                                }
                            }

                            refreshOrganizerStats();
                            window.setInterval(refreshOrganizerStats, 10000);
                            document.addEventListener('visibilitychange', () => {
                                if (!document.hidden) refreshOrganizerStats();
                            });
                        })();
                    </script>
                    <script>
                        (() => {
                            const events = <?= json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                            const grid = document.getElementById('calendarGrid');
                            const monthLabel = document.getElementById('calendarMonth');
                            let displayedMonth = new Date();
                            displayedMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth(), 1);
                            const key = date => `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
                            const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
                            function showEvent(event) {
                                const eventDetails = `<div class="text-start"><strong>${escapeHtml(event.title)}</strong></div>`;
                                Swal.fire({title:'League Timeline',html:eventDetails,showCancelButton:true,confirmButtonText:'Open league',cancelButtonText:'Close'}).then(result => { if(result.isConfirmed && event.url) location.href=event.url; });
                            }
                            function renderCalendar() {
                                grid.innerHTML = '';
                                monthLabel.textContent = displayedMonth.toLocaleDateString(undefined,{month:'long',year:'numeric'});
                                const first = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth(), 1);
                                const cursor = new Date(first); cursor.setDate(cursor.getDate()-first.getDay());
                                const todayKey = key(new Date());
                                for(let day=0; day<42; day++) {
                                    const date = new Date(cursor); date.setDate(cursor.getDate()+day);
                                    const cell = document.createElement('div');
                                    cell.className = 'calendar-day' + (date.getMonth()!==displayedMonth.getMonth()?' is-outside':'') + (key(date)===todayKey?' is-today':'');
                                    cell.innerHTML = `<span class="calendar-date">${date.getDate()}</span>`;
                                    const dayEvents = events.filter(event => event.date===key(date));
                                    dayEvents.slice(0,3).forEach(event => {
                                        const button=document.createElement('button'); button.type='button'; button.className=`calendar-event event-${event.type}`;
                                        button.textContent=(event.time?event.time+' ':'')+event.title; button.title=button.textContent; button.addEventListener('click',()=>showEvent(event)); cell.appendChild(button);
                                    });
                                    if(dayEvents.length>3) {
                                        const more=document.createElement('div'); more.className='calendar-more'; more.textContent=`+${dayEvents.length-3} more`; cell.appendChild(more);
                                    }
                                    grid.appendChild(cell);
                                }
                            }
                            document.getElementById('calendarPrev').addEventListener('click',()=>{ displayedMonth=new Date(displayedMonth.getFullYear(),displayedMonth.getMonth()-1,1); renderCalendar(); });
                            document.getElementById('calendarNext').addEventListener('click',()=>{ displayedMonth=new Date(displayedMonth.getFullYear(),displayedMonth.getMonth()+1,1); renderCalendar(); });
                            document.getElementById('calendarToday').addEventListener('click',()=>{ const now=new Date(); displayedMonth=new Date(now.getFullYear(),now.getMonth(),1); renderCalendar(); });
                            renderCalendar();

                            // Expand / collapse toggle
                            const expandBtn = document.getElementById('calendarExpand');
                            const layoutRow = document.querySelector('.dashboard-layout-row');
                            const calendarEl = document.querySelector('.organizer-calendar');
                            if (expandBtn && layoutRow && calendarEl) {
                                expandBtn.addEventListener('click', function() {
                                    const isExpanded = calendarEl.classList.toggle('is-expanded');
                                    layoutRow.classList.toggle('expanded', isExpanded);
                                    const icon = expandBtn.querySelector('i');
                                    if (icon) {
                                        icon.className = isExpanded ? 'bi bi-arrows-angle-contract' : 'bi bi-arrows-fullscreen';
                                    }
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
                            var pill = document.querySelector('.og-notif-tools .og-panel-count');
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

                            document.querySelectorAll('.og-notif.is-unread').forEach(function (item) {
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
                                        document.querySelectorAll('.og-notif.is-unread').forEach(function (item) {
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
                    <script>
                        $(document).on("submit", "#approveForm", function(e) {
                            e.preventDefault();
                            var formData = new FormData(this);
                            formData.append("approveOrder", true);
                            Swal.fire({
                                title: "Are you sure to approve the order?",
                                showDenyButton: false,
                                showCancelButton: true,
                                confirmButtonText: "Yes",
                            }).then((result) => {
                                /* Read more about isConfirmed, isDenied below */
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: "action/transactionAction.php",
                                        type: "post",
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        success: function(response) {
                                            var res = jQuery.parseJSON(response);
                                            if (res.status == 400) {
                                                Swal.fire({
                                                    icon: "error",
                                                    title: "Something Went Wrong.",
                                                    text: res.msg,
                                                    timer: 10000,
                                                });
                                            } else if (res.status == 201) {
                                                Swal.fire({
                                                    icon: "success",
                                                    title: "SUCCESS",
                                                    text: res.msg,
                                                    timer: 2000,
                                                }).then(function() {
                                                    location.reload();
                                                });
                                            }
                                        },
                                    });
                                } else if (result.isDenied) {
                                    Swal.fire("Changes are not saved", "", "info").then(function() {
                                        location.reload();
                                    });
                                }
                            });
                        });
                        $(document).on("submit", "#markasDoneForm", function(e) {
                            e.preventDefault();
                            var formData = new FormData(this);
                            formData.append("completeOrder", true);
                            Swal.fire({
                                title: "Are you sure to mark as done the transaction?",
                                showDenyButton: false,
                                showCancelButton: true,
                                confirmButtonText: "Yes",
                            }).then((result) => {
                                /* Read more about isConfirmed, isDenied below */
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: "action/transactionAction.php",
                                        type: "post",
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        success: function(response) {
                                            var res = jQuery.parseJSON(response);
                                            if (res.status == 400) {
                                                Swal.fire({
                                                    icon: "error",
                                                    title: "Something Went Wrong.",
                                                    text: res.msg,
                                                    timer: 10000,
                                                });
                                            } else if (res.status == 201) {
                                                Swal.fire({
                                                    icon: "success",
                                                    title: "SUCCESS",
                                                    text: res.msg,
                                                    timer: 2000,
                                                }).then(function() {
                                                    location.reload();
                                                });
                                            }
                                        },
                                    });
                                } else if (result.isDenied) {
                                    Swal.fire("Changes are not saved", "", "info").then(function() {
                                        location.reload();
                                    });
                                }
                            });
                        });
                    </script>

    </body>

    </html>
