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

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['batch_id']) || empty($data['batch_id'])) {
    echo json_encode(['success' => false, 'message' => 'Batch ID is required']);
    exit();
}

$batch_id = intval($data['batch_id']);
$admin_id = $_SESSION['admin']['id'] ?? 1;

// Start transaction
$conn->autocommit(FALSE);

try {
    // Prepare update query for batch details
    $sql = "UPDATE batches SET 
            admission_order_ref = ?,
            admission_order_date = ?,
            location = ?,
            examination_month = ?,
            class_time = ?,
            batch_coordinator = ?,
            scheme_incharge = ?,
            copy_to_list = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param("ssssssssi",
        $data['admission_order_ref'],
        $data['admission_order_date'],
        $data['location'],
        $data['examination_month'],
        $data['class_time'],
        $data['batch_coordinator'],
        $data['scheme_incharge'],
        $data['copy_to_list'],
        $batch_id
    );

    // Execute batch update
    if (!$stmt->execute()) {
        throw new Exception('Error saving batch details: ' . $stmt->error);
    }
    $stmt->close();

    // Handle faculty assignments if provided
    if (isset($data['faculty_ids']) && is_array($data['faculty_ids'])) {
        // First, remove existing faculty assignments for this batch
        $delete_sql = "DELETE FROM batch_faculty WHERE batch_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        if (!$delete_stmt) {
            throw new Exception('Error preparing delete statement: ' . $conn->error);
        }
        $delete_stmt->bind_param("i", $batch_id);
        if (!$delete_stmt->execute()) {
            throw new Exception('Error removing existing faculty assignments: ' . $delete_stmt->error);
        }
        $delete_stmt->close();

        // Add new faculty assignments
        if (!empty($data['faculty_ids'])) {
            $insert_sql = "INSERT INTO batch_faculty (batch_id, faculty_id, assigned_by) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            if (!$insert_stmt) {
                throw new Exception('Error preparing insert statement: ' . $conn->error);
            }

            foreach ($data['faculty_ids'] as $faculty_id) {
                $faculty_id = intval($faculty_id);
                if ($faculty_id > 0) {
                    $insert_stmt->bind_param("iii", $batch_id, $faculty_id, $admin_id);
                    if (!$insert_stmt->execute()) {
                        throw new Exception('Error assigning faculty: ' . $insert_stmt->error);
                    }
                }
            }
            $insert_stmt->close();
        }
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Admission order details and faculty assignments saved successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
