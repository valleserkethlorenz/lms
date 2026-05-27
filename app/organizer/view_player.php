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
    $fetch_team = "SELECT * FROM tbl_team WHERE  id = $id ";
    $team_res = mysqli_query($conn, $fetch_team);
    $team_row = mysqli_fetch_assoc($team_res);
?>

    <body id="page-top" class="body">
        <?php
        include 'includes/bar.php';


        ?>
        <div class="container-fluid">
            <div class="row p-3">
                <div class="card cards">


                    <div class="card-body">
                        <a href="league_info.php?id=<?php echo $team_row["league_id"]; ?>" class="text-black font-weight-bold">
                            < Back</a>
                                <div class="card-header m-2 card-headers">
                                    <h4 class="header_title text-uppercase" style="font-weight:bold; color:white; letter-spacing:2.5px;">TEAM : <?php echo  $team_row['team_name']; ?></span>
                                </div>
                                <div class="row p-4">
                                    <?php
                                    if ($team_row["status"] == "pending") {
                                    ?>
                                        <div class="d-flex flex-row-reverse">
                                            <form id="approvedForm">
                                                <button class="btn btn-success float-right btn-sm m-3 p-3" style="color:white;border:none; border-radius:10px; font-weight:bold" type="submit">

                                                    Approve</button>
                                                <input type="hidden" name="team_id" value="<?php echo $id; ?>">
                                            </form>
                                            <form id="rejectForm">
                                                <button class="btn btn-danger float-right btn-sm m-3 p-3" style="color:white;border:none; border-radius:10px; font-weight:bold" type="submit">
                                                    Reject</button>
                                                <input type="hidden" name="team_id" value="<?php echo $id; ?>">

                                            </form>

                                        </div>
                                    <?php } ?>

                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered first">
                                            <thead class="bg-light">
                                                <tr class="border-0">
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">#</th>
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Player name </th>
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Age</th>
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Role</th>
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Gender</th>
                                                    <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Jersey #</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $count = '0';
                                                $fetch_player = "SELECT * FROM tbl_player where team_id = $id ";
                                                $player_res = mysqli_query($conn, $fetch_player);
                                                while ($player_row = mysqli_fetch_assoc($player_res)) {
                                                    $count++;
                                                    echo '<tr>
                                                              <td style="font-size: 18px;" class="text-center">
                                                                ' . $count . '
                                                            </td>
                                                         
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $player_row['name'] . '</td>
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $player_row['age'] . '</td>
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $player_row['role'] . '</td>
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $player_row['gender'] . '</td>
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $player_row['jersey_number'] . '</td>
                                                           
                                                            
                                                        </tr>';
                                                }
                                                ?>

                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                    </div>


                    <?php
                    include '../../components/modal/orgModal.php';
                    include 'includes/footer.php'; ?>
                    <!-- <script src="../../components/script/adminCat.js"></script> -->
                    <script>
                        $(document).on('submit', '#rejectForm', function(e) {
                            e.preventDefault();
                            var formData = new FormData(this);
                            formData.append("reject", true);
                            Swal.fire({
                                title: 'Are you sure you want to reject this team?',
                                showDenyButton: false,
                                showCancelButton: true,
                                confirmButtonText: 'Yes',
                            }).then((result) => {
                                /* Read more about isConfirmed, isDenied below */
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: "action/leagueAction.php",
                                        type: "post",
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        success: function(response) {
                                            var res = jQuery.parseJSON(response);
                                            if (res.status == 400) {
                                                Swal.fire({
                                                    icon: 'error',
                                                    title: 'Something Went Wrong.',
                                                    text: res.msg,
                                                    timer: 10000
                                                })
                                            } else if (res.status == 201) {
                                                Toast.fire({
                                                    icon: "success",
                                                    title: res.msg
                                                }).then(() => {
                                                    location.reload();

                                                });
                                            }
                                        }
                                    })
                                } else if (result.isDenied) {
                                    Swal.fire('Changes are not saved', '', 'info').then(function() {
                                        location.reload();
                                    });
                                }
                            })
                        });
                        $(document).on('submit', '#approvedForm', function(e) {
                            e.preventDefault();
                            var formData = new FormData(this);
                            formData.append("approved", true);
                            Swal.fire({
                                title: 'Are you sure you want to approved this team?',
                                showDenyButton: false,
                                showCancelButton: true,
                                confirmButtonText: 'Yes',
                            }).then((result) => {
                                /* Read more about isConfirmed, isDenied below */
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: "action/leagueAction.php",
                                        type: "post",
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        success: function(response) {
                                            var res = jQuery.parseJSON(response);
                                            if (res.status == 400) {
                                                Swal.fire({
                                                    icon: 'error',
                                                    title: 'Something Went Wrong.',
                                                    text: res.msg,
                                                    timer: 10000
                                                })
                                            } else if (res.status == 201) {
                                                Toast.fire({
                                                    icon: "success",
                                                    title: res.msg
                                                }).then(() => {
                                                    location.reload();

                                                });
                                            }
                                        }
                                    })
                                } else if (result.isDenied) {
                                    Swal.fire('Changes are not saved', '', 'info').then(function() {
                                        location.reload();
                                    });
                                }
                            })
                        });
                    </script>


    </body>

    </html>
<?Php
} else {
    header("location: /lms/index.php");
}
?>