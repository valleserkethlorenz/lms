<?php
include '../config/dbconnect.php';
function getIpAddr()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ipAddr = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddr = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ipAddr = $_SERVER['REMOTE_ADDR'];
    }
    return $ipAddr;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['register'])) {
        $imageName = null;
        $uploadDir = "../vendor/images/uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (!empty($_FILES['team_image']['name'])) {

            $imageName = time() . "_" . basename($_FILES["team_image"]["name"]);
            $targetFile = $uploadDir . $imageName;

            move_uploaded_file($_FILES["team_image"]["tmp_name"], $targetFile);
        }

        // ======================
        // 1. INSERT TEAM
        // ======================
        $sql = "INSERT INTO tbl_team (team_name, league_id, status, team_image)
            VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $status = "Pending";

        $stmt->bind_param(
            "siss",
            $_POST['team_name'],
            $_POST['id'],   // league_id from hidden input
            $status,
            $imageName
        );

        if ($stmt->execute()) {

            $team_id = $stmt->insert_id;
            $sql2 = "INSERT INTO tbl_player (team_id, name, age, role, gender, jersey_number)
                 VALUES (?, ?, ?, ?, ?, ?)";

            $stmt2 = $conn->prepare($sql2);

            foreach ($_POST['player_name'] as $key => $name) {

                $age = $_POST['player_age'][$key];
                $role = $_POST['player_role'][$key];
                $gender = $_POST['gender'][$key];
                $jersey = $_POST['jersey_number'][$key];

                $stmt2->bind_param(
                    "isissi",
                    $team_id,
                    $name,
                    $age,
                    $role,
                    $gender,
                    $jersey
                );

                $stmt2->execute();
            }

            echo json_encode([
                "status" => 201,
                "msg" => "Team and players registered successfully"
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
}
