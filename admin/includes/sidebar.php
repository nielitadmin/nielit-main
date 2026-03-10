<?php
// Sidebar navigation with role-based access control
// This file should be included in all admin pages

// Include config for APP_URL and other constants
require_once __DIR__ . '/../../config/config.php';

// Ensure session is started and role is set
if (!isset($_SESSION['admin_role'])) {
    $_SESSION['admin_role'] = 'course_coordinator'; // Default fallback
}

$is_master_admin = ($_SESSION['admin_role'] === 'master_admin');
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="admin-sidebar">
    <div class="sidebar-logo">
        <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo">
        <h5>NIELIT Admin</h5>
        <small>Bhubaneswar</small>
    </div>
    
    <nav class="sidebar-nav">
        <!-- Core Features (All Roles) -->
        <div class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
        <div class="nav-item">
            <a href="students.php" class="nav-link <?php echo ($current_page === 'students.php') ? 'active' : ''; ?>">
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
        
        <!-- System Settings (Master Admin Only) -->
        <?php if ($is_master_admin): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">System Settings</div>
        
        <div class="nav-item">
            <a href="manage_centres.php" class="nav-link <?php echo ($current_page === 'manage_centres.php') ? 'active' : ''; ?>">
                <i class="fas fa-building"></i> Training Centres
            </a>
        </div>
        <div class="nav-item">
            <a href="manage_themes.php" class="nav-link <?php echo ($current_page === 'manage_themes.php') ? 'active' : ''; ?>">
                <i class="fas fa-palette"></i> Themes
            </a>
        </div>
        <div class="nav-item">
            <a href="manage_homepage.php" class="nav-link <?php echo ($current_page === 'manage_homepage.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Homepage Content
            </a>
        </div>
        <?php endif; ?>
        
        <div class="nav-divider"></div>
        
        <!-- Student Approval (All Roles) -->
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/batch_module/admin/approve_students.php" class="nav-link">
                <i class="fas fa-user-check"></i> Approve Students
            </a>
        </div>
        
        <!-- Admin Management (Master Admin Only) -->
        <?php if ($is_master_admin): ?>
        <div class="nav-item">
            <a href="add_admin.php" class="nav-link <?php echo ($current_page === 'add_admin.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i> Add Admin
            </a>
        </div>
        <div class="nav-item">
            <a href="manage_admins.php" class="nav-link <?php echo ($current_page === 'manage_admins.php') ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i> Manage Admins
            </a>
        </div>
        <div class="nav-item">
            <a href="manage_course_assignments.php" class="nav-link <?php echo ($current_page === 'manage_course_assignments.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i> Course Assignments
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Reset Password (All Roles) -->
        <div class="nav-item">
            <a href="reset_password.php" class="nav-link <?php echo ($current_page === 'reset_password.php') ? 'active' : ''; ?>">
                <i class="fas fa-key"></i> Reset Password
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <!-- Common Links -->
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
