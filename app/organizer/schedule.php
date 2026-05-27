<?php
include '../../config/dbconnect.php';
session_start();
$userId = $_SESSION['lms_id'];
$full = $_SESSION['lms_username'];
$userType = $_SESSION['lms_role'];
$id = $_GET["id"];


if ($userType == 1) {
    $_SESSION['loggin'];
    $_SESSION['loggin'] == true;
    $loggin = true;
    $time = strtotime("-10 year", time());
    $date = date("Y-m-d", $time);

    $today = date("M/d/Y");
    include 'includes/header.php';
    $fetch_league = "SELECT * FROM tbl_league WHERE id = $id";
    $league_res = mysqli_query($conn, $fetch_league);
    $league_row = mysqli_fetch_assoc($league_res);
?>

    <body id="page-top" class="body">
        <?php
        include 'includes/bar.php';


        ?>

        <div class="container-fluid">
            <div class="row p-3">
                <div class="card cards">


                    <div class="card-body">
                        <a href="league.php" class="text-black font-weight-bold">
                            < Back</a>
                                <div class="card-header m-2 card-headers">
                                    <h4 class="header_title text-uppercase" style="font-weight:bold; color:white; letter-spacing:2.5px;">League event : <?php echo  $league_row['league_name']; ?></span>

                                </div>
                                <?php $page = basename($_SERVER['PHP_SELF']); ?>
                                <div class="p-2">
                                    <ul class="nav nav-underline nav-fill border-bottom mb-4">

                                        <li class="nav-item">
                                            <a class="nav-link fw-semibold <?= ($page == 'league_info.php') ? 'active text-primary' : 'text-dark' ?>"
                                                href="league_info.php?id=<?php echo $id; ?>">
                                                Overview
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link fw-semibold <?= ($page == 'schedule.php') ? 'active text-primary' : 'text-dark' ?>"
                                                href="schedule.php?id=<?php echo $id; ?>">
                                                Schedule
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link fw-semibold <?= ($page == 'standing.php') ? 'active text-primary' : 'text-dark' ?>"
                                                href="standing.php?id=<?php echo $id; ?>">
                                                Standing
                                            </a>
                                        </li>

                                    </ul>
                                </div>
                                <div class="row p-4">
                                    <div class="d-flex flex-row-reverse">
                                        <button class="btn btn-primary float-right btn-sm m-3 p-2" style="color:white;border:none; border-radius:10px; font-weight:bold" data-bs-toggle="modal" data-bs-target="#createSched">
                                            Create Schedule</button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered first">
                                            <thead class="bg-light">
                                                <tr class="border-0">
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">#</th>
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Team A </th>
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Team B</th>
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Date</th>
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Time</th>
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Status</th>
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $count = '0';
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
                                                    ";
                                                $sched_res = mysqli_query($conn, $fetch_sched);
                                                while ($row = mysqli_fetch_assoc($sched_res)) {
                                                    $count++;

                                                    $status = strtolower($row["status"]);
                                                    $date = date("F d, Y", strtotime($row['date']));
                                                    $time = date("h:i a", strtotime($row['time']));

                                                    if ($status == "Upcoming") {
                                                        $badge = "text-bg-warning";
                                                    } elseif ($status == "approved") {
                                                        $badge = "text-bg-success";
                                                    } elseif ($status == "rejected") {
                                                        $badge = "text-bg-danger";
                                                    } else {
                                                        $badge = "text-bg-secondary";
                                                    }
                                                    echo '<tr>
                                                              <td style="font-size: 18px;" class="text-center">
                                                                ' . $count . '
                                                            </td>
                                                         
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $row['team_a_name'] . '</td>
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $row['team_b_name'] . '</td>
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $date . '</td>
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $time . '</td>
                                                          
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center"><span class="badge ' . $badge . ' p-2">' . $row["status"] . '</span></td>
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center"><a type="button" class="btn btn-primary" href="view_player.php?id=' . $row["id"] . '">View</a></td>
                                                           
                                                            
                                                        </tr>';
                                                }
                                                ?>

                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                    </div>
   <div class="modal fade" id="createSched" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="createSched" aria-hidden="true">
       <div class="modal-dialog modal-xl">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title">Post Schedule</h5>
               </div>
               <div class="modal-body">
                   <form class="row g-3" id="scheduleForm">
                       <div class="col-md-6">
                           <label for="team_a" class="form-label">Team A<sup class="text-danger">*</sup>:</label>
                           <select name="team_a" id="team_a" class="form-select">
                               <option disabled selected>Choice Team A</option>
                               <?php
                                $fetch_team = "SELECT * FROM tbl_team where league_id = $id";
                                $team_res = mysqli_query($conn, $fetch_team);
                                while ($row = mysqli_fetch_assoc($team_res)) {
                                    echo '
                                        <option value="' . $row["id"] . '">' . $row["team_name"] . '</option>
                                    ';
                                }
                                ?>
                           </select>
                       </div>
                       <div class="col-md-6">
                           <label for="team_b" class="form-label">Team B<sup class="text-danger">*</sup>:</label>
                           <select name="team_b" id="team_b" class="form-select">
                               <option disabled selected>Choice Team B</option>
                               <?php
                                $fetch_team = "SELECT * FROM tbl_team where league_id = $id";
                                $team_res = mysqli_query($conn, $fetch_team);
                                while ($row = mysqli_fetch_assoc($team_res)) {
                                    echo '
                                        <option value="' . $row["id"] . '">' . $row["team_name"] . '</option>
                                    ';
                                }
                                ?>
                           </select>
                       </div>

                       <div class="col-md-6">
                           <label for="date" class="form-label">Date<sup class="text-danger">*</sup>:</label>
                           <input name="date" class="form-control" id="date" type="date" required>
                       </div>
                       <div class="col-md-6">
                           <label for="time" class="form-label">time<sup class="text-danger">*</sup>:</label>
                           <input name="time" class="form-control" id="time" type="time" required>
                       </div>

                       <div class="modal-footer">
                           <input type="hidden" name="league_id" value="<?php echo $id; ?>">
                           <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                           <button type="submit" class="btn btn-primary">
                               Create
                           </button>
                       </div>
                   </form>
               </div>

           </div>
       </div>
   </div>

                    <?php
                    include '../../components/modal/orgModal.php';
                    include 'includes/footer.php'; ?>
                    <!-- <script src="../../components/script/adminCat.js"></script> -->
                    <script>
                        $(document).on('submit', '#scheduleForm', function(e) {
                            e.preventDefault();
                            var formData = new FormData(this);
                            formData.append("schedule", true);
                            $.ajax({
                                url: "action/leagueAction.php",
                                type: "post",
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function(data) {
                                    var res = jQuery.parseJSON(data);
                                    if (res.status == 401) {
                                        Swal.fire({
                                            icon: 'warning',
                                            title: res.msg,
                                            // text: 'Something Went Wrong.',
                                            timer: 10000
                                        })
                                    } else if (res.status == 201) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'SUCCESS',
                                            text: res.msg,
                                            timer: 2000
                                        }).then(function() {
                                            location.reload();
                                        });
                                    }
                                }

                            });

                        });
                    </script>


    </body>

    </html>
<?Php
} else {
    header("location: /lms/index.php");
}
?>