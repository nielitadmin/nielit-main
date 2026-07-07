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

$requiredColumns = [
    'profile_token',
    'profile_token_expires_at',
    'profile_updated_at',
    'nielit_centre',
];

try {
    echo "Adding NIELIT Centre staff profile fields to faculty table...{$lineBreak}";
    ensureStaffProfileSchema($conn);

    foreach ($requiredColumns as $column) {
        $exists = facultyTableHasColumn($conn, $column);
        echo ($exists ? '✓' : '✗') . " Column `$column`: " . ($exists ? 'present' : 'MISSING') . $lineBreak;
    }

    $idx = $conn->query("SHOW INDEX FROM faculty WHERE Key_name = 'uniq_profile_token'");
    echo ($idx && $idx->num_rows > 0 ? '✓' : '✗') . " Unique index uniq_profile_token" . $lineBreak;

    echo "{$lineBreak}Done. Refresh the staff profile page and click Generate link.{$lineBreak}";
} catch (Throwable $e) {
    echo "✗ Error: " . htmlspecialchars($e->getMessage()) . $lineBreak;
    exit(1);
}

if (!$isCli) {
    echo '</pre><p><a href="../admin/manage_faculty.php">← Back to Staff Management</a></p></body></html>';
}
