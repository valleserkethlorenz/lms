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
                                    <!-- LEFT SIDE: GROUP STAGE -->
                                    <div class="col-lg-6">

                                        <div class="card shadow-sm">
                                            <div class="card-header fw-bold">GROUP STAGE</div>
                                            <div class="card-body">

                                                <!-- GROUP A -->
                                                <div class="card mb-3">
                                                    <div class="card-header fw-bold">GROUP A</div>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm text-center align-middle">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>#</th>
                                                                    <th>Team</th>
                                                                    <th>MP</th>
                                                                    <th>W</th>
                                                                    <th>L</th>
                                                                    <th>Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr>
                                                                    <td>1</td>
                                                                    <td>TEAM 1</td>
                                                                    <td>3</td>
                                                                    <td>3</td>
                                                                    <td>0</td>
                                                                    <td><span class="badge bg-success">QUALIFY</span></td>
                                                                </tr>
                                                                <tr>
                                                                    <td>2</td>
                                                                    <td>TEAM 2</td>
                                                                    <td>3</td>
                                                                    <td>2</td>
                                                                    <td>1</td>
                                                                    <td><span class="badge bg-success">QUALIFY</span></td>
                                                                </tr>
                                                                <tr>
                                                                    <td>3</td>
                                                                    <td>TEAM 3</td>
                                                                    <td>3</td>
                                                                    <td>1</td>
                                                                    <td>2</td>
                                                                    <td><span class="badge bg-danger">ELIMINATED</span></td>
                                                                </tr>
                                                                <tr>
                                                                    <td>4</td>
                                                                    <td>TEAM 4</td>
                                                                    <td>3</td>
                                                                    <td>0</td>
                                                                    <td>3</td>
                                                                    <td><span class="badge bg-danger">ELIMINATED</span></td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <!-- GROUP B -->
                                                <div class="card mb-3">
                                                    <div class="card-header fw-bold">GROUP B</div>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm text-center align-middle">
                                                            <tbody>
                                                                <tr>
                                                                    <td>1</td>
                                                                    <td>TEAM 5</td>
                                                                    <td>3</td>
                                                                    <td>2</td>
                                                                    <td>1</td>
                                                                    <td><span class="badge bg-success">QUALIFY</span></td>
                                                                </tr>
                                                                <tr>
                                                                    <td>2</td>
                                                                    <td>TEAM 6</td>
                                                                    <td>3</td>
                                                                    <td>2</td>
                                                                    <td>1</td>
                                                                    <td><span class="badge bg-success">QUALIFY</span></td>
                                                                </tr>
                                                                <tr>
                                                                    <td>3</td>
                                                                    <td>TEAM 7</td>
                                                                    <td>3</td>
                                                                    <td>2</td>
                                                                    <td>1</td>
                                                                    <td><span class="badge bg-danger">ELIMINATED</span></td>
                                                                </tr>
                                                                <tr>
                                                                    <td>4</td>
                                                                    <td>TEAM 8</td>
                                                                    <td>3</td>
                                                                    <td>1</td>
                                                                    <td>2</td>
                                                                    <td><span class="badge bg-danger">ELIMINATED</span></td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <!-- GROUP C / D COLLAPSE -->
                                                <div class="accordion" id="groupAccordion">

                                                    <div class="accordion-item">
                                                        <h2 class="accordion-header">
                                                            <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#groupC">
                                                                GROUP C
                                                            </button>
                                                        </h2>
                                                        <div id="groupC" class="accordion-collapse collapse">
                                                            <div class="accordion-body">
                                                                No data yet
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="accordion-item mt-2">
                                                        <h2 class="accordion-header">
                                                            <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#groupD">
                                                                GROUP D
                                                            </button>
                                                        </h2>
                                                        <div id="groupD" class="accordion-collapse collapse">
                                                            <div class="accordion-body">
                                                                No data yet
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>

                                            </div>
                                        </div>

                                    </div>

                                    <!-- RIGHT SIDE: KNOCKOUTS -->
                                    <div class="col-lg-6">

                                        <!-- QUARTER FINALS -->
                                        <div class="card mb-3 shadow-sm">
                                            <div class="card-header fw-bold">QUARTER FINALS</div>
                                            <div class="card-body">

                                                <div class="d-flex justify-content-between align-items-center border p-2 mb-2 rounded">
                                                    <div>TEAM 1</div>
                                                    <span class="badge bg-success">2</span>
                                                    <div>TEAM 5</div>
                                                    <span class="badge bg-secondary">0</span>
                                                    <a href="#" class="btn btn-sm btn-outline-primary">View</a>
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center border p-2 mb-2 rounded">
                                                    <div>TEAM 2</div>
                                                    <span class="badge bg-success">2</span>
                                                    <div>TEAM 6</div>
                                                    <span class="badge bg-secondary">1</span>
                                                    <a href="#" class="btn btn-sm btn-outline-primary">View</a>
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center border p-2 mb-2 rounded">
                                                    <div>TEAM 8</div>
                                                    <span class="badge bg-warning">1</span>
                                                    <div>TEAM 12</div>
                                                    <span class="badge bg-warning">1</span>
                                                    <span class="badge bg-info">ONGOING</span>
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center border p-2 rounded">
                                                    <div>TEAM 10</div>
                                                    <span class="badge bg-info">--</span>
                                                    <div>TEAM 7</div>
                                                    <span class="badge bg-info">--</span>
                                                    <span class="badge bg-info">UPCOMING</span>
                                                </div>

                                            </div>
                                        </div>

                                        <!-- SEMI FINALS -->
                                        <div class="card mb-3 shadow-sm">
                                            <div class="card-header fw-bold">SEMI FINALS</div>
                                            <div class="card-body">

                                                <div class="d-flex justify-content-between border p-2 mb-2 rounded">
                                                    <div>TEAM 1</div>
                                                    <span>---</span>
                                                    <div>TEAM 2</div>
                                                    <span class="badge bg-primary">UPCOMING</span>
                                                </div>

                                            </div>
                                        </div>

                                        <!-- GRAND FINALS -->
                                        <div class="card shadow-sm">
                                            <div class="card-header fw-bold">GRAND FINALS</div>
                                            <div class="card-body text-center text-muted">
                                                To be announced
                                            </div>
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