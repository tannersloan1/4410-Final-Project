<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] != "teacher") {
    header("Location: /lms/login.php"); exit();
}
$teacher_id = $_SESSION["user_id"];
$feedback   = null;

// CREATE class
if (isset($_POST["create_class"])) {
    $name = $conn->real_escape_string(trim($_POST["class_name"]));
    if ($name === "") {
        $feedback = ["type"=>"error","msg"=>"Class name cannot be empty."];
    } else {
        $r = $conn->query("INSERT INTO CLASSES (teacher_id, class_name) VALUES ($teacher_id, '$name')");
        if ($r) {
            logActivity($conn, $teacher_id, "teacher", "CREATE", "Created class: $name", "CLASSES");
            $feedback = ["type"=>"success","msg"=>"Class \"$name\" created."];
        } else {
            $feedback = ["type"=>"error","msg"=>"Class already exists or database error."];
        }
    }
}

// Enroll student
if (isset($_POST["enrol_student"])) {
    $email    = $conn->real_escape_string(trim($_POST["student_email"]));
    $class_id = intval($_POST["class_id_enrol"]);

    // Verify class belongs to this teacher
    $cc = $conn->query("SELECT class_id, class_name FROM CLASSES WHERE class_id=$class_id AND teacher_id=$teacher_id");
    if ($cc->num_rows === 0) {
        $feedback = ["type"=>"error","msg"=>"Class not found."];
    } else {
        $class_row = $cc->fetch_assoc();
        $sr = $conn->query("SELECT student_id FROM STUDENT_INFO WHERE email='$email'");
        if ($sr->num_rows === 0) {
            $feedback = ["type"=>"error","msg"=>"No student found with that email."];
        } else {
            $sid = $sr->fetch_assoc()["student_id"];
            $er = $conn->query("INSERT INTO CLASS_ENROLMENTS (class_id, student_id) VALUES ($class_id, $sid)");
            if ($er) {
                logActivity($conn, $teacher_id, "teacher", "CREATE", "Enrolled student $sid in class $class_id", "CLASS_ENROLMENTS");
                $feedback = ["type"=>"success","msg"=>"Student enrolled in \"{$class_row['class_name']}\" successfully."];
            } else {
                $feedback = ["type"=>"error","msg"=>"Student is already enrolled in that class."];
            }
        }
    }
}

// REMOVE student from class
if (isset($_GET["remove"]) && is_numeric($_GET["remove"])) {
    $eid = intval($_GET["remove"]);
    $conn->query("DELETE ce FROM CLASS_ENROLMENTS ce JOIN CLASSES c ON ce.class_id=c.class_id WHERE ce.enrolment_id=$eid AND c.teacher_id=$teacher_id");
    logActivity($conn, $teacher_id, "teacher", "DELETE", "Removed enrolment $eid", "CLASS_ENROLMENTS");
    header("Location: manage-classes.php"); exit();
}

// DELETE class
if (isset($_GET["delete_class"]) && is_numeric($_GET["delete_class"])) {
    $cid = intval($_GET["delete_class"]);
    $conn->query("DELETE FROM CLASSES WHERE class_id=$cid AND teacher_id=$teacher_id");
    logActivity($conn, $teacher_id, "teacher", "DELETE", "Deleted class $cid", "CLASSES");
    header("Location: manage-classes.php"); exit();
}

// Fetch classes + enrolments
$classes_r = $conn->query(
    "SELECT c.class_id, c.class_name,
            COUNT(ce.enrolment_id) AS student_count
     FROM CLASSES c
     LEFT JOIN CLASS_ENROLMENTS ce ON c.class_id = ce.class_id
     WHERE c.teacher_id = $teacher_id
     GROUP BY c.class_id ORDER BY c.class_name"
);
$classes = [];
while ($row = $classes_r->fetch_assoc()) $classes[] = $row;

// Fetch enrolments per class
$enrolments = [];
if (!empty($classes)) {
    $cids = implode(",", array_column($classes, "class_id"));
    $er = $conn->query(
        "SELECT ce.enrolment_id, ce.class_id, si.full_name, si.email
         FROM CLASS_ENROLMENTS ce
         JOIN STUDENT_INFO si ON ce.student_id = si.student_id
         WHERE ce.class_id IN ($cids)
         ORDER BY si.full_name"
    );
    while ($row = $er->fetch_assoc()) $enrolments[$row["class_id"]][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Manage Classes</title>
    <link rel="stylesheet" href="../lms.css?v=10">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body{font-family:"Nunito","Segoe UI",sans-serif;background:#0f172a;color:#f1f5f9;margin:0;}
        .page{max-width:1000px;margin:36px auto;padding:0 24px 80px;}
        .breadcrumb{font-size:.85rem;color:#64748b;margin-bottom:20px;}.breadcrumb a{color:#3b82f6;text-decoration:none;}
        .hero{margin-bottom:28px;}.hero h1{font-size:1.7rem;font-weight:800;color:#f8fafc;margin-bottom:6px;}.hero p{color:#64748b;}
        .alert{padding:12px 16px;border-radius:10px;font-size:.88rem;font-weight:700;margin-bottom:20px;}
        .alert-success{background:#052e16;color:#86efac;border:1px solid #14532d;}
        .alert-error{background:#450a0a;color:#fca5a5;border:1px solid #7f1d1d;}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;}
        .card{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:24px;}
        .card h2{font-size:1rem;font-weight:800;color:#f8fafc;margin-bottom:6px;}
        .card .sub{font-size:.85rem;color:#64748b;margin-bottom:20px;}
        .field{margin-bottom:14px;}.field label{display:block;font-size:.85rem;font-weight:700;color:#cbd5e1;margin-bottom:6px;}
        .field input,.field select{width:100%;padding:10px 13px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:.92rem;font-family:inherit;transition:border-color .2s;box-sizing:border-box;}
        .field input:focus,.field select:focus{outline:none;border-color:#3b82f6;}
        .field input::placeholder{color:#475569;}
        .btn-submit{padding:10px 22px;background:#3b82f6;color:#fff;border:none;border-radius:8px;font-size:.92rem;font-weight:700;cursor:pointer;font-family:inherit;}
        .btn-submit:hover{background:#2563eb;}
        .btn-submit.green{background:#16a34a;}.btn-submit.green:hover{background:#15803d;}
        .class-panel{background:#1e293b;border:1px solid #334155;border-radius:14px;margin-bottom:14px;overflow:hidden;}
        .class-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;cursor:pointer;user-select:none;}
        .class-header:hover{background:#263347;}
        .class-left{display:flex;align-items:center;gap:12px;}
        .class-title{font-size:1rem;font-weight:800;color:#f1f5f9;}
        .class-count{font-size:.8rem;color:#64748b;}
        .class-right{display:flex;align-items:center;gap:8px;}
        .btn-xs{padding:5px 11px;border:none;border-radius:6px;font-size:.78rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block;color:#fff;}
        .btn-danger{background:#7f1d1d;color:#fca5a5;}.btn-danger:hover{background:#dc2626;color:#fff;}
        .chevron{color:#475569;font-size:.8rem;transition:transform .2s;display:inline-block;}
        .chevron.open{transform:rotate(180deg);}
        .class-body{display:none;border-top:1px solid #334155;}
        .class-body.open{display:block;}
        .st-table{width:100%;border-collapse:collapse;}
        .st-table th,.st-table td{padding:10px 20px;text-align:left;border-bottom:1px solid #1e293b;font-size:.88rem;color:#e2e8f0;}
        .st-table th{color:#64748b;font-size:.76rem;text-transform:uppercase;}
        .st-table tr:last-child td{border-bottom:none;}
        .empty-class{padding:20px;color:#475569;font-size:.88rem;font-style:italic;}
        @media(max-width:700px){.two-col{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<?php include "../includes/header.php"; ?>
<main class="page">
    <div class="breadcrumb"><a href="teacher-dash.php">Dashboard</a> › Manage Classes</div>
    <div class="hero"><h1>🏫 Manage Classes</h1><p>Create classes and enroll students so they can see your quizzes.</p></div>

    <?php if ($feedback): ?>
        <div class="alert alert-<?= $feedback["type"] ?>"><?= htmlspecialchars($feedback["msg"]) ?></div>
    <?php endif; ?>

    <div class="two-col">
        <div class="card">
            <h2>Create a new class</h2>
            <p class="sub">Give your class a name like "IT 101" or "Period 3 Biology".</p>
            <form method="POST">
                <div class="field"><label>Class name</label><input type="text" name="class_name" placeholder="e.g. IT 101 - Spring 2026" required></div>
                <button type="submit" name="create_class" class="btn-submit">Create Class</button>
            </form>
        </div>
        <div class="card">
            <h2>Enroll a student</h2>
            <p class="sub">Enter the student's email and pick which class to add them to.</p>
            <form method="POST">
                <div class="field"><label>Student email</label><input type="email" name="student_email" placeholder="student@example.com" required></div>
                <div class="field">
                    <label>Class</label>
                    <select name="class_id_enrol" required>
                        <option value="">— Select a class —</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c["class_id"] ?>"><?= htmlspecialchars($c["class_name"]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="enrol_student" class="btn-submit green">Enroll Student</button>
            </form>
        </div>
    </div>

    <h2 style="font-size:1rem;font-weight:800;color:#f8fafc;margin-bottom:14px;">Your Classes (<?= count($classes) ?>)</h2>

    <?php if (empty($classes)): ?>
        <div class="card" style="text-align:center;padding:40px;color:#475569;">
            <p style="font-size:2rem">🏫</p>
            <p style="margin-top:10px">No classes yet. Create one above.</p>
        </div>
    <?php else: ?>
        <?php foreach ($classes as $c):
            $cid = $c["class_id"];
            $students = $enrolments[$cid] ?? [];
        ?>
        <div class="class-panel">
            <div class="class-header" onclick="toggle(<?= $cid ?>)">
                <div class="class-left">
                    <span style="font-size:1.2rem">📚</span>
                    <span class="class-title"><?= htmlspecialchars($c["class_name"]) ?></span>
                    <span class="class-count"><?= count($students) ?> student<?= count($students)!=1?"s":"" ?></span>
                </div>
                <div class="class-right">
                    <a href="manage-classes.php?delete_class=<?= $cid ?>" class="btn-xs btn-danger"
                       onclick="return confirm('Delete this entire class? Students will be unenrolled.')">Delete class</a>
                    <span class="chevron" id="chev-<?= $cid ?>">▼</span>
                </div>
            </div>
            <div class="class-body" id="body-<?= $cid ?>">
                <?php if (empty($students)): ?>
                    <p class="empty-class">No students enrolled yet. Use the form above to add some.</p>
                <?php else: ?>
                    <table class="st-table">
                        <thead><tr><th>Name</th><th>Email</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?= $s["full_name"] ? htmlspecialchars($s["full_name"]) : "<span style='color:#475569'>No name</span>" ?></td>
                                <td style="color:#64748b"><?= htmlspecialchars($s["email"]) ?></td>
                                <td><a href="manage-classes.php?remove=<?= $s["enrolment_id"] ?>" class="btn-xs btn-danger" onclick="return confirm('Remove this student?')">Remove</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<footer style="border-top:1px solid #1e293b;text-align:center;padding:24px;color:#334155;font-size:.82rem;">&copy; 2026 LMS System</footer>
<script>
function toggle(id) {
    document.getElementById("body-"+id).classList.toggle("open");
    document.getElementById("chev-"+id).classList.toggle("open");
}
</script>
</body>
</html>
