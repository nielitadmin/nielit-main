<?php
/**
 * Quick Fix for FDCP-2026 Course
 * NIELIT Bhubaneswar - Emergency Course Fix
 */

session_start();
require_once __DIR__ . '/../config/config.php';

echo "<h2>Quick Fix for FDCP-2026 Course</h2>";

// Step 1: Check if course exists
$course_code = 'FDCP-2026';
$stmt = $conn->prepare("SELECT * FROM courses WHERE course_code = ?");
$stmt->bind_param("s", $course_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $course = $result->fetch_assoc();
    echo "<p>✅ Course exists: " . htmlspecialchars($course['course_name']) . "</p>";
    
    // Fix all potential issues
    $fixes_needed = [];
    
    if ($course['link_published'] != 1) {
        $fixes_needed[] = "link_published = 1";
    }
    
    if ($course['status'] != 'active') {
        $fixes_needed[] = "status = 'active'";
    }
    
    if (empty($course['course_abbreviation'])) {
        $fixes_needed[] = "course_abbreviation = 'FDCP'";
    }
    
    if (!empty($fixes_needed)) {
        echo "<p>Applying fixes: " . implode(', ', $fixes_needed) . "</p>";
        
        $update_sql = "UPDATE courses SET " . implode(', ', $fixes_needed) . " WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $course['id']);
        
        if ($update_stmt->execute()) {
            echo "<p>✅ All fixes applied successfully</p>";
        } else {
            echo "<p>❌ Failed to apply fixes: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>✅ Course is already properly configured</p>";
    }
    
} else {
    echo "<p>❌ Course doesn't exist. Creating FDCP-2026...</p>";
    
    $create_sql = "INSERT INTO courses (course_name, course_code, course_abbreviation, status, link_published, created_at) VALUES (?, ?, ?, 'active', 1, NOW())";
    $create_stmt = $conn->prepare($create_sql);
    
    $course_name = "Foundation Course in Digital and Computer Proficiency - 2026";
    $course_abbr = "FDCP";
    
    $create_stmt->bind_param("sss", $course_name, $course_code, $course_abbr);
    
    if ($create_stmt->execute()) {
        echo "<p>✅ FDCP-2026 course created successfully</p>";
        echo "<p>New course ID: " . $conn->insert_id . "</p>";
    } else {
        echo "<p>❌ Failed to create course: " . $conn->error . "</p>";
    }
}

// Step 2: Verify the fix
echo "<h3>Verification</h3>";
$verify_stmt = $conn->prepare("SELECT * FROM courses WHERE course_code = ?");
$verify_stmt->bind_param("s", $course_code);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows > 0) {
    $verified_course = $verify_result->fetch_assoc();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
    
    $checks = [
        'ID' => [$verified_course['id'], 'info'],
        'Course Name' => [$verified_course['course_name'], 'info'],
        'Course Code' => [$verified_course['course_code'], 'info'],
        'Course Abbreviation' => [$verified_course['course_abbreviation'], !empty($verified_course['course_abbreviation']) ? 'good' : 'bad'],
        'Link Published' => [$verified_course['link_published'] ? 'Yes' : 'No', $verified_course['link_published'] ? 'good' : 'bad'],
        'Status' => [$verified_course['status'], $verified_course['status'] == 'active' ? 'good' : 'bad']
    ];
    
    foreach ($checks as $field => $data) {
        $value = $data[0];
        $status = $data[1];
        $color = $status == 'good' ? 'green' : ($status == 'bad' ? 'red' : 'black');
        
        echo "<tr>";
        echo "<td><strong>$field</strong></td>";
        echo "<td>" . htmlspecialchars($value) . "</td>";
        echo "<td style='color: $color;'>" . ($status == 'good' ? '✅' : ($status == 'bad' ? '❌' : 'ℹ️')) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Step 3: Test the registration URL
echo "<h3>Test Registration</h3>";
$registration_url = "https://nielitbhubaneswar.in/student/register.php?course=FDCP-2026";
echo "<p><strong>Registration URL:</strong> <a href='$registration_url' target='_blank'>$registration_url</a></p>";

echo "<h3>Fix Complete</h3>";
echo "<p>The FDCP-2026 course should now be properly configured for registration.</p>";
echo "<p>Try accessing the registration URL above.</p>";
?>