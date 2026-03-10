<?php
// Quick fix script to create the admin_course_assignments table
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'master_admin') {
    die("Access denied. Master admin required.");
}

require_once '../config/database.php';

echo "<h2>Course Assignments Table Fix</h2>";

// Step 1: Check if table exists
$check_table = $conn->query("SHOW TABLES LIKE 'admin_course_assignments'");
if ($check_table && $check_table->num_rows > 0) {
    echo "<p>✅ Table 'admin_course_assignments' already exists.</p>";
    
    // Check if it has all required columns
    $columns = [];
    $result = $conn->query("DESCRIBE admin_course_assignments");
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $required_columns = ['id', 'admin_id', 'course_id', 'assigned_at', 'assigned_by', 'is_active', 'assignment_type'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (!empty($missing_columns)) {
        echo "<p>⚠️ Missing columns: " . implode(', ', $missing_columns) . "</p>";
        
        // Add missing columns
        foreach ($missing_columns as $column) {
            switch ($column) {
                case 'is_active':
                    $sql = "ALTER TABLE admin_course_assignments ADD COLUMN is_active TINYINT(1) DEFAULT 1";
                    break;
                case 'assignment_type':
                    $sql = "ALTER TABLE admin_course_assignments ADD COLUMN assignment_type VARCHAR(20) DEFAULT 'Manual'";
                    break;
                case 'assigned_by':
                    $sql = "ALTER TABLE admin_course_assignments ADD COLUMN assigned_by INT";
                    break;
                default:
                    continue 2;
            }
            
            if ($conn->query($sql)) {
                echo "<p>✅ Added column: $column</p>";
            } else {
                echo "<p>❌ Failed to add column $column: " . $conn->error . "</p>";
            }
        }
    } else {
        echo "<p>✅ All required columns exist.</p>";
    }
} else {
    echo "<p>❌ Table 'admin_course_assignments' does not exist. Creating...</p>";
    
    // Create the table
    $create_sql = "
    CREATE TABLE admin_course_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        course_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        assigned_by INT,
        is_active TINYINT(1) DEFAULT 1,
        assignment_type VARCHAR(20) DEFAULT 'Manual',
        INDEX idx_admin_id (admin_id),
        INDEX idx_course_id (course_id),
        INDEX idx_is_active (is_active),
        UNIQUE KEY unique_assignment (admin_id, course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($create_sql)) {
        echo "<p>✅ Table 'admin_course_assignments' created successfully.</p>";
    } else {
        echo "<p>❌ Failed to create table: " . $conn->error . "</p>";
    }
}

// Step 2: Test basic functionality
echo "<h3>Testing Basic Functionality</h3>";

try {
    // Test insert (if no data exists)
    $count_result = $conn->query("SELECT COUNT(*) as count FROM admin_course_assignments");
    $count = $count_result->fetch_assoc()['count'];
    
    echo "<p>Current assignments count: $count</p>";
    
    // Test basic queries
    $test_queries = [
        "SELECT COUNT(*) as count FROM admin WHERE role = 'course_coordinator'" => "Course coordinators",
        "SELECT COUNT(*) as count FROM courses" => "Available courses",
        "SELECT COUNT(*) as count FROM admin_course_assignments WHERE is_active = 1" => "Active assignments"
    ];
    
    foreach ($test_queries as $query => $description) {
        $result = $conn->query($query);
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<p>✅ $description: " . $row['count'] . "</p>";
        } else {
            echo "<p>❌ Failed to query $description: " . $conn->error . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error during testing: " . $e->getMessage() . "</p>";
}

echo "<h3>Next Steps</h3>";
echo "<p><a href='simple_course_assignments.php' class='btn btn-primary'>Test Simple Version</a></p>";
echo "<p><a href='manage_course_assignments.php' class='btn btn-success'>Try Full Version</a></p>";
echo "<p><a href='dashboard.php' class='btn btn-secondary'>Back to Dashboard</a></p>";
?>