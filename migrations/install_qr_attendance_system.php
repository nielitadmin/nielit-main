<?php
/**
 * QR-Based Attendance System Installation
 * NIELIT Bhubaneswar - Database Setup
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/attendance_qr_helper.php';

echo "<h2>QR-Based Attendance System Installation</h2>\n";

try {
    // Read and execute SQL migration
    $sql_file = __DIR__ . '/add_attendance_qr_system.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("SQL migration file not found: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    $sql_statements = explode(';', $sql_content);
    
    echo "<h3>1. Creating Database Tables...</h3>\n";
    
    foreach ($sql_statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            if ($conn->query($statement)) {
                echo "<p style='color: green;'>✓ Executed: " . substr($statement, 0, 50) . "...</p>\n";
            } else {
                echo "<p style='color: orange;'>⚠ Warning: " . $conn->error . "</p>\n";
            }
        }
    }
    
    echo "<h3>2. Generating QR Codes for Existing Students...</h3>\n";
    
    // Generate QR codes for all active students
    $results = batchGenerateStudentQRCodes($conn);
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($results as $result) {
        if ($result['result']['success']) {
            $success_count++;
            echo "<p style='color: green;'>✓ Generated QR for: " . htmlspecialchars($result['student_name']) . " (" . $result['student_id'] . ")</p>\n";
        } else {
            $error_count++;
            echo "<p style='color: red;'>✗ Failed for: " . htmlspecialchars($result['student_name']) . " - " . $result['result']['message'] . "</p>\n";
        }
    }
    
    echo "<h3>3. Installation Summary</h3>\n";
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>QR-Based Attendance System Successfully Installed!</h4>\n";
    echo "<p><strong>QR Codes Generated:</strong> $success_count successful, $error_count failed</p>\n";
    echo "<p><strong>Features Added:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>✓ Student QR codes for attendance</li>\n";
    echo "<li>✓ Course coordinator scanner panel</li>\n";
    echo "<li>✓ Attendance sessions management</li>\n";
    echo "<li>✓ Real-time QR scanning</li>\n";
    echo "<li>✓ Scan logs and statistics</li>\n";
    echo "</ul>\n";
    
    echo "<h4>How to Use:</h4>\n";
    echo "<ol>\n";
    echo "<li><strong>Students:</strong> Go to Student Portal → Attendance to see your QR code</li>\n";
    echo "<li><strong>Coordinators:</strong> Go to Admin Panel → QR Attendance Scanner</li>\n";
    echo "<li><strong>Create Session:</strong> Coordinators create attendance sessions</li>\n";
    echo "<li><strong>Scan QR Codes:</strong> Use the web-based scanner to mark attendance</li>\n";
    echo "</ol>\n";
    
    echo "<h4>Access Links:</h4>\n";
    echo "<ul>\n";
    echo "<li><a href='../admin/attendance_scanner.php' target='_blank'>Course Coordinator Scanner Panel</a></li>\n";
    echo "<li><a href='../student/attendance.php' target='_blank'>Student Attendance Page (with QR code)</a></li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    // Create QR codes directory if it doesn't exist
    $qr_dir = __DIR__ . '/../assets/qr_codes/attendance/';
    if (!file_exists($qr_dir)) {
        mkdir($qr_dir, 0777, true);
        echo "<p style='color: green;'>✓ Created QR codes directory: $qr_dir</p>\n";
    }
    
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>🎉 Installation Complete!</h4>\n";
    echo "<p>Your QR-based attendance system is now ready to use. Students can show their QR codes to coordinators for quick attendance marking.</p>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>❌ Installation Error</h4>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

$conn->close();
?>