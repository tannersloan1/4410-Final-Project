<?php
session_start();

include "../includes/db.php";
include "../includes/activity.php";

// Makes sure you must have logged in with appropiate role
if ($_SESSION["role"] != "admin") {
    die("Restricted access"); 
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Analytics</title>
        <link rel="stylesheet" href="../lms.css?v=5">
    </head>
    <body>
        <?php include "../includes/header.php"; ?>
    </body>
</html>