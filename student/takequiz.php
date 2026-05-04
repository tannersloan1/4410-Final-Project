<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] != "student") {
    header("Location: /lms/login.php");
    exit();
}

$student_id = $_SESSION["user_id"];

if (!isset($_GET["quiz_id"]) || !is_numeric($_GET["quiz_id"])) {
    header("Location: student-dash.php");
    exit();
}

$quiz_id = intval($_GET["quiz_id"]);

// Verify student is enrolled in the class this quiz belongs to
$quiz_r = $conn->query(
    "SELECT q.* FROM QUIZZES q
     JOIN CLASS_ENROLLMENTS ce ON ce.class_id = q.class_id AND ce.student_id = $student_id
     WHERE q.quiz_id = $quiz_id AND q.is_published = 1
     LIMIT 1"
);

if ($quiz_r->num_rows === 0) {
    header("Location: student-dash.php");
    exit();
}

$quiz = $quiz_r->fetch_assoc();

// Block reattempts
$existing = $conn->query(
    "SELECT submission_id FROM STUDENT_SUBMISSIONS
     WHERE quiz_id = $quiz_id AND student_id = $student_id AND submitted_at IS NOT NULL"
);

if ($existing->num_rows > 0) {
    header("Location: quiz-result.php?submission_id=" . $existing->fetch_assoc()["submission_id"]);
    exit();
}

// Load questions + choices
$questions_r = $conn->query("SELECT * FROM QUESTIONS WHERE quiz_id = $quiz_id ORDER BY question_id ASC");
$questions = [];

while ($q = $questions_r->fetch_assoc()) {
    if ($q["question_type"] === "multiple_choice") {
        $cr = $conn->query("SELECT * FROM ANSWER_CHOICES WHERE question_id = {$q['question_id']} ORDER BY choice_order ASC");
        $q["choices"] = [];
        while ($c = $cr->fetch_assoc()) {
            $q["choices"][] = $c;
        }
    }
    $questions[] = $q;
}

if (empty($questions)) {
    header("Location: student-dash.php");
    exit();
}

$total_questions = count($questions);
$total_points    = array_sum(array_column($questions, "points"));

// Create or resume submission
$in_prog = $conn->query(
    "SELECT submission_id, started_at FROM STUDENT_SUBMISSIONS
     WHERE quiz_id = $quiz_id AND student_id = $student_id AND submitted_at IS NULL LIMIT 1"
);

if ($in_prog->num_rows > 0) {
    $s             = $in_prog->fetch_assoc();
    $submission_id = $s["submission_id"];
    $started_at    = $s["started_at"];
} else {
    $conn->query("INSERT INTO STUDENT_SUBMISSIONS (quiz_id, student_id, total_points) VALUES ($quiz_id, $student_id, $total_points)");
    $submission_id = $conn->insert_id;
    $started_at    = date("Y-m-d H:i:s");
    logActivity($conn, $student_id, "student", "START", "Started quiz $quiz_id", "STUDENT_SUBMISSIONS");
}

// Handle submission
if (isset($_POST["submit_quiz"], $_POST["submission_id"])) {
    $sub_id = intval($_POST["submission_id"]);
    $verify = $conn->query(
        "SELECT submission_id FROM STUDENT_SUBMISSIONS
         WHERE submission_id = $sub_id AND student_id = $student_id AND submitted_at IS NULL"
    );

    if ($verify->num_rows === 0) {
        header("Location: student-dash.php");
        exit();
    }

    $earned     = 0;
    $auto_total = 0;

    foreach ($questions as $q) {
        $qid        = $q["question_id"];
        $qtype      = $q["question_type"];
        $qpts       = $q["points"];
        $is_correct = 0;
        $pts_earned = 0;
        $chosen_id  = "NULL";
        $ans_text   = "NULL";

        if ($qtype === "multiple_choice") {
            $auto_total += $qpts;

            if (isset($_POST["answer_$qid"]) && is_numeric($_POST["answer_$qid"])) {
                $cid       = intval($_POST["answer_$qid"]);
                $chosen_id = $cid;
                $cr        = $conn->query("SELECT is_correct FROM ANSWER_CHOICES WHERE choice_id = $cid AND question_id = $qid");

                if ($cr->num_rows > 0 && $cr->fetch_assoc()["is_correct"]) {
                    $is_correct = 1;
                    $pts_earned = $qpts;
                    $earned    += $qpts;
                }
            }

            $conn->query(
                "INSERT INTO STUDENT_ANSWERS (submission_id, question_id, chosen_choice_id, is_correct, points_earned)
                 VALUES ($sub_id, $qid, $chosen_id, $is_correct, $pts_earned)"
            );

        } elseif ($qtype === "fill_in_the_blank") {
            $auto_total += $qpts;
            $typed       = trim($_POST["answer_$qid"] ?? "");
            $ans_text    = "'" . $conn->real_escape_string($typed) . "'";

            if (strtolower($typed) === strtolower($q["answer"])) {
                $is_correct = 1;
                $pts_earned = $qpts;
                $earned    += $qpts;
            }

            $conn->query(
                "INSERT INTO STUDENT_ANSWERS (submission_id, question_id, answer_text, is_correct, points_earned)
                 VALUES ($sub_id, $qid, $ans_text, $is_correct, $pts_earned)"
            );

        } elseif ($qtype === "free_response") {
            $typed    = trim($_POST["answer_$qid"] ?? "");
            $ans_text = "'" . $conn->real_escape_string($typed) . "'";

            $conn->query(
                "INSERT INTO STUDENT_ANSWERS (submission_id, question_id, answer_text, is_correct, points_earned)
                 VALUES ($sub_id, $qid, $ans_text, 0, 0)"
            );
        }
    }

    $pct = $auto_total > 0 ? round(($earned / $auto_total) * 100, 2) : 0;
    $conn->query(
        "UPDATE STUDENT_SUBMISSIONS
         SET score = $earned, total_points = $auto_total, percentage = $pct, submitted_at = NOW()
         WHERE submission_id = $sub_id"
    );
    logActivity($conn, $student_id, "student", "SUBMIT", "Submitted quiz $quiz_id score:$earned/$auto_total", "STUDENT_SUBMISSIONS");
    header("Location: quiz-result.php?submission_id=$sub_id");
    exit();
}

$elapsed   = time() - strtotime($started_at);
$remaining = $quiz["time_limit"] ? max(0, ($quiz["time_limit"] * 60) - $elapsed) : null;
$labels    = ["A", "B", "C", "D"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quiz["title"]) ?></title>
    <link rel="stylesheet" href="../lms.css?v=10">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Nunito", "Segoe UI", sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            margin: 0;
        }

        .quiz-take-page {
            max-width: 760px;
            margin: 36px auto;
            padding: 0 20px 60px;
        }

        .quiz-topbar {
            position: sticky;
            top: 70px;
            z-index: 100;
            background: #0f172a;
            border-bottom: 1px solid #1e293b;
            padding: 12px 0;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .quiz-topbar h1 {
            font-size: 1.1rem;
            color: #fff;
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 400px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .timer {
            font-size: 1rem;
            font-weight: 700;
            padding: 7px 16px;
            border-radius: 8px;
            background: #1e293b;
            color: #fff;
            min-width: 80px;
            text-align: center;
            border: 1px solid #334155;
        }

        .timer.warning {
            background: #451a03;
            color: #fde68a;
            border-color: #92400e;
        }

        .timer.danger {
            background: #450a0a;
            color: #fca5a5;
            border-color: #7f1d1d;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: .7; }
        }

        .progress-pill {
            font-size: .82rem;
            color: #94a3b8;
            white-space: nowrap;
        }

        .bar-mini {
            width: 120px;
            height: 6px;
            background: #1e293b;
            border-radius: 3px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
            margin-left: 8px;
        }

        .bar-fill {
            height: 100%;
            background: #3b82f6;
            border-radius: 3px;
            transition: width .3s;
        }

        .question-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 24px 26px;
            margin-bottom: 18px;
            transition: border-color .2s;
        }

        .question-card.answered {
            border-color: #1d4ed8;
        }

        .q-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .q-num {
            background: #1e3a5f;
            color: #93c5fd;
            font-weight: 700;
            font-size: .85rem;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .q-text {
            color: #e2e8f0;
            font-size: 1rem;
            font-weight: 600;
            line-height: 1.55;
            flex: 1;
        }

        .q-pts {
            font-size: .78rem;
            color: #64748b;
            white-space: nowrap;
        }

        .choices {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .choice-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #111827;
            border: 2px solid #1e293b;
            border-radius: 10px;
            cursor: pointer;
            transition: border-color .15s, background .15s;
        }

        .choice-label:hover {
            border-color: #3b82f6;
            background: #0f2040;
        }

        .choice-label:has(input:checked) {
            border-color: #3b82f6;
            background: #0f2040;
        }

        .choice-label input[type="radio"] {
            accent-color: #3b82f6;
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .choice-letter {
            font-weight: 700;
            color: #64748b;
            min-width: 20px;
            font-size: .9rem;
        }

        .choice-text {
            color: #e2e8f0;
            font-size: .92rem;
        }

        .answer-input {
            width: 100%;
            padding: 12px 14px;
            background: #111827;
            border: 2px solid #1e293b;
            border-radius: 10px;
            color: #fff;
            font-size: .95rem;
            transition: border-color .2s;
            box-sizing: border-box;
            font-family: inherit;
        }

        .answer-input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .answer-textarea {
            width: 100%;
            padding: 12px 14px;
            background: #111827;
            border: 2px solid #1e293b;
            border-radius: 10px;
            color: #fff;
            font-size: .95rem;
            resize: vertical;
            min-height: 100px;
            transition: border-color .2s;
            font-family: inherit;
            box-sizing: border-box;
        }

        .answer-textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .q-type-hint {
            font-size: .8rem;
            color: #64748b;
            margin-bottom: 10px;
        }

        .submit-bar {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 28px;
        }

        .submit-bar p {
            color: #94a3b8;
            font-size: .88rem;
        }

        .btn-submit {
            padding: 13px 32px;
            background: #16a34a;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            font-family: inherit;
        }

        .btn-submit:hover {
            background: #15803d;
        }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<main class="quiz-take-page">
    <form method="post" id="quiz-form">
        <input type="hidden" name="submission_id" value="<?= $submission_id ?>">
        <input type="hidden" name="submit_quiz" value="1">

        <div class="quiz-topbar">
            <h1>📋 <?= htmlspecialchars($quiz["title"]) ?></h1>
            <div class="topbar-right">
                <span class="progress-pill">
                    <span id="ac">0</span>/<?= $total_questions ?> answered
                    <span class="bar-mini">
                        <span class="bar-fill" id="bf" style="width:0%"></span>
                    </span>
                </span>
                <?php if ($remaining !== null): ?>
                    <div class="timer" id="timer">--:--</div>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($questions as $i => $q): $qid = $q["question_id"]; ?>
            <div class="question-card" id="qcard-<?= $qid ?>">
                <div class="q-header">
                    <div class="q-num"><?= $i + 1 ?></div>
                    <div class="q-text"><?= htmlspecialchars($q["question_text"]) ?></div>
                    <div class="q-pts"><?= $q["points"] ?> pt<?= $q["points"] != 1 ? "s" : "" ?></div>
                </div>

                <?php if ($q["question_type"] === "multiple_choice"): ?>
                    <div class="choices">
                        <?php foreach ($q["choices"] as $ci => $ch): ?>
                            <label class="choice-label">
                                <input type="radio" name="answer_<?= $qid ?>" value="<?= $ch["choice_id"] ?>" onchange="mark(<?= $qid ?>)">
                                <span class="choice-letter"><?= $labels[$ci] ?? ($ci + 1) ?></span>
                                <span class="choice-text"><?= htmlspecialchars($ch["choice_text"]) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($q["question_type"] === "fill_in_the_blank"): ?>
                    <p class="q-type-hint">Type your answer below.</p>
                    <input type="text" class="answer-input" name="answer_<?= $qid ?>" placeholder="Your answer..." oninput="mark(<?= $qid ?>)">

                <?php else: ?>
                    <p class="q-type-hint">Write your response below. Your teacher will review it.</p>
                    <textarea class="answer-textarea" name="answer_<?= $qid ?>" placeholder="Write your response here..." oninput="mark(<?= $qid ?>)"></textarea>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="submit-bar">
            <p><span id="sa">0</span>/<?= $total_questions ?> questions answered.</p>
            <button type="submit" class="btn-submit" onclick="return confirmSub()">Submit Quiz</button>
        </div>
    </form>
</main>

<footer style="border-top:1px solid #1e293b;text-align:center;padding:24px;color:#334155;font-size:.82rem;">
    &copy; 2026 LMS System
</footer>

<script>
    const totalQ = <?= $total_questions ?>, done = new Set();

    function mark(qid) {
        done.add(qid);
        document.getElementById("qcard-" + qid).classList.add("answered");
        document.getElementById("ac").textContent = done.size;
        document.getElementById("sa").textContent = done.size;
        document.getElementById("bf").style.width = (done.size / totalQ * 100) + "%";
    }

    function confirmSub() {
        const left = totalQ - done.size;
        if (left > 0) return confirm("You have " + left + " unanswered question" + (left > 1 ? "s" : "") + ". Submit anyway?");
        return confirm("Submit your quiz? You cannot change your answers after submitting.");
    }

    document.querySelectorAll(".answer-input, .answer-textarea").forEach(el => {
        el.addEventListener("input", () => {
            if (el.value.trim()) mark(parseInt(el.name.split("_")[1]));
        });
    });

    <?php if ($remaining !== null): ?>
        let secs = <?= $remaining ?>;
        const te = document.getElementById("timer");

        function tick() {
            const m = Math.floor(secs / 60), s = secs % 60;
            te.textContent = String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
            te.className = secs <= 60 ? "timer danger" : secs <= 300 ? "timer warning" : "timer";
            if (secs <= 0) { document.getElementById("quiz-form").submit(); return; }
            secs--;
            setTimeout(tick, 1000);
        }

        tick();
    <?php endif; ?>
</script>

</body>
</html>
