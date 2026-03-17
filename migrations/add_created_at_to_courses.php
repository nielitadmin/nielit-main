<?php
/**
 * Migration: Add created_at column to courses table
 * Purpose: Add timestamp tracking for course creation
 * Date: March 17, 2026
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Adding created_at column to courses table...</h2>\n";

try {
    // Check if created_at column already exists
    $check_sql = "SHOW COLUMNS FROM courses LIKE 'created_at'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        echo "✅ created_at column already exists in courses table<br>\n";
    } else {
        // Add created_at column
        $alter_sql = "ALTER TABLE courses ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        
        if ($conn->query($alter_sql)) {
            echo "✅ Successfully added created_at column to courses table<br>\n";
            
            // Update existing records with current timestamp
            $update_sql = "UPDATE courses SET created_at = NOW() WHERE created_at IS NULL";
            if ($conn->query($update_sql)) {
                echo "✅ Updated existing courses with current timestamp<br>\n";
            }
        } else {
            throw new Exception("Failed to add created_at column: " . $conn->error);
        }
    }
    
    // Verify the column was added correctly
    $verify_sql = "DESCRIBE courses";
    $verify_result = $conn->query($verify_sql);
    
    echo "<h3>✅ Courses table structure:</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
    
    while ($row = $verify_result->fetch_assoc()) {
        $highlight = ($row['Field'] === 'created_at') ? ' style="background-color: #ffffcc;"' : '';
        echo "<tr{$highlight}>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h3>🎉 Migration completed successfully!</h3>\n";
    echo "<p>The courses table now has a created_at column for timestamp tracking.</p>\n";
    
} catch (Exception $e) {
    echo "<h3>❌ Migration failed!</h3>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

$conn->close();
?>