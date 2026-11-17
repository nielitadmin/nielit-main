<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session_manager.php';
require_once __DIR__ . '/../../includes/nsqf_exam_results_helper.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$role = (string) ($_SESSION['admin_role'] ?? '');
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

if (!canManageNsqfExamResults($role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

$action = strtolower(trim((string) ($_POST['action'] ?? '')));
$courseId = (int) ($_POST['course_id'] ?? 0);
$batchId = (int) ($_POST['batch_id'] ?? 0);

if ($courseId > 0 && !nsqf_exam_user_can_access_course($conn, $role, $adminId, $courseId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You cannot access this course.']);
    exit();
}

ensureNsqfExamResultsSchema($conn);

switch ($action) {
    case 'save_components':
        $raw = $_POST['components'] ?? '';
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            echo json_encode(['success' => false, 'message' => 'Invalid components payload.']);
            exit();
        }
        $n = nsqf_save_course_exam_components($conn, $courseId, $decoded);
        echo json_encode(['success' => true, 'message' => 'Exam structure saved.', 'count' => $n]);
        break;

    case 'seed_olevel':
        $n = nsqf_seed_olevel_components_if_empty($conn, $courseId);
        if ($n === 0 && nsqf_get_course_exam_components($conn, $courseId) !== []) {
            echo json_encode(['success' => true, 'message' => 'O-Level modules (M1–M4) already exist.', 'count' => 0]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Applied default O-Level modules M1–M4.', 'count' => $n]);
        }
        break;

    case 'save_result':
        $batchStudentId = (int) ($_POST['batch_student_id'] ?? 0);
        $componentId = (int) ($_POST['component_id'] ?? 0);
        $status = (string) ($_POST['result_status'] ?? 'exam_not_applied');
        $result = nsqf_save_batch_student_component_result(
            $conn,
            $batchId,
            $batchStudentId,
            $componentId,
            $status,
            $adminId,
            true
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    case 'bulk_save_column':
        $componentId = (int) ($_POST['component_id'] ?? 0);
        $status = nsqf_normalize_exam_result_status($_POST['result_status'] ?? 'exam_not_applied');
        $students = nsqf_get_batch_students_for_exam($conn, $batchId);
        $ok = 0;
        foreach ($students as $stu) {
            $bsId = (int) ($stu['batch_student_id'] ?? 0);
            if ($bsId <= 0) {
                continue;
            }
            $r = nsqf_save_batch_student_component_result($conn, $batchId, $bsId, $componentId, $status, $adminId, false);
            if (!empty($r['success'])) {
                $ok++;
            }
        }
        foreach ($students as $stu) {
            $bsId = (int) ($stu['batch_student_id'] ?? 0);
            if ($bsId > 0) {
                nsqf_sync_overall_result_from_modules($conn, $batchId, $bsId, $adminId);
            }
        }
        echo json_encode(['success' => true, 'message' => "Updated {$ok} student(s) for this module.", 'updated' => $ok]);
        break;

    case 'sync_all_overall':
        $students = nsqf_get_batch_students_for_exam($conn, $batchId);
        foreach ($students as $stu) {
            $bsId = (int) ($stu['batch_student_id'] ?? 0);
            if ($bsId > 0) {
                nsqf_sync_overall_result_from_modules($conn, $batchId, $bsId, $adminId);
            }
        }
        echo json_encode(['success' => true, 'message' => 'Overall batch result status synced from modules.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}