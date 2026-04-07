<?php
/**
 * Export Attendance Data to Excel
 * NIELIT Bhubaneswar - Excel Export for Attendance
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/attendance_in_out_helper.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$session_id = $_GET['session_id'] ?? 0;

if (!$session_id) {
    die('Session ID is required');
}

try {
    // Get session details
    $session_stmt = $conn->prepare("SELECT * FROM attendance_sessions WHERE id = ?");
    $session_stmt->bind_param("i", $session_id);
    $session_stmt->execute();
    $session = $session_stmt->get_result()->fetch_assoc();

    if (!$session) {
        die('Session not found');
    }

    // Get attendance list
    $attendance_list = getSessionAttendanceList($session_id, $conn);
    $stats = getAttendanceStatistics($session_id, $conn);

    // Create Excel content using HTML table format (Excel can read this)
    $filename = "attendance_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $session['session_name']) . "_" . $session['date'] . ".xls";
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Start Excel HTML content
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Session information header
    echo '<tr><td colspan="9" style="font-weight:bold; font-size:16px; text-align:center;">NIELIT Bhubaneswar - Attendance Report</td></tr>';
    echo '<tr><td colspan="9"></td></tr>'; // Empty row
    echo '<tr><td style="font-weight:bold;">Session Name:</td><td colspan="8">' . htmlspecialchars($session['session_name']) . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Course:</td><td colspan="8">' . htmlspecialchars($session['course_name']) . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Subject:</td><td colspan="8">' . htmlspecialchars($session['subject']) . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Date:</td><td colspan="8">' . date('d M Y', strtotime($session['date'])) . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Time:</td><td colspan="8">' . date('h:i A', strtotime($session['start_time'])) . ' - ' . date('h:i A', strtotime($session['end_time'])) . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Coordinator:</td><td colspan="8">' . htmlspecialchars($session['coordinator_name']) . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Generated:</td><td colspan="8">' . date('d M Y h:i A') . '</td></tr>';
    echo '<tr><td colspan="9"></td></tr>'; // Empty row

    // Column headers
    echo '<tr style="background-color:#f0f0f0; font-weight:bold;">';
    echo '<td>Student Name</td>';
    echo '<td>Student ID</td>';
    echo '<td>Time In</td>';
    echo '<td>Time Out</td>';
    echo '<td>Duration (Minutes)</td>';
    echo '<td>Duration (Hours)</td>';
    echo '<td>Status</td>';
    echo '<td>Total Scans</td>';
    echo '<td>Scan History</td>';
    echo '</tr>';

    // Attendance data
    if (!empty($attendance_list)) {
        foreach ($attendance_list as $record) {
            // Calculate duration in hours
            $duration_hours = $record['total_duration_minutes'] > 0 
                ? round($record['total_duration_minutes'] / 60, 2) 
                : 0;

            // Format scan history
            $scan_history = '';
            if ($record['scan_history']) {
                $scans = explode('|', $record['scan_history']);
                $formatted_scans = [];
                foreach ($scans as $scan) {
                    $parts = explode(':', $scan);
                    if (count($parts) == 2) {
                        $formatted_scans[] = strtoupper($parts[0]) . ' ' . $parts[1];
                    }
                }
                $scan_history = implode(', ', $formatted_scans);
            }

            echo '<tr>';
            echo '<td>' . htmlspecialchars($record['student_name']) . '</td>';
            echo '<td>' . htmlspecialchars($record['student_id']) . '</td>';
            echo '<td>' . ($record['time_in'] ?: '-') . '</td>';
            echo '<td>' . ($record['time_out'] ?: '-') . '</td>';
            echo '<td>' . ($record['total_duration_minutes'] ?: 0) . '</td>';
            echo '<td>' . $duration_hours . '</td>';
            echo '<td>' . strtoupper($record['status']) . '</td>';
            echo '<td>' . ($record['total_scans'] ?: 0) . '</td>';
            echo '<td>' . htmlspecialchars($scan_history) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="9" style="text-align:center;">No attendance records found</td></tr>';
    }

    // Summary section
    echo '<tr><td colspan="9"></td></tr>'; // Empty row
    echo '<tr><td colspan="9" style="font-weight:bold; background-color:#e0e0e0;">SUMMARY</td></tr>';
    
    $stats = getAttendanceStatistics($session_id, $conn);
    echo '<tr><td style="font-weight:bold;">Total Students:</td><td colspan="8">' . ($stats['total_students'] ?? 0) . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Present:</td><td colspan="8">' . ($stats['present_count'] ?? 0) . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Partial:</td><td colspan="8">' . ($stats['partial_count'] ?? 0) . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Absent:</td><td colspan="8">' . ($stats['absent_count'] ?? 0) . '</td></tr>';
    
    if ($stats['avg_duration_minutes']) {
        $avg_hours = round($stats['avg_duration_minutes'] / 60, 2);
        echo '<tr><td style="font-weight:bold;">Average Duration:</td><td colspan="8">' . $avg_hours . ' hours</td></tr>';
    }

    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;

} catch (Exception $e) {
    die('Export error: ' . $e->getMessage());
}
?>