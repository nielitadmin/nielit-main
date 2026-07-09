<?php
/**
 * Migration: separate 12th certificate column on students table.
 *
 * CLI:  php migrations/add_twelfth_certificate_doc.php
 * Web:  /migrations/add_twelfth_certificate_doc.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';

$isCli = (PHP_SAPI === 'cli');
$lineBreak = $isCli ? PHP_EOL : '<br>';

if (!$isCli) {
    echo '<!DOCTYPE html><html><head><title>12th Certificate Migration</title></head><body><pre style="font-family:monospace;">';
}

try {
    echo "Adding twelfth_certificate_doc column...{$lineBreak}";
    ensureTwelfthCertificateDocColumn($conn);

    $exists = studentsTableHasColumn($conn, 'twelfth_certificate_doc');
    echo ($exists ? '✓' : '✗') . " Column `twelfth_certificate_doc`: " . ($exists ? 'present' : 'MISSING') . $lineBreak;

    $uploadDir = __DIR__ . '/../student/uploads/marksheets/12th/certificate';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Unable to create directory: ' . $uploadDir);
    }
    echo '✓ ' . $uploadDir . $lineBreak;

    echo "{$lineBreak}Done.{$lineBreak}";
} catch (Throwable $e) {
    echo '✗ Error: ' . htmlspecialchars($e->getMessage()) . $lineBreak;
    exit(1);
}

if (!$isCli) {
    echo '</pre></body></html>';
}
