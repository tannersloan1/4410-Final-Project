<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if ($_SESSION["role"] != "admin") {
    header("Location: /lms/login.php"); exit();
}

$feedback = null;

// Fetch teachers for dropdown
$teachers_r = $conn->query("SELECT teacher_id, full_name FROM TEACHER_INFO ORDER BY full_name");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $class_name = $conn->real_escape_string(trim($_POST["class-name"]));
    $teacher_id = intval($_POST["teacher"]);
    $limit      = max(1, intval($_POST["limit"]));

    if ($class_name === "") {
        $feedback = ["type"=>"error","msg"=>"Class name is required."];
    } else {
        $sql = $conn->prepare("INSERT INTO CLASSES (teacher_id, class_name, student_limit) VALUES (?,?,?)");
        $sql->bind_param("isi", $teacher_id, $class_name, $limit);
        if ($sql->execute()) {
            logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "CREATE",
                "Admin created class: $class_name (teacher: $teacher_id, limit: $limit)", "CLASSES");
            $feedback = ["type"=>"success","msg"=>"Class \"$class_name\" created successfully."];
        } else {
            $feedback = ["type"=>"error","msg"=>"Database error: ".$conn->error];
        }
    }
}

// Fetch all classes with teacher names and enrollment counts
$classes_r = $conn->query(
    "SELECT c.class_id, c.class_name, c.student_limit, c.created_at,
            ti.full_name AS teacher_name,
            COUNT(ce.enrollment_id) AS enrolled
     FROM CLASSES c
     JOIN TEACHER_INFO ti ON c.teacher_id = ti.teacher_id
     LEFT JOIN CLASS_ENROLLMENTS ce ON c.class_id = ce.class_id
     GROUP BY c.class_id
     ORDER BY c.class_name"
);
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
        .hero{margin-bottom:28px;}.hero h1{font-size:1.7rem;font-weight:800;color:#f8fafc;margin-bottom:4px;}.hero p{color:#64748b;}
        .alert{padding:12px 16px;border-radius:10px;font-size:.88rem;font-weight:700;margin-bottom:20px;}
        .alert-success{background:#052e16;color:#86efac;border:1px solid #14532d;}
        .alert-error{background:#450a0a;color:#fca5a5;border:1px solid #7f1d1d;}
        .two-col{display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start;}
        .card{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:24px;}
        .card h2{font-size:1rem;font-weight:800;color:#f8fafc;margin-bottom:6px;}
        .card .sub{font-size:.85rem;color:#64748b;margin-bottom:20px;}
        .field{margin-bottom:14px;}.field label{display:block;font-size:.85rem;font-weight:700;color:#cbd5e1;margin-bottom:6px;}
        .field input,.field select{width:100%;padding:10px 13px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:.92rem;font-family:inherit;transition:border-color .2s;box-sizing:border-box;}
        .field input:focus,.field select:focus{outline:none;border-color:#3b82f6;}
        .field input::placeholder{color:#475569;}
        .btn-submit{padding:10px 22px;background:#3b82f6;color:#fff;border:none;border-radius:8px;font-size:.92rem;font-weight:700;cursor:pointer;font-family:inherit;}
        .btn-submit:hover{background:#2563eb;}
        .t-table{width:100%;border-collapse:collapse;}
        .t-table th,.t-table td{padding:11px 14px;text-align:left;border-bottom:1px solid #1e293b;font-size:.88rem;color:#e2e8f0;}
        .t-table th{color:#64748b;font-size:.76rem;text-transform:uppercase;letter-spacing:.04em;}
        .t-table tr:last-child td{border-bottom:none;}
        @media(max-width:700px){.two-col{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<?php include "../includes/header.php"; ?>
<main class="page">
    <div class="breadcrumb"><a href="admin-dash.php">Dashboard</a> › Classes</div>
    <div class="hero"><h1>🏫 Manage Classes</h1><p>Create classes and assign teachers. Students can self-enroll from their dashboard.</p></div>

    <?php if ($feedback): ?>
        <div class="alert alert-<?= $feedback["type"] ?>"><?= htmlspecialchars($feedback["msg"]) ?></div>
    <?php endif; ?>

    <div class="two-col">
        <div class="card">
            <h2>Create a class</h2>
            <p class="sub">Students will be able to self-enroll from their dashboard.</p>
            <form method="POST">
                <div class="field">
                    <label>Class name</label>
                    <input type="text" name="class-name" placeholder="e.g. IT 101" required>
                </div>
                <div class="field">
                    <label>Teacher</label>
                    <select name="teacher" required>
                        <option value="">Select a teacher</option>
                        <?php while($t = $teachers_r->fetch_assoc()): ?>
                            <option value="<?= $t["teacher_id"] ?>"><?= htmlspecialchars($t["full_name"]) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Student limit</label>
                    <input type="number" name="limit" value="30" min="1" max="500" required>
                </div>
                <button type="submit" class="btn-submit">Create Class</button>
            </form>
        </div>

        <div class="card">
            <h2>All classes (<?= $classes_r->num_rows ?>)</h2>
            <?php if ($classes_r->num_rows > 0): ?>
            <table class="t-table">
                <thead><tr><th>Class</th><th>Teacher</th><th>Enrolled</th><th>Limit</th><th>Created</th></tr></thead>
                <tbody>
                <?php while($c = $classes_r->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c["class_name"]) ?></strong></td>
                    <td style="color:#64748b"><?= htmlspecialchars($c["teacher_name"]) ?></td>
                    <td><?= $c["enrolled"] ?></td>
                    <td><?= $c["student_limit"] ?></td>
                    <td style="color:#475569;font-size:.8rem"><?= date("M j, Y", strtotime($c["created_at"])) ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?><p style="color:#475569">No classes yet.</p><?php endif; ?>
        </div>
    </div>
</main>
<footer style="border-top:1px solid #1e293b;text-align:center;padding:24px;color:#334155;font-size:.82rem;">&copy; 2026 LMS System</footer>
</body>
</html>
