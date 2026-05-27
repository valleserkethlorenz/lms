<?php
include '../../config/dbconnect.php';
session_start();
$userId = $_SESSION['lms_id'];
$full = $_SESSION['lms_username'];
$userType = $_SESSION['lms_role'];

if ($userType == 1) {
    $_SESSION['loggin'];
    $_SESSION['loggin'] == true;
    $loggin = true;
    $time = strtotime("-10 year", time());
    $date = date("Y-m-d", $time);

    $today = date("M/d/Y");
    include 'includes/header.php';
    $pending = array();

    $count = 0;
    $pending = '0';




?>
    <style>

    </style>


    <body id="page-top" class="body">
        <?php
        include 'includes/bar.php';


        ?>
        <div class="container-fluid">
            <div class="row p-3">
                <div class="card cards">


                    <div class="card-body">
                        <div class="card-header m-2 card-headers">
                            <h4 style="font-weight:bold; color:white; letter-spacing:2px;">DASHBOARD OVERVIEW</span>
                        </div>
                        <div class="row g-3 p-4">
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                <div class="card dashboards h-100">
                                    <div class="card-body">
                                        <h5>Total League </h5>
                                        <div class="d-flex justify-content-between">
                                            <h1 class="mb-1" style="font-size:40px; color:black;">0</h1>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                <div class="card dashboards h-100">
                                    <div class="card-body">
                                        <h5>Ongoing League </h5>
                                        <div class="d-flex justify-content-between">
                                            <h1 class="mb-1" style="font-size:40px; color:black;">0</h1>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                <div class="card dashboards h-100">
                                    <div class="card-body">
                                        <h5>Pending Approval </h5>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h1 class="mb-1" style="font-size:40px; color:black;">0</h1>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                <div class="card dashboards h-100">
                                    <div class="card-body">
                                        <h5>Incoming League </h5>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h1 class="mb-1" style="font-size:40px; color:black;">0</h1>
                                        </div>
                                    </div>
                                </div>
                            </div>







                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered first">
                                <thead class="bg-light">
                                    <tr class="border-0">
                                        <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">#</th>
                                        <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">League </th>
                                        <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Sport</th>
                                        <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Team</th>
                                        <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Date</th>
                                        <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Status</th>

                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $count = '0';
                                    $fetch_league = "SELECT * FROM tbl_league";
                                    $league_res = mysqli_query($conn, $fetch_league);
                                    while ($league_row = mysqli_fetch_assoc($league_res)) {
                                        $start_date = date("F d, Y", strtotime($league_row['start_date']));
                                        $end_date = date("F d, Y", strtotime($league_row['end_date']));
                                        $count++;



                                        echo '<tr>
                                                              <td style="font-size: 18px;" class="text-center">
                                                                ' . $count . '
                                                            </td>
                                                         
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $league_row['league_name'] . '</td>
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $league_row['sport'] . '</td>
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $league_row['max_team'] . '</td>
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center">' . $start_date . '-' . $end_date . '</td>
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center"><span class="badge text-bg-primary p-2">' . $league_row['stat'] . '</span></td>
                                                           
                                                            
                                                        </tr>';
                                    }
                                    ?>

                                </tbody>
                            </table>
                        </div>

                    </div>


                    <?php
                    include 'includes/footer.php'; ?>
                    <script>
                        $(document).on("submit", "#approveForm", function(e) {
                            e.preventDefault();
                            var formData = new FormData(this);
                            formData.append("approveOrder", true);
                            Swal.fire({
                                title: "Are you sure to approve the order?",
                                showDenyButton: false,
                                showCancelButton: true,
                                confirmButtonText: "Yes",
                            }).then((result) => {
                                /* Read more about isConfirmed, isDenied below */
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: "action/transactionAction.php",
                                        type: "post",
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        success: function(response) {
                                            var res = jQuery.parseJSON(response);
                                            if (res.status == 400) {
                                                Swal.fire({
                                                    icon: "error",
                                                    title: "Something Went Wrong.",
                                                    text: res.msg,
                                                    timer: 10000,
                                                });
                                            } else if (res.status == 201) {
                                                Swal.fire({
                                                    icon: "success",
                                                    title: "SUCCESS",
                                                    text: res.msg,
                                                    timer: 2000,
                                                }).then(function() {
                                                    location.reload();
                                                });
                                            }
                                        },
                                    });
                                } else if (result.isDenied) {
                                    Swal.fire("Changes are not saved", "", "info").then(function() {
                                        location.reload();
                                    });
                                }
                            });
                        });
                        $(document).on("submit", "#markasDoneForm", function(e) {
                            e.preventDefault();
                            var formData = new FormData(this);
                            formData.append("completeOrder", true);
                            Swal.fire({
                                title: "Are you sure to mark as done the transaction?",
                                showDenyButton: false,
                                showCancelButton: true,
                                confirmButtonText: "Yes",
                            }).then((result) => {
                                /* Read more about isConfirmed, isDenied below */
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: "action/transactionAction.php",
                                        type: "post",
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        success: function(response) {
                                            var res = jQuery.parseJSON(response);
                                            if (res.status == 400) {
                                                Swal.fire({
                                                    icon: "error",
                                                    title: "Something Went Wrong.",
                                                    text: res.msg,
                                                    timer: 10000,
                                                });
                                            } else if (res.status == 201) {
                                                Swal.fire({
                                                    icon: "success",
                                                    title: "SUCCESS",
                                                    text: res.msg,
                                                    timer: 2000,
                                                }).then(function() {
                                                    location.reload();
                                                });
                                            }
                                        },
                                    });
                                } else if (result.isDenied) {
                                    Swal.fire("Changes are not saved", "", "info").then(function() {
                                        location.reload();
                                    });
                                }
                            });
                        });
                    </script>

    </body>

    </html>
<?Php
} else {
    header("location: /ordering/index.php");
}
?>