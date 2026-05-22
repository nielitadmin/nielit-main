<?php
/**
 * Quick fix for faculty table - adds missing staff_category column
 * Run this file once to fix the database issue
 */

require_once __DIR__ . '/config/database.php';

try {
    echo "<h2>Fixing Faculty Table...</h2>";
    
    // Check if faculty table exists
    $check_table = "SHOW TABLES LIKE 'faculty'";
    $result = $conn->query($check_table);
    
    if (!$result || $result->num_rows == 0) {
        echo "<p>❌ Faculty table doesn't exist. Running full installation...</p>";
        
        // Run the full installation
        include __DIR__ . '/migrations/install_faculty_management.php';
        
    } else {
        echo "<p>✓ Faculty table exists</p>";
        
        // Check if staff_category column exists
        $check_column = "SHOW COLUMNS FROM faculty LIKE 'staff_category'";
        $result = $conn->query($check_column);
        
        if (!$result || $result->num_rows == 0) {
            echo "<p>Adding missing staff_category column...</p>";
            
            // Add the missing column
            $sql = "ALTER TABLE faculty ADD COLUMN staff_category VARCHAR(50) DEFAULT 'Teaching' AFTER department";
            
            if ($conn->query($sql)) {
                echo "<p>✓ staff_category column added successfully</p>";
                
                // Add index for better performance
                $index_sql = "ALTER TABLE faculty ADD INDEX idx_category (staff_category)";
                if ($conn->query($index_sql)) {
                    echo "<p>✓ Index added for staff_category column</p>";
                }
            } else {
                throw new Exception("Error adding staff_category column: " . $conn->error);
            }
        } else {
            echo "<p>✓ staff_category column already exists</p>";
        }
    }
    
    echo "<h3>✅ Faculty table is now ready!</h3>";
    echo "<p><a href='admin/manage_faculty.php'>Go to Staff Management</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error: " . $e->getMessage() . "</h3>";
}
?>