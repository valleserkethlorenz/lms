<?php

session_name('LMS_SESSION');
session_start();
include 'alert.php';

echo "Logging you out. Please wait...";
unset($_SESSION["loggin"]);
unset($_SESSION["loggin_stat"]);
unset($_SESSION["lms_id"]);
unset($_SESSION["lms_username"]);
unset($_SESSION["lms_role"]);
unset($_SESSION["public_logout_token"]);

// session_unset();
// session_destroy();

success("testpage.php","Account has been logged out!");

?>
