<?php
/**
 * Migration: Install Public Themes module settings table
 *
 * Creates public_theme_settings and seeds NIELIT Navy & Gold as default.
 * Run from: Admin → DB Migrations → install_public_themes.php
 * Or open Admin → Public Themes (auto-creates table).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/public_theme_helper.php';

$isCli = (PHP_SAPI === 'cli');
$nl = $isCli ? "\n" : "<br>\n";

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Install Public Themes</title></head>';
    echo '<body style="font-family:sans-serif;padding:20px;line-height:1.5;">';
}

echo ($isCli ? '' : '<h1>') . 'Install Public Themes' . ($isCli ? $nl : '</h1>' . $nl);
echo str_repeat('=', 50) . $nl . $nl;

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is not available.');
    }

    if (!ensurePublicThemeSettingsTable($conn)) {
        throw new RuntimeException('Could not create public_theme_settings: ' . ($conn->error ?: 'unknown error'));
    }
    echo 'OK table public_theme_settings ready.' . $nl;

    $active = getActivePublicThemeKey($conn);
    $def = getActivePublicThemeDefinition($conn);
    echo 'OK active public theme: ' . htmlspecialchars($active) . ' (' . htmlspecialchars($def['label'] ?? '') . ')' . $nl . $nl;

    echo 'Available presets (' . count(publicThemeStyleDefinitions()) . '):' . $nl;
    foreach (publicThemePresets() as $key => $meta) {
        echo '  - ' . $key . ' (' . $meta['label'] . ')' . $nl;
    }
    echo $nl . 'Done. Open Admin → Public Themes to choose a style.' . $nl;

    if (!$isCli) {
        echo '<p style="margin-top:16px;"><a href="../admin/manage_public_themes.php">Open Public Themes</a></p>';
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
