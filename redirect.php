<?php
session_start();

// If user_id does not exist, then user is not logged in, so redirect to landing page
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit(); 
}

if ($_SESSION['role'] == "student") {
    // Redirect to dashboard then exit
    header("Location: student/student-dash.php");
    exit();
} else if ($_SESSION['role'] == "teacher") {
    header("Location: teacher/teacher-dash.php");
    exit();
} else if ($_SESSION['role'] == "admin") {
    header("Location: admin/admin-dash.php");
    exit();
}
?>
