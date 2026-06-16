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

$account = resolveStudentAccount($conn, $studentId);
$aadhar = $account['aadhar'] ?? '';

$allSchemes = getSchemesForCourse($conn, $courseId);
$enrolledIds = $aadhar !== '' ? getEnrolledSchemeIdsForCourse($conn, $aadhar, $courseId) : [];

$enrolled = [];
$available = [];
foreach ($allSchemes as $sch) {
    $sid = (int)$sch['id'];
    $item = [
        'id' => $sid,
        'scheme_name' => $sch['scheme_name'],
        'scheme_code' => $sch['scheme_code'],
    ];
    if (in_array($sid, $enrolledIds, true)) {
        $enrolled[] = $item;
    } else {
        $available[] = $item;
    }
}

$hasNullEnrollment = in_array(0, $enrolledIds, true);

echo json_encode([
    'success' => true,
    'course_name' => $courseName,
    'requires_scheme' => !empty($allSchemes),
    'can_enroll_without_scheme' => empty($allSchemes) && !$hasNullEnrollment,
    'already_enrolled_null' => $hasNullEnrollment,
    'enrolled_schemes' => $enrolled,
    'schemes' => $available,
]);
