<?php
/**
 * Generate QR Codes for All Students
 * NIELIT Bhubaneswar
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/attendance_qr_helper.php';

echo "<h2>Generating QR Codes for All Students</h2>";

// Get all students
$students_result = $conn->query("SELECT student_id, name FROM students ORDER BY student_id");

if (!$students_result) {
    echo "<p style='color: red;'>✗ Error fetching students: " . htmlspecialchars($conn->error) . "</p>";
    exit;
}

$total_students = $students_result->num_rows;
echo "<p>Found $total_students students to process...</p>";

$success_count = 0;
$error_count = 0;
$batch_size = 50; // Process in batches to avoid timeout
$current_batch = 0;

echo "<div style='max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>";

while ($student = $students_result->fetch_assoc()) {
    $current_batch++;
    
    $qr_result = generateStudentAttendanceQR($student['student_id'], $student['name'], $conn);
    
    if ($qr_result['success']) {
        $success_count++;
        echo "<p style='color: green; margin: 2px 0;'>✓ {$student['name']} ({$student['student_id']})</p>";
    } else {
        $error_count++;
        echo "<p style='color: red; margin: 2px 0;'>✗ {$student['name']} - {$qr_result['message']}</p>";
    }
    
    // Flush output every batch
    if ($current_batch % $batch_size == 0) {
        echo "<p style='color: blue; margin: 5px 0;'><strong>Processed $current_batch / $total_students students...</strong></p>";
        flush();
        ob_flush();
    }
}

echo "</div>";

echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h4>🎉 QR Code Generation Complete!</h4>";
echo "<p><strong>Results:</strong></p>";
echo "<ul>";
echo "<li>Total Students: $total_students</li>";
echo "<li>Successful: $success_count</li>";
echo "<li>Failed: $error_count</li>";
echo "</ul>";

if ($success_count > 0) {
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Students can now view their QR codes at: <a href='../student/attendance.php' target='_blank'>Student Attendance Page</a></li>";
    echo "<li>Coordinators can scan QR codes at: <a href='attendance_scanner.php' target='_blank'>QR Attendance Scanner</a></li>";
    echo "<li>Test the system by creating an attendance session and scanning a QR code</li>";
    echo "</ol>";
}
echo "</div>";

// Show final status
$final_check = $conn->query("SELECT COUNT(*) as with_qr FROM students WHERE attendance_qr_code IS NOT NULL AND attendance_qr_code != ''");
$students_with_qr = $final_check ? $final_check->fetch_assoc()['with_qr'] : 0;

echo "<p><strong>Final Status:</strong> $students_with_qr students now have QR codes</p>";

$conn->close();
?>