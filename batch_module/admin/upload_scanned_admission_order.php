<?php
/**
 * Upload Scanned Admission Order Handler
 * Handles file upload and locking functionality for scanned admission orders
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/batch_functions.php';

// Check authentication
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$admin_id = $_SESSION['admin_id'] ?? null;
$admin_role = $_SESSION['admin_role'] ?? '';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$action = $_POST['action'] ?? '';
$batch_id = $_POST['batch_id'] ?? '';

if (!is_numeric($batch_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid batch ID']);
    exit();
}

try {
    switch ($action) {
        case 'upload':
            handleUpload($conn, $batch_id, $admin_id);
            break;
            
        case 'lock':
            handleLock($conn, $batch_id, $admin_id);
            break;
            
        case 'unlock':
            handleUnlock($conn, $batch_id, $admin_id, $admin_role);
            break;
            
        case 'download':
            handleDownload($conn, $batch_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleUpload($conn, $batch_id, $admin_id) {
    // Check if batch exists
    $batch_sql = "SELECT id, scanned_order_locked FROM batches WHERE id = ?";
    $batch_stmt = $conn->prepare($batch_sql);
    $batch_stmt->bind_param("i", $batch_id);
    $batch_stmt->execute();
    $batch_result = $batch_stmt->get_result();
    
    if ($batch_result->num_rows === 0) {
        throw new Exception('Batch not found');
    }
    
    $batch = $batch_result->fetch_assoc();
    
    // Check if already locked
    if ($batch['scanned_order_locked']) {
        throw new Exception('Cannot upload: Scanned admission order is locked');
    }
    
    // Validate file upload
    if (!isset($_FILES['scanned_file']) || $_FILES['scanned_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }
    
    $file = $_FILES['scanned_file'];
    
    // Validate file type (PDF only)
    $allowed_types = ['application/pdf'];
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $file['tmp_name']);
    finfo_close($file_info);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Only PDF files are allowed');
    }
    
    // Validate file size (max 10MB)
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_size) {
        throw new Exception('File size must be less than 10MB');
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/../../uploads/scanned_admission_orders';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'batch_' . $batch_id . '_admission_order_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . '/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Remove old file if exists
    $old_file_sql = "SELECT scanned_admission_order FROM batches WHERE id = ?";
    $old_file_stmt = $conn->prepare($old_file_sql);
    $old_file_stmt->bind_param("i", $batch_id);
    $old_file_stmt->execute();
    $old_file_result = $old_file_stmt->get_result();
    
    if ($old_file_result->num_rows > 0) {
        $old_file_data = $old_file_result->fetch_assoc();
        if (!empty($old_file_data['scanned_admission_order'])) {
            $old_file_path = $upload_dir . '/' . $old_file_data['scanned_admission_order'];
            if (file_exists($old_file_path)) {
                unlink($old_file_path);
            }
        }
    }
    
    // Update database
    $update_sql = "UPDATE batches SET 
                   scanned_admission_order = ?, 
                   scanned_order_uploaded_at = NOW(), 
                   scanned_order_uploaded_by = ? 
                   WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sii", $filename, $admin_id, $batch_id);
    
    if (!$update_stmt->execute()) {
        // Remove uploaded file if database update fails
        unlink($file_path);
        throw new Exception('Failed to update database');
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Scanned admission order uploaded successfully',
        'filename' => $filename
    ]);
}

function handleLock($conn, $batch_id, $admin_id) {
    // Check if file exists
    $check_sql = "SELECT scanned_admission_order, scanned_order_locked FROM batches WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $batch_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('Batch not found');
    }
    
    $batch = $check_result->fetch_assoc();
    
    if (empty($batch['scanned_admission_order'])) {
        throw new Exception('No scanned admission order uploaded yet');
    }
    
    if ($batch['scanned_order_locked']) {
        throw new Exception('Scanned admission order is already locked');
    }
    
    // Lock the document
    $lock_sql = "UPDATE batches SET 
                 scanned_order_locked = 1, 
                 scanned_order_locked_at = NOW(), 
                 scanned_order_locked_by = ? 
                 WHERE id = ?";
    $lock_stmt = $conn->prepare($lock_sql);
    $lock_stmt->bind_param("ii", $admin_id, $batch_id);
    
    if (!$lock_stmt->execute()) {
        throw new Exception('Failed to lock scanned admission order');
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Scanned admission order locked successfully'
    ]);
}

function handleUnlock($conn, $batch_id, $admin_id, $admin_role) {
    // Only master admin can unlock
    if ($admin_role !== 'master_admin') {
        throw new Exception('Only Master Admin can unlock scanned admission orders');
    }
    
    // Unlock the document
    $unlock_sql = "UPDATE batches SET 
                   scanned_order_locked = 0, 
                   scanned_order_locked_at = NULL, 
                   scanned_order_locked_by = NULL 
                   WHERE id = ?";
    $unlock_stmt = $conn->prepare($unlock_sql);
    $unlock_stmt->bind_param("i", $batch_id);
    
    if (!$unlock_stmt->execute()) {
        throw new Exception('Failed to unlock scanned admission order');
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Scanned admission order unlocked successfully'
    ]);
}

function handleDownload($conn, $batch_id) {
    // Get file info
    $file_sql = "SELECT scanned_admission_order FROM batches WHERE id = ?";
    $file_stmt = $conn->prepare($file_sql);
    $file_stmt->bind_param("i", $batch_id);
    $file_stmt->execute();
    $file_result = $file_stmt->get_result();
    
    if ($file_result->num_rows === 0) {
        throw new Exception('Batch not found');
    }
    
    $batch = $file_result->fetch_assoc();
    
    if (empty($batch['scanned_admission_order'])) {
        throw new Exception('No scanned admission order available');
    }
    
    $file_path = __DIR__ . '/../../uploads/scanned_admission_orders/' . $batch['scanned_admission_order'];
    
    if (!file_exists($file_path)) {
        throw new Exception('File not found');
    }
    
    // Set headers for download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $batch['scanned_admission_order'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($file_path);
    exit();
}
?>