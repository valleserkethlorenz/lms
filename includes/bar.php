<?php
$loggin = $_SESSION['loggin_stat'] ?? null;
$userId = $_SESSION['lms_id'] ?? null;

?>
<style>
  .nav-link {
    position: relative;
    display: inline-block;
    text-decoration: none;
    letter-spacing: 1.4px;
    color: white;
  }

  .nav-link::after {
    content: "";
    position: absolute;
    height: 2px;
    bottom: -5px;
    left: 50%;
    width: 0;
    background-color: #ffffff;
    transition: all 0.3s ease;
    transform: translateX(-50%);
    color: #0d6efd;
  }

  /* hover animation: center → outward */
  .nav-link:hover::after {
    width: 100%;
    color: #0d6efd;
  }

  /* active state */
  .nav-link.active::after {
    width: 100%;
    background-color: #0d6efd;
    color: #0d6efd;
  }
</style>


<div class="dashboard-main-wrapper">
  <div class="dashboard-header">
    <nav class="navbar navbar-expand-lg navbar-light fixed-top mb-4" style="background-color:#09173c;">
      <div class="container-fluid">

        <a class="navbar-brand" style="color:white; font-size:25px;">
          <img src="vendor/images/logo.png" width="75" alt="" class="d-inline-block align-middle m-2 rounded-circle bg-white" style="filter: drop-shadow(1px 1px 4px #ffffff);">
          <div class="brand-text">
            <h5>City Sport and Development Program</h5>
            <p>League Management System</p>
          </div>
        </a>

        <?php $page = basename($_SERVER['PHP_SELF']); ?>

        <div class="ms-auto d-flex">
          <a class="nav-link font-weight-bold <?= ($page == 'index.php') ? 'active' : '' ?>"
            href="index.php">HOME</a>

          <a class="nav-link font-weight-bold <?= ($page == 'leagues.php') ? 'active' : '' ?>"
            href="leagues.php">LEAGUES</a>

          <a class="nav-link font-weight-bold <?= ($page == 'gallery.php') ? 'active' : '' ?>"
            href="gallery.php">GALLERY</a>

          <a class="nav-link font-weight-bold <?= ($page == 'about.php') ? 'active' : '' ?>"
            href="about.php">ABOUT</a>
          <?php if ($loggin == "true") {
          ?>
            <a type="button" class="btn btn-primary btn" href="app/tr/dashboard.php">DASHBOARD</a>

          <?php
          } else {
          ?>
            <a type="button" class="btn btn-primary btn" href="login.php">LOGIN</a>
          <?php
          } ?>
        </div>
      </div>
    </nav>

  </div>