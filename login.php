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
                $_SESSION["user_id"] = $row["student_id"];
                $_SESSION["role"] = "student";
                logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "login");
                header("Location: redirect.php");
                exit();
            }
        } elseif ($teacher_result->num_rows > 0) {
            $row = $teacher_result->fetch_assoc();
            $hash = $row["password_hash"];
            if (password_verify($password, $hash)) {
                $_SESSION["user_id"] = $row["teacher_id"];
                $_SESSION["role"] = "teacher";
                logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "login");
                header("Location: redirect.php");
                exit();
            }
        } elseif ($admin_result->num_rows > 0) {
            $row = $admin_result->fetch_assoc();
            $hash = $row["password_hash"];
            if (password_verify($password, $hash)) {
                $_SESSION["user_id"] = $row["admin_id"];
                $_SESSION["role"] = "admin";
                logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "login");
                header("Location: redirect.php");
                exit();
            }
        }

        $_SESSION["login-error"] = "Invalid email or password.";
        logActivity($conn, 0, "unknown", $email . " failed login");
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    }

    if (isset($_POST["change-password"])) {
        $email        = $_POST["ch-email"];
        $password     = $_POST["ch-password"];
        $new_password = $_POST["new-password"];
        $new_hash     = password_hash($new_password, PASSWORD_DEFAULT);

        $student = $conn->prepare("SELECT student_id, password_hash FROM STUDENT_USERS WHERE email=?");
        $student->bind_param("s", $email); $student->execute();
        $student_result = $student->get_result();

        $teacher = $conn->prepare("SELECT teacher_id, password_hash FROM TEACHER_USERS WHERE email=?");
        $teacher->bind_param("s", $email); $teacher->execute();
        $teacher_result = $teacher->get_result();

        $admin = $conn->prepare("SELECT admin_id, password_hash FROM ADMIN_USERS WHERE email=?");
        $admin->bind_param("s", $email); $admin->execute();
        $admin_result = $admin->get_result();

        $matched = null;
        $table   = null;
        $id_col  = null;

        if ($student_result->num_rows > 0) {
            $matched = $student_result->fetch_assoc();
            $table   = "STUDENT_USERS"; $id_col = "student_id";
            $log_role = "student";
        } elseif ($teacher_result->num_rows > 0) {
            $matched = $teacher_result->fetch_assoc();
            $table   = "TEACHER_USERS"; $id_col = "teacher_id";
            $log_role = "teacher";
        } elseif ($admin_result->num_rows > 0) {
            $matched = $admin_result->fetch_assoc();
            $table   = "ADMIN_USERS"; $id_col = "admin_id";
            $log_role = "admin";
        }

        if ($matched && password_verify($password, $matched["password_hash"])) {
            $sql = $conn->prepare("UPDATE $table SET password_hash = ? WHERE email = ?");
            $sql->bind_param("ss", $new_hash, $email);
            if ($sql->execute()) {
                logActivity($conn, $matched[$id_col], $log_role, "changed password", NULL, strtolower($table));
                $_SESSION["success"] = "Password updated successfully!";
            } else {
                $_SESSION["change-error"] = "Something went wrong — password not updated.";
            }
        } else {
            $_SESSION["change-error"] = "Incorrect email or current password.";
        }

        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in — LMS</title>
    <link rel="stylesheet" href="lms.css?v=10">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Nunito", "Segoe UI", sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* page layout */
        .page-body {
            flex: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 20px 80px;
            gap: 40px;
            flex-wrap: wrap;
        }

        /* shared card */
        .auth-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 36px 32px;
            width: 100%;
            max-width: 400px;
        }
        .auth-card h2 {
            font-size: 1.3rem;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 6px;
        }
        .auth-card .sub {
            font-size: 0.88rem;
            color: #64748b;
            margin-bottom: 28px;
        }

        /* form elements */
        .field { margin-bottom: 18px; }
        .field label {
            display: block;
            font-size: 0.88rem;
            font-weight: 700;
            color: #cbd5e1;
            margin-bottom: 7px;
        }
        .field input {
            width: 100%;
            padding: 11px 14px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 9px;
            color: #f1f5f9;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .field input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .field input::placeholder { color: #475569; }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s;
            margin-top: 4px;
        }
        .btn-submit:hover { background: #2563eb; }

        /* feedback */
        .alert {
            padding: 11px 14px;
            border-radius: 9px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .alert-error   { background: #450a0a; color: #fca5a5; border: 1px solid #7f1d1d; }
        .alert-success { background: #052e16; color: #86efac; border: 1px solid #14532d; }

        /* divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 22px 0;
            color: #334155;
            font-size: 0.82rem;
        }
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #1e293b;
        }

        .link-row {
            text-align: center;
            font-size: 0.88rem;
            color: #64748b;
            margin-top: 18px;
        }
        .link-row a { color: #3b82f6; text-decoration: none; font-weight: 700; }
        .link-row a:hover { text-decoration: underline; }

        .btn-forgot {
            width: 100%;
            padding: 12px;
            background: transparent;
            color: #64748b;
            border: 1px solid #334155;
            border-radius: 9px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
            margin-top: 10px;
        }
        .btn-forgot:hover { border-color: #475569; color: #94a3b8; }

        .cp-drawer {
            display: none;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #334155;
        }
        .cp-drawer.open { display: block; }
        .cp-drawer h3 {
            font-size: 1rem;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 4px;
        }
        .cp-drawer .cp-sub {
            font-size: 0.84rem;
            color: #64748b;
            margin-bottom: 20px;
        }

        footer {
            border-top: 1px solid #1e293b;
            text-align: center;
            padding: 24px;
            color: #334155;
            font-size: 0.82rem;
            font-family: "Nunito", sans-serif;
        }

        @media (max-width: 500px) {
            .auth-card { padding: 28px 20px; }
            .card-divider { display: none; }
        }
    </style>
</head>
<body>

<?php include "includes/header.php"; ?>

<div class="page-body">

    <!-- Single sign in card -->
    <div class="auth-card">
        <h2>Welcome back 👋</h2>
        <p class="sub">Log in to your account to continue.</p>

        <?php if (!empty($_SESSION["login-error"])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION["login-error"]) ?></div>
            <?php unset($_SESSION["login-error"]); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION["success"])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION["success"]) ?></div>
            <?php unset($_SESSION["success"]); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION["change-error"])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION["change-error"]) ?></div>
            <?php unset($_SESSION["change-error"]); ?>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" required placeholder="you@example.com">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Your password">
            </div>
            <button type="submit" name="sign-in" class="btn-submit">Sign in</button>
        </form>

        <button type="button" class="btn-forgot" onclick="toggleForgot()">
            Forgot password?
        </button>

        <!-- Change password drawer — hidden until button clicked -->
        <div class="cp-drawer" id="cp-drawer">
            <h3>Change your password</h3>
            <p class="cp-sub">Enter your current password and pick a new one.</p>
            <form method="POST">
                <div class="field">
                    <label>Email address</label>
                    <input type="email" name="ch-email" required placeholder="you@example.com">
                </div>
                <div class="field">
                    <label>Current password</label>
                    <input type="password" name="ch-password" required placeholder="Your current password">
                </div>
                <div class="field">
                    <label>New password</label>
                    <input type="password" name="new-password" required placeholder="Choose a new password">
                </div>
                <button type="submit" name="change-password" class="btn-submit" style="background:#475569;">
                    Update password
                </button>
            </form>
        </div>

        <div class="link-row">
            Don't have an account? <a href="/lms/register.php">Sign up</a>
        </div>
    </div>

</div>

<script>
function toggleForgot() {
    const drawer = document.getElementById("cp-drawer");
    const btn    = document.querySelector(".btn-forgot");
    drawer.classList.toggle("open");
    btn.textContent = drawer.classList.contains("open")
        ? "Never mind, go back"
        : "Forgot password?";
}

<?php if (!empty($_SESSION["change-error"]) || !empty($_SESSION["success"])): ?>
// Auto-open the drawer if there's a change-password response
document.getElementById("cp-drawer").classList.add("open");
document.querySelector(".btn-forgot").textContent = "Never mind, go back";
<?php endif; ?>
</script>

<footer>&copy; 2026 LMS System</footer>

</body>
</html>
