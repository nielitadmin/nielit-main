<?php
/**
 * Production-Safe Migration: Add Payment Details Control
 * 
 * This script safely adds the payment_details_required column
 * to the courses table if it doesn't already exist.
 */

// Include database configuration
require_once __DIR__ . '/../config/config.php';

echo "=== Production Payment Control Migration ===\n";

try {
    // Check if column already exists
    $check_query = "SHOW COLUMNS FROM courses LIKE 'payment_details_required'";
    $result = $conn->query($check_query);
    
    if ($result && $result->num_rows > 0) {
        echo "✓ Column 'payment_details_required' already exists. No action needed.\n";
    } else {
        echo "Adding 'payment_details_required' column to courses table...\n";
        
        // Add the column
        $add_column_sql = "ALTER TABLE courses 
                          ADD COLUMN payment_details_required ENUM('optional', 'required') DEFAULT 'optional' 
                          AFTER enrollment_status";
        
        if ($conn->query($add_column_sql)) {
            echo "✓ Successfully added payment_details_required column\n";
            
            // Add index for better performance
            $add_index_sql = "ALTER TABLE courses 
                             ADD INDEX idx_payment_details_required (payment_details_required)";
            
            if ($conn->query($add_index_sql)) {
                echo "✓ Successfully added index for payment_details_required\n";
            } else {
                echo "⚠ Warning: Could not add index (not critical): " . $conn->error . "\n";
            }
            
        } else {
            throw new Exception("Failed to add column: " . $conn->error);
        }
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "Payment details control feature is now available!\n";
    echo "All existing courses default to 'optional' payment details.\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Please check database permissions and try again.\n";
    exit(1);
}

$conn->close();
?>