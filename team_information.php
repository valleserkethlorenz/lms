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

    .info {
        border-radius: 10px;
        box-shadow: 1px 3px 3px 1px rgba(0, 0, 0, 0.2);
        border: 1px solid #d9d9d9;
        min-height: 100px;
    }

    .cards {
        border-radius: 15px;
        box-shadow: 1px 5px 5px 1px rgba(0, 0, 0, 0.2);
    }

    td {
        font-size: 12px;
    }
</style>
<?php
include 'includes/bar.php';

?>

<div class="container-fluid" style="padding-top: 120px;">

    <?php include 'includes/info_header.php';

    $fetch_team = "SELECT COUNT(*) as total_team 
               FROM tbl_team 
               WHERE league_id = '$id'";

    $team_res = mysqli_query($conn, $fetch_team);

    $team_row = mysqli_fetch_assoc($team_res);

    $total_team = $team_row['total_team'];

    $progress = 0;
    $max_team = $league_row['max_team'];
    if ($max_team > 0) {
        $progress = ($total_team / $max_team) * 100;
    }

    $progress = round($progress);


    // =========================
    // REMAINING SLOT
    // =========================
    $remaining = $max_team - $total_team;

    ?>
</div>
<div class="card-body">
    <div class="row g-4">

        <div class="col-lg-9">
            <div class="p-4 h-100">
                <div class="col-md-12 info m-2 p-3" style="min-height: 150px;">
                    <label for="" class="form-label fw-semibold">About</label><br>
                    <span class="text-left">
                        • <?php echo $league_row["add_info"]; ?>
                    </span>
                </div>
                <div class="col-md-12 info m-2 p-3">
                    <div class=" rounded-3 p-3 ">

                        <label class="form-label fw-semibold">
                            Registration Progress
                        </label>

                        <div class="d-flex align-items-center mt-2">

                            <!-- ICON -->
                            <div class="me-3">
                                <div class="bg-primary rounded-circle d-flex justify-content-center align-items-center"
                                    style="width:50px; height:50px;">

                                    <i class="bi bi-people-fill text-white fs-4"></i>

                                </div>
                            </div>

                            <!-- CONTENT -->
                            <div class="flex-grow-1">

                                <!-- TOP -->
                                <div class="d-flex justify-content-between align-items-center">

                                    <div>
                                        <h4 class="mb-0 fw-bold">
                                            <?php echo $total_team; ?>/<?php echo $max_team; ?>
                                        </h4>

                                        <small class="text-muted">
                                            Team Slots Filled
                                        </small>
                                    </div>

                                    <small class="fw-semibold">
                                        <?php echo $progress; ?>%
                                    </small>

                                </div>

                                <!-- PROGRESS -->
                                <div class="progress mt-2" style="height:8px;">
                                    <div class="progress-bar bg-primary"
                                        role="progressbar"
                                        style="width: <?php echo $progress; ?>%">
                                    </div>
                                </div>

                                <!-- FOOTER -->
                                <div class="d-flex justify-content-between mt-2">

                                    <small class="text-muted">
                                        Registration closes on
                                        <?php echo date("M d, Y (h:i A)", strtotime($league_row['regis_end'])); ?>
                                    </small>

                                    <small class="text-success fw-semibold">
                                        <?php echo $remaining; ?> Slots Remaining
                                    </small>

                                </div>

                            </div>

                        </div>
                    </div>
                </div>
                <div class="col-md-12 info m-2 p-3" style="min-height: 150px;">
                    <label for="" class="form-label fw-semibold">Eligibility</label>
                    <h3 class="text-center">
                        <?php echo $league_row["add_info"]; ?>
                    </h3>
                </div>
                <div class="col-md-12 info m-2 p-3" style="min-height: 150px;">
                    <label for="" class="form-label fw-semibold">Announcment</label>
                    <h3 class="text-center">
                        No announcement
                    </h3>
                </div>
                <!-- <div class="col-md-12 info m-2 p-3" style="min-height: 150px;">
                    <label for="" class="form-label fw-semibold">Ready to Compete</label>
                </div> -->


            </div>
        </div>

        <div class="col-lg-3">
            <div class="p-4  h-100">

                <div class="info p-3">

                    <label class="form-label fw-bold mb-3">
                        League Information
                    </label>

                    <table class="table table-borderless mb-0">

                        <tr>
                            <td class="fw-semibold text-muted">League Name</td>
                            <td class="text-end"> <?php echo $league_row["league_name"]; ?>
                            </td>
                        </tr>

                        <tr>
                            <td class="fw-semibold text-muted">Sport</td>
                            <td class="text-end"> <?php echo $league_row["sport"]; ?>
                            </td>
                        </tr>

                        <tr>
                            <td class="fw-semibold text-muted">Division</td>
                            <td class="text-end"> <?php echo $league_row["division"]; ?>
                            </td>
                        </tr>

                        <tr>
                            <td class="fw-semibold text-muted">Category</td>
                            <td class="text-end"> <?php echo $league_row["category"]; ?>
                            </td>
                        </tr>

                        <tr>
                            <td class="fw-semibold text-muted">Team Limit</td>
                            <td class="text-end"> <?php echo $league_row["max_team"]; ?>
                            </td>
                        </tr>

                        <tr>
                            <td class="fw-semibold text-muted">Roster Limit</td>
                            <td class="text-end"> <?php echo $league_row["max_player"]; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Registration Fee</td>
                            <td class="text-end"> <?php echo $league_row["regis_fee"]; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Tournament Period</td>
                            <td class="text-end"> <?php echo $start_date . '-' . $end_date; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Venue</td>
                            <td class="text-end"> <?php echo $league_row["venue"]; ?></td>
                        </tr>
                        <!-- <tr>
                            <td class="fw-semibold text-muted">Contact Person</td>
                            <td class="text-end">City Gymnasium</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Contact Number</td>
                            <td class="text-end">City Gymnasium</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Email Address</td>
                            <td class="text-end">City Gymnasium</td>
                        </tr> -->

                    </table>

                </div>
                <div class="info p-3 mt-2">

                    <label class="form-label fw-bold mb-3">
                        Document
                    </label>

                    <ul>
                        <li>test1</li>
                        <li>test1</li>
                        <li>test1</li>
                    </ul>

                </div>
                <div class="info p-3 mt-2">

                    <label class="form-label fw-bold mb-3">
                        Inquiries
                    </label>
                    <button class="btn-light btn">
                        Contact Us
                    </button>

                </div>

            </div>
        </div>
    </div>
</div>
</div>
</div>
</div>