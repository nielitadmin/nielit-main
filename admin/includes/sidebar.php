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
$is_nsqf_manager = ($_SESSION['admin_role'] === 'nsqf_course_manager');
$is_front_office = ($_SESSION['admin_role'] === 'front_office_desk');
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
            <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
        
        <?php if ($is_front_office): ?>
        <!-- Front Office Desk - Students only -->
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/admin/students.php" class="nav-link <?php echo ($current_page === 'students.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Students
            </a>
        </div>
        
        <?php elseif (!$is_nsqf_manager): ?>
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/admin/students.php" class="nav-link <?php echo ($current_page === 'students.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Students
            </a>
        </div>
        <?php endif; ?>
        
        <?php if ($is_nsqf_manager): ?>
        <!-- NSQF Manager - Only Template Management -->
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/admin/manage_nsqf_templates.php" class="nav-link <?php echo ($current_page === 'manage_nsqf_templates.php') ? 'active' : ''; ?>">
                <i class="fas fa-graduation-cap"></i> Course Templates
            </a>
        </div>
        <?php elseif (!$is_front_office): ?>
        <!-- Other Roles - Full Course Management -->
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> Courses
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (!$is_nsqf_manager && !$is_front_office): ?>
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/batch_module/admin/manage_batches.php" class="nav-link <?php echo ($current_page === 'manage_batches.php') ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i> Batches
            </a>
        </div>
        <?php endif; ?>
        
        <!-- System Settings (Master Admin Only) -->
        <?php if ($is_master_admin): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">System Settings</div>
        
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/admin/manage_centres.php" class="nav-link <?php echo ($current_page === 'manage_centres.php') ? 'active' : ''; ?>">
                <i class="fas fa-building"></i> Training Centres
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/admin/manage_themes.php" class="nav-link <?php echo ($current_page === 'manage_themes.php') ? 'active' : ''; ?>">
                <i class="fas fa-palette"></i> Themes
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/admin/manage_homepage.php" class="nav-link <?php echo ($current_page === 'manage_homepage.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Homepage Content
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (!$is_nsqf_manager && !$is_front_office): ?>
        <div class="nav-divider"></div>
        
        <!-- Student Approval (Non-NSQF, Non-Front-Office Roles Only) -->
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/batch_module/admin/approve_students.php" class="nav-link <?php echo ($current_page === 'approve_students.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i> Approve Students
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Admin Management (Master Admin Only) -->
        <?php if ($is_master_admin): ?>
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/admin/add_admin.php" class="nav-link <?php echo ($current_page === 'add_admin.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i> Add Admin
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/admin/manage_admins.php" class="nav-link <?php echo ($current_page === 'manage_admins.php') ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i> Manage Admins
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/admin/reset_password.php" class="nav-link <?php echo ($current_page === 'reset_password.php') ? 'active' : ''; ?>">
                <i class="fas fa-key"></i> Reset Password
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/admin/manage_course_assignments.php" class="nav-link <?php echo ($current_page === 'manage_course_assignments.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i> Course Assignments
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo APP_URL; ?>/admin/view_otp_logs.php" class="nav-link <?php echo ($current_page === 'view_otp_logs.php') ? 'active' : ''; ?>">
                <i class="fas fa-list-alt"></i> OTP Logs
            </a>
        </div>
        <?php endif; ?>
        
        
        <?php if (!$is_nsqf_manager && !$is_front_office): ?>
        <!-- This section is now empty as Reset Password moved to Master Admin section -->
        <?php endif; ?>
        
        <div class="nav-divider"></div>
        
        <!-- Common Links -->
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
