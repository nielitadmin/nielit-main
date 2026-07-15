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

$ok = report_monitor_ensure_category_targets_table($conn);
if ($ok) {
    echo "OK: report_category_admission_targets is ready.\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
    if (!defined('MIGRATION_WEB_RUNNER')) {
        exit(1);
    }
    throw new RuntimeException('Could not create report_category_admission_targets table: ' . $conn->error);
}

if (!defined('MIGRATION_WEB_RUNNER')) {
    exit(0);
}
