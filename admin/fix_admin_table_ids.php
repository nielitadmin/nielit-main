<?php
session_start();
require_once '../config/database.php';

echo "<h2>🔧 Admin Table ID Fix</h2>";

// Check current admin table structure and data
echo "<h3>1. Current Admin Table Status</h3>";
$current_admins = $conn->query("SELECT id, username, role, created_at FROM admin ORDER BY created_at");
echo "<table border='1' style='border-collapse:collapse; margin: 10px 0;'>";
echo "<tr><th>Current ID</th><th>Username</th><th>Role</th><th>Created At</th><th>Status</th></tr>";

$admins_needing_fix = [];
while ($admin = $current_admins->fetch_assoc()) {
    $status = ($admin['id'] > 0) ? "✅ OK" : "❌ NEEDS FIX";
    if ($admin['id'] == 0) {
        $admins_needing_fix[] = $admin;
    }
    echo "<tr>";
    echo "<td>" . $admin['id'] . "</td>";
    echo "<td>" . htmlspecialchars($admin['username']) . "</td>";
    echo "<td>" . htmlspecialchars($admin['role']) . "</td>";
    echo "<td>" . $admin['created_at'] . "</td>";
    echo "<td>" . $status . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><strong>Admins needing ID fix: " . count($admins_needing_fix) . "</strong></p>";

// Check table structure
echo "<h3>2. Checking Table Structure</h3>";
$structure = $conn->query("SHOW CREATE TABLE admin");
$create_info = $structure->fetch_assoc();
echo "<details><summary>Current Table Definition</summary>";
echo "<pre>" . htmlspecialchars($create_info['Create Table']) . "</pre>";
echo "</details>";

// Check if ID column is AUTO_INCREMENT
$is_auto_increment = strpos($create_info['Create Table'], 'AUTO_INCREMENT') !== false;
echo "<p>ID column AUTO_INCREMENT status: " . ($is_auto_increment ? "✅ Enabled" : "❌ Disabled") . "</p>";

// Fix the table structure if needed
if (!$is_auto_increment) {
    echo "<h3>3. Fixing AUTO_INCREMENT</h3>";
    echo "<div style='background:#fff3cd;padding:10px;border-radius:5px;margin:10px 0;'>";
    echo "⚠️ ID column is not AUTO_INCREMENT. Attempting to fix...<br>";
    
    // Get the highest current ID
    $max_id_result = $conn->query("SELECT MAX(id) as max_id FROM admin WHERE id > 0");
    $max_id = $max_id_result->fetch_assoc()['max_id'] ?? 0;
    $next_id = $max_id + 1;
    
    echo "Current highest ID: $max_id<br>";
    echo "Next AUTO_INCREMENT will start from: $next_id<br>";
    
    // Fix the AUTO_INCREMENT
    $fix_auto = $conn->query("ALTER TABLE admin MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY");
    if ($fix_auto) {
        // Set the AUTO_INCREMENT starting value
        $set_auto = $conn->query("ALTER TABLE admin AUTO_INCREMENT = $next_id");
        if ($set_auto) {
            echo "✅ Fixed AUTO_INCREMENT and set starting value to $next_id<br>";
        } else {
            echo "❌ Failed to set AUTO_INCREMENT value: " . $conn->error . "<br>";
        }
    } else {
        echo "❌ Failed to fix AUTO_INCREMENT: " . $conn->error . "<br>";
    }
    echo "</div>";
}

// Fix admins with ID = 0
if (count($admins_needing_fix) > 0) {
    echo "<h3>4. Fixing Admin IDs</h3>";
    echo "<div style='background:#d1ecf1;padding:10px;border-radius:5px;margin:10px 0;'>";
    
    foreach ($admins_needing_fix as $admin) {
        echo "Fixing ID for: " . htmlspecialchars($admin['username']) . "<br>";
        
        // Create a new record with proper ID (AUTO_INCREMENT will assign it)
        $stmt = $conn->prepare("INSERT INTO admin (username, password, phone, email, role, created_at, updated_at, is_active) 
                               SELECT username, password, phone, email, role, created_at, updated_at, is_active 
                               FROM admin WHERE username = ? AND id = 0 LIMIT 1");
        $stmt->bind_param("s", $admin['username']);
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            echo "✅ Created new record with ID: $new_id<br>";
            
            // Delete the old record with ID = 0
            $delete_stmt = $conn->prepare("DELETE FROM admin WHERE username = ? AND id = 0");
            $delete_stmt->bind_param("s", $admin['username']);
            
            if ($delete_stmt->execute()) {
                echo "✅ Removed old record with ID = 0<br>";
            } else {
                echo "❌ Failed to remove old record: " . $conn->error . "<br>";
            }
            $delete_stmt->close();
        } else {
            echo "❌ Failed to create new record: " . $conn->error . "<br>";
        }
        $stmt->close();
        echo "<br>";
    }
    echo "</div>";
}

// Show final status
echo "<h3>5. Final Admin Table Status</h3>";
$final_admins = $conn->query("SELECT id, username, role, created_at FROM admin ORDER BY id");
echo "<table border='1' style='border-collapse:collapse; margin: 10px 0;'>";
echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Created At</th><th>Status</th></tr>";

$fixed_count = 0;
while ($admin = $final_admins->fetch_assoc()) {
    $status = ($admin['id'] > 0) ? "✅ OK" : "❌ STILL BROKEN";
    if ($admin['id'] > 0) $fixed_count++;
    
    echo "<tr>";
    echo "<td><strong>" . $admin['id'] . "</strong></td>";
    echo "<td>" . htmlspecialchars($admin['username']) . "</td>";
    echo "<td>" . htmlspecialchars($admin['role']) . "</td>";
    echo "<td>" . $admin['created_at'] . "</td>";
    echo "<td>" . $status . "</td>";
    echo "</tr>";
}
echo "</table>";

$total_admins = $final_admins->num_rows;
echo "<p><strong>Total admins: $total_admins</strong></p>";
echo "<p><strong>Admins with proper IDs: $fixed_count</strong></p>";

if ($fixed_count == $total_admins && $total_admins > 0) {
    echo "<div style='background:#d4edda;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h3>✅ Admin Table Fixed Successfully!</h3>";
    echo "<p>All admin records now have proper IDs assigned.</p>";
    echo "<p><strong>The Course Assignments should now work properly!</strong></p>";
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h3>❌ Some Issues Remain</h3>";
    echo "<p>Some admin records may still have issues. Please check manually.</p>";
    echo "</div>";
}

echo "<p><a href='manage_course_assignments.php' class='btn btn-primary'>← Back to Course Assignments</a></p>";
echo "<p><a href='manage_admins.php' class='btn btn-secondary'>View All Admins</a></p>";
?>