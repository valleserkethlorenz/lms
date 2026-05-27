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
                <div class=" p-4 cols border-1">
                    <div class="card shadow-sm mb-3">

                        <!-- DATE TOP -->
                        <div class="card-header bg-light py-2">
                            <div class="text-center fw-bold text-muted">
                                MAY 25, 2026
                            </div>
                        </div>

                        <!-- MATCH CONTENT -->
                        <div class="card-body">

                            <div class="d-flex justify-content-around align-items-center flex-wrap">
                                <span class="text-muted small">3:00 PM</span>

                                <!-- LEFT SIDE -->
                                <div class="d-flex align-items-center gap-2">


                                    <img src="vendor/images/logo.png" width="30" height="30" class="rounded-circle">

                                    <span class="fw-semibold">Team A</span>

                                    <span class="fw-bold text-danger">78</span>

                                </div>

                                <!-- VS -->
                                <div class="fw-bold text-muted mx-2">
                                    VS
                                </div>

                                <!-- RIGHT SIDE -->
                                <div class="d-flex align-items-center gap-2">

                                    <span class="fw-bold text-danger">78</span>

                                    <span class="fw-semibold">Team B</span>
                                    <img src="vendor/images/logo.png" width="30" height="30" class="rounded-circle">


                                </div>
                                <span class="badge bg-success px-3 py-2">
                                    Ongoing
                                </span>

                            </div>

                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
</div>