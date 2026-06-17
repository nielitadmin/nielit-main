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
$recordId = (int)($_GET['student_record_id'] ?? 0);

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
$enrolledById = [];
foreach ($enrolled as $row) {
    $enrolledById[(int)$row['id']] = $row;
}

$courseSchemes = [];
foreach ($allSchemes as $sch) {
    $sid = (int)$sch['id'];
    $courseSchemes[] = [
        'id' => $sid,
        'scheme_name' => $sch['scheme_name'],
        'scheme_code' => $sch['scheme_code'],
        'enrolled' => isset($enrolledById[$sid]),
    ];
}

$orphanCount = 0;
$orphanStmt = $conn->prepare("SELECT COUNT(*) AS c FROM students
    WHERE student_id = ? AND course_id = ?
    AND scheme_id IS NULL
    AND LOWER(status) NOT IN ('rejected', 'inactive')");
if ($orphanStmt) {
    $orphanStmt->bind_param('si', $studentId, $courseId);
    $orphanStmt->execute();
    $orphanCount = (int)($orphanStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $orphanStmt->close();
}

$currentRowSchemeId = 0;
if ($recordId > 0) {
    $rowStmt = $conn->prepare('SELECT scheme_id FROM students WHERE id = ? LIMIT 1');
    if ($rowStmt) {
        $rowStmt->bind_param('i', $recordId);
        $rowStmt->execute();
        $rowData = $rowStmt->get_result()->fetch_assoc();
        $rowStmt->close();
        $currentRowSchemeId = (int)($rowData['scheme_id'] ?? 0);
    }
}

echo json_encode([
    'success' => true,
    'course_name' => $courseName,
    'course_schemes' => $courseSchemes,
    'enrolled_schemes' => array_values(array_map(function ($row) {
        return [
            'id' => (int)$row['id'],
            'scheme_name' => $row['scheme_name'],
            'scheme_code' => $row['scheme_code'],
        ];
    }, $enrolled)),
    'schemes' => array_values(array_map(function ($row) {
        return [
            'id' => (int)$row['id'],
            'scheme_name' => $row['scheme_name'],
            'scheme_code' => $row['scheme_code'],
        ];
    }, array_filter($courseSchemes, function ($row) {
        return empty($row['enrolled']);
    }))),
    'requires_scheme' => !empty($allSchemes),
    'can_enroll_without_scheme' => empty($allSchemes),
    'already_enrolled_null' => $orphanCount > 0 && empty($enrolled),
    'orphan_row_count' => $orphanCount,
    'current_row_scheme_id' => $currentRowSchemeId,
]);
