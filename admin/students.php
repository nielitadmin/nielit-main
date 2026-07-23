<?php
// Start session and include the database connection
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';
require_once __DIR__ . '/../batch_module/includes/batch_functions.php';

// Enable error reporting for debugging (remove in production)
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// Check if the admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Load active theme
$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);

// Ensure scheme enrollment DB index supports multiple schemes per course (no public migration URL needed)
if (function_exists('ensureSchemeEnrollmentUniqueIndex')) {
    ensureSchemeEnrollmentUniqueIndex($conn);
}
if (function_exists('backfillBatchStudentRecordIds')) {
    backfillBatchStudentRecordIds($conn);
}
if (function_exists('repairSoftRemovedEnrollments')) {
    repairSoftRemovedEnrollments($conn);
}
if (function_exists('repairEnrollmentStatusMismatch')) {
    repairEnrollmentStatusMismatch($conn);
}

// Role flags
$admin_role = $_SESSION['admin_role'] ?? '';
$is_course_coordinator = ($admin_role === 'course_coordinator');
$is_front_office = ($admin_role === 'front_office_desk');

$student_category_filter_options = ['General', 'OBC', 'SC', 'ST', 'EWS'];
$student_status_filter_options = ['pending', 'active', 'rejected'];

function studentsFilterRedirectParams(array $source): array {
    $params = [];
    if (!empty($source['filter_course']) && $source['filter_course'] !== 'All') {
        $params['filter_course'] = $source['filter_course'];
    }
    if (!empty($source['filter_gender']) && $source['filter_gender'] !== 'All') {
        $params['filter_gender'] = $source['filter_gender'];
    }
    if (!empty($source['filter_scheme']) && $source['filter_scheme'] !== 'All') {
        $params['filter_scheme'] = $source['filter_scheme'];
    }
    if (!empty($source['filter_category']) && $source['filter_category'] !== 'All') {
        $params['filter_category'] = $source['filter_category'];
    }
    if (!empty($source['filter_status']) && $source['filter_status'] !== 'All') {
        $params['filter_status'] = $source['filter_status'];
    }
    if (!empty($source['start_date'])) {
        $params['start_date'] = $source['start_date'];
    }
    if (!empty($source['end_date'])) {
        $params['end_date'] = $source['end_date'];
    }
    if (!empty($source['page']) && (int)$source['page'] > 1) {
        $params['page'] = (int)$source['page'];
    }
    if (!empty($source['per_page']) && (int)$source['per_page'] !== 25) {
        $params['per_page'] = (int)$source['per_page'];
    }
    return $params;
}

function adminStudentsPageUrl(array $queryParams = []): string {
    $url = relative_url('students.php');
    if (empty($queryParams)) {
        return $url;
    }
    return $url . '?' . http_build_query($queryParams);
}

function studentsRedirectFromSource(array $source): string {
    return adminStudentsPageUrl(studentsFilterRedirectParams($source));
}

// ─── HANDLE: Delete student ───────────────────────────────────────────────────
if (isset($_GET['delete_id'])) {
    if ($is_front_office) {
        $_SESSION['message'] = "Access denied. Front Office Desk cannot delete students.";
        $_SESSION['message_type'] = "danger";
        header('Location: ' . adminStudentsPageUrl());
        exit();
    }
    $delete_id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Student deleted from all courses successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting student: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    header('Location: ' . studentsRedirectFromSource($_GET));
    exit();
}

// ─── HANDLE: Approve student ──────────────────────────────────────────────────
if (isset($_GET['approve_id'])) {
    $approve_id = trim($_GET['approve_id'] ?? '');
    $admin_name = $_SESSION['admin'] ?? 'Admin';
    if ($approve_id === '') {
        $_SESSION['message'] = 'Invalid student ID.';
        $_SESSION['message_type'] = 'warning';
    } else {
        $result = adminApproveStudent($conn, $approve_id, $admin_name);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    header('Location: ' . studentsRedirectFromSource($_GET));
    exit();
}

// ─── HANDLE: De-approve student (return to pending) ───────────────────────────
if (isset($_GET['deapprove_id'])) {
    if ($is_front_office) {
        $_SESSION['message'] = 'Access denied. Front Office Desk cannot de-approve students.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . adminStudentsPageUrl());
        exit();
    }

    $deapprove_id = trim($_GET['deapprove_id'] ?? '');
    $admin_name = $_SESSION['admin'] ?? 'Admin';
    if ($deapprove_id === '') {
        $_SESSION['message'] = 'Invalid student ID.';
        $_SESSION['message_type'] = 'warning';
    } else {
        $result = adminDeapproveStudent($conn, $deapprove_id, $admin_name);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
    }
    header('Location: ' . studentsRedirectFromSource($_GET));
    exit();
}

// ─── HANDLE: Reject student (POST with reason) ────────────────────────────────
if (isset($_POST['reject_student'])) {
    $reject_id        = trim($_POST['reject_id'] ?? '');
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    $rejection_note   = trim($_POST['rejection_note']   ?? '');

    $result = adminRejectStudent($conn, $reject_id, $rejection_reason, $rejection_note, true);
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'warning' : 'danger';

    header('Location: ' . studentsRedirectFromSource($_POST));
    exit();
}

// ─── HANDLE: Reject student (legacy GET) ─────────────────────────────────────
if (isset($_GET['reject_id'])) {
    $reject_id = trim($_GET['reject_id'] ?? '');
    $result = adminRejectStudent($conn, $reject_id, 'Registration rejected by administrator', '', true);
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'warning' : 'danger';
    header('Location: ' . studentsRedirectFromSource($_GET));
    exit();
}

// ─── HANDLE: Update student ───────────────────────────────────────────────────
if (isset($_POST['update_student'])) {
    $student_id  = $_POST['student_id'];
    $name        = $_POST['name'];
    $father_name = $_POST['father_name'];
    $mother_name = $_POST['mother_name'];
    $dob         = $_POST['dob'];
    $mobile      = $_POST['mobile'];
    $email       = $_POST['email'];
    $course      = $_POST['course'];
    $status      = $_POST['status'];
    $address     = $_POST['address'];
    $city        = $_POST['city'];
    $state       = $_POST['state'];
    $pincode     = $_POST['pincode'];

    // FIX: student_id is a string → use 's' not 'i' for the last bind param
    $stmt = $conn->prepare("UPDATE students SET 
        name=?, father_name=?, mother_name=?, dob=?, mobile=?, email=?,
        course=?, status=?, address=?, city=?, state=?, pincode=?
        WHERE student_id=?");
    $stmt->bind_param("sssssssssssss",
        $name, $father_name, $mother_name, $dob, $mobile, $email,
        $course, $status, $address, $city, $state, $pincode, $student_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Student updated successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating student: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    header('Location: ' . adminStudentsPageUrl());
    exit();
}

// ─── HANDLE: Assign batch (single or multi-scheme enrollment rows) ───────────
if (isset($_POST['assign_batch'])) {
    $admin_name   = $_SESSION['admin'] ?? 'Admin';
    $assignments  = $_POST['batch_assignments'] ?? [];
    if (!is_array($assignments)) {
        $assignments = [];
    }

    if (empty($assignments)) {
        $legacy_record = (int)($_POST['student_record_id'] ?? 0);
        $legacy_batch  = (int)($_POST['batch_id'] ?? 0);
        if ($legacy_record > 0 && $legacy_batch > 0) {
            $assignments[$legacy_record] = $legacy_batch;
        }
    }

    $success_count = 0;
    $error_messages = [];
    foreach ($assignments as $record_id => $batch_id) {
        $record_id = (int)$record_id;
        $batch_id  = (int)$batch_id;
        if ($record_id <= 0 || $batch_id <= 0) {
            continue;
        }
        $result = assignEnrollmentToBatch($conn, $record_id, $batch_id, $admin_name);
        if ($result['success']) {
            $success_count++;
        } else {
            $error_messages[] = $result['message'];
        }
    }

    if ($success_count > 0) {
        $_SESSION['message'] = $success_count . ' batch assignment(s) saved successfully.';
        if (!empty($error_messages)) {
            $_SESSION['message'] .= ' ' . $error_messages[0];
        }
        $_SESSION['message_type'] = !empty($error_messages) ? 'warning' : 'success';
    } elseif (!empty($error_messages)) {
        $_SESSION['message'] = $error_messages[0];
        $_SESSION['message_type'] = 'danger';
    } else {
        $_SESSION['message'] = 'Please select at least one batch.';
        $_SESSION['message_type'] = 'warning';
    }

    header('Location: ' . studentsRedirectFromSource($_POST));
    exit();
}

// ─── HANDLE: Bulk assign batch ────────────────────────────────────────────────
if (isset($_POST['bulk_assign_batch'])) {
    $student_record_ids = $_POST['student_record_ids'] ?? [];
    $batch_id           = (int)($_POST['batch_id'] ?? 0);
    $admin_name         = $_SESSION['admin'] ?? 'Admin';

    if ($batch_id > 0 && !empty($student_record_ids)) {
        $success_count = 0;
        $error_count   = 0;
        foreach ($student_record_ids as $rid) {
            $result = assignEnrollmentToBatch($conn, (int)$rid, $batch_id, $admin_name);
            $result['success'] ? $success_count++ : $error_count++;
        }
        $_SESSION['message']      = "$success_count student(s) assigned to batch successfully!";
        $_SESSION['message_type'] = $error_count > 0 ? 'warning' : 'success';
        if ($error_count > 0) {
            $_SESSION['message'] .= " $error_count student(s) failed to assign.";
        }
    } else {
        $_SESSION['message']      = 'Please select students and a batch.';
        $_SESSION['message_type'] = 'warning';
    }

    header('Location: ' . studentsRedirectFromSource($_POST));
    exit();
}

// ─── HANDLE: Bulk approve students ───────────────────────────────────────────
if (isset($_POST['bulk_approve_students'])) {
    if ($is_front_office) {
        $_SESSION['message'] = 'Access denied. Front Office Desk cannot approve students.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . studentsRedirectFromSource($_POST));
        exit();
    }

    $studentIds = $_POST['bulk_approve_student_ids'] ?? [];
    if (!is_array($studentIds)) {
        $studentIds = [];
    }
    $studentIds = array_values(array_unique(array_filter(array_map('trim', $studentIds))));
    $admin_name = $_SESSION['admin'] ?? 'Admin';

    if ($studentIds === []) {
        $_SESSION['message']      = 'Please select at least one student.';
        $_SESSION['message_type'] = 'warning';
        header('Location: ' . studentsRedirectFromSource($_POST));
        exit();
    }

    $approved = 0;
    $skipped = 0;
    $failed = [];

    foreach ($studentIds as $studentIdStr) {
        $result = adminApproveStudent($conn, $studentIdStr, $admin_name);
        if (!empty($result['success'])) {
            $approved++;
            continue;
        }
        $msg = (string)($result['message'] ?? 'Approval failed.');
        if (stripos($msg, 'already') !== false || stripos($msg, 'not found') !== false) {
            $skipped++;
            continue;
        }
        $failed[] = $studentIdStr . ': ' . $msg;
    }

    $parts = [];
    if ($approved > 0) {
        $parts[] = $approved . ' student(s) approved';
    }
    if ($skipped > 0) {
        $parts[] = $skipped . ' skipped (already active or not pending)';
    }
    if ($failed !== []) {
        $parts[] = count($failed) . ' failed';
    }

    $message = $parts !== [] ? implode('. ', $parts) . '.' : 'No students were approved.';
    if ($failed !== []) {
        $message .= ' ' . implode(' | ', array_slice($failed, 0, 3));
        if (count($failed) > 3) {
            $message .= ' …';
        }
    }

    $_SESSION['message']      = $message;
    $_SESSION['message_type'] = $approved > 0 ? 'success' : ($failed !== [] ? 'danger' : 'warning');

    header('Location: ' . studentsRedirectFromSource($_POST));
    exit();
}

// ─── HANDLE: Remove student from batch ───────────────────────────────────────
if (isset($_GET['remove_record']) && isset($_GET['batch_id'])) {
    $student_record_id = (int)$_GET['remove_record'];
    $batch_id          = (int)$_GET['batch_id'];
    $result = removeStudentFromBatch($student_record_id, $batch_id, $conn);
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';

    header('Location: ' . studentsRedirectFromSource($_GET));
    exit();
}

// ─── COORDINATOR: resolve admin_id & assigned courses ────────────────────────
$admin_courses    = [];
$admin_course_ids = [];
$admin_id         = $_SESSION['admin_id'] ?? null;

if ($is_course_coordinator) {
    if (!$admin_id && isset($_SESSION['admin'])) {
        $admin_username = $_SESSION['admin'];
        $tmp = $conn->prepare("SELECT id FROM admin WHERE username = ?");
        $tmp->bind_param("s", $admin_username);
        $tmp->execute();
        $tmp_result = $tmp->get_result();
        if ($row = $tmp_result->fetch_assoc()) {
            $admin_id = $row['id'];
            $_SESSION['admin_id'] = $admin_id;
        }
        $tmp->close();
    }

    if ($admin_id) {
        $tmp = $conn->prepare("SELECT c.id, c.course_name
                               FROM admin_course_assignments aca
                               JOIN courses c ON aca.course_id = c.id
                               WHERE aca.admin_id = ? AND aca.is_active = 1");
        $tmp->bind_param("i", $admin_id);
        $tmp->execute();
        $tmp_result = $tmp->get_result();
        while ($r = $tmp_result->fetch_assoc()) {
            $admin_courses[]    = $r['course_name'];
            $admin_course_ids[] = $r['id'];
        }
        $tmp->close();
    }
}

// ─── HANDLE: Remove student from one course only ─────────────────────────────
if (isset($_GET['remove_course_enrollment'])) {
    if ($is_front_office) {
        $_SESSION['message'] = 'Access denied. Front Office Desk cannot remove course enrollments.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . adminStudentsPageUrl());
        exit();
    }

    $remove_student_id = trim($_GET['student_id'] ?? '');
    $remove_course_id  = (int)($_GET['course_id'] ?? 0);

    if ($is_course_coordinator && !empty($admin_course_ids) && !in_array($remove_course_id, $admin_course_ids, true)) {
        $_SESSION['message'] = 'Access denied. You can only remove enrollments for your assigned courses.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . studentsRedirectFromSource($_GET));
        exit();
    }

    $result = adminRemoveStudentFromCourse($conn, $remove_student_id, $remove_course_id);
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';

    header('Location: ' . studentsRedirectFromSource($_GET));
    exit();
}

// ─── HANDLE: Bulk remove students from course ─────────────────────────────────
if (isset($_POST['bulk_remove_course_enrollment'])) {
    if ($is_front_office) {
        $_SESSION['message'] = 'Access denied. Front Office Desk cannot remove course enrollments.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . studentsRedirectFromSource($_POST));
        exit();
    }

    $studentIds = $_POST['bulk_remove_student_ids'] ?? [];
    $courseIds  = $_POST['bulk_remove_course_ids'] ?? [];
    if (!is_array($studentIds)) {
        $studentIds = [];
    }
    if (!is_array($courseIds)) {
        $courseIds = [];
    }

    $pairs = [];
    $pairCount = min(count($studentIds), count($courseIds));
    for ($i = 0; $i < $pairCount; $i++) {
        $studentIdStr = trim((string)$studentIds[$i]);
        $courseId = (int)$courseIds[$i];
        if ($studentIdStr === '' || $courseId <= 0) {
            continue;
        }
        $pairs[$studentIdStr . ':' . $courseId] = [
            'student_id' => $studentIdStr,
            'course_id' => $courseId,
        ];
    }

    if ($pairs === []) {
        $_SESSION['message']      = 'Please select at least one student.';
        $_SESSION['message_type'] = 'warning';
        header('Location: ' . studentsRedirectFromSource($_POST));
        exit();
    }

    $removed = 0;
    $skipped_access = 0;
    $failed = [];

    foreach ($pairs as $pair) {
        if ($is_course_coordinator && !empty($admin_course_ids) && !in_array($pair['course_id'], $admin_course_ids, true)) {
            $skipped_access++;
            continue;
        }

        $result = adminRemoveStudentFromCourse($conn, $pair['student_id'], $pair['course_id']);
        if (!empty($result['success'])) {
            $removed++;
            continue;
        }
        $failed[] = $pair['student_id'] . ': ' . ($result['message'] ?? 'Removal failed.');
    }

    $parts = [];
    if ($removed > 0) {
        $parts[] = $removed . ' student(s) removed from their course';
    }
    if ($skipped_access > 0) {
        $parts[] = $skipped_access . ' skipped (not your assigned course)';
    }
    if ($failed !== []) {
        $parts[] = count($failed) . ' failed';
    }

    $message = $parts !== [] ? implode('. ', $parts) . '.' : 'No students were removed.';
    if ($failed !== []) {
        $message .= ' ' . implode(' | ', array_slice($failed, 0, 3));
        if (count($failed) > 3) {
            $message .= ' …';
        }
    }

    $_SESSION['message']      = $message;
    $_SESSION['message_type'] = $removed > 0 ? 'success' : ($failed !== [] ? 'danger' : 'warning');

    header('Location: ' . studentsRedirectFromSource($_POST));
    exit();
}

// ─── STAT CARDS ───────────────────────────────────────────────────────────────
// Helper: run a COUNT query safely
function runCount($conn, $sql, $types = '', $values = []) {
    if (strpos($sql, '1=0') !== false) return 0; // short-circuit no-results
    $stmt = $conn->prepare($sql);
    if ($stmt === false) return 0;
    if (!empty($values)) {
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return (int)($row[array_key_first($row)] ?? 0);
}

if ($is_course_coordinator) {
    if (!empty($admin_course_ids)) {
        $ph = implode(',', array_fill(0, count($admin_course_ids), '?'));
        $types = str_repeat('i', count($admin_course_ids));

        $total_students_count  = runCount($conn,
            "SELECT COUNT(DISTINCT CONCAT(student_id, ':', course_id)) FROM students WHERE course_id IN ($ph) AND LOWER(status) != 'inactive'",
            $types, $admin_course_ids);

        $pending_students_count = runCount($conn,
            "SELECT COUNT(*) FROM students WHERE status='pending' AND course_id IN ($ph)",
            $types, $admin_course_ids);

        $active_students_count  = runCount($conn,
            "SELECT COUNT(*) FROM students WHERE status='active' AND course_id IN ($ph)",
            $types, $admin_course_ids);

        $rejected_students_count = runCount($conn,
            "SELECT COUNT(*) FROM students WHERE status='rejected' AND course_id IN ($ph)",
            $types, $admin_course_ids);
    } else {
        $total_students_count = $pending_students_count = $active_students_count = $rejected_students_count = 0;
    }
} else {
    $total_students_count   = runCount($conn, "SELECT COUNT(*) FROM students WHERE LOWER(status) != 'inactive'");
    $pending_students_count = runCount($conn, "SELECT COUNT(*) FROM students WHERE status='pending'");
    $active_students_count  = runCount($conn, "SELECT COUNT(*) FROM students WHERE status='active'");
    $rejected_students_count = runCount($conn, "SELECT COUNT(*) FROM students WHERE status='rejected'");
}

// ─── COURSES DROPDOWN ─────────────────────────────────────────────────────────
if ($is_course_coordinator && !empty($admin_course_ids)) {
    $course_ids_safe = implode(',', array_map('intval', $admin_course_ids));
    $sql_courses = "SELECT id, course_name, course_description FROM courses WHERE id IN ($course_ids_safe) ORDER BY course_name";
} else {
    $sql_courses = "SELECT id, course_name, course_description FROM courses ORDER BY course_name";
}
$courses_result = $conn->query($sql_courses);
// ─── CHECK batches.created_by column ─────────────────────────────────────────
$col_check = $conn->query("SHOW COLUMNS FROM batches LIKE 'created_by'");
$has_created_by_column = ($col_check && $col_check->num_rows > 0);

// ─── LOAD BATCHES (for assignment modals) ────────────────────────────────────
function loadBatches($conn, $is_course_coordinator, $admin_id, $has_created_by_column) {
    if ($is_course_coordinator && $admin_id && $has_created_by_column) {
        $stmt = $conn->prepare("SELECT b.*, c.course_name, s.scheme_name
                                FROM batches b
                                LEFT JOIN courses c ON b.course_id = c.id
                                LEFT JOIN schemes s ON s.id = b.scheme_id
                                WHERE LOWER(TRIM(b.status)) = 'active' AND b.created_by = ?
                                ORDER BY b.batch_name");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    return $conn->query("SELECT b.*, c.course_name, s.scheme_name
                         FROM batches b
                         LEFT JOIN courses c ON b.course_id = c.id
                         LEFT JOIN schemes s ON s.id = b.scheme_id
                         WHERE LOWER(TRIM(b.status)) = 'active'
                         ORDER BY b.batch_name");
}
$active_batches = [];
$batches_load   = loadBatches($conn, $is_course_coordinator, $admin_id, $has_created_by_column);
if ($batches_load) {
    while ($batch_row = $batches_load->fetch_assoc()) {
        $active_batches[] = $batch_row;
    }
}

// ─── FILTERS ──────────────────────────────────────────────────────────────────
$selected_course  = $_GET['filter_course']  ?? 'All';
$selected_gender  = $_GET['filter_gender']  ?? 'All';
$selected_scheme  = $_GET['filter_scheme']  ?? 'All';
$selected_category = $_GET['filter_category'] ?? 'All';
$selected_status   = $_GET['filter_status'] ?? 'All';
$start_date       = $_GET['start_date']     ?? '';
$end_date         = $_GET['end_date']       ?? '';

if ($selected_category !== 'All' && !in_array($selected_category, $student_category_filter_options, true)) {
    $selected_category = 'All';
}
if ($selected_status !== 'All' && !in_array(strtolower($selected_status), $student_status_filter_options, true)) {
    $selected_status = 'All';
} else {
    $selected_status = $selected_status === 'All' ? 'All' : strtolower($selected_status);
}

$allowed_per_page = [10, 25, 50, 100];
$per_page = (int)($_GET['per_page'] ?? 25);
if (!in_array($per_page, $allowed_per_page, true)) {
    $per_page = 25;
}
$page = max(1, (int)($_GET['page'] ?? 1));

/**
 * Build query-string params for students list links (filters + pagination).
 */
function studentsListQueryParams(
    $selected_course,
    $selected_gender,
    $selected_scheme,
    $selected_category,
    $selected_status,
    $start_date,
    $end_date,
    $page,
    $per_page,
    array $extra = []
): array {
    $params = [];
    if ($selected_course !== 'All') {
        $params['filter_course'] = $selected_course;
    }
    if ($selected_gender !== 'All') {
        $params['filter_gender'] = $selected_gender;
    }
    if ($selected_scheme !== 'All') {
        $params['filter_scheme'] = $selected_scheme;
    }
    if ($selected_category !== 'All') {
        $params['filter_category'] = $selected_category;
    }
    if ($selected_status !== 'All') {
        $params['filter_status'] = $selected_status;
    }
    if ($start_date !== '') {
        $params['start_date'] = $start_date;
    }
    if ($end_date !== '') {
        $params['end_date'] = $end_date;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }
    if ($per_page !== 25) {
        $params['per_page'] = $per_page;
    }
    return array_merge($params, $extra);
}

function studentsListUrl(
    $selected_course,
    $selected_gender,
    $selected_scheme,
    $selected_category,
    $selected_status,
    $start_date,
    $end_date,
    $page,
    $per_page,
    array $extra = []
): string {
    $params = studentsListQueryParams(
        $selected_course,
        $selected_gender,
        $selected_scheme,
        $selected_category,
        $selected_status,
        $start_date,
        $end_date,
        $page,
        $per_page,
        $extra
    );
    return adminStudentsPageUrl($params);
}

$filter_scheme_options = [];
if ($selected_course !== 'All') {
    $filter_scheme_options = getSchemesForCourse($conn, (int)$selected_course);
} else {
    $scheme_filter_res = $conn->query("SELECT id, scheme_name, scheme_code FROM schemes WHERE LOWER(status) = 'active' ORDER BY scheme_name");
    if ($scheme_filter_res) {
        while ($sch_row = $scheme_filter_res->fetch_assoc()) {
            $filter_scheme_options[] = $sch_row;
        }
    }
}

// ─── MAIN STUDENTS QUERY (paginated by student + course) ─────────────────────
$where_parts = ["LOWER(s.status) != 'inactive'"];
$bind_types  = '';
$bind_values = [];

if ($is_course_coordinator) {
    if (!empty($admin_course_ids)) {
        $ph = implode(',', array_fill(0, count($admin_course_ids), '?'));
        $where_parts[] = "s.course_id IN ($ph)";
        $bind_types   .= str_repeat('i', count($admin_course_ids));
        $bind_values   = array_merge($bind_values, $admin_course_ids);
    } else {
        $where_parts[] = '1=0';
    }
}

if ($selected_course !== 'All') {
    $where_parts[]  = 's.course_id = ?';
    $bind_types    .= 'i';
    $bind_values[]  = (int)$selected_course;
}

if ($selected_gender !== 'All') {
    $where_parts[]  = 's.gender = ?';
    $bind_types    .= 's';
    $bind_values[]  = $selected_gender;
}

if ($selected_scheme !== 'All') {
    if ($selected_scheme === 'none') {
        $where_parts[] = 's.scheme_id IS NULL';
    } else {
        $where_parts[]  = 's.scheme_id = ?';
        $bind_types    .= 'i';
        $bind_values[]  = (int)$selected_scheme;
    }
}

if ($selected_category !== 'All') {
    $where_parts[]  = 's.category = ?';
    $bind_types    .= 's';
    $bind_values[]  = $selected_category;
}

if ($selected_status !== 'All') {
    $where_parts[]  = 'LOWER(s.status) = ?';
    $bind_types    .= 's';
    $bind_values[]  = $selected_status;
}

if (!empty($start_date) && !empty($end_date)) {
    $where_parts[]  = 's.created_at BETWEEN ? AND ?';
    $bind_types    .= 'ss';
    $bind_values[]  = $start_date;
    $bind_values[]  = $end_date;
}

$where_sql = implode(' AND ', $where_parts);

$count_sql = "SELECT COUNT(*) AS total FROM (
    SELECT s.student_id, s.course_id
    FROM students s
    WHERE $where_sql
    GROUP BY s.student_id, s.course_id
) AS grouped_students";

$count_stmt = $conn->prepare($count_sql);
if ($count_stmt === false) {
    die('Database Error preparing count query: ' . $conn->error);
}
if ($bind_types !== '') {
    $count_stmt->bind_param($bind_types, ...$bind_values);
}
$count_stmt->execute();
$count_row = $count_stmt->get_result()->fetch_assoc();
$count_stmt->close();

$total_filtered = (int)($count_row['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_filtered / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;
$showing_from = $total_filtered > 0 ? $offset + 1 : 0;
$showing_to = min($offset + $per_page, $total_filtered);

$query = "SELECT s.*, b.batch_name, b.batch_code, c.course_name, sch.scheme_name, sch.scheme_code
          FROM (
              SELECT s.student_id, s.course_id, MAX(s.id) AS latest_id
              FROM students s
              WHERE $where_sql
              GROUP BY s.student_id, s.course_id
              ORDER BY MAX(s.created_at) DESC
              LIMIT ? OFFSET ?
          ) AS page_groups
          INNER JOIN students s ON s.id = page_groups.latest_id
          LEFT JOIN batches b ON s.batch_id = b.id
          LEFT JOIN courses c ON s.course_id = c.id
          LEFT JOIN schemes sch ON sch.id = s.scheme_id
          ORDER BY s.created_at DESC";

$paged_bind_types  = $bind_types . 'ii';
$paged_bind_values = array_merge($bind_values, [$per_page, $offset]);

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die('Database Error preparing main query: ' . $conn->error);
}
if ($paged_bind_types !== '') {
    $stmt->bind_param($paged_bind_types, ...$paged_bind_values);
}
if (!$stmt->execute()) {
    die('Execute failed: ' . $stmt->error);
}
$students_result = $stmt->get_result();
if ($students_result === false) {
    die('get_result() failed: ' . $stmt->error);
}
$students_result_count = $students_result->num_rows;
$stmt->close();

$list_query_suffix = studentsListQueryParams(
    $selected_course,
    $selected_gender,
    $selected_scheme,
    $selected_category,
    $selected_status,
    $start_date,
    $end_date,
    $page,
    $per_page
);
$filter_suffix = $list_query_suffix ? '&' . http_build_query($list_query_suffix) : '';

// ─── FILTER STATS (separate query — FIX: was overwriting $result) ───────────
$stats = ['total' => 0, 'active' => 0, 'male' => 0, 'female' => 0];

$stats_where_parts  = [];
$stats_bind_types   = '';
$stats_bind_values  = [];

$stats_status_filter = "LOWER(status) != 'inactive'";

if ($is_course_coordinator) {
    if (!empty($admin_course_ids)) {
        $ph = implode(',', array_map('intval', $admin_course_ids)); // safe int list
        $stats_where_parts[] = "course_id IN ($ph)";
        $stats_where_parts[] = $stats_status_filter;
    } else {
        $stats_where_parts[] = "1=0";
    }
} else {
    $stats_where_parts[] = $stats_status_filter;
}

if ($selected_course !== 'All') {
    $stats_where_parts[]  = "course_id = " . (int)$selected_course;
}
if ($selected_gender !== 'All') {
    $stats_where_parts[]  = "gender = '" . $conn->real_escape_string($selected_gender) . "'";
}
if ($selected_scheme !== 'All') {
    if ($selected_scheme === 'none') {
        $stats_where_parts[] = 'scheme_id IS NULL';
    } else {
        $stats_where_parts[] = 'scheme_id = ' . (int)$selected_scheme;
    }
}
if ($selected_category !== 'All') {
    $stats_where_parts[] = "category = '" . $conn->real_escape_string($selected_category) . "'";
}
if (!empty($start_date) && !empty($end_date)) {
    $stats_where_parts[]  = "created_at BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "'";
} elseif (!empty($start_date)) {
    $stats_where_parts[]  = "created_at >= '" . $conn->real_escape_string($start_date) . "'";
} elseif (!empty($end_date)) {
    $stats_where_parts[]  = "created_at <= '" . $conn->real_escape_string($end_date) . "'";
}

$stats_where = count($stats_where_parts) ? implode(' AND ', $stats_where_parts) : '1=1';
$stats_sql   = "SELECT COUNT(DISTINCT CONCAT(student_id, ':', course_id)) AS total,
                       COUNT(DISTINCT CASE WHEN LOWER(status) = 'active' THEN CONCAT(student_id, ':', course_id) END) AS active,
                       COUNT(DISTINCT CASE WHEN gender = 'Male' THEN CONCAT(student_id, ':', course_id) END) AS male,
                       COUNT(DISTINCT CASE WHEN gender = 'Female' THEN CONCAT(student_id, ':', course_id) END) AS female
                FROM students
                WHERE $stats_where";

$stats_res = $conn->query($stats_sql);
if ($stats_res) {
    $stats_row = $stats_res->fetch_assoc();
    $stats = [
        'total'  => (int)($stats_row['total']  ?? 0),
        'active' => (int)($stats_row['active'] ?? 0),
        'male'   => (int)($stats_row['male']   ?? 0),
        'female' => (int)($stats_row['female'] ?? 0),
    ];
}

$other_gender_count = max($stats['total'] - $stats['male'] - $stats['female'], 0);
$gender_chart_labels = ['Male', 'Female'];
$gender_chart_values = [$stats['male'], $stats['female']];
$gender_chart_colors = ['#0ea5e9', '#f97316'];

if ($other_gender_count > 0) {
    $gender_chart_labels[] = 'Other / Unspecified';
    $gender_chart_values[] = $other_gender_count;
    $gender_chart_colors[] = '#94a3b8';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .batch-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .batch-modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .batch-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        .batch-modal-header h3 { margin: 0; color: #1e293b; }
        .close-modal {
            font-size: 28px; font-weight: bold; color: #64748b;
            cursor: pointer; background: none; border: none;
            padding: 0; width: 30px; height: 30px;
            display: flex; align-items: center; justify-content: center;
        }
        .close-modal:hover { color: #e74c3c; }
        .batch-info { background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .batch-info p { margin: 5px 0; color: #475569; }
        .batch-info strong { color: #1e293b; }
        .overview-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.9fr);
            gap: 1.25rem;
            align-items: stretch;
        }
        .overview-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid #dbe4f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }
        .overview-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .overview-kicker {
            margin: 0 0 0.35rem;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
        }
        .overview-title {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
        }
        .overview-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            background: #e2e8f0;
            color: #334155;
            font-size: 0.88rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .chart-wrap {
            position: relative;
            max-width: 340px;
            height: 280px;
            margin: 0 auto;
        }
        .chart-center {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            text-align: center;
        }
        .chart-center-value {
            font-size: 2rem;
            line-height: 1;
            font-weight: 800;
            color: #0f172a;
        }
        .chart-center-label {
            margin-top: 0.25rem;
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
        }
        .metric-list {
            display: grid;
            gap: 0.8rem;
            margin-top: 1rem;
        }
        .metric-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.85rem 1rem;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        .metric-dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            flex: 0 0 auto;
        }
        .metric-copy {
            min-width: 0;
            flex: 1;
        }
        .metric-label {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
        }
        .metric-note {
            margin: 0.15rem 0 0;
            font-size: 0.8rem;
            color: #64748b;
        }
        .metric-value {
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem;
        }
        .summary-card {
            padding: 1rem;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }
        .summary-top {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.8rem;
        }
        .summary-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 1rem;
            flex: 0 0 auto;
        }
        .summary-value {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1;
            color: #0f172a;
        }
        .summary-label {
            margin: 0.25rem 0 0;
            font-size: 0.86rem;
            color: #64748b;
            font-weight: 600;
        }
        .summary-total .summary-icon { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
        .summary-active .summary-icon { background: linear-gradient(135deg, #10b981, #34d399); }
        .summary-male .summary-icon { background: linear-gradient(135deg, #0ea5e9, #38bdf8); }
        .summary-female .summary-icon { background: linear-gradient(135deg, #f97316, #fb7185); }
        .empty-chart-state {
            min-height: 280px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #64748b;
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            background: #ffffff;
        }
        @media (max-width: 992px) {
            .overview-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .overview-card {
                padding: 1rem;
                border-radius: 16px;
            }
            .overview-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .overview-pill {
                width: 100%;
                justify-content: center;
                white-space: normal;
                text-align: center;
            }
            .summary-grid { grid-template-columns: 1fr; }
            .summary-card { padding: 0.9rem; }
            .summary-value { font-size: 1.45rem; }
            .chart-wrap {
                width: min(100%, 260px);
                height: 220px;
            }
            .chart-center-value { font-size: 1.7rem; }
            .chart-center-label { font-size: 0.8rem; }
            .metric-item {
                align-items: flex-start;
                flex-wrap: wrap;
            }
            .metric-value {
                margin-left: auto;
            }
        }

        .students-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.5rem 1.25rem;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            background: #f8fafc;
        }

        .students-table-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
        }

        .content-card > .card-header {
            flex-wrap: wrap;
            gap: 12px;
        }

        .students-pagination-info {
            color: #64748b;
            font-size: 0.92rem;
            font-weight: 600;
        }

        .students-pagination-nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.45rem;
        }

        .students-pagination-ellipsis {
            color: #94a3b8;
            padding: 0 0.25rem;
            user-select: none;
        }

        /* Compact students list — readable at 100% zoom without horizontal sprawl */
        .students-list-card .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .students-table.modern-table {
            table-layout: fixed;
            width: 100%;
            min-width: 980px;
            font-size: 0.82rem;
        }

        .students-table.modern-table thead th {
            padding: 8px 8px;
            font-size: 0.68rem;
            letter-spacing: 0.04em;
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--light, #f8fafc);
        }

        .students-table.modern-table tbody td {
            padding: 7px 8px;
            vertical-align: middle;
        }

        .students-table .col-check { width: 36px; }
        .students-table .col-sl { width: 44px; }
        .students-table .col-photo { width: 44px; }
        .students-table .col-id { width: 132px; }
        .students-table .col-student { width: 168px; }
        .students-table .col-cat { width: 56px; }
        .students-table .col-course { width: 120px; }
        .students-table .col-scheme { width: 160px; }
        .students-table .col-batch { width: 110px; }
        .students-table .col-status { width: 88px; }
        .students-table .col-date { width: 88px; }
        .students-table .col-actions { width: 168px; }

        .student-photo-cell {
            text-align: center;
            vertical-align: middle;
        }

        .student-photo-thumb {
            width: 32px;
            height: 38px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid var(--border-color, #e2e8f0);
            background: var(--bg-secondary, #f1f5f9);
            display: inline-block;
            vertical-align: middle;
        }

        .student-photo-placeholder {
            width: 32px;
            height: 38px;
            border-radius: 5px;
            border: 1px dashed var(--border-color, #cbd5e1);
            background: var(--bg-secondary, #f8fafc);
            color: #94a3b8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            vertical-align: middle;
        }

        a.student-photo-link {
            display: inline-block;
            line-height: 0;
            cursor: zoom-in;
        }

        a.student-photo-link:hover .student-photo-thumb {
            box-shadow: 0 0 0 2px var(--primary-color, #0ea5e9);
        }

        .student-photo-lightbox {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10050;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: rgba(15, 23, 42, 0.72);
            cursor: zoom-out;
        }

        .student-photo-lightbox.is-open {
            display: flex;
        }

        .student-photo-lightbox-inner {
            position: relative;
            max-width: min(92vw, 520px);
            max-height: 88vh;
            text-align: center;
            cursor: default;
        }

        .student-photo-lightbox-inner img {
            display: block;
            max-width: 100%;
            max-height: min(78vh, 640px);
            width: auto;
            height: auto;
            margin: 0 auto;
            border-radius: 10px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45);
            background: #fff;
            object-fit: contain;
        }

        .student-photo-lightbox-caption {
            margin-top: 0.75rem;
            color: #f8fafc;
            font-size: 0.95rem;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.45);
        }

        .student-photo-lightbox-hint {
            margin-top: 0.35rem;
            color: rgba(248, 250, 252, 0.75);
            font-size: 0.78rem;
        }

        .student-photo-lightbox-close {
            position: absolute;
            top: -12px;
            right: -12px;
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 999px;
            background: #fff;
            color: #0f172a;
            font-size: 1.25rem;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        }

        .student-photo-lightbox-close:hover {
            background: #fee2e2;
            color: #b91c1c;
        }

        .student-id-cell {
            font-size: 0.74rem;
            line-height: 1.25;
            word-break: break-word;
        }

        .student-contact-cell .student-name {
            display: block;
            font-weight: 700;
            color: var(--text-primary, #0f172a);
            line-height: 1.25;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .student-contact-cell .student-meta {
            display: block;
            color: #64748b;
            font-size: 0.72rem;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .students-table .cell-clip {
            display: block;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .students-table .badge.cell-clip,
        .students-table .badge-clip {
            display: inline-block;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
            font-size: 0.72rem;
            padding: 0.28em 0.55em;
            margin: 1px 0;
            line-height: 1.25;
        }

        .students-actions-cell {
            min-width: 0;
            white-space: normal;
            vertical-align: middle;
        }

        .students-actions-cell .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
        }

        .students-actions-cell .btn {
            margin: 0;
            padding: 0.28rem 0.45rem;
            font-size: 0.72rem;
            line-height: 1.2;
        }

        .students-actions-cell .btn .btn-label {
            display: none;
        }

        .overview-grid {
            margin-bottom: 0.75rem;
        }

        .overview-card {
            padding: 1rem 1.15rem;
            border-radius: 14px;
        }

        .chart-wrap {
            max-width: 260px;
            height: 200px;
        }

        .chart-center-value {
            font-size: 1.55rem;
        }

        .stats-grid {
            gap: 0.85rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            padding: 0.85rem 1rem;
        }

        .stat-card .stat-value {
            font-size: 1.35rem;
        }

        @media (min-width: 1400px) {
            .students-table.modern-table {
                min-width: 0;
            }
        }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">

<div class="admin-wrapper">
    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-users"></i> Manage Students</h4>
                <?php if ($admin_role === 'master_admin'): ?>
                <p class="text-muted small mb-0">
                    Assign course &amp; schemes:
                    <a href="check_student_exists.php">Student Record Inspector</a>
                </p>
                <?php endif; ?>
                <small>View and manage all registered students</small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role">
                            <?php
                            if ($admin_role === 'master_admin')       echo 'Master Administrator';
                            elseif ($admin_role === 'front_office_desk')  echo 'Front Office Desk';
                            elseif ($admin_role === 'nsqf_course_manager') echo 'NSQF Course Manager';
                            else echo 'Course Coordinator';
                            ?>
                        </span>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="admin-main">

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <h3 class="stat-value"><?php echo $total_students_count; ?></h3>
                    <p class="stat-label"><?php echo $is_course_coordinator ? 'Students Available' : 'Total Students'; ?></p>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <h3 class="stat-value"><?php echo $pending_students_count; ?></h3>
                    <p class="stat-label">Pending Approval</p>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <h3 class="stat-value"><?php echo $active_students_count; ?></h3>
                    <p class="stat-label"><?php echo $is_course_coordinator ? 'Ready for Assignment' : 'Active Students'; ?></p>
                </div>
                <div class="stat-card danger">
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                    <h3 class="stat-value"><?php echo $rejected_students_count; ?></h3>
                    <p class="stat-label">Rejected</p>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="content-card">
                <div class="card-header" style="display:flex; align-items:center; gap:1rem; justify-content:space-between;">
                    <h5 class="card-title"><i class="fas fa-filter"></i> Filter Students</h5>
                    <div>
                        <a href="dashboard.php#courses-section" class="btn btn-outline-primary" title="View Courses">
                            <i class="fas fa-book"></i> Go to Courses
                        </a>
                    </div>
                </div>

                <form method="GET" action="<?php echo htmlspecialchars(adminStudentsPageUrl()); ?>">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label class="form-label">Filter by Course</label>
                            <select name="filter_course" class="form-select">
                                <?php if ($is_course_coordinator && empty($admin_course_ids)): ?>
                                    <option value="All">No courses assigned</option>
                                <?php else: ?>
                                    <option value="All" <?php if ($selected_course == 'All') echo 'selected'; ?>>
                                        <?php echo $is_course_coordinator ? 'All My Courses' : 'All Courses'; ?>
                                    </option>
                                    <?php
                                    if ($courses_result && $courses_result->num_rows > 0) {
                                        $courses_result->data_seek(0);
                                        while ($course = $courses_result->fetch_assoc()) {
                                            $cid  = (int)$course['id'];
                                            $cname = htmlspecialchars($course['course_name']);
                                            $cdesc = htmlspecialchars($course['course_description'] ?? '');
                                            $display = $cname . ($cdesc ? " ($cdesc)" : '');
                                            $sel = ($selected_course !== 'All' && (int)$selected_course === $cid) ? 'selected' : '';
                                            echo "<option value=\"$cid\" $sel>$display</option>";
                                        }
                                    }
                                    ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Filter by Gender</label>
                            <select name="filter_gender" class="form-select">
                                <option value="All"    <?php if ($selected_gender == 'All')    echo 'selected'; ?>>All Genders</option>
                                <option value="Male"   <?php if ($selected_gender == 'Male')   echo 'selected'; ?>>Male</option>
                                <option value="Female" <?php if ($selected_gender == 'Female') echo 'selected'; ?>>Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Filter by Category</label>
                            <select name="filter_category" class="form-select">
                                <option value="All" <?php if ($selected_category === 'All') echo 'selected'; ?>>All Categories</option>
                                <?php foreach ($student_category_filter_options as $category_option): ?>
                                    <option value="<?php echo htmlspecialchars($category_option); ?>"
                                        <?php if ($selected_category === $category_option) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($category_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Filter by Status</label>
                            <select name="filter_status" class="form-select">
                                <option value="All" <?php if ($selected_status === 'All') echo 'selected'; ?>>All Statuses</option>
                                <option value="pending" <?php if ($selected_status === 'pending') echo 'selected'; ?>>Pending</option>
                                <option value="active" <?php if ($selected_status === 'active') echo 'selected'; ?>>Active</option>
                                <option value="rejected" <?php if ($selected_status === 'rejected') echo 'selected'; ?>>Rejected</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Filter by Scheme / Project</label>
                            <select name="filter_scheme" class="form-select">
                                <option value="All" <?php if ($selected_scheme === 'All') echo 'selected'; ?>>All Schemes</option>
                                <option value="none" <?php if ($selected_scheme === 'none') echo 'selected'; ?>>Not set</option>
                                <?php foreach ($filter_scheme_options as $sch_opt): ?>
                                    <option value="<?php echo (int)$sch_opt['id']; ?>"
                                        <?php if ((string)$selected_scheme === (string)$sch_opt['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($sch_opt['scheme_name'] . ' (' . $sch_opt['scheme_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Rows per page</label>
                            <select name="per_page" class="form-select">
                                <?php foreach ($allowed_per_page as $option): ?>
                                    <option value="<?php echo $option; ?>" <?php echo $per_page === $option ? 'selected' : ''; ?>>
                                        <?php echo $option; ?> per page
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>

                <?php if ($selected_course !== 'All'): ?>
                    <?php
                    $linked_for_course = getSchemesForCourse($conn, (int)$selected_course);
                    $linked_course_name = '';
                    if ($courses_result) {
                        $courses_result->data_seek(0);
                        while ($c = $courses_result->fetch_assoc()) {
                            if ((int)$c['id'] === (int)$selected_course) {
                                $linked_course_name = $c['course_name'];
                                break;
                            }
                        }
                        $courses_result->data_seek(0);
                    }
                    ?>
                    <div style="margin-top:1rem;padding:12px 16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;font-size:13px;color:#0c4a6e;">
                        <strong><i class="fas fa-project-diagram"></i> Schemes for <?php echo htmlspecialchars($linked_course_name ?: 'selected course'); ?>:</strong>
                        <?php if (!empty($linked_for_course)): ?>
                            <?php echo htmlspecialchars(implode(', ', array_map(function ($s) {
                                return $s['scheme_name'] . ' (' . $s['scheme_code'] . ')';
                            }, $linked_for_course))); ?>
                            <span style="color:#64748b;"> — edit linked schemes in <a href="edit_course.php?id=<?php echo (int)$selected_course; ?>">Edit Course</a></span>
                        <?php else: ?>
                            <span style="color:#b45309;">No schemes linked yet.</span>
                            <a href="edit_course.php?id=<?php echo (int)$selected_course; ?>">Add schemes in Edit Course</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Filter Stats (FIX: uses $stats_* variables, NOT $result) -->
                <div class="course-stats" style="margin-top:1.5rem;">
                    <div class="overview-grid">
                        <div class="overview-card">
                            <div class="overview-header">
                                <div>
                                    <p class="overview-kicker">Student Overview</p>
                                    <h4 class="overview-title">Gender distribution</h4>
                                </div>
                                <div class="overview-pill">
                                    <i class="fas fa-users"></i>
                                    <?php echo number_format($stats['total']); ?> total students
                                </div>
                            </div>

                            <?php if ($stats['total'] > 0): ?>
                                <div class="chart-wrap">
                                    <canvas id="studentGenderChart"></canvas>
                                    <div class="chart-center">
                                        <div class="chart-center-value"><?php echo number_format($stats['total']); ?></div>
                                        <div class="chart-center-label">Registered</div>
                                    </div>
                                </div>

                                <div class="metric-list">
                                    <div class="metric-item">
                                        <span class="metric-dot" style="background:#0ea5e9;"></span>
                                        <div class="metric-copy">
                                            <p class="metric-label">Male Students</p>
                                            <p class="metric-note">Current filtered set</p>
                                        </div>
                                        <div class="metric-value"><?php echo number_format($stats['male']); ?></div>
                                    </div>
                                    <div class="metric-item">
                                        <span class="metric-dot" style="background:#f97316;"></span>
                                        <div class="metric-copy">
                                            <p class="metric-label">Female Students</p>
                                            <p class="metric-note">Current filtered set</p>
                                        </div>
                                        <div class="metric-value"><?php echo number_format($stats['female']); ?></div>
                                    </div>
                                    <?php if ($other_gender_count > 0): ?>
                                    <div class="metric-item">
                                        <span class="metric-dot" style="background:#94a3b8;"></span>
                                        <div class="metric-copy">
                                            <p class="metric-label">Other / Unspecified</p>
                                            <p class="metric-note">Not marked as male or female</p>
                                        </div>
                                        <div class="metric-value"><?php echo number_format($other_gender_count); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-chart-state">
                                    <i class="fas fa-chart-pie" style="font-size:2rem;margin-bottom:0.75rem;opacity:0.5;"></i>
                                    <strong>No student data available</strong>
                                    <span>Try adjusting the filters to see a chart.</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="overview-card">
                            <div class="overview-header">
                                <div>
                                    <p class="overview-kicker">Quick Metrics</p>
                                    <h4 class="overview-title">At a glance</h4>
                                </div>
                            </div>

                            <div class="summary-grid">
                                <div class="summary-card summary-total">
                                    <div class="summary-top">
                                        <div class="summary-icon"><i class="fas fa-users"></i></div>
                                        <div>
                                            <p class="summary-value"><?php echo number_format($stats['total']); ?></p>
                                            <p class="summary-label">Total Registered</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="summary-card summary-active">
                                    <div class="summary-top">
                                        <div class="summary-icon"><i class="fas fa-check-circle"></i></div>
                                        <div>
                                            <p class="summary-value"><?php echo number_format($stats['active']); ?></p>
                                            <p class="summary-label">Active Students</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="summary-card summary-male">
                                    <div class="summary-top">
                                        <div class="summary-icon"><i class="fas fa-mars"></i></div>
                                        <div>
                                            <p class="summary-value"><?php echo number_format($stats['male']); ?></p>
                                            <p class="summary-label">Male Students</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="summary-card summary-female">
                                    <div class="summary-top">
                                        <div class="summary-icon"><i class="fas fa-venus"></i></div>
                                        <div>
                                            <p class="summary-value"><?php echo number_format($stats['female']); ?></p>
                                            <p class="summary-label">Female Students</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Students Table -->
            <?php if ($is_course_coordinator && empty($admin_courses)): ?>
            <div class="content-card">
                <div class="card-body text-center" style="padding:3rem;">
                    <div style="color:#64748b;margin-bottom:1.5rem;">
                        <i class="fas fa-user-tie" style="font-size:4rem;opacity:0.3;"></i>
                    </div>
                    <h4 style="color:#374151;margin-bottom:1rem;">No Course Assignments</h4>
                    <p style="color:#6b7280;margin-bottom:1.5rem;">
                        You haven't been assigned to any courses yet. Please contact the Master Admin.
                    </p>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            <?php else: ?>

            <?php if ($is_course_coordinator): ?>
            <div class="content-card" style="margin-bottom:1.5rem;">
                <div class="card-body" style="background:linear-gradient(135deg,#e3f2fd 0%,#f3e5f5 100%);border-left:4px solid #2196f3;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="color:#1976d2;font-size:1.5rem;"><i class="fas fa-info-circle"></i></div>
                        <div>
                            <h6 style="margin:0;color:#1565c0;font-weight:600;">Course Coordinator View</h6>
                            <p style="margin:4px 0 0 0;color:#424242;font-size:14px;">
                                Showing students from your assigned courses (including rejected registrations).
                                Students can be in <strong>multiple batches</strong> — use <strong>Add Batch</strong> to assign more.
                                <?php if ($has_created_by_column): ?>
                                    You can only assign students to <strong>batches you created</strong>.
                                <?php else: ?>
                                    <br><strong>Note:</strong> Run the batch ownership migration to enable batch filtering by creator.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="content-card students-list-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-users"></i> All Students
                        <?php if ($total_filtered > 0): ?>
                            <small style="color:#64748b;font-weight:normal;">
                                (<?php echo number_format($showing_from); ?>–<?php echo number_format($showing_to); ?> of <?php echo number_format($total_filtered); ?>)
                            </small>
                        <?php endif; ?>
                        <?php if ($is_course_coordinator && !empty($admin_courses)): ?>
                            <small style="color:#64748b;font-weight:normal;">
                                (Showing: <?php echo implode(', ', array_map('htmlspecialchars', $admin_courses)); ?>)
                            </small>
                        <?php endif; ?>
                    </h5>
                    <div class="students-table-toolbar">
                        <span id="selected-count" style="color:#64748b;font-size:14px;display:none;">
                            <i class="fas fa-check-square"></i> <span id="count-number">0</span> selected
                        </span>
                        <button type="button" id="bulk-assign-btn" class="btn btn-primary" style="display:none;">
                            <i class="fas fa-layer-group"></i> Bulk Assign to Batch
                        </button>
                        <?php if (!$is_front_office): ?>
                        <button type="button" id="bulk-approve-btn" class="btn btn-success" style="display:none;">
                            <i class="fas fa-check"></i> Approve Selected
                        </button>
                        <button type="button" id="bulk-remove-course-btn" class="btn btn-danger" style="display:none;">
                            <i class="fas fa-user-minus"></i> Remove Selected from Course
                        </button>
                        <?php endif; ?>
                        <a href="<?php echo htmlspecialchars(relative_url('export_students_excel.php')); ?><?php
                            $ep = [];
                            if ($selected_course !== 'All') $ep[] = 'filter_course=' . urlencode($selected_course);
                            if ($selected_scheme !== 'All') $ep[] = 'filter_scheme=' . urlencode($selected_scheme);
                            if ($selected_gender !== 'All') $ep[] = 'filter_gender=' . urlencode($selected_gender);
                            if ($selected_category !== 'All') $ep[] = 'filter_category=' . urlencode($selected_category);
                            if ($selected_status !== 'All') $ep[] = 'filter_status=' . urlencode($selected_status);
                            if (!empty($start_date))         $ep[] = 'start_date='    . urlencode($start_date);
                            if (!empty($end_date))           $ep[] = 'end_date='      . urlencode($end_date);
                            echo !empty($ep) ? '?' . implode('&', $ep) : '';
                        ?>" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table students-table">
                        <thead>
                            <tr>
                                <th class="col-check"><input type="checkbox" id="select-all" title="Select All"></th>
                                <th class="col-sl">#</th>
                                <th class="col-photo student-photo-cell">Photo</th>
                                <th class="col-id">Student ID</th>
                                <th class="col-student">Student</th>
                                <th class="col-cat">Cat.</th>
                                <th class="col-course">Course</th>
                                <th class="col-scheme">Scheme / Project</th>
                                <th class="col-batch">Batch</th>
                                <th class="col-status">Status</th>
                                <th class="col-date">Registered</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $sl_no = $showing_from > 0 ? $showing_from : 1;
                        $course_schemes_cache = [];
                        $student_course_schemes_cache = [];
                        $batch_assign_enrollments_cache = [];
                        $student_course_batches_cache = [];
                        $displayed_student_course = [];
                        $student_course_meta_cache = [];
                        $student_course_count_cache = [];
                        if ($students_result && $students_result_count > 0):
                            while ($row = $students_result->fetch_assoc()):
                                $status     = strtolower($row['status']);
                                $badge_map  = ['active'=>'badge-success','pending'=>'badge-warning','rejected'=>'badge-danger','inactive'=>'badge-secondary'];
                                $badge_cls  = $badge_map[$status] ?? 'badge-secondary';
                                $course_display = htmlspecialchars(!empty($row['course_name']) ? $row['course_name'] : $row['course']);
                                $row_course_id = (int)($row['course_id'] ?? 0);
                                if ($row_course_id > 0 && !isset($course_schemes_cache[$row_course_id])) {
                                    $course_schemes_cache[$row_course_id] = getSchemesForCourse($conn, $row_course_id);
                                }
                                $row_linked_schemes = $course_schemes_cache[$row_course_id] ?? [];
                                $has_linked_schemes = !empty($row_linked_schemes);

                                $schemes_cache_key = $row['student_id'] . ':' . $row_course_id;
                                if ($row_course_id > 0 && !isset($student_course_schemes_cache[$schemes_cache_key])) {
                                    $student_course_schemes_cache[$schemes_cache_key] = getEnrolledSchemesForStudentCourse(
                                        $conn,
                                        (string)$row['student_id'],
                                        $row_course_id
                                    );
                                }
                                $all_student_schemes = $student_course_schemes_cache[$schemes_cache_key] ?? [];

                                $batch_assign_key = $row['student_id'] . ':' . $row_course_id;
                                if ($row_course_id > 0 && !isset($student_course_meta_cache[$batch_assign_key])) {
                                    $meta_stmt = $conn->prepare("SELECT MIN(id) AS primary_id,
                                        MIN(created_at) AS first_registered,
                                        MAX(CASE WHEN LOWER(status) NOT IN ('rejected', 'inactive') THEN id ELSE NULL END) AS latest_active_id,
                                        SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                                        SUM(CASE WHEN LOWER(status) = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
                                        FROM students
                                        WHERE student_id = ? AND course_id = ?");
                                    if ($meta_stmt) {
                                        $meta_stmt->bind_param('si', $row['student_id'], $row_course_id);
                                        $meta_stmt->execute();
                                        $meta_row = $meta_stmt->get_result()->fetch_assoc();
                                        $meta_stmt->close();
                                        $student_course_meta_cache[$batch_assign_key] = [
                                            'primary_id' => (int)($meta_row['latest_active_id'] ?? $meta_row['primary_id'] ?? 0),
                                            'first_registered' => $meta_row['first_registered'] ?? $row['created_at'],
                                            'has_pending' => ((int)($meta_row['pending_count'] ?? 0) > 0),
                                            'rejected_count' => (int)($meta_row['rejected_count'] ?? 0),
                                        ];
                                    }
                                }
                                if ($row_course_id > 0 && !isset($batch_assign_enrollments_cache[$batch_assign_key])) {
                                    $enr_stmt = $conn->prepare("SELECT s.id AS record_id, s.scheme_id, sch.scheme_name, sch.scheme_code
                                        FROM students s
                                        LEFT JOIN schemes sch ON sch.id = s.scheme_id
                                        WHERE s.student_id = ? AND s.course_id = ?
                                        AND LOWER(s.status) NOT IN ('rejected', 'inactive')
                                        ORDER BY sch.scheme_name ASC, s.id ASC");
                                    $batch_assign_rows = [];
                                    if ($enr_stmt) {
                                        $enr_stmt->bind_param('si', $row['student_id'], $row_course_id);
                                        $enr_stmt->execute();
                                        $enr_res = $enr_stmt->get_result();
                                        while ($enr_row = $enr_res->fetch_assoc()) {
                                            $enr_record_id = (int)$enr_row['record_id'];
                                            $enr_batches = getBatchesForStudentRecord($conn, $enr_record_id);
                                            $batch_assign_rows[] = [
                                                'record_id' => $enr_record_id,
                                                'scheme_id' => (int)($enr_row['scheme_id'] ?? 0),
                                                'scheme_name' => !empty($enr_row['scheme_name'])
                                                    ? $enr_row['scheme_name']
                                                    : 'Not set',
                                                'assigned_batch_ids' => array_map(function ($b) {
                                                    return (int)$b['id'];
                                                }, $enr_batches),
                                            ];
                                        }
                                        $enr_stmt->close();
                                    }
                                    $batch_assign_enrollments_cache[$batch_assign_key] = $batch_assign_rows;
                                }
                                $batch_assign_enrollments = $batch_assign_enrollments_cache[$batch_assign_key] ?? [];

                                $record_id = (int)$row['id'];

                                $course_batches_key = $row['student_id'] . ':' . $row_course_id;
                                if ($row_course_id > 0 && !isset($student_course_batches_cache[$course_batches_key])) {
                                    $student_course_batches_cache[$course_batches_key] = getBatchesForStudentCourse(
                                        $conn,
                                        (string)$row['student_id'],
                                        $row_course_id
                                    );
                                }
                                $row_batches = $student_course_batches_cache[$course_batches_key] ?? [];
                                $assigned_batch_ids = array_map(function ($b) {
                                    return (int)$b['id'];
                                }, $row_batches);

                                $student_course_group_key = $row_course_id > 0
                                    ? ($row['student_id'] . ':' . $row_course_id)
                                    : '';
                                if ($student_course_group_key !== '' && isset($displayed_student_course[$student_course_group_key])) {
                                    continue;
                                }
                                if ($student_course_group_key !== '') {
                                    $displayed_student_course[$student_course_group_key] = true;
                                }

                                $course_meta = $student_course_meta_cache[$batch_assign_key] ?? [];
                                $primary_record_id = (int)($course_meta['primary_id'] ?? $record_id);
                                if ($primary_record_id <= 0) {
                                    $primary_record_id = $record_id;
                                }
                                if (!empty($batch_assign_enrollments)) {
                                    $primary_record_id = min(array_column($batch_assign_enrollments, 'record_id'));
                                }

                                if ($status !== 'rejected' && !empty($course_meta['has_pending'])) {
                                    $status = 'pending';
                                    $badge_cls = 'badge-warning';
                                }

                                $has_prior_rejection = ($status === 'pending' || $status === 'active')
                                    && !empty($course_meta['rejected_count']);
                                $prior_rejection_note = $has_prior_rejection
                                    ? 'Reapplied after earlier rejection'
                                    : '';

                                $display_created_at = $course_meta['first_registered'] ?? $row['created_at'];

                                if (!isset($student_course_count_cache[$row['student_id']])) {
                                    $course_count = 0;
                                    $cc_stmt = $conn->prepare("SELECT COUNT(DISTINCT course_id) AS total
                                        FROM students
                                        WHERE student_id = ?
                                        AND LOWER(status) NOT IN ('inactive')");
                                    if ($cc_stmt) {
                                        $cc_stmt->bind_param('s', $row['student_id']);
                                        $cc_stmt->execute();
                                        $cc_row = $cc_stmt->get_result()->fetch_assoc();
                                        $cc_stmt->close();
                                        $course_count = (int)($cc_row['total'] ?? 0);
                                    }
                                    $student_course_count_cache[$row['student_id']] = $course_count;
                                }
                                $student_total_courses = $student_course_count_cache[$row['student_id']];
                                $has_other_course_enrollments = $student_total_courses > 1;
                        ?>
                        <tr>
                            <td>
                                <?php if ($status !== 'rejected'): ?>
                                <input type="checkbox" class="student-checkbox"
                                       value="<?php echo $primary_record_id; ?>"
                                       data-student-id="<?php echo htmlspecialchars($row['student_id']); ?>"
                                       data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                       data-status="<?php echo htmlspecialchars($status); ?>"
                                       data-course="<?php echo $course_display; ?>"
                                       data-course-id="<?php echo (int)($row['course_id'] ?? 0); ?>"
                                       data-scheme-id="<?php echo (int)($row['scheme_id'] ?? 0); ?>">
                                <?php endif; ?>
                            </td>
                            <td><?php echo $sl_no++; ?></td>
                            <td class="student-photo-cell">
                                <?php
                                $photo_rel = trim((string)($row['passport_photo'] ?? ''));
                                $photo_rel = ltrim(str_replace('\\', '/', $photo_rel), '/');
                                $photo_fs = $photo_rel !== '' ? (__DIR__ . '/../' . $photo_rel) : '';
                                $photo_ok = $photo_fs !== '' && is_file($photo_fs);
                                if ($photo_ok):
                                    $photo_url = APP_URL . '/' . $photo_rel;
                                ?>
                                    <a class="student-photo-link"
                                       href="<?php echo htmlspecialchars($photo_url); ?>"
                                       data-photo-url="<?php echo htmlspecialchars($photo_url); ?>"
                                       data-photo-name="<?php echo htmlspecialchars($row['name']); ?>"
                                       title="View photo — <?php echo htmlspecialchars($row['name']); ?>">
                                        <img class="student-photo-thumb"
                                             src="<?php echo htmlspecialchars($photo_url); ?>"
                                             alt="<?php echo htmlspecialchars($row['name']); ?>"
                                             loading="lazy"
                                             width="32"
                                             height="38">
                                    </a>
                                <?php else: ?>
                                    <span class="student-photo-placeholder" title="No photo uploaded">
                                        <i class="fas fa-user"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="student-id-cell"><strong><?php echo htmlspecialchars($row['student_id']); ?></strong></td>
                            <td class="student-contact-cell">
                                <span class="student-name"><?php echo htmlspecialchars($row['name']); ?></span>
                                <?php if (!empty($row['email'])): ?>
                                    <span class="student-meta" title="<?php echo htmlspecialchars($row['email']); ?>"><?php echo htmlspecialchars($row['email']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($row['mobile'])): ?>
                                    <span class="student-meta"><?php echo htmlspecialchars($row['mobile']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $student_category = trim((string)($row['category'] ?? ''));
                                $category_badge_map = [
                                    'General' => 'badge-secondary',
                                    'OBC'     => 'badge-info',
                                    'SC'      => 'badge-warning',
                                    'ST'      => 'badge-success',
                                    'EWS'     => 'badge-primary',
                                ];
                                $category_badge = $category_badge_map[$student_category] ?? 'badge-secondary';
                                ?>
                                <?php if ($student_category !== ''): ?>
                                    <span class="badge <?php echo $category_badge; ?>"><?php echo htmlspecialchars($student_category); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-primary badge-clip" title="<?php echo $course_display; ?>"><?php echo $course_display; ?></span>
                            </td>
                            <td>
                                <?php if (!empty($all_student_schemes)): ?>
                                    <?php foreach ($all_student_schemes as $sch):
                                        $scheme_full = (string)($sch['scheme_name'] ?? '');
                                        $scheme_code = trim((string)($sch['scheme_code'] ?? ''));
                                        $scheme_label = $scheme_code !== '' ? $scheme_code : $scheme_full;
                                    ?>
                                        <span class="badge badge-info badge-clip"
                                              title="<?php echo htmlspecialchars($scheme_full); ?>">
                                            <?php echo htmlspecialchars($scheme_label); ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php elseif (!empty($row['scheme_name'])):
                                    $scheme_full = (string)$row['scheme_name'];
                                    $scheme_code = trim((string)($row['scheme_code'] ?? ''));
                                    $scheme_label = $scheme_code !== '' ? $scheme_code : $scheme_full;
                                ?>
                                    <span class="badge badge-info badge-clip" title="<?php echo htmlspecialchars($scheme_full); ?>">
                                        <?php echo htmlspecialchars($scheme_label); ?>
                                    </span>
                                <?php elseif ($has_linked_schemes): ?>
                                    <span class="badge badge-warning" title="Assign a scheme from this course">Not set</span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row_batches)): ?>
                                    <?php foreach ($row_batches as $rb): ?>
                                        <?php $batch_record_id = (int)($rb['student_record_id'] ?? $record_id); ?>
                                        <span class="badge badge-success badge-clip"
                                              title="<?php echo htmlspecialchars(($rb['batch_name'] ?? '') . (!empty($rb['batch_code']) ? ' (' . $rb['batch_code'] . ')' : '')); ?>">
                                            <i class="fas fa-layer-group"></i>
                                            <?php echo htmlspecialchars($rb['batch_name']); ?>
                                            <?php if (!$is_front_office && $status !== 'rejected'): ?>
                                            <a href="javascript:void(0);"
                                               class="remove-batch-btn"
                                               style="color:#fff;opacity:0.85;margin-left:2px;"
                                               title="Remove from <?php echo htmlspecialchars($rb['batch_name']); ?>"
                                               data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                               data-batch-name="<?php echo htmlspecialchars($rb['batch_name']); ?>"
                                               data-url="<?php echo htmlspecialchars(adminStudentsPageUrl()); ?>?remove_record=<?php echo $batch_record_id; ?>&batch_id=<?php echo (int)$rb['id']; ?><?php echo $filter_suffix; ?>">
                                                <i class="fas fa-times-circle"></i>
                                            </a>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><i class="fas fa-minus-circle"></i> None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_cls; ?>"><?php echo ucfirst($status); ?></span>
                                <?php if ($status === 'rejected' && !empty($row['rejection_reason'])): ?>
                                    <br><small style="color:#dc2626;font-size:11px;" title="<?php echo htmlspecialchars($row['rejection_note'] ?? ''); ?>">
                                        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($row['rejection_reason']); ?>
                                    </small>
                                <?php elseif (!empty($prior_rejection_note)): ?>
                                    <br><small style="color:#b45309;font-size:11px;">
                                        <i class="fas fa-redo"></i> <?php echo htmlspecialchars($prior_rejection_note); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d M Y', strtotime($display_created_at)); ?></td>
                            <td class="students-actions-cell">
                                <div class="action-row">
                                <?php if (!$is_front_office): ?>
                                    <?php if ($status === 'pending'): ?>
                                        <a href="javascript:void(0);"
                                           class="btn btn-success btn-sm approve-student-btn"
                                           title="Approve"
                                           data-student-id="<?php echo htmlspecialchars($row['student_id']); ?>"
                                           data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                           data-url="<?php echo htmlspecialchars(adminStudentsPageUrl()); ?>?approve_id=<?php echo urlencode($row['student_id']); ?><?php echo $filter_suffix; ?>">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="javascript:void(0);"
                                           class="btn btn-danger btn-sm reject-student-btn"
                                           title="Reject"
                                           data-student-id="<?php echo htmlspecialchars($row['student_id']); ?>"
                                           data-student-name="<?php echo htmlspecialchars($row['name']); ?>">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php elseif (in_array($status, ['active', 'approved'], true)): ?>
                                        <a href="<?php echo htmlspecialchars(relative_url('edit_student.php')); ?>?id=<?php echo urlencode($row['student_id']); ?><?php echo $filter_suffix; ?>"
                                           class="btn btn-warning btn-sm" title="Edit Student">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0);"
                                           class="btn btn-secondary btn-sm deapprove-student-btn"
                                           title="De-Approve"
                                           data-student-id="<?php echo htmlspecialchars($row['student_id']); ?>"
                                           data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                           data-url="<?php echo htmlspecialchars(adminStudentsPageUrl()); ?>?deapprove_id=<?php echo urlencode($row['student_id']); ?><?php echo $filter_suffix; ?>">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                    <?php elseif ($status === 'rejected'): ?>
                                        <a href="<?php echo htmlspecialchars(relative_url('edit_student.php')); ?>?id=<?php echo urlencode($row['student_id']); ?><?php echo $filter_suffix; ?>"
                                           class="btn btn-warning btn-sm" title="Edit Student">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars(relative_url('edit_student.php')); ?>?id=<?php echo urlencode($row['student_id']); ?><?php echo $filter_suffix; ?>"
                                           class="btn btn-warning btn-sm" title="Edit Student">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!$is_front_office && $status !== 'rejected'): ?>
                                        <button type="button"
                                                class="btn btn-info btn-sm assign-batch-btn"
                                                title="<?php echo !empty($row_batches) ? 'Add to another batch' : 'Assign to batch'; ?>"
                                                data-student-record-id="<?php echo $primary_record_id; ?>"
                                                data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                data-course="<?php echo $course_display; ?>"
                                                data-course-id="<?php echo (int)($row['course_id'] ?? 0); ?>"
                                                data-scheme-id="<?php echo (int)($row['scheme_id'] ?? 0); ?>"
                                                data-assigned-batch-ids="<?php echo htmlspecialchars(implode(',', $assigned_batch_ids)); ?>"
                                                data-enrollments="<?php echo htmlspecialchars(json_encode($batch_assign_enrollments), ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-plus-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars(relative_url('edit_student.php')); ?>?id=<?php echo urlencode($row['student_id']); ?>"
                                       class="btn btn-warning btn-sm" title="Edit Student">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="<?php echo htmlspecialchars(relative_url('view_student_documents.php')); ?>?id=<?php echo urlencode($row['student_id']); ?><?php echo $filter_suffix; ?>"
                                   class="btn btn-info btn-sm" title="View Documents">
                                    <i class="fas fa-folder-open"></i>
                                </a>
                                <a href="<?php echo htmlspecialchars(relative_url('download_student_form.php')); ?>?record_id=<?php echo $primary_record_id; ?>"
                                   class="btn btn-success btn-sm" title="Download Form" target="_blank">
                                    <i class="fas fa-download"></i>
                                </a>

                                <?php if (!$is_front_office && $row_course_id > 0): ?>
                                <a href="javascript:void(0);"
                                   class="btn btn-outline-danger btn-sm remove-course-btn"
                                   title="Remove from this course only"
                                   data-student-id="<?php echo htmlspecialchars($row['student_id']); ?>"
                                   data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                   data-course-name="<?php echo $course_display; ?>"
                                   data-has-other-courses="<?php echo $has_other_course_enrollments ? '1' : '0'; ?>"
                                   data-url="<?php echo htmlspecialchars(adminStudentsPageUrl()); ?>?remove_course_enrollment=1&amp;student_id=<?php echo urlencode($row['student_id']); ?>&amp;course_id=<?php echo $row_course_id; ?><?php echo $filter_suffix; ?>">
                                    <i class="fas fa-user-minus"></i>
                                </a>
                                <a href="javascript:void(0);"
                                   class="btn btn-danger btn-sm delete-student-btn"
                                   title="Delete student from all courses"
                                   data-student-id="<?php echo htmlspecialchars($row['student_id']); ?>"
                                   data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                   data-has-other-courses="<?php echo $has_other_course_enrollments ? '1' : '0'; ?>"
                                   data-url="<?php echo htmlspecialchars(adminStudentsPageUrl()); ?>?delete_id=<?php echo urlencode($row['student_id']); ?><?php echo $filter_suffix; ?>">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="12" style="padding:2.5rem;text-align:center;color:var(--text-muted);">
                                <strong>No students found for the selected filters.</strong>
                            </td>
                        </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_filtered > 0 && $total_pages > 1): ?>
                <div class="students-pagination">
                    <div class="students-pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </div>
                    <nav class="students-pagination-nav" aria-label="Students pagination">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(studentsListUrl($selected_course, $selected_gender, $selected_scheme, $selected_category, $selected_status, $start_date, $end_date, 1, $per_page)); ?>">
                                <i class="fas fa-angle-double-left"></i> First
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(studentsListUrl($selected_course, $selected_gender, $selected_scheme, $selected_category, $selected_status, $start_date, $end_date, $page - 1, $per_page)); ?>">
                                <i class="fas fa-angle-left"></i> Prev
                            </a>
                        <?php endif; ?>

                        <?php
                        $page_window = 2;
                        $start_page = max(1, $page - $page_window);
                        $end_page = min($total_pages, $page + $page_window);
                        if ($start_page > 1) {
                            echo '<span class="students-pagination-ellipsis">…</span>';
                        }
                        for ($p = $start_page; $p <= $end_page; $p++):
                            $is_active = ($p === $page);
                        ?>
                            <a class="btn btn-sm <?php echo $is_active ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                               href="<?php echo htmlspecialchars(studentsListUrl($selected_course, $selected_gender, $selected_scheme, $selected_category, $selected_status, $start_date, $end_date, $p, $per_page)); ?>"
                               <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($end_page < $total_pages): ?>
                            <span class="students-pagination-ellipsis">…</span>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(studentsListUrl($selected_course, $selected_gender, $selected_scheme, $selected_category, $selected_status, $start_date, $end_date, $page + 1, $per_page)); ?>">
                                Next <i class="fas fa-angle-right"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(studentsListUrl($selected_course, $selected_gender, $selected_scheme, $selected_category, $selected_status, $start_date, $end_date, $total_pages, $per_page)); ?>">
                                Last <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Batch Assignment Modal (single) -->
<div id="batchModal" class="batch-modal">
    <div class="batch-modal-content">
        <div class="batch-modal-header">
            <h3><i class="fas fa-layer-group"></i> Assign Student to Batch</h3>
            <button class="close-modal" onclick="closeBatchModal()">&times;</button>
        </div>
        <div class="batch-info">
            <p><strong>Student:</strong> <span id="modal-student-name"></span></p>
            <p><strong>Course:</strong> <span id="modal-course"></span></p>
            <p id="batch-modal-multi-hint" style="display:none;font-size:12px;color:#64748b;margin-top:8px;">
                <i class="fas fa-info-circle"></i> <span id="batch-modal-multi-hint-text"></span>
            </p>
        </div>
        <form method="POST" action="<?php echo htmlspecialchars(adminStudentsPageUrl()); ?>" id="batch-assign-form">
            <input type="hidden" name="filter_course" value="<?php echo htmlspecialchars($selected_course); ?>">
            <input type="hidden" name="filter_gender" value="<?php echo htmlspecialchars($selected_gender); ?>">
            <input type="hidden" name="filter_scheme" value="<?php echo htmlspecialchars($selected_scheme); ?>">
            <input type="hidden" name="filter_category" value="<?php echo htmlspecialchars($selected_category); ?>">
            <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($selected_status); ?>">
            <input type="hidden" name="start_date"    value="<?php echo htmlspecialchars($start_date); ?>">
            <input type="hidden" name="end_date"      value="<?php echo htmlspecialchars($end_date); ?>">
            <input type="hidden" name="page"          value="<?php echo (int)$page; ?>">
            <input type="hidden" name="per_page"      value="<?php echo (int)$per_page; ?>">
            <div id="batch-modal-enrollments"></div>
            <p id="batch-modal-empty" style="display:none;color:#b45309;font-size:13px;margin-top:8px;">
                <i class="fas fa-exclamation-triangle"></i> No matching batches available for the selected scheme(s).
            </p>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" name="assign_batch" class="btn btn-primary" style="flex:1;">
                    <i class="fas fa-check"></i> Assign to Batch
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeBatchModal()" style="flex:1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Batch Assignment Modal -->
<div id="bulkBatchModal" class="batch-modal">
    <div class="batch-modal-content">
        <div class="batch-modal-header">
            <h3><i class="fas fa-layer-group"></i> Bulk Assign Students to Batch</h3>
            <button class="close-modal" onclick="closeBulkBatchModal()">&times;</button>
        </div>
        <div class="batch-info">
            <p><strong>Selected Students:</strong> <span id="bulk-modal-count">0</span></p>
            <p style="font-size:12px;color:#64748b;margin-top:5px;">
                <i class="fas fa-info-circle"></i> All selected students will be assigned to the same batch
            </p>
        </div>
        <form method="POST" action="<?php echo htmlspecialchars(adminStudentsPageUrl()); ?>" id="bulk-assign-form">
            <input type="hidden" name="filter_course" value="<?php echo htmlspecialchars($selected_course); ?>">
            <input type="hidden" name="filter_gender" value="<?php echo htmlspecialchars($selected_gender); ?>">
            <input type="hidden" name="filter_scheme" value="<?php echo htmlspecialchars($selected_scheme); ?>">
            <input type="hidden" name="filter_category" value="<?php echo htmlspecialchars($selected_category); ?>">
            <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($selected_status); ?>">
            <input type="hidden" name="start_date"    value="<?php echo htmlspecialchars($start_date); ?>">
            <input type="hidden" name="end_date"      value="<?php echo htmlspecialchars($end_date); ?>">
            <input type="hidden" name="page"          value="<?php echo (int)$page; ?>">
            <input type="hidden" name="per_page"      value="<?php echo (int)$per_page; ?>">
            <div id="bulk-student-ids"></div>
            <div class="form-group">
                <label class="form-label">Select Batch</label>
                <select name="batch_id" id="bulk-modal-batch-select" class="form-control" required>
                    <option value="">-- Select a Batch --</option>
                    <?php foreach ($active_batches as $batch): ?>
                        <option value="<?php echo $batch['id']; ?>"
                                data-course-id="<?php echo (int)$batch['course_id']; ?>"
                                data-course="<?php echo htmlspecialchars($batch['course_name']); ?>"
                                data-scheme-id="<?php echo (int)($batch['scheme_id'] ?? 0); ?>">
                            <?php echo htmlspecialchars($batch['batch_name']); ?>
                            (<?php echo htmlspecialchars($batch['batch_code']); ?>) -
                            <?php echo htmlspecialchars($batch['course_name']); ?>
                            <?php if (!empty($batch['scheme_name'])): ?> — <?php echo htmlspecialchars($batch['scheme_name']); ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color:#64748b;margin-top:5px;display:block;">
                    <i class="fas fa-lightbulb"></i> Batches are filtered by selected students' courses
                </small>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" name="bulk_assign_batch" class="btn btn-primary" style="flex:1;">
                    <i class="fas fa-check"></i> Assign All to Batch
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeBulkBatchModal()" style="flex:1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk approve (hidden POST form) -->
<?php if (!$is_front_office): ?>
<form method="POST" action="<?php echo htmlspecialchars(adminStudentsPageUrl()); ?>" id="bulk-approve-form" style="display:none;">
    <input type="hidden" name="filter_course" value="<?php echo htmlspecialchars($selected_course); ?>">
    <input type="hidden" name="filter_gender" value="<?php echo htmlspecialchars($selected_gender); ?>">
    <input type="hidden" name="filter_scheme" value="<?php echo htmlspecialchars($selected_scheme); ?>">
    <input type="hidden" name="filter_category" value="<?php echo htmlspecialchars($selected_category); ?>">
    <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($selected_status); ?>">
    <input type="hidden" name="start_date"    value="<?php echo htmlspecialchars($start_date); ?>">
    <input type="hidden" name="end_date"      value="<?php echo htmlspecialchars($end_date); ?>">
    <input type="hidden" name="page"          value="<?php echo (int)$page; ?>">
    <input type="hidden" name="per_page"      value="<?php echo (int)$per_page; ?>">
    <div id="bulk-approve-student-fields"></div>
    <button type="submit" name="bulk_approve_students" value="1" id="bulk-approve-submit"></button>
</form>
<?php endif; ?>

<!-- Bulk Remove From Course Modal -->
<?php if (!$is_front_office): ?>
<div id="bulkRemoveCourseModal" class="batch-modal">
    <div class="batch-modal-content">
        <div class="batch-modal-header">
            <h3><i class="fas fa-user-minus"></i> Remove Selected From Course</h3>
            <button class="close-modal" onclick="closeBulkRemoveCourseModal()">&times;</button>
        </div>
        <div class="batch-info">
            <p><strong>Selected students:</strong> <span id="bulk-remove-modal-count">0</span></p>
            <ul id="bulk-remove-modal-list" style="font-size:13px;color:#64748b;margin:8px 0 0 0;padding-left:18px;max-height:160px;overflow-y:auto;"></ul>
            <p style="font-size:12px;color:#64748b;margin-top:10px;">
                <i class="fas fa-info-circle"></i> Each student is removed from the <strong>course shown in their row</strong> only. Other course enrollments are kept.
            </p>
        </div>
        <form method="POST" action="<?php echo htmlspecialchars(adminStudentsPageUrl()); ?>" id="bulk-remove-course-form">
            <input type="hidden" name="filter_course" value="<?php echo htmlspecialchars($selected_course); ?>">
            <input type="hidden" name="filter_gender" value="<?php echo htmlspecialchars($selected_gender); ?>">
            <input type="hidden" name="filter_scheme" value="<?php echo htmlspecialchars($selected_scheme); ?>">
            <input type="hidden" name="filter_category" value="<?php echo htmlspecialchars($selected_category); ?>">
            <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($selected_status); ?>">
            <input type="hidden" name="start_date"    value="<?php echo htmlspecialchars($start_date); ?>">
            <input type="hidden" name="end_date"      value="<?php echo htmlspecialchars($end_date); ?>">
            <input type="hidden" name="page"          value="<?php echo (int)$page; ?>">
            <input type="hidden" name="per_page"      value="<?php echo (int)$per_page; ?>">
            <div id="bulk-remove-course-fields"></div>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" name="bulk_remove_course_enrollment" class="btn btn-danger" style="flex:1;">
                    <i class="fas fa-user-minus"></i> Remove From Course
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeBulkRemoveCourseModal()" style="flex:1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Rejection Reason Modal -->
<div id="rejectModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;justify-content:center;align-items:center;">
    <div style="background:white;border-radius:12px;padding:32px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,0.2);">
        <div style="text-align:center;margin-bottom:20px;">
            <div style="width:64px;height:64px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i class="fas fa-times-circle" style="font-size:32px;color:#dc2626;"></i>
            </div>
            <h3 style="margin:0 0 6px;font-size:20px;color:#1e293b;">Reject Student</h3>
            <p style="margin:0 0 10px;color:#64748b;font-size:14px;">Rejecting: <strong id="rejectStudentName"></strong></p>
            <p style="margin:0;color:#64748b;font-size:13px;">The student will receive an email with the rejection reason and instructions to reapply.</p>
        </div>
        <form method="POST" action="<?php echo htmlspecialchars(adminStudentsPageUrl()); ?>" id="rejectForm">
            <input type="hidden" name="reject_student" value="1">
            <input type="hidden" name="reject_id" id="rejectStudentId">
            <input type="hidden" name="filter_course" value="<?php echo htmlspecialchars($selected_course ?? ''); ?>">
            <input type="hidden" name="filter_gender" value="<?php echo htmlspecialchars($selected_gender ?? 'All'); ?>">
            <input type="hidden" name="filter_scheme" value="<?php echo htmlspecialchars($selected_scheme ?? 'All'); ?>">
            <input type="hidden" name="filter_category" value="<?php echo htmlspecialchars($selected_category ?? 'All'); ?>">
            <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($selected_status ?? 'All'); ?>">
            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date ?? ''); ?>">
            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date ?? ''); ?>">
            <input type="hidden" name="page" value="<?php echo (int)($page ?? 1); ?>">
            <input type="hidden" name="per_page" value="<?php echo (int)($per_page ?? 25); ?>">
            <div style="margin-bottom:16px;">
                <label style="display:block;font-weight:600;margin-bottom:8px;color:#374151;">Reason for Rejection *</label>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php
                    $reasons = [
                        'Not Eligible'                    => 'Not eligible for the course',
                        'Incomplete Documents'            => 'Incomplete or missing documents',
                        'Invalid Documents'               => 'Invalid or forged documents',
                        'Age Criteria Not Met'            => 'Age criteria not met',
                        'Educational Qualification Not Met' => 'Educational qualification not met',
                        'Duplicate Registration'          => 'Duplicate registration',
                        'Other'                           => 'Other reason',
                    ];
                    foreach ($reasons as $value => $label):
                    ?>
                    <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer;" class="reason-label">
                        <input type="radio" name="rejection_reason" value="<?php echo htmlspecialchars($value); ?>" required onchange="highlightReason(this);">
                        <span style="font-size:14px;color:#374151;"><?php echo htmlspecialchars($label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div id="otherNoteDiv" style="display:none;margin-bottom:16px;">
                <label style="display:block;font-weight:600;margin-bottom:6px;color:#374151;">Additional Note</label>
                <textarea name="rejection_note" rows="2" placeholder="Specify the reason..."
                          style="width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px;resize:vertical;box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                <button type="button" onclick="closeRejectModal()"
                        style="padding:10px 24px;border:none;border-radius:8px;background:#6b7280;color:white;font-size:14px;cursor:pointer;">
                    Cancel
                </button>
                <button type="submit"
                        style="padding:10px 24px;border:none;border-radius:8px;background:#dc2626;color:white;font-size:14px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-times"></i> Confirm Reject
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
const studentGenderChartData = <?php echo json_encode([
    'labels' => $gender_chart_labels,
    'values' => $gender_chart_values,
    'colors' => $gender_chart_colors,
], JSON_UNESCAPED_SLASHES); ?>;

// ── Toast on page load (from session) ────────────────────────────────────────
<?php if (isset($_SESSION['message'])): ?>
document.addEventListener('DOMContentLoaded', function () {
    const type = '<?php echo addslashes($_SESSION['message_type'] ?? 'success'); ?>';
    const msg  = '<?php echo addslashes($_SESSION['message']); ?>';
    const toastType = type === 'danger' ? 'error' : type;
    toast[toastType](msg);
});
<?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>

document.addEventListener('DOMContentLoaded', function () {
    const chartCanvas = document.getElementById('studentGenderChart');
    if (!chartCanvas || !studentGenderChartData.values.length) {
        return;
    }

    const totalValue = studentGenderChartData.values.reduce((sum, value) => sum + Number(value || 0), 0);
    if (!totalValue) {
        return;
    }

    new Chart(chartCanvas, {
        type: 'doughnut',
        data: {
            labels: studentGenderChartData.labels,
            datasets: [{
                data: studentGenderChartData.values,
                backgroundColor: studentGenderChartData.colors,
                borderColor: '#ffffff',
                borderWidth: 4,
                hoverOffset: 8,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const value = Number(context.raw || 0);
                            const percent = totalValue ? ((value / totalValue) * 100).toFixed(1) : '0.0';
                            return `${context.label}: ${value} (${percent}%)`;
                        },
                    },
                },
            },
        },
    });
});

// ── Batch modal (single + multi-scheme) ───────────────────────────────────────
const ALL_BATCHES = <?php
    $batch_js = array_map(function ($b) {
        return [
            'id'          => (int)$b['id'],
            'batch_name'  => $b['batch_name'] ?? '',
            'batch_code'  => $b['batch_code'] ?? '',
            'course_id'   => (int)($b['course_id'] ?? 0),
            'course_name' => $b['course_name'] ?? '',
            'scheme_id'   => (int)($b['scheme_id'] ?? 0),
            'scheme_name' => $b['scheme_name'] ?? '',
        ];
    }, $active_batches);
    echo json_encode($batch_js, JSON_UNESCAPED_UNICODE);
?>;

function schemeMatchesBatch(studentSchemeId, batchSchemeId) {
    const s = parseInt(studentSchemeId, 10) || 0;
    const b = parseInt(batchSchemeId, 10) || 0;
    if (s === 0) return true;
    if (b === 0) return false;
    return s === b;
}

function courseMatchesBatch(studentCourseId, batchCourseId, studentCourseName, batchCourseName) {
    const studentId = parseInt(studentCourseId, 10) || 0;
    const batchId = parseInt(batchCourseId, 10) || 0;
    if (studentId > 0 && batchId > 0) {
        return studentId === batchId;
    }
    const norm = (studentCourseName || '').trim().toLowerCase();
    const optCourse = (batchCourseName || '').trim().toLowerCase();
    return norm !== '' && optCourse === norm;
}

function batchOptionLabel(batch) {
    let label = batch.batch_name + ' (' + batch.batch_code + ')';
    if (batch.scheme_name) {
        label += ' — ' + batch.scheme_name;
    }
    return label;
}

function getEligibleBatchesForEnrollment(enrollment, courseId, courseName) {
    const assigned = new Set((enrollment.assigned_batch_ids || []).map(String));
    return ALL_BATCHES.filter(batch => {
        return courseMatchesBatch(courseId, batch.course_id, courseName, batch.course_name)
            && schemeMatchesBatch(enrollment.scheme_id || 0, batch.scheme_id)
            && !assigned.has(String(batch.id));
    });
}

function openBatchModal(studentRecordId, studentName, course, studentSchemeId, courseId, assignedBatchIds, enrollmentsJson) {
    document.getElementById('modal-student-name').textContent = studentName;
    document.getElementById('modal-course').textContent       = course;

    let enrollments = [];
    try {
        enrollments = JSON.parse(enrollmentsJson || '[]');
    } catch (e) {
        enrollments = [];
    }

    if (!enrollments.length) {
        enrollments = [{
            record_id: parseInt(studentRecordId, 10) || 0,
            scheme_id: parseInt(studentSchemeId, 10) || 0,
            scheme_name: 'This enrollment',
            assigned_batch_ids: String(assignedBatchIds || '')
                .split(',')
                .map(id => parseInt(id, 10))
                .filter(id => id > 0),
        }];
    }

    const multi = enrollments.length > 1;
    const hasAnyBatch = enrollments.some(enr => (enr.assigned_batch_ids || []).length > 0);
    const hintEl = document.getElementById('batch-modal-multi-hint');
    const hintTextEl = document.getElementById('batch-modal-multi-hint-text');
    if (multi) {
        hintTextEl.textContent = 'This student has multiple scheme enrollments. Pick a batch for each scheme below.';
        hintEl.style.display = 'block';
    } else if (hasAnyBatch) {
        hintTextEl.textContent = 'Student is already in one or more batches. Select another batch below to add them.';
        hintEl.style.display = 'block';
    } else {
        hintEl.style.display = 'none';
    }

    const container = document.getElementById('batch-modal-enrollments');
    container.innerHTML = '';
    let totalOptions = 0;

    enrollments.forEach(enrollment => {
        const eligible = getEligibleBatchesForEnrollment(enrollment, courseId, course);
        totalOptions += eligible.length;

        const block = document.createElement('div');
        block.className = 'form-group';
        block.style.marginBottom = '14px';

        const label = document.createElement('label');
        label.className = 'form-label';
        const hasBatch = (enrollment.assigned_batch_ids || []).length > 0;
        label.textContent = enrollment.scheme_name
            + (hasBatch ? ' (add another batch)' : '');

        const select = document.createElement('select');
        select.name = 'batch_assignments[' + enrollment.record_id + ']';
        select.className = 'form-control batch-enrollment-select';
        if (enrollment.record_id === parseInt(studentRecordId, 10)) {
            select.dataset.focus = '1';
        }

        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = '-- Select a Batch --';
        select.appendChild(empty);

        eligible.forEach(batch => {
            const opt = document.createElement('option');
            opt.value = batch.id;
            opt.textContent = batchOptionLabel(batch);
            select.appendChild(opt);
        });

        if (!eligible.length) {
            const none = document.createElement('option');
            none.value = '';
            none.textContent = 'No batches available for this scheme';
            none.disabled = true;
            select.appendChild(none);
        }

        block.appendChild(label);
        block.appendChild(select);
        container.appendChild(block);
    });

    document.getElementById('batch-modal-empty').style.display = totalOptions === 0 ? 'block' : 'none';

    const focusSelect = container.querySelector('select[data-focus="1"]')
        || container.querySelector('select');
    if (focusSelect) {
        focusSelect.focus();
    }

    document.getElementById('batchModal').style.display = 'block';
}
function closeBatchModal() {
    document.getElementById('batchModal').style.display = 'none';
}

// ── Bulk batch modal ──────────────────────────────────────────────────────────
function openBulkBatchModal() {
    const checked = document.querySelectorAll('.student-checkbox:checked');
    if (!checked.length) { toast.warning('Please select at least one student'); return; }

    document.getElementById('bulk-modal-count').textContent = checked.length;

    const container = document.getElementById('bulk-student-ids');
    container.innerHTML = '';
    const courses = new Set();
    const courseIds = new Set();

    checked.forEach(cb => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'student_record_ids[]'; inp.value = cb.value;
        container.appendChild(inp);
        const c = (cb.dataset.course || '').trim().toLowerCase();
        if (c) courses.add(c);
        const cid = parseInt(cb.dataset.courseId, 10) || 0;
        if (cid > 0) courseIds.add(cid);
    });

    document.getElementById('bulk-modal-batch-select').querySelectorAll('option').forEach(opt => {
        if (!opt.value) { opt.style.display = ''; return; }
        const batchCourseId = parseInt(opt.dataset.courseId, 10) || 0;
        const oc = (opt.dataset.course || '').trim().toLowerCase();
        const courseOk = (batchCourseId > 0 && courseIds.has(batchCourseId))
            || (courseIds.size === 0 && courses.has(oc));
        let schemeOk = true;
        checked.forEach(cb => {
            if (!schemeMatchesBatch(cb.dataset.schemeId || '0', opt.dataset.schemeId)) {
                schemeOk = false;
            }
        });
        opt.style.display = (courseOk && schemeOk) ? '' : 'none';
    });

    document.getElementById('bulk-modal-batch-select').value = '';
    document.getElementById('bulkBatchModal').style.display = 'block';
}
function closeBulkBatchModal() {
    document.getElementById('bulkBatchModal').style.display = 'none';
}

// ── Bulk remove from course modal ─────────────────────────────────────────────
function openBulkRemoveCourseModal() {
    const checked = document.querySelectorAll('.student-checkbox:checked');
    if (!checked.length) {
        toast.warning('Please select at least one student');
        return;
    }

    document.getElementById('bulk-remove-modal-count').textContent = checked.length;

    const list = document.getElementById('bulk-remove-modal-list');
    const fields = document.getElementById('bulk-remove-course-fields');
    list.innerHTML = '';
    fields.innerHTML = '';

    checked.forEach(cb => {
        const studentId = cb.dataset.studentId || '';
        const studentName = cb.dataset.studentName || 'Student';
        const courseName = cb.dataset.course || 'course';
        const courseId = parseInt(cb.dataset.courseId, 10) || 0;
        if (!studentId || courseId <= 0) {
            return;
        }

        const li = document.createElement('li');
        li.textContent = studentName + ' (' + studentId + ') — ' + courseName;
        list.appendChild(li);

        const sidInput = document.createElement('input');
        sidInput.type = 'hidden';
        sidInput.name = 'bulk_remove_student_ids[]';
        sidInput.value = studentId;
        fields.appendChild(sidInput);

        const cidInput = document.createElement('input');
        cidInput.type = 'hidden';
        cidInput.name = 'bulk_remove_course_ids[]';
        cidInput.value = String(courseId);
        fields.appendChild(cidInput);
    });

    if (!fields.children.length) {
        toast.warning('Selected students are missing course details.');
        return;
    }

    document.getElementById('bulkRemoveCourseModal').style.display = 'block';
}
function closeBulkRemoveCourseModal() {
    const modal = document.getElementById('bulkRemoveCourseModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// ── Rejection modal ───────────────────────────────────────────────────────────
function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}
function highlightReason(radio) {
    document.querySelectorAll('#rejectModal .reason-label').forEach(l => {
        l.style.borderColor = '#e5e7eb'; l.style.background = 'white';
    });
    radio.parentElement.style.borderColor = '#dc2626';
    radio.parentElement.style.background  = '#fff5f5';
    document.getElementById('otherNoteDiv').style.display = radio.value === 'Other' ? 'block' : 'none';
}

// ── Selection UI update ───────────────────────────────────────────────────────
function updateSelectionUI() {
    const checked = document.querySelectorAll('.student-checkbox:checked');
    const count = checked.length;
    const pendingCount = Array.from(checked).filter(cb => (cb.dataset.status || '') === 'pending').length;

    document.getElementById('selected-count').style.display = count ? 'inline' : 'none';
    document.getElementById('bulk-assign-btn').style.display = count ? 'inline-block' : 'none';
    const bulkApproveBtn = document.getElementById('bulk-approve-btn');
    if (bulkApproveBtn) {
        bulkApproveBtn.style.display = pendingCount ? 'inline-block' : 'none';
    }
    const bulkRemoveBtn = document.getElementById('bulk-remove-course-btn');
    if (bulkRemoveBtn) {
        bulkRemoveBtn.style.display = count ? 'inline-block' : 'none';
    }
    document.getElementById('count-number').textContent = count;
}

async function submitBulkApproveSelected() {
    const checked = Array.from(document.querySelectorAll('.student-checkbox:checked'))
        .filter(cb => (cb.dataset.status || '') === 'pending');
    if (!checked.length) {
        toast.warning('Select at least one pending student to approve.');
        return;
    }

    const studentIds = [...new Set(checked.map(cb => cb.dataset.studentId).filter(Boolean))];
    const confirmed = await showConfirm({
        title: 'Approve Selected Students',
        message: `Approve <strong>${studentIds.length}</strong> pending student(s)? They will become Active and can log in.`,
        confirmText: 'Approve Selected',
        cancelText: 'Cancel',
        type: 'warning'
    });
    if (!confirmed) {
        return;
    }

    const fields = document.getElementById('bulk-approve-student-fields');
    fields.innerHTML = '';
    studentIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'bulk_approve_student_ids[]';
        input.value = id;
        fields.appendChild(input);
    });

    toast.loading('Approving students…');
    document.getElementById('bulk-approve-submit').click();
}

// ── DOMContentLoaded wiring ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    // Assign batch buttons
    document.querySelectorAll('.assign-batch-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            openBatchModal(
                this.dataset.studentRecordId,
                this.dataset.studentName,
                this.dataset.course,
                this.dataset.schemeId || '0',
                this.dataset.courseId || '0',
                this.dataset.assignedBatchIds || '',
                this.dataset.enrollments || '[]'
            );
        });
    });

    // Remove from batch (delegated — works for × icon inside batch badges)
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.remove-batch-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const confirmed = await showConfirm({
            title: 'Remove from Batch',
            message: `Remove <strong>${btn.dataset.studentName}</strong> from <strong>${btn.dataset.batchName}</strong>?`,
            confirmText: 'Remove', cancelText: 'Cancel', type: 'warning'
        });
        if (confirmed) { toast.loading('Removing…'); window.location.href = btn.dataset.url; }
    });

    // Remove from this course only
    document.querySelectorAll('.remove-course-btn').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const keepNote = this.dataset.hasOtherCourses === '1'
                ? ' Other course enrollments will be kept.'
                : '';
            const confirmed = await showConfirm({
                title: 'Remove From Course',
                message: `Remove <strong>${this.dataset.studentName}</strong> from <strong>${this.dataset.courseName}</strong> only?${keepNote}`,
                confirmText: 'Remove From Course', cancelText: 'Cancel', type: 'warning'
            });
            if (confirmed) { toast.loading('Removing from course…'); window.location.href = this.dataset.url; }
        });
    });

    // Delete buttons
    document.querySelectorAll('.delete-student-btn').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const allCoursesNote = this.dataset.hasOtherCourses === '1'
                ? ' This will delete the student from <strong>all courses</strong>, not just the one shown in this row.'
                : '';
            const confirmed = await showConfirm({
                title: 'Delete From All Courses',
                message: `Delete <strong>${this.dataset.studentName}</strong> (${this.dataset.studentId}) from every course?${allCoursesNote} This cannot be undone.`,
                confirmText: 'Delete All', cancelText: 'Cancel', type: 'danger'
            });
            if (confirmed) { toast.loading('Deleting…'); window.location.href = this.dataset.url; }
        });
    });

    // Approve buttons
    document.querySelectorAll('.approve-student-btn').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const confirmed = await showConfirm({
                title: 'Approve Student',
                message: `Approve <strong>${this.dataset.studentName}</strong> (${this.dataset.studentId})?`,
                confirmText: 'Approve', cancelText: 'Cancel', type: 'warning'
            });
            if (confirmed) { toast.loading('Approving…'); window.location.href = this.dataset.url; }
        });
    });

    // De-approve buttons
    document.querySelectorAll('.deapprove-student-btn').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const confirmed = await showConfirm({
                title: 'De-Approve Student',
                message: `De-approve <strong>${this.dataset.studentName}</strong> (${this.dataset.studentId})? They will return to pending status and cannot log in until approved again.`,
                confirmText: 'De-Approve', cancelText: 'Cancel', type: 'warning'
            });
            if (confirmed) { toast.loading('Updating status…'); window.location.href = this.dataset.url; }
        });
    });

    // Reject buttons → open reason modal
    document.querySelectorAll('.reject-student-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('rejectStudentName').textContent = this.dataset.studentName;
            document.getElementById('rejectStudentId').value         = this.dataset.studentId;
            document.querySelectorAll('#rejectModal input[type="radio"]').forEach(r => r.checked = false);
            document.querySelectorAll('#rejectModal .reason-label').forEach(l => {
                l.style.borderColor = '#e5e7eb'; l.style.background = 'white';
            });
            document.getElementById('otherNoteDiv').style.display = 'none';
            document.getElementById('rejectModal').style.display  = 'flex';
        });
    });

    // Reject modal backdrop
    document.getElementById('rejectModal').addEventListener('click', function (e) {
        if (e.target === this) closeRejectModal();
    });

    // Select-all checkbox
    const selectAll = document.getElementById('select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked);
            updateSelectionUI();
        });
    }

    // Individual checkboxes
    document.querySelectorAll('.student-checkbox').forEach(cb => {
        cb.addEventListener('change', function () {
            updateSelectionUI();
            const all     = document.querySelectorAll('.student-checkbox');
            const checked = document.querySelectorAll('.student-checkbox:checked');
            if (selectAll) selectAll.checked = all.length > 0 && all.length === checked.length;
        });
    });

    // Bulk assign button
    const bulkBtn = document.getElementById('bulk-assign-btn');
    if (bulkBtn) bulkBtn.addEventListener('click', openBulkBatchModal);

    const bulkRemoveBtn = document.getElementById('bulk-remove-course-btn');
    if (bulkRemoveBtn) bulkRemoveBtn.addEventListener('click', openBulkRemoveCourseModal);

    const bulkApproveBtn = document.getElementById('bulk-approve-btn');
    if (bulkApproveBtn) bulkApproveBtn.addEventListener('click', submitBulkApproveSelected);

    // Close modals on outside click
    window.addEventListener('click', function (e) {
        if (e.target === document.getElementById('batchModal'))     closeBatchModal();
        if (e.target === document.getElementById('bulkBatchModal')) closeBulkBatchModal();
        if (e.target === document.getElementById('bulkRemoveCourseModal')) closeBulkRemoveCourseModal();
    });
});
</script>

<div id="studentPhotoLightbox" class="student-photo-lightbox" role="dialog" aria-modal="true" aria-label="Student photo" hidden>
    <div class="student-photo-lightbox-inner">
        <button type="button" class="student-photo-lightbox-close" id="studentPhotoLightboxClose" aria-label="Close photo">&times;</button>
        <img id="studentPhotoLightboxImg" src="" alt="Student photo">
        <div class="student-photo-lightbox-caption" id="studentPhotoLightboxCaption"></div>
        <div class="student-photo-lightbox-hint">Click anywhere to close</div>
    </div>
</div>
<script>
(function () {
    const lightbox = document.getElementById('studentPhotoLightbox');
    const lightboxImg = document.getElementById('studentPhotoLightboxImg');
    const lightboxCaption = document.getElementById('studentPhotoLightboxCaption');
    const closeBtn = document.getElementById('studentPhotoLightboxClose');
    if (!lightbox || !lightboxImg) return;

    function openStudentPhoto(url, name) {
        lightboxImg.src = url;
        lightboxImg.alt = name ? (name + ' photo') : 'Student photo';
        lightboxCaption.textContent = name || '';
        lightbox.hidden = false;
        lightbox.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeStudentPhoto() {
        lightbox.classList.remove('is-open');
        lightbox.hidden = true;
        lightboxImg.removeAttribute('src');
        lightboxCaption.textContent = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('.student-photo-link');
        if (link) {
            e.preventDefault();
            openStudentPhoto(link.dataset.photoUrl || link.getAttribute('href'), link.dataset.photoName || '');
            return;
        }
        if (lightbox.classList.contains('is-open')) {
            closeStudentPhoto();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeStudentPhoto();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && lightbox.classList.contains('is-open')) {
            closeStudentPhoto();
        }
    });
})();
</script>
</body>
</html>
<?php $conn->close(); ?>