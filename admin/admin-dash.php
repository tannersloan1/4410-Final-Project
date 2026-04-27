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
    $adminName = $sql->get_result()->fetch_assoc();
    $name = "NO NAME IN DATABASE.";
}
else {
    // Error here
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
    <body id="dash-body">
        <?php include "../includes/header.php"; ?>

        <?php if ($adminName && $adminName["full_name"] !== NULL): ?>
            <h1 class="dash-hello">Hello, <?php echo htmlspecialchars($adminName["full_name"]); ?>!</h1>
        <?php else: ?>
            <h1 class="dash-hello">Hello, <?php echo htmlspecialchars($name); ?>!</h1>
        <?php endif; ?>

        <div class="dash-container">
            <div class="dash-grid">
                <a href="student.php" class="card">
                    <div class="card-content">
                        <span>Student</span>
                    </div>
                </a>

                <a href="teacher.php" class="card">
                    <div class="card-content">
                        <span>Teacher</span>
                    </div>
                </a>

                <a href="analytics.php" class="card">
                    <div class="card-content">
                        <span>Analytics / Logs</span>
                    </div>
                </a>
            </div>
        </div>
    </body>
</html>
