<?php
session_start();
include "../includes/db.php";
include "../includes/activity.php";

if ($_SESSION["role"] != "student") {
    header("Location: /lms/login.php"); exit();
}

$student_id = $_SESSION["user_id"];

$sql = $conn->prepare("SELECT email, full_name FROM STUDENT_INFO WHERE student_id=?");
$sql->bind_param("i", $student_id);
$sql->execute();
$studentName = $sql->get_result()->fetch_assoc();

$stmt = $conn->prepare(
    "SELECT c.class_id, c.class_name FROM CLASSES c
     JOIN CLASS_ENROLLMENTS ce ON c.class_id = ce.class_id
     WHERE ce.student_id = ?"
);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$results = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../lms.css?v=5">
</head>
<body>
<?php include "../includes/header.php"; ?>

<h1 class="dash-hello">Hello, <?= htmlspecialchars($studentName["full_name"] ?? "Student") ?>!</h1>

<div class="dash-container">
    <div class="dash-grid">
        <a href="class-register.php" class="card">
            <div class="card-content">
                <span>Register For Classes</span>
            </div>
        </a>
        <?php while ($row = $results->fetch_assoc()): ?>
            <a href="class.php?id=<?= $row["class_id"] ?>" class="card">
                <div class="card-content">
                    <span><?= htmlspecialchars($row["class_name"]) ?></span>
                </div>
            </a>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
