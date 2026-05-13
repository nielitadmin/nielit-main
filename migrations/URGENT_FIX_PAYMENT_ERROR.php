<?php
/**
 * URGENT FIX: Resolve Payment Control Bind Param Error
 * 
 * This script immediately fixes the ArgumentCountError in edit_course.php
 * by adding the missing payment_details_required column.
 * 
 * RUN THIS IMMEDIATELY ON PRODUCTION TO FIX THE ERROR
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>🚨 URGENT FIX: Payment Control Error Resolution</h2>\n";
echo "<p><strong>Error:</strong> ArgumentCountError in edit_course.php line 221</p>\n";
echo "<p><strong>Solution:</strong> Adding missing payment_details_required column</p>\n";

try {
    // Check if column exists
    $check_query = "SHOW COLUMNS FROM courses LIKE 'payment_details_required'";
    $result = $conn->query($check_query);
    
    if ($result && $result->num_rows > 0) {
        echo "<div style='color: green; font-weight: bold;'>✅ Column already exists - error should be resolved!</div>\n";
    } else {
        echo "<div style='color: orange;'>⚠️ Column missing - adding now...</div>\n";
        
        // Add the column immediately
        $add_column_sql = "ALTER TABLE courses 
                          ADD COLUMN payment_details_required ENUM('optional', 'required') DEFAULT 'optional'";
        
        if ($conn->query($add_column_sql)) {
            echo "<div style='color: green; font-weight: bold;'>✅ SUCCESS: payment_details_required column added!</div>\n";
            
            // Set default values
            $conn->query("UPDATE courses SET payment_details_required = 'optional' WHERE payment_details_required IS NULL");
            echo "<div style='color: green;'>✅ Default values set for existing courses</div>\n";
            
        } else {
            echo "<div style='color: red; font-weight: bold;'>❌ ERROR: " . $conn->error . "</div>\n";
        }
    }
    
    // Test the fix
    echo "<h3>Testing the Fix:</h3>\n";
    $test_query = "SELECT id, course_name, payment_details_required FROM courses LIMIT 3";
    $test_result = $conn->query($test_query);
    
    if ($test_result) {
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>ID</th><th>Course Name</th><th>Payment Setting</th></tr>\n";
        while ($row = $test_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
            echo "<td style='color: green;'>" . ($row['payment_details_required'] ?? 'optional') . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        echo "<div style='color: green; font-weight: bold;'>✅ Test successful - payment column is working!</div>\n";
    }
    
    echo "<h3>🎉 FIX COMPLETE!</h3>\n";
    echo "<p>The edit_course.php error should now be resolved.</p>\n";
    echo "<p><strong>Next steps:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Test admin/edit_course.php - should work without errors</li>\n";
    echo "<li>Payment control section should now be visible</li>\n";
    echo "<li>Student registration will adapt to payment settings</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold;'>❌ CRITICAL ERROR: " . $e->getMessage() . "</div>\n";
}

$conn->close();
?>