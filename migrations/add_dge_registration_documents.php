<?php
/**
 * Migration: DGE project registration document columns on students table.
 *
 * CLI:  php migrations/add_dge_registration_documents.php
 * Web:  /migrations/add_dge_registration_documents.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';

$isCli = (PHP_SAPI === 'cli');
$lineBreak = $isCli ? PHP_EOL : '<br>';

if (!$isCli) {
    echo '<!DOCTYPE html><html><head><title>DGE Registration Documents Migration</title></head><body><pre style="font-family:monospace;">';
}

$columns = [
    'bank_passbook_doc' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Path to bank passbook (Aadhaar bank seeding)' AFTER other_documents_doc",
    'income_certificate_doc' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Path to income certificate' AFTER bank_passbook_doc",
    'aadhaar_bank_seeding_doc' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Path to Aadhaar bank seeding proof' AFTER income_certificate_doc",
];

$uploadDirs = [
    __DIR__ . '/../student/uploads/dge/bank_passbook',
    __DIR__ . '/../student/uploads/dge/income_certificate',
    __DIR__ . '/../student/uploads/dge/aadhaar_bank_seeding',
];

try {
    echo "Adding DGE registration document columns...{$lineBreak}";
    ensureDgeRegistrationDocumentColumns($conn);

    foreach (array_keys($columns) as $column) {
        $exists = studentsTableHasColumn($conn, $column);
        echo ($exists ? '✓' : '✗') . " Column `$column`: " . ($exists ? 'present' : 'MISSING') . $lineBreak;
    }

    echo "{$lineBreak}Creating upload directories...{$lineBreak}";
    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException('Unable to create directory: ' . $dir);
        }
        echo '✓ ' . $dir . $lineBreak;
    }

    echo "{$lineBreak}Done. DGE project documents are ready on the registration form.{$lineBreak}";
} catch (Throwable $e) {
    echo '✗ Error: ' . htmlspecialchars($e->getMessage()) . $lineBreak;
    exit(1);
}

if (!$isCli) {
    echo '</pre><p><a href="../student/register.php">← Back to Registration</a></p></body></html>';
}
