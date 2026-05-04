<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if ($_SESSION["role"] != "admin") {
    header("Location: /lms/login.php");
    exit();
}

$feedback = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["teacher-register"])) {
    $email     = $conn->real_escape_string(trim($_POST["register-email"]));
    $full_name = $conn->real_escape_string(trim($_POST["full_name"]));
    $password  = $_POST["password"];
    $hash      = password_hash($password, PASSWORD_DEFAULT);

    $check = $conn->query("SELECT teacher_id FROM TEACHER_INFO WHERE email='$email'");

    if ($check->num_rows > 0) {
        $feedback = ["type" => "error", "msg" => "A teacher with that email already exists."];
    } else {
        $info = $conn->prepare("INSERT INTO TEACHER_INFO (email, full_name) VALUES (?,?)");
        $info->bind_param("ss", $email, $full_name);

        if ($info->execute()) {
            $tid = $conn->insert_id;
            $user = $conn->prepare("INSERT INTO TEACHER_USERS (teacher_id, email, password_hash) VALUES (?,?,?)");
            $user->bind_param("iss", $tid, $email, $hash);

            if ($user->execute()) {
                logActivity($conn, $_SESSION["user_id"], "admin", "register", "Admin created teacher: $email", "teacher_users/teacher_info");
                $feedback = ["type" => "success", "msg" => "Teacher account created for $email."];
            }
        } else {
            $feedback = ["type" => "error", "msg" => "Database error: " . $conn->error];
        }
    }
}

if (isset($_GET["delete"]) && is_numeric($_GET["delete"])) {
    $tid = intval($_GET["delete"]);
    $conn->query("DELETE FROM TEACHER_USERS WHERE teacher_id=$tid");
    $conn->query("DELETE FROM TEACHER_INFO WHERE teacher_id=$tid");
    logActivity($conn, $_SESSION["user_id"], "admin", "DELETE", "Deleted teacher $tid", "teacher_info");
    header("Location: teacher.php");
    exit();
}

$teachers_r = $conn->query("
    SELECT ti.teacher_id, ti.email, ti.full_name, tu.created_at
    FROM TEACHER_INFO ti
    LEFT JOIN TEACHER_USERS tu ON ti.teacher_id = tu.teacher_id
    ORDER BY ti.teacher_id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers</title>
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
            max-width: 1000px;
            margin: 36px auto;
            padding: 0 24px 80px;
        }

        .breadcrumb {
            font-size: .85rem;
            color: #64748b;
            margin-bottom: 20px;
        }

        .breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
        }

        .hero {
            margin-bottom: 28px;
        }

        .hero h1 {
            font-size: 1.7rem;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 4px;
        }

        .hero p {
            color: #64748b;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #052e16;
            color: #86efac;
            border: 1px solid #14532d;
        }

        .alert-error {
            background: #450a0a;
            color: #fca5a5;
            border: 1px solid #7f1d1d;
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            align-items: start;
        }

        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 24px;
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
            margin-bottom: 20px;
        }

        .field {
            margin-bottom: 14px;
        }

        .field label {
            display: block;
            font-size: .85rem;
            font-weight: 700;
            color: #cbd5e1;
            margin-bottom: 6px;
        }

        .field input {
            width: 100%;
            padding: 10px 13px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #f1f5f9;
            font-size: .92rem;
            font-family: inherit;
            transition: border-color .2s;
            box-sizing: border-box;
        }

        .field input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .field input::placeholder {
            color: #475569;
        }

        .btn-submit {
            padding: 10px 22px;
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .92rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
        }

        .btn-submit:hover {
            background: #2563eb;
        }

        .t-table {
            width: 100%;
            border-collapse: collapse;
        }

        .t-table th,
        .t-table td {
            padding: 11px 14px;
            text-align: left;
            border-bottom: 1px solid #1e293b;
            font-size: .88rem;
            color: #e2e8f0;
        }

        .t-table th {
            color: #64748b;
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .t-table tr:last-child td {
            border-bottom: none;
        }

        .btn-del {
            padding: 5px 11px;
            background: #7f1d1d;
            color: #fca5a5;
            border: none;
            border-radius: 6px;
            font-size: .78rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-del:hover {
            background: #dc2626;
            color: #fff;
        }

        @media (max-width: 700px) {
            .two-col {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<main class="page">
    <div class="breadcrumb">
        <a href="admin-dash.php">Dashboard</a> › Teachers
    </div>

    <div class="hero">
        <h1>✏️ Manage Teachers</h1>
        <p>Create teacher accounts and view who's on the platform.</p>
    </div>

    <?php if ($feedback): ?>
        <div class="alert alert-<?= $feedback["type"] ?>"><?= htmlspecialchars($feedback["msg"]) ?></div>
    <?php endif; ?>

    <div class="two-col">
        <div class="card">
            <h2>Add a teacher</h2>
            <p class="sub">They can log in immediately with these credentials.</p>
            <form method="POST">
                <div class="field">
                    <label>Full name</label>
                    <input type="text" name="full_name" placeholder="Jane Smith" required>
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="register-email" placeholder="teacher@school.edu" required>
                </div>
                <div class="field">
                    <label>Temporary password</label>
                    <input type="password" name="password" placeholder="Min 6 characters" required>
                </div>
                <button type="submit" name="teacher-register" class="btn-submit">Create Account</button>
            </form>
        </div>

        <div class="card">
            <h2>All teachers (<?= $teachers_r->num_rows ?>)</h2>
            <?php if ($teachers_r->num_rows > 0): ?>
                <table class="t-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $n = 1; while ($t = $teachers_r->fetch_assoc()): ?>
                            <tr>
                                <td><?= $n++ ?></td>
                                <td><?= $t["full_name"] ? htmlspecialchars($t["full_name"]) : "<span style='color:#475569'>—</span>" ?></td>
                                <td style="color:#64748b"><?= htmlspecialchars($t["email"]) ?></td>
                                <td style="color:#475569;font-size:.8rem"><?= $t["created_at"] ? date("M j, Y", strtotime($t["created_at"])) : "—" ?></td>
                                <td>
                                    <a href="teacher.php?delete=<?= $t["teacher_id"] ?>"
                                       class="btn-del"
                                       onclick="return confirm('Delete this teacher? This cannot be undone.')">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:#475569">No teachers yet.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<footer style="border-top:1px solid #1e293b;text-align:center;padding:24px;color:#334155;font-size:.82rem;">
    &copy; 2026 LMS System
</footer>

</body>
</html>
