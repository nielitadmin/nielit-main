<?php
/**
 * Add eligibility column to courses table
 * Required for NSQF template integration
 */

require_once __DIR__ . '/../config/config.php';

echo "Adding eligibility column to courses table...\n";

try {
    // Check if eligibility column already exists
    $check_sql = "SHOW COLUMNS FROM courses LIKE 'eligibility'";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows > 0) {
        echo "✅ Eligibility column already exists!\n";
    } else {
        // Add eligibility column
        $sql = "ALTER TABLE courses ADD COLUMN eligibility TEXT AFTER description";
        
        if ($conn->query($sql)) {
            echo "✅ Eligibility column added successfully!\n";
        } else {
            echo "❌ Error adding eligibility column: " . $conn->error . "\n";
        }
        
        // Add index for better performance
        $index_sql = "CREATE INDEX idx_courses_eligibility ON courses(eligibility(100))";
        
        if ($conn->query($index_sql)) {
            echo "✅ Eligibility index created!\n";
        } else {
            echo "⚠️ Warning: Could not create eligibility index: " . $conn->error . "\n";
        }
    }
    
    // Update table comment
    $comment_sql = "ALTER TABLE courses COMMENT = 'Courses table with eligibility criteria support for NSQF integration'";
    
    if ($conn->query($comment_sql)) {
        echo "✅ Table comment updated!\n";
    } else {
        echo "⚠️ Warning: Could not update table comment: " . $conn->error . "\n";
    }
    
    echo "\n🎉 Eligibility column installation completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>