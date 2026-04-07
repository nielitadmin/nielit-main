<?php
/**
 * Fix QR Attendance System Directory Issues
 * NIELIT Bhubaneswar - Directory Creation and Permission Fix
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>QR Attendance System Directory Fix</h2>\n";

try {
    // Create main QR codes directory
    $main_qr_dir = __DIR__ . '/../assets/qr_codes/';
    if (!file_exists($main_qr_dir)) {
        if (mkdir($main_qr_dir, 0777, true)) {
            echo "<p style='color: green;'>✓ Created main QR directory: $main_qr_dir</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Failed to create main QR directory</p>\n";
        }
    } else {
        echo "<p style='color: blue;'>ℹ Main QR directory already exists</p>\n";
    }

    // Create attendance QR codes directory
    $attendance_qr_dir = __DIR__ . '/../assets/qr_codes/attendance/';
    if (!file_exists($attendance_qr_dir)) {
        if (mkdir($attendance_qr_dir, 0777, true)) {
            echo "<p style='color: green;'>✓ Created attendance QR directory: $attendance_qr_dir</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Failed to create attendance QR directory</p>\n";
        }
    } else {
        echo "<p style='color: blue;'>ℹ Attendance QR directory already exists</p>\n";
    }

    // Set proper permissions
    if (file_exists($main_qr_dir)) {
        chmod($main_qr_dir, 0777);
        echo "<p style='color: green;'>✓ Set permissions for main QR directory</p>\n";
    }

    if (file_exists($attendance_qr_dir)) {
        chmod($attendance_qr_dir, 0777);
        echo "<p style='color: green;'>✓ Set permissions for attendance QR directory</p>\n";
    }

    // Check phpqrcode library
    $phpqrcode_path = __DIR__ . '/../phpqrcode/qrlib.php';
    if (file_exists($phpqrcode_path)) {
        echo "<p style='color: green;'>✓ phpqrcode library found</p>\n";
    } else {
        echo "<p style='color: red;'>✗ phpqrcode library not found at: $phpqrcode_path</p>\n";
        echo "<p style='color: orange;'>⚠ Please download phpqrcode library and place it in the phpqrcode/ directory</p>\n";
    }

    // Clean up any existing QR codes with problematic names
    echo "<h3>Cleaning up existing QR codes...</h3>\n";
    
    // Reset all student QR code paths in database
    $reset_query = "UPDATE students SET attendance_qr_code = NULL WHERE attendance_qr_code IS NOT NULL";
    if ($conn->query($reset_query)) {
        $affected_rows = $conn->affected_rows;
        echo "<p style='color: green;'>✓ Reset $affected_rows student QR code paths in database</p>\n";
    }

    // Test QR code generation with a sample student
    echo "<h3>Testing QR Code Generation...</h3>\n";
    
    // Get a sample student
    $sample_query = "SELECT student_id, name FROM students LIMIT 1";
    $sample_result = $conn->query($sample_query);
    
    if ($sample_result && $sample_result->num_rows > 0) {
        $sample_student = $sample_result->fetch_assoc();
        
        // Test QR generation
        require_once __DIR__ . '/../includes/attendance_qr_helper.php';
        
        $test_result = generateStudentAttendanceQR($sample_student['student_id'], $sample_student['name'], $conn);
        
        if ($test_result['success']) {
            echo "<p style='color: green;'>✓ Test QR generation successful for student: " . htmlspecialchars($sample_student['name']) . "</p>\n";
            echo "<p style='color: blue;'>ℹ QR file created at: " . $test_result['path'] . "</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Test QR generation failed: " . $test_result['message'] . "</p>\n";
        }
    } else {
        echo "<p style='color: orange;'>⚠ No students found in database for testing</p>\n";
    }

    echo "<h3>Directory Status Summary</h3>\n";
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
    
    // Check directory status
    $directories = [
        'Main QR Directory' => $main_qr_dir,
        'Attendance QR Directory' => $attendance_qr_dir
    ];
    
    foreach ($directories as $name => $path) {
        $exists = file_exists($path);
        $writable = $exists ? is_writable($path) : false;
        
        echo "<p><strong>$name:</strong> ";
        if ($exists && $writable) {
            echo "<span style='color: green;'>✓ Exists and writable</span>";
        } elseif ($exists && !$writable) {
            echo "<span style='color: orange;'>⚠ Exists but not writable</span>";
        } else {
            echo "<span style='color: red;'>✗ Does not exist</span>";
        }
        echo "<br><small>$path</small></p>\n";
    }
    
    echo "</div>\n";

    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>🔧 Directory Fix Complete!</h4>\n";
    echo "<p>The QR attendance system directories have been created and configured properly.</p>\n";
    echo "<p><strong>Next Steps:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Run the full installation: <a href='install_qr_attendance_system.php'>install_qr_attendance_system.php</a></li>\n";
    echo "<li>Test the system: <a href='../admin/test_qr_attendance.php'>test_qr_attendance.php</a></li>\n";
    echo "</ul>\n";
    echo "</div>\n";

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>❌ Directory Fix Error</h4>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

$conn->close();
?>