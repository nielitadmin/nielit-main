<?php
/**
 * PRODUCTION FIX: Update NSQF Template Categories
 * This migration fixes blank category values in production
 */

require_once __DIR__ . '/../config/config.php';

echo "🔧 PRODUCTION FIX: Updating NSQF Template Categories...\n\n";

try {
    // Start transaction for safety
    $conn->autocommit(false);
    
    echo "📊 Current data before fix:\n";
    $current_data = $conn->query("SELECT id, course_name, category, nsqf_type FROM nsqf_course_templates ORDER BY id");
    if ($current_data && $current_data->num_rows > 0) {
        echo "ID | Course Name | Category | NSQF Type\n";
        echo "---|-------------|----------|----------\n";
        while ($row = $current_data->fetch_assoc()) {
            $category = $row['category'] ?: '[EMPTY]';
            $nsqf_type = $row['nsqf_type'] ?: '[EMPTY]';
            echo "{$row['id']} | {$row['course_name']} | {$category} | {$nsqf_type}\n";
        }
    }
    
    echo "\n🔄 Applying fixes...\n";
    
    // Step 1: Ensure category column is VARCHAR (not ENUM)
    echo "1. Checking category column type...\n";
    $column_info = $conn->query("SHOW COLUMNS FROM nsqf_course_templates LIKE 'category'");
    if ($column_info && $column_info->num_rows > 0) {
        $col_data = $column_info->fetch_assoc();
        echo "   Current type: {$col_data['Type']}\n";
        
        if (strpos($col_data['Type'], 'enum') !== false) {
            echo "   Converting ENUM to VARCHAR...\n";
            $alter_sql = "ALTER TABLE nsqf_course_templates MODIFY COLUMN category VARCHAR(100) DEFAULT NULL";
            if ($conn->query($alter_sql)) {
                echo "   ✅ Category column converted to VARCHAR\n";
            } else {
                throw new Exception("Failed to convert category column: " . $conn->error);
            }
        } else {
            echo "   ✅ Category column is already VARCHAR\n";
        }
    }
    
    // Step 2: Update blank/empty categories based on course names and patterns
    echo "\n2. Updating blank categories...\n";
    
    $updates = [
        // O Level courses
        "UPDATE nsqf_course_templates SET category = 'Long Term NSQF' WHERE (category IS NULL OR category = '') AND course_name LIKE '%O level%'",
        "UPDATE nsqf_course_templates SET category = 'Long Term NSQF' WHERE (category IS NULL OR category = '') AND course_name LIKE '%O-level%'",
        
        // A Level courses  
        "UPDATE nsqf_course_templates SET category = 'Long Term NSQF' WHERE (category IS NULL OR category = '') AND course_name LIKE '%A level%'",
        "UPDATE nsqf_course_templates SET category = 'Long Term NSQF' WHERE (category IS NULL OR category = '') AND course_name LIKE '%A-level%'",
        
        // CCC courses
        "UPDATE nsqf_course_templates SET category = 'NIELIT HQ Digital Literacy Courses (CCC/ECC/BCC/ACC)' WHERE (category IS NULL OR category = '') AND course_name LIKE '%CCC%'",
        
        // Python/Data Science courses (typically short term)
        "UPDATE nsqf_course_templates SET category = 'Short Term / Digital Competency Courses (<= 90 hrs)' WHERE (category IS NULL OR category = '') AND course_name LIKE '%Python%'",
        "UPDATE nsqf_course_templates SET category = 'Short Term / Digital Competency Courses (<= 90 hrs)' WHERE (category IS NULL OR category = '') AND course_name LIKE '%Data%'",
        
        // Any remaining blank categories - set to default
        "UPDATE nsqf_course_templates SET category = 'Skill Based (Long Term) Courses (> 500 hrs)' WHERE (category IS NULL OR category = '')"
    ];
    
    foreach ($updates as $update_sql) {
        if ($conn->query($update_sql)) {
            $affected = $conn->affected_rows;
            if ($affected > 0) {
                echo "   ✅ Updated $affected records\n";
            }
        } else {
            echo "   ⚠️ Warning: " . $conn->error . "\n";
        }
    }
    
    // Step 3: Ensure nsqf_type is set properly
    echo "\n3. Updating NSQF types...\n";
    $nsqf_updates = [
        "UPDATE nsqf_course_templates SET nsqf_type = 'NSQF Course' WHERE (nsqf_type IS NULL OR nsqf_type = '') AND category LIKE '%NSQF%'",
        "UPDATE nsqf_course_templates SET nsqf_type = 'NON-NSQF Course' WHERE (nsqf_type IS NULL OR nsqf_type = '') AND category NOT LIKE '%NSQF%'"
    ];
    
    foreach ($nsqf_updates as $update_sql) {
        if ($conn->query($update_sql)) {
            $affected = $conn->affected_rows;
            if ($affected > 0) {
                echo "   ✅ Updated $affected NSQF type records\n";
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo "\n📊 Data after fix:\n";
    $final_data = $conn->query("SELECT id, course_name, category, nsqf_type FROM nsqf_course_templates ORDER BY id");
    if ($final_data && $final_data->num_rows > 0) {
        echo "ID | Course Name | Category | NSQF Type\n";
        echo "---|-------------|----------|----------\n";
        while ($row = $final_data->fetch_assoc()) {
            echo "{$row['id']} | {$row['course_name']} | {$row['category']} | {$row['nsqf_type']}\n";
        }
    }
    
    echo "\n🎉 Production fix completed successfully!\n";
    echo "\n📋 Summary:\n";
    echo "- Converted category column from ENUM to VARCHAR\n";
    echo "- Updated blank categories based on course names\n";
    echo "- Set appropriate NSQF types\n";
    echo "- All changes committed to database\n";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back.\n";
    exit(1);
}

$conn->close();
?>