<?php
/**
 * PRODUCTION-SAFE Migration: Add Payment Details Control
 * 
 * This script safely adds the payment_details_required column
 * to the courses table on production servers.
 * 
 * SAFE TO RUN MULTIPLE TIMES - Will not cause errors if column already exists
 */

// Only run if accessed directly or via command line
if (basename($_SERVER['PHP_SELF']) !== basename(__FILE__) && php_sapi_name() !== 'cli') {
    die('This migration must be run directly.');
}

require_once __DIR__ . '/../config/config.php';

echo "=== PRODUCTION-SAFE Payment Control Migration ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Server: " . $_SERVER['HTTP_HOST'] ?? 'CLI' . "\n\n";

try {
    // Step 1: Check if column already exists
    echo "Step 1: Checking if payment_details_required column exists...\n";
    $check_query = "SHOW COLUMNS FROM courses LIKE 'payment_details_required'";
    $result = $conn->query($check_query);
    
    if ($result && $result->num_rows > 0) {
        echo "✅ Column 'payment_details_required' already exists. No action needed.\n";
        echo "✅ Migration already complete!\n\n";
        
        // Show current column info
        $column_info = $result->fetch_assoc();
        echo "Current column details:\n";
        echo "- Type: " . $column_info['Type'] . "\n";
        echo "- Default: " . ($column_info['Default'] ?? 'NULL') . "\n";
        echo "- Null: " . $column_info['Null'] . "\n\n";
        
    } else {
        echo "❌ Column 'payment_details_required' does not exist. Adding now...\n\n";
        
        // Step 2: Add the column
        echo "Step 2: Adding payment_details_required column...\n";
        $add_column_sql = "ALTER TABLE courses 
                          ADD COLUMN payment_details_required ENUM('optional', 'required') DEFAULT 'optional' 
                          AFTER enrollment_status";
        
        if ($conn->query($add_column_sql)) {
            echo "✅ Successfully added payment_details_required column\n";
            
            // Step 3: Set default values for existing courses
            echo "Step 3: Setting default values for existing courses...\n";
            $update_sql = "UPDATE courses 
                          SET payment_details_required = 'optional' 
                          WHERE payment_details_required IS NULL";
            
            if ($conn->query($update_sql)) {
                $affected_rows = $conn->affected_rows;
                echo "✅ Updated $affected_rows existing courses to 'optional'\n";
            } else {
                echo "⚠️ Warning: Could not update existing courses: " . $conn->error . "\n";
            }
            
            // Step 4: Add index for better performance (optional)
            echo "Step 4: Adding index for better performance...\n";
            $add_index_sql = "ALTER TABLE courses 
                             ADD INDEX idx_payment_details_required (payment_details_required)";
            
            if ($conn->query($add_index_sql)) {
                echo "✅ Successfully added index for payment_details_required\n";
            } else {
                echo "⚠️ Warning: Could not add index (not critical): " . $conn->error . "\n";
            }
            
        } else {
            echo "❌ Error adding column: " . $conn->error . "\n";
            exit(1);
        }
    }
    
    // Step 5: Verify the migration
    echo "\nStep 5: Verifying migration...\n";
    $verify_query = "SHOW COLUMNS FROM courses LIKE 'payment_details_required'";
    $verify_result = $conn->query($verify_query);
    
    if ($verify_result && $verify_result->num_rows > 0) {
        echo "✅ Verification successful - column exists and is accessible\n";
        
        // Test a sample query
        $test_query = "SELECT COUNT(*) as total, 
                              SUM(CASE WHEN payment_details_required = 'optional' THEN 1 ELSE 0 END) as optional_count,
                              SUM(CASE WHEN payment_details_required = 'required' THEN 1 ELSE 0 END) as required_count
                       FROM courses";
        $test_result = $conn->query($test_query);
        
        if ($test_result) {
            $stats = $test_result->fetch_assoc();
            echo "\nCourse Payment Settings Summary:\n";
            echo "- Total courses: " . $stats['total'] . "\n";
            echo "- Optional payment: " . $stats['optional_count'] . "\n";
            echo "- Required payment: " . $stats['required_count'] . "\n";
        }
        
    } else {
        echo "❌ Verification failed - column not found after migration\n";
        exit(1);
    }
    
    echo "\n=== MIGRATION COMPLETE ===\n";
    echo "✅ Payment details control feature is now available!\n";
    echo "✅ Administrators can now control payment requirements per course.\n";
    echo "✅ The edit_course.php page will now show payment control options.\n\n";
    
    echo "Next steps:\n";
    echo "1. Test the admin panel: admin/edit_course.php\n";
    echo "2. Test student registration with different payment settings\n";
    echo "3. Verify the payment control system is working correctly\n\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed with exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

$conn->close();

echo "Migration script completed successfully!\n";
?>