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

function nsqf_templates_table_has_column($conn, $column) {
    static $cache = [];
    if (!isset($cache[$column])) {
        $safe_column = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM nsqf_course_templates LIKE '{$safe_column}'");
        $cache[$column] = $result && $result->num_rows > 0;
    }
    return $cache[$column];
}

function nsqf_template_category_values($category) {
    if (function_exists('get_equivalent_template_categories')) {
        return get_equivalent_template_categories($category);
    }

    return ($category === '' || $category === 'NSQF') ? [] : [$category];
}

function fetch_nsqf_templates($conn, array $category_values = []) {
    $conditions = [];

    if (nsqf_templates_table_has_column($conn, 'is_active')) {
        $conditions[] = '(is_active = 1 OR is_active IS NULL)';
    }

    if (nsqf_templates_table_has_column($conn, 'nsqf_type')) {
        $conditions[] = "(
            nsqf_type IS NULL
            OR TRIM(nsqf_type) = ''
            OR LOWER(TRIM(nsqf_type)) IN ('nsqf course', 'nsqf')
        )";
    }

    $sql = 'SELECT id, course_name, eligibility, category, nsqf_type FROM nsqf_course_templates';
    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $params = [];
    $types = '';

    if (!empty($category_values)) {
        $placeholders = implode(',', array_fill(0, count($category_values), '?'));
        $sql .= empty($conditions) ? ' WHERE ' : ' AND ';
        $sql .= "category IN ($placeholders)";
        $params = $category_values;
        $types = str_repeat('s', count($category_values));
    }

    $sql .= ' ORDER BY course_name ASC';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Could not prepare statement: ' . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
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

    $stmt->close();
    return $templates;
}

try {
    $category_values = nsqf_template_category_values($category);
    $templates = fetch_nsqf_templates($conn, $category_values);
    $used_fallback = false;

    // If category filter excluded everything, return all active NSQF templates
    if (empty($templates) && !empty($category_values)) {
        $templates = fetch_nsqf_templates($conn, []);
        $used_fallback = !empty($templates);
    }

    echo json_encode([
        'success' => true,
        'category' => $category,
        'templates' => $templates,
        'count' => count($templates),
        'used_fallback' => $used_fallback,
        'api_version' => '2026-10-06-v3',
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching templates: ' . $e->getMessage(),
    ]);
}

$conn->close();
