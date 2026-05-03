<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if ($_SESSION["role"] != "admin") {
    header("Location: /lms/login.php");
    exit();
}

$admin_id = $_SESSION["user_id"];

$sql = $conn->prepare("SELECT email, full_name FROM ADMIN_INFO WHERE admin_id=?");
$sql->bind_param("i", $admin_id);
$sql->execute();
$admin = $sql->get_result()->fetch_assoc();

$total_students = $conn->query("SELECT COUNT(*) AS c FROM STUDENT_INFO")->fetch_assoc()["c"];
$total_teachers = $conn->query("SELECT COUNT(*) AS c FROM TEACHER_INFO")->fetch_assoc()["c"];
$total_quizzes  = $conn->query("SELECT COUNT(*) AS c FROM QUIZZES")->fetch_assoc()["c"];
$total_subs     = $conn->query("SELECT COUNT(*) AS c FROM STUDENT_SUBMISSIONS WHERE submitted_at IS NOT NULL")->fetch_assoc()["c"];

$logs_r = $conn->query("SELECT * FROM LOGS ORDER BY created_at DESC LIMIT 8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
            max-width: 1100px;
            margin: 36px auto;
            padding: 0 24px 80px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 28px;
        }

        .stat-box {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .stat-box .icon {
            font-size: 1.4rem;
            margin-bottom: 8px;
        }

        .stat-box span {
            display: block;
            font-size: 0.75rem;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 4px;
        }

        .stat-box strong {
            font-size: 1.8rem;
            color: #fff;
            font-weight: 800;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .action-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 24px;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: transform .2s, border-color .2s;
        }

        .action-card:hover {
            transform: translateY(-3px);
            border-color: #475569;
        }

        .action-card .icon {
            font-size: 2rem;
        }

        .action-card h2 {
            font-size: 1rem;
            font-weight: 800;
        }

        .action-card p {
            color: #64748b;
            font-size: 0.87rem;
            line-height: 1.55;
        }

        .card-btn {
            display: inline-block;
            padding: 9px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.87rem;
            text-decoration: none;
            color: #fff;
            margin-top: auto;
            font-family: inherit;
        }

        .panel {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 24px;
        }

        .panel h2 {
            font-size: 1rem;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 16px;
        }

        .log-table {
            width: 100%;
            border-collapse: collapse;
        }

        .log-table th,
        .log-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #1e293b;
            font-size: 0.85rem;
            color: #e2e8f0;
        }

        .log-table th {
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .log-table tr:last-child td {
            border-bottom: none;
        }

        @media (max-width: 800px) {
            .stats-grid,
            .action-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<main class="page">
    <div class="hero">
        <h1>Hello, <?= htmlspecialchars($admin["full_name"] ?? "Admin") ?> 👋</h1>
        <p>Here's what's going on across the system.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="icon">🎓</div>
            <span>Students</span>
            <strong><?= $total_students ?></strong>
        </div>
        <div class="stat-box">
            <div class="icon">✏️</div>
            <span>Teachers</span>
            <strong><?= $total_teachers ?></strong>
        </div>
        <div class="stat-box">
            <div class="icon">📋</div>
            <span>Quizzes</span>
            <strong><?= $total_quizzes ?></strong>
        </div>
        <div class="stat-box">
            <div class="icon">📝</div>
            <span>Submissions</span>
            <strong><?= $total_subs ?></strong>
        </div>
    </div>

    <div class="action-grid">
        <a href="teacher.php" class="action-card">
            <div class="icon">✏️</div>
            <h2 style="color:#3b82f6">Teachers</h2>
            <p>Add new teacher accounts and view all existing teachers.</p>
            <span class="card-btn" style="background:#2563eb">Manage Teachers</span>
        </a>
        <a href="student.php" class="action-card">
            <div class="icon">🎓</div>
            <h2 style="color:#22c55e">Students</h2>
            <p>View all student accounts and manage their status.</p>
            <span class="card-btn" style="background:#16a34a">Manage Students</span>
        </a>
        <a href="analytics.php" class="action-card">
            <div class="icon">📊</div>
            <h2 style="color:#a855f7">Analytics & Logs</h2>
            <p>View system activity logs and platform wide stats.</p>
            <span class="card-btn" style="background:#7c3aed">View Analytics</span>
        </a>
    </div>

    <div class="panel">
        <h2>Recent Activity</h2>
        <?php if ($logs_r && $logs_r->num_rows > 0): ?>
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $logs_r->fetch_assoc()): ?>
                        <tr>
                            <td style="text-transform:capitalize"><?= htmlspecialchars($log["role"]) ?></td>
                            <td><?= htmlspecialchars($log["action_type"]) ?></td>
                            <td style="color:#64748b"><?= htmlspecialchars($log["action_description"] ?? "—") ?></td>
                            <td style="color:#475569;font-size:0.8rem"><?= date("M j, g:ia", strtotime($log["created_at"])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#475569">No activity logged yet.</p>
        <?php endif; ?>
    </div>
</main>

<footer style="border-top:1px solid #1e293b;text-align:center;padding:24px;color:#334155;font-size:.82rem;">
    &copy; 2026 LMS System
</footer>

</body>
</html>
