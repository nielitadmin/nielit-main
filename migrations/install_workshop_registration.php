<?php
/**
 * Workshop registration: registration_form on courses, class_standard on students.
 */
require_once __DIR__ . '/../config/config.php';

echo "=== Workshop Registration Migration ===\n";

$statements = [
    "ALTER TABLE courses ADD COLUMN registration_form ENUM('full','workshop') NOT NULL DEFAULT 'full' AFTER course_type",
    "UPDATE courses SET registration_form = 'workshop' WHERE course_type = 'Workshop' AND registration_form = 'full'",
    "UPDATE courses SET registration_form = 'workshop' WHERE category = 'Workshop' AND registration_form = 'full'",
    "ALTER TABLE students ADD COLUMN class_standard VARCHAR(20) NULL DEFAULT NULL AFTER category",
];

foreach ($statements as $sql) {
    echo substr($sql, 0, 70) . "...\n";
    if ($conn->query($sql)) {
        echo "OK\n";
    } else {
        if (strpos($conn->error, 'Duplicate column') !== false) {
            echo "SKIP (already exists)\n";
        } else {
            echo "ERR: " . $conn->error . "\n";
        }
    }
}

echo "Done.\n";
