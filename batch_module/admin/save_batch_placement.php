<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/batch_placement_helper.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$role = $_SESSION['admin_role'] ?? '';
if (!canManageBatchPlacement($role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to update placements.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

$batch_id = (int) ($_POST['batch_id'] ?? 0);
$student_record_id = (int) ($_POST['student_record_id'] ?? 0);
$admin_id = (int) ($_SESSION['admin_id'] ?? 0);

$result = saveBatchStudentPlacement($conn, $batch_id, $student_record_id, $_POST, $admin_id);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
