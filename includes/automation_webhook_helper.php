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
            alert_email VARCHAR(255) DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $ok = (bool) $conn->query($sql);
        if ($ok) {
            $conn->query('INSERT IGNORE INTO portal_automation_settings (id) VALUES (1)');
            ensurePortalAutomationChatColumns($conn);
            ensurePortalAutomationAlertEmailColumn($conn);
        }
        return $ok;
    }
}

if (!function_exists('ensurePortalAutomationChatColumns')) {
    function ensurePortalAutomationChatColumns(mysqli $conn): void
    {
        $cols = [
            'chat_webhook_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER notify_student_actions',
            'chat_webhook_url' => 'VARCHAR(500) NULL DEFAULT NULL AFTER chat_webhook_enabled',
            'ai_provider' => "VARCHAR(30) NOT NULL DEFAULT 'auto' AFTER chat_webhook_url",
            'gemini_api_key' => 'VARCHAR(255) NULL DEFAULT NULL AFTER ai_provider',
            'gemini_model' => "VARCHAR(80) NOT NULL DEFAULT 'gemini-2.5-flash' AFTER gemini_api_key",
            'openai_api_key' => 'VARCHAR(255) NULL DEFAULT NULL AFTER gemini_model',
            'openai_model' => "VARCHAR(80) NOT NULL DEFAULT 'gpt-4o-mini' AFTER openai_api_key",
        ];
        foreach ($cols as $name => $def) {
            $esc = $conn->real_escape_string($name);
            $check = @$conn->query("SHOW COLUMNS FROM portal_automation_settings LIKE '{$esc}'");
            if (!$check || $check->num_rows === 0) {
                @$conn->query("ALTER TABLE portal_automation_settings ADD COLUMN `{$name}` {$def}");
            }
        }
    }
}

if (!function_exists('ensurePortalAutomationAlertEmailColumn')) {
    function ensurePortalAutomationAlertEmailColumn(mysqli $conn): void
    {
        $check = $conn->query("SHOW COLUMNS FROM portal_automation_settings LIKE 'alert_email'");
        if ($check && $check->num_rows > 0) {
            return;
        }
        $conn->query(
            "ALTER TABLE portal_automation_settings
             ADD COLUMN alert_email VARCHAR(255) NULL DEFAULT NULL AFTER chat_webhook_url"
        );
    }
}

if (!function_exists('automationDefaultAlertEmail')) {
    function automationDefaultAlertEmail(): string
    {
        return 'saswatsomya111@gmail.com';
    }
}

if (!function_exists('automationFormatIst')) {
    function automationFormatIst(?string $dateTime): string
    {
        $raw = trim((string) $dateTime);
        if ($raw === '') {
            return date('d M Y, H:i:s') . ' IST';
        }
        try {
            $dt = new DateTime($raw);
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            return $dt->format('d M Y, H:i:s') . ' IST';
        } catch (Exception $e) {
            return $raw;
        }
    }
}

if (!function_exists('getPortalAutomationSettings')) {
    /** @return array<string, mixed> */
    function getPortalAutomationSettings(mysqli $conn): array
    {
        ensurePortalAutomationSettingsTable($conn);
        ensurePortalAutomationChatColumns($conn);
        ensurePortalAutomationAlertEmailColumn($conn);
        $res = $conn->query('SELECT * FROM portal_automation_settings WHERE id = 1 LIMIT 1');
        if (!$res || !($row = $res->fetch_assoc())) {
            return [
                'webhook_enabled' => 0,
                'webhook_url' => '',
                'webhook_secret' => '',
                'notify_student_actions' => 1,
                'chat_webhook_enabled' => 0,
                'chat_webhook_url' => '',
                'ai_provider' => 'auto',
                'gemini_api_key' => '',
                'gemini_model' => 'gemini-2.5-flash',
                'openai_api_key' => '',
                'openai_model' => 'gpt-4o-mini',
                'alert_email' => automationDefaultAlertEmail(),
            ];
        }
        if (trim((string) ($row['alert_email'] ?? '')) === '') {
            $row['alert_email'] = automationDefaultAlertEmail();
        }
        return $row;
    }
}

if (!function_exists('savePortalAutomationSettings')) {
    function savePortalAutomationSettings(mysqli $conn, array $data): bool
    {
        ensurePortalAutomationSettingsTable($conn);
        ensurePortalAutomationChatColumns($conn);
        ensurePortalAutomationAlertEmailColumn($conn);
        $enabled = !empty($data['webhook_enabled']) ? 1 : 0;
        $url = trim((string) ($data['webhook_url'] ?? ''));
        $secret = trim((string) ($data['webhook_secret'] ?? ''));
        $notify = !isset($data['notify_student_actions']) || !empty($data['notify_student_actions']) ? 1 : 0;
        $chatEnabled = !empty($data['chat_webhook_enabled']) ? 1 : 0;
        $chatUrl = trim((string) ($data['chat_webhook_url'] ?? ''));
        $alertEmail = trim((string) ($data['alert_email'] ?? ''));
        if ($alertEmail !== '' && !filter_var($alertEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        if ($chatUrl !== '' && !preg_match('#^https?://#i', $chatUrl)) {
            $chatUrl = 'https://' . $chatUrl;
        }
        $aiProvider = strtolower(trim((string) ($data['ai_provider'] ?? 'auto')));
        if (!in_array($aiProvider, ['auto', 'gemini', 'openai', 'n8n', 'local'], true)) {
            $aiProvider = 'auto';
        }
        $geminiModel = trim((string) ($data['gemini_model'] ?? 'gemini-2.5-flash')) ?: 'gemini-2.5-flash';
        $openaiModel = trim((string) ($data['openai_model'] ?? 'gpt-4o-mini')) ?: 'gpt-4o-mini';

        // Preserve stored key when blank unless explicit clear flag is passed
        $existing = getPortalAutomationSettings($conn);
        $geminiKeyInput = trim((string) ($data['gemini_api_key'] ?? ''));
        $openaiKeyInput = trim((string) ($data['openai_api_key'] ?? ''));
        $geminiKey = !empty($data['clear_gemini_key'])
            ? ''
            : ($geminiKeyInput !== '' ? $geminiKeyInput : (string) ($existing['gemini_api_key'] ?? ''));
        $openaiKey = !empty($data['clear_openai_key'])
            ? ''
            : ($openaiKeyInput !== '' ? $openaiKeyInput : (string) ($existing['openai_api_key'] ?? ''));

        $stmt = $conn->prepare(
            'UPDATE portal_automation_settings
             SET webhook_enabled = ?, webhook_url = NULLIF(?, \'\'), webhook_secret = NULLIF(?, \'\'), notify_student_actions = ?,
                 chat_webhook_enabled = ?, chat_webhook_url = NULLIF(?, \'\'), alert_email = NULLIF(?, \'\'),
                 ai_provider = ?, gemini_api_key = NULLIF(?, \'\'), gemini_model = ?,
                 openai_api_key = NULLIF(?, \'\'), openai_model = ?
             WHERE id = 1'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param(
            'issiisssssss',
            $enabled,
            $url,
            $secret,
            $notify,
            $chatEnabled,
            $chatUrl,
            $alertEmail,
            $aiProvider,
            $geminiKey,
            $geminiModel,
            $openaiKey,
            $openaiModel
        );
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

        $createdAt = (string) ($event['created_at'] ?? date('c'));
        $alertTo = trim((string) ($settings['alert_email'] ?? ''));
        if ($alertTo === '') {
            $alertTo = automationDefaultAlertEmail();
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
            'created_at' => $createdAt,
            'created_at_ist' => automationFormatIst($createdAt),
            'notify_email' => $alertTo,
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
     * Synchronous n8n chat webhook for Vani AI assistant (JSON in/out).
     * Returns string reply or structured Vani response array when n8n emits metadata.
     *
     * @param list<array{role: string, content: string}> $history
     * @return string|array<string, mixed>|null
     */
    function automationPortalChatRequest(
        mysqli $conn,
        string $message,
        array $history = [],
        array $actor = [],
        array $context = []
    ) {
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
            'assistant' => 'Vani',
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
            'User-Agent: NIELIT-PortalAssistant/2.0-Vani',
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
            $payload = $decoded;
            if (isset($decoded['body']) && is_array($decoded['body'])) {
                $payload = $decoded['body'];
            } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
                $payload = $decoded['data'];
            }
            $replyText = $payload['reply'] ?? ($payload['output'] ?? null);
            if (!is_string($replyText) || trim($replyText) === '') {
                return null;
            }
            if (isset($payload['avatar_state']) || isset($payload['ui_action']) || isset($payload['quick_replies']) || isset($payload['lang']) || isset($payload['cards'])) {
                return [
                    'reply' => trim($replyText),
                    'avatar_state' => (string) ($payload['avatar_state'] ?? ''),
                    'lang' => (string) ($payload['lang'] ?? ''),
                    'ui_action' => (string) ($payload['ui_action'] ?? 'NONE'),
                    'quick_replies' => is_array($payload['quick_replies'] ?? null) ? $payload['quick_replies'] : [],
                    'cards' => is_array($payload['cards'] ?? null) ? $payload['cards'] : [],
                ];
            }
            return trim($replyText);
        } catch (Throwable $e) {
            error_log('automationPortalChatRequest: ' . $e->getMessage());
        }

        return null;
    }
}