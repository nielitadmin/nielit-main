<?php
/**
 * Mantra L1 (RD Service) fingerprint attendance — capture validation and kiosk helpers.
 * Encrypted PID biometric payload is never stored.
 */

require_once __DIR__ . '/attendance_in_out_helper.php';

if (!function_exists('ensureBiometricAttendanceTables')) {
    function ensureBiometricAttendanceTables($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $sql = "CREATE TABLE IF NOT EXISTS biometric_capture_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            session_id INT NOT NULL,
            student_id VARCHAR(50) NOT NULL,
            coordinator_id VARCHAR(80) NULL,
            err_code VARCHAR(20) NULL,
            quality_score VARCHAR(20) NULL,
            nm_points VARCHAR(20) NULL,
            device_code VARCHAR(120) NULL,
            device_model VARCHAR(120) NULL,
            rds_id VARCHAR(120) NULL,
            capture_ts VARCHAR(40) NULL,
            pid_hash CHAR(64) NULL,
            result VARCHAR(30) NOT NULL DEFAULT 'ok',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_bio_session (session_id),
            KEY idx_bio_student (student_id),
            KEY idx_bio_hash (pid_hash),
            KEY idx_bio_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($sql)) {
            error_log('ensureBiometricAttendanceTables failed: ' . $conn->error);
            return false;
        }
        $ready = true;
        return true;
    }
}

if (!function_exists('biometricNormalizeDigits')) {
    function biometricNormalizeDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}

if (!function_exists('biometricAadhaarLast4')) {
    function biometricAadhaarLast4(?string $aadhar): string
    {
        $digits = biometricNormalizeDigits((string) $aadhar);
        if (strlen($digits) < 4) {
            return '';
        }
        return substr($digits, -4);
    }
}

if (!function_exists('biometricStudentPhotoUrl')) {
    function biometricStudentPhotoUrl(array $row): string
    {
        $rel = ltrim(str_replace('\\', '/', trim((string) ($row['passport_photo'] ?? ''))), '/');
        if ($rel === '') {
            return '';
        }
        $fs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($fs)) {
            return '';
        }
        $base = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
        return $base . '/' . $rel;
    }
}

if (!function_exists('lookupBiometricKioskStudent')) {
    /**
     * Exact match only (Student ID, 10-digit mobile, or 12-digit Aadhaar).
     *
     * @return array<string,mixed>|null
     */
    function lookupBiometricKioskStudent($conn, string $query, int $courseId): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $direct = attendanceFindStudentForSession($conn, $query, $courseId);
        if ($direct && attendanceStudentInCourse($conn, (string) $direct['student_id'], $courseId, $direct)) {
            return $direct;
        }

        $digits = biometricNormalizeDigits($query);
        $studentId = $query;

        if (strlen($digits) === 12) {
            $stmt = $conn->prepare("SELECT student_id FROM students
                WHERE REPLACE(REPLACE(REPLACE(IFNULL(aadhar,''),' ',''),'-',''),'/','') = ?
                ORDER BY (course_id = ?) DESC, id DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('si', $digits, $courseId);
                $stmt->execute();
                $hit = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($hit) {
                    $studentId = (string) $hit['student_id'];
                }
            }
            if ($studentId === $query) {
                $acc = $conn->query("SHOW TABLES LIKE 'student_accounts'");
                if ($acc && $acc->num_rows > 0) {
                    $stmt = $conn->prepare('SELECT student_id FROM student_accounts WHERE aadhar = ? LIMIT 1');
                    if ($stmt) {
                        $stmt->bind_param('s', $digits);
                        $stmt->execute();
                        $hit = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if ($hit) {
                            $studentId = (string) $hit['student_id'];
                        }
                    }
                }
            }
        } elseif (strlen($digits) === 10) {
            $stmt = $conn->prepare("SELECT student_id FROM students
                WHERE REPLACE(REPLACE(IFNULL(mobile,''),' ',''),'-','') = ?
                ORDER BY (course_id = ?) DESC, id DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('si', $digits, $courseId);
                $stmt->execute();
                $hit = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($hit) {
                    $studentId = (string) $hit['student_id'];
                }
            }
        }

        $row = attendanceFindStudentForSession($conn, $studentId, $courseId);
        if (!$row) {
            return null;
        }
        if (!attendanceStudentInCourse($conn, (string) $row['student_id'], $courseId, $row)) {
            return null;
        }
        return $row;
    }
}

if (!function_exists('parseMantraPidData')) {
    /**
     * @return array<string,string>
     */
    function parseMantraPidData(string $xml): array
    {
        $xml = trim($xml);
        $out = [
            'err_code' => '',
            'err_info' => '',
            'q_score' => '',
            'nm_points' => '',
            'ts' => '',
            'dc' => '',
            'mi' => '',
            'rds_id' => '',
            'has_data' => '0',
        ];
        if ($xml === '') {
            return $out;
        }
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if (!$doc) {
            return $out;
        }
        $resp = $doc->Resp ?? null;
        if ($resp !== null) {
            $out['err_code'] = trim((string) ($resp['errCode'] ?? $resp['err_code'] ?? ''));
            $out['err_info'] = trim((string) ($resp['errInfo'] ?? $resp['err_info'] ?? ''));
            $out['q_score'] = trim((string) ($resp['qScore'] ?? $resp['q_score'] ?? ''));
            $out['nm_points'] = trim((string) ($resp['nmPoints'] ?? $resp['nm_points'] ?? ''));
            $out['ts'] = trim((string) ($resp['ts'] ?? ''));
        }
        $dev = $doc->DeviceInfo ?? null;
        if ($dev !== null) {
            $out['dc'] = trim((string) ($dev['dc'] ?? ''));
            $out['mi'] = trim((string) ($dev['mi'] ?? ''));
            $out['rds_id'] = trim((string) ($dev['rdsId'] ?? $dev['rds_id'] ?? ''));
        }
        if (isset($doc->Data) && trim((string) $doc->Data) !== '') {
            $out['has_data'] = '1';
        }
        return $out;
    }
}

if (!function_exists('validateMantraPidCapture')) {
    /**
     * @return array{ok:bool,message:string,meta:array<string,string>,hash:string}
     */
    function validateMantraPidCapture(string $xml): array
    {
        $xml = trim($xml);
        if ($xml === '' || strlen($xml) > 65536) {
            return ['ok' => false, 'message' => 'No fingerprint data received from the device.', 'meta' => [], 'hash' => ''];
        }
        if (stripos($xml, 'PidData') === false && stripos($xml, 'Resp') === false) {
            return ['ok' => false, 'message' => 'Device did not return a valid fingerprint capture.', 'meta' => [], 'hash' => ''];
        }
        $meta = parseMantraPidData($xml);
        $err = $meta['err_code'];
        $okCapture = ($err === '0') || ($err === '' && $meta['has_data'] === '1');
        if (!$okCapture) {
            $info = $meta['err_info'] !== '' ? $meta['err_info'] : ($err !== '' ? ('error ' . $err) : 'capture failed');
            return ['ok' => false, 'message' => 'Fingerprint capture failed: ' . $info, 'meta' => $meta, 'hash' => ''];
        }
        $nm = (int) $meta['nm_points'];
        $qs = (int) $meta['q_score'];
        if ($nm > 0 && $nm < 16) {
            return ['ok' => false, 'message' => 'Fingerprint quality is too low. Place the finger firmly and try again.', 'meta' => $meta, 'hash' => ''];
        }
        if ($qs > 0 && $qs < 30) {
            return ['ok' => false, 'message' => 'Fingerprint quality is too low. Place the finger firmly and try again.', 'meta' => $meta, 'hash' => ''];
        }
        if ($meta['ts'] !== '') {
            $ts = strtotime($meta['ts']);
            if ($ts !== false && abs(time() - $ts) > 180) {
                return ['ok' => false, 'message' => 'Fingerprint capture is stale. Capture again on this PC.', 'meta' => $meta, 'hash' => ''];
            }
        }
        $hash = hash('sha256', $xml);
        return ['ok' => true, 'message' => 'ok', 'meta' => $meta, 'hash' => $hash];
    }
}

if (!function_exists('biometricPidHashWasUsed')) {
    function biometricPidHashWasUsed($conn, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }
        $stmt = $conn->prepare('SELECT id FROM biometric_capture_logs WHERE pid_hash = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE) LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (bool) $row;
    }
}

if (!function_exists('logBiometricCapture')) {
    /**
     * @param array<string,string> $meta
     */
    function logBiometricCapture($conn, int $sessionId, string $studentId, string $coordinatorId, array $meta, string $hash, string $result): void
    {
        ensureBiometricAttendanceTables($conn);
        $stmt = $conn->prepare('INSERT INTO biometric_capture_logs
            (session_id, student_id, coordinator_id, err_code, quality_score, nm_points, device_code, device_model, rds_id, capture_ts, pid_hash, result)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            return;
        }
        $err = (string) ($meta['err_code'] ?? '');
        $qs = (string) ($meta['q_score'] ?? '');
        $nm = (string) ($meta['nm_points'] ?? '');
        $dc = (string) ($meta['dc'] ?? '');
        $mi = (string) ($meta['mi'] ?? '');
        $rds = (string) ($meta['rds_id'] ?? '');
        $ts = (string) ($meta['ts'] ?? '');
        $stmt->bind_param(
            'isssssssssss',
            $sessionId,
            $studentId,
            $coordinatorId,
            $err,
            $qs,
            $nm,
            $dc,
            $mi,
            $rds,
            $ts,
            $hash,
            $result
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('processBiometricKioskAttendance')) {
    /**
     * @return array<string,mixed>
     */
    function processBiometricKioskAttendance($conn, int $sessionId, string $studentId, string $aadhaarLast4, string $pidXml, string $coordinatorId): array
    {
        ensureBiometricAttendanceTables($conn);
        $studentId = trim($studentId);
        if ($sessionId <= 0 || $studentId === '') {
            return ['success' => false, 'result' => 'invalid', 'message' => 'Select a session and student first.'];
        }

        $sessionStmt = $conn->prepare("SELECT * FROM attendance_sessions WHERE id = ? AND status = 'active'");
        if (!$sessionStmt) {
            return ['success' => false, 'result' => 'error', 'message' => 'Database error.'];
        }
        $sessionStmt->bind_param('i', $sessionId);
        $sessionStmt->execute();
        $session = $sessionStmt->get_result()->fetch_assoc();
        $sessionStmt->close();
        if (!$session) {
            return ['success' => false, 'result' => 'expired', 'message' => 'Session is not active.'];
        }

        $row = lookupBiometricKioskStudent($conn, $studentId, (int) $session['course_id']);
        if (!$row) {
            return ['success' => false, 'result' => 'unknown_student', 'message' => 'Student not found in this course.'];
        }

        $needLast4 = biometricAadhaarLast4((string) ($row['aadhar'] ?? ''));
        if ($needLast4 !== '') {
            $given = biometricNormalizeDigits($aadhaarLast4);
            if (strlen($given) !== 4 || !hash_equals($needLast4, $given)) {
                logBiometricCapture($conn, $sessionId, $studentId, $coordinatorId, [], '', 'aadhaar_mismatch');
                return [
                    'success' => false,
                    'result' => 'aadhaar_mismatch',
                    'message' => 'Last 4 digits of Aadhaar do not match. Attendance not marked.',
                ];
            }
        }

        $check = validateMantraPidCapture($pidXml);
        if (!$check['ok']) {
            logBiometricCapture($conn, $sessionId, $studentId, $coordinatorId, $check['meta'], $check['hash'], 'capture_fail');
            return ['success' => false, 'result' => 'capture_fail', 'message' => $check['message']];
        }
        if (biometricPidHashWasUsed($conn, $check['hash'])) {
            return ['success' => false, 'result' => 'replay', 'message' => 'This fingerprint capture was already used. Capture again.'];
        }

        $marked = processInOutAttendanceForStudent($studentId, $sessionId, $coordinatorId, $conn);
        logBiometricCapture(
            $conn,
            $sessionId,
            $studentId,
            $coordinatorId,
            $check['meta'],
            $check['hash'],
            !empty($marked['success']) ? 'ok' : (string) ($marked['result'] ?? 'fail')
        );
        if (!empty($marked['success'])) {
            $marked['method'] = 'biometric';
            $marked['message'] = preg_replace('/scan recorded/i', 'fingerprint recorded', (string) $marked['message']);
        }
        return $marked;
    }
}
