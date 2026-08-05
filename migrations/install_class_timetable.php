<?php
/**
 * Migration: Install Class Timetable table
 *
 * Creates class_timetable for weekly per-batch schedules (Mon–Sat).
 * Run from: Admin → DB Migrations → install_class_timetable.php
 * Or visit: /migrations/install_class_timetable.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/class_timetable_helper.php';

$isCli = (PHP_SAPI === 'cli');
$nl = $isCli ? "\n" : "<br>\n";

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Install Class Timetable</title></head>';
    echo '<body style="font-family:sans-serif;padding:20px;line-height:1.5;">';
}

echo ($isCli ? '' : '<h1>') . 'Install Class Timetable' . ($isCli ? $nl : '</h1>' . $nl);
echo str_repeat('=', 50) . $nl . $nl;

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is not available.');
    }

    if (!ensureClassTimetableTable($conn)) {
        throw new RuntimeException('Could not create class_timetable: ' . ($conn->error ?: 'unknown error'));
    }
    echo 'OK table class_timetable ready.' . $nl;

    $countRes = $conn->query('SELECT COUNT(*) AS c FROM class_timetable');
    $count = $countRes ? (int) ($countRes->fetch_assoc()['c'] ?? 0) : 0;
    echo 'OK existing timetable slots: ' . $count . $nl . $nl;

    echo 'Done. Open Admin → Class Timetable to manage weekly schedules.' . $nl;

    if (!$isCli) {
        echo '<p style="margin-top:16px;"><a href="../admin/manage_class_timetable.php">Open Class Timetable</a></p>';
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
