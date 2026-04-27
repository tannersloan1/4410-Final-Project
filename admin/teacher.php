<?php
session_start();

include "../includes/db.php";
include "../includes/activity.php";

// Makes sure you must have logged in with appropiate role
if ($_SESSION["role"] != "admin") {
    die("Restricted access"); 
}

$sql = $conn->prepare("SELECT full_name, teacher_id FROM TEACHER_INFO");
$sql->execute();
$result = $sql->get_result();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["teacher-register"])) {
        $email = $_POST["register-email"];
        $password = $_POST["password"];
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $info = $conn->prepare("INSERT INTO TEACHER_INFO (email) VALUES (?)");
        $info->bind_param("s", $email);
        if (!$info->execute()) {
            // Add error message here
        }

        $teacher_id = $conn->insert_id;

        $user = $conn->prepare("INSERT INTO TEACHER_USERS (teacher_id, email, password_hash) VALUES (?,?,?)");
        $user->bind_param("iss", $teacher_id, $email, $password_hash);
        if ($user->execute()) {
            logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "register", "admin registered teacher " . $teacher_id, "teacher_users/teacher_info");

            $_SESSION["tr-success"] = "Successfully registered teacher with email: " . $email;

            header("Location: " . $_SERVER["PHP_SELF"]);
            exit();
        }
        else {
            // Add error message here
        }
    }

    else if (isset($_POST["create-class"])) {
        $class = $_POST["class-name"];
        $teacher = $_POST["teacher"];
        $limit = $_POST["limit"];

        $sql = $conn->prepare("INSERT INTO CLASSES (teacher_id, class_name, student_limit) VALUES (?,?,?)");
        $sql->bind_param("isi", $teacher, $class, $limit);

        if ($sql->execute()) {
            logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "create class", 
            "admin created class " . $class . " with teacher id: " . $teacher . " with a max student count of " . $limit . ".", "teacher_users/teacher_info");

            $_SESSION["cc-success"] = "Successfully made class with class name: " . $class . ", lead by teacher with id: " . $teacher . ", and has a max student count of " . $limit;

            header("Location: " . $_SERVER["PHP_SELF"]);
            exit();
        }
        else {
            // Add error message here
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Teacher Admin Control</title>
        <link rel="stylesheet" href="../lms.css?v=5">
    </head>
    <body>
        <?php include "../includes/header.php"; ?>

        <div class="item">
            <form method="POST">
                <label for="register-email">Email</label>
                <input name="register-email" type="email" required>

                <label for="password">Temp Password</label>
                <input type="password" name="password" required>

                <button type="submit" name="teacher-register">Submit</button>
            </form>

            <?php if (!empty($_SESSION["tr-success"])): ?>
                <div><?php echo htmlspecialchars($_SESSION["tr-success"]) ?></div>
                <?php unset($_SESSION["tr-success"]); 
            endif; ?>
        </div>

        <div class="item">

        </div>

        <!-- This might be moved into a different file but for now it will be in here -->
        <div class="item">
            <form method="POST">
                <label for="class-name">Class Name</label>
                <input name="class-name" type="text" required>

                <label for="teacher">Teacher</label>
                <select name="teacher" required>
                    <option value="">Select The Teacher</option>
                    <?php
                    while($row = $result->fetch_assoc()) {
                        echo "<option value='" . $row["teacher_id"] . "'>" . $row["full_name"] . "</option>";
                    }
                    ?>
                </select>

                <label for="limit">Set Student Count Limit</label>
                <input type="number" name="limit" required>

                <button type="submit" name="create-class">Submit</button>
            </form>

            <?php if (!empty($_SESSION["cc-success"])): ?>
                <div><?php echo htmlspecialchars($_SESSION["cc-success"]) ?></div>
                <?php unset($_SESSION["cc-success"]); 
            endif; ?>
        </div>
    </body>
</html>