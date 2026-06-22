<?php
/**
 * Batch student certificate upload support.
 * Run once: php migrations/install_batch_student_certificates.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../batch_module/includes/batch_certificate_helper.php';

echo "Installing batch student certificate support...\n";

if (ensureBatchCertificateSchema($conn)) {
    echo "OK: batch_students certificate columns and certificates table are ready.\n";
} else {
    echo "WARN: batch_students table not found. Run batch module setup first.\n";
}

echo "Done.\n";
