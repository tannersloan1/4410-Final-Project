<?php
session_start();

include "../includes/db.php";
include "../includes/activity.php";

// Makes sure you must have logged in with appropiate role
if ($_SESSION["role"] != "student") {
    die("Restricted access"); 
}

$sql = $conn->prepare("SELECT c.class_id, c.class_name, t.full_name, c.student_limit, COUNT(ce.student_id) AS enrolled_students
FROM CLASSES c JOIN TEACHER_INFO t ON c.teacher_id = t.teacher_id
LEFT JOIN CLASS_ENROLLMENTS ce ON c.class_id = ce.class_id
GROUP BY c.class_id, c.class_name, t.full_name, c.student_limit");
$sql->execute();
$result = $sql->get_result();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["register"])) {
        $studentID = $_SESSION["user_id"];
        $classID = $_POST["classID"];

        $stmt = $conn->prepare("SELECT class_name FROM CLASSES WHERE class_id = ?");
        $stmt->bind_param("i", $classID);

        if ($stmt->execute()) {
            $class = $stmt->get_result()->fetch_assoc();
            $className = $class["class_name"];
        }
        else {
            // Error message
        }

        $sql = $conn->prepare("INSERT INTO CLASS_ENROLLMENTS (class_id, student_id) VALUES (?,?)");
        $sql->bind_param("ii", $classID, $studentID);

        if ($sql->execute()) {
            $date = new DateTime();
            logActivity($conn, $_SESSION["user_id"], $_SESSION["role"], "register", 
            "student " . $_SESSION["user_id"] . " registered for " . $className . " at " . $date->format("Y-m-d H:i:s") . ".", "teacher_users/teacher_info");

            $_SESSION["cr-success"] = "You have successfully registered for " . $className . " with class ID: " . $classID . "!";

            header("Location: " . $_SERVER["PHP_SELF"]);
            exit();
        }
        else {
            // Error message
        }
    }
}
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
                <?php
                    while ($row = $result->fetch_assoc()) {
                        echo "<div>";
                            if ($row["enrolled_students"] < $row["student_limit"]) {
                                echo "<input type='radio' name='classID' value='" . $row["class_id"] . "'>";
                            }
                            else {
                                echo "🚫";
                            }
                            echo " " . $row["class_name"] . " - ";
                            echo "Class ID: " . $row["class_id"] . " - ";
                            echo "Teacher: " . $row["full_name"] . " - ";
                            echo "Amount enrolled: " . $row["enrolled_students"] . "/" . $row["student_limit"];
                        echo "</div>";
                    }
                ?>

                <button type="submit" name="register">Submit</button>
            </form>

            <?php if (!empty($_SESSION["cr-success"])): ?>
                <div><?php echo htmlspecialchars($_SESSION["cr-success"]) ?></div>
                <?php unset($_SESSION["cr-success"]); 
            endif; ?>
        </div>
    </body>
</html>