<?php
// Only students can self-register; teachers are created by admins
session_start();

include "includes/db.php";
include "includes/activity.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email     = trim($_POST["email"]);
    $password  = $_POST["password"];
    $confirm   = $_POST["confirm_password"];

    // Basic validation
    if ($full_name === "") {
        $_SESSION["reg-error"] = "Please enter your full name.";
        header("Location: " . $_SERVER["PHP_SELF"]); exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["reg-error"] = "Please enter a valid email address.";
        header("Location: " . $_SERVER["PHP_SELF"]); exit();
    }
    if (strlen($password) < 6) {
        $_SESSION["reg-error"] = "Password must be at least 6 characters.";
        header("Location: " . $_SERVER["PHP_SELF"]); exit();
    }
    if ($password !== $confirm) {
        $_SESSION["reg-error"] = "Passwords don't match.";
        header("Location: " . $_SERVER["PHP_SELF"]); exit();
    }

    // Check if email already exists
    $check = $conn->prepare("SELECT student_id FROM STUDENT_USERS WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION["reg-error"] = "An account with that email already exists.";
        header("Location: " . $_SERVER["PHP_SELF"]); exit();
    }

    // Insert into STUDENT_INFO (with full_name if column exists, else just email)
    $sql = $conn->prepare("INSERT INTO STUDENT_INFO (full_name, email) VALUES (?, ?)");
    if ($sql) {
        $sql->bind_param("ss", $full_name, $email);
    } else {
        // fallback if full_name column doesn't exist yet
        $sql = $conn->prepare("INSERT INTO STUDENT_INFO (email) VALUES (?)");
        $sql->bind_param("s", $email);
    }

    if (!$sql->execute()) {
        $_SESSION["reg-error"] = "Could not create account. Please try again.";
        header("Location: " . $_SERVER["PHP_SELF"]); exit();
    }

    $student_id = $conn->insert_id;
    $hash       = password_hash($password, PASSWORD_DEFAULT);

    $user = $conn->prepare("INSERT INTO STUDENT_USERS (student_id, email, password_hash) VALUES (?,?,?)");
    $user->bind_param("iss", $student_id, $email, $hash);

    if ($user->execute()) {
        logActivity($conn, $student_id, "student", "register", "student registered", "student_users/student_info");
        $_SESSION["reg-success"] = "Account created! You can log in now.";
        header("Location: login.php");
        exit();
    } else {
        $_SESSION["reg-error"] = "Something went wrong. Please try again.";
        header("Location: " . $_SERVER["PHP_SELF"]); exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account — LMS</title>
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
            gap: 48px;
            flex-wrap: wrap;
        }

        /* form card */
        .auth-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 36px 32px;
            width: 100%;
            max-width: 420px;
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
        .field input:focus { outline: none; border-color: #3b82f6; }
        .field input::placeholder { color: #475569; }
        .field-hint {
            font-size: 0.78rem;
            color: #475569;
            margin-top: 5px;
        }

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

        .alert {
            padding: 11px 14px;
            border-radius: 9px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .alert-error   { background: #450a0a; color: #fca5a5; border: 1px solid #7f1d1d; }
        .alert-success { background: #052e16; color: #86efac; border: 1px solid #14532d; }

        .link-row {
            text-align: center;
            font-size: 0.88rem;
            color: #64748b;
            margin-top: 18px;
        }
        .link-row a { color: #3b82f6; text-decoration: none; font-weight: 700; }
        .link-row a:hover { text-decoration: underline; }

        /* info panel */
        .info-panel {
            max-width: 300px;
            padding-top: 12px;
        }
        .info-panel h3 {
            font-size: 1rem;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 20px;
        }
        .info-item {
            display: flex;
            gap: 14px;
            margin-bottom: 22px;
            align-items: flex-start;
        }
        .info-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #3b82f6;
            margin-top: 6px;
            flex-shrink: 0;
        }
        .info-item h4 {
            font-size: 0.9rem;
            font-weight: 700;
            color: #e2e8f0;
            margin-bottom: 3px;
        }
        .info-item p {
            font-size: 0.83rem;
            color: #64748b;
            line-height: 1.6;
        }

        .teacher-note {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 16px 18px;
            margin-top: 28px;
            font-size: 0.83rem;
            color: #64748b;
            line-height: 1.65;
        }
        .teacher-note strong { color: #94a3b8; }

        footer {
            border-top: 1px solid #1e293b;
            text-align: center;
            padding: 24px;
            color: #334155;
            font-size: 0.82rem;
            font-family: "Nunito", sans-serif;
        }

        @media (max-width: 500px) {
            .auth-card  { padding: 28px 20px; }
            .info-panel { display: none; }
        }
    </style>
</head>
<body>

<?php include "includes/header.php"; ?>

<div class="page-body">

    <!-- Register form -->
    <div class="auth-card">
        <h2>Create an account</h2>
        <p class="sub">Student sign-up. Free and takes about a minute.</p>

        <?php if (!empty($_SESSION["reg-error"])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION["reg-error"]) ?></div>
            <?php unset($_SESSION["reg-error"]); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION["reg-success"])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION["reg-success"]) ?></div>
            <?php unset($_SESSION["reg-success"]); ?>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" required
                       placeholder="Jane Smith"
                       value="<?= htmlspecialchars($_POST["full_name"] ?? "") ?>">
            </div>
            <div class="field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" required
                       placeholder="you@example.com"
                       value="<?= htmlspecialchars($_POST["email"] ?? "") ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       placeholder="At least 6 characters">
                <p class="field-hint">At least 6 characters.</p>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       placeholder="Same password again">
            </div>
            <button type="submit" class="btn-submit">Create account</button>
        </form>

        <div class="link-row">
            Already have an account? <a href="/lms/login.php">Log in</a>
        </div>
    </div>



</div>

<footer>&copy; 2026 LMS System</footer>

</body>
</html>
