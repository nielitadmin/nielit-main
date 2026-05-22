<?php
/**
 * Add staff_category column to faculty table
 * This fixes the missing column issue in manage_faculty.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    echo "Adding staff_category column to faculty table...\n";
    
    // Check if column already exists
    $check_column = "SHOW COLUMNS FROM faculty LIKE 'staff_category'";
    $result = $conn->query($check_column);
    
    if ($result && $result->num_rows > 0) {
        echo "✓ staff_category column already exists\n";
    } else {
        // Add the missing column
        $sql = "ALTER TABLE faculty ADD COLUMN staff_category VARCHAR(50) DEFAULT 'Teaching' AFTER department";
        
        if ($conn->query($sql)) {
            echo "✓ staff_category column added successfully\n";
            
            // Add index for better performance
            $index_sql = "ALTER TABLE faculty ADD INDEX idx_category (staff_category)";
            if ($conn->query($index_sql)) {
                echo "✓ Index added for staff_category column\n";
            }
        } else {
            throw new Exception("Error adding staff_category column: " . $conn->error);
        }
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>