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
    'Director Project Incharge, NIELIT Bhubaneswar, for Kind Information',
    'Project Incharge MIS, NIELIT Bhubaneswar, for Kind Information and necessary action for institute monthly MIS data',
    'Examination Project Incharge, NIELIT Bhubaneswar, For Kind Information and necessary action',
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

// Generate HTML content (compatible with Word)
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admission Order</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12pt; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .hindi-text { font-size: 14pt; font-weight: bold; }
        .english-text { font-size: 12pt; font-weight: bold; }
        .sub-text { font-size: 10pt; }
        .ref-date { margin: 20px 0; }
        .ref-left { float: left; font-weight: bold; }
        .date-right { float: right; font-weight: bold; }
        .clear { clear: both; }
        .title { text-align: center; font-size: 16pt; font-weight: bold; text-decoration: underline; margin: 20px 0; }
        .details-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .details-table td { padding: 5px; vertical-align: top; }
        .students-table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 10pt; }
        .students-table th, .students-table td { border: 1px solid black; padding: 4px; text-align: center; }
        .students-table th { background-color: #f0f0f0; font-weight: bold; }
        .category-table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 10pt; }
        .category-table th, .category-table td { border: 1px solid black; padding: 3px; text-align: center; }
        .category-table th { background-color: #f0f0f0; }
        .signature { text-align: right; margin-top: 30px; }
        .copy-to { margin-top: 20px; }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="hindi-text"><?php echo htmlspecialchars(INSTITUTE_NAME_HI_FORMAL, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="english-text">National Institute of Electronics and Information Technology (NIELIT)</div>
    <div class="english-text">Bhubaneswar/Baleshwar Extension Centre</div>
    <div class="sub-text">(An Autonomous Scientific Society of Ministry of Electronics and Information Technology (MeitY), Govt. of India)</div>
</div>

<!-- Reference and Date -->
<div class="ref-date">
    <div class="ref-left">Ref: <?php echo htmlspecialchars($batch['admission_order_ref']); ?></div>
    <div class="date-right">Dated: <?php echo date('d.m.Y', strtotime($order_date)); ?></div>
    <div class="clear"></div>
</div>

<!-- Title -->
<div class="title">ADMISSION ORDER</div>

<!-- Admission Details -->
<p>The following eligible students are admitted in the <strong><?php echo $batch['batch_name']; ?></strong> Batch of "<strong><?php echo htmlspecialchars($batch['course_name']); ?></strong>" which commenced from <strong><?php echo date('d-m-Y', strtotime($batch['start_date'])); ?></strong>.</p>

<!-- Course Details -->
<table class="details-table">
    <tr>
        <td width="25%"><strong>Location:</strong></td>
        <td width="25%"><?php echo htmlspecialchars($location); ?></td>
        <td width="25%"><strong>Faculty Name:</strong></td>
        <td width="25%"><?php echo htmlspecialchars($faculty_name); ?></td>
    </tr>
    <tr>
        <td><strong>Course Name:</strong></td>
        <td><?php echo htmlspecialchars($batch['course_name']); ?></td>
        <td><strong>Start Date:</strong></td>
        <td><?php echo date('d.m.Y', strtotime($batch['start_date'])); ?></td>
    </tr>
    <tr>
        <td><strong>Batch ID:</strong></td>
        <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
        <td><strong>End Date:</strong></td>
        <td><?php echo date('d.m.Y', strtotime($batch['end_date'])); ?></td>
    </tr>
    <tr>
        <td><strong>Exam Month:</strong></td>
        <td><?php echo htmlspecialchars($batch['examination_month']); ?></td>
        <td><strong>Time:</strong></td>
        <td><?php echo htmlspecialchars($class_time); ?></td>
    </tr>
    <tr>
        <td><strong>Scheme:</strong></td>
        <td><?php echo htmlspecialchars($batch['scheme_name'] ?? 'General'); ?></td>
        <td><strong>Duration:</strong></td>
        <td><?php echo htmlspecialchars($batch['duration']); ?></td>
    </tr>
</table>

<!-- Students Table -->
<table class="students-table">
    <thead>
        <tr>
            <th width="5%">SL</th>
            <th width="12%">NIELIT REG</th>
            <th width="20%">NAME</th>
            <th width="18%">FATHER NAME</th>
            <th width="12%">MOBILE</th>
            <th width="13%">AADHAAR</th>
            <th width="6%">GEN</th>
            <th width="8%">CAT</th>
            <th width="6%">REMARK</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $sl_no = 1;
        foreach ($students as $student): 
        ?>
        <tr>
            <td><?php echo $sl_no++; ?></td>
            <td><?php echo htmlspecialchars($student['nielit_registration_no'] ?? $student['id']); ?></td>
            <td style="text-align: left;"><?php echo strtoupper(htmlspecialchars($student['full_name'])); ?></td>
            <td style="text-align: left;"><?php echo strtoupper(htmlspecialchars($student['father_name'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($student['mobile']); ?></td>
            <td><?php echo htmlspecialchars($student['aadhar_number'] ?? 'N/A'); ?></td>
            <td><?php echo strtoupper(substr($student['gender'], 0, 1)); ?></td>
            <td><?php echo strtoupper($student['category'] ?? 'GEN'); ?></td>
            <td><?php echo htmlspecialchars($batch['scheme_code'] ?? ''); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Category Summary -->
<table class="category-table">
    <tr>
        <th colspan="2">SC</th>
        <th colspan="2">ST</th>
        <th colspan="2">OBC</th>
        <th colspan="2">PWD</th>
        <th colspan="2">GEN</th>
        <th colspan="2">TOTAL</th>
        <th rowspan="2">TOTAL</th>
    </tr>
    <tr>
        <th>M</th><th>F</th>
        <th>M</th><th>F</th>
        <th>M</th><th>F</th>
        <th>M</th><th>F</th>
        <th>M</th><th>F</th>
        <th>M</th><th>F</th>
    </tr>
    <tr>
        <td><?php echo $category_gender_counts['SC']['M']; ?></td>
        <td><?php echo $category_gender_counts['SC']['F']; ?></td>
        <td><?php echo $category_gender_counts['ST']['M']; ?></td>
        <td><?php echo $category_gender_counts['ST']['F']; ?></td>
        <td><?php echo $category_gender_counts['OBC']['M']; ?></td>
        <td><?php echo $category_gender_counts['OBC']['F']; ?></td>
        <td><?php echo $category_gender_counts['PWD']['M']; ?></td>
        <td><?php echo $category_gender_counts['PWD']['F']; ?></td>
        <td><?php echo $category_gender_counts['GEN']['M']; ?></td>
        <td><?php echo $category_gender_counts['GEN']['F']; ?></td>
        <td><?php echo $total_male; ?></td>
        <td><?php echo $total_female; ?></td>
        <td><?php echo $total_students; ?></td>
    </tr>
</table>

<!-- Footer Note -->
<p>All documents and eligibility of above listed students (<?php echo $total_students; ?> No's) as per Course norms and Project/scheme norms are checked and Verified by undersigned.</p>

<!-- Signature -->
<div class="signature">
    <p><strong>Signature</strong></p>
    <p><?php echo date('d-m-Y'); ?></p>
    <p><strong><?php echo htmlspecialchars($scheme_incharge); ?></strong></p>
    <p><strong>
    <?php 
    $scheme_code = $batch['scheme_code'] ?? 'SCSP/TSP';
    if (strtolower($scheme_code) === 'regular') {
        echo 'Project Incharge,';
    } else {
        echo '(' . htmlspecialchars($scheme_code) . ') Project Incharge,';
    }
    ?>
    </strong></p>
    <p><strong>NIELIT Bhubaneswar.</strong></p>
</div>

<!-- Copy To -->
<div class="copy-to">
    <p><strong>Copy to:</strong></p>
    <ol>
        <?php foreach ($copy_to_list as $recipient): ?>
            <li><?php echo htmlspecialchars($recipient); ?></li>
        <?php endforeach; ?>
    </ol>
</div>

</body>
</html>

<?php
$conn->close();
exit;
?>