<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/qr_helper.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

// Get POST data
$course_id = (int) ($_POST['course_id'] ?? 0);
$course_code = trim((string) ($_POST['course_code'] ?? ''));
$force_regenerate = isset($_POST['force_regenerate']) && $_POST['force_regenerate'] == '1';

if ($course_code === '') {
    echo json_encode(['success' => false, 'message' => 'Course code is required']);
    exit();
}

if ($course_id <= 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Save the course first, then generate the link and QR code.',
        'apply_link' => null,
        'qr_code_path' => null,
    ]);
    exit();
}

$result = syncCourseRegistrationLinkAndQr($conn, $course_id, true);

echo json_encode([
    'success' => !empty($result['success']),
    'message' => $result['message'] ?? ($result['success'] ? 'Link and QR code synced.' : 'Sync failed'),
    'apply_link' => $result['apply_link'] ?? null,
    'qr_code_path' => $result['qr_code_path'] ?? null,
    'qr_code_url' => $result['qr_code_url'] ?? null,
]);

$conn->close();
