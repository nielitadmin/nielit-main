<?php
/**
 * Online Classes helper — schedule, meeting links, Drive recording links.
 */

if (!function_exists('ensureOnlineClassesTable')) {
    function ensureOnlineClassesTable($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS online_classes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            batch_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            scheduled_at DATETIME NOT NULL,
            duration_minutes INT NOT NULL DEFAULT 60,
            meeting_url VARCHAR(1000) NOT NULL,
            recording_url VARCHAR(1000) NULL,
            platform VARCHAR(50) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'scheduled',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_oc_batch (batch_id),
            KEY idx_oc_scheduled (scheduled_at),
            KEY idx_oc_active (is_active),
            KEY idx_oc_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('ensureOnlineClassesTable failed: ' . $conn->error);
            return false;
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('onlineClassSanitizeUrl')) {
    function onlineClassSanitizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }
}

if (!function_exists('onlineClassIsValidUrl')) {
    function onlineClassIsValidUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }
}

if (!function_exists('onlineClassComputeStatus')) {
    /**
     * Derive display status from stored status + schedule window.
     * Manual 'cancelled' always wins.
     */
    function onlineClassComputeStatus(array $row, ?DateTimeInterface $now = null): string
    {
        $stored = strtolower(trim((string) ($row['status'] ?? 'scheduled')));
        if ($stored === 'cancelled') {
            return 'cancelled';
        }

        $now = $now ?? new DateTimeImmutable('now');
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) ($row['scheduled_at'] ?? ''));
        if (!$start) {
            $start = new DateTimeImmutable((string) ($row['scheduled_at'] ?? 'now'));
        }
        $duration = max(1, (int) ($row['duration_minutes'] ?? 60));
        $end = $start->modify('+' . $duration . ' minutes');

        if ($now < $start) {
            return 'upcoming';
        }
        if ($now >= $start && $now < $end) {
            return 'live';
        }
        return 'completed';
    }
}

if (!function_exists('onlineClassStatusBadgeClass')) {
    function onlineClassStatusBadgeClass(string $status): string
    {
        switch (strtolower($status)) {
            case 'live':
                return 'badge-danger';
            case 'upcoming':
                return 'badge-primary';
            case 'completed':
                return 'badge-success';
            case 'cancelled':
                return 'badge-secondary';
            default:
                return 'badge-info';
        }
    }
}

if (!function_exists('onlineClassStatusLabel')) {
    function onlineClassStatusLabel(string $status): string
    {
        $map = [
            'upcoming' => 'Upcoming',
            'live' => 'Live Now',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'scheduled' => 'Scheduled',
        ];
        return $map[strtolower($status)] ?? ucfirst($status);
    }
}

if (!function_exists('getOnlineClassById')) {
    function getOnlineClassById($conn, int $id): ?array
    {
        ensureOnlineClassesTable($conn);
        $stmt = $conn->prepare(
            "SELECT oc.*, b.batch_name, b.batch_code, c.course_name
             FROM online_classes oc
             LEFT JOIN batches b ON b.id = oc.batch_id
             LEFT JOIN courses c ON c.id = b.course_id
             WHERE oc.id = ? LIMIT 1"
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

if (!function_exists('listOnlineClassesAdmin')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function listOnlineClassesAdmin($conn, ?int $batchId = null, string $filter = 'all'): array
    {
        ensureOnlineClassesTable($conn);

        $sql = "SELECT oc.*, b.batch_name, b.batch_code, c.course_name
                FROM online_classes oc
                LEFT JOIN batches b ON b.id = oc.batch_id
                LEFT JOIN courses c ON c.id = b.course_id
                WHERE 1=1";
        $types = '';
        $params = [];

        if ($batchId !== null && $batchId > 0) {
            $sql .= ' AND oc.batch_id = ?';
            $types .= 'i';
            $params[] = $batchId;
        }

        if ($filter === 'active') {
            $sql .= ' AND oc.is_active = 1 AND oc.status != \'cancelled\'';
        } elseif ($filter === 'cancelled') {
            $sql .= ' AND oc.status = \'cancelled\'';
        }

        $sql .= ' ORDER BY oc.scheduled_at DESC, oc.id DESC';

        $rows = [];
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['display_status'] = onlineClassComputeStatus($row);
                $rows[] = $row;
            }
            $stmt->close();
        } else {
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $row['display_status'] = onlineClassComputeStatus($row);
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }
}

if (!function_exists('listOnlineClassesForBatches')) {
    /**
     * Student-facing list for one or more batch IDs.
     *
     * @param array<int, int> $batchIds
     * @return array<int, array<string, mixed>>
     */
    function listOnlineClassesForBatches($conn, array $batchIds): array
    {
        ensureOnlineClassesTable($conn);
        $batchIds = array_values(array_unique(array_filter(array_map('intval', $batchIds))));
        if (empty($batchIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
        $sql = "SELECT oc.*, b.batch_name, b.batch_code, c.course_name
                FROM online_classes oc
                LEFT JOIN batches b ON b.id = oc.batch_id
                LEFT JOIN courses c ON c.id = b.course_id
                WHERE oc.is_active = 1
                  AND oc.status != 'cancelled'
                  AND oc.batch_id IN ($placeholders)
                ORDER BY oc.scheduled_at DESC, oc.id DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $types = str_repeat('i', count($batchIds));
        $stmt->bind_param($types, ...$batchIds);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $row['display_status'] = onlineClassComputeStatus($row);
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('saveOnlineClass')) {
    /**
     * Create or update an online class.
     *
     * @param array<string, mixed> $data
     * @return array{success:bool,message:string,id?:int}
     */
    function saveOnlineClass($conn, array $data, ?int $id = null): array
    {
        ensureOnlineClassesTable($conn);

        $batchId = (int) ($data['batch_id'] ?? 0);
        $title = trim(strip_tags((string) ($data['title'] ?? '')));
        $description = trim(strip_tags((string) ($data['description'] ?? '')));
        $scheduledAt = trim((string) ($data['scheduled_at'] ?? ''));
        $duration = max(15, min(480, (int) ($data['duration_minutes'] ?? 60)));
        $meetingUrl = onlineClassSanitizeUrl((string) ($data['meeting_url'] ?? ''));
        $recordingUrl = onlineClassSanitizeUrl((string) ($data['recording_url'] ?? ''));
        $platform = trim(strip_tags((string) ($data['platform'] ?? '')));
        $status = strtolower(trim((string) ($data['status'] ?? 'scheduled')));
        $isActive = !empty($data['is_active']) ? 1 : 0;
        $createdBy = trim((string) ($data['created_by'] ?? ''));

        if (!in_array($status, ['scheduled', 'cancelled'], true)) {
            $status = 'scheduled';
        }

        if ($batchId <= 0) {
            return ['success' => false, 'message' => 'Please select a batch.'];
        }
        if ($title === '') {
            return ['success' => false, 'message' => 'Class title is required.'];
        }
        if ($scheduledAt === '') {
            return ['success' => false, 'message' => 'Date and time are required.'];
        }

        // Normalize datetime-local (YYYY-MM-DDTHH:MM) → MySQL DATETIME
        $scheduledAt = str_replace('T', ' ', $scheduledAt);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $scheduledAt)) {
            $scheduledAt .= ':00';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $scheduledAt);
        if (!$dt) {
            return ['success' => false, 'message' => 'Invalid date/time format.'];
        }
        $scheduledAt = $dt->format('Y-m-d H:i:s');

        if (!onlineClassIsValidUrl($meetingUrl)) {
            return ['success' => false, 'message' => 'Please enter a valid meeting/join URL.'];
        }
        if ($recordingUrl !== '' && !onlineClassIsValidUrl($recordingUrl)) {
            return ['success' => false, 'message' => 'Recording link must be a valid URL (e.g. Google Drive).'];
        }
        if (mb_strlen($title) > 255) {
            return ['success' => false, 'message' => 'Title is too long (max 255 characters).'];
        }
        if (mb_strlen($platform) > 50) {
            $platform = mb_substr($platform, 0, 50);
        }

        // Confirm batch exists
        $check = $conn->prepare('SELECT id FROM batches WHERE id = ? LIMIT 1');
        if ($check) {
            $check->bind_param('i', $batchId);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                $check->close();
                return ['success' => false, 'message' => 'Selected batch was not found.'];
            }
            $check->close();
        }

        if ($id !== null && $id > 0) {
            $stmt = $conn->prepare(
                "UPDATE online_classes
                 SET batch_id=?, title=?, description=?, scheduled_at=?, duration_minutes=?,
                     meeting_url=?, recording_url=?, platform=?, status=?, is_active=?
                 WHERE id=?"
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
            $descVal = $description;
            $recVal = $recordingUrl;
            $platVal = $platform;
            $stmt->bind_param(
                'isssissssii',
                $batchId,
                $title,
                $descVal,
                $scheduledAt,
                $duration,
                $meetingUrl,
                $recVal,
                $platVal,
                $status,
                $isActive,
                $id
            );
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                return ['success' => false, 'message' => 'Failed to update class: ' . $err];
            }
            $stmt->close();
            return ['success' => true, 'message' => 'Online class updated successfully.', 'id' => $id];
        }

        $stmt = $conn->prepare(
            "INSERT INTO online_classes
             (batch_id, title, description, scheduled_at, duration_minutes, meeting_url, recording_url, platform, status, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $descVal = $description;
        $recVal = $recordingUrl;
        $platVal = $platform;
        $stmt->bind_param(
            'isssissssis',
            $batchId,
            $title,
            $descVal,
            $scheduledAt,
            $duration,
            $meetingUrl,
            $recVal,
            $platVal,
            $status,
            $isActive,
            $createdBy
        );
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to create class: ' . $err];
        }
        $newId = (int) $stmt->insert_id;
        $stmt->close();
        return ['success' => true, 'message' => 'Online class created successfully.', 'id' => $newId];
    }
}

if (!function_exists('deleteOnlineClass')) {
    function deleteOnlineClass($conn, int $id): array
    {
        ensureOnlineClassesTable($conn);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid class ID.'];
        }
        $stmt = $conn->prepare('DELETE FROM online_classes WHERE id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to delete: ' . $err];
        }
        $stmt->close();
        return ['success' => true, 'message' => 'Online class deleted.'];
    }
}

if (!function_exists('getStudentOnlineClassBatchIds')) {
    /**
     * Resolve all batch IDs linked to a student login ID.
     *
     * @return array<int, int>
     */
    function getStudentOnlineClassBatchIds($conn, string $studentIdStr, ?int $activeRecordId = null): array
    {
        $ids = [];

        // From enrollment rows (students.batch_id)
        $stmt = $conn->prepare(
            "SELECT DISTINCT batch_id FROM students
             WHERE student_id = ? AND batch_id IS NOT NULL AND batch_id > 0
               AND LOWER(COALESCE(status, '')) NOT IN ('rejected', 'inactive')"
        );
        if ($stmt) {
            $stmt->bind_param('s', $studentIdStr);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $ids[(int) $row['batch_id']] = (int) $row['batch_id'];
            }
            $stmt->close();
        }

        // From batch_students junction
        $batchFunctions = dirname(__DIR__) . '/batch_module/includes/batch_functions.php';
        if (is_file($batchFunctions)) {
            require_once $batchFunctions;
            if ($activeRecordId && $activeRecordId > 0 && function_exists('getBatchesForStudentRecord')) {
                foreach (getBatchesForStudentRecord($conn, $activeRecordId) as $b) {
                    $bid = (int) ($b['id'] ?? 0);
                    if ($bid > 0) {
                        $ids[$bid] = $bid;
                    }
                }
            }
        }

        // Also join batch_students via students.id for this login
        $sql = "SELECT DISTINCT bs.batch_id
                FROM batch_students bs
                INNER JOIN students s ON s.id = bs.student_id
                WHERE s.student_id = ? AND bs.batch_id > 0";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $studentIdStr);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $ids[(int) $row['batch_id']] = (int) $row['batch_id'];
            }
            $stmt->close();
        }

        // student_record_id path if column exists
        $colCheck = @$conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $sql2 = "SELECT DISTINCT bs.batch_id
                     FROM batch_students bs
                     INNER JOIN students s ON s.id = bs.student_record_id
                     WHERE s.student_id = ? AND bs.batch_id > 0";
            $stmt2 = $conn->prepare($sql2);
            if ($stmt2) {
                $stmt2->bind_param('s', $studentIdStr);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                while ($row = $res2->fetch_assoc()) {
                    $ids[(int) $row['batch_id']] = (int) $row['batch_id'];
                }
                $stmt2->close();
            }
        }

        return array_values($ids);
    }
}
