<?php
/**
 * CLI: Normalize legacy sub-category labels in the database.
 * NON-NSQF Course -> Non-NSQF Course
 * GOVT/CORPORATE Training -> Govt/Corporate Training
 *
 * Run once: php migrations/rename_sub_category_labels.php
 * Or use admin/run_sub_category_label_migration.php (master admin, web UI).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/course_sub_category_migration.php';

echo "Renaming sub-category labels...\n";

foreach (run_sub_category_label_migration($conn) as $result) {
    if ($result['success']) {
        echo "OK: {$result['label']} ({$result['affected']} row(s))\n";
    } else {
        echo "SKIP/ERROR: {$result['label']} - {$result['error']}\n";
    }
}

echo "Done.\n";
$conn->close();
