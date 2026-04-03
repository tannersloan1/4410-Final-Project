<?php
session_start();

// If user_id does not exist, then user is not logged in, so redirect to landing page
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit(); 
}

if ($_SESSION['role'] == "student") {
    //redirect to patient view and exit after
    header("Location: student/student-dash.php");
    exit();
//if a doctor is logged in
} else if ($_SESSION['role'] == "teacher") {
    //redirect to doctor view and exit after
    header("Location: teacher/teacher-dash.php");
    exit();
//if admin is logged in, redirect to admin dashboard
} else if ($_SESSION['role'] == "admin") {
    header("Location: admin/admin-dash.php");
    exit();
}
?>