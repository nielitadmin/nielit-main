<?php
/**
 * Migration: Add created_by field to batches table
 * Purpose: Track which admin created each batch for ownership filtering
 * Date: March 17, 2026
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Adding created_by field to batches table...</h2>\n";

try {
    // Check if created_by column already exists
    $check_sql = "SHOW COLUMNS FROM batches LIKE 'created_by'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        echo "✅ created_by column already exists in batches table<br>\n";
    } else {
        // Add created_by column
        $alter_sql = "ALTER TABLE batches ADD COLUMN created_by INT(11) NULL AFTER batch_coordinator";
        
        if ($conn->query($alter_sql)) {
            echo "✅ Successfully added created_by column to batches table<br>\n";
            
            // Add foreign key constraint
            $fk_sql = "ALTER TABLE batches ADD CONSTRAINT fk_batches_created_by 
                       FOREIGN KEY (created_by) REFERENCES admin(id) ON DELETE SET NULL";
            
            if ($conn->query($fk_sql)) {
                echo "✅ Successfully added foreign key constraint for created_by<br>\n";
            } else {
                echo "⚠️ Warning: Could not add foreign key constraint: " . $conn->error . "<br>\n";
            }
            
            // Update existing batches to set created_by to the first master admin
            // This is for backward compatibility
            $update_sql = "UPDATE batches SET created_by = (
                            SELECT id FROM admin WHERE role = 'master_admin' LIMIT 1
                          ) WHERE created_by IS NULL";
            
            if ($conn->query($update_sql)) {
                echo "✅ Updated existing batches with default creator<br>\n";
            } else {
                echo "⚠️ Warning: Could not update existing batches: " . $conn->error . "<br>\n";
            }
            
        } else {
            throw new Exception("Failed to add created_by column: " . $conn->error);
        }
    }
    
    // Verify the column was added correctly
    $verify_sql = "DESCRIBE batches";
    $verify_result = $conn->query($verify_sql);
    
    echo "<h3>✅ Batches table structure:</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
    
    while ($row = $verify_result->fetch_assoc()) {
        $highlight = ($row['Field'] === 'created_by') ? ' style="background-color: #ffffcc;"' : '';
        echo "<tr{$highlight}>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h3>🎉 Migration completed successfully!</h3>\n";
    echo "<p><strong>Next steps:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Update batch creation forms to set created_by field</li>\n";
    echo "<li>Update batch assignment dropdowns to filter by creator</li>\n";
    echo "<li>Test the ownership filtering functionality</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<h3>❌ Migration failed!</h3>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

$conn->close();
?>