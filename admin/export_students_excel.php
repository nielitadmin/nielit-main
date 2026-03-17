<?php
/**
 * NIELIT BHUBANESWAR - STUDENTS EXCEL EXPORT
 * Export all student details to Excel format
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");
    exit();
}

// Get admin's assigned courses for filtering (same logic as students.php)
$admin_courses = [];
$admin_course_ids = [];
$is_course_coordinator = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'course_coordinator';

if ($is_course_coordinator) {
    // Get admin_id from session or fetch from database
    $admin_id = $_SESSION['admin_id'] ?? null;
    
    // If admin_id not in session, fetch it from database using username
    if (!$admin_id && isset($_SESSION['admin'])) {
        $admin_username = $_SESSION['admin'];
        $admin_query = "SELECT id FROM admin WHERE username = ?";
        $admin_stmt = $conn->prepare($admin_query);
        $admin_stmt->bind_param("s", $admin_username);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        if ($admin_row = $admin_result->fetch_assoc()) {
            $admin_id = $admin_row['id'];
            $_SESSION['admin_id'] = $admin_id;
        }
    }
    
    // Get assigned courses for this coordinator
    if ($admin_id) {
        $course_query = "SELECT c.id, c.course_name 
                        FROM admin_course_assignments aca
                        JOIN courses c ON aca.course_id = c.id
                        WHERE aca.admin_id = ? AND aca.is_active = 1";
        $course_stmt = $conn->prepare($course_query);
        $course_stmt->bind_param("i", $admin_id);
        $course_stmt->execute();
        $course_result = $course_stmt->get_result();
        while ($course_row = $course_result->fetch_assoc()) {
            $admin_courses[] = $course_row['course_name'];
            $admin_course_ids[] = $course_row['id'];
        }
    }
}

// Handle filters (same as students.php)
$selected_course = isset($_GET['filter_course']) ? $_GET['filter_course'] : 'All';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build query with same filtering logic as students.php
$query = "SELECT s.*, b.batch_name, b.batch_code,
          GROUP_CONCAT(DISTINCT CONCAT(ed.exam_passed, ' - ', ed.exam_name, ' (', ed.year_of_passing, ')') SEPARATOR '; ') as education_details
          FROM students s 
          LEFT JOIN batches b ON s.batch_id = b.id 
          LEFT JOIN education_details ed ON s.student_id = ed.student_id
          WHERE 1=1";

// Apply same filtering logic as students.php
if ($is_course_coordinator) {
    if (!empty($admin_courses)) {
        $placeholders = str_repeat('?,', count($admin_courses) - 1) . '?';
        $query .= " AND s.course IN ($placeholders)";
        
        // For course coordinators, show only students not assigned to batches and not rejected
        $query .= " AND s.batch_id IS NULL AND s.status != 'rejected'";
    } else {
        // Coordinator has no assigned courses - show no students
        $query .= " AND 1=0";
    }
}

if ($selected_course != 'All') {
    $query .= " AND s.course = ?";
}

if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND s.created_at BETWEEN ? AND ?";
}

$query .= " GROUP BY s.student_id ORDER BY s.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);

// Bind parameters dynamically (same logic as students.php)
$bind_types = '';
$bind_values = [];

// Add admin courses if coordinator
if ($is_course_coordinator && !empty($admin_courses)) {
    $bind_types .= str_repeat('s', count($admin_courses));
    $bind_values = array_merge($bind_values, $admin_courses);
}

// Add selected course filter
if ($selected_course != 'All') {
    $bind_types .= 's';
    $bind_values[] = $selected_course;
}

// Add date range filter
if (!empty($start_date) && !empty($end_date)) {
    $bind_types .= 'ss';
    $bind_values[] = $start_date;
    $bind_values[] = $end_date;
}

// Bind parameters if any
if (!empty($bind_values)) {
    $stmt->bind_param($bind_types, ...$bind_values);
}

$stmt->execute();
$result = $stmt->get_result();

// Generate filename with timestamp and filters
$filename = 'NIELIT_Students_Export_' . date('Y-m-d_H-i-s');
if ($selected_course != 'All') {
    $filename .= '_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $selected_course);
}
if (!empty($start_date) && !empty($end_date)) {
    $filename .= '_' . $start_date . '_to_' . $end_date;
}
$filename .= '.csv';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
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
    'Training Center',
    'College Name',
    'Education Details',
    'UTR Number',
    'Batch Name',
    'Batch Code',
    'Status',
    'Registration Date',
    'Last Updated'
];

fputcsv($output, $headers);

// Export data
$sl_no = 1;
while ($row = $result->fetch_assoc()) {
    // Calculate age from DOB
    $age = '';
    if (!empty($row['dob'])) {
        $dob = new DateTime($row['dob']);
        $today = new DateTime();
        $age = $today->diff($dob)->y;
    }
    
    $data = [
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
        $row['course'] ?? '',
        $row['training_center'] ?? '',
        $row['college_name'] ?? '',
        $row['education_details'] ?? '',
        $row['utr_number'] ?? '',
        $row['batch_name'] ?? 'Not Assigned',
        $row['batch_code'] ?? '',
        ucfirst($row['status'] ?? ''),
        !empty($row['created_at']) ? date('d-m-Y H:i:s', strtotime($row['created_at'])) : '',
        !empty($row['updated_at']) ? date('d-m-Y H:i:s', strtotime($row['updated_at'])) : ''
    ];
    
    fputcsv($output, $data);
}

fclose($output);
$conn->close();
exit();
?>