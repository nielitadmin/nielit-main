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
if (!isset($data['action']) || $data['action'] !== 'add_faculty') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Validate required fields
if (!isset($data['name']) || empty(trim($data['name']))) {
    echo json_encode(['success' => false, 'message' => 'Faculty name is required']);
    exit();
}

$admin_id = $_SESSION['admin']['id'] ?? 1;

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
        
        echo json_encode([
            'success' => true,
            'message' => 'Faculty member added successfully',
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