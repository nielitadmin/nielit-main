<?php
/**
 * Batch student placement columns + placement_coordinator role support.
 * Run once via System Settings → DB Migrations.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../batch_module/includes/batch_placement_helper.php';

echo "Installing batch student placement support...\n";

if (ensureBatchPlacementSchema($conn)) {
    echo "OK: batch_students placement columns are ready.\n";
} else {
    echo "WARN: batch_students table not found. Run batch module setup first.\n";
}

$roleSql = "ALTER TABLE admin MODIFY COLUMN role ENUM(
    'master_admin',
    'course_coordinator',
    'nsqf_course_manager',
    'data_entry_operator',
    'report_viewer',
    'front_office_desk',
    'placement_coordinator'
) NOT NULL DEFAULT 'course_coordinator'";

if ($conn->query($roleSql)) {
    echo "OK: placement_coordinator role added to admin table.\n";
} else {
    echo "WARN: Could not update admin role enum: " . $conn->error . "\n";
}

echo "Done.\n";
