<?php
$full     = $full ?? '';
$userType = $userType ?? null;
$userId   = $userId ?? null;
$conn     = $conn ?? null;
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
    #accordionSidebar .nav-item#logout-item { margin-top:auto; border-top:1px solid rgba(255,255,255,0.06); padding-top:10px; padding-bottom:12px; }
    .sidebar-logo-wrap { padding-top:16px; padding-bottom:12px; }
    .sidebar-logo { width:64px; height:64px; border-radius:50%; overflow:hidden; margin:0 auto; box-shadow:0 4px 12px rgba(0,0,0,0.18); }
    .sidebar-logo img { width:100%; height:100%; object-fit:cover; display:block; border-radius:50%; }
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
        body.lms-mini .sidebar .caret-icon,
        body.lms-mini .sidebar .settings-submenu { display:none !important; }
        /* Keep icons visible & centered in the rail (SB Admin forces nav-link width to 14rem) */
        body.lms-mini .sidebar .nav-link { display:flex !important; align-items:center; justify-content:center; width:100% !important; padding:1rem 0 !important; text-align:center; }
        body.lms-mini .sidebar .nav-link i { margin-right:0 !important; font-size:1.05rem; }
        body.lms-mini .sidebar-logo { width:40px; height:40px; }
    }
    @media (max-width:768px) {
        /* Topbar spans the full width on mobile (sidebar is an off-canvas drawer) */
        .topbar { left:0 !important; width:100% !important; }
        .topbar .topbar-title { font-size:.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:48vw; }
        .topbar .user-role-text { display:none; }
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
        <li class="nav-item sidelist" id="users-item">
            <a class="nav-link" href="users.php" style="font-weight:500;">
                <i class="bi bi-people-fill"></i>
                <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">User Accounts</span>
            </a>
        </li>
        <li class="nav-item sidelist" id="utility-item">
            <a class="nav-link" href="utility.php" style="font-weight:500;">
                <i class="bi bi-tools"></i>
                <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">Utility</span>
            </a>
        </li>
        <li class="nav-item sidelist" id="archive-item">
            <a class="nav-link" href="archive.php" style="font-weight:500;">
                <i class="bi bi-archive-fill"></i>
                <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">Archive</span>
            </a>
        </li>
        <li class="nav-item sidelist" id="auditlog-item">
            <a class="nav-link" href="audit_log.php" style="font-weight:500;">
                <i class="bi bi-journal-text"></i>
                <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">Audit Log</span>
            </a>
        </li>
        <li class="nav-item sidelist" id="settings-item">
            <a class="nav-link" href="settings.php" style="font-weight:500;">
                <i class="bi bi-shield-lock-fill"></i>
                <span style="letter-spacing:0.5px;text-transform:uppercase;font-size:13px;">Account Settings</span>
            </a>
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
                <button class="btn btn-link topbar-toggle p-0 m-2" type="button" onclick="lmsToggleSidebar()" aria-label="Toggle sidebar">
                    <i class="bi bi-list text-white fs-3"></i>
                </button>
                <h1 class="mt-2 topbar-title" style="color:white;letter-spacing:1px;font-weight:500;">ADMIN PANEL</h1>
                <ul class="navbar-nav ms-auto align-items-center">
                    
                    <li class="nav-item no-arrow">
                        <div class="nav-link d-flex align-items-center" style="color:white;">
                            <i class="bi bi-person-circle" style="font-size:32px;color:white;"></i>
                            <div class="ms-2 text-capitalize" style="color:white;font-size:13px;">
                                <b><?php echo htmlspecialchars($full); ?></b><br><span class="user-role-text">Admin</span>
                            </div>
                        </div>
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
