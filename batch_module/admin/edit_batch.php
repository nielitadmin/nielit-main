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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

// Get all schemes for dropdown
$schemes_sql = "SELECT id, scheme_name, scheme_code FROM schemes WHERE status = 'Active' ORDER BY scheme_name";
$schemes_result = $conn->query($schemes_sql);
$schemes = [];
while ($row = $schemes_result->fetch_assoc()) {
    $schemes[] = $row;
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
                    </div>
                </div>
                
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

                <form method="POST" action="">
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
                                <small class="text-muted">Training center location</small>
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
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Batch
                        </button>
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

</body>
</html>
