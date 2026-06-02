<?php
/**
 * Production Migration: Add registration_token Column to courses Table
 * 
 * INSTRUCTIONS:
 * 1. Upload this file to your production server: /migrations/add_registration_token_production.php
 * 2. Run it ONCE by accessing: https://nielitbhubaneswar.in/migrations/add_registration_token_production.php
 * 3. Delete this file immediately after successful execution for security
 * 
 * This migration:
 * - Adds registration_token column to courses table
 * - Creates index for fast lookups
 * - Generates tokens for existing courses
 */

// Use existing database configuration
require_once __DIR__ . '/../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// HTML output styling
?>
<!DOCTYPE html>
<html>
<head>
    <title>Production Migration - Add Registration Token</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
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
        .step { 
            background: #ecf0f1; 
            padding: 15px; 
            margin: 15px 0; 
            border-left: 4px solid #3498db;
            border-radius: 4px;
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            border-left-color: #28a745;
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            border-left-color: #dc3545;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }
        code { 
            background: #f8f9fa; 
            padding: 2px 6px; 
            border-radius: 3px;
            color: #e83e8c;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Production Migration: Add Registration Token</h1>
    
<?php

// Function to generate random token
function generateToken($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $token;
}

// Connect to database (using $conn from config/database.php)
echo '<div class="step">📡 <strong>Step 1:</strong> Connecting to production database...</div>';

if ($conn->connect_error) {
    echo '<div class="step error">❌ <strong>Connection Failed:</strong> ' . htmlspecialchars($conn->connect_error) . '</div>';
    echo '</div></body></html>';
    die();
}

echo '<div class="step success">✅ <strong>Connected successfully</strong> to database: <code>' . DB_NAME . '</code></div>';

// Check if column already exists
echo '<div class="step">🔍 <strong>Step 2:</strong> Checking if column exists...</div>';

$check_sql = "SHOW COLUMNS FROM courses LIKE 'registration_token'";
$result = $conn->query($check_sql);

if ($result && $result->num_rows > 0) {
    echo '<div class="step warning">⚠️ <strong>Column already exists!</strong> The <code>registration_token</code> column is already in the courses table.</div>';
} else {
    // Add column
    echo '<div class="step">➕ <strong>Step 3:</strong> Adding registration_token column...</div>';
    
    $alter_sql = "ALTER TABLE courses ADD COLUMN registration_token VARCHAR(255) DEFAULT NULL";
    
    if ($conn->query($alter_sql)) {
        echo '<div class="step success">✅ <strong>Column added successfully!</strong> Added <code>registration_token VARCHAR(255)</code></div>';
    } else {
        echo '<div class="step error">❌ <strong>Error adding column:</strong> ' . htmlspecialchars($conn->error) . '</div>';
        echo '</div></body></html>';
        $conn->close();
        die();
    }
    
    // Add index
    echo '<div class="step">🔑 <strong>Step 4:</strong> Creating index for fast lookups...</div>';
    
    $index_sql = "ALTER TABLE courses ADD INDEX idx_registration_token (registration_token)";
    
    if ($conn->query($index_sql)) {
        echo '<div class="step success">✅ <strong>Index created successfully!</strong> Added index on <code>registration_token</code></div>';
    } else {
        echo '<div class="step warning">⚠️ <strong>Index creation note:</strong> ' . htmlspecialchars($conn->error) . '</div>';
    }
}

// Check and add enrollment_closing_date column if missing
echo '<div class="step">🔍 <strong>Step 4.5:</strong> Checking enrollment_closing_date column...</div>';

$check_enrollment_sql = "SHOW COLUMNS FROM courses LIKE 'enrollment_closing_date'";
$enrollment_result = $conn->query($check_enrollment_sql);

if ($enrollment_result && $enrollment_result->num_rows > 0) {
    echo '<div class="step info">ℹ️ <strong>Column exists:</strong> <code>enrollment_closing_date</code> already in table</div>';
} else {
    echo '<div class="step">➕ <strong>Step 4.6:</strong> Adding enrollment_closing_date column...</div>';
    
    $add_enrollment_sql = "ALTER TABLE courses ADD COLUMN enrollment_closing_date DATE DEFAULT NULL";
    
    if ($conn->query($add_enrollment_sql)) {
        echo '<div class="step success">✅ <strong>Column added successfully!</strong> Added <code>enrollment_closing_date DATE</code></div>';
    } else {
        echo '<div class="step error">❌ <strong>Error adding enrollment_closing_date:</strong> ' . htmlspecialchars($conn->error) . '</div>';
    }
}

// Generate tokens for existing courses
echo '<div class="step">🎲 <strong>Step 5:</strong> Generating tokens for existing courses...</div>';

$courses_sql = "SELECT id, course_name, registration_token FROM courses WHERE registration_token IS NULL OR registration_token = ''";
$courses_result = $conn->query($courses_sql);

if ($courses_result && $courses_result->num_rows > 0) {
    $updated_count = 0;
    $course_list = [];
    
    while ($course = $courses_result->fetch_assoc()) {
        $token = generateToken(8);
        $course_id = $course['id'];
        $course_name = $course['course_name'];
        
        $update_sql = "UPDATE courses SET registration_token = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $token, $course_id);
        
        if ($stmt->execute()) {
            $updated_count++;
            $course_list[] = "Course ID {$course_id} ({$course_name}): <code>{$token}</code>";
        }
        $stmt->close();
    }
    
    echo '<div class="step success">✅ <strong>Generated ' . $updated_count . ' tokens</strong> for existing courses:</div>';
    echo '<div class="step info">';
    foreach ($course_list as $course_info) {
        echo $course_info . '<br>';
    }
    echo '</div>';
} else {
    echo '<div class="step info">ℹ️ <strong>No courses need tokens.</strong> All courses already have tokens assigned.</div>';
}

// Verify the changes
echo '<div class="step">✔️ <strong>Step 6:</strong> Verifying changes...</div>';

$verify_sql = "SELECT COUNT(*) as total, 
                      SUM(CASE WHEN registration_token IS NOT NULL THEN 1 ELSE 0 END) as with_token,
                      SUM(CASE WHEN registration_token IS NULL THEN 1 ELSE 0 END) as without_token
               FROM courses";
$verify_result = $conn->query($verify_sql);
$stats = $verify_result->fetch_assoc();

echo '<div class="step success">';
echo '✅ <strong>Verification Complete:</strong><br>';
echo '• Total courses: <code>' . $stats['total'] . '</code><br>';
echo '• Courses with tokens: <code>' . $stats['with_token'] . '</code><br>';
echo '• Courses without tokens: <code>' . $stats['without_token'] . '</code>';
echo '</div>';

// Close connection
$conn->close();

// Final instructions
echo '<div class="step warning">';
echo '<h3>⚠️ IMPORTANT - Security Instructions:</h3>';
echo '<ol>';
echo '<li><strong>Delete this file NOW</strong> from your production server</li>';
echo '<li>Test adding a new course to verify everything works</li>';
echo '<li>Check the registration link generation works correctly</li>';
echo '</ol>';
echo '<p>File to delete: <code>/migrations/add_registration_token_production.php</code></p>';
echo '</div>';

echo '<div class="step success">';
echo '<h3>🎉 Migration Completed Successfully!</h3>';
echo '<p>The production database has been updated. You can now add courses without errors.</p>';
echo '</div>';

?>

</div>
</body>
</html>
