<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['admin'])) {
    die('Unauthorized');
}

$batch_id = $_GET['batch_id'];
$scheme_id = $_GET['scheme_id'];

// Fetch batch details (same as PDF version)
$batch_query = "SELECT b.*, c.course_name, c.course_code, c.duration, c.training_fees, c.course_coordinator,
                s.scheme_name, s.scheme_code
                FROM batches b
                LEFT JOIN courses c ON b.course_id = c.id
                LEFT JOIN schemes s ON b.scheme_id = s.id
                WHERE b.id = ?";
$stmt = $conn->prepare($batch_query);

if (!$stmt) {
    $batch_query = "SELECT b.*, c.course_name, c.course_code, c.duration, c.training_fees, c.course_coordinator,
                    NULL as scheme_name, NULL as scheme_code
                    FROM batches b
                    LEFT JOIN courses c ON b.course_id = c.id
                    WHERE b.id = ?";
    $stmt = $conn->prepare($batch_query);
}

$stmt->bind_param("i", $batch_id);
$stmt->execute();
$batch = $stmt->get_result()->fetch_assoc();

if (!$batch) {
    die("Batch not found");
}

// Auto-generate reference number if not set
if (empty($batch['admission_order_ref'])) {
    $batch['admission_order_ref'] = "NIELIT/BBSR/Admission Order/FY-" . date('y') . "-" . (date('y')+1) . "/" . $batch_id;
}

$order_date = !empty($batch['admission_order_date']) ? $batch['admission_order_date'] : date('Y-m-d');

if (empty($batch['examination_month'])) {
    $batch['examination_month'] = date('F Y', strtotime($batch['end_date']));
}

$faculty_name = !empty($batch['course_coordinator']) ? $batch['course_coordinator'] : 
                (!empty($batch['batch_coordinator']) ? $batch['batch_coordinator'] : 'To be assigned');

$scheme_incharge = !empty($batch['scheme_incharge']) ? $batch['scheme_incharge'] : $faculty_name;
$class_time = !empty($batch['class_time']) ? $batch['class_time'] : '9:00 AM to 1:30 PM';
$location = !empty($batch['location']) ? $batch['location'] : 'NIELIT Bhubaneswar';

// Fetch students
$students = [];
$check_batch_students = $conn->query("SHOW TABLES LIKE 'batch_students'");
if ($check_batch_students && $check_batch_students->num_rows > 0) {
    $students_query = "SELECT s.id, s.name as full_name, s.father_name, s.mobile, s.aadhar as aadhar_number, 
                       s.gender, s.category, bs.enrollment_date, s.nielit_registration_no
                       FROM batch_students bs
                       INNER JOIN students s ON bs.student_id = s.id
                       WHERE bs.batch_id = ?
                       ORDER BY s.name";
    
    $stmt = $conn->prepare($students_query);
    if ($stmt) {
        $stmt->bind_param("i", $batch_id);
        $stmt->execute();
        $students_result = $stmt->get_result();
        
        while ($row = $students_result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
    }
}

if (empty($students)) {
    $students_query = "SELECT s.id, s.name as full_name, s.father_name, s.mobile, s.aadhar as aadhar_number, 
                       s.gender, s.category, s.created_at as enrollment_date, s.nielit_registration_no
                       FROM students s
                       WHERE s.batch_id = ?
                       ORDER BY s.name";
    
    $stmt = $conn->prepare($students_query);
    if ($stmt) {
        $stmt->bind_param("i", $batch_id);
        $stmt->execute();
        $students_result = $stmt->get_result();
        
        while ($row = $students_result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
    }
}

if (empty($students)) {
    die("No students found for this batch");
}

// Count by category and gender
$category_gender_counts = [
    'SC' => ['M' => 0, 'F' => 0],
    'ST' => ['M' => 0, 'F' => 0],
    'OBC' => ['M' => 0, 'F' => 0],
    'GEN' => ['M' => 0, 'F' => 0],
    'PWD' => ['M' => 0, 'F' => 0]
];

foreach ($students as $student) {
    $category = strtoupper(trim($student['category'] ?? 'GEN'));
    $gender = strtoupper(substr(trim($student['gender'] ?? 'M'), 0, 1));
    
    if ($category == 'GENERAL' || empty($category)) {
        $category = 'GEN';
    }
    
    if (!isset($category_gender_counts[$category])) {
        $category = 'GEN';
    }
    
    if (($gender == 'M' || $gender == 'F')) {
        $category_gender_counts[$category][$gender]++;
    }
}

$total_male = 0;
$total_female = 0;
foreach ($category_gender_counts as $counts) {
    $total_male += $counts['M'];
    $total_female += $counts['F'];
}

$total_students = count($students);

// Get copy to list
$default_copy_to = [
    'Director Incharge, NIELIT Bhubaneswar, for Kind Information',
    'Incharge MIS, NIELIT Bhubaneswar, for Kind Information and necessary action for institute monthly MIS data',
    'Examination Incharge, NIELIT Bhubaneswar, For Kind Information and necessary action',
    'Ms. SukanyaPalli, Assistant Accounts& DDO, Account Section, NIELIT Bhubaneswar, For Kind Information and necessary action'
];

if (!empty($batch['copy_to_list'])) {
    $copy_to_list = array_filter(array_map('trim', explode("\n", $batch['copy_to_list'])));
} else {
    $copy_to_list = $default_copy_to;
}

// Generate filename
$filename = 'Admission_Order_' . $batch['batch_name'] . '_' . date('Y-m-d') . '.doc';

// Set headers for Word document download
header('Content-Type: application/msword');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Generate RTF content (Rich Text Format - compatible with Word)
echo '{\rtf1\ansi\deff0 {\fonttbl {\f0 Times New Roman;}}';
echo '\f0\fs24'; // Font size 12pt

// Header
echo '\qc\b\fs28 राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्प्रौद्योगिकी संस्थान (रा.इ.सू.प्रौ. सं) भुवनेश्वर\b0\fs24\par';
echo '\qc\b\fs26 National Institute of Electronics and Information Technology (NIELIT)\b0\fs24\par';
echo '\qc\b\fs22 Bhubaneswar/Balasore Extension Centre\b0\fs24\par';
echo '\qc\fs18 (An Autonomous Scientific Society of Ministry of Electronics and Information Technology (MeitY), Govt. of India)\fs24\par\par';

// Reference and Date
echo '\ql\b Ref: ' . $batch['admission_order_ref'] . '\b0\tab\tab\tab\tab\b Dated: ' . date('d.m.Y', strtotime($order_date)) . '\b0\par\par';

// Title
echo '\qc\b\ul\fs28 ADMISSION ORDER\ul0\b0\fs24\par\par';

// Admission details
echo '\ql The following eligible students are admitted in the \b ' . $batch['batch_name'] . '\b0 Batch of "\b ' . $batch['course_name'] . '\b0" which commenced from \b ' . date('d-m-Y', strtotime($batch['start_date'])) . '\b0.\par\par';

// Course details table (simplified for RTF)
echo '\ql\b Location:\b0 ' . $location . '\tab\tab\b Faculty Name:\b0 ' . $faculty_name . '\par';
echo '\b Course Name:\b0 ' . $batch['course_name'] . '\tab\tab\b Start Date:\b0 ' . date('d.m.Y', strtotime($batch['start_date'])) . '\par';
echo '\b Batch ID:\b0 ' . $batch['batch_name'] . '\tab\tab\b End Date:\b0 ' . date('d.m.Y', strtotime($batch['end_date'])) . '\par';
echo '\b Exam Month:\b0 ' . $batch['examination_month'] . '\tab\tab\b Time:\b0 ' . $class_time . '\par';
echo '\b Scheme:\b0 ' . ($batch['scheme_name'] ?? 'General') . '\tab\tab\b Duration:\b0 ' . $batch['duration'] . '\par\par';

// Students table header
echo '\ql\b SL\tab NIELIT REG\tab NAME\tab FATHER NAME\tab MOBILE\tab AADHAAR\tab GEN\tab CAT\tab REMARK\b0\par';
echo '\\trowd\\trgaph108\\trleft-108\\cellx1000\\cellx2500\\cellx4500\\cellx6500\\cellx7500\\cellx8500\\cellx9000\\cellx9500\\cellx11000';

// Student rows
$sl_no = 1;
foreach ($students as $student) {
    echo '\\intbl ' . $sl_no++ . '\\cell ';
    echo ($student['nielit_registration_no'] ?? $student['id']) . '\\cell ';
    echo strtoupper($student['full_name']) . '\\cell ';
    echo strtoupper($student['father_name'] ?? '') . '\\cell ';
    echo $student['mobile'] . '\\cell ';
    echo ($student['aadhar_number'] ?? 'N/A') . '\\cell ';
    echo strtoupper(substr($student['gender'], 0, 1)) . '\\cell ';
    echo strtoupper($student['category'] ?? 'GEN') . '\\cell ';
    echo ($batch['scheme_code'] ?? '') . '\\cell \\row';
}

echo '\par\par';

// Category summary
echo '\ql\b Category Summary:\b0\par';
echo 'SC: M-' . $category_gender_counts['SC']['M'] . ', F-' . $category_gender_counts['SC']['F'] . '\tab ';
echo 'ST: M-' . $category_gender_counts['ST']['M'] . ', F-' . $category_gender_counts['ST']['F'] . '\tab ';
echo 'OBC: M-' . $category_gender_counts['OBC']['M'] . ', F-' . $category_gender_counts['OBC']['F'] . '\par';
echo 'GEN: M-' . $category_gender_counts['GEN']['M'] . ', F-' . $category_gender_counts['GEN']['F'] . '\tab ';
echo 'PWD: M-' . $category_gender_counts['PWD']['M'] . ', F-' . $category_gender_counts['PWD']['F'] . '\tab ';
echo '\b Total: M-' . $total_male . ', F-' . $total_female . ', Total-' . $total_students . '\b0\par\par';

// Footer note
echo '\ql All documents and eligibility of above listed students (' . $total_students . ' No\'s) as per Course norms and Project/scheme norms are checked and Verified by undersigned.\par\par\par';

// Signature section
echo '\qr\b Signature\b0\par';
echo '\qr ' . date('d-m-Y') . '\par';
echo '\qr\b ' . $scheme_incharge . '\b0\par';

// Signature title based on scheme
$scheme_code = $batch['scheme_code'] ?? 'SCSP/TSP';
if (strtolower($scheme_code) === 'regular') {
    echo '\qr\b Project Incharge,\b0\par';
} else {
    echo '\qr\b (' . $scheme_code . ') Incharge,\b0\par';
}

echo '\qr\b NIELIT Bhubaneswar.\b0\par\par';

// Copy to section
echo '\ql\b Copy to:\b0\par';
$counter = 1;
foreach ($copy_to_list as $recipient) {
    echo $counter++ . '. ' . $recipient . '\par';
}

echo '}'; // Close RTF document

$conn->close();
exit;
?>