<?php
session_start();

include "includes/db.php";
include "includes/activity.php";

logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "logout");

session_destroy();

header("Location: /lms/index.php");

exit();
?>
