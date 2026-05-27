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
?>

    <body id="page-top" class="body">
        <?php
        include 'includes/bar.php';


        ?>
        <div class="container-fluid">
            <div class="row p-3">
                <div class="card cards">


                    <div class="card-body">
                        <div class="card-header m-2 card-headers">
                            <h4 class="header_title" style="font-weight:bold; color:white; letter-spacing:2.5px;">LEAGUE MANAGEMENT</span>
                        </div>
                        <div class="row p-4">
                            <div class="d-flex flex-row-reverse">
                                <button class="btn btn-primary float-right btn-sm m-3 p-2" style="color:white;border:none; border-radius:10px; font-weight:bold" data-bs-toggle="modal" data-bs-target="#addCat">

                                    Register League</button>
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
                                            <th class="border-0 text-center font-weight-bold" style="font-size: 16px; font-family: head;">Action</th>

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
                                                            <td style="font-size: 18px; text-transform:capitalize;" class="text-center"><a type="button" class="btn btn-primary" href="league_info.php?id='.$league_row["id"].'">Manage</a></td>
                                                           
                                                            
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
                        $(document).on('submit', '#regisLeague', function(e) {
                            e.preventDefault();
                            var formData = new FormData(this);
                            formData.append("regisLeague", true);
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