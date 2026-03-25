<?php
/**
 * AJAX endpoint to fetch existing NSQF courses
 * Used to show dropdown of existing courses when NSQF category is selected
 */

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$category = $_GET['category'] ?? '';

if (!in_array($category, ['Long Term NSQF', 'Short Term NSQF'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid category']);
    exit();
}

try {
    // Fetch existing courses for the selected NSQF category
    $stmt = $conn->prepare("SELECT id, course_name, course_code, duration, fees 
                           FROM courses 
                           WHERE course_type = ? AND status = 'active' 
                           ORDER BY course_name ASC");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'category' => $category,
        'courses' => $courses,
        'count' => count($courses)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching courses: ' . $e->getMessage()
    ]);
}

$conn->close();
?>