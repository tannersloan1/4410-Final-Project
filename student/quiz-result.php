<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] != "student") {
    header("Location: /lms/login.php");
    exit();
}

$student_id = $_SESSION["user_id"];

if (!isset($_GET["submission_id"]) || !is_numeric($_GET["submission_id"])) {
    header("Location: student-dash.php");
    exit();
}

$sub_id = intval($_GET["submission_id"]);

// Load submission. must belong to this student
$sub_r = $conn->query(
    "SELECT ss.*, q.title AS quiz_title, q.description AS quiz_description,
            q.quiz_id, c.class_name
     FROM STUDENT_SUBMISSIONS ss
     JOIN QUIZZES q ON ss.quiz_id = q.quiz_id
     LEFT JOIN CLASSES c ON q.class_id = c.class_id
     WHERE ss.submission_id = $sub_id AND ss.student_id = $student_id"
);

if ($sub_r->num_rows === 0) {
    header("Location: student-dash.php");
    exit();
}
$sub = $sub_r->fetch_assoc();

// Load per question answers
$answers_r = $conn->query(
    "SELECT sa.is_correct, sa.points_earned, sa.answer_text,
            qu.question_text, qu.question_type, qu.points AS max_points, qu.answer AS correct_answer,
            ac_chosen.choice_text  AS chosen_text,
            ac_correct.choice_text AS correct_choice_text
     FROM STUDENT_ANSWERS sa
     JOIN QUESTIONS qu ON sa.question_id = qu.question_id
     LEFT JOIN ANSWER_CHOICES ac_chosen  ON sa.chosen_choice_id = ac_chosen.choice_id
     LEFT JOIN ANSWER_CHOICES ac_correct ON qu.question_id = ac_correct.question_id
                                        AND ac_correct.is_correct = 1
     WHERE sa.submission_id = $sub_id
     ORDER BY qu.question_id ASC"
);
$answers = [];
while ($a = $answers_r->fetch_assoc()) {
    $answers[] = $a;
}

$pct          = $sub["percentage"];
$has_manual   = !empty(array_filter($answers, fn($a) => $a["question_type"] === "free_response"));

// Grade label
if ($pct >= 90)      { $grade = "A";  $grade_color = "#86efac"; }
elseif ($pct >= 80)  { $grade = "B";  $grade_color = "#86efac"; }
elseif ($pct >= 70)  { $grade = "C";  $grade_color = "#fde68a"; }
elseif ($pct >= 60)  { $grade = "D";  $grade_color = "#fde68a"; }
else                 { $grade = "F";  $grade_color = "#fca5a5"; }

// Time taken
$time_taken = "—";
if ($sub["started_at"] && $sub["submitted_at"]) {
    $diff = strtotime($sub["submitted_at"]) - strtotime($sub["started_at"]);
    $mins = floor($diff / 60);
    $secs = $diff % 60;
    $time_taken = $mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results — <?= htmlspecialchars($sub["quiz_title"]) ?></title>
    <link rel="stylesheet" href="../lms.css?v=10">
    <style>
        .result-page {
            max-width: 760px;
            margin: 36px auto;
            padding: 0 20px 60px;
        }

        .breadcrumb { font-size: 0.85rem; color: #94a3b8; margin-bottom: 20px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }

        /* Score hero card */
        .score-hero {
            background: #1f2a3a;
            border: 1px solid #334155;
            border-radius: 18px;
            padding: 36px;
            text-align: center;
            margin-bottom: 24px;
        }
        .score-hero h1 { font-size: 1.3rem; color: #fff; margin-bottom: 6px; }
        .score-hero .class-name { color: #64748b; font-size: 0.88rem; margin-bottom: 28px; }

        .score-circle {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 6px solid;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .score-circle .pct  { font-size: 2.2rem; font-weight: 700; }
        .score-circle .grade-lbl { font-size: 0.8rem; color: #94a3b8; margin-top: 2px; }

        .score-meta {
            display: flex;
            justify-content: center;
            gap: 32px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .score-meta-item { text-align: center; }
        .score-meta-item span  { display: block; font-size: 0.78rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .score-meta-item strong { font-size: 1.3rem; color: #fff; }

        .result-message {
            font-size: 1rem;
            color: #94a3b8;
            margin-top: 8px;
        }

        /* Manual grading notice */
        .manual-notice {
            background: #451a03;
            border: 1px solid #92400e;
            border-radius: 10px;
            padding: 14px 18px;
            color: #fde68a;
            font-size: 0.88rem;
            margin-bottom: 20px;
        }

        /* Section */
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #1e293b;
        }

        /* Answer cards */
        .answer-card {
            background: #1f2a3a;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 12px;
            border-left: 4px solid #334155;
        }
        .answer-card.correct   { border-left-color: #22c55e; }
        .answer-card.incorrect { border-left-color: #ef4444; }
        .answer-card.manual    { border-left-color: #f59e0b; }
        .answer-card.skipped   { border-left-color: #475569; }

        .answer-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }
        .q-num {
            background: #1e293b;
            color: #94a3b8;
            font-weight: 700;
            font-size: 0.8rem;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .q-num.correct   { background: #052e16; color: #86efac; }
        .q-num.incorrect { background: #450a0a; color: #fca5a5; }
        .q-num.manual    { background: #451a03; color: #fde68a; }

        .q-text { color: #e2e8f0; font-weight: 600; line-height: 1.5; flex: 1; }

        .pts-badge {
            font-size: 0.8rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .pts-correct   { background: #14532d; color: #86efac; }
        .pts-incorrect { background: #450a0a; color: #fca5a5; }
        .pts-manual    { background: #334155; color: #94a3b8; }

        .answer-detail { font-size: 0.88rem; padding-left: 40px; }
        .their-answer  { color: #94a3b8; margin-bottom: 4px; }
        .their-answer .val-correct { color: #86efac; font-weight: 700; }
        .their-answer .val-wrong   { color: #fca5a5; text-decoration: line-through; }
        .their-answer .val-blank   { color: #475569; font-style: italic; }
        .correct-answer { color: #86efac; font-size: 0.85rem; }
        .free-text-response {
            background: #111827;
            border-radius: 8px;
            padding: 10px 14px;
            color: #cbd5e1;
            font-size: 0.88rem;
            margin-top: 6px;
            line-height: 1.6;
        }

        /* Back button */
        .btn-back {
            display: inline-block;
            padding: 12px 24px;
            background: #2563eb;
            color: #fff;
            border-radius: 10px;
            font-weight: 700;
            text-decoration: none;
            font-size: 0.95rem;
        }
        .btn-back:hover { background: #1d4ed8; }

        @media (max-width: 500px) {
            .score-meta { gap: 16px; }
        }
    </style>
</head>
<body>
<?php include "../includes/header.php"; ?>

<main class="result-page">

    <div class="breadcrumb">
        <a href="student-dash.php">Dashboard</a> &rsaquo; Quiz Results
    </div>

    <!-- Score hero -->
    <div class="score-hero">
        <h1><?= htmlspecialchars($sub["quiz_title"]) ?></h1>
        <p class="class-name"><?= $sub["class_name"] ? htmlspecialchars($sub["class_name"]) : "" ?></p>

        <div class="score-circle" style="border-color:<?= $grade_color ?>; color:<?= $grade_color ?>">
            <span class="pct"><?= $pct ?>%</span>
            <span class="grade-lbl">Grade <?= $grade ?></span>
        </div>

        <div class="score-meta">
            <div class="score-meta-item">
                <span>Score</span>
                <strong><?= $sub["score"] ?>/<?= $sub["total_points"] ?></strong>
            </div>
            <div class="score-meta-item">
                <span>Questions</span>
                <strong><?= count($answers) ?></strong>
            </div>
            <div class="score-meta-item">
                <span>Time Taken</span>
                <strong><?= $time_taken ?></strong>
            </div>
            <div class="score-meta-item">
                <span>Submitted</span>
                <strong><?= date("M j", strtotime($sub["submitted_at"])) ?></strong>
            </div>
        </div>

        <p class="result-message">
            <?php if ($pct >= 90): ?>
                🌟 Outstanding work! You nailed it.
            <?php elseif ($pct >= 80): ?>
                🎉 Great job! Keep it up.
            <?php elseif ($pct >= 70): ?>
                👍 Good effort — review the questions you missed below.
            <?php elseif ($pct >= 60): ?>
                📚 Not bad, but there's room to improve. Check the review below.
            <?php else: ?>
                💪 Keep studying. You can do better next time. Review your answers below.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($has_manual): ?>
    <div class="manual-notice">
        ⚠️ This quiz has free response questions that require manual grading by your teacher.
        Your score may increase once they've been reviewed.
    </div>
    <?php endif; ?>

    <!-- Per-question review -->
    <div class="section-title">Answer Review</div>

    <?php foreach ($answers as $i => $a):
        $is_manual  = ($a["question_type"] === "free_response");
        $skipped    = (!$is_manual && $a["answer_text"] === null && $a["chosen_text"] === null);

        if ($is_manual)       $card_class = "manual";
        elseif ($skipped)     $card_class = "skipped";
        elseif ($a["is_correct"]) $card_class = "correct";
        else                  $card_class = "incorrect";

        $num_class  = $is_manual ? "manual" : ($skipped ? "" : ($a["is_correct"] ? "correct" : "incorrect"));
    ?>
    <div class="answer-card <?= $card_class ?>">
        <div class="answer-header">
            <div class="q-num <?= $num_class ?>"><?= $i + 1 ?></div>
            <div class="q-text"><?= htmlspecialchars($a["question_text"]) ?></div>
            <span class="pts-badge <?= $is_manual ? "pts-manual" : ($a["is_correct"] ? "pts-correct" : "pts-incorrect") ?>">
                <?php if ($is_manual): ?>
                    pending
                <?php elseif ($a["is_correct"]): ?>
                    +<?= $a["points_earned"] ?> pts
                <?php else: ?>
                    0/<?= $a["max_points"] ?> pts
                <?php endif; ?>
            </span>
        </div>

        <div class="answer-detail">
            <?php if ($a["question_type"] === "multiple_choice"): ?>
                <p class="their-answer">
                    Your answer:
                    <?php if (!$a["chosen_text"]): ?>
                        <span class="val-blank">Not answered</span>
                    <?php elseif ($a["is_correct"]): ?>
                        <span class="val-correct">✓ <?= htmlspecialchars($a["chosen_text"]) ?></span>
                    <?php else: ?>
                        <span class="val-wrong"><?= htmlspecialchars($a["chosen_text"]) ?></span>
                    <?php endif; ?>
                </p>
                <?php if (!$a["is_correct"] && $a["correct_choice_text"]): ?>
                    <p class="correct-answer">✓ Correct answer: <?= htmlspecialchars($a["correct_choice_text"]) ?></p>
                <?php endif; ?>

            <?php elseif ($a["question_type"] === "fill_in_the_blank"): ?>
                <p class="their-answer">
                    Your answer:
                    <?php if (!$a["answer_text"] || $a["answer_text"] === ""): ?>
                        <span class="val-blank">Not answered</span>
                    <?php elseif ($a["is_correct"]): ?>
                        <span class="val-correct">✓ <?= htmlspecialchars($a["answer_text"]) ?></span>
                    <?php else: ?>
                        <span class="val-wrong"><?= htmlspecialchars($a["answer_text"]) ?></span>
                    <?php endif; ?>
                </p>
                <?php if (!$a["is_correct"]): ?>
                    <p class="correct-answer">✓ Correct answer: <?= htmlspecialchars($a["correct_answer"]) ?></p>
                <?php endif; ?>

            <?php else: ?>
                <p class="their-answer" style="color:#fde68a">⏳ Awaiting teacher review</p>
                <?php if ($a["answer_text"]): ?>
                    <div class="free-text-response"><?= nl2br(htmlspecialchars($a["answer_text"])) ?></div>
                <?php else: ?>
                    <p style="color:#475569; font-size:0.85rem; margin-top:6px; font-style:italic">No response given.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div style="margin-top:28px; text-align:center;">
        <a href="student-dash.php" class="btn-back">← Back to Dashboard</a>
    </div>

</main>

<footer>
    <p>&copy; 2025 LMS System. All rights reserved.</p>
</footer>
</body>
</html>
