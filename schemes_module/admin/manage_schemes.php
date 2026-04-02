<?php
// Start session and include the database connection
session_start();
require_once __DIR__ . '/../../config/config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: ../login_new.php");
    exit();
}

// Auto-create schemes tables if they don't exist
$conn->query("CREATE TABLE IF NOT EXISTS `schemes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scheme_name` varchar(255) NOT NULL,
  `scheme_code` varchar(50) NOT NULL,
  `description` text,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scheme_code` (`scheme_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `course_schemes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `scheme_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_scheme_unique` (`course_id`, `scheme_id`),
  KEY `course_id` (`course_id`),
  KEY `scheme_id` (`scheme_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle adding a new scheme
if (isset($_POST['add_scheme'])) {
    $scheme_name = trim($_POST['scheme_name']);
    $scheme_code = trim($_POST['scheme_code']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    
    // Check if scheme code already exists
    $check_sql = "SELECT id FROM schemes WHERE scheme_code = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $scheme_code);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['message'] = "Scheme code '{$scheme_code}' already exists. Please use a different code.";
        $_SESSION['message_type'] = "danger";
    } else {
        $insert_sql = "INSERT INTO schemes (scheme_name, scheme_code, description, status) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssss", $scheme_name, $scheme_code, $description, $status);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Scheme added successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding scheme: " . $conn->error;
            $_SESSION['message_type'] = "danger";
        }
    }
    
    header("Location: manage_schemes.php");
    exit();
}

// Handle deleting a scheme
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    $delete_sql = "DELETE FROM schemes WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Scheme deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting scheme: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: manage_schemes.php");
    exit();
}

// Fetch all schemes
$schemes_query = "SELECT s.*, 
                  (SELECT COUNT(*) FROM course_schemes WHERE scheme_id = s.id) as course_count
                  FROM schemes s 
                  ORDER BY s.created_at DESC";
$schemes_result = $conn->query($schemes_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schemes - NIELIT Bhubaneswar</title>
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
                <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/students.php" class="nav-link">
                    <i class="fas fa-users"></i> Students
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/manage_courses.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/manage_batches.php" class="nav-link">
                    <i class="fas fa-layer-group"></i> Batches
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_schemes.php" class="nav-link active">
                    <i class="fas fa-project-diagram"></i> Schemes/Projects
                </a>
            </div>
            
            <div class="nav-divider"></div>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/index.php" class="nav-link">
                    <i class="fas fa-globe"></i> View Website
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/logout.php" class="nav-link">
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
                <h4><i class="fas fa-project-diagram"></i> Manage Schemes/Projects</h4>
                <small>Create and manage government schemes and projects</small>
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
            <!-- Add Scheme Button -->
            <div style="margin-bottom: 20px;">
                <button type="button" class="btn btn-primary" onclick="openAddSchemeModal()">
                    <i class="fas fa-plus"></i> Add New Scheme
                </button>
            </div>

            <!-- Schemes Table -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list"></i> All Schemes
                    </h5>
                </div>
                
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Sl. No.</th>
                                <th>Scheme Code</th>
                                <th>Scheme Name</th>
                                <th>Description</th>
                                <th>Courses</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sl_no = 1;
                            if ($schemes_result && $schemes_result->num_rows > 0):
                                while ($scheme = $schemes_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $sl_no++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($scheme['scheme_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($scheme['scheme_name']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($scheme['description'], 0, 80)) . (strlen($scheme['description']) > 80 ? '...' : ''); ?></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <i class="fas fa-book"></i> <?php echo $scheme['course_count']; ?> Courses
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($scheme['status'] == 'Active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($scheme['created_at'])); ?></td>
                                    <td>
                                        <a href="edit_scheme.php?id=<?php echo $scheme['id']; ?>" class="btn btn-warning btn-sm" title="Edit Scheme">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0);" 
                                           class="btn btn-danger btn-sm delete-scheme-btn" 
                                           title="Delete Scheme"
                                           data-scheme-id="<?php echo $scheme['id']; ?>"
                                           data-scheme-name="<?php echo htmlspecialchars($scheme['scheme_name']); ?>"
                                           data-course-count="<?php echo $scheme['course_count']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-inbox" style="font-size: 48px; color: #cbd5e0; margin-bottom: 16px;"></i>
                                        <p style="color: #64748b;">No schemes found. Click "Add New Scheme" to create one.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Scheme Modal -->
<div id="addSchemeModal" class="batch-modal">
    <div class="batch-modal-content">
        <div class="batch-modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Scheme</h3>
            <button class="close-modal" onclick="closeAddSchemeModal()">&times;</button>
        </div>
        
        <form method="POST" action="manage_schemes.php">
            <div class="form-group">
                <label class="form-label">Scheme Name <span style="color: red;">*</span></label>
                <input type="text" name="scheme_name" class="form-control" required placeholder="e.g., Special Component Plan for Scheduled Castes">
            </div>
            
            <div class="form-group">
                <label class="form-label">Scheme Code <span style="color: red;">*</span></label>
                <input type="text" name="scheme_code" class="form-control" required placeholder="e.g., SCSP">
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the scheme"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="add_scheme" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-check"></i> Add Scheme
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeAddSchemeModal()" style="flex: 1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
// Show toast notification if there's a session message
<?php if (isset($_SESSION['message'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const messageType = '<?php echo isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'success'; ?>';
        const message = '<?php echo addslashes($_SESSION['message']); ?>';
        const toastType = messageType === 'danger' ? 'error' : messageType;
        toast[toastType](message);
    });
    <?php 
    unset($_SESSION['message']); 
    unset($_SESSION['message_type']);
    ?>
<?php endif; ?>

function openAddSchemeModal() {
    document.getElementById('addSchemeModal').style.display = 'block';
}

function closeAddSchemeModal() {
    document.getElementById('addSchemeModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('addSchemeModal');
    if (event.target === modal) {
        closeAddSchemeModal();
    }
}

// Handle delete scheme buttons
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-scheme-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', async function() {
            const schemeId = this.getAttribute('data-scheme-id');
            const schemeName = this.getAttribute('data-scheme-name');
            const courseCount = this.getAttribute('data-course-count');
            
            let message = `Are you sure you want to delete the scheme <strong>${schemeName}</strong>?`;
            if (courseCount > 0) {
                message += `<br><br><span style="color: #f59e0b;">⚠️ This scheme is linked to ${courseCount} course(s). Deleting it will remove these associations.</span>`;
            }
            
            const confirmed = await showConfirm({
                title: 'Delete Scheme',
                message: message,
                confirmText: 'Delete',
                cancelText: 'Cancel',
                type: 'danger'
            });
            
            if (confirmed) {
                window.location.href = `manage_schemes.php?delete_id=${schemeId}`;
            }
        });
    });
});
</script>

<style>
.batch-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.batch-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 30px;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}
.batch-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e2e8f0;
}
.batch-modal-header h3 {
    margin: 0;
    color: #1e293b;
}
.close-modal {
    font-size: 28px;
    font-weight: bold;
    color: #64748b;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.close-modal:hover {
    color: #e74c3c;
}
</style>

</body>
</html>
<?php
$conn->close();
?>
