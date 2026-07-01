<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/includes/student_inspector_directory.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin']) || ($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'batches') {
    $courseId = (int)($_GET['course_id'] ?? 0);
    $batches = inspectorGetDirectoryBatches($conn, $courseId);
    $out = [];
    foreach ($batches as $b) {
        $out[] = [
            'id' => (int)$b['id'],
            'batch_name' => (string)($b['batch_name'] ?? 'Batch'),
            'enrolled_count' => (int)($b['enrolled_count'] ?? 0),
        ];
    }
    echo json_encode(['success' => true, 'batches' => $out]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
