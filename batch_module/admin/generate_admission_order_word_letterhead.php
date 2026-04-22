<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

/**
 * LogoAssetManager - Handles logo image loading and base64 encoding for letterhead documents
 */
class LogoAssetManager {
    private $logoPath;
    
    public function __construct($logoPath = null) {
        $this->logoPath = $logoPath ?: __DIR__ . '/../../assets/images/bhubaneswar_logo.png';
    }
    
    /**
     * Get logo as base64 encoded string for HTML embedding
     * @return string|null Base64 encoded image data or null if file not found
     */
    public function getLogoBase64() {
        if (!file_exists($this->logoPath)) {
            error_log("Logo file not found: " . $this->logoPath);
            return null; // Graceful degradation
        }
        
        if (!is_readable($this->logoPath)) {
            error_log("Logo file not readable: " . $this->logoPath);
            return null;
        }
        
        $imageData = file_get_contents($this->logoPath);
        if ($imageData === false) {
            error_log("Failed to read logo file: " . $this->logoPath);
            return null;
        }
        
        $imageType = pathinfo($this->logoPath, PATHINFO_EXTENSION);
        
        // Validate image type
        if (!in_array(strtolower($imageType), ['png', 'jpg', 'jpeg', 'gif'])) {
            error_log("Unsupported image type: " . $imageType);
            return null;
        }
        
        return 'data:image/' . $imageType . ';base64,' . base64_encode($imageData);
    }
    
    /**
     * Check if logo file exists and is accessible
     * @return bool True if logo is available
     */
    public function isLogoAvailable() {
        return file_exists($this->logoPath) && is_readable($this->logoPath);
    }
    
    /**
     * Get logo file size in bytes
     * @return int|false File size or false if file not accessible
     */
    public function getLogoFileSize() {
        if (!$this->isLogoAvailable()) {
            return false;
        }
        return filesize($this->logoPath);
    }
}

// Validate admin authentication
if (!isset($_SESSION['admin'])) {
    die('Unauthorized');
}

// Get request parameters
$batch_id = $_GET['batch_id'] ?? null;
$scheme_id = $_GET['scheme_id'] ?? null;

// Validate required parameters
if (!$batch_id) {
    die('Batch ID is required');
}

// Validate batch_id is numeric
if (!is_numeric($batch_id)) {
    die('Invalid batch ID format');
}

// Database connection validation
if (!$conn) {
    die('Database connection failed');
}

// Fetch batch details with course and scheme information
// Try with schemes table first
$batch_query = "SELECT b.*, c.course_name, c.course_code, c.duration, c.training_fees, c.course_coordinator,
                s.scheme_name, s.scheme_code
                FROM batches b
                LEFT JOIN courses c ON b.course_id = c.id
                LEFT JOIN schemes s ON b.scheme_id = s.id
                WHERE b.id = ?";
$stmt = $conn->prepare($batch_query);

// If schemes table doesn't exist, try without it
if (!$stmt) {
    $batch_query = "SELECT b.*, c.course_name, c.course_code, c.duration, c.training_fees, c.course_coordinator,
                    NULL as scheme_name, NULL as scheme_code
                    FROM batches b
                    LEFT JOIN courses c ON b.course_id = c.id
                    WHERE b.id = ?";
    $stmt = $conn->prepare($batch_query);
    
    if (!$stmt) {
        die("Error preparing batch query: " . $conn->error);
    }
}

$stmt->bind_param("i", $batch_id);
$stmt->execute();
$batch = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$batch) {
    die("Batch not found");
}

// Auto-generate reference number if not set
if (empty($batch['admission_order_ref'])) {
    $batch['admission_order_ref'] = "NIELIT/BBSR/Admission Order/FY-" . date('y') . "-" . (date('y')+1) . "/" . $batch_id;
}

// Use custom date or today's date
$order_date = !empty($batch['admission_order_date']) ? $batch['admission_order_date'] : date('Y-m-d');

// Auto-calculate examination month if not set
if (empty($batch['examination_month'])) {
    $batch['examination_month'] = date('F Y', strtotime($batch['end_date']));
}

// Use course coordinator as faculty name, fallback to batch coordinator
$faculty_name = !empty($batch['course_coordinator']) ? $batch['course_coordinator'] : 
                (!empty($batch['batch_coordinator']) ? $batch['batch_coordinator'] : 'To be assigned');

// Use scheme_incharge if set, otherwise use faculty_name
$scheme_incharge = !empty($batch['scheme_incharge']) ? $batch['scheme_incharge'] : $faculty_name;

// Get class time
$class_time = !empty($batch['class_time']) ? $batch['class_time'] : '9:00 AM to 1:30 PM';

// Get location
$location = !empty($batch['location']) ? $batch['location'] : 'NIELIT Bhubaneswar';

// Determine extension centre name based on location
$extension_centre = ($location == 'NIELIT Balasore') ? 'Balasore' : 'Bhubaneswar';

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

// Debug output for now - will be replaced with actual document generation
echo "<!-- Batch Data Retrieved Successfully -->";
echo "<!-- Batch: " . htmlspecialchars($batch['batch_name']) . " -->";
echo "<!-- Course: " . htmlspecialchars($batch['course_name']) . " -->";
echo "<!-- Reference: " . htmlspecialchars($batch['admission_order_ref']) . " -->";
echo "<!-- Extension Centre: " . htmlspecialchars($extension_centre) . " -->";

// Fetch students linked to this batch - try multiple methods
$students = [];

// Method 1: Try batch_students table first (preferred method)
$check_batch_students = $conn->query("SHOW TABLES LIKE 'batch_students'");
if ($check_batch_students && $check_batch_students->num_rows > 0) {
    // Check if nielit_registration_no column exists
    $check_column = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'nielit_registration_no'");
    $has_nielit_column = ($check_column && $check_column->num_rows > 0);
    
    if ($has_nielit_column) {
        $students_query = "SELECT s.id, s.name as full_name, s.father_name, s.mobile, s.aadhar as aadhar_number, 
                           s.gender, s.category, bs.enrollment_date, bs.nielit_registration_no
                           FROM batch_students bs
                           INNER JOIN students s ON bs.student_id = s.id
                           WHERE bs.batch_id = ?
                           ORDER BY s.name";
    } else {
        $students_query = "SELECT s.id, s.name as full_name, s.father_name, s.mobile, s.aadhar as aadhar_number, 
                           s.gender, s.category, bs.enrollment_date, s.nielit_registration_no
                           FROM batch_students bs
                           INNER JOIN students s ON bs.student_id = s.id
                           WHERE bs.batch_id = ?
                           ORDER BY s.name";
    }
    
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

// Method 2: If no students found, try students table with batch_id
if (empty($students)) {
    // Check if nielit_registration_no column exists in students table
    $check_column = $conn->query("SHOW COLUMNS FROM students LIKE 'nielit_registration_no'");
    $has_nielit_column = ($check_column && $check_column->num_rows > 0);
    
    if ($has_nielit_column) {
        $students_query = "SELECT s.id, s.name as full_name, s.father_name, s.mobile, s.aadhar as aadhar_number, 
                           s.gender, s.category, s.created_at as enrollment_date, s.nielit_registration_no
                           FROM students s
                           WHERE s.batch_id = ?
                           ORDER BY s.name";
    } else {
        $students_query = "SELECT s.id, s.name as full_name, s.father_name, s.mobile, s.aadhar as aadhar_number, 
                           s.gender, s.category, s.created_at as enrollment_date, NULL as nielit_registration_no
                           FROM students s
                           WHERE s.batch_id = ?
                           ORDER BY s.name";
    }
    
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

// If still no students found, show error
if (empty($students)) {
    die("No students found for this batch. Students must be enrolled in this batch to generate admission order.");
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
    
    // Normalize category names - handle variations
    if ($category == 'GENERAL' || empty($category)) {
        $category = 'GEN';
    }
    
    // Map category to our standard categories
    if (!isset($category_gender_counts[$category])) {
        // If category not found, default to GEN
        $category = 'GEN';
    }
    
    if (($gender == 'M' || $gender == 'F')) {
        $category_gender_counts[$category][$gender]++;
    }
}

// Calculate totals
$total_male = 0;
$total_female = 0;
foreach ($category_gender_counts as $counts) {
    $total_male += $counts['M'];
    $total_female += $counts['F'];
}

$total_students = count($students);

echo "<!-- Students Retrieved: " . $total_students . " -->";
echo "<!-- Total Male: " . $total_male . ", Total Female: " . $total_female . " -->";

// Initialize logo manager
$logoManager = new LogoAssetManager();
$logoBase64 = $logoManager->getLogoBase64();

echo "<!-- Logo Available: " . ($logoBase64 ? 'Yes' : 'No') . " -->";
if ($logoManager->isLogoAvailable()) {
    echo "<!-- Logo File Size: " . $logoManager->getLogoFileSize() . " bytes -->";
}

// Generate filename for download
$filename = 'Admission_Order_Letterhead_' . $batch['batch_name'] . '_' . date('Y-m-d') . '.doc';

// Set headers for Word document download
header('Content-Type: application/msword');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Generate letterhead HTML document
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admission Order - Letterhead</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 12pt; 
            margin: 15mm 10mm 15mm 10mm; 
            line-height: 1.2;
        }
        
        /* Letterhead Header Styles */
        .letterhead-header { 
            display: table; 
            width: 100%; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        .logo-section { 
            display: table-cell; 
            width: 80px; 
            vertical-align: middle; 
            padding-right: 15px;
        }
        
        .logo-section img { 
            height: 70px; 
            width: auto; 
            max-width: 80px;
        }
        
        .header-text-section { 
            display: table-cell; 
            vertical-align: middle; 
            text-align: center;
        }
        
        .hindi-institutional-name { 
            font-size: 16pt; 
            font-weight: bold; 
            margin: 2px 0;
            color: #000;
        }
        
        .english-institutional-name { 
            font-size: 14pt; 
            font-weight: bold; 
            margin: 2px 0;
            color: #000;
        }
        
        .extension-centre { 
            font-size: 12pt; 
            font-weight: bold; 
            margin: 2px 0;
            color: #000;
        }
        
        .government-affiliation { 
            font-size: 9pt; 
            margin: 2px 0;
            color: #333;
            font-style: italic;
        }
        
        /* Reference and Date Section */
        .ref-date-section { 
            margin: 15px 0; 
            font-size: 10pt;
        }
        
        .ref-left { 
            float: left; 
            font-weight: bold; 
        }
        
        .date-right { 
            float: right; 
            font-weight: bold; 
        }
        
        .clear { 
            clear: both; 
        }
        
        /* Document Title */
        .document-title { 
            text-align: center; 
            font-size: 16pt; 
            font-weight: bold; 
            text-decoration: underline; 
            margin: 20px 0; 
        }
        
        /* Content Sections */
        .admission-details { 
            margin-bottom: 15px; 
            line-height: 1.4; 
            font-size: 10pt;
        }
        
        .details-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 10px 0; 
            font-size: 9pt;
        }
        
        .details-table td { 
            padding: 3px 5px; 
            vertical-align: top; 
        }
        
        .details-table td:first-child { 
            font-weight: bold; 
            width: 20%;
        }
        
        /* Students Table */
        .students-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0; 
            font-size: 8pt;
        }
        
        .students-table th, .students-table td { 
            border: 1px solid #000; 
            padding: 3px; 
            text-align: center;
        }
        
        .students-table th { 
            background-color: #f0f0f0; 
            font-weight: bold; 
            font-size: 7pt;
        }
        
        .students-table td.text-left { 
            text-align: left; 
        }
        
        /* Category Summary Table */
        .category-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0; 
            font-size: 8pt;
        }
        
        .category-table th, .category-table td { 
            border: 1px solid #000; 
            padding: 2px; 
            text-align: center;
        }
        
        .category-table th { 
            background-color: #f0f0f0; 
            font-weight: bold;
        }
        
        /* Signature Section */
        .signature-section { 
            text-align: right; 
            margin-top: 30px; 
            font-size: 10pt;
        }
        
        .signature-section p { 
            margin: 3px 0; 
        }
        
        /* Copy To Section */
        .copy-to-section { 
            margin-top: 20px; 
            font-size: 9pt;
        }
        
        .copy-to-section ol { 
            margin: 5px 0 0 20px; 
            padding: 0;
        }
        
        .copy-to-section li { 
            margin: 3px 0; 
        }
    </style>
</head>
<body>

<!-- Letterhead Header -->
<div class="letterhead-header">
    <div class="logo-section">
        <?php if ($logoBase64): ?>
            <img src="<?php echo $logoBase64; ?>" alt="NIELIT Logo">
        <?php endif; ?>
    </div>
    <div class="header-text-section">
        <div class="hindi-institutional-name">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान (रा.इ.सू.प्रौ. सं) भुवनेश्वर</div>
        <div class="english-institutional-name">National Institute of Electronics and Information Technology (NIELIT)</div>
        <div class="extension-centre"><?php echo htmlspecialchars($extension_centre); ?> Extension Centre</div>
        <div class="government-affiliation">(An Autonomous Scientific Society of Ministry of Electronics and Information Technology (MeitY), Govt. of India)</div>
    </div>
</div>

<!-- Reference and Date -->
<div class="ref-date-section">
    <div class="ref-left">Ref: <?php echo htmlspecialchars($batch['admission_order_ref']); ?></div>
    <div class="date-right">Dated: <?php echo date('d.m.Y', strtotime($order_date)); ?></div>
    <div class="clear"></div>
</div>

<!-- Document Title -->
<div class="document-title">ADMISSION ORDER</div>

<!-- Admission Details -->
<div class="admission-details">
    <p>The following eligible students are admitted in the <strong><?php echo htmlspecialchars($batch['batch_name']); ?></strong> Batch of "<strong><?php echo htmlspecialchars($batch['course_name']); ?></strong>" which commenced from <strong><?php echo date('d-m-Y', strtotime($batch['start_date'])); ?></strong>.</p>
</div>

<!-- Course Details Table -->
<table class="details-table">
    <tr>
        <td>Location:</td>
        <td><?php echo htmlspecialchars($location); ?></td>
        <td>Faculty Name:</td>
        <td><?php echo htmlspecialchars($faculty_name); ?></td>
    </tr>
    <tr>
        <td>Course Name:</td>
        <td><?php echo htmlspecialchars($batch['course_name']); ?></td>
        <td>Start Date:</td>
        <td><?php echo date('d.m.Y', strtotime($batch['start_date'])); ?></td>
    </tr>
    <tr>
        <td>Batch ID:</td>
        <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
        <td>End Date:</td>
        <td><?php echo date('d.m.Y', strtotime($batch['end_date'])); ?></td>
    </tr>
    <tr>
        <td>Exam Month:</td>
        <td><?php echo htmlspecialchars($batch['examination_month']); ?></td>
        <td>Time:</td>
        <td><?php echo htmlspecialchars($class_time); ?></td>
    </tr>
    <tr>
        <td>Scheme:</td>
        <td><?php echo htmlspecialchars($batch['scheme_name'] ?? 'General'); ?></td>
        <td>Duration:</td>
        <td><?php echo htmlspecialchars($batch['duration']); ?></td>
    </tr>
</table>

<!-- Students Table -->
<table class="students-table">
    <thead>
        <tr>
            <th style="width: 5%;">SL</th>
            <th style="width: 12%;">NIELIT REG</th>
            <th style="width: 20%;">NAME</th>
            <th style="width: 18%;">FATHER NAME</th>
            <th style="width: 12%;">MOBILE</th>
            <th style="width: 13%;">AADHAAR</th>
            <th style="width: 6%;">GEN</th>
            <th style="width: 8%;">CAT</th>
            <th style="width: 6%;">REMARK</th>
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
            <td class="text-left"><?php echo strtoupper(htmlspecialchars($student['full_name'])); ?></td>
            <td class="text-left"><?php echo strtoupper(htmlspecialchars($student['father_name'] ?? '')); ?></td>
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
<p style="margin-top: 15px; font-size: 10pt;">All documents and eligibility of above listed students (<?php echo $total_students; ?> No's) as per Course norms and Project/scheme norms are checked and Verified by undersigned.</p>

<!-- Signature Section -->
<div class="signature-section">
    <p><strong>Signature</strong></p>
    <p><?php echo date('d-m-Y'); ?></p>
    <p><strong><?php echo htmlspecialchars($scheme_incharge); ?></strong></p>
    <p><strong>
    <?php 
    $scheme_code = $batch['scheme_code'] ?? 'SCSP/TSP';
    if (strtolower($scheme_code) === 'regular') {
        echo 'Project Incharge,';
    } else {
        echo '(' . htmlspecialchars($scheme_code) . ') Incharge,';
    }
    ?>
    </strong></p>
    <p><strong>NIELIT <?php echo htmlspecialchars($extension_centre); ?>.</strong></p>
</div>

<!-- Copy To Section -->
<div class="copy-to-section">
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
?>