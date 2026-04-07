<?php
/**
 * Test Excel Export Functionality
 * NIELIT Bhubaneswar - Test Script
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/attendance_in_out_helper.php';

// Set admin session for testing
$_SESSION['admin'] = 1;
$_SESSION['admin_name'] = 'Test Admin';

echo "<h2>Excel Export Test</h2>";

// Test 1: Check if we have attendance sessions
echo "<h3>1. Testing Attendance Sessions</h3>";
$sessions_query = "SELECT * FROM attendance_sessions ORDER BY date DESC LIMIT 5";
$sessions_result = $conn->query($sessions_query);

if ($sessions_result && $sessions_result->num_rows > 0) {
    echo "<p>✅ Found " . $sessions_result->num_rows . " attendance sessions</p>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Session Name</th><th>Date</th><th>Course</th><th>Export Link</th></tr>";
    
    while ($session = $sessions_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $session['id'] . "</td>";
        echo "<td>" . htmlspecialchars($session['session_name']) . "</td>";
        echo "<td>" . $session['date'] . "</td>";
        echo "<td>" . htmlspecialchars($session['course_name']) . "</td>";
        echo "<td><a href='export_attendance.php?session_id=" . $session['id'] . "' target='_blank'>Export Excel</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ No attendance sessions found</p>";
}

// Test 2: Check monthly reports
echo "<h3>2. Testing Monthly Reports</h3>";
$current_year = date('Y');
$current_month = date('n');

echo "<p>Testing monthly report for " . date('F Y') . "</p>";
echo "<p><a href='attendance_reports.php?export=excel&year=$current_year&month=$current_month' target='_blank'>Export Monthly Report</a></p>";

// Test 3: Check attendance data
echo "<h3>3. Testing Attendance Data</h3>";
$attendance_query = "SELECT COUNT(*) as total FROM attendance_logs";
$attendance_result = $conn->query($attendance_query);
$attendance_count = $attendance_result ? $attendance_result->fetch_assoc()['total'] : 0;

echo "<p>Total attendance logs: " . $attendance_count . "</p>";

if ($attendance_count > 0) {
    echo "<p>✅ Attendance data available for export</p>";
} else {
    echo "<p>❌ No attendance data found</p>";
}

// Test 4: Check helper functions
echo "<h3>4. Testing Helper Functions</h3>";
if (function_exists('getSessionAttendanceList')) {
    echo "<p>✅ getSessionAttendanceList function available</p>";
} else {
    echo "<p>❌ getSessionAttendanceList function missing</p>";
}

if (function_exists('getAttendanceStatistics')) {
    echo "<p>✅ getAttendanceStatistics function available</p>";
} else {
    echo "<p>❌ getAttendanceStatistics function missing</p>";
}

if (function_exists('getMonthlyAttendanceReport')) {
    echo "<p>✅ getMonthlyAttendanceReport function available</p>";
} else {
    echo "<p>❌ getMonthlyAttendanceReport function missing</p>";
}

echo "<h3>Test Complete</h3>";
echo "<p>If you see export links above, click them to test Excel export functionality.</p>";
?>