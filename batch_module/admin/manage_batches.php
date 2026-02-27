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

// Handle batch actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_batch':
                // Get course code for batch code generation
                $course_sql = "SELECT course_code FROM courses WHERE id = ?";
                $stmt = $conn->prepare($course_sql);
                $stmt->bind_param("i", $_POST['course_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $course = $result->fetch_assoc();
                $stmt->close();
                
                $batch_code = generateBatchCode($course['course_code'], $conn);
                
                $data = [
                    'course_id' => $_POST['course_id'],
                    'batch_name' => $_POST['batch_name'],
                    'batch_code' => $batch_code,
                    'start_date' => $_POST['start_date'],
                    'end_date' => $_POST['end_date'],
                    'training_fees' => $_POST['training_fees'],
                    'seats_total' => $_POST['seats_total'],
                    'batch_coordinator' => $_POST['batch_coordinator'],
                    'status' => $_POST['status']
                ];
                
                $result = createBatch($data, $conn);
                if ($result) {
                    $message = "Batch created successfully! Batch Code: " . $batch_code;
                    $message_type = 'success';
                } else {
                    $message = "Error creating batch";
                    $message_type = 'danger';
                }
                break;
                
            case 'update_batch':
                $data = [
                    'batch_name' => $_POST['batch_name'],
                    'start_date' => $_POST['start_date'],
                    'end_date' => $_POST['end_date'],
                    'training_fees' => $_POST['training_fees'],
                    'seats_total' => $_POST['seats_total'],
                    'batch_coordinator' => $_POST['batch_coordinator'],
                    'status' => $_POST['status']
                ];
                
                $result = updateBatch($_POST['batch_id'], $data, $conn);
                $message = $result ? "Batch updated successfully" : "Error updating batch";
                $message_type = $result ? 'success' : 'danger';
                break;
        }
    }
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $result = deleteBatch($_GET['delete'], $conn);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'danger';
}

// Get all courses for dropdown
$courses_sql = "SELECT id, course_name, course_code FROM courses ORDER BY course_name";
$courses_result = $conn->query($courses_sql);
$courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}

// Get all batches
$batches_sql = "SELECT b.*, c.course_name, c.course_code,
                (SELECT COUNT(*) FROM students WHERE batch_id = b.id) as enrolled_count
                FROM batches b 
                LEFT JOIN courses c ON b.course_id = c.id 
                ORDER BY b.created_at DESC";
$batches_result = $conn->query($batches_sql);

// Check if query failed (tables don't exist)
if (!$batches_result) {
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
        <p style="color: #7f8c8d;">Database Error: ' . htmlspecialchars($conn->error) . '</p>
        <a href="../../admin/dashboard.php" style="display: inline-block; margin-top: 20px; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a>
    </div>');
}

$batches = [];
while ($row = $batches_result->fetch_assoc()) {
    $batches[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Batches - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
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
                <h4><i class="fas fa-layer-group"></i> Batch Management</h4>
                <small>Create and manage course batches</small>
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

            <!-- Create New Batch -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-plus-circle"></i> Create New Batch
                    </h5>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_batch">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Course *</label>
                            <select class="form-control" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_name']); ?> (<?php echo $course['course_code']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Batch Name *</label>
                            <input type="text" class="form-control" name="batch_name" placeholder="e.g., DBC Batch 25" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Training Fees (₹) *</label>
                            <input type="number" step="0.01" class="form-control" name="training_fees" placeholder="15000.00" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Total Seats *</label>
                            <input type="number" class="form-control" name="seats_total" placeholder="30" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Batch Coordinator *</label>
                            <input type="text" class="form-control" name="batch_coordinator" placeholder="Dr. Kumar Singh" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status *</label>
                            <select class="form-control" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-top: 16px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Create Batch
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Batches -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list"></i> All Batches
                    </h5>
                </div>
                
                <?php if (!empty($batches)): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Batch Code</th>
                                    <th>Batch Name</th>
                                    <th>Course</th>
                                    <th>Duration</th>
                                    <th>Seats</th>
                                    <th>Fees</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batches as $batch): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($batch['batch_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                                        <td><?php echo htmlspecialchars($batch['course_name']); ?></td>
                                        <td>
                                            <?php echo date('d M Y', strtotime($batch['start_date'])); ?><br>
                                            <small class="text-muted">to <?php echo date('d M Y', strtotime($batch['end_date'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo $batch['enrolled_count']; ?> / <?php echo $batch['seats_total']; ?>
                                            </span>
                                        </td>
                                        <td>₹<?php echo number_format($batch['training_fees'], 2); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $batch['status'] === 'Active' ? 'success' : 
                                                    ($batch['status'] === 'Completed' ? 'secondary' : 'danger'); 
                                            ?>">
                                                <?php echo $batch['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="batch_details.php?id=<?php echo $batch['id']; ?>" class="btn btn-primary btn-sm" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_batch.php?id=<?php echo $batch['id']; ?>" class="btn btn-warning btn-sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $batch['id']; ?>" class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Are you sure you want to delete this batch?');" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.3;"></i>
                        <p style="margin: 0; font-size: 16px;">No batches found. Create your first batch above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

</body>
</html>
