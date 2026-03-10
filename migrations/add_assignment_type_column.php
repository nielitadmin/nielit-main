<?php
/**
 * Migration: Add assignment_type column to admin_course_assignments
 * Date: 2026-03-10
 * Description: Adds assignment_type column to track manual vs auto assignments
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Adding assignment_type column to admin_course_assignments table</h2>";

try {
    // Check if column already exists
    $check_query = "SHOW COLUMNS FROM admin_course_assignments LIKE 'assignment_type'";
    $check_result = $conn->query($check_query);
    
    if ($check_result->num_rows > 0) {
        echo "<div style='color: orange;'>⚠️ Column 'assignment_type' already exists. Skipping...</div>";
    } else {
        // Add the column
        $alter_query = "ALTER TABLE admin_course_assignments 
                       ADD COLUMN assignment_type VARCHAR(20) DEFAULT 'Manual' 
                       AFTER assigned_by";
        
        if ($conn->query($alter_query)) {
            echo "<div style='color: green;'>✅ Successfully added assignment_type column</div>";
            
            // Update existing records
            $update_query = "UPDATE admin_course_assignments 
                           SET assignment_type = 'Manual' 
                           WHERE assignment_type IS NULL";
            
            if ($conn->query($update_query)) {
                echo "<div style='color: green;'>✅ Updated existing records with 'Manual' type</div>";
            }
            
            // Add index
            $index_query = "CREATE INDEX idx_assignment_type ON admin_course_assignments(assignment_type)";
            if ($conn->query($index_query)) {
                echo "<div style='color: green;'>✅ Added index for assignment_type column</div>";
            }
            
        } else {
            echo "<div style='color: red;'>❌ Error adding column: " . $conn->error . "</div>";
        }
    }
    
    // Show current table structure
    echo "<h3>Current Table Structure:</h3>";
    $desc_query = "DESCRIBE admin_course_assignments";
    $desc_result = $conn->query($desc_query);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $desc_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
}

$conn->close();
?>

<p><a href="../admin/manage_course_assignments.php">← Back to Course Assignments</a></p>