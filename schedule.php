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
                        <?php

                        $count = 0;

                        $fetch_sched = "
    SELECT 
        tbl_schedule.*,
        ta.team_name AS team_a_name,
        tb.team_name AS team_b_name
    FROM tbl_schedule
    INNER JOIN tbl_team AS ta 
        ON tbl_schedule.team_a = ta.id
    INNER JOIN tbl_team AS tb 
        ON tbl_schedule.team_b = tb.id
    WHERE tbl_schedule.league_id = $id
    ORDER BY tbl_schedule.date ASC, tbl_schedule.time ASC
";

                        $sched_res = mysqli_query($conn, $fetch_sched);

                        $schedules = [];

                        while ($row = mysqli_fetch_assoc($sched_res)) {
                            $schedules[$row['date']][] = $row;
                        }

                        ?>

                        <!-- DISPLAY -->
                        <?php foreach ($schedules as $date => $matches): ?>

                            <!-- DATE HEADER -->
                            <div class="card-header bg-light py-2 mt-3">
                                <div class="text-center fw-bold text-muted">
                                    <?= date("F d, Y", strtotime($date)) ?>
                                </div>
                            </div>

                            <?php foreach ($matches as $row): ?>

                                <div class="card-body border-bottom">

                                    <div class="d-flex justify-content-around align-items-center flex-wrap">

                                        <!-- TIME -->
                                        <span class="text-muted small">
                                            <?= date("h:i A", strtotime($row['time'])) ?>
                                        </span>

                                        <!-- TEAM A -->
                                        <div class="d-flex align-items-center gap-2">

                                            <img src="vendor/images/logo.png" width="30" height="30" class="rounded-circle">

                                            <span class="fw-semibold">
                                                <?= htmlspecialchars($row['team_a_name']) ?>
                                            </span>

                                            <span class="fw-bold text-danger">
                                                <?= $row['team_a_score'] ?? 0 ?>
                                            </span>

                                        </div>

                                        <!-- VS -->
                                        <div class="fw-bold text-muted mx-2">VS</div>

                                        <!-- TEAM B -->
                                        <div class="d-flex align-items-center gap-2">

                                            <span class="fw-bold text-danger">
                                                <?= $row['team_b_score'] ?? 0 ?>
                                            </span>

                                            <span class="fw-semibold">
                                                <?= htmlspecialchars($row['team_b_name']) ?>
                                            </span>

                                            <img src="vendor/images/logo.png" width="30" height="30" class="rounded-circle">

                                        </div>

                                        <!-- STATUS -->
                                        <?php
                                        $status = strtolower($row['status']);

                                        if ($status == "pending") {
                                            $badge = "bg-warning";
                                        } elseif ($status == "approved" || $status == "ongoing") {
                                            $badge = "bg-success";
                                        } elseif ($status == "rejected" || $status == "cancelled") {
                                            $badge = "bg-danger";
                                        } else {
                                            $badge = "bg-secondary";
                                        }
                                        ?>

                                        <span class="badge <?= $badge ?> px-3 py-2">
                                            <?= ucfirst($row['status']) ?>
                                        </span>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php endforeach; ?>
                        <!-- DATE TOP -->
                        

                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
</div>