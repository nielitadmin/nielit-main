<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../batch_module/includes/batch_certificate_helper.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$certificate_id = (int) ($_GET['id'] ?? 0);
$cert = getCertificateForStudent($conn, $certificate_id, $student_id);

if (!$cert) {
    http_response_code(404);
    echo 'Certificate not found.';
    exit;
}

$absolutePath = batch_certificate_absolute_path($cert['file_path'] ?? '');
if ($absolutePath === '') {
    http_response_code(404);
    echo 'Certificate file not found.';
    exit;
}

$ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
if ($ext === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="certificate.pdf"');
} elseif ($ext === 'jpg' || $ext === 'jpeg') {
    header('Content-Type: image/jpeg');
    header('Content-Disposition: inline; filename="certificate.jpg"');
} elseif ($ext === 'png') {
    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="certificate.png"');
} else {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: inline');
}

header('Content-Length: ' . filesize($absolutePath));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($absolutePath);
exit;
