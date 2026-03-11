<?php
session_start();
require_once '../config/database.php';

echo "<h2>🔧 Course Assignments Data Fix</h2>";

// Check current table structure
echo "<h3>1. Checking Table Structure</h3>";
$structure = $conn->query("DESCRIBE admin_course_assignments");
echo "<table border='1' style='border-collapse:collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $structure->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check current data
echo "<h3>2. Current Data Status</h3>";
$current = $conn->query("SELECT COUNT(*) as total, 
                         COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                         COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive
                         FROM admin_course_assignments");
$stats = $current->fetch_assoc();
echo "Total assignments: " . $stats['total'] . "<br>";
echo "Active assignments: " . $stats['active'] . "<br>";
echo "Inactive assignments: " . $stats['inactive'] . "<br>";

// Fix 1: Check if ID column is auto-increment
echo "<h3>3. Fixing Primary Key Issue</h3>";
$show_create = $conn->query("SHOW CREATE TABLE admin_course_assignments");
$create_info = $show_create->fetch_assoc();
echo "<details><summary>Current Table Definition</summary>";
echo "<pre>" . htmlspecialchars($create_info['Create Table']) . "</pre>";
echo "</details>";

if (strpos($create_info['Create Table'], 'AUTO_INCREMENT') === false) {
    echo "<div style='background:#fff3cd;padding:10px;border-radius:5px;margin:10px 0;'>";
    echo "⚠️ ID column is not AUTO_INCREMENT - this needs to be fixed<br>";
    echo "Attempting to fix...<br>";
    
    // Fix the ID column
    $fix_id = $conn->query("ALTER TABLE admin_course_assignments MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY");
    if ($fix_id) {
        echo "✅ Fixed ID column to AUTO_INCREMENT<br>";
    } else {
        echo "❌ Failed to fix ID column: " . $conn->error . "<br>";
    }
    echo "</div>";
} else {
    echo "✅ ID column is properly set as AUTO_INCREMENT<br>";
}

// Fix 2: Activate recent assignments
echo "<h3>4. Activating Recent Assignments</h3>";
if ($stats['inactive'] > 0) {
    echo "<div style='background:#d1ecf1;padding:10px;border-radius:5px;margin:10px 0;'>";
    echo "Found " . $stats['inactive'] . " inactive assignments. Activating the most recent ones...<br>";
    
    // Get unique admin-course pairs and activate the most recent assignment for each
    $activate_query = "
        UPDATE admin_course_assignments aca1
        SET is_active = 1
        WHERE aca1.assigned_at = (
            SELECT MAX(aca2.assigned_at)
            FROM admin_course_assignments aca2
            WHERE aca2.admin_id = aca1.admin_id 
            AND aca2.course_id = aca1.course_id
        )
    ";
    
    $activate_result = $conn->query($activate_query);
    if ($activate_result) {
        $affected = $conn->affected_rows;
        echo "✅ Activated $affected assignments<br>";
    } else {
        echo "❌ Failed to activate assignments: " . $conn->error . "<br>";
    }
    echo "</div>";
}

// Fix 3: Remove duplicate inactive assignments
echo "<h3>5. Cleaning Up Duplicates</h3>";
$cleanup_query = "
    DELETE aca1 FROM admin_course_assignments aca1
    INNER JOIN admin_course_assignments aca2
    WHERE aca1.id < aca2.id
    AND aca1.admin_id = aca2.admin_id
    AND aca1.course_id = aca2.course_id
    AND aca1.is_active = 0
";

$cleanup_result = $conn->query($cleanup_query);
if ($cleanup_result) {
    $cleaned = $conn->affected_rows;
    echo "✅ Removed $cleaned duplicate inactive assignments<br>";
} else {
    echo "❌ Failed to clean duplicates: " . $conn->error . "<br>";
}

// Final status
echo "<h3>6. Final Status</h3>";
$final = $conn->query("SELECT COUNT(*) as total, 
                       COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                       COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive
                       FROM admin_course_assignments");
$final_stats = $final->fetch_assoc();
echo "Total assignments: " . $final_stats['total'] . "<br>";
echo "Active assignments: " . $final_stats['active'] . "<br>";
echo "Inactive assignments: " . $final_stats['inactive'] . "<br>";

if ($final_stats['active'] > 0) {
    echo "<div style='background:#d4edda;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h3>✅ Fix Complete!</h3>";
    echo "<p>You now have " . $final_stats['active'] . " active course assignments.</p>";
    echo "<p><strong>The Course Assignments page should now show data!</strong></p>";
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h3>❌ Still No Active Assignments</h3>";
    echo "<p>You may need to create new assignments.</p>";
    echo "</div>";
}

echo "<p><a href='manage_course_assignments.php' class='btn btn-primary'>← Back to Course Assignments</a></p>";
?>