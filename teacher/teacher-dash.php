<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] != "teacher") {
    header("Location: /lms/login.php");
    exit();
}

$teacher_id  = $_SESSION["user_id"];
$teacher_name = $_SESSION["full_name"] ?? "Teacher";


// CSV EXPORT — must happen before any HTML output
if (isset($_GET["export_csv"])) {
    $csv_result = $conn->query(
        "SELECT q.title, q.is_published,
                c.class_name,
                COUNT(DISTINCT qu.question_id)      AS question_count,
                COUNT(DISTINCT ss.submission_id)    AS submissions,
                ROUND(AVG(ss.percentage), 1)        AS avg_score,
                ROUND(MAX(ss.percentage), 1)        AS top_score,
                ROUND(MIN(ss.percentage), 1)        AS low_score
         FROM QUIZZES q
         LEFT JOIN CLASSES c              ON q.class_id   = c.class_id
         LEFT JOIN QUESTIONS qu           ON q.quiz_id    = qu.quiz_id
         LEFT JOIN STUDENT_SUBMISSIONS ss ON q.quiz_id    = ss.quiz_id
                                         AND ss.submitted_at IS NOT NULL
         WHERE q.teacher_id = $teacher_id
         GROUP BY q.quiz_id
         ORDER BY q.created_at DESC"
    );

    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=\"quiz_report_" . date("Y-m-d") . ".csv\"");

    $out = fopen("php://output", "w");
    fputcsv($out, ["Quiz Title", "Class", "Status", "Questions", "Submissions", "Avg Score (%)", "Top Score (%)", "Low Score (%)"]);
    while ($row = $csv_result->fetch_assoc()) {
        fputcsv($out, [
            $row["title"],
            $row["class_name"] ?? "—",
            $row["is_published"] ? "Published" : "Draft",
            $row["question_count"],
            $row["submissions"],
            $row["avg_score"]  ?? "—",
            $row["top_score"]  ?? "—",
            $row["low_score"]  ?? "—",
        ]);
    }
    fclose($out);
    exit();
}


// STAT CARDS — real aggregated numbers
// Total unique students across all this teacher's classes
$r = $conn->query(
    "SELECT COUNT(DISTINCT ce.student_id) AS total
     FROM CLASS_ENROLLMENTS ce JOIN CLASSES c ON ce.class_id = c.class_id WHERE teacher_id = $teacher_id"
);
$total_students = $r->fetch_assoc()["total"] ?? 0;

// Total quizzes created
$r = $conn->query("SELECT COUNT(*) AS total FROM QUIZZES WHERE teacher_id = $teacher_id");
$total_quizzes = $r->fetch_assoc()["total"] ?? 0;

// Overall average score across all submitted attempts
$r = $conn->query(
    "SELECT ROUND(AVG(ss.percentage), 1) AS avg_score
     FROM STUDENT_SUBMISSIONS ss
     JOIN QUIZZES q ON ss.quiz_id = q.quiz_id
     WHERE q.teacher_id = $teacher_id AND ss.submitted_at IS NOT NULL"
);
$avg_score = $r->fetch_assoc()["avg_score"] ?? null;

// Top score across all submissions
$r = $conn->query(
    "SELECT ROUND(MAX(ss.percentage), 1) AS top_score
     FROM STUDENT_SUBMISSIONS ss
     JOIN QUIZZES q ON ss.quiz_id = q.quiz_id
     WHERE q.teacher_id = $teacher_id AND ss.submitted_at IS NOT NULL"
);
$top_score = $r->fetch_assoc()["top_score"] ?? null;


// QUIZ TABLE — with per-quiz stats
$quizzes_result = $conn->query(
    "SELECT q.quiz_id, q.title, q.is_published, q.created_at,
            c.class_name,
            COUNT(DISTINCT qu.question_id)      AS question_count,
            COALESCE(SUM(qu.points), 0)         AS total_points,
            COUNT(DISTINCT ss.submission_id)    AS submissions,
            ROUND(AVG(ss.percentage), 1)        AS avg_score,
            ROUND(MAX(ss.percentage), 1)        AS top_score,
            ROUND(MIN(ss.percentage), 1)        AS low_score
     FROM QUIZZES q
     LEFT JOIN CLASSES c              ON q.class_id   = c.class_id
     LEFT JOIN QUESTIONS qu           ON q.quiz_id    = qu.quiz_id
     LEFT JOIN STUDENT_SUBMISSIONS ss ON q.quiz_id    = ss.quiz_id
                                     AND ss.submitted_at IS NOT NULL
     WHERE q.teacher_id = $teacher_id
     GROUP BY q.quiz_id
     ORDER BY q.created_at DESC"
);


// RECENT RESULTS — last 5 submissions across all teacher's quizzes
$recent_result = $conn->query(
    "SELECT ss.percentage, ss.score, ss.total_points, ss.submitted_at,
            si.full_name AS student_name,
            q.title      AS quiz_title
     FROM STUDENT_SUBMISSIONS ss
     JOIN QUIZZES q        ON ss.quiz_id    = q.quiz_id
     JOIN STUDENT_INFO si  ON ss.student_id = si.student_id
     WHERE q.teacher_id = $teacher_id AND ss.submitted_at IS NOT NULL
     ORDER BY ss.submitted_at DESC
     LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="../lms.css?v=10">
    <style>
        .teacher-page {
            max-width: 1180px;
            margin: 36px auto;
            padding: 0 20px;
        }

        /* Hero */
        .teacher-hero { margin-bottom: 28px; }
        .teacher-hero h1 { font-size: 1.9rem; color: #fff; margin-bottom: 6px; }
        .teacher-hero p  { color: #94a3b8; }

        /* Quick action cards */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        .action-card {
            background: #1f2a3a;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 24px;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: transform 0.2s, border-color 0.2s;
        }
        .action-card:hover { transform: translateY(-3px); border-color: #475569; }
        .action-card .icon { font-size: 2rem; }
        .action-card h2   { font-size: 1.05rem; }
        .action-card p    { color: #94a3b8; font-size: 0.88rem; line-height: 1.5; }
        .action-card.blue   h2 { color: #3b82f6; }
        .action-card.green  h2 { color: #22c55e; }
        .action-card.purple h2 { color: #a855f7; }
        .action-btn {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.88rem;
            text-align: center;
            text-decoration: none;
            color: #fff;
            border: none;
            cursor: pointer;
            margin-top: auto;
        }
        .btn-blue   { background: #2563eb; }
        .btn-green  { background: #16a34a; }
        .btn-purple { background: #7c3aed; }
        .action-btn:hover { filter: brightness(1.15); }

        /* Stat boxes */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-box {
            background: #1f2a3a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 20px;
        }
        .stat-box .stat-icon { font-size: 1.4rem; margin-bottom: 10px; }
        .stat-box span  { display: block; font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .stat-box strong { font-size: 1.9rem; color: #fff; font-weight: 700; }

        /* Panel */
        .dashboard-panel {
            background: #1f2a3a;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .panel-header h2 { color: #fff; font-size: 1.1rem; }

        /* Quiz table */
        .assessment-table {
            width: 100%;
            border-collapse: collapse;
            background: #111827;
            border-radius: 10px;
            overflow: hidden;
        }
        .assessment-table th,
        .assessment-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #1e293b;
            color: #e2e8f0;
            font-size: 0.9rem;
        }
        .assessment-table th {
            background: #1e3a5f;
            color: #93c5fd;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .assessment-table tr:last-child td { border-bottom: none; }
        .assessment-table tr:hover td { background: #1e293b; }

        .badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .badge-pub   { background: #14532d; color: #86efac; }
        .badge-draft { background: #334155; color: #94a3b8; }

        /* Score colouring */
        .score-high { color: #86efac; font-weight: 700; }
        .score-mid  { color: #fde68a; font-weight: 700; }
        .score-low  { color: #fca5a5; font-weight: 700; }

        .mini {
            padding: 5px 10px;
            border: none;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            color: #fff;
            margin: 2px;
        }
        .btn-edit      { background: #d97706; }
        .btn-questions { background: #7c3aed; }
        .btn-results   { background: #0891b2; }

        /* Analytics 2-col layout */
        .analytics-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* Recent submissions table */
        .recent-table {
            width: 100%;
            border-collapse: collapse;
        }
        .recent-table th,
        .recent-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #1e293b;
            font-size: 0.87rem;
            color: #e2e8f0;
        }
        .recent-table th { color: #64748b; font-size: 0.78rem; text-transform: uppercase; }
        .recent-table tr:last-child td { border-bottom: none; }

        /* Reports panel */
        .report-btn {
            display: block;
            width: 100%;
            margin-bottom: 10px;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #7c3aed;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.9rem;
            text-align: left;
            text-decoration: none;
        }
        .report-btn:hover { filter: brightness(1.15); }
        .report-btn.csv   { background: #0f766e; }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        .table-wrap { overflow-x: auto; }

        @media (max-width: 900px) {
            .action-grid, .stats-grid, .analytics-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<?php include "../includes/header.php"; ?>

<main class="teacher-page">

    <!-- Hero -->
    <div class="teacher-hero">
        <h1>Welcome back, <?= htmlspecialchars($teacher_name) ?> 👋</h1>
        <p>Manage your quizzes, track student progress, and generate reports.</p>
    </div>

    <!-- Quick action cards -->
    <div class="action-grid">
        <div class="action-card blue">
            <div class="icon">📋</div>
            <h2>Manage Quizzes</h2>
            <p>Create, edit, and publish quizzes for your classes.</p>
            <a href="quiz.php" class="action-btn btn-blue">Go to Quizzes</a>
        </div>
        <div class="action-card green">
            <div class="icon">📊</div>
            <h2>Analyze Progress</h2>
            <p>View student performance and detailed analytics below.</p>
            <button type="button" class="action-btn btn-green"
                    onclick="document.getElementById('analytics').scrollIntoView({behavior:'smooth'})">
                View Analytics
            </button>
        </div>
        <div class="action-card purple">
            <div class="icon">📄</div>
            <h2>Generate Reports</h2>
            <p>Download CSV reports of student performance.</p>
            <button type="button" class="action-btn btn-purple"
                    onclick="document.getElementById('reports').scrollIntoView({behavior:'smooth'})">
                Generate Report
            </button>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-icon">👥</div>
            <span>Total Students</span>
            <strong><?= $total_students ?></strong>
        </div>
        <div class="stat-box">
            <div class="stat-icon">📋</div>
            <span>Quizzes Created</span>
            <strong><?= $total_quizzes ?></strong>
        </div>
        <div class="stat-box">
            <div class="stat-icon">📈</div>
            <span>Average Score</span>
            <strong><?= $avg_score !== null ? $avg_score . "%" : "—" ?></strong>
        </div>
        <div class="stat-box">
            <div class="stat-icon">🏆</div>
            <span>Top Score</span>
            <strong><?= $top_score !== null ? $top_score . "%" : "—" ?></strong>
        </div>
    </div>

    <!-- Quiz table -->
    <div class="dashboard-panel">
        <div class="panel-header">
            <h2>My Quizzes</h2>
            <a href="quiz.php" class="mini btn-edit" style="background:#2563eb; padding:8px 14px">
                + Create New Quiz
            </a>
        </div>

        <?php if ($quizzes_result && $quizzes_result->num_rows > 0): ?>
        <div class="table-wrap">
            <table class="assessment-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Class</th>
                        <th>Questions</th>
                        <th>Points</th>
                        <th>Submissions</th>
                        <th>Avg Score</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $n = 1; while ($quiz = $quizzes_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $n++ ?></td>
                        <td><strong><?= htmlspecialchars($quiz["title"]) ?></strong></td>
                        <td><?= $quiz["class_name"] ? htmlspecialchars($quiz["class_name"]) : "<span style='color:#475569'>—</span>" ?></td>
                        <td><?= $quiz["question_count"] ?></td>
                        <td><?= $quiz["total_points"] ?></td>
                        <td><?= $quiz["submissions"] ?></td>
                        <td>
                            <?php if ($quiz["submissions"] > 0):
                                $s = $quiz["avg_score"];
                                $cls = $s >= 80 ? "score-high" : ($s >= 60 ? "score-mid" : "score-low");
                            ?>
                                <span class="<?= $cls ?>"><?= $s ?>%</span>
                            <?php else: ?>
                                <span style="color:#475569">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($quiz["is_published"]): ?>
                                <span class="badge badge-pub">Published</span>
                            <?php else: ?>
                                <span class="badge badge-draft">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="quiz.php?edit=<?= $quiz["quiz_id"] ?>"             class="mini btn-edit">Edit</a>
                            <a href="add_questions.php?quiz_id=<?= $quiz["quiz_id"] ?>" class="mini btn-questions">Questions</a>
                            <a href="results.php?quiz_id=<?= $quiz["quiz_id"] ?>"       class="mini btn-results">Results</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <p style="font-size:2rem">📭</p>
                <p>No quizzes yet. <a href="quiz.php" style="color:#3b82f6">Create your first one.</a></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Analytics + Reports -->
    <div class="analytics-grid" id="analytics">

        <!-- Recent submissions -->
        <div class="dashboard-panel">
            <div class="panel-header">
                <h2>Recent Submissions</h2>
            </div>

            <?php if ($recent_result && $recent_result->num_rows > 0): ?>
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Quiz</th>
                        <th>Score</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($sub = $recent_result->fetch_assoc()):
                    $pct = $sub["percentage"];
                    $cls = $pct >= 80 ? "score-high" : ($pct >= 60 ? "score-mid" : "score-low");
                ?>
                    <tr>
                        <td><?= htmlspecialchars($sub["student_name"]) ?></td>
                        <td><?= htmlspecialchars($sub["quiz_title"]) ?></td>
                        <td>
                            <span class="<?= $cls ?>"><?= $pct ?>%</span>
                            <small style="color:#64748b"> (<?= $sub["score"] ?>/<?= $sub["total_points"] ?>)</small>
                        </td>
                        <td style="color:#64748b; font-size:0.82rem">
                            <?= date("M j, g:ia", strtotime($sub["submitted_at"])) ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No submissions yet.</p>
                    <p style="font-size:0.85rem; margin-top:8px">Results will appear here once students complete quizzes.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reports panel -->
        <div class="dashboard-panel" id="reports">
            <h2 style="color:#fff; margin-bottom:8px">Generate Reports</h2>
            <p style="color:#64748b; font-size:0.88rem; margin-bottom:18px">Download data for your records.</p>

            <a href="teacher-dash.php?export_csv=1" class="report-btn csv">
                ⬇️ Download Full CSV Report
            </a>

            <div style="background:#111827; border-radius:10px; padding:14px; margin-top:16px;">
                <p style="color:#64748b; font-size:0.82rem; margin-bottom:10px; text-transform:uppercase; letter-spacing:0.05em;">CSV includes:</p>
                <ul style="color:#94a3b8; font-size:0.85rem; padding-left:16px; line-height:2;">
                    <li>Quiz title &amp; class</li>
                    <li>Question &amp; submission counts</li>
                    <li>Average, top &amp; low scores</li>
                    <li>Published / draft status</li>
                </ul>
            </div>
        </div>

    </div>

</main>

<footer>
    <p>&copy; 2026 LMS System. All rights reserved.</p>
</footer>
</body>
</html>
