<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/batch_result_helper.php';
require_once __DIR__ . '/../../includes/nsqf_exam_results_helper.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$role = (string) ($_SESSION['admin_role'] ?? '');
if (!canManageBatchResultStatus($role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to update results.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

$batchId = (int) ($_POST['batch_id'] ?? 0);
$batchStudentId = (int) ($_POST['batch_student_id'] ?? 0);
$componentId = (int) ($_POST['component_id'] ?? 0);
$status = (string) ($_POST['result_status'] ?? 'exam_not_applied');
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

ensureNsqfExamResultsSchema($conn);
$result = nsqf_save_batch_student_component_result(
    $conn,
    $batchId,
    $batchStudentId,
    $componentId,
    $status,
    $adminId,
    true
);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);