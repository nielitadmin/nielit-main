<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['admin'])) {
    die('Unauthorized');
}

$batch_id = $_GET['batch_id'];
$scheme_id = $_GET['scheme_id'];

// Fetch batch details with course coordinator
$batch_query = "SELECT b.*, c.course_name, c.course_code, c.duration, c.training_fees, c.course_coordinator,
                s.scheme_name, s.scheme_code
                FROM batches b
                LEFT JOIN courses c ON b.course_id = c.id
                LEFT JOIN schemes s ON b.scheme_id = s.id
                WHERE b.id = ?";
$stmt = $conn->prepare($batch_query);
if (!$stmt) {
    die("Error preparing batch query: " . $conn->error);
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

// If still no students found, show helpful error
if (empty($students)) {
    echo "<div style='padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; margin: 20px;'>";
    echo "<h4 style='color: #856404; margin-top: 0;'><i class='fas fa-exclamation-triangle'></i> No Students Found</h4>";
    echo "<p style='color: #856404;'>No students are enrolled in this batch yet. Students must be:</p>";
    echo "<ul style='color: #856404;'>";
    echo "<li>Added to the batch_students table with batch_id = $batch_id, OR</li>";
    echo "<li>Have their batch_id field set to $batch_id in the students table</li>";
    echo "</ul>";
    echo "<p style='color: #856404;'><strong>Debug:</strong> <a href='debug_batch_students.php?batch_id=$batch_id' target='_blank'>Click here to debug this batch</a></p>";
    echo "</div>";
    die();
}

// Show success message with student count
$student_count = count($students);
echo "<div class='no-print' style='padding: 12px; background: #d1fae5; border: 1px solid #10b981; border-radius: 8px; margin-bottom: 20px;'>";
echo "<p style='color: #065f46; margin: 0;'><i class='fas fa-check-circle'></i> <strong>$student_count students</strong> found and loaded successfully for this admission order.</p>";
echo "</div>";

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

// Calculate estimated page count based on student count
// Approximate: ~20-25 students fit on first page, additional students require second page
$estimated_pages = ($total_students <= 22) ? 1 : 2;

// Count PWD students separately (independent of category)
$pwd_counts = ['M' => 0, 'F' => 0];
foreach ($students as $student) {
    if (isset($student['pwd_status']) && $student['pwd_status'] == 'Yes') {
        $gender = strtoupper(substr(trim($student['gender'] ?? 'M'), 0, 1));
        if ($gender == 'M' || $gender == 'F') {
            $pwd_counts[$gender]++;
        }
    }
}
$total_pwd = $pwd_counts['M'] + $pwd_counts['F'];
?>

<!-- Editable Fields Section (NOT included in print/PDF) -->
<div id="editable-section" class="no-print" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 2px solid #007bff;">
    <h6 style="margin-bottom: 15px; color: #007bff;">
        <i class="fas fa-edit"></i> Edit Order Details (Preview updates as you type - Click "Save Changes & Regenerate" to save to database)
    </h6>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 13px;">
        <div>
            <strong>Ref:</strong>
            <input type="text" id="edit_ref" class="inline-edit-field" 
                   value="<?php echo htmlspecialchars($batch['admission_order_ref']); ?>" 
                   oninput="updateField('ref', this.value)"
                   style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px;">
        </div>
        <div>
            <strong>Dated:</strong>
            <input type="date" id="edit_date" class="inline-edit-field" 
                   value="<?php echo $order_date; ?>" 
                   oninput="updateField('date', this.value)"
                   style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px;">
        </div>
        <div>
            <strong>Location:</strong>
            <select id="edit_location" class="inline-edit-field" 
                    onchange="updateField('location', this.value)"
                    style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px;">
                <option value="NIELIT Bhubaneswar" <?php echo ($location == 'NIELIT Bhubaneswar') ? 'selected' : ''; ?>>NIELIT Bhubaneswar</option>
                <option value="NIELIT Balasore" <?php echo ($location == 'NIELIT Balasore') ? 'selected' : ''; ?>>NIELIT Balasore</option>
            </select>
        </div>
        <div>
            <strong>Examination Month:</strong>
            <input type="text" id="edit_exam_month" class="inline-edit-field" 
                   value="<?php echo htmlspecialchars($batch['examination_month']); ?>" 
                   oninput="updateField('exam_month', this.value)"
                   placeholder="e.g., March 2026"
                   style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px;">
        </div>
        <div>
            <strong>Time:</strong>
            <input type="text" id="edit_time" class="inline-edit-field" 
                   value="<?php echo htmlspecialchars($class_time); ?>" 
                   oninput="updateField('time', this.value)"
                   placeholder="e.g., 9:00 AM to 1:30 PM"
                   style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px;">
        </div>
        <div>
            <strong>Faculty Name:</strong>
            <input type="text" id="edit_faculty" class="inline-edit-field" 
                   value="<?php echo htmlspecialchars($faculty_name); ?>" 
                   oninput="updateField('faculty', this.value)"
                   placeholder="e.g., Kaushik Mohanty"
                   style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px;">
        </div>
        <div>
            <strong>Scheme/Project Incharge:</strong>
            <input type="text" id="edit_incharge" class="inline-edit-field" 
                   value="<?php echo htmlspecialchars($scheme_incharge); ?>" 
                   oninput="updateField('incharge', this.value)"
                   placeholder="e.g., Name of Incharge"
                   style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px;">
        </div>
        <div style="grid-column: span 2;">
            <strong>Copy To (Recipients - one per line):</strong>
            <textarea id="edit_copy_to" class="inline-edit-field" rows="4"
                      oninput="updateField('copy_to', this.value)"
                      placeholder="Enter recipients, one per line"
                      style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px; font-family: Arial, sans-serif;"><?php echo implode("\n", $copy_to_list); ?></textarea>
            <small style="color: #666;">Tip: Each line will become a numbered item in the "Copy to" section</small>
        </div>
    </div>
</div>

<!-- Printable Content (A4 formatted - Professional 2-page layout with minimal margins) -->
<div id="printable-content" style="font-family: Arial, sans-serif; max-width: 190mm; margin: 0 auto; padding: 8mm 5mm 8mm 5mm; box-sizing: border-box; font-size: 9pt; line-height: 1.2;">
    <!-- Header -->
    <table style="width: 100%; margin-bottom: 8px;">
        <tr>
            <td style="width: 70px; vertical-align: middle; padding-right: 8px;">
                <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo" style="height: 55px; width: auto;">
            </td>
            <td style="text-align: center; vertical-align: middle;">
                <h3 style="margin: 2px 0; font-size: 11pt; font-weight: bold;">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान (रा.इ.सू.प्रौ. सं) भुवनेश्वर</h3>
                <h4 style="margin: 2px 0; font-size: 10pt;">National Institute of Electronics and Information Technology (NIELIT)</h4>
                <h4 style="margin: 2px 0; font-size: 9pt;">Bhubaneswar/Balasore Extension Centre</h4>
                <p style="font-size: 7pt; margin: 2px 0;">(An Autonomous Scientific Society of Ministry of Electronics and Information Technology (MeitY), Govt. of India)</p>
            </td>
        </tr>
    </table>

    <!-- Reference and Date -->
    <div style="margin-bottom: 8px; font-size: 9pt;">
        <div style="float: left;">
            <strong>Ref: <span id="display_ref"><?php echo htmlspecialchars($batch['admission_order_ref']); ?></span></strong>
        </div>
        <div style="float: right;">
            <strong>Dated: <span id="display_date"><?php echo date('d.m.Y', strtotime($order_date)); ?></span></strong>
        </div>
        <div style="clear: both;"></div>
    </div>

    <!-- Title -->
    <div style="text-align: center; margin: 8px 0;">
        <h3 style="text-decoration: underline; font-size: 12pt; margin: 0; font-weight: bold;">ADMISSION ORDER</h3>
    </div>

    <!-- Admission Details -->
    <div style="margin-bottom: 8px; line-height: 1.3; font-size: 8pt;">
        <p style="margin: 3px 0;">The following eligible students are admitted in the <strong><?php echo $batch['batch_name']; ?></strong> Batch of "<strong><?php echo htmlspecialchars($batch['course_name']); ?></strong>" which commenced from <strong><?php echo date('d-m-Y', strtotime($batch['start_date'])); ?></strong>.</p>
        
        <table style="width: 100%; margin: 5px 0; font-size: 8pt;">
            <tr>
                <td style="width: 25%; padding: 2px 0; vertical-align: top;"><strong>Location:</strong></td>
                <td style="width: 25%; padding: 2px 0; vertical-align: top;"><span id="display_location"><?php echo htmlspecialchars($location); ?></span></td>
                <td style="width: 25%; padding: 2px 0; vertical-align: top;"><strong>Faculty Name:</strong></td>
                <td style="width: 25%; padding: 2px 0; vertical-align: top;"><span id="display_faculty"><?php echo htmlspecialchars($faculty_name); ?></span></td>
            </tr>
            <tr>
                <td style="padding: 2px 0; vertical-align: top;"><strong>Course Name:</strong></td>
                <td style="padding: 2px 0; vertical-align: top;"><?php echo htmlspecialchars($batch['course_name']); ?> (<?php echo htmlspecialchars($batch['course_code']); ?>)</td>
                <td style="padding: 2px 0; vertical-align: top;"><strong>Start Date:</strong></td>
                <td style="padding: 2px 0; vertical-align: top;"><?php echo date('d.m.Y', strtotime($batch['start_date'])); ?></td>
            </tr>
            <tr>
                <td style="padding: 2px 0; vertical-align: top;"><strong>Batch ID:</strong></td>
                <td style="padding: 2px 0; vertical-align: top;"><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                <td style="padding: 2px 0; vertical-align: top;"><strong>End Date:</strong></td>
                <td style="padding: 2px 0; vertical-align: top;"><?php echo date('d.m.Y', strtotime($batch['end_date'])); ?></td>
            </tr>
            <tr>
                <td style="padding: 2px 0; vertical-align: top;"><strong>Exam Month:</strong></td>
                <td style="padding: 2px 0; vertical-align: top;"><span id="display_exam_month"><?php echo htmlspecialchars($batch['examination_month']); ?></span></td>
                <td style="padding: 2px 0; vertical-align: top;"><strong>Time:</strong></td>
                <td style="padding: 2px 0; vertical-align: top;"><span id="display_time"><?php echo htmlspecialchars($class_time); ?></span></td>
            </tr>
            <tr>
                <td style="padding: 2px 0; vertical-align: top;"><strong>Scheme:</strong></td>
                <td style="padding: 2px 0; vertical-align: top;"><?php echo htmlspecialchars($batch['scheme_name'] ?? 'General'); ?></td>
                <td style="padding: 2px 0; vertical-align: top;"><strong>Duration:</strong></td>
                <td style="padding: 2px 0; vertical-align: top;"><?php echo htmlspecialchars($batch['duration']); ?></td>
            </tr>
        </table>
    </div>

    <!-- Students Table -->
    <table style="width: 100%; border-collapse: collapse; font-size: 7px; margin: 8px 0;">
        <thead>
            <tr style="background: #f0f0f0;">
                <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 4%;">SL</th>
                <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 11%;">NIELIT REG</th>
                <th style="border: 1px solid #000; padding: 3px; width: 19%;">NAME</th>
                <th style="border: 1px solid #000; padding: 3px; width: 17%;">FATHER NAME</th>
                <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 10%;">MOBILE</th>
                <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 12%;">AADHAAR</th>
                <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 5%;">GEN</th>
                <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 6%;">CAT</th>
                <th style="border: 1px solid #000; padding: 3px; text-align: center; width: 16%;">REMARK</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sl_no = 1;
            foreach ($students as $student): 
            ?>
            <tr>
                <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 7px;"><?php echo $sl_no++; ?></td>
                <td style="border: 1px solid #000; padding: 2px; font-size: 6px;"><?php echo htmlspecialchars($student['nielit_registration_no'] ?? $student['id']); ?></td>
                <td style="border: 1px solid #000; padding: 2px; font-size: 6px;"><?php echo strtoupper(htmlspecialchars($student['full_name'])); ?></td>
                <td style="border: 1px solid #000; padding: 2px; font-size: 6px;"><?php echo strtoupper(htmlspecialchars($student['father_name'] ?? '')); ?></td>
                <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo htmlspecialchars($student['mobile']); ?></td>
                <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo htmlspecialchars($student['aadhar_number'] ?? 'N/A'); ?></td>
                <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo strtoupper(substr($student['gender'], 0, 1)); ?></td>
                <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo strtoupper($student['category'] ?? 'GEN'); ?></td>
                <td style="border: 1px solid #000; padding: 2px; font-size: 6px; word-wrap: break-word;"><?php echo htmlspecialchars($batch['scheme_code'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Category Summary -->
    <table style="width: 100%; border-collapse: collapse; font-size: 7px; margin: 8px 0;">
        <tr>
            <th style="border: 1px solid #000; padding: 3px; background: #f0f0f0; font-size: 7px;" colspan="2">SC</th>
            <th style="border: 1px solid #000; padding: 3px; background: #f0f0f0; font-size: 7px;" colspan="2">ST</th>
            <th style="border: 1px solid #000; padding: 3px; background: #f0f0f0; font-size: 7px;" colspan="2">OBC</th>
            <th style="border: 1px solid #000; padding: 3px; background: #f0f0f0; font-size: 7px;" colspan="2">PWD</th>
            <th style="border: 1px solid #000; padding: 3px; background: #f0f0f0; font-size: 7px;" colspan="2">GEN</th>
            <th style="border: 1px solid #000; padding: 3px; background: #f0f0f0; font-size: 7px;" colspan="2">TOTAL</th>
            <th style="border: 1px solid #000; padding: 3px; background: #f0f0f0; font-size: 7px;" rowspan="2">TOTAL</th>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;">M</td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;">F</td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;">M</td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;">F</td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;">M</td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;">F</td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;">M</td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;">F</td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;">M</td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;">F</td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;">M</td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;">F</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $category_gender_counts['SC']['M']; ?></td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $category_gender_counts['SC']['F']; ?></td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $category_gender_counts['ST']['M']; ?></td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $category_gender_counts['ST']['F']; ?></td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $category_gender_counts['OBC']['M']; ?></td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $category_gender_counts['OBC']['F']; ?></td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $category_gender_counts['PWD']['M']; ?></td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $category_gender_counts['PWD']['F']; ?></td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $category_gender_counts['GEN']['M']; ?></td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $category_gender_counts['GEN']['F']; ?></td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $total_male; ?></td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $total_female; ?></td>
            <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 6px;"><?php echo $total_students; ?></td>
        </tr>
    </table>

    <!-- PWD Summary -->
    <?php if ($total_pwd > 0): ?>
    <div style="margin-top: 8px; padding: 6px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 3px solid #3b82f6; border-radius: 4px;">
        <p style="margin: 0; font-weight: 700; color: #1e40af; font-size: 8pt;">
            <i class="fas fa-wheelchair" style="font-size: 9px;"></i> 
            <span>Persons with Disabilities (PWD) Summary:</span>
        </p>
        <div style="margin-top: 4px; display: flex; gap: 12px; font-size: 7pt; color: #1e3a8a;">
            <div><strong>Male:</strong> <?php echo $pwd_counts['M']; ?></div>
            <div><strong>Female:</strong> <?php echo $pwd_counts['F']; ?></div>
            <div><strong>Total PWD:</strong> <?php echo $total_pwd; ?></div>
        </div>
        <p style="margin: 4px 0 0 0; font-size: 6pt; color: #64748b; font-style: italic;">
            Note: PWD students are also counted in their respective categories above
        </p>
    </div>
    <?php endif; ?>

    <!-- Footer Note -->
    <div style="margin-top: 12px; font-size: 8pt;">
        <p style="margin: 0;">All documents and eligibility of above listed students (<?php echo $total_students; ?> No's) as per Course norms and Project/scheme norms are checked and Verified by undersigned.</p>
    </div>

    <!-- Signature -->
    <div style="margin-top: 20px; text-align: right; font-size: 8pt;">
        <p style="margin: 3px 0;"><strong>Signature</strong></p>
        <p style="margin: 3px 0;"><?php echo date('d-m-Y'); ?></p>
        <p style="margin: 3px 0;"><strong><span id="display_incharge"><?php echo htmlspecialchars($scheme_incharge); ?></span></strong></p>
        <p style="margin: 3px 0;"><strong>(<?php echo htmlspecialchars($batch['scheme_code'] ?? 'SCSP/TSP'); ?>) Incharge,</strong></p>
        <p style="margin: 3px 0;"><strong>NIELIT Bhubaneswar.</strong></p>
    </div>

    <!-- Copy To -->
    <div style="margin-top: 15px; font-size: 7pt;">
        <p style="margin: 0 0 4px 0;"><strong>Copy to:</strong></p>
        <ol id="display_copy_to" style="margin: 0 0 0 15px; padding: 0;">
            <?php foreach ($copy_to_list as $recipient): ?>
                <li style="margin: 2px 0;"><?php echo htmlspecialchars($recipient); ?></li>
            <?php endforeach; ?>
        </ol>
    </div>

    <!-- Page Footer -->
    <div style="text-align: center; margin-top: 15px; font-size: 7pt; color: #666;">
        <p style="margin: 0;">Page 1 of <?php echo $estimated_pages; ?></p>
    </div>
</div>

<script>
function updateField(field, value) {
    switch(field) {
        case 'ref':
            document.getElementById('display_ref').textContent = value;
            break;
        case 'date':
            // Convert YYYY-MM-DD to DD.MM.YYYY
            const parts = value.split('-');
            const formatted = parts[2] + '.' + parts[1] + '.' + parts[0];
            document.getElementById('display_date').textContent = formatted;
            break;
        case 'location':
            document.getElementById('display_location').textContent = value;
            break;
        case 'exam_month':
            document.getElementById('display_exam_month').textContent = value;
            break;
        case 'time':
            document.getElementById('display_time').textContent = value;
            break;
        case 'faculty':
            document.getElementById('display_faculty').textContent = value;
            break;
        case 'incharge':
            document.getElementById('display_incharge').textContent = value;
            break;
        case 'copy_to':
            // Split by newlines and filter out empty lines
            const recipients = value.split('\n').filter(line => line.trim() !== '');
            const copyToList = document.getElementById('display_copy_to');
            
            // Clear existing list
            copyToList.innerHTML = '';
            
            // Add each recipient as a list item
            recipients.forEach(recipient => {
                const li = document.createElement('li');
                li.textContent = recipient.trim();
                copyToList.appendChild(li);
            });
            break;
    }
}
</script>

<?php
$conn->close();
?>
