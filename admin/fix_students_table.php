<?php
/**
 * Fix Students Table - Add attendance_qr_code Column
 * NIELIT Bhubaneswar
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Fixing Students Table</h2>";

// Add attendance_qr_code column to students table
$sql = "ALTER TABLE `students` ADD COLUMN `attendance_qr_code` VARCHAR(255) DEFAULT NULL";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>✓ Added attendance_qr_code column to students table</p>";
} else {
    // Check if column already exists
    if (strpos($conn->error, 'Duplicate column name') !== false) {
        echo "<p style='color: orange;'>⚠ Column already exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($conn->error) . "</p>";
    }
}

// Verify the column was added
$column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'attendance_qr_code'");
if ($column_check && $column_check->num_rows > 0) {
    echo "<p style='color: green;'>✓ attendance_qr_code column confirmed in students table</p>";
} else {
    echo "<p style='color: red;'>✗ attendance_qr_code column still missing</p>";
}

echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h4>✅ Students Table Fixed!</h4>";
echo "<p>The students table now has the attendance_qr_code column.</p>";
echo "<p>Next step: Generate QR codes for all students.</p>";
echo "</div>";

$conn->close();
?>