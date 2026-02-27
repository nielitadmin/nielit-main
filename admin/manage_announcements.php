<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");
    exit();
}

// Handle Add Announcement
if (isset($_POST['add_announcement'])) {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $type = $_POST['type'];
    $target_audience = $_POST['target_audience'];
    $course_code = $_POST['course_code'] ?? null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $created_by = $_SESSION['admin'];

    $sql = "INSERT INTO announcements (title, message, type, target_audience, course_code, is_active, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssis", $title, $message, $type, $target_audience, $course_code, $is_active, $created_by);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Announcement added successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error adding announcement!";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: manage_announcements.php");
    exit();
}

// Handle Edit Announcement
if (isset($_POST['edit_announcement'])) {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $message = $_POST['message'];
    $type = $_POST['type'];
    $target_audience = $_POST['target_audience'];
    $course_code = $_POST['course_code'] ?? null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $sql = "UPDATE announcements SET title=?, message=?, type=?, target_audience=?, course_code=?, is_active=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssii", $title, $message, $type, $target_audience, $course_code, $is_active, $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Announcement updated successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating announcement!";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: manage_announcements.php");
    exit();
}

// Handle Delete Announcement
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $sql = "DELETE FROM announcements WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Announcement deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting announcement!";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: manage_announcements.php");
    exit();
}

// Handle Toggle Active Status
if (isset($_GET['toggle_id'])) {
    $id = $_GET['toggle_id'];
    $sql = "UPDATE announcements SET is_active = NOT is_active WHERE id=?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: manage_announcements.php");
    exit();
}

// Fetch all announcements
$sql = "SELECT * FROM announcements ORDER BY created_at DESC";
$result = $conn->query($sql);

// Fetch courses for dropdown
$sql_courses = "SELECT DISTINCT course_code, course_name FROM courses ORDER BY course_name";
$courses_result = $conn->query($sql_courses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <style>
        /* Global Fixes */
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        /* Sidebar Fixes */
        .admin-sidebar {
            width: 260px !important;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-logo {
            padding: 1.5rem;
            text-align: center;
        }
        
        .sidebar-logo img {
            max-width: 80px;
            margin-bottom: 10px;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-item {
            margin: 4px 1rem;
        }
        
        .nav-link {
            display: flex !important;
            align-items: center !important;
            padding: 12px 16px !important;
            width: 100% !important;
            text-align: left !important;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 500;
        }
        
        .nav-link i {
            width: 20px !important;
            min-width: 20px !important;
            margin-right: 12px !important;
            text-align: center !important;
            flex-shrink: 0 !important;
        }
        
        .nav-link span {
            flex: 1 !important;
            white-space: nowrap !important;
        }
        
        /* Main Content Area */
        .admin-main {
            margin-left: 260px;
            min-height: 100vh;
            background-color: #f5f7fa;
        }
        
        /* Top Bar Fixes */
        .admin-topbar {
            background: white;
            padding: 1.25rem 2rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .topbar-left {
            flex: 1;
            min-width: 250px;
        }
        
        .topbar-left h4 {
            margin: 0 0 5px 0;
            color: #1f2937;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .topbar-left small {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .admin-user {
            color: #4b5563;
            font-size: 0.9rem;
            padding: 8px 12px;
            background-color: #f3f4f6;
            border-radius: 8px;
        }
        
        /* Content Area */
        .admin-content {
            padding: 2rem;
            color: #333;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Card Styling */
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
        }
        
        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1.25rem 1.5rem;
            text-align: left !important;
        }
        
        .card-title {
            margin: 0;
            color: #1f2937;
            font-size: 1.15rem;
            font-weight: 600;
            text-align: left !important;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Table Styling */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
        }
        
        .table {
            color: #333;
            margin-bottom: 0;
            width: 100%;
        }
        
        .table td, .table th {
            color: #333;
            vertical-align: middle;
            padding: 1rem 0.75rem;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
        }
        
        .table tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table tbody tr:hover {
            background-color: #f9fafb;
        }
        
        .table tbody tr:last-child {
            border-bottom: none;
        }
        
        /* Badge Styling */
        .badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            font-weight: 500;
            border-radius: 6px;
        }
        
        /* Button Styling */
        .btn-sm {
            margin-right: 5px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .btn-sm:last-child {
            margin-right: 0;
        }
        
        .btn-primary {
            background-color: #2563eb;
            border-color: #2563eb;
        }
        
        .btn-primary:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
        }
        
        /* Alert Styling */
        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        /* Modal Styling */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1.25rem 1.5rem;
        }
        
        .modal-title {
            color: #1f2937;
            font-weight: 600;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
        }
        
        /* Form Styling */
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 0.625rem 0.875rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .admin-topbar {
                padding: 1rem;
            }
            
            .topbar-left h4 {
                font-size: 1.25rem;
            }
            
            .admin-content {
                padding: 1rem;
            }
            
            .table {
                font-size: 0.875rem;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: #6b7280;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-logo">
            <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo">
            <h5 style="color: white; margin: 10px 0 0 0; font-size: 1.1rem;">NIELIT Admin</h5>
            <small style="color: rgba(255, 255, 255, 0.7);">Bhubaneswar</small>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="students.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/manage_batches.php" class="nav-link">
                    <i class="fas fa-layer-group"></i>
                    <span>Batches</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php" class="nav-link">
                    <i class="fas fa-project-diagram"></i>
                    <span>Schemes/Projects</span>
                </a>
            </div>
            
            <div class="nav-divider"></div>
            <div class="nav-section-title">System Settings</div>
            
            <div class="nav-item">
                <a href="manage_centres.php" class="nav-link">
                    <i class="fas fa-building"></i>
                    <span>Training Centres</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_themes.php" class="nav-link">
                    <i class="fas fa-palette"></i>
                    <span>Themes</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_homepage.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Homepage Content</span>
                </a>
            </div>
            
            <div class="nav-divider"></div>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/approve_students.php" class="nav-link">
                    <i class="fas fa-user-check"></i>
                    <span>Approve Students</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_announcements.php" class="nav-link active">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="add_admin.php" class="nav-link">
                    <i class="fas fa-user-shield"></i>
                    <span>Add Admin</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="reset_password.php" class="nav-link">
                    <i class="fas fa-key"></i>
                    <span>Reset Password</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/index.php" class="nav-link">
                    <i class="fas fa-globe"></i>
                    <span>View Website</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="admin-main">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-bullhorn"></i> Manage Announcements</h4>
                <small>Create and manage announcements for students</small>
            </div>
            <div class="topbar-right">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                    <i class="fas fa-plus"></i> Add Announcement
                </button>
                <span class="admin-user">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['admin']); ?>
                </span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="admin-content">
            <!-- Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            <?php endif; ?>

            <!-- Announcements List -->
            <div class="content-card">
                <div class="card-header" style="text-align: left;">
                    <h5 class="card-title" style="text-align: left;"><i class="fas fa-list"></i> All Announcements</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th style="min-width: 250px;">Title</th>
                                    <th style="width: 100px;">Type</th>
                                    <th style="width: 120px;">Target</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 120px;">Created By</th>
                                    <th style="width: 120px;">Date</th>
                                    <th style="width: 180px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo $row['id']; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo substr(htmlspecialchars($row['message']), 0, 60); ?>...</small>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = [
                                                'info' => 'bg-info',
                                                'success' => 'bg-success',
                                                'warning' => 'bg-warning text-dark',
                                                'danger' => 'bg-danger'
                                            ];
                                            $type = isset($row['type']) ? $row['type'] : 'info';
                                            ?>
                                            <span class="badge <?php echo $badge_class[$type]; ?>">
                                                <?php echo ucfirst($type); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($row['target_audience'] == 'specific_course') {
                                                echo '<span class="badge bg-secondary">' . htmlspecialchars($row['course_code']) . '</span>';
                                            } else {
                                                echo '<span class="badge bg-primary">' . ucfirst(str_replace('_', ' ', $row['target_audience'])) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $is_active = isset($row['is_active']) ? $row['is_active'] : 0;
                                            if ($is_active): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['created_by']); ?></td>
                                        <td><small><?php echo date('d M Y', strtotime($row['created_at'])); ?></small></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-btn" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-title="<?php echo htmlspecialchars($row['title']); ?>"
                                                    data-message="<?php echo htmlspecialchars($row['message']); ?>"
                                                    data-type="<?php echo $type; ?>"
                                                    data-target="<?php echo $row['target_audience']; ?>"
                                                    data-course="<?php echo $row['course_code'] ?? ''; ?>"
                                                    data-active="<?php echo $is_active; ?>"
                                                    title="Edit Announcement">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?toggle_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" title="Toggle Status">
                                                <i class="fas fa-toggle-<?php echo ($is_active ? 'on' : 'off'); ?>"></i>
                                            </a>
                                            <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this announcement?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="empty-state">
                                                <i class="fas fa-bullhorn"></i>
                                                <p>No announcements yet. Click "Add Announcement" to create one.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Announcement Modal -->
    <div class="modal fade" id="addAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="Enter announcement title">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message *</label>
                            <textarea name="message" class="form-control" rows="4" required placeholder="Enter announcement message"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Type *</label>
                                <select name="type" class="form-select" required>
                                    <option value="info">Info (Blue)</option>
                                    <option value="success">Success (Green)</option>
                                    <option value="warning">Warning (Yellow)</option>
                                    <option value="danger">Danger (Red)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Target Audience *</label>
                                <select name="target_audience" id="target_audience_add" class="form-select" required onchange="toggleCourseSelect('add')">
                                    <option value="all">All Students</option>
                                    <option value="students">Students Only</option>
                                    <option value="specific_course">Specific Course</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3" id="course_select_add" style="display:none;">
                            <label class="form-label">Select Course</label>
                            <select name="course_code" class="form-select">
                                <option value="">Select Course</option>
                                <?php 
                                if ($courses_result) {
                                    $courses_result->data_seek(0);
                                    while ($course = $courses_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $course['course_code']; ?>">
                                        <?php echo $course['course_name']; ?> (<?php echo $course['course_code']; ?>)
                                    </option>
                                <?php 
                                    endwhile;
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active_add" checked>
                            <label class="form-check-label" for="is_active_add">Active (Visible to students)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_announcement" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Announcement Modal -->
    <div class="modal fade" id="editAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message *</label>
                            <textarea name="message" id="edit_message" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Type *</label>
                                <select name="type" id="edit_type" class="form-select" required>
                                    <option value="info">Info (Blue)</option>
                                    <option value="success">Success (Green)</option>
                                    <option value="warning">Warning (Yellow)</option>
                                    <option value="danger">Danger (Red)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Target Audience *</label>
                                <select name="target_audience" id="edit_target_audience" class="form-select" required onchange="toggleCourseSelect('edit')">
                                    <option value="all">All Students</option>
                                    <option value="students">Students Only</option>
                                    <option value="specific_course">Specific Course</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3" id="course_select_edit" style="display:none;">
                            <label class="form-label">Select Course</label>
                            <select name="course_code" id="edit_course_code" class="form-select">
                                <option value="">Select Course</option>
                                <?php 
                                if ($courses_result) {
                                    $courses_result->data_seek(0);
                                    while ($course = $courses_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $course['course_code']; ?>">
                                        <?php echo $course['course_name']; ?> (<?php echo $course['course_code']; ?>)
                                    </option>
                                <?php 
                                    endwhile;
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">Active (Visible to students)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_announcement" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCourseSelect(mode) {
            const select = document.getElementById('target_audience_' + mode);
            const courseDiv = document.getElementById('course_select_' + mode);
            
            // Check if elements exist
            if (!select || !courseDiv) {
                console.warn('toggleCourseSelect: Elements not found for mode:', mode);
                return;
            }
            
            if (select.value === 'specific_course') {
                courseDiv.style.display = 'block';
            } else {
                courseDiv.style.display = 'none';
            }
        }

        function editAnnouncement(data) {
            console.log('Edit data:', data); // Debug log
            
            document.getElementById('edit_id').value = data.id || '';
            document.getElementById('edit_title').value = data.title || '';
            document.getElementById('edit_message').value = data.message || '';
            document.getElementById('edit_type').value = data.type || 'info';
            document.getElementById('edit_target_audience').value = data.target_audience || 'all';
            document.getElementById('edit_course_code').value = data.course_code || '';
            document.getElementById('edit_is_active').checked = (data.is_active == 1 || data.is_active == '1');
            
            toggleCourseSelect('edit');
            
            try {
                const modal = new bootstrap.Modal(document.getElementById('editAnnouncementModal'));
                modal.show();
            } catch (error) {
                console.error('Error opening modal:', error);
                alert('Error opening edit modal. Please refresh the page and try again.');
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure modals work properly
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('hidden.bs.modal', function () {
                    document.body.style.overflow = 'auto';
                });
            });
            
            // Attach click event to all edit buttons
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const data = {
                        id: this.getAttribute('data-id'),
                        title: this.getAttribute('data-title'),
                        message: this.getAttribute('data-message'),
                        type: this.getAttribute('data-type'),
                        target_audience: this.getAttribute('data-target'),
                        course_code: this.getAttribute('data-course'),
                        is_active: this.getAttribute('data-active')
                    };
                    editAnnouncement(data);
                });
            });
        });
    </script>
</body>
</html>