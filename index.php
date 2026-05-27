<?php

session_start();
include 'config/dbconnect.php';



$time = strtotime("-10 year", time());
$date = date("Y-m-d", $time);
include 'includes/header.php';
include 'config/dbconnect.php'; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style rel="stylesheet">
  /* HERO SECTION */
  .hero {
    min-height: 100vh;
    background:
      linear-gradient(to right, rgba(3, 12, 39, 0.95), rgba(3, 12, 39, 0.65)),
      url('https://images.unsplash.com/photo-1574629810360-7efbbe195018?q=80&w=1920&auto=format&fit=crop') center/cover no-repeat;
    display: flex;
    align-items: center;
  }

  .hero-content {
    max-width: 650px;
  }

  .hero h3 {
    color: white;
    font-size: 60px;
    font-weight: 800;
    line-height: 1.05;
  }

  .hero h3 span {
    color: #1f6fff;
  }

  .league-btn {
    margin-top: 25px;
    background: #1f6fff;
    color: white;
    padding: 16px 35px;
    border-radius: 18px;
    font-size: 22px;
    font-weight: 600;
    border: none;
    text-decoration: none;
  }

  .league-btn:hover {
    background: #1256d1;
    color: white;
    text-decoration: none;

  }
</style>
<?php
include 'includes/bar.php';
?>

<body id="page-top">
  <section class="hero">
    <div class="container">

      <div class="hero-content">

        <h3>
          Connecting <br>
          Communities, <br>
          Fostering Future <br>
          <span>Champions.</span>
        </h3>

        <a type="button" class="league-btn" href="leagues.php">
          View Leagues
          <i class="bi bi-trophy ms-2"></i>
        </a>

      </div>

    </div>
  </section>




  <!-- Bootstrap core JavaScript-->
  <?php
  include 'includes/footer.php';

  ?>





</body>