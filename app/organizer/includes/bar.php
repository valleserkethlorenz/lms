<?php
// Ensure variables expected by this include are defined to avoid notices
$full = ($full ?? '') ?: ($_SESSION['lms_username'] ?? '');
$organizerRoleLabel = 'Organizer';
$conn = $conn ?? null;
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
        .sidebar { position:fixed; top:0; left:-250px; height:100vh; height:100dvh; width:220px; overflow:hidden; transition:left 0.3s ease-in-out; z-index:1050; }
        .sidebar.active { left:0; }
        #accordionSidebar .nav-item { width:100%; }
        #accordionSidebar .nav-item > .nav-link,
        #accordionSidebar #logout-item .nav-link {
            display:flex !important;
            align-items:center;
            justify-content:flex-start !important;
            width:100% !important;
            min-height:44px;
            padding:0.55rem 1rem !important;
            text-align:left !important;
        }
        #accordionSidebar .nav-item > .nav-link > i:first-child,
        #accordionSidebar #logout-item .nav-link > i {
            display:inline-block;
            flex:0 0 24px;
            width:24px;
            margin:0 12px 0 0 !important;
            font-size:1rem;
            text-align:center;
        }
        #accordionSidebar .nav-item > .nav-link > span,
        #accordionSidebar #logout-item .nav-link span {
            display:inline !important;
            font-size:13px !important;
            line-height:1.3;
            white-space:nowrap;
        }
        #accordionSidebar .nav-item > .nav-link .caret-icon { flex:0 0 auto; margin:0 0 0 auto !important; }
        #accordionSidebar .settings-submenu { margin-left:36px; padding-left:0; }
        #accordionSidebar .settings-submenu .nav-link {
            display:flex !important;
            align-items:center;
            width:100% !important;
            min-height:38px;
            margin-right:0;
            padding:0.45rem 0.75rem !important;
            text-align:left !important;
        }
        #accordionSidebar .settings-submenu .nav-link i {
            flex:0 0 20px;
            width:20px;
            margin:0 10px 0 0 !important;
        }
        #content-wrapper { margin-left:0 !important; width:100%; }
    }
    #accordionSidebar { display:flex; flex-direction:column; height:100vh; padding-top:0.5rem; }
    #accordionSidebar .nav-item#logout-item { margin-top:auto; border-top:1px solid rgba(255,255,255,0.06); padding-top:10px; padding-bottom:12px; }
    .sidebar-logo-wrap { padding-top:16px; padding-bottom:12px; }
    .sidebar-logo { width:64px; height:64px; border-radius:50%; overflow:hidden; margin:0 auto; box-shadow:0 4px 12px rgba(0,0,0,0.18); }
    .sidebar-logo img { width:100%; height:100%; object-fit:cover; display:block; border-radius:50%; }
    .role-notification-link { position:relative; display:inline-flex !important; align-items:center; justify-content:center; width:46px; height:46px; padding:0 !important; margin-right:8px; border-radius:50%; line-height:1; cursor:pointer; z-index:1; }
    .role-notification-link:hover { background:rgba(255,255,255,.1); }
    .role-notification-link .bi-bell-fill { display:block; font-size:24px; line-height:1; }
    .role-notification-badge { position:absolute; top:2px; right:0; display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:20px; padding:0 5px; border:2px solid #09173c; border-radius:4px; background:#dc3545; color:#fff; font-size:10px; font-weight:700; line-height:1; pointer-events:none; z-index:2; }
    /* Submenu */
    .settings-submenu { display:none; padding-left:1.5rem; }
    .settings-submenu.open { display:block; }
    .settings-submenu a {
        font-size:13px !important;
        font-weight:400 !important;
        padding:7px 12px !important;
        letter-spacing:0.5px;
        margin-right:8px;
        margin-bottom:2px;
        border-radius:0 6px 6px 0;
        border-left:3px solid transparent;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }
    .settings-submenu a:hover {
        background:rgba(47, 125, 228, 0.12) !important;
        border-left-color:#2f7de4;
        color:#fff !important;
    }
    .settings-submenu a.actives {
        background:rgba(47, 125, 228, 0.18) !important;
        border-left-color:#2f7de4;
        color:#fff !important;
    }
    .settings-toggle .caret-icon { transition: transform 0.2s; }
    .settings-toggle.open .caret-icon { transform: rotate(180deg); }
    /* ── Collapsible sidebar (mini rail on desktop, drawer on mobile) ── */
    #accordionSidebar, #content-wrapper, .topbar { transition: left .25s ease, width .25s ease, margin-left .25s ease; }
    .lms-sidebar-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1045; display:none; }
    .lms-sidebar-backdrop.show { display:block; }
    .topbar-toggle { display:inline-flex; align-items:center; justify-content:center; }
    @media (min-width:769px) {
        body.lms-mini .sidebar { width:68px !important; min-width:68px !important; max-width:68px !important; }
        body.lms-mini #content-wrapper { margin-left:68px !important; }
        body.lms-mini .topbar { left:68px !important; width:calc(100% - 68px) !important; }
        body.lms-mini .sidebar .nav-link span,
        body.lms-mini .sidebar .caret-icon { display:none !important; }
        /* Keep icons visible & centered in the rail (SB Admin forces nav-link width to 14rem) */
        body.lms-mini .sidebar .nav-link { display:flex !important; align-items:center; justify-content:center; width:100% !important; padding:1rem 0 !important; text-align:center; }
        body.lms-mini .sidebar .nav-link i { margin-right:0 !important; font-size:1.05rem; }
        body.lms-mini .sidebar-logo { width:40px; height:40px; }
        /* Sub-items shown as a flyout next to the rail (no rail expansion) */
        body.lms-mini #accordionSidebar { overflow:visible; }
        body.lms-mini .sidebar .nav-item { position:relative; }
        body.lms-mini .sidebar .settings-submenu {
            display:none !important; position:absolute; left:100%; top:0; margin:0;
            min-width:210px; padding:6px 0; background:#0d2050;
            border-radius:0 8px 8px 0; box-shadow:0 10px 28px rgba(0,0,0,.4); z-index:1200;
        }
        body.lms-mini .sidebar .nav-item:hover > .settings-submenu,
        body.lms-mini .sidebar .nav-item:focus-within > .settings-submenu { display:block !important; }
        body.lms-mini .sidebar .settings-submenu .nav-link { width:auto !important; justify-content:flex-start; padding:7px 16px !important; text-align:left; }
        body.lms-mini .sidebar .settings-submenu .nav-link i { margin-right:.3rem !important; font-size:.9rem !important; }
    }
    @media (max-width:768px) {
        /* Topbar spans the full width on mobile (sidebar is an off-canvas drawer) */
        #accordionSidebar { top:70px; height:calc(100vh - 70px); height:calc(100dvh - 70px); overflow-x:hidden; overflow-y:auto; overscroll-behavior:contain; }
        #accordionSidebar .sidebar-logo-wrap { padding-top:4px; padding-bottom:4px; }
        #accordionSidebar .sidebar-logo { width:40px; height:40px; }
        #accordionSidebar .nav-item { flex:0 0 auto; }
        #accordionSidebar .nav-item > .nav-link,
        #accordionSidebar #logout-item .nav-link { min-height:36px; padding:0.3rem 0.75rem !important; }
        #accordionSidebar .nav-item > .nav-link > i:first-child,
        #accordionSidebar #logout-item .nav-link > i { flex-basis:20px; width:20px; margin-right:8px !important; font-size:0.9rem; }
        #accordionSidebar .nav-item > .nav-link > span,
        #accordionSidebar #logout-item .nav-link span { font-size:12px !important; }
        #accordionSidebar .settings-submenu { margin-left:28px; }
        #accordionSidebar .settings-submenu .nav-link { min-height:30px; padding:0.2rem 0.5rem !important; font-size:12px !important; }
        #accordionSidebar .settings-submenu .nav-link i { flex-basis:18px; width:18px; margin-right:6px !important; }
        #accordionSidebar .nav-item#logout-item { flex-shrink:0; padding-top:4px; padding-bottom:4px; }
        .topbar { left:0 !important; width:100% !important; z-index:1060 !important; height:70px; min-height:70px; padding-top:0; padding-bottom:0; }
        .topbar .navbar-nav { flex:0 0 auto; flex-direction:row; gap:4px; margin-left:auto !important; }
        .topbar .navbar-nav > .nav-item { margin:0 !important; }
        .topbar .topbar-title { font-size:.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:48vw; }
        .topbar .role-notification-link,
        .topbar .organizer-account { width:42px; height:42px; margin:0; }
        .topbar .organizer-account { display:inline-flex; align-items:center; justify-content:center; padding:0 !important; }
        .topbar .organizer-account-label { display:none; }
    }
    @media (max-width:575.98px) {
        .topbar .topbar-title { display:none; }
    }
</style>
<div id="wrapper">
    <div id="lmsSidebarBackdrop" class="lms-sidebar-backdrop"></div>
    <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar" style="background-color:#09173c;">
        <li class="nav-item text-center sidebar-logo-wrap">
            <a href="" class="d-inline-block" style="text-decoration:none;">
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
        <li class="nav-item sidelist" id="leagues-item">
            <a class="nav-link settings-toggle" href="#" onclick="toggleSettings(event,'leaguesSub','leaguesToggleBtn')" style="font-weight:500;" id="leaguesToggleBtn">
                <i class="bi bi-trophy"></i>
                <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">Leagues</span>
                <i class="bi bi-chevron-down caret-icon ms-auto" style="font-size:12px;"></i>
            </a>
            <div class="settings-submenu" id="leaguesSub">
                <a class="nav-link" href="league.php"><i class="bi bi-trophy me-1"></i>League Management</a>
                <a class="nav-link" href="archive.php"><i class="bi bi-archive me-1"></i>Archived Leagues</a>
            </div>
        </li>
        <li class="nav-item sidelist" id="reports-item">
            <a class="nav-link" href="reports.php" style="font-weight:500;">
                <i class="bi bi-file-earmark-bar-graph"></i>
                <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">Report</span>
            </a>
        </li>
        <li class="nav-item sidelist" id="gallery-item">
            <a class="nav-link" href="gallery.php" style="font-weight:500;">
                <i class="bi bi-images"></i>
                <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">Gallery</span>
            </a>
        </li>
        <li class="nav-item sidelist" id="settings-item">
            <a class="nav-link settings-toggle" href="#" onclick="toggleSettings(event,'settingsSub','settingsToggleBtn')" style="font-weight:500;" id="settingsToggleBtn">
                <i class="bi bi-gear"></i>
                <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">Settings</span>
                <i class="bi bi-chevron-down caret-icon ms-auto" style="font-size:12px;"></i>
            </a>
            <div class="settings-submenu" id="settingsSub">
                <a class="nav-link" href="settings.php?tab=accounts"><i class="bi bi-person-gear me-1"></i>Accounts</a>
            </div>
        </li>
        <li class="nav-item sidelist" id="logout-item">
            <form class="logoutForm m-0 p-0" style="width:100%;">
                <button type="submit" class="nav-link text-start w-100 border-0 bg-transparent" style="font-weight:400;color:#fff;padding-left:1rem;">
                    <i class="bi bi-box-arrow-right"></i>
                    <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">Logout</span>
                </button>
            </form>
        </li>
        <hr class="sidebar-divider d-none d-md-block">
    </ul>
    <!-- Content Wrapper -->
    <div id="content-wrapper" style="background-color:#ffffff; padding-top:70px; margin-left:220px;">
        <div id="content">
            <nav class="navbar navbar-expand navbar-light topbar flex-nowrap mb-4 shadow" style="background-color:#09173c; position:fixed; top:0; left:220px; right:0; width:calc(100% - 220px); min-height:70px; z-index:999;">
                <button id="organizerSidebarToggle" class="btn btn-link topbar-toggle p-0 m-2" type="button" aria-label="Toggle sidebar" aria-controls="accordionSidebar" aria-expanded="false">
                    <i class="bi bi-list text-white fs-3"></i>
                </button>
                <h1 class="mt-2 topbar-title" style="color:white;letter-spacing:1px;font-weight:500;">ORGANIZER PANEL</h1>
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-2">
                        <button type="button" class="nav-link text-white role-notification-link" aria-label="Notifications" data-bs-toggle="popover" data-bs-trigger="click">
                            <i class="bi bi-bell-fill"></i>
                            <?php if ($_bar_unread > 0): ?><span class="role-notification-badge"><?= $_bar_unread > 99 ? '99+' : (int)$_bar_unread ?></span><?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item no-arrow">
                        <div class="nav-link d-flex align-items-center organizer-account" style="color:white;">
                            <i class="bi bi-person-circle" style="font-size:32px;color:white;"></i>
                            <div class="organizer-account-label ms-2 text-capitalize" style="color:white;font-size:13px;">
                                <b><?php echo htmlspecialchars($full, ENT_QUOTES, 'UTF-8'); ?></b><br>
                                <span class="user-role-text"><?php echo $organizerRoleLabel; ?></span>
                            </div>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
        <script>
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidelist a').forEach(link => {
                const href = link.getAttribute('href');
                if (!href || href === '#') return;
                const linkPage = new URL(href, window.location.href).pathname.split('/').pop();
                if (currentPage === linkPage) link.classList.add('actives');
            });

            // --- Submenu state persistence via localStorage ---
            const submenus = [
                { subId: 'leaguesSub',  btnId: 'leaguesToggleBtn',  urls: ['league.php','archive.php','create_league.php','edit_league.php','league_info.php','pending_teams.php','schedule.php','standing.php','view_player.php'] },
                { subId: 'settingsSub', btnId: 'settingsToggleBtn', urls: ['settings.php'] }
            ];

            submenus.forEach(function(menu) {
                const sub = document.getElementById(menu.subId);
                const btn = document.getElementById(menu.btnId);
                if (!sub || !btn) return;

                const storageKey = 'sidebar_' + menu.subId;
                const savedState = localStorage.getItem(storageKey);
                const onSubPage = menu.urls.includes(currentPage);

                // If on a page belonging to this submenu, always open it
                if (onSubPage) {
                    sub.classList.add('open');
                    btn.classList.add('open');
                    localStorage.setItem(storageKey, 'open');
                    if (menu.subId === 'leaguesSub' && !['league.php', 'archive.php'].includes(currentPage)) {
                        const managementLink = sub.querySelector('a[href="league.php"]');
                        if (managementLink) managementLink.classList.add('actives');
                        btn.classList.add('actives');
                    }
                } else if (savedState === 'open') {
                    // Restore saved open state
                    sub.classList.add('open');
                    btn.classList.add('open');
                }
                // If savedState === 'closed' or null (no state yet), leave closed
            });

            function toggleSettings(e, subId, btnId) {
                e.preventDefault();
                const sub = document.getElementById(subId);
                const btn = document.getElementById(btnId);
                if (sub) sub.classList.toggle('open');
                if (btn) btn.classList.toggle('open');
                // Save state to localStorage
                const isOpen = sub && sub.classList.contains('open');
                localStorage.setItem('sidebar_' + subId, isOpen ? 'open' : 'closed');
            }
        </script>
<script>
    (function () {
        var sidebar = document.getElementById('accordionSidebar');
        var backdrop = document.getElementById('lmsSidebarBackdrop');
        var toggleButton = document.getElementById('organizerSidebarToggle');
        var mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 768px)') : null;
        if (!sidebar) return;
        var key = 'lms_sidebar_mini';
        function isMobile() { return mobileQuery ? mobileQuery.matches : window.innerWidth <= 768; }
        function setDrawerOpen(open) {
            sidebar.classList.toggle('active', open);
            if (backdrop) backdrop.classList.toggle('show', open);
            if (toggleButton) toggleButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (toggleButton) {
            toggleButton.addEventListener('click', function () {
                if (isMobile()) {
                    setDrawerOpen(!sidebar.classList.contains('active'));
                } else {
                    var mini = document.body.classList.toggle('lms-mini');
                    try { localStorage.setItem(key, mini ? '1' : '0'); } catch (e) {}
                }
            });
        }
        function isDesktop() { return !isMobile(); }
        try { if (isDesktop() && localStorage.getItem(key) === '1') document.body.classList.add('lms-mini'); } catch (e) {}
        if (backdrop) {
            backdrop.addEventListener('click', function () {
                setDrawerOpen(false);
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                setDrawerOpen(false);
            }
        });
        sidebar.querySelectorAll('a.nav-link').forEach(function (a) {
            a.addEventListener('click', function () {
                if (isMobile()) {
                    var href = a.getAttribute('href');
                    if (href && href !== '#') {
                        setDrawerOpen(false);
                    }
                }
            });
        });
        window.addEventListener('resize', function () {
            if (isDesktop()) {
                setDrawerOpen(false);
            }
        });
    })();
</script>
