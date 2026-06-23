<?php
/**
 * Rename NIELIT Balasore Extension → NIELIT Baleshwar Extension
 * Aligns centre names, cities, and batch locations with Odisha gazette spelling.
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Rename Balasore to Baleshwar</title></head><body style="font-family:sans-serif;padding:20px;">';
echo '<h1>Rename Balasore → Baleshwar</h1>';

$steps = [];

// Centres table
$sqlCentres = "UPDATE centres
    SET name = 'NIELIT Baleshwar Extension',
        city = 'Baleshwar',
        address = REPLACE(REPLACE(address, 'Balasore Extension Centre', 'Baleshwar Extension Centre'), 'Balasore', 'Baleshwar')
    WHERE code = 'BALA' OR name LIKE '%Balasore%' OR city = 'Balasore'";
if ($conn->query($sqlCentres)) {
    $steps[] = 'Centres updated: ' . $conn->affected_rows . ' row(s)';
} else {
    $steps[] = 'Centres update failed: ' . htmlspecialchars($conn->error);
}

// Batch location values
$checkBatches = $conn->query("SHOW COLUMNS FROM batches LIKE 'location'");
if ($checkBatches && $checkBatches->num_rows > 0) {
    $sqlBatches = "UPDATE batches SET location = 'NIELIT Baleshwar' WHERE location = 'NIELIT Balasore'";
    if ($conn->query($sqlBatches)) {
        $steps[] = 'Batch locations updated: ' . $conn->affected_rows . ' row(s)';
    } else {
        $steps[] = 'Batch location update failed: ' . htmlspecialchars($conn->error);
    }
}

// News articles (if table exists)
$checkNews = $conn->query("SHOW TABLES LIKE 'news'");
if ($checkNews && $checkNews->num_rows > 0) {
    $sqlNews = "UPDATE news
        SET title = REPLACE(title, 'Balasore', 'Baleshwar'),
            content = REPLACE(content, 'Balasore', 'Baleshwar')
        WHERE title LIKE '%Balasore%' OR content LIKE '%Balasore%'";
    if ($conn->query($sqlNews)) {
        $steps[] = 'News articles updated: ' . $conn->affected_rows . ' row(s)';
    } else {
        $steps[] = 'News update failed: ' . htmlspecialchars($conn->error);
    }
}

echo '<ul>';
foreach ($steps as $step) {
    echo '<li>' . htmlspecialchars($step) . '</li>';
}
echo '</ul>';
echo '<p><strong>Done.</strong> Refresh the site to see Baleshwar naming on all pages.</p>';
echo '</body></html>';
