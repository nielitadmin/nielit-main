<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/theme_loader.php';
require_once __DIR__ . '/../../includes/url_helper.php';
require_once __DIR__ . '/../../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../../includes/admin_assets.php';
require_once __DIR__ . '/../../includes/session_manager.php';
require_once __DIR__ . '/../includes/batch_functions.php';
require_once __DIR__ . '/../includes/batch_certificate_helper.php';
require_once __DIR__ . '/../includes/batch_placement_helper.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../../admin/login.php");
    exit();
}

if (!isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id'])) {
    if (!init_admin_session($_SESSION['admin'])) {
        session_unset();
        session_destroy();
        header("Location: ../../admin/login.php");
        exit();
    }
}

refresh_session_permissions();

$admin_role = $_SESSION['admin_role'] ?? '';
$is_placement_coordinator = ($admin_role === 'placement_coordinator');

if ($is_placement_coordinator && !canViewBatchPlacements($admin_role)) {
    header('Location: manage_batches.php');
    exit();
}

// Check user role for lock bypass
$is_master_admin = ($admin_role === 'master_admin');
$current_admin_id = $_SESSION['admin_id'] ?? null;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_batches.php");
    exit();
}

$batch_id = $_GET['id'];
$batch = getBatchById($batch_id, $conn);

if (!$batch) {
    header("Location: manage_batches.php");
    exit();
}

repairBatchStudentsJunction($conn, (int)$batch_id);
ensureBatchCertificateSchema($conn);
ensureBatchPlacementSchema($conn);

// Check if batch is locked
$is_locked = isBatchLocked($batch_id, $conn);
$lock_info = getBatchLockInfo($batch_id, $conn);

// Handle remove student first — redirect after action (PRG)
if (isset($_GET['remove_student']) && !$is_locked) {
    $remove_record_id = (int)$_GET['remove_student'];
    $result = removeStudentFromBatch($remove_record_id, $batch_id, $conn);
    $_SESSION['batch_details_message'] = $result['message'];
    $_SESSION['batch_details_message_type'] = $result['success'] ? 'success' : 'danger';
    header('Location: batch_details.php?id=' . (int)$batch_id);
    exit();
} elseif (isset($_GET['remove_student']) && $is_locked) {
    $_SESSION['batch_details_message'] = 'Cannot remove student: Batch is locked and cannot be modified.';
    $_SESSION['batch_details_message_type'] = 'danger';
    header('Location: batch_details.php?id=' . (int)$batch_id);
    exit();
}

$students = getBatchStudents($batch_id, $conn);
$eligible_students = getEligibleStudentsForBatch($batch_id, $conn);
$move_target_batches = getMoveTargetBatches($batch_id, $conn);
$stats = getBatchStats($batch_id, $conn);
$can_upload_certificates = isBatchCertificateUploadAllowed($batch);
$certificate_upload_hint = batch_certificate_upload_reason($batch);
$can_manage_placement = canManageBatchPlacement($admin_role);
$can_view_placement = canViewBatchPlacements($admin_role);
$placement_stats = getBatchPlacementStats($conn, (int) $batch_id);
$placement_status_options = batch_placement_status_options();
$placement_package_types = batch_placement_package_type_options();

$message = $_SESSION['batch_details_message'] ?? '';
$message_type = $_SESSION['batch_details_message_type'] ?? 'success';
unset($_SESSION['batch_details_message'], $_SESSION['batch_details_message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_students_to_batch']) && !$is_locked) {
    $record_ids = $_POST['student_record_ids'] ?? [];
    if (empty($record_ids)) {
        $message = 'Please select at least one student to add.';
        $message_type = 'warning';
    } else {
    $result = addStudentsToBatch($record_ids, $batch_id, $_SESSION['admin'] ?? 'Admin', $conn);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'danger';
    $students = getBatchStudents($batch_id, $conn);
    $eligible_students = getEligibleStudentsForBatch($batch_id, $conn);
    $stats = getBatchStats($batch_id, $conn);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_students_to_batch']) && $is_locked) {
    $message = 'Cannot add students: Batch is locked.';
    $message_type = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_students_to_batch']) && !$is_locked) {
    $target_batch_id = (int)($_POST['target_batch_id'] ?? 0);
    $move_record_ids = $_POST['move_student_record_ids'] ?? [];
    if ($target_batch_id <= 0 || empty($move_record_ids)) {
        $message = 'Please select student(s) and a destination batch.';
        $message_type = 'warning';
    } else {
        $result = moveStudentsToBatch($move_record_ids, $batch_id, $target_batch_id, $_SESSION['admin'] ?? 'Admin', $conn);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'danger';
        $students = getBatchStudents($batch_id, $conn);
        $eligible_students = getEligibleStudentsForBatch($batch_id, $conn);
        $move_target_batches = getMoveTargetBatches($batch_id, $conn);
        $stats = getBatchStats($batch_id, $conn);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_students_to_batch']) && $is_locked) {
    $message = 'Cannot move students: Batch is locked.';
    $message_type = 'danger';
}

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Function to determine qualification level priority (defined once outside the loop)
    function getQualificationLevel($exam_passed) {
        $exam_passed = strtolower(trim($exam_passed));
        
        // Define qualification hierarchy (higher number = higher qualification)
        $levels = [
            'phd' => 8, 'ph.d' => 8, 'doctorate' => 8,
            'post graduation' => 7, 'pg' => 7, 'master' => 7, 'm.tech' => 7, 'mtech' => 7, 'm.sc' => 7, 'msc' => 7, 'ma' => 7, 'mba' => 7,
            'graduation' => 6, 'graduate' => 6, 'degree' => 6, 'b.tech' => 6, 'btech' => 6, 'b.sc' => 6, 'bsc' => 6, 'ba' => 6, 'bcom' => 6, 'b.com' => 6,
            'diploma' => 5, 'polytechnic' => 5,
            'iti' => 4, 'industrial training' => 4,
            '+2' => 3, '12th' => 3, '12' => 3, 'higher secondary' => 3, 'intermediate' => 3, 'hse' => 3,
            '10th' => 2, '10' => 2, 'secondary' => 2, 'matriculation' => 2, 'ssc' => 2,
            '8th' => 1, '5th' => 1, 'primary' => 1
        ];
        
        // Check for exact matches first
        if (isset($levels[$exam_passed])) {
            return $levels[$exam_passed];
        }
        
        // Check for partial matches
        foreach ($levels as $key => $level) {
            if (strpos($exam_passed, $key) !== false) {
                return $level;
            }
        }
        
        return 0; // Unknown qualification
    }
    
    // Set headers for Excel download
    $filename = 'batch_' . $batch['batch_code'] . '_students_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Create file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add CSV headers - Essential student data with education details and highest qualification
    $headers = [
        'ID',
        'Course',
        'Batch ID',
        'Batch Name',
        'Name',
        'Father Name',
        'Mother Name',
        'Date of Birth',
        'Age',
        'Mobile',
        'Aadhar Number',
        'APAAR ID',
        'Gender',
        'Religion',
        'Marital Status',
        'Category',
        'PWD Status',
        'Distinguishing Marks',
        'Position',
        'Nationality',
        'Email',
        'State',
        'City',
        'Pincode',
        'Address',
        'Created At',
        'Course ID',
        'Student ID',
        'NIELIT Registration No.',
        'Registration Date',
        'Status',
        'Approved By',
        'Approved At',
        'College Name',
        'UTR Number',
        'Payment Receipt',
        'Training Center',
        'Enrollment Date',
        'Fees Status',
        'Fees Paid',
        'Attendance Percentage',
        // Education Details from education_details table
        'Highest Qualification',
        'Exam Passed',
        'Exam Name',
        'Year of Passing',
        'Institute Name',
        'Stream',
        'Percentage'
    ];
    
    fputcsv($output, $headers);
    
    // Add student data - Essential fields with batch name, education details and highest qualification
    foreach ($students as $student) {
        // Get education details for this student
        $education_details = [];
        $ed_sql = "SELECT exam_passed, exam_name, year_of_passing, institute_name, stream, percentage 
                   FROM education_details 
                   WHERE student_id = ? 
                   ORDER BY id ASC";
        $ed_stmt = $conn->prepare($ed_sql);
        if ($ed_stmt) {
            $ed_stmt->bind_param("s", $student['student_id']);
            $ed_stmt->execute();
            $ed_result = $ed_stmt->get_result();
            while ($ed_row = $ed_result->fetch_assoc()) {
                $education_details[] = $ed_row;
            }
            $ed_stmt->close();
        }
        
        // Determine highest qualification
        $highest_qualification = '';
        $highest_level = 0;
        $highest_qualification_details = null;
        
        foreach ($education_details as $ed) {
            $level = getQualificationLevel($ed['exam_passed'] ?? '');
            if ($level > $highest_level) {
                $highest_level = $level;
                $highest_qualification = $ed['exam_passed'] ?? '';
                $highest_qualification_details = $ed;
            }
        }
        
        // Use first education record for detailed fields, or highest qualification if available
        $display_education = $highest_qualification_details ?? (!empty($education_details) ? $education_details[0] : []);
        
        $row = [
            $student['id'] ?? '',
            $student['course'] ?? '',
            $student['batch_id'] ?? '',
            $batch['batch_name'] ?? '', // Batch name from the batch data
            $student['name'] ?? '',
            $student['father_name'] ?? '',
            $student['mother_name'] ?? '',
            $student['dob'] ?? '',
            $student['age'] ?? '',
            $student['mobile'] ?? '',
            $student['aadhar'] ?? '',
            $student['apaar_id'] ?? '',
            $student['gender'] ?? '',
            $student['religion'] ?? '',
            $student['marital_status'] ?? '',
            $student['category'] ?? '',
            $student['pwd_status'] ?? '',
            $student['distinguishing_marks'] ?? '',
            $student['position'] ?? '',
            $student['nationality'] ?? '',
            $student['email'] ?? '',
            $student['state'] ?? '',
            $student['city'] ?? '',
            $student['pincode'] ?? '',
            $student['address'] ?? '',
            isset($student['created_at']) ? date('d-m-Y H:i:s', strtotime($student['created_at'])) : '',
            $student['course_id'] ?? '',
            $student['student_id'] ?? '',
            $student['nielit_registration_no'] ?? '',
            isset($student['registration_date']) ? date('d-m-Y', strtotime($student['registration_date'])) : '',
            $student['status'] ?? '',
            $student['approved_by'] ?? '',
            isset($student['approved_at']) ? date('d-m-Y H:i:s', strtotime($student['approved_at'])) : '',
            $student['college_name'] ?? '',
            $student['utr_number'] ?? '',
            $student['payment_receipt'] ?? '',
            $student['training_center'] ?? '',
            isset($student['enrollment_date']) ? date('d-m-Y', strtotime($student['enrollment_date'])) : '',
            $student['fees_status'] ?? '',
            $student['fees_paid'] ?? '0',
            $student['attendance_percentage'] ?? '0',
            // Education details from education_details table
            $highest_qualification, // Highest qualification determined from all records
            $display_education['exam_passed'] ?? '',
            $display_education['exam_name'] ?? '',
            $display_education['year_of_passing'] ?? '',
            $display_education['institute_name'] ?? '',
            $display_education['stream'] ?? '',
            $display_education['percentage'] ?? ''
        ];
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}
$active_theme = loadActiveTheme($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Details - <?php echo htmlspecialchars($batch['batch_name']); ?></title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color, #0a1628) 0%, #112240 100%);
            color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: none;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
        }
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, var(--primary-color, #0c2340) 0%, var(--secondary-color, #123a66) 100%);
        }
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%);
        }
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        }
        .stat-card:nth-child(5) {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        }
        .stat-card .stat-value,
        .stat-card .stat-label {
            color: #ffffff !important;
            position: relative;
            z-index: 1;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 8px;
            line-height: 1.1;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
        }
        .stat-label {
            font-size: 14px;
            font-weight: 600;
            opacity: 0.95;
        }
        .batch-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .info-box {
            background: var(--bg-card, #f8fafc);
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color, #3b82f6);
        }
        .info-box h6 {
            color: var(--text-secondary, #64748b);
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .info-box p {
            color: var(--text-primary, #1e293b);
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }

        .nielit-reg-input {
            min-width: 160px;
            background: #ffffff !important;
            color: #0f172a !important;
            border: 1px solid #94a3b8 !important;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .nielit-reg-input::placeholder {
            color: #475569 !important;
            opacity: 1 !important;
            font-weight: 500;
        }

        .nielit-reg-input:focus {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18) !important;
            outline: none;
        }

        .nielit-reg-input:disabled,
        .nielit-reg-input[disabled] {
            background: #f1f5f9 !important;
            color: #0f172a !important;
            border-color: #64748b !important;
            opacity: 1 !important;
            -webkit-text-fill-color: #0f172a;
            cursor: not-allowed;
        }

        .nielit-reg-input:disabled::placeholder,
        .nielit-reg-input[disabled]::placeholder {
            color: #334155 !important;
            opacity: 1 !important;
        }

        html[data-mode="night"] .nielit-reg-input {
            background: #13243c !important;
            color: #f8fafc !important;
            border-color: #64748b !important;
            -webkit-text-fill-color: #f8fafc;
        }

        html[data-mode="night"] .nielit-reg-input::placeholder {
            color: #cbd5e1 !important;
        }

        html[data-mode="night"] .nielit-reg-input:disabled,
        html[data-mode="night"] .nielit-reg-input[disabled] {
            background: #1e293b !important;
            color: #e2e8f0 !important;
            border-color: #94a3b8 !important;
            -webkit-text-fill-color: #e2e8f0;
        }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">

<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
function confirmRemoveStudent(studentId, studentName, batchId) {
    // Create modern confirmation dialog
    const dialog = document.createElement('div');
    dialog.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        animation: fadeIn 0.2s ease;
    `;
    
    dialog.innerHTML = `
        <div style="
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        ">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="
                    width: 60px;
                    height: 60px;
                    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 16px;
                ">
                    <i class="fas fa-exclamation-triangle" style="color: white; font-size: 28px;"></i>
                </div>
                <h3 style="margin: 0 0 8px 0; color: #1e293b; font-size: 20px;">Remove Student from Batch?</h3>
                <p style="margin: 0; color: #64748b; font-size: 14px;">This action cannot be undone</p>
            </div>
            
            <div style="
                background: #f8fafc;
                padding: 16px;
                border-radius: 8px;
                margin-bottom: 24px;
                border-left: 4px solid #f5576c;
            ">
                <p style="margin: 0 0 8px 0; color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600;">Student Name</p>
                <p style="margin: 0; color: #1e293b; font-size: 16px; font-weight: 600;">${studentName}</p>
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button onclick="closeConfirmDialog()" style="
                    flex: 1;
                    padding: 12px 24px;
                    border: 2px solid #e2e8f0;
                    background: white;
                    color: #64748b;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button onclick="proceedRemoveStudent(${studentId}, ${batchId})" style="
                    flex: 1;
                    padding: 12px 24px;
                    border: none;
                    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                    color: white;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(245, 87, 108, 0.4)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <i class="fas fa-user-times"></i> Remove Student
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(dialog);
    dialog.confirmDialog = true;
}

function closeConfirmDialog() {
    const dialogs = document.querySelectorAll('[style*="z-index: 10000"]');
    dialogs.forEach(dialog => {
        if (dialog.confirmDialog) {
            dialog.style.animation = 'fadeOut 0.2s ease';
            setTimeout(() => dialog.remove(), 200);
        }
    });
}

function proceedRemoveStudent(studentId, batchId) {
    closeConfirmDialog();
    showToast('Removing student from batch...', 'info');
    window.location.href = `?id=${batchId}&remove_student=${studentId}`;
}

function updateNielitRegNo(studentId, batchId) {
    const regNo = document.getElementById('nielit_reg_' + studentId).value;
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    
    // Show loading state
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    
    fetch('update_nielit_reg.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `student_id=${studentId}&batch_id=${batchId}&nielit_reg_no=${encodeURIComponent(regNo)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const input = document.getElementById('nielit_reg_' + studentId);
            if (input && typeof data.nielit_reg_no === 'string') {
                input.value = data.nielit_reg_no;
            }
            // Show success feedback
            btn.innerHTML = '<i class="fas fa-check"></i>';
            btn.classList.remove('btn-success');
            btn.classList.add('btn-success');
            
            // Show success toast
            showToast('NIELIT Registration Number updated successfully', 'success');
            
            // Reset button after 2 seconds
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }, 2000);
        } else {
            showToast('Error: ' + data.message, 'error');
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to update registration number', 'error');
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    });
}

function uploadStudentCertificate(studentRecordId, batchId, inputEl) {
    const file = inputEl.files[0];
    if (!file) return;

    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!allowedTypes.includes(file.type)) {
        showToast('Only PDF, JPG, and PNG files are allowed.', 'error');
        inputEl.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('batch_id', batchId);
    formData.append('student_record_id', studentRecordId);
    formData.append('certificate_file', file);

    showToast('Uploading certificate...', 'info');

    fetch('save_batch_certificate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Certificate uploaded successfully.', 'success');
            setTimeout(() => window.location.reload(), 900);
        } else {
            showToast(data.message || 'Certificate upload failed.', 'error');
            inputEl.value = '';
        }
    })
    .catch(error => {
        console.error(error);
        showToast('Certificate upload failed.', 'error');
        inputEl.value = '';
    });
}

function openPlacementModal(data) {
    document.getElementById('placementStudentRecordId').value = data.student_record_id || '';
    document.getElementById('placementStudentName').textContent = data.student_name || '';
    document.getElementById('placementStatus').value = data.placement_status || 'not_placed';
    document.getElementById('placementCompany').value = data.placement_company || '';
    document.getElementById('placementRole').value = data.placement_role || '';
    document.getElementById('placementPackageAmount').value = data.placement_package_amount || '';
    document.getElementById('placementPackageType').value = data.placement_package_type || 'annual';
    document.getElementById('placementLocation').value = data.placement_location || '';
    document.getElementById('placementDate').value = data.placement_date || '';
    document.getElementById('placementRemarks').value = data.placement_remarks || '';
    document.getElementById('placementModal').style.display = 'flex';
}

function closePlacementModal() {
    document.getElementById('placementModal').style.display = 'none';
}

function saveBatchPlacement() {
    const form = document.getElementById('placementForm');
    const formData = new FormData(form);
    formData.append('batch_id', <?php echo (int) $batch_id; ?>);

    showToast('Saving placement...', 'info');
    fetch('save_batch_placement.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error(text || 'Invalid server response');
        }
    })
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Placement saved.', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message || 'Could not save placement.', 'error');
        }
    })
    .catch(error => {
        console.error(error);
        showToast(error.message || 'Could not save placement.', 'error');
    });
}

// Scanned Admission Order Functions
function uploadScannedOrder(batchId, fileInput) {
    const file = fileInput.files[0];
    if (!file) return;
    
    // Validate file type
    if (file.type !== 'application/pdf') {
        showToast('Only PDF files are allowed', 'error');
        fileInput.value = '';
        return;
    }
    
    // Validate file size (10MB)
    if (file.size > 10 * 1024 * 1024) {
        showToast('File size must be less than 10MB', 'error');
        fileInput.value = '';
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('batch_id', batchId);
    formData.append('scanned_file', file);
    
    // Show loading toast
    showToast('Uploading scanned admission order...', 'info');
    
    fetch('upload_scanned_admission_order.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Reload page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to upload file', 'error');
    })
    .finally(() => {
        fileInput.value = '';
    });
}

function lockScannedOrder(batchId) {
    // Show confirmation dialog
    const dialog = document.createElement('div');
    dialog.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        animation: fadeIn 0.2s ease;
    `;
    
    dialog.innerHTML = `
        <div style="
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        ">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="
                    width: 60px;
                    height: 60px;
                    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 16px;
                ">
                    <i class="fas fa-lock" style="color: white; font-size: 28px;"></i>
                </div>
                <h3 style="margin: 0 0 8px 0; color: #1e293b; font-size: 20px;">Lock Scanned Admission Order?</h3>
                <p style="margin: 0; color: #64748b; font-size: 14px;">Once locked, the document cannot be modified or replaced</p>
            </div>
            
            <div style="
                background: #fff3cd;
                padding: 16px;
                border-radius: 8px;
                margin-bottom: 24px;
                border-left: 4px solid #ffc107;
            ">
                <p style="margin: 0; color: #856404; font-size: 14px;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Warning:</strong> This action will prevent any further modifications to the scanned admission order. Only Master Admin can unlock it later.
                </p>
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button onclick="closeConfirmDialog()" style="
                    flex: 1;
                    padding: 12px 24px;
                    border: 2px solid #e2e8f0;
                    background: white;
                    color: #64748b;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button onclick="proceedLockScannedOrder(${batchId})" style="
                    flex: 1;
                    padding: 12px 24px;
                    border: none;
                    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                    color: white;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(220, 53, 69, 0.4)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <i class="fas fa-lock"></i> Lock Document
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(dialog);
    dialog.confirmDialog = true;
}

function proceedLockScannedOrder(batchId) {
    closeConfirmDialog();
    
    const formData = new FormData();
    formData.append('action', 'lock');
    formData.append('batch_id', batchId);
    
    showToast('Locking scanned admission order...', 'info');
    
    fetch('upload_scanned_admission_order.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Reload page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to lock document', 'error');
    });
}

function unlockScannedOrder(batchId) {
    const formData = new FormData();
    formData.append('action', 'unlock');
    formData.append('batch_id', batchId);
    
    showToast('Unlocking scanned admission order...', 'info');
    
    fetch('upload_scanned_admission_order.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Reload page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to unlock document', 'error');
    });
}

function downloadScannedOrder(batchId) {
    // Create a temporary form to trigger download
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'upload_scanned_admission_order.php';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'download';
    
    const batchInput = document.createElement('input');
    batchInput.type = 'hidden';
    batchInput.name = 'batch_id';
    batchInput.value = batchId;
    
    form.appendChild(actionInput);
    form.appendChild(batchInput);
    document.body.appendChild(form);
    
    form.submit();
    document.body.removeChild(form);
}
</script>

<style>
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
.cert-upload-wrap {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 150px;
}
.cert-upload-wrap .cert-status {
    font-size: 11px;
    color: #64748b;
}
.cert-upload-wrap .btn {
    white-space: nowrap;
}
.certificate-info-banner {
    margin-bottom: 16px;
}
</style>

<div class="admin-wrapper">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../../admin/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($batch['batch_name']); ?></h4>
                <small><?php echo htmlspecialchars($batch['course_name']); ?> - <?php echo htmlspecialchars($batch['batch_code']); ?>
                <?php if ($is_locked): ?>
                    <span class="badge badge-danger" style="margin-left: 8px;">
                        <i class="fas fa-lock"></i> LOCKED
                    </span>
                <?php endif; ?>
                </small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars(get_role_display_name()); ?></span>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="admin-main">
            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showToast('<?php echo addslashes($message); ?>', '<?php echo $message_type === 'success' ? 'success' : 'error'; ?>');
                    });
                </script>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card stat-card-filled">
                    <div class="stat-value"><?php echo $stats['total_students'] ?? 0; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card stat-card-filled">
                    <div class="stat-value"><?php echo $stats['fees_paid_count'] ?? 0; ?></div>
                    <div class="stat-label">Fees Paid</div>
                </div>
                <div class="stat-card stat-card-filled">
                    <div class="stat-value">₹<?php echo number_format($stats['total_fees_collected'] ?? 0); ?></div>
                    <div class="stat-label">Fees Collected</div>
                </div>
                <div class="stat-card stat-card-filled">
                    <div class="stat-value"><?php echo number_format($stats['avg_attendance'] ?? 0, 1); ?>%</div>
                    <div class="stat-label">Avg Attendance</div>
                </div>
                <?php if ($can_view_placement): ?>
                <div class="stat-card stat-card-filled">
                    <div class="stat-value"><?php echo (int) ($placement_stats['placed'] ?? 0); ?> / <?php echo (int) ($placement_stats['total'] ?? 0); ?></div>
                    <div class="stat-label">Students Placed</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Batch Information -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-info-circle"></i> Batch Information
                    </h5>
                    <div>
                        <?php 
                        $lock_restricted = $is_locked && !$is_master_admin; // Only restrict if locked AND not master admin
                        if ($lock_restricted): ?>
                            <!-- Lock Warning for Course Coordinators -->
                            <div class="alert alert-warning" style="margin-bottom: 16px;">
                                <i class="fas fa-lock"></i> <strong>Batch is Locked:</strong> Admission order generation is disabled for locked batches.
                                <?php if ($lock_info && $lock_info['locked_at']): ?>
                                    <br><small>Locked on <?php echo date('M d, Y \a\t g:i A', strtotime($lock_info['locked_at'])); ?>
                                    <?php if ($lock_info['locked_by_username']): ?>
                                        by <?php echo htmlspecialchars($lock_info['locked_by_username']); ?>
                                    <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-lock"></i> Generate Admission Order (Locked)
                            </button>
                        <?php elseif ($is_locked && $is_master_admin): ?>
                            <!-- Lock Override Notice for Master Admins -->
                            <div class="alert alert-info" style="margin-bottom: 16px;">
                                <i class="fas fa-shield-alt"></i> <strong>Master Admin Override:</strong> This batch is locked, but you can generate admission orders.
                                <?php if ($lock_info && $lock_info['locked_at']): ?>
                                    <br><small>Locked on <?php echo date('M d, Y \a\t g:i A', strtotime($lock_info['locked_at'])); ?>
                                    <?php if ($lock_info['locked_by_username']): ?>
                                        by <?php echo htmlspecialchars($lock_info['locked_by_username']); ?>
                                    <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <a href="generate_admission_order.php?batch_id=<?php echo $batch_id; ?>" class="btn btn-warning">
                                <i class="fas fa-shield-alt"></i> Generate Admission Order (Override)
                            </a>
                        <?php else: ?>
                            <a href="generate_admission_order.php?batch_id=<?php echo $batch_id; ?>" class="btn btn-success">
                                <i class="fas fa-file-alt"></i> Generate Admission Order
                            </a>
                        <?php endif; ?>
                        <a href="manage_batches.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Batches
                        </a>
                    </div>
                </div>
                
                <div class="batch-info-grid">
                    <div class="info-box">
                        <h6>Batch Code</h6>
                        <p><?php echo htmlspecialchars($batch['batch_code']); ?></p>
                    </div>
                    <div class="info-box">
                        <h6>Coordinator</h6>
                        <p><?php echo htmlspecialchars($batch['batch_coordinator']); ?></p>
                    </div>
                    <div class="info-box">
                        <h6>Start Date</h6>
                        <p><?php echo date('d M Y', strtotime($batch['start_date'])); ?></p>
                    </div>
                    <div class="info-box">
                        <h6>End Date</h6>
                        <p><?php echo date('d M Y', strtotime($batch['end_date'])); ?></p>
                    </div>
                    <div class="info-box">
                        <h6>Training Fees</h6>
                        <p>₹<?php echo number_format($batch['training_fees'], 2); ?></p>
                    </div>
                    <div class="info-box">
                        <h6>Seats</h6>
                        <p>
                            <?php echo $batch['seats_filled']; ?> / <?php echo $batch['seats_total']; ?>
                            <?php 
                            $is_full = $batch['seats_filled'] >= $batch['seats_total'];
                            $is_almost_full = $batch['seats_filled'] >= ($batch['seats_total'] * 0.9);
                            if ($is_full): 
                            ?>
                                <br><span style="display: inline-block; background: #dc2626; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; margin-top: 8px;">
                                    <i class="fas fa-ban"></i> BATCH FULL
                                </span>
                            <?php elseif ($is_almost_full): ?>
                                <br><span style="display: inline-block; background: #f59e0b; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; margin-top: 8px;">
                                    <i class="fas fa-exclamation-triangle"></i> Almost Full
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if (!empty($batch['scheme_name'])): ?>
                    <div class="info-box" style="border-left-color: #10b981;">
                        <h6>Scheme/Project</h6>
                        <p>
                            <?php echo htmlspecialchars($batch['scheme_name']); ?>
                            <?php if (!empty($batch['scheme_code'])): ?>
                                <br><small style="color: #64748b; font-size: 12px;"><?php echo htmlspecialchars($batch['scheme_code']); ?></small>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Scanned Admission Order Upload -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-file-upload"></i> Scanned Admission Order
                    </h5>
                </div>
                
                <?php
                // Get scanned admission order info - check if columns exist first
                $scanned_info = null;
                $has_scanned_file = false;
                $is_scanned_locked = false;
                
                $col_check = $conn->query("SHOW COLUMNS FROM batches LIKE 'scanned_admission_order'");
                if ($col_check && $col_check->num_rows > 0) {
                    $scanned_order_sql = "SELECT scanned_admission_order, scanned_order_uploaded_at, scanned_order_uploaded_by, 
                                                 scanned_order_locked, scanned_order_locked_at, scanned_order_locked_by,
                                                 u1.username as uploaded_by_username, u2.username as locked_by_username
                                          FROM batches b
                                          LEFT JOIN admin u1 ON b.scanned_order_uploaded_by = u1.id
                                          LEFT JOIN admin u2 ON b.scanned_order_locked_by = u2.id
                                          WHERE b.id = ?";
                    $scanned_stmt = $conn->prepare($scanned_order_sql);
                    if ($scanned_stmt) {
                        $scanned_stmt->bind_param("i", $batch_id);
                        $scanned_stmt->execute();
                        $scanned_result = $scanned_stmt->get_result();
                        $scanned_info = $scanned_result->fetch_assoc();
                    }
                }
                
                $has_scanned_file = !empty($scanned_info['scanned_admission_order']);
                $is_scanned_locked = $scanned_info['scanned_order_locked'] ?? false;
                ?>
                
                <div style="padding: 20px;">
                    <?php if ($col_check && $col_check->num_rows === 0): ?>
                        <!-- Migration not run yet -->
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Setup Required:</strong> The scanned admission order feature needs a database migration.
                            Please run <code>migrations/install_scanned_admission_order.php</code> to enable this feature.
                        </div>
                    <?php elseif ($has_scanned_file): ?>
                        <!-- File exists - show info and actions -->
                        <div class="alert alert-success" style="margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <i class="fas fa-file-pdf" style="color: #dc3545; margin-right: 8px;"></i>
                                    <strong>Scanned Admission Order Available</strong>
                                    <?php if ($is_scanned_locked): ?>
                                        <span class="badge badge-danger" style="margin-left: 8px;">
                                            <i class="fas fa-lock"></i> LOCKED
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning" style="margin-left: 8px;">
                                            <i class="fas fa-unlock"></i> UNLOCKED
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button onclick="downloadScannedOrder(<?php echo $batch_id; ?>)" class="btn btn-primary btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </button>
                                </div>
                            </div>
                            
                            <div style="margin-top: 12px; font-size: 13px; color: #666;">
                                <div>
                                    <i class="fas fa-upload"></i> 
                                    Uploaded on <?php echo date('M d, Y \a\t g:i A', strtotime($scanned_info['scanned_order_uploaded_at'])); ?>
                                    <?php if ($scanned_info['uploaded_by_username']): ?>
                                        by <?php echo htmlspecialchars($scanned_info['uploaded_by_username']); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($is_scanned_locked && $scanned_info['scanned_order_locked_at']): ?>
                                    <div style="margin-top: 4px;">
                                        <i class="fas fa-lock"></i> 
                                        Locked on <?php echo date('M d, Y \a\t g:i A', strtotime($scanned_info['scanned_order_locked_at'])); ?>
                                        <?php if ($scanned_info['locked_by_username']): ?>
                                            by <?php echo htmlspecialchars($scanned_info['locked_by_username']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Action buttons -->
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <?php if (!$is_scanned_locked): ?>
                                <!-- Replace file option -->
                                <div style="flex: 1;">
                                    <label for="scanned_file_replace" class="btn btn-warning" style="margin: 0; cursor: pointer;">
                                        <i class="fas fa-sync-alt"></i> Replace File
                                    </label>
                                    <input type="file" id="scanned_file_replace" accept=".pdf" style="display: none;" onchange="uploadScannedOrder(<?php echo $batch_id; ?>, this)">
                                </div>
                                
                                <!-- Lock button -->
                                <button onclick="lockScannedOrder(<?php echo $batch_id; ?>)" class="btn btn-danger">
                                    <i class="fas fa-lock"></i> Lock Document
                                </button>
                            <?php else: ?>
                                <!-- Locked state -->
                                <div class="alert alert-info" style="flex: 1; margin: 0;">
                                    <i class="fas fa-info-circle"></i> 
                                    Document is locked and cannot be modified.
                                    <?php if ($is_master_admin): ?>
                                        <button onclick="unlockScannedOrder(<?php echo $batch_id; ?>)" class="btn btn-warning btn-sm" style="margin-left: 12px;">
                                            <i class="fas fa-unlock"></i> Unlock (Master Admin)
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php else: ?>
                        <!-- No file uploaded yet -->
                        <div class="alert alert-info" style="margin-bottom: 20px;">
                            <i class="fas fa-info-circle"></i> 
                            No scanned admission order uploaded yet. Upload a PDF file of the signed admission order.
                        </div>
                        
                        <!-- Upload form -->
                        <div style="max-width: 400px;">
                            <label for="scanned_file_upload" class="btn btn-success" style="width: 100%; cursor: pointer; padding: 12px;">
                                <i class="fas fa-cloud-upload-alt"></i> Upload Scanned Admission Order (PDF)
                            </label>
                            <input type="file" id="scanned_file_upload" accept=".pdf" style="display: none;" onchange="uploadScannedOrder(<?php echo $batch_id; ?>, this)">
                            <small class="form-text text-muted" style="margin-top: 8px;">
                                Only PDF files are allowed. Maximum file size: 10MB.
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Students to Batch -->
            <?php if (!$is_locked && !$is_placement_coordinator && !empty($eligible_students)): ?>
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-user-plus"></i> Add Students to Batch
                    </h5>
                    <span class="badge badge-primary"><?php echo count($eligible_students); ?> available</span>
                </div>
                <p style="color:#64748b;font-size:14px;margin-bottom:16px;">
                    Select students registered for <strong><?php echo htmlspecialchars($batch['course_name']); ?></strong> who are not yet in this batch.
                </p>
                <form method="POST" action="">
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th style="width:40px;"><input type="checkbox" id="select-all-eligible" title="Select All"></th>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eligible_students as $estu): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="eligible-student-checkbox" name="student_record_ids[]" value="<?php echo (int)$estu['id']; ?>">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($estu['student_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($estu['name']); ?></td>
                                    <td><?php echo htmlspecialchars($estu['email']); ?></td>
                                    <td><?php echo htmlspecialchars($estu['mobile']); ?></td>
                                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars(ucfirst($estu['status'])); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:16px;display:flex;gap:10px;align-items:center;">
                        <button type="submit" name="add_students_to_batch" value="1" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add Selected to Batch
                        </button>
                        <span id="eligible-selected-count" style="color:#64748b;font-size:14px;display:none;">
                            <span id="eligible-count-number">0</span> selected
                        </span>
                    </div>
                </form>
            </div>
            <?php elseif (!$is_locked): ?>
            <div class="content-card">
                <div class="alert alert-info" style="margin:0;">
                    <i class="fas fa-info-circle"></i>
                    No unassigned students available for this course. Assign a course from <strong>Admin → Students → Assign Course</strong>, or wait for new registrations.
                </div>
            </div>
            <?php endif; ?>

            <!-- Students List -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-users"></i> Enrolled Students
                    </h5>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <?php if (!empty($students) && !$is_locked && !empty($move_target_batches)): ?>
                            <button type="button" id="bulk-move-btn" class="btn btn-warning btn-sm" style="display:none;" onclick="openMoveModal()">
                                <i class="fas fa-exchange-alt"></i> Move Selected (<span id="enrolled-selected-count">0</span>)
                            </button>
                        <?php endif; ?>
                        <?php if (!empty($students)): ?>
                            <a href="?id=<?php echo $batch_id; ?>&export=excel" class="btn btn-success btn-sm">
                                <i class="fas fa-file-excel"></i> Export to Excel
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($can_upload_certificates): ?>
                    <div class="alert alert-success certificate-info-banner" style="margin: 16px 16px 0;">
                        <i class="fas fa-certificate"></i>
                        <strong>Certificate upload is enabled.</strong>
                        This batch is Completed and Locked. Upload each student's completion certificate (PDF/JPG/PNG). Students will see it under <em>My Certificates</em> in the student portal.
                    </div>
                <?php elseif (!empty($students)): ?>
                    <div class="alert alert-info certificate-info-banner" style="margin: 16px 16px 0;">
                        <i class="fas fa-info-circle"></i>
                        <?php echo htmlspecialchars($certificate_upload_hint ?: 'Certificate upload will be available after the batch is marked Completed and Locked.'); ?>
                    </div>
                <?php endif; ?>

                <?php if ($can_view_placement && !empty($students)): ?>
                    <div class="alert alert-primary certificate-info-banner" style="margin: 16px 16px 0;">
                        <i class="fas fa-briefcase"></i>
                        <strong>Placement tracking:</strong>
                        <?php echo (int) ($placement_stats['placed'] ?? 0); ?> placed,
                        <?php echo (int) ($placement_stats['in_process'] ?? 0); ?> in process,
                        <?php echo (int) ($placement_stats['higher_studies'] ?? 0); ?> higher studies,
                        <?php echo (int) ($placement_stats['not_placed'] ?? 0); ?> not placed.
                        <?php if ($can_manage_placement): ?>
                            Use the <strong>Placement</strong> column to record company, role, and package.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($students)): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <?php if (!$is_locked && !empty($move_target_batches)): ?>
                                    <th style="width:40px;">
                                        <input type="checkbox" id="select-all-enrolled" title="Select All">
                                    </th>
                                    <?php endif; ?>
                                    <th>Student ID</th>
                                    <th>NIELIT Portal Reg. No.</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Enrollment Date</th>
                                    <th>Fees Status</th>
                                    <th>Attendance</th>
                                    <th>Certificate</th>
                                    <?php if ($can_view_placement): ?>
                                    <th>Placement</th>
                                    <?php endif; ?>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <?php if (!$is_locked && !empty($move_target_batches)): ?>
                                        <td>
                                            <input type="checkbox"
                                                   class="enrolled-student-checkbox"
                                                   value="<?php echo (int)$student['id']; ?>"
                                                   data-name="<?php echo htmlspecialchars($student['name'], ENT_QUOTES); ?>">
                                        </td>
                                        <?php endif; ?>
                                        <td><strong><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></strong></td>
                                        <td>
                                            <div style="display: flex; gap: 5px; align-items: center;">
                                                <input type="text" 
                                                       id="nielit_reg_<?php echo $student['id']; ?>"
                                                       class="form-control form-control-sm nielit-reg-input" 
                                                       value="<?php echo htmlspecialchars($student['nielit_registration_no'] ?? ''); ?>" 
                                                       placeholder="Enter Reg. No."
                                                       <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                <?php if ($is_locked): ?>
                                                    <button type="button" class="btn btn-secondary btn-sm" disabled title="Batch is locked">
                                                        <i class="fas fa-lock"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" 
                                                            class="btn btn-success btn-sm" 
                                                            onclick="updateNielitRegNo(<?php echo $student['id']; ?>, <?php echo $batch_id; ?>)"
                                                            title="Save Registration Number">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['mobile']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($student['enrollment_date'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $student['fees_status'] === 'Paid' ? 'success' : 
                                                    ($student['fees_status'] === 'Partial' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo $student['fees_status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($student['attendance_percentage'], 1); ?>%</td>
                                        <td>
                                            <?php if (!empty($student['certificate_file'])): ?>
                                                <div class="cert-upload-wrap">
                                                    <a href="view_batch_certificate.php?batch_id=<?php echo (int)$batch_id; ?>&student_record_id=<?php echo (int)$student['id']; ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="view_batch_certificate.php?batch_id=<?php echo (int)$batch_id; ?>&student_record_id=<?php echo (int)$student['id']; ?>&download=1" class="btn btn-outline-success btn-sm">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                    <?php if ($student['certificate_number']): ?>
                                                        <span class="cert-status"><?php echo htmlspecialchars($student['certificate_number']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($can_upload_certificates): ?>
                                                        <label class="btn btn-outline-secondary btn-sm mb-0" style="cursor:pointer;">
                                                            <i class="fas fa-upload"></i> Replace
                                                            <input type="file" class="d-none cert-file-input" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" onchange="uploadStudentCertificate(<?php echo (int)$student['id']; ?>, <?php echo (int)$batch_id; ?>, this)">
                                                        </label>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif ($can_upload_certificates): ?>
                                                <div class="cert-upload-wrap">
                                                    <label class="btn btn-warning btn-sm mb-0" style="cursor:pointer;">
                                                        <i class="fas fa-upload"></i> Upload Certificate
                                                        <input type="file" class="d-none cert-file-input" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" onchange="uploadStudentCertificate(<?php echo (int)$student['id']; ?>, <?php echo (int)$batch_id; ?>, this)">
                                                    </label>
                                                    <span class="cert-status">PDF, JPG, or PNG</span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">Not uploaded</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($can_view_placement):
                                            $pStatus = strtolower(trim((string) ($student['placement_status'] ?? 'not_placed')));
                                            $pStatusLabel = $placement_status_options[$pStatus] ?? 'Not placed';
                                            $pBadge = batch_placement_status_badge_class($pStatus);
                                            $pPackage = batch_placement_format_package(
                                                $student['placement_package_amount'] ?? null,
                                                $student['placement_package_type'] ?? 'annual'
                                            );
                                        ?>
                                        <td>
                                            <span class="badge badge-<?php echo htmlspecialchars($pBadge); ?>">
                                                <?php echo htmlspecialchars($pStatusLabel); ?>
                                            </span>
                                            <?php if ($pStatus === 'placed' && !empty($student['placement_company'])): ?>
                                                <div class="small mt-1"><strong><?php echo htmlspecialchars($student['placement_company']); ?></strong></div>
                                                <?php if (!empty($student['placement_role'])): ?>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($student['placement_role']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($pPackage !== ''): ?>
                                                    <div class="small text-success"><?php echo htmlspecialchars($pPackage); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($student['placement_location'])): ?>
                                                    <div class="small text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($student['placement_location']); ?></div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($can_manage_placement): ?>
                                                <button type="button"
                                                        class="btn btn-outline-primary btn-sm mt-1"
                                                        onclick='openPlacementModal(<?php echo json_encode([
                                                            "student_record_id" => (int) $student["id"],
                                                            "student_name" => $student["name"],
                                                            "placement_status" => $pStatus,
                                                            "placement_company" => $student["placement_company"] ?? "",
                                                            "placement_role" => $student["placement_role"] ?? "",
                                                            "placement_package_amount" => $student["placement_package_amount"] ?? "",
                                                            "placement_package_type" => $student["placement_package_type"] ?? "annual",
                                                            "placement_location" => $student["placement_location"] ?? "",
                                                            "placement_date" => $student["placement_date"] ?? "",
                                                            "placement_remarks" => $student["placement_remarks"] ?? "",
                                                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                                    <i class="fas fa-briefcase"></i> <?php echo ($pStatus === 'not_placed' && empty($student['placement_company'])) ? 'Add' : 'Edit'; ?>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        <td>
                                            <a href="../../admin/view_student_documents.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn btn-primary btn-sm" title="View Student Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (!$is_placement_coordinator): ?>
                                            <?php if ($is_locked): ?>
                                                <button class="btn btn-secondary btn-sm" disabled title="Batch is locked">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            <?php else: ?>
                                                <?php if (!empty($move_target_batches)): ?>
                                                <button type="button"
                                                        class="btn btn-warning btn-sm"
                                                        title="Move to Another Batch"
                                                        onclick="openMoveModal([<?php echo (int)$student['id']; ?>], ['<?php echo htmlspecialchars(addslashes($student['name'])); ?>'])">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                                <?php endif; ?>
                                                <a href="javascript:void(0);" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="confirmRemoveStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['name'])); ?>', <?php echo $batch_id; ?>);" 
                                                   title="Remove from Batch">
                                                    <i class="fas fa-user-times"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-user-slash" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.3;"></i>
                        <p style="margin: 0; font-size: 16px;">No students enrolled in this batch yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php if (!$is_locked && !empty($move_target_batches)): ?>
<div id="moveBatchModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10001;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:12px;padding:24px;max-width:480px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.25);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;font-size:18px;color:#1e293b;">
                <i class="fas fa-exchange-alt" style="color:#f59e0b;"></i> Move to Another Batch
            </h3>
            <button type="button" onclick="closeMoveModal()" style="border:none;background:none;font-size:24px;cursor:pointer;color:#64748b;">&times;</button>
        </div>
        <p style="margin:0 0 12px;color:#64748b;font-size:14px;">
            Course: <strong><?php echo htmlspecialchars($batch['course_name']); ?></strong>
        </p>
        <p id="move-modal-students" style="margin:0 0 16px;color:#1e293b;font-size:14px;"></p>
        <form method="POST" action="">
            <div id="move-modal-record-ids"></div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label" style="display:block;font-weight:600;margin-bottom:8px;">Destination Batch</label>
                <select name="target_batch_id" class="form-control" required>
                    <option value="">-- Select Batch --</option>
                    <?php foreach ($move_target_batches as $tb): ?>
                    <option value="<?php echo (int)$tb['id']; ?>">
                        <?php echo htmlspecialchars($tb['batch_name'] . ' (' . $tb['batch_code'] . ') — ' . $tb['seats_filled'] . '/' . $tb['seats_total'] . ' seats'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" name="move_students_to_batch" value="1" class="btn btn-warning" style="flex:1;">
                    <i class="fas fa-exchange-alt"></i> Move Students
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeMoveModal()" style="flex:1;">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openMoveModal(recordIds, names) {
    const modal = document.getElementById('moveBatchModal');
    if (!modal) return;

    if (!recordIds || !recordIds.length) {
        recordIds = [];
        names = [];
        document.querySelectorAll('.enrolled-student-checkbox:checked').forEach(function (cb) {
            recordIds.push(cb.value);
            names.push(cb.getAttribute('data-name') || 'Student');
        });
    }

    if (!recordIds.length) {
        showToast('Please select at least one student.', 'warning');
        return;
    }

    const idsContainer = document.getElementById('move-modal-record-ids');
    idsContainer.innerHTML = '';
    recordIds.forEach(function (id) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'move_student_record_ids[]';
        inp.value = id;
        idsContainer.appendChild(inp);
    });

    const label = document.getElementById('move-modal-students');
    if (recordIds.length === 1) {
        label.textContent = 'Moving: ' + (names[0] || '1 student');
    } else {
        label.textContent = 'Moving ' + recordIds.length + ' students';
    }

    modal.style.display = 'flex';
}

function closeMoveModal() {
    const modal = document.getElementById('moveBatchModal');
    if (modal) modal.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('select-all-eligible');
    if (!selectAll) return;

    const checkboxes = document.querySelectorAll('.eligible-student-checkbox');
    const countEl = document.getElementById('eligible-selected-count');
    const countNum = document.getElementById('eligible-count-number');

    function updateEligibleCount() {
        const n = document.querySelectorAll('.eligible-student-checkbox:checked').length;
        if (countEl) countEl.style.display = n ? 'inline' : 'none';
        if (countNum) countNum.textContent = n;
    }

    selectAll.addEventListener('change', function () {
        checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
        updateEligibleCount();
    });

    checkboxes.forEach(cb => cb.addEventListener('change', updateEligibleCount));

    const selectAllEnrolled = document.getElementById('select-all-enrolled');
    const enrolledCheckboxes = document.querySelectorAll('.enrolled-student-checkbox');
    const bulkMoveBtn = document.getElementById('bulk-move-btn');
    const enrolledCountEl = document.getElementById('enrolled-selected-count');

    function updateEnrolledCount() {
        const n = document.querySelectorAll('.enrolled-student-checkbox:checked').length;
        if (bulkMoveBtn) bulkMoveBtn.style.display = n ? 'inline-flex' : 'none';
        if (enrolledCountEl) enrolledCountEl.textContent = n;
    }

    if (selectAllEnrolled) {
        selectAllEnrolled.addEventListener('change', function () {
            enrolledCheckboxes.forEach(function (cb) { cb.checked = selectAllEnrolled.checked; });
            updateEnrolledCount();
        });
    }

    enrolledCheckboxes.forEach(function (cb) {
        cb.addEventListener('change', updateEnrolledCount);
    });
});
</script>

<?php if ($can_manage_placement): ?>
<div id="placementModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:10000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:12px;max-width:560px;width:100%;padding:24px;box-shadow:0 20px 40px rgba(0,0,0,0.2);max-height:90vh;overflow:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h5 style="margin:0;"><i class="fas fa-briefcase"></i> Student Placement</h5>
            <button type="button" class="btn btn-sm btn-secondary" onclick="closePlacementModal()">&times;</button>
        </div>
        <p class="text-muted mb-3">Student: <strong id="placementStudentName"></strong></p>
        <form id="placementForm" onsubmit="event.preventDefault(); saveBatchPlacement();">
            <input type="hidden" name="student_record_id" id="placementStudentRecordId">
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select class="form-control" name="placement_status" id="placementStatus">
                    <?php foreach ($placement_status_options as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Company / Organization</label>
                <input type="text" class="form-control" name="placement_company" id="placementCompany" placeholder="e.g. TCS, Infosys, Local employer">
            </div>
            <div class="mb-3">
                <label class="form-label">Job Role / Designation</label>
                <input type="text" class="form-control" name="placement_role" id="placementRole" placeholder="e.g. Junior Developer">
            </div>
            <div class="row">
                <div class="col-md-7 mb-3">
                    <label class="form-label">Package Amount</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="placement_package_amount" id="placementPackageAmount" placeholder="e.g. 3.6">
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">Package Type</label>
                    <select class="form-control" name="placement_package_type" id="placementPackageType">
                        <?php foreach ($placement_package_types as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Location</label>
                <input type="text" class="form-control" name="placement_location" id="placementLocation" placeholder="City / state">
            </div>
            <div class="mb-3">
                <label class="form-label">Placement Date</label>
                <input type="date" class="form-control" name="placement_date" id="placementDate">
            </div>
            <div class="mb-3">
                <label class="form-label">Remarks</label>
                <textarea class="form-control" name="placement_remarks" id="placementRemarks" rows="2" placeholder="Optional notes"></textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Placement</button>
                <button type="button" class="btn btn-secondary" onclick="closePlacementModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

</body>
</html>
