<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['admin'])) {
    die('Unauthorized');
}

// Check if PHPWord is available, if not, install it via Composer
$phpword_path = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($phpword_path)) {
    // Create composer.json if it doesn't exist
    $composer_json = __DIR__ . '/../../composer.json';
    if (!file_exists($composer_json)) {
        $composer_config = [
            "require" => [
                "phpoffice/phpword" => "^1.0"
            ]
        ];
        file_put_contents($composer_json, json_encode($composer_config, JSON_PRETTY_PRINT));
    }
    
    // Show installation instructions
    echo "<div style='padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; margin: 20px;'>";
    echo "<h4 style='color: #856404; margin-top: 0;'><i class='fas fa-exclamation-triangle'></i> PHPWord Library Required</h4>";
    echo "<p style='color: #856404;'>To generate Word documents, you need to install PHPWord library using Composer:</p>";
    echo "<ol style='color: #856404;'>";
    echo "<li>Open command prompt/terminal in your project root directory</li>";
    echo "<li>Run: <code>composer install</code></li>";
    echo "<li>If Composer is not installed, download it from <a href='https://getcomposer.org/download/' target='_blank'>getcomposer.org</a></li>";
    echo "</ol>";
    echo "<p style='color: #856404;'><strong>Alternative:</strong> <a href='generate_admission_order_word_simple.php?batch_id=" . $_GET['batch_id'] . "&scheme_id=" . $_GET['scheme_id'] . "' class='btn btn-primary'>Use Simple Word Generator (No Dependencies)</a></p>";
    echo "</div>";
    die();
}

require_once $phpword_path;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Table;
use PhpOffice\PhpWord\Style\TablePosition;

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

// Create new Word document
$phpWord = new PhpWord();

// Set document properties
$properties = $phpWord->getDocInfo();
$properties->setCreator('NIELIT Bhubaneswar');
$properties->setCompany('NIELIT');
$properties->setTitle('Admission Order - ' . $batch['batch_name']);
$properties->setDescription('Admission Order for ' . $batch['course_name']);

// Add section
$section = $phpWord->addSection([
    'marginTop' => 720,    // 0.5 inch
    'marginBottom' => 720,
    'marginLeft' => 720,
    'marginRight' => 720,
]);

// Header with logo and title
$header = $section->addHeader();
$headerTable = $header->addTable(['borderSize' => 0, 'cellMargin' => 50]);
$headerTable->addRow();

// Logo cell
$logoCell = $headerTable->addCell(1500);
$logoPath = __DIR__ . '/../../assets/images/bhubaneswar_logo.png';
if (file_exists($logoPath)) {
    $logoCell->addImage($logoPath, ['width' => 60, 'height' => 60]);
}

// Title cell
$titleCell = $headerTable->addCell(8000);
$titleCell->addText(INSTITUTE_NAME_HI_FORMAL, 
    ['name' => 'Arial', 'size' => 11, 'bold' => true], ['alignment' => 'center']);
$titleCell->addText('National Institute of Electronics and Information Technology (NIELIT)', 
    ['name' => 'Arial', 'size' => 10, 'bold' => true], ['alignment' => 'center']);
$titleCell->addText('Bhubaneswar/Balasore Extension Centre', 
    ['name' => 'Arial', 'size' => 9], ['alignment' => 'center']);
$titleCell->addText('(An Autonomous Scientific Society of Ministry of Electronics and Information Technology (MeitY), Govt. of India)', 
    ['name' => 'Arial', 'size' => 8], ['alignment' => 'center']);

// Reference and Date
$section->addTextBreak();
$refTable = $section->addTable(['borderSize' => 0]);
$refTable->addRow();
$refTable->addCell(5000)->addText('Ref: ' . $batch['admission_order_ref'], ['name' => 'Arial', 'size' => 9, 'bold' => true]);
$refTable->addCell(5000)->addText('Dated: ' . date('d.m.Y', strtotime($order_date)), ['name' => 'Arial', 'size' => 9, 'bold' => true], ['alignment' => 'right']);

// Title
$section->addTextBreak();
$section->addText('ADMISSION ORDER', ['name' => 'Arial', 'size' => 14, 'bold' => true, 'underline' => 'single'], ['alignment' => 'center']);
$section->addTextBreak();

// Admission details
$section->addText('The following eligible students are admitted in the ' . $batch['batch_name'] . ' Batch of "' . $batch['course_name'] . '" which commenced from ' . date('d-m-Y', strtotime($batch['start_date'])) . '.', 
    ['name' => 'Arial', 'size' => 9], ['alignment' => 'both']);

$section->addTextBreak();

// Course details table
$detailsTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80]);
$detailsTable->addRow();
$detailsTable->addCell(2500)->addText('Location:', ['name' => 'Arial', 'size' => 8, 'bold' => true]);
$detailsTable->addCell(2500)->addText($location, ['name' => 'Arial', 'size' => 8]);
$detailsTable->addCell(2500)->addText('Faculty Name:', ['name' => 'Arial', 'size' => 8, 'bold' => true]);
$detailsTable->addCell(2500)->addText($faculty_name, ['name' => 'Arial', 'size' => 8]);

$detailsTable->addRow();
$detailsTable->addCell(2500)->addText('Course Name:', ['name' => 'Arial', 'size' => 8, 'bold' => true]);
$detailsTable->addCell(2500)->addText($batch['course_name'], ['name' => 'Arial', 'size' => 8]);
$detailsTable->addCell(2500)->addText('Start Date:', ['name' => 'Arial', 'size' => 8, 'bold' => true]);
$detailsTable->addCell(2500)->addText(date('d.m.Y', strtotime($batch['start_date'])), ['name' => 'Arial', 'size' => 8]);

$detailsTable->addRow();
$detailsTable->addCell(2500)->addText('Batch ID:', ['name' => 'Arial', 'size' => 8, 'bold' => true]);
$detailsTable->addCell(2500)->addText($batch['batch_name'], ['name' => 'Arial', 'size' => 8]);
$detailsTable->addCell(2500)->addText('End Date:', ['name' => 'Arial', 'size' => 8, 'bold' => true]);
$detailsTable->addCell(2500)->addText(date('d.m.Y', strtotime($batch['end_date'])), ['name' => 'Arial', 'size' => 8]);

$detailsTable->addRow();
$detailsTable->addCell(2500)->addText('Exam Month:', ['name' => 'Arial', 'size' => 8, 'bold' => true]);
$detailsTable->addCell(2500)->addText($batch['examination_month'], ['name' => 'Arial', 'size' => 8]);
$detailsTable->addCell(2500)->addText('Time:', ['name' => 'Arial', 'size' => 8, 'bold' => true]);
$detailsTable->addCell(2500)->addText($class_time, ['name' => 'Arial', 'size' => 8]);

$detailsTable->addRow();
$detailsTable->addCell(2500)->addText('Scheme:', ['name' => 'Arial', 'size' => 8, 'bold' => true]);
$detailsTable->addCell(2500)->addText($batch['scheme_name'] ?? 'General', ['name' => 'Arial', 'size' => 8]);
$detailsTable->addCell(2500)->addText('Duration:', ['name' => 'Arial', 'size' => 8, 'bold' => true]);
$detailsTable->addCell(2500)->addText($batch['duration'], ['name' => 'Arial', 'size' => 8]);

$section->addTextBreak();

// Students table
$studentsTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 40]);

// Header row
$studentsTable->addRow(400);
$studentsTable->addCell(400)->addText('SL', ['name' => 'Arial', 'size' => 7, 'bold' => true], ['alignment' => 'center']);
$studentsTable->addCell(1100)->addText('NIELIT REG', ['name' => 'Arial', 'size' => 7, 'bold' => true], ['alignment' => 'center']);
$studentsTable->addCell(1900)->addText('NAME', ['name' => 'Arial', 'size' => 7, 'bold' => true], ['alignment' => 'center']);
$studentsTable->addCell(1700)->addText('FATHER NAME', ['name' => 'Arial', 'size' => 7, 'bold' => true], ['alignment' => 'center']);
$studentsTable->addCell(1000)->addText('MOBILE', ['name' => 'Arial', 'size' => 7, 'bold' => true], ['alignment' => 'center']);
$studentsTable->addCell(1200)->addText('AADHAAR', ['name' => 'Arial', 'size' => 7, 'bold' => true], ['alignment' => 'center']);
$studentsTable->addCell(500)->addText('GEN', ['name' => 'Arial', 'size' => 7, 'bold' => true], ['alignment' => 'center']);
$studentsTable->addCell(600)->addText('CAT', ['name' => 'Arial', 'size' => 7, 'bold' => true], ['alignment' => 'center']);
$studentsTable->addCell(1600)->addText('REMARK', ['name' => 'Arial', 'size' => 7, 'bold' => true], ['alignment' => 'center']);

// Student rows
$sl_no = 1;
foreach ($students as $student) {
    $studentsTable->addRow();
    $studentsTable->addCell(400)->addText($sl_no++, ['name' => 'Arial', 'size' => 6], ['alignment' => 'center']);
    $studentsTable->addCell(1100)->addText($student['nielit_registration_no'] ?? $student['id'], ['name' => 'Arial', 'size' => 6]);
    $studentsTable->addCell(1900)->addText(strtoupper($student['full_name']), ['name' => 'Arial', 'size' => 6]);
    $studentsTable->addCell(1700)->addText(strtoupper($student['father_name'] ?? ''), ['name' => 'Arial', 'size' => 6]);
    $studentsTable->addCell(1000)->addText($student['mobile'], ['name' => 'Arial', 'size' => 6], ['alignment' => 'center']);
    $studentsTable->addCell(1200)->addText($student['aadhar_number'] ?? 'N/A', ['name' => 'Arial', 'size' => 6], ['alignment' => 'center']);
    $studentsTable->addCell(500)->addText(strtoupper(substr($student['gender'], 0, 1)), ['name' => 'Arial', 'size' => 6], ['alignment' => 'center']);
    $studentsTable->addCell(600)->addText(strtoupper($student['category'] ?? 'GEN'), ['name' => 'Arial', 'size' => 6], ['alignment' => 'center']);
    $studentsTable->addCell(1600)->addText($batch['scheme_code'] ?? '', ['name' => 'Arial', 'size' => 6]);
}

$section->addTextBreak();

// Category summary (simplified for Word)
$total_students = count($students);
$section->addText("Total Students: $total_students", ['name' => 'Arial', 'size' => 9, 'bold' => true]);

$section->addTextBreak();

// Footer note
$section->addText("All documents and eligibility of above listed students ($total_students No's) as per Course norms and Project/scheme norms are checked and Verified by undersigned.", 
    ['name' => 'Arial', 'size' => 8]);

$section->addTextBreak(2);

// Signature section
$signatureTable = $section->addTable(['borderSize' => 0]);
$signatureTable->addRow();
$signatureTable->addCell(5000); // Empty left cell
$signatureCell = $signatureTable->addCell(5000);

$signatureCell->addText('Signature', ['name' => 'Arial', 'size' => 9, 'bold' => true], ['alignment' => 'right']);
$signatureCell->addText(date('d-m-Y'), ['name' => 'Arial', 'size' => 9], ['alignment' => 'right']);
$signatureCell->addText($scheme_incharge, ['name' => 'Arial', 'size' => 9, 'bold' => true], ['alignment' => 'right']);

// Signature title based on scheme
$scheme_code = $batch['scheme_code'] ?? 'SCSP/TSP';
if (strtolower($scheme_code) === 'regular') {
    $signatureCell->addText('Project Incharge,', ['name' => 'Arial', 'size' => 9, 'bold' => true], ['alignment' => 'right']);
} else {
    $signatureCell->addText("($scheme_code) Project Incharge,", ['name' => 'Arial', 'size' => 9, 'bold' => true], ['alignment' => 'right']);
}

$signatureCell->addText('NIELIT Bhubaneswar.', ['name' => 'Arial', 'size' => 9, 'bold' => true], ['alignment' => 'right']);

$section->addTextBreak();

// Copy to section
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

$section->addText('Copy to:', ['name' => 'Arial', 'size' => 8, 'bold' => true]);

$listStyle = ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_NUMBER_NESTED];
foreach ($copy_to_list as $recipient) {
    $section->addListItem($recipient, 0, ['name' => 'Arial', 'size' => 7], $listStyle);
}

// Generate filename
$filename = 'Admission_Order_' . $batch['batch_name'] . '_' . date('Y-m-d') . '.docx';

// Save and download
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save('php://output');

$conn->close();
exit;
?>