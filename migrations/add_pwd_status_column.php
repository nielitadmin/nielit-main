<?php
/**
 * Database Migration: Add pwd_status column to students table
 * 
 * This script adds the pwd_status column to track Persons with Disabilities status
 * Run this script once to update the database schema
 */

require_once __DIR__ . '/config/config.php';

echo "<h2>Adding PWD Status Column to Students Table</h2>";
echo "<hr>";

// Check if column already exists
$check_query = "SHOW COLUMNS FROM students LIKE 'pwd_status'";
$result = $conn->query($check_query);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'><strong>⚠️ Column 'pwd_status' already exists!</strong></p>";
    echo "<p>No changes needed. The database is already up to date.</p>";
} else {
    // Add the column
    $alter_query = "ALTER TABLE students 
                    ADD COLUMN pwd_status VARCHAR(3) DEFAULT 'No' 
                    AFTER category";
    
    if ($conn->query($alter_query) === TRUE) {
        echo "<p style='color: green;'><strong>✅ SUCCESS!</strong></p>";
        echo "<p>Column 'pwd_status' has been added to the students table.</p>";
        echo "<ul>";
        echo "<li>Column Type: VARCHAR(3)</li>";
        echo "<li>Default Value: 'No'</li>";
        echo "<li>Position: After 'category' column</li>";
        echo "<li>Allowed Values: 'Yes', 'No'</li>";
        echo "</ul>";
        
        // Verify the column was added
        $verify_query = "SHOW COLUMNS FROM students LIKE 'pwd_status'";
        $verify_result = $conn->query($verify_query);
        
        if ($verify_result->num_rows > 0) {
            $column_info = $verify_result->fetch_assoc();
            echo "<h3>Column Details:</h3>";
            echo "<pre>";
            print_r($column_info);
            echo "</pre>";
        }
        
        // Count existing records
        $count_query = "SELECT COUNT(*) as total FROM students";
        $count_result = $conn->query($count_query);
        $count = $count_result->fetch_assoc()['total'];
        
        echo "<h3>Impact:</h3>";
        echo "<p>Total existing student records: <strong>$count</strong></p>";
        echo "<p>All existing records will have pwd_status = 'No' (default value)</p>";
        
    } else {
        echo "<p style='color: red;'><strong>❌ ERROR!</strong></p>";
        echo "<p>Failed to add column: " . $conn->error . "</p>";
    }
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Verify the column was added successfully above</li>";
echo "<li>Test registration form with PWD field</li>";
echo "<li>Test admin views with PWD status</li>";
echo "<li>Delete this migration script after successful deployment</li>";
echo "</ol>";

$conn->close();
?>
