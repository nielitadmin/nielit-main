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
if (!isset($data['action']) || $data['action'] !== 'add_faculty') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Validate required fields
if (!isset($data['name']) || empty(trim($data['name']))) {
    echo json_encode(['success' => false, 'message' => 'Faculty name is required']);
    exit();
}

$admin_id = $_SESSION['admin_id'];

try {
    // Prepare insert query
    $sql = "INSERT INTO faculty (name, email, phone, designation, department, created_by, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, 1)";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    // Clean and prepare data
    $name = trim($data['name']);
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $designation = trim($data['designation'] ?? '');
    $department = trim($data['department'] ?? '');
    
    // Bind parameters - faculty created by current admin
    $stmt->bind_param("sssssi", $name, $email, $phone, $designation, $department, $admin_id);
    
    // Execute query
    if ($stmt->execute()) {
        $faculty_id = $conn->insert_id;
        
        // Send confirmation email if email is provided
        $email_sent = false;
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_sent = sendFacultyConfirmationEmail($email, $name, $designation, $department);
            
            // Update sent_email timestamp if email was sent successfully
            if ($email_sent) {
                $update_sql = "UPDATE faculty SET email_confirmed_at = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $faculty_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Faculty member added successfully' . ($email_sent ? ' and confirmation email sent' : ''),
            'email_sent' => $email_sent,
            'faculty' => [
                'id' => $faculty_id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'designation' => $designation,
                'department' => $department
            ]
        ]);
    } else {
        throw new Exception('Error adding faculty: ' . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>