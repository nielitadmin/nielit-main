<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get student ID
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit();
}

// Fetch student details
$sql = "SELECT * FROM students WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $student = $result->fetch_assoc();
    
    // Format dates
    if ($student['created_at']) {
        $student['created_at_formatted'] = date('d M Y, h:i A', strtotime($student['created_at']));
    }
    if ($student['dob']) {
        $student['dob_formatted'] = date('d M Y', strtotime($student['dob']));
    }
    
    // Fetch education details
    $sql_education = "SELECT * FROM education_details WHERE student_id = ? ORDER BY id ASC";
    $stmt_education = $conn->prepare($sql_education);
    $education_records = [];
    if ($stmt_education) {
        $stmt_education->bind_param("s", $student['student_id']);
        $stmt_education->execute();
        $education_result = $stmt_education->get_result();
        while ($row = $education_result->fetch_assoc()) {
            $education_records[] = $row;
        }
        $stmt_education->close();
    }
    
    $student['education_records'] = $education_records;
    
    echo json_encode([
        'success' => true,
        'student' => $student
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Student not found'
    ]);
}

$stmt->close();
$conn->close();
?>
