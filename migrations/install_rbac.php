<?php
/**
 * RBAC Database Installation Script
 * NIELIT Bhubaneswar Student Management System
 * 
 * This script executes all RBAC migrations in order with rollback capability
 * Requirements: 10.1, 10.2, 10.3, 10.4
 * 
 * Usage:
 *   Install: php install_rbac.php install
 *   Rollback: php install_rbac.php rollback
 *   Verify: php install_rbac.php verify
 */

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Color output for CLI
class Colors {
    public static $GREEN = "\033[0;32m";
    public static $RED = "\033[0;31m";
    public static $YELLOW = "\033[1;33m";
    public static $BLUE = "\033[0;34m";
    public static $NC = "\033[0m"; // No Color
}

/**
 * Log message with color
 */
function log_message($message, $color = null) {
    $timestamp = date('Y-m-d H:i:s');
    if ($color && php_sapi_name() === 'cli') {
        echo $color . "[{$timestamp}] {$message}" . Colors::$NC . "\n";
    } else {
        echo "[{$timestamp}] {$message}\n";
    }
}

/**
 * Execute SQL query with error handling
 */
function execute_sql($conn, $sql, $description) {
    log_message("Executing: {$description}", Colors::$BLUE);
    
    if ($conn->multi_query($sql)) {
        do {
            // Store first result set
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        
        log_message("✓ Success: {$description}", Colors::$GREEN);
        return true;
    } else {
        log_message("✗ Error: {$description} - " . $conn->error, Colors::$RED);
        return false;
    }
}

/**
 * Check if column exists in table
 */
function column_exists($conn, $table, $column) {
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
            AND TABLE_NAME = '{$table}' 
            AND COLUMN_NAME = '{$column}'";
    $result = $conn->query($sql);
    return $result && $result->num_rows > 0;
}

/**
 * Check if table exists
 */
function table_exists($conn, $table) {
    $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
            AND TABLE_NAME = '{$table}'";
    $result = $conn->query($sql);
    return $result && $result->num_rows > 0;
}

/**
 * Check if index exists
 */
function index_exists($conn, $table, $index) {
    $sql = "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
            AND TABLE_NAME = '{$table}' 
            AND INDEX_NAME = '{$index}'";
    $result = $conn->query($sql);
    return $result && $result->num_rows > 0;
}

/**
 * Install RBAC schema
 */
function install_rbac($conn) {
    log_message("=== Starting RBAC Installation ===", Colors::$YELLOW);
    
    $success = true;
    
    // Migration 1: Add role column to admin table
    if (!column_exists($conn, 'admin', 'role')) {
        $sql = "ALTER TABLE admin 
                ADD COLUMN role ENUM(
                    'master_admin', 
                    'course_coordinator', 
                    'data_entry_operator', 
                    'report_viewer'
                ) NOT NULL DEFAULT 'master_admin' 
                AFTER email";
        $success = execute_sql($conn, $sql, "Add role column to admin table") && $success;
    } else {
        log_message("⊙ Skipped: role column already exists", Colors::$YELLOW);
    }
    
    // Migration 2: Add created_at column
    if (!column_exists($conn, 'admin', 'created_at')) {
        $sql = "ALTER TABLE admin 
                ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP 
                AFTER role";
        $success = execute_sql($conn, $sql, "Add created_at column to admin table") && $success;
    } else {
        log_message("⊙ Skipped: created_at column already exists", Colors::$YELLOW);
    }
    
    // Migration 3: Add updated_at column
    if (!column_exists($conn, 'admin', 'updated_at')) {
        $sql = "ALTER TABLE admin 
                ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
                AFTER created_at";
        $success = execute_sql($conn, $sql, "Add updated_at column to admin table") && $success;
    } else {
        log_message("⊙ Skipped: updated_at column already exists", Colors::$YELLOW);
    }
    
    // Migration 4: Add is_active column
    if (!column_exists($conn, 'admin', 'is_active')) {
        $sql = "ALTER TABLE admin 
                ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 
                AFTER updated_at";
        $success = execute_sql($conn, $sql, "Add is_active column to admin table") && $success;
    } else {
        log_message("⊙ Skipped: is_active column already exists", Colors::$YELLOW);
    }
    
    // Migration 5: Add index on role column
    if (!index_exists($conn, 'admin', 'idx_role')) {
        $sql = "CREATE INDEX idx_role ON admin(role)";
        $success = execute_sql($conn, $sql, "Add index on role column") && $success;
    } else {
        log_message("⊙ Skipped: idx_role index already exists", Colors::$YELLOW);
    }
    
    // Migration 6: Add index on is_active column
    if (!index_exists($conn, 'admin', 'idx_active')) {
        $sql = "CREATE INDEX idx_active ON admin(is_active)";
        $success = execute_sql($conn, $sql, "Add index on is_active column") && $success;
    } else {
        log_message("⊙ Skipped: idx_active index already exists", Colors::$YELLOW);
    }
    
    // Migration 7: Update existing admins to master_admin role
    $sql = "UPDATE admin SET role = 'master_admin' WHERE role IS NULL OR role = ''";
    $success = execute_sql($conn, $sql, "Set master_admin role for existing admins") && $success;
    
    // Migration 8: Create admin_course_assignments table
    if (!table_exists($conn, 'admin_course_assignments')) {
        $sql = "CREATE TABLE admin_course_assignments (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    admin_id INT(11) NOT NULL,
                    course_id INT(11) NOT NULL,
                    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    assigned_by INT(11) NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY unique_assignment (admin_id, course_id),
                    KEY idx_admin (admin_id),
                    KEY idx_course (course_id),
                    CONSTRAINT fk_course_admin FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE,
                    CONSTRAINT fk_course_assigned_by FOREIGN KEY (assigned_by) REFERENCES admin(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 
                COMMENT='Stores course assignments for course coordinators'";
        $success = execute_sql($conn, $sql, "Create admin_course_assignments table") && $success;
    } else {
        log_message("⊙ Skipped: admin_course_assignments table already exists", Colors::$YELLOW);
    }
    
    // Migration 9: Create audit_log table
    if (!table_exists($conn, 'audit_log')) {
        $sql = "CREATE TABLE audit_log (
                    id BIGINT(20) NOT NULL AUTO_INCREMENT,
                    admin_id INT(11) NOT NULL,
                    admin_username VARCHAR(255) NOT NULL,
                    role VARCHAR(50) NOT NULL,
                    action_type ENUM('create', 'update', 'delete', 'view', 'export', 'login', 'logout') NOT NULL,
                    resource_type VARCHAR(50) NOT NULL,
                    resource_id INT(11) DEFAULT NULL,
                    details TEXT,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent VARCHAR(255) DEFAULT NULL,
                    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_admin (admin_id),
                    KEY idx_timestamp (timestamp),
                    KEY idx_action_type (action_type),
                    KEY idx_resource (resource_type, resource_id),
                    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 
                COMMENT='Audit trail of all administrative actions'";
        $success = execute_sql($conn, $sql, "Create audit_log table") && $success;
    } else {
        log_message("⊙ Skipped: audit_log table already exists", Colors::$YELLOW);
    }
    
    if ($success) {
        log_message("=== RBAC Installation Completed Successfully ===", Colors::$GREEN);
    } else {
        log_message("=== RBAC Installation Completed with Errors ===", Colors::$RED);
    }
    
    return $success;
}

/**
 * Rollback RBAC schema
 */
function rollback_rbac($conn) {
    log_message("=== Starting RBAC Rollback ===", Colors::$YELLOW);
    log_message("WARNING: This will remove all RBAC data!", Colors::$RED);
    
    if (php_sapi_name() === 'cli') {
        echo "Are you sure you want to rollback? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) !== 'yes') {
            log_message("Rollback cancelled", Colors::$YELLOW);
            return false;
        }
        fclose($handle);
    }
    
    $success = true;
    
    // Rollback 9: Drop audit_log table
    if (table_exists($conn, 'audit_log')) {
        $sql = "DROP TABLE audit_log";
        $success = execute_sql($conn, $sql, "Drop audit_log table") && $success;
    } else {
        log_message("⊙ Skipped: audit_log table does not exist", Colors::$YELLOW);
    }
    
    // Rollback 8: Drop admin_course_assignments table
    if (table_exists($conn, 'admin_course_assignments')) {
        $sql = "DROP TABLE admin_course_assignments";
        $success = execute_sql($conn, $sql, "Drop admin_course_assignments table") && $success;
    } else {
        log_message("⊙ Skipped: admin_course_assignments table does not exist", Colors::$YELLOW);
    }
    
    // Rollback 6: Drop idx_active index
    if (index_exists($conn, 'admin', 'idx_active')) {
        $sql = "DROP INDEX idx_active ON admin";
        $success = execute_sql($conn, $sql, "Drop idx_active index") && $success;
    } else {
        log_message("⊙ Skipped: idx_active index does not exist", Colors::$YELLOW);
    }
    
    // Rollback 5: Drop idx_role index
    if (index_exists($conn, 'admin', 'idx_role')) {
        $sql = "DROP INDEX idx_role ON admin";
        $success = execute_sql($conn, $sql, "Drop idx_role index") && $success;
    } else {
        log_message("⊙ Skipped: idx_role index does not exist", Colors::$YELLOW);
    }
    
    // Rollback 4: Drop is_active column
    if (column_exists($conn, 'admin', 'is_active')) {
        $sql = "ALTER TABLE admin DROP COLUMN is_active";
        $success = execute_sql($conn, $sql, "Drop is_active column") && $success;
    } else {
        log_message("⊙ Skipped: is_active column does not exist", Colors::$YELLOW);
    }
    
    // Rollback 3: Drop updated_at column
    if (column_exists($conn, 'admin', 'updated_at')) {
        $sql = "ALTER TABLE admin DROP COLUMN updated_at";
        $success = execute_sql($conn, $sql, "Drop updated_at column") && $success;
    } else {
        log_message("⊙ Skipped: updated_at column does not exist", Colors::$YELLOW);
    }
    
    // Rollback 2: Drop created_at column
    if (column_exists($conn, 'admin', 'created_at')) {
        $sql = "ALTER TABLE admin DROP COLUMN created_at";
        $success = execute_sql($conn, $sql, "Drop created_at column") && $success;
    } else {
        log_message("⊙ Skipped: created_at column does not exist", Colors::$YELLOW);
    }
    
    // Rollback 1: Drop role column
    if (column_exists($conn, 'admin', 'role')) {
        $sql = "ALTER TABLE admin DROP COLUMN role";
        $success = execute_sql($conn, $sql, "Drop role column") && $success;
    } else {
        log_message("⊙ Skipped: role column does not exist", Colors::$YELLOW);
    }
    
    if ($success) {
        log_message("=== RBAC Rollback Completed Successfully ===", Colors::$GREEN);
    } else {
        log_message("=== RBAC Rollback Completed with Errors ===", Colors::$RED);
    }
    
    return $success;
}

/**
 * Verify RBAC installation
 */
function verify_rbac($conn) {
    log_message("=== Verifying RBAC Installation ===", Colors::$YELLOW);
    
    $all_valid = true;
    
    // Check admin table columns
    $required_columns = ['role', 'created_at', 'updated_at', 'is_active'];
    foreach ($required_columns as $column) {
        if (column_exists($conn, 'admin', $column)) {
            log_message("✓ Column 'admin.{$column}' exists", Colors::$GREEN);
        } else {
            log_message("✗ Column 'admin.{$column}' missing", Colors::$RED);
            $all_valid = false;
        }
    }
    
    // Check indexes
    $required_indexes = [
        ['admin', 'idx_role'],
        ['admin', 'idx_active']
    ];
    foreach ($required_indexes as list($table, $index)) {
        if (index_exists($conn, $table, $index)) {
            log_message("✓ Index '{$table}.{$index}' exists", Colors::$GREEN);
        } else {
            log_message("✗ Index '{$table}.{$index}' missing", Colors::$RED);
            $all_valid = false;
        }
    }
    
    // Check tables
    $required_tables = ['admin_course_assignments', 'audit_log'];
    foreach ($required_tables as $table) {
        if (table_exists($conn, $table)) {
            log_message("✓ Table '{$table}' exists", Colors::$GREEN);
        } else {
            log_message("✗ Table '{$table}' missing", Colors::$RED);
            $all_valid = false;
        }
    }
    
    // Validate data
    $sql = "SELECT COUNT(*) as count FROM admin WHERE role IS NULL OR role = ''";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row['count'] == 0) {
            log_message("✓ All admin users have valid roles", Colors::$GREEN);
        } else {
            log_message("✗ {$row['count']} admin users have invalid roles", Colors::$RED);
            $all_valid = false;
        }
    }
    
    // Check foreign key constraints
    $sql = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
            AND TABLE_NAME = 'admin_course_assignments' 
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows >= 2) {
        log_message("✓ Foreign key constraints exist on admin_course_assignments", Colors::$GREEN);
    } else {
        log_message("✗ Foreign key constraints missing on admin_course_assignments", Colors::$RED);
        $all_valid = false;
    }
    
    $sql = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
            AND TABLE_NAME = 'audit_log' 
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows >= 1) {
        log_message("✓ Foreign key constraints exist on audit_log", Colors::$GREEN);
    } else {
        log_message("✗ Foreign key constraints missing on audit_log", Colors::$RED);
        $all_valid = false;
    }
    
    if ($all_valid) {
        log_message("=== RBAC Verification Passed ===", Colors::$GREEN);
    } else {
        log_message("=== RBAC Verification Failed ===", Colors::$RED);
    }
    
    return $all_valid;
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'install':
        $success = install_rbac($conn);
        if ($success) {
            log_message("\nRunning verification...", Colors::$BLUE);
            verify_rbac($conn);
        }
        exit($success ? 0 : 1);
        
    case 'rollback':
        $success = rollback_rbac($conn);
        exit($success ? 0 : 1);
        
    case 'verify':
        $success = verify_rbac($conn);
        exit($success ? 0 : 1);
        
    case 'help':
    default:
        echo "\nRBAC Database Installation Script\n";
        echo "==================================\n\n";
        echo "Usage: php install_rbac.php [command]\n\n";
        echo "Commands:\n";
        echo "  install   - Install RBAC schema (safe to run multiple times)\n";
        echo "  rollback  - Remove RBAC schema (WARNING: deletes all RBAC data)\n";
        echo "  verify    - Verify RBAC installation\n";
        echo "  help      - Show this help message\n\n";
        echo "Examples:\n";
        echo "  php install_rbac.php install\n";
        echo "  php install_rbac.php verify\n";
        echo "  php install_rbac.php rollback\n\n";
        exit(0);
}
