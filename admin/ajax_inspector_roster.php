<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';
require_once __DIR__ . '/includes/student_inspector_roster.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin']) || ($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'preview_batch') {
    $batchId = (int)($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0);
    echo json_encode(inspectorGetBatchRosterPreview($conn, $batchId));
    exit;
}

if ($action === 'target_batches') {
    $courseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
    $schemeId = normalizeEnrollmentSchemeId($_GET['scheme_id'] ?? $_POST['scheme_id'] ?? null);
    $courseSchemes = getSchemesForCourse($conn, $courseId);
    $requiresScheme = !empty($courseSchemes);

    if ($courseId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid course.', 'batches' => []]);
        exit;
    }

    if ($requiresScheme && $schemeId === null) {
        echo json_encode([
            'success' => true,
            'requires_scheme' => true,
            'batches' => [],
            'message' => 'Select a scheme/project to load batches.',
        ]);
        exit;
    }

    $batches = inspectorGetTargetBatchesForRoster($conn, $courseId, $schemeId);
    $out = [];
    foreach ($batches as $b) {
        $out[] = [
            'id' => (int)$b['id'],
            'batch_name' => (string)($b['batch_name'] ?? ''),
            'batch_code' => (string)($b['batch_code'] ?? ''),
            'scheme_name' => (string)($b['scheme_name'] ?? ''),
            'needs_scheme_set' => !empty($b['needs_scheme_set']),
            'enrolled_count' => (int)($b['enrolled_count'] ?? 0),
            'seats_total' => (int)($b['seats_total'] ?? 0),
        ];
    }

    $message = '';
    if (empty($out)) {
        $message = 'No active batches found for this course'
            . ($schemeId ? ' and scheme' : '')
            . '. Create one in Manage Batches first.';
    }

    echo json_encode([
        'success' => true,
        'requires_scheme' => $requiresScheme,
        'batches' => $out,
        'message' => $message,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
