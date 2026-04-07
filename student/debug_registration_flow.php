<?php
/**
 * Debug Registration Flow
 * NIELIT Bhubaneswar - Debug Script
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';

echo "<h2>Registration Flow Debug</h2>";

// Test 1: Check if course exists
echo "<h3>1. Testing Course Access</h3>";
$course_code = 'FDCP-2026';
echo "<p>Testing course: <strong>$course_code</strong></p>";

$stmt = $conn->prepare("SELECT * FROM courses WHERE (course_code = ? OR course_abbreviation = ?)");
if ($stmt) {
    $stmt->bind_param("ss", $course_code, $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $course = $result->fetch_assoc();
        echo "<p>✅ Course found:</p>";
        echo "<ul>";
        echo "<li>ID: " . $course['id'] . "</li>";
        echo "<li>Name: " . htmlspecialchars($course['course_name']) . "</li>";
        echo "<li>Code: " . htmlspecialchars($course['course_code']) . "</li>";
        echo "<li>Link Published: " . ($course['link_published'] ? 'Yes' : 'No') . "</li>";
        echo "<li>Status: " . htmlspecialchars($course['status']) . "</li>";
        echo "</ul>";
        
        if (!$course['link_published']) {
            echo "<p>❌ <strong>ISSUE FOUND:</strong> Course link is not published!</p>";
            echo "<p><strong>Fix:</strong> Set link_published = 1 for this course</p>";
            
            // Auto-fix
            $fix_stmt = $conn->prepare("UPDATE courses SET link_published = 1 WHERE id = ?");
            $fix_stmt->bind_param("i", $course['id']);
            if ($fix_stmt->execute()) {
                echo "<p>✅ <strong>FIXED:</strong> Course link is now published</p>";
            } else {
                echo "<p>❌ Failed to fix: " . $conn->error . "</p>";
            }
        }
    } else {
        echo "<p>❌ Course not found with code: $course_code</p>";
        
        // Check what courses exist
        $all_courses = $conn->query("SELECT id, course_name, course_code, course_abbreviation, link_published FROM courses LIMIT 10");
        if ($all_courses && $all_courses->num_rows > 0) {
            echo "<p>Available courses:</p>";
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Name</th><th>Code</th><th>Abbreviation</th><th>Link Published</th></tr>";
            while ($c = $all_courses->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $c['id'] . "</td>";
                echo "<td>" . htmlspecialchars($c['course_name']) . "</td>";
                echo "<td>" . htmlspecialchars($c['course_code']) . "</td>";
                echo "<td>" . htmlspecialchars($c['course_abbreviation']) . "</td>";
                echo "<td>" . ($c['link_published'] ? 'Yes' : 'No') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
} else {
    echo "<p>❌ Database error: " . $conn->error . "</p>";
}

// Test 2: Check registration page access
echo "<h3>2. Testing Registration Page Access</h3>";
$register_url = APP_URL . "/student/register.php?course=$course_code";
echo "<p>Registration URL: <a href='$register_url' target='_blank'>$register_url</a></p>";

// Test 3: Check file permissions
echo "<h3>3. Testing File Permissions</h3>";
$upload_dir = __DIR__ . '/uploads/students/';
echo "<p>Upload directory: $upload_dir</p>";

if (!is_dir($upload_dir)) {
    echo "<p>❌ Upload directory doesn't exist. Creating...</p>";
    if (mkdir($upload_dir, 0755, true)) {
        echo "<p>✅ Upload directory created</p>";
    } else {
        echo "<p>❌ Failed to create upload directory</p>";
    }
} else {
    echo "<p>✅ Upload directory exists</p>";
}

if (is_writable($upload_dir)) {
    echo "<p>✅ Upload directory is writable</p>";
} else {
    echo "<p>❌ Upload directory is not writable</p>";
    echo "<p>Current permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "</p>";
}

// Test 4: Check database tables
echo "<h3>4. Testing Database Tables</h3>";

$tables = ['students', 'education_details', 'courses'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<p>✅ Table '$table' exists</p>";
    } else {
        echo "<p>❌ Table '$table' missing</p>";
    }
}

// Test 5: Check required functions
echo "<h3>5. Testing Required Functions</h3>";

require_once __DIR__ . '/../includes/student_id_helper.php';
require_once __DIR__ . '/../includes/email_helper.php';

if (function_exists('getNextStudentID')) {
    echo "<p>✅ getNextStudentID function available</p>";
    
    // Test student ID generation
    if (isset($course['id'])) {
        $test_id = getNextStudentID($course['id'], $conn);
        if ($test_id) {
            echo "<p>✅ Student ID generation works: $test_id</p>";
        } else {
            echo "<p>❌ Student ID generation failed</p>";
        }
    }
} else {
    echo "<p>❌ getNextStudentID function missing</p>";
}

if (function_exists('sendRegistrationEmail')) {
    echo "<p>✅ sendRegistrationEmail function available</p>";
} else {
    echo "<p>❌ sendRegistrationEmail function missing</p>";
}

// Test 6: Check session configuration
echo "<h3>6. Testing Session Configuration</h3>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session save path: " . session_save_path() . "</p>";
echo "<p>Session cookie params: " . json_encode(session_get_cookie_params()) . "</p>";

// Test 7: Test form submission simulation
echo "<h3>7. Form Submission Test</h3>";
echo "<p>You can test the registration by:</p>";
echo "<ol>";
echo "<li>Go to: <a href='$register_url' target='_blank'>Registration Page</a></li>";
echo "<li>Fill out the form completely</li>";
echo "<li>Submit and check if it redirects to success page</li>";
echo "</ol>";

// Test 8: Check success page
echo "<h3>8. Testing Success Page</h3>";
$success_url = APP_URL . "/student/registration_success.php";
echo "<p>Success page URL: <a href='$success_url' target='_blank'>$success_url</a></p>";
echo "<p>Note: This will redirect to courses page if no session data</p>";

echo "<h3>Debug Complete</h3>";
echo "<p>If you see any ❌ issues above, those need to be fixed for registration to work properly.</p>";
?>