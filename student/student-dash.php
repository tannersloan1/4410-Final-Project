<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] != "student") {
    header("Location: /lms/login.php"); exit();
}

$student_id   = $_SESSION["user_id"];
$student_name = $_SESSION["full_name"] ?? "Student";

// Get all quizzes for classes this student is enrolled in
$q_r = $conn->query(
    "SELECT q.quiz_id, q.title, q.description, q.time_limit, q.created_at,
            c.class_name,
            COUNT(DISTINCT qu.question_id)  AS question_count,
            COALESCE(SUM(qu.points), 0)     AS total_points,
            ss.submission_id, ss.score, ss.percentage, ss.submitted_at
     FROM CLASS_ENROLMENTS ce
     JOIN CLASSES c   ON ce.class_id  = c.class_id
     JOIN QUIZZES q   ON q.class_id   = c.class_id
     JOIN QUESTIONS qu ON qu.quiz_id  = q.quiz_id
     LEFT JOIN STUDENT_SUBMISSIONS ss ON ss.quiz_id = q.quiz_id AND ss.student_id = $student_id
     WHERE ce.student_id = $student_id
       AND q.is_published = 1
     GROUP BY q.quiz_id
     ORDER BY ss.submitted_at IS NOT NULL ASC, q.created_at DESC"
);

$quizzes     = [];
$done_count  = 0;
$total_count = 0;
while ($row = $q_r->fetch_assoc()) {
    $quizzes[] = $row;
    $total_count++;
    if ($row["submitted_at"]) $done_count++;
}

$avg_r  = $conn->query("SELECT ROUND(AVG(percentage),1) AS a FROM STUDENT_SUBMISSIONS WHERE student_id=$student_id AND submitted_at IS NOT NULL");
$my_avg = $avg_r->fetch_assoc()["a"] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../lms.css?v=10">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body{font-family:"Nunito","Segoe UI",sans-serif;background:#0f172a;color:#f1f5f9;margin:0;}
        .page{max-width:960px;margin:36px auto;padding:0 20px 60px;}
        .hero{margin-bottom:28px;}.hero h1{font-size:1.9rem;color:#fff;margin-bottom:6px;font-weight:800;}.hero p{color:#94a3b8;}
        .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px;}
        .stat-box{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;text-align:center;}
        .stat-box .icon{font-size:1.4rem;margin-bottom:8px;}
        .stat-box span{display:block;font-size:.78rem;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;}
        .stat-box strong{font-size:1.9rem;color:#fff;font-weight:700;}
        .progress-wrap{margin-bottom:28px;}
        .progress-label{display:flex;justify-content:space-between;font-size:.85rem;color:#94a3b8;margin-bottom:8px;}
        .progress-bar{height:10px;background:#1e293b;border-radius:5px;overflow:hidden;}
        .progress-fill{height:100%;background:linear-gradient(90deg,#2563eb,#7c3aed);border-radius:5px;}
        .section-title{font-size:1rem;color:#fff;font-weight:700;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #1e293b;}
        .quiz-grid{display:flex;flex-direction:column;gap:14px;margin-bottom:40px;}
        .quiz-card{background:#1e293b;border:1px solid #334155;border-radius:14px;padding:20px 24px;display:flex;align-items:center;gap:20px;transition:border-color .2s,transform .2s;}
        .quiz-card:hover{border-color:#475569;transform:translateX(4px);}
        .quiz-card.completed{border-left:4px solid #22c55e;}.quiz-card.pending{border-left:4px solid #3b82f6;}
        .quiz-card-icon{font-size:2rem;flex-shrink:0;}
        .quiz-card-body{flex:1;min-width:0;}
        .quiz-card-body h3{color:#fff;font-size:1.05rem;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:700;}
        .quiz-card-meta{display:flex;flex-wrap:wrap;gap:14px;font-size:.82rem;color:#64748b;margin-top:6px;}
        .score-pill{display:inline-flex;align-items:center;padding:4px 12px;border-radius:20px;font-size:.82rem;font-weight:700;white-space:nowrap;}
        .pill-high{background:#14532d;color:#86efac;}.pill-mid{background:#451a03;color:#fde68a;}.pill-low{background:#450a0a;color:#fca5a5;}
        .quiz-card-action{flex-shrink:0;}
        .btn-take{display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;border-radius:8px;font-weight:700;font-size:.9rem;text-decoration:none;}
        .btn-take:hover{background:#1d4ed8;}
        .btn-review{display:inline-block;padding:10px 20px;background:#334155;color:#e2e8f0;border-radius:8px;font-weight:700;font-size:.9rem;text-decoration:none;}
        .btn-review:hover{background:#475569;}
        .empty-state{text-align:center;padding:60px 20px;color:#64748b;background:#1e293b;border:1px solid #334155;border-radius:14px;}
        @media(max-width:600px){.stats-row{grid-template-columns:1fr 1fr;}.quiz-card{flex-wrap:wrap;}}
    </style>
</head>
<body>
<?php include "../includes/header.php"; ?>
<main class="page">
    <div class="hero">
        <h1>Welcome back, <?= htmlspecialchars($student_name) ?> 👋</h1>
        <p>Here are your available quizzes. Good luck!</p>
    </div>
    <div class="stats-row">
        <div class="stat-box"><div class="icon">📋</div><span>Available Quizzes</span><strong><?= $total_count ?></strong></div>
        <div class="stat-box"><div class="icon">✅</div><span>Completed</span><strong><?= $done_count ?></strong></div>
        <div class="stat-box"><div class="icon">📈</div><span>My Average</span><strong><?= $my_avg !== null ? $my_avg."%" : "—" ?></strong></div>
    </div>
    <?php if ($total_count > 0): ?>
    <div class="progress-wrap">
        <div class="progress-label"><span>Overall progress</span><span><?= $done_count ?>/<?= $total_count ?> completed</span></div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $total_count>0?round($done_count/$total_count*100):0 ?>%"></div></div>
    </div>
    <?php endif; ?>

    <?php if (empty($quizzes)): ?>
        <div class="empty-state">
            <p style="font-size:2.5rem">📭</p>
            <p>No quizzes available yet.</p>
            <p style="font-size:.85rem;margin-top:8px">Your teacher hasn't published any quizzes for your class yet.</p>
        </div>
    <?php else: ?>
        <?php $pending = array_filter($quizzes, fn($q) => !$q["submitted_at"]); ?>
        <?php if (!empty($pending)): ?>
            <div class="section-title">📝 To Do (<?= count($pending) ?>)</div>
            <div class="quiz-grid">
            <?php foreach ($pending as $q): ?>
                <div class="quiz-card pending">
                    <div class="quiz-card-icon">📋</div>
                    <div class="quiz-card-body">
                        <h3><?= htmlspecialchars($q["title"]) ?></h3>
                        <?php if ($q["description"]): ?><p style="color:#94a3b8;font-size:.85rem;margin-top:2px"><?= htmlspecialchars(mb_strimwidth($q["description"],0,100,"…")) ?></p><?php endif; ?>
                        <div class="quiz-card-meta">
                            <span>🏫 <?= htmlspecialchars($q["class_name"]) ?></span>
                            <span>❓ <?= $q["question_count"] ?> question<?= $q["question_count"]!=1?"s":"" ?></span>
                            <span>⭐ <?= $q["total_points"] ?> pts</span>
                            <?php if ($q["time_limit"]): ?><span>⏱️ <?= $q["time_limit"] ?> min</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="quiz-card-action">
                        <a href="takequiz.php?quiz_id=<?= $q["quiz_id"] ?>" class="btn-take">Start Quiz →</a>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php $completed = array_filter($quizzes, fn($q) => $q["submitted_at"]); ?>
        <?php if (!empty($completed)): ?>
            <div class="section-title">✅ Completed (<?= count($completed) ?>)</div>
            <div class="quiz-grid">
            <?php foreach ($completed as $q):
                $pct=$q["percentage"];
                $pill=$pct>=80?"pill-high":($pct>=60?"pill-mid":"pill-low");
                $emoji=$pct>=80?"🏆":($pct>=60?"📊":"📉");
            ?>
                <div class="quiz-card completed">
                    <div class="quiz-card-icon"><?= $emoji ?></div>
                    <div class="quiz-card-body">
                        <h3><?= htmlspecialchars($q["title"]) ?></h3>
                        <div class="quiz-card-meta">
                            <span>🏫 <?= htmlspecialchars($q["class_name"]) ?></span>
                            <span>❓ <?= $q["question_count"] ?> questions</span>
                            <span>📅 Submitted <?= date("M j", strtotime($q["submitted_at"])) ?></span>
                        </div>
                    </div>
                    <div class="quiz-card-action" style="display:flex;align-items:center;gap:12px;">
                        <span class="score-pill <?= $pill ?>"><?= $q["score"] ?>/<?= $q["total_points"] ?> &nbsp; <?= $pct ?>%</span>
                        <a href="quizresults.php?submission_id=<?= $q["submission_id"] ?>" class="btn-review">Review</a>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<footer style="border-top:1px solid #1e293b;text-align:center;padding:24px;color:#334155;font-size:.82rem;">&copy; 2026 LMS System</footer>
</body>
</html>
