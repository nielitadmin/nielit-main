<?php
// Debug script to identify the issue with manage_course_assignments.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug: Course Assignments Page</h2>";

// Test 1: Check if session works
session_start();
echo "<h3>1. Session Test</h3>";
echo "Session started: " . (session_status() === PHP_SESSION_ACTIVE ? "✅ YES" : "❌ NO") . "<br>";
echo "Admin logged in: " . (isset($_SESSION['admin_logged_in']) ? "✅ YES" : "❌ NO") . "<br>";
echo "Admin role: " . ($_SESSION['admin_role'] ?? 'NOT SET') . "<br>";

// Test 2: Check database connection
echo "<h3>2. Database Connection Test</h3>";
try {
    require_once '../config/database.php';
    echo "Database connection: ✅ SUCCESS<br>";
} catch (Exception $e) {
    echo "Database connection: ❌ FAILED - " . $e->getMessage() . "<br>";
    exit();
}

// Test 3: Check if required tables exist
echo "<h3>3. Database Tables Test</h3>";
$required_tables = [
    'admin',
    'courses', 
    'admin_course_assignments',
    'centres'
];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "Table '$table': ✅ EXISTS<br>";
    } else {
        echo "Table '$table': ❌ MISSING<br>";
    }
}

// Test 4: Check admin_course_assignments table structure
echo "<h3>4. admin_course_assignments Table Structure</h3>";
try {
    $result = $conn->query("DESCRIBE admin_course_assignments");
    if ($result) {
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error checking table structure: " . $e->getMessage() . "<br>";
}

// Test 5: Check if theme_loader exists
echo "<h3>5. Theme Loader Test</h3>";
if (file_exists('../includes/theme_loader.php')) {
    echo "theme_loader.php: ✅ EXISTS<br>";
    try {
        require_once '../includes/theme_loader.php';
        echo "theme_loader.php: ✅ LOADED<br>";
    } catch (Exception $e) {
        echo "theme_loader.php: ❌ ERROR - " . $e->getMessage() . "<br>";
    }
} else {
    echo "theme_loader.php: ❌ MISSING<br>";
}

// Test 6: Check if we can create the admin_course_assignments table
echo "<h3>6. Create Missing Table (if needed)</h3>";
$create_table_sql = "
CREATE TABLE IF NOT EXISTS admin_course_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    course_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    is_active TINYINT(1) DEFAULT 1,
    assignment_type VARCHAR(20) DEFAULT 'Manual',
    FOREIGN KEY (admin_id) REFERENCES admin(id),
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (assigned_by) REFERENCES admin(id),
    UNIQUE KEY unique_assignment (admin_id, course_id)
)";

try {
    if ($conn->query($create_table_sql)) {
        echo "admin_course_assignments table: ✅ CREATED/VERIFIED<br>";
    } else {
        echo "admin_course_assignments table: ❌ ERROR - " . $conn->error . "<br>";
    }
} catch (Exception $e) {
    echo "admin_course_assignments table: ❌ EXCEPTION - " . $e->getMessage() . "<br>";
}

// Test 7: Simple query test
echo "<h3>7. Simple Query Test</h3>";
try {
    $test_query = "SELECT COUNT(*) as count FROM admin WHERE role = 'master_admin'";
    $result = $conn->query($test_query);
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Master admins count: " . $row['count'] . "<br>";
    }
} catch (Exception $e) {
    echo "Query test failed: " . $e->getMessage() . "<br>";
}

echo "<h3>8. Next Steps</h3>";
echo "If all tests pass, try accessing the main page again.<br>";
echo "<a href='manage_course_assignments.php'>Try Course Assignments Page</a><br>";
echo "<a href='dashboard.php'>Back to Dashboard</a><br>";
?>