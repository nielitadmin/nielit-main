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
    $batches = inspectorGetTargetBatchesForRoster($conn, $courseId, $schemeId);
    $out = [];
    foreach ($batches as $b) {
        $out[] = [
            'id' => (int)$b['id'],
            'batch_name' => (string)($b['batch_name'] ?? ''),
            'batch_code' => (string)($b['batch_code'] ?? ''),
            'enrolled_count' => (int)($b['enrolled_count'] ?? 0),
            'seats_total' => (int)($b['seats_total'] ?? 0),
        ];
    }
    echo json_encode(['success' => true, 'batches' => $out]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
