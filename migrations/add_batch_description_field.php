<?php
/**
 * Migration: Add optional batch_description to batches table
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../batch_module/includes/batch_functions.php';

echo "<h2>Adding batch description field...</h2>\n";

try {
    ensureBatchDescriptionColumn($conn);
    echo "✅ batch_description column is ready on batches table<br>\n";

    $verify = $conn->query("SHOW COLUMNS FROM batches LIKE 'batch_description'");
    if ($verify && $verify->num_rows > 0) {
        $row = $verify->fetch_assoc();
        echo "Column type: " . htmlspecialchars($row['Type'] ?? '') . "<br>\n";
    }

    echo "<h3>🎉 Migration completed successfully!</h3>\n";
} catch (Exception $e) {
    echo "<h3>❌ Migration failed!</h3>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

$conn->close();
