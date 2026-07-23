<?php
/**
 * Migration: Install Soft Navy & Gold admin theme
 *
 * Saves the preferred NIELIT admin sidebar/portal color set into Manage Themes:
 *   Primary   #0c2340  (soft navy)
 *   Secondary #123a66  (mid navy)
 *   Accent    #f59e0b  (gold)
 *
 * Run from: Admin → DB Migrations → install_soft_navy_gold_theme.php
 * Or open Manage Themes after running to Activate if needed.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/themes_schema.php';

$isCli = (PHP_SAPI === 'cli');
$nl = $isCli ? "\n" : "<br>\n";

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Install Soft Navy & Gold Theme</title></head>';
    echo '<body style="font-family:sans-serif;padding:20px;line-height:1.5;">';
}

echo ($isCli ? '' : '<h1>') . 'Install Soft Navy & Gold Theme' . ($isCli ? $nl : '</h1>' . $nl);
echo str_repeat('=', 50) . $nl . $nl;

$themeName = 'Soft Navy & Gold';
$primary = '#0c2340';
$secondary = '#123a66';
$accent = '#f59e0b';
$logoPath = 'assets/images/bhubaneswar_logo.png';
$faviconPath = 'assets/images/favicon.ico';

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is not available.');
    }

    if (!ensureThemesSchema($conn)) {
        throw new RuntimeException('Could not ensure themes table schema: ' . ($conn->error ?: 'unknown error'));
    }
    echo 'OK themes table schema ready.' . $nl;

    // Find existing theme by name (idempotent)
    $existingId = null;
    $stmt = $conn->prepare('SELECT id, is_active FROM themes WHERE theme_name = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('s', $themeName);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $existingId = (int) $row['id'];
    }
    $stmt->close();

    if ($existingId) {
        $upd = $conn->prepare(
            'UPDATE themes SET primary_color = ?, secondary_color = ?, accent_color = ?,
             logo_path = ?, favicon_path = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        if (!$upd) {
            throw new RuntimeException('Update prepare failed: ' . $conn->error);
        }
        $upd->bind_param('sssssi', $primary, $secondary, $accent, $logoPath, $faviconPath, $existingId);
        if (!$upd->execute()) {
            throw new RuntimeException('Update failed: ' . $upd->error);
        }
        $upd->close();
        echo "OK updated existing theme #{$existingId}: {$themeName}" . $nl;
        $themeId = $existingId;
    } else {
        $ins = $conn->prepare(
            'INSERT INTO themes (theme_name, primary_color, secondary_color, accent_color, logo_path, favicon_path, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 0)'
        );
        if (!$ins) {
            throw new RuntimeException('Insert prepare failed: ' . $conn->error);
        }
        $ins->bind_param('ssssss', $themeName, $primary, $secondary, $accent, $logoPath, $faviconPath);
        if (!$ins->execute()) {
            throw new RuntimeException('Insert failed: ' . $ins->error);
        }
        $themeId = (int) $conn->insert_id;
        $ins->close();
        echo "OK created theme #{$themeId}: {$themeName}" . $nl;
    }

    // Activate this theme (deactivate others first)
    if (!$conn->query('UPDATE themes SET is_active = 0')) {
        throw new RuntimeException('Could not clear active themes: ' . $conn->error);
    }
    $act = $conn->prepare('UPDATE themes SET is_active = 1 WHERE id = ?');
    if (!$act) {
        throw new RuntimeException('Activate prepare failed: ' . $conn->error);
    }
    $act->bind_param('i', $themeId);
    if (!$act->execute()) {
        throw new RuntimeException('Activate failed: ' . $act->error);
    }
    $act->close();
    echo "OK activated theme #{$themeId}" . $nl . $nl;

    echo 'Colors:' . $nl;
    echo "  Primary   {$primary}" . $nl;
    echo "  Secondary {$secondary}" . $nl;
    echo "  Accent    {$accent}" . $nl . $nl;

    echo 'Done. Open Manage Themes to review / edit:' . $nl;
    echo '  /admin/manage_themes' . $nl;

    if (!$isCli) {
        echo '<p style="margin-top:16px;"><a href="../admin/manage_themes.php">Open Manage Themes</a></p>';
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
