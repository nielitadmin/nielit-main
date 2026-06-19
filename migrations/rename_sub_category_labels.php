<?php
/**
 * Normalize legacy sub-category labels in the database.
 * NON-NSQF Course -> Non-NSQF Course
 * GOVT/CORPORATE Training -> Govt/Corporate Training
 *
 * Run once: php migrations/rename_sub_category_labels.php
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/course_category_options.php';

$updates = [
    "UPDATE courses SET category = 'Govt/Corporate Training' WHERE category = 'GOVT/CORPORATE Training'" => 'courses.category (Govt)',
    "UPDATE courses SET course_type = 'Govt/Corporate Training' WHERE course_type = 'GOVT/CORPORATE Training'" => 'courses.course_type (Govt)',
    "UPDATE nsqf_course_templates SET nsqf_type = 'Non-NSQF Course' WHERE nsqf_type = 'NON-NSQF Course'" => 'nsqf_course_templates.nsqf_type',
];

echo "Renaming sub-category labels...\n";

foreach ($updates as $sql => $label) {
    if ($conn->query($sql)) {
        echo "OK: {$label} ({$conn->affected_rows} row(s))\n";
    } else {
        echo "SKIP/ERROR: {$label} - {$conn->error}\n";
    }
}

echo "Done.\n";
$conn->close();
