<?php
/**
 * Enhanced Attendance System with IN/OUT Tracking
 * NIELIT Bhubaneswar - Advanced QR Attendance Management
 */

$attendanceAccessHelper = __DIR__ . '/attendance_access_helper.php';
if (is_file($attendanceAccessHelper)) {
    require_once $attendanceAccessHelper;
}

if (!function_exists('ensureAttendanceInOutTables')) {
    /**
     * Create IN/OUT tables if this database never ran the enhanced attendance migration.
     */
    function ensureAttendanceInOutTables($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $logs = "CREATE TABLE IF NOT EXISTS attendance_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            student_id VARCHAR(50) NOT NULL,
            student_name VARCHAR(255) NOT NULL,
            scan_type ENUM('in', 'out') NOT NULL,
            scan_time DATETIME NOT NULL,
            coordinator_id VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            duration_minutes INT NULL,
            status ENUM('valid', 'duplicate', 'too_early') DEFAULT 'valid',
            scan_method VARCHAR(20) NOT NULL DEFAULT 'qr',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_student (session_id, student_id),
            INDEX idx_scan_time (scan_time),
            INDEX idx_student_date (student_id, scan_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($logs)) {
            error_log('ensureAttendanceInOutTables logs failed: ' . $conn->error);
            return false;
        }

        $summary = "CREATE TABLE IF NOT EXISTS attendance_summary (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($summary)) {
            error_log('ensureAttendanceInOutTables summary failed: ' . $conn->error);
            return false;
        }

        $logTimeCol = $conn->query("SHOW COLUMNS FROM attendance_logs LIKE 'created_at'");
        if ($logTimeCol && $logTimeCol->num_rows === 0) {
            @$conn->query("ALTER TABLE attendance_logs ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }
        $methodCol = $conn->query("SHOW COLUMNS FROM attendance_logs LIKE 'scan_method'");
        if ($methodCol && $methodCol->num_rows === 0) {
            @$conn->query("ALTER TABLE attendance_logs ADD COLUMN scan_method VARCHAR(20) NOT NULL DEFAULT 'qr'");
        }

        $sessionCols = [
            'session_type' => "ALTER TABLE attendance_sessions ADD COLUMN session_type ENUM('regular','in_out') DEFAULT 'in_out'",
            'min_duration_minutes' => 'ALTER TABLE attendance_sessions ADD COLUMN min_duration_minutes INT DEFAULT 1',
            'auto_out_hours' => 'ALTER TABLE attendance_sessions ADD COLUMN auto_out_hours INT DEFAULT 8',
            'batch_id' => 'ALTER TABLE attendance_sessions ADD COLUMN batch_id INT NULL DEFAULT NULL AFTER course_id',
            'classes_held' => 'ALTER TABLE attendance_sessions ADD COLUMN classes_held INT NULL DEFAULT NULL',
        ];
        foreach ($sessionCols as $col => $alter) {
            $check = $conn->query("SHOW COLUMNS FROM attendance_sessions LIKE '" . $conn->real_escape_string($col) . "'");
            if ($check && $check->num_rows === 0) {
                if (!$conn->query($alter)) {
                    error_log('ensureAttendanceInOutTables alter ' . $col . ' failed: ' . $conn->error);
                }
            }
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('attendanceListCentres')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function attendanceListCentres($conn): array
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }
        $t = $conn->query("SHOW TABLES LIKE 'centres'");
        if (!$t || $t->num_rows === 0) {
            return [];
        }
        $sql = "SELECT id, name, code FROM centres";
        $active = $conn->query("SHOW COLUMNS FROM centres LIKE 'is_active'");
        if ($active && $active->num_rows > 0) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY name ASC";
        $r = $conn->query($sql);
        $rows = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        if (function_exists('attendanceRestrictCentreRows')) {
            return attendanceRestrictCentreRows($conn, $rows);
        }
        return $rows;
    }
}

if (!function_exists('attendanceListCoursesForCentre')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function attendanceListCoursesForCentre($conn, int $centreId = 0): array
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }
        $hasCentre = false;
        $col = $conn->query("SHOW COLUMNS FROM courses LIKE 'centre_id'");
        if ($col && $col->num_rows > 0) {
            $hasCentre = true;
        }
        $sql = "SELECT c.id, c.course_name, c.course_code";
        if ($hasCentre) {
            $sql .= ", c.centre_id, IFNULL(ct.name, '') AS centre_name
                     FROM courses c
                     LEFT JOIN centres ct ON ct.id = c.centre_id";
        } else {
            $sql .= ", 0 AS centre_id, '' AS centre_name FROM courses c";
        }
        $sql .= " WHERE 1=1";
        $hasStatus = $conn->query("SHOW COLUMNS FROM courses LIKE 'status'");
        if ($hasStatus && $hasStatus->num_rows > 0) {
            $sql .= " AND (c.status = 'active' OR c.status IS NULL OR c.status = '')";
        }
        $params = [];
        $types = '';
        if ($hasCentre && $centreId > 0) {
            $sql .= " AND c.centre_id = ?";
            $types = 'i';
            $params[] = $centreId;
        }
        $sql .= " ORDER BY centre_name ASC, c.course_name ASC";
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $r = $conn->query($sql);
            $rows = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        }
        if ($rows === [] && $centreId === 0) {
            $r = $conn->query("SELECT id, course_name, course_code, 0 AS centre_id, '' AS centre_name FROM courses ORDER BY course_name");
            $rows = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        }
        if (function_exists('attendanceRestrictCourseRows')) {
            $rows = attendanceRestrictCourseRows($conn, $rows ?: []);
        }
        return $rows ?: [];
    }
}

if (!function_exists('attendanceFormatCourseLabel')) {
    /**
     * Course dropdown / report label with code, e.g. "DBC — Drone Boot Camp NO-13".
     */
    function attendanceFormatCourseLabel(array $course, bool $includeCentre = false): string
    {
        $name = trim((string) ($course['course_name'] ?? ''));
        $code = trim((string) ($course['course_code'] ?? ''));
        $label = $code !== '' ? ($code . ' — ' . $name) : $name;
        if ($includeCentre) {
            $centre = trim((string) ($course['centre_name'] ?? ''));
            if ($centre !== '') {
                $label .= ' — ' . $centre;
            }
        }
        return $label !== '' ? $label : 'Course';
    }
}

if (!function_exists('attendanceStudentCourseIds')) {
    /**
     * All course ids a student is enrolled in (students rows + student_enrollments).
     * @return list<int>
     */
    function attendanceStudentCourseIds($conn, string $studentId): array
    {
        $ids = [];
        $studentId = trim($studentId);
        if ($studentId === '' || !($conn instanceof mysqli)) {
            return $ids;
        }
        $add = static function ($id) use (&$ids): void {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        };
        $stmt = $conn->prepare('SELECT DISTINCT course_id FROM students
            WHERE LOWER(TRIM(student_id)) = LOWER(?) AND course_id IS NOT NULL AND course_id > 0');
        if ($stmt) {
            $stmt->bind_param('s', $studentId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $add($r['course_id'] ?? 0);
            }
            $stmt->close();
        }
        $tbl = $conn->query("SHOW TABLES LIKE 'student_enrollments'");
        if ($tbl && $tbl->num_rows > 0) {
            $sql = "SELECT DISTINCT se.course_id
                    FROM student_enrollments se
                    LEFT JOIN students s ON s.id = se.student_record_id
                    LEFT JOIN student_accounts sa ON sa.id = se.account_id
                    WHERE se.course_id IS NOT NULL AND se.course_id > 0
                      AND LOWER(TRIM(IFNULL(se.status,''))) IN ('active','approved','')
                      AND (LOWER(TRIM(IFNULL(s.student_id,''))) = LOWER(?)
                           OR LOWER(TRIM(IFNULL(sa.student_id,''))) = LOWER(?))";
            $enr = $conn->prepare($sql);
            if ($enr) {
                $enr->bind_param('ss', $studentId, $studentId);
                $enr->execute();
                $res = $enr->get_result();
                while ($r = $res->fetch_assoc()) {
                    $add($r['course_id'] ?? 0);
                }
                $enr->close();
            }
        }
        return $ids;
    }
}

if (!function_exists('attendanceCentreName')) {
    function attendanceCentreName($conn, int $centreId): string
    {
        if ($centreId <= 0 || !($conn instanceof mysqli)) {
            return 'All centres';
        }
        $stmt = $conn->prepare('SELECT name FROM centres WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return 'Centre';
        }
        $stmt->bind_param('i', $centreId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $name = trim((string) ($row['name'] ?? ''));
        return $name !== '' ? $name : 'Centre';
    }
}

if (!function_exists('attendanceSessionsHaveBatchColumn')) {
    function attendanceSessionsHaveBatchColumn($conn): bool
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        ensureAttendanceInOutTables($conn);
        $col = $conn->query("SHOW COLUMNS FROM attendance_sessions LIKE 'batch_id'");
        return $col && $col->num_rows > 0;
    }
}

if (!function_exists('attendanceNormalizeClassesHeld')) {
    function attendanceNormalizeClassesHeld($value): int
    {
        $n = (int) $value;
        if ($n < 0 || $n > 500) {
            return 0;
        }
        return $n;
    }
}

if (!function_exists('attendanceSessionsHaveClassesHeldColumn')) {
    function attendanceSessionsHaveClassesHeldColumn($conn): bool
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        ensureAttendanceInOutTables($conn);
        $col = $conn->query("SHOW COLUMNS FROM attendance_sessions LIKE 'classes_held'");
        return $col && $col->num_rows > 0;
    }
}

if (!function_exists('attendanceSaveSessionClassesHeld')) {
    function attendanceSaveSessionClassesHeld($conn, int $sessionId, $classesHeld): void
    {
        $sessionId = (int) $sessionId;
        $classesHeld = attendanceNormalizeClassesHeld($classesHeld);
        if ($sessionId <= 0 || !attendanceSessionsHaveClassesHeldColumn($conn)) {
            return;
        }
        $stmt = $conn->prepare('UPDATE attendance_sessions SET classes_held = ? WHERE id = ?');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ii', $classesHeld, $sessionId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('attendanceSumSessionClassesHeld')) {
    /**
     * Sum of classes_held saved on sessions in this month (optional course / centre / section).
     */
    function attendanceSumSessionClassesHeld($conn, int $year, int $month, int $courseId = 0, int $centreId = 0, int $batchId = 0): int
    {
        if (!($conn instanceof mysqli) || !attendanceSessionsHaveClassesHeldColumn($conn)) {
            return 0;
        }
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        $sql = "SELECT COALESCE(SUM(s.classes_held), 0) AS held
                FROM attendance_sessions s
                LEFT JOIN courses c ON c.id = s.course_id
                WHERE s.classes_held > 0 AND s.date >= ? AND s.date <= ?";
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
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return attendanceNormalizeClassesHeld($row['held'] ?? 0);
    }
}

if (!function_exists('attendanceListBatchesForCourse')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function attendanceListBatchesForCourse($conn, int $courseId = 0, int $centreId = 0): array
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }
        $t = $conn->query("SHOW TABLES LIKE 'batches'");
        if (!$t || $t->num_rows === 0) {
            return [];
        }
        $countSelect = "0 AS student_count";
        $bs = $conn->query("SHOW TABLES LIKE 'batch_students'");
        if ($bs && $bs->num_rows > 0) {
            $countSelect = "(SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id) AS student_count";
        }
        $sql = "SELECT b.id, b.batch_name, b.batch_code, b.course_id, b.status,
                       IFNULL(c.course_name, '') AS course_name,
                       IFNULL(c.centre_id, 0) AS centre_id,
                       IFNULL(ct.name, '') AS centre_name,
                       {$countSelect}
                FROM batches b
                LEFT JOIN courses c ON c.id = b.course_id
                LEFT JOIN centres ct ON ct.id = c.centre_id
                WHERE 1=1";
        $params = [];
        $types = '';
        if ($courseId > 0) {
            $sql .= " AND b.course_id = ?";
            $types .= 'i';
            $params[] = $courseId;
        }
        if ($centreId > 0) {
            $sql .= " AND c.centre_id = ?";
            $types .= 'i';
            $params[] = $centreId;
        }
        $hasStatus = $conn->query("SHOW COLUMNS FROM batches LIKE 'status'");
        if ($hasStatus && $hasStatus->num_rows > 0) {
            $sql .= " AND (b.status IS NULL OR b.status = '' OR LOWER(b.status) NOT IN ('cancelled','canceled'))";
        }
        $sql .= " ORDER BY centre_name ASC, course_name ASC, b.batch_name ASC";
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows ?: [];
        }
        $r = $conn->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }
}

if (!function_exists('attendanceFormatBatchLabel')) {
    /**
     * @param array<string,mixed> $batch
     */
    function attendanceFormatBatchLabel(array $batch, bool $includeCourse = false): string
    {
        $bLabel = trim((string) ($batch['batch_name'] ?? ''));
        $bCode = trim((string) ($batch['batch_code'] ?? ''));
        $text = $bCode !== '' ? ($bLabel . ' (' . $bCode . ')') : $bLabel;
        if ($includeCourse) {
            $bCourse = trim((string) ($batch['course_name'] ?? ''));
            if ($bCourse !== '') {
                $text .= ' — ' . $bCourse;
            }
        }
        if (array_key_exists('student_count', $batch)) {
            $n = (int) $batch['student_count'];
            $text .= ' — ' . $n . ' student' . ($n === 1 ? '' : 's');
        }
        return $text;
    }
}

if (!function_exists('attendanceFormatClock12')) {
    /**
     * Display a TIME or DATETIME as 12-hour clock, e.g. 1:28:20 PM.
     */
    function attendanceFormatClock12($time, bool $withSeconds = true, bool $withIst = false): string
    {
        $raw = trim((string) $time);
        if ($raw === '' || $raw === '-' || $raw === '—') {
            return '';
        }
        $raw = trim((string) preg_replace('/\s*IST\s*$/i', '', $raw));
        $tz = new DateTimeZone('Asia/Kolkata');
        $dt = DateTime::createFromFormat('H:i:s', $raw, $tz)
            ?: DateTime::createFromFormat('H:i', $raw, $tz)
            ?: DateTime::createFromFormat('Y-m-d H:i:s', substr($raw, 0, 19), $tz)
            ?: DateTime::createFromFormat('g:i:s A', $raw, $tz)
            ?: DateTime::createFromFormat('h:i:s A', $raw, $tz)
            ?: DateTime::createFromFormat('g:i A', $raw, $tz);
        if (!$dt) {
            try {
                $dt = new DateTime($raw, $tz);
            } catch (Exception $e) {
                return $withIst ? ($raw . ' IST') : $raw;
            }
        }
        $out = $dt->format($withSeconds ? 'g:i:s A' : 'g:i A');
        return $withIst ? ($out . ' IST') : $out;
    }
}

if (!function_exists('attendanceBatchName')) {
    function attendanceBatchName($conn, int $batchId): string
    {
        if ($batchId <= 0 || !($conn instanceof mysqli)) {
            return 'All batches';
        }
        $stmt = $conn->prepare('SELECT batch_name, batch_code FROM batches WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return 'Batch';
        }
        $stmt->bind_param('i', $batchId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $name = trim((string) ($row['batch_name'] ?? ''));
        $code = trim((string) ($row['batch_code'] ?? ''));
        if ($name !== '' && $code !== '') {
            return $name . ' (' . $code . ')';
        }
        return $name !== '' ? $name : ($code !== '' ? $code : 'Batch');
    }
}

if (!function_exists('attendanceStudentRecordIds')) {
    /**
     * @return array<int,int>
     */
    function attendanceStudentRecordIds($conn, string $student_id): array
    {
        $ids = [];
        $student_id = trim($student_id);
        if ($student_id === '' || !($conn instanceof mysqli)) {
            return $ids;
        }
        $stmt = $conn->prepare('SELECT id FROM students WHERE LOWER(TRIM(student_id)) = LOWER(?)');
        if ($stmt) {
            $stmt->bind_param('s', $student_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            $stmt->close();
        }
        return array_values(array_unique($ids));
    }
}

if (!function_exists('attendanceStudentInBatch')) {
    function attendanceStudentInBatch($conn, string $student_id, int $batch_id): bool
    {
        $batch_id = (int) $batch_id;
        $student_id = trim($student_id);
        if ($batch_id <= 0 || $student_id === '' || !($conn instanceof mysqli)) {
            return $batch_id <= 0;
        }
        $ids = attendanceStudentRecordIds($conn, $student_id);
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids)) . 'i';
            $params = $ids;
            $params[] = $batch_id;
            $stmt = $conn->prepare("SELECT id FROM students WHERE id IN ({$placeholders}) AND batch_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $hit = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($hit) {
                    return true;
                }
            }
        }
        $enrTbl = $conn->query("SHOW TABLES LIKE 'student_enrollments'");
        if ($enrTbl && $enrTbl->num_rows > 0) {
            $col = $conn->query("SHOW COLUMNS FROM student_enrollments LIKE 'batch_id'");
            if ($col && $col->num_rows > 0) {
                $sql = "SELECT se.id FROM student_enrollments se
                        LEFT JOIN students s ON s.id = se.student_record_id
                        LEFT JOIN student_accounts sa ON sa.id = se.account_id
                        WHERE se.batch_id = ?
                          AND (LOWER(TRIM(IFNULL(s.student_id,''))) = LOWER(?)
                               OR LOWER(TRIM(IFNULL(sa.student_id,''))) = LOWER(?))
                        LIMIT 1";
                $enr = $conn->prepare($sql);
                if ($enr) {
                    $enr->bind_param('iss', $batch_id, $student_id, $student_id);
                    $enr->execute();
                    $hit = $enr->get_result()->fetch_assoc();
                    $enr->close();
                    if ($hit) {
                        return true;
                    }
                }
            }
        }
        $t = $conn->query("SHOW TABLES LIKE 'batch_students'");
        if (!$t || $t->num_rows === 0) {
            return false;
        }
        $hasRecordCol = false;
        $col = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
        if ($col && $col->num_rows > 0) {
            $hasRecordCol = true;
        }
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = 'i' . str_repeat('i', count($ids));
            $params = array_merge([$batch_id], $ids);
            $sql = "SELECT id FROM batch_students WHERE batch_id = ? AND student_id IN ({$placeholders}) LIMIT 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $hit = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($hit) {
                    return true;
                }
            }
            if ($hasRecordCol) {
                $sql = "SELECT id FROM batch_students WHERE batch_id = ? AND student_record_id IN ({$placeholders}) LIMIT 1";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $hit = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($hit) {
                        return true;
                    }
                }
            }
        }
        $login = $conn->prepare('SELECT id FROM batch_students WHERE batch_id = ? AND CAST(student_id AS CHAR) = ? LIMIT 1');
        if ($login) {
            $login->bind_param('is', $batch_id, $student_id);
            $login->execute();
            $hit = $login->get_result()->fetch_assoc();
            $login->close();
            if ($hit) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('attendanceSyncBatchAttendance')) {
    function attendanceSyncBatchAttendance($conn, string $student_id, int $batch_id, string $date, string $summaryStatus, $marked_by = ''): void
    {
        if (!($conn instanceof mysqli) || $batch_id <= 0 || trim($student_id) === '') {
            return;
        }
        $date = substr($date, 0, 10);
        $summaryStatus = strtolower(trim($summaryStatus));
        $batchStatus = 'Present';
        if ($summaryStatus === 'absent') {
            $batchStatus = 'Absent';
        } elseif ($summaryStatus === 'partial') {
            $batchStatus = 'Late';
        }

        $ids = attendanceStudentRecordIds($conn, $student_id);
        $ba = $conn->query("SHOW TABLES LIKE 'batch_attendance'");
        if ($ba && $ba->num_rows > 0 && $ids !== []) {
            $hasMarked = false;
            $mc = $conn->query("SHOW COLUMNS FROM batch_attendance LIKE 'marked_by'");
            if ($mc && $mc->num_rows > 0) {
                $hasMarked = true;
            }
            foreach ($ids as $numericId) {
                $find = $conn->prepare('SELECT id FROM batch_attendance WHERE batch_id = ? AND student_id = ? AND attendance_date = ? LIMIT 1');
                if (!$find) {
                    continue;
                }
                $find->bind_param('iis', $batch_id, $numericId, $date);
                $find->execute();
                $existing = $find->get_result()->fetch_assoc();
                $find->close();
                if ($existing) {
                    $upd = $conn->prepare('UPDATE batch_attendance SET status = ? WHERE id = ?');
                    if ($upd) {
                        $eid = (int) $existing['id'];
                        $upd->bind_param('si', $batchStatus, $eid);
                        $upd->execute();
                        $upd->close();
                    }
                } elseif ($hasMarked) {
                    $ins = $conn->prepare('INSERT INTO batch_attendance (batch_id, student_id, attendance_date, status, marked_by) VALUES (?, ?, ?, ?, ?)');
                    if ($ins) {
                        $marked = substr((string) $marked_by, 0, 100);
                        $ins->bind_param('iisss', $batch_id, $numericId, $date, $batchStatus, $marked);
                        $ins->execute();
                        $ins->close();
                    }
                } else {
                    $ins = $conn->prepare('INSERT INTO batch_attendance (batch_id, student_id, attendance_date, status) VALUES (?, ?, ?, ?)');
                    if ($ins) {
                        $ins->bind_param('iiss', $batch_id, $numericId, $date, $batchStatus);
                        $ins->execute();
                        $ins->close();
                    }
                }
            }
        }

        $pctCol = $conn->query("SHOW TABLES LIKE 'batch_students'");
        if (!$pctCol || $pctCol->num_rows === 0) {
            return;
        }
        $pctCheck = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'attendance_percentage'");
        if (!$pctCheck || $pctCheck->num_rows === 0) {
            return;
        }
        $percent = 0.0;
        if (attendanceSessionsHaveBatchColumn($conn)) {
            $sum = $conn->prepare(
                "SELECT COUNT(*) AS total_days,
                        SUM(CASE WHEN a.status IN ('present','partial') THEN 1 ELSE 0 END) AS present_days
                 FROM attendance_summary a
                 INNER JOIN attendance_sessions s ON s.id = a.session_id
                 WHERE LOWER(TRIM(a.student_id)) = LOWER(?) AND s.batch_id = ?"
            );
            if ($sum) {
                $sum->bind_param('si', $student_id, $batch_id);
                $sum->execute();
                $row = $sum->get_result()->fetch_assoc();
                $sum->close();
                $total = (int) ($row['total_days'] ?? 0);
                $present = (int) ($row['present_days'] ?? 0);
                if ($total > 0) {
                    $percent = round(($present / $total) * 100, 2);
                }
            }
        }
        $ids = $ids !== [] ? $ids : attendanceStudentRecordIds($conn, $student_id);
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = 'di' . str_repeat('i', count($ids));
        $params = array_merge([$percent, $batch_id], $ids);
        $sql = "UPDATE batch_students SET attendance_percentage = ? WHERE batch_id = ? AND student_id IN ({$placeholders})";
        $upd = $conn->prepare($sql);
        if ($upd) {
            $upd->bind_param($types, ...$params);
            $upd->execute();
            $upd->close();
        }
        $recCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
        if ($recCol && $recCol->num_rows > 0) {
            $sql = "UPDATE batch_students SET attendance_percentage = ? WHERE batch_id = ? AND student_record_id IN ({$placeholders})";
            $upd = $conn->prepare($sql);
            if ($upd) {
                $upd->bind_param($types, ...$params);
                $upd->execute();
                $upd->close();
            }
        }
    }
}

if (!function_exists('getStudentPortalAttendance')) {
    /**
     * @return array{total_classes:int,present_count:int,absent_count:int,partial_count:int,late_count:int,attendance_percentage:float,records:array,batches:array}
     */
    function getStudentPortalAttendance($conn, string $student_id, int $batch_id = 0): array
    {
        $empty = [
            'total_classes' => 0,
            'present_count' => 0,
            'absent_count' => 0,
            'partial_count' => 0,
            'late_count' => 0,
            'attendance_percentage' => 0.0,
            'records' => [],
            'batches' => [],
        ];
        $student_id = trim($student_id);
        if ($student_id === '' || !($conn instanceof mysqli)) {
            return $empty;
        }
        ensureAttendanceInOutTables($conn);
        $hasSummary = $conn->query("SHOW TABLES LIKE 'attendance_summary'");
        if ($hasSummary && $hasSummary->num_rows > 0) {
            $batchJoin = '';
            $batchSelect = "'' AS batch_name, 0 AS batch_id";
            $where = "WHERE LOWER(TRIM(a.student_id)) = LOWER(?)";
            $types = 's';
            $params = [$student_id];
            if (attendanceSessionsHaveBatchColumn($conn)) {
                $bt = $conn->query("SHOW TABLES LIKE 'batches'");
                if ($bt && $bt->num_rows > 0) {
                    $batchSelect = "IFNULL(b.batch_name, '') AS batch_name, IFNULL(sess.batch_id, 0) AS batch_id";
                    $batchJoin = " LEFT JOIN batches b ON b.id = sess.batch_id ";
                }
                if ($batch_id > 0) {
                    $where .= " AND sess.batch_id = ?";
                    $types .= 'i';
                    $params[] = $batch_id;
                }
            }
            $sql = "SELECT a.date, a.status, a.time_in, a.time_out, a.total_duration_minutes,
                           IFNULL(sess.subject, '') AS subject,
                           IFNULL(sess.session_name, '') AS session_name,
                           IFNULL(sess.course_name, '') AS course_name,
                           IFNULL(ct.name, '') AS centre_name,
                           {$batchSelect}
                    FROM attendance_summary a
                    LEFT JOIN attendance_sessions sess ON sess.id = a.session_id
                    LEFT JOIN courses c ON c.id = sess.course_id
                    LEFT JOIN centres ct ON ct.id = c.centre_id
                    {$batchJoin}
                    {$where}
                    ORDER BY a.date DESC, a.id DESC
                    LIMIT 200";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                if ($rows) {
                    $present = 0;
                    $absent = 0;
                    $partial = 0;
                    $byBatch = [];
                    $records = [];
                    foreach ($rows as $row) {
                        $st = strtolower((string) ($row['status'] ?? ''));
                        if ($st === 'present') {
                            $present++;
                        } elseif ($st === 'partial') {
                            $partial++;
                        } else {
                            $absent++;
                        }
                        $bid = (int) ($row['batch_id'] ?? 0);
                        $bname = trim((string) ($row['batch_name'] ?? ''));
                        $bkey = $bid > 0 ? (string) $bid : ($bname !== '' ? $bname : '0');
                        if (!isset($byBatch[$bkey])) {
                            $byBatch[$bkey] = [
                                'batch_id' => $bid,
                                'batch_name' => $bname !== '' ? $bname : 'Unassigned batch',
                                'total' => 0,
                                'present' => 0,
                            ];
                        }
                        $byBatch[$bkey]['total']++;
                        if ($st === 'present' || $st === 'partial') {
                            $byBatch[$bkey]['present']++;
                        }
                        $in = attendanceFormatClock12((string) ($row['time_in'] ?? ''), false);
                        $out = attendanceFormatClock12((string) ($row['time_out'] ?? ''), false);
                        $timeLabel = trim($in . ($out !== '' ? ' – ' . $out : ''));
                        $records[] = [
                            'date' => (string) ($row['date'] ?? ''),
                            'subject' => trim((string) ($row['subject'] ?? '')) !== '' ? (string) $row['subject'] : (string) ($row['session_name'] ?? 'Class'),
                            'course_name' => (string) ($row['course_name'] ?? ''),
                            'centre_name' => (string) ($row['centre_name'] ?? ''),
                            'batch_name' => $bname,
                            'time' => $timeLabel !== '' ? $timeLabel : '—',
                            'status' => $st !== '' ? $st : 'absent',
                            'hours' => round(((int) ($row['total_duration_minutes'] ?? 0)) / 60, 2),
                            'remarks' => '',
                        ];
                    }
                    $total = count($rows);
                    $pct = $total > 0 ? round((($present + $partial) / $total) * 100, 1) : 0.0;
                    $batches = [];
                    foreach ($byBatch as $brow) {
                        $btotal = (int) $brow['total'];
                        $brow['attendance_percentage'] = $btotal > 0
                            ? round(($brow['present'] / $btotal) * 100, 1)
                            : 0.0;
                        $batches[] = $brow;
                    }
                    return [
                        'total_classes' => $total,
                        'present_count' => $present,
                        'absent_count' => $absent,
                        'partial_count' => $partial,
                        'late_count' => $partial,
                        'attendance_percentage' => $pct,
                        'records' => $records,
                        'batches' => $batches,
                    ];
                }
            }
        }

        $legacy = $conn->query("SHOW TABLES LIKE 'attendance'");
        if (!$legacy || $legacy->num_rows === 0) {
            return $empty;
        }
        $stmt = $conn->prepare("SELECT date, subject, time, status, remarks FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 50");
        if (!$stmt) {
            return $empty;
        }
        $stmt->bind_param('s', $student_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $present = 0;
        $absent = 0;
        $late = 0;
        $records = [];
        foreach ($rows as $row) {
            $st = strtolower((string) ($row['status'] ?? ''));
            if ($st === 'present') {
                $present++;
            } elseif ($st === 'late') {
                $late++;
            } else {
                $absent++;
            }
            $records[] = [
                'date' => (string) ($row['date'] ?? ''),
                'subject' => (string) ($row['subject'] ?? 'General'),
                'course_name' => '',
                'centre_name' => '',
                'batch_name' => '',
                'time' => (string) ($row['time'] ?? 'N/A'),
                'status' => $st !== '' ? $st : 'absent',
                'hours' => 0,
                'remarks' => (string) ($row['remarks'] ?? ''),
            ];
        }
        $total = count($rows);
        $pct = $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0.0;
        return [
            'total_classes' => $total,
            'present_count' => $present,
            'absent_count' => $absent,
            'partial_count' => 0,
            'late_count' => $late,
            'attendance_percentage' => $pct,
            'records' => $records,
            'batches' => [],
        ];
    }
}

/**
 * Process IN/OUT QR scan with time validation
 */
function processInOutAttendanceQRScan($qr_data, $session_id, $coordinator_id, $conn) {
    $attendance_data = json_decode($qr_data, true);

    if (!$attendance_data || ($attendance_data['type'] ?? '') !== 'student_attendance') {
        return [
            'success' => false,
            'result' => 'invalid',
            'message' => 'Invalid QR code format'
        ];
    }

    $student_id = trim((string) ($attendance_data['student_id'] ?? ''));
    if ($student_id === '') {
        return [
            'success' => false,
            'result' => 'invalid',
            'message' => 'QR code has no student ID'
        ];
    }

    return processInOutAttendanceForStudent($student_id, $session_id, $coordinator_id, $conn);
}

/**
 * Record IN/OUT for a known student in an active session.
 *
 * @return array<string,mixed>
 */
function processInOutAttendanceForStudent($student_id, $session_id, $coordinator_id, $conn, $scan_method = 'qr') {
    try {
        if (!ensureAttendanceInOutTables($conn)) {
            return [
                'success' => false,
                'result' => 'error',
                'message' => 'Attendance log tables could not be created. Ask an administrator to create attendance_logs.',
            ];
        }
        $student_id = trim((string) $student_id);
        $session_id = (int) $session_id;
        $scan_method = strtolower(trim((string) $scan_method)) === 'biometric' ? 'biometric' : 'qr';
        @date_default_timezone_set('Asia/Kolkata');
        if ($conn instanceof mysqli) {
            @$conn->query("SET time_zone = '+05:30'");
        }
        $current_time = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

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
        $session_stmt->close();

        if (!$session) {
            return [
                'success' => false,
                'result' => 'expired',
                'message' => 'Session not active or expired'
            ];
        }

        $student_row = attendanceFindStudentForSession($conn, $student_id, (int) $session['course_id']);
        if (!$student_row) {
            return [
                'success' => false,
                'result' => 'unknown_student',
                'message' => 'Student not found in system'
            ];
        }

        $session_course_id = (int) $session['course_id'];
        if (!attendanceStudentInCourse($conn, $student_id, $session_course_id, $student_row)) {
            return [
                'success' => false,
                'result' => 'not_enrolled',
                'message' => 'Student is not enrolled in this course',
                'student_id' => $student_id
            ];
        }

        $session_batch_id = (int) ($session['batch_id'] ?? 0);
        if ($session_batch_id > 0 && !attendanceStudentInBatch($conn, $student_id, $session_batch_id)) {
            return [
                'success' => false,
                'result' => 'not_in_batch',
                'message' => 'Student is not assigned to this batch. Assign them to the batch first.',
                'student_id' => $student_id
            ];
        }

        $student_name = trim((string) ($student_row['name'] ?? ''));
        if ($student_name === '') {
            $student_name = $student_id;
        }

        $student_status = strtolower(trim((string) ($student_row['status'] ?? '')));
        if ($student_status !== '' && !in_array($student_status, ['active', 'approved'], true)) {
            return [
                'success' => false,
                'result' => 'invalid_status',
                'message' => 'Student status is not active',
                'status' => $student_status
            ];
        }

        // Latest valid punch for this session on the session day or today (IST), without SQL DATE() TZ traps.
        $last_scan_stmt = $conn->prepare("
            SELECT * FROM attendance_logs 
            WHERE session_id = ? AND student_id = ? AND status = 'valid'
            ORDER BY id DESC LIMIT 12
        ");
        if (!$last_scan_stmt) {
            return [
                'success' => false,
                'result' => 'error',
                'message' => 'Database error (last scan): ' . $conn->error,
            ];
        }
        $last_scan_stmt->bind_param("is", $session_id, $student_id);
        $last_scan_stmt->execute();
        $last_res = $last_scan_stmt->get_result();
        $last_rows = ($last_res) ? $last_res->fetch_all(MYSQLI_ASSOC) : [];
        $last_scan_stmt->close();
        $sessionDay = substr((string) ($session['date'] ?? ''), 0, 10);
        $todayIst = $current_time->format('Y-m-d');
        $attendanceDay = $todayIst !== '' ? $todayIst : $sessionDay;
        $last_scan = null;
        foreach ($last_rows as $cand) {
            if (function_exists('biometricPunchIstDateTime')) {
                $candDay = biometricPunchIstDateTime(
                    (string) ($cand['scan_time'] ?? ''),
                    (string) ($cand['created_at'] ?? '')
                )->format('Y-m-d');
            } else {
                $candDay = substr((string) ($cand['scan_time'] ?? ''), 0, 10);
            }
            if ($candDay === $attendanceDay) {
                $last_scan = $cand;
                break;
            }
        }

        // Determine scan type (IN or OUT)
        $scan_type = 'in';
        $duration_minutes = null;
        $status = 'valid';

        if ($last_scan) {
            $last_scan_time = function_exists('biometricPunchIstDateTime')
                ? biometricPunchIstDateTime(
                    (string) ($last_scan['scan_time'] ?? ''),
                    (string) ($last_scan['created_at'] ?? '')
                )
                : new DateTime($last_scan['scan_time'], new DateTimeZone('Asia/Kolkata'));
            $time_diff = $current_time->getTimestamp() - $last_scan_time->getTimestamp();
            $minutes_diff = floor($time_diff / 60);

            // Check minimum duration between scans
            $min_duration = $session['min_duration_minutes'] ?? 1;
            
            if ($minutes_diff < $min_duration) {
                // Too early to scan again
                logAttendanceScan($session_id, $student_id, $student_name, $scan_type, $coordinator_id, $conn, 'too_early', null, $scan_method);
                
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
        $log_id = logAttendanceScan($session_id, $student_id, $student_name, $scan_type, $coordinator_id, $conn, $status, $duration_minutes, $scan_method);

        try {
            updateAttendanceSummary($session_id, $student_id, $student_name, $attendanceDay, $coordinator_id, $conn);
        } catch (Throwable $e) {
            error_log('updateAttendanceSummary: ' . $e->getMessage());
        }

        $summaryStatus = 'partial';
        if ($scan_type === 'out') {
            $summaryStatus = 'present';
        }
        try {
            if ($session_batch_id > 0) {
                attendanceSyncBatchAttendance(
                    $conn,
                    $student_id,
                    $session_batch_id,
                    $attendanceDay,
                    $summaryStatus,
                    $coordinator_id
                );
            }
        } catch (Throwable $e) {
            error_log('attendanceSyncBatchAttendance: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'result' => 'success',
            'student_id' => $student_id,
            'student_name' => $student_name,
            'scan_type' => $scan_type,
            'scan_time' => attendanceFormatClock12($current_time->format('H:i:s'), true, true),
            'duration_minutes' => $duration_minutes,
            'message' => ucfirst($scan_type) . ' scan recorded successfully' . 
                        ($duration_minutes ? " (Duration: {$duration_minutes} minutes)" : '')
        ];

    } catch (Throwable $e) {
        return [
            'success' => false,
            'result' => 'error',
            'message' => 'Could not save attendance: ' . $e->getMessage(),
        ];
    }
}

/**
 * Prefer the students row that matches the session course.
 *
 * @return array<string,mixed>|null
 */
function attendanceFindStudentForSession($conn, $student_id, $course_id) {
    $student_id = trim((string) $student_id);
    $course_id = (int) $course_id;
    if ($student_id === '') {
        return null;
    }
    $sql = 'SELECT student_id, name, status, course_id, passport_photo, aadhar, mobile
            FROM students WHERE LOWER(TRIM(student_id)) = LOWER(?)
            ORDER BY (course_id = ?) DESC, id DESC LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $sql = 'SELECT student_id, name, status, course_id FROM students
                WHERE LOWER(TRIM(student_id)) = LOWER(?)
                ORDER BY (course_id = ?) DESC, id DESC LIMIT 1';
        $stmt = $conn->prepare($sql);
    }
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('si', $student_id, $course_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        return $row;
    }
    $acc = $conn->query("SHOW TABLES LIKE 'student_accounts'");
    if ($acc && $acc->num_rows > 0) {
        $as = $conn->prepare('SELECT student_id FROM student_accounts WHERE LOWER(TRIM(student_id)) = LOWER(?) LIMIT 1');
        if ($as) {
            $as->bind_param('s', $student_id);
            $as->execute();
            $hit = $as->get_result()->fetch_assoc();
            $as->close();
            if ($hit && strcasecmp((string) $hit['student_id'], $student_id) !== 0) {
                return attendanceFindStudentForSession($conn, (string) $hit['student_id'], $course_id);
            }
        }
    }
    return null;
}

function attendanceStudentInCourse($conn, $student_id, $course_id, ?array $student_row = null): bool {
    $course_id = (int) $course_id;
    $student_id = trim((string) $student_id);
    if ($course_id <= 0 || $student_id === '') {
        return false;
    }
    if ($student_row && (int) ($student_row['course_id'] ?? 0) === $course_id) {
        return true;
    }
    if (function_exists('attendanceStudentCourseIds')) {
        return in_array($course_id, attendanceStudentCourseIds($conn, $student_id), true);
    }
    $stmt = $conn->prepare('SELECT id FROM students WHERE LOWER(TRIM(student_id)) = LOWER(?) AND course_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('si', $student_id, $course_id);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($found) {
            return true;
        }
    }
    $tables = $conn->query("SHOW TABLES LIKE 'student_enrollments'");
    if (!$tables || $tables->num_rows === 0) {
        return false;
    }
    $sql = 'SELECT se.id FROM student_enrollments se
            LEFT JOIN students s ON s.id = se.student_record_id
            LEFT JOIN student_accounts sa ON sa.id = se.account_id
            WHERE se.course_id = ?
              AND (LOWER(TRIM(IFNULL(s.student_id,\'\'))) = LOWER(?)
                   OR LOWER(TRIM(IFNULL(sa.student_id,\'\'))) = LOWER(?))
            LIMIT 1';
    $enr = $conn->prepare($sql);
    if (!$enr) {
        return false;
    }
    $enr->bind_param('iss', $course_id, $student_id, $student_id);
    $enr->execute();
    $ok = (bool) $enr->get_result()->fetch_assoc();
    $enr->close();
    return $ok;
}

function attendanceStudentCourseNames($conn, string $student_id): array {
    $names = [];
    $student_id = trim($student_id);
    if ($student_id === '') {
        return $names;
    }
    $stmt = $conn->prepare('SELECT DISTINCT c.course_name FROM students s
                            LEFT JOIN courses c ON c.id = s.course_id
                            WHERE LOWER(TRIM(s.student_id)) = LOWER(?) AND c.course_name IS NOT NULL');
    if ($stmt) {
        $stmt->bind_param('s', $student_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $n = trim((string) ($row['course_name'] ?? ''));
            if ($n !== '') {
                $names[] = $n;
            }
        }
        $stmt->close();
    }
    $tables = $conn->query("SHOW TABLES LIKE 'student_enrollments'");
    if ($tables && $tables->num_rows > 0) {
        $sql = 'SELECT DISTINCT c.course_name FROM student_enrollments se
                INNER JOIN courses c ON c.id = se.course_id
                LEFT JOIN students s ON s.id = se.student_record_id
                LEFT JOIN student_accounts sa ON sa.id = se.account_id
                WHERE LOWER(TRIM(IFNULL(s.student_id,\'\'))) = LOWER(?)
                   OR LOWER(TRIM(IFNULL(sa.student_id,\'\'))) = LOWER(?)';
        $enr = $conn->prepare($sql);
        if ($enr) {
            $enr->bind_param('ss', $student_id, $student_id);
            $enr->execute();
            $res = $enr->get_result();
            while ($row = $res->fetch_assoc()) {
                $n = trim((string) ($row['course_name'] ?? ''));
                if ($n !== '' && !in_array($n, $names, true)) {
                    $names[] = $n;
                }
            }
            $enr->close();
        }
    }
    return $names;
}

/**
 * Log attendance scan to attendance_logs table
 */
function logAttendanceScan($session_id, $student_id, $student_name, $scan_type, $coordinator_id, $conn, $status = 'valid', $duration_minutes = null, $scan_method = 'qr') {
    if ($conn instanceof mysqli) {
        @$conn->query("SET time_zone = '+05:30'");
    }
    $scan_method = strtolower(trim((string) $scan_method)) === 'biometric' ? 'biometric' : 'qr';
    $scan_time = function_exists('biometricIstNowString')
        ? biometricIstNowString()
        : (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
    $coordinator_id = substr((string) $coordinator_id, 0, 50);
    $hasMethod = false;
    $methodCheck = $conn->query("SHOW COLUMNS FROM attendance_logs LIKE 'scan_method'");
    if ($methodCheck && $methodCheck->num_rows > 0) {
        $hasMethod = true;
    }
    if ($hasMethod) {
        $stmt = $conn->prepare("
            INSERT INTO attendance_logs 
            (session_id, student_id, student_name, scan_type, scan_time, coordinator_id, ip_address, user_agent, duration_minutes, status, scan_method) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    } else {
        $stmt = $conn->prepare("
            INSERT INTO attendance_logs 
            (session_id, student_id, student_name, scan_type, scan_time, coordinator_id, ip_address, user_agent, duration_minutes, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    }
    if (!$stmt) {
        throw new RuntimeException('Could not write attendance log: ' . $conn->error);
    }

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $duration_bind = (int) ($duration_minutes ?? 0);

    if ($hasMethod) {
        $stmt->bind_param("isssssssiss",
            $session_id, $student_id, $student_name, $scan_type, $scan_time,
            $coordinator_id, $ip_address, $user_agent, $duration_bind, $status, $scan_method
        );
    } else {
        $stmt->bind_param("isssssssis",
            $session_id, $student_id, $student_name, $scan_type, $scan_time,
            $coordinator_id, $ip_address, $user_agent, $duration_bind, $status
        );
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        if ($hasMethod && stripos($err, 'scan_method') !== false) {
            $stmt = $conn->prepare("
                INSERT INTO attendance_logs 
                (session_id, student_id, student_name, scan_type, scan_time, coordinator_id, ip_address, user_agent, duration_minutes, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param("isssssssis",
                    $session_id, $student_id, $student_name, $scan_type, $scan_time,
                    $coordinator_id, $ip_address, $user_agent, $duration_bind, $status
                );
                if ($stmt->execute()) {
                    $id = (int) $conn->insert_id;
                    $stmt->close();
                    return $id;
                }
                $err = $stmt->error;
                $stmt->close();
            }
        }
        throw new RuntimeException('Could not write attendance log: ' . $err);
    }
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Update attendance summary table
 */
function updateAttendanceSummary($session_id, $student_id, $student_name, $date, $coordinator_id, $conn) {
    $date = substr(trim((string) $date), 0, 10);
    $scans_stmt = $conn->prepare("
        SELECT scan_type, scan_time, created_at, duration_minutes 
        FROM attendance_logs 
        WHERE session_id = ? AND student_id = ? AND status = 'valid'
        ORDER BY id ASC
    ");
    if (!$scans_stmt) {
        throw new RuntimeException('Could not read attendance summary: ' . $conn->error);
    }
    $scans_stmt->bind_param("is", $session_id, $student_id);
    $scans_stmt->execute();
    $all = $scans_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $scans_stmt->close();

    $scans = [];
    foreach ($all as $scan) {
        if (function_exists('biometricPunchIstDateTime')) {
            $day = biometricPunchIstDateTime((string) $scan['scan_time'], (string) ($scan['created_at'] ?? ''))->format('Y-m-d');
        } else {
            $day = substr((string) $scan['scan_time'], 0, 10);
        }
        if ($day === $date) {
            $scans[] = $scan;
        }
    }

    if ($scans === []) {
        return;
    }

    // Pair every IN with the following OUT so a re-entry updates IN.
    $pairs = [];
    $openIn = null;
    $total_duration = 0;
    $status = 'absent';

    foreach ($scans as $scan) {
        $punchTime = function_exists('biometricPunchIstDateTime')
            ? biometricPunchIstDateTime((string) $scan['scan_time'], (string) ($scan['created_at'] ?? ''))->format('H:i:s')
            : date('H:i:s', strtotime($scan['scan_time']));
        $kind = strtolower((string) ($scan['scan_type'] ?? ''));
        if ($kind === 'in') {
            if ($openIn !== null) {
                $pairs[] = ['in' => $openIn, 'out' => null];
            }
            $openIn = $punchTime;
        } elseif ($kind === 'out') {
            $pairs[] = ['in' => $openIn, 'out' => $punchTime];
            $openIn = null;
            if (!empty($scan['duration_minutes'])) {
                $total_duration += (int) $scan['duration_minutes'];
            }
        }
    }
    if ($openIn !== null) {
        $pairs[] = ['in' => $openIn, 'out' => null];
    }

    $time_in = null;
    $time_out = null;
    if ($pairs !== []) {
        $lastPair = $pairs[count($pairs) - 1];
        $time_in = $lastPair['in'];
        $time_out = $lastPair['out'];
        $completed = 0;
        foreach ($pairs as $pair) {
            if (!empty($pair['in']) && !empty($pair['out'])) {
                $completed++;
            }
        }
        if ($time_in && $time_out) {
            $status = 'present';
        } elseif ($time_in && $completed > 0) {
            $status = 'present';
        } elseif ($time_in) {
            $status = 'partial';
        }
    }

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
    if (!$summary_stmt) {
        throw new RuntimeException('Could not write attendance summary: ' . $conn->error);
    }
    $summary_stmt->bind_param("isssssiss",
        $session_id, $student_id, $student_name, $date,
        $time_in, $time_out, $total_duration, $status, $coordinator_id
    );
    if (!$summary_stmt->execute()) {
        $err = $summary_stmt->error;
        $summary_stmt->close();
        throw new RuntimeException('Could not write attendance summary: ' . $err);
    }
    $summary_stmt->close();
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
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as &$record) {
        if (!empty($record['time_in'])) {
            $record['time_in'] = attendanceFormatClock12($record['time_in'], true);
        }
        if (!empty($record['time_out'])) {
            $record['time_out'] = attendanceFormatClock12($record['time_out'], true);
        }
        if (!empty($record['scan_history'])) {
            $scans = explode('|', (string) $record['scan_history']);
            $formatted = [];
            foreach ($scans as $scan) {
                $parts = explode(':', $scan, 2);
                if (count($parts) === 2) {
                    $clock = attendanceFormatClock12($parts[1], true);
                    $formatted[] = strtolower($parts[0]) . ':' . ($clock !== '' ? $clock : $parts[1]);
                } else {
                    $formatted[] = $scan;
                }
            }
            $record['scan_history'] = implode('|', $formatted);
        }
    }
    unset($record);
    return $rows;
}

if (!function_exists('attendanceAppendStudentCourseCentreFilters')) {
    function attendanceAppendStudentCourseCentreFilters($student_id, $course_id, $centre_id, &$where_clause, &$params, &$types, $batch_id = 0, $conn = null)
    {
        $course_id = ($course_id !== null && $course_id !== '') ? (int) $course_id : 0;
        $centre_id = (int) $centre_id;
        $batch_id = (int) $batch_id;
        if (!empty($student_id)) {
            $where_clause .= " AND a.student_id = ?";
            $params[] = $student_id;
            $types .= "s";
        }
        if ($course_id > 0) {
            $where_clause .= " AND sess.course_id = ?";
            $params[] = $course_id;
            $types .= "i";
        }
        if ($centre_id > 0) {
            $where_clause .= " AND c.centre_id = ?";
            $params[] = $centre_id;
            $types .= "i";
        }
        if ($batch_id > 0) {
            $where_clause .= " AND sess.batch_id = ?";
            $params[] = $batch_id;
            $types .= "i";
        }
        if (function_exists('attendanceAppendGrantedCentreFilter')) {
            attendanceAppendGrantedCentreFilter($conn ?? ($GLOBALS['conn'] ?? null), $where_clause, $params, $types);
        }
    }
}

if (!function_exists('attendanceSummaryReportSelectFrom')) {
    function attendanceSummaryReportSelectFrom($conn = null)
    {
        $batchSelect = "'' as batch_name";
        $batchJoin = '';
        if ($conn instanceof mysqli && attendanceSessionsHaveBatchColumn($conn)) {
            $bt = $conn->query("SHOW TABLES LIKE 'batches'");
            if ($bt && $bt->num_rows > 0) {
                $batchSelect = "IFNULL(MAX(b.batch_name), '') as batch_name";
                $batchJoin = " LEFT JOIN batches b ON b.id = sess.batch_id ";
            }
        }
        return "
        SELECT
            a.student_id,
            a.student_name,
            IFNULL(MAX(c.course_name), 'N/A') as course_name,
            IFNULL(MAX(ct.name), '') as centre_name,
            {$batchSelect},
            COUNT(*) as total_days,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN a.status = 'partial' THEN 1 ELSE 0 END) as partial_days,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(a.total_duration_minutes) as total_minutes,
            ROUND(SUM(a.total_duration_minutes) / 60, 2) as total_hours,
            ROUND(
                (SUM(CASE WHEN a.status IN ('present', 'partial') THEN 1 ELSE 0 END) / COUNT(*)) * 100,
                2
            ) as attendance_percentage
        FROM attendance_summary a
        LEFT JOIN attendance_sessions sess ON sess.id = a.session_id
        LEFT JOIN courses c ON c.id = sess.course_id
        LEFT JOIN centres ct ON ct.id = c.centre_id
        {$batchJoin}
        ";
    }
}

if (!function_exists('attendanceRunSummaryReport')) {
    function attendanceRunSummaryReport($conn, $where_clause, $types, $params, $log_name)
    {
        $groupBatch = '';
        if ($conn instanceof mysqli && attendanceSessionsHaveBatchColumn($conn)) {
            $groupBatch = ', sess.batch_id';
        }
        $sql = attendanceSummaryReportSelectFrom($conn)
            . " {$where_clause}
            GROUP BY a.student_id, a.student_name, ct.id, c.id{$groupBatch}
            ORDER BY centre_name ASC, batch_name ASC, a.student_name ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in {$log_name}: " . $conn->error);
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('attendanceApplyClassesHeld')) {
    /**
     * Recalculate % using classes actually held: (present + partial) / classes_held × 100.
     * Leave $classesHeld at 0 to keep the default (marked days only).
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    function attendanceApplyClassesHeld(array $rows, int $classesHeld): array
    {
        if ($classesHeld <= 0 || $rows === []) {
            return $rows;
        }
        foreach ($rows as &$row) {
            $present = (int) ($row['present_days'] ?? 0);
            $partial = (int) ($row['partial_days'] ?? 0);
            $attended = $present + $partial;
            $row['classes_held'] = $classesHeld;
            $row['total_days'] = $classesHeld;
            $row['absent_days'] = max(0, $classesHeld - $attended);
            $pct = round(($attended / $classesHeld) * 100, 2);
            if ($pct > 100) {
                $pct = 100.0;
            }
            $row['attendance_percentage'] = $pct;
        }
        unset($row);
        return $rows;
    }
}

/**
 * Get monthly attendance report
 */
function getMonthlyAttendanceReport($student_id = null, $year = null, $month = null, $course_id = null, $conn = null, $centre_id = null, $batch_id = null) {
    $year = $year ?? date('Y');
    $month = $month ?? date('n');
    if ($conn === null) {
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn === null) return [];
    }

    $where_clause = "WHERE YEAR(a.date) = ? AND MONTH(a.date) = ?";
    $params = [$year, $month];
    $types = "ii";
    attendanceAppendStudentCourseCentreFilters($student_id, $course_id, $centre_id, $where_clause, $params, $types, $batch_id, $conn);

    return attendanceRunSummaryReport($conn, $where_clause, $types, $params, 'getMonthlyAttendanceReport');
}

/**
 * Get weekly attendance report
 */
function getWeeklyAttendanceReport($student_id = null, $year = null, $week = null, $course_id = null, $conn = null, $centre_id = null, $batch_id = null) {
    $year = $year ?? date('Y');
    $week = $week ?? date('W');
    if ($conn === null) {
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn === null) return [];
    }

    $where_clause = "WHERE YEAR(a.date) = ? AND WEEK(a.date, 1) = ?";
    $params = [$year, $week];
    $types = "ii";
    attendanceAppendStudentCourseCentreFilters($student_id, $course_id, $centre_id, $where_clause, $params, $types, $batch_id, $conn);

    return attendanceRunSummaryReport($conn, $where_clause, $types, $params, 'getWeeklyAttendanceReport');
}

/**
 * Get quarterly attendance report
 */
function getQuarterlyAttendanceReport($student_id = null, $year = null, $quarter = null, $course_id = null, $conn = null, $centre_id = null, $batch_id = null) {
    $year = $year ?? date('Y');
    $quarter = $quarter ?? ceil(date('n') / 3);
    if ($conn === null) {
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn === null) return [];
    }

    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $quarter * 3;

    $where_clause = "WHERE YEAR(a.date) = ? AND MONTH(a.date) BETWEEN ? AND ?";
    $params = [$year, $start_month, $end_month];
    $types = "iii";
    attendanceAppendStudentCourseCentreFilters($student_id, $course_id, $centre_id, $where_clause, $params, $types, $batch_id, $conn);

    return attendanceRunSummaryReport($conn, $where_clause, $types, $params, 'getQuarterlyAttendanceReport');
}

/**
 * Get yearly attendance report
 */
function getYearlyAttendanceReport($student_id = null, $year = null, $course_id = null, $conn = null, $centre_id = null, $batch_id = null) {
    $year = $year ?? date('Y');
    if ($conn === null) {
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn === null) return [];
    }

    $where_clause = "WHERE YEAR(a.date) = ?";
    $params = [$year];
    $types = "i";
    attendanceAppendStudentCourseCentreFilters($student_id, $course_id, $centre_id, $where_clause, $params, $types, $batch_id, $conn);

    return attendanceRunSummaryReport($conn, $where_clause, $types, $params, 'getYearlyAttendanceReport');
}

/**
 * Get custom date range attendance report
 */
function getCustomRangeAttendanceReport($student_id = null, $start_date = null, $end_date = null, $course_id = null, $conn = null, $centre_id = null, $batch_id = null) {
    if (!$start_date || !$end_date) {
        return [];
    }
    if ($conn === null) {
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn === null) return [];
    }

    $where_clause = "WHERE a.date BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    $types = "ss";
    attendanceAppendStudentCourseCentreFilters($student_id, $course_id, $centre_id, $where_clause, $params, $types, $batch_id, $conn);

    return attendanceRunSummaryReport($conn, $where_clause, $types, $params, 'getCustomRangeAttendanceReport');
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