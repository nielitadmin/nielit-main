<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'master_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

require_once '../config/database.php';

$admin_id = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : 0;

if ($admin_id <= 0) {
    echo json_encode(['assigned_courses' => []]);
    exit();
}

try {
    $query = "SELECT course_id FROM admin_course_assignments 
              WHERE admin_id = ? AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assigned_courses = [];
    while ($row = $result->fetch_assoc()) {
        $assigned_courses[] = intval($row['course_id']);
    }
    
    echo json_encode(['assigned_courses' => $assigned_courses]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>