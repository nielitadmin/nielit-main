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
$sql = "SELECT * FROM courses";
$result = $conn->query($sql);

// Delete course
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $delete_sql = "DELETE FROM courses WHERE id = $delete_id";
    if ($conn->query($delete_sql) === TRUE) {
        $_SESSION['message'] = "Course deleted successfully!";
    } else {
        $_SESSION['message'] = "Error deleting course: " . $conn->error;
    }
    header("Location: dashboard.php");
    exit();
}

// Update course
if (isset($_POST['update_course'])) {
    $course_id = $_POST['course_id'];
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
            $pdf_path = 'course_pdf/' . basename($pdf_file['name']);
            if (move_uploaded_file($pdf_file['tmp_name'], $pdf_path)) {
                $description_pdf = $pdf_path;
            }
        }
    }

    $update_sql = "UPDATE courses SET 
        course_name = '$course_name',
        eligibility = '$eligibility',
        duration = '$duration',
        training_fees = '$training_fees',
        category = '$category',
        start_date = '$start_date',
        end_date = '$end_date',
        description_url = '$description_url',
        apply_link = '$apply_link',
        course_coordinator = '$course_coordinator'";

    if (!empty($description_pdf)) {
        $update_sql .= ", description_pdf = '$description_pdf'";
    }

    $update_sql .= " WHERE id = $course_id";

    if ($conn->query($update_sql) === TRUE) {
        $_SESSION['message'] = "Course updated successfully!";
    } else {
        $_SESSION['message'] = "Error updating course: " . $conn->error;
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
            $pdf_path = 'course_pdf/' . basename($pdf_file['name']);
            if (move_uploaded_file($pdf_file['tmp_name'], $pdf_path)) {
                $description_pdf = $pdf_path;
            }
        }
    }

    $insert_sql = "INSERT INTO courses (
        course_name, eligibility, duration, training_fees, category,
        start_date, end_date, description_url, description_pdf, apply_link, course_coordinator
    ) VALUES (
        '$course_name', '$eligibility', '$duration', '$training_fees', '$category',
        '$start_date', '$end_date', '$description_url', '$description_pdf', '$apply_link', '$course_coordinator'
    )";

    if ($conn->query($insert_sql) === TRUE) {
        $_SESSION['message'] = "Course added successfully!";
    } else {
        $_SESSION['message'] = "Error adding course: " . $conn->error;
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <style>
        :root {
            --primary: #0d47a1;
            --secondary: #1565c0;
            --success: #2e7d32;
            --danger: #c62828;
            --warning: #f57c00;
            --info: #0277bd;
            --dark: #263238;
            --light: #eceff1;
        }
        
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 1000;
        }
        
        .sidebar .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar .logo img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            padding: 5px;
        }
        
        .sidebar .logo h5 {
            color: white;
            margin-top: 10px;
            font-size: 16px;
            font-weight: 600;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .top-bar {
            background: white;
            padding: 15px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-bar h4 {
            margin: 0;
            color: var(--dark);
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .stat-card.primary .icon {
            background: rgba(13, 71, 161, 0.1);
            color: var(--primary);
        }
        
        .stat-card.success .icon {
            background: rgba(46, 125, 50, 0.1);
            color: var(--success);
        }
        
        .stat-card.warning .icon {
            background: rgba(245, 124, 0, 0.1);
            color: var(--warning);
        }
        
        .stat-card h3 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }
        
        .stat-card p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .content-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .content-card h5 {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light);
        }
        
        .table {
            margin: 0;
        }
        
        .table thead th {
            background: var(--light);
            color: var(--dark);
            font-weight: 600;
            border: none;
            padding: 15px;
        }
        
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
        }
        
        .btn-action {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 6px;
        }
        
        .badge-category {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .modal-header {
            background: var(--primary);
            color: white;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo">
        <h5>NIELIT Admin</h5>
        <small class="text-white-50">Bhubaneswar</small>
    </div>
    
    <nav class="nav flex-column mt-4">
        <a class="nav-link active" href="dashboard.php">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a class="nav-link" href="students.php">
            <i class="fas fa-users"></i> Students
        </a>
        <a class="nav-link" href="dashboard.php">
            <i class="fas fa-book"></i> Courses
        </a>
        <a class="nav-link" href="manage_batches.php">
            <i class="fas fa-layer-group"></i> Batches
        </a>
        <a class="nav-link" href="add_admin.php">
            <i class="fas fa-user-shield"></i> Add Admin
        </a>
        <a class="nav-link" href="reset_password.php">
            <i class="fas fa-key"></i> Reset Password
        </a>
        <hr class="text-white-50 mx-3">
        <a class="nav-link" href="<?php echo APP_URL; ?>/index.php">
            <i class="fas fa-globe"></i> View Website
        </a>
        <a class="nav-link" href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
        <div>
            <h4><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h4>
            <small class="text-muted">Welcome back, Admin!</small>
        </div>
        <div class="user-info">
            <div>
                <div class="fw-bold"><?php echo $_SESSION['admin']; ?></div>
                <small class="text-muted">Administrator</small>
            </div>
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card primary">
                <div class="icon">
                    <i class="fas fa-book"></i>
                </div>
                <h3><?php echo $total_courses; ?></h3>
                <p>Total Courses</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card success">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo $total_students; ?></h3>
                <p>Total Students</p>
            </div>
        </div>
    </div>

    <!-- Courses Table -->
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5><i class="fas fa-book me-2"></i>All Courses</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                <i class="fas fa-plus me-2"></i>Add New Course
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
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
                            <small class="text-muted"><?php echo htmlspecialchars($row['eligibility']); ?></small>
                        </td>
                        <td>
                            <span class="badge-category bg-primary text-white">
                                <?php echo htmlspecialchars($row['category']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['duration']); ?></td>
                        <td>₹<?php echo htmlspecialchars($row['training_fees']); ?></td>
                        <td><?php echo date('d M Y', strtotime($row['start_date'])); ?></td>
                        <td>
                            <a href="edit_course.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning btn-action">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="dashboard.php?delete_id=<?php echo $row['id']; ?>" 
                               class="btn btn-sm btn-danger btn-action" 
                               onclick="return confirm('Are you sure?')">
                                <i class="fas fa-trash"></i>
                            </a>
                            <a href="manage_batches.php?course_id=<?php echo $row['id']; ?>" 
                               class="btn btn-sm btn-info btn-action">
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

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course Name *</label>
                            <input type="text" class="form-control" name="course_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Long Term NSQF">Long Term NSQF</option>
                                <option value="Short Term NSQF">Short Term NSQF</option>
                                <option value="Short-Term Non-NSQF">Short-Term Non-NSQF</option>
                                <option value="Internship Program">Internship Program</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Eligibility *</label>
                            <input type="text" class="form-control" name="eligibility" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration *</label>
                            <input type="text" class="form-control" name="duration" placeholder="e.g., 6 Months" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Training Fees *</label>
                            <input type="text" class="form-control" name="training_fees" placeholder="e.g., 15000" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course Coordinator *</label>
                            <input type="text" class="form-control" name="course_coordinator" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Description URL</label>
                            <input type="url" class="form-control" name="description_url" placeholder="https://...">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Apply Link</label>
                            <input type="url" class="form-control" name="apply_link" placeholder="https://...">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Description PDF</label>
                            <input type="file" class="form-control" name="description_pdf" accept=".pdf">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_course" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Add Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
