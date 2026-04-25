<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] != "teacher") {
    header("Location: /lms/login.php");
    exit();
}

$teacher_id = $_SESSION["user_id"];
$feedback   = "";


// Validate quiz_id and confirm it belongs to this teacher
if (!isset($_GET["quiz_id"]) || !is_numeric($_GET["quiz_id"])) {
    header("Location: quiz.php");
    exit();
}

$quiz_id     = intval($_GET["quiz_id"]);
$quiz_result = $conn->query(
    "SELECT q.*, c.class_name FROM QUIZZES q
     LEFT JOIN CLASSES c ON q.class_id = c.class_id
     WHERE q.quiz_id = $quiz_id AND q.teacher_id = $teacher_id"
);

if ($quiz_result->num_rows === 0) {
    header("Location: quiz.php");
    exit();
}
$quiz     = $quiz_result->fetch_assoc();
$is_new   = isset($_GET["new"]);  // came straight from creating the quiz

// ADD question
if (isset($_POST["add_question"])) {
    $question_text = $conn->real_escape_string(trim($_POST["question_text"]));
    $question_type = $_POST["question_type"];
    $points        = max(1, intval($_POST["points"]));
    $allowed_types = ["multiple_choice", "fill_in_the_blank", "free_response"];

    if ($question_text === "") {
        $feedback = ["type" => "error", "msg" => "Question text cannot be empty."];

    } elseif (!in_array($question_type, $allowed_types)) {
        $feedback = ["type" => "error", "msg" => "Invalid question type."];

    } elseif ($question_type === "multiple_choice") {
        // Validate choices
        $choices     = $_POST["choices"] ?? [];
        $correct_idx = isset($_POST["correct_choice"]) ? intval($_POST["correct_choice"]) : -1;
        $choices     = array_map("trim", $choices);

        $filled = array_filter($choices, fn($c) => $c !== "");
        if (count($filled) < 2) {
            $feedback = ["type" => "error", "msg" => "Please provide at least 2 answer choices."];
        } elseif ($correct_idx < 0 || $correct_idx > 3 || trim($choices[$correct_idx] ?? "") === "") {
            $feedback = ["type" => "error", "msg" => "Please select a valid correct answer."];
        } else {
            // Insert question — answer field holds the correct choice text for quick grading
            $correct_text = $conn->real_escape_string($choices[$correct_idx]);
            $sql = "INSERT INTO QUESTIONS (quiz_id, question_text, question_type, answer, points)
                    VALUES ($quiz_id, '$question_text', 'multiple_choice', '$correct_text', $points)";
            if ($conn->query($sql)) {
                $question_id = $conn->insert_id;
                // Insert all choices
                foreach ($choices as $order => $choice_text) {
                    if (trim($choice_text) === "") continue;
                    $choice_text_esc = $conn->real_escape_string($choice_text);
                    $is_correct      = ($order === $correct_idx) ? 1 : 0;
                    $choice_order    = $order + 1;
                    $conn->query(
                        "INSERT INTO ANSWER_CHOICES (question_id, choice_text, is_correct, choice_order)
                         VALUES ($question_id, '$choice_text_esc', $is_correct, $choice_order)"
                    );
                }
                logActivity($conn, $teacher_id, "teacher", "CREATE", "Added MC question to quiz $quiz_id", "QUESTIONS");
                $feedback = ["type" => "success", "msg" => "Multiple choice question added."];
            } else {
                $feedback = ["type" => "error", "msg" => "Database error: " . $conn->error];
            }
        }

    } else {
        // fill_in_the_blank or free_response
        $answer = $conn->real_escape_string(trim($_POST["answer_text"] ?? ""));
        if ($question_type === "fill_in_the_blank" && $answer === "") {
            $feedback = ["type" => "error", "msg" => "Please enter the correct answer."];
        } else {
            $sql = "INSERT INTO QUESTIONS (quiz_id, question_text, question_type, answer, points)
                    VALUES ($quiz_id, '$question_text', '$question_type', '$answer', $points)";
            if ($conn->query($sql)) {
                logActivity($conn, $teacher_id, "teacher", "CREATE", "Added question to quiz $quiz_id", "QUESTIONS");
                $feedback = ["type" => "success", "msg" => "Question added."];
            } else {
                $feedback = ["type" => "error", "msg" => "Database error: " . $conn->error];
            }
        }
    }
}

// DELETE question
if (isset($_GET["delete_q"])) {
    $question_id = intval($_GET["delete_q"]);
    // Confirm the question belongs to this teacher's quiz
    $check = $conn->query(
        "SELECT q.question_id FROM QUESTIONS q
         JOIN QUIZZES qz ON q.quiz_id = qz.quiz_id
         WHERE q.question_id = $question_id AND qz.teacher_id = $teacher_id AND qz.quiz_id = $quiz_id"
    );
    if ($check->num_rows > 0) {
        $conn->query("DELETE FROM QUESTIONS WHERE question_id = $question_id");
        logActivity($conn, $teacher_id, "teacher", "DELETE", "Deleted question $question_id from quiz $quiz_id", "QUESTIONS");
        $feedback = ["type" => "success", "msg" => "Question deleted."];
    } else {
        $feedback = ["type" => "error", "msg" => "Question not found or access denied."];
    }
    header("Location: add_questions.php?quiz_id=$quiz_id");
    exit();
}


// Fetch existing questions for this quiz
$questions_result = $conn->query(
    "SELECT * FROM QUESTIONS WHERE quiz_id = $quiz_id ORDER BY question_id ASC"
);
$questions = [];
while ($row = $questions_result->fetch_assoc()) {
    // Also fetch choices for MC questions
    if ($row["question_type"] === "multiple_choice") {
        $choices_result = $conn->query(
            "SELECT * FROM ANSWER_CHOICES WHERE question_id = {$row['question_id']} ORDER BY choice_order ASC"
        );
        $row["choices"] = [];
        while ($c = $choices_result->fetch_assoc()) {
            $row["choices"][] = $c;
        }
    }
    $questions[] = $row;
}

$question_count  = count($questions);
$total_points    = array_sum(array_column($questions, "points"));
$labels          = ["A", "B", "C", "D"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Builder — <?= htmlspecialchars($quiz["title"]) ?></title>
    <link rel="stylesheet" href="../lms.css?v=10">
    <style>
        .qb-page {
            max-width: 1000px;
            margin: 36px auto;
            padding: 0 20px;
        }

        /* breadcrumb */
        .breadcrumb { font-size: 0.85rem; color: #94a3b8; margin-bottom: 18px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }

        /* welcome banner (shown only on first creation) */
        .welcome-banner {
            background: linear-gradient(135deg, #1e3a5f, #1f2a3a);
            border: 1px solid #3b82f6;
            border-radius: 14px;
            padding: 22px 26px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .welcome-banner .icon { font-size: 2rem; }
        .welcome-banner h2 { color: #93c5fd; margin-bottom: 4px; }
        .welcome-banner p  { color: #cbd5e1; font-size: 0.9rem; }

        /* quiz meta bar */
        .quiz-meta {
            background: #1f2a3a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 18px 22px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .quiz-meta h1 { font-size: 1.4rem; color: #fff; }
        .quiz-meta p  { color: #94a3b8; font-size: 0.88rem; margin-top: 3px; }
        .meta-stats { display: flex; gap: 20px; }
        .meta-stat { text-align: center; }
        .meta-stat strong { display: block; font-size: 1.5rem; color: #fff; }
        .meta-stat span   { font-size: 0.78rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }

        /* feedback */
        .feedback {
            padding: 13px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .feedback.success { background: #14532d; color: #86efac; border: 1px solid #166534; }
        .feedback.error   { background: #450a0a; color: #fca5a5; border: 1px solid #7f1d1d; }

        /* form card */
        .form-card {
            background: #1f2a3a;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 28px;
            margin-bottom: 28px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
        }
        .form-card h2 { color: #fff; margin-bottom: 20px; font-size: 1.15rem; }

        .form-card label {
            display: block;
            color: #e2e8f0;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
        .form-card input[type="text"],
        .form-card input[type="number"],
        .form-card select,
        .form-card textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #475569;
            border-radius: 8px;
            background: #0f172a;
            color: #fff;
            font-size: 0.95rem;
            transition: border-color 0.2s;
            margin-bottom: 14px;
        }
        .form-card input:focus,
        .form-card select:focus,
        .form-card textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .form-card textarea { resize: vertical; min-height: 80px; }

        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
        }

        /* type tabs */
        .type-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .type-tab {
            padding: 9px 18px;
            border-radius: 8px;
            border: 2px solid #334155;
            background: #0f172a;
            color: #94a3b8;
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .type-tab:hover { border-color: #3b82f6; color: #93c5fd; }
        .type-tab.active { border-color: #3b82f6; background: #1e3a5f; color: #93c5fd; }

        /* answer choices grid */
        .choices-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }
        .choice-row {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 10px 14px;
            transition: border-color 0.2s;
        }
        .choice-row:has(input[type="radio"]:checked) {
            border-color: #22c55e;
            background: #052e16;
        }
        .choice-label {
            font-weight: 700;
            color: #64748b;
            min-width: 20px;
            font-size: 0.9rem;
        }
        .choice-row input[type="text"] {
            flex: 1;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 0.9rem;
            padding: 0;
            margin: 0;
        }
        .choice-row input[type="text"]:focus { outline: none; }
        .choice-row input[type="radio"] { accent-color: #22c55e; width: 16px; height: 16px; cursor: pointer; }

        .choices-hint {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 14px;
        }

        /* section that changes based on question type */
        .type-section { display: none; }
        .type-section.visible { display: block; }

        .btn-primary {
            padding: 11px 24px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.95rem;
        }
        .btn-primary:hover { background: #1d4ed8; }

        /* question list */
        .q-list { display: flex; flex-direction: column; gap: 14px; }

        .q-card {
            background: #111827;
            border: 1px solid #1e293b;
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }
        .q-number {
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
        .q-body { flex: 1; }
        .q-text { color: #e2e8f0; font-weight: 600; margin-bottom: 8px; line-height: 1.5; }
        .q-meta { font-size: 0.8rem; color: #64748b; margin-bottom: 10px; }
        .q-meta span { margin-right: 14px; }

        .q-choices { display: flex; flex-direction: column; gap: 5px; }
        .q-choice {
            font-size: 0.85rem;
            padding: 5px 10px;
            border-radius: 6px;
            color: #94a3b8;
            background: #1e293b;
        }
        .q-choice.correct {
            background: #052e16;
            color: #86efac;
            font-weight: 600;
        }
        .q-choice.correct::after { content: " ✓"; }

        .q-answer {
            font-size: 0.85rem;
            color: #86efac;
            background: #052e16;
            padding: 5px 10px;
            border-radius: 6px;
            display: inline-block;
        }

        .q-actions { flex-shrink: 0; }
        .btn-del {
            padding: 6px 12px;
            background: #7f1d1d;
            color: #fca5a5;
            border: 1px solid #991b1b;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-del:hover { background: #dc2626; color: #fff; }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        .empty-state .icon { font-size: 2.5rem; margin-bottom: 10px; }

        /* done button area */
        .done-bar {
            background: #1f2a3a;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 40px;
        }
        .done-bar p { color: #94a3b8; font-size: 0.9rem; }
        .btn-done {
            padding: 12px 28px;
            background: #16a34a;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-block;
        }
        .btn-done:hover { background: #15803d; }
        .btn-back {
            padding: 12px 20px;
            background: #334155;
            color: #e2e8f0;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-block;
        }
        .btn-back:hover { background: #475569; }

        @media (max-width: 600px) {
            .choices-grid { grid-template-columns: 1fr; }
            .form-row     { grid-template-columns: 1fr; }
            .quiz-meta    { flex-direction: column; }
        }
    </style>
</head>
<body>
<?php include "../includes/header.php"; ?>

<main class="qb-page">

    <div class="breadcrumb">
        <a href="teacher-dash.php">Dashboard</a> &rsaquo;
        <a href="quiz.php">Manage Quizzes</a> &rsaquo;
        Question Builder
    </div>

    <?php if ($is_new): ?>
    <div class="welcome-banner">
        <div class="icon">🎉</div>
        <div>
            <h2>Quiz created! Now add your questions.</h2>
            <p>Your quiz is saved as a draft. Add questions below, then publish it when you're ready.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quiz meta bar -->
    <div class="quiz-meta">
        <div>
            <h1><?= htmlspecialchars($quiz["title"]) ?></h1>
            <p>
                <?= $quiz["class_name"] ? htmlspecialchars($quiz["class_name"]) : "No class assigned" ?>
                <?= $quiz["time_limit"] ? " &bull; " . $quiz["time_limit"] . " min limit" : "" ?>
                &bull; <span style="color:<?= $quiz['is_published'] ? '#86efac' : '#f59e0b' ?>">
                    <?= $quiz["is_published"] ? "Published" : "Draft" ?>
                </span>
            </p>
        </div>
        <div class="meta-stats">
            <div class="meta-stat">
                <strong><?= $question_count ?></strong>
                <span>Questions</span>
            </div>
            <div class="meta-stat">
                <strong><?= $total_points ?></strong>
                <span>Total pts</span>
            </div>
        </div>
    </div>

    <?php if ($feedback): ?>
        <div class="feedback <?= $feedback["type"] ?>">
            <?= htmlspecialchars($feedback["msg"]) ?>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         ADD QUESTION FORM
         ============================================================ -->
    <div class="form-card">
        <h2>➕ Add a Question</h2>

        <form method="post" id="question-form">

            <!-- Question text -->
            <label for="question_text">Question</label>
            <textarea id="question_text" name="question_text"
                      placeholder="e.g. Which of the following is NOT a valid IP address class?"
                      required></textarea>

            <!-- Type + points row -->
            <div class="form-row">
                <div>
                    <label>Question Type</label>
                    <div class="type-tabs">
                        <button type="button" class="type-tab active" data-type="multiple_choice">
                            Multiple Choice
                        </button>
                        <button type="button" class="type-tab" data-type="fill_in_the_blank">
                            Fill in the Blank
                        </button>
                        <button type="button" class="type-tab" data-type="free_response">
                            Free Response
                        </button>
                    </div>
                    <input type="hidden" name="question_type" id="question_type" value="multiple_choice">
                </div>
                <div>
                    <label for="points">Points</label>
                    <input type="number" id="points" name="points" min="1" max="100" value="1">
                </div>
            </div>

            <!-- ---- MULTIPLE CHOICE section ---- -->
            <div class="type-section visible" id="section-multiple_choice">
                <label>Answer Choices <span style="color:#ef4444">*</span></label>
                <p class="choices-hint">Fill in the choices and click the circle next to the correct answer.</p>
                <div class="choices-grid">
                    <?php foreach (["A","B","C","D"] as $i => $letter): ?>
                    <div class="choice-row">
                        <span class="choice-label"><?= $letter ?></span>
                        <input type="text" name="choices[<?= $i ?>]"
                               placeholder="Choice <?= $letter ?>">
                        <input type="radio" name="correct_choice" value="<?= $i ?>"
                               title="Mark as correct answer"
                               <?= $i === 0 ? "checked" : "" ?>>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ---- FILL IN THE BLANK section ---- -->
            <div class="type-section" id="section-fill_in_the_blank">
                <label for="answer_fitb">Correct Answer <span style="color:#ef4444">*</span></label>
                <input type="text" id="answer_fitb" placeholder="The exact answer students must enter"
                       oninput="document.getElementById('answer_text').value = this.value">
                <p class="choices-hint" style="margin-top:-10px">Grading is case-insensitive.</p>
            </div>

            <!-- ---- FREE RESPONSE section ---- -->
            <div class="type-section" id="section-free_response">
                <p style="color:#f59e0b; font-size:0.88rem; padding:10px 14px; background:#451a03; border-radius:8px; border:1px solid #92400e; margin-bottom:14px;">
                    ⚠️ Free response questions are not auto-graded. You will need to review and score them manually.
                </p>
                <label for="answer_fr">Model Answer (optional — for your reference only)</label>
                <textarea id="answer_fr" placeholder="Enter a sample or ideal answer..."
                          oninput="document.getElementById('answer_text').value = this.value"></textarea>
            </div>

            <!-- Hidden field that actually gets submitted for answer text -->
            <input type="hidden" name="answer_text" id="answer_text">

            <button type="submit" name="add_question" class="btn-primary">Add Question</button>
        </form>
    </div>


        //QUESTION LIST
    <div class="form-card">
        <h2>Questions (<?= $question_count ?>)</h2>

        <?php if (empty($questions)): ?>
            <div class="empty-state">
                <div class="icon">❓</div>
                <p>No questions yet. Add your first one above.</p>
            </div>
        <?php else: ?>
            <div class="q-list">
            <?php foreach ($questions as $num => $q): ?>
                <div class="q-card">
                    <div class="q-number"><?= $num + 1 ?></div>
                    <div class="q-body">
                        <div class="q-text"><?= htmlspecialchars($q["question_text"]) ?></div>
                        <div class="q-meta">
                            <span><?= ucwords(str_replace("_", " ", $q["question_type"])) ?></span>
                            <span><?= $q["points"] ?> pt<?= $q["points"] != 1 ? "s" : "" ?></span>
                            <?php if (!$q["auto_graded"]): ?>
                                <span style="color:#f59e0b">Manual grading</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($q["question_type"] === "multiple_choice" && !empty($q["choices"])): ?>
                            <div class="q-choices">
                            <?php foreach ($q["choices"] as $ci => $choice): ?>
                                <div class="q-choice <?= $choice["is_correct"] ? "correct" : "" ?>">
                                    <?= $labels[$ci] ?? ($ci + 1) ?>. <?= htmlspecialchars($choice["choice_text"]) ?>
                                </div>
                            <?php endforeach; ?>
                            </div>

                        <?php elseif ($q["question_type"] === "fill_in_the_blank"): ?>
                            <div>
                                <span style="color:#64748b; font-size:0.82rem">Answer: </span>
                                <span class="q-answer"><?= htmlspecialchars($q["answer"]) ?></span>
                            </div>

                        <?php elseif ($q["question_type"] === "free_response"): ?>
                            <?php if ($q["answer"]): ?>
                            <div>
                                <span style="color:#64748b; font-size:0.82rem">Model answer: </span>
                                <span style="color:#cbd5e1; font-size:0.85rem"><?= htmlspecialchars($q["answer"]) ?></span>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="q-actions">
                        <a href="add_questions.php?quiz_id=<?= $quiz_id ?>&delete_q=<?= $q["question_id"] ?>"
                           class="btn-del"
                           onclick="return confirm('Delete this question? This cannot be undone.')">Delete</a>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Done / back bar -->
    <div class="done-bar">
        <div>
            <p>
                <?php if ($question_count === 0): ?>
                    Add at least one question before publishing.
                <?php elseif (!$quiz["is_published"]): ?>
                    Ready? Go to quiz management to publish this quiz.
                <?php else: ?>
                    This quiz is live. Students can take it now.
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="quiz.php" class="btn-back">← Back to Quizzes</a>
            <?php if ($question_count > 0 && !$quiz["is_published"]): ?>
                <a href="quiz.php?toggle_publish=<?= $quiz_id ?>"
                   class="btn-done"
                   onclick="return confirm('Publish this quiz now? Students will be able to see and take it.')">
                    Publish Quiz
                </a>
            <?php endif; ?>
        </div>
    </div>

</main>

<footer>
    <p>&copy; 2026 LMS System. All rights reserved.</p>
</footer>

<script>
//Type tab switching
const tabs     = document.querySelectorAll(".type-tab");
const sections = document.querySelectorAll(".type-section");
const typeInput = document.getElementById("question_type");
const answerInput = document.getElementById("answer_text");

tabs.forEach(tab => {
    tab.addEventListener("click", () => {
        const type = tab.dataset.type;

        tabs.forEach(t => t.classList.remove("active"));
        tab.classList.add("active");

        sections.forEach(s => s.classList.remove("visible"));
        document.getElementById("section-" + type).classList.add("visible");

        typeInput.value = type;
        answerInput.value = "";

        // Clear fill in and free-response helper inputs
        const fitb = document.getElementById("answer_fitb");
        const fr   = document.getElementById("answer_fr");
        if (fitb) fitb.value = "";
        if (fr)   fr.value   = "";
    });
});

//Keep answer_text in sync for MC
//Highlight choice row when radio is selected
document.querySelectorAll('.choice-row input[type="radio"]').forEach(radio => {
    radio.addEventListener("change", () => {
        document.querySelectorAll(".choice-row").forEach(row => row.style.borderColor = "");
    });
});

// Clear form after successful submission
<?php if ($feedback && $feedback["type"] === "success"): ?>
document.getElementById("question-form").reset();
// Reset to multiple choice tab
tabs.forEach(t => t.classList.remove("active"));
tabs[0].classList.add("active");
sections.forEach(s => s.classList.remove("visible"));
document.getElementById("section-multiple_choice").classList.add("visible");
typeInput.value = "multiple_choice";
<?php endif; ?>
</script>

</body>
</html>
