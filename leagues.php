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
    <div class="card bg-transparent border-0">
        <div class="card-header bg-transparent border-0">
            <h2 class="font-weight-bolder">Browse Leagues</h2>
        </div>
        <div class="card-body">
            <div class="row align-items-start g-4">
                <div class="col-3 ">
                    <div class="p-4 h-100 cols">
                        <span class="text-uppercase font-weight-bolder text-muted mt-1">Filter</span>
                        <div class="col-12">
                            <p class="text-muted font-weight-bolder mt-1">Sport</p>
                        </div>
                        <div class="col-12">
                            <input type="checkbox">
                            <label for="" class="form-label">All Sport</label>
                        </div>
                        <div class="col-12">
                            <input type="checkbox">
                            <label for="" class="form-label">Basketball</label>
                        </div>
                        <div class="col-12">
                            <input type="checkbox">
                            <label for="" class="form-label">Volleyball</label>
                        </div>
                        <div class="col-12">
                            <p class="text-muted font-weight-bolder mt-1">Status</p>
                        </div>
                        <div class="col-12">
                            <input type="checkbox">
                            <label for="" class="form-label">Upcoming</label>
                        </div>
                        <div class="col-12">
                            <input type="checkbox">
                            <label for="" class="form-label">Total Registration</label>
                        </div>
                        <div class="col-12">
                            <input type="checkbox">
                            <label for="" class="form-label">Ongoing</label>
                        </div>
                        <div class="col-12">
                            <input type="checkbox">
                            <label for="" class="form-label">Ended</label>
                        </div>
                        <div class="text-center">
                            <div class="col-12 m-2">
                                <button class="btn-primary btn" style="border:1px solid white; padding:5px 50px;"> Apply Filters</button>
                            </div>
                            <div class="col-12 m-2">
                                <button class="btn-light  btn" style="border:1px solid blue; padding:5px 50px;"> Reset Filters</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-9 ">
                    <div class=" p-4 cols">
                        <div class="row g-3 p-4">
                            <?php $count = '0';
                            $fetch_league = "SELECT * FROM tbl_league";
                            $league_res = mysqli_query($conn, $fetch_league);
                            while ($league_row = mysqli_fetch_assoc($league_res)) {
                                $start_date = date("F d, Y", strtotime($league_row['start_date']));
                                $end_date = date("F d, Y", strtotime($league_row['end_date']));
                                $count++;
                            ?>
                                <div class="col-xl-3 col-lg-4 col-md-6">
                                    <div class="card cards">
                                        <div class="card-header position-relative p-0 border-0 overflow-hidden">

                                            <!-- IMAGE -->
                                            <img src="vendor/images/uploads/<?php echo  $league_row['league_image']; ?>"
                                                alt=""
                                                class="img-fluid w-100"
                                                style="object-fit:cover;">
                                            <!-- BADGE OVERLAY -->
                                            <span class="badge bg-success position-absolute top-0 end-0 m-3 px-3 py-2">
                                                <?php echo $league_row['stat']; ?>
                                            </span>

                                        </div>
                                        <div class="card-body p-2 pt-0">
                                            <div class="p-1">

                                                <p class=" mb-0 font-weight-bolder text-capitalize"><?php echo $league_row['league_name']; ?></p>
                                            </div>
                                            <div class="p-3 bg-light rounded bg-transparent ">

                                                <div class="d-grid gap-2">
                                                    <span><i class="bi bi-calendar"></i> <?php echo $start_date . '-' . $end_date; ?></span>
                                                    <span><i class="bi bi-person"></i> <?php echo $league_row['max_team']; ?></span>
                                                </div>
                                            </div>
                                            <div class="text-center">
                                                <a type="button" href="team_information.php?id=<?php echo $league_row["id"]; ?>" class="btn-light btn" style="border:1px solid blue;">View details</a>

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