<?php
/**
 * Attendance QR Code Helper Functions
 * NIELIT Bhubaneswar - QR-Based Attendance System
 */

require_once __DIR__ . '/../phpqrcode/qrlib.php';

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
        $stmt = $conn->prepare("
            INSERT INTO attendance_sessions 
            (session_name, course_id, course_name, subject, date, start_time, end_time, coordinator_id, coordinator_name, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
        ");
        
        $stmt->bind_param("sisssssss", 
            $session_data['session_name'],
            $session_data['course_id'],
            $session_data['course_name'],
            $session_data['subject'],
            $session_data['date'],
            $session_data['start_time'],
            $session_data['end_time'],
            $session_data['coordinator_id'],
            $session_data['coordinator_name']
        );

        if ($stmt->execute()) {
            $session_id = $conn->insert_id;
            return [
                'success' => true,
                'session_id' => $session_id,
                'message' => 'Attendance session created successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to create attendance session'
            ];
        }

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
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt->bind_param("issssss", $session_id, $student_id, $student_name, $result, $coordinator_id, $ip_address, $user_agent);
    $stmt->execute();
}

/**
 * Get active attendance sessions for coordinator
 */
function getActiveAttendanceSessions($coordinator_id, $conn) {
    $stmt = $conn->prepare("
        SELECT * FROM attendance_sessions 
        WHERE coordinator_id = ? AND status IN ('scheduled', 'active') 
        ORDER BY date DESC, start_time DESC
    ");
    $stmt->bind_param("s", $coordinator_id);
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