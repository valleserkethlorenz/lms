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

            <div class="col-md-12">
                <div class="card shadow-sm border-0 rounded-4" style="min-height: 300px;">

                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-5">

                        <h2 class="fw-bold mb-3">
                            About The League
                        </h2>

                        <p class="text-muted w-75">
                            Welcome to our league tournament where teams compete with passion,
                            teamwork, and sportsmanship. This event is designed to provide
                            athletes an exciting and competitive environment while promoting
                            unity, discipline, and camaraderie among participants.

                            Teams will battle through the group stages and knockout rounds
                            to claim the championship title. Stay updated with schedules,
                            standings, and match results throughout the tournament.
                        </p>

                        <div class="mt-3">
                            <span class="badge bg-primary px-3 py-2">
                                Competitive
                            </span>

                            <span class="badge bg-success px-3 py-2">
                                Fair Play
                            </span>

                            <span class="badge bg-dark px-3 py-2">
                                Championship
                            </span>
                        </div>

                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
</div>