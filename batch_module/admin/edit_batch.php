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
$batch_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($batch_id === 0) {
    header("Location: manage_batches.php");
    exit();
}

// Get current admin info
$admin_id = $_SESSION['admin_id'] ?? null;
$is_master_admin = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'master_admin';

// Get admin ID if not in session
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

// Handle lock/unlock actions
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'lock') {
        $result = lockBatch($batch_id, $admin_id, $conn);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'danger';
    } elseif ($_GET['action'] === 'unlock' && $is_master_admin) {
        $result = unlockBatch($batch_id, $admin_id, $conn);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'danger';
    }
}

// Check if batch is locked
$is_locked = isBatchLocked($batch_id, $conn);
$lock_info = getBatchLockInfo($batch_id, $conn);

// Handle form submission (only if batch is not locked)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
    $data = [
        'batch_name' => $_POST['batch_name'],
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'training_fees' => $_POST['training_fees'],
        'seats_total' => $_POST['seats_total'],
        'batch_coordinator' => $_POST['batch_coordinator'],
        'status' => $_POST['status'],
        'scheme_id' => !empty($_POST['scheme_id']) ? $_POST['scheme_id'] : null,
        'admission_order_ref' => $_POST['admission_order_ref'] ?? null,
        'admission_order_date' => $_POST['admission_order_date'] ?? null,
        'examination_month' => $_POST['examination_month'] ?? null,
        'class_time' => $_POST['class_time'] ?? '9:00 AM to 1:30 PM',
        'copy_to_list' => $_POST['copy_to_list'] ?? null,
        'location' => $_POST['location'] ?? 'NIELIT Bhubaneswar'
    ];
    
    $result = updateBatch($batch_id, $data, $conn);
    if ($result) {
        $message = "Batch updated successfully!";
        $message_type = 'success';
    } else {
        $message = "Error updating batch";
        $message_type = 'danger';
    }
}

// Get batch details
$batch_sql = "SELECT b.*, c.course_name, c.course_code, s.scheme_name, s.scheme_code
              FROM batches b 
              LEFT JOIN courses c ON b.course_id = c.id 
              LEFT JOIN schemes s ON b.scheme_id = s.id
              WHERE b.id = ?";
$stmt = $conn->prepare($batch_sql);

if (!$stmt) {
    // If schemes table doesn't exist, try without it
    $batch_sql = "SELECT b.*, c.course_name, c.course_code, NULL as scheme_name, NULL as scheme_code
                  FROM batches b 
                  LEFT JOIN courses c ON b.course_id = c.id 
                  WHERE b.id = ?";
    $stmt = $conn->prepare($batch_sql);
    
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }
}

$stmt->bind_param("i", $batch_id);
$stmt->execute();
$result = $stmt->get_result();
$batch = $result->fetch_assoc();
$stmt->close();

if (!$batch) {
    header("Location: manage_batches.php");
    exit();
}

// Get all courses for dropdown
$courses_sql = "SELECT id, course_name, course_code FROM courses ORDER BY course_name";
$courses_result = $conn->query($courses_sql);
$courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}

// Get all schemes for dropdown (check if table exists first)
$schemes = [];
$table_check = $conn->query("SHOW TABLES LIKE 'schemes'");
if ($table_check && $table_check->num_rows > 0) {
    $schemes_sql = "SELECT id, scheme_name, scheme_code FROM schemes WHERE status = 'Active' ORDER BY scheme_name";
    $schemes_result = $conn->query($schemes_sql);
    if ($schemes_result) {
        while ($row = $schemes_result->fetch_assoc()) {
            $schemes[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Batch - NIELIT Bhubaneswar</title>
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
                <h4><i class="fas fa-edit"></i> Edit Batch</h4>
                <small>Update batch information</small>
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

            <!-- Batch Info Card -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-info-circle"></i> Batch Information
                    </h5>
                    <div>
                        <span class="badge badge-primary"><?php echo htmlspecialchars($batch['batch_code']); ?></span>
                        <span class="badge badge-<?php 
                            echo $batch['status'] === 'Active' ? 'success' : 
                                ($batch['status'] === 'Completed' ? 'secondary' : 'danger'); 
                        ?>">
                            <?php echo $batch['status']; ?>
                        </span>
                        <?php if ($is_locked): ?>
                            <span class="badge badge-danger">
                                <i class="fas fa-lock"></i> LOCKED
                            </span>
                        <?php else: ?>
                            <span class="badge badge-success">
                                <i class="fas fa-unlock"></i> UNLOCKED
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($is_locked): ?>
                <!-- Lock Warning -->
                <div style="background: linear-gradient(135deg, #fee2e2 0%, #fef3c7 100%); border: 1px solid #f87171; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                        <div style="color: #dc2626; font-size: 1.5rem;">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div>
                            <h6 style="margin: 0; color: #dc2626; font-weight: 600;">Batch is Locked</h6>
                            <p style="margin: 4px 0 0 0; color: #7c2d12; font-size: 14px;">
                                This batch has been locked and cannot be modified. All editing features are disabled.
                            </p>
                        </div>
                    </div>
                    <?php if ($lock_info && $lock_info['locked_at']): ?>
                    <div style="background: rgba(255,255,255,0.7); padding: 10px; border-radius: 4px; font-size: 12px; color: #7c2d12;">
                        <strong>Locked:</strong> <?php echo date('M d, Y \a\t g:i A', strtotime($lock_info['locked_at'])); ?>
                        <?php if ($lock_info['locked_by_username']): ?>
                            by <strong><?php echo htmlspecialchars($lock_info['locked_by_username']); ?></strong>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($is_master_admin): ?>
                    <div style="margin-top: 15px;">
                        <a href="?id=<?php echo $batch_id; ?>&action=unlock" 
                           class="btn btn-warning btn-sm unlock-batch-btn"
                           data-batch-name="<?php echo htmlspecialchars($batch['batch_name']); ?>">
                            <i class="fas fa-unlock"></i> Unlock Batch (Master Admin)
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <!-- Lock Action -->
                <div style="background: linear-gradient(135deg, #e0f2fe 0%, #f3e5f5 100%); border: 1px solid #29b6f6; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="color: #0277bd; font-size: 1.2rem;">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div>
                                <h6 style="margin: 0; color: #0277bd; font-weight: 600;">Batch Security</h6>
                                <p style="margin: 2px 0 0 0; color: #01579b; font-size: 13px;">
                                    Lock this batch to prevent any further modifications
                                </p>
                            </div>
                        </div>
                        <a href="?id=<?php echo $batch_id; ?>&action=lock" 
                           class="btn btn-danger btn-sm lock-batch-btn"
                           data-batch-name="<?php echo htmlspecialchars($batch['batch_name']); ?>">
                            <i class="fas fa-lock"></i> Lock Batch
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="padding: 20px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <strong>Course:</strong> <?php echo htmlspecialchars($batch['course_name']); ?> (<?php echo $batch['course_code']; ?>)
                        </div>
                        <div>
                            <strong>Batch Code:</strong> <?php echo htmlspecialchars($batch['batch_code']); ?>
                        </div>
                    </div>
                </div>

                <form method="POST" action="" <?php echo $is_locked ? 'style="pointer-events: none; opacity: 0.6;"' : ''; ?>>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Batch Name *</label>
                            <input type="text" class="form-control" name="batch_name" 
                                   value="<?php echo htmlspecialchars($batch['batch_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Batch Coordinator *</label>
                            <input type="text" class="form-control" name="batch_coordinator" 
                                   value="<?php echo htmlspecialchars($batch['batch_coordinator']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $batch['start_date']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $batch['end_date']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Training Fees (₹) *</label>
                            <input type="number" step="0.01" class="form-control" name="training_fees" 
                                   value="<?php echo $batch['training_fees']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Total Seats *</label>
                            <input type="number" class="form-control" name="seats_total" 
                                   value="<?php echo $batch['seats_total']; ?>" required>
                            <small class="text-muted">Currently filled: <?php echo $batch['seats_filled']; ?> seats</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status *</label>
                            <select class="form-control" name="status" required>
                                <option value="Active" <?php echo $batch['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Completed" <?php echo $batch['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo $batch['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Scheme/Project</label>
                            <select class="form-control" name="scheme_id">
                                <option value="">-- No Scheme --</option>
                                <?php foreach ($schemes as $scheme): ?>
                                    <option value="<?php echo $scheme['id']; ?>" 
                                            <?php echo ($batch['scheme_id'] == $scheme['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($scheme['scheme_name']) . ' (' . htmlspecialchars($scheme['scheme_code']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Link this batch to a government scheme/project</small>
                        </div>
                    </div>
                    
                    <!-- Admission Order Details Section -->
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid #e9ecef;">
                        <h6 style="margin-bottom: 16px; color: #495057;">
                            <i class="fas fa-file-alt"></i> Admission Order Details
                        </h6>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Reference Number</label>
                                <input type="text" class="form-control" name="admission_order_ref" 
                                       value="<?php echo htmlspecialchars($batch['admission_order_ref'] ?? ''); ?>" 
                                       placeholder="e.g., NIELIT/BBSR/AO/2026/001">
                                <small class="text-muted">Leave blank for auto-generation</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Order Date</label>
                                <input type="date" class="form-control" name="admission_order_date" 
                                       value="<?php echo $batch['admission_order_date'] ?? date('Y-m-d'); ?>">
                                <small class="text-muted">Defaults to today's date</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Location</label>
                                <select class="form-control" name="location">
                                    <option value="NIELIT Bhubaneswar" <?php echo (($batch['location'] ?? 'NIELIT Bhubaneswar') == 'NIELIT Bhubaneswar') ? 'selected' : ''; ?>>NIELIT Bhubaneswar</option>
                                    <option value="NIELIT Balasore" <?php echo (($batch['location'] ?? '') == 'NIELIT Balasore') ? 'selected' : ''; ?>>NIELIT Balasore</option>
                                </select>
                                <small class="text-muted">Training centre location</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Examination Month</label>
                                <input type="text" class="form-control" name="examination_month" 
                                       value="<?php echo htmlspecialchars($batch['examination_month'] ?? ''); ?>" 
                                       placeholder="e.g., March 2026">
                                <small class="text-muted">Leave blank for auto-calculation</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Class Time</label>
                                <input type="text" class="form-control" name="class_time" 
                                       value="<?php echo htmlspecialchars($batch['class_time'] ?? '9:00 AM to 1:30 PM'); ?>" 
                                       placeholder="e.g., 9:00 AM to 1:30 PM">
                                <small class="text-muted">Training session timings</small>
                            </div>
                            
                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label">Copy To (Recipients)</label>
                                <textarea class="form-control" name="copy_to_list" rows="3" 
                                          placeholder="Enter recipients, one per line"><?php echo htmlspecialchars($batch['copy_to_list'] ?? ''); ?></textarea>
                                <small class="text-muted">Leave blank for default recipients</small>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid #e9ecef; display: flex; gap: 12px;">
                        <?php if ($is_locked): ?>
                            <button type="button" class="btn btn-secondary" disabled>
                                <i class="fas fa-lock"></i> Batch is Locked
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Batch
                            </button>
                        <?php endif; ?>
                        <a href="manage_batches.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <a href="batch_details.php?id=<?php echo $batch_id; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </div>
                </form>
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

// Handle lock batch confirmation
document.addEventListener('DOMContentLoaded', function() {
    const lockButton = document.querySelector('.lock-batch-btn');
    if (lockButton) {
        lockButton.addEventListener('click', async function(e) {
            e.preventDefault();
            const batchName = this.getAttribute('data-batch-name');
            const url = this.href;
            
            const confirmed = await showConfirm({
                title: 'Lock Batch',
                message: `Are you sure you want to lock batch <strong>${batchName}</strong>?<br><br>
                         <strong>Warning:</strong> Once locked, this batch cannot be edited by anyone. 
                         Only Master Admins can unlock it. This will prevent:
                         <ul style="text-align: left; margin: 10px 0;">
                            <li>Editing batch information</li>
                            <li>Modifying student assignments</li>
                            <li>Updating admission orders</li>
                            <li>Any other batch modifications</li>
                         </ul>`,
                confirmText: 'Lock Batch',
                cancelText: 'Cancel',
                type: 'danger'
            });
            
            if (confirmed) {
                // Show loading toast
                const loadingToast = toast.loading('Locking batch...');
                
                // Redirect to lock URL
                window.location.href = url;
            }
        });
    }
    
    // Handle unlock batch confirmation
    const unlockButton = document.querySelector('.unlock-batch-btn');
    if (unlockButton) {
        unlockButton.addEventListener('click', async function(e) {
            e.preventDefault();
            const batchName = this.getAttribute('data-batch-name');
            const url = this.href;
            
            const confirmed = await showConfirm({
                title: 'Unlock Batch',
                message: `Are you sure you want to unlock batch <strong>${batchName}</strong>?<br><br>
                         This will allow the batch to be edited again. All modification restrictions will be removed.`,
                confirmText: 'Unlock Batch',
                cancelText: 'Cancel',
                type: 'warning'
            });
            
            if (confirmed) {
                // Show loading toast
                const loadingToast = toast.loading('Unlocking batch...');
                
                // Redirect to unlock URL
                window.location.href = url;
            }
        });
    }
});
</script>

</body>
</html>
