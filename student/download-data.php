<?php
session_start();

include "../includes/db.php";
require ('../fpdf.php');

// Makes sure you must have logged in with appropiate role
if ($_SESSION["role"] != "student") {
    die("Restricted access"); 
}


$student_id = $_SESSION['user_id'];


// Get all class + quiz + submission data

$query = "
SELECT 
    c.class_id,
    c.class_name,
    q.quiz_id,
    q.title AS quiz_title,
    ss.score,
    ss.total_points,
    ss.percentage,
    ss.submitted_at
FROM CLASS_ENROLLMENTS ce
JOIN CLASSES c ON ce.class_id = c.class_id
JOIN QUIZZES q ON q.class_id = c.class_id
LEFT JOIN STUDENT_SUBMISSIONS ss 
    ON ss.quiz_id = q.quiz_id 
    AND ss.student_id = ce.student_id
WHERE ce.student_id = ?
AND q.is_published = TRUE
ORDER BY c.class_name, q.created_at
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

// Organize data by class

$classes = [];

while ($row = $result->fetch_assoc()) {
    $class_id = $row['class_id'];

    if (!isset($classes[$class_id])) {
        $classes[$class_id] = [
            'class_name' => $row['class_name'],
            'quizzes' => [],
            'total_percentage' => 0,
            'quiz_count' => 0
        ];
    }

    // Only count quizzes that have a submission
    if (!is_null($row['percentage'])) {
        $classes[$class_id]['total_percentage'] += $row['percentage'];
        $classes[$class_id]['quiz_count']++;
    }

    $classes[$class_id]['quizzes'][] = $row;
}

$nameQuery = "SELECT full_name FROM STUDENT_INFO WHERE student_id = ?";
$nameStmt = $conn->prepare($nameQuery);
$nameStmt->bind_param("i", $student_id);
$nameStmt->execute();
$nameResult = $nameStmt->get_result();
$student = $nameResult->fetch_assoc();

$student_name = $student ? $student['full_name'] : "Student";

// Create PDF

class PDF extends FPDF
{
    function Header()
    {
        // Header with placeholder names
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'LMS Report Card', 0, 1, 'C');

        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'School Name Placeholder', 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();

// Student Info Section

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, "Student: " . $student_name, 0, 1);
$pdf->Ln(3);

foreach ($classes as $class) {
    if ($pdf->GetY() > 230) {
        $pdf->AddPage();
    }

    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, $class['class_name'], 0, 1);

    // Class average
    $pdf->SetFont('Arial', '', 11);

    $average = 0;
    if ($class['quiz_count'] > 0) {
        $average = $class['total_percentage'] / $class['quiz_count'];
    }

    $pdf->Cell(0, 7, "Class Average: " . number_format($average, 2) . "%", 0, 1);
    $pdf->Ln(2);

    // Table Header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(70, 8, "Quiz Title", 1);
    $pdf->Cell(30, 8, "Score", 1);
    $pdf->Cell(25, 8, "Percent", 1);
    $pdf->Cell(45, 8, "Submitted", 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 10);

    foreach ($class['quizzes'] as $quiz) {

        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
        }

        $scoreText = "-";
        $percentText = "-";
        $dateText = "-";

        if (!is_null($quiz['score'])) {
            $scoreText = $quiz['score'] . "/" . $quiz['total_points'];
            $percentText = number_format($quiz['percentage'], 2) . "%";
            $dateText = date("Y-m-d", strtotime($quiz['submitted_at']));
        }

        $pdf->Cell(70, 8, $quiz['quiz_title'], 1);
        $pdf->Cell(30, 8, $scoreText, 1);
        $pdf->Cell(25, 8, $percentText, 1);
        $pdf->Cell(45, 8, $dateText, 1);
        $pdf->Ln();
    }

    $pdf->Ln(6);
}

ob_clean();
$pdf->Output('I', 'report_card.pdf');
exit;
?>