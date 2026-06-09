<?php
/**
 * Migration: Add project_level_label to courses table
 *
 * Used for "Project Name / Course Level" on the public courses page.
 *
 * PRODUCTION — run once:
 *   https://nielitbhubaneswar.in/migrations/install_project_level_label.php
 *
 * Then delete this file from the server for security (optional but recommended).
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration — Project Level Label</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 24px; }
        .box { max-width: 720px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        h1 { margin-top: 0; color: #0d47a1; }
        .ok { background: #d1fae5; color: #065f46; padding: 12px; border-radius: 6px; margin: 12px 0; }
        .skip { background: #e0f2fe; color: #0c4a6e; padding: 12px; border-radius: 6px; margin: 12px 0; }
        .err { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin: 12px 0; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="box">
    <h1>Install <code>project_level_label</code></h1>
    <p>Adds optional public label column on <code>courses</code> for project name / NSQF level display.</p>
<?php

$column = 'project_level_label';
$check = $conn->query("SHOW COLUMNS FROM courses LIKE '{$column}'");

if ($check && $check->num_rows > 0) {
    echo '<div class="skip"><strong>SKIP:</strong> Column <code>' . htmlspecialchars($column) . '</code> already exists. Nothing to do.</div>';
} else {
    $sql = "ALTER TABLE courses ADD COLUMN project_level_label VARCHAR(255) DEFAULT NULL AFTER course_description";
    if ($conn->query($sql)) {
        echo '<div class="ok"><strong>SUCCESS:</strong> Column <code>project_level_label</code> added to <code>courses</code>.</div>';
    } else {
        // Fallback without AFTER (older MySQL / different schema)
        $fallback = "ALTER TABLE courses ADD COLUMN project_level_label VARCHAR(255) DEFAULT NULL";
        if ($conn->query($fallback)) {
            echo '<div class="ok"><strong>SUCCESS:</strong> Column added (fallback, without AFTER clause).</div>';
        } else {
            echo '<div class="err"><strong>ERROR:</strong> ' . htmlspecialchars($conn->error) . '</div>';
        }
    }
}

$verify = $conn->query("SHOW COLUMNS FROM courses LIKE 'project_level_label'");
if ($verify && $verify->num_rows > 0) {
    $col = $verify->fetch_assoc();
    echo '<div class="ok"><strong>Verified:</strong> <code>' . htmlspecialchars($col['Field']) . '</code> — '
        . htmlspecialchars($col['Type']) . ', default ' . htmlspecialchars($col['Default'] ?? 'NULL') . '</div>';
    echo '<p>Admins can set this in <strong>Edit Course → Project Name / Course Level</strong>. It appears on <code>/public/courses.php</code>.</p>';
} else {
    echo '<div class="err"><strong>Verify failed:</strong> column not found after migration.</div>';
}

$conn->close();
?>
    <p><small>Safe to run multiple times — skips if column already exists.</small></p>
</div>
</body>
</html>
