<?php
/**
 * Update NSQF Templates Schema
 * Updates the nsqf_course_templates table to match the Category/Sub-Category pattern from edit_course.php
 */

require_once __DIR__ . '/../config/config.php';

echo "Updating NSQF Course Templates Schema...\n";

try {
    // First, check if the table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'nsqf_course_templates'");
    if ($check_table->num_rows == 0) {
        echo "⚠️ Table nsqf_course_templates does not exist. Running initial migration...\n";
        include_once __DIR__ . '/install_nsqf_templates.php';
    }
    
    // Check if nsqf_type column already exists
    $check_column = $conn->query("SHOW COLUMNS FROM nsqf_course_templates LIKE 'nsqf_type'");
    
    if ($check_column->num_rows == 0) {
        // Add nsqf_type column
        $add_column_sql = "ALTER TABLE nsqf_course_templates 
                          ADD COLUMN nsqf_type VARCHAR(50) NOT NULL DEFAULT 'NSQF Course' 
                          AFTER category";
        
        if ($conn->query($add_column_sql)) {
            echo "✅ Added nsqf_type column successfully!\n";
        } else {
            echo "❌ Error adding nsqf_type column: " . $conn->error . "\n";
        }
    } else {
        echo "✅ nsqf_type column already exists!\n";
    }
    
    // Update the category column to remove ENUM constraint and allow the new categories
    $modify_category_sql = "ALTER TABLE nsqf_course_templates 
                           MODIFY COLUMN category VARCHAR(255) NOT NULL";
    
    if ($conn->query($modify_category_sql)) {
        echo "✅ Updated category column to VARCHAR!\n";
    } else {
        echo "❌ Error updating category column: " . $conn->error . "\n";
    }
    
    // Update existing data to match new structure
    $update_data_sql = "UPDATE nsqf_course_templates SET 
                        category = CASE 
                            WHEN category = 'Long Term NSQF' THEN 'Skill Based (Long Term) >500 hrs'
                            WHEN category = 'Short Term NSQF' THEN 'Short Term / Digital Competency <=90 hrs'
                            ELSE category
                        END,
                        nsqf_type = 'NSQF Course'
                        WHERE category IN ('Long Term NSQF', 'Short Term NSQF')";
    
    if ($conn->query($update_data_sql)) {
        echo "✅ Updated existing data to new structure!\n";
    } else {
        echo "❌ Error updating existing data: " . $conn->error . "\n";
    }
    
    // Verify the update
    $verify_sql = "SELECT category, nsqf_type, COUNT(*) as count 
                   FROM nsqf_course_templates 
                   GROUP BY category, nsqf_type";
    $result = $conn->query($verify_sql);
    
    if ($result) {
        echo "\n📊 Current template distribution:\n";
        while ($row = $result->fetch_assoc()) {
            echo "   - {$row['category']} | {$row['nsqf_type']}: {$row['count']} templates\n";
        }
    }
    
    echo "\n🎉 NSQF Templates schema updated successfully!\n";
    echo "\n📋 Changes made:\n";
    echo "1. Added nsqf_type column (NSQF Course / NON-NSQF Course)\n";
    echo "2. Updated category column to match edit_course.php options\n";
    echo "3. Migrated existing data to new structure\n";
    
} catch (Exception $e) {
    echo "❌ Error updating NSQF templates schema: " . $e->getMessage() . "\n";
}

$conn->close();
?>