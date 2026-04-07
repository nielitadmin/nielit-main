<?php
/**
 * Debug Form Submission Issues
 * NIELIT Bhubaneswar - Form Submission Debug
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';

echo "<h2>🔍 Form Submission Debug</h2>";

// Check if there are any session errors
echo "<h3>Step 1: Session Error Check</h3>";
if (isset($_SESSION['error'])) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ Session Error Found:</strong><br>";
    echo htmlspecialchars($_SESSION['error']);
    echo "</div>";
    
    // Clear the error after displaying
    unset($_SESSION['error']);
} else {
    echo "<p>✅ No session errors found</p>";
}

// Check if there are any success messages
if (isset($_SESSION['success'])) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>✅ Session Success Found:</strong><br>";
    echo $_SESSION['success'];
    echo "</div>";
    
    // Clear the success after displaying
    unset($_SESSION['success']);
} else {
    echo "<p>ℹ️ No success messages in session</p>";
}

// Check recent error logs (if accessible)
echo "<h3>Step 2: Server Error Log Check</h3>";
$error_log_paths = [
    '/home/u664913565/domains/nielitbhubaneswar.in/public_html/error_log',
    '/home/u664913565/domains/nielitbhubaneswar.in/logs/error_log',
    __DIR__ . '/../error_log',
    __DIR__ . '/error_log'
];

$found_error_log = false;
foreach ($error_log_paths as $log_path) {
    if (file_exists($log_path) && is_readable($log_path)) {
        echo "<p>✅ Found error log: $log_path</p>";
        
        // Read last 20 lines
        $lines = file($log_path);
        if ($lines) {
            $recent_lines = array_slice($lines, -20);
            echo "<div style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto;'>";
            echo "<strong>Recent Error Log Entries:</strong><br>";
            foreach ($recent_lines as $line) {
                if (stripos($line, 'registration') !== false || stripos($line, 'submit') !== false || stripos($line, 'student') !== false) {
                    echo "<span style='color: red;'>" . htmlspecialchars($line) . "</span>";
                } else {
                    echo htmlspecialchars($line);
                }
            }
            echo "</div>";
        }
        $found_error_log = true;
        break;
    }
}

if (!$found_error_log) {
    echo "<p>⚠️ No accessible error log found</p>";
}

// Test database connection and required tables
echo "<h3>Step 3: Database Connection Test</h3>";
try {
    $test_query = $conn->query("SELECT COUNT(*) as count FROM students");
    if ($test_query) {
        $result = $test_query->fetch_assoc();
        echo "<p>✅ Database connection working - Students table has " . $result['count'] . " records</p>";
    } else {
        echo "<p>❌ Database query failed: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test file upload directories
echo "<h3>Step 4: File Upload Directory Test</h3>";
$upload_dirs = [
    __DIR__ . '/uploads/students/',
    __DIR__ . '/uploads/aadhar/',
    __DIR__ . '/uploads/caste_certificates/',
    __DIR__ . '/uploads/marksheets/10th/',
    __DIR__ . '/uploads/marksheets/12th/',
    __DIR__ . '/uploads/marksheets/graduation/',
    __DIR__ . '/uploads/other/'
];

foreach ($upload_dirs as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "<p>✅ Directory writable: " . basename(dirname($dir)) . "/" . basename($dir) . "</p>";
        } else {
            echo "<p>❌ Directory not writable: " . basename(dirname($dir)) . "/" . basename($dir) . "</p>";
        }
    } else {
        echo "<p>⚠️ Directory missing: " . basename(dirname($dir)) . "/" . basename($dir) . "</p>";
        // Try to create it
        if (mkdir($dir, 0755, true)) {
            echo "<p>✅ Created directory: " . basename(dirname($dir)) . "/" . basename($dir) . "</p>";
        } else {
            echo "<p>❌ Failed to create directory: " . basename(dirname($dir)) . "/" . basename($dir) . "</p>";
        }
    }
}

// Test required functions
echo "<h3>Step 5: Required Functions Test</h3>";
require_once __DIR__ . '/../includes/student_id_helper.php';
require_once __DIR__ . '/../includes/email_helper.php';

if (function_exists('getNextStudentID')) {
    echo "<p>✅ getNextStudentID function available</p>";
} else {
    echo "<p>❌ getNextStudentID function missing</p>";
}

if (function_exists('sendRegistrationEmail')) {
    echo "<p>✅ sendRegistrationEmail function available</p>";
} else {
    echo "<p>❌ sendRegistrationEmail function missing</p>";
}

// Test form submission with minimal data
echo "<h3>Step 6: Minimal Form Submission Test</h3>";
echo "<p>Test the form submission with this minimal test form:</p>";

echo '<div style="background: #e8f4fd; padding: 20px; border: 1px solid #bee5eb; border-radius: 5px; margin: 20px 0;">';
echo '<h4>🧪 Test Form (No File Uploads)</h4>';
echo '<form method="POST" action="submit_registration.php" style="max-width: 600px;">';
echo '<input type="hidden" name="course_id" value="65">';

$test_fields = [
    'name' => 'Test Student',
    'father_name' => 'Test Father',
    'mother_name' => 'Test Mother',
    'dob' => '1990-01-01',
    'mobile' => '9876543210',
    'email' => 'test@example.com',
    'aadhar' => '123456789012',
    'gender' => 'Male',
    'religion' => 'Hindu',
    'marital_status' => 'Single',
    'category' => 'General',
    'position' => 'Student',
    'nationality' => 'Indian',
    'state' => 'Odisha',
    'city' => 'Bhubaneswar',
    'pincode' => '751001',
    'address' => 'Test Address, Bhubaneswar',
    'training_center' => 'NIELIT Bhubaneswar',
    'college_name' => 'Test College'
];

foreach ($test_fields as $field => $value) {
    echo "<div style='margin: 5px 0;'>";
    echo "<label style='display: inline-block; width: 150px;'>$field:</label>";
    echo "<input type='text' name='$field' value='$value' style='width: 200px; padding: 2px;'>";
    echo "</div>";
}

echo '<div style="margin: 10px 0;">';
echo '<button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px;">Test Submit (No Files)</button>';
echo '</div>';
echo '</form>';
echo '</div>';

// Common issues and solutions
echo "<h3>Step 7: Common Issues & Solutions</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
echo "<h4>🔧 Common Registration Issues:</h4>";
echo "<ul>";
echo "<li><strong>File Upload Errors:</strong> Check file size limits (5MB for images, 10MB for PDFs)</li>";
echo "<li><strong>Required Field Missing:</strong> All mandatory fields must be filled</li>";
echo "<li><strong>Database Errors:</strong> Check if all required columns exist in students table</li>";
echo "<li><strong>Permission Errors:</strong> Upload directories must be writable</li>";
echo "<li><strong>Session Issues:</strong> Check if sessions are working properly</li>";
echo "</ul>";
echo "</div>";

echo "<h3>Step 8: Next Steps</h3>";
echo "<ol>";
echo "<li><strong>Try the test form above</strong> - This will test without file uploads</li>";
echo "<li><strong>Check browser console</strong> - Look for JavaScript errors during submission</li>";
echo "<li><strong>Check network tab</strong> - See if the form actually submits to submit_registration.php</li>";
echo "<li><strong>Try with smaller files</strong> - Use very small image files for testing</li>";
echo "</ol>";

echo "<hr>";
echo "<p><em>Debug completed at: " . date('Y-m-d H:i:s') . "</em></p>";
?>