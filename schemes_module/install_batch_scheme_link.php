<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>Installing Batch-Scheme Link System</h2>";

// Add scheme_id column to batches table
$sql1 = "ALTER TABLE batches ADD COLUMN IF NOT EXISTS scheme_id INT NULL AFTER course_id";
if ($conn->query($sql1)) {
    echo "<p style='color: green;'>✓ Added scheme_id column to batches table</p>";
} else {
    echo "<p style='color: red;'>✗ Error adding scheme_id column: " . $conn->error . "</p>";
}

// Add foreign key
$sql2 = "ALTER TABLE batches ADD CONSTRAINT fk_batch_scheme FOREIGN KEY (scheme_id) REFERENCES schemes(id) ON DELETE SET NULL";
if ($conn->query($sql2)) {
    echo "<p style='color: green;'>✓ Added foreign key constraint</p>";
} else {
    // Ignore if already exists
    if (strpos($conn->error, 'Duplicate') === false) {
        echo "<p style='color: orange;'>⚠ Foreign key may already exist: " . $conn->error . "</p>";
    } else {
        echo "<p style='color: green;'>✓ Foreign key already exists</p>";
    }
}

echo "<h3>Installation Complete!</h3>";
echo "<p><a href='../batch_module/admin/manage_batches.php'>Go to Manage Batches</a></p>";

$conn->close();
?>
