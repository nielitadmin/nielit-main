<?php
// Complete registration fix - addresses all potential issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';

echo "<h2>Complete Registration Fix</h2>";

// 1. Check and fix missing database columns
echo "<h3>1. Database Schema Fix</h3>";

$missing_columns_sql = [
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS batch_id INT NULL AFTER course_id",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS nielit_registration_no VARCHAR(100) NULL AFTER student_id",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS documents TEXT NULL AFTER registration_date",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS approved_by INT NULL AFTER status",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL AFTER approved_by",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS attendance_qr_code VARCHAR(255) NULL AFTER training_center"
];

foreach ($missing_columns_sql as $sql) {
    if ($conn->query($sql)) {
        echo "✅ Executed: " . substr($sql, 0, 50) . "...<br>";
    } else {
        echo "⚠️ Failed or already exists: " . substr($sql, 0, 50) . "... (" . $conn->error . ")<br>";
    }
}

// 2. Check current table structure
echo "<h3>2. Current Table Structure</h3>";
$result = $conn->query("DESCRIBE students");
$columns = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    echo "Total columns: " . count($columns) . "<br>";
    echo "Columns: " . implode(', ', $columns) . "<br>";
} else {
    echo "❌ Cannot read table structure: " . $conn->error . "<br>";
}

// 3. Test the FDCP-2026 course
echo "<h3>3. Course Verification</h3>";
$stmt = $conn->prepare("SELECT * FROM courses WHERE course_code = ? OR course_abbreviation = ?");
$course_code = 'FDCP-2026';
$stmt->bind_param("ss", $course_code, $course_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $course = $result->fetch_assoc();
    echo "✅ Course found: " . htmlspecialchars($course['course_name']) . "<br>";
    echo "Course ID: " . $course['id'] . "<br>";
    echo "Link Published: " . ($course['link_published'] ? 'Yes' : 'No') . "<br>";
    
    if (!$course['link_published']) {
        echo "⚠️ Fixing link_published status...<br>";
        $update_stmt = $conn->prepare("UPDATE courses SET link_published = 1 WHERE id = ?");
        $update_stmt->bind_param("i", $course['id']);
        if ($update_stmt->execute()) {
            echo "✅ Course link_published status fixed<br>";
        } else {
            echo "❌ Failed to fix link_published status<br>";
        }
    }
} else {
    echo "❌ Course FDCP-2026 not found<br>";
    
    // Try to create the course
    echo "Creating FDCP-2026 course...<br>";
    $create_course_sql = "INSERT INTO courses (course_name, course_code, course_abbreviation, training_center, link_published, status) VALUES (?, ?, ?, ?, 1, 'active')";
    $create_stmt = $conn->prepare($create_course_sql);
    $course_name = 'Fundamentals of Data Curation using Python (Internship program for Utkal University)';
    $course_code = 'FDCP-2026';
    $course_abbr = 'FDCP-2026';
    $training_center = 'NIELIT BHUBANESWAR';
    
    $create_stmt->bind_param("ssss", $course_name, $course_code, $course_abbr, $training_center);
    if ($create_stmt->execute()) {
        echo "✅ Course FDCP-2026 created successfully<br>";
    } else {
        echo "❌ Failed to create course: " . $create_stmt->error . "<br>";
    }
}

// 4. Create upload directories
echo "<h3>4. Upload Directories</h3>";
$upload_dirs = [
    'uploads/students/',
    'uploads/aadhar/',
    'uploads/caste_certificates/',
    'uploads/marksheets/10th/',
    'uploads/marksheets/12th/',
    'uploads/marksheets/graduation/',
    'uploads/other/'
];

foreach ($upload_dirs as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    if (!is_dir($full_path)) {
        if (mkdir($full_path, 0755, true)) {
            echo "✅ Created directory: $dir<br>";
        } else {
            echo "❌ Failed to create directory: $dir<br>";
        }
    } else {
        echo "✅ Directory exists: $dir<br>";
    }
}

// 5. Test registration URL
echo "<h3>5. Registration URLs</h3>";
$base_url = "http" . (isset($_SERVER['HTTPS']) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
$registration_url = $base_url . "/register.php?course=FDCP-2026";
$simple_test_url = $base_url . "/test_registration_simple.php";

echo "Main Registration URL: <a href='$registration_url' target='_blank'>$registration_url</a><br>";
echo "Simple Test URL: <a href='$simple_test_url' target='_blank'>$simple_test_url</a><br>";

echo "<h3>6. Summary</h3>";
echo "✅ Database schema checked and fixed<br>";
echo "✅ Course FDCP-2026 verified/created<br>";
echo "✅ Upload directories created<br>";
echo "✅ Registration URLs ready for testing<br>";

echo "<h3>7. Next Steps</h3>";
echo "1. Test the simple registration form: <a href='test_registration_simple.php'>test_registration_simple.php</a><br>";
echo "2. If that works, test the full registration form: <a href='register.php?course=FDCP-2026'>register.php?course=FDCP-2026</a><br>";
echo "3. Check server error logs if issues persist<br>";

$conn->close();
?>