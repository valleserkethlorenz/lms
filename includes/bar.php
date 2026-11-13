<?php
$userId = (int)($_SESSION['lms_id'] ?? 0);
$userRole = (int)($_SESSION['lms_role'] ?? 0);
$isAuthenticated = $userId > 0 && in_array($userRole, [1, 2, 3, 4], true);
?>
<style>
  .nav-link {
    position: relative;
    display: inline-block;
    text-decoration: none;
    letter-spacing: 1.2px;
    color: white;
    font-size: 13px;
    font-weight: 400;
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

  /* Login button */
  .navbar .btn-login {
    background: #0d6efd;
    border: none;
    color: #fff;
    font-size: 13px;
    font-weight: 500;
    letter-spacing: 0.6px;
    padding: 6px 18px;
    border-radius: 50px;
    transition: all 0.25s ease;
    box-shadow: 0 2px 6px rgba(13,110,253,0.25);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    line-height: 1.4;
  }

  .navbar .btn-login:hover {
    background: #0b5ed7;
    box-shadow: 0 4px 12px rgba(13,110,253,0.4);
    transform: translateY(-1px);
    color: #fff;
  }

  .navbar .btn-login:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(13,110,253,0.25);
  }

  .navbar .btn-login i {
    font-size: 14px;
  }

</style>


<div class="dashboard-main-wrapper">
  <div class="dashboard-header">
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top mb-4" style="background-color:#09173c; padding-top:4px; padding-bottom:4px;">
      <div class="container-fluid">

        <a class="navbar-brand" style="color:white; font-size:14px;">
          <img src="assets\images\logo.jpg" alt="CSDP Logo" class="d-inline-block align-middle m-2" style="width:40px;height:40px;border-radius:50%;object-fit:cover;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.18);">
          <div class="brand-text">
            <h5>City Sports Development Program</h5>
            <p>League Management System</p>
          </div>
        </a>

        <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar" aria-controls="publicNavbar" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>

        <?php $page = basename($_SERVER['PHP_SELF']); ?>
                <?php
                $is_tr = $isAuthenticated && $userRole === 2;
                ?>

                <div class="collapse navbar-collapse" id="publicNavbar">
                  <?php if (!$is_tr) : ?>
                    <div class="ms-auto d-flex align-items-center">
                      <a class="nav-link <?= ($page == 'index.php') ? 'active' : '' ?>" href="index.php"><i class="bi bi-house-door me-1"></i>Home</a>

                      <a class="nav-link <?= ($page == 'leagues.php') ? 'active' : '' ?>" href="leagues.php"><i class="bi bi-trophy me-1"></i>Leagues</a>

                      <a class="nav-link <?= ($page == 'gallery.php') ? 'active' : '' ?>" href="gallery.php"><i class="bi bi-images me-1"></i>Gallery</a>

                      <a class="nav-link <?= ($page == 'about.php') ? 'active' : '' ?>" href="about.php"><i class="bi bi-info-circle me-1"></i>About</a>
                      <?php if ($isAuthenticated) {
                        // Signed-in users keep a route back to their own portal from
                        // every public page, where the login button is hidden.
                        $portalByRole = [
                          1 => 'app/organizer/dashboard.php',
                          2 => 'app/tr/dashboard.php',
                          3 => 'app/admin/dashboard.php',
                          4 => 'app/committee/dashboard.php',
                        ];
                        $portalUrl = $portalByRole[$userRole] ?? 'index.php';
                      ?>
                        <a class="btn-login ms-2" href="<?= htmlspecialchars($portalUrl) ?>"><i class="bi bi-speedometer2"></i>Dashboard</a>
                      <?php } else {
                        // Hide the login button when already on the login page
                        if ($page !== 'login.php') { ?>
                          <a class="btn-login ms-2" href="login.php"><i class="bi bi-box-arrow-in-right"></i>Login</a>
                      <?php }
                      } ?>
                    </div>
                  <?php endif; ?>
                </div>
      </div>
    </nav>

  </div>
