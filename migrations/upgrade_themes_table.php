<?php
/**
 * Upgrade themes table to the current schema (adds missing columns safely).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/themes_schema.php';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Upgrade Themes Table</title></head><body style="font-family:sans-serif;padding:20px;">';
echo '<h1>Upgrade Themes Table</h1>';

if (ensureThemesSchema($conn)) {
    echo '<p style="color:green;"><strong>Success.</strong> The themes table is ready for Manage Themes.</p>';
    echo '<ul>';
    echo '<li><code>theme_name</code></li>';
    echo '<li><code>primary_color</code>, <code>secondary_color</code>, <code>accent_color</code></li>';
    echo '<li><code>logo_path</code>, <code>favicon_path</code></li>';
    echo '<li><code>is_active</code>, <code>created_at</code>, <code>updated_at</code></li>';
    echo '</ul>';
} else {
    echo '<p style="color:red;"><strong>Failed.</strong> ' . htmlspecialchars($conn->error ?: 'Could not upgrade themes table.') . '</p>';
}

echo '</body></html>';
