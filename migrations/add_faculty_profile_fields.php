<?php
/**
 * Migration: extended NIELIT Centre staff profile fields on faculty table.
 *
 * CLI:  php migrations/add_faculty_profile_fields.php
 * Web:  /migrations/add_faculty_profile_fields.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/staff_profile_helper.php';

$isCli = (PHP_SAPI === 'cli');
$lineBreak = $isCli ? PHP_EOL : '<br>';

if (!$isCli) {
    echo '<!DOCTYPE html><html><head><title>Staff Profile Migration</title></head><body><pre style="font-family:monospace;">';
}

try {
    echo "Adding NIELIT Centre staff profile fields to faculty table...{$lineBreak}";
    ensureStaffProfileSchema($conn);
    echo "✓ All profile columns are present.{$lineBreak}";
    echo "{$lineBreak}Done.{$lineBreak}";
} catch (Throwable $e) {
    echo "✗ Error: " . htmlspecialchars($e->getMessage()) . $lineBreak;
    exit(1);
}

if (!$isCli) {
    echo '</pre><p><a href="../admin/manage_faculty.php">← Back to Staff Management</a></p></body></html>';
}
