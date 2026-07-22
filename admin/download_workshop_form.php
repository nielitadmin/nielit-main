<?php
/**
 * Admin: download blank physical workshop/awareness registration PDF
 * with course fields filled from the course record.
 *
 * GET ?course_id=123
 */
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/render_workshop_blank_form_pdf.php';

if (!isset($_SESSION['admin'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit();
}

$courseId = (int) ($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: dashboard.php');
    exit();
}

$stmt = $conn->prepare(
    'SELECT course_name, course_code, training_center, course_description, start_date, end_date
     FROM courses WHERE id = ? LIMIT 1'
);
if (!$stmt) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo 'Database error.';
    exit();
}
$stmt->bind_param('i', $courseId);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: dashboard.php');
    exit();
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$courseName = (string) ($course['course_name'] ?? '');
$courseCode = (string) ($course['course_code'] ?? '');
$trainingCentre = (string) ($course['training_center'] ?? 'NIELIT Bhubaneswar');
if (trim($trainingCentre) === '') {
    $trainingCentre = 'NIELIT Bhubaneswar';
}
$courseDescription = (string) ($course['course_description'] ?? '');
$startDate = (string) ($course['start_date'] ?? '');
$endDate = (string) ($course['end_date'] ?? '');

outputWorkshopBlankRegistrationPdf(
    $courseName,
    $courseCode,
    'D',
    $trainingCentre,
    $courseDescription,
    $startDate,
    $endDate
);
exit;
