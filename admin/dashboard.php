<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/session_manager.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");
    exit();
}

// Initialize session if role is missing (for backward compatibility)
if (!isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id'])) {
    if (!init_admin_session($_SESSION['admin'])) {
        // Session initialization failed, redirect to login
        session_unset();
        session_destroy();
        header("Location: login_new.php");
        exit();
    }
}

// Load active theme
$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);

// Get admin's assigned courses for filtering (used throughout the page)
$admin_courses = [];
$is_course_coordinator = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'course_coordinator';

if ($is_course_coordinator) {
    // Get admin_id from session or fetch from database
    $admin_id = $_SESSION['admin_id'] ?? null;
    
    // If admin_id not in session, fetch it from database using username
    if (!$admin_id && isset($_SESSION['admin'])) {
        $admin_username = $_SESSION['admin'];
        $admin_query = "SELECT id FROM admin WHERE username = ?";
        $admin_stmt = $conn->prepare($admin_query);
        $admin_stmt->bind_param("s", $admin_username);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        if ($admin_row = $admin_result->fetch_assoc()) {
            $admin_id = $admin_row['id'];
            $_SESSION['admin_id'] = $admin_id; // Store for future use
        }
    }
    
    // Get assigned courses for this coordinator
    if ($admin_id) {
        $course_query = "SELECT c.id, c.course_name 
                        FROM admin_course_assignments aca
                        JOIN courses c ON aca.course_id = c.id
                        WHERE aca.admin_id = ? AND aca.is_active = 1";
        $course_stmt = $conn->prepare($course_query);
        $course_stmt->bind_param("i", $admin_id);
        $course_stmt->execute();
        $course_result = $course_stmt->get_result();
        while ($course_row = $course_result->fetch_assoc()) {
            $admin_courses[] = $course_row['course_name'];
        }
    }
}

// Get filter parameter
$filter_category = $_GET['category'] ?? 'all';

// Build query with filter and student count
$sql = "SELECT courses.*, 
        (SELECT COUNT(*) FROM students WHERE students.course = courses.course_name) as student_count 
        FROM courses WHERE 1=1";

// Add course coordinator filtering
if ($is_course_coordinator) {
    if (!empty($admin_courses)) {
        // Coordinator has assigned courses - show only those courses
        $placeholders = str_repeat('?,', count($admin_courses) - 1) . '?';
        $sql .= " AND courses.course_name IN ($placeholders)";
    } else {
        // Coordinator has no assigned courses - show no courses
        $sql .= " AND 1=0"; // This makes the query return no results
    }
}

// Add category filter
if ($filter_category !== 'all') {
    $sql .= " AND category = ?";
}

$sql .= " ORDER BY id DESC";

// Execute query with filters
$bind_types = '';
$bind_values = [];

// Add admin courses if coordinator (only if they have assigned courses)
if ($is_course_coordinator && !empty($admin_courses)) {
    $bind_types .= str_repeat('s', count($admin_courses));
    $bind_values = array_merge($bind_values, $admin_courses);
}

// Add category filter
if ($filter_category !== 'all') {
    $bind_types .= 's';
    $bind_values[] = $filter_category;
}

// Bind parameters if any
if (!empty($bind_values)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($bind_types, ...$bind_values);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Check if query failed
if (!$result) {
    die("Database query failed: " . $conn->error);
}

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
    $course_code = strtoupper($_POST['course_code'] ?? '');
    $course_abbreviation = strtoupper($_POST['course_abbreviation'] ?? '');
    $eligibility = $_POST['eligibility'];
    $duration = $_POST['duration'];
    $training_fees = $_POST['training_fees'];
    $category = $_POST['category'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $description_url = $_POST['description_url'];
    $apply_link = $_POST['apply_link'];
    $course_coordinator = $_POST['course_coordinator'];
    $training_center = $_POST['training_center'] ?? (!empty($centres) ? $centres[0]['name'] : 'NIELIT BHUBANESWAR');
    $link_published = isset($_POST['link_published']) ? 1 : 0;
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
        course_name, course_code, course_abbreviation, eligibility, duration, training_fees, category,
        start_date, end_date, description_url, description_pdf, apply_link, course_coordinator,
        training_center, link_published
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("ssssssssssssssi", 
        $course_name, $course_code, $course_abbreviation, $eligibility, $duration, $training_fees, $category,
        $start_date, $end_date, $description_url, $description_pdf, $apply_link, $course_coordinator,
        $training_center, $link_published
    );

    if ($stmt->execute()) {
        $course_id = $conn->insert_id;
        
        // Auto-assign course to course coordinator who created it
        if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'course_coordinator' && isset($_SESSION['admin_id'])) {
            $admin_id = $_SESSION['admin_id'];
            $assigned_by = $_SESSION['admin_id']; // Self-assigned
            
            $assign_stmt = $conn->prepare("INSERT INTO admin_course_assignments (admin_id, course_id, is_active, assigned_by, assignment_type) VALUES (?, ?, 1, ?, 'Auto-Assigned')");
            $assign_stmt->bind_param("iii", $admin_id, $course_id, $assigned_by);
            $assign_stmt->execute();
            $assign_stmt->close();
        }
        
        // Handle scheme associations for new course
        if (isset($_POST['schemes']) && !empty($_POST['schemes'])) {
            $insert_scheme_sql = "INSERT INTO course_schemes (course_id, scheme_id) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($insert_scheme_sql);
            
            foreach ($_POST['schemes'] as $scheme_id) {
                $stmt_insert->bind_param("ii", $course_id, $scheme_id);
                $stmt_insert->execute();
            }
        }
        
        // Auto-generate QR code if registration link exists
        if (!empty($apply_link)) {
            require_once __DIR__ . '/../includes/qr_helper.php';
            $qr_result = generateCourseQRCode($course_id, $course_code);
            
            if ($qr_result['success']) {
                // Update course with QR path
                $stmt_update = $conn->prepare("UPDATE courses SET qr_code_path = ?, qr_generated_at = NOW() WHERE id = ?");
                $stmt_update->bind_param("si", $qr_result['path'], $course_id);
                $stmt_update->execute();
                
                $_SESSION['message'] = "Course added successfully! Registration link and QR code generated. Course automatically assigned to you.";
            } else {
                $_SESSION['message'] = "Course added successfully! But QR code generation failed. Course automatically assigned to you.";
            }
        } else {
            $_SESSION['message'] = "Course added successfully! Generate registration link to create QR code. Course automatically assigned to you.";
        }
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error adding course: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }

    header("Location: dashboard.php");
    exit();
}

// Get statistics
// Total courses (filtered for coordinators)
if ($is_course_coordinator) {
    if (!empty($admin_courses)) {
        $placeholders = str_repeat('?,', count($admin_courses) - 1) . '?';
        $stats_sql = "SELECT COUNT(*) as count FROM courses WHERE course_name IN ($placeholders)";
        $stats_stmt = $conn->prepare($stats_sql);
        $stats_stmt->bind_param(str_repeat('s', count($admin_courses)), ...$admin_courses);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $total_courses = $stats_result ? $stats_result->fetch_assoc()['count'] : 0;
    } else {
        // Coordinator has no assigned courses - return 0
        $total_courses = 0;
    }
} else {
    $stats_query = $conn->query("SELECT COUNT(*) as count FROM courses");
    $total_courses = $stats_query ? $stats_query->fetch_assoc()['count'] : 0;
}

// Total students (filtered for coordinators)
if ($is_course_coordinator) {
    if (!empty($admin_courses)) {
        $placeholders = str_repeat('?,', count($admin_courses) - 1) . '?';
        $stats_sql = "SELECT COUNT(*) as count FROM students WHERE course IN ($placeholders)";
        $stats_stmt = $conn->prepare($stats_sql);
        $stats_stmt->bind_param(str_repeat('s', count($admin_courses)), ...$admin_courses);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $total_students = $stats_result ? $stats_result->fetch_assoc()['count'] : 0;
    } else {
        // Coordinator has no assigned courses - return 0
        $total_students = 0;
    }
} else {
} else {
    $stats_query = $conn->query("SELECT COUNT(*) as count FROM students");
    $total_students = $stats_query ? $stats_query->fetch_assoc()['count'] : 0;
}

// System Enhancement Module statistics
$stats_query = $conn->query("SELECT COUNT(*) as count FROM centres WHERE is_active = 1");
$total_centres = $stats_query ? $stats_query->fetch_assoc()['count'] : 0;

// Fetch active centres for dropdown
$centres_query = "SELECT id, name, code FROM centres WHERE is_active = 1 ORDER BY name ASC";
$centres_result = $conn->query($centres_query);
$centres = [];
if ($centres_result) {
    while ($centre = $centres_result->fetch_assoc()) {
        $centres[] = $centre;
    }
}

$stats_query = $conn->query("SELECT theme_name FROM themes WHERE is_active = 1 LIMIT 1");
$active_theme_name = $stats_query && $stats_query->num_rows > 0 ? $stats_query->fetch_assoc()['theme_name'] : 'Default Theme';

$stats_query = $conn->query("SELECT COUNT(*) as count FROM homepage_content WHERE is_active = 1");
$total_homepage_sections = $stats_query ? $stats_query->fetch_assoc()['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; object-src 'none';">
    <title>Admin Dashboard - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo getThemeFavicon($active_theme); ?>" type="image/x-icon">
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-header">
            <img src="<?php echo APP_URL . '/' . $theme_logo; ?>" alt="NIELIT Logo" class="sidebar-logo">
            <h3>NIELIT Admin</h3>
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
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/manage_batches.php" class="nav-link">
                    <i class="fas fa-layer-group"></i> Batches
                </a>
            </div>
            
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'master_admin'): ?>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php" class="nav-link">
                    <i class="fas fa-project-diagram"></i> Schemes/Projects
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'master_admin'): ?>
            <div class="nav-divider"></div>
            <div class="nav-section-title">System Settings</div>
            
            <div class="nav-item">
                <a href="manage_centres.php" class="nav-link">
                    <i class="fas fa-building"></i> Training Centres
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_themes.php" class="nav-link">
                    <i class="fas fa-palette"></i> Themes
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_homepage.php" class="nav-link">
                    <i class="fas fa-home"></i> Homepage Content
                </a>
            </div>
            
            <div class="nav-divider"></div>
            <?php endif; ?>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/approve_students.php" class="nav-link">
                    <i class="fas fa-user-check"></i> Approve Students
                </a>
            </div>
            
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'master_admin'): ?>
            <div class="nav-item">
                <a href="add_admin.php" class="nav-link">
                    <i class="fas fa-user-plus"></i> Add Admin
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_admins.php" class="nav-link">
                    <i class="fas fa-users-cog"></i> Manage Admins
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_course_assignments.php" class="nav-link">
                    <i class="fas fa-user-tie"></i> Course Assignments
                </a>
            </div>
            <?php endif; ?>
            
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
                        <span class="user-role">
                            <?php 
                            echo isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'master_admin' 
                                ? 'Master Administrator' 
                                : 'Course Coordinator'; 
                            ?>
                        </span>
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
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        toast.<?php echo ($_SESSION['message_type'] ?? 'success') === 'success' ? 'success' : 'error'; ?>('<?php echo addslashes($_SESSION['message']); ?>', 5000);
                    });
                </script>
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
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $total_centres; ?></h3>
                    <p class="stat-label">Training Centres</p>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-palette"></i>
                    </div>
                    <h3 class="stat-value" style="font-size: 1.2rem;"><?php echo htmlspecialchars($active_theme_name); ?></h3>
                    <p class="stat-label">Active Theme</p>
                </div>
                
                <div class="stat-card secondary">
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $total_homepage_sections; ?></h3>
                    <p class="stat-label">Homepage Sections</p>
                </div>
            </div>

            <!-- Courses Table -->
            <div class="content-card" style="margin-bottom: 20px;">
                <div class="card-header" style="border-bottom: 1px solid #e2e8f0;">
                    <h5 class="card-title" style="margin: 0;">
                        <i class="fas fa-filter"></i> Filter Courses
                    </h5>
                </div>
                <div style="padding: 20px;">
                    <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 16px; align-items: end;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label"><i class="fas fa-tag"></i> Filter by Category</label>
                            <select name="category" class="form-select" onchange="this.form.submit()" style="width: 100%;">
                                <option value="all" <?= $filter_category === 'all' ? 'selected' : '' ?>>All Categories</option>
                                <option value="Long Term NSQF" <?= $filter_category === 'Long Term NSQF' ? 'selected' : '' ?>>Long Term NSQF</option>
                                <option value="Short Term NSQF" <?= $filter_category === 'Short Term NSQF' ? 'selected' : '' ?>>Short Term NSQF</option>
                                <option value="Short-Term Non-NSQF" <?= $filter_category === 'Short-Term Non-NSQF' ? 'selected' : '' ?>>Short-Term Non-NSQF</option>
                                <option value="Internship Program" <?= $filter_category === 'Internship Program' ? 'selected' : '' ?>>Internship Program</option>
                            </select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; color: #64748b;">
                            <i class="fas fa-info-circle"></i>
                            <span>
                                <?php 
                                $total_filtered = $result->num_rows;
                                if ($filter_category !== 'all') {
                                    echo '<strong style="color: #0d47a1;">' . $total_filtered . ' results</strong> found';
                                } else {
                                    echo '<strong style="color: #64748b;">' . $total_filtered . ' total</strong> courses';
                                }
                                ?>
                            </span>
                        </div>
                        <div>
                            <?php if ($filter_category !== 'all'): ?>
                                <a href="dashboard.php" class="btn btn-secondary" style="width: 100%;">
                                    <i class="fas fa-redo"></i> Clear Filter
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary" disabled style="width: 100%; opacity: 0.5;">
                                    <i class="fas fa-filter"></i> No Filter
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($is_course_coordinator && empty($admin_courses)): ?>
                <!-- No Course Assignments Message for Coordinators -->
                <div class="content-card">
                    <div class="card-body text-center" style="padding: 3rem;">
                        <div style="color: #64748b; margin-bottom: 1.5rem;">
                            <i class="fas fa-book" style="font-size: 4rem; opacity: 0.3;"></i>
                        </div>
                        <h4 style="color: #374151; margin-bottom: 1rem;">No Course Assignments</h4>
                        <p style="color: #6b7280; margin-bottom: 1.5rem;">
                            You haven't been assigned to any courses yet. Please contact the Master Admin to assign courses to your coordinator account.
                        </p>
                        <div style="background: #f3f4f6; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                            <small style="color: #6b7280;">
                                <i class="fas fa-info-circle"></i> 
                                Course coordinators can only view and manage courses they are assigned to.
                            </small>
                        </div>
                        <a href="students.php" class="btn btn-secondary me-2">
                            <i class="fas fa-users"></i> View Students
                        </a>
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </a>
                    </div>
                </div>
            <?php else: ?>
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-book"></i> All Courses
                        <?php if ($is_course_coordinator && !empty($admin_courses)): ?>
                            <small style="color: #64748b; font-weight: normal;">
                                (Showing your assigned courses: <?php echo implode(', ', $admin_courses); ?>)
                            </small>
                        <?php endif; ?>
                    </h5>
                    <?php if (!$is_course_coordinator): ?>
                    <button class="btn btn-primary" onclick="openModal('addCourseModal')">
                        <i class="fas fa-plus"></i> Add New Course
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Course Name</th>
                                <th>Course Code</th>
                                <th>Student ID Code</th>
                                <th>Category</th>
                                <th>Duration</th>
                                <th>Fees</th>
                                <th>Start Date</th>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['course_name']); ?></strong><br>
                                        <small style="color: #64748b;"><?php echo htmlspecialchars($row['eligibility']); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['course_code'])): ?>
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($row['course_code']); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Not Set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['course_abbreviation'])): ?>
                                            <span class="badge badge-success"><?php echo htmlspecialchars($row['course_abbreviation']); ?></span>
                                            <br><small class="text-muted">NIELIT/2026/<?php echo htmlspecialchars($row['course_abbreviation']); ?>/####</small>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Not Set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo htmlspecialchars($row['category']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['duration']); ?></td>
                                    <td>₹<?php echo is_numeric($row['training_fees']) ? number_format($row['training_fees']) : htmlspecialchars($row['training_fees']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($row['start_date'])); ?></td>
                                    <td>
                                        <?php 
                                        $student_count = $row['student_count'] ?? 0;
                                        $badge_class = $student_count > 0 ? 'badge-success' : 'badge-secondary';
                                        ?>
                                        <a href="students.php?filter_course=<?php echo urlencode($row['course_name']); ?>" 
                                           class="badge <?php echo $badge_class; ?>" 
                                           style="text-decoration: none; font-size: 14px; padding: 6px 12px;">
                                            <i class="fas fa-users"></i> <?php echo $student_count; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="edit_course.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="dashboard.php?delete_id=<?php echo $row['id']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirmDelete(event, '<?php echo htmlspecialchars($row['course_name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #64748b;">
                                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.3;"></i>
                                        <p style="margin: 0; font-size: 16px;">No courses found. Click "Add New Course" to get started.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Add Course Modal -->
<div class="modal" id="addCourseModal">
    <div class="modal-dialog" style="max-width: 900px;">
        <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Course</h5>
            <button type="button" onclick="closeModal('addCourseModal')" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <form action="dashboard.php" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <!-- Course Name and Codes Row -->
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label class="form-label">Course Name *</label>
                        <input type="text" class="form-control" id="add_course_name_dash" name="course_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course Code * <small>(e.g., PPI-2026)</small></label>
                        <input type="text" class="form-control" name="course_code" maxlength="20" required style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Student ID Code * <small>(e.g., PPI)</small></label>
                        <input type="text" class="form-control" name="course_abbreviation" id="add_abbr_dash" maxlength="10" required style="text-transform: uppercase;" placeholder="PPI">
                        <small class="text-muted">For ID: NIELIT/2026/<strong>PPI</strong>/0001</small>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
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
                        <label class="form-label">Training Centre *</label>
                        <select class="form-select" name="training_center" required>
                            <option value="">-- Select Training Centre --</option>
                            <?php foreach ($centres as $centre): ?>
                                <option value="<?= htmlspecialchars($centre['name']) ?>"><?= htmlspecialchars($centre['name']) ?> (<?= htmlspecialchars($centre['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
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
                
                <hr style="margin: 24px 0; border-color: #e3f2fd;">
                <h6 style="color: #0d47a1; margin-bottom: 16px;"><i class="fas fa-link"></i> Registration Link Settings</h6>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                    <!-- Schemes/Projects Selection -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-project-diagram"></i> Schemes/Projects
                        </label>
                        <?php
                        // Fetch all active schemes for add course form
                        $schemes_query_add = "SELECT * FROM schemes WHERE status = 'Active' ORDER BY scheme_name";
                        $schemes_result_add = $conn->query($schemes_query_add);
                        ?>
                        
                        <div style="background: #f8f9fa; padding: 16px; border-radius: 6px; border: 1px solid #dee2e6;">
                            <?php if ($schemes_result_add && $schemes_result_add->num_rows > 0): ?>
                                <?php while ($scheme = $schemes_result_add->fetch_assoc()): ?>
                                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                                        <input type="checkbox" 
                                               name="schemes[]" 
                                               value="<?php echo $scheme['id']; ?>"
                                               style="width: 18px; height: 18px;">
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($scheme['scheme_name']); ?></span>
                                        <span style="color: #6c757d; font-size: 12px;">(<?php echo htmlspecialchars($scheme['scheme_code']); ?>)</span>
                                    </label>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="color: #6c757d; margin: 0;">
                                    <i class="fas fa-info-circle"></i> No schemes available. 
                                    <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php" target="_blank" style="color: #007bff;">Create schemes</a>
                                </p>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">Select one or more schemes/projects for this course</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Apply Link</label>
                        <div style="display: flex; gap: 8px;">
                            <input type="url" class="form-control" name="apply_link" id="add_apply_link_dash" placeholder="Will be auto-generated" readonly>
                            <button type="button" class="btn btn-success" onclick="generateApplyLinkDash()" style="white-space: nowrap;">
                                <i class="fas fa-magic"></i> Generate Link
                            </button>
                        </div>
                        <small class="text-muted">Click "Generate Link" to create registration URL automatically</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Publish Status</label>
                        <div style="padding-top: 8px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="link_published" id="add_link_published_dash" value="1" style="width: 20px; height: 20px;">
                                <span id="add_publish_status_dash">Unpublished</span>
                            </label>
                        </div>
                        <small class="text-muted">Toggle to show/hide on website</small>
                    </div>
                </div>
                
                <div style="background: #e3f2fd; padding: 12px; border-radius: 6px; margin-top: 12px;">
                    <strong><i class="fas fa-info-circle"></i> Preview:</strong> 
                    <span id="link_preview_dash">Enter course name and click "Generate Link"</span>
                </div>
                
                <div style="background: #fff3cd; padding: 12px; border-radius: 6px; margin-top: 12px; border-left: 4px solid #ffc107;">
                    <i class="fas fa-lightbulb"></i> <strong>Note:</strong> QR code will be generated automatically when you save the course with a registration link.
                </div>
                
                <div class="form-group" style="margin-top: 16px;">
                    <label class="form-label">Description PDF</label>
                    <input type="file" class="form-control" name="description_pdf" accept=".pdf">
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

<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
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

// Modern confirm delete
async function confirmDelete(event, courseName) {
    event.preventDefault();
    const confirmed = await showConfirm({
        title: 'Delete Course?',
        message: `Are you sure you want to delete "${courseName}"? This action cannot be undone.`,
        confirmText: 'Delete',
        cancelText: 'Cancel',
        type: 'danger'
    });
    
    if (confirmed) {
        window.location.href = event.target.closest('a').href;
    }
    return false;
}

// Generate Apply Link for Dashboard (Simple - no AJAX for new courses)
function generateApplyLinkDash() {
    const courseNameInput = document.getElementById('add_course_name_dash');
    const courseCodeInput = document.querySelector('input[name="course_code"]');
    const linkInput = document.getElementById('add_apply_link_dash');
    const previewSpan = document.getElementById('link_preview_dash');
    
    const courseName = courseNameInput.value.trim();
    const courseCode = courseCodeInput.value.trim();
    
    if (!courseName) {
        toast.warning('Please enter course name first!');
        courseNameInput.focus();
        return;
    }
    
    if (!courseCode) {
        toast.warning('Please enter course code first!');
        courseCodeInput.focus();
        return;
    }
    
    // Generate link based on course CODE (not course name)
    const baseUrl = window.location.origin + window.location.pathname.replace('dashboard.php', '');
    const registrationLink = baseUrl + '../student/register.php?course=' + encodeURIComponent(courseCode);
    
    linkInput.value = registrationLink;
    previewSpan.textContent = registrationLink;
    
    // Show success message
    toast.success('Registration link generated! QR code will be created automatically when you save the course.');
}

// Toggle publish status label
document.addEventListener('DOMContentLoaded', function() {
    const publishCheckbox = document.getElementById('add_link_published_dash');
    if (publishCheckbox) {
        publishCheckbox.addEventListener('change', function() {
            const statusSpan = document.getElementById('add_publish_status_dash');
            statusSpan.textContent = this.checked ? 'Published' : 'Unpublished';
            statusSpan.style.color = this.checked ? '#28a745' : '';
            statusSpan.style.fontWeight = this.checked ? 'bold' : '';
        });
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>
