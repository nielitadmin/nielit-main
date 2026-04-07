<?php
/**
 * Debug Submit Registration
 * NIELIT Bhubaneswar - Debug Version of Form Submission
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

echo "<h2>🔍 Registration Submission Debug</h2>";

// Log the submission attempt
$log_file = __DIR__ . '/debug_submission.log';
$timestamp = date('Y-m-d H:i:s');
$log_entry = "\n=== DEBUG SUBMISSION ===\n";
$log_entry .= "Timestamp: $timestamp\n";
$log_entry .= "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
$log_entry .= "Content Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'Unknown') . "\n";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo "<p>❌ Not a POST request. Method: " . $_SERVER['REQUEST_METHOD'] . "</p>";
    echo "<p><a href='register.php?course=FDCP-2026'>Go back to registration</a></p>";
    exit();
}

echo "<p>✅ POST request received</p>";

// Check if required files are included
$required_includes = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../includes/student_id_helper.php',
    __DIR__ . '/../includes/email_helper.php'
];

foreach ($required_includes as $file) {
    if (file_exists($file)) {
        require_once $file;
        echo "<p>✅ Included: " . basename($file) . "</p>";
    } else {
        echo "<p>❌ Missing: " . basename($file) . "</p>";
        $log_entry .= "Missing file: $file\n";
    }
}

// Check database connection
if (isset($conn)) {
    echo "<p>✅ Database connection available</p>";
} else {
    echo "<p>❌ Database connection not available</p>";
    $log_entry .= "Database connection failed\n";
}

// Check POST data
echo "<h3>POST Data Analysis</h3>";
$required_fields = ['course_id', 'name', 'father_name', 'mother_name', 'dob', 'mobile', 'email', 'aadhar'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $missing_fields[] = $field;
        echo "<p>❌ Missing required field: $field</p>";
    } else {
        echo "<p>✅ Field present: $field = " . substr($_POST[$field], 0, 20) . (strlen($_POST[$field]) > 20 ? '...' : '') . "</p>";
    }
}

if (!empty($missing_fields)) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ Missing Required Fields:</strong><br>";
    echo implode(', ', $missing_fields);
    echo "</div>";
    $log_entry .= "Missing fields: " . implode(', ', $missing_fields) . "\n";
}

// Check file uploads
echo "<h3>File Upload Analysis</h3>";
$required_files = ['passport_photo', 'signature'];
$optional_files = ['aadhar_card', 'tenth_marksheet', 'payment_receipt'];

foreach ($required_files as $file_field) {
    if (isset($_FILES[$file_field]) && $_FILES[$file_field]['error'] === UPLOAD_ERR_OK) {
        echo "<p>✅ Required file uploaded: $file_field (" . $_FILES[$file_field]['size'] . " bytes)</p>";
    } else {
        $error_code = $_FILES[$file_field]['error'] ?? 'missing';
        echo "<p>❌ Required file missing/error: $file_field (Error: $error_code)</p>";
        $log_entry .= "File error: $file_field = $error_code\n";
    }
}

foreach ($optional_files as $file_field) {
    if (isset($_FILES[$file_field]) && $_FILES[$file_field]['error'] === UPLOAD_ERR_OK) {
        echo "<p>✅ Optional file uploaded: $file_field (" . $_FILES[$file_field]['size'] . " bytes)</p>";
    } else {
        echo "<p>ℹ️ Optional file not uploaded: $file_field</p>";
    }
}

// Check course validation
echo "<h3>Course Validation</h3>";
$course_id = intval($_POST['course_id'] ?? 0);
if ($course_id > 0 && isset($conn)) {
    $stmt = $conn->prepare("SELECT course_name, course_code FROM courses WHERE id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $course = $result->fetch_assoc();
        echo "<p>✅ Course found: " . htmlspecialchars($course['course_name']) . " (ID: $course_id)</p>";
    } else {
        echo "<p>❌ Course not found for ID: $course_id</p>";
        $log_entry .= "Course not found: $course_id\n";
    }
} else {
    echo "<p>❌ Invalid course ID: $course_id</p>";
    $log_entry .= "Invalid course ID: $course_id\n";
}

// Test student ID generation
echo "<h3>Student ID Generation Test</h3>";
if (function_exists('getNextStudentID') && $course_id > 0) {
    $test_student_id = getNextStudentID($course_id, $conn);
    if ($test_student_id) {
        echo "<p>✅ Student ID generation works: $test_student_id</p>";
    } else {
        echo "<p>❌ Student ID generation failed</p>";
        $log_entry .= "Student ID generation failed\n";
    }
} else {
    echo "<p>❌ Cannot test student ID generation</p>";
}

// Write log
$log_entry .= "=== END DEBUG ===\n\n";
file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

// Summary
echo "<h3>🎯 Summary</h3>";
if (empty($missing_fields) && isset($conn) && $course_id > 0) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
    echo "<strong>✅ Basic validation passed!</strong><br>";
    echo "The form data looks good. The issue might be in the actual processing logic.";
    echo "</div>";
    
    echo "<h4>Next Steps:</h4>";
    echo "<ol>";
    echo "<li>Try submitting with very small files (under 1MB)</li>";
    echo "<li>Check server error logs for detailed errors</li>";
    echo "<li>Test with the actual submit_registration.php</li>";
    echo "</ol>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<strong>❌ Issues found!</strong><br>";
    echo "Fix the issues above before proceeding.";
    echo "</div>";
}

echo "<h4>🔧 Debug Tools:</h4>";
echo "<p><a href='debug_form_submission.php'>Form Submission Debug</a></p>";
echo "<p><a href='register.php?course=FDCP-2026'>Back to Registration Form</a></p>";

echo "<hr>";
echo "<p><em>Debug completed at: " . date('Y-m-d H:i:s') . "</em></p>";
?>