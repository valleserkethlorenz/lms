<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../includes/summaryStrip.php';
requireRole([4]);
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();

$msg = '';
// The match workspace lives on its own page. Old deep links (notifications,
// bookmarks) are redirected there so the register stays a clean list.
$viewId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($viewId > 0) {
    header('Location: manage_match.php?id=' . $viewId);
    exit;
}
// Fetch all assigned matches
$matches = [];
$stmt = $conn->prepare("
    SELECT s.id, l.league_name, ta.team_name AS team_a_name, tb.team_name AS team_b_name,
           s.date, s.time, s.venue, s.status, s.a_score, s.b_score, s.livestream_url, s.livestream_status
    FROM tbl_schedule s
    JOIN tbl_league l ON l.id = s.league_id
    JOIN tbl_team ta ON ta.id = s.team_a
    JOIN tbl_team tb ON tb.id = s.team_b
    WHERE s.committee_user_id = ? AND s.published_at IS NOT NULL
    ORDER BY s.date DESC, s.time DESC
");
$stmt->bind_param('i', $userId); $stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $matches[] = $r;
$stmt->close();

include 'includes/header.php';
?>
<body id="page-top" class="body committee-matches">
<?php include 'includes/bar.php'; ?>

<?php include 'includes/matches-page-styles.php'; ?>
<?php include_once __DIR__ . '/../../includes/summary-strip-styles.php'; ?>
<div class="container-fluid" style="padding-top:20px;">
<div class="row p-3">
    <div class="mt-page w-100">

        <?php
        $matchCompleted = count(array_filter($matches, static fn($match) => strtolower((string) $match['status']) === 'completed'));
        $matchScheduled = count(array_filter($matches, static fn($match) => in_array(strtolower((string) $match['status']), ['scheduled', 'upcoming'], true)));
        $matchToday     = count(array_filter($matches, static fn($match) => (string) $match['date'] === date('Y-m-d')));

        // Open fixtures — completed and cancelled rows never surface as "next".
        $cmtOpenMatches = array_values(array_filter($matches, static function ($match) {
            return !in_array(strtolower((string) $match['status']), ['completed', 'cancelled'], true);
        }));
        usort($cmtOpenMatches, static function ($a, $b) {
            return strcmp($a['date'] . ' ' . $a['time'], $b['date'] . ' ' . $b['time']);
        });
        $cmtUpcomingMatches = array_values(array_filter($cmtOpenMatches, static fn($match) => $match['date'] >= date('Y-m-d')));
        $cmtNextMatch       = $cmtUpcomingMatches[0] ?? $cmtOpenMatches[0] ?? null;
        $cmtOpenCount       = count($cmtOpenMatches);
        $cmtLiveCount       = count(array_filter($matches, static fn($match) => strtolower((string) ($match['livestream_status'] ?? '')) === 'live'));

        // Two-letter monogram used by the scoreboard header and register rows.
        $cmtTeamInitials = static function ($name): string {
            $name = trim((string) $name);
            if ($name === '') {
                return '--';
            }
            $parts = preg_split('/\s+/', $name) ?: [];
            if (count($parts) === 1) {
                $word = function_exists('mb_substr') ? mb_substr($parts[0], 0, 2) : substr($parts[0], 0, 2);
                return function_exists('mb_strtoupper') ? mb_strtoupper($word) : strtoupper($word);
            }
            $first = function_exists('mb_substr') ? mb_substr($parts[0], 0, 1) : substr($parts[0], 0, 1);
            $lastWord = $parts[count($parts) - 1];
            $last  = function_exists('mb_substr') ? mb_substr($lastWord, 0, 1) : substr($lastWord, 0, 1);
            return function_exists('mb_strtoupper') ? mb_strtoupper($first . $last) : strtoupper($first . $last);
        };

        // Schedule status -> pill modifier.
        $cmtStatusTone = static function ($status): string {
            return match (strtolower(trim((string) $status))) {
                'completed' => 'is-completed',
                'cancelled' => 'is-cancelled',
                'postponed' => 'is-postponed',
                'live', 'ongoing' => 'is-live',
                'scheduled', 'upcoming' => 'is-scheduled',
                default => 'is-default',
            };
        };

        // Livestream status -> dot modifier.
        $cmtStreamTone = static function ($status): string {
            return match (strtolower(trim((string) $status))) {
                'live' => 'is-live',
                'scheduled' => 'is-scheduled',
                'ended' => 'is-ended',
                default => 'is-none',
            };
        };

        $cmtStatusLabel = static function ($status): string {
            $label = trim((string) $status);
            return $label !== '' ? ucfirst($label) : 'Scheduled';
        };
        ?>

        <div class="mt-section-head">
            <span class="mt-section-title">Assignment overview</span>
        </div>

        <?php renderSummaryStrip([
            ['label' => 'Assigned matches', 'value' => count($matches),   'note' => 'Published assignments'],
            ['label' => 'Scheduled',        'value' => $matchScheduled,   'note' => 'Scheduled or upcoming'],
            ['label' => 'Completed',        'value' => $matchCompleted,   'note' => 'Finished matches'],
            ['label' => 'Today',            'value' => $matchToday,       'note' => 'Matches dated today'],
        ], 'Assigned match totals'); ?>



        <section class="mt-register" id="matchRegister" aria-label="Assigned matches register">
            <header class="mt-register-head">
                <div>
                    <span class="mt-eyebrow">Match register</span>
                    <h2 class="mt-register-title">Assigned matches <span class="mt-register-count"><?= count($matches) ?></span></h2>
                    
                </div>
                <div class="mt-view-switch" role="group" aria-label="Register view">
                    <button type="button" class="mt-view-btn is-active" id="cmtBtnTable" aria-pressed="true"><i class="bi bi-table" aria-hidden="true"></i>Table</button>
                    <button type="button" class="mt-view-btn" id="cmtBtnCalendar" aria-pressed="false"><i class="bi bi-calendar3" aria-hidden="true"></i>Calendar</button>
                </div>
            </header>

            <div class="mt-toolbar" id="cmtToolbar">
                <div class="mt-search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="cmtSearch" placeholder="Search fixtures, leagues or venues" aria-label="Search assigned matches">
                </div>
                <select id="cmtStatusFilter" class="form-select" aria-label="Filter matches by status">
                    <option value="all">All statuses</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="completed">Completed</option>
                    <option value="postponed">Postponed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <span class="mt-toolbar-meta" id="cmtFilterMeta"><?= count($matches) ?> fixture<?= count($matches) === 1 ? '' : 's' ?></span>
            </div>

            <div id="cmtTableWrapper">
                <div class="mt-table-wrap">
                    <table class="mt-table">
                        <thead>
                            <tr>
                                <th scope="col" class="mt-col-num">#</th>
                                <th scope="col">Fixture</th>
                                <th scope="col">League</th>
                                <th scope="col">Schedule</th>
                                <th scope="col">Venue</th>
                                <th scope="col">Status</th>
                                <th scope="col">Broadcast</th>
                                <th scope="col" class="mt-col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody id="cmtRegisterBody">
                        <?php foreach ($matches as $i => $m):
                            $cmtStatusKey  = strtolower(trim((string) $m['status']));
                            $cmtRowStatus  = $cmtStatusLabel($m['status']);
                            $cmtVenueText  = trim((string) ($m['venue'] ?? '')) !== '' ? $m['venue'] : 'To be announced';
                            $cmtStreamState = trim((string) ($m['livestream_status'] ?? ''));
                            $cmtSearchBlob = implode(' ', [
                                $m['league_name'], $m['team_a_name'], $m['team_b_name'],
                                (string) ($m['venue'] ?? ''), (string) $m['status'], $cmtStreamState,
                                date('M j Y', strtotime($m['date'])), date('g:i A', strtotime($m['time'])),
                            ]);
                            $cmtSearchBlob = function_exists('mb_strtolower') ? mb_strtolower($cmtSearchBlob) : strtolower($cmtSearchBlob);
                        ?>

                            <tr data-match-row data-status="<?= htmlspecialchars($cmtStatusKey, ENT_QUOTES) ?>" data-search="<?= htmlspecialchars($cmtSearchBlob, ENT_QUOTES) ?>">
                                <td class="mt-num"><?= sprintf('%02d', $i + 1) ?></td>
                                <td>
                                    <div class="mt-fixture">
                                        <span class="mt-fixture-team">
                                            <span class="mt-fixture-crest" aria-hidden="true"><?= htmlspecialchars($cmtTeamInitials($m['team_a_name'])) ?></span>
                                            <span class="mt-fixture-name"><?= htmlspecialchars($m['team_a_name']) ?></span>
                                        </span>
                                        <span class="mt-fixture-team is-b">
                                            <span class="mt-fixture-crest" aria-hidden="true"><?= htmlspecialchars($cmtTeamInitials($m['team_b_name'])) ?></span>
                                            <span class="mt-fixture-name"><?= htmlspecialchars($m['team_b_name']) ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td><span class="mt-league"><?= htmlspecialchars($m['league_name']) ?></span></td>
                                <td>
                                    <div class="mt-when">
                                        <strong><?= date('M j, Y', strtotime($m['date'])) ?></strong>
                                        <span><?= date('g:i A', strtotime($m['time'])) ?></span>
                                    </div>
                                </td>
                                <td><span class="mt-venue"><i class="bi bi-geo-alt" aria-hidden="true"></i><span><?= htmlspecialchars($cmtVenueText) ?></span></span></td>
                                <td><span class="mt-status <?= $cmtStatusTone($m['status']) ?>"><?= htmlspecialchars($cmtRowStatus) ?></span></td>
                                <td>
                                    <?php if (!empty($m['livestream_url'])): ?>
                                    <span class="mt-stream-state <?= $cmtStreamTone($cmtStreamState) ?>">
                                        <span class="mt-stream-dot" aria-hidden="true"></span><?= htmlspecialchars($cmtStreamState !== '' ? $cmtStreamState : 'Ready') ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="mt-stream-none">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td class="mt-col-action">
                                    <a href="manage_match.php?id=<?= (int) $m['id'] ?>" class="mt-row-btn" aria-label="Manage <?= htmlspecialchars($m['team_a_name']) ?> versus <?= htmlspecialchars($m['team_b_name']) ?>">Manage<i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$matches): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="mt-empty">
                                        <i class="bi bi-calendar2-x" aria-hidden="true"></i>
                                        <span class="mt-empty-title">No matches assigned yet</span>
                                        <span class="mt-empty-sub">New fixtures appear here as soon as the organizer publishes them to you.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                            <tr id="cmtNoResults" hidden>
                                <td colspan="8">
                                    <div class="mt-empty">
                                        <i class="bi bi-search" aria-hidden="true"></i>
                                        <span class="mt-empty-title">No matches match your filters</span>
                                        <span class="mt-empty-sub">Try a different search term or status.</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="cmtCalendarWrapper" class="mt-calendar" hidden>
                <div class="mt-cal-legend" aria-label="Calendar legend">
                    <span><span class="mt-cal-dot is-scheduled" aria-hidden="true"></span>Scheduled</span>
                    <span><span class="mt-cal-dot is-completed" aria-hidden="true"></span>Completed</span>
                    <span><span class="mt-cal-dot is-postponed" aria-hidden="true"></span>Postponed</span>
                    <span><span class="mt-cal-dot is-cancelled" aria-hidden="true"></span>Cancelled</span>
                    <span><span class="mt-cal-dot is-live" aria-hidden="true"></span>Live</span>
                </div>
                <div id="committeeCalendar"></div>
            </div>
        </section>

    </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>


<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/fullcalendar/6.1.10/index.global.min.js"></script>
<script>
var cmtMatches = <?php echo json_encode(array_map(function ($m) {
    return [
        'id'                => (int) $m['id'],
        'team_a'            => $m['team_a_name'],
        'team_b'            => $m['team_b_name'],
        'league'            => $m['league_name'],
        'date'              => $m['date'],
        'time'              => substr((string) $m['time'], 0, 8),
        'venue'             => $m['venue'] ?? '',
        'status'            => $m['status'],
        'livestream'        => $m['livestream_url'] ?? '',
        'livestream_status' => $m['livestream_status'] ?? '',
    ];
}, $matches), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

var cmtCalendarInstance = null;

(function () {
    'use strict';

    var tableWrap  = document.getElementById('cmtTableWrapper');
    var calendarWrap = document.getElementById('cmtCalendarWrapper');
    var btnTable   = document.getElementById('cmtBtnTable');
    var btnCalendar = document.getElementById('cmtBtnCalendar');
    var toolbar    = document.getElementById('cmtToolbar');

    function setRegisterView(view) {
        if (!tableWrap || !calendarWrap || !btnTable || !btnCalendar) return;
        var isCalendar = view === 'calendar';
        tableWrap.hidden = isCalendar;
        calendarWrap.hidden = !isCalendar;
        if (toolbar) toolbar.hidden = isCalendar;
        btnTable.classList.toggle('is-active', !isCalendar);
        btnCalendar.classList.toggle('is-active', isCalendar);
        btnTable.setAttribute('aria-pressed', String(!isCalendar));
        btnCalendar.setAttribute('aria-pressed', String(isCalendar));
        if (isCalendar) {
            if (!cmtCalendarInstance) {
                initCommitteeCalendar();
            } else {
                cmtCalendarInstance.render();
                fixCalendarIcons();
            }
            window.setTimeout(function () {
                if (cmtCalendarInstance) cmtCalendarInstance.updateSize();
            }, 30);
        }
    }

    if (btnTable) btnTable.addEventListener('click', function () { setRegisterView('table'); });
    if (btnCalendar) btnCalendar.addEventListener('click', function () { setRegisterView('calendar'); });

    // Client-side register filter: search across fixture, league, venue and date.
    var search         = document.getElementById('cmtSearch');
    var statusFilter   = document.getElementById('cmtStatusFilter');
    var registerBody   = document.getElementById('cmtRegisterBody');
    var noResults      = document.getElementById('cmtNoResults');
    var filterMeta     = document.getElementById('cmtFilterMeta');

    if (search && statusFilter && registerBody) {
        var rows  = Array.prototype.slice.call(registerBody.querySelectorAll('tr[data-match-row]'));
        var total = rows.length;

        var applyFilters = function () {
            var term   = search.value.trim().toLowerCase();
            var wanted = statusFilter.value;
            var shown  = 0;

            rows.forEach(function (row) {
                var hay = row.getAttribute('data-search') || '';
                var statusOk = wanted === 'all' || row.getAttribute('data-status') === wanted;
                var termOk = term === '' || hay.indexOf(term) !== -1;
                var show = statusOk && termOk;
                row.hidden = !show;
                if (show) shown++;
            });

            if (noResults) noResults.hidden = !(shown === 0 && total > 0);
            if (filterMeta) {
                filterMeta.textContent = (term !== '' || wanted !== 'all')
                    ? shown + ' of ' + total + ' shown'
                    : total + ' fixture' + (total === 1 ? '' : 's');
            }
        };

        search.addEventListener('input', applyFilters);
        statusFilter.addEventListener('change', applyFilters);
        applyFilters();
    }


    function fixCalendarIcons() {
        var prevIcon = document.querySelector('#committeeCalendar .fc-prev-button .fc-icon');
        var nextIcon = document.querySelector('#committeeCalendar .fc-next-button .fc-icon');
        if (prevIcon) prevIcon.innerHTML = '<i class="bi bi-chevron-left"></i>';
        if (nextIcon) nextIcon.innerHTML = '<i class="bi bi-chevron-right"></i>';
    }

    function initCommitteeCalendar() {
        var calEl = document.getElementById('committeeCalendar');
        if (!calEl || typeof FullCalendar === 'undefined') return;

        var statusColors = {
            completed: '#137a4e',
            scheduled: '#2f6fed',
            upcoming:  '#2f6fed',
            postponed: '#b26a12',
            cancelled: '#b91c1c'
        };

        var events = cmtMatches.map(function (m) {
            var state = String(m.status || '').toLowerCase();
            var isLive = String(m.livestream_status || '').toLowerCase() === 'live';
            var color = isLive ? '#dc2626' : (statusColors[state] || '#6f7a8d');
            return {
                id: m.id,
                title: (isLive ? 'LIVE \u00b7 ' : '') + m.team_a + ' vs ' + m.team_b,
                start: m.date + 'T' + m.time,
                backgroundColor: color,
                borderColor: color,
                extendedProps: m
            };
        });

        cmtCalendarInstance = new FullCalendar.Calendar(calEl, {
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
            height: 'auto',
            dayMaxEvents: 3,
            events: events,
            eventDidMount: function (info) {
                var venue = info.event.extendedProps && info.event.extendedProps.venue ? info.event.extendedProps.venue : '';
                info.el.setAttribute('title', info.event.title + (venue ? ' - ' + venue : ''));
            },
            eventClick: function (info) {
                window.location.href = 'manage_match.php?id=' + info.event.extendedProps.id;
            }
        });

        cmtCalendarInstance.render();
        fixCalendarIcons();
    }

    setRegisterView('table');
})();
</script>
</body></html>

