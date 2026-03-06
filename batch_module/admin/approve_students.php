<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/batch_functions.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../../admin/login_new.php");
    exit();
}

$message = '';
$message_type = 'success';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve':
                $result = approveStudent($_POST['student_id'], $_POST['batch_id'], $_SESSION['admin'], $conn);
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'reject':
                $result = rejectStudent($_POST['student_id'], $_SESSION['admin'], $conn);
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'danger';
                break;
        }
    }
}

// Check if tables exist by trying a simple query
$test_query = $conn->query("SHOW TABLES LIKE 'batches'");
if (!$test_query || $test_query->num_rows === 0) {
    die('<div style="font-family: Arial; padding: 40px; text-align: center;">
        <h2 style="color: #e74c3c;">⚠️ Database Tables Not Found!</h2>
        <p style="font-size: 18px; margin: 20px 0;">The batch management tables haven\'t been created yet.</p>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px auto; max-width: 600px; text-align: left;">
            <h3>📋 Quick Fix:</h3>
            <ol style="line-height: 2;">
                <li>Open <strong>phpMyAdmin</strong></li>
                <li>Select your database: <strong>nielit_bhubaneswar</strong></li>
                <li>Click the <strong>"Import"</strong> tab</li>
                <li>Choose file: <strong>batch_module/database_batch_system.sql</strong></li>
                <li>Click <strong>"Go"</strong></li>
                <li>Refresh this page</li>
            </ol>
        </div>
        <a href="../../admin/dashboard.php" style="display: inline-block; margin-top: 20px; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a>
    </div>');
}

// Get pending students
$pending_students = getPendingStudents($conn);

// Get active batches for dropdown
$active_batches = getActiveBatches($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Students - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <style>
        .student-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }
        .student-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .student-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .batch-select {
            flex: 1;
            max-width: 300px;
        }
    </style>
</head>
<body>

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
                <a href="../../admin/dashboard.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_batches.php" class="nav-link">
                    <i class="fas fa-layer-group"></i> Batches
                </a>
            </div>
            
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'master_admin'): ?>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php" class="nav-link">
                    <i class="fas fa-project-diagram"></i> Schemes/Projects
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'master_admin'): ?>
            <div class="nav-divider"></div>
            <div class="nav-section-title">System Settings</div>
            
            <div class="nav-item">
                <a href="../../admin/manage_centres.php" class="nav-link">
                    <i class="fas fa-building"></i> Training Centres
                </a>
            </div>
            <div class="nav-item">
                <a href="../../admin/manage_themes.php" class="nav-link">
                    <i class="fas fa-palette"></i> Themes
                </a>
            </div>
            <div class="nav-item">
                <a href="../../admin/manage_homepage.php" class="nav-link">
                    <i class="fas fa-home"></i> Homepage Content
                </a>
            </div>
            
            <div class="nav-divider"></div>
            <?php endif; ?>
            
            <div class="nav-item">
                <a href="approve_students.php" class="nav-link active">
                    <i class="fas fa-user-check"></i> Approve Students
                </a>
            </div>
            
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'master_admin'): ?>
            <div class="nav-item">
                <a href="../../admin/add_admin.php" class="nav-link">
                    <i class="fas fa-user-plus"></i> Add Admin
                </a>
            </div>
            <div class="nav-item">
                <a href="../../admin/manage_admins.php" class="nav-link">
                    <i class="fas fa-users-cog"></i> Manage Admins
                </a>
            </div>
            <?php endif; ?>
            
            <div class="nav-item">
                <a href="../../admin/reset_password.php" class="nav-link">
                    <i class="fas fa-key"></i> Reset Password
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
                <h4><i class="fas fa-user-check"></i> Approve Students</h4>
                <small>Review and approve student registrations</small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role">
                            <?php 
                            echo isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'master_admin' 
                                ? 'Master Administrator' 
                                : 'Course Coordinator'; 
                            ?>
                        </span>
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
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Pending Students -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-clock"></i> Pending Approvals
                        <span class="badge badge-warning" style="margin-left: 8px;"><?php echo count($pending_students); ?></span>
                    </h5>
                </div>
                
                <?php if (!empty($pending_students)): ?>
                    <?php foreach ($pending_students as $student): ?>
                        <div class="student-card">
                            <div class="student-info">
                                <div class="info-item">
                                    <span class="info-label">Name</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Course</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['course']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Email</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['email']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Mobile</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['mobile']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Registration Date</span>
                                    <span class="info-value"><?php echo date('d M Y', strtotime($student['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <form method="POST" action="" class="action-buttons">
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                
                                <select name="batch_id" class="form-control batch-select" required>
                                    <option value="">Select Batch</option>
                                    <?php foreach ($active_batches as $batch): ?>
                                        <?php if ($batch['course_name'] === $student['course']): ?>
                                            <option value="<?php echo $batch['id']; ?>">
                                                <?php echo htmlspecialchars($batch['batch_name']); ?> 
                                                (<?php echo $batch['enrolled_count']; ?>/<?php echo $batch['seats_total']; ?> seats)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button type="submit" name="action" value="approve" class="btn btn-success">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                
                                <button type="submit" name="action" value="reject" class="btn btn-danger"
                                        onclick="return confirm('Are you sure you want to reject this student?');">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                                
                                <button type="button" class="btn btn-secondary" onclick="viewStudentDetails(<?php echo $student['id']; ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.3; color: #10b981;"></i>
                        <p style="margin: 0; font-size: 16px;">No pending approvals. All students have been processed!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Student Details Modal -->
<div class="modal" id="studentDetailsModal" style="display: none;">
    <div class="modal-dialog" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);">
            <h5 class="modal-title" style="color: white;"><i class="fas fa-user"></i> Student Details</h5>
            <button type="button" class="close-modal" onclick="closeStudentModal()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 0; width: 30px; height: 30px;">&times;</button>
        </div>
        <div class="modal-body" id="studentDetailsContent">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #2563eb;"></i>
                <p style="margin-top: 16px; color: #64748b;">Loading student details...</p>
            </div>
        </div>
        <div style="padding: 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: right;">
            <button type="button" class="btn btn-secondary" onclick="closeStudentModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<script>
function viewStudentDetails(studentId) {
    const modal = document.getElementById('studentDetailsModal');
    const content = document.getElementById('studentDetailsContent');
    
    // Show modal with loading state
    modal.style.display = 'flex';
    content.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #2563eb;"></i>
            <p style="margin-top: 16px; color: #64748b;">Loading student details...</p>
        </div>
    `;
    
    // Fetch student details via AJAX
    fetch('<?php echo APP_URL; ?>/batch_module/admin/get_student_details.php?id=' + studentId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = formatStudentDetails(data.student);
            } else {
                content.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #ef4444;"></i>
                        <p style="margin-top: 16px; color: #64748b;">${data.message || 'Failed to load student details'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            content.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #ef4444;"></i>
                    <p style="margin-top: 16px; color: #64748b;">Error loading student details</p>
                </div>
            `;
        });
}

function formatStudentDetails(student) {
    let educationHTML = '';
    if (student.education_records && student.education_records.length > 0) {
        educationHTML = `
            <div style="margin-top: 24px;">
                <h6 style="color: #1e293b; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-book-open"></i> Educational Qualifications
                </h6>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead style="background: #f8fafc;">
                            <tr>
                                <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: left;">Exam Passed</th>
                                <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: left;">Exam Name</th>
                                <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">Year</th>
                                <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: left;">Institute/Board</th>
                                <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: left;">Stream</th>
                                <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">%/CGPA</th>
                            </tr>
                        </thead>
                        <tbody>`;
        student.education_records.forEach(edu => {
            educationHTML += `
                            <tr>
                                <td style="padding: 8px; border: 1px solid #e2e8f0;">${edu.exam_passed || '-'}</td>
                                <td style="padding: 8px; border: 1px solid #e2e8f0;">${edu.exam_name || '-'}</td>
                                <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">${edu.year_of_passing || '-'}</td>
                                <td style="padding: 8px; border: 1px solid #e2e8f0;">${edu.institute_name || '-'}</td>
                                <td style="padding: 8px; border: 1px solid #e2e8f0;">${edu.stream || '-'}</td>
                                <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">${edu.percentage || '-'}</td>
                            </tr>`;
        });
        educationHTML += `
                        </tbody>
                    </table>
                </div>
            </div>`;
    }
    
    return `
        <div style="padding: 20px; max-height: 70vh; overflow-y: auto;">
            <!-- Personal Information -->
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                <h6 style="color: #1e293b; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user"></i> Personal Information
                </h6>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Student ID</label>
                        <div style="font-size: 14px; color: #1e293b; font-weight: 500;">${student.student_id || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Full Name</label>
                        <div style="font-size: 14px; color: #1e293b; font-weight: 500;">${student.name || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Father's Name</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.father_name || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Mother's Name</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.mother_name || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Date of Birth</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.dob_formatted || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Age</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.age ? student.age + ' years' : 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Gender</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.gender || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Category</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.category || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Aadhar Number</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.aadhar || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">APAAR ID</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.apaar_id || 'Not Provided'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Religion</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.religion || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Marital Status</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.marital_status || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Nationality</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.nationality || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">PWD Status</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.pwd_status === 'Yes' ? '<span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;"><i class="fas fa-wheelchair"></i> Yes</span>' : 'No'}</div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                <h6 style="color: #1e293b; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-address-book"></i> Contact Information
                </h6>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Email</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.email || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Mobile</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.mobile || 'N/A'}</div>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Address</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.address || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">City</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.city || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">State</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.state || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Pincode</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.pincode || 'N/A'}</div>
                    </div>
                </div>
            </div>

            <!-- Course Information -->
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                <h6 style="color: #1e293b; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-graduation-cap"></i> Course & Academic Information
                </h6>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Course</label>
                        <div style="font-size: 14px; color: #1e293b; font-weight: 500;">${student.course || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Status</label>
                        <div style="font-size: 14px; color: #1e293b;">
                            <span style="background: ${student.status === 'Approved' ? '#10b981' : '#f59e0b'}; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                ${student.status || 'Pending'}
                            </span>
                        </div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">College Name</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.college_name || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Training Center</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.training_center || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Registration Date</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.created_at_formatted || 'N/A'}</div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">UTR Number</label>
                        <div style="font-size: 14px; color: #1e293b;">${student.utr_number || 'N/A'}</div>
                    </div>
                </div>
            </div>

            ${educationHTML}
            
            <!-- Documents Section -->
            <div style="margin-top: 24px;">
                <h6 style="color: #1e293b; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-folder-open"></i> Uploaded Documents
                </h6>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <!-- Passport Photo -->
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 8px; text-transform: uppercase;">
                            <i class="fas fa-image"></i> Passport Photo
                        </div>
                        ${student.passport_photo ? 
                            `<img src="../${student.passport_photo}" style="max-width: 100%; max-height: 120px; border-radius: 4px; margin-bottom: 8px;" alt="Passport Photo">
                            <div><a href="../${student.passport_photo}" target="_blank" style="font-size: 12px; color: #2563eb;"><i class="fas fa-eye"></i> View</a></div>` : 
                            '<div style="color: #94a3b8; font-size: 12px;"><i class="fas fa-times-circle"></i> Not Uploaded</div>'}
                    </div>
                    
                    <!-- Signature -->
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 8px; text-transform: uppercase;">
                            <i class="fas fa-signature"></i> Signature
                        </div>
                        ${student.signature ? 
                            `<img src="../${student.signature}" style="max-width: 100%; max-height: 120px; border-radius: 4px; margin-bottom: 8px;" alt="Signature">
                            <div><a href="../${student.signature}" target="_blank" style="font-size: 12px; color: #2563eb;"><i class="fas fa-eye"></i> View</a></div>` : 
                            '<div style="color: #94a3b8; font-size: 12px;"><i class="fas fa-times-circle"></i> Not Uploaded</div>'}
                    </div>
                    
                    <!-- Aadhar Card -->
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 8px; text-transform: uppercase;">
                            <i class="fas fa-id-card"></i> Aadhar Card
                        </div>
                        ${student.aadhar_card_doc ? 
                            `<div style="padding: 20px;"><i class="fas fa-file-pdf" style="font-size: 40px; color: #dc3545;"></i></div>
                            <div><a href="../${student.aadhar_card_doc}" target="_blank" style="font-size: 12px; color: #2563eb;"><i class="fas fa-eye"></i> View</a></div>` : 
                            '<div style="color: #94a3b8; font-size: 12px;"><i class="fas fa-times-circle"></i> Not Uploaded</div>'}
                    </div>
                    
                    <!-- 10th Marksheet -->
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 8px; text-transform: uppercase;">
                            <i class="fas fa-certificate"></i> 10th Marksheet
                        </div>
                        ${student.tenth_marksheet_doc ? 
                            `<div style="padding: 20px;"><i class="fas fa-file-pdf" style="font-size: 40px; color: #dc3545;"></i></div>
                            <div><a href="../${student.tenth_marksheet_doc}" target="_blank" style="font-size: 12px; color: #2563eb;"><i class="fas fa-eye"></i> View</a></div>` : 
                            '<div style="color: #94a3b8; font-size: 12px;"><i class="fas fa-times-circle"></i> Not Uploaded</div>'}
                    </div>
                    
                    <!-- 12th Marksheet -->
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 8px; text-transform: uppercase;">
                            <i class="fas fa-certificate"></i> 12th/Diploma
                        </div>
                        ${student.twelfth_marksheet_doc ? 
                            `<div style="padding: 20px;"><i class="fas fa-file-pdf" style="font-size: 40px; color: #dc3545;"></i></div>
                            <div><a href="../${student.twelfth_marksheet_doc}" target="_blank" style="font-size: 12px; color: #2563eb;"><i class="fas fa-eye"></i> View</a></div>` : 
                            '<div style="color: #94a3b8; font-size: 12px;"><i class="fas fa-times-circle"></i> Not Uploaded</div>'}
                    </div>
                    
                    <!-- Graduation Certificate -->
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 8px; text-transform: uppercase;">
                            <i class="fas fa-user-graduate"></i> Graduation
                        </div>
                        ${student.graduation_certificate_doc ? 
                            `<div style="padding: 20px;"><i class="fas fa-file-pdf" style="font-size: 40px; color: #dc3545;"></i></div>
                            <div><a href="../${student.graduation_certificate_doc}" target="_blank" style="font-size: 12px; color: #2563eb;"><i class="fas fa-eye"></i> View</a></div>` : 
                            '<div style="color: #94a3b8; font-size: 12px;"><i class="fas fa-times-circle"></i> Not Uploaded</div>'}
                    </div>
                    
                    <!-- Caste Certificate -->
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 8px; text-transform: uppercase;">
                            <i class="fas fa-file-alt"></i> Caste Certificate
                        </div>
                        ${student.caste_certificate_doc ? 
                            `<div style="padding: 20px;"><i class="fas fa-file-pdf" style="font-size: 40px; color: #dc3545;"></i></div>
                            <div><a href="../${student.caste_certificate_doc}" target="_blank" style="font-size: 12px; color: #2563eb;"><i class="fas fa-eye"></i> View</a></div>` : 
                            '<div style="color: #94a3b8; font-size: 12px;"><i class="fas fa-times-circle"></i> Optional</div>'}
                    </div>
                    
                    <!-- Payment Receipt -->
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 8px; text-transform: uppercase;">
                            <i class="fas fa-receipt"></i> Payment Receipt
                        </div>
                        ${student.payment_receipt ? 
                            `<div style="padding: 20px;"><i class="fas fa-file-pdf" style="font-size: 40px; color: #dc3545;"></i></div>
                            <div><a href="../${student.payment_receipt}" target="_blank" style="font-size: 12px; color: #2563eb;"><i class="fas fa-eye"></i> View</a></div>` : 
                            '<div style="color: #94a3b8; font-size: 12px;"><i class="fas fa-times-circle"></i> Optional</div>'}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function closeStudentModal() {
    document.getElementById('studentDetailsModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('studentDetailsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeStudentModal();
    }
});
</script>

</body>
</html>
