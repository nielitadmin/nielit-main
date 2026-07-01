<?php
/**
 * Admin-only: run scheme enrollment DB updates (replaces blocked /migrations/ URL).
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Scheme Enrollment Setup</title></head><body style="font-family:sans-serif;padding:2rem;max-width:640px;">';
echo '<h1>Scheme enrollment setup</h1>';

ensureSchemeEnrollmentUniqueIndex($conn);

$backfill = $conn->query(
    "UPDATE student_enrollments se
     INNER JOIN students s ON s.id = se.student_record_id
     SET se.scheme_id = s.scheme_id
     WHERE se.scheme_id IS NULL AND s.scheme_id IS NOT NULL"
);
$affected = $backfill ? $conn->affected_rows : 0;

echo '<p><strong>Done.</strong> Database is ready for multiple schemes per course.</p>';
echo '<p>Backfilled enrollment scheme links: ' . (int)$affected . ' row(s).</p>';
echo '<p><a href="students.php">Back to Students</a></p>';
echo '</body></html>';
