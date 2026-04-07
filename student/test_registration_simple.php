<?php
/**
 * Simple Registration Test
 * NIELIT Bhubaneswar - Minimal Registration Test
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';

echo "<h2>Simple Registration Test</h2>";

// Test the exact URL that's failing
$course_code = 'FDCP-2026';
$test_url = "https://nielitbhubaneswar.in/student/register.php?course=$course_code";

echo "<h3>Testing Registration URL</h3>";
echo "<p>URL: <a href='$test_url' target='_blank'>$test_url</a></p>";

// Check if course exists and is accessible
echo "<h3>Course Verification</h3>";

$stmt = $conn->prepare("SELECT * FROM courses WHERE course_code = ? OR course_abbreviation = ?");
$stmt->bind_param("ss", $course_code, $course_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $course = $result->fetch_assoc();
    echo "<p>✅ Course found: " . htmlspecialchars($course['course_name']) . "</p>";
    echo "<p>Link Published: " . ($course['link_published'] ? 'Yes' : 'No') . "</p>";
    echo "<p>Status: " . htmlspecialchars($course['status']) . "</p>";
    
    if (!$course['link_published']) {
        echo "<p>❌ <strong>PROBLEM FOUND:</strong> Course link is not published!</p>";
        echo "<p>Fixing this now...</p>";
        
        $fix_stmt = $conn->prepare("UPDATE courses SET link_published = 1 WHERE id = ?");
        $fix_stmt->bind_param("i", $course['id']);
        
        if ($fix_stmt->execute()) {
            echo "<p>✅ <strong>FIXED:</strong> Course link is now published</p>";
        } else {
            echo "<p>❌ Failed to fix: " . $conn->error . "</p>";
        }
    }
    
    // Create a simple test form
    echo "<h3>Simple Test Form</h3>";
    echo "<p>This form will test the basic submission process:</p>";
    
    echo '<form method="POST" action="submit_registration.php" enctype="multipart/form-data" style="border: 1px solid #ccc; padding: 20px; max-width: 600px;">';
    echo '<input type="hidden" name="course_id" value="' . $course['id'] . '">';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Name: <input type="text" name="name" value="Test Student" required style="width: 200px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Father Name: <input type="text" name="father_name" value="Test Father" required style="width: 200px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Mother Name: <input type="text" name="mother_name" value="Test Mother" required style="width: 200px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>DOB: <input type="date" name="dob" value="1990-01-01" required></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Mobile: <input type="text" name="mobile" value="9876543210" required style="width: 150px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Email: <input type="email" name="email" value="test@example.com" required style="width: 200px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Aadhar: <input type="text" name="aadhar" value="123456789012" required style="width: 150px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Gender: <select name="gender" required><option value="Male">Male</option><option value="Female">Female</option></select></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Religion: <select name="religion" required><option value="Hindu">Hindu</option><option value="Other">Other</option></select></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Marital Status: <select name="marital_status" required><option value="Single">Single</option><option value="Married">Married</option></select></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Category: <select name="category" required><option value="General">General</option><option value="OBC">OBC</option></select></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Position: <input type="text" name="position" value="Student" required style="width: 150px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Nationality: <input type="text" name="nationality" value="Indian" required style="width: 150px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>State: <input type="text" name="state" value="Odisha" required style="width: 150px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>City: <input type="text" name="city" value="Bhubaneswar" required style="width: 150px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Pincode: <input type="text" name="pincode" value="751001" required style="width: 100px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Address: <textarea name="address" required style="width: 300px; height: 60px;">Test Address, Bhubaneswar</textarea></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>Training Center: <input type="text" name="training_center" value="NIELIT Bhubaneswar" required style="width: 200px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<label>College Name: <input type="text" name="college_name" value="Test College" style="width: 200px;"></label>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<p><strong>Note:</strong> This test form skips file uploads to test basic submission</p>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 10px;">';
    echo '<button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px;">Test Submit</button>';
    echo '</div>';
    
    echo '</form>';
    
} else {
    echo "<p>❌ Course not found: $course_code</p>";
    
    // Show available courses
    $all_courses = $conn->query("SELECT * FROM courses ORDER BY id DESC LIMIT 10");
    if ($all_courses && $all_courses->num_rows > 0) {
        echo "<h4>Available Courses:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Code</th><th>Abbreviation</th><th>Link Published</th><th>Status</th></tr>";
        
        while ($c = $all_courses->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $c['id'] . "</td>";
            echo "<td>" . htmlspecialchars($c['course_name']) . "</td>";
            echo "<td>" . htmlspecialchars($c['course_code']) . "</td>";
            echo "<td>" . htmlspecialchars($c['course_abbreviation']) . "</td>";
            echo "<td>" . ($c['link_published'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . htmlspecialchars($c['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Check session and error messages
echo "<h3>Session Information</h3>";
if (isset($_SESSION['error'])) {
    echo "<p style='color: red;'><strong>Session Error:</strong> " . $_SESSION['error'] . "</p>";
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    echo "<p style='color: green;'><strong>Session Success:</strong> " . $_SESSION['success'] . "</p>";
    unset($_SESSION['success']);
}

echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Keys: " . implode(', ', array_keys($_SESSION)) . "</p>";

echo "<h3>Next Steps</h3>";
echo "<ol>";
echo "<li>Try the registration URL above</li>";
echo "<li>If it loads, fill out the form completely</li>";
echo "<li>If it doesn't redirect to success page, check browser console for errors</li>";
echo "<li>Use the simple test form above to test basic submission</li>";
echo "</ol>";
?>