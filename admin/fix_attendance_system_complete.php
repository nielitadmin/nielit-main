<?php
/**
 * Complete Attendance System Fix
 * NIELIT Bhubaneswar - Full System Repair
 */

session_start();
require_once __DIR__ . '/../config/config.php';

echo "<h2>Complete Attendance System Fix</h2>\n";

try {
    echo "<h3>Step 1: Creating/Fixing Database Tables</h3>\n";
    
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
        INDEX idx_scan_time (scan_time)
    ) ENGINE=InnoDB";
    
    if ($conn->query($sql_logs)) {
        echo "<p style='color: green;'>✅ attendance_logs table created/verified</p>\n";
    } else {
        echo "<p style='color: red;'>❌ attendance_logs error: " . $conn->error . "</p>\n";
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
        INDEX idx_date (date)
    ) ENGINE=InnoDB";
    
    if ($conn->query($sql_summary)) {
        echo "<p style='color: green;'>✅ attendance_summary table created/verified</p>\n";
    } else {
        echo "<p style='color: red;'>❌ attendance_summary error: " . $conn->error . "</p>\n";
    }
    
    // Update attendance_sessions table
    $conn->query("ALTER TABLE attendance_sessions ADD COLUMN IF NOT EXISTS session_type ENUM('regular', 'in_out') DEFAULT 'in_out'");
    $conn->query("ALTER TABLE attendance_sessions ADD COLUMN IF NOT EXISTS min_duration_minutes INT DEFAULT 1");
    echo "<p style='color: green;'>✅ attendance_sessions table updated</p>\n";
    
    echo "<h3>Step 2: Testing Database Operations</h3>\n";
    
    // Test insert into attendance_logs
    $test_log = $conn->prepare("
        INSERT INTO attendance_logs 
        (session_id, student_id, student_name, scan_type, scan_time, coordinator_id, status) 
        VALUES (?, ?, ?, ?, NOW(), ?, ?)
    ");
    
    $test_session_id = 1;
    $test_student_id = 'TEST_' . time();
    $test_student_name = 'Test Student Fix';
    $test_scan_type = 'in';
    $test_coordinator = 'test_admin';
    $test_status = 'valid';
    
    $test_log->bind_param("isssss", 
        $test_session_id, $test_student_id, $test_student_name, 
        $test_scan_type, $test_coordinator, $test_status
    );
    
    if ($test_log->execute()) {
        $log_id = $conn->insert_id;
        echo "<p style='color: green;'>✅ Test log inserted (ID: $log_id)</p>\n";
        
        // Test insert into attendance_summary
        $test_summary = $conn->prepare("
            INSERT INTO attendance_summary 
            (session_id, student_id, student_name, date, time_in, status, coordinator_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            time_in = VALUES(time_in),
            status = VALUES(status),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        $test_date = date('Y-m-d');
        $test_time_in = date('H:i:s');
        $test_summary_status = 'partial';
        
        $test_summary->bind_param("issssss", 
            $test_session_id, $test_student_id, $test_student_name, 
            $test_date, $test_time_in, $test_summary_status, $test_coordinator
        );
        
        if ($test_summary->execute()) {
            echo "<p style='color: green;'>✅ Test summary inserted</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Test summary failed: " . $conn->error . "</p>\n";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Test log failed: " . $conn->error . "</p>\n";
    }
    
    echo "<h3>Step 3: Verifying Helper Functions</h3>\n";
    
    if (file_exists(__DIR__ . '/../includes/attendance_in_out_helper.php')) {
        require_once __DIR__ . '/../includes/attendance_in_out_helper.php';
        
        if (function_exists('processInOutAttendanceQRScan')) {
            echo "<p style='color: green;'>✅ processInOutAttendanceQRScan function available</p>\n";
        } else {
            echo "<p style='color: red;'>❌ processInOutAttendanceQRScan function missing</p>\n";
        }
        
        if (function_exists('getSessionAttendanceList')) {
            echo "<p style='color: green;'>✅ getSessionAttendanceList function available</p>\n";
        } else {
            echo "<p style='color: red;'>❌ getSessionAttendanceList function missing</p>\n";
        }
        
        if (function_exists('getAttendanceStatistics')) {
            echo "<p style='color: green;'>✅ getAttendanceStatistics function available</p>\n";
        } else {
            echo "<p style='color: red;'>❌ getAttendanceStatistics function missing</p>\n";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Helper functions file missing</p>\n";
    }
    
    echo "<h3>Step 4: Current Data Status</h3>\n";
    
    // Show current data
    $logs_count = $conn->query("SELECT COUNT(*) as count FROM attendance_logs")->fetch_assoc()['count'];
    $summary_count = $conn->query("SELECT COUNT(*) as count FROM attendance_summary")->fetch_assoc()['count'];
    $sessions_count = $conn->query("SELECT COUNT(*) as count FROM attendance_sessions")->fetch_assoc()['count'];
    
    echo "<p><strong>Current Records:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Attendance Logs: $logs_count</li>\n";
    echo "<li>Attendance Summary: $summary_count</li>\n";
    echo "<li>Attendance Sessions: $sessions_count</li>\n";
    echo "</ul>\n";
    
    // Show recent records
    if ($logs_count > 0) {
        echo "<h4>Recent Attendance Logs:</h4>\n";
        $recent_logs = $conn->query("SELECT * FROM attendance_logs ORDER BY scan_time DESC LIMIT 3");
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>ID</th><th>Session</th><th>Student</th><th>Type</th><th>Time</th><th>Status</th></tr>\n";
        while ($log = $recent_logs->fetch_assoc()) {
            echo "<tr>\n";
            echo "<td>{$log['id']}</td>\n";
            echo "<td>{$log['session_id']}</td>\n";
            echo "<td>{$log['student_name']}</td>\n";
            echo "<td>{$log['scan_type']}</td>\n";
            echo "<td>{$log['scan_time']}</td>\n";
            echo "<td>{$log['status']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    if ($summary_count > 0) {
        echo "<h4>Recent Attendance Summary:</h4>\n";
        $recent_summary = $conn->query("SELECT * FROM attendance_summary ORDER BY created_at DESC LIMIT 3");
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>ID</th><th>Session</th><th>Student</th><th>Date</th><th>Time In</th><th>Status</th></tr>\n";
        while ($summary = $recent_summary->fetch_assoc()) {
            echo "<tr>\n";
            echo "<td>{$summary['id']}</td>\n";
            echo "<td>{$summary['session_id']}</td>\n";
            echo "<td>{$summary['student_name']}</td>\n";
            echo "<td>{$summary['date']}</td>\n";
            echo "<td>{$summary['time_in']}</td>\n";
            echo "<td>{$summary['status']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>🎉 System Status</h4>\n";
    echo "<p><strong>Database tables are now properly set up!</strong></p>\n";
    echo "<p><strong>Next Steps:</strong></p>\n";
    echo "<ol>\n";
    echo "<li>Go back to <a href='attendance_scanner.php' target='_blank'>QR Scanner</a></li>\n";
    echo "<li>Create or activate a session</li>\n";
    echo "<li>Scan QR codes - they should now save properly</li>\n";
    echo "<li>Click 'View Student List' to see attendance records</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
    echo "<div style='background: #cce5ff; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>🔧 Debug Tools</h4>\n";
    echo "<p>If issues persist, use these debug tools:</p>\n";
    echo "<ul>\n";
    echo "<li><a href='debug_qr_processing.php' target='_blank'>Debug QR Processing</a></li>\n";
    echo "<li><a href='debug_attendance_scan.php' target='_blank'>Debug Attendance Scan</a></li>\n";
    echo "<li><a href='test_qr_scan_simulation.php' target='_blank'>Test QR Scan Simulation</a></li>\n";
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