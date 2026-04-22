<?php
// Diagnostic script to identify registration form issues
session_start();
require_once __DIR__ . '/../config/config.php';

echo "<h1>Registration Form Diagnostic Report</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; background: #f0fff0; padding: 10px; border-left: 4px solid green; }
    .error { color: red; background: #fff0f0; padding: 10px; border-left: 4px solid red; }
    .warning { color: orange; background: #fff8f0; padding: 10px; border-left: 4px solid orange; }
    .info { color: blue; background: #f0f8ff; padding: 10px; border-left: 4px solid blue; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
</style>";

// 1. Check if registration files exist
echo "<h2>1. File Existence Check</h2>";
$files_to_check = [
    'student/register.php' => 'Main registration form',
    'student/submit_registration.php' => 'Form submission handler',
    'config/config.php' => 'Configuration file',
    'includes/student_id_helper.php' => 'Student ID helper',
    'includes/email_helper.php' => 'Email helper'
];

foreach ($files_to_check as $file => $description) {
    $full_path = __DIR__ . '/../' . $file;
    if (file_exists($full_path)) {
        echo "<div class='success'>✓ $description ($file) - EXISTS</div>";
    } else {
        echo "<div class='error'>✗ $description ($file) - MISSING</div>";
    }
}

// 2. Check database connection
echo "<h2>2. Database Connection</h2>";
if (isset($conn) && $conn->ping()) {
    echo "<div class='success'>✓ Database connection - OK</div>";
} else {
    echo "<div class='error'>✗ Database connection - FAILED</div>";
}

// 3. Check courses table
echo "<h2>3. Courses Table Check</h2>";
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM courses WHERE link_published = 1");
    if ($result) {
        $row = $result->fetch_assoc();
        $published_courses = $row['count'];
        if ($published_courses > 0) {
            echo "<div class='success'>✓ Published courses available: $published_courses</div>";
            
            // Get a sample course
            $sample = $conn->query("SELECT id, course_name, course_code FROM courses WHERE link_published = 1 LIMIT 1");
            if ($sample && $sample->num_rows > 0) {
                $course = $sample->fetch_assoc();
                echo "<div class='info'>Sample course: ID={$course['id']}, Name={$course['course_name']}, Code={$course['course_code']}</div>";
                
                // Generate test URLs
                echo "<h3>Test URLs:</h3>";
                echo "<div class='info'>";
                echo "With Course ID: <a href='" . APP_URL . "/student/register.php?course_id={$course['id']}' target='_blank'>" . APP_URL . "/student/register.php?course_id={$course['id']}</a><br>";
                echo "With Course Code: <a href='" . APP_URL . "/student/register.php?course={$course['course_code']}' target='_blank'>" . APP_URL . "/student/register.php?course={$course['course_code']}</a>";
                echo "</div>";
            }
        } else {
            echo "<div class='warning'>⚠ No published courses found. Please publish at least one course.</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error checking courses table: " . $e->getMessage() . "</div>";
}

// 4. Check students table structure
echo "<h2>4. Students Table Structure</h2>";
try {
    $result = $conn->query("DESCRIBE students");
    if ($result) {
        echo "<div class='success'>✓ Students table exists</div>";
        echo "<details><summary>Click to see table structure</summary><pre>";
        while ($row = $result->fetch_assoc()) {
            echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']}\n";
        }
        echo "</pre></details>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error checking students table: " . $e->getMessage() . "</div>";
}

// 5. Check upload directories
echo "<h2>5. Upload Directory Check</h2>";
$upload_dirs = [
    'student/uploads/students/',
    'student/uploads/aadhar/',
    'student/uploads/caste_certificates/',
    'student/uploads/marksheets/10th/',
    'student/uploads/marksheets/12th/',
    'student/uploads/marksheets/graduation/',
    'student/uploads/other/'
];

foreach ($upload_dirs as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    if (is_dir($full_path)) {
        $writable = is_writable($full_path) ? 'WRITABLE' : 'NOT WRITABLE';
        $class = is_writable($full_path) ? 'success' : 'error';
        echo "<div class='$class'>✓ $dir - EXISTS and $writable</div>";
    } else {
        echo "<div class='warning'>⚠ $dir - MISSING (will be created automatically)</div>";
    }
}

// 6. Check PHP configuration
echo "<h2>6. PHP Configuration</h2>";
$upload_max = ini_get('upload_max_filesize');
$post_max = ini_get('post_max_size');
$max_execution = ini_get('max_execution_time');

echo "<div class='info'>Upload max filesize: $upload_max</div>";
echo "<div class='info'>Post max size: $post_max</div>";
echo "<div class='info'>Max execution time: $max_execution seconds</div>";

// 7. Session check
echo "<h2>7. Session Check</h2>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<div class='success'>✓ Session is active</div>";
    echo "<div class='info'>Session ID: " . session_id() . "</div>";
} else {
    echo "<div class='error'>✗ Session is not active</div>";
}

// 8. Common issues and solutions
echo "<h2>8. Common Issues & Solutions</h2>";
echo "<div class='info'>
<h3>If Submit Registration button is not responding:</h3>
<ol>
<li><strong>Multi-step form:</strong> The registration form has 3 steps. The Submit button only appears on Step 3.</li>
<li><strong>Navigation:</strong> Use 'Next' button to move through steps 1 → 2 → 3.</li>
<li><strong>Validation:</strong> Fill all required fields in each step before proceeding.</li>
<li><strong>JavaScript errors:</strong> Open browser Developer Tools (F12) and check Console for errors.</li>
<li><strong>File uploads:</strong> Ensure passport photo, signature, Aadhar card, and 10th marksheet are uploaded.</li>
</ol>

<h3>Step-by-step process:</h3>
<ol>
<li><strong>Step 1:</strong> Fill Course & Personal Information → Click 'Next'</li>
<li><strong>Step 2:</strong> Fill Contact & Address Information → Click 'Next'</li>
<li><strong>Step 3:</strong> Fill Academic Details & Upload Documents → Click 'Submit Registration'</li>
</ol>
</div>";

// 9. Quick test form
echo "<h2>9. Quick Test</h2>";
if (isset($course)) {
    echo "<div class='info'>";
    echo "<a href='" . APP_URL . "/student/test_registration_debug.php' target='_blank' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Open Debug Test Page</a>";
    echo "</div>";
}

echo "<hr><p><em>Diagnostic completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>