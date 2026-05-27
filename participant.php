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
    .cols {
        border-radius: 15px;
        box-shadow: 1px 5px 5px 1px rgba(0, 0, 0, 0.2);
        border: 1px solid #d9d9d9;
        border-radius: 25px;
        min-height: 700px;
    }

    .cards {
        border-radius: 15px;
        box-shadow: 1px 5px 5px 1px rgba(0, 0, 0, 0.2);
    }
</style>
<?php
include 'includes/bar.php';

?>

<div class="container-fluid" style="padding-top: 120px;">
    <?php include 'includes/info_header.php'; ?>
    <div class="card-body">
        <div class="row align-items-start g-4">

            <div class="col-12 ">
                <div class=" p-4 cols">
                    <div class="row g-3 p-4">
                        <?php
                        $count = '0';
                        $fetch_team = "SELECT * FROM tbl_team where league_id = $id";
                        $team_res = mysqli_query($conn, $fetch_team);
                        while ($row = mysqli_fetch_assoc($team_res)) {

                        ?>
                            <div class="col-xl-3 col-lg-3 col-md-3">
                                <div class="card cards">
                                    <div class="card-header p-3 border-0 bg-transparent text-center">

                                        <!-- IMAGE -->
                                        <img src="vendor/images/uploads/<?Php echo $row["team_image"]; ?>"
                                            alt=""
                                            class="img-fluid " style="border-radius: 15px; height:250px;">
                                    </div>
                                    <div class="card-body p-2 pt-0">
                                        <div class="p-1 text-center">
                                            <p class=" mb-0 font-weight-bolder text-uppercase"><?php echo $row["team_name"]; ?></p>
                                        </div>

                                    </div>
                                </div>
                            </div>

                        <?php
                        }
                        ?>


                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
</div>