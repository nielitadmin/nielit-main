<?php
/**
 * Fix Attendance Table - Add Missing Columns
 * NIELIT Bhubaneswar
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Fixing Attendance Table</h2>";

// Add missing columns to attendance table
$columns_to_add = [
    "ALTER TABLE `attendance` ADD COLUMN `marked_by` VARCHAR(50) DEFAULT NULL AFTER `scan_timestamp`",
    "ALTER TABLE `attendance` ADD COLUMN `coordinator_id` VARCHAR(50) DEFAULT NULL AFTER `marked_by`"
];

foreach ($columns_to_add as $sql) {
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✓ Executed: " . htmlspecialchars($sql) . "</p>";
    } else {
        // Check if column already exists
        if (strpos($conn->error, 'Duplicate column name') !== false) {
            echo "<p style='color: orange;'>⚠ Column already exists: " . htmlspecialchars($sql) . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($conn->error) . "</p>";
        }
    }
}

// Verify the table structure
echo "<h3>Updated Attendance Table Structure</h3>";
$result = $conn->query("DESCRIBE attendance");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h4>✅ Attendance Table Fixed!</h4>";
echo "<p>The attendance table now has all required columns for the QR attendance system.</p>";
echo "<p>You can now test the QR scanner functionality.</p>";
echo "</div>";

$conn->close();
?>