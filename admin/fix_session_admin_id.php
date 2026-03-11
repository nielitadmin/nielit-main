<?php
session_start();

echo "<h2>🔧 Session Admin ID Fix</h2>";

echo "<h3>Before Fix:</h3>";
echo "admin_id: " . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'NOT SET') . "<br>";

// Set the admin_id based on your master admin (saswat - ID 6)
if (!isset($_SESSION['admin_id']) && isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_id'] = 6; // Your master admin ID from the database
    echo "<h3>✅ Fix Applied:</h3>";
    echo "Set admin_id to 6 (saswat - master admin)<br>";
} else {
    echo "<h3>ℹ️ No Fix Needed:</h3>";
    echo "admin_id is already set<br>";
}

echo "<h3>After Fix:</h3>";
echo "admin_id: " . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'NOT SET') . "<br>";

echo "<p><strong>Now try assigning courses again!</strong></p>";
echo "<p><a href='manage_course_assignments.php'>← Back to Course Assignments</a></p>";
?>