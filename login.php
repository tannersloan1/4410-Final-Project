<?php
session_start();

include "includes/db.php";
include "includes/activity.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["sign-in"])) {
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

        // If no redirect, show this
        $_SESSION["login-error"] = "Invalid login";

        logActivity($conn, 0, "unknown", $email . " failed login");

        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    }

    if (isset($_POST["change-password"])) {
        $email = $_POST["ch-email"];
        $password = $_POST["ch-password"];
        $new_password = $_POST["new-password"];
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

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
                $sql = $conn->prepare("UPDATE STUDENT_USERS SET password_hash = ? WHERE email = ?");
                $sql->bind_param("ss", $new_hash, $email);

                if ($sql->execute()) {
                    logActivity($conn, $row["student_id"], "student", "changed password", NULL, "student_users");

                    $_SESSION["success"] = "Password for " . $email . " successfully changed!";

                    header("Location: " . $_SERVER["PHP_SELF"]);
                    exit();
                }
                else {
                    $_SESSION["change-error"] = "Password could not be updated";
                    header("Location: " . $_SERVER["PHP_SELF"]);
                    exit();
                }
            }
            else {
                $_SESSION["change-error"] = "Invalid email and password combo";
                header("Location: " . $_SERVER["PHP_SELF"]);
                exit();
            }
        }
        elseif ($teacher_result->num_rows > 0) {
            $row = $teacher_result->fetch_assoc();
            $hash = $row["password_hash"];

            if (password_verify($password, $hash)) {
                $sql = $conn->prepare("UPDATE TEACHER_USERS SET password_hash = ? WHERE email = ?");
                $sql->bind_param("ss", $new_hash, $email);

                if ($sql->execute()) {
                    logActivity($conn, $row["teacher_id"], "teacher", "changed password", NULL, "teacher_users");

                    $_SESSION["success"] = "Password for " . $email . " successfully changed!";

                    header("Location: " . $_SERVER["PHP_SELF"]);
                    exit();
                }
                else {
                    $_SESSION["change-error"] = "Password could not be updated";
                    header("Location: " . $_SERVER["PHP_SELF"]);
                    exit();
                }
            }
            else {
                $_SESSION["change-error"] = "Invalid email and password combo";
                header("Location: " . $_SERVER["PHP_SELF"]);
                exit();
            }
        }
        elseif ($admin_result->num_rows > 0) {
            $row = $admin_result->fetch_assoc();
            $hash = $row["password_hash"];

            if (password_verify($password, $hash)) {
                $sql = $conn->prepare("UPDATE ADMIN_USERS SET password_hash = ? WHERE email = ?");
                $sql->bind_param("ss", $new_hash, $email);

                if ($sql->execute()) {
                    logActivity($conn, $row["admin_id"], "admin", "changed password", NULL, "admin_users");

                    $_SESSION["success"] = "Password for " . $email . " successfully changed!";

                    header("Location: " . $_SERVER["PHP_SELF"]);
                    exit();
                }
                else {
                    $_SESSION["change-error"] = "Password could not be updated";
                    header("Location: " . $_SERVER["PHP_SELF"]);
                    exit();
                }
            }
            else {
                $_SESSION["change-error"] = "Invalid email and password combo";
                header("Location: " . $_SERVER["PHP_SELF"]);
                exit();
            }
        }
        else {
            $_SESSION["change-error"] = "Invalid email and password combo";
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>LMS Login</title>
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
                <button type="submit" name="sign-in">Sign In</button>
            </form>

            <!-- register link -->
            <p>Don't have an account?</p>
            <a href="register.php">
                <button>Create Account</button>
            </a>

            <?php if (!empty($_SESSION["login-error"])): ?>
                <div><?php echo htmlspecialchars($_SESSION["login-error"]) ?></div>
                <?php unset($_SESSION["login-error"]); 
            endif; ?>
        </div>

        <div>
            <p>Change your password</p>
            <form method="POST">
                <div>
                    <label for="ch-email">Email Address</label>
                    <input type="email" name="ch-email" required placeholder="Enter your email">
                </div>
                <div>
                    <label for="ch-password">Password</label>
                    <input type="password" name="ch-password" required placeholder="Enter your current password">
                </div>
                <div>
                    <label for="new-password">New Password</label>
                    <input type="password" name="new-password" required placeholder="Enter your new password">
                </div>
                <button type="submit" name="change-password">Change Password</button>
            </form>

            <?php if (!empty($_SESSION["success"])): ?>
                <div><?php echo htmlspecialchars($_SESSION["success"]) ?></div>
                <?php unset($_SESSION["success"]); 
            endif; ?>

            <?php if (!empty($_SESSION["change-error"])): ?>
                <div><?php echo htmlspecialchars($_SESSION["change-error"]) ?></div>
                <?php unset($_SESSION["change-error"]); 
            endif; ?>
        </div>
    </body>
</html>
