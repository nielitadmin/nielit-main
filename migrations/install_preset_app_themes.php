<?php
/**
 * Migration: Install 30+ preset application themes for Manage Themes
 *
 * Seeds color presets and pairs them with Sidebar Themes suggestions.
 * Safe to re-run (skips existing theme names).
 *
 * Run from: Admin → DB Migrations → install_preset_app_themes.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/preset_themes_catalog.php';

$isCli = (PHP_SAPI === 'cli');
$nl = $isCli ? "\n" : "<br>\n";

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Install Preset App Themes</title></head>';
    echo '<body style="font-family:sans-serif;padding:20px;line-height:1.5;">';
}

echo ($isCli ? '' : '<h1>') . 'Install Preset App Themes' . ($isCli ? $nl : '</h1>' . $nl);
echo str_repeat('=', 50) . $nl . $nl;

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is not available.');
    }

    $result = seedPresetAppThemes($conn);
    echo 'Catalog size: ' . (int) $result['total'] . $nl;
    echo 'Inserted: ' . (int) $result['inserted'] . $nl;
    echo 'Already present (skipped): ' . (int) $result['skipped'] . $nl . $nl;
    echo 'Done. Open Manage Themes to activate any preset.' . $nl;
    echo 'Sidebar Themes page will suggest matching app themes.' . $nl;

    if (!$isCli) {
        echo '<p style="margin-top:16px;">';
        echo '<a href="../admin/manage_themes.php">Open Manage Themes</a> · ';
        echo '<a href="../admin/manage_sidebar_themes.php">Open Sidebar Themes</a>';
        echo '</p>';
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
