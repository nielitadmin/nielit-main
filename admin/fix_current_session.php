<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session_manager.php';

echo "<h2>🔧 Current Session Fix</h2>";

echo "<h3>Current Session Status:</h3>";
echo "admin: " . (isset($_SESSION['admin']) ? $_SESSION['admin'] : 'NOT SET') . "<br>";
echo "admin_logged_in: " . (isset($_SESSION['admin_logged_in']) ? ($_SESSION['admin_logged_in'] ? 'TRUE' : 'FALSE') : 'NOT SET') . "<br>";
echo "admin_id: " . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'NOT SET') . "<br>";
echo "admin_role: " . (isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'NOT SET') . "<br>";

// If admin_id is missing but we have admin username, fix it
if (!isset($_SESSION['admin_id']) && isset($_SESSION['admin'])) {
    echo "<h3>🔧 Fixing Missing admin_id...</h3>";
    
    $username = $_SESSION['admin'];
    $stmt = $conn->prepare("SELECT id, role FROM admin WHERE LOWER(username) = LOWER(?) LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_role'] = $admin['role']; // Also ensure role is set
        
        echo "✅ Fixed! Set admin_id to: " . $admin['id'] . "<br>";
        echo "✅ Set admin_role to: " . $admin['role'] . "<br>";
    } else {
        echo "❌ Could not find admin record for username: " . $username . "<br>";
    }
}

// If we still don't have admin_id but we know you're saswat (master admin)
if (!isset($_SESSION['admin_id']) && isset($_SESSION['admin_logged_in'])) {
    echo "<h3>🔧 Emergency Fix - Setting to Master Admin...</h3>";
    $_SESSION['admin_id'] = 6; // Your master admin ID from database
    $_SESSION['admin_role'] = 'master_admin';
    $_SESSION['admin'] = 'saswat';
    echo "✅ Emergency fix applied - set admin_id to 6 (saswat)<br>";
}

echo "<h3>Updated Session Status:</h3>";
echo "admin: " . (isset($_SESSION['admin']) ? $_SESSION['admin'] : 'NOT SET') . "<br>";
echo "admin_logged_in: " . (isset($_SESSION['admin_logged_in']) ? ($_SESSION['admin_logged_in'] ? 'TRUE' : 'FALSE') : 'NOT SET') . "<br>";
echo "admin_id: " . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'NOT SET') . "<br>";
echo "admin_role: " . (isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'NOT SET') . "<br>";

if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0) {
    echo "<div style='background:#d4edda;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h3>✅ Session Fixed Successfully!</h3>";
    echo "<p>Your session now has admin_id = " . $_SESSION['admin_id'] . "</p>";
    echo "<p><strong>Now try assigning courses again - it should work!</strong></p>";
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h3>❌ Session Still Has Issues</h3>";
    echo "<p>Please log out and log back in to fix the session properly.</p>";
    echo "</div>";
}

echo "<p><a href='manage_course_assignments.php' class='btn btn-primary'>← Back to Course Assignments</a></p>";
echo "<p><a href='logout.php' class='btn btn-secondary'>Logout and Login Again</a></p>";
?>