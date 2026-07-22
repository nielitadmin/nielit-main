<?php
/**
 * Download blank printable workshop registration form (physical / offline).
 * Optional: ?token=... or ?course=CODE — auto-fills course fields.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/course_public_display.php';
require_once __DIR__ . '/../includes/render_workshop_blank_form_pdf.php';

$courseName = null;
$courseCode = null;
$trainingCentre = null;
$courseDescription = null;
$startDate = null;
$endDate = null;
$eligibility = null;

$token = normalizeRegistrationToken((string) ($_GET['token'] ?? ''));
$legacy = trim((string) ($_GET['course'] ?? ''));
if ($token !== '' || $legacy !== '') {
    $course = loadCourseByRegistrationParam($conn, $token, $legacy);
    if ($course) {
        $courseName = (string) ($course['course_name'] ?? '');
        $courseCode = (string) ($course['course_code'] ?? '');
        $trainingCentre = (string) ($course['training_center'] ?? '');
        $courseDescription = (string) ($course['course_description'] ?? '');
        $startDate = (string) ($course['start_date'] ?? '');
        $endDate = (string) ($course['end_date'] ?? '');
        $eligibility = (string) ($course['eligibility'] ?? '');
    }
}

outputWorkshopBlankRegistrationPdf(
    $courseName,
    $courseCode,
    'D',
    $trainingCentre,
    $courseDescription,
    $startDate,
    $endDate,
    $eligibility
);
exit;
