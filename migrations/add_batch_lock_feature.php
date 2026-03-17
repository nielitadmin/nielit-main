<?php
/**
 * Migration: Add batch locking feature
 * Purpose: Add is_locked field to batches table for preventing modifications
 * Date: March 17, 2026
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Adding batch locking feature...</h2>\n";

try {
    // Check if is_locked column already exists
    $check_sql = "SHOW COLUMNS FROM batches LIKE 'is_locked'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        echo "✅ is_locked column already exists in batches table<br>\n";
    } else {
        // Add is_locked column
        $alter_sql = "ALTER TABLE batches ADD COLUMN is_locked TINYINT(1) DEFAULT 0 AFTER status";
        
        if ($conn->query($alter_sql)) {
            echo "✅ Successfully added is_locked column to batches table<br>\n";
            
            // Add locked_at timestamp column
            $locked_at_sql = "ALTER TABLE batches ADD COLUMN locked_at TIMESTAMP NULL AFTER is_locked";
            if ($conn->query($locked_at_sql)) {
                echo "✅ Successfully added locked_at column to batches table<br>\n";
            }
            
            // Add locked_by column to track who locked the batch
            $locked_by_sql = "ALTER TABLE batches ADD COLUMN locked_by INT(11) NULL AFTER locked_at";
            if ($conn->query($locked_by_sql)) {
                echo "✅ Successfully added locked_by column to batches table<br>\n";
                
                // Add foreign key constraint for locked_by
                $fk_sql = "ALTER TABLE batches ADD CONSTRAINT fk_batches_locked_by 
                           FOREIGN KEY (locked_by) REFERENCES admin(id) ON DELETE SET NULL";
                
                if ($conn->query($fk_sql)) {
                    echo "✅ Successfully added foreign key constraint for locked_by<br>\n";
                } else {
                    echo "⚠️ Warning: Could not add foreign key constraint: " . $conn->error . "<br>\n";
                }
            }
            
        } else {
            throw new Exception("Failed to add is_locked column: " . $conn->error);
        }
    }
    
    // Verify the columns were added correctly
    $verify_sql = "DESCRIBE batches";
    $verify_result = $conn->query($verify_sql);
    
    echo "<h3>✅ Batches table structure:</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
    
    while ($row = $verify_result->fetch_assoc()) {
        $highlight = in_array($row['Field'], ['is_locked', 'locked_at', 'locked_by']) ? ' style="background-color: #ffffcc;"' : '';
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
    echo "<p><strong>Batch Locking Feature Added:</strong></p>\n";
    echo "<ul>\n";
    echo "<li><strong>is_locked</strong> - Boolean flag to indicate if batch is locked</li>\n";
    echo "<li><strong>locked_at</strong> - Timestamp when batch was locked</li>\n";
    echo "<li><strong>locked_by</strong> - Admin ID who locked the batch</li>\n";
    echo "</ul>\n";
    
    echo "<p><strong>Next steps:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Update edit_batch.php to include lock/unlock functionality</li>\n";
    echo "<li>Add lock checks to batch_details.php</li>\n";
    echo "<li>Update generate_admission_order.php with lock restrictions</li>\n";
    echo "<li>Add lock status indicators in manage_batches.php</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<h3>❌ Migration failed!</h3>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

$conn->close();
?>