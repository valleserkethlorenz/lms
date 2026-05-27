<?php
session_start();
include '../../../config/dbconnect.php';
date_default_timezone_set('Asia/Manila');

$created = date("Y-m-d H:i:s");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = '';


    if (isset($_POST['regisLeague'])) {

        // =========================
        // IMAGE UPLOAD
        // =========================
        $imageName = null;

        if (!empty($_FILES['league_image']['name'])) {

            $uploadDir = "../../../vendor/images/uploads/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $imageName = time() . "_" . basename($_FILES["league_image"]["name"]);
            $targetFile = $uploadDir . $imageName;

            move_uploaded_file($_FILES["league_image"]["tmp_name"], $targetFile);
        }

        // =========================
        // INSERT QUERY
        // =========================
        $sql = "INSERT INTO tbl_league (
                league_name, sport, division, category, format,
                start_date, end_date, regis_start, regis_end,
                venue, max_team, max_player, regis_fee,
                descrip, add_info, league_image, `stat`
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?
            )";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $reg_fee = !empty($_POST['reg_fee']) ? $_POST['reg_fee'] : null;
        $status = 'Upcoming';
        $stmt->bind_param(
            "ssssssssssiidsss",
            $_POST['league_name'],
            $_POST['sport'],
            $_POST['division'],
            $_POST['category'],
            $_POST['format'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['regis_start'],
            $_POST['regis_end'],
            $_POST['venue'],
            $_POST['max_team'],
            $_POST['max_player'],
            $reg_fee,
            $_POST['descrip'],
            $_POST['add_info'],
            $imageName,
            $status
        );

        if ($stmt->execute()) {
            echo json_encode([
                "status" => 201,
                "msg" => "League created successfully"
            ]);
        } else {
            echo json_encode([
                "status" => 500,
                "msg" => $stmt->error
            ]);
        }

        $stmt->close();
        $conn->close();
    }
    if (isset($_POST['schedule'])) {

        $sql = "INSERT INTO tbl_schedule 
            (league_id, team_a, team_b, date, time, status)  
            VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $status = 'Upcoming';

        $stmt->bind_param(
            "iiisss",
            $_POST['league_id'],
            $_POST['team_a'],
            $_POST['team_b'],
            $_POST['date'],
            $_POST['time'],
            $status
        );

        if ($stmt->execute()) {
            echo json_encode([
                "status" => 201,
                "msg" => "Schedule created successfully"
            ]);
        } else {
            echo json_encode([
                "status" => 500,
                "msg" => $stmt->error
            ]);
        }

        $stmt->close();
        $conn->close();
    }
    if (isset($_POST['approved'])) {
        $team_id = $_POST["team_id"];
        $sql = "UPDATE `tbl_team` SET `status`='Approved' WHERE `id` ='$team_id'";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            $res = [
                'status' => 201,
                'msg' => "Team approved successfully ",

            ];
            echo json_encode($res);
            exit;
        }
    }
    if (isset($_POST['reject'])) {
        $team_id = $_POST["team_id"];
        $sql = "UPDATE `tbl_team` SET `status`='Rejected' WHERE `id` ='$team_id'";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            $res = [
                'status' => 201,
                'msg' => "Team reject successfully ",

            ];
            echo json_encode($res);
            exit;
        }
    }
}
