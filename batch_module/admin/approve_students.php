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
                <a href="../../admin/manage_courses.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_batches.php" class="nav-link">
                    <i class="fas fa-layer-group"></i> Batches
                </a>
            </div>
            <div class="nav-item">
                <a href="approve_students.php" class="nav-link active">
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
                <h4><i class="fas fa-user-check"></i> Approve Students</h4>
                <small>Review and approve student registrations</small>
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
                                
                                <a href="../../admin/edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
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

</body>
</html>
