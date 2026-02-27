<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$batch_id = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
$nielit_reg_no = isset($_POST['nielit_reg_no']) ? trim($_POST['nielit_reg_no']) : '';

// Validate inputs
if (!$student_id || !$batch_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid student or batch ID']);
    exit();
}

// Check if batch_students table exists and has nielit_registration_no column
$check_table = $conn->query("SHOW TABLES LIKE 'batch_students'");
$has_batch_students = ($check_table && $check_table->num_rows > 0);

if ($has_batch_students) {
    // Check if nielit_registration_no column exists in batch_students
    $check_column = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'nielit_registration_no'");
    $has_column = ($check_column && $check_column->num_rows > 0);
    
    if ($has_column) {
        // Update in batch_students table
        $sql = "UPDATE batch_students SET nielit_registration_no = ? WHERE student_id = ? AND batch_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $nielit_reg_no, $student_id, $batch_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'NIELIT Registration Number updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        // Column doesn't exist, update students table instead
        $sql = "UPDATE students SET nielit_registration_no = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $nielit_reg_no, $student_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'NIELIT Registration Number updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating: ' . $stmt->error]);
        }
        $stmt->close();
    }
} else {
    // No batch_students table, update students table
    $sql = "UPDATE students SET nielit_registration_no = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $nielit_reg_no, $student_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'NIELIT Registration Number updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating: ' . $stmt->error]);
    }
    $stmt->close();
}

$conn->close();
?>
