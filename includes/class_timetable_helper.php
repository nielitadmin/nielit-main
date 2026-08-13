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

        // Ensure centre_id column exists
        $colCheck = $conn->query("SHOW COLUMNS FROM class_timetable LIKE 'centre_id'");
        if ($colCheck && $colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE class_timetable ADD COLUMN centre_id INT NULL DEFAULT NULL AFTER batch_id");
            $conn->query("ALTER TABLE class_timetable ADD KEY idx_ct_centre (centre_id)");
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

if (!function_exists('classTimetablePeriods')) {
    /**
     * Fixed hourly periods matching the institute spreadsheet layout.
     * @return list<array{key:string,start:string,end:string,label:string,short:string}>
     */
    function classTimetablePeriods(): array
    {
        return [
            ['key' => '07:00', 'start' => '07:00:00', 'end' => '08:00:00', 'label' => "7:00 AM to\n8:00 AM", 'short' => '7–8 AM'],
            ['key' => '08:00', 'start' => '08:00:00', 'end' => '09:00:00', 'label' => "8:00 AM to\n9:00 AM", 'short' => '8–9 AM'],
            ['key' => '09:00', 'start' => '09:00:00', 'end' => '10:00:00', 'label' => "9:00 AM to\n10:00 AM", 'short' => '9–10 AM'],
            ['key' => '10:00', 'start' => '10:00:00', 'end' => '11:00:00', 'label' => "10:00 AM to\n11:00 AM", 'short' => '10–11 AM'],
            ['key' => '11:00', 'start' => '11:00:00', 'end' => '12:00:00', 'label' => "11:00 AM to\n12:00 PM", 'short' => '11–12 PM'],
            ['key' => '12:00', 'start' => '12:00:00', 'end' => '13:00:00', 'label' => "12:00 PM to\n01:00 PM", 'short' => '12–1 PM'],
            ['key' => '13:30', 'start' => '13:30:00', 'end' => '14:30:00', 'label' => "01:30 PM to\n02:30 PM", 'short' => '1:30–2:30'],
            ['key' => '14:30', 'start' => '14:30:00', 'end' => '15:30:00', 'label' => "02:30 PM to\n03:30 PM", 'short' => '2:30–3:30'],
            ['key' => '15:30', 'start' => '15:30:00', 'end' => '16:30:00', 'label' => "03:30 PM to\n04:30 PM", 'short' => '3:30–4:30'],
            ['key' => '16:30', 'start' => '16:30:00', 'end' => '17:30:00', 'label' => "04:30 PM to\n05:30 PM", 'short' => '4:30–5:30'],
            ['key' => '17:30', 'start' => '17:30:00', 'end' => '18:30:00', 'label' => "05:30 PM to\n06:30 PM", 'short' => '5:30–6:30'],
            ['key' => '18:30', 'start' => '18:30:00', 'end' => '19:30:00', 'label' => "06:30 PM to\n07:30 PM", 'short' => '6:30–7:30'],
        ];
    }
}

if (!function_exists('classTimetableTimeToMinutes')) {
    function classTimetableTimeToMinutes(?string $time): int
    {
        $norm = classTimetableNormalizeTime((string) $time);
        if ($norm === '') {
            return -1;
        }
        $parts = explode(':', $norm);
        return ((int) $parts[0] * 60) + (int) $parts[1];
    }
}

if (!function_exists('classTimetableFacultyInitials')) {
    function classTimetableFacultyInitials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }
        // Already looks like initials (e.g. SLD / J.S.)
        if (preg_match('/^[A-Za-z]{1,5}([.\s]*[A-Za-z]){0,4}$/', $name) && strlen(preg_replace('/[^A-Za-z]/', '', $name)) <= 5) {
            $compact = strtoupper(preg_replace('/[^A-Za-z]/', '', $name));
            if ($compact !== '') {
                return $compact;
            }
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        $initials = '';
        foreach ($parts as $part) {
            $part = preg_replace('/[^A-Za-z]/', '', $part);
            if ($part !== '') {
                $initials .= strtoupper($part[0]);
            }
        }
        return $initials;
    }
}

if (!function_exists('classTimetableCellLabel')) {
    /** Spreadsheet-style: "CCC (JS)" */
    function classTimetableCellLabel(array $slot): string
    {
        $subject = trim((string) ($slot['subject'] ?? ''));
        $initials = classTimetableFacultyInitials($slot['faculty_name'] ?? '');
        if ($subject === '') {
            return $initials !== '' ? '(' . $initials . ')' : '';
        }
        return $initials !== '' ? ($subject . ' (' . $initials . ')') : $subject;
    }
}

if (!function_exists('classTimetableMatchPeriodKey')) {
    function classTimetableMatchPeriodKey(array $slot): ?string
    {
        $startMin = classTimetableTimeToMinutes($slot['start_time'] ?? '');
        $endMin = classTimetableTimeToMinutes($slot['end_time'] ?? '');
        if ($startMin < 0) {
            return null;
        }
        foreach (classTimetablePeriods() as $period) {
            $pStart = classTimetableTimeToMinutes($period['start']);
            $pEnd = classTimetableTimeToMinutes($period['end']);
            // Exact start, or slot overlaps majority of period
            if ($startMin === $pStart) {
                return $period['key'];
            }
            if ($endMin > $startMin && $startMin < $pEnd && $endMin > $pStart) {
                $overlap = min($endMin, $pEnd) - max($startMin, $pStart);
                if ($overlap >= 30) {
                    return $period['key'];
                }
            }
        }
        return null;
    }
}

if (!function_exists('classTimetableBuildGrid')) {
    /**
     * @param list<array<string,mixed>> $slots
     * @return array{days:array<int,string>,periods:list<array>,grid:array<int,array<string,list<array>>>,unplaced:list<array>}
     */
    function classTimetableBuildGrid(array $slots): array
    {
        $days = classTimetableDayLabels();
        // Match spreadsheet: Mon–Fri; include Saturday only when used
        $hasSaturday = false;
        foreach ($slots as $slot) {
            if ((int) ($slot['day_of_week'] ?? 0) === 6) {
                $hasSaturday = true;
                break;
            }
        }
        if (!$hasSaturday) {
            unset($days[6]);
        }

        $periods = classTimetablePeriods();
        $grid = [];
        foreach ($days as $day => $_label) {
            $grid[$day] = [];
            foreach ($periods as $period) {
                $grid[$day][$period['key']] = [];
            }
        }

        $unplaced = [];
        foreach ($slots as $slot) {
            $day = (int) ($slot['day_of_week'] ?? 0);
            $key = classTimetableMatchPeriodKey($slot);
            if ($day > 0 && $key !== null && isset($grid[$day][$key])) {
                $grid[$day][$key][] = $slot;
            } else {
                $unplaced[] = $slot;
            }
        }

        return [
            'days' => $days,
            'periods' => $periods,
            'grid' => $grid,
            'unplaced' => $unplaced,
        ];
    }
}

if (!function_exists('classTimetableBuildLegends')) {
    /**
     * @param list<array<string,mixed>> $slots
     * @return array{faculty:array<string,string>,subjects:array<string,string>}
     */
    function classTimetableBuildLegends(array $slots): array
    {
        $faculty = [];
        $subjects = [];
        foreach ($slots as $slot) {
            $name = trim((string) ($slot['faculty_name'] ?? ''));
            if ($name !== '') {
                $ini = classTimetableFacultyInitials($name);
                if ($ini !== '') {
                    // Handle duplicate initials for different faculty names
                    if (!isset($faculty[$ini])) {
                        $faculty[$ini] = $name;
                    } elseif ($faculty[$ini] !== $name) {
                        // Different person, same initials — append number
                        $counter = 2;
                        while (isset($faculty[$ini . $counter])) {
                            $counter++;
                        }
                        $faculty[$ini . $counter] = $name;
                    }
                }
            }
            $subject = trim((string) ($slot['subject'] ?? ''));
            if ($subject !== '') {
                $subjects[$subject] = $subject;
            }
        }
        ksort($faculty);
        ksort($subjects);
        return ['faculty' => $faculty, 'subjects' => $subjects];
    }
}

if (!function_exists('classTimetableFacultyDisplayMap')) {
    /**
     * Build map: faculty_name → unique display code (e.g. "FT", "FT2" if duplicates).
     * @param list<array<string,mixed>> $slots
     * @return array<string,string>
     */
    function classTimetableFacultyDisplayMap(array $slots): array
    {
        $nameToBase = [];
        foreach ($slots as $slot) {
            $name = trim((string) ($slot['faculty_name'] ?? ''));
            if ($name !== '' && !isset($nameToBase[$name])) {
                $nameToBase[$name] = classTimetableFacultyInitials($name);
            }
        }
        // Detect collisions and number them
        $baseCount = [];
        foreach ($nameToBase as $name => $base) {
            $baseCount[$base][] = $name;
        }
        $displayMap = [];
        foreach ($baseCount as $base => $names) {
            if (count($names) === 1) {
                $displayMap[$names[0]] = $base;
            } else {
                $n = 1;
                foreach ($names as $name) {
                    $displayMap[$name] = $base . $n;
                    $n++;
                }
            }
        }
        return $displayMap;
    }
}

if (!function_exists('classTimetableNormalizeDate')) {
    /** Valid Y-m-d or null. */
    function classTimetableNormalizeDate(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '0000-00-00' || strpos($raw, '0000-') === 0) {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }
        $year = (int) date('Y', $ts);
        if ($year < 1990 || $year > 2100) {
            return null;
        }
        return date('Y-m-d', $ts);
    }
}

if (!function_exists('classTimetableSlotActiveOnDate')) {
    /**
     * Show a slot on a calendar date when the batch (preferred) date window covers that day.
     * Course dates are used only when batch start/end are missing.
     * If neither has dates, the slot is treated as always active.
     */
    function classTimetableSlotActiveOnDate(array $slot, string $dateYmd): bool
    {
        $date = classTimetableNormalizeDate($dateYmd);
        if ($date === null) {
            return false;
        }

        $batchStatus = strtolower(trim((string) ($slot['batch_status'] ?? '')));
        if (in_array($batchStatus, ['cancelled', 'deleted'], true)) {
            return false;
        }

        // Timetable is per-batch: prefer batch window so course dates don't hide valid batch days
        $effectiveStart = classTimetableNormalizeDate($slot['batch_start_date'] ?? null);
        $effectiveEnd = classTimetableNormalizeDate($slot['batch_end_date'] ?? null);
        if ($effectiveStart === null) {
            $effectiveStart = classTimetableNormalizeDate($slot['course_start_date'] ?? null);
        }
        if ($effectiveEnd === null) {
            $effectiveEnd = classTimetableNormalizeDate($slot['course_end_date'] ?? null);
        }

        if ($effectiveStart !== null && $date < $effectiveStart) {
            return false;
        }
        if ($effectiveEnd !== null && $date > $effectiveEnd) {
            return false;
        }

        return true;
    }
}

if (!function_exists('classTimetableFilterSlotsForDate')) {
    /** @param list<array<string,mixed>> $slots @return list<array<string,mixed>> */
    function classTimetableFilterSlotsForDate(array $slots, string $dateYmd): array
    {
        $out = [];
        foreach ($slots as $slot) {
            if (classTimetableSlotActiveOnDate($slot, $dateYmd)) {
                $out[] = $slot;
            }
        }
        return $out;
    }
}

if (!function_exists('classTimetableBuildMonth')) {
    /**
     * Expand weekly recurring slots onto a calendar month (Mon–Sat),
     * filtered by batch/course start–end dates for each calendar day.
     *
     * @param list<array<string,mixed>> $slots
     * @return array{
     *   year:int,month:int,label:string,weeks:list<list<array{date:?string,day:int,in_month:bool,slots:list}>>,
     *   by_date:array<string,list>
     * }
     */
    function classTimetableBuildMonth(array $slots, int $year, int $month): array
    {
        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $byDow = [];
        foreach ($slots as $slot) {
            $d = (int) ($slot['day_of_week'] ?? 0);
            if ($d < 1 || $d > 6) {
                continue;
            }
            if (!isset($byDow[$d])) {
                $byDow[$d] = [];
            }
            $byDow[$d][] = $slot;
        }
        foreach ($byDow as &$list) {
            usort($list, static function ($a, $b) {
                return strcmp((string) ($a['start_time'] ?? ''), (string) ($b['start_time'] ?? ''));
            });
        }
        unset($list);

        $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        // PHP N: 1=Mon … 7=Sun — align calendar to Monday start
        $startN = (int) date('N', mktime(0, 0, 0, $month, 1, $year));
        $cells = [];
        // Leading blanks from previous month
        for ($i = 1; $i < $startN; $i++) {
            $cells[] = [
                'date' => null,
                'day' => 0,
                'in_month' => false,
                'slots' => [],
            ];
        }

        $byDate = [];
        for ($dom = 1; $dom <= $daysInMonth; $dom++) {
            $ts = mktime(0, 0, 0, $month, $dom, $year);
            $n = (int) date('N', $ts); // 1–7
            $dateStr = date('Y-m-d', $ts);
            $daySlots = ($n >= 1 && $n <= 6) ? ($byDow[$n] ?? []) : [];
            $daySlots = classTimetableFilterSlotsForDate($daySlots, $dateStr);
            $byDate[$dateStr] = $daySlots;
            $cells[] = [
                'date' => $dateStr,
                'day' => $dom,
                'dow' => $n,
                'in_month' => true,
                'slots' => $daySlots,
            ];
        }

        // Trailing blanks to complete last week (7 columns Mon–Sun)
        while (count($cells) % 7 !== 0) {
            $cells[] = [
                'date' => null,
                'day' => 0,
                'in_month' => false,
                'slots' => [],
            ];
        }

        $weeks = array_chunk($cells, 7);
        $label = date('F Y', mktime(0, 0, 0, $month, 1, $year));

        return [
            'year' => $year,
            'month' => $month,
            'label' => $label,
            'weeks' => $weeks,
            'by_date' => $byDate,
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
    /**
     * @param bool $activeOnly When true (weekly live grid), only active slots.
     *                         When false (month-wise records), include soft-deleted slots too.
     * @return list<array<string,mixed>>
     */
    function listClassTimetableAdmin($conn, ?int $batchId = null, ?int $centreId = null, bool $activeOnly = true): array
    {
        ensureClassTimetableTable($conn);
        $sql = "SELECT ct.*, b.batch_name, b.batch_code, b.start_date AS batch_start_date, b.end_date AS batch_end_date,
                       b.status AS batch_status, c.course_name, c.start_date AS course_start_date, c.end_date AS course_end_date,
                       c.enrollment_status AS course_enrollment_status, c.centre_id AS course_centre_id,
                       c.training_center AS course_training_center, ctr.name AS centre_name
                FROM class_timetable ct
                LEFT JOIN batches b ON b.id = ct.batch_id
                LEFT JOIN courses c ON c.id = b.course_id
                LEFT JOIN centres ctr ON ctr.id = ct.centre_id
                WHERE 1=1";
        $types = '';
        $params = [];
        if ($activeOnly) {
            $sql .= ' AND ct.is_active = 1';
        }
        if ($batchId !== null && $batchId > 0) {
            $sql .= ' AND ct.batch_id = ?';
            $types .= 'i';
            $params[] = $batchId;
        }
        if ($centreId !== null && $centreId > 0) {
            // Match slot centre, or fall back to course centre / training_center name when slot centre is blank
            $centreName = '';
            $nameStmt = $conn->prepare('SELECT name FROM centres WHERE id = ? LIMIT 1');
            if ($nameStmt) {
                $nameStmt->bind_param('i', $centreId);
                $nameStmt->execute();
                $nr = $nameStmt->get_result()->fetch_assoc();
                $nameStmt->close();
                $centreName = trim((string) ($nr['name'] ?? ''));
            }
            if ($centreName !== '') {
                $sql .= ' AND (
                    ct.centre_id = ?
                    OR (ct.centre_id IS NULL AND c.centre_id = ?)
                    OR (ct.centre_id IS NULL AND TRIM(COALESCE(c.training_center, \'\')) = ?)
                )';
                $types .= 'iis';
                $params[] = $centreId;
                $params[] = $centreId;
                $params[] = $centreName;
            } else {
                $sql .= ' AND (ct.centre_id = ? OR (ct.centre_id IS NULL AND c.centre_id = ?))';
                $types .= 'ii';
                $params[] = $centreId;
                $params[] = $centreId;
            }
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
            } else {
                error_log('listClassTimetableAdmin query failed: ' . $conn->error);
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
        $sql = "SELECT ct.*, b.batch_name, b.batch_code, b.start_date AS batch_start_date, b.end_date AS batch_end_date,
                       b.status AS batch_status, c.course_name, c.start_date AS course_start_date, c.end_date AS course_end_date,
                       c.enrollment_status AS course_enrollment_status, c.centre_id AS course_centre_id
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
        $centreId = !empty($data['centre_id']) ? (int) $data['centre_id'] : null;
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

        $check = $conn->prepare(
            'SELECT b.id, c.centre_id AS course_centre_id
             FROM batches b
             LEFT JOIN courses c ON c.id = b.course_id
             WHERE b.id = ? LIMIT 1'
        );
        if (!$check) {
            return ['success' => false, 'message' => 'Could not validate batch.'];
        }
        $check->bind_param('i', $batchId);
        $check->execute();
        $batchRow = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$batchRow) {
            return ['success' => false, 'message' => 'Selected batch was not found.'];
        }
        if (($centreId === null || $centreId <= 0) && !empty($batchRow['course_centre_id'])) {
            $centreId = (int) $batchRow['course_centre_id'];
        }

        $facultyVal = $faculty !== '' ? $faculty : null;
        $roomVal = $room !== '' ? $room : null;
        $notesVal = $notes !== '' ? $notes : null;
        $centreIdVal = ($centreId !== null && $centreId > 0) ? $centreId : 0;

        if ($id !== null && $id > 0) {
            $stmt = $conn->prepare(
                'UPDATE class_timetable
                 SET batch_id = ?, centre_id = NULLIF(?, 0), day_of_week = ?, start_time = ?, end_time = ?, subject = ?,
                     faculty_name = ?, room = ?, notes = ?, is_active = ?
                 WHERE id = ?'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
            $stmt->bind_param(
                'iiissssssii',
                $batchId,
                $centreIdVal,
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
             (batch_id, centre_id, day_of_week, start_time, end_time, subject, faculty_name, room, notes, is_active, created_by)
             VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param(
            'iiissssssis',
            $batchId,
            $centreIdVal,
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
    /**
     * Soft-delete: hide from weekly grid, keep for month-wise records.
     * @return array{success:bool,message:string}
     */
    function deleteClassTimetableSlot($conn, int $id): array
    {
        ensureClassTimetableTable($conn);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid slot.'];
        }
        $stmt = $conn->prepare('UPDATE class_timetable SET is_active = 0 WHERE id = ? AND is_active = 1');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not remove slot.'];
        }
        if ($affected < 1) {
            return ['success' => false, 'message' => 'Slot not found or already removed from weekly grid.'];
        }
        return [
            'success' => true,
            'message' => 'Removed from weekly grid. It remains in month-wise records.',
        ];
    }
}

if (!function_exists('clearClassTimetableForYear')) {
    /**
     * Permanently delete live and archived timetable slots (optional centre filter).
     *
     * @return array{success:bool,message:string,cleared?:int}
     */
    function clearClassTimetableForYear($conn, int $year, int $centreId = 0): array
    {
        ensureClassTimetableTable($conn);
        if ($year < 2000 || $year > 2100) {
            return ['success' => false, 'message' => 'Invalid year.'];
        }

        $sql = "DELETE ct FROM class_timetable ct
                LEFT JOIN batches b ON b.id = ct.batch_id
                LEFT JOIN courses c ON c.id = b.course_id
                WHERE 1=1";
        $types = '';
        $params = [];

        if ($centreId > 0) {
            $centreName = '';
            $nameStmt = $conn->prepare('SELECT name FROM centres WHERE id = ? LIMIT 1');
            if ($nameStmt) {
                $nameStmt->bind_param('i', $centreId);
                $nameStmt->execute();
                $nr = $nameStmt->get_result()->fetch_assoc();
                $nameStmt->close();
                $centreName = trim((string) ($nr['name'] ?? ''));
            }
            if ($centreName !== '') {
                $sql .= ' AND (
                    ct.centre_id = ?
                    OR (ct.centre_id IS NULL AND c.centre_id = ?)
                    OR (ct.centre_id IS NULL AND TRIM(COALESCE(c.training_center, \'\')) = ?)
                )';
                $types = 'iis';
                $params = [$centreId, $centreId, $centreName];
            } else {
                $sql .= ' AND (ct.centre_id = ? OR (ct.centre_id IS NULL AND c.centre_id = ?))';
                $types = 'ii';
                $params = [$centreId, $centreId];
            }
        }

        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
            $stmt->bind_param($types, ...$params);
            $ok = $stmt->execute();
            $cleared = (int) $stmt->affected_rows;
            $err = $stmt->error;
            $stmt->close();
        } else {
            $ok = $conn->query($sql);
            $cleared = $ok ? (int) $conn->affected_rows : 0;
            $err = $conn->error;
        }

        if (!$ok) {
            return ['success' => false, 'message' => 'Could not clear timetable: ' . $err];
        }

        $scope = $centreId > 0 ? ' for the selected centre' : '';
        if ($cleared < 1) {
            return [
                'success' => true,
                'cleared' => 0,
                'message' => "No timetable slots found{$scope}. Already empty.",
            ];
        }
        return [
            'success' => true,
            'cleared' => $cleared,
            'message' => "Deleted {$cleared} slot(s){$scope}, including archived records. This cannot be undone.",
        ];
    }
}
