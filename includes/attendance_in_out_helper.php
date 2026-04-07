<?php
/**
 * Enhanced Attendance System with IN/OUT Tracking
 * NIELIT Bhubaneswar - Advanced QR Attendance Management
 */

/**
 * Process IN/OUT QR scan with time validation
 */
function processInOutAttendanceQRScan($qr_data, $session_id, $coordinator_id, $conn) {
    try {
        // Decode QR data
        $attendance_data = json_decode($qr_data, true);
        
        if (!$attendance_data || $attendance_data['type'] !== 'student_attendance') {
            return [
                'success' => false,
                'result' => 'invalid',
                'message' => 'Invalid QR code format'
            ];
        }

        $student_id = $attendance_data['student_id'];
        $student_name = $attendance_data['student_name'];
        $current_time = new DateTime();

        // Get session details
        $session_stmt = $conn->prepare("SELECT * FROM attendance_sessions WHERE id = ? AND status = 'active'");
        $session_stmt->bind_param("i", $session_id);
        $session_stmt->execute();
        $session = $session_stmt->get_result()->fetch_assoc();

        if (!$session) {
            return [
                'success' => false,
                'result' => 'expired',
                'message' => 'Session not active or expired'
            ];
        }

        // Get last scan for this student in this session
        $last_scan_stmt = $conn->prepare("
            SELECT * FROM attendance_logs 
            WHERE session_id = ? AND student_id = ? AND DATE(scan_time) = ? 
            ORDER BY scan_time DESC LIMIT 1
        ");
        $last_scan_stmt->bind_param("iss", $session_id, $student_id, $session['date']);
        $last_scan_stmt->execute();
        $last_scan = $last_scan_stmt->get_result()->fetch_assoc();

        // Determine scan type (IN or OUT)
        $scan_type = 'in';
        $duration_minutes = null;
        $status = 'valid';

        if ($last_scan) {
            $last_scan_time = new DateTime($last_scan['scan_time']);
            $time_diff = $current_time->getTimestamp() - $last_scan_time->getTimestamp();
            $minutes_diff = floor($time_diff / 60);

            // Check minimum duration between scans
            $min_duration = $session['min_duration_minutes'] ?? 1;
            
            if ($minutes_diff < $min_duration) {
                // Too early to scan again
                logAttendanceScan($session_id, $student_id, $student_name, $scan_type, $coordinator_id, $conn, 'too_early');
                
                return [
                    'success' => false,
                    'result' => 'too_early',
                    'message' => "Please wait at least {$min_duration} minute(s) before scanning again",
                    'student_name' => $student_name,
                    'last_scan_type' => $last_scan['scan_type'],
                    'minutes_remaining' => $min_duration - $minutes_diff
                ];
            }

            // Determine next scan type
            if ($last_scan['scan_type'] === 'in') {
                $scan_type = 'out';
                $duration_minutes = $minutes_diff;
            } else {
                $scan_type = 'in';
            }
        }

        // Log the scan
        $log_id = logAttendanceScan($session_id, $student_id, $student_name, $scan_type, $coordinator_id, $conn, $status, $duration_minutes);

        // Update attendance summary
        updateAttendanceSummary($session_id, $student_id, $student_name, $session['date'], $coordinator_id, $conn);

        return [
            'success' => true,
            'result' => 'success',
            'student_id' => $student_id,
            'student_name' => $student_name,
            'scan_type' => $scan_type,
            'scan_time' => $current_time->format('H:i:s'),
            'duration_minutes' => $duration_minutes,
            'message' => ucfirst($scan_type) . ' scan recorded successfully' . 
                        ($duration_minutes ? " (Duration: {$duration_minutes} minutes)" : '')
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'result' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Log attendance scan to attendance_logs table
 */
function logAttendanceScan($session_id, $student_id, $student_name, $scan_type, $coordinator_id, $conn, $status = 'valid', $duration_minutes = null) {
    $stmt = $conn->prepare("
        INSERT INTO attendance_logs 
        (session_id, student_id, student_name, scan_type, scan_time, coordinator_id, ip_address, user_agent, duration_minutes, status) 
        VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)
    ");
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt->bind_param("issssssis", 
        $session_id, $student_id, $student_name, $scan_type, 
        $coordinator_id, $ip_address, $user_agent, $duration_minutes, $status
    );
    
    $stmt->execute();
    return $conn->insert_id;
}

/**
 * Update attendance summary table
 */
function updateAttendanceSummary($session_id, $student_id, $student_name, $date, $coordinator_id, $conn) {
    // Get all scans for this student on this date
    $scans_stmt = $conn->prepare("
        SELECT scan_type, scan_time, duration_minutes 
        FROM attendance_logs 
        WHERE session_id = ? AND student_id = ? AND DATE(scan_time) = ? AND status = 'valid'
        ORDER BY scan_time ASC
    ");
    $scans_stmt->bind_param("iss", $session_id, $student_id, $date);
    $scans_stmt->execute();
    $scans = $scans_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($scans)) return;

    // Calculate time in, time out, and total duration
    $time_in = null;
    $time_out = null;
    $total_duration = 0;
    $status = 'absent';

    foreach ($scans as $scan) {
        if ($scan['scan_type'] === 'in' && !$time_in) {
            $time_in = date('H:i:s', strtotime($scan['scan_time']));
        }
        if ($scan['scan_type'] === 'out') {
            $time_out = date('H:i:s', strtotime($scan['scan_time']));
            if ($scan['duration_minutes']) {
                $total_duration += $scan['duration_minutes'];
            }
        }
    }

    // Determine status
    if ($time_in && $time_out) {
        $status = 'present';
    } elseif ($time_in) {
        $status = 'partial';
    }

    // Insert or update summary
    $summary_stmt = $conn->prepare("
        INSERT INTO attendance_summary 
        (session_id, student_id, student_name, date, time_in, time_out, total_duration_minutes, status, coordinator_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        time_in = VALUES(time_in),
        time_out = VALUES(time_out),
        total_duration_minutes = VALUES(total_duration_minutes),
        status = VALUES(status),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $summary_stmt->bind_param("isssssiss", 
        $session_id, $student_id, $student_name, $date, 
        $time_in, $time_out, $total_duration, $status, $coordinator_id
    );
    
    $summary_stmt->execute();
}

/**
 * Get session attendance list with IN/OUT details
 */
function getSessionAttendanceList($session_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            GROUP_CONCAT(
                CONCAT(l.scan_type, ':', TIME(l.scan_time)) 
                ORDER BY l.scan_time ASC 
                SEPARATOR '|'
            ) as scan_history,
            COUNT(l.id) as total_scans
        FROM attendance_summary s
        LEFT JOIN attendance_logs l ON s.session_id = l.session_id 
            AND s.student_id = l.student_id 
            AND DATE(l.scan_time) = s.date
            AND l.status = 'valid'
        WHERE s.session_id = ?
        GROUP BY s.id
        ORDER BY s.student_name ASC
    ");
    
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get monthly attendance report
 */
function getMonthlyAttendanceReport($student_id = null, $year = null, $month = null, $conn) {
    $year = $year ?? date('Y');
    $month = $month ?? date('n');
    
    $where_clause = "WHERE YEAR(date) = ? AND MONTH(date) = ?";
    $params = [$year, $month];
    $types = "ii";
    
    if ($student_id) {
        $where_clause .= " AND student_id = ?";
        $params[] = $student_id;
        $types .= "s";
    }
    
    $stmt = $conn->prepare("
        SELECT 
            student_id,
            student_name,
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(total_duration_minutes) as total_minutes,
            ROUND(SUM(total_duration_minutes) / 60, 2) as total_hours,
            ROUND(
                (SUM(CASE WHEN status IN ('present', 'partial') THEN 1 ELSE 0 END) / COUNT(*)) * 100, 
                2
            ) as attendance_percentage
        FROM attendance_summary 
        {$where_clause}
        GROUP BY student_id, student_name
        ORDER BY student_name ASC
    ");
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get attendance statistics for dashboard
 */
function getAttendanceStatistics($session_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT student_id) as total_students,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            AVG(total_duration_minutes) as avg_duration_minutes
        FROM attendance_summary 
        WHERE session_id = ?
    ");
    
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}
?>