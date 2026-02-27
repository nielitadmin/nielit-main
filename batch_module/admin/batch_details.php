<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/batch_functions.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../../admin/login_new.php");
    exit();
}

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

$students = getBatchStudents($batch_id, $conn);
$stats = getBatchStats($batch_id, $conn);

$message = '';
$message_type = 'success';

// Handle remove student
if (isset($_GET['remove_student'])) {
    $result = removeStudentFromBatch($_GET['remove_student'], $batch_id, $conn);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'danger';
    
    // Refresh data
    $students = getBatchStudents($batch_id, $conn);
    $stats = getBatchStats($batch_id, $conn);
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
            <div class="nav-item">
                <a href="../../admin/students.php" class="nav-link">
                    <i class="fas fa-users"></i> Students
                </a>
            </div>
            <div class="nav-item">
                <a href="../../admin/manage_courses.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </div>
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
            <div class="nav-item">
                <a href="../../admin/add_admin.php" class="nav-link">
                    <i class="fas fa-user-shield"></i> Add Admin
                </a>
            </div>
            
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
                <small><?php echo htmlspecialchars($batch['course_name']); ?> - <?php echo htmlspecialchars($batch['batch_code']); ?></small>
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
                        <a href="generate_admission_order.php?batch_id=<?php echo $batch_id; ?>" class="btn btn-success">
                            <i class="fas fa-file-alt"></i> Generate Admission Order
                        </a>
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

            <!-- Students List -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-users"></i> Enrolled Students
                    </h5>
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
                                                       style="min-width: 150px;">
                                                <button type="button" 
                                                        class="btn btn-success btn-sm" 
                                                        onclick="updateNielitRegNo(<?php echo $student['id']; ?>, <?php echo $batch_id; ?>)"
                                                        title="Save Registration Number">
                                                    <i class="fas fa-save"></i>
                                                </button>
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
                                            <a href="javascript:void(0);" 
                                               class="btn btn-danger btn-sm" 
                                               onclick="confirmRemoveStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['name'])); ?>', <?php echo $batch_id; ?>);" 
                                               title="Remove from Batch">
                                                <i class="fas fa-user-times"></i>
                                            </a>
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
