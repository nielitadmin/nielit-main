<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$batch_id = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
$nielit_reg_no = isset($_POST['nielit_reg_no']) ? trim((string)$_POST['nielit_reg_no']) : '';

if ($student_id <= 0 || $batch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student or batch ID']);
    exit();
}

$updated = false;
$errors = [];

// Ensure students.nielit_registration_no exists
$studentCol = $conn->query("SHOW COLUMNS FROM students LIKE 'nielit_registration_no'");
if (!$studentCol || $studentCol->num_rows === 0) {
    $conn->query("ALTER TABLE students ADD COLUMN nielit_registration_no VARCHAR(100) NULL DEFAULT NULL");
}

// Always store on the student record (source of truth across pages)
$stuStmt = $conn->prepare('UPDATE students SET nielit_registration_no = ? WHERE id = ?');
if ($stuStmt) {
    $stuStmt->bind_param('si', $nielit_reg_no, $student_id);
    if ($stuStmt->execute()) {
        $updated = true;
    } else {
        $errors[] = $stuStmt->error;
    }
    $stuStmt->close();
}

$check_table = $conn->query("SHOW TABLES LIKE 'batch_students'");
$has_batch_students = ($check_table && $check_table->num_rows > 0);

if ($has_batch_students) {
    $check_column = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'nielit_registration_no'");
    if (!$check_column || $check_column->num_rows === 0) {
        $conn->query("ALTER TABLE batch_students ADD COLUMN nielit_registration_no VARCHAR(100) NULL DEFAULT NULL");
    }

    $hasRecordCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
    $hasRecordId = ($hasRecordCol && $hasRecordCol->num_rows > 0);

    if ($hasRecordId) {
        $bsStmt = $conn->prepare(
            'UPDATE batch_students
             SET nielit_registration_no = ?
             WHERE batch_id = ?
               AND (student_record_id = ? OR student_id = ?)'
        );
        if ($bsStmt) {
            $bsStmt->bind_param('siii', $nielit_reg_no, $batch_id, $student_id, $student_id);
            if ($bsStmt->execute()) {
                $updated = true;
            } else {
                $errors[] = $bsStmt->error;
            }
            $bsStmt->close();
        }
    } else {
        $bsStmt = $conn->prepare(
            'UPDATE batch_students
             SET nielit_registration_no = ?
             WHERE batch_id = ? AND student_id = ?'
        );
        if ($bsStmt) {
            $bsStmt->bind_param('sii', $nielit_reg_no, $batch_id, $student_id);
            if ($bsStmt->execute()) {
                $updated = true;
            } else {
                $errors[] = $bsStmt->error;
            }
            $bsStmt->close();
        }
    }
}

if ($updated) {
    echo json_encode([
        'success' => true,
        'message' => 'NIELIT Registration Number updated successfully',
        'nielit_reg_no' => $nielit_reg_no,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating' . (!empty($errors) ? (': ' . implode('; ', $errors)) : ''),
    ]);
}

$conn->close();
