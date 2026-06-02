<?php
/**
 * Check if course code or abbreviation already exists
 * Returns JSON response
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['code']) || !isset($input['abbreviation'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

$code = strtoupper(trim($input['code']));
$abbreviation = strtoupper(trim($input['abbreviation']));
$exclude_id = isset($input['exclude_id']) ? intval($input['exclude_id']) : null;

// Check if code or abbreviation exists
$query = "SELECT id, course_name, course_code, course_abbreviation 
          FROM courses 
          WHERE (UPPER(TRIM(course_code)) = ? OR UPPER(TRIM(course_abbreviation)) = ?)";

if ($exclude_id) {
    $query .= " AND id != ?";
}

$query .= " LIMIT 1";

$stmt = $conn->prepare($query);

if ($exclude_id) {
    $stmt->bind_param("ssi", $code, $abbreviation, $exclude_id);
} else {
    $stmt->bind_param("ss", $code, $abbreviation);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'exists' => true,
        'course_id' => $row['id'],
        'course_name' => $row['course_name'],
        'course_code' => $row['course_code'],
        'course_abbreviation' => $row['course_abbreviation']
    ]);
} else {
    echo json_encode([
        'exists' => false
    ]);
}

$stmt->close();
$conn->close();
?>
