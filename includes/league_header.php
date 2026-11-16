<?php
// Ensure DB connection and session are available
require_once __DIR__ . '/../config/dbconnect.php';
require_once __DIR__ . '/../config/leagueStatus.php';
require_once __DIR__ . '/storageService.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$loggin = $_SESSION['loggin'] ?? false;
$currentRole = (int)($_SESSION['lms_role'] ?? 0);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$leagueStmt = mysqli_prepare($conn, "SELECT l.*, CONCAT(IFNULL(u.fname, ''), ' ', IFNULL(u.lname, '')) AS organizer_name FROM tbl_league l LEFT JOIN tbl_user u ON l.created_by = u.id WHERE l.id=? AND l.publication_status='Published' AND (l.is_archived=0 OR l.is_archived IS NULL) LIMIT 1");
mysqli_stmt_bind_param($leagueStmt, 'i', $id);
mysqli_stmt_execute($leagueStmt);
$league_res = mysqli_stmt_get_result($leagueStmt);
$league_row = mysqli_fetch_assoc($league_res);
mysqli_stmt_close($leagueStmt);
if (!$league_row) {
    http_response_code(404);
    header('Location: /lms/leagues.php');
    exit;
}
$userId = (int) ($_SESSION['lms_id'] ?? 0);

// Registration rules now live in one place so the hero button and the
// dedicated registration page (app/tr/register_team.php) can never disagree.
require_once __DIR__ . '/registrationGate.php';
// Server reference clock, captured once so the debug dump and the countdown
// script below always describe the same instant (registrationGate.php keeps
// its own copy internally and does not expose it).
$nowTs             = time();
$registrationGate  = getRegistrationGate($conn, (int) $id, $userId);

$leagueStatus      = $registrationGate['leagueStatus'];
$leagueStatusLabel = $registrationGate['leagueStatusLabel'];
$leagueStatusPill  = $registrationGate['leagueStatusPill'];
$start_date = date("F d", strtotime($league_row['start_date']));
$end_date = date("F d, Y", strtotime($league_row['end_date']));

/* -------------------------------------------------------------------------
   Registration window + slot state. Kept under the original variable names
   so the hero markup and the countdown script below stay untouched.
   ------------------------------------------------------------------------- */
$regOpen                   = $registrationGate['regOpen'];
$regStartTs                = $registrationGate['regStartTs'];
$regEndTs                  = $registrationGate['regEndTs'];
$registrationUpcoming      = $registrationGate['registrationUpcoming'];
$registrationLabel         = $registrationGate['registrationLabel'];
$registeredCount           = $registrationGate['registeredCount'];
$maxTeam                   = $registrationGate['maxTeam'];
$slotsFull                 = $registrationGate['slotsFull'];
$hasApplication            = $registrationGate['hasApplication'];
$existingApplicationStatus = $registrationGate['existingApplicationStatus'];

/* Hero artwork */
$heroImgName = (string)($league_row['league_image'] ?? '');
if (StorageService::isCloudinary($heroImgName)) {
    $heroImgSrc = $heroImgName;
} else {
    $heroImgSrc = 'assets/uploads/' . basename($heroImgName);
    if ($heroImgName === '' || !is_file(__DIR__ . '/../' . $heroImgSrc)) {
        $heroImgSrc = 'assets/images/default-league.svg';
    }
}

/* Hero facts */
$heroDate = static function ($value) {
    $ts = trim((string) $value) !== '' ? strtotime((string) $value) : false;
    return $ts ? date('M d, Y', $ts) : '—';
};
$heroLeagueDates = $heroDate($league_row['start_date'] ?? '') . ' – ' . $heroDate($league_row['end_date'] ?? '');
$heroRegisDates  = $heroDate($league_row['regis_start'] ?? '') . ' – ' . $heroDate($league_row['regis_end'] ?? '');
$heroSport       = trim((string)($league_row['sport'] ?? ''))    ?: '—';
$heroDivision    = trim((string)($league_row['division'] ?? '')) ?: '—';
$heroCategory    = trim((string)($league_row['category'] ?? '')) ?: '—';
$heroVenue       = trim((string)($league_row['venue'] ?? ''));

// Team registration is a dedicated page now (app/tr/register_team.php); the
// #regisTeam modal was removed in favour of it. Authentication also has
// dedicated pages now, so the login/sign-up modals are no longer included.

// Shared public league design layer (breadcrumb / hero / pills / tabs / panels)
require_once __DIR__ . '/league-view-styles.php';

$page             = basename($_SERVER['PHP_SELF']);
$safeId           = htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8');
$leagueBackUrl    = $currentRole === 2 ? 'app/tr/browse_leagues.php' : 'leagues.php';
$leagueBackLabel  = $currentRole === 2 ? 'Browse League' : 'All leagues';
$leagueNameSafe   = htmlspecialchars((string)($league_row['league_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$leagueDetailTabs = [
    ['page' => 'team_information.php', 'label' => 'Overview',     'icon' => 'bi-layout-text-window-reverse'],
    ['page' => 'participant.php',      'label' => 'Participants', 'icon' => 'bi-people'],
    ['page' => 'schedule.php',         'label' => 'Schedule',     'icon' => 'bi-calendar-week'],
    ['page' => 'standing.php',         'label' => 'Standings',    'icon' => 'bi-list-ol'],
];
// Sub-pages get the organizer trail: hub › league name (link) › current page.
$leaguePageLabels  = array_column($leagueDetailTabs, 'label', 'page');
$leagueCurrentLabel = $leaguePageLabels[$page] ?? '';
?>
<div class="container-fluid lp-page lms-league-page">
    <nav class="lp-crumbs" aria-label="Breadcrumb">
        <a href="<?= htmlspecialchars($leagueBackUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-arrow-left"></i> <?= htmlspecialchars($leagueBackLabel, ENT_QUOTES, 'UTF-8') ?></a>
        <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
        <?php if ($page !== 'team_information.php' && $leagueCurrentLabel !== '') : ?>
            <a href="team_information.php?id=<?= $safeId ?>"><?= $leagueNameSafe ?></a>
            <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
            <span class="lp-crumb-current"><?= htmlspecialchars($leagueCurrentLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <?php else : ?>
            <span class="lp-crumb-current"><?= $leagueNameSafe ?></span>
        <?php endif; ?>
    </nav>

    <header class="lp-hero">
        <div class="lp-hero-bg" style="background-image:url('<?= htmlspecialchars($heroImgSrc, ENT_QUOTES, 'UTF-8') ?>')"></div>
        <div class="lp-hero-inner">
            <div class="lp-hero-top">
                <span class="lp-pill <?= htmlspecialchars($leagueStatusPill, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-broadcast"></i><?= htmlspecialchars($leagueStatusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="lp-pill lp-pill-ghost"><i class="bi bi-dribbble"></i><?= htmlspecialchars($heroSport, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="lp-pill lp-pill-ghost"><i class="bi bi-diagram-3"></i><?= htmlspecialchars($heroDivision, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="lp-pill lp-pill-ghost"><i class="bi bi-gender-ambiguous"></i><?= htmlspecialchars($heroCategory, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($slotsFull): ?>
                    <span class="lp-pill lp-pill-ghost"><i class="bi bi-people-fill"></i>Slots full</span>
                <?php endif; ?>
            </div>

            <h1 class="lp-hero-title"><?= $leagueNameSafe ?></h1>

            <div class="lp-hero-bottom">
                <div class="lp-hero-meta">
                    <div class="lp-meta-item">
                        <i class="bi bi-calendar3"></i>
                        <span><small>League dates</small><?= htmlspecialchars($heroLeagueDates, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="lp-meta-item">
                        <i class="bi bi-hourglass-split"></i>
                        <span><small>Registration</small><?= htmlspecialchars($heroRegisDates, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    
                    <?php if ($heroVenue !== ''): ?>
                        <div class="lp-meta-item">
                            <i class="bi bi-geo-alt"></i>
                            <span><small>Venue</small><?= htmlspecialchars($heroVenue, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="lp-hero-actions">
                    <?php if ($regOpen && !$slotsFull) : ?>
                        <div id="regCountdown" class="lp-countdown">
                            Registration closes in <strong id="regTime">--</strong>
                        </div>
                    <?php endif; ?>

                    <?php
                    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
                        $debugInfo = [
                            'nowTs' => $nowTs,
                            'now' => date('c', $nowTs),
                            'regStartTs' => $regStartTs,
                            'regStart' => $league_row['regis_start'] ?? null,
                            'regEndTs' => $regEndTs,
                            'regEnd' => $league_row['regis_end'] ?? null,
                            'regOpen' => $regOpen,
                            'slotsFull' => $slotsFull,
                            'maxTeam' => $maxTeam,
                            'registeredCount' => $registeredCount,
                            'loggin' => $loggin,
                            'userId' => $_SESSION['lms_id'] ?? null
                        ];
                        echo '<pre style="background:#f8f9fa;padding:10px;border:1px solid #ddd;color:#0f172a;font-size:11px;max-width:100%;overflow:auto;">'.htmlspecialchars(json_encode($debugInfo, JSON_PRETTY_PRINT)).'</pre>';
                    }

                    if (!$regOpen || $slotsFull) {
                    ?>
                        <button id="registerBtn" class="lp-btn lp-btn-state" type="button" disabled>
                            <i class="bi bi-lock-fill"></i><?= htmlspecialchars($registrationLabel, ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    <?php
                    } elseif ($loggin != true) {
                    ?>
                        <a class="lp-btn lp-btn-light" href="login.php?redirect=<?= urlencode($page . '?id=' . $id) ?>">
                            <i class="bi bi-box-arrow-in-right"></i>Sign in to register
                        </a>
                    <?php
                    } elseif ($currentRole !== 2) {
                    ?>
                        <button class="lp-btn lp-btn-state" type="button" disabled title="Only Team Representatives can register teams">
                            <i class="bi bi-person-badge"></i>Team Representative account required
                        </button>
                    <?php
                    } elseif ($hasApplication) {
                    ?>
                        <button class="lp-btn lp-btn-state" type="button" disabled>
                            <i class="bi bi-check2-circle"></i><?= $existingApplicationStatus === 'rejected' ? 'Application rejected (final)' : ($existingApplicationStatus === 'correction requested' ? 'Correction required in dashboard' : 'Application submitted') ?>
                        </button>
                    <?php
                    } else {
                    ?>
                        <button id="registerBtn" class="lp-btn lp-btn-primary" type="button"
                                data-register-href="app/tr/register_team.php?id=<?= (int) $id ?>">
                            <i class="bi bi-plus-circle"></i>Register team
                        </button>
                    <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </header>

    <ul class="nav lp-tabs-nav" role="tablist">
        <?php foreach ($leagueDetailTabs as $tab): $isActiveTab = ($page === $tab['page']); ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link<?= $isActiveTab ? ' active' : '' ?>" role="tab"
                   href="<?= htmlspecialchars($tab['page'], ENT_QUOTES, 'UTF-8') ?>?id=<?= $safeId ?>">
                    <i class="bi <?= htmlspecialchars($tab['icon'], ENT_QUOTES, 'UTF-8') ?>"></i><?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$slotsFull) : ?>
        <script>
            (function() {
                var regStart = <?php echo ($regStartTs ? ($regStartTs * 1000) : 0); ?>; // server timestamp in ms
                var regEnd = <?php echo ($regEndTs ? ($regEndTs * 1000) : 0); ?>;     // server timestamp in ms
                var serverNow = <?php echo (int) $nowTs * 1000; ?>;             // server timestamp in ms
                var upcoming = <?= json_encode((bool)$registrationUpcoming) ?>;
                var windowEnabled = <?= json_encode((int)($league_row['registration_window_open'] ?? 0) === 1) ?>;

                var el = document.getElementById('regTime');
                var btn = document.getElementById('registerBtn');

                // Registration is a dedicated page now. Keeping it a <button>
                // preserves the countdown's disable/relabel behaviour below.
                if (btn && btn.dataset.registerHref) {
                    btn.addEventListener('click', function () {
                        window.location.href = btn.dataset.registerHref;
                    });
                }

                // sync client clock with server to avoid timezone/clock drift issues
                var timeOffset = serverNow - Date.now();

                function formatRemaining(ms) {
                    if (ms <= 0) return '0m';
                    var sec = Math.floor(ms / 1000);
                    var days = Math.floor(sec / 86400);
                    sec %= 86400;
                    var hours = Math.floor(sec / 3600);
                    sec %= 3600;
                    var minutes = Math.floor(sec / 60);
                    var parts = [];
                    if (days > 0) parts.push(days + 'd');
                    if (hours > 0) parts.push(hours + 'h');
                    parts.push(minutes + 'm');
                    return parts.join(' ');
                }

                // debug info
                try {
                    console.info('League registration debug', {
                        regStart: regStart,
                        regStartDate: regStart ? new Date(regStart).toString() : null,
                        regEnd: regEnd,
                        regEndDate: regEnd ? new Date(regEnd).toString() : null,
                        serverNow: serverNow,
                        serverNowDate: new Date(serverNow).toString(),
                        clientNow: Date.now(),
                        timeOffset: timeOffset
                    });
                } catch (e) {}

                function update() {
                    var now = Date.now() + timeOffset; // adjusted to server time

                    // If dates are not valid, update timer only and log
                    if (!regStart || !regEnd) {
                        if (el) el.textContent = (regEnd ? formatRemaining(Math.max(0, regEnd - now)) : '--');
                        if (!regStart || !regEnd) console.warn('Invalid registration timestamps', {regStart: regStart, regEnd: regEnd});
                        return;
                    }

                    if (!btn) {
                        if (el) el.textContent = formatRemaining(Math.max(0, regEnd - now));
                        console.warn('Register button not found');
                        return;
                    }

                    // Recheck account eligibility and capacity on the server when registration opens.
                    if (upcoming && windowEnabled && now >= regStart && now <= regEnd) {
                        location.reload();
                        return;
                    }
                    if (now > regEnd) {
                        btn.disabled = true;
                        btn.setAttribute('disabled','disabled');
                        btn.innerHTML = '<i class="bi bi-lock-fill"></i>Registration Closed';
                        btn.classList.remove('lp-btn-primary');
                        btn.classList.add('lp-btn-state');
                    }

                    // Countdown label
                    if (el) {
                        if (now <= regEnd) {
                            el.textContent = formatRemaining(regEnd - now);
                        } else {
                            el.textContent = '0m';
                        }
                    }
                }

                update();
                var iv = setInterval(update, 1000);
            })();
        </script>
    <?php endif; ?>
</div>
