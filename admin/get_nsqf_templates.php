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

// Accept unified 'NSQF' category (or empty legacy requests) and map to both template types
if ($category !== '' && !in_array($category, ['NSQF', 'Long Term NSQF', 'Short Term NSQF'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid category']);
    exit();
}

try {
    // Fetch active NSQF templates for the selected category (map 'NSQF' to both long & short)
    if ($category === 'NSQF' || $category === '') {
        // For production compatibility - include all NSQF-related categories
        $stmt = $conn->prepare("SELECT id, course_name, eligibility 
                           FROM nsqf_course_templates 
                           WHERE (category IN ('Long Term NSQF','Short Term NSQF','Skill Based (Long Term) >500 hrs','Skill Based (Short Term) Courses (90-500 hrs)') 
                                  OR category = '' OR category IS NULL) 
                           AND is_active = 1 
                           ORDER BY course_name ASC");
    } else {
        $stmt = $conn->prepare("SELECT id, course_name, eligibility 
                           FROM nsqf_course_templates 
                           WHERE category = ? AND is_active = 1 
                           ORDER BY course_name ASC");
    }
    
    if ($stmt === false) {
        // Table might not exist - run migration
        include_once __DIR__ . '/../migrations/install_nsqf_templates.php';
        
        // Try again after migration
        $stmt = $conn->prepare("SELECT id, course_name, eligibility 
                               FROM nsqf_course_templates 
                               WHERE category = ? AND is_active = 1 
                               ORDER BY course_name ASC");
        
        if ($stmt === false) {
            throw new Exception("Could not prepare statement: " . $conn->error);
        }
    }
    
    if ($category === 'NSQF' || $category === '') {
        // no params
    } else {
        $stmt->bind_param("s", $category);
    }
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
        'count' => count($templates),
        'debug' => [
            'query_executed' => true,
            'user_role' => $_SESSION['admin_role'] ?? 'not_set',
            'sql_used' => ($category === 'NSQF' || $category === '') ? 'blank_category_compatible' : 'specific_category'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching templates: ' . $e->getMessage()
    ]);
}

$conn->close();
?>