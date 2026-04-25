<?php
session_start();
include "./includes/db.php";

$isLoggedIn = isset($_SESSION["user_id"]);
$role       = $_SESSION["role"] ?? null;

if ($isLoggedIn) {
    if ($role === "student")     { header("Location: /lms/student/student-dash.php"); exit(); }
    elseif ($role === "teacher") { header("Location: /lms/teacher/teacher-dash.php"); exit(); }
    elseif ($role === "admin")   { header("Location: /lms/admin/admin-dash.php");     exit(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS — Learning Management System</title>
    <link rel="stylesheet" href="lms.css?v=10">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Nunito", "Segoe UI", sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            margin: 0;
        }

        /* nav */
        .top-nav {
            max-width: 1100px;
            margin: 0 auto;
            padding: 22px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 1.25rem;
            font-weight: 800;
            color: #f1f5f9;
            text-decoration: none;
        }
        .logo span { color: #3b82f6; }
        .nav-btns { display: flex; gap: 10px; }
        .btn-ghost {
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            color: #94a3b8;
            transition: color 0.2s;
            font-family: inherit;
        }
        .btn-ghost:hover { color: #f1f5f9; }
        .btn-solid {
            padding: 9px 22px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            background: #3b82f6;
            color: #fff;
            transition: background 0.2s;
            font-family: inherit;
        }
        .btn-solid:hover { background: #2563eb; }

        /* hero */
        .hero {
            max-width: 700px;
            margin: 60px auto 0;
            padding: 0 28px;
            text-align: center;
        }
        .hero h1 {
            font-size: clamp(2rem, 5vw, 3.2rem);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 18px;
            color: #f8fafc;
        }
        .hero h1 .blue { color: #3b82f6; }
        .hero p {
            font-size: 1.05rem;
            color: #94a3b8;
            line-height: 1.75;
            margin-bottom: 36px;
            font-weight: 400;
        }
        .hero-btns {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-big {
            padding: 13px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            font-family: inherit;
            transition: all 0.2s;
        }
        .btn-big.blue { background: #3b82f6; color: #fff; }
        .btn-big.blue:hover { background: #2563eb; transform: translateY(-1px); }
        .btn-big.outline {
            background: transparent;
            color: #94a3b8;
            border: 1px solid #334155;
        }
        .btn-big.outline:hover { color: #f1f5f9; border-color: #475569; }

        /* divider */
        .squiggle {
            text-align: center;
            margin: 64px 0 48px;
            color: #1e293b;
            font-size: 1.8rem;
            letter-spacing: 6px;
        }

        /* cards section */
        .cards-section {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 28px;
        }
        .cards-intro {
            margin-bottom: 32px;
        }
        .cards-intro h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 8px;
        }
        .cards-intro p {
            color: #64748b;
            font-size: 0.95rem;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 80px;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 28px 24px;
        }
        .card-emoji { font-size: 2rem; margin-bottom: 14px; }
        .card h3 {
            font-size: 1.05rem;
            font-weight: 800;
            margin-bottom: 10px;
            color: #f1f5f9;
        }
        .card p {
            font-size: 0.88rem;
            color: #64748b;
            line-height: 1.7;
        }
        .card ul {
            list-style: none;
            margin-top: 14px;
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .card ul li {
            font-size: 0.85rem;
            color: #94a3b8;
            padding-left: 16px;
            position: relative;
        }
        .card ul li::before {
            content: "–";
            position: absolute;
            left: 0;
            color: #475569;
        }

        /* steps */
        .steps-section {
            max-width: 600px;
            margin: 0 auto 80px;
            padding: 0 28px;
        }
        .steps-section h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 28px;
        }
        .step {
            display: flex;
            gap: 18px;
            margin-bottom: 28px;
            align-items: flex-start;
        }
        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #3b82f6;
            margin-top: 7px;
            flex-shrink: 0;
        }
        .step h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #e2e8f0;
            margin-bottom: 4px;
        }
        .step p {
            font-size: 0.87rem;
            color: #64748b;
            line-height: 1.65;
        }

        /* bottom cta */
        .bottom-cta {
            max-width: 500px;
            margin: 0 auto 80px;
            padding: 0 28px;
            text-align: center;
        }
        .bottom-cta h2 {
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 10px;
        }
        .bottom-cta p {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 24px;
        }

        footer {
            border-top: 1px solid #1e293b;
            text-align: center;
            padding: 28px;
            color: #334155;
            font-size: 0.82rem;
        }

        @media (max-width: 700px) {
            .cards-grid { grid-template-columns: 1fr; }
            .hero h1 { font-size: 2rem; }
        }
    </style>
</head>
<body>

<nav>
    <div class="top-nav">
        <a href="/lms/index.php" class="logo">LMS</a>
        <div class="nav-btns">
            <a href="/lms/login.php"    class="btn-ghost">Log in</a>
            <a href="/lms/register.php" class="btn-solid">Sign up</a>
        </div>
    </div>
</nav>

<section class="hero">
    <h1>Quizzes, grades, and feedback <br><span class="blue">all in one spot.</span></h1>
    <p>
        A simple platform for teachers to build quizzes and for students
        to take them. 
    </p>
    <div class="hero-btns">
        <a href="/lms/register.php" class="btn-big blue">Get started</a>
        <a href="/lms/login.php"    class="btn-big outline">Already have an account</a>
    </div>
</section>

<div class="squiggle"></div>



<section class="steps-section">
    <h2>How it works</h2>

    <div class="step">
        <div class="step-dot"></div>
        <div>
            <h4>Create an account</h4>
            <p>Sign up as a student or teacher. Takes about 30 seconds.</p>
        </div>
    </div>

    <div class="step">
        <div class="step-dot"></div>
        <div>
            <h4>Teacher builds a quiz</h4>
            <p>Add questions, set a time limit if needed, assign it to a class, and hit publish.</p>
        </div>
    </div>

    <div class="step">
        <div class="step-dot"></div>
        <div>
            <h4>Student takes the quiz</h4>
            <p>Students see it on their dashboard and submit when they're done.</p>
        </div>
    </div>

    <div class="step">
        <div class="step-dot"></div>
        <div>
            <h4>Everyone sees the results</h4>
            <p>Students get their score and a question-by-question breakdown. Teachers get a full class overview.</p>
        </div>
    </div>
</section>



<footer>
    &copy; 2026 LMS System
</footer>

</body>
</html>
