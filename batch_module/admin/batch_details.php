<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/batch_functions.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../../admin/login_new.php");
    exit();
}

// Check user role for lock bypass
$is_master_admin = ($_SESSION['admin_role'] === 'master_admin');
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

// Check if batch is locked
$is_locked = isBatchLocked($batch_id, $conn);
$lock_info = getBatchLockInfo($batch_id, $conn);

$students = getBatchStudents($batch_id, $conn);
$stats = getBatchStats($batch_id, $conn);

$message = '';
$message_type = 'success';

// Handle remove student (only if batch is not locked)
if (isset($_GET['remove_student']) && !$is_locked) {
    $result = removeStudentFromBatch($_GET['remove_student'], $batch_id, $conn);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'danger';
    
    // Refresh data
    $students = getBatchStudents($batch_id, $conn);
    $stats = getBatchStats($batch_id, $conn);
} elseif (isset($_GET['remove_student']) && $is_locked) {
    $message = 'Cannot remove student: Batch is locked and cannot be modified.';
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Details - <?php echo htmlspecialchars($batch['batch_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        .batch-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .info-box {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        .info-box h6 {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .info-box p {
            color: #1e293b;
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }
    </style>
</head>
<body>

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
</style>

<div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-logo">
            <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo">
            <h5>NIELIT Admin</h5>
            <small>Bhubaneswar</small>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="../../admin/dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
            
            <?php 
            // Get user role for sidebar restrictions
            $is_master_admin = ($_SESSION['admin_role'] === 'master_admin');
            $is_nsqf_manager = ($_SESSION['admin_role'] === 'nsqf_course_manager');
            ?>
            
            <?php if (!$is_nsqf_manager): ?>
            <div class="nav-item">
                <a href="../../admin/students.php" class="nav-link">
                    <i class="fas fa-users"></i> Students
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($is_nsqf_manager): ?>
            <div class="nav-item">
                <a href="../../admin/manage_nsqf_templates.php" class="nav-link">
                    <i class="fas fa-graduation-cap"></i> Course Templates
                </a>
            </div>
            <?php else: ?>
            <div class="nav-item">
                <a href="../../admin/manage_courses.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (!$is_nsqf_manager): ?>
            <div class="nav-item">
                <a href="manage_batches.php" class="nav-link active">
                    <i class="fas fa-layer-group"></i> Batches
                </a>
            </div>
            <div class="nav-item">
                <a href="approve_students.php" class="nav-link">
                    <i class="fas fa-user-check"></i> Approve Students
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($is_master_admin): ?>
            <div class="nav-divider"></div>
            <div class="nav-item">
                <a href="../../admin/add_admin.php" class="nav-link">
                    <i class="fas fa-user-shield"></i> Add Admin
                </a>
            </div>
            <div class="nav-item">
                <a href="../../admin/manage_admins.php" class="nav-link">
                    <i class="fas fa-users-cog"></i> Manage Admins
                </a>
            </div>
            <?php endif; ?>
            
            <div class="nav-divider"></div>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/index.php" class="nav-link">
                    <i class="fas fa-globe"></i> View Website
                </a>
            </div>
            <div class="nav-item">
                <a href="../../admin/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>
    </aside>

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
                        <span class="user-role">Administrator</span>
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
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_students'] ?? 0; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['fees_paid_count'] ?? 0; ?></div>
                    <div class="stat-label">Fees Paid</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">₹<?php echo number_format($stats['total_fees_collected'] ?? 0); ?></div>
                    <div class="stat-label">Fees Collected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['avg_attendance'] ?? 0, 1); ?>%</div>
                    <div class="stat-label">Avg Attendance</div>
                </div>
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
                        <p><?php echo $batch['seats_filled']; ?> / <?php echo $batch['seats_total']; ?></p>
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

            <!-- Students List -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-users"></i> Enrolled Students
                    </h5>
                    <div>
                        <?php if (!empty($students)): ?>
                            <a href="?id=<?php echo $batch_id; ?>&export=excel" class="btn btn-success">
                                <i class="fas fa-file-excel"></i> Export to Excel
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($students)): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>NIELIT Portal Reg. No.</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Enrollment Date</th>
                                    <th>Fees Status</th>
                                    <th>Attendance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></strong></td>
                                        <td>
                                            <div style="display: flex; gap: 5px; align-items: center;">
                                                <input type="text" 
                                                       id="nielit_reg_<?php echo $student['id']; ?>"
                                                       class="form-control form-control-sm" 
                                                       value="<?php echo htmlspecialchars($student['nielit_registration_no'] ?? ''); ?>" 
                                                       placeholder="Enter Reg. No."
                                                       style="min-width: 150px;"
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
                                            <a href="../../admin/view_student_documents.php?id=<?php echo urlencode($student['student_id']); ?>" class="btn btn-primary btn-sm" title="View Student Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($is_locked): ?>
                                                <button class="btn btn-secondary btn-sm" disabled title="Batch is locked">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            <?php else: ?>
                                                <a href="javascript:void(0);" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="confirmRemoveStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['name'])); ?>', <?php echo $batch_id; ?>);" 
                                                   title="Remove from Batch">
                                                    <i class="fas fa-user-times"></i>
                                                </a>
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

</body>
</html>
