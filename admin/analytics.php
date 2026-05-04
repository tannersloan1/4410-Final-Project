<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if ($_SESSION["role"] != "admin") {
    header("Location: /lms/login.php");
    exit();
}

// Platform stats
$total_students  = $conn->query("SELECT COUNT(*) AS c FROM STUDENT_INFO")->fetch_assoc()["c"];
$total_teachers  = $conn->query("SELECT COUNT(*) AS c FROM TEACHER_INFO")->fetch_assoc()["c"];
$total_quizzes   = $conn->query("SELECT COUNT(*) AS c FROM QUIZZES")->fetch_assoc()["c"];
$pub_quizzes     = $conn->query("SELECT COUNT(*) AS c FROM QUIZZES WHERE is_published=1")->fetch_assoc()["c"];
$total_subs      = $conn->query("SELECT COUNT(*) AS c FROM STUDENT_SUBMISSIONS WHERE submitted_at IS NOT NULL")->fetch_assoc()["c"];
$avg_score_r     = $conn->query("SELECT ROUND(AVG(percentage),1) AS a FROM STUDENT_SUBMISSIONS WHERE submitted_at IS NOT NULL");
$avg_score       = $avg_score_r->fetch_assoc()["a"];
$total_questions = $conn->query("SELECT COUNT(*) AS c FROM QUESTIONS")->fetch_assoc()["c"];
$total_logs      = $conn->query("SELECT COUNT(*) AS c FROM LOGS")->fetch_assoc()["c"];

// CSV export
if (isset($_GET["export_logs"])) {
    $all_logs = $conn->query("SELECT * FROM LOGS ORDER BY created_at DESC");
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=\"activity_logs_" . date("Y-m-d") . ".csv\"");
    $out = fopen("php://output", "w");
    fputcsv($out, ["ID", "Role", "Action", "Description", "Table", "IP", "Time"]);
    while ($l = $all_logs->fetch_assoc()) {
        fputcsv($out, [
            $l["id"],
            $l["role"],
            $l["action_type"],
            $l["action_description"],
            $l["table_affected"],
            $l["ip_address"],
            $l["created_at"]
        ]);
    }
    fclose($out);
    exit();
}

// Filter
$filter_role = $_GET["role"] ?? "";
$where = $filter_role ? "WHERE role='" . addslashes($filter_role) . "'" : "";
$logs_r = $conn->query("SELECT * FROM LOGS $where ORDER BY created_at DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Logs</title>
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
            padding: 18px;
            text-align: center;
        }

        .stat-box .icon {
            font-size: 1.3rem;
            margin-bottom: 8px;
        }

        .stat-box span {
            display: block;
            font-size: .72rem;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 4px;
        }

        .stat-box strong {
            font-size: 1.6rem;
            color: #fff;
            font-weight: 800;
        }

        .panel {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }

        .panel-header h2 {
            font-size: 1rem;
            font-weight: 800;
            color: #f8fafc;
        }

        .controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-btn {
            padding: 7px 14px;
            border-radius: 7px;
            border: 1px solid #334155;
            background: #0f172a;
            color: #94a3b8;
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: #1e3a5f;
            color: #93c5fd;
            border-color: #3b82f6;
        }

        .export-btn {
            padding: 7px 14px;
            border-radius: 7px;
            background: #0f766e;
            color: #fff;
            font-size: .82rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
        }

        .export-btn:hover {
            filter: brightness(1.15);
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
            font-size: .85rem;
            color: #e2e8f0;
        }

        .log-table th {
            color: #64748b;
            font-size: .74rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            background: #111827;
        }

        .log-table tr:last-child td {
            border-bottom: none;
        }

        .role-pill {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 700;
        }

        .pill-student { background: #052e16; color: #86efac; }
        .pill-teacher { background: #1e3a5f; color: #93c5fd; }
        .pill-admin   { background: #3b0764; color: #d8b4fe; }
        .pill-unknown { background: #1e293b; color: #64748b; }

        @media (max-width: 800px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<main class="page">
    <div class="breadcrumb">
        <a href="admin-dash.php">Dashboard</a> › Analytics & Logs
    </div>

    <div class="hero">
        <h1>📊 Analytics & Logs</h1>
        <p>Platform wide stats and a full activity log.</p>
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
            <span>Published Quizzes</span>
            <strong><?= $pub_quizzes ?>/<?= $total_quizzes ?></strong>
        </div>
        <div class="stat-box">
            <div class="icon">📝</div>
            <span>Submissions</span>
            <strong><?= $total_subs ?></strong>
        </div>
        <div class="stat-box">
            <div class="icon">📈</div>
            <span>Platform Avg</span>
            <strong><?= $avg_score !== null ? $avg_score . "%" : "—" ?></strong>
        </div>
        <div class="stat-box">
            <div class="icon">❓</div>
            <span>Questions</span>
            <strong><?= $total_questions ?></strong>
        </div>
        <div class="stat-box">
            <div class="icon">📌</div>
            <span>Log Entries</span>
            <strong><?= $total_logs ?></strong>
        </div>
        <div class="stat-box">
            <div class="icon">🏫</div>
            <span>Classes</span>
            <strong><?= $conn->query("SELECT COUNT(DISTINCT class_name) AS c FROM CLASSES")->fetch_assoc()["c"] ?></strong>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2>Activity Log (last 50)</h2>
            <div class="controls">
                <a href="analytics.php" class="filter-btn <?= !$filter_role ? 'active' : '' ?>">All</a>
                <a href="analytics.php?role=student" class="filter-btn <?= $filter_role === 'student' ? 'active' : '' ?>">Students</a>
                <a href="analytics.php?role=teacher" class="filter-btn <?= $filter_role === 'teacher' ? 'active' : '' ?>">Teachers</a>
                <a href="analytics.php?role=admin"   class="filter-btn <?= $filter_role === 'admin'   ? 'active' : '' ?>">Admins</a>
                <a href="analytics.php?export_logs=1" class="export-btn">⬇️ Export CSV</a>
            </div>
        </div>

        <?php if ($logs_r && $logs_r->num_rows > 0): ?>
            <div style="overflow-x:auto">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Table</th>
                            <th>IP</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($log = $logs_r->fetch_assoc()):
                            $pc = "pill-" . ($log["role"] ?? "unknown");
                        ?>
                            <tr>
                                <td><span class="role-pill <?= $pc ?>"><?= htmlspecialchars($log["role"]) ?></span></td>
                                <td><?= htmlspecialchars($log["action_type"]) ?></td>
                                <td style="color:#64748b"><?= htmlspecialchars($log["action_description"] ?? "—") ?></td>
                                <td style="color:#475569; font-size:.8rem"><?= htmlspecialchars($log["table_affected"] ?? "—") ?></td>
                                <td style="color:#334155; font-size:.78rem"><?= htmlspecialchars($log["ip_address"] ?? "—") ?></td>
                                <td style="color:#475569; font-size:.8rem;white-space:nowrap"><?= date("M j, g:ia", strtotime($log["created_at"])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:#475569">No logs found.</p>
        <?php endif; ?>
    </div>
</main>

<footer style="border-top:1px solid #1e293b;text-align:center;padding:24px;color:#334155;font-size:.82rem;">
    &copy; 2026 LMS System
</footer>

</body>
</html>
