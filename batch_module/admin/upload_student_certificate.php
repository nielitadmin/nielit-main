<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/batch_functions.php';
require_once __DIR__ . '/../includes/batch_certificate_helper.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
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

if ($batch_id <= 0 || $student_record_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid batch or student.']);
    exit();
}

if (!isset($_FILES['certificate_file'])) {
    echo json_encode(['success' => false, 'message' => 'Please choose a certificate file.']);
    exit();
}

$result = uploadBatchStudentCertificate($conn, $batch_id, $student_record_id, $_FILES['certificate_file'], $admin_id);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
