<?php
/**
 * Mantra L1 (RD Service) fingerprint attendance — capture validation and kiosk helpers.
 * Encrypted PID biometric payload is never stored.
 */

require_once __DIR__ . '/attendance_in_out_helper.php';

if (!function_exists('biometricKioskJsonExit')) {
    /**
     * @param array<string,mixed> $payload
     */
    function biometricKioskJsonExit($payload): void
    {
        if (!is_array($payload)) {
            $payload = ['success' => false, 'message' => 'Unexpected server result.'];
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $json = json_encode($payload, $flags);
        if (!is_string($json) || $json === '') {
            $json = '{"success":false,"message":"Could not encode the server response."}';
        }
        echo $json;
        exit;
    }
}

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
     * Exact match by Student ID, 10-digit mobile, or 12-digit Aadhaar.
     *
     * @return array{ok:bool,row?:array<string,mixed>,message:string}
     */
    function lookupBiometricKioskStudent($conn, string $query, int $courseId, string $courseName = '', int $batchId = 0): array
    {
        $query = trim($query);
        $courseName = trim($courseName);
        $courseLabel = $courseName !== '' ? $courseName : 'this session course';
        if ($query === '') {
            return ['ok' => false, 'message' => 'Enter the full Student ID, mobile number, or Aadhaar number.'];
        }

        $row = attendanceFindStudentForSession($conn, $query, $courseId);
        if (!$row) {
            $digits = biometricNormalizeDigits($query);
            $resolvedId = '';
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
                        $resolvedId = (string) $hit['student_id'];
                    }
                }
                if ($resolvedId === '') {
                    $acc = $conn->query("SHOW TABLES LIKE 'student_accounts'");
                    if ($acc && $acc->num_rows > 0) {
                        $stmt = $conn->prepare("SELECT student_id FROM student_accounts
                            WHERE REPLACE(REPLACE(REPLACE(IFNULL(aadhar,''),' ',''),'-',''),'/','') = ? LIMIT 1");
                        if ($stmt) {
                            $stmt->bind_param('s', $digits);
                            $stmt->execute();
                            $hit = $stmt->get_result()->fetch_assoc();
                            $stmt->close();
                            if ($hit) {
                                $resolvedId = (string) $hit['student_id'];
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
                        $resolvedId = (string) $hit['student_id'];
                    }
                }
            }
            if ($resolvedId !== '') {
                $row = attendanceFindStudentForSession($conn, $resolvedId, $courseId);
            }
        }

        if (!$row) {
            return [
                'ok' => false,
                'message' => 'No student found with that ID / mobile / Aadhaar. Type the full Student ID (not the name).',
            ];
        }

        if (!attendanceStudentInCourse($conn, (string) $row['student_id'], $courseId, $row)) {
            $other = attendanceStudentCourseNames($conn, (string) $row['student_id']);
            $extra = $other !== [] ? (' Enrolled in: ' . implode(', ', $other) . '.') : '';
            return [
                'ok' => false,
                'message' => ($row['name'] ?? 'This student') . ' (' . $row['student_id'] . ') is not enrolled in ' . $courseLabel . '.' . $extra . ' Create/start a session for their actual course.',
            ];
        }

        if ($batchId > 0 && function_exists('attendanceStudentInBatch') && !attendanceStudentInBatch($conn, (string) $row['student_id'], $batchId)) {
            return [
                'ok' => false,
                'message' => ($row['name'] ?? 'This student') . ' (' . $row['student_id'] . ') is not assigned to this batch. Assign them to the batch first.',
            ];
        }

        return ['ok' => true, 'row' => $row, 'message' => 'ok'];
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
        $doc = @simplexml_load_string($xml);
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
        if ($xml === '' || strlen($xml) > 524288) {
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
            $tsUtc = strtotime($meta['ts'] . ' UTC');
            $now = time();
            $skew = 999999;
            if ($ts !== false) {
                $skew = min($skew, abs($now - $ts));
            }
            if ($tsUtc !== false) {
                $skew = min($skew, abs($now - $tsUtc));
            }
            if ($skew > 43200) {
                return ['ok' => false, 'message' => 'Fingerprint capture is stale. Capture again on this PC.', 'meta' => $meta, 'hash' => ''];
            }
        }
        $hash = hash('sha256', $xml);
        return ['ok' => true, 'message' => 'ok', 'meta' => $meta, 'hash' => $hash];
    }
}

if (!function_exists('validateMantraPidMeta')) {
    /**
     * @param array<string,string> $meta
     * @return array{ok:bool,message:string,meta:array<string,string>,hash:string}
     */
    function validateMantraPidMeta(array $meta, string $hash = ''): array
    {
        $meta = [
            'err_code' => trim((string) ($meta['err_code'] ?? '')),
            'err_info' => trim((string) ($meta['err_info'] ?? '')),
            'q_score' => trim((string) ($meta['q_score'] ?? '')),
            'nm_points' => trim((string) ($meta['nm_points'] ?? '')),
            'ts' => trim((string) ($meta['ts'] ?? '')),
            'dc' => trim((string) ($meta['dc'] ?? '')),
            'mi' => trim((string) ($meta['mi'] ?? '')),
            'rds_id' => trim((string) ($meta['rds_id'] ?? '')),
            'has_data' => trim((string) ($meta['has_data'] ?? '0')),
        ];
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
            $tsUtc = strtotime($meta['ts'] . ' UTC');
            $now = time();
            $skew = 999999;
            if ($ts !== false) {
                $skew = min($skew, abs($now - $ts));
            }
            if ($tsUtc !== false) {
                $skew = min($skew, abs($now - $tsUtc));
            }
            if ($skew > 43200) {
                return ['ok' => false, 'message' => 'Fingerprint capture is stale. Capture again on this PC.', 'meta' => $meta, 'hash' => ''];
            }
        }
        if ($hash === '') {
            $hash = hash('sha256', implode('|', [$meta['err_code'], $meta['ts'], $meta['dc'], $meta['q_score'], $meta['nm_points']]));
        }
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
            error_log('logBiometricCapture prepare failed: ' . $conn->error);
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
        if (!$stmt->execute()) {
            error_log('logBiometricCapture execute failed: ' . $stmt->error);
        }
        $stmt->close();
    }
}

if (!function_exists('biometricLooksLikeReaderSerial')) {
    function biometricLooksLikeReaderSerial(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || strpbrk($value, " \t\n") !== false) {
            return false;
        }
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return false;
        }
        return (bool) preg_match('/^[A-Za-z0-9_\-\.]{4,64}$/', $value) && preg_match('/[0-9]/', $value);
    }
}

if (!function_exists('biometricSanitizeReaderId')) {
    function biometricSanitizeReaderId(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || strlen($raw) > 64) {
            return '';
        }
        if (biometricLooksLikeReaderSerial($raw) || filter_var($raw, FILTER_VALIDATE_IP)) {
            return $raw;
        }
        return '';
    }
}

if (!function_exists('biometricNormalizeDeviceId')) {
    /**
     * Prefer the SecuGen/Mantra reader serial; fall back to the kiosk IP.
     */
    function biometricNormalizeDeviceId(string $ip, string $fallback = ''): string
    {
        $fallback = trim($fallback);
        if (biometricLooksLikeReaderSerial($fallback)) {
            return $fallback;
        }
        $ip = trim($ip);
        if ($ip !== '' && $ip !== 'unknown' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_IP)) {
            return $fallback;
        }
        if (preg_match('/((?:\d{1,3}\.){3}\d{1,3})/', $fallback, $m) && filter_var($m[1], FILTER_VALIDATE_IP)) {
            return $m[1];
        }
        if (preg_match('/([0-9a-f:]{3,})/i', $fallback, $m) && filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $m[1];
        }
        if ($ip !== '' && $ip !== 'unknown') {
            return $ip;
        }
        return '';
    }
}

if (!function_exists('processBiometricKioskAttendance')) {
    /**
     * @return array<string,mixed>
     */
    function processBiometricKioskAttendance($conn, int $sessionId, string $studentId, string $aadhaarLast4, string $pidXml, string $coordinatorId, array $pidMeta = []): array
    {
        ensureBiometricAttendanceTables($conn);
        ensureAttendanceInOutTables($conn);
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

        $found = lookupBiometricKioskStudent(
            $conn,
            $studentId,
            (int) $session['course_id'],
            (string) ($session['course_name'] ?? '')
        );
        if (empty($found['ok']) || empty($found['row'])) {
            return ['success' => false, 'result' => 'unknown_student', 'message' => (string) ($found['message'] ?? 'Student not found in this course.')];
        }
        $row = $found['row'];
        $studentId = trim((string) ($row['student_id'] ?? $studentId));

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

        if (trim($pidXml) !== '') {
            $check = validateMantraPidCapture($pidXml);
        } else {
            $check = validateMantraPidMeta($pidMeta, trim((string) ($pidMeta['hash'] ?? '')));
        }
        if (!$check['ok']) {
            logBiometricCapture($conn, $sessionId, $studentId, $coordinatorId, $check['meta'] ?? [], (string) ($check['hash'] ?? ''), 'capture_fail');
            return ['success' => false, 'result' => 'capture_fail', 'message' => $check['message']];
        }
        if (biometricPidHashWasUsed($conn, $check['hash'])) {
            return ['success' => false, 'result' => 'replay', 'message' => 'This fingerprint capture was already used. Capture again.'];
        }

        $marked = processInOutAttendanceForStudent($studentId, $sessionId, $coordinatorId, $conn, 'biometric');
        logBiometricCapture(
            $conn,
            $sessionId,
            $studentId,
            $coordinatorId,
            $check['meta'] ?? [],
            (string) ($check['hash'] ?? ''),
            !empty($marked['success']) ? 'ok' : (string) ($marked['result'] ?? 'fail')
        );
        if (!empty($marked['success'])) {
            $marked['method'] = 'biometric';
            $marked['message'] = preg_replace('/scan recorded/i', 'fingerprint recorded', (string) $marked['message']);
        }
        return $marked;
    }
}

if (!function_exists('biometricIstTimezone')) {
    function biometricIstTimezone(): DateTimeZone
    {
        return new DateTimeZone('Asia/Kolkata');
    }
}

if (!function_exists('biometricIstNowString')) {
    function biometricIstNowString(): string
    {
        @date_default_timezone_set('Asia/Kolkata');
        return (new DateTime('now', biometricIstTimezone()))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('biometricParseNaiveDateTime')) {
    function biometricParseNaiveDateTime(string $raw, DateTimeZone $tz): ?DateTime
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $slice = substr($raw, 0, 19);
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $slice, $tz);
        if ($dt instanceof DateTime) {
            return $dt;
        }
        try {
            return new DateTime($raw, $tz);
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('biometricDbTimeToIst')) {
    /**
     * Convert a naive DB DATETIME to Asia/Kolkata.
     * Production MySQL DATETIME from NOW() is often UTC wall-clock (06:03 → 11:33 IST).
     */
    function biometricDbTimeToIst(string $raw): DateTime
    {
        $ist = biometricIstTimezone();
        $naive = biometricParseNaiveDateTime($raw, $ist);
        if (!$naive) {
            return new DateTime('now', $ist);
        }
        $fromUtc = biometricParseNaiveDateTime($raw, new DateTimeZone('UTC'));
        if (!$fromUtc) {
            return $naive;
        }
        $fromUtc->setTimezone($ist);
        $now = new DateTime('now', $ist);
        if ($fromUtc->getTimestamp() > $now->getTimestamp() + 900) {
            return $naive;
        }
        return $fromUtc;
    }
}

if (!function_exists('biometricPunchIstDateTime')) {
    /**
     * Prefer TIMESTAMP created_at (session +05:30 = IST). If DATETIME scan_time is UTC
     * wall-clock it sits ~5h30m behind created_at — use the later IST value.
     */
    function biometricPunchIstDateTime(string $scanTime, string $createdAt = ''): DateTime
    {
        $ist = biometricIstTimezone();
        $scan = biometricParseNaiveDateTime($scanTime, $ist);
        $created = biometricParseNaiveDateTime($createdAt, $ist);
        if ($scan && $created) {
            $diff = abs($created->getTimestamp() - $scan->getTimestamp());
            if ($diff >= 4 * 3600 && $diff <= 7 * 3600) {
                $chosen = $created >= $scan ? $created : $scan;
            } else {
                $chosen = $created;
            }
        } elseif ($created) {
            $chosen = $created;
        } elseif ($scanTime !== '') {
            $chosen = biometricDbTimeToIst($scanTime);
        } else {
            return new DateTime('now', $ist);
        }
        // UTC wall-clock leftovers look like early morning (e.g. 06:03 instead of 11:33 IST).
        if ((int) $chosen->format('G') < 8) {
            $asUtc = biometricDbTimeToIst($chosen->format('Y-m-d H:i:s'));
            if ($asUtc->getTimestamp() > $chosen->getTimestamp()) {
                return $asUtc;
            }
        }
        return $chosen;
    }
}

if (!function_exists('getFingerprintMonthlyRecord')) {
    /**
     * Monthly IN/OUT grid for fingerprint attendance, with Mantra device IDs.
     *
     * @return array{days:int,start:string,end:string,rows:array<int,array<string,mixed>>}
     */
    function getFingerprintMonthlyRecord($conn, int $year, int $month, int $courseId = 0, int $centreId = 0, int $batchId = 0): array
    {
        $days = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = sprintf('%04d-%02d-%02d', $year, $month, $days);
        $out = ['days' => $days, 'start' => $start, 'end' => $end, 'rows' => []];
        if (!($conn instanceof mysqli)) {
            return $out;
        }
        @date_default_timezone_set('Asia/Kolkata');
        @$conn->query("SET time_zone = '+05:30'");
        ensureBiometricAttendanceTables($conn);
        ensureAttendanceInOutTables($conn);

        $hasLogs = $conn->query("SHOW TABLES LIKE 'attendance_logs'");
        $hasBio = $conn->query("SHOW TABLES LIKE 'biometric_capture_logs'");
        if (!$hasLogs || $hasLogs->num_rows === 0) {
            return $out;
        }
        $bioOk = $hasBio && $hasBio->num_rows > 0;
        $hasCreated = false;
        $createdCol = $conn->query("SHOW COLUMNS FROM attendance_logs LIKE 'created_at'");
        if ($createdCol && $createdCol->num_rows > 0) {
            $hasCreated = true;
        }
        $hasMethod = false;
        $methodCol = $conn->query("SHOW COLUMNS FROM attendance_logs LIKE 'scan_method'");
        if ($methodCol && $methodCol->num_rows > 0) {
            $hasMethod = true;
        }

        $createdSelect = $hasCreated ? 'l.created_at' : 'l.scan_time AS created_at';
        $bioDevice = "''";
        if ($bioOk) {
            $bioDevice = "(SELECT IFNULL(NULLIF(TRIM(b.device_code), ''), TRIM(b.rds_id))
                FROM biometric_capture_logs b
                WHERE b.result IN ('ok', 'success')
                  AND TRIM(b.student_id) = TRIM(l.student_id)
                  AND (b.session_id = l.session_id OR b.session_id = 0)
                ORDER BY b.id DESC LIMIT 1)";
        }

        $batchSelect = "'' AS batch_name";
        $batchJoin = '';
        if (attendanceSessionsHaveBatchColumn($conn)) {
            $bt = $conn->query("SHOW TABLES LIKE 'batches'");
            if ($bt && $bt->num_rows > 0) {
                $batchSelect = "IFNULL(b.batch_name, '') AS batch_name";
                $batchJoin = " LEFT JOIN batches b ON b.id = s.batch_id ";
            }
        }

        $sql = "SELECT
                    l.student_id,
                    l.student_name,
                    l.scan_time,
                    {$createdSelect},
                    l.scan_type,
                    s.course_name,
                    s.session_name,
                    s.subject,
                    IFNULL(ct.name, '') AS centre_name,
                    {$batchSelect},
                    l.ip_address,
                    {$bioDevice} AS bio_device
                FROM attendance_logs l
                INNER JOIN attendance_sessions s ON s.id = l.session_id
                LEFT JOIN courses c ON c.id = s.course_id
                LEFT JOIN centres ct ON ct.id = c.centre_id
                {$batchJoin}
                WHERE l.status = 'valid'
                  AND l.scan_time >= DATE_SUB(?, INTERVAL 1 DAY)
                  AND l.scan_time < DATE_ADD(?, INTERVAL 2 DAY)";
        $fingerprintWhere = [];
        if ($hasMethod) {
            $fingerprintWhere[] = "l.scan_method = 'biometric'";
        }
        if ($bioOk) {
            $fingerprintWhere[] = "EXISTS (
                SELECT 1 FROM biometric_capture_logs b2
                WHERE b2.result IN ('ok', 'success')
                  AND TRIM(b2.student_id) = TRIM(l.student_id)
                  AND (b2.session_id = l.session_id
                       OR ABS(TIMESTAMPDIFF(MINUTE, b2.created_at, l.scan_time)) <= 360)
            )";
            $fingerprintWhere[] = "l.session_id IN (
                SELECT DISTINCT session_id FROM biometric_capture_logs WHERE result IN ('ok', 'success')
            )";
        }
        if ($fingerprintWhere !== []) {
            $sql .= ' AND (' . implode(' OR ', $fingerprintWhere) . ')';
        }
        $types = 'ss';
        $params = [$start, $end];
        if ($courseId > 0) {
            $sql .= ' AND s.course_id = ?';
            $types .= 'i';
            $params[] = $courseId;
        }
        if ($centreId > 0) {
            $sql .= ' AND c.centre_id = ?';
            $types .= 'i';
            $params[] = $centreId;
        }
        if ($batchId > 0 && attendanceSessionsHaveBatchColumn($conn)) {
            $sql .= ' AND s.batch_id = ?';
            $types .= 'i';
            $params[] = $batchId;
        }
        $sql .= ' ORDER BY centre_name ASC, batch_name ASC, l.student_name ASC, l.student_id ASC, l.scan_time ASC';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('getFingerprintMonthlyRecord prepare failed: ' . $conn->error);
            return $out;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $byStudent = [];
        while ($row = $result->fetch_assoc()) {
            $sid = (string) $row['student_id'];
            $rowKey = $sid . "\t" . (string) ($row['centre_name'] ?? '') . "\t" . (string) ($row['batch_name'] ?? '') . "\t" . (string) ($row['course_name'] ?? '');
            if (!isset($byStudent[$rowKey])) {
                $byStudent[$rowKey] = [
                    'student_id' => $sid,
                    'name' => (string) ($row['student_name'] ?? ''),
                    'centre' => trim((string) ($row['centre_name'] ?? '')),
                    'batch' => trim((string) ($row['batch_name'] ?? '')),
                    'course' => (string) ($row['course_name'] ?? ''),
                    'session' => (string) ($row['session_name'] ?? ''),
                    'subject' => (string) ($row['subject'] ?? ''),
                    'devices' => [],
                    'days' => [],
                ];
            }
            $device = biometricNormalizeDeviceId(
                (string) ($row['ip_address'] ?? ''),
                (string) ($row['bio_device'] ?? '')
            );
            if ($device !== '') {
                $byStudent[$rowKey]['devices'][$device] = true;
            }
            $scanRaw = (string) ($row['scan_time'] ?? '');
            try {
                $ist = biometricPunchIstDateTime($scanRaw, (string) ($row['created_at'] ?? ''));
            } catch (Throwable $e) {
                try {
                    $ist = new DateTime(substr($scanRaw, 0, 19), biometricIstTimezone());
                } catch (Throwable $e2) {
                    continue;
                }
            }
            $istDay = $ist->format('Y-m-d');
            $naiveDay = substr(trim($scanRaw), 0, 10);
            $inMonth = ($istDay >= $start && $istDay <= $end)
                || ($naiveDay >= $start && $naiveDay <= $end);
            if (!$inMonth) {
                continue;
            }
            $day = (int) $ist->format('j');
            if ($naiveDay >= $start && $naiveDay <= $end && ($istDay < $start || $istDay > $end)) {
                $day = (int) substr($naiveDay, 8, 2);
            }
            if ($day < 1 || $day > $days) {
                continue;
            }
            if (!isset($byStudent[$rowKey]['days'][$day])) {
                $byStudent[$rowKey]['days'][$day] = [
                    'in' => '',
                    'out' => '',
                    'pairs' => [],
                    '_open_in' => '',
                ];
            }
            $cell = &$byStudent[$rowKey]['days'][$day];
            $time = $ist->format('g:i A');
            $kind = strtolower((string) ($row['scan_type'] ?? ''));
            if ($kind === 'in') {
                if ($cell['_open_in'] !== '') {
                    $cell['pairs'][] = ['in' => $cell['_open_in'], 'out' => ''];
                }
                $cell['_open_in'] = $time;
                $cell['in'] = $time;
            } elseif ($kind === 'out') {
                $inTime = $cell['_open_in'];
                $cell['pairs'][] = ['in' => $inTime, 'out' => $time];
                $cell['_open_in'] = '';
                if ($inTime !== '') {
                    $cell['in'] = $inTime;
                }
                $cell['out'] = $time;
            }
            unset($cell);
        }
        $stmt->close();

        foreach ($byStudent as &$stu) {
            foreach ($stu['days'] as &$cell) {
                if (!empty($cell['_open_in'])) {
                    $cell['pairs'][] = ['in' => $cell['_open_in'], 'out' => ''];
                    $cell['in'] = $cell['_open_in'];
                }
                unset($cell['_open_in']);
            }
            unset($cell);
        }
        unset($stu);

        foreach ($byStudent as $row) {
            if (empty($row['days'])) {
                continue;
            }
            $devices = array_keys($row['devices']);
            sort($devices);
            $row['device_id'] = $devices !== [] ? implode(', ', $devices) : '—';
            if (trim((string) ($row['centre'] ?? '')) === '') {
                $row['centre'] = '—';
            }
            if (trim((string) ($row['batch'] ?? '')) === '') {
                $row['batch'] = '—';
            }
            $dept = trim($row['course']);
            if ($row['session'] !== '') {
                $dept .= ($dept !== '' ? ' — ' : '') . $row['session'];
            }
            if ($row['subject'] !== '' && $row['subject'] !== $row['session']) {
                $dept .= ($dept !== '' ? ' — ' : '') . $row['subject'];
            }
            $row['department'] = $dept !== '' ? $dept : '—';
            unset($row['devices'], $row['session'], $row['subject']);
            $out['rows'][] = $row;
        }
        return $out;
    }
}
