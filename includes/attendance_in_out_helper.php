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
        if (!$session_stmt) {
            return [
                'success' => false,
                'result' => 'error',
                'message' => 'Database error (session lookup): ' . $conn->error
            ];
        }
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

        // Verify student exists and is enrolled in the session's course
        $student_check = $conn->prepare("SELECT course_id, status FROM students WHERE student_id = ? LIMIT 1");
        if (!$student_check) {
            return [
                'success' => false,
                'result' => 'error',
                'message' => 'Database error (student lookup): ' . $conn->error
            ];
        }
        $student_check->bind_param("s", $student_id);
        $student_check->execute();
        $student_row = $student_check->get_result()->fetch_assoc();

        if (!$student_row) {
            return [
                'success' => false,
                'result' => 'unknown_student',
                'message' => 'Student not found in system'
            ];
        }

        // Ensure student is enrolled in the session's course
        $student_course_id = (int)$student_row['course_id'];
        $session_course_id = (int)$session['course_id'];

        if ($student_course_id !== $session_course_id) {
            return [
                'success' => false,
                'result' => 'not_enrolled',
                'message' => 'Student is not enrolled in this course',
                'student_id' => $student_id
            ];
        }

        // Optionally ensure student status is active
        $student_status = $student_row['status'] ?? '';
        if (!empty($student_status) && $student_status !== 'active') {
            return [
                'success' => false,
                'result' => 'invalid_status',
                'message' => 'Student status is not active',
                'status' => $student_status
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
function getMonthlyAttendanceReport($student_id = null, $year = null, $month = null, $course_id = null, $conn = null) {
    $year = $year ?? date('Y');
    $month = $month ?? date('n');
    if ($conn === null) {
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn === null) return [];
    }
    
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
            'N/A' as course_name,
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
    
        if (!$stmt) {
            error_log("Prepare failed in getMonthlyAttendanceReport: " . $conn->error);
            return [];
        }
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get weekly attendance report
 */
function getWeeklyAttendanceReport($student_id = null, $year = null, $week = null, $course_id = null, $conn = null) {
    $year = $year ?? date('Y');
    $week = $week ?? date('W');
    if ($conn === null) {
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn === null) return [];
    }
    
    $where_clause = "WHERE YEAR(date) = ? AND WEEK(date, 1) = ?";
    $params = [$year, $week];
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
            'N/A' as course_name,
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
    
    if (!$stmt) {
        error_log("Prepare failed in getWeeklyAttendanceReport: " . $conn->error);
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get quarterly attendance report
 */
function getQuarterlyAttendanceReport($student_id = null, $year = null, $quarter = null, $course_id = null, $conn = null) {
    $year = $year ?? date('Y');
    $quarter = $quarter ?? ceil(date('n') / 3);
    if ($conn === null) {
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn === null) return [];
    }
    
    // Calculate quarter months
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $quarter * 3;
    
    $where_clause = "WHERE YEAR(date) = ? AND MONTH(date) BETWEEN ? AND ?";
    $params = [$year, $start_month, $end_month];
    $types = "iii";
    
    if ($student_id) {
        $where_clause .= " AND student_id = ?";
        $params[] = $student_id;
        $types .= "s";
    }
    
    $stmt = $conn->prepare("
        SELECT 
            student_id,
            student_name,
            'N/A' as course_name,
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
    
    if (!$stmt) {
        error_log("Prepare failed in getQuarterlyAttendanceReport: " . $conn->error);
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get yearly attendance report
 */
function getYearlyAttendanceReport($student_id = null, $year = null, $course_id = null, $conn = null) {
    $year = $year ?? date('Y');
    if ($conn === null) {
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn === null) return [];
    }
    
    $where_clause = "WHERE YEAR(date) = ?";
    $params = [$year];
    $types = "i";
    
    if ($student_id) {
        $where_clause .= " AND student_id = ?";
        $params[] = $student_id;
        $types .= "s";
    }
    
    $stmt = $conn->prepare("
        SELECT 
            student_id,
            student_name,
            'N/A' as course_name,
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
    
    if (!$stmt) {
        error_log("Prepare failed in getYearlyAttendanceReport: " . $conn->error);
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get custom date range attendance report
 */
function getCustomRangeAttendanceReport($student_id = null, $start_date = null, $end_date = null, $course_id = null, $conn = null) {
    if (!$start_date || !$end_date) {
        return [];
    }
    if ($conn === null) {
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn === null) return [];
    }
    
    $where_clause = "WHERE date BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    $types = "ss";
    
    if ($student_id) {
        $where_clause .= " AND student_id = ?";
        $params[] = $student_id;
        $types .= "s";
    }
    
    $stmt = $conn->prepare("
        SELECT 
            student_id,
            student_name,
            'N/A' as course_name,
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
    
    if (!$stmt) {
        error_log("Prepare failed in getCustomRangeAttendanceReport: " . $conn->error);
        return [];
    }
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
    
    if (!$stmt) {
        error_log("Prepare failed in getAttendanceStatistics: " . $conn->error);
        return [
            'total_students' => 0,
            'present_count' => 0,
            'partial_count' => 0,
            'absent_count' => 0,
            'avg_duration_minutes' => 0
        ];
    }
    $stmt->bind_param("i", $session_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}
?>