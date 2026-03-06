<?php
/**
 * Simple RBAC Migration - Add role column to admin table
 * Two roles: master_admin and course_coordinator
 */

require_once __DIR__ . '/../config/database.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Simple RBAC Migration</title>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
    h1 { color: #0d47a1; }
    .success { color: green; padding: 10px; background: #e8f5e9; border-left: 4px solid green; margin: 10px 0; }
    .error { color: red; padding: 10px; background: #ffebee; border-left: 4px solid red; margin: 10px 0; }
    .info { color: #0d47a1; padding: 10px; background: #e3f2fd; border-left: 4px solid #0d47a1; margin: 10px 0; }
    .btn { display: inline-block; padding: 10px 20px; background: #0d47a1; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; }
</style>";
echo "</head><body>";

echo "<h1>🔐 Simple RBAC System Installation</h1>";
echo "<hr>";

// Step 1: Check if role column already exists
echo "<h2>Step 1: Checking admin table structure</h2>";

$check_column = "SHOW COLUMNS FROM admin LIKE 'role'";
$result = $conn->query($check_column);

if ($result && $result->num_rows > 0) {
    echo "<div class='info'>ℹ️ Role column already exists. Skipping column creation.</div>";
} else {
    // Add role column
    echo "<div class='info'>Adding role column to admin table...</div>";
    
    $add_role = "ALTER TABLE admin 
                 ADD COLUMN role ENUM('master_admin', 'course_coordinator') NOT NULL DEFAULT 'master_admin' AFTER password";
    
    if ($conn->query($add_role)) {
        echo "<div class='success'>✓ Role column added successfully!</div>";
    } else {
        echo "<div class='error'>✗ Error adding role column: " . $conn->error . "</div>";
        echo "</body></html>";
        exit;
    }
}

// Step 2: Check for created_at column
echo "<h2>Step 2: Adding timestamp columns</h2>";

$check_created = "SHOW COLUMNS FROM admin LIKE 'created_at'";
$result_created = $conn->query($check_created);

if ($result_created && $result_created->num_rows > 0) {
    echo "<div class='info'>ℹ️ Timestamp columns already exist.</div>";
} else {
    $add_timestamps = "ALTER TABLE admin 
                       ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    
    if ($conn->query($add_timestamps)) {
        echo "<div class='success'>✓ Timestamp columns added successfully!</div>";
    } else {
        echo "<div class='error'>✗ Error adding timestamp columns: " . $conn->error . "</div>";
    }
}

// Step 3: Set all existing admins to master_admin
echo "<h2>Step 3: Setting existing admins to master_admin role</h2>";

$update_roles = "UPDATE admin SET role = 'master_admin' WHERE role IS NULL OR role = ''";
if ($conn->query($update_roles)) {
    $affected = $conn->affected_rows;
    echo "<div class='success'>✓ Updated $affected admin(s) to master_admin role</div>";
} else {
    echo "<div class='error'>✗ Error updating roles: " . $conn->error . "</div>";
}

// Step 4: Display current admins
echo "<h2>Step 4: Current Admin Users</h2>";

$admins_query = "SELECT id, username, role, created_at FROM admin ORDER BY id";
$admins_result = $conn->query($admins_query);

if ($admins_result && $admins_result->num_rows > 0) {
    echo "<table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr style='background: #0d47a1; color: white;'>";
    echo "<th style='padding: 12px; text-align: left; border: 1px solid #ddd;'>ID</th>";
    echo "<th style='padding: 12px; text-align: left; border: 1px solid #ddd;'>Username</th>";
    echo "<th style='padding: 12px; text-align: left; border: 1px solid #ddd;'>Role</th>";
    echo "<th style='padding: 12px; text-align: left; border: 1px solid #ddd;'>Created</th>";
    echo "</tr>";
    
    while ($admin = $admins_result->fetch_assoc()) {
        $role_badge = $admin['role'] == 'master_admin' 
            ? '<span style="background: #4caf50; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Master Admin</span>'
            : '<span style="background: #2196f3; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Course Coordinator</span>';
        
        echo "<tr style='background: " . ($admin['role'] == 'master_admin' ? '#f1f8e9' : '#e3f2fd') . ";'>";
        echo "<td style='padding: 12px; border: 1px solid #ddd;'>" . $admin['id'] . "</td>";
        echo "<td style='padding: 12px; border: 1px solid #ddd;'><strong>" . htmlspecialchars($admin['username']) . "</strong></td>";
        echo "<td style='padding: 12px; border: 1px solid #ddd;'>" . $role_badge . "</td>";
        echo "<td style='padding: 12px; border: 1px solid #ddd;'>" . ($admin['created_at'] ? date('d M Y, h:i A', strtotime($admin['created_at'])) : 'N/A') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<div class='error'>No admin users found!</div>";
}

echo "<hr>";
echo "<h2>✅ Installation Complete!</h2>";
echo "<div class='success'>";
echo "<p><strong>Simple RBAC system has been installed successfully!</strong></p>";
echo "<p><strong>Two Roles Available:</strong></p>";
echo "<ul>";
echo "<li><strong>Master Admin:</strong> Full access to all features including admin management</li>";
echo "<li><strong>Course Coordinator:</strong> Access to Dashboard, Students, Courses, Batches, Approve Students, Reset Password</li>";
echo "</ul>";
echo "</div>";

echo "<h3>Next Steps:</h3>";
echo "<p><a href='../admin/login.php' class='btn'>Go to Login</a></p>";
echo "<p><a href='../admin/dashboard.php' class='btn'>Go to Dashboard</a></p>";

echo "<hr>";
echo "<p style='text-align: center; color: #666;'>NIELIT Bhubaneswar - Simple RBAC Installation</p>";

$conn->close();

echo "</body></html>";
?>
