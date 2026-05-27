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
    <div class="row g-3">

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
</div>