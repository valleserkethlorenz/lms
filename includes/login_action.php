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
function isMobileDevice()
{
    return preg_match('/iPhone|Android.+Mobile|Windows Phone/i', $_SERVER['HTTP_USER_AGENT']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['signUp'])) {
        $fname = $_POST["fname"];
        $lname = $_POST["lname"];
        $email = $_POST["email"];
        $phone = $_POST["phone"];

        $pass = $_POST["pass1"];
        $pass2 = $_POST["pass2"];
        $suffix = isset($_POST['suffix']) && !empty($_POST['suffix']) ? trim($_POST['suffix']) : '';
        $mname = isset($_POST['mname']) && !empty($_POST['mname']) ? trim($_POST['mname']) : '';

        $existSql = "SELECT * FROM `tbl_user` WHERE `fname` = '$fname' and `lname` = '$lname'  ";
        $result = mysqli_query($conn, $existSql);
        $numExistRows = mysqli_num_rows($result);
        if ($numExistRows > 0) {
            $res = [
                'status' => 401,
                'msg' => "Users already have an account",
            ];
            echo json_encode($res);
            exit;
        } else {
            $existSql = "SELECT * FROM `tbl_user` WHERE `phone` = '$phone'";
            $result = mysqli_query($conn, $existSql);
            $numExistRows = mysqli_num_rows($result);
            if ($numExistRows > 0) {
                $res = [
                    'status' => 401,
                    'msg' => "Phone already registered",
                ];
                echo json_encode($res);
                exit;
            } else {
                $existSql = "SELECT * FROM `tbl_user` WHERE `email` = '$email' ";
                $result = mysqli_query($conn, $existSql);
                $numExistRows = mysqli_num_rows($result);
                if ($numExistRows > 0) {
                    $res = [
                        'status' => 401,
                        'msg' => "Email already exist",
                    ];
                    echo json_encode($res);
                    exit;
                } else {
                    if (($pass == $pass2)) {
                        $hash = password_hash($pass, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO `tbl_user` (`fname`, `lname`,`mname`, `suffix`, `phone`,`email`,`pass`, `role`, `stat`) VALUES ('$fname', '$lname','$mname', '$suffix', '$phone', '$email', '$hash', '2', '1')";
                        $result = mysqli_query($conn, $sql);
                        if ($result) {
                            $res = [
                                'status' => 201,
                                'msg' => "Account created successfully",
                            ];
                            echo json_encode($res);
                            exit;
                        } else {
                            $res = [
                                'status' => 401,
                                'msg' => "Something went wrong",
                            ];
                            echo json_encode($res);
                            exit;
                        }
                    } else {
                        $res = [
                            'status' => 401,
                            'msg' => "Password doesn't match",
                        ];
                        echo json_encode($res);
                        exit;
                    }
                }
            }
        }
    }
    if (isset($_POST['loginBtn'])) {

        $time = time() - 30;
        $ip_address = getIpAddr();

        $check_login_row = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) AS total_count 
         FROM tbl_attempts 
         WHERE try_time > $time AND ip_address = '$ip_address'"
        ));

        $total_count = $check_login_row['total_count'];

        if ($total_count >= 3) {
            echo json_encode([
                'status' => 401,
                'msg' => 'Please login after 30 seconds'
            ]);
            exit;
        }

        $username = mysqli_real_escape_string($conn, $_POST['email']);
        $password = mysqli_real_escape_string($conn, $_POST['password']);

        $sql = "SELECT * FROM tbl_user WHERE email='$username' AND stat='1'";
        $res = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($res);

        if ($row && password_verify($password, $row['pass'])) {

            $userType = $row['role'];

            mysqli_query($conn, "DELETE FROM tbl_attempts WHERE ip_address='$ip_address'");

            session_start();
            $_SESSION['loggin'] = true;
            $_SESSION['loggin_stat'] = "true";
            $_SESSION['lms_id'] = $row['id'];
            $_SESSION['lms_username'] = $row['fname'] . ' ' . $row['lname'];
            $_SESSION['lms_role'] = $userType;

            // 🎯 Role-based redirect
            if ($userType == 1) {
                $redirect = "app/organizer/dashboard.php";
            } else if ($userType == 2) {
                $redirect = "app/tr/dashboard.php";
            } else {
                $redirect = "login.php";
            }

            echo json_encode([
                'status' => 200,
                'msg' => 'Login successful',
                'redirect' => $redirect
            ]);
            exit;
        } else {
        }
    }
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
        $sql = "INSERT INTO tbl_team (team_name, league_id, status, team_image, team_repre, email, phone, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $status = "Pending";

        $stmt->bind_param(
            "sissssss",
            $_POST['team_name'],
            $_POST['id'],   // league_id from hidden input
            $status,
            $imageName,
            $_POST['team_repre'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['userId']
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
    if (isset($_POST['loginModal'])) {

        $time = time() - 30;
        $ip_address = getIpAddr();

        $check_login_row = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) AS total_count 
         FROM tbl_attempts 
         WHERE try_time > $time AND ip_address = '$ip_address'"
        ));

        $total_count = $check_login_row['total_count'];

        if ($total_count >= 3) {
            echo json_encode([
                'status' => 401,
                'msg' => 'Please login after 30 seconds'
            ]);
            exit;
        }

        $username = mysqli_real_escape_string($conn, $_POST['email']);
        $password = mysqli_real_escape_string($conn, $_POST['password']);

        $sql = "SELECT * FROM tbl_user WHERE email='$username' AND stat='1'";
        $res = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($res);

        if ($row && password_verify($password, $row['pass'])) {

            $userType = $row['role'];

            mysqli_query($conn, "DELETE FROM tbl_attempts WHERE ip_address='$ip_address'");

            session_start();
            $_SESSION['loggin'] = true;
            $_SESSION['loggin_stat'] = "true";
            $_SESSION['lms_id'] = $row['id'];
            $_SESSION['lms_username'] = $row['fname'] . ' ' . $row['lname'];
            $_SESSION['lms_role'] = $userType;


            echo json_encode([
                'status' => 200,
                'msg' => 'Login successful'

            ]);
            exit;
        } else {
        }
    }
}
