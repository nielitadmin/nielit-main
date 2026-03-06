<?php
// Start session and include the database connection
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");  // Redirect if not logged in as admin
    exit();
}

// Load active theme
$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);

// Handle deleting a student
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id']; // Retrieve student_id to delete

    // Ensure the delete query only affects the selected student's record
    $delete_sql = "DELETE FROM students WHERE student_id = ?";

    // Prepare the statement to delete the student
    $stmt = $conn->prepare($delete_sql);

    // Bind the parameter as a string ('s' for string) since student_id is likely a string
    $stmt->bind_param("s", $delete_id);  // Change from "i" to "s" for string

    if ($stmt->execute()) {
        $_SESSION['message'] = "Student deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting student: " . $conn->error;
        $_SESSION['message_type'] = "error";
    }
}

// Handle approving a student
if (isset($_GET['approve_id'])) {
    $approve_id = $_GET['approve_id'];
    
    $approve_sql = "UPDATE students SET status = 'active' WHERE student_id = ?";
    $stmt = $conn->prepare($approve_sql);
    $stmt->bind_param("s", $approve_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Student approved successfully! Status changed to Active.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error approving student: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
}

// Handle rejecting a student
if (isset($_GET['reject_id'])) {
    $reject_id = $_GET['reject_id'];
    
    $reject_sql = "UPDATE students SET status = 'rejected' WHERE student_id = ?";
    $stmt = $conn->prepare($reject_sql);
    $stmt->bind_param("s", $reject_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Student registration rejected.";
        $_SESSION['message_type'] = "warning";
    } else {
        $_SESSION['message'] = "Error rejecting student: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
}

// Handle updating a student
if (isset($_POST['update_student'])) {
    $student_id = $_POST['student_id'];
    $name = $_POST['name'];
    $father_name = $_POST['father_name'];
    $mother_name = $_POST['mother_name'];
    $dob = $_POST['dob'];
    $mobile = $_POST['mobile'];
    $email = $_POST['email'];
    $course = $_POST['course'];
    $status = $_POST['status'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $pincode = $_POST['pincode'];

    $update_sql = "UPDATE students SET 
                    name = ?, father_name = ?, mother_name = ?, dob = ?, mobile = ?, email = ?, 
                    course = ?, status = ?, address = ?, city = ?, state = ?, pincode = ? 
                    WHERE student_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssssssssssssi", $name, $father_name, $mother_name, $dob, $mobile, $email, 
                     $course, $status, $address, $city, $state, $pincode, $student_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Student updated successfully!";
    } else {
        $_SESSION['message'] = "Error updating student: " . $conn->error;
    }
}


// Query to get total number of students
$total_students_query = "SELECT COUNT(*) AS total_students FROM students";
$total_students_result = $conn->query($total_students_query);
$total_students_row = $total_students_result->fetch_assoc();
$total_students_count = $total_students_row['total_students'];

// Query to get pending students count
$pending_students_query = "SELECT COUNT(*) AS pending_students FROM students WHERE status = 'pending'";
$pending_students_result = $conn->query($pending_students_query);
$pending_students_row = $pending_students_result->fetch_assoc();
$pending_students_count = $pending_students_row['pending_students'];

// Query to get active students count
$active_students_query = "SELECT COUNT(*) AS active_students FROM students WHERE status = 'active'";
$active_students_result = $conn->query($active_students_query);
$active_students_row = $active_students_result->fetch_assoc();
$active_students_count = $active_students_row['active_students'];


// Query for the last 12 months' Total Students Count
$month_query = "
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total_students 
    FROM students 
    WHERE created_at >= CURDATE() - INTERVAL 12 MONTH
    GROUP BY month 
    ORDER BY month ASC
";
$month_result = $conn->query($month_query);

// Prepare data for charts
$months = [];
$total_students = array_fill(0, 12, 0); // Initialize array with 12 zeros (for 12 months)

$current_month = date('Y-m'); // Current month
$start_month = date('Y-m', strtotime('-11 months')); // 12 months back

// Fill in the months array
for ($i = 0; $i < 12; $i++) {
    $month_label = date('Y-m', strtotime("$start_month +$i month"));
    $months[] = $month_label;
}

// Fill total_students with the data from the query
while ($row = $month_result->fetch_assoc()) {
    $month_index = array_search($row['month'], $months);
    if ($month_index !== false) {
        $total_students[$month_index] = $row['total_students'];
    }
}

// Fetch gender distribution
$gender_query = "SELECT gender, COUNT(*) AS gender_count FROM students GROUP BY gender";
$gender_result = $conn->query($gender_query);

// Fetch category distribution
$category_query = "SELECT category, COUNT(*) AS category_count FROM students GROUP BY category";
$category_result = $conn->query($category_query);

$gender_data = ['Male' => 0, 'Female' => 0];
while ($row = $gender_result->fetch_assoc()) {
    $gender_data[$row['gender']] = $row['gender_count'];
}

$category_data = ['General' => 0, 'OBC' => 0, 'SC' => 0, 'ST' => 0, 'EWS' => 0];
while ($row = $category_result->fetch_assoc()) {
    $category_data[$row['category']] = $row['category_count'];
}

// Fetch all courses for dropdown list
$sql_courses = "SELECT course_name FROM courses";
$courses_result = $conn->query($sql_courses);

// Handle batch assignment
if (isset($_POST['assign_batch'])) {
    $student_id = $_POST['student_id'];
    $batch_id = $_POST['batch_id'];
    
    if (!empty($batch_id)) {
        // Update student's batch_id
        $assign_sql = "UPDATE students SET batch_id = ? WHERE student_id = ?";
        $stmt = $conn->prepare($assign_sql);
        $stmt->bind_param("is", $batch_id, $student_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Student assigned to batch successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error assigning student to batch: " . $conn->error;
            $_SESSION['message_type'] = "danger";
        }
    }
    
    // Preserve filter parameters in redirect
    $redirect_params = [];
    if (isset($_POST['filter_course']) && !empty($_POST['filter_course'])) {
        $redirect_params[] = 'filter_course=' . urlencode($_POST['filter_course']);
    }
    if (isset($_POST['start_date']) && !empty($_POST['start_date'])) {
        $redirect_params[] = 'start_date=' . urlencode($_POST['start_date']);
    }
    if (isset($_POST['end_date']) && !empty($_POST['end_date'])) {
        $redirect_params[] = 'end_date=' . urlencode($_POST['end_date']);
    }
    
    $redirect_url = 'students.php';
    if (!empty($redirect_params)) {
        $redirect_url .= '?' . implode('&', $redirect_params);
    }
    
    header("Location: " . $redirect_url);
    exit();
}

// Handle bulk batch assignment
if (isset($_POST['bulk_assign_batch'])) {
    $student_ids = isset($_POST['student_ids']) ? $_POST['student_ids'] : [];
    $batch_id = $_POST['batch_id'];
    
    if (!empty($batch_id) && !empty($student_ids)) {
        $success_count = 0;
        $error_count = 0;
        
        foreach ($student_ids as $student_id) {
            $assign_sql = "UPDATE students SET batch_id = ? WHERE student_id = ?";
            $stmt = $conn->prepare($assign_sql);
            $stmt->bind_param("is", $batch_id, $student_id);
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        if ($success_count > 0) {
            $_SESSION['message'] = "$success_count student(s) assigned to batch successfully!";
            $_SESSION['message_type'] = "success";
        }
        if ($error_count > 0) {
            $_SESSION['message'] .= " $error_count student(s) failed to assign.";
            $_SESSION['message_type'] = "warning";
        }
    } else {
        $_SESSION['message'] = "Please select students and a batch.";
        $_SESSION['message_type'] = "warning";
    }
    
    // Preserve filter parameters in redirect
    $redirect_params = [];
    if (isset($_POST['filter_course']) && !empty($_POST['filter_course'])) {
        $redirect_params[] = 'filter_course=' . urlencode($_POST['filter_course']);
    }
    if (isset($_POST['start_date']) && !empty($_POST['start_date'])) {
        $redirect_params[] = 'start_date=' . urlencode($_POST['start_date']);
    }
    if (isset($_POST['end_date']) && !empty($_POST['end_date'])) {
        $redirect_params[] = 'end_date=' . urlencode($_POST['end_date']);
    }
    
    $redirect_url = 'students.php';
    if (!empty($redirect_params)) {
        $redirect_url .= '?' . implode('&', $redirect_params);
    }
    
    header("Location: " . $redirect_url);
    exit();
}

// Handle removing student from batch
if (isset($_GET['remove_batch'])) {
    $student_id = $_GET['remove_batch'];
    
    $remove_sql = "UPDATE students SET batch_id = NULL WHERE student_id = ?";
    $stmt = $conn->prepare($remove_sql);
    $stmt->bind_param("s", $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Student removed from batch successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error removing student from batch: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    
    // Preserve filter parameters in redirect
    $redirect_params = [];
    if (isset($_GET['filter_course']) && !empty($_GET['filter_course'])) {
        $redirect_params[] = 'filter_course=' . urlencode($_GET['filter_course']);
    }
    if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
        $redirect_params[] = 'start_date=' . urlencode($_GET['start_date']);
    }
    if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
        $redirect_params[] = 'end_date=' . urlencode($_GET['end_date']);
    }
    
    $redirect_url = 'students.php';
    if (!empty($redirect_params)) {
        $redirect_url .= '?' . implode('&', $redirect_params);
    }
    
    header("Location: " . $redirect_url);
    exit();
}

// Handle course filter and date range filter
$selected_course = isset($_GET['filter_course']) ? $_GET['filter_course'] : 'All';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Modified query to include batch information
$query = "SELECT s.*, b.batch_name, b.batch_code 
          FROM students s 
          LEFT JOIN batches b ON s.batch_id = b.id 
          WHERE 1=1";

if ($selected_course != 'All') {
    $query .= " AND s.course = ?";
}

if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND s.created_at BETWEEN ? AND ?";
}

$query .= " ORDER BY s.created_at DESC";

// Preparing the final query based on the conditions
$stmt = $conn->prepare($query);

if ($selected_course != 'All' && !empty($start_date) && !empty($end_date)) {
    $stmt->bind_param("sss", $selected_course, $start_date, $end_date);
} elseif ($selected_course != 'All') {
    $stmt->bind_param("s", $selected_course);
} elseif (!empty($start_date) && !empty($end_date)) {
    $stmt->bind_param("ss", $start_date, $end_date);
}


$stmt->execute();
$result = $stmt->get_result();

// Get all active batches for assignment dropdown
$batches_query = "SELECT b.*, c.course_name 
                  FROM batches b 
                  LEFT JOIN courses c ON b.course_id = c.id 
                  WHERE b.status = 'Active' 
                  ORDER BY b.batch_name";
$batches_result = $conn->query($batches_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo getThemeFavicon($active_theme); ?>" type="image/x-icon">
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
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
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
        .batch-info {
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .batch-info p {
            margin: 5px 0;
            color: #475569;
        }
        .batch-info strong {
            color: #1e293b;
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-logo">
            <img src="<?php echo APP_URL . '/' . $theme_logo; ?>" alt="NIELIT Logo">
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
                <a href="students.php" class="nav-link active">
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
                <h4><i class="fas fa-users"></i> Manage Students</h4>
                <small>View and manage all registered students</small>
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
            <!-- Toast notifications will appear here automatically -->

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $total_students_count; ?></h3>
                    <p class="stat-label">Total Students</p>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $pending_students_count; ?></h3>
                    <p class="stat-label">Pending Approval</p>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $active_students_count; ?></h3>
                    <p class="stat-label">Active Students</p>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-filter"></i> Filter Students
                    </h5>
                </div>
                
                <form method="GET" action="students.php">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 16px; align-items: end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Filter by Course</label>
                            <select name="filter_course" class="form-select">
                                <option value="All" <?php if ($selected_course == 'All') echo 'selected'; ?>>All Courses</option>
                                <?php
                                $courses_result->data_seek(0);
                                while ($course = $courses_result->fetch_assoc()) {
                                    $course_name = $course['course_name'];
                                    echo "<option value=\"$course_name\" " . ($selected_course == $course_name ? 'selected' : '') . ">{$course_name}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-users"></i> All Students
                    </h5>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span id="selected-count" style="color: #64748b; font-size: 14px; display: none;">
                            <i class="fas fa-check-square"></i> <span id="count-number">0</span> selected
                        </span>
                        <button type="button" id="bulk-assign-btn" class="btn btn-primary" style="display: none;">
                            <i class="fas fa-layer-group"></i> Bulk Assign to Batch
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="select-all" title="Select All">
                                </th>
                                <th>Sl. No.</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Course</th>
                                <th>Batch</th>
                                <th>Status</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sl_no = 1;
                            while ($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td>
                                        <?php if (empty($row['batch_name'])): ?>
                                            <input type="checkbox" class="student-checkbox" 
                                                   value="<?php echo $row['student_id']; ?>"
                                                   data-course="<?php echo htmlspecialchars($row['course']); ?>">
                                        <?php else: ?>
                                            <span style="color: #cbd5e1;" title="Already assigned to a batch">
                                                <i class="fas fa-check-circle"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $sl_no++; ?></td>
                                    <td><strong><?php echo $row['student_id']; ?></strong></td>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo $row['email']; ?></td>
                                    <td><?php echo $row['mobile']; ?></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo $row['course']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['batch_name'])): ?>
                                            <span class="badge badge-success" title="<?php echo htmlspecialchars($row['batch_code']); ?>">
                                                <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($row['batch_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-minus-circle"></i> Not Assigned
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = strtolower($row['status']);
                                        $badge_class = 'badge-secondary';
                                        
                                        if ($status == 'active') {
                                            $badge_class = 'badge-success';
                                        } elseif ($status == 'pending') {
                                            $badge_class = 'badge-warning';
                                        } elseif ($status == 'rejected') {
                                            $badge_class = 'badge-danger';
                                        } elseif ($status == 'inactive') {
                                            $badge_class = 'badge-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <?php if (strtolower($row['status']) == 'pending'): ?>
                                            <a href="javascript:void(0);" 
                                               class="btn btn-success btn-sm approve-student-btn" 
                                               title="Approve Student"
                                               data-student-id="<?php echo $row['student_id']; ?>"
                                               data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                               data-url="students.php?approve_id=<?php echo $row['student_id']; ?><?php 
                                                if ($selected_course != 'All') echo '&filter_course=' . urlencode($selected_course);
                                                if (!empty($start_date)) echo '&start_date=' . urlencode($start_date);
                                                if (!empty($end_date)) echo '&end_date=' . urlencode($end_date);
                                            ?>">
                                                <i class="fas fa-check"></i> Approve
                                            </a>
                                            <a href="javascript:void(0);" 
                                               class="btn btn-danger btn-sm reject-student-btn" 
                                               title="Reject Student"
                                               data-student-id="<?php echo $row['student_id']; ?>"
                                               data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                               data-url="students.php?reject_id=<?php echo $row['student_id']; ?><?php 
                                                if ($selected_course != 'All') echo '&filter_course=' . urlencode($selected_course);
                                                if (!empty($start_date)) echo '&start_date=' . urlencode($start_date);
                                                if (!empty($end_date)) echo '&end_date=' . urlencode($end_date);
                                            ?>">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        <?php else: ?>
                                            <a href="edit_student.php?id=<?php echo $row['student_id']; ?><?php 
                                                if ($selected_course != 'All') echo '&filter_course=' . urlencode($selected_course);
                                                if (!empty($start_date)) echo '&start_date=' . urlencode($start_date);
                                                if (!empty($end_date)) echo '&end_date=' . urlencode($end_date);
                                            ?>" class="btn btn-warning btn-sm" title="Edit Student">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($row['batch_name'])): ?>
                                            <a href="javascript:void(0);" 
                                               class="btn btn-secondary btn-sm remove-batch-btn" 
                                               title="Remove from Batch"
                                               data-student-id="<?php echo $row['student_id']; ?>"
                                               data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                               data-batch-name="<?php echo htmlspecialchars($row['batch_name']); ?>"
                                               data-url="students.php?remove_batch=<?php echo $row['student_id']; ?><?php 
                                                if ($selected_course != 'All') echo '&filter_course=' . urlencode($selected_course);
                                                if (!empty($start_date)) echo '&start_date=' . urlencode($start_date);
                                                if (!empty($end_date)) echo '&end_date=' . urlencode($end_date);
                                            ?>">
                                                <i class="fas fa-unlink"></i>
                                            </a>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn btn-info btn-sm assign-batch-btn" 
                                                    title="Assign to Batch"
                                                    data-student-id="<?php echo $row['student_id']; ?>"
                                                    data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                    data-course="<?php echo htmlspecialchars($row['course']); ?>">
                                                <i class="fas fa-plus-circle"></i> Assign Batch
                                            </button>
                                        <?php endif; ?>
                                        
                                        <a href="view_student_documents.php?id=<?php echo $row['student_id']; ?><?php 
                                            if ($selected_course != 'All') echo '&filter_course=' . urlencode($selected_course);
                                            if (!empty($start_date)) echo '&start_date=' . urlencode($start_date);
                                            if (!empty($end_date)) echo '&end_date=' . urlencode($end_date);
                                        ?>" class="btn btn-info btn-sm" title="View Documents">
                                            <i class="fas fa-folder-open"></i>
                                        </a>
                                        <a href="download_student_form.php?id=<?php echo $row['student_id']; ?>" class="btn btn-success btn-sm" title="Download Form" target="_blank">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="javascript:void(0);" 
                                           class="btn btn-danger btn-sm delete-student-btn" 
                                           title="Delete Student"
                                           data-student-id="<?php echo $row['student_id']; ?>"
                                           data-student-name="<?php echo htmlspecialchars($row['name']); ?>"
                                           data-url="students.php?delete_id=<?php echo $row['student_id']; ?><?php 
                                            if ($selected_course != 'All') echo '&filter_course=' . urlencode($selected_course);
                                            if (!empty($start_date)) echo '&start_date=' . urlencode($start_date);
                                            if (!empty($end_date)) echo '&end_date=' . urlencode($end_date);
                                        ?>">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Batch Assignment Modal -->
<div id="batchModal" class="batch-modal">
    <div class="batch-modal-content">
        <div class="batch-modal-header">
            <h3><i class="fas fa-layer-group"></i> Assign Student to Batch</h3>
            <button class="close-modal" onclick="closeBatchModal()">&times;</button>
        </div>
        
        <div class="batch-info">
            <p><strong>Student:</strong> <span id="modal-student-name"></span></p>
            <p><strong>Course:</strong> <span id="modal-course"></span></p>
        </div>
        
        <form method="POST" action="students.php">
            <input type="hidden" name="student_id" id="modal-student-id">
            <input type="hidden" name="filter_course" value="<?php echo htmlspecialchars($selected_course); ?>">
            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            
            <div class="form-group">
                <label class="form-label">Select Batch</label>
                <select name="batch_id" id="modal-batch-select" class="form-control" required>
                    <option value="">-- Select a Batch --</option>
                    <?php 
                    if ($batches_result && $batches_result->num_rows > 0):
                        while ($batch = $batches_result->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $batch['id']; ?>" data-course="<?php echo htmlspecialchars($batch['course_name']); ?>">
                            <?php echo htmlspecialchars($batch['batch_name']); ?> 
                            (<?php echo htmlspecialchars($batch['batch_code']); ?>)
                        </option>
                    <?php 
                        endwhile;
                    endif;
                    ?>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="assign_batch" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-check"></i> Assign to Batch
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeBatchModal()" style="flex: 1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Batch Assignment Modal -->
<div id="bulkBatchModal" class="batch-modal">
    <div class="batch-modal-content">
        <div class="batch-modal-header">
            <h3><i class="fas fa-layer-group"></i> Bulk Assign Students to Batch</h3>
            <button class="close-modal" onclick="closeBulkBatchModal()">&times;</button>
        </div>
        
        <div class="batch-info">
            <p><strong>Selected Students:</strong> <span id="bulk-modal-count">0</span></p>
            <p style="font-size: 12px; color: #64748b; margin-top: 5px;">
                <i class="fas fa-info-circle"></i> All selected students will be assigned to the same batch
            </p>
        </div>
        
        <form method="POST" action="students.php" id="bulk-assign-form">
            <input type="hidden" name="filter_course" value="<?php echo htmlspecialchars($selected_course); ?>">
            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            <div id="bulk-student-ids"></div>
            
            <div class="form-group">
                <label class="form-label">Select Batch</label>
                <select name="batch_id" id="bulk-modal-batch-select" class="form-control" required>
                    <option value="">-- Select a Batch --</option>
                    <?php 
                    // Reset the result pointer
                    $batches_query = "SELECT b.*, c.course_name 
                                      FROM batches b 
                                      LEFT JOIN courses c ON b.course_id = c.id 
                                      WHERE b.status = 'Active' 
                                      ORDER BY b.batch_name";
                    $batches_result2 = $conn->query($batches_query);
                    
                    if ($batches_result2 && $batches_result2->num_rows > 0):
                        while ($batch = $batches_result2->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $batch['id']; ?>" data-course="<?php echo htmlspecialchars($batch['course_name']); ?>">
                            <?php echo htmlspecialchars($batch['batch_name']); ?> 
                            (<?php echo htmlspecialchars($batch['batch_code']); ?>) - 
                            <?php echo htmlspecialchars($batch['course_name']); ?>
                        </option>
                    <?php 
                        endwhile;
                    endif;
                    ?>
                </select>
                <small style="color: #64748b; margin-top: 5px; display: block;">
                    <i class="fas fa-lightbulb"></i> Tip: Batches are filtered based on the courses of selected students
                </small>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="bulk_assign_batch" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-check"></i> Assign All to Batch
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeBulkBatchModal()" style="flex: 1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
// Show toast notification if there's a session message
<?php if (isset($_SESSION['message'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const messageType = '<?php echo isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'success'; ?>';
        const message = '<?php echo addslashes($_SESSION['message']); ?>';
        
        // Map message types to toast types
        const toastType = messageType === 'danger' ? 'error' : messageType;
        
        toast[toastType](message);
    });
    <?php 
    unset($_SESSION['message']); 
    unset($_SESSION['message_type']);
    ?>
<?php endif; ?>

// Batch Assignment Modal Functions
function openBatchModal(studentId, studentName, course) {
    document.getElementById('modal-student-id').value = studentId;
    document.getElementById('modal-student-name').textContent = studentName;
    document.getElementById('modal-course').textContent = course;
    
    // Filter batches by course
    const batchSelect = document.getElementById('modal-batch-select');
    const options = batchSelect.querySelectorAll('option');
    
    console.log('Student Course:', course);
    let matchCount = 0;
    
    options.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
            return;
        }
        
        const optionCourse = option.getAttribute('data-course');
        console.log('Batch Option:', option.text, '| Course:', optionCourse, '| Match:', optionCourse === course);
        
        if (optionCourse === course) {
            option.style.display = 'block';
            matchCount++;
        } else {
            option.style.display = 'none';
        }
    });
    
    console.log('Total matching batches:', matchCount);
    
    batchSelect.value = '';
    document.getElementById('batchModal').style.display = 'block';
}

function closeBatchModal() {
    document.getElementById('batchModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('batchModal');
    const bulkModal = document.getElementById('bulkBatchModal');
    if (event.target === modal) {
        closeBatchModal();
    }
    if (event.target === bulkModal) {
        closeBulkBatchModal();
    }
}

// Bulk Batch Assignment Functions
function openBulkBatchModal() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    const count = checkboxes.length;
    
    if (count === 0) {
        toast.warning('Please select at least one student');
        return;
    }
    
    // Update count
    document.getElementById('bulk-modal-count').textContent = count;
    
    // Clear previous student IDs
    const container = document.getElementById('bulk-student-ids');
    container.innerHTML = '';
    
    // Collect courses of selected students
    const courses = new Set();
    checkboxes.forEach(checkbox => {
        // Add hidden input for each student ID
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'student_ids[]';
        input.value = checkbox.value;
        container.appendChild(input);
        
        // Collect course
        const course = checkbox.getAttribute('data-course');
        if (course) {
            courses.add(course);
        }
    });
    
    // Filter batch options by courses
    const batchSelect = document.getElementById('bulk-modal-batch-select');
    const options = batchSelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
            return;
        }
        
        const optionCourse = option.getAttribute('data-course');
        if (courses.has(optionCourse)) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
    
    batchSelect.value = '';
    document.getElementById('bulkBatchModal').style.display = 'block';
}

function closeBulkBatchModal() {
    document.getElementById('bulkBatchModal').style.display = 'none';
}

// Update selected count and show/hide bulk assign button
function updateSelectionUI() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    const count = checkboxes.length;
    const countDisplay = document.getElementById('selected-count');
    const bulkBtn = document.getElementById('bulk-assign-btn');
    const countNumber = document.getElementById('count-number');
    
    if (count > 0) {
        countDisplay.style.display = 'inline';
        bulkBtn.style.display = 'inline-block';
        countNumber.textContent = count;
    } else {
        countDisplay.style.display = 'none';
        bulkBtn.style.display = 'none';
    }
}

// Attach event listeners to assign batch buttons
document.addEventListener('DOMContentLoaded', function() {
    const assignButtons = document.querySelectorAll('.assign-batch-btn');
    assignButtons.forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.getAttribute('data-student-id');
            const studentName = this.getAttribute('data-student-name');
            const course = this.getAttribute('data-course');
            openBatchModal(studentId, studentName, course);
        });
    });
    
    // Handle remove batch buttons with modern confirmation
    const removeButtons = document.querySelectorAll('.remove-batch-btn');
    removeButtons.forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            const studentName = this.getAttribute('data-student-name');
            const batchName = this.getAttribute('data-batch-name');
            const url = this.getAttribute('data-url');
            
            const confirmed = await showConfirm({
                title: 'Remove from Batch',
                message: `Are you sure you want to remove <strong>${studentName}</strong> from batch <strong>${batchName}</strong>?`,
                confirmText: 'Remove',
                cancelText: 'Cancel',
                type: 'warning'
            });
            
            if (confirmed) {
                // Show loading toast
                const loadingToast = toast.loading('Removing student from batch...');
                
                // Redirect to remove URL
                window.location.href = url;
            }
        });
    });
    
    // Handle delete student buttons with modern confirmation
    const deleteButtons = document.querySelectorAll('.delete-student-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            const studentName = this.getAttribute('data-student-name');
            const studentId = this.getAttribute('data-student-id');
            const url = this.getAttribute('data-url');
            
            const confirmed = await showConfirm({
                title: 'Delete Student',
                message: `Are you sure you want to delete <strong>${studentName}</strong> (${studentId})? This action cannot be undone.`,
                confirmText: 'Delete',
                cancelText: 'Cancel',
                type: 'danger'
            });
            
            if (confirmed) {
                // Show loading toast
                const loadingToast = toast.loading('Deleting student...');
                
                // Redirect to delete URL
                window.location.href = url;
            }
        });
    });
    
    // Handle approve student buttons with modern confirmation
    const approveButtons = document.querySelectorAll('.approve-student-btn');
    approveButtons.forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            const studentName = this.getAttribute('data-student-name');
            const studentId = this.getAttribute('data-student-id');
            const url = this.getAttribute('data-url');
            
            const confirmed = await showConfirm({
                title: 'Approve Student',
                message: `Approve <strong>${studentName}</strong> (${studentId})? They will be able to login to the student portal.`,
                confirmText: 'Approve',
                cancelText: 'Cancel',
                type: 'warning'
            });
            
            if (confirmed) {
                // Show loading toast
                const loadingToast = toast.loading('Approving student...');
                
                // Redirect to approve URL
                window.location.href = url;
            }
        });
    });
    
    // Handle reject student buttons with modern confirmation
    const rejectButtons = document.querySelectorAll('.reject-student-btn');
    rejectButtons.forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            const studentName = this.getAttribute('data-student-name');
            const studentId = this.getAttribute('data-student-id');
            const url = this.getAttribute('data-url');
            
            const confirmed = await showConfirm({
                title: 'Reject Student',
                message: `Are you sure you want to reject <strong>${studentName}</strong> (${studentId})? This will mark their registration as rejected.`,
                confirmText: 'Reject',
                cancelText: 'Cancel',
                type: 'danger'
            });
            
            if (confirmed) {
                // Show loading toast
                const loadingToast = toast.loading('Rejecting student...');
                
                // Redirect to reject URL
                window.location.href = url;
            }
        });
    });
    
    // Handle select all checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectionUI();
        });
    }
    
    // Handle individual checkboxes
    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
    studentCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectionUI();
            
            // Update select all checkbox state
            const allCheckboxes = document.querySelectorAll('.student-checkbox');
            const checkedCheckboxes = document.querySelectorAll('.student-checkbox:checked');
            const selectAll = document.getElementById('select-all');
            
            if (selectAll) {
                selectAll.checked = allCheckboxes.length === checkedCheckboxes.length && allCheckboxes.length > 0;
            }
        });
    });
    
    // Handle bulk assign button
    const bulkAssignBtn = document.getElementById('bulk-assign-btn');
    if (bulkAssignBtn) {
        bulkAssignBtn.addEventListener('click', function() {
            openBulkBatchModal();
        });
    }
});
</script>

</body>
</html>

<?php
$conn->close();
?>
