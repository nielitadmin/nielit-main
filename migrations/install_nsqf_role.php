<?php
/**
 * NSQF Role Installation Script
 * Adds NSQF Course Manager role to the RBAC system
 */

require_once __DIR__ . '/../config/config.php';

echo "Installing NSQF Course Manager Role...\n";

try {
    // Add nsqf_course_manager role to the existing enum
    $sql = "ALTER TABLE admin 
            MODIFY COLUMN role ENUM(
                'master_admin', 
                'course_coordinator', 
                'nsqf_course_manager',
                'data_entry_operator', 
                'report_viewer'
            ) NOT NULL DEFAULT 'master_admin'";
    
    if ($conn->query($sql)) {
        echo "✅ NSQF Course Manager role added successfully!\n";
    } else {
        echo "⚠️ Warning: Could not add NSQF role: " . $conn->error . "\n";
    }
    
    // Add comment to document the new role
    $comment_sql = "ALTER TABLE admin 
                    COMMENT = 'Admin users with role-based access control. nsqf_course_manager can only manage Long Term NSQF and Short Term NSQF courses.'";
    
    if ($conn->query($comment_sql)) {
        echo "✅ Table comment updated!\n";
    } else {
        echo "⚠️ Warning: Could not update table comment: " . $conn->error . "\n";
    }
    
    // Verify the migration
    $verify_sql = "SELECT COLUMN_NAME, COLUMN_TYPE 
                   FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_NAME = 'admin' 
                   AND COLUMN_NAME = 'role'";
    
    $result = $conn->query($verify_sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "✅ Role column verified: " . $row['COLUMN_TYPE'] . "\n";
        
        // Check if nsqf_course_manager is in the enum
        if (strpos($row['COLUMN_TYPE'], 'nsqf_course_manager') !== false) {
            echo "✅ NSQF Course Manager role is available!\n";
        } else {
            echo "❌ NSQF Course Manager role not found in enum!\n";
        }
    }
    
    echo "\n🎉 NSQF Role installation completed!\n";
    echo "\n📋 Next steps:\n";
    echo "1. Go to Add Admin page to create NSQF Course Manager users\n";
    echo "2. NSQF Course Managers can only create/edit Long Term NSQF and Short Term NSQF courses\n";
    echo "3. They will see a filtered course list showing only NSQF courses\n";
    
} catch (Exception $e) {
    echo "❌ Error installing NSQF role: " . $e->getMessage() . "\n";
}

$conn->close();
?>