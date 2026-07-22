<?php
/**
 * Download blank printable workshop registration form (physical / offline).
 * Optional: ?token=... or ?course=CODE — auto-fills course name, code, training centre.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/course_public_display.php';
require_once __DIR__ . '/../includes/render_workshop_blank_form_pdf.php';

$courseName = null;
$courseCode = null;
$trainingCentre = null;

$token = normalizeRegistrationToken((string) ($_GET['token'] ?? ''));
$legacy = trim((string) ($_GET['course'] ?? ''));
if ($token !== '' || $legacy !== '') {
    $course = loadCourseByRegistrationParam($conn, $token, $legacy);
    if ($course) {
        $courseName = (string) ($course['course_name'] ?? '');
        $courseCode = (string) ($course['course_code'] ?? '');
        $trainingCentre = (string) ($course['training_center'] ?? '');
    }
}

outputWorkshopBlankRegistrationPdf($courseName, $courseCode, 'D', $trainingCentre);
exit;
