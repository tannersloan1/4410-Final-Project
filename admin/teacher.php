<?php
session_start();

include "../includes/db.php";
include "../includes/activity.php";

// Makes sure you must have logged in with appropiate role
if ($_SESSION["role"] != "admin") {
    die("Restricted access"); 
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["teacher-register"])) {
        $email = $_POST["register-email"];
        $password = $_POST["password"];
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $info = $conn->prepare("INSERT INTO TEACHER_INFO (email) VALUES (?)");
        $info->bind_param("s", $email);
        if (!$info->execute()) {
            // Add error message here
        }

        $teacher_id = $conn->insert_id;

        $user = $conn->prepare("INSERT INTO TEACHER_USERS (teacher_id, email, password_hash) VALUES (?,?,?)");
        $user->bind_param("iss", $teacher_id, $email, $password_hash);
        if ($user->execute()) {
            logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "register", "admin registered teacher " . $teacher_id, "teacher_users/teacher_info");

            header("Location: " . $_SERVER["PHP_SELF"]);
            exit();
        }
        else {
            // Add error message here
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Teacher Admin Control</title>
        <link rel="stylesheet" href="../lms.css?v=5">
    </head>
    <body>
        <?php include "../includes/header.php"; ?>

        <div class="teacher-register">
            <form method="POST">
                <label for="register-email">Email</label>
                <input name="register-email" type="email" required>

                <label for="password">Temp Password</label>
                <input type="password" name="password" required>

                <button type="submit" name="teacher-register">Submit</button>
            </form>
        </div>

        <div class="teacher-lookup">

        </div>
    </body>
</html>