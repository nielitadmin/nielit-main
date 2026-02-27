<?php
/**
 * Audit Logger
 * 
 * This module provides audit logging functionality for administrative actions.
 * All CRUD operations performed by administrators are logged with timestamp,
 * admin username, action type, resource details, and result status.
 * 
 * Validates: Requirements 11.5
 */

/**
 * Log an administrative action to the audit log
 * 
 * @param mysqli $conn Database connection
 * @param string $admin_username Username of the administrator performing the action
 * @param string $action_type Type of action (create, update, delete, activate, deactivate)
 * @param string $resource_type Type of resource being modified (centre, theme, homepage_content)
 * @param int|null $resource_id ID of the resource being modified (null for create before ID is known)
 * @param string $resource_name Human-readable name/identifier of the resource
 * @param string $result Result of the action (success, failure)
 * @param string|null $details Additional details about the action (optional)
 * @return bool Success status of logging operation
 */
function logAuditAction($conn, $admin_username, $action_type, $resource_type, $resource_id, $resource_name, $result, $details = null) {
    try {
        $stmt = $conn->prepare("INSERT INTO audit_logs (admin_username, action_type, resource_type, resource_id, resource_name, result, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            error_log("Audit log prepare failed: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("sssisss", $admin_username, $action_type, $resource_type, $resource_id, $resource_name, $result, $details);
        
        $success = $stmt->execute();
        
        if (!$success) {
            error_log("Audit log insert failed: " . $stmt->error);
        }
        
        $stmt->close();
        return $success;
        
    } catch (Exception $e) {
        error_log("Audit logging exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Log a centre management action
 * 
 * @param mysqli $conn Database connection
 * @param string $admin_username Username of the administrator
 * @param string $action_type Action type (create, update, delete, activate, deactivate)
 * @param int|null $centre_id Centre ID
 * @param string $centre_name Centre name
 * @param string $result Result status (success, failure)
 * @param string|null $details Additional details
 * @return bool Success status
 */
function logCentreAction($conn, $admin_username, $action_type, $centre_id, $centre_name, $result, $details = null) {
    return logAuditAction($conn, $admin_username, $action_type, 'centre', $centre_id, $centre_name, $result, $details);
}

/**
 * Log a theme management action
 * 
 * @param mysqli $conn Database connection
 * @param string $admin_username Username of the administrator
 * @param string $action_type Action type (create, update, delete, activate, deactivate)
 * @param int|null $theme_id Theme ID
 * @param string $theme_name Theme name
 * @param string $result Result status (success, failure)
 * @param string|null $details Additional details
 * @return bool Success status
 */
function logThemeAction($conn, $admin_username, $action_type, $theme_id, $theme_name, $result, $details = null) {
    return logAuditAction($conn, $admin_username, $action_type, 'theme', $theme_id, $theme_name, $result, $details);
}

/**
 * Log a homepage content management action
 * 
 * @param mysqli $conn Database connection
 * @param string $admin_username Username of the administrator
 * @param string $action_type Action type (create, update, delete, activate, deactivate)
 * @param int|null $content_id Content section ID
 * @param string $section_key Section key identifier
 * @param string $result Result status (success, failure)
 * @param string|null $details Additional details
 * @return bool Success status
 */
function logHomepageContentAction($conn, $admin_username, $action_type, $content_id, $section_key, $result, $details = null) {
    return logAuditAction($conn, $admin_username, $action_type, 'homepage_content', $content_id, $section_key, $result, $details);
}

/**
 * Get recent audit logs
 * 
 * @param mysqli $conn Database connection
 * @param int $limit Number of logs to retrieve (default: 100)
 * @param string|null $resource_type Filter by resource type (optional)
 * @param string|null $admin_username Filter by admin username (optional)
 * @return mysqli_result|false Query result
 */
function getAuditLogs($conn, $limit = 100, $resource_type = null, $admin_username = null) {
    $sql = "SELECT * FROM audit_logs WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($resource_type !== null) {
        $sql .= " AND resource_type = ?";
        $params[] = $resource_type;
        $types .= "s";
    }
    
    if ($admin_username !== null) {
        $sql .= " AND admin_username = ?";
        $params[] = $admin_username;
        $types .= "s";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Get audit logs for a specific resource
 * 
 * @param mysqli $conn Database connection
 * @param string $resource_type Resource type (centre, theme, homepage_content)
 * @param int $resource_id Resource ID
 * @param int $limit Number of logs to retrieve (default: 50)
 * @return mysqli_result|false Query result
 */
function getResourceAuditLogs($conn, $resource_type, $resource_id, $limit = 50) {
    $stmt = $conn->prepare("SELECT * FROM audit_logs WHERE resource_type = ? AND resource_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("sii", $resource_type, $resource_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Get audit log statistics
 * 
 * @param mysqli $conn Database connection
 * @param string|null $start_date Start date for statistics (optional, format: Y-m-d)
 * @param string|null $end_date End date for statistics (optional, format: Y-m-d)
 * @return array Statistics array with counts by action type and resource type
 */
function getAuditLogStatistics($conn, $start_date = null, $end_date = null) {
    $sql = "SELECT 
                action_type,
                resource_type,
                result,
                COUNT(*) as count
            FROM audit_logs
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($start_date !== null) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $start_date;
        $types .= "s";
    }
    
    if ($end_date !== null) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $end_date;
        $types .= "s";
    }
    
    $sql .= " GROUP BY action_type, resource_type, result";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $statistics = [];
    while ($row = $result->fetch_assoc()) {
        $statistics[] = $row;
    }
    
    return $statistics;
}
