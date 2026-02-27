<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");
    exit();
}

if (!isset($_GET['course_id'])) {
    die("Course ID is missing.");
}

$course_id = $_GET['course_id'];

// Handle delete batch action
if (isset($_GET['delete_batch'])) {
    $batch_id = $_GET['delete_batch'];
    $delete_sql = "DELETE FROM batches WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $batch_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Batch deleted successfully.";
    } else {
        $_SESSION['message'] = "Error deleting batch: " . $stmt->error;
    }
    $stmt->close();

    header("Location: manage_batches.php?course_id=" . $course_id);
    exit();
}

// Handle new batch form submission
if (isset($_POST['add_batch'])) {
    $batch_name = $_POST['batch_name'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $training_fees = $_POST['training_fees'];
    $seats_available = $_POST['seats_available'];
    $batch_coordinator = $_POST['batch_coordinator'];

    $sql = "INSERT INTO batches (course_id, batch_name, start_date, end_date, training_fees, seats_available, batch_coordinator)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssdis", $course_id, $batch_name, $start_date, $end_date, $training_fees, $seats_available, $batch_coordinator);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Batch added successfully!";
    } else {
        $_SESSION['message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch course name
$course_name = '';
$course_sql = "SELECT course_name FROM courses WHERE id = ?";
$stmt = $conn->prepare($course_sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$stmt->bind_result($course_name);
$stmt->fetch();
$stmt->close();

// Fetch all batches for the course
$batches = [];
$batch_sql = "SELECT * FROM batches WHERE course_id = ?";
$stmt = $conn->prepare($batch_sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $batches[] = $row;
}
$stmt->close();
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
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="students.php" class="nav-link">
                    <i class="fas fa-users"></i> Students
                </a>
            </div>
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_batches.php" class="nav-link active">
                    <i class="fas fa-layer-group"></i> Batches
                </a>
            </div>
            <div class="nav-item">
                <a href="add_admin.php" class="nav-link">
                    <i class="fas fa-user-shield"></i> Add Admin
                </a>
            </div>
            <div class="nav-item">
                <a href="reset_password.php" class="nav-link">
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
                <a href="logout.php" class="nav-link">
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
                <h4><i class="fas fa-layer-group"></i> Manage Batches</h4>
                <small>Course: <?= htmlspecialchars($course_name) ?></small>
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
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $_SESSION['message'] ?>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <!-- Existing Batches -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list"></i> Existing Batches
                    </h5>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                
                <?php if (!empty($batches)): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Batch Name</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Fees</th>
                                    <th>Seats</th>
                                    <th>Coordinator</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batches as $batch): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($batch['batch_name']) ?></strong></td>
                                        <td><?= date('d M Y', strtotime($batch['start_date'])) ?></td>
                                        <td><?= date('d M Y', strtotime($batch['end_date'])) ?></td>
                                        <td>₹<?= is_numeric($batch['training_fees']) ? number_format($batch['training_fees']) : htmlspecialchars($batch['training_fees']) ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= htmlspecialchars($batch['seats_available']) ?> seats
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($batch['batch_coordinator']) ?></td>
                                        <td>
                                            <a href="manage_batches.php?course_id=<?= $course_id ?>&delete_batch=<?= $batch['id'] ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Are you sure you want to delete this batch?');">
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
                        <p style="margin: 0; font-size: 16px;">No batches found. Add a new batch below.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add New Batch Form -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-plus"></i> Add New Batch
                    </h5>
                </div>
                
                <form method="POST" action="">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Batch Name *</label>
                            <input type="text" class="form-control" name="batch_name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Batch Coordinator *</label>
                            <input type="text" class="form-control" name="batch_coordinator" required>
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
                            <label class="form-label">Training Fees *</label>
                            <input type="text" class="form-control" name="training_fees" placeholder="e.g., 15000" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Seats Available *</label>
                            <input type="number" class="form-control" name="seats_available" placeholder="e.g., 30" required>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 16px;">
                        <button type="submit" name="add_batch" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Batch
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

</body>
</html>
