<?php
/**
 * NIELIT BHUBANESWAR - STUDENTS EXCEL EXPORT
 * Exports enrollment rows (aligned with students.php filters)
 */

session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

$admin_courses = [];
$admin_course_ids = [];
$is_course_coordinator = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'course_coordinator';

if ($is_course_coordinator) {
    $admin_id = $_SESSION['admin_id'] ?? null;

    if (!$admin_id && isset($_SESSION['admin'])) {
        $admin_username = $_SESSION['admin'];
        $admin_stmt = $conn->prepare('SELECT id FROM admin WHERE username = ?');
        if ($admin_stmt) {
            $admin_stmt->bind_param('s', $admin_username);
            $admin_stmt->execute();
            $admin_row = $admin_stmt->get_result()->fetch_assoc();
            $admin_stmt->close();
            if ($admin_row) {
                $admin_id = (int)$admin_row['id'];
                $_SESSION['admin_id'] = $admin_id;
            }
        }
    }

    if ($admin_id) {
        $course_stmt = $conn->prepare(
            'SELECT c.id, c.course_name
             FROM admin_course_assignments aca
             JOIN courses c ON aca.course_id = c.id
             WHERE aca.admin_id = ? AND aca.is_active = 1'
        );
        if ($course_stmt) {
            $course_stmt->bind_param('i', $admin_id);
            $course_stmt->execute();
            $course_result = $course_stmt->get_result();
            while ($course_row = $course_result->fetch_assoc()) {
                $admin_courses[] = $course_row['course_name'];
                $admin_course_ids[] = (int)$course_row['id'];
            }
            $course_stmt->close();
        }
    }
}

$selected_course = $_GET['filter_course'] ?? 'All';
$selected_gender = $_GET['filter_gender'] ?? 'All';
$selected_category = $_GET['filter_category'] ?? 'All';
$selected_status = $_GET['filter_status'] ?? 'All';
$student_category_filter_options = ['General', 'OBC', 'SC', 'ST', 'EWS'];
$student_status_filter_options = ['pending', 'active', 'rejected'];
if ($selected_category !== 'All' && !in_array($selected_category, $student_category_filter_options, true)) {
    $selected_category = 'All';
}
if ($selected_status !== 'All' && !in_array(strtolower($selected_status), $student_status_filter_options, true)) {
    $selected_status = 'All';
} else {
    $selected_status = $selected_status === 'All' ? 'All' : strtolower($selected_status);
}
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$query = "SELECT s.*, b.batch_name, b.batch_code, c.course_name,
                 sch.scheme_name, sch.scheme_code,
                 GROUP_CONCAT(DISTINCT CONCAT(ed.exam_passed, ' - ', ed.exam_name, ' (', ed.year_of_passing, ')') SEPARATOR '; ') AS education_summary
          FROM students s
          LEFT JOIN batches b ON s.batch_id = b.id
          LEFT JOIN courses c ON c.id = s.course_id
          LEFT JOIN schemes sch ON sch.id = s.scheme_id
          LEFT JOIN education_details ed ON s.student_id = ed.student_id
          WHERE 1=1";

$bind_types = '';
$bind_values = [];

if ($is_course_coordinator) {
    if (!empty($admin_course_ids)) {
        $ph = implode(',', array_fill(0, count($admin_course_ids), '?'));
        $query .= " AND s.course_id IN ($ph) AND s.batch_id IS NULL AND LOWER(s.status) != 'inactive'";
        $bind_types .= str_repeat('i', count($admin_course_ids));
        $bind_values = array_merge($bind_values, $admin_course_ids);
    } else {
        $query .= ' AND 1=0';
    }
}

if ($selected_course !== 'All') {
    $query .= ' AND s.course_id = ?';
    $bind_types .= 'i';
    $bind_values[] = (int)$selected_course;
}

if ($selected_gender !== 'All') {
    $query .= ' AND s.gender = ?';
    $bind_types .= 's';
    $bind_values[] = $selected_gender;
}

if ($selected_category !== 'All') {
    $query .= ' AND s.category = ?';
    $bind_types .= 's';
    $bind_values[] = $selected_category;
}

if ($selected_status !== 'All') {
    $query .= ' AND LOWER(s.status) = ?';
    $bind_types .= 's';
    $bind_values[] = $selected_status;
}

if (!empty($start_date) && !empty($end_date)) {
    $query .= ' AND s.created_at BETWEEN ? AND ?';
    $bind_types .= 'ss';
    $bind_values[] = $start_date;
    $bind_values[] = $end_date;
}

$query .= ' GROUP BY s.id ORDER BY s.created_at DESC';

$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Error preparing statement: ' . $conn->error);
}

if (!empty($bind_values)) {
    $stmt->bind_param($bind_types, ...$bind_values);
}

if (!$stmt->execute()) {
    die('Error executing statement: ' . $stmt->error);
}

$result = $stmt->get_result();
if (!$result) {
    die('Error getting result: ' . $stmt->error);
}

$filename = 'NIELIT_Students_Export_' . date('Y-m-d_H-i-s');
if ($selected_course !== 'All') {
    $filename .= '_course_' . (int)$selected_course;
}
if (!empty($start_date) && !empty($end_date)) {
    $filename .= '_' . $start_date . '_to_' . $end_date;
}
$filename .= '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

$headers = [
    'Sl. No.',
    'Student ID',
    'Name',
    'Father Name',
    'Mother Name',
    'Date of Birth',
    'Age',
    'Gender',
    'Marital Status',
    'Mobile',
    'Email',
    'Aadhar',
    'APAAR ID',
    'Nationality',
    'Religion',
    'Category',
    'PWD Status',
    'Position',
    'Distinguishing Marks',
    'Address',
    'City',
    'State',
    'Pincode',
    'Course',
    'Scheme / Project',
    'Training Center',
    'College Name',
    'Education Details',
    'UTR Number',
    'Batch Name',
    'Batch Code',
    'Status',
    'Registration Date',
    'Last Updated',
];

fputcsv($output, $headers);

$sl_no = 1;
while ($row = $result->fetch_assoc()) {
    $age = '';
    if (!empty($row['dob'])) {
        $dob = new DateTime($row['dob']);
        $today = new DateTime();
        $age = $today->diff($dob)->y;
    }

    $courseLabel = !empty($row['course_name']) ? $row['course_name'] : ($row['course'] ?? '');

    fputcsv($output, [
        $sl_no++,
        $row['student_id'] ?? '',
        $row['name'] ?? '',
        $row['father_name'] ?? '',
        $row['mother_name'] ?? '',
        $row['dob'] ?? '',
        $age,
        $row['gender'] ?? '',
        $row['marital_status'] ?? '',
        $row['mobile'] ?? '',
        $row['email'] ?? '',
        $row['aadhar'] ?? '',
        $row['apaar_id'] ?? '',
        $row['nationality'] ?? '',
        $row['religion'] ?? '',
        $row['category'] ?? '',
        $row['pwd_status'] ?? '',
        $row['position'] ?? '',
        $row['distinguishing_marks'] ?? '',
        $row['address'] ?? '',
        $row['city'] ?? '',
        $row['state'] ?? '',
        $row['pincode'] ?? '',
        $courseLabel,
        $row['scheme_name'] ?? '',
        $row['training_center'] ?? '',
        $row['college_name'] ?? '',
        $row['education_summary'] ?? '',
        $row['utr_number'] ?? '',
        $row['batch_name'] ?? 'Not Assigned',
        $row['batch_code'] ?? '',
        ucfirst($row['status'] ?? ''),
        !empty($row['created_at']) ? date('d-m-Y H:i:s', strtotime($row['created_at'])) : '',
        !empty($row['updated_at']) ? date('d-m-Y H:i:s', strtotime($row['updated_at'])) : '',
    ]);
}

fclose($output);
$stmt->close();
$conn->close();
exit();
