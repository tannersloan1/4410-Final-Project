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
$edit_quiz  = null; // holds quiz data when editing


// Fetch this teacher's classes for the "Assign to class" dropdown
$classes_result = $conn->query(
    "SELECT DISTINCT class_id, class_name FROM CLASSES
     WHERE teacher_id = $teacher_id ORDER BY class_name"
);


// CREATE quiz
if (isset($_POST["create_quiz"])) {
    $title       = $conn->real_escape_string(trim($_POST["title"]));
    $description = $conn->real_escape_string(trim($_POST["description"]));
    $class_id    = ($_POST["class_id"] != "0" && is_numeric($_POST["class_id"])) ? intval($_POST["class_id"]) : "NULL";
    $time_limit  = $_POST["time_limit"] !== "" ? intval($_POST["time_limit"]) : "NULL";

    if ($title === "") {
        $feedback = ["type" => "error", "msg" => "Title is required."];
    } else {
        $sql = "INSERT INTO QUIZZES (teacher_id, class_id, title, description, time_limit)
                VALUES ($teacher_id, $class_id, '$title', '$description', $time_limit)";
        if ($conn->query($sql)) {
            $new_id = $conn->insert_id;
            logActivity($conn, $teacher_id, "teacher", "CREATE", "Created quiz: $title", "QUIZZES");
            // Redirect to question builder for the new quiz
            header("Location: add_questions.php?quiz_id=$new_id&new=1");
            exit();
        } else {
            $feedback = ["type" => "error", "msg" => "Database error: " . $conn->error];
        }
    }
}

// UPDATE quiz
if (isset($_POST["update_quiz"])) {
    $quiz_id     = intval($_POST["quiz_id"]);
    $title       = $conn->real_escape_string(trim($_POST["title"]));
    $description = $conn->real_escape_string(trim($_POST["description"]));
    $class_id    = ($_POST["class_id"] != "0" && is_numeric($_POST["class_id"])) ? intval($_POST["class_id"]) : "NULL";
    $time_limit  = $_POST["time_limit"] !== "" ? intval($_POST["time_limit"]) : "NULL";

    // Make sure this quiz belongs to the logged-in teacher
    $check = $conn->query("SELECT quiz_id FROM QUIZZES WHERE quiz_id = $quiz_id AND teacher_id = $teacher_id");
    if ($check->num_rows === 0) {
        $feedback = ["type" => "error", "msg" => "Quiz not found or access denied."];
    } elseif ($title === "") {
        $feedback = ["type" => "error", "msg" => "Title is required."];
    } else {
        $sql = "UPDATE QUIZZES
                SET title='$title', description='$description',
                    class_id=$class_id, time_limit=$time_limit
                WHERE quiz_id=$quiz_id AND teacher_id=$teacher_id";
        if ($conn->query($sql)) {
            logActivity($conn, $teacher_id, "teacher", "UPDATE", "Updated quiz: $title", "QUIZZES");
            $feedback = ["type" => "success", "msg" => "Quiz updated successfully."];
        } else {
            $feedback = ["type" => "error", "msg" => "Database error: " . $conn->error];
        }
    }
}


// TOGGLE PUBLISH
if (isset($_GET["toggle_publish"])) {
    $quiz_id = intval($_GET["toggle_publish"]);
    $check = $conn->query("SELECT is_published FROM QUIZZES WHERE quiz_id=$quiz_id AND teacher_id=$teacher_id");
    if ($check->num_rows > 0) {
        $row       = $check->fetch_assoc();
        $new_state = $row["is_published"] ? 0 : 1;
        $conn->query("UPDATE QUIZZES SET is_published=$new_state WHERE quiz_id=$quiz_id");
        logActivity($conn, $teacher_id, "teacher", "UPDATE", "Toggled publish on quiz $quiz_id", "QUIZZES");
    }
    header("Location: quiz.php");
    exit();
}


// DELETE quiz
if (isset($_GET["delete"])) {
    $quiz_id = intval($_GET["delete"]);
    $check = $conn->query("SELECT title FROM QUIZZES WHERE quiz_id=$quiz_id AND teacher_id=$teacher_id");
    if ($check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $conn->query("DELETE FROM QUIZZES WHERE quiz_id=$quiz_id AND teacher_id=$teacher_id");
        logActivity($conn, $teacher_id, "teacher", "DELETE", "Deleted quiz: " . $row["title"], "QUIZZES");
        $feedback = ["type" => "success", "msg" => "Quiz \"" . htmlspecialchars($row["title"]) . "\" deleted."];
    } else {
        $feedback = ["type" => "error", "msg" => "Quiz not found or access denied."];
    }
}


// LOAD quiz for editing
if (isset($_GET["edit"])) {
    $quiz_id    = intval($_GET["edit"]);
    $edit_result = $conn->query(
        "SELECT * FROM QUIZZES WHERE quiz_id=$quiz_id AND teacher_id=$teacher_id"
    );
    if ($edit_result->num_rows > 0) {
        $edit_quiz = $edit_result->fetch_assoc();
    } else {
        $feedback = ["type" => "error", "msg" => "Quiz not found or access denied."];
    }
}


// Fetch all quizzes for this teacher
$quizzes_result = $conn->query(
    "SELECT q.*,
            c.class_name,
            COUNT(DISTINCT qu.question_id)   AS question_count,
            COUNT(DISTINCT ss.submission_id) AS submission_count
     FROM QUIZZES q
     LEFT JOIN CLASSES c           ON q.class_id      = c.class_id
     LEFT JOIN QUESTIONS qu        ON q.quiz_id        = qu.quiz_id
     LEFT JOIN STUDENT_SUBMISSIONS ss ON q.quiz_id    = ss.quiz_id
     WHERE q.teacher_id = $teacher_id
     GROUP BY q.quiz_id
     ORDER BY q.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quizzes</title>
    <link rel="stylesheet" href="../lms.css?v=10">
    <style>
        /* ---- page-level additions (quiz.php only) ---- */
        .quiz-page {
            max-width: 1100px;
            margin: 36px auto;
            padding: 0 20px;
        }

        .quiz-hero { margin-bottom: 24px; }
        .quiz-hero h1 { font-size: 1.9rem; color: #fff; margin-bottom: 6px; }
        .quiz-hero p  { color: #cbd5e1; }

        /* breadcrumb */
        .breadcrumb { font-size: 0.85rem; color: #94a3b8; margin-bottom: 18px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }

        /* feedback banner */
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
        .form-card h2 { color: #fff; margin-bottom: 20px; font-size: 1.2rem; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-grid .full { grid-column: 1 / -1; }

        .form-card label {
            display: block;
            color: #e2e8f0;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }

        .form-card input,
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
        }
        .form-card input:focus,
        .form-card select:focus,
        .form-card textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .form-card textarea { resize: vertical; min-height: 80px; }

        .form-hint { font-size: 0.8rem; color: #64748b; margin-top: 4px; }

        .btn-row {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-primary {
            padding: 11px 22px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.95rem;
        }
        .btn-primary:hover { background: #1d4ed8; }

        .btn-secondary {
            padding: 11px 22px;
            background: #334155;
            color: #e2e8f0;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-secondary:hover { background: #475569; }

        /* quiz table */
        .quiz-table {
            width: 100%;
            border-collapse: collapse;
            background: #111827;
            border-radius: 10px;
            overflow: hidden;
        }
        .quiz-table th,
        .quiz-table td {
            padding: 13px 15px;
            text-align: left;
            border-bottom: 1px solid #1e293b;
            color: #e2e8f0;
            font-size: 0.92rem;
        }
        .quiz-table th { background: #1e3a5f; color: #93c5fd; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .quiz-table tr:last-child td { border-bottom: none; }
        .quiz-table tr:hover td { background: #1e293b; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .badge-published  { background: #14532d; color: #86efac; }
        .badge-draft      { background: #334155; color: #94a3b8; }

        .action-btns { display: flex; gap: 5px; flex-wrap: wrap; }

        .btn-xs {
            padding: 6px 11px;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            color: #fff;
        }
        .btn-edit     { background: #d97706; }
        .btn-edit:hover { background: #b45309; }
        .btn-questions { background: #7c3aed; }
        .btn-questions:hover { background: #6d28d9; }
        .btn-results  { background: #0891b2; }
        .btn-results:hover { background: #0e7490; }
        .btn-delete   { background: #dc2626; }
        .btn-delete:hover { background: #b91c1c; }
        .btn-publish  { background: #16a34a; }
        .btn-publish:hover  { background: #15803d; }
        .btn-unpublish { background: #475569; }
        .btn-unpublish:hover { background: #334155; }

        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #64748b;
        }
        .empty-state p { margin-top: 10px; }

        @media (max-width: 700px) {
            .form-grid { grid-template-columns: 1fr; }
            .quiz-table th:nth-child(4),
            .quiz-table td:nth-child(4),
            .quiz-table th:nth-child(5),
            .quiz-table td:nth-child(5) { display: none; }
        }
    </style>
</head>
<body>
<?php include "../includes/header.php"; ?>

<main class="quiz-page">

    <div class="breadcrumb">
        <a href="teacher-dash.php">Dashboard</a> &rsaquo; Manage Quizzes
    </div>

    <div class="quiz-hero">
        <h1>📋 Manage Quizzes</h1>
        <p>Create, edit, and publish quizzes for your classes.</p>
    </div>

    <?php if ($feedback): ?>
        <div class="feedback <?= $feedback["type"] ?>">
            <?= htmlspecialchars($feedback["msg"]) ?>
        </div>
    <?php endif; ?>


        //CREATE / EDIT FORM
    <div class="form-card">
        <h2><?= $edit_quiz ? "✏️ Edit Quiz" : "➕ Create New Quiz" ?></h2>

        <form method="post">
            <?php if ($edit_quiz): ?>
                <input type="hidden" name="quiz_id" value="<?= $edit_quiz["quiz_id"] ?>">
            <?php endif; ?>

            <div class="form-grid">

                <div class="full">
                    <label for="title">Quiz Title <span style="color:#ef4444">*</span></label>
                    <input type="text" id="title" name="title" required
                           placeholder="e.g. Chapter 3 — Networking Basics"
                           value="<?= $edit_quiz ? htmlspecialchars($edit_quiz["title"]) : "" ?>">
                </div>

                <div>
                    <label for="class_id">Assign to Class</label>
                    <select id="class_id" name="class_id">
                        <option value="0">— No class assigned —</option>
                        <?php
                        if ($classes_result && $classes_result->num_rows > 0):
                            $classes_result->data_seek(0);
                            while ($cls = $classes_result->fetch_assoc()):
                                $sel = ($edit_quiz && $edit_quiz["class_id"] == $cls["class_id"]) ? "selected" : "";
                        ?>
                            <option value="<?= $cls["class_id"] ?>" <?= $sel ?>>
                                <?= htmlspecialchars($cls["class_name"]) ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>

                <div>
                    <label for="time_limit">Time Limit (minutes)</label>
                    <input type="number" id="time_limit" name="time_limit" min="1" max="300"
                           placeholder="Leave blank for no limit"
                           value="<?= ($edit_quiz && $edit_quiz["time_limit"]) ? $edit_quiz["time_limit"] : "" ?>">
                    <p class="form-hint">Leave blank for unlimited time.</p>
                </div>

                <div class="full">
                    <label for="description">Description (optional)</label>
                    <textarea id="description" name="description"
                              placeholder="Instructions or notes for students..."><?= $edit_quiz ? htmlspecialchars($edit_quiz["description"]) : "" ?></textarea>
                </div>

            </div><!-- .form-grid -->

            <div class="btn-row">
                <?php if ($edit_quiz): ?>
                    <button type="submit" name="update_quiz" class="btn-primary">💾 Save Changes</button>
                    <a href="quiz.php" class="btn-secondary">Cancel</a>
                <?php else: ?>
                    <button type="submit" name="create_quiz" class="btn-primary">Create Quiz & Add Questions →</button>
                <?php endif; ?>
            </div>
        </form>
    </div>


        //QUIZ LIST
    <div class="form-card">
        <h2>My Quizzes</h2>

        <?php if ($quizzes_result && $quizzes_result->num_rows > 0): ?>
        <div style="overflow-x:auto">
            <table class="quiz-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Class</th>
                        <th>Questions</th>
                        <th>Submissions</th>
                        <th>Time limit</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $row_num = 1; while ($quiz = $quizzes_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row_num++ ?></td>
                        <td><strong><?= htmlspecialchars($quiz["title"]) ?></strong>
                            <?php if ($quiz["description"]): ?>
                                <br><small style="color:#64748b"><?= htmlspecialchars(mb_strimwidth($quiz["description"], 0, 60, "…")) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $quiz["class_name"] ? htmlspecialchars($quiz["class_name"]) : "<span style='color:#475569'>—</span>" ?></td>
                        <td><?= $quiz["question_count"] ?></td>
                        <td><?= $quiz["submission_count"] ?></td>
                        <td><?= $quiz["time_limit"] ? $quiz["time_limit"] . " min" : "Unlimited" ?></td>
                        <td>
                            <?php if ($quiz["is_published"]): ?>
                                <span class="badge badge-published">Published</span>
                            <?php else: ?>
                                <span class="badge badge-draft">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date("M j, Y", strtotime($quiz["created_at"])) ?></td>
                        <td>
                            <div class="action-btns">
                                <a href="quiz.php?edit=<?= $quiz["quiz_id"] ?>" class="btn-xs btn-edit">Edit</a>
                                <a href="add_questions.php?quiz_id=<?= $quiz["quiz_id"] ?>" class="btn-xs btn-questions">Questions</a>
                                <a href="results.php?quiz_id=<?= $quiz["quiz_id"] ?>" class="btn-xs btn-results">Results</a>
                                <?php if ($quiz["is_published"]): ?>
                                    <a href="quiz.php?toggle_publish=<?= $quiz["quiz_id"] ?>" class="btn-xs btn-unpublish"
                                       onclick="return confirm('Unpublish this quiz? Students won\'t be able to take it.')">Unpublish</a>
                                <?php else: ?>
                                    <a href="quiz.php?toggle_publish=<?= $quiz["quiz_id"] ?>" class="btn-xs btn-publish"
                                       onclick="return confirm('Publish this quiz? Students will be able to take it.')">Publish</a>
                                <?php endif; ?>
                                <a href="quiz.php?delete=<?= $quiz["quiz_id"] ?>" class="btn-xs btn-delete"
                                   onclick="return confirm('Delete \'<?= addslashes($quiz["title"]) ?>\'? This cannot be undone and will also delete all questions and student submissions.')">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
            <div class="empty-state">
                <div style="font-size:2.5rem">📭</div>
                <p>No quizzes yet. Create your first one above!</p>
            </div>
        <?php endif; ?>
    </div>

</main>

<footer>
    <p>&copy; 2026 LMS System. All rights reserved.</p>
</footer>

</body>
</html>
