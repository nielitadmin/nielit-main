<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/email_helper.php';

// Set JSON header
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin']) || !isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate action
if (!isset($data['action']) || $data['action'] !== 'resend_email') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Validate required fields
if (!isset($data['faculty_id']) || empty($data['faculty_id'])) {
    echo json_encode(['success' => false, 'message' => 'Faculty ID is required']);
    exit();
}

$faculty_id = intval($data['faculty_id']);

try {
    // Fetch faculty details
    $sql = "SELECT id, name, email, designation, department FROM faculty WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $faculty = $result->fetch_assoc();
    $stmt->close();
    
    if (!$faculty) {
        throw new Exception('Faculty member not found');
    }
    
    if (empty($faculty['email'])) {
        throw new Exception('No email address on file for this faculty member');
    }
    
    if (!filter_var($faculty['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address on file');
    }
    
    // Send confirmation email
    $email_sent = sendFacultyConfirmationEmail(
        $faculty['email'],
        $faculty['name'],
        $faculty['designation'],
        $faculty['department']
    );
    
    if ($email_sent) {
        // Update last email sent timestamp
        $update_sql = "UPDATE faculty SET email_confirmed_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $faculty_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Confirmation email sent successfully to ' . htmlspecialchars($faculty['email'])
        ]);
    } else {
        throw new Exception('Failed to send email. Please try again later.');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
