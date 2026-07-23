<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/activity_logger.php';

$studentId = $_SESSION['student_id'] ?? null;
$studentName = $_SESSION['student_name'] ?? null;

if ($studentId) {
    logActivity($conn, [
        'actor_type' => 'student',
        'actor_id' => (string) $studentId,
        'actor_name' => $studentName ?: $studentId,
        'action' => 'student_logout',
        'entity_type' => 'student',
        'entity_id' => (string) $studentId,
        'entity_name' => $studentName ?: $studentId,
        'description' => 'Student "' . ($studentName ?: $studentId) . '" logged out.',
    ]);
}

session_unset();
session_destroy();

header('Location: login.php');
exit;
