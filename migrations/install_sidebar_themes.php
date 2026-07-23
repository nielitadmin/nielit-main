<?php
/**
 * Migration: Install Sidebar Themes module settings table
 *
 * Creates sidebar_theme_settings and seeds Soft Navy as the default active style.
 * Run from: Admin → DB Migrations → install_sidebar_themes.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';

$isCli = (PHP_SAPI === 'cli');
$nl = $isCli ? "\n" : "<br>\n";

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Install Sidebar Themes</title></head>';
    echo '<body style="font-family:sans-serif;padding:20px;line-height:1.5;">';
}

echo ($isCli ? '' : '<h1>') . 'Install Sidebar Themes' . ($isCli ? $nl : '</h1>' . $nl);
echo str_repeat('=', 50) . $nl . $nl;

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is not available.');
    }

    if (!ensureSidebarThemeSettingsTable($conn)) {
        throw new RuntimeException('Could not create sidebar_theme_settings: ' . ($conn->error ?: 'unknown error'));
    }
    echo 'OK table sidebar_theme_settings ready.' . $nl;

    $active = getActiveSidebarTheme($conn);
    echo 'OK active sidebar style: ' . htmlspecialchars($active) . $nl . $nl;

    echo 'Available presets:' . $nl;
    foreach (sidebarThemePresets() as $key => $meta) {
        echo '  - ' . $key . ' (' . $meta['label'] . ')' . $nl;
    }
    echo $nl . 'Done. Open Admin → Sidebar Themes to choose a style.' . $nl;

    if (!$isCli) {
        echo '<p style="margin-top:16px;"><a href="../admin/manage_sidebar_themes.php">Open Sidebar Themes</a></p>';
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
