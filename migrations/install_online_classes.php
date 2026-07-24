<?php
/**
 * Migration: Install Online Classes table
 *
 * Creates online_classes for scheduled sessions, meeting links, and Drive recording URLs.
 * Run from: Admin → DB Migrations → install_online_classes.php
 * Or visit: /migrations/install_online_classes.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/online_class_helper.php';

$isCli = (PHP_SAPI === 'cli');
$nl = $isCli ? "\n" : "<br>\n";

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Install Online Classes</title></head>';
    echo '<body style="font-family:sans-serif;padding:20px;line-height:1.5;">';
}

echo ($isCli ? '' : '<h1>') . 'Install Online Classes' . ($isCli ? $nl : '</h1>' . $nl);
echo str_repeat('=', 50) . $nl . $nl;

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is not available.');
    }

    if (!ensureOnlineClassesTable($conn)) {
        throw new RuntimeException('Could not create online_classes: ' . ($conn->error ?: 'unknown error'));
    }
    echo 'OK table online_classes ready.' . $nl;

    $countRes = $conn->query('SELECT COUNT(*) AS c FROM online_classes');
    $count = $countRes ? (int) ($countRes->fetch_assoc()['c'] ?? 0) : 0;
    echo 'OK existing class rows: ' . $count . $nl . $nl;

    echo 'Done. Open Admin → Online Classes to schedule sessions.' . $nl;

    if (!$isCli) {
        echo '<p style="margin-top:16px;"><a href="../admin/manage_online_classes.php">Open Online Classes</a></p>';
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . htmlspecialchars($e->getMessage()) . $nl;
    if (!$isCli) {
        echo '</body></html>';
    }
    exit(1);
}

if (!$isCli) {
    echo '</body></html>';
}
