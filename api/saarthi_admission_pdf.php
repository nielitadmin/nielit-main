<?php
/**
 * Saarthi Chat Admission Form PDF Download Endpoint
 * Streams pre-filled admission PDF using short-lived session token (for chat registrants)
 * or authenticated student/admin session.
 */
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/student_form_helpers.php';
require_once __DIR__ . '/../includes/render_student_admission_form_pdf.php';

$recordId = (int) ($_GET['record_id'] ?? 0);
$token = trim((string) ($_GET['token'] ?? ''));

if ($recordId <= 0) {
    http_response_code(400);
    echo 'Invalid record ID.';
    exit;
}

$authorized = false;
if (!empty($_SESSION['admin'])) {
    $authorized = true;
}

$student = fetchStudentEnrollmentByRecordId($conn, $recordId);
if (!$student) {
    http_response_code(404);
    echo 'Registration record not found.';
    exit;
}

if (!$authorized && !empty($_SESSION['student_id']) && enrollmentBelongsToStudentId($student, (string) $_SESSION['student_id'])) {
    $authorized = true;
}

if (!$authorized && $token !== '' && isset($_SESSION['saarthi_pdf_tokens'][$token])) {
    $grant = $_SESSION['saarthi_pdf_tokens'][$token];
    if ((int) ($grant['record_id'] ?? 0) === $recordId && (int) ($grant['expires'] ?? 0) >= time()) {
        $authorized = true;
    }
}

if (!$authorized) {
    http_response_code(403);
    echo 'Download link expired or unauthorized. Please log in to the Student Portal to download your Admission Form.';
    exit;
}

$educationRecords = fetchEducationRecordsForStudentId($conn, (string) ($student['student_id'] ?? ''));
renderStudentAdmissionFormPdf($student, $educationRecords, 'D');
exit;
