<?php
/**
 * Attendance QR Code Helper Functions
 * NIELIT Bhubaneswar - QR-Based Attendance System
 */

require_once __DIR__ . '/../phpqrcode/qrlib.php';
$attendanceAccessHelper = __DIR__ . '/attendance_access_helper.php';
if (is_file($attendanceAccessHelper)) {
    require_once $attendanceAccessHelper;
}

/**
 * Generate unique QR code for student attendance
 * 
 * @param string $student_id - Student ID
 * @param string $student_name - Student name
 * @param mysqli $conn - Database connection
 * @return array - Result array with success status and QR path
 */
function generateStudentAttendanceQR($student_id, $student_name, $conn) {
    try {
        // Create safe filename by replacing special characters
        $safe_student_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $student_id);
        $filename = 'student_qr_' . $safe_student_id . '.png';
        
        // Create QR codes directory if it doesn't exist
        $qr_dir = __DIR__ . '/../assets/qr_codes/attendance/';
        if (!file_exists($qr_dir)) {
            if (!mkdir($qr_dir, 0777, true)) {
                return [
                    'success' => false,
                    'path' => '',
                    'message' => 'Failed to create QR codes directory'
                ];
            }
        }

        // Check if QR code file already exists and is valid
        $qr_file_path = $qr_dir . $filename;
        $relative_path = 'assets/qr_codes/attendance/' . $filename;
        
        if (file_exists($qr_file_path) && filesize($qr_file_path) > 0) {
            // File exists and is not empty, update database record and return existing path
            $stmt = $conn->prepare("UPDATE students SET attendance_qr_code = ? WHERE student_id = ?");
            if ($stmt) {
                $stmt->bind_param("ss", $relative_path, $student_id);
                $stmt->execute();
                $stmt->close();
            }
            
            return [
                'success' => true,
                'path' => $relative_path,
                'full_path' => $qr_file_path,
                'message' => 'Using existing QR code file',
                'file_existed' => true
            ];
        }

        // Ensure directory is writable
        if (!is_writable($qr_dir)) {
            chmod($qr_dir, 0777);
            if (!is_writable($qr_dir)) {
                return [
                    'success' => false,
                    'path' => '',
                    'message' => 'QR codes directory is not writable'
                ];
            }
        }

        // Generate unique attendance data (consistent for same student)
        $attendance_data = [
            'type' => 'student_attendance',
            'student_id' => $student_id,
            'student_name' => $student_name,
            'generated_at' => time(),
            'hash' => md5($student_id . $student_name . 'nielit_attendance')
        ];

        // Convert to JSON for QR code
        $qr_data = json_encode($attendance_data);

        // Generate QR Code with higher error correction for scanning reliability
        QRcode::png($qr_data, $qr_file_path, QR_ECLEVEL_M, 8, 2);

        // Verify file was created and has content
        if (file_exists($qr_file_path) && filesize($qr_file_path) > 0) {
            // Update student record with QR path
            $stmt = $conn->prepare("UPDATE students SET attendance_qr_code = ? WHERE student_id = ?");
            if ($stmt) {
                $stmt->bind_param("ss", $relative_path, $student_id);
                $stmt->execute();
                $stmt->close();
            }

            return [
                'success' => true,
                'path' => $relative_path,
                'full_path' => $qr_file_path,
                'data' => $attendance_data,
                'safe_filename' => $filename,
                'original_student_id' => $student_id,
                'safe_student_id' => $safe_student_id,
                'message' => 'Student attendance QR code generated successfully',
                'file_existed' => false
            ];
        } else {
            return [
                'success' => false,
                'path' => '',
                'message' => 'QR Code file was not created or is empty. Check directory permissions.'
            ];
        }

    } catch (Exception $e) {
        return [
            'success' => false,
            'path' => '',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Create attendance session for course coordinator
 * 
 * @param array $session_data - Session details
 * @param mysqli $conn - Database connection
 * @return array - Result with session ID
 */
function createAttendanceSession($session_data, $conn) {
    try {
        if (function_exists('ensureAttendanceInOutTables')) {
            ensureAttendanceInOutTables($conn);
        }
        $courseId = (int) ($session_data['course_id'] ?? 0);
        $batchId = (int) ($session_data['batch_id'] ?? 0);
        if ($courseId <= 0) {
            return [
                'success' => false,
                'message' => 'Please select a course.'
            ];
        }
        if (function_exists('attendanceAdminCanUseCourse') && !attendanceAdminCanUseCourse($conn, $courseId)) {
            return [
                'success' => false,
                'message' => 'That course is not in a centre assigned to you.'
            ];
        }
        if ($batchId > 0) {
            $bt = $conn->prepare('SELECT id, course_id FROM batches WHERE id = ? LIMIT 1');
            if ($bt) {
                $bt->bind_param('i', $batchId);
                $bt->execute();
                $batchRow = $bt->get_result()->fetch_assoc();
                $bt->close();
                if (!$batchRow) {
                    return ['success' => false, 'message' => 'Selected batch was not found.'];
                }
                if ((int) ($batchRow['course_id'] ?? 0) !== $courseId) {
                    return ['success' => false, 'message' => 'Selected batch does not belong to this course.'];
                }
            }
        } elseif (function_exists('attendanceListBatchesForCourse')) {
            $courseBatches = attendanceListBatchesForCourse($conn, $courseId, 0);
            if ($courseBatches !== []) {
                return [
                    'success' => false,
                    'message' => 'Please select a section for this course.'
                ];
            }
        }

        $sessionName = trim((string) ($session_data['session_name'] ?? ''));
        $courseName = trim((string) ($session_data['course_name'] ?? ''));
        $subject = trim((string) ($session_data['subject'] ?? ''));
        if ($subject === '') {
            $subject = $sessionName;
        }
        $sessionDate = trim((string) ($session_data['date'] ?? ''));
        $startTime = trim((string) ($session_data['start_time'] ?? ''));
        $endTime = trim((string) ($session_data['end_time'] ?? ''));
        if ($sessionName === '' || $sessionDate === '' || $startTime === '' || $endTime === '') {
            return [
                'success' => false,
                'message' => 'Please fill section name, date, and times.'
            ];
        }

        $hasBatchCol = function_exists('attendanceSessionsHaveBatchColumn') && attendanceSessionsHaveBatchColumn($conn);
        if ($hasBatchCol) {
            $stmt = $conn->prepare("
                INSERT INTO attendance_sessions
                (session_name, course_id, batch_id, course_name, subject, date, start_time, end_time, coordinator_id, coordinator_name, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
            ");
        } else {
            $stmt = $conn->prepare("
                INSERT INTO attendance_sessions
                (session_name, course_id, course_name, subject, date, start_time, end_time, coordinator_id, coordinator_name, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
            ");
        }

        if (!$stmt) {
            error_log("Prepare failed in createAttendanceSession: " . $conn->error);
            return [
                'success' => false,
                'message' => 'Database error: ' . $conn->error
            ];
        }

        if ($hasBatchCol) {
            $stmt->bind_param(
                "siisssssss",
                $sessionName,
                $courseId,
                $batchId,
                $courseName,
                $subject,
                $sessionDate,
                $startTime,
                $endTime,
                $session_data['coordinator_id'],
                $session_data['coordinator_name']
            );
        } else {
            $stmt->bind_param(
                "sisssssss",
                $sessionName,
                $courseId,
                $courseName,
                $subject,
                $sessionDate,
                $startTime,
                $endTime,
                $session_data['coordinator_id'],
                $session_data['coordinator_name']
            );
        }

        if ($stmt->execute()) {
            $session_id = $conn->insert_id;
            return [
                'success' => true,
                'session_id' => $session_id,
                'message' => 'Attendance session created successfully'
            ];
        }
        return [
            'success' => false,
            'message' => 'Failed to create attendance session'
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Update an existing attendance session (name, course, batch, times).
 */
function updateAttendanceSession($session_id, $session_data, $conn) {
    try {
        if (function_exists('ensureAttendanceInOutTables')) {
            ensureAttendanceInOutTables($conn);
        }
        $session_id = (int) $session_id;
        $coordinator_id = (string) ($session_data['coordinator_id'] ?? '');
        if ($session_id <= 0 || $coordinator_id === '') {
            return ['success' => false, 'message' => 'Session not found.'];
        }

        $check = $conn->prepare("SELECT id, status FROM attendance_sessions WHERE id = ? AND coordinator_id = ? LIMIT 1");
        if (!$check) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $check->bind_param('is', $session_id, $coordinator_id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$existing) {
            return ['success' => false, 'message' => 'Session not found.'];
        }
        $st = strtolower((string) ($existing['status'] ?? ''));
        if ($st === 'completed' || $st === 'cancelled') {
            return ['success' => false, 'message' => 'Ended sessions cannot be edited. Start a new session instead.'];
        }

        $courseId = (int) ($session_data['course_id'] ?? 0);
        $batchId = (int) ($session_data['batch_id'] ?? 0);
        if ($courseId <= 0) {
            return ['success' => false, 'message' => 'Please select a course.'];
        }
        if ($batchId > 0) {
            $bt = $conn->prepare('SELECT id, course_id FROM batches WHERE id = ? LIMIT 1');
            if ($bt) {
                $bt->bind_param('i', $batchId);
                $bt->execute();
                $batchRow = $bt->get_result()->fetch_assoc();
                $bt->close();
                if (!$batchRow) {
                    return ['success' => false, 'message' => 'Selected batch was not found.'];
                }
                if ((int) ($batchRow['course_id'] ?? 0) !== $courseId) {
                    return ['success' => false, 'message' => 'Selected batch does not belong to this course.'];
                }
            }
        } elseif (function_exists('attendanceListBatchesForCourse')) {
            $courseBatches = attendanceListBatchesForCourse($conn, $courseId, 0);
            if ($courseBatches !== []) {
                return ['success' => false, 'message' => 'Please select a section for this course.'];
            }
        }

        $name = trim((string) ($session_data['session_name'] ?? ''));
        $courseName = trim((string) ($session_data['course_name'] ?? ''));
        $subject = trim((string) ($session_data['subject'] ?? ''));
        if ($subject === '') {
            $subject = $name;
        }
        $date = trim((string) ($session_data['date'] ?? ''));
        $start = trim((string) ($session_data['start_time'] ?? ''));
        $end = trim((string) ($session_data['end_time'] ?? ''));
        if ($name === '' || $date === '' || $start === '' || $end === '') {
            return ['success' => false, 'message' => 'Please fill section name, date, and times.'];
        }

        $hasBatchCol = function_exists('attendanceSessionsHaveBatchColumn') && attendanceSessionsHaveBatchColumn($conn);
        if ($hasBatchCol) {
            $stmt = $conn->prepare("
                UPDATE attendance_sessions
                SET session_name = ?, course_id = ?, batch_id = ?, course_name = ?, subject = ?, date = ?, start_time = ?, end_time = ?, updated_at = NOW()
                WHERE id = ? AND coordinator_id = ?
            ");
        } else {
            $stmt = $conn->prepare("
                UPDATE attendance_sessions
                SET session_name = ?, course_id = ?, course_name = ?, subject = ?, date = ?, start_time = ?, end_time = ?, updated_at = NOW()
                WHERE id = ? AND coordinator_id = ?
            ");
        }
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        if ($hasBatchCol) {
            $stmt->bind_param(
                'siisssssis',
                $name,
                $courseId,
                $batchId,
                $courseName,
                $subject,
                $date,
                $start,
                $end,
                $session_id,
                $coordinator_id
            );
        } else {
            $stmt->bind_param(
                'sisssssis',
                $name,
                $courseId,
                $courseName,
                $subject,
                $date,
                $start,
                $end,
                $session_id,
                $coordinator_id
            );
        }
        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'session_id' => $session_id, 'message' => 'Session updated.'];
        }
        $err = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => $err !== '' ? $err : 'Could not update session.'];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Process QR code scan for attendance
 * 
 * @param string $qr_data - Scanned QR data
 * @param int $session_id - Active session ID
 * @param string $coordinator_id - Coordinator ID
 * @param mysqli $conn - Database connection
 * @return array - Scan result
 */
function processAttendanceQRScan($qr_data, $session_id, $coordinator_id, $conn) {
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

        // Get session details
        $session_stmt = $conn->prepare("SELECT * FROM attendance_sessions WHERE id = ? AND status = 'active'");
        if (!$session_stmt) {
            error_log("Prepare failed in processAttendanceQRScan (session): " . $conn->error);
            return [
                'success' => false,
                'result' => 'error',
                'message' => 'Database error: ' . $conn->error
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

        // Check if already marked present today for this session
        $check_stmt = $conn->prepare("
            SELECT id FROM attendance 
            WHERE student_id = ? AND session_id = ? AND date = ? AND status = 'present'
        ");
        
        if (!$check_stmt) {
            error_log("Prepare failed in processAttendanceQRScan (check): " . $conn->error);
            return [
                'success' => false,
                'result' => 'error',
                'message' => 'Database error: ' . $conn->error
            ];
        }
        
        $check_stmt->bind_param("sis", $student_id, $session_id, $session['date']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            // Log duplicate scan
            logQRScan($session_id, $student_id, $student_name, 'duplicate', $coordinator_id, $conn);
            
            return [
                'success' => false,
                'result' => 'duplicate',
                'message' => 'Student already marked present for this session'
            ];
        }

        // Mark attendance
        $attendance_stmt = $conn->prepare("
            INSERT INTO attendance 
            (session_id, student_id, date, subject, time, status, scan_method, scan_timestamp, marked_by, coordinator_id, remarks) 
            VALUES (?, ?, ?, ?, ?, 'present', 'qr_scan', NOW(), ?, ?, 'Marked via QR scan')
        ");
        
        if (!$attendance_stmt) {
            error_log("Prepare failed in processAttendanceQRScan (attendance): " . $conn->error);
            return [
                'success' => false,
                'result' => 'error',
                'message' => 'Database error: ' . $conn->error
            ];
        }
        
        $current_time = date('H:i:s');
        $attendance_stmt->bind_param("issssss", 
            $session_id, 
            $student_id, 
            $session['date'], 
            $session['subject'], 
            $current_time, 
            $coordinator_id, 
            $coordinator_id
        );

        if ($attendance_stmt->execute()) {
            // Log successful scan
            logQRScan($session_id, $student_id, $student_name, 'success', $coordinator_id, $conn);
            
            return [
                'success' => true,
                'result' => 'success',
                'student_id' => $student_id,
                'student_name' => $student_name,
                'message' => 'Attendance marked successfully'
            ];
        } else {
            return [
                'success' => false,
                'result' => 'invalid',
                'message' => 'Failed to mark attendance'
            ];
        }

    } catch (Exception $e) {
        return [
            'success' => false,
            'result' => 'invalid',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Log QR scan attempt
 */
function logQRScan($session_id, $student_id, $student_name, $result, $coordinator_id, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO qr_scan_logs 
        (session_id, student_id, student_name, scan_result, coordinator_id, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        error_log("Prepare failed in logQRScan: " . $conn->error);
        return false;
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt->bind_param("issssss", $session_id, $student_id, $student_name, $result, $coordinator_id, $ip_address, $user_agent);
    return $stmt->execute();
}

/**
 * Get active attendance sessions for coordinator
 */
function getActiveAttendanceSessions($coordinator_id, $conn, $centre_id = 0, $batch_id = 0) {
    $centre_id = (int) $centre_id;
    $batch_id = (int) $batch_id;
    $hasBatchCol = function_exists('attendanceSessionsHaveBatchColumn') && attendanceSessionsHaveBatchColumn($conn);
    $batchSelect = $hasBatchCol
        ? ", IFNULL(b.batch_name, '') AS batch_name, IFNULL(b.batch_code, '') AS batch_code, IFNULL(s.batch_id, 0) AS session_batch_id"
        : ", '' AS batch_name, '' AS batch_code, 0 AS session_batch_id";
    $studentCountSelect = ', 0 AS student_count';
    $bs = $conn->query("SHOW TABLES LIKE 'batch_students'");
    if ($hasBatchCol && $bs && $bs->num_rows > 0) {
        $studentCountSelect = ', (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = s.batch_id) AS student_count';
    }
    $batchJoin = $hasBatchCol ? " LEFT JOIN batches b ON b.id = s.batch_id " : '';
    $sql = "SELECT s.*, IFNULL(ct.name, '') AS centre_name, IFNULL(c.centre_id, 0) AS course_centre_id
            {$batchSelect}
            {$studentCountSelect}
            FROM attendance_sessions s
            LEFT JOIN courses c ON c.id = s.course_id
            LEFT JOIN centres ct ON ct.id = c.centre_id
            {$batchJoin}
            WHERE s.coordinator_id = ? AND s.status IN ('scheduled', 'active')";
    $types = 's';
    $params = [$coordinator_id];
    if ($centre_id > 0) {
        $sql .= " AND c.centre_id = ?";
        $types .= 'i';
        $params[] = $centre_id;
    }
    if ($hasBatchCol && $batch_id > 0) {
        $sql .= " AND s.batch_id = ?";
        $types .= 'i';
        $params[] = $batch_id;
    }
    $sql .= " ORDER BY s.date DESC, s.start_time DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed in getActiveAttendanceSessions: " . $conn->error);
        $stmt = $conn->prepare("
            SELECT * FROM attendance_sessions 
            WHERE coordinator_id = ? AND status IN ('scheduled', 'active') 
            ORDER BY date DESC, start_time DESC
        ");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param("s", $coordinator_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Activate attendance session for QR scanning
 */
function activateAttendanceSession($session_id, $coordinator_id, $conn) {
    $stmt = $conn->prepare("
        UPDATE attendance_sessions 
        SET status = 'active', qr_scanner_active = 1, updated_at = NOW() 
        WHERE id = ? AND coordinator_id = ?
    ");
    
    if (!$stmt) {
        error_log("Prepare failed in activateAttendanceSession: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("is", $session_id, $coordinator_id);
    
    return $stmt->execute();
}

/**
 * Deactivate attendance session
 */
function deactivateAttendanceSession($session_id, $coordinator_id, $conn) {
    $stmt = $conn->prepare("
        UPDATE attendance_sessions 
        SET status = 'completed', qr_scanner_active = 0, updated_at = NOW() 
        WHERE id = ? AND coordinator_id = ?
    ");
    
    if (!$stmt) {
        error_log("Prepare failed in deactivateAttendanceSession: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("is", $session_id, $coordinator_id);
    
    return $stmt->execute();
}

/**
 * Get attendance statistics for session
 */
function getSessionAttendanceStats($session_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_scans,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            COUNT(DISTINCT student_id) as unique_students
        FROM attendance 
        WHERE session_id = ?
    ");
    
    if (!$stmt) {
        error_log("Prepare failed in getSessionAttendanceStats: " . $conn->error);
        return ['total_scans' => 0, 'present_count' => 0, 'unique_students' => 0];
    }
    
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Batch generate QR codes for all students
 */
function batchGenerateStudentQRCodes($conn) {
    $results = [];
    
    $query = "SELECT student_id, name FROM students WHERE status = 'active'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($student = $result->fetch_assoc()) {
            $qr_result = generateStudentAttendanceQR($student['student_id'], $student['name'], $conn);
            
            $results[] = [
                'student_id' => $student['student_id'],
                'student_name' => $student['name'],
                'result' => $qr_result
            ];
        }
    }
    
    return $results;
}