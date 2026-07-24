<?php
/**
 * Ensure public directory columns on faculty for Our Team / Contact pages.
 * Safe to run multiple times.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/staff_profile_helper.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    ensureStaffProfileSchema($conn);
    echo "OK: Staff & Faculty public directory schema is ready.\n";
    echo "- show_on_website\n";
    echo "- show_on_contact\n";
    echo "- display_order\n";
    echo "- public_bio\n";
    echo "- profile_photo (existing)\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
