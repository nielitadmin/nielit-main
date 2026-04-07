<?php
/**
 * Simple QR Test - No session required
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/attendance_qr_helper.php';

echo "<h2>Simple QR System Test</h2>";

// Test database connection
if ($conn) {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
} else {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
    exit;
}

// Check if tables exist
$tables = ['attendance_sessions', 'qr_scan_logs', 'students', 'attendance'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Table '$table' exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Table '$table' missing</p>";
    }
}

// Check students with QR codes
$qr_result = $conn->query("SELECT COUNT(*) as count FROM students WHERE attendance_qr_code IS NOT NULL");
$qr_count = $qr_result ? $qr_result->fetch_assoc()['count'] : 0;
echo "<p>Students with QR codes: $qr_count</p>";

// Check active sessions
$session_result_count = $conn->query("SELECT COUNT(*) as count FROM attendance_sessions WHERE status = 'active'");
$session_count = $session_result_count ? $session_result_count->fetch_assoc()['count'] : 0;
echo "<p>Active sessions: $session_count</p>";

// Test QR data generation
$test_data = [
    'type' => 'student_attendance',
    'student_id' => 'TEST123',
    'student_name' => 'Test Student',
    'generated_at' => time(),
    'hash' => md5('test')
];

$test_json = json_encode($test_data);
echo "<p>Test QR JSON: <code>" . htmlspecialchars($test_json) . "</code></p>";

// Test JSON decode
$decoded = json_decode($test_json, true);
if ($decoded && $decoded['type'] === 'student_attendance') {
    echo "<p style='color: green;'>✓ JSON encoding/decoding works</p>";
    echo "<p>Decoded student name: " . htmlspecialchars($decoded['student_name']) . "</p>";
} else {
    echo "<p style='color: red;'>✗ JSON encoding/decoding failed</p>";
}

echo "<h3>Manual QR Processing Test</h3>";

// Create a test session if none exists
$session_result = $conn->query("SELECT id FROM attendance_sessions WHERE status = 'active' LIMIT 1");
if ($session_result && $session_result->num_rows > 0) {
    $session_id = $session_result->fetch_assoc()['id'];
    echo "<p>Using existing session ID: $session_id</p>";
} else {
    // Create test session
    $conn->query("INSERT INTO attendance_sessions (session_name, course_id, course_name, subject, date, start_time, end_time, coordinator_id, coordinator_name, status) VALUES ('Test Session', 1, 'Test Course', 'Test Subject', CURDATE(), '09:00:00', '17:00:00', 'admin', 'Test Admin', 'active')");
    $session_id = $conn->insert_id;
    echo "<p>Created new test session ID: $session_id</p>";
}

// Test the processAttendanceQRScan function directly
try {
    $result = processAttendanceQRScan($test_json, $session_id, 'admin', $conn);
    echo "<p>Function result:</p>";
    echo "<pre style='background: #f8f9fa; padding: 10px;'>";
    print_r($result);
    echo "</pre>";
    
    if (isset($result['student_name'])) {
        echo "<p style='color: green;'>✓ Student name returned: " . htmlspecialchars($result['student_name']) . "</p>";
    } else {
        echo "<p style='color: orange;'>⚠ No student_name in result</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Function error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn->close();
?>