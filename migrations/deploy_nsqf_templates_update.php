<?php
/**
 * Production Deployment Script for NSQF Templates Category/Sub-Category Update
 * 
 * This script safely deploys the NSQF templates update to production
 * with proper error handling and rollback capabilities.
 */

// Set error reporting for production deployment
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include configuration
require_once __DIR__ . '/../config/config.php';

// Deployment configuration
$deployment_config = [
    'backup_enabled' => true,
    'backup_directory' => __DIR__ . '/backups/',
    'log_file' => __DIR__ . '/deployment.log',
    'dry_run' => false // Set to true for testing
];

/**
 * Log deployment messages
 */
function log_deployment($message, $level = 'INFO') {
    global $deployment_config;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message\n";
    
    echo $log_message;
    
    if (isset($deployment_config['log_file'])) {
        file_put_contents($deployment_config['log_file'], $log_message, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Create database backup
 */
function create_backup($conn) {
    global $deployment_config;
    
    if (!$deployment_config['backup_enabled']) {
        log_deployment("Backup disabled, skipping...", 'WARNING');
        return true;
    }
    
    // Create backup directory if it doesn't exist
    if (!file_exists($deployment_config['backup_directory'])) {
        mkdir($deployment_config['backup_directory'], 0755, true);
    }
    
    $backup_file = $deployment_config['backup_directory'] . 'nsqf_templates_backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Get database credentials from config
    $db_host = DB_HOST;
    $db_name = DB_NAME;
    $db_user = DB_USER;
    $db_pass = DB_PASS;
    
    // Create mysqldump command
    $command = "mysqldump -h $db_host -u $db_user -p$db_pass $db_name nsqf_course_templates > $backup_file 2>&1";
    
    log_deployment("Creating backup: $backup_file");
    
    if ($deployment_config['dry_run']) {
        log_deployment("DRY RUN: Would execute: $command", 'INFO');
        return true;
    }
    
    $output = [];
    $return_code = 0;
    exec($command, $output, $return_code);
    
    if ($return_code === 0 && file_exists($backup_file)) {
        log_deployment("Backup created successfully: $backup_file", 'SUCCESS');
        return $backup_file;
    } else {
        log_deployment("Backup failed. Return code: $return_code", 'ERROR');
        log_deployment("Output: " . implode("\n", $output), 'ERROR');
        return false;
    }
}

/**
 * Check prerequisites
 */
function check_prerequisites($conn) {
    log_deployment("Checking deployment prerequisites...");
    
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'nsqf_course_templates'");
    if ($result->num_rows === 0) {
        log_deployment("Table nsqf_course_templates does not exist", 'ERROR');
        return false;
    }
    
    // Check if nsqf_type column already exists
    $result = $conn->query("SHOW COLUMNS FROM nsqf_course_templates LIKE 'nsqf_type'");
    if ($result->num_rows > 0) {
        log_deployment("Column nsqf_type already exists - deployment may have been run before", 'WARNING');
    }
    
    // Check for existing data
    $result = $conn->query("SELECT COUNT(*) as count FROM nsqf_course_templates");
    $row = $result->fetch_assoc();
    log_deployment("Found {$row['count']} existing templates");
    
    // Check admin users
    $result = $conn->query("SELECT COUNT(*) as count FROM admin WHERE role = 'nsqf_course_manager'");
    $row = $result->fetch_assoc();
    log_deployment("Found {$row['count']} NSQF course managers");
    
    return true;
}

/**
 * Execute the migration
 */
function execute_migration($conn) {
    global $deployment_config;
    
    log_deployment("Starting database migration...");
    
    try {
        // Start transaction
        $conn->autocommit(false);
        
        // Check if nsqf_type column exists
        $check_column = $conn->query("SHOW COLUMNS FROM nsqf_course_templates LIKE 'nsqf_type'");
        
        if ($check_column->num_rows == 0) {
            log_deployment("Adding nsqf_type column...");
            
            if (!$deployment_config['dry_run']) {
                $add_column_sql = "ALTER TABLE nsqf_course_templates 
                                  ADD COLUMN nsqf_type VARCHAR(50) NOT NULL DEFAULT 'NSQF Course' 
                                  AFTER category";
                
                if (!$conn->query($add_column_sql)) {
                    throw new Exception("Failed to add nsqf_type column: " . $conn->error);
                }
            }
            
            log_deployment("✅ Added nsqf_type column successfully!");
        } else {
            log_deployment("✅ nsqf_type column already exists!");
        }
        
        // Update category column
        log_deployment("Updating category column to VARCHAR...");
        
        if (!$deployment_config['dry_run']) {
            $modify_category_sql = "ALTER TABLE nsqf_course_templates 
                                   MODIFY COLUMN category VARCHAR(255) NOT NULL";
            
            if (!$conn->query($modify_category_sql)) {
                throw new Exception("Failed to update category column: " . $conn->error);
            }
        }
        
        log_deployment("✅ Updated category column to VARCHAR!");
        
        // Update existing data
        log_deployment("Migrating existing data...");
        
        if (!$deployment_config['dry_run']) {
            $update_data_sql = "UPDATE nsqf_course_templates SET 
                                category = CASE 
                                    WHEN category = 'Long Term NSQF' THEN 'Skill Based (Long Term) >500 hrs'
                                    WHEN category = 'Short Term NSQF' THEN 'Short Term / Digital Competency <=90 hrs'
                                    ELSE category
                                END,
                                nsqf_type = 'NSQF Course'
                                WHERE category IN ('Long Term NSQF', 'Short Term NSQF')";
            
            if (!$conn->query($update_data_sql)) {
                throw new Exception("Failed to update existing data: " . $conn->error);
            }
        }
        
        log_deployment("✅ Updated existing data to new structure!");
        
        // Commit transaction
        if (!$deployment_config['dry_run']) {
            $conn->commit();
        }
        
        log_deployment("✅ Migration completed successfully!", 'SUCCESS');
        return true;
        
    } catch (Exception $e) {
        // Rollback on error
        if (!$deployment_config['dry_run']) {
            $conn->rollback();
        }
        log_deployment("Migration failed: " . $e->getMessage(), 'ERROR');
        return false;
    } finally {
        $conn->autocommit(true);
    }
}

/**
 * Verify deployment
 */
function verify_deployment($conn) {
    log_deployment("Verifying deployment...");
    
    try {
        // Check table structure
        $result = $conn->query("SHOW COLUMNS FROM nsqf_course_templates");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        if (!in_array('nsqf_type', $columns)) {
            log_deployment("Verification failed: nsqf_type column not found", 'ERROR');
            return false;
        }
        
        // Check data distribution
        $result = $conn->query("SELECT category, nsqf_type, COUNT(*) as count 
                               FROM nsqf_course_templates 
                               GROUP BY category, nsqf_type");
        
        log_deployment("📊 Current template distribution:");
        while ($row = $result->fetch_assoc()) {
            log_deployment("   - {$row['category']} | {$row['nsqf_type']}: {$row['count']} templates");
        }
        
        // Check for any NULL values
        $result = $conn->query("SELECT COUNT(*) as count FROM nsqf_course_templates WHERE nsqf_type IS NULL");
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            log_deployment("Warning: Found {$row['count']} templates with NULL nsqf_type", 'WARNING');
        }
        
        log_deployment("✅ Deployment verification completed!", 'SUCCESS');
        return true;
        
    } catch (Exception $e) {
        log_deployment("Verification failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Main deployment execution
function main() {
    global $conn, $deployment_config;
    
    log_deployment("=== NSQF Templates Category/Sub-Category Deployment Started ===", 'INFO');
    log_deployment("Dry run mode: " . ($deployment_config['dry_run'] ? 'ENABLED' : 'DISABLED'));
    
    // Step 1: Check prerequisites
    if (!check_prerequisites($conn)) {
        log_deployment("Prerequisites check failed. Aborting deployment.", 'ERROR');
        return false;
    }
    
    // Step 2: Create backup
    $backup_file = create_backup($conn);
    if ($deployment_config['backup_enabled'] && !$backup_file) {
        log_deployment("Backup creation failed. Aborting deployment.", 'ERROR');
        return false;
    }
    
    // Step 3: Execute migration
    if (!execute_migration($conn)) {
        log_deployment("Migration failed. Check logs for details.", 'ERROR');
        if ($backup_file) {
            log_deployment("Backup available for rollback: $backup_file", 'INFO');
        }
        return false;
    }
    
    // Step 4: Verify deployment
    if (!verify_deployment($conn)) {
        log_deployment("Deployment verification failed.", 'ERROR');
        return false;
    }
    
    log_deployment("=== NSQF Templates Deployment Completed Successfully ===", 'SUCCESS');
    log_deployment("🎉 Deployment Summary:");
    log_deployment("   - Database schema updated");
    log_deployment("   - Existing data migrated");
    log_deployment("   - Verification passed");
    if ($backup_file) {
        log_deployment("   - Backup created: $backup_file");
    }
    
    return true;
}

// Execute deployment
if (main()) {
    exit(0); // Success
} else {
    exit(1); // Failure
}
?>