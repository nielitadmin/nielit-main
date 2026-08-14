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
        <div class="nav-item">
            <a href="<?php echo app_url('admin/students'); ?>" class="nav-link <?php echo ($current_page === 'students.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Students
            </a>
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
        <div class="nav-divider"></div>
        <div class="nav-section-title">Teaching</div>
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
        <div class="nav-divider"></div>
        <div class="nav-section-title">System Settings</div>
        
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_centres'); ?>" class="nav-link <?php echo ($current_page === 'manage_centres.php') ? 'active' : ''; ?>">
                <i class="fas fa-building"></i> Training Centres
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_themes'); ?>" class="nav-link <?php echo ($current_page === 'manage_themes.php') ? 'active' : ''; ?>" title="Themes">
                <i class="fas fa-palette"></i> Themes
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_public_themes'); ?>" class="nav-link <?php echo ($current_page === 'manage_public_themes.php') ? 'active' : ''; ?>" title="Public Themes">
                <i class="fas fa-globe"></i> Public Themes
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_sidebar_themes'); ?>" class="nav-link <?php echo ($current_page === 'manage_sidebar_themes.php') ? 'active' : ''; ?>" title="Sidebar Themes">
                <i class="fas fa-columns"></i> Sidebar Themes
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_maintenance'); ?>" class="nav-link <?php echo ($current_page === 'manage_maintenance.php') ? 'active' : ''; ?>">
                <i class="fas fa-tools"></i> Maintenance Mode
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_migrations'); ?>" class="nav-link <?php echo in_array($current_page, ['manage_migrations.php', 'run_migration.php'], true) ? 'active' : ''; ?>">
                <i class="fas fa-database"></i> DB Migrations
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_activity'); ?>" class="nav-link <?php echo ($current_page === 'manage_activity.php') ? 'active' : ''; ?>">
                <i class="fas fa-stream"></i> Activity Log
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/visitor_stats'); ?>" class="nav-link <?php echo ($current_page === 'visitor_stats.php') ? 'active' : ''; ?>">
                <i class="fas fa-eye"></i> Visitor Statistics
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/check_student_exists'); ?>" class="nav-link <?php echo ($current_page === 'check_student_exists.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i> Student Record Inspector
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_homepage'); ?>" class="nav-link <?php echo ($current_page === 'manage_homepage.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Homepage Content
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_news'); ?>" class="nav-link <?php echo ($current_page === 'manage_news.php') ? 'active' : ''; ?>">
                <i class="fas fa-newspaper"></i> News & Updates
            </a>
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
        
        <!-- Admin Management (Master Admin Only) -->
        <?php if ($is_master_admin): ?>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/add_admin'); ?>" class="nav-link <?php echo ($current_page === 'add_admin.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i> Add Admin
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_admins'); ?>" class="nav-link <?php echo ($current_page === 'manage_admins.php') ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i> Manage Admins
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/manage_course_assignments'); ?>" class="nav-link <?php echo ($current_page === 'manage_course_assignments.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i> Course Assignments
            </a>
        </div>
        
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
            <a href="<?php echo app_url('admin/attendance_reports'); ?>" class="nav-link <?php echo ($current_page === 'attendance_reports.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Attendance Reports
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/report_monitor'); ?>" class="nav-link <?php echo ($current_page === 'report_monitor.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Report Monitor
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('admin/view_otp_logs'); ?>" class="nav-link <?php echo ($current_page === 'view_otp_logs.php') ? 'active' : ''; ?>">
                <i class="fas fa-list-alt"></i> OTP Logs
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo app_url('api/admin/manage_api_keys'); ?>" class="nav-link <?php echo ($current_page === 'manage_api_keys.php') ? 'active' : ''; ?>">
                <i class="fas fa-key"></i> API Management
            </a>
        </div>
        <?php endif; ?>
        
        
        <?php if (!$is_faculty && !$is_nsqf_manager && !$is_front_office && !$is_placement_coordinator): ?>
        <!-- This section is now empty as Reset Password moved to shared section -->
        <?php endif; ?>

        <?php
        $canAccessLibrary = false;
        if (isset($conn) && $conn instanceof mysqli) {
            $libHelper = __DIR__ . '/../../includes/library_helper.php';
            if (is_file($libHelper)) {
                require_once $libHelper;
                $canAccessLibrary = function_exists('admin_can_access_library') && admin_can_access_library($conn);
            }
        }
        ?>
        <?php
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
