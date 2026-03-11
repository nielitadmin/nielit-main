<?php
/**
 * Session Compatibility Fix
 * 
 * This script fixes session compatibility issues between old and new login systems.
 * It ensures that all required session variables are properly set.
 */

session_start();
require_once '../config/database.php';
require_once '../includes/session_manager.php';

echo "<h1>Session Compatibility Fix</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
</style>";

echo "<div class='section'>";
echo "<h2>Current Session Status</h2>";

// Check current session variables
$session_vars = [
    'admin' => $_SESSION['admin'] ?? null,
    'admin_logged_in' => $_SESSION['admin_logged_in'] ?? null,
    'admin_id' => $_SESSION['admin_id'] ?? null,
    'admin_role' => $_SESSION['admin_role'] ?? null,
    'admin_email' => $_SESSION['admin_email'] ?? null
];

foreach ($session_vars as $var => $value) {
    $status = $value !== null ? "<span class='success'>SET</span>" : "<span class='error'>NOT SET</span>";
    echo "$var: $status";
    if ($value !== null) {
        echo " (Value: " . htmlspecialchars($value) . ")";
    }
    echo "<br>";
}

echo "</div>";

// Fix session if needed
echo "<div class='section'>";
echo "<h2>Session Fix</h2>";

$needs_fix = false;
$fixes_applied = [];

// Check if admin is logged in but missing admin_logged_in
if (isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true;
    $fixes_applied[] = "Added admin_logged_in = true";
    $needs_fix = true;
}

// Check if we have admin but missing role/id
if (isset($_SESSION['admin']) && (!isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id']))) {
    $username = $_SESSION['admin'];
    
    // Try to initialize session using session manager
    if (function_exists('init_admin_session')) {
        if (init_admin_session($username)) {
            $fixes_applied[] = "Initialized session using session manager";
            $needs_fix = true;
        } else {
            // Manual initialization
            $stmt = $conn->prepare("SELECT id, username, role, email, is_active FROM admin WHERE LOWER(username) = LOWER(?) LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $admin = $result->fetch_assoc();
                
                if ($admin['is_active']) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_logged_in'] = true;
                    
                    $fixes_applied[] = "Manually initialized session variables";
                    $needs_fix = true;
                } else {
                    echo "<span class='error'>Admin account is inactive</span><br>";
                }
            } else {
                echo "<span class='error'>Admin not found in database</span><br>";
            }
        }
    }
}

if ($needs_fix) {
    echo "<span class='success'>Session fixes applied:</span><br>";
    foreach ($fixes_applied as $fix) {
        echo "• $fix<br>";
    }
} else {
    echo "<span class='info'>No session fixes needed</span><br>";
}

echo "</div>";

// Show updated session status
echo "<div class='section'>";
echo "<h2>Updated Session Status</h2>";

$updated_session_vars = [
    'admin' => $_SESSION['admin'] ?? null,
    'admin_logged_in' => $_SESSION['admin_logged_in'] ?? null,
    'admin_id' => $_SESSION['admin_id'] ?? null,
    'admin_role' => $_SESSION['admin_role'] ?? null,
    'admin_email' => $_SESSION['admin_email'] ?? null
];

foreach ($updated_session_vars as $var => $value) {
    $status = $value !== null ? "<span class='success'>SET</span>" : "<span class='error'>NOT SET</span>";
    echo "$var: $status";
    if ($value !== null) {
        echo " (Value: " . htmlspecialchars($value) . ")";
    }
    echo "<br>";
}

echo "</div>";

// Test access to course assignments
echo "<div class='section'>";
echo "<h2>Course Assignments Access Test</h2>";

$can_access = false;
$access_issues = [];

// Check login status
$is_logged_in = isset($_SESSION['admin_logged_in']) || isset($_SESSION['admin']);
if (!$is_logged_in) {
    $access_issues[] = "Not logged in";
} else {
    echo "<span class='success'>✓ Login check passed</span><br>";
}

// Check master admin role
if (!isset($_SESSION['admin_role'])) {
    $access_issues[] = "Admin role not set";
} elseif ($_SESSION['admin_role'] !== 'master_admin') {
    $access_issues[] = "Not a master admin (role: " . $_SESSION['admin_role'] . ")";
} else {
    echo "<span class='success'>✓ Master admin role check passed</span><br>";
}

if (empty($access_issues)) {
    $can_access = true;
    echo "<span class='success'>✓ Can access course assignments page</span><br>";
    echo "<button onclick=\"window.open('manage_course_assignments.php', '_blank')\">Open Course Assignments</button><br>";
} else {
    echo "<span class='error'>✗ Cannot access course assignments:</span><br>";
    foreach ($access_issues as $issue) {
        echo "• $issue<br>";
    }
}

echo "</div>";

echo "<p><strong>Fix completed at:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>