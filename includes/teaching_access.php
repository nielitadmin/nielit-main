<?php
/**
 * Teaching tools access: Class Timetable + Course Action Plans.
 */

if (!function_exists('admin_role_enum_sql')) {
    function admin_role_enum_sql(): string
    {
        return "ENUM('master_admin','course_coordinator','nsqf_course_manager','data_entry_operator','report_viewer','front_office_desk','placement_coordinator','faculty') NOT NULL DEFAULT 'course_coordinator'";
    }
}

if (!function_exists('ensureAdminRoleEnum')) {
    function ensureAdminRoleEnum($conn): void
    {
        static $ready = false;
        if ($ready || !($conn instanceof mysqli)) {
            return;
        }
        $conn->query('ALTER TABLE admin MODIFY COLUMN role ' . admin_role_enum_sql());
        $ready = true;
    }
}

if (!function_exists('admin_role_is_faculty')) {
    function admin_role_is_faculty(?string $role = null): bool
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        return $role === 'faculty';
    }
}

if (!function_exists('admin_teaching_tools_denied')) {
    function admin_teaching_tools_denied(?string $role = null): bool
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        return in_array($role, ['nsqf_course_manager', 'front_office_desk', 'placement_coordinator'], true);
    }
}

if (!function_exists('admin_can_access_teaching_tools')) {
    function admin_can_access_teaching_tools(?string $role = null): bool
    {
        return !admin_teaching_tools_denied($role);
    }
}

if (!function_exists('admin_can_edit_class_timetable')) {
    /** Faculty, coordinators, and master admin may edit the timetable. */
    function admin_can_edit_class_timetable(?string $role = null): bool
    {
        return admin_can_access_teaching_tools($role);
    }
}

if (!function_exists('admin_can_clear_class_timetable')) {
    /** Year-end wipe: master admin only. */
    function admin_can_clear_class_timetable(?string $role = null): bool
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        return $role === 'master_admin';
    }
}

if (!function_exists('admin_can_manage_lesson_plans')) {
    /** Create / edit / delete course action plan headers and topic grids. */
    function admin_can_manage_lesson_plans(?string $role = null): bool
    {
        return admin_can_access_teaching_tools($role);
    }
}

if (!function_exists('admin_can_update_lesson_plan_daily')) {
    function admin_can_update_lesson_plan_daily(?string $role = null): bool
    {
        return admin_can_access_teaching_tools($role);
    }
}

if (!function_exists('admin_lesson_plan_own_only')) {
    /** Faculty and course coordinators only see/edit plans they created. */
    function admin_lesson_plan_own_only(?string $role = null): bool
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        return in_array($role, ['faculty', 'course_coordinator'], true);
    }
}

if (!function_exists('admin_current_username')) {
    function admin_current_username(): string
    {
        return trim((string) ($_SESSION['admin'] ?? ''));
    }
}

if (!function_exists('admin_can_access_lesson_plan')) {
    /** @param array<string,mixed>|null $plan */
    function admin_can_access_lesson_plan(?array $plan): bool
    {
        if (!$plan) {
            return false;
        }
        if (!admin_lesson_plan_own_only()) {
            return true;
        }
        $owner = strtolower(trim((string) ($plan['created_by'] ?? '')));
        $me = strtolower(admin_current_username());
        return $me !== '' && $owner === $me;
    }
}

if (!function_exists('admin_require_own_lesson_plan')) {
    /** @param array<string,mixed>|null $plan */
    function admin_require_own_lesson_plan(?array $plan): void
    {
        if (admin_can_access_lesson_plan($plan)) {
            return;
        }
        $_SESSION['message'] = 'You can only open course action plans that you created.';
        $_SESSION['message_type'] = 'danger';
        header('Location: manage_lesson_plans.php');
        exit();
    }
}

if (!function_exists('admin_require_teaching_tools')) {
    function admin_require_teaching_tools(): void
    {
        if (!isset($_SESSION['admin'])) {
            header('Location: login.php');
            exit();
        }
        if (admin_teaching_tools_denied()) {
            $_SESSION['message'] = 'Access denied.';
            $_SESSION['message_type'] = 'danger';
            $dash = function_exists('relative_url') ? relative_url('dashboard.php') : 'dashboard.php';
            header('Location: ' . $dash);
            exit();
        }
    }
}

if (!function_exists('admin_redirect_faculty_from_restricted_page')) {
    function admin_redirect_faculty_from_restricted_page(): void
    {
        if (!admin_role_is_faculty()) {
            return;
        }
        $target = defined('APP_URL')
            ? (rtrim((string) APP_URL, '/') . '/admin/manage_class_timetable.php')
            : '/admin/manage_class_timetable.php';
        header('Location: ' . $target);
        exit();
    }
}
