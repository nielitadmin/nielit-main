<?php
/**
 * Student / admin activity hooks for n8n, Make, or custom automations.
 * Configure webhook URL in API & Automation (master admin).
 */

if (!function_exists('ensurePortalAutomationSettingsTable')) {
    function ensurePortalAutomationSettingsTable(mysqli $conn): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS portal_automation_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            webhook_enabled TINYINT(1) NOT NULL DEFAULT 0,
            webhook_url VARCHAR(500) DEFAULT NULL,
            webhook_secret VARCHAR(255) DEFAULT NULL,
            notify_student_actions TINYINT(1) NOT NULL DEFAULT 1,
            chat_webhook_enabled TINYINT(1) NOT NULL DEFAULT 0,
            chat_webhook_url VARCHAR(500) DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $ok = (bool) $conn->query($sql);
        if ($ok) {
            $conn->query('INSERT IGNORE INTO portal_automation_settings (id) VALUES (1)');
            ensurePortalAutomationChatColumns($conn);
        }
        return $ok;
    }
}

if (!function_exists('ensurePortalAutomationChatColumns')) {
    function ensurePortalAutomationChatColumns(mysqli $conn): void
    {
        $check = $conn->query("SHOW COLUMNS FROM portal_automation_settings LIKE 'chat_webhook_enabled'");
        if ($check && $check->num_rows > 0) {
            return;
        }
        $conn->query(
            'ALTER TABLE portal_automation_settings
             ADD COLUMN chat_webhook_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER notify_student_actions,
             ADD COLUMN chat_webhook_url VARCHAR(500) NULL DEFAULT NULL AFTER chat_webhook_enabled'
        );
    }
}

if (!function_exists('getPortalAutomationSettings')) {
    /** @return array<string, mixed> */
    function getPortalAutomationSettings(mysqli $conn): array
    {
        ensurePortalAutomationSettingsTable($conn);
        ensurePortalAutomationChatColumns($conn);
        $res = $conn->query('SELECT * FROM portal_automation_settings WHERE id = 1 LIMIT 1');
        if (!$res || !($row = $res->fetch_assoc())) {
            return [
                'webhook_enabled' => 0,
                'webhook_url' => '',
                'webhook_secret' => '',
                'notify_student_actions' => 1,
                'chat_webhook_enabled' => 0,
                'chat_webhook_url' => '',
            ];
        }
        return $row;
    }
}

if (!function_exists('savePortalAutomationSettings')) {
    function savePortalAutomationSettings(mysqli $conn, array $data): bool
    {
        ensurePortalAutomationSettingsTable($conn);
        ensurePortalAutomationChatColumns($conn);
        $enabled = !empty($data['webhook_enabled']) ? 1 : 0;
        $url = trim((string) ($data['webhook_url'] ?? ''));
        $secret = trim((string) ($data['webhook_secret'] ?? ''));
        $notify = !isset($data['notify_student_actions']) || !empty($data['notify_student_actions']) ? 1 : 0;
        $chatEnabled = !empty($data['chat_webhook_enabled']) ? 1 : 0;
        $chatUrl = trim((string) ($data['chat_webhook_url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        if ($chatUrl !== '' && !preg_match('#^https?://#i', $chatUrl)) {
            $chatUrl = 'https://' . $chatUrl;
        }
        $stmt = $conn->prepare(
            'UPDATE portal_automation_settings
             SET webhook_enabled = ?, webhook_url = NULLIF(?, \'\'), webhook_secret = NULLIF(?, \'\'), notify_student_actions = ?,
                 chat_webhook_enabled = ?, chat_webhook_url = NULLIF(?, \'\')
             WHERE id = 1'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('issiis', $enabled, $url, $secret, $notify, $chatEnabled, $chatUrl);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('automationStudentActionPrefixes')) {
    /** @return list<string> */
    function automationStudentActionPrefixes(): array
    {
        return [
            'student_',
        ];
    }
}

if (!function_exists('automationShouldDispatchActivity')) {
    function automationShouldDispatchActivity(string $action, string $entityType, array $details = []): bool
    {
        $action = strtolower(trim($action));
        $entityType = strtolower(trim($entityType));
        if ($entityType === 'student') {
            return true;
        }
        foreach (automationStudentActionPrefixes() as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }
        $source = strtolower(trim((string) ($details['source'] ?? '')));
        if ($source === 'student_record_inspector') {
            return true;
        }
        return false;
    }
}

if (!function_exists('automationDispatchActivityEvent')) {
    /**
     * POST JSON to n8n webhook (non-blocking, short timeout). Failures are logged only.
     */
    function automationDispatchActivityEvent(mysqli $conn, int $logId, array $event): void
    {
        if ($logId <= 0) {
            return;
        }
        $settings = getPortalAutomationSettings($conn);
        if (empty($settings['webhook_enabled']) || empty($settings['notify_student_actions'])) {
            return;
        }
        $url = trim((string) ($settings['webhook_url'] ?? ''));
        if ($url === '') {
            if (defined('STUDENT_AUTOMATION_WEBHOOK_URL') && STUDENT_AUTOMATION_WEBHOOK_URL !== '') {
                $url = (string) STUDENT_AUTOMATION_WEBHOOK_URL;
            } else {
                return;
            }
        }

        $action = (string) ($event['action'] ?? '');
        $entityType = (string) ($event['entity_type'] ?? '');
        $details = is_array($event['details'] ?? null) ? $event['details'] : [];
        if (is_string($event['details'] ?? null) && $event['details'] !== '') {
            $decoded = json_decode((string) $event['details'], true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }
        if (!automationShouldDispatchActivity($action, $entityType, $details)) {
            return;
        }

        $payload = [
            'event' => 'student_activity',
            'log_id' => $logId,
            'action' => $action,
            'description' => (string) ($event['description'] ?? ''),
            'entity_type' => $entityType,
            'entity_id' => (string) ($event['entity_id'] ?? ''),
            'entity_name' => (string) ($event['entity_name'] ?? ''),
            'result' => (string) ($event['result'] ?? 'success'),
            'actor_type' => (string) ($event['actor_type'] ?? ''),
            'actor_name' => (string) ($event['actor_name'] ?? ''),
            'actor_role' => (string) ($event['actor_role'] ?? ''),
            'details' => $details,
            'created_at' => (string) ($event['created_at'] ?? date('c')),
            'portal_url' => defined('APP_URL') ? rtrim((string) APP_URL, '/') : '',
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return;
        }

        $secret = trim((string) ($settings['webhook_secret'] ?? ''));
        if ($secret === '' && defined('STUDENT_AUTOMATION_WEBHOOK_SECRET')) {
            $secret = (string) STUDENT_AUTOMATION_WEBHOOK_SECRET;
        }

        $headers = [
            'Content-Type: application/json',
            'User-Agent: NIELIT-StudentAutomation/1.0',
            'X-NIELIT-Event: student_activity',
        ];
        if ($secret !== '') {
            $headers[] = 'X-NIELIT-Webhook-Secret: ' . $secret;
        }

        try {
            if (!function_exists('curl_init')) {
                return;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 3,
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code >= 400) {
                error_log('automationDispatchActivityEvent HTTP ' . $code . ' for log #' . $logId);
            }
            curl_close($ch);
        } catch (Throwable $e) {
            error_log('automationDispatchActivityEvent: ' . $e->getMessage());
        }
    }
}

if (!function_exists('automationFetchActivityFeed')) {
    /**
     * @return array{events: list<array<string, mixed>>, next_since_id: int}
     */
    function automationFetchActivityFeed(mysqli $conn, int $sinceId = 0, int $limit = 100, array $filters = []): array
    {
        if (!function_exists('ensureActivityLogsTable')) {
            require_once __DIR__ . '/activity_logger.php';
        }
        ensureActivityLogsTable($conn);
        $limit = max(1, min(500, $limit));
        $sinceId = max(0, $sinceId);

        $sql = 'SELECT id, actor_type, actor_id, actor_name, actor_role, action, entity_type, entity_id, entity_name,
                       description, details, result, created_at
                FROM activity_logs WHERE id > ?';
        $types = 'i';
        $params = [$sinceId];

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source !== '') {
            $sql .= ' AND details LIKE ?';
            $types .= 's';
            $params[] = '%"source":"' . str_replace(['%', '_'], ['\\%', '\\_'], $source) . '"%';
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $sql .= ' AND action = ?';
            $types .= 's';
            $params[] = $action;
        }

        $entityType = trim((string) ($filters['entity_type'] ?? ''));
        if ($entityType !== '') {
            $sql .= ' AND entity_type = ?';
            $types .= 's';
            $params[] = $entityType;
        } elseif (empty($action)) {
            $sql .= " AND (entity_type = 'student' OR action LIKE 'student\\_%')";
        }

        $sql .= ' ORDER BY id ASC LIMIT ?';
        $types .= 'i';
        $params[] = $limit;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['events' => [], 'next_since_id' => $sinceId];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $events = [];
        $maxId = $sinceId;
        while ($row = $res->fetch_assoc()) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > $maxId) {
                $maxId = $id;
            }
            $detailsRaw = (string) ($row['details'] ?? '');
            $details = json_decode($detailsRaw, true);
            if (!is_array($details)) {
                $details = $detailsRaw !== '' ? ['raw' => $detailsRaw] : [];
            }
            if ($source !== '' && strtolower((string) ($details['source'] ?? '')) !== strtolower($source)) {
                continue;
            }
            $events[] = [
                'id' => $id,
                'action' => (string) ($row['action'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'entity_type' => (string) ($row['entity_type'] ?? ''),
                'entity_id' => (string) ($row['entity_id'] ?? ''),
                'entity_name' => (string) ($row['entity_name'] ?? ''),
                'result' => (string) ($row['result'] ?? ''),
                'actor_name' => (string) ($row['actor_name'] ?? ''),
                'actor_role' => (string) ($row['actor_role'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'details' => $details,
            ];
        }
        $stmt->close();

        return ['events' => $events, 'next_since_id' => $maxId];
    }
}

if (!function_exists('automationPortalChatRequest')) {
    /**
     * Synchronous n8n chat webhook for homepage assistant (JSON in/out).
     *
     * @param list<array{role: string, content: string}> $history
     */
    function automationPortalChatRequest(
        mysqli $conn,
        string $message,
        array $history = [],
        array $actor = [],
        array $context = []
    ): ?string {
        $settings = getPortalAutomationSettings($conn);
        if (empty($settings['chat_webhook_enabled'])) {
            return null;
        }
        $url = trim((string) ($settings['chat_webhook_url'] ?? ''));
        if ($url === '') {
            return null;
        }
        if (!function_exists('curl_init')) {
            return null;
        }

        $secret = trim((string) ($settings['webhook_secret'] ?? ''));
        $body = json_encode([
            'event' => 'portal_chat',
            'message' => $message,
            'history' => array_slice($history, -10),
            'actor' => $actor,
            'context' => $context,
            'portal_url' => defined('APP_URL') ? rtrim((string) APP_URL, '/') : '',
            'created_at' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return null;
        }

        $headers = [
            'Content-Type: application/json',
            'User-Agent: NIELIT-PortalAssistant/1.0',
            'X-NIELIT-Event: portal_chat',
        ];
        if ($secret !== '') {
            $headers[] = 'X-NIELIT-Webhook-Secret: ' . $secret;
        }

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 18,
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!is_string($raw) || $raw === '' || $code >= 400) {
                error_log('automationPortalChatRequest HTTP ' . $code);
                return null;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return trim($raw);
            }
            if (isset($decoded['reply']) && is_string($decoded['reply'])) {
                return trim($decoded['reply']);
            }
            if (isset($decoded['output']) && is_string($decoded['output'])) {
                return trim($decoded['output']);
            }
            if (isset($decoded['body']) && is_array($decoded['body']) && isset($decoded['body']['reply'])) {
                return trim((string) $decoded['body']['reply']);
            }
            if (isset($decoded['data']['reply']) && is_string($decoded['data']['reply'])) {
                return trim($decoded['data']['reply']);
            }
        } catch (Throwable $e) {
            error_log('automationPortalChatRequest: ' . $e->getMessage());
        }

        return null;
    }
}