<?php
/**
 * Migration: Add enrollment_status column to courses table
 * This adds enrollment status functionality to courses
 */

require_once __DIR__ . '/../config/config.php';

echo "Adding enrollment_status column to courses table...\n\n";

try {
    // Add enrollment_status column
    $sql = "ALTER TABLE courses ADD COLUMN enrollment_status ENUM('ongoing', 'closed') DEFAULT 'ongoing' AFTER link_published";
    
    if ($conn->query($sql) === TRUE) {
        echo "✅ Successfully added enrollment_status column to courses table\n";
        echo "   - Column type: ENUM('ongoing', 'closed')\n";
        echo "   - Default value: 'ongoing'\n";
        echo "   - Position: After link_published column\n\n";
    } else {
        throw new Exception("Error adding enrollment_status column: " . $conn->error);
    }
    
    // Verify the column was added
    $result = $conn->query("SHOW COLUMNS FROM courses LIKE 'enrollment_status'");
    if ($result && $result->num_rows > 0) {
        $column = $result->fetch_assoc();
        echo "✅ Column verification successful:\n";
        echo "   - Field: " . $column['Field'] . "\n";
        echo "   - Type: " . $column['Type'] . "\n";
        echo "   - Default: " . $column['Default'] . "\n\n";
    }
    
    echo "🎉 Migration completed successfully!\n";
    echo "📝 Next steps:\n";
    echo "   1. Update edit_course.php to include enrollment status field\n";
    echo "   2. Update public courses page to display enrollment status\n";
    echo "   3. Add appropriate styling for closed enrollments\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    
    // Check if column already exists
    $result = $conn->query("SHOW COLUMNS FROM courses LIKE 'enrollment_status'");
    if ($result && $result->num_rows > 0) {
        echo "ℹ️  Note: enrollment_status column already exists in the table\n";
    }
}

$conn->close();
?>