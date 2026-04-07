<?php
/**
 * Enhanced Attendance System Installation
 * NIELIT Bhubaneswar - IN/OUT Tracking & Reports
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Enhanced Attendance System Installation</h2>\n";

try {
    // Read and execute SQL migration
    $sql_file = __DIR__ . '/add_in_out_attendance_system.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("SQL migration file not found: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    $sql_statements = explode(';', $sql_content);
    
    echo "<h3>1. Creating Enhanced Database Tables...</h3>\n";
    
    foreach ($sql_statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            if ($conn->query($statement)) {
                echo "<p style='color: green;'>✓ Executed: " . substr($statement, 0, 80) . "...</p>\n";
            } else {
                echo "<p style='color: orange;'>⚠ Warning: " . $conn->error . "</p>\n";
            }
        }
    }
    
    echo "<h3>2. System Enhancement Summary</h3>\n";
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>🎉 Enhanced Attendance System Installed!</h4>\n";
    echo "<p><strong>New Features Added:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>✅ <strong>IN/OUT Tracking:</strong> Students scan when entering and leaving</li>\n";
    echo "<li>✅ <strong>Time Duration Calculation:</strong> Automatic duration tracking</li>\n";
    echo "<li>✅ <strong>Minimum Time Validation:</strong> Prevents rapid duplicate scans</li>\n";
    echo "<li>✅ <strong>Student List Display:</strong> View attendance list after scanning</li>\n";
    echo "<li>✅ <strong>Monthly Reports:</strong> Comprehensive attendance analytics</li>\n";
    echo "<li>✅ <strong>Attendance Statistics:</strong> Present, Partial, Absent tracking</li>\n";
    echo "<li>✅ <strong>Export Functionality:</strong> Excel and print reports</li>\n";
    echo "</ul>\n";
    
    echo "<h4>📊 Database Tables Created:</h4>\n";
    echo "<ul>\n";
    echo "<li><strong>attendance_logs:</strong> Detailed IN/OUT scan tracking</li>\n";
    echo "<li><strong>attendance_summary:</strong> Daily attendance summaries</li>\n";
    echo "<li><strong>monthly_attendance_reports:</strong> Monthly analytics</li>\n";
    echo "</ul>\n";
    
    echo "<h4>🔧 Enhanced Features:</h4>\n";
    echo "<ul>\n";
    echo "<li><strong>Smart Scanning:</strong> Alternates between IN and OUT automatically</li>\n";
    echo "<li><strong>Duration Tracking:</strong> Calculates time spent in sessions</li>\n";
    echo "<li><strong>Status Classification:</strong> Present (IN+OUT), Partial (IN only), Absent</li>\n";
    echo "<li><strong>Time Validation:</strong> Minimum 1-minute gap between scans</li>\n";
    echo "<li><strong>Real-time Updates:</strong> Live attendance statistics</li>\n";
    echo "</ul>\n";
    
    echo "<h4>📱 How to Use:</h4>\n";
    echo "<ol>\n";
    echo "<li><strong>Create Session:</strong> Coordinator creates attendance session</li>\n";
    echo "<li><strong>Students Scan IN:</strong> First scan marks entry time</li>\n";
    echo "<li><strong>Students Scan OUT:</strong> Second scan marks exit time and calculates duration</li>\n";
    echo "<li><strong>View Student List:</strong> Click 'View Student List' to see attendance</li>\n";
    echo "<li><strong>Monthly Reports:</strong> Access comprehensive reports via admin panel</li>\n";
    echo "</ol>\n";
    
    echo "<h4>🎯 Access Points:</h4>\n";
    echo "<ul>\n";
    echo "<li><a href='../admin/attendance_scanner.php' target='_blank'>Enhanced QR Scanner</a></li>\n";
    echo "<li><a href='../admin/attendance_reports.php' target='_blank'>Monthly Reports</a></li>\n";
    echo "<li><a href='../student/attendance.php' target='_blank'>Student QR Codes</a></li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    echo "<div style='background: #cce5ff; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>📋 Next Steps:</h4>\n";
    echo "<ol>\n";
    echo "<li>Test the enhanced QR scanner with IN/OUT functionality</li>\n";
    echo "<li>Create attendance sessions and scan student QR codes</li>\n";
    echo "<li>View student lists to see attendance details</li>\n";
    echo "<li>Generate monthly reports for analytics</li>\n";
    echo "<li>Export reports to Excel for external use</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h4>❌ Installation Error</h4>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

$conn->close();
?>