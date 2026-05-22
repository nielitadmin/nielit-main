<?php
/**
 * Fix NSQF Type Column - Emergency Migration
 * Adds the missing nsqf_type column to nsqf_course_templates table
 */

require_once __DIR__ . '/../config/config.php';

echo "🔧 Fixing NSQF Type Column...\n";

try {
    // Check if nsqf_course_templates table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'nsqf_course_templates'");
    
    if ($table_check->num_rows == 0) {
        echo "❌ nsqf_course_templates table does not exist. Please run install_nsqf_templates.php first.\n";
        exit(1);
    }
    
    // Check if nsqf_type column already exists
    $column_check = $conn->query("SHOW COLUMNS FROM nsqf_course_templates LIKE 'nsqf_type'");
    
    if ($column_check->num_rows > 0) {
        echo "✅ nsqf_type column already exists!\n";
        exit(0);
    }
    
    echo "Adding nsqf_type column...\n";
    
    // Add the missing column
    $add_column_sql = "ALTER TABLE nsqf_course_templates 
                      ADD COLUMN nsqf_type VARCHAR(50) NOT NULL DEFAULT 'NSQF Course' 
                      AFTER category";
    
    if ($conn->query($add_column_sql)) {
        echo "✅ Added nsqf_type column successfully!\n";
        
        // Add index for better performance
        $index_sql = "ALTER TABLE nsqf_course_templates ADD INDEX idx_nsqf_type (nsqf_type)";
        if ($conn->query($index_sql)) {
            echo "✅ Added index for nsqf_type column!\n";
        }
        
        // Update existing records based on category
        $update_sql = "UPDATE nsqf_course_templates 
                      SET nsqf_type = CASE 
                          WHEN category LIKE '%NSQF%' THEN 'NSQF Course'
                          ELSE 'NON-NSQF Course'
                      END";
        
        if ($conn->query($update_sql)) {
            echo "✅ Updated existing records with appropriate nsqf_type values!\n";
        }
        
        // Verify the fix
        $verify_sql = "SELECT category, nsqf_type, COUNT(*) as count 
                      FROM nsqf_course_templates 
                      GROUP BY category, nsqf_type";
        $result = $conn->query($verify_sql);
        
        if ($result && $result->num_rows > 0) {
            echo "\n📊 Current template distribution:\n";
            while ($row = $result->fetch_assoc()) {
                echo "   - {$row['category']} | {$row['nsqf_type']}: {$row['count']} templates\n";
            }
        }
        
        echo "\n🎉 NSQF Type column fix completed successfully!\n";
        
    } else {
        throw new Exception("Failed to add nsqf_type column: " . $conn->error);
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
?>