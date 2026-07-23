<?php
/**
 * System-wide activity logger for admin Activity module.
 * Safe to call from anywhere — failures never break the main request.
 * All activity times use Asia/Kolkata (IST).
 */

if (!function_exists('activityAppTimezone')) {
    function activityAppTimezone(): string
    {
        return 'Asia/Kolkata';
    }
}

if (!function_exists('activityEnsureAppTimezone')) {
    function activityEnsureAppTimezone(): void
    {
        @date_default_timezone_set(activityAppTimezone());
    }
}

if (!function_exists('activitySetDbTimezone')) {
    function activitySetDbTimezone(?mysqli $conn = null): void
    {
        if (!$conn) {
            global $conn;
        }
        if ($conn instanceof mysqli) {
            // IST = UTC+05:30 — MySQL TIMESTAMP converts to/from this session zone
            @$conn->query("SET time_zone = '+05:30'");
        }
        activityEnsureAppTimezone();
    }
}

if (!function_exists('formatActivityDateTime')) {
    /**
     * Format activity timestamp for display in Asia/Kolkata.
     * Handles values already in IST (after SET time_zone) or UTC wall-clock strings.
     */
    function formatActivityDateTime($createdAt, string $format = 'd M Y, h:i A'): string
    {
        activityEnsureAppTimezone();
        $raw = trim((string) $createdAt);
        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return '—';
        }

        try {
            // Prefer interpreting DB value in IST (session time_zone +05:30 on fetch).
            $dt = new DateTime($raw, new DateTimeZone(activityAppTimezone()));
            return $dt->format($format);
        } catch (Throwable $e) {
            $ts = strtotime($raw);
            return $ts ? date($format, $ts) : $raw;
        }
    }
}

if (!function_exists('ensureActivityLogsTable')) {
    function ensureActivityLogsTable(?mysqli $conn = null): bool
    {
        if (!$conn) {
            global $conn;
        }
        if (!$conn instanceof mysqli) {
            return false;
        }

        activitySetDbTimezone($conn);

        static $ready = null;
        if ($ready === true) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_type ENUM('admin','student','system') NOT NULL DEFAULT 'system',
            actor_id VARCHAR(100) DEFAULT NULL,
            actor_name VARCHAR(255) DEFAULT NULL,
            actor_role VARCHAR(100) DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(100) DEFAULT NULL,
            entity_id VARCHAR(100) DEFAULT NULL,
            entity_name VARCHAR(255) DEFAULT NULL,
            description TEXT NOT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            result ENUM('success','failure','info') NOT NULL DEFAULT 'success',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_activity_created (created_at),
            KEY idx_activity_actor_type (actor_type),
            KEY idx_activity_action (action),
            KEY idx_activity_entity (entity_type, entity_id),
            KEY idx_activity_actor_id (actor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $ok = (bool) $conn->query($sql);
            $ready = $ok;
            return $ok;
        } catch (Throwable $e) {
            error_log('ensureActivityLogsTable: ' . $e->getMessage());
            $ready = false;
            return false;
        }
    }
}

if (!function_exists('activityClientIp')) {
    function activityClientIp(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];
        foreach ($candidates as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }
            if (strpos($raw, ',') !== false) {
                $raw = trim(explode(',', $raw)[0]);
            }
            if (filter_var($raw, FILTER_VALIDATE_IP)) {
                return $raw;
            }
        }
        return '';
    }
}

if (!function_exists('activityResolveActor')) {
    /**
     * @return array{actor_type:string,actor_id:?string,actor_name:?string,actor_role:?string}
     */
    function activityResolveActor(array $override = []): array
    {
        $actorType = $override['actor_type'] ?? null;
        $actorId = $override['actor_id'] ?? null;
        $actorName = $override['actor_name'] ?? null;
        $actorRole = $override['actor_role'] ?? null;

        if ($actorType === null) {
            if (!empty($_SESSION['admin']) || !empty($_SESSION['admin_id'])) {
                $actorType = 'admin';
            } elseif (!empty($_SESSION['student_id'])) {
                $actorType = 'student';
            } else {
                $actorType = 'system';
            }
        }

        if ($actorType === 'admin') {
            $actorId = $actorId ?? (isset($_SESSION['admin_id']) ? (string) $_SESSION['admin_id'] : null);
            $actorName = $actorName ?? ($_SESSION['admin'] ?? null);
            $actorRole = $actorRole ?? ($_SESSION['admin_role'] ?? null);
        } elseif ($actorType === 'student') {
            $actorId = $actorId ?? ($_SESSION['student_id'] ?? null);
            $actorName = $actorName ?? ($_SESSION['student_name'] ?? $actorId);
        }

        return [
            'actor_type' => $actorType,
            'actor_id' => $actorId !== null && $actorId !== '' ? (string) $actorId : null,
            'actor_name' => $actorName !== null && $actorName !== '' ? (string) $actorName : null,
            'actor_role' => $actorRole !== null && $actorRole !== '' ? (string) $actorRole : null,
        ];
    }
}

if (!function_exists('logActivity')) {
    /**
     * Log one system activity.
     *
     * Keys: action (required), description (required),
     * actor_type, actor_id, actor_name, actor_role,
     * entity_type, entity_id, entity_name, details (array|string), result
     */
    function logActivity(?mysqli $conn, array $data): bool
    {
        try {
            if (!$conn) {
                global $conn;
            }
            if (!$conn instanceof mysqli) {
                return false;
            }
            activitySetDbTimezone($conn);
            if (!ensureActivityLogsTable($conn)) {
                return false;
            }

            $action = trim((string) ($data['action'] ?? ''));
            $description = trim((string) ($data['description'] ?? ''));
            if ($action === '' || $description === '') {
                return false;
            }

            $actor = activityResolveActor($data);
            $entityType = array_key_exists('entity_type', $data) && $data['entity_type'] !== null && $data['entity_type'] !== ''
                ? (string) $data['entity_type'] : '';
            $entityId = array_key_exists('entity_id', $data) && $data['entity_id'] !== null && $data['entity_id'] !== ''
                ? (string) $data['entity_id'] : '';
            $entityName = array_key_exists('entity_name', $data) && $data['entity_name'] !== null && $data['entity_name'] !== ''
                ? (string) $data['entity_name'] : '';
            $result = (string) ($data['result'] ?? 'success');
            if (!in_array($result, ['success', 'failure', 'info'], true)) {
                $result = 'info';
            }

            $details = $data['details'] ?? '';
            if (is_array($details)) {
                $details = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($details === false) {
                    $details = '';
                }
            } else {
                $details = (string) $details;
            }

            $ip = activityClientIp();
            $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
            activityEnsureAppTimezone();
            $createdAt = date('Y-m-d H:i:s');

            $stmt = $conn->prepare(
                "INSERT INTO activity_logs
                (actor_type, actor_id, actor_name, actor_role, action, entity_type, entity_id, entity_name, description, details, ip_address, user_agent, result, created_at)
                VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, ?)"
            );
            if (!$stmt) {
                error_log('logActivity prepare failed: ' . $conn->error);
                return false;
            }

            $actorType = (string) $actor['actor_type'];
            $actorId = (string) ($actor['actor_id'] ?? '');
            $actorName = (string) ($actor['actor_name'] ?? '');
            $actorRole = (string) ($actor['actor_role'] ?? '');
            $action = (string) $action;
            $description = (string) $description;

            $stmt->bind_param(
                'ssssssssssssss',
                $actorType,
                $actorId,
                $actorName,
                $actorRole,
                $action,
                $entityType,
                $entityId,
                $entityName,
                $description,
                $details,
                $ip,
                $ua,
                $result,
                $createdAt
            );
            $ok = $stmt->execute();
            if (!$ok) {
                error_log('logActivity insert failed: ' . $stmt->error);
            }
            $stmt->close();
            return $ok;
        } catch (Throwable $e) {
            error_log('logActivity exception: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('logActivityEvent')) {
    /** Convenience wrapper using global $conn */
    function logActivityEvent(string $action, string $description, array $extra = []): bool
    {
        global $conn;
        $extra['action'] = $action;
        $extra['description'] = $description;
        return logActivity($conn instanceof mysqli ? $conn : null, $extra);
    }
}

if (!function_exists('fetchActivityLogs')) {
    /**
     * @return array{rows:array,total:int}
     */
    function fetchActivityLogs(mysqli $conn, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        activitySetDbTimezone($conn);
        if (!ensureActivityLogsTable($conn)) {
            return ['rows' => [], 'total' => 0];
        }

        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($filters['actor_type'])) {
            $where[] = 'actor_type = ?';
            $params[] = $filters['actor_type'];
            $types .= 's';
        }
        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
            $types .= 's';
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = ?';
            $params[] = $filters['entity_type'];
            $types .= 's';
        }
        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $where[] = '(actor_name LIKE ? OR actor_id LIKE ? OR description LIKE ? OR entity_name LIKE ? OR action LIKE ?)';
            array_push($params, $q, $q, $q, $q, $q);
            $types .= 'sssss';
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        $whereSql = implode(' AND ', $where);
        $total = 0;
        $countSql = "SELECT COUNT(*) AS c FROM activity_logs WHERE {$whereSql}";
        $countStmt = $conn->prepare($countSql);
        if ($countStmt) {
            if ($types !== '') {
                $countStmt->bind_param($types, ...$params);
            }
            $countStmt->execute();
            $total = (int) ($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
            $countStmt->close();
        }

        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $sql = "SELECT * FROM activity_logs WHERE {$whereSql} ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $rows = [];
        if ($stmt) {
            $bindTypes = $types . 'ii';
            $bindParams = array_merge($params, [$limit, $offset]);
            $stmt->bind_param($bindTypes, ...$bindParams);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }

        return ['rows' => $rows, 'total' => $total];
    }
}

if (!function_exists('activityActionLabels')) {
    function activityActionLabels(): array
    {
        return [
            'admin_login' => 'Admin Login',
            'admin_logout' => 'Admin Logout',
            'student_login' => 'Student Login',
            'student_logout' => 'Student Logout',
            'student_register' => 'Student Registration',
            'course_apply' => 'Course Application',
            'student_approve' => 'Student Approved',
            'student_deapprove' => 'Student De-approved',
            'student_reject' => 'Student Rejected',
            'batch_assign' => 'Batch Assignment',
            'course_create' => 'Course Created',
            'course_update' => 'Course Updated',
            'course_delete' => 'Course Deleted',
            'batch_create' => 'Batch Created',
            'batch_update' => 'Batch Updated',
            'batch_delete' => 'Batch Deleted',
            'centre_create' => 'Centre Created',
            'centre_update' => 'Centre Updated',
            'theme_create' => 'Theme Created',
            'theme_update' => 'Theme Updated',
            'homepage_update' => 'Homepage Updated',
            'other' => 'Other',
        ];
    }
}
