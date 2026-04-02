<?php
/**
 * Migration: Add front_office_desk role to admin table
 */
require_once __DIR__ . '/../config/config.php';

echo "<h2>🔧 Front Office Desk Role Migration</h2>";

// Add role to enum
$result = $conn->query("ALTER TABLE admin MODIFY COLUMN role ENUM('master_admin','course_coordinator','nsqf_course_manager','data_entry_operator','report_viewer','front_office_desk') NOT NULL DEFAULT 'course_coordinator'");

if ($result) {
    echo "<p>✅ front_office_desk role added to admin table.</p>";
} else {
    // Try with existing enum values from DB
    $enum_result = $conn->query("SHOW COLUMNS FROM admin LIKE 'role'");
    $row = $enum_result->fetch_assoc();
    echo "<p>Current enum: " . $row['Type'] . "</p>";
    echo "<p>⚠️ Could not alter: " . $conn->error . " — role may already exist.</p>";
}

echo "<p><a href='../admin/add_admin.php'>Go to Add Admin</a></p>";
$conn->close();
?>
