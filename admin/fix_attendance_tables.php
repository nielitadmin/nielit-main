<?php
/**
 * Fix Attendance Tables - Manual Creation
 * NIELIT Bhubaneswar - Database Fix
 */

session_start();
require_once __DIR__ . '/../config/config.php';

echo "<h2>Fixing Attendance System Tables</h2>\n";

try {
    // Create attendance_logs table
    $sql_logs = "
    CREATE TABLE IF NOT EXISTS attendance_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        scan_type ENUM('in', 'out') NOT NULL,
        scan_time DATETIME NOT NULL,
        coordinator_id VARCHAR(50) NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        duration_minutes INT NULL,
        status ENUM('valid', 'duplicate', 'too_early') DEFAULT 'valid',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session_student (session_id, student_id),
        INDEX idx_scan_time (scan_time),
        INDEX idx_student_date (student_id, scan_time)
    )";
    
    if ($conn->query($sql_logs)) {
        echo "<p style='color: green;'>✅ attendance_logs table created successfully</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ attendance_logs: " . $conn->error . "</p>\n";
    }
    
    // Create attendance_summary table
    $sql_summary = "
    CREATE TABLE IF NOT EXISTS attendance_summary (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        date DATE NOT NULL,
        time_in TIME NULL,
        time_out TIME NULL,
        total_duration_minutes INT DEFAULT 0,
        status ENUM('present', 'partial', 'absent') DEFAULT 'absent',
        coordinator_id VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_session_student_date (session_id, student_id, date),
        INDEX idx_date (date),
        INDEX idx_student_month (student_id, date)
    )";
    
    if ($conn->query($sql_summary)) {
        echo "<p style='color: green;'>✅ attendance_summary table created successfully</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ attendance_summary: " . $conn->error . "</p>\n";
    }
    
    // Update attendance_sessions table
    $sql_update_sessions = "
    ALTER TABLE attendance_sessions 
    ADD COLUMN IF NOT EXISTS session_type ENUM('regular', 'in_out') DEFAULT 'in_out',
    ADD COLUMN IF NOT EXISTS min_duration_minutes INT DEFAULT 1,
    ADD COLUMN IF NOT EXISTS auto_out_hours INT DEFAULT 8
    ";
    
    if ($conn->query($sql_update_sessions)) {
        echo "<p style='color: green;'>✅ attendance_sessions table updated successfully</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ attendance_sessions update: " . $conn->error . "</p>\n";
    }
    
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>🎉 Database Tables Fixed!</h4>\n";
    echo "<p>The enhanced attendance system database tables have been created successfully.</p>\n";
    echo "<p><strong>Next Steps:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>✅ Test the QR scanner at <a href='attendance_scanner.php'>attendance_scanner.php</a></li>\n";
    echo "<li>✅ Create attendance sessions and scan QR codes</li>\n";
    echo "<li>✅ View student lists and monthly reports</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>❌ Fix Error</h4>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

$conn->close();
?>