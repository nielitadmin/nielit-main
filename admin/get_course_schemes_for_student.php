<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$studentId = trim($_GET['student_id'] ?? '');
$courseId = (int)($_GET['course_id'] ?? 0);

if ($studentId === '' || $courseId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$courseName = '';
$courseStmt = $conn->prepare('SELECT course_name FROM courses WHERE id = ? LIMIT 1');
if ($courseStmt) {
    $courseStmt->bind_param('i', $courseId);
    $courseStmt->execute();
    $courseRow = $courseStmt->get_result()->fetch_assoc();
    $courseStmt->close();
    $courseName = $courseRow['course_name'] ?? '';
}

$allSchemes = getSchemesForCourse($conn, $courseId);
$enrolled = getEnrolledSchemesForStudentCourse($conn, $studentId, $courseId);
$enrolledIds = array_map(function ($row) {
    return (int)$row['id'];
}, $enrolled);

$available = [];
foreach ($allSchemes as $sch) {
    $sid = (int)$sch['id'];
    if (!in_array($sid, $enrolledIds, true)) {
        $available[] = [
            'id' => $sid,
            'scheme_name' => $sch['scheme_name'],
            'scheme_code' => $sch['scheme_code'],
        ];
    }
}

$hasNullEnrollment = false;
$nullStmt = $conn->prepare("SELECT id FROM students WHERE student_id = ? AND course_id = ? AND scheme_id IS NULL AND LOWER(status) NOT IN ('rejected') LIMIT 1");
if ($nullStmt) {
    $nullStmt->bind_param('si', $studentId, $courseId);
    $nullStmt->execute();
    $hasNullEnrollment = $nullStmt->get_result()->num_rows > 0;
    $nullStmt->close();
}

echo json_encode([
    'success' => true,
    'course_name' => $courseName,
    'requires_scheme' => !empty($allSchemes),
    'can_enroll_without_scheme' => empty($allSchemes) && !$hasNullEnrollment,
    'already_enrolled_null' => $hasNullEnrollment,
    'enrolled_schemes' => array_map(function ($row) {
        return [
            'id' => (int)$row['id'],
            'scheme_name' => $row['scheme_name'],
            'scheme_code' => $row['scheme_code'],
        ];
    }, $enrolled),
    'schemes' => $available,
]);
