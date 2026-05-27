<?php

session_start();
include 'alert.php';

echo "Logging you out. Please wait...";
unset($_SESSION["loggin"]);
unset($_SESSION["userId"]);
unset($_SESSION["userFull"]);
unset($_SESSION["role"]);

// session_unset();
// session_destroy();

success("testpage.php","Account has been logged out!");

?>
