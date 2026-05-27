    <?php
    // session_start();

    $id = $_GET['id'];
    $fetch_league = "SELECT * FROM tbl_league where id = $id";
    $league_res = mysqli_query($conn, $fetch_league);
    $league_row = mysqli_fetch_assoc($league_res);
    $start_date = date("F d", strtotime($league_row['start_date']));
    $end_date = date("F d, Y", strtotime($league_row['end_date']));
    include 'includes/header.php';
    include 'includes/footer.php';
    include 'components/modal/regisModal.php';
    include 'components/modal/loginModal.php';

    ?>

    <a href="leagues.php" class="text-black font-weight-bold">
        < Back</a>
            <div class="card bg-transparent border-0">
                <div class="card m-1" style="background: linear-gradient(to left, rgba(3, 22, 87, 0.95), rgba(2, 66, 194, 0.65));">
                    <div class="card-header bg-transparent border-0">
                        <span class="badge bg-success text-left pr-3 pl-3">
                            <?php echo $league_row['stat']; ?>
                        </span>
                    </div>
                    <div class="card-body border-0 bg-transparent">
                        <h1 class="font-weight-bold text-white text-capitalize">
                            <?php echo $league_row['league_name']; ?>

                        </h1>
                    </div>
                    <div class="card-footer bg-transparent border-0 p-3">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <!-- LEFT SIDE -->
                            <div class="text">
                                <span class="m-5 text-capitalize text-light"><i class="bi bi-calendar-event"></i> <?php echo $start_date . '-' . $end_date; ?>
                                </span>
                                <span class="m-5 text-capitalize text-light"><i class="bi bi-geo-alt"></i> <?php echo $league_row['venue']; ?></span>
                                <span class="m-5 text-capitalize text-light"><i class="bi bi-people-fill"></i> <?php echo $league_row['max_team']; ?></span>
                                <span class="m-5 text-capitalize text-light"><i class="bi bi-cash-stack"></i> 100,000.00</span>
                            </div>
                            <!-- RIGHT SIDE -->
                            <div>
                                <?php
                                if ($loggin != true) {
                                ?>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal" type="button">
                                        Register Team

                                    </button>
                                <?php
                                } else {
                                ?>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#regisTeam" type="button">
                                        Register Team
                                    </button>
                                <?php } ?>


                            </div>

                        </div>

                    </div>
                </div>
                <div class="card m-1 border-0">
                    <div class="card-header bg-transparent border-0"></div>
                    <?php $page = basename($_SERVER['PHP_SELF']); ?>

                    <ul class="nav nav-underline nav-fill border-bottom mb-4">

                        <li class="nav-item">
                            <a class="nav-link fw-semibold <?= ($page == 'team_information.php') ? 'active text-primary' : 'text-dark' ?>"
                                href="team_information.php?id=<?php echo $id; ?>">
                                Overview
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link fw-semibold <?= ($page == 'participant.php') ? 'active text-primary' : 'text-dark' ?>"
                                href="participant.php?id=<?php echo $id; ?>">
                                Participant
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