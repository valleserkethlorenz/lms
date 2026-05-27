<?php
include '../../config/dbconnect.php';
session_start();
$userId = $_SESSION['lms_id'];
$full = $_SESSION['lms_username'];
$userType = $_SESSION['lms_role'];

if ($userType == 2) {
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
                            <h4 style="font-weight:bold; color:white; letter-spacing:2px;">NOTIFICATION</span>
                        </div>
                        <div class="col-md-12 mt-4">

                            <div class="card shadow-sm border-0 rounded-4">

                                <!-- HEADER -->
                                <div class="card-header bg-white border-0 pt-4 d-flex justify-content-between align-items-center">

                                    <div>
                                        <h3 class="fw-bold mb-0">
                                            Notifications
                                        </h3>

                                        <small class="text-muted">
                                            Latest league updates and announcements
                                        </small>
                                    </div>

                                    <span class="badge bg-danger rounded-pill px-3 py-2">
                                        3 New
                                    </span>

                                </div>

                                <!-- BODY -->
                                <div class="card-body">

                                    <!-- NOTIFICATION ITEM -->
                                    <div class="d-flex align-items-start border-bottom pb-3 mb-3">

                                        <!-- ICON -->
                                        <div class="me-3">
                                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex justify-content-center align-items-center"
                                                style="width:50px; height:50px;">

                                                <i class="bi bi-trophy-fill text-primary fs-4"></i>

                                            </div>
                                        </div>

                                        <!-- CONTENT -->
                                        <div class="flex-grow-1">

                                            <div class="d-flex justify-content-between">

                                                <h6 class="fw-bold mb-1">
                                                    Quarter Finals Schedule Released
                                                </h6>

                                                <small class="text-muted">
                                                    5 mins ago
                                                </small>

                                            </div>

                                            <p class="text-muted mb-1">
                                                The official quarter finals match schedule is now available.
                                                Teams are advised to check their assigned match dates.
                                            </p>

                                            <a href="#" class="small text-decoration-none">
                                                View Details
                                            </a>

                                        </div>

                                    </div>

                                    <!-- NOTIFICATION ITEM -->
                                    <div class="d-flex align-items-start border-bottom pb-3 mb-3">

                                        <div class="me-3">
                                            <div class="bg-success bg-opacity-10 rounded-circle d-flex justify-content-center align-items-center"
                                                style="width:50px; height:50px;">

                                                <i class="bi bi-check-circle-fill text-success fs-4"></i>

                                            </div>
                                        </div>

                                        <div class="flex-grow-1">

                                            <div class="d-flex justify-content-between">

                                                <h6 class="fw-bold mb-1">
                                                    Team Registration Approved
                                                </h6>

                                                <small class="text-muted">
                                                    1 hour ago
                                                </small>

                                            </div>

                                            <p class="text-muted mb-1">
                                                Your team registration has been successfully approved
                                                and added to the tournament bracket.
                                            </p>

                                            <a href="#" class="small text-decoration-none">
                                                View Team
                                            </a>

                                        </div>

                                    </div>

                                    <!-- NOTIFICATION ITEM -->
                                    <div class="d-flex align-items-start">

                                        <div class="me-3">
                                            <div class="bg-warning bg-opacity-10 rounded-circle d-flex justify-content-center align-items-center"
                                                style="width:50px; height:50px;">

                                                <i class="bi bi-megaphone-fill text-warning fs-4"></i>

                                            </div>
                                        </div>

                                        <div class="flex-grow-1">

                                            <div class="d-flex justify-content-between">

                                                <h6 class="fw-bold mb-1">
                                                    League Announcement
                                                </h6>

                                                <small class="text-muted">
                                                    Yesterday
                                                </small>

                                            </div>

                                            <p class="text-muted mb-1">
                                                Please arrive 30 minutes before your scheduled game
                                                for attendance and warm-up preparation.
                                            </p>

                                            <a href="#" class="small text-decoration-none">
                                                Read More
                                            </a>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- Bootstrap Icons -->
                      
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