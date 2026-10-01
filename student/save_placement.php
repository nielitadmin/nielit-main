<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../batch_module/includes/batch_placement_helper.php';

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to update placement details.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$student_login_id = trim((string) $_SESSION['student_id']);
$batch_id = (int) ($_POST['batch_id'] ?? 0);
$student_record_id = (int) ($_POST['student_record_id'] ?? 0);

$result = saveStudentSelfPlacement($conn, $student_login_id, $batch_id, $student_record_id, $_POST);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
