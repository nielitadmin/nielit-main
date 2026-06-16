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

$courseId = (int)($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid course', 'schemes' => []]);
    exit;
}

$schemes = getSchemesForCourse($conn, $courseId);
$out = [];
foreach ($schemes as $sch) {
    $out[] = [
        'id' => (int)$sch['id'],
        'scheme_name' => $sch['scheme_name'],
        'scheme_code' => $sch['scheme_code'],
    ];
}

echo json_encode([
    'success' => true,
    'schemes' => $out,
    'requires_scheme' => !empty($out),
]);
