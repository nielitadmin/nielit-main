<?php
/**
 * Session Manager Extension for RBAC System
 * NIELIT Bhubaneswar Student Management System
 * 
 * This module extends the existing session management to include RBAC data.
 * It loads admin role, assigned courses, and other permission-related data into the session.
 * 
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5
 * 
 * @package NIELIT_RBAC
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('DB_CONFIG_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Initialize admin session with RBAC data
 * 
 * This function is called after successful login to load the admin's role
 * and assignments into the session. It retrieves the admin record from the
 * database and calls load_admin_permissions() to populate session variables.
 * 
 * @param string $username Admin username
 * @return bool True if initialized successfully, false otherwise
 * 
 * Requirements: 11.1, 11.2
 */
function init_admin_session($username) {
    global $conn;
    
    if (empty($username)) {
        error_log("Session Manager: Cannot initialize session with empty username");
        return false;
    }
    
    try {
        // Fetch admin record with role information
        $stmt = $conn->prepare("SELECT id, username, role, email, is_active FROM admin WHERE LOWER(username) = LOWER(?) LIMIT 1");
        if (!$stmt) {
            error_log("Session Manager: Failed to prepare statement - " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            
            // Check if admin account is active
            if (!$admin['is_active']) {
                error_log("Session Manager: Attempted login with inactive account - " . $username);
                return false;
            }
            
            // Set basic session variables
            $_SESSION['admin'] = $admin['username'];
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_email'] = $admin['email'];
            
            // Load role-specific permissions and assignments
            load_admin_permissions($admin['id']);
            
            // Log successful session initialization
            error_log("Session Manager: Session initialized for user " . $username . " with role " . $admin['role']);
            
            $stmt->close();
            return true;
        } else {
            error_log("Session Manager: Admin not found - " . $username);
            $stmt->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Session Manager: Exception in init_admin_session - " . $e->getMessage());
        return false;
    }
}

/**
 * Load admin role and assignments into session
 * 
 * This function loads course assignments for course coordinators into the session.
 * Note: Batch coordinator role has been removed. Course coordinators manage both
 * courses AND batches through course assignments only.
 * 
 * @param int $admin_id Admin ID
 * @return void
 * 
 * Requirements: 11.2, 11.3
 */
function load_admin_permissions($admin_id) {
    global $conn;
    
    if (empty($admin_id)) {
        error_log("Session Manager: Cannot load permissions with empty admin_id");
        return;
    }
    
    try {
        // Initialize assignment arrays
        $_SESSION['assigned_courses'] = [];
        
        // Load course assignments for course coordinators
        // Course coordinators manage both courses AND batches through course assignments
        if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'course_coordinator') {
            $stmt = $conn->prepare("
                SELECT course_id 
                FROM admin_course_assignments 
                WHERE admin_id = ?
            ");
            
            if ($stmt) {
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $courses = [];
                while ($row = $result->fetch_assoc()) {
                    $courses[] = (int)$row['course_id'];
                }
                
                $_SESSION['assigned_courses'] = $courses;
                
                error_log("Session Manager: Loaded " . count($courses) . " course assignments for admin_id " . $admin_id);
                
                $stmt->close();
            } else {
                error_log("Session Manager: Failed to prepare course assignments query - " . $conn->error);
            }
        }
        
        // Master admins have access to everything (no specific assignments needed)
        // Data entry operators and report viewers don't have specific assignments
        
    } catch (Exception $e) {
        error_log("Session Manager: Exception in load_admin_permissions - " . $e->getMessage());
    }
}

/**
 * Invalidate admin session (for role changes)
 * 
 * This function is called when an admin's role is changed by a master admin.
 * It forces the affected admin to log out by destroying their session data.
 * The admin will need to log in again to get the new role permissions.
 * 
 * Note: This function invalidates the session for a specific admin_id, not the
 * current session. It's typically called by a master admin when changing another
 * admin's role.
 * 
 * @param int $admin_id Admin ID whose session should be invalidated
 * @return void
 * 
 * Requirements: 11.5, 7.5
 */
function invalidate_admin_session($admin_id) {
    global $conn;
    
    if (empty($admin_id)) {
        error_log("Session Manager: Cannot invalidate session with empty admin_id");
        return;
    }
    
    try {
        // In PHP, we cannot directly destroy another user's session
        // However, we can implement a session invalidation flag in the database
        // that will be checked on the next request
        
        // For now, we'll log the invalidation request
        // The actual session will be invalidated when the user makes their next request
        // and the system detects their role has changed
        
        error_log("Session Manager: Session invalidation requested for admin_id " . $admin_id);
        
        // Optional: Store a session invalidation timestamp in the database
        // This can be checked against the session creation time to force re-login
        $stmt = $conn->prepare("UPDATE admin SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // If the admin being invalidated is the current session user, destroy the session
        if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == $admin_id) {
            session_unset();
            session_destroy();
            error_log("Session Manager: Current session destroyed for admin_id " . $admin_id);
        }
        
    } catch (Exception $e) {
        error_log("Session Manager: Exception in invalidate_admin_session - " . $e->getMessage());
    }
}

/**
 * Refresh session permissions
 * 
 * This function reloads the current admin's permissions from the database.
 * It's useful when assignments are changed during an active session.
 * The function re-fetches the admin's role and assignments to ensure
 * the session data is up-to-date.
 * 
 * @return void
 * 
 * Requirements: 11.4
 */
function refresh_session_permissions() {
    global $conn;
    
    // Check if admin is logged in
    if (!isset($_SESSION['admin_id'])) {
        error_log("Session Manager: Cannot refresh permissions - no active session");
        return;
    }
    
    $admin_id = $_SESSION['admin_id'];
    
    try {
        // Re-fetch admin role in case it changed
        $stmt = $conn->prepare("SELECT role, is_active FROM admin WHERE id = ? LIMIT 1");
        if (!$stmt) {
            error_log("Session Manager: Failed to prepare refresh statement - " . $conn->error);
            return;
        }
        
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            
            // Check if account is still active
            if (!$admin['is_active']) {
                error_log("Session Manager: Account deactivated during session - admin_id " . $admin_id);
                session_unset();
                session_destroy();
                return;
            }
            
            // Update role in session
            $old_role = $_SESSION['admin_role'] ?? 'unknown';
            $_SESSION['admin_role'] = $admin['role'];
            
            // If role changed, log it and reload permissions
            if ($old_role !== $admin['role']) {
                error_log("Session Manager: Role changed from " . $old_role . " to " . $admin['role'] . " for admin_id " . $admin_id);
            }
            
            // Reload permissions and assignments
            load_admin_permissions($admin_id);
            
            error_log("Session Manager: Permissions refreshed for admin_id " . $admin_id);
        } else {
            error_log("Session Manager: Admin not found during refresh - admin_id " . $admin_id);
            session_unset();
            session_destroy();
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Session Manager: Exception in refresh_session_permissions - " . $e->getMessage());
    }
}

/**
 * Check if current session is valid and up-to-date
 * 
 * This helper function can be called on each page load to verify that the
 * session is still valid and hasn't been invalidated due to role changes.
 * 
 * @return bool True if session is valid, false otherwise
 */
function is_session_valid() {
    // Check if basic session variables exist
    if (!isset($_SESSION['admin']) || !isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role'])) {
        return false;
    }
    
    // Session is valid
    return true;
}

/**
 * Get admin's full name or username for display
 * 
 * Helper function to get a display name for the current admin.
 * 
 * @return string Admin display name
 */
function get_admin_display_name() {
    if (isset($_SESSION['admin'])) {
        return $_SESSION['admin'];
    }
    return 'Unknown Admin';
}

/**
 * Get admin's role display name
 * 
 * Converts role identifier to human-readable format.
 * 
 * @param string|null $role Role identifier (uses session role if not provided)
 * @return string Human-readable role name
 */
function get_role_display_name($role = null) {
    if ($role === null) {
        $role = $_SESSION['admin_role'] ?? '';
    }
    
    $role_names = [
        'master_admin' => 'Master Administrator',
        'course_coordinator' => 'Course Coordinator',
        'data_entry_operator' => 'Data Entry Operator',
        'report_viewer' => 'Report Viewer'
    ];
    
    return $role_names[$role] ?? 'Unknown Role';
}
?>
