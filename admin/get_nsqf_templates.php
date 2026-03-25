<?php
/**
 * AJAX endpoint to fetch NSQF course templates
 * Used by Course Coordinators when creating courses from NSQF templates
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
    // Fetch active NSQF templates for the selected category
    $stmt = $conn->prepare("SELECT id, course_name, eligibility 
                           FROM nsqf_course_templates 
                           WHERE category = ? AND is_active = 1 
                           ORDER BY course_name ASC");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'category' => $category,
        'templates' => $templates,
        'count' => count($templates)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching templates: ' . $e->getMessage()
    ]);
}

$conn->close();
?>