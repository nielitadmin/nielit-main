<?php
/**
 * Load student profile row + all active enrollment rows for portal pages.
 * Expects $conn and $student_id to be set by the including script.
 */
require_once __DIR__ . '/../../includes/multi_course_helper.php';

$student_enrollments = getEnrollmentsForStudentId($conn, $student_id);
$student_enrollment_rows = [];
$student_profile = null;

$rowSql = "SELECT s.*, c.course_name, c.duration, c.fees AS catalog_fees,
                  sch.scheme_name, sch.scheme_code,
                  b.batch_name, b.batch_code
           FROM students s
           LEFT JOIN courses c ON c.id = s.course_id
           LEFT JOIN schemes sch ON sch.id = s.scheme_id
           LEFT JOIN batches b ON b.id = s.batch_id
           WHERE s.student_id = ?
           AND LOWER(COALESCE(s.status, '')) NOT IN ('rejected', 'inactive')
           ORDER BY c.course_name ASC, sch.scheme_name ASC, s.id DESC";

$rowStmt = $conn->prepare($rowSql);
if ($rowStmt) {
    $rowStmt->bind_param('s', $student_id);
    $rowStmt->execute();
    $rowResult = $rowStmt->get_result();
    while ($row = $rowResult->fetch_assoc()) {
        $student_enrollment_rows[] = $row;
    }
    $rowStmt->close();
}

if (!empty($student_enrollment_rows)) {
    $student_profile = $student_enrollment_rows[0];
} else {
    $fallbackStmt = $conn->prepare('SELECT * FROM students WHERE student_id = ? ORDER BY id DESC LIMIT 1');
    if ($fallbackStmt) {
        $fallbackStmt->bind_param('s', $student_id);
        $fallbackStmt->execute();
        $student_profile = $fallbackStmt->get_result()->fetch_assoc();
        $fallbackStmt->close();
    }
}
