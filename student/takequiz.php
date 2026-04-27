<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] != "student") {
    header("Location: /lms/login.php");
    exit();
}

$student_id = $_SESSION["user_id"];

// Validate quiz_id
if (!isset($_GET["quiz_id"]) || !is_numeric($_GET["quiz_id"])) {
    header("Location: student-dash.php");
    exit();
}

$quiz_id     = intval($_GET["quiz_id"]);

// Load the quiz. Mmust be published and student must be in the class
$quiz_r = $conn->query(
    "SELECT q.* FROM QUIZZES q
     JOIN CLASSES c ON q.class_id = c.class_id
     WHERE q.quiz_id    = $quiz_id
       AND q.is_published = 1
       AND c.student_id = $student_id
     LIMIT 1"
);
if ($quiz_r->num_rows === 0) {
    header("Location: student-dash.php");
    exit();
}
$quiz = $quiz_r->fetch_assoc();

// Block re-attempts. One submission per student per quiz
$existing = $conn->query(
    "SELECT submission_id FROM STUDENT_SUBMISSIONS
     WHERE quiz_id = $quiz_id AND student_id = $student_id AND submitted_at IS NOT NULL"
);
if ($existing->num_rows > 0) {
    $sub = $existing->fetch_assoc();
    header("Location: quiz-result.php?submission_id=" . $sub["submission_id"]);
    exit();
}

// Load all questions with their answer choices
$questions_r = $conn->query(
    "SELECT * FROM QUESTIONS WHERE quiz_id = $quiz_id ORDER BY question_id ASC"
);
$questions = [];
while ($q = $questions_r->fetch_assoc()) {
    if ($q["question_type"] === "multiple_choice") {
        $choices_r = $conn->query(
            "SELECT * FROM ANSWER_CHOICES WHERE question_id = {$q['question_id']} ORDER BY choice_order ASC"
        );
        $q["choices"] = [];
        while ($c = $choices_r->fetch_assoc()) {
            $q["choices"][] = $c;
        }
    }
    $questions[] = $q;
}

if (empty($questions)) {
    // Quiz has no questions yet
    header("Location: student-dash.php");
    exit();
}

$total_questions = count($questions);
$total_points    = array_sum(array_column($questions, "points"));

// Create or resume an in progress submission

$in_progress = $conn->query(
    "SELECT submission_id, started_at FROM STUDENT_SUBMISSIONS
     WHERE quiz_id = $quiz_id AND student_id = $student_id AND submitted_at IS NULL
     LIMIT 1"
);

if ($in_progress->num_rows > 0) {
    $submission = $in_progress->fetch_assoc();
    $submission_id = $submission["submission_id"];
    $started_at    = $submission["started_at"];
} else {
    // Start a new submission
    $conn->query(
        "INSERT INTO STUDENT_SUBMISSIONS (quiz_id, student_id, total_points)
         VALUES ($quiz_id, $student_id, $total_points)"
    );
    $submission_id = $conn->insert_id;
    $started_at    = date("Y-m-d H:i:s");
    logActivity($conn, $student_id, "student", "START", "Started quiz $quiz_id", "STUDENT_SUBMISSIONS");
}


// SUBMIT quiz
if (isset($_POST["submit_quiz"]) && isset($_POST["submission_id"])) {
    $sub_id = intval($_POST["submission_id"]);

    // Verify ownership
    $verify = $conn->query(
        "SELECT submission_id FROM STUDENT_SUBMISSIONS
         WHERE submission_id = $sub_id AND student_id = $student_id AND submitted_at IS NULL"
    );
    if ($verify->num_rows === 0) {
        header("Location: student-dash.php");
        exit();
    }

    $earned_points = 0;

    foreach ($questions as $q) {
        $q_id      = $q["question_id"];
        $q_type    = $q["question_type"];
        $q_points  = $q["points"];
        $is_correct   = 0;
        $points_earned = 0;
        $chosen_choice_id = "NULL";
        $answer_text_val  = "NULL";

        if ($q_type === "multiple_choice") {
            $post_key = "answer_" . $q_id;
            if (isset($_POST[$post_key]) && is_numeric($_POST[$post_key])) {
                $choice_id = intval($_POST[$post_key]);
                $chosen_choice_id = $choice_id;

                // Check if correct
                $check = $conn->query(
                    "SELECT is_correct FROM ANSWER_CHOICES
                     WHERE choice_id = $choice_id AND question_id = $q_id"
                );
                if ($check->num_rows > 0 && $check->fetch_assoc()["is_correct"]) {
                    $is_correct    = 1;
                    $points_earned = $q_points;
                    $earned_points += $q_points;
                }
            }
            $conn->query(
                "INSERT INTO STUDENT_ANSWERS
                    (submission_id, question_id, chosen_choice_id, is_correct, points_earned)
                 VALUES ($sub_id, $q_id, $chosen_choice_id, $is_correct, $points_earned)"
            );

        } elseif ($q_type === "fill_in_the_blank") {
            $typed = trim($_POST["answer_" . $q_id] ?? "");
            $answer_text_val = $conn->real_escape_string($typed);

            // Case insensitive match
            if (strtolower($typed) === strtolower($q["answer"])) {
                $is_correct    = 1;
                $points_earned = $q_points;
                $earned_points += $q_points;
            }
            $conn->query(
                "INSERT INTO STUDENT_ANSWERS
                    (submission_id, question_id, answer_text, is_correct, points_earned)
                 VALUES ($sub_id, $q_id, '$answer_text_val', $is_correct, $points_earned)"
            );

        } elseif ($q_type === "free_response") {
            $typed = trim($_POST["answer_" . $q_id] ?? "");
            $answer_text_val = $conn->real_escape_string($typed);
            $conn->query(
                "INSERT INTO STUDENT_ANSWERS
                    (submission_id, question_id, answer_text, is_correct, points_earned)
                 VALUES ($sub_id, $q_id, '$answer_text_val', 0, 0)"
            );
        }
    }

    // Calculate percentage
    $auto_graded_points = 0;
    foreach ($questions as $q) {
        if ($q["question_type"] !== "free_response") {
            $auto_graded_points += $q["points"];
        }
    }
    $percentage = $auto_graded_points > 0
        ? round(($earned_points / $auto_graded_points) * 100, 2)
        : 0;

    // Finalise submission
    $conn->query(
        "UPDATE STUDENT_SUBMISSIONS
         SET score=$earned_points, total_points=$auto_graded_points,
             percentage=$percentage, submitted_at=NOW()
         WHERE submission_id=$sub_id"
    );

    logActivity($conn, $student_id, "student", "SUBMIT", "Submitted quiz $quiz_id — score: $earned_points/$auto_graded_points", "STUDENT_SUBMISSIONS");

    header("Location: quiz-result.php?submission_id=$sub_id");
    exit();
}

// Time elapsed for countdown (seconds)
$elapsed   = time() - strtotime($started_at);
$remaining = $quiz["time_limit"] ? max(0, ($quiz["time_limit"] * 60) - $elapsed) : null;

$labels = ["A", "B", "C", "D"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quiz["title"]) ?></title>
    <link rel="stylesheet" href="../lms.css?v=10">
    <style>
        .quiz-take-page {
            max-width: 760px;
            margin: 36px auto;
            padding: 0 20px 60px;
        }

        /* Sticky header bar */
        .quiz-topbar {
            position: sticky;
            top: 70px; /* below nav */
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
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 400px;
        }
        .topbar-right { display: flex; align-items: center; gap: 16px; }

        /* Timer */
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
        .timer.warning { background: #451a03; color: #fde68a; border-color: #92400e; }
        .timer.danger  { background: #450a0a; color: #fca5a5; border-color: #7f1d1d; animation: pulse 1s infinite; }

        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.7} }

        /* Progress */
        .progress-pill {
            font-size: 0.82rem;
            color: #94a3b8;
            white-space: nowrap;
        }
        .progress-bar-mini {
            width: 120px;
            height: 6px;
            background: #1e293b;
            border-radius: 3px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
            margin-left: 8px;
        }
        .progress-bar-fill {
            height: 100%;
            background: #3b82f6;
            border-radius: 3px;
            transition: width 0.3s;
        }

        /* Question cards */
        .question-card {
            background: #1f2a3a;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 24px 26px;
            margin-bottom: 18px;
            transition: border-color 0.2s;
        }
        .question-card.answered { border-color: #1d4ed8; }

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
            font-size: 0.85rem;
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
            font-size: 0.78rem;
            color: #64748b;
            white-space: nowrap;
        }

        /* Multiple choice options */
        .choices { display: flex; flex-direction: column; gap: 10px; }
        .choice-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #111827;
            border: 2px solid #1e293b;
            border-radius: 10px;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
        }
        .choice-label:hover { border-color: #3b82f6; background: #0f2040; }
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
            font-size: 0.9rem;
        }
        .choice-text { color: #e2e8f0; font-size: 0.92rem; }

        /* Fill in the blank / free response */
        .answer-input {
            width: 100%;
            padding: 12px 14px;
            background: #111827;
            border: 2px solid #1e293b;
            border-radius: 10px;
            color: #fff;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .answer-input:focus { outline: none; border-color: #3b82f6; }
        .answer-textarea {
            width: 100%;
            padding: 12px 14px;
            background: #111827;
            border: 2px solid #1e293b;
            border-radius: 10px;
            color: #fff;
            font-size: 0.95rem;
            resize: vertical;
            min-height: 100px;
            transition: border-color 0.2s;
            font-family: inherit;
        }
        .answer-textarea:focus { outline: none; border-color: #3b82f6; }

        .q-type-hint {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 10px;
        }

        /* Submit bar */
        .submit-bar {
            background: #1f2a3a;
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
        .submit-bar p { color: #94a3b8; font-size: 0.88rem; }
        .answered-count { color: #fff; font-weight: 700; }

        .btn-submit {
            padding: 13px 32px;
            background: #16a34a;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
        }
        .btn-submit:hover { background: #15803d; }
        .btn-submit:disabled { background: #1e293b; color: #475569; cursor: not-allowed; }

        @media (max-width: 600px) {
            .quiz-topbar { top: 0; }
            .progress-bar-mini { display: none; }
        }
    </style>
</head>
<body>
<?php include "../includes/header.php"; ?>

<main class="quiz-take-page">

    <form method="post" id="quiz-form">
        <input type="hidden" name="submission_id" value="<?= $submission_id ?>">
        <input type="hidden" name="submit_quiz" value="1">

        <!-- Sticky top bar -->
        <div class="quiz-topbar">
            <h1>📋 <?= htmlspecialchars($quiz["title"]) ?></h1>
            <div class="topbar-right">
                <span class="progress-pill">
                    <span id="answered-count">0</span>/<?= $total_questions ?> answered
                    <span class="progress-bar-mini">
                        <span class="progress-bar-fill" id="progress-fill" style="width:0%"></span>
                    </span>
                </span>
                <?php if ($remaining !== null): ?>
                    <div class="timer" id="timer">--:--</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Questions -->
        <?php foreach ($questions as $i => $q):
            $q_id = $q["question_id"];
        ?>
        <div class="question-card" id="qcard-<?= $q_id ?>" data-qid="<?= $q_id ?>">
            <div class="q-header">
                <div class="q-num"><?= $i + 1 ?></div>
                <div class="q-text"><?= htmlspecialchars($q["question_text"]) ?></div>
                <div class="q-pts"><?= $q["points"] ?> pt<?= $q["points"] != 1 ? "s" : "" ?></div>
            </div>

            <?php if ($q["question_type"] === "multiple_choice"): ?>
                <div class="choices" id="choices-<?= $q_id ?>">
                <?php foreach ($q["choices"] as $ci => $choice): ?>
                    <label class="choice-label">
                        <input type="radio" name="answer_<?= $q_id ?>" value="<?= $choice['choice_id'] ?>"
                               onchange="markAnswered(<?= $q_id ?>)">
                        <span class="choice-letter"><?= $labels[$ci] ?? ($ci+1) ?></span>
                        <span class="choice-text"><?= htmlspecialchars($choice["choice_text"]) ?></span>
                    </label>
                <?php endforeach; ?>
                </div>

            <?php elseif ($q["question_type"] === "fill_in_the_blank"): ?>
                <p class="q-type-hint">Type your answer in the box below.</p>
                <input type="text" class="answer-input" name="answer_<?= $q_id ?>"
                       placeholder="Your answer..."
                       oninput="markAnswered(<?= $q_id ?>)">

            <?php elseif ($q["question_type"] === "free_response"): ?>
                <p class="q-type-hint">Write your response below. This question will be reviewed by your teacher.</p>
                <textarea class="answer-textarea" name="answer_<?= $q_id ?>"
                          placeholder="Write your response here..."
                          oninput="markAnswered(<?= $q_id ?>)"></textarea>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <!-- Submit bar -->
        <div class="submit-bar">
            <p>
                <span class="answered-count"><span id="submit-answered">0</span>/<?= $total_questions ?></span>
                questions answered.
                <?php if ($total_questions > 0): ?>
                    Make sure to review your answers before submitting.
                <?php endif; ?>
            </p>
            <button type="submit" class="btn-submit" id="submit-btn"
                    onclick="return confirmSubmit()">
                Submit Quiz
            </button>
        </div>

    </form>

</main>

<footer>
    <p>&copy; 2026 LMS System. All rights reserved.</p>
</footer>

<script>
const totalQuestions = <?= $total_questions ?>;
const answeredSet    = new Set();

function markAnswered(qid) {
    answeredSet.add(qid);
    document.getElementById("qcard-" + qid).classList.add("answered");
    updateProgress();
}

function updateProgress() {
    const count = answeredSet.size;
    document.getElementById("answered-count").textContent  = count;
    document.getElementById("submit-answered").textContent = count;
    const pct = totalQuestions > 0 ? (count / totalQuestions * 100) : 0;
    document.getElementById("progress-fill").style.width   = pct + "%";
}

function confirmSubmit() {
    const unanswered = totalQuestions - answeredSet.size;
    if (unanswered > 0) {
        return confirm(
            "You have " + unanswered + " unanswered question" +
            (unanswered > 1 ? "s" : "") +
            ". Submit anyway?"
        );
    }
    return confirm("Submit your quiz? You cannot change your answers after submitting.");
}

// Listen to textarea/input changes for fill in and free response
document.querySelectorAll(".answer-input, .answer-textarea").forEach(el => {
    el.addEventListener("input", () => {
        const name  = el.getAttribute("name"); // "answer_123"
        const qid   = parseInt(name.split("_")[1]);
        if (el.value.trim() !== "") {
            markAnswered(qid);
        } else {
            answeredSet.delete(qid);
            document.getElementById("qcard-" + qid).classList.remove("answered");
            updateProgress();
        }
    });
});

<?php if ($remaining !== null): ?>
// Countdown timer
let secondsLeft = <?= $remaining ?>;
const timerEl   = document.getElementById("timer");

function formatTime(s) {
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return String(m).padStart(2,"0") + ":" + String(sec).padStart(2,"0");
}

function tick() {
    timerEl.textContent = formatTime(secondsLeft);
    if (secondsLeft <= 60)       timerEl.className = "timer danger";
    else if (secondsLeft <= 300) timerEl.className = "timer warning";

    if (secondsLeft <= 0) {
        // Auto submit when time runs out
        document.getElementById("quiz-form").submit();
        return;
    }
    secondsLeft--;
    setTimeout(tick, 1000);
}
tick();
<?php endif; ?>
</script>

</body>
</html>
