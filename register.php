<?php
// Only students can register and then teachers are made by admins/admins are made by other admins
session_start();

include "includes/db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $sql = $conn->prepare("INSERT INTO STUDENT_INFO (email) VALUES (?)");
    $sql->bind_param("s", $email);
    if (!$sql->execute()) {
        // Add error message here
    }

    $student_id = $conn->insert_id;

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $user = $conn->prepare("INSERT INTO STUDENT_USERS (student_id, email, password_hash) VALUES (?,?,?)");
    $user->bind_param("iss", $student_id, $email, $hash);
    if ($user->execute()) {
        header("Location: login.php");
        exit();
    }
    else {
        // Add error message here
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Student Registration</title>
        <link rel="stylesheet" href="lms.css?v=5">
    </head>
    <body>
        <?php include "includes/header.php"; ?>

        <form method="POST">
            <label for="email">Email</label>
            <input type="email" name="email" required>

            <label for="password">Password</label>
            <input type="password" name="password" required>

            <button type="submit">Submit</button>
        </form>

        <p>Already have an account?</p>
        <a href="login.php">
            <button>Sign In</button>
        </a>

        <!-- Display error message here -->
    </body>
</html>