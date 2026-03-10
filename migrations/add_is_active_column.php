<?php
/**
 * Migration: Add is_active column to admin_course_assignments table
 * Date: 2026-03-10
 * Description: Adds is_active column to allow enabling/disabling course assignments
 * 
 * HOW TO RUN:
 * 1. Access this file in your browser: http://yourdomain.com/migrations/add_is_active_column.php
 * 2. The migration will run automatically and show results
 * 3. Delete or move this file after successful migration for security
 */

// Include database configuration
require_once __DIR__ . '/../config/config.php';

// Set content type to HTML
header('Content-Type: text/html; charset=utf-8');

// Start output
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Add is_active Column Migration</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f7fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #34495e;
            margin-top: 25px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #28a745;
            margin: 15px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
            margin: 15px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #17a2b8;
            margin: 15px 0;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
            margin: 15px 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: #3498db;
            color: white;
            text-align: center;
            line-height: 30px;
            border-radius: 50%;
            margin-right: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Add is_active Column Migration</h1>
        <p><strong>Migration Date:</strong> March 10, 2026</p>
        <p><strong>Target Table:</strong> admin_course_assignments</p>
";

// Function to check if column exists
function columnExists($conn, $table, $column) {
    $query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = $conn->query($query);
    return $result && $result->num_rows > 0;
}

// Function to check if index exists
function indexExists($conn, $table, $index) {
    $query = "SHOW INDEX FROM `$table` WHERE Key_name = '$index'";
    $result = $conn->query($query);
    return $result && $result->num_rows > 0;
}

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'admin_course_assignments'");
if ($table_check->num_rows == 0) {
    echo "<div class='error'>";
    echo "<strong>❌ Error:</strong> Table <code>admin_course_assignments</code> does not exist!<br>";
    echo "Please run the RBAC migration first to create this table.";
    echo "</div>";
    echo "</div></body></html>";
    exit;
}

echo "<div class='success'>";
echo "✅ Table <code>admin_course_assignments</code> exists.";
echo "</div>";

// Migration steps
$steps = [];
$errors = [];

// Step 1: Check if is_active column already exists
echo "<h2>Step 1: Checking if is_active column exists</h2>";
if (columnExists($conn, 'admin_course_assignments', 'is_active')) {
    echo "<div class='warning'>";
    echo "⚠️ Column <code>is_active</code> already exists. Skipping column creation.";
    echo "</div>";
    $steps[] = "Column already exists (skipped)";
} else {
    echo "<div class='info'>";
    echo "ℹ️ Column <code>is_active</code> does not exist. Adding now...";
    echo "</div>";
    
    // Add is_active column
    $sql = "ALTER TABLE admin_course_assignments 
            ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 
            AFTER course_id";
    
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>";
        echo "✅ Successfully added <code>is_active</code> column.";
        echo "</div>";
        $steps[] = "Added is_active column";
    } else {
        echo "<div class='error'>";
        echo "❌ Error adding column: " . $conn->error;
        echo "</div>";
        $errors[] = "Failed to add is_active column: " . $conn->error;
    }
}

// Step 2: Check if index exists
echo "<h2>Step 2: Checking if index exists</h2>";
if (indexExists($conn, 'admin_course_assignments', 'idx_is_active')) {
    echo "<div class='warning'>";
    echo "⚠️ Index <code>idx_is_active</code> already exists. Skipping index creation.";
    echo "</div>";
    $steps[] = "Index already exists (skipped)";
} else {
    echo "<div class='info'>";
    echo "ℹ️ Index <code>idx_is_active</code> does not exist. Creating now...";
    echo "</div>";
    
    // Add index
    $sql = "CREATE INDEX idx_is_active ON admin_course_assignments(is_active)";
    
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>";
        echo "✅ Successfully created index <code>idx_is_active</code>.";
        echo "</div>";
        $steps[] = "Created idx_is_active index";
    } else {
        echo "<div class='error'>";
        echo "❌ Error creating index: " . $conn->error;
        echo "</div>";
        $errors[] = "Failed to create index: " . $conn->error;
    }
}

// Step 3: Update existing records
echo "<h2>Step 3: Updating existing records</h2>";
$sql = "UPDATE admin_course_assignments SET is_active = 1 WHERE is_active IS NULL OR is_active = 0";
$result = $conn->query($sql);

if ($result === TRUE) {
    $affected_rows = $conn->affected_rows;
    echo "<div class='success'>";
    echo "✅ Updated $affected_rows existing record(s) to active status.";
    echo "</div>";
    $steps[] = "Updated $affected_rows existing records";
} else {
    echo "<div class='error'>";
    echo "❌ Error updating records: " . $conn->error;
    echo "</div>";
    $errors[] = "Failed to update records: " . $conn->error;
}

// Step 4: Verify the migration
echo "<h2>Step 4: Verifying migration</h2>";
$verify_query = "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
                 FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'admin_course_assignments' 
                 AND COLUMN_NAME = 'is_active'";

$verify_result = $conn->query($verify_query);

if ($verify_result && $verify_result->num_rows > 0) {
    $column_info = $verify_result->fetch_assoc();
    echo "<div class='success'>";
    echo "✅ Column verification successful:<br>";
    echo "<pre>";
    echo "Column Name:    " . $column_info['COLUMN_NAME'] . "\n";
    echo "Data Type:      " . $column_info['DATA_TYPE'] . "\n";
    echo "Column Type:    " . $column_info['COLUMN_TYPE'] . "\n";
    echo "Nullable:       " . $column_info['IS_NULLABLE'] . "\n";
    echo "Default Value:  " . $column_info['COLUMN_DEFAULT'];
    echo "</pre>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "❌ Column verification failed!";
    echo "</div>";
    $errors[] = "Column verification failed";
}

// Final summary
echo "<h2>📊 Migration Summary</h2>";

if (empty($errors)) {
    echo "<div class='success'>";
    echo "<h3>✅ Migration Completed Successfully!</h3>";
    echo "<p><strong>Steps completed:</strong></p>";
    echo "<ul>";
    foreach ($steps as $step) {
        echo "<li>$step</li>";
    }
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<h3>🎯 Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Test the course coordinator filtering feature in <code>admin/students.php</code></li>";
    echo "<li>Assign courses to course coordinators using the admin panel</li>";
    echo "<li>Login as a course coordinator and verify they only see students from assigned courses</li>";
    echo "<li><strong>Delete this migration file for security:</strong> <code>migrations/add_is_active_column.php</code></li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>❌ Migration Completed with Errors</h3>";
    echo "<p><strong>Errors encountered:</strong></p>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
    echo "</div>";
}

// Show current table structure
echo "<h2>📋 Current Table Structure</h2>";
$structure_query = "SHOW COLUMNS FROM admin_course_assignments";
$structure_result = $conn->query($structure_query);

if ($structure_result) {
    echo "<div class='step'>";
    echo "<pre>";
    echo str_pad("Field", 25) . str_pad("Type", 20) . str_pad("Null", 8) . str_pad("Key", 8) . "Default\n";
    echo str_repeat("-", 80) . "\n";
    while ($row = $structure_result->fetch_assoc()) {
        echo str_pad($row['Field'], 25) . 
             str_pad($row['Type'], 20) . 
             str_pad($row['Null'], 8) . 
             str_pad($row['Key'], 8) . 
             ($row['Default'] ?? 'NULL') . "\n";
    }
    echo "</pre>";
    echo "</div>";
}

echo "
    </div>
</body>
</html>";

$conn->close();
?>
