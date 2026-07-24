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
            join_token VARCHAR(64) NULL,
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
            KEY idx_oc_status (status),
            UNIQUE KEY uq_oc_join_token (join_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('ensureOnlineClassesTable failed: ' . $conn->error);
            return false;
        }

        // Upgrade older installs that lack join_token
        $col = @$conn->query("SHOW COLUMNS FROM online_classes LIKE 'join_token'");
        if (!$col || $col->num_rows === 0) {
            if (!$conn->query("ALTER TABLE online_classes ADD COLUMN join_token VARCHAR(64) NULL AFTER meeting_url")) {
                error_log('ensureOnlineClassesTable add join_token failed: ' . $conn->error);
                // Continue — lookups may still work via meeting_url once column exists later
            } else {
                @$conn->query('ALTER TABLE online_classes ADD UNIQUE KEY uq_oc_join_token (join_token)');
            }
        }

        onlineClassBackfillJoinTokens($conn);

        $ready = true;
        return true;
    }
}

if (!function_exists('onlineClassGenerateJoinToken')) {
    function onlineClassGenerateJoinToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}

if (!function_exists('onlineClassRoomName')) {
    /** Stable Jitsi room name derived from join token. */
    function onlineClassRoomName(string $token): string
    {
        $token = preg_replace('/[^a-zA-Z0-9]/', '', $token) ?? '';
        return 'NIELITBBSR' . strtoupper(substr($token, 0, 24));
    }
}

if (!function_exists('onlineClassLoadVideoConfig')) {
    function onlineClassLoadVideoConfig(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $cfg = __DIR__ . '/online_class_config.php';
        if (is_file($cfg)) {
            require_once $cfg;
        }
        $loaded = true;
    }
}

if (!function_exists('onlineClassJitsiDomain')) {
    function onlineClassJitsiDomain(): string
    {
        onlineClassLoadVideoConfig();
        $domain = defined('ONLINE_CLASS_JITSI_DOMAIN') ? trim((string) ONLINE_CLASS_JITSI_DOMAIN) : 'meet.jit.si';
        $domain = preg_replace('#^https?://#i', '', $domain) ?? '';
        $domain = rtrim($domain, '/');
        return $domain !== '' ? $domain : 'meet.jit.si';
    }
}

if (!function_exists('onlineClassVideoMode')) {
    /**
     * @return 'open'|'embed'
     */
    function onlineClassVideoMode(): string
    {
        onlineClassLoadVideoConfig();
        $mode = defined('ONLINE_CLASS_VIDEO_MODE') ? strtolower(trim((string) ONLINE_CLASS_VIDEO_MODE)) : 'open';
        $domain = strtolower(onlineClassJitsiDomain());

        // Never embed public meet.jit.si — they force a 5-minute disconnect.
        if ($domain === 'meet.jit.si' || $domain === '8x8.vc') {
            return 'open';
        }

        return $mode === 'embed' ? 'embed' : 'open';
    }
}

if (!function_exists('onlineClassExternalRoomUrl')) {
    /**
     * Full-page Jitsi room URL (free when using meet.jit.si without iframe embed).
     */
    function onlineClassExternalRoomUrl(string $roomName, string $displayName = ''): string
    {
        $domain = onlineClassJitsiDomain();
        $roomName = trim($roomName);
        $url = 'https://' . $domain . '/' . rawurlencode($roomName);

        $parts = [];
        if ($displayName !== '') {
            $parts[] = 'userInfo.displayName="' . str_replace(['"', '#'], '', $displayName) . '"';
        }
        $parts[] = 'config.startWithAudioMuted=true';
        $parts[] = 'config.disableDeepLinking=true';

        return $url . '#' . implode('&', $parts);
    }
}

if (!function_exists('onlineClassSiteJoinUrl')) {
    /** Join URL hosted on this website. */
    function onlineClassSiteJoinUrl(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        if (!function_exists('app_url')) {
            require_once __DIR__ . '/url_helper.php';
        }
        return app_url('student/join_class') . '?t=' . rawurlencode($token);
    }
}

if (!function_exists('onlineClassEnrichRow')) {
    /** Attach computed join_url / room_name / display_status. */
    function onlineClassEnrichRow(array $row): array
    {
        $token = trim((string) ($row['join_token'] ?? ''));
        if ($token !== '') {
            $row['join_url'] = onlineClassSiteJoinUrl($token);
            $row['room_name'] = onlineClassRoomName($token);
            // Keep meeting_url aligned with site join link
            $row['meeting_url'] = $row['join_url'];
        } else {
            $row['join_url'] = trim((string) ($row['meeting_url'] ?? ''));
            $row['room_name'] = '';
        }
        $row['display_status'] = onlineClassComputeStatus($row);
        return $row;
    }
}

if (!function_exists('onlineClassBackfillJoinTokens')) {
    function onlineClassBackfillJoinTokens($conn): void
    {
        $res = @$conn->query("SELECT id, meeting_url, join_token FROM online_classes WHERE join_token IS NULL OR join_token = ''");
        if (!$res) {
            return;
        }
        while ($row = $res->fetch_assoc()) {
            $id = (int) $row['id'];
            $token = '';
            // Reuse token already present in meeting_url if possible
            if (preg_match('/[?&]t=([a-fA-F0-9]{16,64})/', (string) ($row['meeting_url'] ?? ''), $m)) {
                $token = strtolower($m[1]);
            }
            if ($token === '') {
                $token = onlineClassGenerateJoinToken();
            }
            $url = onlineClassSiteJoinUrl($token);
            $stmt = $conn->prepare('UPDATE online_classes SET join_token = ?, meeting_url = ?, platform = COALESCE(NULLIF(platform, \'\'), ?) WHERE id = ?');
            if ($stmt) {
                $platform = 'NIELIT Classroom';
                $stmt->bind_param('sssi', $token, $url, $platform, $id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

if (!function_exists('getOnlineClassByJoinToken')) {
    function getOnlineClassByJoinToken($conn, string $token): ?array
    {
        ensureOnlineClassesTable($conn);
        $token = strtolower(trim($token));
        if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/', $token)) {
            return null;
        }

        $sql = "SELECT oc.*, b.batch_name, b.batch_code, c.course_name
                FROM online_classes oc
                LEFT JOIN batches b ON b.id = oc.batch_id
                LEFT JOIN courses c ON c.id = b.course_id
                WHERE oc.join_token = ?
                   OR oc.meeting_url LIKE ?
                   OR oc.meeting_url LIKE ?
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('getOnlineClassByJoinToken prepare failed: ' . $conn->error);
            return null;
        }

        $likePlain = '%t=' . $token . '%';
        $likeEncoded = '%t%3D' . $token . '%';
        $stmt->bind_param('sss', $token, $likePlain, $likeEncoded);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        // Repair: ensure join_token matches the URL token students are using
        $storedToken = strtolower(trim((string) ($row['join_token'] ?? '')));
        if ($storedToken !== $token) {
            $url = onlineClassSiteJoinUrl($token);
            $fix = $conn->prepare('UPDATE online_classes SET join_token = ?, meeting_url = ? WHERE id = ?');
            if ($fix) {
                $id = (int) $row['id'];
                $fix->bind_param('ssi', $token, $url, $id);
                $fix->execute();
                $fix->close();
                $row['join_token'] = $token;
                $row['meeting_url'] = $url;
            }
        }

        return onlineClassEnrichRow($row);
    }
}

if (!function_exists('onlineClassCanJoinNow')) {
    /**
     * Allow join from 30 minutes before start until 30 minutes after scheduled end.
     */
    function onlineClassCanJoinNow(array $row, ?DateTimeInterface $now = null): array
    {
        $status = onlineClassComputeStatus($row, $now);
        if ($status === 'cancelled' || empty($row['is_active'])) {
            return ['allowed' => false, 'reason' => 'This class is not available.'];
        }

        $now = $now ?? new DateTimeImmutable('now');
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) ($row['scheduled_at'] ?? ''));
        if (!$start) {
            $start = new DateTimeImmutable((string) ($row['scheduled_at'] ?? 'now'));
        }
        $duration = max(1, (int) ($row['duration_minutes'] ?? 60));
        $end = $start->modify('+' . $duration . ' minutes');
        $openFrom = $start->modify('-30 minutes');
        $openUntil = $end->modify('+30 minutes');

        if ($now < $openFrom) {
            return [
                'allowed' => false,
                'reason' => 'Classroom opens 30 minutes before the scheduled start (' . $start->format('d M Y, h:i A') . ').',
            ];
        }
        if ($now > $openUntil) {
            return [
                'allowed' => false,
                'reason' => 'This live session has ended. Check Online Classes for a recording link.',
            ];
        }

        return ['allowed' => true, 'reason' => ''];
    }
}

if (!function_exists('onlineClassStudentMayAccess')) {
    function onlineClassStudentMayAccess($conn, array $classRow, string $studentIdStr, ?int $activeRecordId = null): bool
    {
        $batchId = (int) ($classRow['batch_id'] ?? 0);
        if ($batchId <= 0) {
            return false;
        }

        $ids = getStudentOnlineClassBatchIds($conn, $studentIdStr, $activeRecordId);
        if (in_array($batchId, $ids, true)) {
            return true;
        }

        // Allow enrolled course students even when their batch is not assigned yet
        $courseId = (int) ($classRow['course_id'] ?? 0);
        if ($courseId <= 0) {
            $stmt = $conn->prepare('SELECT course_id FROM batches WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $batchId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $courseId = (int) ($row['course_id'] ?? 0);
            }
        }

        if ($courseId <= 0) {
            return false;
        }

        $courseIds = getStudentOnlineClassCourseIds($conn, $studentIdStr);
        return in_array($courseId, $courseIds, true);
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
        return $row ? onlineClassEnrichRow($row) : null;
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
                $rows[] = onlineClassEnrichRow($row);
            }
            $stmt->close();
        } else {
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = onlineClassEnrichRow($row);
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
        return listOnlineClassesForStudentScope($conn, $batchIds, []);
    }
}

if (!function_exists('getStudentOnlineClassCourseIds')) {
    /**
     * Active course IDs for a student login (includes enrollments with no batch yet).
     *
     * @return array<int, int>
     */
    function getStudentOnlineClassCourseIds($conn, string $studentIdStr): array
    {
        $ids = [];
        $studentIdStr = trim($studentIdStr);
        if ($studentIdStr === '') {
            return [];
        }

        $stmt = $conn->prepare(
            "SELECT DISTINCT course_id FROM students
             WHERE student_id = ?
               AND course_id IS NOT NULL AND course_id > 0
               AND LOWER(COALESCE(status, '')) NOT IN ('rejected', 'inactive', 'cancelled')"
        );
        if ($stmt) {
            $stmt->bind_param('s', $studentIdStr);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $cid = (int) ($row['course_id'] ?? 0);
                if ($cid > 0) {
                    $ids[$cid] = $cid;
                }
            }
            $stmt->close();
        }

        return array_values($ids);
    }
}

if (!function_exists('listOnlineClassesForStudentScope')) {
    /**
     * Classes visible to a student: assigned batches OR batches under enrolled courses.
     * (So Test_course classes show even when batch is still "Not assigned".)
     *
     * @param array<int, int> $batchIds
     * @param array<int, int> $courseIds
     * @return array<int, array<string, mixed>>
     */
    function listOnlineClassesForStudentScope($conn, array $batchIds, array $courseIds): array
    {
        ensureOnlineClassesTable($conn);
        $batchIds = array_values(array_unique(array_filter(array_map('intval', $batchIds), static function ($id) {
            return $id > 0;
        })));
        $courseIds = array_values(array_unique(array_filter(array_map('intval', $courseIds), static function ($id) {
            return $id > 0;
        })));

        if (empty($batchIds) && empty($courseIds)) {
            return [];
        }

        $where = [];
        if (!empty($batchIds)) {
            $where[] = 'oc.batch_id IN (' . implode(',', $batchIds) . ')';
        }
        if (!empty($courseIds)) {
            $where[] = 'b.course_id IN (' . implode(',', $courseIds) . ')';
        }

        $sql = "SELECT oc.*, b.batch_name, b.batch_code, b.course_id, c.course_name
                FROM online_classes oc
                LEFT JOIN batches b ON b.id = oc.batch_id
                LEFT JOIN courses c ON c.id = b.course_id
                WHERE oc.is_active = 1
                  AND oc.status != 'cancelled'
                  AND (" . implode(' OR ', $where) . ")
                ORDER BY oc.scheduled_at DESC, oc.id DESC";

        $result = $conn->query($sql);
        if (!$result) {
            error_log('listOnlineClassesForStudentScope query failed: ' . $conn->error);
            return [];
        }

        $rows = [];
        $seen = [];
        while ($row = $result->fetch_assoc()) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $rows[] = onlineClassEnrichRow($row);
        }
        return $rows;
    }
}

if (!function_exists('listOnlineClassesForStudent')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function listOnlineClassesForStudent($conn, string $studentIdStr, ?int $activeRecordId = null): array
    {
        $batchIds = getStudentOnlineClassBatchIds($conn, $studentIdStr, $activeRecordId);
        $courseIds = getStudentOnlineClassCourseIds($conn, $studentIdStr);
        return listOnlineClassesForStudentScope($conn, $batchIds, $courseIds);
    }
}

if (!function_exists('saveOnlineClass')) {
    /**
     * Create or update an online class.
     * Meeting join links are auto-generated on this site (join_token).
     *
     * @param array<string, mixed> $data
     * @return array{success:bool,message:string,id?:int,join_url?:string}
     */
    function saveOnlineClass($conn, array $data, ?int $id = null): array
    {
        ensureOnlineClassesTable($conn);

        $batchId = (int) ($data['batch_id'] ?? 0);
        $title = trim(strip_tags((string) ($data['title'] ?? '')));
        $description = trim(strip_tags((string) ($data['description'] ?? '')));
        $scheduledAt = trim((string) ($data['scheduled_at'] ?? ''));
        $duration = max(15, min(480, (int) ($data['duration_minutes'] ?? 60)));
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

        $scheduledAt = str_replace('T', ' ', $scheduledAt);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $scheduledAt)) {
            $scheduledAt .= ':00';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $scheduledAt);
        if (!$dt) {
            return ['success' => false, 'message' => 'Invalid date/time format.'];
        }
        $scheduledAt = $dt->format('Y-m-d H:i:s');

        if ($recordingUrl !== '' && !onlineClassIsValidUrl($recordingUrl)) {
            return ['success' => false, 'message' => 'Recording link must be a valid URL (e.g. Google Drive).'];
        }
        if (mb_strlen($title) > 255) {
            return ['success' => false, 'message' => 'Title is too long (max 255 characters).'];
        }
        if ($platform === '') {
            $platform = 'NIELIT Classroom';
        }
        if (mb_strlen($platform) > 50) {
            $platform = mb_substr($platform, 0, 50);
        }

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

        $descVal = $description;
        $recVal = $recordingUrl;
        $platVal = $platform;

        if ($id !== null && $id > 0) {
            $existing = getOnlineClassById($conn, $id);
            if (!$existing) {
                return ['success' => false, 'message' => 'Class not found.'];
            }
            $token = trim((string) ($existing['join_token'] ?? ''));
            if ($token === '') {
                $token = onlineClassGenerateJoinToken();
            }
            $meetingUrl = onlineClassSiteJoinUrl($token);

            $stmt = $conn->prepare(
                "UPDATE online_classes
                 SET batch_id=?, title=?, description=?, scheduled_at=?, duration_minutes=?,
                     meeting_url=?, join_token=?, recording_url=?, platform=?, status=?, is_active=?
                 WHERE id=?"
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
            $stmt->bind_param(
                'isssisssssii',
                $batchId,
                $title,
                $descVal,
                $scheduledAt,
                $duration,
                $meetingUrl,
                $token,
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
            return [
                'success' => true,
                'message' => 'Online class updated successfully.',
                'id' => $id,
                'join_url' => $meetingUrl,
            ];
        }

        $token = onlineClassGenerateJoinToken();
        $meetingUrl = onlineClassSiteJoinUrl($token);

        $stmt = $conn->prepare(
            "INSERT INTO online_classes
             (batch_id, title, description, scheduled_at, duration_minutes, meeting_url, join_token, recording_url, platform, status, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param(
            'isssisssssis',
            $batchId,
            $title,
            $descVal,
            $scheduledAt,
            $duration,
            $meetingUrl,
            $token,
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
        return [
            'success' => true,
            'message' => 'Online class created. Join link generated on your site.',
            'id' => $newId,
            'join_url' => $meetingUrl,
        ];
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
        $studentIdStr = trim($studentIdStr);
        if ($studentIdStr === '') {
            return [];
        }

        $recordIds = [];
        if ($activeRecordId && $activeRecordId > 0) {
            $recordIds[$activeRecordId] = $activeRecordId;
        }

        // All enrollment rows for this login
        $stmt = $conn->prepare(
            "SELECT id, batch_id, status FROM students
             WHERE student_id = ?"
        );
        if ($stmt) {
            $stmt->bind_param('s', $studentIdStr);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $status = strtolower(trim((string) ($row['status'] ?? '')));
                if (in_array($status, ['rejected', 'inactive', 'cancelled'], true)) {
                    continue;
                }
                $rid = (int) ($row['id'] ?? 0);
                if ($rid > 0) {
                    $recordIds[$rid] = $rid;
                }
                $bid = (int) ($row['batch_id'] ?? 0);
                if ($bid > 0) {
                    $ids[$bid] = $bid;
                }
            }
            $stmt->close();
        }

        $batchFunctions = dirname(__DIR__) . '/batch_module/includes/batch_functions.php';
        if (is_file($batchFunctions)) {
            require_once $batchFunctions;
            if (function_exists('getBatchesForStudentRecord')) {
                foreach ($recordIds as $rid) {
                    foreach (getBatchesForStudentRecord($conn, (int) $rid) as $b) {
                        $bid = (int) ($b['id'] ?? 0);
                        if ($bid > 0) {
                            $ids[$bid] = $bid;
                        }
                    }
                }
            }
        }

        // batch_students via students.id
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

if (!function_exists('getStudentOnlineClassBatchLabels')) {
    /**
     * @param array<int, int> $batchIds
     * @return array<int, string>
     */
    function getStudentOnlineClassBatchLabels($conn, array $batchIds): array
    {
        $batchIds = array_values(array_unique(array_filter(array_map('intval', $batchIds))));
        if (empty($batchIds)) {
            return [];
        }
        $inList = implode(',', $batchIds);
        $labels = [];
        $res = $conn->query("SELECT id, batch_name, batch_code FROM batches WHERE id IN ($inList)");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $label = trim((string) ($row['batch_name'] ?? ''));
                if (!empty($row['batch_code'])) {
                    $label .= ' (' . $row['batch_code'] . ')';
                }
                $labels[] = $label !== '' ? $label : ('Batch #' . $row['id']);
            }
        }
        return $labels;
    }
}
