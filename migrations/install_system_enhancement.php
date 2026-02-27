<?php
/**
 * System Enhancement Module Installation Script
 * NIELIT Bhubaneswar Student Management System
 * 
 * This script installs the database schema for:
 * - Centre Management
 * - Theme Customization
 * - Homepage Content Management
 */

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Color codes for CLI output
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");

/**
 * Print colored message
 */
function printMessage($message, $color = COLOR_RESET) {
    if (php_sapi_name() === 'cli') {
        echo $color . $message . COLOR_RESET . "\n";
    } else {
        echo "<p>" . htmlspecialchars($message) . "</p>";
    }
}

/**
 * Check if table exists
 */
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

/**
 * Check if column exists
 */
function columnExists($conn, $tableName, $columnName) {
    $result = $conn->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
    return $result && $result->num_rows > 0;
}

/**
 * Install centres table
 */
function installCentresTable($conn) {
    printMessage("📦 Installing centres table...", COLOR_BLUE);
    
    if (tableExists($conn, 'centres')) {
        printMessage("✓ Centres table already exists, skipping...", COLOR_YELLOW);
        return true;
    }
    
    $sql = "CREATE TABLE centres (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) NOT NULL UNIQUE,
        address TEXT,
        city VARCHAR(100),
        state VARCHAR(100),
        pincode VARCHAR(10),
        phone VARCHAR(20),
        email VARCHAR(255),
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_code (code),
        KEY idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 
    COMMENT='Stores NIELIT centre information'";
    
    if ($conn->query($sql)) {
        printMessage("✓ Centres table created successfully", COLOR_GREEN);
        return true;
    } else {
        printMessage("✗ Failed to create centres table: " . $conn->error, COLOR_RED);
        return false;
    }
}

/**
 * Insert default centres
 */
function insertDefaultCentres($conn) {
    printMessage("📦 Inserting default centres...", COLOR_BLUE);
    
    // Check if centres already exist
    $result = $conn->query("SELECT COUNT(*) as count FROM centres");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row['count'] > 0) {
            printMessage("✓ Centres already exist, skipping...", COLOR_YELLOW);
            return true;
        }
    }
    
    $sql = "INSERT INTO centres (name, code, address, city, state, phone, email) VALUES
        ('NIELIT Bhubaneswar', 'BBSR', 'OCAC Tower, Acharya Vihar', 'Bhubaneswar', 'Odisha', '0674-2960354', 'dir-bbsr@nielit.gov.in'),
        ('NIELIT Balasore Extension', 'BALA', 'Balasore', 'Balasore', 'Odisha', '', '')";
    
    if ($conn->query($sql)) {
        printMessage("✓ Default centres inserted successfully", COLOR_GREEN);
        return true;
    } else {
        printMessage("✗ Failed to insert default centres: " . $conn->error, COLOR_RED);
        return false;
    }
}

/**
 * Add centre_id to courses table
 */
function addCentreIdToCourses($conn) {
    printMessage("📦 Adding centre_id to courses table...", COLOR_BLUE);
    
    if (!tableExists($conn, 'courses')) {
        printMessage("✗ Courses table does not exist", COLOR_RED);
        return false;
    }
    
    if (columnExists($conn, 'courses', 'centre_id')) {
        printMessage("✓ centre_id column already exists, skipping...", COLOR_YELLOW);
        return true;
    }
    
    $sql = "ALTER TABLE courses 
        ADD COLUMN centre_id INT(11) DEFAULT NULL AFTER id,
        ADD KEY idx_centre (centre_id),
        ADD CONSTRAINT fk_course_centre FOREIGN KEY (centre_id) REFERENCES centres(id) ON DELETE SET NULL";
    
    if ($conn->query($sql)) {
        printMessage("✓ centre_id column added successfully", COLOR_GREEN);
        
        // Update existing courses to default centre
        $conn->query("UPDATE courses SET centre_id = 1 WHERE centre_id IS NULL");
        printMessage("✓ Existing courses updated with default centre", COLOR_GREEN);
        
        return true;
    } else {
        printMessage("✗ Failed to add centre_id column: " . $conn->error, COLOR_RED);
        return false;
    }
}

/**
 * Install themes table
 */
function installThemesTable($conn) {
    printMessage("📦 Installing themes table...", COLOR_BLUE);
    
    if (tableExists($conn, 'themes')) {
        printMessage("✓ Themes table already exists, skipping...", COLOR_YELLOW);
        return true;
    }
    
    $sql = "CREATE TABLE themes (
        id INT(11) NOT NULL AUTO_INCREMENT,
        theme_name VARCHAR(100) NOT NULL,
        primary_color VARCHAR(7) NOT NULL,
        secondary_color VARCHAR(7) NOT NULL,
        accent_color VARCHAR(7) NOT NULL,
        logo_path VARCHAR(255),
        favicon_path VARCHAR(255),
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 
    COMMENT='Stores theme customization configurations'";
    
    if ($conn->query($sql)) {
        printMessage("✓ Themes table created successfully", COLOR_GREEN);
        return true;
    } else {
        printMessage("✗ Failed to create themes table: " . $conn->error, COLOR_RED);
        return false;
    }
}

/**
 * Install homepage_content table
 */
function installHomepageContentTable($conn) {
    printMessage("📦 Installing homepage_content table...", COLOR_BLUE);
    
    if (tableExists($conn, 'homepage_content')) {
        printMessage("✓ Homepage_content table already exists, skipping...", COLOR_YELLOW);
        return true;
    }
    
    $sql = "CREATE TABLE homepage_content (
        id INT(11) NOT NULL AUTO_INCREMENT,
        section_key VARCHAR(50) NOT NULL UNIQUE,
        section_title VARCHAR(255) NOT NULL,
        section_content TEXT,
        section_type ENUM('banner', 'announcement', 'featured_course', 'text_block', 'image_block') NOT NULL,
        display_order INT(11) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_section_key (section_key),
        KEY idx_active (is_active),
        KEY idx_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 
    COMMENT='Stores dynamic homepage content sections'";
    
    if ($conn->query($sql)) {
        printMessage("✓ Homepage_content table created successfully", COLOR_GREEN);
        return true;
    } else {
        printMessage("✗ Failed to create homepage_content table: " . $conn->error, COLOR_RED);
        return false;
    }
}

/**
 * Create audit_logs table
 */
function createAuditLogsTable($conn) {
    printMessage("\n📋 Creating audit_logs table...", COLOR_BLUE);
    
    if (tableExists($conn, 'audit_logs')) {
        printMessage("✓ Audit_logs table already exists, skipping...", COLOR_YELLOW);
        return true;
    }
    
    $sql = "CREATE TABLE audit_logs (
        id INT(11) NOT NULL AUTO_INCREMENT,
        admin_username VARCHAR(100) NOT NULL,
        action_type ENUM('create', 'update', 'delete', 'activate', 'deactivate', 'reorder') NOT NULL,
        resource_type ENUM('centre', 'theme', 'homepage_content') NOT NULL,
        resource_id INT(11) DEFAULT NULL,
        resource_name VARCHAR(255) NOT NULL,
        result ENUM('success', 'failure') NOT NULL,
        details TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_admin_username (admin_username),
        KEY idx_action_type (action_type),
        KEY idx_resource_type (resource_type),
        KEY idx_resource_id (resource_id),
        KEY idx_result (result),
        KEY idx_created_at (created_at),
        KEY idx_resource_lookup (resource_type, resource_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 
    COMMENT='Audit log for administrative actions'";
    
    if ($conn->query($sql)) {
        printMessage("✓ Audit_logs table created successfully", COLOR_GREEN);
        return true;
    } else {
        printMessage("✗ Failed to create audit_logs table: " . $conn->error, COLOR_RED);
        return false;
    }
}

/**
 * Verify installation
 */
function verifyInstallation($conn) {
    printMessage("\n🔍 Verifying installation...", COLOR_BLUE);
    
    $errors = [];
    
    // Check centres table
    if (!tableExists($conn, 'centres')) {
        $errors[] = "Centres table does not exist";
    }
    
    // Check themes table
    if (!tableExists($conn, 'themes')) {
        $errors[] = "Themes table does not exist";
    }
    
    // Check homepage_content table
    if (!tableExists($conn, 'homepage_content')) {
        $errors[] = "Homepage_content table does not exist";
    }
    
    // Check audit_logs table
    if (!tableExists($conn, 'audit_logs')) {
        $errors[] = "Audit_logs table does not exist";
    }
    
    // Check centre_id column in courses
    if (tableExists($conn, 'courses') && !columnExists($conn, 'courses', 'centre_id')) {
        $errors[] = "centre_id column does not exist in courses table";
    }
    
    // Check default centres
    $result = $conn->query("SELECT COUNT(*) as count FROM centres");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row['count'] < 2) {
            $errors[] = "Default centres not inserted";
        }
    }
    
    if (empty($errors)) {
        printMessage("✓ All checks passed!", COLOR_GREEN);
        return true;
    } else {
        printMessage("✗ Verification failed:", COLOR_RED);
        foreach ($errors as $error) {
            printMessage("  - " . $error, COLOR_RED);
        }
        return false;
    }
}

/**
 * Main installation function
 */
function install($conn) {
    printMessage("\n🚀 Starting System Enhancement Module Installation...\n", COLOR_BLUE);
    
    $steps = [
        'installCentresTable',
        'insertDefaultCentres',
        'addCentreIdToCourses',
        'installThemesTable',
        'installHomepageContentTable',
        'createAuditLogsTable'
    ];
    
    foreach ($steps as $step) {
        if (!$step($conn)) {
            printMessage("\n✗ Installation failed at step: $step", COLOR_RED);
            return false;
        }
    }
    
    printMessage("\n✓ Installation completed successfully!", COLOR_GREEN);
    
    // Verify installation
    return verifyInstallation($conn);
}

/**
 * Rollback function
 */
function rollback($conn) {
    printMessage("\n⚠️  WARNING: This will remove all System Enhancement Module data!", COLOR_YELLOW);
    printMessage("This includes:", COLOR_YELLOW);
    printMessage("  - All centres", COLOR_YELLOW);
    printMessage("  - All themes", COLOR_YELLOW);
    printMessage("  - All homepage content", COLOR_YELLOW);
    printMessage("  - centre_id column from courses table", COLOR_YELLOW);
    
    if (php_sapi_name() === 'cli') {
        echo "\nType 'yes' to continue: ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) !== 'yes') {
            printMessage("Rollback cancelled", COLOR_YELLOW);
            return false;
        }
    }
    
    printMessage("\n🔄 Starting rollback...\n", COLOR_BLUE);
    
    // Remove foreign key constraint
    if (tableExists($conn, 'courses') && columnExists($conn, 'courses', 'centre_id')) {
        $conn->query("ALTER TABLE courses DROP FOREIGN KEY fk_course_centre");
        $conn->query("ALTER TABLE courses DROP COLUMN centre_id");
        printMessage("✓ Removed centre_id from courses table", COLOR_GREEN);
    }
    
    // Drop tables
    if (tableExists($conn, 'homepage_content')) {
        $conn->query("DROP TABLE homepage_content");
        printMessage("✓ Dropped homepage_content table", COLOR_GREEN);
    }
    
    if (tableExists($conn, 'themes')) {
        $conn->query("DROP TABLE themes");
        printMessage("✓ Dropped themes table", COLOR_GREEN);
    }
    
    if (tableExists($conn, 'centres')) {
        $conn->query("DROP TABLE centres");
        printMessage("✓ Dropped centres table", COLOR_GREEN);
    }
    
    printMessage("\n✓ Rollback completed successfully!", COLOR_GREEN);
    return true;
}

// Main execution
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? 'install';
    
    switch ($command) {
        case 'install':
            install($conn);
            break;
        case 'verify':
            verifyInstallation($conn);
            break;
        case 'rollback':
            rollback($conn);
            break;
        default:
            printMessage("Usage: php install_system_enhancement.php [install|verify|rollback]", COLOR_YELLOW);
            break;
    }
} else {
    // Web interface
    echo "<h1>System Enhancement Module Installation</h1>";
    install($conn);
}

$conn->close();
?>
