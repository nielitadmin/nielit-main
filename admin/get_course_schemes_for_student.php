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
    echo json_encode(['success' => false, 'message' => 'Invalid parameters', 'schemes' => []]);
    exit;
}

$account = resolveStudentAccount($conn, $studentId);
$aadhar = $account['aadhar'] ?? '';

$allSchemes = getSchemesForCourse($conn, $courseId);
$enrolled = $aadhar !== '' ? getEnrolledSchemeIdsForCourse($conn, $aadhar, $courseId) : [];

$available = [];
foreach ($allSchemes as $sch) {
    $sid = (int)$sch['id'];
    if (!in_array($sid, $enrolled, true)) {
        $available[] = [
            'id' => $sid,
            'scheme_name' => $sch['scheme_name'],
            'scheme_code' => $sch['scheme_code'],
        ];
    }
}

$requiresScheme = !empty($allSchemes);
$canEnrollWithoutScheme = empty($allSchemes) && !in_array(0, $enrolled, true);

echo json_encode([
    'success' => true,
    'requires_scheme' => $requiresScheme,
    'can_enroll_without_scheme' => $canEnrollWithoutScheme,
    'already_enrolled_null' => in_array(0, $enrolled, true),
    'schemes' => $available,
]);
