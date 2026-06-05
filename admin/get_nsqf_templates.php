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
require_once __DIR__ . '/../includes/course_category_options.php';

header('Content-Type: application/json');

$category = trim($_GET['category'] ?? '');
$valid_categories = get_all_valid_nsqf_template_categories();

if ($category !== '' && !in_array($category, $valid_categories, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid category']);
    exit();
}

try {
    // Only return templates marked as NSQF Course (legacy rows may have NULL nsqf_type)
    $base_sql = "SELECT id, course_name, eligibility, category
                 FROM nsqf_course_templates
                 WHERE is_active = 1
                 AND (nsqf_type = 'NSQF Course' OR nsqf_type IS NULL OR nsqf_type = '')";

    if ($category === 'Long Term NSQF') {
        $sql = $base_sql . " AND category IN ('Long Term NSQF', 'Skill Based (Long Term) >500 hrs', 'Skill Based (Long Term) Courses (> 500 hrs)')
                 ORDER BY course_name ASC";
        $stmt = $conn->prepare($sql);
    } elseif ($category === 'Short Term NSQF') {
        $sql = $base_sql . " AND category IN ('Short Term NSQF', 'Skill Based (Short Term) 90-500 hrs', 'Skill Based (Short Term) Courses (90-500 hrs)', 'Short Term / Digital Competency <=90 hrs', 'Short Term / Digital Competency Courses (<= 90 hrs)')
                 ORDER BY course_name ASC";
        $stmt = $conn->prepare($sql);
    } elseif ($category !== '' && $category !== 'NSQF') {
        $sql = $base_sql . " AND category = ? ORDER BY course_name ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt !== false) {
            $stmt->bind_param('s', $category);
        }
    } else {
        // Empty or 'NSQF' — return all active NSQF templates
        $sql = $base_sql . " ORDER BY course_name ASC";
        $stmt = $conn->prepare($sql);
    }

    if ($stmt === false) {
        include_once __DIR__ . '/../migrations/install_nsqf_templates.php';
        $sql = $base_sql . " ORDER BY course_name ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Could not prepare statement: ' . $conn->error);
        }
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
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching templates: ' . $e->getMessage()
    ]);
}

$conn->close();
