<?php
/**
 * Permission Checker Module
 * 
 * Centralized permission validation for all admin portal pages and actions.
 * Implements role-based access control (RBAC) with hierarchical permissions.
 * 
 * Role Hierarchy:
 * - Level 4: master_admin (full system access)
 * - Level 3: course_coordinator (manages courses AND batches)
 * - Level 2: data_entry_operator (student records only)
 * - Level 1: report_viewer (read-only access)
 * 
 * Requirements: 7.1, 7.2, 7.3, 7.4, 7.6, 2.1, 3.2, 3.8, 4.2, 4.7
 */

/**
 * Permission matrix defining what actions each role can perform
 * 
 * master_admin: Has all permissions (wildcard '*')
 * course_coordinator: Manages courses, batches, and students within assigned courses
 * data_entry_operator: Can only view, add, and edit students
 * report_viewer: Read-only access to view and export data
 */
function get_permission_matrix() {
    return [
        'master_admin' => ['*'], // All permissions
        
        'course_coordinator' => [
            // Student management
            'view_students',
            'add_students',
            'edit_students',
            'manage_course_students',
            
            // Course management
            'view_courses',
            'edit_courses',
            
            // Batch management (course coordinators manage batches for their courses)
            'view_batches',
            'edit_batches',
            'add_batches',
            'manage_course_batches',
            'generate_admission_orders',
            'approve_students',
            
            // Reports
            'view_reports',
            'generate_reports',
            'export_reports'
        ],
        
        'data_entry_operator' => [
            'view_students',
            'add_students',
            'edit_students'
        ],
        
        'report_viewer' => [
            'view_students',
            'view_courses',
            'view_batches',
            'view_reports',
            'export_reports'
        ]
    ];
}

/**
 * Get current admin's role from session
 * 
 * @return string|null Role name or null if not logged in
 */
function get_admin_role() {
    if (!isset($_SESSION['admin_role'])) {
        return null;
    }
    return $_SESSION['admin_role'];
}

/**
 * Check if current admin has permission for a specific action
 * 
 * @param string $action Action identifier (e.g., 'view_students', 'edit_course')
 * @param array $context Additional context (e.g., ['course_id' => 5, 'batch_id' => 10])
 * @return bool True if permitted, false otherwise
 */
function has_permission($action, $context = []) {
    // Get current admin role
    $role = get_admin_role();
    
    // If no role is set, deny access (fail closed)
    if ($role === null) {
        return false;
    }
    
    // Get permission matrix
    $permission_matrix = get_permission_matrix();
    
    // If role doesn't exist in matrix, deny access
    if (!isset($permission_matrix[$role])) {
        return false;
    }
    
    $permissions = $permission_matrix[$role];
    
    // Master admin has all permissions
    if (in_array('*', $permissions)) {
        return true;
    }
    
    // Check if role has the specific permission
    if (!in_array($action, $permissions)) {
        return false;
    }
    
    // For course coordinators, check course-specific access
    if ($role === 'course_coordinator') {
        // If action requires course access, verify it
        if (isset($context['course_id'])) {
            if (!has_course_access($context['course_id'])) {
                return false;
            }
        }
        
        // If action requires batch access, verify it through course
        if (isset($context['batch_id'])) {
            if (!has_batch_access($context['batch_id'])) {
                return false;
            }
        }
    }
    
    return true;
}

/**
 * Require permission or redirect to access denied page
 * 
 * @param string $action Action identifier
 * @param array $context Additional context
 * @return void Redirects if permission denied
 */
function require_permission($action, $context = []) {
    if (!has_permission($action, $context)) {
        // Log the access denial attempt
        if (function_exists('log_admin_action')) {
            log_admin_action(
                'view',
                'access_denied',
                null,
                json_encode([
                    'action' => $action,
                    'context' => $context,
                    'role' => get_admin_role()
                ])
            );
        }
        
        // Redirect to access denied page
        header("Location: " . get_base_url() . "/admin/access_denied.php?action=" . urlencode($action));
        exit();
    }
}

/**
 * Check if admin has access to specific course
 * 
 * @param int $course_id Course ID
 * @return bool True if has access
 */
function has_course_access($course_id) {
    $role = get_admin_role();
    
    // Master admin has access to all courses
    if ($role === 'master_admin') {
        return true;
    }
    
    // Course coordinators have access only to assigned courses
    if ($role === 'course_coordinator') {
        if (!isset($_SESSION['assigned_courses'])) {
            return false;
        }
        
        $assigned_courses = $_SESSION['assigned_courses'];
        return in_array($course_id, $assigned_courses);
    }
    
    // Other roles don't have course-specific access control
    // Their access is controlled by action permissions
    return true;
}

/**
 * Check if admin has access to specific batch
 * 
 * Note: Batch access is now controlled through course assignments.
 * Course coordinators manage both courses AND batches.
 * 
 * @param int $batch_id Batch ID
 * @return bool True if has access
 */
function has_batch_access($batch_id) {
    global $conn;
    
    $role = get_admin_role();
    
    // Master admin has access to all batches
    if ($role === 'master_admin') {
        return true;
    }
    
    // Course coordinators have access to batches of their assigned courses
    if ($role === 'course_coordinator') {
        if (!isset($_SESSION['assigned_courses'])) {
            return false;
        }
        
        $assigned_courses = $_SESSION['assigned_courses'];
        
        // If no assigned courses, no batch access
        if (empty($assigned_courses)) {
            return false;
        }
        
        // Check if the batch belongs to one of the assigned courses
        $placeholders = implode(',', array_fill(0, count($assigned_courses), '?'));
        $query = "SELECT COUNT(*) as count FROM batches WHERE id = ? AND course_id IN ($placeholders)";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            // On database error, fail closed (deny access)
            return false;
        }
        
        // Bind batch_id and all course_ids
        $types = 'i' . str_repeat('i', count($assigned_courses));
        $params = array_merge([$batch_id], $assigned_courses);
        $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['count'] > 0;
    }
    
    // Other roles don't have batch-specific access control
    // Their access is controlled by action permissions
    return true;
}

/**
 * Get base URL for redirects
 * 
 * @return string Base URL
 */
function get_base_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $base = dirname(dirname($script));
    
    return $protocol . '://' . $host . $base;
}
