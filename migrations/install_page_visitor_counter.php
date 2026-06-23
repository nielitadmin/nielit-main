<?php
/**
 * Install page visitor counter tables.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/visitor_counter.php';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Install Visitor Counter</title></head><body style="font-family:sans-serif;padding:20px;">';
echo '<h1>Page Visitor Counter</h1>';

if (visitorCounterEnsureTables($conn)) {
    echo '<p style="color:green;"><strong>Success.</strong> Visitor counter tables are ready.</p>';
    echo '<ul>';
    echo '<li><code>page_visit_daily</code></li>';
    echo '<li><code>page_visit_uniques</code></li>';
    echo '<li><code>site_visit_daily</code></li>';
    echo '<li><code>site_visit_uniques</code></li>';
    echo '</ul>';
} else {
    echo '<p style="color:red;"><strong>Failed.</strong> Could not create visitor counter tables.</p>';
}

echo '</body></html>';
