<?php
/**
 * Deactivate removed public nav items (PM SHRI / Management) in navigation_menu.
 * Safe to run multiple times.
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/plain; charset=utf-8');

$tableCheck = $conn->query("SHOW TABLES LIKE 'navigation_menu'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    echo "SKIP: navigation_menu table not found.\n";
    exit;
}

$sql = "UPDATE navigation_menu
        SET is_active = 0
        WHERE is_active = 1
          AND (
            LOWER(label) LIKE '%pm shri%'
            OR LOWER(label) LIKE '%kv jnv%'
            OR LOWER(label) = 'management'
            OR LOWER(label) = 'course registration'
            OR LOWER(label) = 'mock test portal'
            OR LOWER(url) LIKE '%management%'
            OR LOWER(url) LIKE '%membership_form%'
          )";

if (!$conn->query($sql)) {
    http_response_code(500);
    echo "ERROR: " . $conn->error . "\n";
    exit(1);
}

echo "OK: Deactivated {$conn->affected_rows} navigation item(s) for PM SHRI / Management.\n";
