<?php
/**
 * Class Timetable helper — weekly per-batch schedule (Mon–Sat).
 */

if (!function_exists('ensureClassTimetableTable')) {
    function ensureClassTimetableTable($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS class_timetable (
            id INT PRIMARY KEY AUTO_INCREMENT,
            batch_id INT NOT NULL,
            day_of_week TINYINT NOT NULL COMMENT '1=Mon … 6=Sat',
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            subject VARCHAR(255) NOT NULL,
            faculty_name VARCHAR(255) NULL,
            room VARCHAR(100) NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_ct_batch (batch_id),
            KEY idx_ct_batch_day (batch_id, day_of_week, start_time),
            KEY idx_ct_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('ensureClassTimetableTable failed: ' . $conn->error);
            return false;
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('classTimetableDayLabels')) {
    /** @return array<int,string> */
    function classTimetableDayLabels(): array
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];
    }
}

if (!function_exists('classTimetableDayLabel')) {
    function classTimetableDayLabel(int $day): string
    {
        $labels = classTimetableDayLabels();
        return $labels[$day] ?? ('Day ' . $day);
    }
}

if (!function_exists('classTimetableFormatTime')) {
    function classTimetableFormatTime(?string $time): string
    {
        if ($time === null || $time === '') {
            return '—';
        }
        $ts = strtotime($time);
        return $ts ? date('h:i A', $ts) : (string) $time;
    }
}

if (!function_exists('classTimetableNormalizeTime')) {
    function classTimetableNormalizeTime(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $raw)) {
            $parts = explode(':', $raw);
            $h = (int) $parts[0];
            $m = (int) $parts[1];
            $s = isset($parts[2]) ? (int) $parts[2] : 0;
            if ($h >= 0 && $h <= 23 && $m >= 0 && $m <= 59 && $s >= 0 && $s <= 59) {
                return sprintf('%02d:%02d:%02d', $h, $m, $s);
            }
        }
        $ts = strtotime($raw);
        return $ts ? date('H:i:s', $ts) : '';
    }
}

if (!function_exists('getClassTimetableById')) {
    function getClassTimetableById($conn, int $id): ?array
    {
        ensureClassTimetableTable($conn);
        $stmt = $conn->prepare(
            "SELECT ct.*, b.batch_name, b.batch_code, c.course_name
             FROM class_timetable ct
             LEFT JOIN batches b ON b.id = ct.batch_id
             LEFT JOIN courses c ON c.id = b.course_id
             WHERE ct.id = ? LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('listClassTimetableAdmin')) {
    /** @return list<array<string,mixed>> */
    function listClassTimetableAdmin($conn, ?int $batchId = null): array
    {
        ensureClassTimetableTable($conn);
        $sql = "SELECT ct.*, b.batch_name, b.batch_code, c.course_name
                FROM class_timetable ct
                LEFT JOIN batches b ON b.id = ct.batch_id
                LEFT JOIN courses c ON c.id = b.course_id
                WHERE 1=1";
        $types = '';
        $params = [];
        if ($batchId !== null && $batchId > 0) {
            $sql .= ' AND ct.batch_id = ?';
            $types .= 'i';
            $params[] = $batchId;
        }
        $sql .= ' ORDER BY ct.batch_id ASC, ct.day_of_week ASC, ct.start_time ASC';

        $rows = [];
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log('listClassTimetableAdmin prepare failed: ' . $conn->error);
                return [];
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        } else {
            $res = $conn->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }
}

if (!function_exists('listClassTimetableForBatches')) {
    /**
     * @param list<int> $batchIds
     * @return list<array<string,mixed>>
     */
    function listClassTimetableForBatches($conn, array $batchIds): array
    {
        ensureClassTimetableTable($conn);
        $batchIds = array_values(array_unique(array_filter(array_map('intval', $batchIds), static function ($id) {
            return $id > 0;
        })));
        if (empty($batchIds)) {
            return [];
        }
        $inList = implode(',', $batchIds);
        $sql = "SELECT ct.*, b.batch_name, b.batch_code, c.course_name
                FROM class_timetable ct
                LEFT JOIN batches b ON b.id = ct.batch_id
                LEFT JOIN courses c ON c.id = b.course_id
                WHERE ct.batch_id IN ($inList) AND ct.is_active = 1
                ORDER BY ct.batch_id ASC, ct.day_of_week ASC, ct.start_time ASC";
        $rows = [];
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('groupClassTimetableByDay')) {
    /**
     * @param list<array<string,mixed>> $slots
     * @return array<int, list<array<string,mixed>>>
     */
    function groupClassTimetableByDay(array $slots): array
    {
        $grouped = [];
        foreach (classTimetableDayLabels() as $day => $_label) {
            $grouped[$day] = [];
        }
        foreach ($slots as $slot) {
            $d = (int) ($slot['day_of_week'] ?? 0);
            if (!isset($grouped[$d])) {
                $grouped[$d] = [];
            }
            $grouped[$d][] = $slot;
        }
        return $grouped;
    }
}

if (!function_exists('saveClassTimetableSlot')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string,id?:int}
     */
    function saveClassTimetableSlot($conn, array $data, ?int $id = null): array
    {
        ensureClassTimetableTable($conn);

        $batchId = (int) ($data['batch_id'] ?? 0);
        $day = (int) ($data['day_of_week'] ?? 0);
        $start = classTimetableNormalizeTime((string) ($data['start_time'] ?? ''));
        $end = classTimetableNormalizeTime((string) ($data['end_time'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $faculty = trim((string) ($data['faculty_name'] ?? ''));
        $room = trim((string) ($data['room'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $isActive = !empty($data['is_active']) ? 1 : 0;
        $createdBy = trim((string) ($data['created_by'] ?? 'admin'));

        if ($batchId <= 0) {
            return ['success' => false, 'message' => 'Please select a batch.'];
        }
        if ($day < 1 || $day > 6) {
            return ['success' => false, 'message' => 'Please select a valid day (Monday–Saturday).'];
        }
        if ($start === '' || $end === '') {
            return ['success' => false, 'message' => 'Start and end time are required.'];
        }
        if ($start >= $end) {
            return ['success' => false, 'message' => 'End time must be after start time.'];
        }
        if ($subject === '') {
            return ['success' => false, 'message' => 'Subject is required.'];
        }
        if (strlen($subject) > 255) {
            return ['success' => false, 'message' => 'Subject is too long.'];
        }

        $check = $conn->prepare('SELECT id FROM batches WHERE id = ? LIMIT 1');
        if (!$check) {
            return ['success' => false, 'message' => 'Could not validate batch.'];
        }
        $check->bind_param('i', $batchId);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            $check->close();
            return ['success' => false, 'message' => 'Selected batch was not found.'];
        }
        $check->close();

        $facultyVal = $faculty !== '' ? $faculty : null;
        $roomVal = $room !== '' ? $room : null;
        $notesVal = $notes !== '' ? $notes : null;

        if ($id !== null && $id > 0) {
            $stmt = $conn->prepare(
                'UPDATE class_timetable
                 SET batch_id = ?, day_of_week = ?, start_time = ?, end_time = ?, subject = ?,
                     faculty_name = ?, room = ?, notes = ?, is_active = ?
                 WHERE id = ?'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
            $stmt->bind_param(
                'iissssssii',
                $batchId,
                $day,
                $start,
                $end,
                $subject,
                $facultyVal,
                $roomVal,
                $notesVal,
                $isActive,
                $id
            );
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            if (!$ok) {
                return ['success' => false, 'message' => 'Could not update slot: ' . $err];
            }
            return ['success' => true, 'message' => 'Timetable slot updated.', 'id' => $id];
        }

        $stmt = $conn->prepare(
            'INSERT INTO class_timetable
             (batch_id, day_of_week, start_time, end_time, subject, faculty_name, room, notes, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param(
            'iissssssis',
            $batchId,
            $day,
            $start,
            $end,
            $subject,
            $facultyVal,
            $roomVal,
            $notesVal,
            $isActive,
            $createdBy
        );
        $ok = $stmt->execute();
        $newId = (int) $stmt->insert_id;
        $err = $stmt->error;
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not create slot: ' . $err];
        }
        return ['success' => true, 'message' => 'Timetable slot added.', 'id' => $newId];
    }
}

if (!function_exists('deleteClassTimetableSlot')) {
    /** @return array{success:bool,message:string} */
    function deleteClassTimetableSlot($conn, int $id): array
    {
        ensureClassTimetableTable($conn);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid slot.'];
        }
        $stmt = $conn->prepare('DELETE FROM class_timetable WHERE id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not delete slot.'];
        }
        if ($affected < 1) {
            return ['success' => false, 'message' => 'Slot not found.'];
        }
        return ['success' => true, 'message' => 'Timetable slot deleted.'];
    }
}
