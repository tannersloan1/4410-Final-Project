<?php
session_start();

include "includes/db.php";
include "includes/activity.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $student = $conn->prepare("SELECT student_id, password_hash FROM STUDENT_USERS WHERE email=?");
    $student->bind_param("s", $email);
    $student->execute();
    $student_result = $student->get_result();

    $teacher = $conn->prepare("SELECT teacher_id, password_hash FROM TEACHER_USERS WHERE email=?");
    $teacher->bind_param("s", $email);
    $teacher->execute();
    $teacher_result = $teacher->get_result();

    $admin = $conn->prepare("SELECT admin_id, password_hash FROM ADMIN_USERS WHERE email=?");
    $admin->bind_param("s", $email);
    $admin->execute();
    $admin_result = $admin->get_result();

    if ($student_result->num_rows > 0) {
        $row = $student_result->fetch_assoc();
        $hash = $row["password_hash"];

        if (password_verify($password, $hash)) {
            // Storing student id and role in session
            $_SESSION["user_id"] = $row["student_id"];
            $_SESSION["role"] = "student";

            logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "login");

            header("Location: redirect.php");
            exit();
        }
    }
    elseif ($teacher_result->num_rows > 0) {
        $row = $teacher_result->fetch_assoc();
        $hash = $row["password_hash"];

        if (password_verify($password, $hash)) {
            // Storing teacher id and role in session
            $_SESSION["user_id"] = $row["teacher_id"];
            $_SESSION["role"] = "teacher";

            logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "login");

            header("Location: redirect.php");
            exit();
        }
    }
    elseif ($admin_result->num_rows > 0) {
        $row = $admin_result->fetch_assoc();
        $hash = $row["password_hash"];

        if (password_verify($password, $hash)) {
            // Storing admin id and role in session
            $_SESSION["user_id"] = $row["admin_id"];
            $_SESSION["role"] = "admin";

            logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "login");

            header("Location: redirect.php");
            exit();
        }
    }
    else {
        $error = "Invalid login";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>LSM Login</title>
        <link rel="stylesheet" href="lms.css?v=5">
    </head>
    <body>
        <?php include "includes/header.php"; ?>
        <!-- login form container -->
        <div>
            <h1>LMS Login Page</h1>
            
            <!-- login form -->
            <form method="POST">
                <div>
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>
                <div>
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                <button type="submit">Sign In</button>
            </form>

            <!-- register link -->
            <p>Don't have an account?</p>
            <a href="register.php">
                <button>Create Account</button>
            </a>

            <!-- displaying error if login fails -->
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        </div>
    </body>
</html>
