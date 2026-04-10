<?php
session_start();

include "../includes/db.php";
include "../includes/activity.php";

// Makes sure you must have logged in with appropiate role
if ($_SESSION["role"] != "teacher") {
    die("Restricted access"); 
}

$sql = $conn->prepare("SELECT teacher_id, email, full_name FROM TEACHER_INFO WHERE teacher_id = ?");
$sql->bind_param("i", $_SESSION["user_id"]);
$sql->execute();
$info = $sql->get_result()->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");

    if ($email != $info["email"] && $email != "") {
        $sql = $conn->prepare("UPDATE TEACHER_USERS SET email = ? WHERE teacher_id = ?");
        $sql->bind_param("si", $email, $_SESSION["user_id"]);
        $sql->execute();
        $sql = $conn->prepare("UPDATE TEACHER_INFO SET email = ? WHERE teacher_id = ?");
        $sql->bind_param("si", $email, $_SESSION["user_id"]);
        $sql->execute();
        logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "update",
        $_SESSION["role"] . " " . $_SESSION["user_id"] . " updated their email.", "teacher_info/teacher_users");

        header("Location " . $_SERVER["PHP_SELF"]);
        exit();
    }
    if ($full_name != $info["full_name"] && $full_name != "") {
        $sql = $conn->prepare("UPDATE TEACHER_INFO SET full_name = ? WHERE teacher_id = ?");
        $sql->bind_param("si", $full_name, $_SESSION["user_id"]);
        $sql->execute();
        logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "update",
        $_SESSION["role"] . " " . $_SESSION["user_id"] . " updated their full name.", "teacher_info");

        header("Location " . $_SERVER["PHP_SELF"]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Settings</title>
        <link rel="stylesheet" href="../lms.css?v=5">
    </head>
    <body>
        <?php include "../includes/header.php"; ?>

        <?php if ($info["full_name"] != NULL): ?>
            <h1>Hello <?php echo htmlspecialchars($info["full_name"]); ?></h1>
        <?php else: ?>
            <h1>No name on file, please update!</h1>
        <?php endif; ?>

        <div class="current">
            <p>Email: <?php echo htmlspecialchars($info["email"]); ?></p>
            <p>Name: <?php echo htmlspecialchars($info["full_name"]); ?></p>
        </div>

        <div class="data">
            <form method="POST">
                <label for="email">Email</label>
                <input name="email" type="email">

                <label for="full_name">Full Name</label>
                <input name="full_name" type="text" placeholder="Formatted like: First Last">

                <button type="submit">Submit</button>
            </form>
        </div>
    </body>
</html>