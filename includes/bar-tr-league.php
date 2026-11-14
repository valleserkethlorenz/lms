<?php
/**
 * ===========================================================================
 *  Team Representative portal chrome — public league-detail pages
 * ===========================================================================
 *  The league detail pages (team_information.php, participant.php,
 *  schedule.php, standing.php) live in the site root because they are the
 *  shared public views. A Team Representative who opens them from
 *  app/tr/browse_leagues.php used to fall out of the portal layout and lose
 *  the sidebar entirely.
 *
 *  This partial renders the same sidebar + topbar as app/tr/includes/bar.php,
 *  but with paths relative to the site root (the portal menu itself comes from
 *  app/tr/includes/nav-links.php so both renderers stay in sync). It is only
 *  included when the current session role is 2; every other visitor keeps the
 *  public navbar from includes/bar.php.
 *
 *  Differences from the portal bar on purpose:
 *   - No #content-wrapper: these pages print their own containers, so the
 *     layout is expressed as body padding + fixed sidebar/topbar.
 *   - Logout posts to app/tr/action/logout.php with the session CSRF token
 *     (the public footer loads jQuery/SweetAlert but not the portal footer).
 *
 *  Expects: $conn (mysqli) from config/dbconnect.php and an active
 *  LMS_SESSION (see config/leagueSession.php).
 * ===========================================================================
 */

$trNavBase   = 'app/tr/';   // portal directory relative to the site root
$trActiveKey = 'browse';    // the league detail pages belong to "Browse League"
$userType    = 2;

$userId = $userId ?? (int) ($_SESSION['lms_id'] ?? 0);
$trLoginName = isset($full) ? trim((string) $full) : '';
$trLoginName = $trLoginName !== '' ? $trLoginName : (string) ($_SESSION['lms_username'] ?? '');
$trLoginName = $trLoginName !== '' ? $trLoginName : 'Team Representative';

$conn = $conn ?? null;
$_bar_unread = 0;
if ($userId > 0 && $conn instanceof mysqli) {
    $_bar_stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_notification WHERE user_id = ? AND is_read = 0");
    if ($_bar_stmt) {
        $_bar_stmt->bind_param('i', $userId);
        $_bar_stmt->execute();
        $_bar_stmt->bind_result($_bar_unread);
        $_bar_stmt->fetch();
        $_bar_stmt->close();
    }
}

// Same token contract as config/auth_guard.php::csrfToken(), without loading the
// guard (these pages are public and must not start an auth session).
if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$trLogoutCsrf = (string) $_SESSION['csrf_token'];

$trNavLinks = require __DIR__ . '/../app/tr/includes/nav-links.php';
?>
<style>
    /* ---------- Sidebar links (same look as app/tr/includes/bar.php) -------- */
    .sidelist a { color:white; font-weight:400; transition:background 0.2s ease, border-color 0.2s ease; border-left:4px solid transparent; }
    .sidelist a:hover { background:rgba(47,125,228,0.08); border-left-color:#2f7de4; color:#fff !important; }
    .sidelist > a.actives { background:rgba(47,125,228,0.15); border-left-color:#2f7de4; color:#fff !important; font-weight:600; }
    #accordionSidebar { display:flex; flex-direction:column; height:100vh; padding-top:0.5rem; background-color:#09173c; }
    #accordionSidebar #logout-item { margin-top:auto; border-top:1px solid rgba(255,255,255,0.06); padding-top:10px; padding-bottom:12px; }
    .sidebar-logo-wrap { padding-top:16px; padding-bottom:12px; }
    .sidebar-logo { width:64px; height:64px; border-radius:50%; overflow:hidden; margin:0 auto; box-shadow:0 4px 12px rgba(0,0,0,0.18); }
    .sidebar-logo img { width:100%; height:100%; object-fit:cover; display:block; border-radius:50%; }
    .lms-sidebar-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1045; display:none; }
    .lms-sidebar-backdrop.show { display:block; }

    /* ---------- Topbar ----------------------------------------------------- */
    .topbar { background-color:#09173c; display:flex; align-items:center; gap:4px; padding:0 .75rem; }
    .topbar .topbar-title { margin:0; color:#fff; font-size:1.25rem; font-weight:500; letter-spacing:1px; }
    .topbar-toggle { display:inline-flex; align-items:center; justify-content:center; }
    .role-notification-link { position:relative; display:inline-flex !important; align-items:center; justify-content:center; width:46px; height:46px; padding:0 !important; margin-right:8px; border-radius:50%; line-height:1; cursor:pointer; z-index:1; }
    .role-notification-link:hover { background:rgba(255,255,255,.1); }
    .role-notification-link .bi-bell-fill { display:block; font-size:24px; line-height:1; }
    .role-notification-badge { position:absolute; top:2px; right:0; display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:20px; padding:0 5px; border:2px solid #09173c; border-radius:4px; background:#dc3545; color:#fff; font-size:10px; font-weight:700; line-height:1; pointer-events:none; z-index:2; }
    .account-link .account-menu-label { margin-left:.5rem; color:#fff; font-size:13px; letter-spacing:normal; text-align:left; white-space:nowrap; }

    /* ---------- Layout: fixed sidebar + fixed topbar, content via body ----- */
    @media (min-width:769px) {
        body { padding-left:220px; }
        body.lms-mini { padding-left:68px; }
        #accordionSidebar { position:fixed; top:0; left:0; width:220px; z-index:1030; transition:width .25s ease; }
        .topbar { position:fixed; top:0; left:220px; right:0; min-height:70px; z-index:1020; transition:left .25s ease, width .25s ease; }
        body.lms-mini #accordionSidebar { width:68px; min-width:68px; max-width:68px; }
        body.lms-mini .topbar { left:68px; }
        body.lms-mini .sidebar .nav-link span { display:none !important; }
        /* Keep icons visible & centered in the rail (SB Admin forces nav-link width to 14rem) */
        body.lms-mini .sidebar .nav-link { display:flex !important; align-items:center; justify-content:center; width:100% !important; padding:1rem 0 !important; text-align:center; }
        body.lms-mini .sidebar .nav-link i { margin-right:0 !important; font-size:1.05rem; }
        body.lms-mini .sidebar-logo { width:40px; height:40px; }
    }
    @media (max-width:768px) {
        #accordionSidebar { position:fixed; top:0; left:-250px; width:220px; height:100vh; height:100dvh; overflow-y:auto; overscroll-behavior:contain; z-index:1050; transition:left .3s ease-in-out; }
        #accordionSidebar.active { left:0; }
        #accordionSidebar #logout-item { position:sticky; bottom:0; z-index:1; flex-shrink:0; background:#09173c; }
        #accordionSidebar .nav-item { width:100%; }
        #accordionSidebar .nav-item .nav-link {
            display:flex !important;
            align-items:center;
            justify-content:flex-start !important;
            width:100% !important;
            min-height:52px;
            padding:0.85rem 1rem !important;
            text-align:left !important;
        }
        #accordionSidebar .nav-item .nav-link i {
            display:inline-block;
            flex:0 0 24px;
            width:24px;
            margin:0 12px 0 0 !important;
            font-size:1rem;
            text-align:center;
        }
        #accordionSidebar .nav-item .nav-link span {
            display:inline !important;
            font-size:13px !important;
            line-height:1.3;
            white-space:nowrap;
        }
        .topbar { position:fixed; top:0; left:0; right:0; min-height:70px; z-index:1060; }
        .topbar .topbar-title { font-size:.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:44vw; }
        .topbar .user-role-text { display:none; }
    }
    @media (max-width:575.98px) {
        .topbar .topbar-title { display:none; }
        .topbar .navbar-nav { flex:0 0 auto; flex-direction:row; gap:4px; margin-left:auto !important; }
        .topbar .navbar-nav > .nav-item { margin:0 !important; }
        .topbar .role-notification-link,
        .topbar .account-link { width:42px; height:42px; margin:0; }
        .topbar .account-link { display:inline-flex; align-items:center; justify-content:center; padding:0 !important; }
        .topbar .account-link .account-menu-label { display:none; }
    }
</style>

<div id="lmsSidebarBackdrop" class="lms-sidebar-backdrop"></div>
<ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
    <li class="nav-item text-center sidebar-logo-wrap">
        <a href="index.php" class="d-inline-block" style="text-decoration:none;" aria-label="City Sports Development Program home">
            <div class="sidebar-logo">
                <img src="assets/images/logo.jpg" alt="CSDP Logo">
            </div>
        </a>
    </li>
    <hr class="sidebar-divider">
    <?php foreach ($trNavLinks as $trNavItem): $trNavHref = $trNavBase . $trNavItem['href']; ?>
    <li class="nav-item sidelist" id="<?= htmlspecialchars($trNavItem['id'], ENT_QUOTES, 'UTF-8') ?>">
        <a class="nav-link<?= $trActiveKey === $trNavItem['key'] ? ' actives' : '' ?>"
           href="<?= htmlspecialchars($trNavHref, ENT_QUOTES, 'UTF-8') ?>" style="font-weight:500;">
            <i class="bi <?= htmlspecialchars($trNavItem['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
            <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;"><?= htmlspecialchars($trNavItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
    </li>
    <?php endforeach; ?>
    <li class="nav-item sidelist" id="logout-item">
        <form class="trLeagueLogoutForm m-0 p-0" style="width:100%;"
              data-logout-url="app/tr/action/logout.php"
              data-logout-csrf="<?= htmlspecialchars($trLogoutCsrf, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="nav-link text-start w-100 border-0 bg-transparent" style="font-weight:400;color:#fff;padding-left:1rem;">
                <i class="bi bi-box-arrow-right"></i>
                <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">Logout</span>
            </button>
        </form>
    </li>
    <hr class="sidebar-divider d-none d-md-block">
</ul>

<nav class="navbar navbar-expand navbar-light topbar flex-nowrap shadow" aria-label="Team representative portal">
    <button class="btn btn-link topbar-toggle p-0 m-2" type="button" onclick="lmsToggleSidebar()" aria-label="Toggle sidebar">
        <i class="bi bi-list text-white fs-3"></i>
    </button>
    <h1 class="topbar-title">TEAM REPRESENTATIVE PORTAL</h1>
    <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item me-2">
            <button type="button" class="nav-link text-white role-notification-link" aria-label="Notifications" data-bs-toggle="popover" data-bs-trigger="click">
                <i class="bi bi-bell-fill"></i>
                <?php if ($_bar_unread > 0): ?><span class="role-notification-badge"><?= $_bar_unread > 99 ? '99+' : (int) $_bar_unread ?></span><?php endif; ?>
            </button>
        </li>
        <li class="nav-item no-arrow">
            <a class="nav-link text-white account-link" href="<?= htmlspecialchars($trNavBase . 'account.php', ENT_QUOTES, 'UTF-8') ?>" aria-label="My Account">
                <i class="bi bi-person-circle" style="font-size:32px;color:white;"></i>
                <span class="account-menu-label"><b><?= htmlspecialchars($trLoginName, ENT_QUOTES, 'UTF-8') ?></b><br><span class="user-role-text">Team Representative</span></span>
            </a>
        </li>
    </ul>
</nav>

<script>
    function lmsToggleSidebar() {
        var sidebar = document.getElementById('accordionSidebar');
        var backdrop = document.getElementById('lmsSidebarBackdrop');
        if (!sidebar) return;
        if (window.innerWidth <= 768) {
            var open = sidebar.classList.toggle('active');
            if (backdrop) backdrop.classList.toggle('show', open);
        } else {
            var mini = document.body.classList.toggle('lms-mini');
            try { localStorage.setItem('lms_sidebar_mini', mini ? '1' : '0'); } catch (e) {}
        }
    }
    (function () {
        var sidebar = document.getElementById('accordionSidebar');
        var backdrop = document.getElementById('lmsSidebarBackdrop');
        if (!sidebar) return;
        var key = 'lms_sidebar_mini';
        function isDesktop() { return window.innerWidth > 768; }
        try { if (isDesktop() && localStorage.getItem(key) === '1') document.body.classList.add('lms-mini'); } catch (e) {}
        if (backdrop) {
            backdrop.addEventListener('click', function () {
                sidebar.classList.remove('active');
                backdrop.classList.remove('show');
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                if (backdrop) backdrop.classList.remove('show');
            }
        });
        sidebar.querySelectorAll('a.nav-link').forEach(function (a) {
            a.addEventListener('click', function () {
                if (window.innerWidth <= 768) {
                    var href = a.getAttribute('href');
                    if (href && href !== '#') {
                        sidebar.classList.remove('active');
                        if (backdrop) backdrop.classList.remove('show');
                    }
                }
            });
        });
        window.addEventListener('resize', function () {
            if (isDesktop()) {
                sidebar.classList.remove('active');
                if (backdrop) backdrop.classList.remove('show');
            }
        });
    })();
</script>
<script>
    // Logout for the root-level league pages: the public footer (loaded at the
    // end of these pages) does not run the portal's .logoutForm handler, so the
    // request is issued here against app/tr/action/logout.php.
    (function () {
        document.addEventListener('submit', function (event) {
            var form = event.target && event.target.closest ? event.target.closest('.trLeagueLogoutForm') : null;
            if (!form) return;
            event.preventDefault();

            var url = form.getAttribute('data-logout-url');
            var token = form.getAttribute('data-logout-csrf') || '';

            function send() {
                var body = new FormData();
                body.append('logout', '1');
                body.append('csrf_token', token);
                fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.status === 201) {
                            if (window.Toast) {
                                // Shared floating confirmation (assets/js/lms-toast.js).
                                Toast.fire({ icon: 'success', title: 'Logged out', timer: 1200 })
                                    .then(function () { window.location.href = 'index.php'; });
                            } else {
                                window.location.href = 'index.php';
                            }
                        } else if (window.Toast) {
                            Toast.fire({ icon: 'error', title: (res && res.msg) || 'Logout failed.' });
                        }
                    })
                    .catch(function () {
                        if (window.Toast) Toast.fire({ icon: 'error', title: 'Logout failed.' });
                    });
            }

            if (window.LmsDialog) {
                LmsDialog.confirm({
                    title: 'Log out of your account?',
                    text: 'You will need to sign in again to continue managing your team.',
                    confirmButtonText: 'Yes, log out',
                    cancelButtonText: 'Stay signed in'
                }).then(function (confirmed) { if (confirmed) send(); });
            } else if (window.Swal) {
                Swal.fire({
                    text: 'Are you sure you want to logout?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, logout!'
                }).then(function (result) { if (result.isConfirmed) send(); });
            } else if (window.confirm('Are you sure you want to logout?')) {
                send();
            }
        });
    })();
</script>
<?php
// Portal notification popover, re-pointed at the portal endpoints.
$notifCsrf      = $trLogoutCsrf;
$notifActionUrl = 'includes/notificationAction.php';
$notifRouteBase = 'app/tr/';
include __DIR__ . '/notificationPopover.php';
?>
