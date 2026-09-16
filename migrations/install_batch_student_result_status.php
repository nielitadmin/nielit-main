<?php
/**
 * Ensure batch_students.result_status columns exist.
 * Safe to run multiple times. Also auto-created when opening Batch Details.
 */
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/../config/config.php';
}
require_once __DIR__ . '/../batch_module/includes/batch_result_helper.php';

echo "=== batch_students result_status ===\n";
$ok = ensureBatchResultSchema($conn);
echo $ok ? "OK: result_status columns ready.\n" : "WARN: batch_students table not found.\n";
