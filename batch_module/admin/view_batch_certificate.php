<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/batch_functions.php';
require_once __DIR__ . '/../includes/batch_certificate_helper.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$batch_id = (int) ($_GET['batch_id'] ?? 0);
$student_record_id = (int) ($_GET['student_record_id'] ?? 0);
$download = !empty($_GET['download']);

if ($batch_id <= 0 || $student_record_id <= 0) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

$studentRow = batch_certificate_get_batch_student_row($conn, $batch_id, $student_record_id);
$filePath = batch_certificate_absolute_path($studentRow['certificate_file'] ?? '');

if ($filePath === '') {
    http_response_code(404);
    echo 'Certificate not found.';
    exit;
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';
$filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($studentRow['certificate_number'] ?? 'certificate')) . '.' . $ext;

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');

if ($download) {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
    header('Content-Disposition: inline; filename="' . $filename . '"');
}

readfile($filePath);
exit;
