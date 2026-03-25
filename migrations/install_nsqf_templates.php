<?php
/**
 * NSQF Course Templates Installation Script
 * Creates the two-tier NSQF system: Templates + Course Instances
 */

require_once __DIR__ . '/../config/config.php';

echo "Installing NSQF Course Templates System...\n";

try {
    // Create NSQF course templates table
    $sql = "CREATE TABLE IF NOT EXISTS nsqf_course_templates (
        id INT(11) NOT NULL AUTO_INCREMENT,
        course_name VARCHAR(255) NOT NULL,
        category ENUM('Long Term NSQF', 'Short Term NSQF') NOT NULL,
        eligibility TEXT,
        created_by INT(11) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY unique_course_category (course_name, category),
        KEY idx_category (category),
        KEY idx_created_by (created_by),
        KEY idx_is_active (is_active),
        CONSTRAINT fk_nsqf_template_creator FOREIGN KEY (created_by) REFERENCES admin(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ NSQF course templates table created successfully!\n";
    } else {
        echo "⚠️ Warning: Could not create templates table: " . $conn->error . "\n";
    }
    
    // Add table comment
    $comment_sql = "ALTER TABLE nsqf_course_templates 
                    COMMENT = 'NSQF course templates created by NSQF Course Managers. Course Coordinators select from these templates to create actual courses.'";
    
    if ($conn->query($comment_sql)) {
        echo "✅ Table comment added!\n";
    } else {
        echo "⚠️ Warning: Could not add table comment: " . $conn->error . "\n";
    }
    
    // Add course name index
    $index_sql = "CREATE INDEX IF NOT EXISTS idx_course_name ON nsqf_course_templates(course_name)";
    
    if ($conn->query($index_sql)) {
        echo "✅ Course name index created!\n";
    } else {
        echo "⚠️ Warning: Could not create course name index: " . $conn->error . "\n";
    }
    
    // Insert sample NSQF course templates
    $sample_templates = [
        ['Data Analytics', 'Long Term NSQF', '12th Pass with Mathematics'],
        ['Web Development', 'Long Term NSQF', '12th Pass'],
        ['Digital Marketing', 'Short Term NSQF', '10th Pass'],
        ['Cyber Security', 'Long Term NSQF', 'Graduate in any discipline'],
        ['Mobile App Development', 'Short Term NSQF', '12th Pass with Computer Science']
    ];
    
    // Get first NSQF manager or master admin for sample data
    $admin_query = $conn->query("SELECT id FROM admin WHERE role IN ('nsqf_course_manager', 'master_admin') LIMIT 1");
    if ($admin_query && $admin_query->num_rows > 0) {
        $admin_id = $admin_query->fetch_assoc()['id'];
        
        $insert_sql = "INSERT IGNORE INTO nsqf_course_templates (course_name, category, eligibility, created_by) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        
        foreach ($sample_templates as $template) {
            $stmt->bind_param("sssi", $template[0], $template[1], $template[2], $admin_id);
            $stmt->execute();
        }
        
        echo "✅ Sample NSQF course templates added!\n";
        $stmt->close();
    }
    
    // Verify the installation
    $verify_sql = "SELECT COUNT(*) as count FROM nsqf_course_templates";
    $result = $conn->query($verify_sql);
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "✅ Templates table verified: $count templates available\n";
    }
    
    echo "\n🎉 NSQF Course Templates System installed successfully!\n";
    echo "\n📋 Next steps:\n";
    echo "1. NSQF Course Managers can create course templates\n";
    echo "2. Course Coordinators can select from templates to create courses\n";
    echo "3. Eligibility will auto-populate based on selected template\n";
    
} catch (Exception $e) {
    echo "❌ Error installing NSQF templates system: " . $e->getMessage() . "\n";
}

$conn->close();
?>