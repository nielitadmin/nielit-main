<?php
/**
 * Migration: Install Student Fingerprint Kiosk
 * Creates the student_kiosk_allowed_ips table used to allow-list the static
 * IP(s) that may use student/self_fingerprint.php.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/student_kiosk_helper.php';

echo "==============================================\n";
echo "Installing Student Fingerprint Kiosk\n";
echo "==============================================\n\n";

try {
    if (ensureStudentKioskTables($conn)) {
        echo "\xE2\x9C\x93 Table 'student_kiosk_allowed_ips' is ready.\n";
    } else {
        echo "\xE2\x9C\x97 Could not create table: " . $conn->error . "\n";
    }

    echo "\nNext steps:\n";
    echo "1. Log in as Master Admin.\n";
    echo "2. Go to Admin -> System Settings -> Student Fingerprint Kiosk.\n";
    echo "3. Add the institute's public static IP so the kiosk page works from that network only.\n";
    echo "4. Open student/self_fingerprint.php on the kiosk PC (with the reader + its service running).\n\n";
    echo "Installation complete.\n";
} catch (Throwable $e) {
    echo "\xE2\x9C\x97 Error: " . $e->getMessage() . "\n";
}
