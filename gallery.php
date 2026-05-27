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
    <div class="card-body">
        <div class="row align-items-start g-4">

            <div class="col-md-12 mt-4">

                <div class="card shadow-sm border-0 rounded-4">

                    <!-- HEADER -->
                    <div class="card-header bg-white border-0 pt-4">
                        <h3 class="fw-bold text-center mb-0">
                            League Gallery
                        </h3>

                        <p class="text-muted text-center mb-0">
                            Highlights and memorable moments from the tournament
                        </p>
                    </div>

                    <!-- BODY -->
                    <div class="card-body">

                        <div class="row g-3">

                            <!-- IMAGE 1 -->
                            <div class="col-md-4">
                                <div class="overflow-hidden rounded-4 shadow-sm">
                                    <img src="vendor/images/image1.avif"
                                        class="img-fluid w-100"
                                        style="height:250px; object-fit:cover;">

                                    <div class="p-3 bg-white">
                                        <h6 class="fw-bold mb-1">
                                            Opening Match
                                        </h6>

                                        <small class="text-muted">
                                            Exciting start of the tournament season
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- IMAGE 2 -->
                            <div class="col-md-4">
                                <div class="overflow-hidden rounded-4 shadow-sm">
                                    <img src="vendor/images/image2.avif"
                                        class="img-fluid w-100"
                                        style="height:250px; object-fit:cover;">

                                    <div class="p-3 bg-white">
                                        <h6 class="fw-bold mb-1">
                                            Team Celebration
                                        </h6>

                                        <small class="text-muted">
                                            Players celebrating after a hard-fought victory
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- IMAGE 3 -->
                            <div class="col-md-4">
                                <div class="overflow-hidden rounded-4 shadow-sm">
                                    <img src="vendor/images/image3.avif"
                                        class="img-fluid w-100"
                                        style="height:250px; object-fit:cover;">

                                    <div class="p-3 bg-white">
                                        <h6 class="fw-bold mb-1">
                                            Championship Game
                                        </h6>

                                        <small class="text-muted">
                                            Intense action during the finals match
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- IMAGE 4 -->
                            <div class="col-md-6">
                                <div class="overflow-hidden rounded-4 shadow-sm">
                                    <img src="vendor/images/image4.avif"
                                        class="img-fluid w-100"
                                        style="height:300px; object-fit:cover;">

                                    <div class="p-3 bg-white">
                                        <h6 class="fw-bold mb-1">
                                            Crowd & Fans
                                        </h6>

                                        <small class="text-muted">
                                            Supporters cheering throughout the tournament
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- IMAGE 5 -->
                            <div class="col-md-6">
                                <div class="overflow-hidden rounded-4 shadow-sm">
                                    <img src="vendor/images/image5.avif"
                                        class="img-fluid w-100"
                                        style="height:300px; object-fit:cover;">

                                    <div class="p-3 bg-white">
                                        <h6 class="fw-bold mb-1">
                                            Award Ceremony
                                        </h6>
                                        <small class="text-muted">
                                            Champions receiving their trophies and medals
                                        </small>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>
    </div>
</div>
</div>