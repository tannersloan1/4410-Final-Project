<?php
// include this file in body part with css already included

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isLoggedIn = isset($_SESSION["user_id"]);
$role = $_SESSION["role"] ?? null;
?>

<div class="nav">
    <div class="nav-inner">
        <div class="header">LMS System</div>
        <div class="links">
            <a href="/lms/index.php">Home</a>  
            <?php if ($isLoggedIn): ?>
                <?php if ($role === "student"): ?>
                    <a href="/lms/student/student-dash.php">Dashboard</a>
                <?php elseif ($role === "teacher"): ?>
                    <a href="/lms/teacher/teacher-dash.php">Dashboard</a>
                <?php else: ?>
                    <a href="/lms/admin/admin-dash.php">Dashboard</a>
                <?php endif; ?>
                <a href="/lms/logout.php">Logout</a>
                <?php if ($role === "student"): ?>
                    <a href="/lms/student/student-info.php">Account Info</a>
                <?php elseif ($role === "teacher"): ?>
                    <a href="/lms/teacher/teacher-info.php">Account Info</a>
                <?php else: ?>
                    <a href="/lms/admin/admin-info.php">Account Info</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="/lms/login.php">Login</a>
                <a href="/lms/register.php">Create Account</a>
            <?php endif; ?>
        </div>
    </div>
</div>
