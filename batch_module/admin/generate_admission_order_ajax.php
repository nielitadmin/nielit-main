<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/institute_branding.php';

if (!isset($_SESSION['admin']) || !isset($_SESSION['admin_id'])) {
    die('Unauthorized');
}

$batch_id = $_GET['batch_id'];
$scheme_id = $_GET['scheme_id'];

// Fetch batch details with course coordinator
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

// Fetch faculty members assigned to this batch
$faculty_list = [];
$assigned_faculty_ids = [];
$faculty_query = "SELECT f.id, f.name, f.designation 
                  FROM batch_faculty bf 
                  INNER JOIN faculty f ON bf.faculty_id = f.id 
                  WHERE bf.batch_id = ? AND f.is_active = 1 
                  ORDER BY f.name";
$stmt = $conn->prepare($faculty_query);
if ($stmt) {
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $faculty_result = $stmt->get_result();
    
    while ($row = $faculty_result->fetch_assoc()) {
        $faculty_list[] = $row;
        $assigned_faculty_ids[] = $row['id'];
    }
    $stmt->close();
}

// If no faculty assigned to batch, use the course coordinator as fallback
if (empty($faculty_list) && !empty($faculty_name)) {
    $faculty_list[] = ['id' => 0, 'name' => $faculty_name, 'designation' => ''];
}

// Create faculty display string
$faculty_display = '';
if (!empty($faculty_list)) {
    $faculty_names = array_map(function($f) {
        return $f['name'];
    }, $faculty_list);
    $faculty_display = implode(', ', $faculty_names);
} else {
    $faculty_display = $faculty_name;
}

// Fetch all active faculty for dropdown so the admin can choose from the full list
$all_faculty = [];
$admin_id = $_SESSION['admin_id'] ?? 1;
$admin_role = $_SESSION['admin_role'] ?? '';

// Keep query compatible across environments where optional columns may not exist.
$has_email_confirmed_at = false;
$column_check = $conn->query("SHOW COLUMNS FROM faculty LIKE 'email_confirmed_at'");
if ($column_check && $column_check->num_rows > 0) {
    $has_email_confirmed_at = true;
}

$email_confirmed_select = $has_email_confirmed_at
    ? 'f.email_confirmed_at'
    : 'NULL AS email_confirmed_at';

// Show all faculty records so the admission order can reference any active faculty member
$all_faculty_query = "SELECT f.id, f.name, f.designation, f.email, {$email_confirmed_select}, f.created_by, a.username AS creator_username, a.role AS creator_role
                      FROM faculty f
                      LEFT JOIN admin a ON f.created_by = a.id
                      WHERE f.is_active = 1
                      ORDER BY f.name";
$result = $conn->query($all_faculty_query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $all_faculty[] = $row;
    }
}

if (empty($all_faculty) && !empty($faculty_list)) {
    foreach ($faculty_list as $faculty_row) {
        $all_faculty[] = [
            'id' => $faculty_row['id'] ?? 0,
            'name' => $faculty_row['name'] ?? '',
            'designation' => $faculty_row['designation'] ?? '',
            'email' => '',
            'email_confirmed_at' => null,
            'created_by' => 0,
            'creator_username' => null,
            'creator_role' => null
        ];
    }
}

// Use scheme_incharge if set, otherwise use faculty_name
$scheme_incharge = !empty($batch['scheme_incharge']) ? $batch['scheme_incharge'] : $faculty_name;

// Get class time
$class_time = !empty($batch['class_time']) ? $batch['class_time'] : '9:00 AM to 1:30 PM';

// Get location
$location = normalize_nielit_batch_location(!empty($batch['location']) ? $batch['location'] : 'NIELIT Bhubaneswar');

// Determine extension centre name based on location
$extension_centre = nielit_location_centre_label($location);

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

// Fetch students linked to this batch - try multiple methods
$students = [];
require_once __DIR__ . '/../../includes/nielit_registration_helper.php';
$nielitSql = sqlNielitRegistrationNo('s', 'bs');

// Method 1: Try batch_students table first (preferred method)
$check_batch_students = $conn->query("SHOW TABLES LIKE 'batch_students'");
if ($check_batch_students && $check_batch_students->num_rows > 0) {
    $students_query = "SELECT s.id, s.name as full_name, s.father_name, s.mobile, s.aadhar as aadhar_number, 
                       s.gender, s.category, bs.enrollment_date, {$nielitSql}
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

// Method 2: If no students found, try students table with batch_id
if (empty($students)) {
    $nielitSqlSolo = sqlNielitRegistrationNo('s');
    $students_query = "SELECT s.id, s.name as full_name, s.father_name, s.mobile, s.aadhar as aadhar_number, 
                       s.gender, s.category, s.created_at as enrollment_date, {$nielitSqlSolo}
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

// Compact single-page layout targets up to ~35 students on A4
$estimated_pages = ($total_students <= 35) ? 1 : 2;

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
                <option value="<?php echo htmlspecialchars(NIELIT_BALESHWAR_EXTENSION); ?>" <?php echo is_nielit_baleshwar_location($location) ? 'selected' : ''; ?>><?php echo htmlspecialchars(NIELIT_BALESHWAR_EXTENSION); ?></option>
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
            <div style="display: flex; gap: 10px; align-items: flex-start;">
                <div style="flex: 1;">
                    <select id="edit_faculty" class="inline-edit-field" multiple 
                            onchange="updateFacultyField()"
                            style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px; min-height: 80px;">
                        <?php foreach ($all_faculty as $faculty): ?>
                            <?php 
                            $is_selected = in_array($faculty['id'], $assigned_faculty_ids);
                            $is_own_faculty = ($faculty['created_by'] == $admin_id);
                            $is_master_created = (($faculty['creator_role'] ?? '') === 'master_admin');
                            $is_global_faculty = (empty($faculty['created_by']) || $faculty['created_by'] == 0 || $is_master_created);
                            ?>
                            <option value="<?php echo htmlspecialchars($faculty['name']); ?>" 
                                    data-id="<?php echo $faculty['id']; ?>"
                                    data-designation="<?php echo htmlspecialchars($faculty['designation']); ?>"
                                    data-can-delete="<?php echo ($admin_role === 'master_admin') ? 'true' : 'false'; ?>"
                                    <?php echo $is_selected ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($faculty['name']); ?>
                                <?php if (!empty($faculty['designation'])): ?>
                                    (<?php echo htmlspecialchars($faculty['designation']); ?>)
                                <?php endif; ?>
                                <?php if ($is_global_faculty): ?>
                                    [Global]
                                <?php elseif ($is_own_faculty && $admin_role === 'master_admin'): ?>
                                    [My Faculty]
                                <?php endif; ?>
                                <?php if ($admin_role === 'master_admin' && !$is_global_faculty && !empty($faculty['creator_username'])): ?>
                                    [By: <?php echo htmlspecialchars($faculty['creator_username']); ?>]
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Hold Ctrl/Cmd to select multiple faculty members<br>
                        <?php if ($admin_role === 'master_admin'): ?>
                            <strong>[My Faculty]</strong> = Faculty you added | <strong>[Global]</strong> = System/master-admin faculty | <strong>[By: username]</strong> = Added by coordinator
                        <?php else: ?>
                            <strong>[Global]</strong> = System/master-admin faculty
                        <?php endif; ?>
                    </small>
                    <div style="margin-top: 10px;">
                        <a href="<?php echo APP_URL; ?>/admin/manage_faculty.php" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-envelope"></i> Manage Faculty Emails
                        </a>
                    </div>
                </div>
            </div>
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

<!-- Printable Content (A4 — letterhead-friendly: light tables, bottom clearance) -->
<div id="printable-content">
<style>
    #printable-content {
        font-family: Arial, sans-serif;
        width: 100%;
        max-width: 794px;
        margin: 0 auto;
        padding: 2mm 5mm 8mm 5mm;
        box-sizing: border-box;
        font-size: 8.5pt;
        line-height: 1.15;
        overflow: visible;
        background: #ffffff;
        position: relative;
        left: 0;
        transform: none;
    }
    .ao-header-block { width: 100%; max-width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0 0 10px 0; }
    .ao-header-block td { border: none; vertical-align: middle; }
    .ao-header-logo { width: 34mm; min-width: 30mm; max-width: 36mm; padding-right: 8px; }
    .ao-header-logo img {
        height: 13mm;
        width: auto;
        max-width: 34mm;
        max-height: 14mm;
        object-fit: contain;
        display: block;
    }
    .ao-header-text { text-align: center; padding: 2px 0; }
    .ao-header-text .ao-hindi { margin: 0 0 5px 0; font-size: 9.5pt; font-weight: 700; line-height: 1.35; }
    .ao-header-text .ao-institute { margin: 0 0 4px 0; font-size: 9pt; font-weight: 600; line-height: 1.35; }
    .ao-header-text .ao-centre { margin: 0 0 5px 0; font-size: 8.5pt; font-weight: 600; line-height: 1.35; }
    .ao-header-text .ao-tagline { margin: 0; font-size: 6.5pt; font-weight: normal; line-height: 1.3; color: #333; }
    .ao-ref-date { width: 100%; border-collapse: collapse; margin: 6px 0 8px 0; font-size: 8pt; table-layout: fixed; }
    .ao-ref-date td { vertical-align: middle; padding: 4px 0; border: none; }
    .ao-ref-date .ao-ref-cell { width: 62%; text-align: left; padding-right: 8px; word-wrap: break-word; }
    .ao-ref-date .ao-date-cell { width: 38%; text-align: right; white-space: nowrap; padding-left: 8px; }
    .ao-header-rule {
        width: 100%;
        height: 0;
        border: none;
        border-top: 2.5px solid #000;
        margin: 4px 0 10px 0;
        padding: 0;
    }
    .ao-doc-title { text-align: center; margin: 8px 0 8px 0; padding: 2px 0; }
    .ao-doc-title h3 { text-decoration: underline; font-size: 11pt; margin: 0; font-weight: 700; letter-spacing: 0.5px; }
    .ao-details-table { width: 100%; margin: 2px 0; font-size: 7.5pt; border-collapse: collapse; table-layout: fixed; }
    .ao-details-table td { padding: 2px 4px 2px 0; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
    .ao-details-table .ao-label { width: 18%; font-weight: 600; }
    .ao-details-table .ao-value { width: 32%; }
    .ao-light-table { width: 100%; max-width: 100%; border-collapse: collapse; table-layout: fixed; margin: 2px 0; font-family: Arial, sans-serif; }
    .ao-light-table th, .ao-light-table td {
        border: 0.5px solid #999;
        padding: 1px 2px;
        vertical-align: top;
        white-space: normal;
        word-wrap: break-word;
        overflow-wrap: break-word;
        line-height: 1.22;
        font-weight: normal;
        color: #222;
    }
    .ao-light-table th {
        background: #fafafa;
        font-size: 8px;
        font-weight: 600;
        color: #333;
        border-color: #aaa;
    }
    .ao-students-table {
        page-break-inside: auto;
        break-inside: auto;
    }
    .ao-students-table thead {
        /* Prevent orphan header row alone at bottom of page (html2pdf / Chrome print) */
        display: table-row-group;
    }
    .ao-students-table thead tr {
        page-break-inside: avoid;
        break-inside: avoid;
        page-break-after: avoid;
    }
    .ao-students-table tbody tr {
        page-break-inside: avoid;
        break-inside: avoid;
        page-break-after: auto;
    }
    .ao-students-table .ao-sl { width: 4%; text-align: center; font-size: 8px; }
    .ao-students-table .ao-reg { width: 12%; font-size: 8px; word-break: break-word; overflow-wrap: anywhere; line-height: 1.25; }
    .ao-students-table .ao-name { width: 20%; font-size: 8.5px; text-align: left; }
    .ao-students-table .ao-father { width: 18%; font-size: 8.5px; text-align: left; }
    .ao-students-table .ao-mobile { width: 9%; text-align: center; font-size: 8px; }
    .ao-students-table .ao-aadhaar { width: 11%; text-align: center; font-size: 7.5px; word-break: break-word; overflow-wrap: anywhere; }
    .ao-students-table .ao-gen { width: 4%; text-align: center; font-size: 8px; }
    .ao-students-table .ao-cat { width: 6%; text-align: center; font-size: 8px; }
    .ao-students-table .ao-remark { width: 16%; font-size: 7.5px; text-align: left; word-break: break-word; overflow-wrap: anywhere; }
    .ao-keep-together { page-break-inside: avoid; break-inside: avoid; }
    .ao-footer-block { margin-top: 8px; font-size: 7pt; line-height: 1.3; }
    .ao-footer-signature { margin-top: 10px; font-size: 7pt; line-height: 1.3; page-break-inside: avoid; break-inside: avoid; }
    .ao-footer-signature p { margin: 0 0 2px 0; font-weight: normal; }
    .ao-footer-signature .ao-sig-name { font-weight: 600; }
    .ao-sig-block { display: inline-block; text-align: right; }
    .ao-sig-pad {
        height: 12mm;
        min-height: 12mm;
        margin: 0 0 4px 0;
    }
    .ao-copy-list { margin: 2px 0 0 14px; padding: 0; font-size: 6.5pt; }
    .ao-copy-list li { margin: 0 0 1px 0; line-height: 1.25; }
    .ao-footer-section {
        page-break-inside: avoid;
        break-inside: avoid;
        margin-top: 10px;
    }
    @media print {
        #printable-content {
            max-width: 100% !important;
            width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .ao-header-logo img {
            height: 13mm !important;
            width: auto !important;
            max-width: 34mm !important;
            max-height: 14mm !important;
        }
        .ao-light-table,
        .ao-header-block,
        .ao-details-table,
        .ao-ref-date {
            width: 100% !important;
            max-width: 100% !important;
            table-layout: fixed !important;
        }
    }
</style>
    <!-- Header -->
    <table class="ao-header-block">
        <tr>
            <td class="ao-header-logo">
                <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo" width="145" height="60">
            </td>
            <td class="ao-header-text">
                <p class="ao-hindi"><?php echo htmlspecialchars(INSTITUTE_NAME_HI_FORMAL, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="ao-institute">National Institute of Electronics and Information Technology (NIELIT)</p>
                <p class="ao-centre">Bhubaneswar/Baleshwar Extension Centre</p>
                <p class="ao-tagline">(An Autonomous Scientific Society of Ministry of Electronics and Information Technology (MeitY), Govt. of India)</p>
            </td>
        </tr>
    </table>

    <!-- Reference and Date -->
    <table class="ao-ref-date">
        <tr>
            <td class="ao-ref-cell"><strong>Ref: <span id="display_ref"><?php echo htmlspecialchars($batch['admission_order_ref']); ?></span></strong></td>
            <td class="ao-date-cell"><strong>Dated: <span id="display_date"><?php echo date('d.m.Y', strtotime($order_date)); ?></span></strong></td>
        </tr>
    </table>

    <hr class="ao-header-rule" aria-hidden="true">

    <!-- Title -->
    <div class="ao-doc-title">
        <h3>ADMISSION ORDER</h3>
    </div>

    <!-- Admission Details -->
    <div style="margin-bottom: 3px; line-height: 1.15; font-size: 7.5pt;">
        <p style="margin: 1px 0;">The following eligible students are admitted in the <strong><?php echo $batch['batch_name']; ?></strong> Batch of "<strong><?php echo htmlspecialchars($batch['course_name']); ?></strong>" which commenced from <strong><?php echo date('d-m-Y', strtotime($batch['start_date'])); ?></strong>.</p>
        
        <table class="ao-details-table">
            <tr>
                <td class="ao-label">Location:</td>
                <td class="ao-value"><span id="display_location"><?php echo htmlspecialchars($location); ?></span></td>
                <td class="ao-label">Faculty Name:</td>
                <td class="ao-value"><span id="display_faculty"><?php echo htmlspecialchars($faculty_display); ?></span></td>
            </tr>
            <tr>
                <td class="ao-label">Course Name:</td>
                <td class="ao-value"><?php echo htmlspecialchars($batch['course_name']); ?></td>
                <td class="ao-label">Start Date:</td>
                <td class="ao-value" style="white-space: nowrap;"><?php echo date('d.m.Y', strtotime($batch['start_date'])); ?></td>
            </tr>
            <tr>
                <td class="ao-label">Batch ID:</td>
                <td class="ao-value"><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                <td class="ao-label">End Date:</td>
                <td class="ao-value" style="white-space: nowrap;"><?php echo date('d.m.Y', strtotime($batch['end_date'])); ?></td>
            </tr>
            <tr>
                <td class="ao-label">Exam Month:</td>
                <td class="ao-value"><span id="display_exam_month"><?php echo htmlspecialchars($batch['examination_month']); ?></span></td>
                <td class="ao-label">Time:</td>
                <td class="ao-value"><span id="display_time"><?php echo htmlspecialchars($class_time); ?></span></td>
            </tr>
            <tr>
                <td class="ao-label">Scheme:</td>
                <td class="ao-value"><?php echo htmlspecialchars($batch['scheme_name'] ?? 'General'); ?></td>
                <td class="ao-label">Duration:</td>
                <td class="ao-value"><?php echo htmlspecialchars($batch['duration']); ?></td>
            </tr>
        </table>
    </div>

    <!-- Students Table -->
    <table class="ao-light-table ao-students-table">
        <thead>
            <tr>
                <th class="ao-sl">SL</th>
                <th class="ao-reg">NIELIT REG</th>
                <th class="ao-name">NAME</th>
                <th class="ao-father">FATHER NAME</th>
                <th class="ao-mobile">MOBILE</th>
                <th class="ao-aadhaar">AADHAAR</th>
                <th class="ao-gen">GEN</th>
                <th class="ao-cat">CAT</th>
                <th class="ao-remark">REMARK</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sl_no = 1;
            foreach ($students as $student): 
            ?>
            <tr>
                <td class="ao-sl"><?php echo $sl_no++; ?></td>
                <td class="ao-reg"><?php echo htmlspecialchars(trim((string) resolveNielitRegistrationNo($student))); ?></td>
                <td class="ao-name"><?php echo strtoupper(htmlspecialchars($student['full_name'])); ?></td>
                <td class="ao-father"><?php echo strtoupper(htmlspecialchars($student['father_name'] ?? '')); ?></td>
                <td class="ao-mobile"><?php echo htmlspecialchars($student['mobile']); ?></td>
                <td class="ao-aadhaar"><?php echo htmlspecialchars($student['aadhar_number'] ?? 'N/A'); ?></td>
                <td class="ao-gen"><?php echo strtoupper(substr($student['gender'], 0, 1)); ?></td>
                <td class="ao-cat"><?php echo strtoupper($student['category'] ?? 'GEN'); ?></td>
                <td class="ao-remark"><?php echo htmlspecialchars($batch['scheme_code'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Category Summary -->
    <table class="ao-light-table ao-summary-table" style="font-size: 6px; margin-top: 1px;">
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
            <td style="text-align:center;font-size:6px;">M</td>
            <td style="text-align:center;font-size:6px;">F</td>
            <td style="text-align:center;font-size:6px;">M</td>
            <td style="text-align:center;font-size:6px;">F</td>
            <td style="text-align:center;font-size:6px;">M</td>
            <td style="text-align:center;font-size:6px;">F</td>
            <td style="text-align:center;font-size:6px;">M</td>
            <td style="text-align:center;font-size:6px;">F</td>
            <td style="text-align:center;font-size:6px;">M</td>
            <td style="text-align:center;font-size:6px;">F</td>
            <td style="text-align:center;font-size:6px;">M</td>
            <td style="text-align:center;font-size:6px;">F</td>
        </tr>
        <tr>
            <td style="text-align:center;font-size:6px;"><?php echo $category_gender_counts['SC']['M']; ?></td>
            <td style="text-align:center;font-size:6px;"><?php echo $category_gender_counts['SC']['F']; ?></td>
            <td style="text-align:center;font-size:6px;"><?php echo $category_gender_counts['ST']['M']; ?></td>
            <td style="text-align:center;font-size:6px;"><?php echo $category_gender_counts['ST']['F']; ?></td>
            <td style="text-align:center;font-size:6px;"><?php echo $category_gender_counts['OBC']['M']; ?></td>
            <td style="text-align:center;font-size:6px;"><?php echo $category_gender_counts['OBC']['F']; ?></td>
            <td style="text-align:center;font-size:6px;"><?php echo $category_gender_counts['PWD']['M']; ?></td>
            <td style="text-align:center;font-size:6px;"><?php echo $category_gender_counts['PWD']['F']; ?></td>
            <td style="text-align:center;font-size:6px;"><?php echo $category_gender_counts['GEN']['M']; ?></td>
            <td style="text-align:center;font-size:6px;"><?php echo $category_gender_counts['GEN']['F']; ?></td>
            <td style="text-align:center;font-size:6px;"><?php echo $total_male; ?></td>
            <td style="text-align:center;font-size:6px;"><?php echo $total_female; ?></td>
            <td style="text-align:center;font-size:6px;"><?php echo $total_students; ?></td>
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

    <!-- Footer (spaced for letterhead printing) -->
    <div class="ao-footer-section">
    <div class="ao-footer-block">
        <p style="margin: 0;">All documents and eligibility of above listed students (<?php echo $total_students; ?> No's) as per Course norms and Project/scheme norms are checked and Verified by undersigned.</p>
    </div>

    <table class="ao-footer-signature" style="width: 100%; border: none; border-collapse: collapse;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 12px; border: none;">
                <p style="margin: 0 0 4px 0; font-weight: 600;">Copy to:</p>
                <ol id="display_copy_to" class="ao-copy-list">
                    <?php foreach ($copy_to_list as $recipient): ?>
                        <li><?php echo htmlspecialchars($recipient); ?></li>
                    <?php endforeach; ?>
                </ol>
            </td>
            <td style="width: 50%; vertical-align: bottom; text-align: right; border: none; padding-top: 4px;">
                <div class="ao-sig-block">
                    <div class="ao-sig-pad" aria-hidden="true">&nbsp;</div>
                    <p style="margin: 0 0 2px 0;">Signature</p>
                    <p style="margin: 0 0 3px 0;"><span id="display_signature_date"><?php echo date('d-m-Y', strtotime($order_date)); ?></span></p>
                    <p class="ao-sig-name" style="margin: 0 0 2px 0;"><span id="display_incharge"><?php echo htmlspecialchars($scheme_incharge); ?></span></p>
                    <p style="margin: 0 0 2px 0;"><?php 
                        $scheme_code = $batch['scheme_code'] ?? 'SCSP/TSP';
                        if (strtolower($scheme_code) === 'regular') {
                            echo 'Project Incharge,';
                        } else {
                            echo '(' . htmlspecialchars($scheme_code) . ') Project Incharge,';
                        }
                    ?></p>
                    <p style="margin: 0;">NIELIT Bhubaneswar.</p>
                </div>
            </td>
        </tr>
    </table>
    </div>
    <?php if ($estimated_pages > 1): ?>
    <div class="no-print" style="text-align: center; margin-top: 4px; font-size: 6.5pt; color: #666;">
        <p style="margin: 0;">Page 1 of <?php echo $estimated_pages; ?></p>
    </div>
    <?php endif; ?>
</div>

<script>
function updateField(field, value) {
    switch(field) {
        case 'ref':
            document.getElementById('display_ref').textContent = value;
            break;
        case 'date':
            // Convert YYYY-MM-DD to DD.MM.YYYY (header) and DD-MM-YYYY (signature)
            const parts = value.split('-');
            const formatted = parts[2] + '.' + parts[1] + '.' + parts[0];
            const signatureFormatted = parts[2] + '-' + parts[1] + '-' + parts[0];
            document.getElementById('display_date').textContent = formatted;
            const sigDateEl = document.getElementById('display_signature_date');
            if (sigDateEl) {
                sigDateEl.textContent = signatureFormatted;
            }
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

function updateFacultyField() {
    const select = document.getElementById('edit_faculty');
    const selectedOptions = Array.from(select.selectedOptions);
    
    if (selectedOptions.length === 0) {
        document.getElementById('display_faculty').textContent = 'To be assigned';
        return;
    }
    
    const facultyNames = selectedOptions.map(option => option.value);
    
    document.getElementById('display_faculty').textContent = facultyNames.join(', ');
}

function openAddFacultyModal() {
    console.log('openAddFacultyModal called'); // Debug log
    alert('Add Faculty button clicked! Modal will open now.'); // Test alert
    
    // Create modal HTML if it doesn't exist
    if (!document.getElementById('addFacultyModal')) {
        console.log('Creating add faculty modal'); // Debug log
        const modalHTML = `
        <div class="modal fade" id="addFacultyModal" tabindex="-1" aria-labelledby="addFacultyModalLabel" aria-hidden="true"
             style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050;">
            <div class="modal-dialog" style="position: relative; margin: 50px auto; max-width: 500px;">
                <div class="modal-content" style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div class="modal-header" style="padding: 15px 20px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                        <h5 class="modal-title" id="addFacultyModalLabel" style="margin: 0; font-size: 16px; font-weight: 600;">
                            <i class="fas fa-user-plus"></i> Add New Faculty Member
                        </h5>
                        <button type="button" class="btn-close" onclick="closeAddFacultyModal()" aria-label="Close" 
                                style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 20px;">
                        <form id="addFacultyForm">
                            <div class="mb-3" style="margin-bottom: 15px;">
                                <label for="faculty_name" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Name *</label>
                                <input type="text" class="form-control" id="faculty_name" name="name" required 
                                       placeholder="e.g., Dr. John Smith"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div class="mb-3" style="margin-bottom: 15px;">
                                <label for="faculty_email" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Email</label>
                                <input type="email" class="form-control" id="faculty_email" name="email" 
                                       placeholder="e.g., john.smith@nielit.gov.in"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div class="mb-3" style="margin-bottom: 15px;">
                                <label for="faculty_phone" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Phone</label>
                                <input type="text" class="form-control" id="faculty_phone" name="phone" 
                                       placeholder="e.g., 9876543210"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div class="mb-3" style="margin-bottom: 15px;">
                                <label for="faculty_designation" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Designation</label>
                                <input type="text" class="form-control" id="faculty_designation" name="designation" 
                                       placeholder="e.g., Professor, Assistant Professor, Lecturer"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div class="mb-3" style="margin-bottom: 15px;">
                                <label for="faculty_department" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Department</label>
                                <input type="text" class="form-control" id="faculty_department" name="department" 
                                       placeholder="e.g., Computer Science, Information Technology"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeAddFacultyModal()"
                                style="padding: 8px 16px; border: 1px solid #6c757d; background: #6c757d; color: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                        <button type="button" class="btn btn-success" onclick="addNewFaculty()"
                                style="padding: 8px 16px; border: 1px solid #198754; background: #198754; color: white; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-save"></i> Add Faculty
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    // Clear form
    document.getElementById('addFacultyForm').reset();
    
    // Show modal
    const modal = document.getElementById('addFacultyModal');
    modal.style.display = 'block';
    
    // Add click outside to close
    modal.onclick = function(event) {
        if (event.target === modal) {
            closeAddFacultyModal();
        }
    };
}

function openDeleteFacultyModal() {
    console.log('openDeleteFacultyModal called'); // Debug log
    alert('Delete Faculty button clicked! Modal will open now.'); // Test alert
    
    // Get all faculty that can be deleted (owned by current user)
    const facultySelect = document.getElementById('edit_faculty');
    const deletableFaculty = Array.from(facultySelect.options).filter(option => 
        option.getAttribute('data-can-delete') === 'true'
    );
    
    if (deletableFaculty.length === 0) {
        alert('You have no faculty members to delete. You can only delete faculty you have added.');
        return;
    }
    
    // Create modal HTML if it doesn't exist
    if (!document.getElementById('deleteFacultyModal')) {
        const modalHTML = `
        <div class="modal fade" id="deleteFacultyModal" tabindex="-1" aria-labelledby="deleteFacultyModalLabel" aria-hidden="true"
             style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050;">
            <div class="modal-dialog" style="position: relative; margin: 50px auto; max-width: 500px;">
                <div class="modal-content" style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div class="modal-header" style="padding: 15px 20px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                        <h5 class="modal-title" id="deleteFacultyModalLabel" style="margin: 0; font-size: 16px; font-weight: 600; color: #dc3545;">
                            <i class="fas fa-trash"></i> Delete Faculty Member
                        </h5>
                        <button type="button" class="btn-close" onclick="closeDeleteFacultyModal()" aria-label="Close" 
                                style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 20px;">
                        <div class="alert alert-warning" style="padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 15px;">
                            <strong>Warning:</strong> You can only delete faculty members you have added. This action cannot be undone.
                        </div>
                        <div class="mb-3" style="margin-bottom: 15px;">
                            <label for="faculty_to_delete" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Select Faculty to Delete:</label>
                            <select class="form-control" id="faculty_to_delete" name="faculty_to_delete" 
                                    style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="">-- Select Faculty --</option>
                            </select>
                        </div>
                        <div id="delete_faculty_info" style="display: none; padding: 10px; background: #f8f9fa; border-radius: 4px; margin-top: 10px;">
                            <strong>Faculty Details:</strong><br>
                            <span id="delete_faculty_details"></span>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteFacultyModal()"
                                style="padding: 8px 16px; border: 1px solid #6c757d; background: #6c757d; color: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="deleteFaculty()" id="confirmDeleteBtn" disabled
                                style="padding: 8px 16px; border: 1px solid #dc3545; background: #dc3545; color: white; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-trash"></i> Delete Faculty
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    // Populate the delete dropdown
    const deleteSelect = document.getElementById('faculty_to_delete');
    deleteSelect.innerHTML = '<option value="">-- Select Faculty --</option>';
    
    deletableFaculty.forEach(option => {
        const deleteOption = document.createElement('option');
        deleteOption.value = option.getAttribute('data-id');
        deleteOption.textContent = option.textContent;
        deleteOption.setAttribute('data-name', option.value);
        deleteOption.setAttribute('data-designation', option.getAttribute('data-designation'));
        deleteSelect.appendChild(deleteOption);
    });
    
    // Add change event listener
    deleteSelect.onchange = function() {
        const selectedOption = this.options[this.selectedIndex];
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        const infoDiv = document.getElementById('delete_faculty_info');
        const detailsSpan = document.getElementById('delete_faculty_details');
        
        if (this.value) {
            const name = selectedOption.getAttribute('data-name');
            const designation = selectedOption.getAttribute('data-designation');
            detailsSpan.innerHTML = `Name: ${name}<br>Designation: ${designation || 'Not specified'}`;
            infoDiv.style.display = 'block';
            confirmBtn.disabled = false;
        } else {
            infoDiv.style.display = 'none';
            confirmBtn.disabled = true;
        }
    };
    
    // Show modal
    const modal = document.getElementById('deleteFacultyModal');
    modal.style.display = 'block';
    
    // Add click outside to close
    modal.onclick = function(event) {
        if (event.target === modal) {
            closeDeleteFacultyModal();
        }
    };
}

function closeAddFacultyModal() {
    const modal = document.getElementById('addFacultyModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function closeDeleteFacultyModal() {
    const modal = document.getElementById('deleteFacultyModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function showDeleteConfirmToast(facultyName) {
    return new Promise((resolve) => {
        const existingToast = document.getElementById('delete-confirm-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.id = 'delete-confirm-toast';
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.maxWidth = '420px';
        toast.style.background = '#fff';
        toast.style.border = '1px solid #dc3545';
        toast.style.borderRadius = '8px';
        toast.style.boxShadow = '0 6px 20px rgba(0, 0, 0, 0.2)';
        toast.style.padding = '14px';
        toast.style.zIndex = '10000';

        const message = document.createElement('div');
        message.style.marginBottom = '10px';
        message.style.color = '#212529';
        message.style.fontSize = '14px';
        message.textContent = `Delete "${facultyName}"? This action cannot be undone.`;

        const actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.justifyContent = 'flex-end';
        actions.style.gap = '8px';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-sm btn-secondary';
        cancelBtn.textContent = 'Cancel';

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn btn-sm btn-danger';
        deleteBtn.textContent = 'Delete';

        actions.appendChild(cancelBtn);
        actions.appendChild(deleteBtn);
        toast.appendChild(message);
        toast.appendChild(actions);
        document.body.appendChild(toast);

        const cleanup = (confirmed) => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
            resolve(confirmed);
        };

        cancelBtn.addEventListener('click', () => cleanup(false));
        deleteBtn.addEventListener('click', () => cleanup(true));

        setTimeout(() => {
            if (document.body.contains(toast)) {
                cleanup(false);
            }
        }, 12000);
    });
}

async function deleteFaculty() {
    const deleteSelect = document.getElementById('faculty_to_delete');
    const facultyId = deleteSelect.value;
    const facultyName = deleteSelect.options[deleteSelect.selectedIndex].getAttribute('data-name');
    
    if (!facultyId) {
        alert('Please select a faculty member to delete.');
        return;
    }
    
    const confirmed = await showDeleteConfirmToast(facultyName);
    if (!confirmed) {
        return;
    }
    
    // Show loading state
    const btn = document.getElementById('confirmDeleteBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    btn.disabled = true;
    
    // Send AJAX request
    fetch('delete_faculty_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'delete_faculty',
            faculty_id: facultyId
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Remove faculty from dropdown
            const facultySelect = document.getElementById('edit_faculty');
            const optionToRemove = Array.from(facultySelect.options).find(option => 
                option.getAttribute('data-id') === facultyId
            );
            
            if (optionToRemove) {
                facultySelect.removeChild(optionToRemove);
            }
            
            // Update the display
            updateFacultyField();
            
            // Close modal
            closeDeleteFacultyModal();
            
            // Show success message
            showToast('Faculty member deleted successfully!', 'success');
        } else {
            alert('Error deleting faculty: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting faculty. Please try again.');
    })
    .finally(() => {
        // Restore button
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function addNewFaculty() {
    const form = document.getElementById('addFacultyForm');
    const formData = new FormData(form);
    
    // Validate required fields
    const name = formData.get('name').trim();
    if (!name) {
        alert('Faculty name is required!');
        return;
    }
    
    // Show loading state
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    btn.disabled = true;
    
    // Prepare data
    const facultyData = {
        action: 'add_faculty',
        name: name,
        email: formData.get('email').trim(),
        phone: formData.get('phone').trim(),
        designation: formData.get('designation').trim(),
        department: formData.get('department').trim()
    };
    
    // Send AJAX request
    fetch('add_faculty_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(facultyData)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Add new faculty to dropdown
            const facultySelect = document.getElementById('edit_faculty');
            const newOption = document.createElement('option');
            newOption.value = result.faculty.name;
            newOption.setAttribute('data-id', result.faculty.id);
            newOption.setAttribute('data-designation', result.faculty.designation || '');
            newOption.selected = true; // Auto-select the new faculty
            
            const displayText = result.faculty.name + 
                (result.faculty.designation ? ' (' + result.faculty.designation + ')' : '') +
                (<?php echo json_encode($admin_role === 'master_admin'); ?> ? ' [Global]' : ' [My Faculty]');
            newOption.textContent = displayText;
            
            facultySelect.appendChild(newOption);
            
            // Update the display
            updateFacultyField();
            
            // Close modal
            closeAddFacultyModal();
            
            // Show success message
            showToast('Faculty member added successfully!', 'success');
        } else {
            alert('Error adding faculty: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding faculty. Please try again.');
    })
    .finally(() => {
        // Restore button
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function showToast(message, type = 'info') {
    // Simple toast notification - you can enhance this
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'success' ? 'success' : 'info'} alert-dismissible fade show`;
    toast.style.position = 'fixed';
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 5000);
}

// Add event listeners when the page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up event listeners');
    setupFacultyButtons();
});

// Also try to add listeners after a short delay in case DOM isn't ready
setTimeout(function() {
    console.log('Delayed setup of event listeners');
    setupFacultyButtons();
}, 1000);

function setupFacultyButtons() {
    // Add Faculty button
    const addBtn = document.getElementById('addFacultyBtn');
    if (addBtn) {
        console.log('Add Faculty button found');
        if (!addBtn.hasAttribute('data-listener-added')) {
            addBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Add Faculty button clicked via event listener');
                openAddFacultyModal();
            });
            addBtn.setAttribute('data-listener-added', 'true');
            console.log('Add Faculty button listener added');
        }
    } else {
        console.log('Add Faculty button not found');
    }
    
    // Delete Faculty button
    const deleteBtn = document.getElementById('deleteFacultyBtn');
    if (deleteBtn) {
        console.log('Delete Faculty button found');
        if (!deleteBtn.hasAttribute('data-listener-added')) {
            deleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Delete Faculty button clicked via event listener');
                openDeleteFacultyModal();
            });
            deleteBtn.setAttribute('data-listener-added', 'true');
            console.log('Delete Faculty button listener added');
        }
    } else {
        console.log('Delete Faculty button not found');
    }
}
</script>

<?php
$conn->close();
?>
