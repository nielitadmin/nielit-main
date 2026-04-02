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
                
                // Get current admin ID for created_by field
                $admin_id = $_SESSION['admin_id'] ?? null;
                if (!$admin_id && isset($_SESSION['admin'])) {
                    $admin_username = $_SESSION['admin'];
                    $admin_query = "SELECT id FROM admin WHERE username = ?";
                    $admin_stmt = $conn->prepare($admin_query);
                    $admin_stmt->bind_param("s", $admin_username);
                    $admin_stmt->execute();
                    $admin_result = $admin_stmt->get_result();
                    if ($admin_row = $admin_result->fetch_assoc()) {
                        $admin_id = $admin_row['id'];
                        $_SESSION['admin_id'] = $admin_id;
                    }
                }
                
                $data = [
                    'course_id' => $_POST['course_id'],
                    'batch_name' => $_POST['batch_name'],
                    'batch_code' => $batch_code,
                    'start_date' => $_POST['start_date'],
                    'end_date' => $_POST['end_date'],
                    'training_fees' => $_POST['training_fees'],
                    'seats_total' => $_POST['seats_total'],
                    'batch_coordinator' => $_POST['batch_coordinator'],
                    'status' => $_POST['status'],
                    'created_by' => $admin_id
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

// Handle lock/unlock actions (Master Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lock_action'])) {
    $is_master_admin = ($_SESSION['admin_role'] === 'master_admin');
    $current_admin_id = $_SESSION['admin_id'] ?? null;
    
    if (!$current_admin_id && isset($_SESSION['admin'])) {
        $admin_username = $_SESSION['admin'];
        $admin_query = "SELECT id FROM admin WHERE username = ?";
        $admin_stmt = $conn->prepare($admin_query);
        $admin_stmt->bind_param("s", $admin_username);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        if ($admin_row = $admin_result->fetch_assoc()) {
            $current_admin_id = $admin_row['id'];
            $_SESSION['admin_id'] = $current_admin_id;
        }
    }
    
    if ($is_master_admin && $current_admin_id) {
        $batch_id = $_POST['batch_id'];
        $action = $_POST['lock_action'];
        
        if ($action === 'lock') {
            $result = lockBatch($batch_id, $current_admin_id, $conn);
        } elseif ($action === 'unlock') {
            $result = unlockBatch($batch_id, $current_admin_id, $conn);
        }
        
        if (isset($result)) {
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'danger';
        }
    } else {
        $message = "Access denied. Only Master Admins can lock/unlock batches.";
        $message_type = 'danger';
    }
}

// Resolve role and admin ID FIRST (needed for both courses and batches queries)
$is_master_admin = ($_SESSION['admin_role'] === 'master_admin');
$current_admin_id = $_SESSION['admin_id'] ?? null;

// Get current admin ID from DB if not in session
if (!$current_admin_id && isset($_SESSION['admin'])) {
    $admin_username = $_SESSION['admin'];
    $admin_query = "SELECT id FROM admin WHERE username = ?";
    $admin_stmt = $conn->prepare($admin_query);
    $admin_stmt->bind_param("s", $admin_username);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    if ($admin_row = $admin_result->fetch_assoc()) {
        $current_admin_id = $admin_row['id'];
        $_SESSION['admin_id'] = $current_admin_id;
    }
}

// Get courses for dropdown - filtered by assignment for non-master-admins
if ($is_master_admin) {
    // Master admin sees all courses
    $courses_sql = "SELECT id, course_name, course_code FROM courses ORDER BY course_name";
    $courses_result = $conn->query($courses_sql);
} else {
    // Course coordinators only see their assigned courses
    $courses_sql = "SELECT c.id, c.course_name, c.course_code 
                    FROM courses c
                    INNER JOIN admin_course_assignments aca ON c.id = aca.course_id
                    WHERE aca.admin_id = ? AND aca.is_active = 1
                    ORDER BY c.course_name";
    $courses_stmt = $conn->prepare($courses_sql);
    
    if ($courses_stmt === false) {
        // Fallback: show all courses if assignments table doesn't exist
        $courses_result = $conn->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name");
    } else {
        $courses_stmt->bind_param("i", $current_admin_id);
        $courses_stmt->execute();
        $courses_result = $courses_stmt->get_result();
    }
}
$courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}

// (role and admin ID already resolved above)

// Build batch query with role-based filtering
if ($is_master_admin) {
    // Master admin sees all batches
    $batches_sql = "SELECT b.*, c.course_name, c.course_code,
                    (SELECT COUNT(*) FROM students WHERE batch_id = b.id) as enrolled_count,
                    CASE WHEN b.is_locked = 1 THEN 1 ELSE 0 END as is_locked
                    FROM batches b 
                    LEFT JOIN courses c ON b.course_id = c.id 
                    ORDER BY b.created_at DESC";
    $batches_result = $conn->query($batches_sql);
} else {
    // Course coordinators see only batches they created
    $batches_sql = "SELECT b.*, c.course_name, c.course_code,
                    (SELECT COUNT(*) FROM students WHERE batch_id = b.id) as enrolled_count,
                    CASE WHEN b.is_locked = 1 THEN 1 ELSE 0 END as is_locked
                    FROM batches b 
                    LEFT JOIN courses c ON b.course_id = c.id 
                    WHERE b.created_by = ?
                    ORDER BY b.created_at DESC";
    $stmt = $conn->prepare($batches_sql);
    $stmt->bind_param("i", $current_admin_id);
    $stmt->execute();
    $batches_result = $stmt->get_result();
}

// If the query fails (is_locked column doesn't exist), try without it
if (!$batches_result) {
    if ($is_master_admin) {
        $batches_sql = "SELECT b.*, c.course_name, c.course_code,
                        (SELECT COUNT(*) FROM students WHERE batch_id = b.id) as enrolled_count,
                        0 as is_locked
                        FROM batches b 
                        LEFT JOIN courses c ON b.course_id = c.id 
                        ORDER BY b.created_at DESC";
        $batches_result = $conn->query($batches_sql);
    } else {
        $batches_sql = "SELECT b.*, c.course_name, c.course_code,
                        (SELECT COUNT(*) FROM students WHERE batch_id = b.id) as enrolled_count,
                        0 as is_locked
                        FROM batches b 
                        LEFT JOIN courses c ON b.course_id = c.id 
                        WHERE b.created_by = ?
                        ORDER BY b.created_at DESC";
        $stmt = $conn->prepare($batches_sql);
        $stmt->bind_param("i", $current_admin_id);
        $stmt->execute();
        $batches_result = $stmt->get_result();
    }
}

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
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
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
                <h4><i class="fas fa-layer-group"></i> 
                    <?php if ($is_master_admin): ?>
                        Batch Management
                    <?php else: ?>
                        My Batches
                    <?php endif; ?>
                </h4>
                <small>
                    <?php if ($is_master_admin): ?>
                        Create and manage all course batches
                    <?php else: ?>
                        Create and manage your course batches
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
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Role-based Information Banner -->
            <?php if (!$is_master_admin): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Course Coordinator View:</strong> You can only see and manage batches that you created. 
                    The course dropdown shows only your assigned courses. Master Admins can see and manage all batches.
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
                            <?php if (!$is_master_admin && empty($courses)): ?>
                                <small class="text-danger"><i class="fas fa-exclamation-circle"></i> No courses assigned to you yet. Contact Master Admin to assign courses.</small>
                            <?php endif; ?>
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
                        <i class="fas fa-list"></i> 
                        <?php if ($is_master_admin): ?>
                            All Batches
                        <?php else: ?>
                            My Batches
                        <?php endif; ?>
                        <span class="badge badge-primary"><?php echo count($batches); ?></span>
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
                                    <th>Lock Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batches as $batch): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($batch['batch_code']); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($batch['batch_name']); ?>
                                            <?php if ($batch['is_locked']): ?>
                                                <br><small class="text-muted"><i class="fas fa-lock"></i> Locked</small>
                                            <?php endif; ?>
                                        </td>
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
                                            <?php if ($batch['is_locked']): ?>
                                                <span class="badge badge-danger">
                                                    <i class="fas fa-lock"></i> LOCKED
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-unlock"></i> UNLOCKED
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="batch_details.php?id=<?php echo $batch['id']; ?>" class="btn btn-primary btn-sm" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($batch['is_locked']): ?>
                                                <!-- Locked batch actions -->
                                                <?php if ($is_master_admin): ?>
                                                    <!-- Master Admin can unlock -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="batch_id" value="<?php echo $batch['id']; ?>">
                                                        <input type="hidden" name="lock_action" value="unlock">
                                                        <button type="submit" class="btn btn-success btn-sm" title="Unlock Batch" onclick="return confirm('Are you sure you want to unlock this batch? This will allow modifications.')">
                                                            <i class="fas fa-unlock"></i>
                                                        </button>
                                                    </form>
                                                    <button class="btn btn-secondary btn-sm" disabled title="Cannot edit locked batch">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-secondary btn-sm" disabled title="Cannot delete locked batch">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Course Coordinator cannot unlock -->
                                                    <button class="btn btn-secondary btn-sm" disabled title="Batch is locked - only Master Admin can unlock">
                                                        <i class="fas fa-lock"></i>
                                                    </button>
                                                    <button class="btn btn-secondary btn-sm" disabled title="Cannot edit locked batch">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-secondary btn-sm" disabled title="Cannot delete locked batch">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- Unlocked batch actions -->
                                                <?php if ($is_master_admin): ?>
                                                    <!-- Master Admin can lock -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="batch_id" value="<?php echo $batch['id']; ?>">
                                                        <input type="hidden" name="lock_action" value="lock">
                                                        <button type="submit" class="btn btn-warning btn-sm" title="Lock Batch" onclick="return confirm('Are you sure you want to lock this batch? This will prevent all modifications.')">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <a href="edit_batch.php?id=<?php echo $batch['id']; ?>" class="btn btn-info btn-sm" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="javascript:void(0);" 
                                                   class="btn btn-danger btn-sm delete-batch-btn" 
                                                   title="Delete Batch"
                                                   data-batch-id="<?php echo $batch['id']; ?>"
                                                   data-batch-name="<?php echo htmlspecialchars($batch['batch_name']); ?>"
                                                   data-batch-code="<?php echo htmlspecialchars($batch['batch_code']); ?>"
                                                   data-url="?delete=<?php echo $batch['id']; ?>">
                                                    <i class="fas fa-trash"></i>
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
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.3;"></i>
                        <?php if ($is_master_admin): ?>
                            <p style="margin: 0; font-size: 16px;">No batches found. Create your first batch above.</p>
                        <?php else: ?>
                            <p style="margin: 0; font-size: 16px;">You haven't created any batches yet. Create your first batch above.</p>
                            <p style="margin: 8px 0 0 0; font-size: 14px; opacity: 0.7;">You can only see batches that you created.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
// Show toast notification if there's a session message
<?php if (!empty($message)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const messageType = '<?php echo $message_type; ?>';
        const message = '<?php echo addslashes($message); ?>';
        
        // Map message types to toast types
        const toastType = messageType === 'danger' ? 'error' : messageType;
        
        toast[toastType](message);
    });
<?php endif; ?>

// Handle delete batch buttons with modern confirmation
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-batch-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            const batchName = this.getAttribute('data-batch-name');
            const batchCode = this.getAttribute('data-batch-code');
            const batchId = this.getAttribute('data-batch-id');
            const url = this.getAttribute('data-url');
            
            const confirmed = await showConfirm({
                title: 'Delete Batch',
                message: `Are you sure you want to delete batch <strong>${batchName}</strong> (${batchCode})? This action cannot be undone and will affect all students assigned to this batch.`,
                confirmText: 'Delete',
                cancelText: 'Cancel',
                type: 'danger'
            });
            
            if (confirmed) {
                // Show loading toast
                const loadingToast = toast.loading('Deleting batch...');
                
                // Redirect to delete URL
                window.location.href = url;
            }
        });
    });
});
</script>

</body>
</html>
