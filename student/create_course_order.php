<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/payment_gateway_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in to the student portal.']);
    exit;
}

$studentCode = (string) $_SESSION['student_id'];
$courseId = (int) ($_POST['course_id'] ?? 0);
$recordId = (int) ($_POST['record_id'] ?? 0);

$owns = $conn->prepare("SELECT id, name, email, mobile FROM students WHERE student_id = ? AND course_id = ? AND LOWER(COALESCE(status,'')) NOT IN ('rejected','inactive') ORDER BY id DESC LIMIT 1");
if (!$owns) {
    echo json_encode(['ok' => false, 'error' => 'Could not check this enrollment.']);
    exit;
}
$owns->bind_param('si', $studentCode, $courseId);
$owns->execute();
$student = $owns->get_result()->fetch_assoc();
$owns->close();
if (!$student) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'This course is not on your application.']);
    exit;
}

$phone = trim((string) ($student['mobile'] ?? ''));
$provider = strtolower(trim((string) ($_POST['gateway'] ?? 'razorpay')));
$result = payment_start_checkout(
    $conn,
    $provider,
    $studentCode,
    $recordId > 0 ? $recordId : (int) $student['id'],
    $courseId,
    (string) ($student['name'] ?? ''),
    (string) ($student['email'] ?? ''),
    $phone
);
if (empty($result['ok'])) {
    http_response_code(422);
}
echo json_encode($result);
