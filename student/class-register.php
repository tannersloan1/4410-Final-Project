<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if ($_SESSION["role"] != "student") {
    die("Restricted access");
}

$student_id = $_SESSION["user_id"];

// LEAVE class
if (isset($_GET["leave"]) && is_numeric($_GET["leave"])) {
    $class_id = intval($_GET["leave"]);
    $conn->query("DELETE FROM CLASS_ENROLLMENTS WHERE class_id=$class_id AND student_id=$student_id");
    logActivity($conn, $student_id, "student", "DELETE", "Left class $class_id", "CLASS_ENROLLMENTS");
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}

// REGISTER for class
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["register"])) {
    if (empty($_POST["classID"])) {
        $_SESSION["cr-error"] = "Please select a class before submitting.";
        header("Location: " . $_SERVER["PHP_SELF"]); exit();
    }

    $classID = intval($_POST["classID"]);
    $stmt = $conn->prepare("SELECT class_name FROM CLASSES WHERE class_id = ?");
    $stmt->bind_param("i", $classID);
    $stmt->execute();
    $class = $stmt->get_result()->fetch_assoc();

    if (!$class) {
        $_SESSION["cr-error"] = "Class not found.";
        header("Location: " . $_SERVER["PHP_SELF"]); exit();
    }

    $sql = $conn->prepare("INSERT INTO CLASS_ENROLLMENTS (class_id, student_id) VALUES (?,?)");
    $sql->bind_param("ii", $classID, $student_id);
    if ($sql->execute()) {
        logActivity($conn, $student_id, "student", "register", "Student registered for " . $class["class_name"], "CLASS_ENROLLMENTS");
        $_SESSION["cr-success"] = "You have successfully registered for " . $class["class_name"] . "!";
    } else {
        $_SESSION["cr-error"] = "You are already enrolled in that class.";
    }
    header("Location: " . $_SERVER["PHP_SELF"]); exit();
}

// Fetch all classes with enrollment info
$sql = $conn->prepare(
    "SELECT c.class_id, c.class_name, t.full_name, c.student_limit,
            COUNT(ce.student_id) AS enrolled_students,
            EXISTS (SELECT 1 FROM CLASS_ENROLLMENTS ce2 WHERE ce2.class_id = c.class_id AND ce2.student_id = ?) AS isEnrolled
     FROM CLASSES c
     JOIN TEACHER_INFO t ON c.teacher_id = t.teacher_id
     LEFT JOIN CLASS_ENROLLMENTS ce ON c.class_id = ce.class_id
     GROUP BY c.class_id, c.class_name, t.full_name, c.student_limit"
);
$sql->bind_param("i", $student_id);
$sql->execute();
$result = $sql->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Register</title>
    <link rel="stylesheet" href="../lms.css?v=5">
</head>
<body>
<?php include "../includes/header.php"; ?>

<div class="item">
    <form method="POST">
        <?php while ($row = $result->fetch_assoc()):
            $isEnrolled = (bool)$row["isEnrolled"];
            $full      = $row["enrolled_students"] >= $row["student_limit"];
        ?>
        <div style="margin-bottom:8px; display:flex; align-items:center; gap:10px;">
            <?php if ($isEnrolled): ?>
                ✅
            <?php elseif ($full): ?>
                🚫
            <?php else: ?>
                <input type="radio" name="classID" value="<?= $row["class_id"] ?>">
            <?php endif; ?>

            <?= htmlspecialchars($row["class_name"]) ?> —
            Teacher: <?= htmlspecialchars($row["full_name"]) ?> —
            <?= $row["enrolled_students"] ?>/<?= $row["student_limit"] ?> enrolled

            <?php if ($isEnrolled): ?>
                <a href="class-register.php?leave=<?= $row["class_id"] ?>"
                   onclick="return confirm('Leave <?= addslashes($row["class_name"]) ?>?')"
                   style="font-size:0.8rem; color:#ef4444; text-decoration:none; margin-left:6px;">
                    Leave
                </a>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>

        <button type="submit" name="register" style="margin-top:10px;">Submit</button>
    </form>

    <?php if (!empty($_SESSION["cr-success"])): ?>
        <div style="color:#86efac;background:#052e16;border:1px solid #14532d;padding:12px 16px;border-radius:8px;margin-top:14px;font-weight:700;">
            <?= htmlspecialchars($_SESSION["cr-success"]) ?>
        </div>
        <?php unset($_SESSION["cr-success"]); endif; ?>

    <?php if (!empty($_SESSION["cr-error"])): ?>
        <div style="color:#fca5a5;background:#450a0a;border:1px solid #7f1d1d;padding:12px 16px;border-radius:8px;margin-top:14px;font-weight:700;">
            <?= htmlspecialchars($_SESSION["cr-error"]) ?>
        </div>
        <?php unset($_SESSION["cr-error"]); endif; ?>
</div>

</body>
</html>
