<?php
session_start();

include "../includes/db.php";
include "../includes/activity.php";

if ($_SESSION["role"] != "teacher") {
    header("Location: /lms/login.php");
    exit();
}

$teacher_id = $_SESSION["user_id"];
$feedback   = "";

// Fetch current info
$sql = $conn->prepare("SELECT teacher_id, email, full_name FROM TEACHER_INFO WHERE teacher_id = ?");
$sql->bind_param("i", $teacher_id);
$sql->execute();
$info = $sql->get_result()->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_email     = trim($_POST["email"] ?? "");
    $new_full_name = trim($_POST["full_name"] ?? "");
    $updated       = false;

    if ($new_email !== "" && $new_email !== $info["email"]) {
        $sql = $conn->prepare("UPDATE TEACHER_USERS SET email = ? WHERE teacher_id = ?");
        $sql->bind_param("si", $new_email, $teacher_id);
        $sql->execute();
        $sql = $conn->prepare("UPDATE TEACHER_INFO SET email = ? WHERE teacher_id = ?");
        $sql->bind_param("si", $new_email, $teacher_id);
        $sql->execute();
        logActivity($conn, $teacher_id, "teacher", "update", "Teacher updated their email.", "teacher_info/teacher_users");
        $info["email"] = $new_email;
        $updated = true;
    }

    if ($new_full_name !== "" && $new_full_name !== $info["full_name"]) {
        $sql = $conn->prepare("UPDATE TEACHER_INFO SET full_name = ? WHERE teacher_id = ?");
        $sql->bind_param("si", $new_full_name, $teacher_id);
        $sql->execute();
        logActivity($conn, $teacher_id, "teacher", "update", "Teacher updated their name.", "teacher_info");
        $info["full_name"] = $new_full_name;
        $updated = true;
    }

    if ($updated) {
        $feedback = ["type" => "success", "msg" => "Account updated successfully."];
    } else {
        $feedback = ["type" => "info", "msg" => "Nothing changed — your details are already up to date."];
    }
}

$display_name = $info["full_name"] ?: "Teacher";
$initials     = strtoupper(implode("", array_map(fn($w) => $w[0], explode(" ", trim($display_name)))));
$initials     = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Info — LMS</title>
    <link rel="stylesheet" href="../lms.css?v=10">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Nunito", "Segoe UI", sans-serif; background: #0f172a; color: #f1f5f9; margin: 0; }

        .info-page {
            max-width: 680px;
            margin: 40px auto;
            padding: 0 24px 80px;
        }

        .breadcrumb { font-size: 0.85rem; color: #64748b; margin-bottom: 24px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        /* Avatar + name hero */
        .profile-hero {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 32px;
        }
        .avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: #1e3a5f;
            border: 2px solid #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 800;
            color: #93c5fd;
            flex-shrink: 0;
        }
        .profile-hero h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 4px;
        }
        .profile-hero p { font-size: 0.88rem; color: #64748b; }

        /* feedback */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 700;
            margin-bottom: 24px;
        }
        .alert-success { background: #052e16; color: #86efac; border: 1px solid #14532d; }
        .alert-info    { background: #1e293b; color: #94a3b8; border: 1px solid #334155; }

        /* card */
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 20px;
        }
        .card h2 {
            font-size: 1rem;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 6px;
        }
        .card .card-sub {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 24px;
        }

        /* current values */
        .current-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 28px;
            padding: 18px;
            background: #0f172a;
            border-radius: 10px;
            border: 1px solid #1e293b;
        }
        .current-item span {
            display: block;
            font-size: 0.75rem;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .current-item strong {
            font-size: 0.95rem;
            color: #e2e8f0;
            font-weight: 600;
        }

        /* form */
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
        .field-hint { font-size: 0.78rem; color: #475569; margin-top: 5px; }

        .btn-save {
            padding: 12px 28px;
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s;
        }
        .btn-save:hover { background: #2563eb; }

        /* role badge */
        .role-badge {
            display: inline-block;
            padding: 3px 12px;
            background: #1e3a5f;
            color: #93c5fd;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            margin-top: 4px;
        }

        @media (max-width: 500px) {
            .current-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include "../includes/header.php"; ?>

<main class="info-page">

    <div class="breadcrumb">
        <a href="teacher-dash.php">Dashboard</a> &rsaquo; Account Info
    </div>

    <div class="profile-hero">
        <div class="avatar"><?= htmlspecialchars($initials) ?></div>
        <div>
            <h1><?= $info["full_name"] ? htmlspecialchars($info["full_name"]) : "No name set yet" ?></h1>
            <p><?= htmlspecialchars($info["email"]) ?></p>
            <span class="role-badge">Teacher</span>
        </div>
    </div>

    <?php if ($feedback): ?>
        <div class="alert alert-<?= $feedback["type"] ?>"><?= htmlspecialchars($feedback["msg"]) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Update your details</h2>
        <p class="card-sub">Leave a field blank to keep it the same.</p>

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
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name"
                       placeholder="e.g. Jane Smith">
                <p class="field-hint">First and last name.</p>
            </div>
            <div class="field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email"
                       placeholder="e.g. jane@school.edu">
            </div>
            <button type="submit" class="btn-save">Save changes</button>
        </form>
    </div>

</main>

<footer style="border-top:1px solid #1e293b; text-align:center; padding:24px; color:#334155; font-size:0.82rem; font-family:'Nunito',sans-serif;">
    &copy; 2026 LMS System
</footer>

</body>
</html>
