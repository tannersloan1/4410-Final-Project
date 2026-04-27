<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] != "teacher") {
    header("Location: /lms/login.php");
    exit();
}

$teacher_id = $_SESSION["user_id"];

// Validate quiz_id
if (!isset($_GET["quiz_id"]) || !is_numeric($_GET["quiz_id"])) {
    header("Location: teacher-dash.php");
    exit();
}

$quiz_id     = intval($_GET["quiz_id"]);
$quiz_result = $conn->query(
    "SELECT q.*, c.class_name FROM QUIZZES q
     LEFT JOIN CLASSES c ON q.class_id = c.class_id
     WHERE q.quiz_id = $quiz_id AND q.teacher_id = $teacher_id"
);

if ($quiz_result->num_rows === 0) {
    header("Location: teacher-dash.php");
    exit();
}
$quiz = $quiz_result->fetch_assoc();


// CSV export for this quiz
if (isset($_GET["export_csv"])) {
    $csv_r = $conn->query(
        "SELECT si.full_name, si.email,
                ss.score, ss.total_points, ss.percentage,
                ss.started_at, ss.submitted_at
         FROM STUDENT_SUBMISSIONS ss
         JOIN STUDENT_INFO si ON ss.student_id = si.student_id
         WHERE ss.quiz_id = $quiz_id AND ss.submitted_at IS NOT NULL
         ORDER BY ss.percentage DESC"
    );

    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=\"results_" . preg_replace('/[^a-z0-9]/i','_', $quiz["title"]) . "_" . date("Y-m-d") . ".csv\"");

    $out = fopen("php://output", "w");
    fputcsv($out, ["Student Name", "Email", "Score", "Total Points", "Percentage (%)", "Started", "Submitted"]);
    while ($row = $csv_r->fetch_assoc()) {
        fputcsv($out, [
            $row["full_name"],
            $row["email"],
            $row["score"],
            $row["total_points"],
            $row["percentage"],
            $row["started_at"],
            $row["submitted_at"],
        ]);
    }
    fclose($out);
    exit();
}


// Summary stats for this quiz
$stats_r = $conn->query(
    "SELECT COUNT(*)                        AS total_submissions,
            ROUND(AVG(percentage), 1)       AS avg_score,
            ROUND(MAX(percentage), 1)       AS top_score,
            ROUND(MIN(percentage), 1)       AS low_score,
            SUM(percentage >= 80)           AS passed,
            SUM(percentage <  60)           AS struggling
     FROM STUDENT_SUBMISSIONS
     WHERE quiz_id = $quiz_id AND submitted_at IS NOT NULL"
);
$stats = $stats_r->fetch_assoc();

// Total questions and points in the quiz
$qinfo_r = $conn->query(
    "SELECT COUNT(*) AS q_count, COALESCE(SUM(points),0) AS total_pts
     FROM QUESTIONS WHERE quiz_id = $quiz_id"
);
$qinfo = $qinfo_r->fetch_assoc();


// Per-question difficulty
$difficulty_r = $conn->query(
    "SELECT qu.question_id, qu.question_text, qu.question_type, qu.points,
            COUNT(sa.answer_id)              AS attempts,
            SUM(sa.is_correct)               AS correct_count,
            ROUND(AVG(sa.is_correct)*100, 1) AS pct_correct
     FROM QUESTIONS qu
     LEFT JOIN STUDENT_ANSWERS sa ON qu.question_id = sa.question_id
     WHERE qu.quiz_id = $quiz_id
     GROUP BY qu.question_id
     ORDER BY pct_correct ASC"   // hardest first
);
$difficulty_rows = [];
while ($row = $difficulty_r->fetch_assoc()) {
    $difficulty_rows[] = $row;
}

// Per-student submission list
$students_r = $conn->query(
    "SELECT ss.submission_id, ss.score, ss.total_points, ss.percentage,
            ss.submitted_at, ss.started_at,
            si.full_name, si.email
     FROM STUDENT_SUBMISSIONS ss
     JOIN STUDENT_INFO si ON ss.student_id = si.student_id
     WHERE ss.quiz_id = $quiz_id AND ss.submitted_at IS NOT NULL
     ORDER BY ss.percentage DESC"
);
$student_rows = [];
while ($row = $students_r->fetch_assoc()) {
    $student_rows[] = $row;
}


// Individual answer breakdown
$detail_submission = null;
$detail_answers    = [];
if (isset($_GET["submission_id"]) && is_numeric($_GET["submission_id"])) {
    $sub_id = intval($_GET["submission_id"]);

    // Verify this submission belongs to a quiz owned by this teacher
    $sub_check = $conn->query(
        "SELECT ss.*, si.full_name, si.email
         FROM STUDENT_SUBMISSIONS ss
         JOIN STUDENT_INFO si ON ss.student_id = si.student_id
         JOIN QUIZZES q ON ss.quiz_id = q.quiz_id
         WHERE ss.submission_id = $sub_id AND q.teacher_id = $teacher_id AND ss.quiz_id = $quiz_id"
    );
    if ($sub_check->num_rows > 0) {
        $detail_submission = $sub_check->fetch_assoc();

        $answers_r = $conn->query(
            "SELECT sa.is_correct, sa.points_earned, sa.answer_text,
                    qu.question_text, qu.question_type, qu.points AS max_points, qu.answer AS correct_answer,
                    ac_chosen.choice_text AS chosen_text,
                    ac_correct.choice_text AS correct_choice_text
             FROM STUDENT_ANSWERS sa
             JOIN QUESTIONS qu ON sa.question_id = qu.question_id
             LEFT JOIN ANSWER_CHOICES ac_chosen  ON sa.chosen_choice_id = ac_chosen.choice_id
             LEFT JOIN ANSWER_CHOICES ac_correct ON qu.question_id = ac_correct.question_id
                                                AND ac_correct.is_correct = 1
             WHERE sa.submission_id = $sub_id
             ORDER BY qu.question_id ASC"
        );
        while ($a = $answers_r->fetch_assoc()) {
            $detail_answers[] = $a;
        }
    }
}

$has_submissions = count($student_rows) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results — <?= htmlspecialchars($quiz["title"]) ?></title>
    <link rel="stylesheet" href="../lms.css?v=10">
    <style>
        .results-page {
            max-width: 1100px;
            margin: 36px auto;
            padding: 0 20px;
        }

        .breadcrumb { font-size: 0.85rem; color: #94a3b8; margin-bottom: 18px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }

        /* Quiz header card */
        .quiz-header {
            background: #1f2a3a;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 22px 26px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }
        .quiz-header h1 { font-size: 1.5rem; color: #fff; margin-bottom: 4px; }
        .quiz-header p  { color: #94a3b8; font-size: 0.88rem; }
        .btn-csv {
            padding: 10px 18px;
            background: #0f766e;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.88rem;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-csv:hover { filter: brightness(1.15); }

        /* Stat boxes */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        .stat-box {
            background: #1f2a3a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
        }
        .stat-box .icon  { font-size: 1.3rem; margin-bottom: 8px; }
        .stat-box span   { display: block; font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
        .stat-box strong { font-size: 1.7rem; color: #fff; font-weight: 700; }

        /* Panel */
        .panel {
            background: #1f2a3a;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .panel h2 { color: #fff; font-size: 1.05rem; margin-bottom: 16px; }

        /* Student table */
        .results-table {
            width: 100%;
            border-collapse: collapse;
            background: #111827;
            border-radius: 10px;
            overflow: hidden;
        }
        .results-table th,
        .results-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #1e293b;
            color: #e2e8f0;
            font-size: 0.88rem;
        }
        .results-table th {
            background: #1e3a5f;
            color: #93c5fd;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .results-table tr:last-child td { border-bottom: none; }
        .results-table tr:hover td { background: #1a2235; cursor: pointer; }

        .rank-1 td:first-child { color: #fbbf24; font-weight: 700; }
        .rank-2 td:first-child { color: #94a3b8; font-weight: 700; }
        .rank-3 td:first-child { color: #cd7c2f; font-weight: 700; }

        .score-high { color: #86efac; font-weight: 700; }
        .score-mid  { color: #fde68a; font-weight: 700; }
        .score-low  { color: #fca5a5; font-weight: 700; }

        .btn-view {
            padding: 5px 12px;
            background: #0891b2;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-view:hover { background: #0e7490; }

        /* Score bar */
        .score-bar-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .score-bar {
            flex: 1;
            height: 8px;
            background: #1e293b;
            border-radius: 4px;
            overflow: hidden;
        }
        .score-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.4s ease;
        }
        .fill-high { background: #22c55e; }
        .fill-mid  { background: #f59e0b; }
        .fill-low  { background: #ef4444; }

        /* Difficulty table */
        .diff-table {
            width: 100%;
            border-collapse: collapse;
        }
        .diff-table th,
        .diff-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #1e293b;
            font-size: 0.86rem;
            color: #e2e8f0;
        }
        .diff-table th { color: #64748b; font-size: 0.76rem; text-transform: uppercase; }
        .diff-table tr:last-child td { border-bottom: none; }

        /* Two-column layout */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* Detail modal (inline section) */
        .detail-panel {
            background: #111827;
            border: 1px solid #3b82f6;
            border-radius: 14px;
            padding: 26px;
            margin-bottom: 24px;
        }
        .detail-panel h2 { color: #93c5fd; margin-bottom: 4px; }
        .detail-panel .sub-meta { color: #64748b; font-size: 0.85rem; margin-bottom: 20px; }

        .answer-card {
            background: #1f2a3a;
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 12px;
            border-left: 4px solid #334155;
        }
        .answer-card.correct   { border-left-color: #22c55e; }
        .answer-card.incorrect { border-left-color: #ef4444; }
        .answer-card.manual    { border-left-color: #f59e0b; }

        .answer-q { color: #e2e8f0; font-weight: 600; margin-bottom: 10px; line-height: 1.5; }
        .answer-row { display: flex; gap: 20px; flex-wrap: wrap; font-size: 0.85rem; }
        .answer-given   { color: #94a3b8; }
        .answer-correct { color: #86efac; }
        .answer-wrong   { color: #fca5a5; text-decoration: line-through; }
        .answer-pts { margin-left: auto; font-weight: 700; white-space: nowrap; }

        .badge-correct   { color: #86efac; font-size: 0.78rem; font-weight: 700; }
        .badge-incorrect { color: #fca5a5; font-size: 0.78rem; font-weight: 700; }
        .badge-manual    { color: #fde68a; font-size: 0.78rem; font-weight: 700; }

        .btn-close {
            padding: 8px 16px;
            background: #334155;
            color: #e2e8f0;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.88rem;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        .btn-close:hover { background: #475569; }

        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #64748b;
        }
        .empty-state p { margin-top: 10px; }

        @media (max-width: 800px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .two-col    { grid-template-columns: 1fr; }
        }
        @media (max-width: 500px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<?php include "../includes/header.php"; ?>

<main class="results-page">

    <div class="breadcrumb">
        <a href="teacher-dash.php">Dashboard</a> &rsaquo;
        <a href="quiz.php">Manage Quizzes</a> &rsaquo;
        Results
    </div>

    <!-- Quiz header -->
    <div class="quiz-header">
        <div>
            <h1><?= htmlspecialchars($quiz["title"]) ?></h1>
            <p>
                <?= $quiz["class_name"] ? htmlspecialchars($quiz["class_name"]) . " &bull; " : "" ?>
                <?= $qinfo["q_count"] ?> question<?= $qinfo["q_count"] != 1 ? "s" : "" ?> &bull;
                <?= $qinfo["total_pts"] ?> total points
                <?= $quiz["time_limit"] ? " &bull; " . $quiz["time_limit"] . " min limit" : "" ?>
            </p>
        </div>
        <?php if ($has_submissions): ?>
            <a href="results.php?quiz_id=<?= $quiz_id ?>&export_csv=1" class="btn-csv">
                ⬇️ Export CSV
            </a>
        <?php endif; ?>
    </div>

    <!-- Stat cards -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="icon">📝</div>
            <span>Submissions</span>
            <strong><?= $stats["total_submissions"] ?? 0 ?></strong>
        </div>
        <div class="stat-box">
            <div class="icon">📈</div>
            <span>Average</span>
            <strong><?= $stats["avg_score"] !== null ? $stats["avg_score"] . "%" : "—" ?></strong>
        </div>
        <div class="stat-box">
            <div class="icon">🏆</div>
            <span>Top Score</span>
            <strong><?= $stats["top_score"] !== null ? $stats["top_score"] . "%" : "—" ?></strong>
        </div>
        <div class="stat-box">
            <div class="icon">✅</div>
            <span>Passed (≥80%)</span>
            <strong><?= $stats["passed"] ?? 0 ?></strong>
        </div>
        <div class="stat-box">
            <div class="icon">⚠️</div>
            <span>Struggling (&lt;60%)</span>
            <strong style="color:<?= ($stats["struggling"] ?? 0) > 0 ? "#fca5a5" : "#fff" ?>">
                <?= $stats["struggling"] ?? 0 ?>
            </strong>
        </div>
    </div>

    <?php if (!$has_submissions): ?>
        <div class="panel">
            <div class="empty-state">
                <p style="font-size:2.5rem">📭</p>
                <p>No submissions yet for this quiz.</p>
                <p style="font-size:0.85rem; margin-top:8px">
                    <?= $quiz["is_published"] ? "The quiz is published — results will appear here once students submit." : "This quiz is still a draft. Publish it so students can take it." ?>
                </p>
                <?php if (!$quiz["is_published"]): ?>
                    <a href="quiz.php?toggle_publish=<?= $quiz_id ?>"
                       style="display:inline-block; margin-top:14px; padding:10px 20px; background:#16a34a; color:#fff; border-radius:8px; font-weight:700; text-decoration:none;"
                       onclick="return confirm('Publish this quiz?')">Publish Quiz</a>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>

    <!-- Individual answer breakdown (if a submission is selected) -->
    <?php if ($detail_submission): ?>
    <div class="detail-panel">
        <a href="results.php?quiz_id=<?= $quiz_id ?>" class="btn-close">← Back to all results</a>

        <h2><?= htmlspecialchars($detail_submission["full_name"]) ?>'s Answers</h2>
        <p class="sub-meta">
            <?= htmlspecialchars($detail_submission["email"]) ?> &bull;
            Score: <strong style="color:#fff"><?= $detail_submission["score"] ?>/<?= $detail_submission["total_points"] ?></strong>
            (<?= $detail_submission["percentage"] ?>%) &bull;
            Submitted <?= date("M j, Y g:ia", strtotime($detail_submission["submitted_at"])) ?>
        </p>

        <?php if (empty($detail_answers)): ?>
            <p style="color:#64748b">No answer data recorded for this submission.</p>
        <?php else: ?>
            <?php foreach ($detail_answers as $i => $ans):
                $is_manual = ($ans["question_type"] === "free_response");
                $card_class = $is_manual ? "manual" : ($ans["is_correct"] ? "correct" : "incorrect");
            ?>
            <div class="answer-card <?= $card_class ?>">
                <div class="answer-q">
                    <span style="color:#64748b; font-size:0.8rem">Q<?= $i+1 ?>.</span>
                    <?= htmlspecialchars($ans["question_text"]) ?>
                </div>
                <div class="answer-row">
                    <?php if ($ans["question_type"] === "multiple_choice"): ?>
                        <span class="answer-given">
                            Their answer:
                            <span class="<?= $ans["is_correct"] ? "answer-correct" : "answer-wrong" ?>">
                                <?= $ans["chosen_text"] ? htmlspecialchars($ans["chosen_text"]) : "<em>No answer</em>" ?>
                            </span>
                        </span>
                        <?php if (!$ans["is_correct"]): ?>
                            <span class="answer-correct">
                                Correct: <?= htmlspecialchars($ans["correct_choice_text"] ?? $ans["correct_answer"]) ?>
                            </span>
                        <?php endif; ?>

                    <?php elseif ($ans["question_type"] === "fill_in_the_blank"): ?>
                        <span class="answer-given">
                            Their answer:
                            <span class="<?= $ans["is_correct"] ? "answer-correct" : "answer-wrong" ?>">
                                <?= $ans["answer_text"] ? htmlspecialchars($ans["answer_text"]) : "<em>No answer</em>" ?>
                            </span>
                        </span>
                        <?php if (!$ans["is_correct"]): ?>
                            <span class="answer-correct">
                                Correct: <?= htmlspecialchars($ans["correct_answer"]) ?>
                            </span>
                        <?php endif; ?>

                    <?php else: ?>
                        <span class="answer-given">
                            Response: <?= $ans["answer_text"] ? htmlspecialchars($ans["answer_text"]) : "<em>No response</em>" ?>
                        </span>
                        <span class="badge-manual">⚠️ Manual grading required</span>
                    <?php endif; ?>

                    <span class="answer-pts">
                        <?php if ($is_manual): ?>
                            <span style="color:#64748b">—/<?= $ans["max_points"] ?> pts</span>
                        <?php elseif ($ans["is_correct"]): ?>
                            <span class="badge-correct">✓ +<?= $ans["points_earned"] ?> pts</span>
                        <?php else: ?>
                            <span class="badge-incorrect">✗ 0/<?= $ans["max_points"] ?> pts</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Student scores table + question difficulty side by side -->
    <div class="two-col">

        <!-- Student scores -->
        <div class="panel">
            <h2>Student Scores</h2>
            <div style="overflow-x:auto">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Student</th>
                            <th>Score</th>
                            <th>%</th>
                            <th>Time taken</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($student_rows as $rank => $s):
                        $pct = $s["percentage"];
                        $cls = $pct >= 80 ? "score-high" : ($pct >= 60 ? "score-mid" : "score-low");
                        $fill_cls = $pct >= 80 ? "fill-high" : ($pct >= 60 ? "fill-mid" : "fill-low");
                        $rank_cls = $rank === 0 ? "rank-1" : ($rank === 1 ? "rank-2" : ($rank === 2 ? "rank-3" : ""));

                        // Calculate time taken
                        $time_taken = "—";
                        if ($s["started_at"] && $s["submitted_at"]) {
                            $diff = strtotime($s["submitted_at"]) - strtotime($s["started_at"]);
                            $mins = floor($diff / 60);
                            $secs = $diff % 60;
                            $time_taken = $mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s";
                        }
                    ?>
                        <tr class="<?= $rank_cls ?>">
                            <td>#<?= $rank + 1 ?></td>
                            <td>
                                <strong><?= htmlspecialchars($s["full_name"]) ?></strong>
                                <br><small style="color:#64748b"><?= htmlspecialchars($s["email"]) ?></small>
                            </td>
                            <td><?= $s["score"] ?>/<?= $s["total_points"] ?></td>
                            <td>
                                <div class="score-bar-wrap">
                                    <div class="score-bar">
                                        <div class="score-bar-fill <?= $fill_cls ?>"
                                             style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <span class="<?= $cls ?>" style="min-width:42px"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <td style="color:#64748b; font-size:0.82rem"><?= $time_taken ?></td>
                            <td>
                                <a href="results.php?quiz_id=<?= $quiz_id ?>&submission_id=<?= $s["submission_id"] ?>"
                                   class="btn-view">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Question difficulty -->
        <div class="panel">
            <h2>Question Difficulty</h2>
            <?php if (empty($difficulty_rows)): ?>
                <p style="color:#64748b">No questions found.</p>
            <?php else: ?>
            <table class="diff-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Question</th>
                        <th>% Correct</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($difficulty_rows as $di => $dq):
                    $pct = $dq["pct_correct"];
                    $fill = $pct >= 80 ? "fill-high" : ($pct >= 50 ? "fill-mid" : "fill-low");
                    $no_data = ($dq["attempts"] == 0);
                ?>
                    <tr>
                        <td style="color:#64748b"><?= $di + 1 ?></td>
                        <td style="max-width:180px">
                            <?= htmlspecialchars(mb_strimwidth($dq["question_text"], 0, 60, "…")) ?>
                            <br>
                            <small style="color:#475569"><?= $dq["points"] ?> pt<?= $dq["points"] != 1 ? "s" : "" ?></small>
                        </td>
                        <td style="min-width:100px">
                            <?php if ($no_data): ?>
                                <span style="color:#475569">No data</span>
                            <?php else: ?>
                                <div class="score-bar-wrap">
                                    <div class="score-bar" style="min-width:60px">
                                        <div class="score-bar-fill <?= $fill ?>"
                                             style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <span style="font-size:0.82rem; color:#e2e8f0; min-width:36px"><?= $pct ?>%</span>
                                </div>
                                <small style="color:#475569"><?= $dq["correct_count"] ?>/<?= $dq["attempts"] ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div><!-- .two-col -->

    <?php endif; // has_submissions ?>

</main>

<footer>
    <p>&copy; 2026 LMS System. All rights reserved.</p>
</footer>
</body>
</html>
