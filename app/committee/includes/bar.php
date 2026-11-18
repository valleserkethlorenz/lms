<?php
$full     = $full ?? '';
$userId   = $userId ?? null;
$conn     = $conn ?? null;
$_bar_unread = 0;
if (!empty($userId) && !empty($conn)) {
    $_bar_stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_notification WHERE user_id = ? AND is_read = 0");
    if ($_bar_stmt) {
        $_bar_stmt->bind_param('i', $userId);
        $_bar_stmt->execute();
        $_bar_stmt->bind_result($_bar_unread);
        $_bar_stmt->fetch();
        $_bar_stmt->close();
    }
}
?>
<style>
    .sidelist a { color:white; font-weight:400; transition:background 0.2s ease, border-color 0.2s ease; border-left:4px solid transparent; }
    .sidelist a:hover { background:rgba(47,125,228,0.08); border-left-color:#2f7de4; color:#fff !important; }
    .sidelist > a.actives { background:rgba(47,125,228,0.15); border-left-color:#2f7de4; color:#fff !important; font-weight:600; }
    @media (min-width:769px) {
        .sidebar { position:fixed; top:0; left:0; height:100vh; width:220px; z-index:1040; }
        #content-wrapper { margin-left:220px !important; }
        .btn.d-lg-none { display:none !important; }
    }
    @media (max-width:768px) {
        .sidebar { position:fixed; top:0; left:-250px; height:100%; width:220px; transition:left 0.3s ease-in-out; z-index:1050; }
        .sidebar.active { left:0; }
        #content-wrapper { margin-left:0 !important; width:100%; }
    }
    #accordionSidebar { display:flex; flex-direction:column; height:100vh; padding-top:0.5rem; }
    .sidebar-logo-wrap { padding-top:16px; padding-bottom:12px; }
    .sidebar-logo { width:64px; height:64px; border-radius:50%; overflow:hidden; margin:0 auto; box-shadow:0 4px 12px rgba(0,0,0,0.18); }
    .sidebar-logo img { width:100%; height:100%; object-fit:cover; display:block; border-radius:50%; }
    .role-notification-link { position:relative; display:inline-flex !important; align-items:center; justify-content:center; width:46px; height:46px; padding:0 !important; margin-right:8px; border-radius:50%; line-height:1; cursor:pointer; }
    .role-notification-link:hover { background:rgba(255,255,255,.1); }
    .role-notification-link .bi-bell-fill { display:block; font-size:24px; line-height:1; }
    .role-notification-badge { position:absolute; top:2px; right:0; display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:20px; padding:0 5px; border:2px solid #09173c; border-radius:4px; background:#dc3545; color:#fff; font-size:10px; font-weight:700; line-height:1; pointer-events:none; z-index:2; }
    /* ── Collapsible sidebar (mini rail on desktop, drawer on mobile) ── */
    #accordionSidebar, #content-wrapper, .topbar { transition: left .25s ease, width .25s ease, margin-left .25s ease; }
    .lms-sidebar-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1045; display:none; }
    .lms-sidebar-backdrop.show { display:block; }
    .topbar-toggle { display:inline-flex; align-items:center; justify-content:center; }
    /* ── Topbar account menu (floating window) ── */
    .topbar .topbar-account { position:relative; }
    .topbar .topbar-account-toggle { display:flex; align-items:center; margin:0; padding:.35rem .5rem; border:0; border-radius:8px; background:transparent; color:#fff; line-height:1.2; text-align:left; cursor:pointer; }
    .topbar .topbar-account-toggle:hover,
    .topbar .topbar-account-toggle[aria-expanded="true"] { background:rgba(255,255,255,.08); }
    .topbar .topbar-account-toggle:focus-visible { outline:2px solid #fff; outline-offset:2px; }
    .topbar .topbar-account-caret { margin-left:8px; font-size:11px; opacity:.75; transition:transform .2s ease; }
    .topbar .topbar-account-toggle[aria-expanded="true"] .topbar-account-caret { transform:rotate(180deg); }
    .topbar .topbar-account-menu { position:absolute; top:100%; right:0; min-width:236px; margin-top:10px; padding:6px; border:1px solid #dbe4f0; border-radius:10px; background:#fff; box-shadow:0 18px 40px -18px rgba(7,17,41,.45); }
    .topbar .topbar-account-menu .account-menu-identity { padding:10px 12px 8px; }
    .topbar .topbar-account-menu .account-menu-name { display:block; font-size:13px; font-weight:600; color:#172742; line-height:1.3; overflow-wrap:anywhere; }
    .topbar .topbar-account-menu .account-menu-role { display:block; margin-top:2px; font-size:11px; color:#75809a; }
    .topbar .topbar-account-menu .dropdown-divider { margin:4px 0; border-color:#e8edf4; }
    .topbar .topbar-account-menu .dropdown-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:6px; color:#1f2d48; font-size:13px; font-weight:500; line-height:1.3; }
    .topbar .topbar-account-menu .dropdown-item i { width:18px; font-size:15px; color:#2f6fed; text-align:center; }
    .topbar .topbar-account-menu .dropdown-item:hover,
    .topbar .topbar-account-menu .dropdown-item:focus { background:#eef3fc; color:#12408f; }
    .topbar .topbar-account-menu .dropdown-item.logout-item,
    .topbar .topbar-account-menu .dropdown-item.logout-item i { color:#b42318; }
    .topbar .topbar-account-menu .dropdown-item.logout-item:hover,
    .topbar .topbar-account-menu .dropdown-item.logout-item:focus { background:#fdf0ef; color:#8f1c13; }
    @media (min-width:769px) {
        body.lms-mini .sidebar { width:68px !important; min-width:68px !important; max-width:68px !important; }
        body.lms-mini #content-wrapper { margin-left:68px !important; }
        body.lms-mini .topbar { left:68px !important; width:calc(100% - 68px) !important; }
        body.lms-mini .sidebar .nav-link span,
        body.lms-mini .sidebar .caret-icon,
        body.lms-mini .sidebar .settings-submenu { display:none !important; }
        /* Keep icons visible & centered in the rail (SB Admin forces nav-link width to 14rem) */
        body.lms-mini .sidebar .nav-link { display:flex !important; align-items:center; justify-content:center; width:100% !important; padding:1rem 0 !important; text-align:center; }
        body.lms-mini .sidebar .nav-link i { margin-right:0 !important; font-size:1.05rem; }
        body.lms-mini .sidebar-logo { width:40px; height:40px; }
    }
    @media (max-width:768px) {
        /* Off-canvas drawer: keep it below the fixed topbar and inside the
           visible viewport. SB Admin forces .sidebar min-height:100vh. */
        .sidebar { height:100vh; height:100dvh; min-height:0; overflow-y:auto; overscroll-behavior:contain; }
        #accordionSidebar {
            top:70px;
            height:calc(100vh - 70px);
            height:calc(100dvh - 70px);
            min-height:0;
            max-height:calc(100vh - 70px);
            max-height:calc(100dvh - 70px);
            overflow-x:hidden;
            overflow-y:auto;
            overscroll-behavior:contain;
        }
        #accordionSidebar .sidebar-logo-wrap { position:sticky; top:0; padding-top:8px; padding-bottom:8px; z-index:3; background:#09173c; }
        #accordionSidebar .sidebar-logo { width:48px; height:48px; }
        #accordionSidebar .nav-item { width:100%; }
        #accordionSidebar .nav-item .nav-link {
            display:flex !important;
            align-items:center;
            justify-content:flex-start !important;
            width:100% !important;
            min-height:44px;
            padding:0.55rem 1rem !important;
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
        #content-wrapper { margin-left:0 !important; width:100%; }
        /* Topbar spans the full width on mobile (sidebar is an off-canvas drawer) */
        .topbar { left:0 !important; right:0 !important; width:100% !important; z-index:1060 !important; height:70px; min-height:70px; padding-top:0; padding-bottom:0; }
        .topbar-toggle { flex:0 0 auto; margin:0 10px 0 0 !important; }
        .topbar .topbar-title { font-size:.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:48vw; }
        .topbar .user-role-text { display:none; }
    }
    @media (max-width:575.98px) {
        .topbar .topbar-title { display:none; }
        .topbar .navbar-nav { flex:0 0 auto; flex-direction:row; gap:4px; margin-left:auto !important; }
        .topbar .navbar-nav > .nav-item { margin:0 !important; }
        .topbar .role-notification-link { width:42px; height:42px; margin:0; }
        .topbar .committee-account { width:42px; height:42px; margin:0; display:inline-flex; align-items:center; justify-content:center; padding:0 !important; }
        .topbar .committee-account-label { display:none; }
        .topbar .topbar-account-caret { display:none; }
    }
</style>
<script>
    document.addEventListener('click', function (event) {
        var target = event.target;
        var toggleButton = target && target.closest ? target.closest('.topbar-toggle') : null;
        if (!toggleButton) return;

        var sidebar = document.getElementById('accordionSidebar');
        if (!sidebar) return;

        if (window.innerWidth <= 768) {
            var open = !sidebar.classList.contains('active');
            sidebar.classList.toggle('active', open);
            var backdrop = document.getElementById('lmsSidebarBackdrop');
            if (backdrop) backdrop.classList.toggle('show', open);
        } else {
            var mini = document.body.classList.toggle('lms-mini');
            try { localStorage.setItem('lms_sidebar_mini', mini ? '1' : '0'); } catch (e) {}
        }
    }, true);
</script>
<div id="wrapper">
    <div id="lmsSidebarBackdrop" class="lms-sidebar-backdrop"></div>
    <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar" style="background-color:#09173c;">
        <li class="nav-item text-center sidebar-logo-wrap">
            <a href="../../index.php" class="d-inline-block" style="text-decoration:none;">
                <div class="sidebar-logo">
                    <img src="../../assets/images/logo.jpg" alt="CSDP Logo">
                </div>
            </a>
        </li>
        <hr class="sidebar-divider">
        <li class="nav-item sidelist" id="dashboard-item">
            <a class="nav-link" href="dashboard.php" style="font-weight:500;">
                <i class="bi bi-speedometer2"></i>
                <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">Dashboard</span>
            </a>
        </li>
        <li class="nav-item sidelist" id="matches-item">
            <a class="nav-link" href="matches.php" style="font-weight:500;">
                <i class="bi bi-calendar2-check-fill"></i>
                <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">Matches</span>
            </a>
        </li>
    </ul>

    <div id="content-wrapper" style="background-color:#ffffff; padding-top:70px; margin-left:220px;">
        <div id="content">
            <nav class="navbar navbar-expand navbar-light topbar flex-nowrap mb-4 shadow" style="background-color:#09173c; position:fixed; top:0; left:220px; right:0; width:calc(100% - 220px); min-height:70px; z-index:999;">
                <button class="btn btn-link topbar-toggle p-0 m-2" type="button" aria-label="Toggle sidebar">
                    <i class="bi bi-list text-white fs-3"></i>
                </button>
                <h1 class="mt-2 topbar-title" style="color:white;letter-spacing:1px;font-weight:500;">COMMITTEE PANEL</h1>
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-2">
                        <button type="button" class="nav-link text-white role-notification-link" aria-label="Notifications" data-bs-toggle="popover" data-bs-trigger="click">
                            <i class="bi bi-bell-fill"></i>
                            <?php if ($_bar_unread > 0): ?><span class="role-notification-badge"><?= $_bar_unread > 99 ? '99+' : (int)$_bar_unread ?></span><?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item dropdown topbar-account">
                        <button class="nav-link topbar-account-toggle committee-account" type="button" id="committeeAccountMenuToggle" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true" aria-label="Open account menu">
                            <i class="bi bi-person-circle" style="font-size:32px;color:white;" aria-hidden="true"></i>
                            <span class="committee-account-label ms-2 text-capitalize" style="color:white;font-size:13px;">
                                <b><?php echo htmlspecialchars($full); ?></b><br><span class="user-role-text">Committee</span>
                            </span>
                            <i class="bi bi-chevron-down topbar-account-caret" aria-hidden="true"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end topbar-account-menu" aria-labelledby="committeeAccountMenuToggle">
                            <li class="account-menu-identity">
                                <span class="account-menu-name"><?php echo htmlspecialchars($full); ?></span>
                                <span class="account-menu-role">Committee</span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="account.php"><i class="bi bi-person-gear" aria-hidden="true"></i>My Account</a>
                            </li>
                            <li>
                                <form class="logoutForm m-0 p-0">
                                    <button type="submit" class="dropdown-item logout-item"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Logout</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </div>
        <script>
            const currentUrl = window.location.href;
            document.querySelectorAll('.sidelist a').forEach(link => {
                if (currentUrl.includes(link.getAttribute('href'))) link.classList.add('actives');
            });
        </script>
<script>
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
