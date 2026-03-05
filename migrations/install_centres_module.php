<?php
/**
 * Install Centres Module Migration
 * This script creates the centres table and sets up the training centres system
 * 
 * HOW TO USE:
 * 1. Upload this file to your server in the migrations/ folder
 * 2. Access it in your browser: https://yourdomain.com/migrations/install_centres_module.php
 * 3. The script will create all necessary tables and data
 * 4. Delete this file after successful installation for security
 */

require_once __DIR__ . '/../config/database.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html><head><title>Install Centres Module</title>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
    h1 { color: #0d47a1; }
    .success { color: green; padding: 10px; background: #e8f5e9; border-left: 4px solid green; margin: 10px 0; }
    .error { color: red; padding: 10px; background: #ffebee; border-left: 4px solid red; margin: 10px 0; }
    .info { color: #0d47a1; padding: 10px; background: #e3f2fd; border-left: 4px solid #0d47a1; margin: 10px 0; }
    .warning { color: #f57c00; padding: 10px; background: #fff3e0; border-left: 4px solid #f57c00; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #0d47a1; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .btn { display: inline-block; padding: 10px 20px; background: #0d47a1; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; }
    .btn:hover { background: #1565c0; }
</style>";
echo "</head><body>";

echo "<h1>🚀 Install Centres Module</h1>";
echo "<hr>";

$errors = [];
$success = [];

// Step 1: Check if centres table already exists
echo "<h2>Step 1: Checking Existing Tables</h2>";
$check_centres = $conn->query("SHOW TABLES LIKE 'centres'");
$centres_exists = $check_centres && $check_centres->num_rows > 0;

if ($centres_exists) {
    echo "<div class='warning'>⚠️ Centres table already exists. Skipping table creation.</div>";
} else {
    echo "<div class='info'>ℹ️ Centres table does not exist. Will create it.</div>";
}

// Step 2: Create centres table
if (!$centres_exists) {
    echo "<h2>Step 2: Creating Centres Table</h2>";
    
    $sql_create_centres = "CREATE TABLE centres (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) NOT NULL UNIQUE,
        address TEXT,
        city VARCHAR(100),
        state VARCHAR(100),
        pincode VARCHAR(10),
        phone VARCHAR(20),
        email VARCHAR(255),
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_code (code),
        KEY idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores NIELIT centre information'";
    
    if ($conn->query($sql_create_centres)) {
        echo "<div class='success'>✓ Centres table created successfully!</div>";
        $success[] = "Centres table created";
    } else {
        echo "<div class='error'>✗ Failed to create centres table: " . $conn->error . "</div>";
        $errors[] = "Failed to create centres table";
    }
} else {
    echo "<h2>Step 2: Centres Table Already Exists</h2>";
    echo "<div class='info'>ℹ️ Skipping table creation.</div>";
}

// Step 3: Insert default centres
echo "<h2>Step 3: Inserting Default Centres</h2>";

// Check if centres already exist
$check_data = $conn->query("SELECT COUNT(*) as count FROM centres");
$row = $check_data->fetch_assoc();
$has_data = $row['count'] > 0;

if ($has_data) {
    echo "<div class='warning'>⚠️ Centres already exist in database. Skipping data insertion.</div>";
    echo "<div class='info'>Current centres count: " . $row['count'] . "</div>";
} else {
    $sql_insert_centres = "INSERT INTO centres (name, code, address, city, state, pincode, phone, email, is_active) VALUES
        ('NIELIT Bhubaneswar', 'BBSR', 'OCAC Tower, Acharya Vihar', 'Bhubaneswar', 'Odisha', '751013', '0674-2960354', 'dir-bbsr@nielit.gov.in', 1),
        ('NIELIT Balasore Extension', 'BALA', 'Balasore', 'Balasore', 'Odisha', '', '', '', 1)";
    
    if ($conn->query($sql_insert_centres)) {
        echo "<div class='success'>✓ Default centres inserted successfully!</div>";
        echo "<div class='info'>Inserted 2 centres: NIELIT Bhubaneswar, NIELIT Balasore Extension</div>";
        $success[] = "Default centres inserted";
    } else {
        echo "<div class='error'>✗ Failed to insert centres: " . $conn->error . "</div>";
        $errors[] = "Failed to insert centres";
    }
}

// Step 4: Check if centre_id column exists in courses table
echo "<h2>Step 4: Checking Courses Table</h2>";

$check_column = $conn->query("SHOW COLUMNS FROM courses LIKE 'centre_id'");
$column_exists = $check_column && $check_column->num_rows > 0;

if ($column_exists) {
    echo "<div class='warning'>⚠️ centre_id column already exists in courses table.</div>";
} else {
    echo "<div class='info'>ℹ️ centre_id column does not exist. Will add it.</div>";
    
    // Add centre_id column
    $sql_add_column = "ALTER TABLE courses 
        ADD COLUMN centre_id INT(11) DEFAULT NULL AFTER id,
        ADD KEY idx_centre (centre_id)";
    
    if ($conn->query($sql_add_column)) {
        echo "<div class='success'>✓ centre_id column added to courses table!</div>";
        $success[] = "centre_id column added";
        
        // Try to add foreign key constraint
        $sql_add_fk = "ALTER TABLE courses 
            ADD CONSTRAINT fk_course_centre 
            FOREIGN KEY (centre_id) REFERENCES centres(id) ON DELETE SET NULL";
        
        if ($conn->query($sql_add_fk)) {
            echo "<div class='success'>✓ Foreign key constraint added!</div>";
            $success[] = "Foreign key constraint added";
        } else {
            echo "<div class='warning'>⚠️ Could not add foreign key constraint: " . $conn->error . "</div>";
            echo "<div class='info'>This is not critical. The system will still work.</div>";
        }
        
        // Update existing courses to default centre (Bhubaneswar = ID 1)
        $sql_update_courses = "UPDATE courses SET centre_id = 1 WHERE centre_id IS NULL";
        if ($conn->query($sql_update_courses)) {
            $affected = $conn->affected_rows;
            echo "<div class='success'>✓ Updated $affected existing courses to NIELIT Bhubaneswar centre!</div>";
            $success[] = "Existing courses linked to default centre";
        }
    } else {
        echo "<div class='error'>✗ Failed to add centre_id column: " . $conn->error . "</div>";
        $errors[] = "Failed to add centre_id column";
    }
}

// Step 5: Display current centres
echo "<h2>Step 5: Current Centres in Database</h2>";

$sql_show_centres = "SELECT id, name, code, city, state, is_active FROM centres ORDER BY name ASC";
$result_centres = $conn->query($sql_show_centres);

if ($result_centres && $result_centres->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Code</th><th>City</th><th>State</th><th>Status</th></tr>";
    
    while ($centre = $result_centres->fetch_assoc()) {
        $status = $centre['is_active'] == 1 ? '<span style="color: green;">✓ Active</span>' : '<span style="color: red;">✗ Inactive</span>';
        echo "<tr>";
        echo "<td>" . $centre['id'] . "</td>";
        echo "<td>" . htmlspecialchars($centre['name']) . "</td>";
        echo "<td>" . htmlspecialchars($centre['code']) . "</td>";
        echo "<td>" . htmlspecialchars($centre['city']) . "</td>";
        echo "<td>" . htmlspecialchars($centre['state']) . "</td>";
        echo "<td>" . $status . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<div class='error'>✗ No centres found in database!</div>";
}

// Step 6: Display courses with centres
echo "<h2>Step 6: Courses Linked to Centres</h2>";

$sql_courses = "SELECT c.id, c.course_name, c.category, cen.name as centre_name, cen.is_active as centre_active
                FROM courses c
                LEFT JOIN centres cen ON c.centre_id = cen.id
                ORDER BY c.category, c.course_name
                LIMIT 10";
$result_courses = $conn->query($sql_courses);

if ($result_courses && $result_courses->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Course ID</th><th>Course Name</th><th>Category</th><th>Centre</th><th>Centre Status</th></tr>";
    
    while ($course = $result_courses->fetch_assoc()) {
        $centre_name = $course['centre_name'] ? htmlspecialchars($course['centre_name']) : '<span style="color: red;">Not Assigned</span>';
        $centre_status = '';
        if ($course['centre_name']) {
            $centre_status = $course['centre_active'] == 1 ? '<span style="color: green;">✓ Active</span>' : '<span style="color: red;">✗ Inactive</span>';
        }
        
        echo "<tr>";
        echo "<td>" . $course['id'] . "</td>";
        echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['category']) . "</td>";
        echo "<td>" . $centre_name . "</td>";
        echo "<td>" . $centre_status . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "<div class='info'>Showing first 10 courses. Total courses in database: " . $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'] . "</div>";
} else {
    echo "<div class='warning'>⚠️ No courses found in database.</div>";
}

// Summary
echo "<hr>";
echo "<h2>📊 Installation Summary</h2>";

if (count($errors) > 0) {
    echo "<div class='error'>";
    echo "<h3>❌ Errors Encountered:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . $error . "</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (count($success) > 0) {
    echo "<div class='success'>";
    echo "<h3>✅ Successful Operations:</h3>";
    echo "<ul>";
    foreach ($success as $item) {
        echo "<li>" . $item . "</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (count($errors) == 0) {
    echo "<div class='success'>";
    echo "<h2>🎉 Installation Complete!</h2>";
    echo "<p>The centres module has been successfully installed.</p>";
    echo "</div>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li><a href='../admin/manage_centres.php' class='btn'>Go to Manage Centres</a></li>";
    echo "<li><a href='../public/courses.php' class='btn'>View Courses Page</a></li>";
    echo "<li><a href='../admin/edit_course.php' class='btn'>Edit a Course</a></li>";
    echo "</ol>";
    
    echo "<div class='warning'>";
    echo "<h3>⚠️ Security Notice</h3>";
    echo "<p><strong>Important:</strong> For security reasons, please delete this file after successful installation:</p>";
    echo "<code>migrations/install_centres_module.php</code>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h2>⚠️ Installation Incomplete</h2>";
    echo "<p>Some errors occurred during installation. Please check the errors above and try again.</p>";
    echo "<p>You may need to:</p>";
    echo "<ul>";
    echo "<li>Check database permissions</li>";
    echo "<li>Verify database connection</li>";
    echo "<li>Run SQL queries manually in phpMyAdmin</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
echo "<p style='text-align: center; color: #666;'>NIELIT Bhubaneswar - Centres Module Installation Script</p>";

$conn->close();

echo "</body></html>";
?>
