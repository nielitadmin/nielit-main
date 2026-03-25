<?php
/**
 * Quick fix to ensure NSQF templates table exists
 * Run this if you get "Call to a member function bind_param() on bool" error
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>🔧 NSQF Templates Table Fix</h2>";

// Check if table exists
$check_query = "SHOW TABLES LIKE 'nsqf_course_templates'";
$result = $conn->query($check_query);

if ($result->num_rows == 0) {
    echo "<p>❌ nsqf_course_templates table not found. Creating...</p>";
    
    // Run the migration
    include_once __DIR__ . '/../migrations/install_nsqf_templates.php';
    
    echo "<p>✅ Migration completed!</p>";
} else {
    echo "<p>✅ nsqf_course_templates table already exists.</p>";
}

// Verify table structure
$describe_query = "DESCRIBE nsqf_course_templates";
$result = $conn->query($describe_query);

if ($result) {
    echo "<h3>📋 Table Structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Could not describe table: " . $conn->error . "</p>";
}

// Check if there are any templates
$count_query = "SELECT COUNT(*) as count FROM nsqf_course_templates WHERE is_active = 1";
$result = $conn->query($count_query);

if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo "<h3>📊 Template Count: $count active templates</h3>";
    
    if ($count == 0) {
        echo "<p>⚠️ No templates found. You may need to create some templates first.</p>";
        echo "<p><a href='manage_nsqf_templates.php'>Go to Template Management</a></p>";
    } else {
        // Show sample templates
        $sample_query = "SELECT course_name, category FROM nsqf_course_templates WHERE is_active = 1 LIMIT 3";
        $sample_result = $conn->query($sample_query);
        
        echo "<h4>Sample Templates:</h4>";
        echo "<ul>";
        while ($template = $sample_result->fetch_assoc()) {
            echo "<li>{$template['course_name']} ({$template['category']})</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p>❌ Could not count templates: " . $conn->error . "</p>";
}

echo "<h3>🎯 Next Steps:</h3>";
echo "<ul>";
echo "<li><a href='manage_nsqf_templates.php'>Manage NSQF Templates</a></li>";
echo "<li><a href='dashboard.php'>Back to Dashboard</a></li>";
echo "<li><a href='edit_course.php?id=1'>Test Edit Course</a> (if you have courses)</li>";
echo "</ul>";

$conn->close();
?>