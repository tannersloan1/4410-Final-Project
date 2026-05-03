<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if ($_SESSION["role"] != "admin") {
    header("Location: /lms/login.php");
    exit();
}

$admin_id = $_SESSION["user_id"];
$feedback = null;

$sql = $conn->prepare("SELECT email, full_name FROM ADMIN_INFO WHERE admin_id=?");
$sql->bind_param("i", $admin_id);
$sql->execute();
$info = $sql->get_result()->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_name  = $conn->real_escape_string(trim($_POST["full_name"] ?? ""));
    $new_email = $conn->real_escape_string(trim($_POST["email"] ?? ""));
    $updated = false;

    if ($new_name && $new_name !== $info["full_name"]) {
        $conn->query("UPDATE ADMIN_INFO SET full_name='$new_name' WHERE admin_id=$admin_id");
        $info["full_name"] = $new_name;
        $updated = true;
    }

    if ($new_email && $new_email !== $info["email"]) {
        $conn->query("UPDATE ADMIN_INFO SET email='$new_email' WHERE admin_id=$admin_id");
        $conn->query("UPDATE ADMIN_USERS SET email='$new_email' WHERE admin_id=$admin_id");
        $info["email"] = $new_email;
        $updated = true;
    }

    $feedback = $updated
        ? ["type" => "success", "msg" => "Account updated."]
        : ["type" => "info",    "msg" => "Nothing changed."];
}

$display_name = $info["full_name"] ?: "Admin";
$initials = strtoupper(substr(implode("", array_map(fn($w) => $w[0], explode(" ", trim($display_name)))), 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Info</title>
    <link rel="stylesheet" href="../lms.css?v=10">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Nunito", "Segoe UI", sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            margin: 0;
        }

        .page {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 24px 80px;
        }

        .breadcrumb {
            font-size: .85rem;
            color: #64748b;
            margin-bottom: 24px;
        }

        .breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
        }

        .profile-hero {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 32px;
        }

        .avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #3b0764;
            border: 2px solid #a855f7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 800;
            color: #d8b4fe;
            flex-shrink: 0;
        }

        .profile-hero h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 4px;
        }

        .profile-hero p {
            font-size: .88rem;
            color: #64748b;
        }

        .role-badge {
            display: inline-block;
            padding: 3px 12px;
            background: #3b0764;
            color: #d8b4fe;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 700;
            margin-top: 4px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .alert-success {
            background: #052e16;
            color: #86efac;
            border: 1px solid #14532d;
        }

        .alert-info {
            background: #1e293b;
            color: #94a3b8;
            border: 1px solid #334155;
        }

        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 28px;
        }

        .card h2 {
            font-size: 1rem;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 6px;
        }

        .card .sub {
            font-size: .85rem;
            color: #64748b;
            margin-bottom: 24px;
        }

        .current-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            margin-bottom: 28px;
            padding: 20px;
            background: #0f172a;
            border-radius: 10px;
            border: 1px solid #1e293b;
        }

        .current-item {
            flex: 1;
            min-width: 200px;
        }

        .current-item span {
            display: block;
            font-size: .75rem;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 4px;
        }

        .current-item strong {
            font-size: .95rem;
            color: #e2e8f0;
            font-weight: 600;
        }

        .field {
            margin-bottom: 18px;
        }

        .field label {
            display: block;
            font-size: .88rem;
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
            font-size: .95rem;
            font-family: inherit;
            transition: border-color .2s;
            box-sizing: border-box;
        }

        .field input:focus {
            outline: none;
            border-color: #a855f7;
        }

        .field input::placeholder {
            color: #475569;
        }

        .btn-save {
            padding: 12px 28px;
            background: #7c3aed;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
        }

        .btn-save:hover {
            background: #6d28d9;
        }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<main class="page">
    <div class="breadcrumb">
        <a href="admin-dash.php">Dashboard</a> › Account Info
    </div>

    <div class="profile-hero">
        <div class="avatar"><?= htmlspecialchars($initials) ?></div>
        <div>
            <h1><?= $info["full_name"] ? htmlspecialchars($info["full_name"]) : "No name set" ?></h1>
            <p><?= htmlspecialchars($info["email"]) ?></p>
            <span class="role-badge">Admin</span>
        </div>
    </div>

    <?php if ($feedback): ?>
        <div class="alert alert-<?= $feedback["type"] ?>"><?= htmlspecialchars($feedback["msg"]) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Update your details</h2>
        <p class="sub">Leave a field blank to keep it the same.</p>

        <div class="current-grid">
            <div class="current-item">
                <span>Current name</span>
                <strong><?= $info["full_name"] ? htmlspecialchars($info["full_name"]) : "—" ?></strong>
            </div>
            <div class="current-item">
                <span>Current email</span>
                <strong><?= htmlspecialchars($info["email"]) ?></strong>
            </div>
        </div>

        <form method="POST">
            <div class="field">
                <label>Full name</label>
                <input type="text" name="full_name" placeholder="e.g. Admin User">
            </div>
            <div class="field">
                <label>Email address</label>
                <input type="email" name="email" placeholder="e.g. admin@school.edu">
            </div>
            <button type="submit" class="btn-save">Save changes</button>
        </form>
    </div>
</main>

<footer style="border-top:1px solid #1e293b;text-align:center;padding:24px;color:#334155;font-size:.82rem;">
    &copy; 2026 LMS System
</footer>

</body>
</html>
