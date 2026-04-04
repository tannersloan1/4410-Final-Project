<?php
session_start();

include "../includes/db.php";
include "../includes/activity.php";

// Makes sure you must have logged in with appropiate role
if ($_SESSION["role"] != "admin") {
    die("Restricted access"); 
}

$sql = $conn->prepare("SELECT email, full_name FROM ADMIN_INFO WHERE admin_id=?");
$sql->bind_param("i", $_SESSION["user_id"]);
if ($sql->execute()) {
    $admin = $sql->get_result()->fetch_assoc();
}
else {
    $name = "NO NAME IN DATABASE.";
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard</title>
        <link rel="stylesheet" href="../lms.css?v=5">
    </head>
    <body>
        <?php include "../includes/header.php"; ?>

        <?php if ($admin): ?>
            <h1 class="dash-hello">Hello, <?php echo htmlspecialchars($admin["full_name"]); ?>!</h1>
        <?php else: ?>
            <h1 class="dash-hello">Hello, <?php echo htmlspecialchars($name); ?>!</h1>
        <?php endif; ?>

        <div class="dash-container">
            <div class="dash-grid">
                <a href="student.php">
                    <div class="card">
                        <div class="card-content">
                            <p>Student</p>
                        </div>
                    </div>
                </a>
                <a href="teacher.php">
                    <div class="card">
                        <div class="card-content">
                            <p>Teacher</p>
                        </div>
                    </div>
                </a>
                <a href="analytics.php">
                    <div class="card">
                        <div class="card-content">
                            <p>Analytics</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </body>
</html>
