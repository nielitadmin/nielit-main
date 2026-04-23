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

// Validate action
if (!isset($data['action']) || $data['action'] !== 'delete_faculty') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Validate required fields
if (!isset($data['faculty_id']) || empty($data['faculty_id'])) {
    echo json_encode(['success' => false, 'message' => 'Faculty ID is required']);
    exit();
}

$admin_id = $_SESSION['admin']['id'] ?? 1;
$admin_role = $_SESSION['admin']['role'] ?? '';
$faculty_id = intval($data['faculty_id']);

try {
    // First, check if the faculty exists and if the current admin can delete it
    $check_sql = "SELECT id, name, created_by FROM faculty WHERE id = ? AND is_active = 1";
    $check_stmt = $conn->prepare($check_sql);
    
    if (!$check_stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $check_stmt->bind_param("i", $faculty_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $faculty = $result->fetch_assoc();
    $check_stmt->close();
    
    if (!$faculty) {
        throw new Exception('Faculty member not found or already deleted');
    }
    
    // Check permissions - only allow deletion if:
    // 1. Master admin can delete any faculty
    // 2. Course coordinator can only delete faculty they created
    if ($admin_role !== 'master_admin' && $faculty['created_by'] != $admin_id) {
        throw new Exception('You can only delete faculty members you have added');
    }
    
    // Check if faculty is assigned to any batches
    $batch_check_sql = "SELECT COUNT(*) as batch_count FROM batch_faculty WHERE faculty_id = ?";
    $batch_stmt = $conn->prepare($batch_check_sql);
    $batch_stmt->bind_param("i", $faculty_id);
    $batch_stmt->execute();
    $batch_result = $batch_stmt->get_result();
    $batch_data = $batch_result->fetch_assoc();
    $batch_stmt->close();
    
    if ($batch_data['batch_count'] > 0) {
        // Faculty is assigned to batches - soft delete (deactivate)
        $delete_sql = "UPDATE faculty SET is_active = 0 WHERE id = ?";
        $action_message = "Faculty member deactivated (was assigned to batches)";
    } else {
        // Faculty not assigned to any batches - hard delete
        $delete_sql = "DELETE FROM faculty WHERE id = ?";
        $action_message = "Faculty member deleted successfully";
    }
    
    $delete_stmt = $conn->prepare($delete_sql);
    
    if (!$delete_stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $delete_stmt->bind_param("i", $faculty_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => $action_message,
            'faculty_id' => $faculty_id,
            'faculty_name' => $faculty['name']
        ]);
    } else {
        throw new Exception('Error deleting faculty: ' . $delete_stmt->error);
    }
    
    $delete_stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>