<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");
    exit();
}

// Fetch all courses
$sql = "SELECT * FROM courses ORDER BY created_at DESC";
$result = $conn->query($sql);

// Delete course
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $delete_sql = "DELETE FROM courses WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Course deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting course: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    header("Location: dashboard.php");
    exit();
}

// Add course
if (isset($_POST['add_course'])) {
    $course_name = $_POST['course_name'];
    $eligibility = $_POST['eligibility'];
    $duration = $_POST['duration'];
    $training_fees = $_POST['training_fees'];
    $category = $_POST['category'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $description_url = $_POST['description_url'];
    $apply_link = $_POST['apply_link'];
    $course_coordinator = $_POST['course_coordinator'];
    $description_pdf = '';

    if (isset($_FILES['description_pdf']) && $_FILES['description_pdf']['error'] == 0) {
        $pdf_file = $_FILES['description_pdf'];
        if ($pdf_file['type'] == 'application/pdf') {
            $pdf_path = '../course_pdf/' . uniqid('course_', true) . '.pdf';
            if (move_uploaded_file($pdf_file['tmp_name'], $pdf_path)) {
                $description_pdf = $pdf_path;
            }
        }
    }

    $insert_sql = "INSERT INTO courses (
        course_name, eligibility, duration, training_fees, category,
        start_date, end_date, description_url, description_pdf, apply_link, course_coordinator
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("sssssssssss", 
        $course_name, $eligibility, $duration, $training_fees, $category,
        $start_date, $end_date, $description_url, $description_pdf, $apply_link, $course_coordinator
    );

    if ($stmt->execute()) {
        $_SESSION['message'] = "Course added successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error adding course: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }

    header("Location: dashboard.php");
    exit();
}

// Get statistics
$total_courses = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NIELIT Bhubaneswar</title>
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
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="students.php" class="nav-link">
                    <i class="fas fa-users"></i> Students
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_courses.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_batches.php" class="nav-link">
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
                <h4><i class="fas fa-tachometer-alt"></i> Dashboard</h4>
                <small>Welcome back, Admin!</small>
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
                <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'success'; ?>">
                    <i class="fas fa-<?php echo ($_SESSION['message_type'] ?? 'success') == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $_SESSION['message']; ?>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $total_courses; ?></h3>
                    <p class="stat-label">Total Courses</p>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $total_students; ?></h3>
                    <p class="stat-label">Total Students</p>
                </div>
            </div>

            <!-- Courses Table -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-book"></i> All Courses
                    </h5>
                    <button class="btn btn-primary" onclick="openModal('addCourseModal')">
                        <i class="fas fa-plus"></i> Add New Course
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Course Name</th>
                                <th>Category</th>
                                <th>Duration</th>
                                <th>Fees</th>
                                <th>Start Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['course_name']); ?></strong><br>
                                    <small style="color: #64748b;"><?php echo htmlspecialchars($row['eligibility']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-primary">
                                        <?php echo htmlspecialchars($row['category']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['duration']); ?></td>
                                <td>₹<?php echo number_format($row['training_fees']); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['start_date'])); ?></td>
                                <td>
                                    <a href="edit_course.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="dashboard.php?delete_id=<?php echo $row['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this course?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <a href="manage_batches.php?course_id=<?php echo $row['id']; ?>" 
                                       class="btn btn-info btn-sm">
                                        <i class="fas fa-layer-group"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Course Modal -->
<div class="modal" id="addCourseModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Course</h5>
            <button type="button" onclick="closeModal('addCourseModal')" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <form action="dashboard.php" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Course Name *</label>
                        <input type="text" class="form-control" name="course_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select class="form-select" name="category" required>
                            <option value="">Select Category</option>
                            <option value="Long Term NSQF">Long Term NSQF</option>
                            <option value="Short Term NSQF">Short Term NSQF</option>
                            <option value="Short-Term Non-NSQF">Short-Term Non-NSQF</option>
                            <option value="Internship Program">Internship Program</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Eligibility *</label>
                        <input type="text" class="form-control" name="eligibility" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duration *</label>
                        <input type="text" class="form-control" name="duration" placeholder="e.g., 6 Months" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Training Fees *</label>
                        <input type="text" class="form-control" name="training_fees" placeholder="e.g., 15000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course Coordinator *</label>
                        <input type="text" class="form-control" name="course_coordinator" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Date *</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date *</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description URL</label>
                    <input type="url" class="form-control" name="description_url" placeholder="https://...">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Apply Link</label>
                        <input type="url" class="form-control" name="apply_link" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description PDF</label>
                        <input type="file" class="form-control" name="description_pdf" accept=".pdf">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCourseModal')">Cancel</button>
                <button type="submit" name="add_course" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Course
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}
</script>

</body>
</html>
<?php $conn->close(); ?>
