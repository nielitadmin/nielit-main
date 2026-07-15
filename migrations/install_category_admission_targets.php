<?php
/**
 * Migration: Category admission targets for Report Monitor
 * Stores annual admission targets per category, financial year, and centre scope.
 *
 * CLI: php migrations/install_category_admission_targets.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/report_monitor_helper.php';

echo "Installing category admission targets table...\n";

if (report_monitor_ensure_category_targets_table($conn)) {
    echo "OK: report_category_admission_targets is ready.\n";
    exit(0);
}

echo "ERROR: " . $conn->error . "\n";
exit(1);
