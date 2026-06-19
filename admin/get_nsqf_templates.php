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

$category_options_file = __DIR__ . '/../includes/course_category_options.php';
if (file_exists($category_options_file)) {
    require_once $category_options_file;
}

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$category = trim($_GET['category'] ?? '');

/**
 * Map a requested category to all equivalent DB values (current + legacy labels).
 */
function nsqf_template_category_values($category) {
    $aliases = [
        'Degree / Diploma / PG' => ['Degree / Diploma / PG'],
        'Skill Based (Long Term) >500 hrs' => [
            'Skill Based (Long Term) >500 hrs',
            'Long Term NSQF',
            'Skill Based (Long Term) Courses (> 500 hrs)',
        ],
        'Skill Based (Short Term) 90-500 hrs' => [
            'Skill Based (Short Term) 90-500 hrs',
            'Skill Based (Short Term) Courses (90-500 hrs)',
        ],
        'Short Term / Digital Competency <=90 hrs' => [
            'Short Term / Digital Competency <=90 hrs',
            'Short Term / Digital Competency Courses (<= 90 hrs)',
            'Short Term NSQF',
        ],
        'NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)' => [
            'NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)',
            'NIELIT HQ Digital Literacy Courses (CCC/ECC/BCC/ACC)',
        ],
        'Long Term NSQF' => [
            'Long Term NSQF',
            'Skill Based (Long Term) >500 hrs',
            'Skill Based (Long Term) Courses (> 500 hrs)',
        ],
        'Short Term NSQF' => [
            'Short Term NSQF',
            'Skill Based (Short Term) 90-500 hrs',
            'Skill Based (Short Term) Courses (90-500 hrs)',
            'Short Term / Digital Competency <=90 hrs',
            'Short Term / Digital Competency Courses (<= 90 hrs)',
        ],
        'NSQF' => [],
    ];

    if ($category === '' || $category === 'NSQF') {
        return [];
    }

    if (isset($aliases[$category])) {
        return $aliases[$category];
    }

    return [$category];
}

try {
    // Return all active NSQF templates unless explicitly marked Non-NSQF
    $base_sql = "SELECT id, course_name, eligibility, category, nsqf_type
                 FROM nsqf_course_templates
                 WHERE is_active = 1
                 AND (
                     nsqf_type IS NULL
                     OR TRIM(nsqf_type) = ''
                     OR LOWER(TRIM(nsqf_type)) = 'nsqf course'
                 )";

    $category_values = nsqf_template_category_values($category);

    if (!empty($category_values)) {
        $placeholders = implode(',', array_fill(0, count($category_values), '?'));
        $sql = $base_sql . " AND category IN ($placeholders) ORDER BY course_name ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Could not prepare statement: ' . $conn->error);
        }
        $types = str_repeat('s', count($category_values));
        $stmt->bind_param($types, ...$category_values);
    } else {
        $sql = $base_sql . " ORDER BY course_name ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            include_once __DIR__ . '/../migrations/install_nsqf_templates.php';
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Could not prepare statement: ' . $conn->error);
            }
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $templates[] = [
            'id' => $row['id'],
            'course_name' => $row['course_name'],
            'eligibility' => $row['eligibility'],
            'category' => $row['category'],
        ];
    }

    echo json_encode([
        'success' => true,
        'category' => $category,
        'templates' => $templates,
        'count' => count($templates),
        'api_version' => '2026-06-05-v2',
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching templates: ' . $e->getMessage()
    ]);
}

$conn->close();
