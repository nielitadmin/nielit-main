<?php
// Sidebar navigation with role-based access control
// This file should be included in all admin pages

// Include config for APP_URL and other constants
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/url_helper.php';
require_once __DIR__ . '/../../includes/sidebar_theme_helper.php';

// Ensure session is started and role is set
if (!isset($_SESSION['admin_role'])) {
    $_SESSION['admin_role'] = 'course_coordinator'; // Default fallback
}

$is_master_admin = ($_SESSION['admin_role'] === 'master_admin');
$is_course_coordinator = ($_SESSION['admin_role'] === 'course_coordinator');
$is_nsqf_manager = ($_SESSION['admin_role'] === 'nsqf_course_manager');
$is_front_office = ($_SESSION['admin_role'] === 'front_office_desk');
$is_placement_coordinator = ($_SESSION['admin_role'] === 'placement_coordinator');
$is_faculty = ($_SESSION['admin_role'] === 'faculty');
$current_page = basename($_SERVER['PHP_SELF']);

global $conn;
$sidebarStyleKey = getActiveSidebarTheme($conn instanceof mysqli ? $conn : null);
$sidebarStyleClass = sidebarThemeBodyClass($sidebarStyleKey);
?>

<script>
(function () {
    var cls = <?php echo json_encode($sidebarStyleClass); ?>;
    if (!cls) return;
    document.documentElement.classList.add(cls);
    if (document.body) {
        document.body.classList.add(cls);
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add(cls);
        });
    }
})();
</script>

<style id="admin-sidebar-critical">
<?php echo sidebarThemeEmitCriticalCss(); ?>
/* Dropdown styles */
.nav-item.has-dropdown .nav-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.nav-item.has-dropdown .nav-link .dropdown-icon {
    transition: transform 0.3s ease;
    font-size: 0.75rem;
}
.nav-item.has-dropdown .nav-link.active .dropdown-icon,
.nav-item.has-dropdown .nav-link:hover .dropdown-icon {
    transform: rotate(180deg);
}
.nav-item.has-dropdown .dropdown-menu {
    display: none;
    overflow: hidden;
    animation: dropdownFade 0.3s ease-in-out;
}
.nav-item.has-dropdown .dropdown-menu.show {
    display: block;
}
.nav-item.has-dropdown .dropdown-item {
    display: flex;
    align-items: center;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    font-size: 0.85rem;
    color: #8a94a6;
    text-decoration: none;
    transition: all 0.2s ease;
}
.nav-item.has-dropdown .dropdown-item:hover,
.nav-item.has-dropdown .dropdown-item.active {
    background-color: rgba(6, 182, 212, 0.1);
    color: #0ea5e9;
    border-left: 3px solid #0ea5e9;
}
.nav-item.has-dropdown .dropdown-section {
    padding: 0 0.5rem;
}
.nav-item.has-dropdown .dropdown-section-title {
    display: block;
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
}
@keyframes dropdownFade {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 500px;
    }
}
</style>

<button type="button" class="sidebar-toggle-btn" aria-label="Toggle navigation" onclick="toggleAdminSidebar()">
    <i class="fas fa-bars"></i>
</button>

<aside class="admin-sidebar <?php echo htmlspecialchars($sidebarStyleClass); ?>" id="adminSidebar">
    <div class="sidebar-logo">
        <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo">
        <h5><?php echo $is_faculty ? 'NIELIT Faculty' : 'NIELIT Admin'; ?></h5>
        <small>Bhubaneswar</small>
    </div>

    <div class="sidebar-clock" id="sidebarClock" aria-live="polite" title="India Standard Time (Asia/Kolkata)">
        <span class="sidebar-clock-time" id="sidebarClockTime">--:--:--</span>
        <span class="sidebar-clock-date" id="sidebarClockDate">Loading…</span>
        <span class="sidebar-clock-label">IST · Asia/Kolkata</span>
    </div>
    
    <nav class="sidebar-nav">
        <?php if ($is_faculty): ?>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_class_timetable'); ?>" class="nav-link <?php echo ($current_page === 'manage_class_timetable.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> Class Timetable
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_lesson_plans'); ?>" class="nav-link <?php echo in_array($current_page, ['manage_lesson_plans.php', 'edit_lesson_plan.php', 'lesson_plan_daily.php'], true) ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i> Course Action Plans
            </a>
        </div>
        <?php elseif (!$is_placement_coordinator): ?>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/dashboard'); ?>" class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
        <?php endif; ?>
        
        <?php if ($is_faculty): ?>
        <?php elseif ($is_placement_coordinator): ?>
        <!-- Placement Coordinator - Batches only (no course dashboard) -->
        <div class="nav-item">
            <a href="<?php echo app_url('batch_module/admin/manage_batches'); ?>" class="nav-link <?php echo in_array($current_page, ['manage_batches.php', 'batch_details.php'], true) ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i> Batches
            </a>
        </div>

        <?php elseif ($is_front_office): ?>
        <!-- Front Office Desk - Students only -->
        <div class="nav-item">
            <a href="<?php echo app_url('admin/students'); ?>" class="nav-link <?php echo ($current_page === 'students.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Students
            </a>
        </div>

        <?php elseif (!$is_nsqf_manager): ?>
        <div class="nav-item has-dropdown">
            <button type="button" class="nav-link <?php echo ($current_page === 'students.php' || $current_page === 'candidate_records.php') ? 'active' : ''; ?>" onclick="toggleDropdown(this)">
                <i class="fas fa-users"></i> Students
                <i class="fas fa-chevron-down dropdown-icon"></i>
            </button>
            <div class="dropdown-menu">
                <a href="<?php echo app_url('admin/students'); ?>" class="dropdown-item <?php echo ($current_page === 'students.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-friends"></i> All Students
                </a>
                <?php if ($is_master_admin): ?>
                <a href="<?php echo app_url('admin/candidate_records'); ?>" class="dropdown-item <?php echo ($current_page === 'candidate_records.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-teacher"></i> Workshop Records
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($is_nsqf_manager): ?>
        <!-- NSQF Manager - Manage NSQF Course -->
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_nsqf_templates'); ?>" class="nav-link <?php echo ($current_page === 'manage_nsqf_templates.php') ? 'active' : ''; ?>">
                <i class="fas fa-graduation-cap"></i> Manage NSQF Course
            </a>
        </div>
        <?php elseif (!$is_faculty && !$is_front_office && !$is_placement_coordinator): ?>
        <!-- Other Roles - Full Course Management -->
        <div class="nav-item">
            <a href="<?php echo app_url('admin/dashboard'); ?>" class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> Courses
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (!$is_faculty && !$is_nsqf_manager && !$is_front_office && !$is_placement_coordinator): ?>
        <div class="nav-item">
            <a href="<?php echo app_url('batch_module/admin/manage_batches'); ?>" class="nav-link <?php echo ($current_page === 'manage_batches.php') ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i> Batches
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_online_classes'); ?>" class="nav-link <?php echo ($current_page === 'manage_online_classes.php') ? 'active' : ''; ?>">
                <i class="fas fa-video"></i> Online Classes
            </a>
        </div>
        <?php endif; ?>
        
        <?php if ($is_master_admin): ?>
        <!-- Schemes/Projects - Master Admin Only -->
        <div class="nav-item">
            <a href="<?php echo app_url('schemes_module/admin/manage_schemes'); ?>" class="nav-link <?php echo ($current_page === 'manage_schemes.php') ? 'active' : ''; ?>">
                <i class="fas fa-project-diagram"></i> Schemes/Projects
            </a>
        </div>
        <!-- Staff & Faculty Directory - Master Admin Only -->
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_faculty'); ?>" class="nav-link <?php echo ($current_page === 'manage_faculty.php') ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i> Staff & Faculty
            </a>
        </div>
        <?php endif; ?>
        
        <!-- System Settings (Master Admin Only) -->
        <?php if ($is_master_admin): ?>
        <div class="nav-item has-dropdown">
            <button type="button" class="nav-link <?php echo in_array($current_page, ['manage_centres.php', 'manage_themes.php', 'manage_public_themes.php', 'manage_festival_themes.php', 'manage_sidebar_themes.php', 'manage_maintenance.php', 'manage_migrations.php', 'manage_student_kiosk.php', 'manage_activity.php', 'visitor_stats.php', 'check_student_exists.php', 'manage_homepage.php', 'manage_news.php'], true) ? 'active' : ''; ?>" onclick="toggleDropdown(this)">
                <i class="fas fa-cog"></i> System Settings
                <i class="fas fa-chevron-down dropdown-icon"></i>
            </button>
            <div class="dropdown-menu">
                <a href="<?php echo app_url('admin/manage_centres'); ?>" class="dropdown-item <?php echo ($current_page === 'manage_centres.php') ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i> Training Centres
                </a>
                <a href="<?php echo app_url('admin/manage_themes'); ?>" class="dropdown-item <?php echo ($current_page === 'manage_themes.php') ? 'active' : ''; ?>" title="Themes">
                    <i class="fas fa-palette"></i> Themes
                </a>
                <a href="<?php echo app_url('admin/manage_public_themes'); ?>" class="dropdown-item <?php echo ($current_page === 'manage_public_themes.php') ? 'active' : ''; ?>" title="Public Themes">
                    <i class="fas fa-globe"></i> Public Themes
                </a>
                <a href="<?php echo app_url('admin/manage_festival_themes'); ?>" class="dropdown-item <?php echo ($current_page === 'manage_festival_themes.php') ? 'active' : ''; ?>" title="Festival Themes">
                    <i class="fas fa-gift"></i> Festival Themes
                </a>
                <a href="<?php echo app_url('admin/manage_sidebar_themes'); ?>" class="dropdown-item <?php echo ($current_page === 'manage_sidebar_themes.php') ? 'active' : ''; ?>" title="Sidebar Themes">
                    <i class="fas fa-columns"></i> Sidebar Themes
                </a>
                <a href="<?php echo app_url('admin/manage_maintenance'); ?>" class="dropdown-item <?php echo ($current_page === 'manage_maintenance.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tools"></i> Maintenance Mode
                </a>
                <a href="<?php echo app_url('admin/manage_migrations'); ?>" class="dropdown-item <?php echo in_array($current_page, ['manage_migrations.php', 'run_migration.php'], true) ? 'active' : ''; ?>">
                    <i class="fas fa-database"></i> DB Migrations
                </a>
                <a href="<?php echo app_url('admin/manage_student_kiosk'); ?>" class="dropdown-item <?php echo ($current_page === 'manage_student_kiosk.php') ? 'active' : ''; ?>">
                    <i class="fas fa-fingerprint"></i> Student Fingerprint Kiosk
                </a>
                <a href="<?php echo app_url('admin/manage_activity'); ?>" class="dropdown-item <?php echo ($current_page === 'manage_activity.php') ? 'active' : ''; ?>">
                    <i class="fas fa-stream"></i> Activity Log
                </a>
                <a href="<?php echo app_url('admin/visitor_stats'); ?>" class="dropdown-item <?php echo ($current_page === 'visitor_stats.php') ? 'active' : ''; ?>">
                    <i class="fas fa-eye"></i> Visitor Statistics
                </a>
                <a href="<?php echo app_url('admin/check_student_exists'); ?>" class="dropdown-item <?php echo ($current_page === 'check_student_exists.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-check"></i> Student Record Inspector
                </a>
                <a href="<?php echo app_url('admin/manage_homepage'); ?>" class="dropdown-item <?php echo ($current_page === 'manage_homepage.php') ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Homepage Content
                </a>
                <a href="<?php echo app_url('admin/manage_news'); ?>" class="dropdown-item <?php echo ($current_page === 'manage_news.php') ? 'active' : ''; ?>">
                    <i class="fas fa-newspaper"></i> News & Updates
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!$is_faculty && !$is_nsqf_manager && !$is_front_office && !$is_placement_coordinator): ?>
        <div class="nav-divider"></div>
        
        <!-- Student Approval (Non-NSQF, Non-Front-Office Roles Only) -->
        <div class="nav-item">
            <a href="<?php echo app_url('batch_module/admin/approve_students'); ?>" class="nav-link <?php echo ($current_page === 'approve_students.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i> Approve Students
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Admin & Management Dropdown (Master Admin Only) - only 3 items -->
        <?php if ($is_master_admin): ?>
        <div class="nav-item has-dropdown">
            <button type="button" class="nav-link <?php echo in_array($current_page, ['add_admin.php', 'manage_admins.php', 'manage_course_assignments.php'], true) ? 'active' : ''; ?>" onclick="toggleDropdown(this)">
                <i class="fas fa-users-cog"></i> Admin & Management
                <i class="fas fa-chevron-down dropdown-icon"></i>
            </button>
            <div class="dropdown-menu">
                <a href="<?php echo app_url('admin/add_admin'); ?>" class="dropdown-item <?php echo ($current_page === 'add_admin.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-plus"></i> Add Admin
                </a>
                <a href="<?php echo app_url('admin/manage_admins'); ?>" class="dropdown-item <?php echo ($current_page === 'manage_admins.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i> Manage Admins
                </a>
                <a href="<?php echo app_url('admin/manage_course_assignments'); ?>" class="dropdown-item <?php echo ($current_page === 'manage_course_assignments.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie"></i> Course Assignments
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Student Attendance (separate section) -->
        <?php
        $canAccessAttendance = $is_master_admin;
        if (!$canAccessAttendance && isset($conn) && $conn instanceof mysqli) {
            $attHelper = __DIR__ . '/../../includes/attendance_access_helper.php';
            if (is_file($attHelper)) {
                require_once $attHelper;
                $canAccessAttendance = function_exists('admin_can_access_attendance') && admin_can_access_attendance($conn);
            }
        }
        ?>
        <?php if ($canAccessAttendance): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">Student Attendance</div>
        <?php if ($is_master_admin): ?>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_attendance_access'); ?>" class="nav-link <?php echo ($current_page === 'manage_attendance_access.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i> Grant Attendance Access
            </a>
        </div>
        <?php endif; ?>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/attendance_scanner'); ?>" class="nav-link <?php echo ($current_page === 'attendance_scanner.php') ? 'active' : ''; ?>">
                <i class="fas fa-qrcode"></i> QR Attendance Scanner
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/attendance_biometric'); ?>" class="nav-link <?php echo ($current_page === 'attendance_biometric.php') ? 'active' : ''; ?>">
                <i class="fas fa-fingerprint"></i> Fingerprint Attendance
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/attendance_fingerprint_enroll'); ?>" class="nav-link <?php echo ($current_page === 'attendance_fingerprint_enroll.php') ? 'active' : ''; ?>">
                <i class="fas fa-id-badge"></i> Fingerprint Enrolment
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/attendance_fingerprint_registry'); ?>" class="nav-link <?php echo ($current_page === 'attendance_fingerprint_registry.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i> Registered Candidates
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/attendance_biometric_report'); ?>" class="nav-link <?php echo ($current_page === 'attendance_biometric_report.php') ? 'active' : ''; ?>">
                <i class="fas fa-th"></i> Fingerprint Report
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/attendance_reports'); ?>" class="nav-link <?php echo ($current_page === 'attendance_reports.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Attendance Reports
            </a>
        </div>
        <?php endif; ?>

        <!-- Recruitment (separate section) -->
        <?php
        $canAccessRecruitment = false;
        if (isset($conn) && $conn instanceof mysqli) {
            $recHelper = __DIR__ . '/../../includes/recruitment_helper.php';
            if (is_file($recHelper)) {
                require_once $recHelper;
                $canAccessRecruitment = function_exists('recruitmentCanAccess') && recruitmentCanAccess(null, $conn);
            }
        }
        ?>
        <?php if ($canAccessRecruitment): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">Recruitment</div>
        <?php if ($is_master_admin): ?>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_recruitment_access'); ?>" class="nav-link <?php echo ($current_page === 'manage_recruitment_access.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i> Grant Recruitment Access
            </a>
        </div>
        <?php endif; ?>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/recruitment'); ?>" class="nav-link <?php echo ($current_page === 'recruitment.php') ? 'active' : ''; ?>">
                <i class="fas fa-briefcase"></i> Job Openings
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/recruitment_applications'); ?>" class="nav-link <?php echo in_array($current_page, ['recruitment_applications.php', 'recruitment_application.php'], true) ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Applications
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/recruitment_interviews'); ?>" class="nav-link <?php echo in_array($current_page, ['recruitment_interviews.php', 'recruitment_interview.php'], true) ? 'active' : ''; ?>">
                <i class="fas fa-video"></i> Interviews
            </a>
        </div>
        <?php endif; ?>

        <!-- Library (separate section) -->
        <?php
        $canAccessLibrary = false;
        if (isset($conn) && $conn instanceof mysqli) {
            $libHelper = __DIR__ . '/../../includes/library_helper.php';
            if (is_file($libHelper)) {
                require_once $libHelper;
                $canAccessLibrary = function_exists('admin_can_access_library') && admin_can_access_library($conn);
            }
        }
        $canAccessLabInstruments = false;
        $canAccessItLab = false;
        if (isset($conn) && $conn instanceof mysqli) {
            $labsHelper = __DIR__ . '/../../includes/labs_helper.php';
            if (is_file($labsHelper)) {
                require_once $labsHelper;
                $canAccessLabInstruments = function_exists('admin_can_access_lab') && admin_can_access_lab($conn, 'instrument');
                $canAccessItLab = function_exists('admin_can_access_lab') && admin_can_access_lab($conn, 'itlab');
            }
        }
        ?>
        <?php if ($canAccessLibrary): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">Library</div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/library'); ?>" class="nav-link <?php echo ($current_page === 'library.php') ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> Library Home
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/library_stock'); ?>" class="nav-link <?php echo ($current_page === 'library_stock.php') ? 'active' : ''; ?>">
                <i class="fas fa-boxes-stacked"></i> Stock Register
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/library_student_issues'); ?>" class="nav-link <?php echo ($current_page === 'library_student_issues.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i> Student Issue / Return
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/library_staff_issues'); ?>" class="nav-link <?php echo ($current_page === 'library_staff_issues.php') ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i> Staff Issue / Return
            </a>
        </div>
        <?php endif; ?>

        <!-- Lab Instruments (separate section) -->
        <?php if ($canAccessLabInstruments): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">Lab Instruments</div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/lab_instruments'); ?>" class="nav-link <?php echo ($current_page === 'lab_instruments.php') ? 'active' : ''; ?>">
                <i class="fas fa-microchip"></i> Instruments Home
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/lab_instruments_stock'); ?>" class="nav-link <?php echo ($current_page === 'lab_instruments_stock.php') ? 'active' : ''; ?>">
                <i class="fas fa-toolbox"></i> Stock Register
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/lab_instruments_student_issues'); ?>" class="nav-link <?php echo ($current_page === 'lab_instruments_student_issues.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i> Student Issue / Return
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/lab_instruments_staff_issues'); ?>" class="nav-link <?php echo ($current_page === 'lab_instruments_staff_issues.php') ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i> Staff Issue / Return
            </a>
        </div>
        <?php endif; ?>

        <!-- IT / Computer Lab (separate section) -->
        <?php if ($canAccessItLab): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">IT / Computer Lab</div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/it_lab'); ?>" class="nav-link <?php echo ($current_page === 'it_lab.php') ? 'active' : ''; ?>">
                <i class="fas fa-desktop"></i> IT Lab Home
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/it_lab_systems'); ?>" class="nav-link <?php echo ($current_page === 'it_lab_systems.php') ? 'active' : ''; ?>">
                <i class="fas fa-keyboard"></i> Systems &amp; parts
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/it_lab_student_issues'); ?>" class="nav-link <?php echo ($current_page === 'it_lab_student_issues.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i> Student Issue / Return
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/it_lab_staff_issues'); ?>" class="nav-link <?php echo ($current_page === 'it_lab_staff_issues.php') ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i> Staff Issue / Return
            </a>
        </div>
        <?php endif; ?>

        <?php if ($is_master_admin || $is_course_coordinator || $is_faculty): ?>
        <div class="nav-divider"></div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/reset_password'); ?>" class="nav-link <?php echo ($current_page === 'reset_password.php') ? 'active' : ''; ?>">
                <i class="fas fa-key"></i> Reset Password
            </a>
        </div>
        <?php endif; ?>

        <?php
        $pendingTicketCount = 0;
        if ($is_master_admin && isset($conn) && $conn instanceof mysqli) {
            $stHelper = __DIR__ . '/../../includes/support_ticket_helper.php';
            if (is_file($stHelper)) {
                require_once $stHelper;
                if (function_exists('countPendingSupportTickets')) {
                    $pendingTicketCount = countPendingSupportTickets($conn);
                }
            }
        }
        $ticketNavPages = ['manage_support_tickets.php', 'view_support_ticket.php'];
        ?>
        <div class="nav-divider"></div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_support_tickets'); ?>" class="nav-link <?php echo in_array($current_page, $ticketNavPages, true) ? 'active' : ''; ?>">
                <i class="fas fa-headset"></i>
                <?php echo $is_master_admin ? 'Pending Tickets' : 'Support Tickets'; ?>
                <?php if ($is_master_admin && $pendingTicketCount > 0): ?>
                    <b style="margin-left:auto;background:#dc2626;color:#fff;border-radius:999px;padding:1px 7px;font-size:0.7rem;font-weight:700;"><?php echo (int) $pendingTicketCount; ?></b>
                <?php endif; ?>
            </a>
        </div>
        
        <div class="nav-divider"></div>
        
        <!-- Common Links -->
        <div class="nav-item">
            <a href="<?php echo app_url(); ?>" class="nav-link" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-globe"></i> View Website
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/logout'); ?>" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>
</aside>

<div class="sidebar-overlay" onclick="closeAdminSidebar()"></div>

<script>
function toggleAdminSidebar() {
    document.body.classList.toggle('sidebar-open');
}

function closeAdminSidebar() {
    document.body.classList.remove('sidebar-open');
}

// Dropdown toggle function
function toggleDropdown(button) {
    var dropdownMenu = button.nextElementSibling;
    if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
        dropdownMenu.classList.toggle('show');
    }
    
    // Close other open dropdowns in the sidebar
    var sidebar = document.getElementById('adminSidebar');
    if (sidebar) {
        var otherDropdowns = sidebar.querySelectorAll('.dropdown-menu.show');
        otherDropdowns.forEach(function(item) {
            if (item !== dropdownMenu) {
                item.classList.remove('show');
            }
        });
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    var sidebar = document.getElementById('adminSidebar');
    if (sidebar && !sidebar.contains(event.target)) {
        var dropdownMenus = sidebar.querySelectorAll('.dropdown-menu.show');
        dropdownMenus.forEach(function(item) {
            item.classList.remove('show');
        });
    }
});

(function initSidebarClock() {
    var timeEl = document.getElementById('sidebarClockTime');
    var dateEl = document.getElementById('sidebarClockDate');
    if (!timeEl || !dateEl) {
        return;
    }

    var tz = 'Asia/Kolkata';
    var timeFmt = new Intl.DateTimeFormat('en-IN', {
        timeZone: tz,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
    var dateFmt = new Intl.DateTimeFormat('en-IN', {
        timeZone: tz,
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });

    function tick() {
        var now = new Date();
        timeEl.textContent = timeFmt.format(now);
        dateEl.textContent = dateFmt.format(now);
    }

    tick();
    setInterval(tick, 1000);
})();

document.addEventListener('DOMContentLoaded', function () {
    if (window.innerWidth <= 480) {
        document.body.classList.add('sidebar-open');
    }

    document.querySelectorAll('.admin-sidebar .nav-link').forEach(function (link) {
        if (!link.getAttribute('title')) {
            link.setAttribute('title', (link.textContent || '').trim());
        }
        link.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                closeAdminSidebar();
            }
        });
    });
});
</script>
