<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>QR Code Status Check</h2>";

// Check total students
$total_result = $conn->query("SELECT COUNT(*) as total FROM students");
$total_students = $total_result ? $total_result->fetch_assoc()['total'] : 0;
echo "<p>Total students: $total_students</p>";

// Check students with QR codes
$qr_result = $conn->query("SELECT COUNT(*) as with_qr FROM students WHERE attendance_qr_code IS NOT NULL AND attendance_qr_code != ''");
$students_with_qr = $qr_result ? $qr_result->fetch_assoc()['with_qr'] : 0;
echo "<p>Students with QR codes: $students_with_qr</p>";

// Check if attendance_qr_code column exists
$column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'attendance_qr_code'");
if ($column_check && $column_check->num_rows > 0) {
    echo "<p style='color: green;'>✓ attendance_qr_code column exists</p>";
} else {
    echo "<p style='color: red;'>✗ attendance_qr_code column missing</p>";
}

// Show sample students
echo "<h3>Sample Students (first 5)</h3>";
$sample_result = $conn->query("SELECT student_id, name, attendance_qr_code FROM students LIMIT 5");
if ($sample_result && $sample_result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Student ID</th><th>Name</th><th>QR Code Path</th></tr>";
    while ($row = $sample_result->fetch_assoc()) {
        $qr_status = empty($row['attendance_qr_code']) ? 'No QR' : 'Has QR';
        echo "<tr>";
        echo "<td>{$row['student_id']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>$qr_status</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No students found</p>";
}

if ($students_with_qr == 0 && $total_students > 0) {
    echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>⚠ QR Codes Need to be Generated</h4>";
    echo "<p>You have $total_students students but none have QR codes generated.</p>";
    echo "<p>The installation script should have generated them. Let me check if the QR generation worked...</p>";
    echo "</div>";
    
    // Try to generate QR for one student
    require_once __DIR__ . '/../includes/attendance_qr_helper.php';
    
    $test_student = $conn->query("SELECT student_id, name FROM students LIMIT 1");
    if ($test_student && $test_student->num_rows > 0) {
        $student = $test_student->fetch_assoc();
        echo "<h3>Testing QR Generation</h3>";
        echo "<p>Testing QR generation for: {$student['name']} ({$student['student_id']})</p>";
        
        $qr_result = generateStudentAttendanceQR($student['student_id'], $student['name'], $conn);
        
        if ($qr_result['success']) {
            echo "<p style='color: green;'>✓ QR generation successful!</p>";
            echo "<p>QR Path: {$qr_result['path']}</p>";
        } else {
            echo "<p style='color: red;'>✗ QR generation failed: {$qr_result['message']}</p>";
        }
    }
}

$conn->close();
?>