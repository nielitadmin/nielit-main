<?php
/**
 * Test Registration Now - Final Test
 * NIELIT Bhubaneswar - Registration Test
 */

session_start();
require_once __DIR__ . '/../config/config.php';

echo "<h2>🚀 Registration Test - FDCP-2026</h2>";

// Test the exact registration flow
$course_code = 'FDCP-2026';
$registration_url = "https://nielitbhubaneswar.in/student/register.php?course=$course_code";

echo "<h3>Step 1: Course Verification</h3>";

// Verify course exists and is properly configured
$stmt = $conn->prepare("SELECT * FROM courses WHERE course_code = ?");
$stmt->bind_param("s", $course_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $course = $result->fetch_assoc();
    echo "<p>✅ Course found: " . htmlspecialchars($course['course_name']) . "</p>";
    echo "<p>✅ Link Published: " . ($course['link_published'] ? 'Yes' : 'No') . "</p>";
    echo "<p>✅ Course ID: " . $course['id'] . "</p>";
    
    if (!$course['link_published']) {
        echo "<p>❌ <strong>FIXING:</strong> Course link not published</p>";
        $fix_stmt = $conn->prepare("UPDATE courses SET link_published = 1 WHERE id = ?");
        $fix_stmt->bind_param("i", $course['id']);
        if ($fix_stmt->execute()) {
            echo "<p>✅ <strong>FIXED:</strong> Course link now published</p>";
        }
    }
} else {
    echo "<p>❌ Course not found</p>";
    exit;
}

echo "<h3>Step 2: Registration Page Test</h3>";
echo "<p><strong>Registration URL:</strong> <a href='$registration_url' target='_blank' style='color: #007bff; font-weight: bold;'>$registration_url</a></p>";

echo "<h3>Step 3: Test Instructions</h3>";
echo "<div style='background: #f8f9fa; padding: 20px; border-left: 4px solid #007bff; margin: 20px 0;'>";
echo "<h4>🎯 How to Test Registration:</h4>";
echo "<ol>";
echo "<li><strong>Click the registration URL above</strong> - It should load the registration form</li>";
echo "<li><strong>Fill out the form completely:</strong>";
echo "<ul>";
echo "<li>Personal details (name, DOB, mobile, email, etc.)</li>";
echo "<li>Upload passport photo (JPG/PNG, max 5MB)</li>";
echo "<li>Upload signature (JPG/PNG, max 5MB)</li>";
echo "<li>Upload Aadhar card (PDF/JPG, max 10MB)</li>";
echo "<li>Upload 10th marksheet (PDF/JPG, max 10MB)</li>";
echo "<li>Fill educational qualifications</li>";
echo "</ul></li>";
echo "<li><strong>Submit the form</strong> - It should redirect to success page</li>";
echo "<li><strong>Check for success message</strong> with Student ID and Password</li>";
echo "</ol>";
echo "</div>";

echo "<h3>Step 4: Expected Flow</h3>";
echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>✅ Expected Success Flow:</strong></p>";
echo "<p>1. Registration form loads with FDCP-2026 pre-selected</p>";
echo "<p>2. Form validation works (client-side)</p>";
echo "<p>3. File uploads work properly</p>";
echo "<p>4. Form submits to: <code>submit_registration.php</code></p>";
echo "<p>5. Student ID generated: <code>NIELIT/2026/FDCP/XXXX</code></p>";
echo "<p>6. Redirects to: <code>registration_success.php</code></p>";
echo "<p>7. Shows success message with credentials</p>";
echo "</div>";

echo "<h3>Step 5: Troubleshooting</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>⚠️ If Registration Fails:</strong></p>";
echo "<p>1. <strong>Form doesn't load:</strong> Course link not published (should be fixed above)</p>";
echo "<p>2. <strong>Form validation errors:</strong> Check browser console for JavaScript errors</p>";
echo "<p>3. <strong>File upload errors:</strong> Check file size and format requirements</p>";
echo "<p>4. <strong>Doesn't redirect to success:</strong> Check server error logs</p>";
echo "<p>5. <strong>Database errors:</strong> Check required fields are filled</p>";
echo "</div>";

echo "<h3>Step 6: Debug Tools</h3>";
echo "<p>If you encounter issues, use these debug tools:</p>";
echo "<ul>";
echo "<li><a href='debug_registration_flow.php' target='_blank'>Registration Flow Debug</a></li>";
echo "<li><a href='test_registration_simple.php' target='_blank'>Simple Registration Test</a></li>";
echo "<li><a href='../admin/fix_course_registration.php' target='_blank'>Course Registration Fix</a></li>";
echo "</ul>";

echo "<h3>🎯 START TESTING NOW</h3>";
echo "<p style='font-size: 18px; font-weight: bold; color: #007bff;'>";
echo "<a href='$registration_url' target='_blank' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>🚀 TEST REGISTRATION NOW</a>";
echo "</p>";

echo "<hr>";
echo "<p><em>Debug completed at: " . date('Y-m-d H:i:s') . "</em></p>";
?>