<?php
// Start session and include the database connection
session_start();
require_once __DIR__ . '/../../config/config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: ../login_new.php");
    exit();
}

// Get scheme ID
if (!isset($_GET['id'])) {
    header("Location: manage_schemes.php");
    exit();
}

$scheme_id = $_GET['id'];

// Handle updating scheme
if (isset($_POST['update_scheme'])) {
    $scheme_name = trim($_POST['scheme_name']);
    $scheme_code = trim($_POST['scheme_code']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    
    $update_sql = "UPDATE schemes SET scheme_name = ?, scheme_code = ?, description = ?, status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssssi", $scheme_name, $scheme_code, $description, $status, $scheme_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Scheme updated successfully!";
        $_SESSION['message_type'] = "success";
        header("Location: manage_schemes.php");
        exit();
    } else {
        $_SESSION['message'] = "Error updating scheme: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
}

// Handle updating course fees via AJAX
if (isset($_POST['action']) && $_POST['action'] == 'update_course_fees') {
    header('Content-Type: application/json');
    
    $course_id = $_POST['course_id'];
    $new_fees = trim($_POST['fees']);
    
    $update_fees_sql = "UPDATE courses SET training_fees = ? WHERE id = ?";
    $stmt = $conn->prepare($update_fees_sql);
    $stmt->bind_param("si", $new_fees, $course_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Fees updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating fees: ' . $conn->error]);
    }
    exit();
}

// Fetch scheme details
$scheme_query = "SELECT * FROM schemes WHERE id = ?";
$stmt = $conn->prepare($scheme_query);
$stmt->bind_param("i", $scheme_id);
$stmt->execute();
$scheme_result = $stmt->get_result();

if ($scheme_result->num_rows == 0) {
    header("Location: manage_schemes.php");
    exit();
}

$scheme = $scheme_result->fetch_assoc();

// Fetch courses linked to this scheme
$courses_query = "SELECT c.*, c.training_fees as fees, cs.id as link_id 
                  FROM courses c 
                  INNER JOIN course_schemes cs ON c.id = cs.course_id 
                  WHERE cs.scheme_id = ?
                  ORDER BY c.course_name";
$stmt = $conn->prepare($courses_query);
$stmt->bind_param("i", $scheme_id);
$stmt->execute();
$courses_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Scheme - NIELIT Bhubaneswar</title>
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
                <h4><i class="fas fa-edit"></i> Edit Scheme</h4>
                <small>Update scheme details</small>
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
            <div style="margin-bottom: 20px;">
                <a href="manage_schemes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Schemes
                </a>
                <a href="generate_admission_order.php?scheme_id=<?php echo $scheme_id; ?>" class="btn btn-success">
                    <i class="fas fa-file-alt"></i> Generate Admission Order
                </a>
            </div>

            <!-- Edit Scheme Form -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-edit"></i> Scheme Details
                    </h5>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Scheme Name <span style="color: red;">*</span></label>
                        <input type="text" name="scheme_name" class="form-control" required value="<?php echo htmlspecialchars($scheme['scheme_name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Scheme Code <span style="color: red;">*</span></label>
                        <input type="text" name="scheme_code" class="form-control" required value="<?php echo htmlspecialchars($scheme['scheme_code']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($scheme['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="Active" <?php echo $scheme['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $scheme['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="update_scheme" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Scheme
                        </button>
                        <a href="manage_schemes.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Linked Courses -->
            <div class="content-card" style="margin-top: 20px;">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-book"></i> Courses Under This Scheme
                    </h5>
                </div>
                
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Sl. No.</th>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Duration</th>
                                <th>Fees (Editable)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sl_no = 1;
                            if ($courses_result && $courses_result->num_rows > 0):
                                while ($course = $courses_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $sl_no++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['duration']); ?></td>
                                    <td>
                                        <div class="editable-fees-container" data-course-id="<?php echo $course['id']; ?>">
                                            <span class="fees-display">
                                                ₹<?php 
                                                $fees_value = preg_replace('/[^0-9.]/', '', $course['fees']);
                                                echo htmlspecialchars($course['fees']);
                                                ?>
                                            </span>
                                            <input type="text" 
                                                   class="fees-input form-control" 
                                                   value="<?php echo htmlspecialchars($course['fees']); ?>" 
                                                   style="display: none; width: 120px; display: inline-block;">
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary edit-fees-btn" 
                                                data-course-id="<?php echo $course['id']; ?>"
                                                title="Edit Fees">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-success save-fees-btn" 
                                                data-course-id="<?php echo $course['id']; ?>"
                                                style="display: none;"
                                                title="Save Fees">
                                            <i class="fas fa-check"></i> Save
                                        </button>
                                        <button class="btn btn-sm btn-secondary cancel-fees-btn" 
                                                data-course-id="<?php echo $course['id']; ?>"
                                                style="display: none;"
                                                title="Cancel">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-inbox" style="font-size: 48px; color: #cbd5e0; margin-bottom: 16px;"></i>
                                        <p style="color: #64748b;">No courses linked to this scheme yet.</p>
                                        <p style="color: #64748b; font-size: 14px;">You can link courses to this scheme from the "Edit Course" page.</p>
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

// Handle inline fees editing
document.addEventListener('DOMContentLoaded', function() {
    // Edit button click
    document.querySelectorAll('.edit-fees-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const courseId = this.getAttribute('data-course-id');
            const container = document.querySelector(`.editable-fees-container[data-course-id="${courseId}"]`);
            const display = container.querySelector('.fees-display');
            const input = container.querySelector('.fees-input');
            const editBtn = this;
            const saveBtn = document.querySelector(`.save-fees-btn[data-course-id="${courseId}"]`);
            const cancelBtn = document.querySelector(`.cancel-fees-btn[data-course-id="${courseId}"]`);
            
            // Show input, hide display
            display.style.display = 'none';
            input.style.display = 'inline-block';
            input.focus();
            
            // Toggle buttons
            editBtn.style.display = 'none';
            saveBtn.style.display = 'inline-block';
            cancelBtn.style.display = 'inline-block';
        });
    });
    
    // Save button click
    document.querySelectorAll('.save-fees-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const courseId = this.getAttribute('data-course-id');
            const container = document.querySelector(`.editable-fees-container[data-course-id="${courseId}"]`);
            const input = container.querySelector('.fees-input');
            const newFees = input.value.trim();
            
            if (!newFees) {
                toast.error('Fees cannot be empty');
                return;
            }
            
            // Show loading toast
            toast.info('Updating fees...');
            
            try {
                const formData = new FormData();
                formData.append('action', 'update_course_fees');
                formData.append('course_id', courseId);
                formData.append('fees', newFees);
                
                const response = await fetch('edit_scheme.php?id=<?php echo $scheme_id; ?>', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update display
                    const display = container.querySelector('.fees-display');
                    display.textContent = '₹' + newFees;
                    
                    // Reset view
                    resetFeesView(courseId);
                    
                    toast.success('Fees updated successfully!');
                } else {
                    toast.error(result.message || 'Error updating fees');
                }
            } catch (error) {
                toast.error('Error updating fees: ' + error.message);
            }
        });
    });
    
    // Cancel button click
    document.querySelectorAll('.cancel-fees-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const courseId = this.getAttribute('data-course-id');
            const container = document.querySelector(`.editable-fees-container[data-course-id="${courseId}"]`);
            const input = container.querySelector('.fees-input');
            const display = container.querySelector('.fees-display');
            
            // Reset input to original value
            input.value = display.textContent.replace('₹', '');
            
            resetFeesView(courseId);
        });
    });
    
    function resetFeesView(courseId) {
        const container = document.querySelector(`.editable-fees-container[data-course-id="${courseId}"]`);
        const display = container.querySelector('.fees-display');
        const input = container.querySelector('.fees-input');
        const editBtn = document.querySelector(`.edit-fees-btn[data-course-id="${courseId}"]`);
        const saveBtn = document.querySelector(`.save-fees-btn[data-course-id="${courseId}"]`);
        const cancelBtn = document.querySelector(`.cancel-fees-btn[data-course-id="${courseId}"]`);
        
        // Show display, hide input
        display.style.display = 'inline';
        input.style.display = 'none';
        
        // Toggle buttons
        editBtn.style.display = 'inline-block';
        saveBtn.style.display = 'none';
        cancelBtn.style.display = 'none';
    }
});
</script>

</body>
</html>
<?php
$conn->close();
?>
