<?php

?>

<style>
    .sidelist a:hover {
        background-color: #2f7de4;
        border-top-right-radius: 20px;
        border-bottom-right-radius: 20px;
        color: black !important;

    }

    .sidelist a {
        color: white;
    }

    .actives {
        background-color: #2f7de4;
        border-top-right-radius: 20px;
        border-bottom-right-radius: 20px;
    }

    .actives a {
        color: black !important;
        font-weight: bold;
    }




    /* Sidebar responsiveness */
    @media (max-width: 768px) {
        #wrapper {
            display: flex;
            flex-direction: column;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: -250px;
            height: 100%;
            width: 220px;
            transition: left 0.3s ease-in-out;
            z-index: 1050;
        }

        .sidebar.active {
            left: 0;
        }

        #content-wrapper {
            margin-left: 0 !important;
            width: 100%;
        }
    }

    /* Make cards stack on phones */
    @media (max-width: 576px) {
        .dashboards h1 {
            font-size: 28px;
        }

        .dashboards img {
            width: 30px;
        }
    }
</style>
<div id="wrapper">
    <!-- Sidebar -->
    <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar" style="background-color:#09173c;">
        <div class="container text-center" style="background-color:#09173c;">
            <img src="../../vendor/images/logo.png" alt="logout" width="100" draggable="false" (dragstart)="false;" class="unselectable" class="class_logo" style="margin-top:25px; border-radius:100%; border:4px solid white; ">
        </div>
        <!-- Sidebar - Brand -->
        <a class="sidebar-brand d-flex align-items-center justify-content-center disabled" style="text-decoration:none;" draggable="false" (dragstart)="false;" class="unselectable">

            <div class="sidebar-brand-text mx-3 mt-2">
                <span class="navbar-text" id="datetime" style="color:white; font-size:11px; letter-spacing: 2px;">


                </span>
            </div>
        </a>
        <!-- Divider -->
        <hr class="sidebar-divider">

        <li class="nav-item sidelist" id="home-item">
            <a class="nav-link" href="../../index.php" draggable="false" (dragstart)="false;" class="unselectable" style="font-weight:bolder;">
                <i data-feather="home" class="m-2"></i>
                <span style="letter-spacing:2px; text-transform:uppercase; font-size:15px;">home</span>
            </a>
        </li>
        <!-- Nav Item -->
        <li class="nav-item sidelist" id="dashboard-item">
            <a class="nav-link" href="dashboard.php" draggable="false" (dragstart)="false;" class="unselectable" style="font-weight:bolder;">
                <i data-feather="home" class="m-2"></i>
                <span style="letter-spacing:2px; text-transform:uppercase; font-size:15px;">DASHBOARD</span>
            </a>
        </li>
        <li class="nav-item sidelist" id="leagues-item">
            <a class="nav-link" href="league.php" draggable="false" (dragstart)="false;" class="unselectable" style="font-weight:bolder;">
                <i data-feather="home" class="m-2"></i>
                <span style="letter-spacing:2px; text-transform:uppercase; font-size:15px;">LEAGUES</span>
            </a>
        </li>
        <li class="nav-item sidelist" id="notification-item">
            <a class="nav-link" href="notification.php" draggable="false" (dragstart)="false;" class="unselectable" style="font-weight:bolder;">
                <i data-feather="home" class="m-2"></i>
                <span style="letter-spacing:2px; text-transform:uppercase; font-size:15px;">notification</span>
            </a>
        </li>



        <hr class="sidebar-divider d-none d-md-block">

    </ul>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarMenu" style="background-color:#11224e; color:white;">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title text-white">Navigation</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas">

            </button>
        </div>
        <div class="offcanvas-body p-0">


            <a class="sidebar-brand d-flex align-items-center justify-content-center disabled" style="text-decoration:none;" draggable="false" (dragstart)="false;" class="unselectable">

                <div class="sidebar-brand-text mx-3 mt-2">
                    <span class="navbar-text" id="sidedatetime" style="color:white; font-size:13px; letter-spacing: 2px;">


                    </span>
                </div>
            </a>

            <ul class="navbar-nav">

                <li class="nav-item sidelist" id="dashboard-item">
                    <a class="nav-link d-flex align-items-center px-3 py-2 text-white fw-bold" href="dashboard.php">
                        <img src="../../vendor/images/icon/chart.png" alt="chart" class="me-2" width="30">
                        <span>DASHBOARD</span>
                    </a>






            </ul>
        </div>
    </div>

    <!-- Content Wrapper -->
    <div id="content-wrapper" style="background-color:#D3D3D3;">
        <!-- Main Content -->
        <div id="content">
            <!-- Topbar -->
            <nav class="navbar navbar-expand navbar-light topbar mb-4 static-top shadow" style="background-color:#09173c;">
                <!-- Sidebar Toggle (Topbar) -->
                <button class="btn btn-link d-lg-none p-0 m-2"
                    type="button"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#sidebarMenu">

                    <img src="../../vendor/images/icon/hamburger.png" alt="hamburger" width="30">

                </button>

                <h3 class="mt-2 title_header" style="color:white; letter-spacing:2.5px; font-weight:bold;">
                    LEAGUES MANAGEMENT SYSTEM
                </h3>

                <!-- Right side -->
                <ul class="navbar-nav ms-auto align-items-center">


                    <!-- User Dropdown -->
                    <li class="nav-item dropdown no-arrow">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <img class="img-profile rounded-circle" src="../../vendor/images/logo.png" width="40" height="40">
                            <div class="ms-2 text-capitalize" style="color:white; font-size:13px;">
                                <b><?php echo $full; ?></b><br>
                                <?php if ($userType == '1') {
                                    $userType = 'Organizer';
                                } else {
                                    $userType = 'Team Repre';
                                }
                                echo $userType; ?>
                            </div>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="userDropdown">

                            <form id="logoutForm" class="dropdown-item " style="background-color:transparent;">
                                <button type="submit" class="border-0" style="background-color:transparent;">
                                    <!-- <img src="../../vendor/images/icon/logout.png" width="19" style="margin-right:7px;"> -->
                                    Logout
                                </button>
                            </form>
                        </ul>
                    </li>

                </ul>
            </nav>


        </div>

        <script>
            const currentUrl = window.location.href;

            // Get all nav links
            const navLinks = document.querySelectorAll(' .sidelist a');

            navLinks.forEach(link => {
                // If link's href matches current URL, add the 'active' class to its parent li
                if (currentUrl.includes(link.getAttribute('href'))) {
                    link.closest('.nav-item').classList.add('actives');
                }
            });
            document.getElementById("sidebarToggle").addEventListener("click", function() {
                document.getElementById("accordionSidebar").classList.toggle("d-none");
            });
        </script>