<?php
/**
 * Migration: Training Partner quarterly admissions table.
 * CLI: php migrations/install_training_partner_admissions.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/training_partner_admissions_helper.php';

echo "Installing training partner quarterly admissions table...\n";

$ok = tp_admissions_ensure_table($conn);
if ($ok) {
    echo "OK: training_partner_quarterly_admissions is ready.\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
    if (!defined('MIGRATION_WEB_RUNNER')) {
        exit(1);
    }
    throw new RuntimeException('Could not create training_partner_quarterly_admissions table: ' . $conn->error);
}

if (!defined('MIGRATION_WEB_RUNNER')) {
    exit(0);
}
