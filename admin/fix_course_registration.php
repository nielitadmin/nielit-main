<?php
/**
 * Fix Course Registration Issues
 * NIELIT Bhubaneswar - Course Registration Fix
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Set admin session for testing
$_SESSION['admin'] = 1;
$_SESSION['admin_name'] = 'Debug Admin';

echo "<h2>Course Registration Fix</h2>";

// Step 1: Check FDCP-2026 course
echo "<h3>Step 1: Checking FDCP-2026 Course</h3>";

$course_code = 'FDCP-2026';
$stmt = $conn->prepare("SELECT * FROM courses WHERE course_code = ? OR course_abbreviation = ?");
$stmt->bind_param("ss", $course_code, $course_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $course = $result->fetch_assoc();
    echo "<p>✅ Course found: " . htmlspecialchars($course['course_name']) . "</p>";
    
    // Check if link is published
    if ($course['link_published'] != 1) {
        echo "<p>❌ Course link not published. Fixing...</p>";
        
        $fix_stmt = $conn->prepare("UPDATE courses SET link_published = 1 WHERE id = ?");
        $fix_stmt->bind_param("i", $course['id']);
        
        if ($fix_stmt->execute()) {
            echo "<p>✅ Course link published successfully</p>";
        } else {
            echo "<p>❌ Failed to publish course link: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>✅ Course link is already published</p>";
    }
    
    // Check course status
    if ($course['status'] != 'active') {
        echo "<p>❌ Course status is not active. Current status: " . $course['status'] . "</p>";
        echo "<p>Fixing course status...</p>";
        
        $status_stmt = $conn->prepare("UPDATE courses SET status = 'active' WHERE id = ?");
        $status_stmt->bind_param("i", $course['id']);
        
        if ($status_stmt->execute()) {
            echo "<p>✅ Course status set to active</p>";
        } else {
            echo "<p>❌ Failed to update course status: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>✅ Course status is active</p>";
    }
    
    // Display course details
    echo "<h4>Course Details:</h4>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>" . $course['id'] . "</td></tr>";
    echo "<tr><td>Course Name</td><td>" . htmlspecialchars($course['course_name']) . "</td></tr>";
    echo "<tr><td>Course Code</td><td>" . htmlspecialchars($course['course_code']) . "</td></tr>";
    echo "<tr><td>Course Abbreviation</td><td>" . htmlspecialchars($course['course_abbreviation']) . "</td></tr>";
    echo "<tr><td>Link Published</td><td>" . ($course['link_published'] ? 'Yes' : 'No') . "</td></tr>";
    echo "<tr><td>Status</td><td>" . htmlspecialchars($course['status']) . "</td></tr>";
    echo "</table>";
    
} else {
    echo "<p>❌ Course not found. Creating FDCP-2026 course...</p>";
    
    $create_stmt = $conn->prepare("INSERT INTO courses (course_name, course_code, course_abbreviation, status, link_published, created_at) VALUES (?, ?, ?, 'active', 1, NOW())");
    $course_name = "Foundation Course in Digital and Computer Proficiency - 2026";
    $course_abbr = "FDCP";
    
    $create_stmt->bind_param("sss", $course_name, $course_code, $course_abbr);
    
    if ($create_stmt->execute()) {
        echo "<p>✅ FDCP-2026 course created successfully</p>";
        $course_id = $conn->insert_id;
        echo "<p>New course ID: $course_id</p>";
    } else {
        echo "<p>❌ Failed to create course: " . $conn->error . "</p>";
    }
}

// Step 2: Test registration URL
echo "<h3>Step 2: Testing Registration URL</h3>";
$registration_url = "https://nielitbhubaneswar.in/student/register.php?course=FDCP-2026";
echo "<p>Registration URL: <a href='$registration_url' target='_blank'>$registration_url</a></p>";

// Step 3: Check upload directories
echo "<h3>Step 3: Checking Upload Directories</h3>";

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
    $full_path = __DIR__ . '/../' . $dir;
    
    if (!is_dir($full_path)) {
        echo "<p>❌ Directory missing: $dir. Creating...</p>";
        if (mkdir($full_path, 0755, true)) {
            echo "<p>✅ Created directory: $dir</p>";
        } else {
            echo "<p>❌ Failed to create directory: $dir</p>";
        }
    } else {
        echo "<p>✅ Directory exists: $dir</p>";
    }
    
    if (is_writable($full_path)) {
        echo "<p>✅ Directory writable: $dir</p>";
    } else {
        echo "<p>❌ Directory not writable: $dir</p>";
    }
}

// Step 4: Test database connection and tables
echo "<h3>Step 4: Testing Database</h3>";

$required_tables = ['students', 'courses', 'education_details'];
foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<p>✅ Table exists: $table</p>";
        
        // Check table structure for students table
        if ($table == 'students') {
            $columns = $conn->query("SHOW COLUMNS FROM students");
            $column_names = [];
            while ($col = $columns->fetch_assoc()) {
                $column_names[] = $col['Field'];
            }
            echo "<p>Students table columns: " . implode(', ', $column_names) . "</p>";
        }
    } else {
        echo "<p>❌ Table missing: $table</p>";
    }
}

// Step 5: Test form submission endpoint
echo "<h3>Step 5: Testing Form Submission</h3>";
$submit_url = "https://nielitbhubaneswar.in/student/submit_registration.php";
echo "<p>Form submission endpoint: $submit_url</p>";

if (file_exists(__DIR__ . '/../student/submit_registration.php')) {
    echo "<p>✅ Submit registration file exists</p>";
} else {
    echo "<p>❌ Submit registration file missing</p>";
}

// Step 6: Test success page
echo "<h3>Step 6: Testing Success Page</h3>";
$success_url = "https://nielitbhubaneswar.in/student/registration_success.php";
echo "<p>Success page: $success_url</p>";

if (file_exists(__DIR__ . '/../student/registration_success.php')) {
    echo "<p>✅ Registration success file exists</p>";
} else {
    echo "<p>❌ Registration success file missing</p>";
}

echo "<h3>Fix Complete</h3>";
echo "<p>Try the registration again: <a href='$registration_url' target='_blank'>Test Registration</a></p>";
?>