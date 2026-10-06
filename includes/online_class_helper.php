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

if (!function_exists('onlineClassNielitJitsiDomain')) {
    function onlineClassNielitJitsiDomain(): string
    {
        return 'meet.nielitbhubaneswar.in';
    }
}

if (!function_exists('onlineClassVideoProviderOptions')) {
    /**
     * @return array<string,array{label:string,description:string,domain:?string}>
     */
    function onlineClassVideoProviderOptions(): array
    {
        return [
            'official' => [
                'label' => 'Jitsi Official (meet.jit.si)',
                'description' => 'Free public server. Use full-page mode only (no embed).',
                'domain' => 'meet.jit.si',
            ],
            'nielit_gcp' => [
                'label' => 'NIELIT Server (' . onlineClassNielitJitsiDomain() . ')',
                'description' => 'Self-hosted Jitsi server — recommended for production.',
                'domain' => onlineClassNielitJitsiDomain(),
            ],
            'custom' => [
                'label' => 'Custom Jitsi server',
                'description' => 'Any other self-hosted Jitsi domain you control.',
                'domain' => null,
            ],
            'disabled' => [
                'label' => 'Video disabled',
                'description' => 'Keep class scheduling; block live video until re-enabled.',
                'domain' => null,
            ],
        ];
    }
}

if (!function_exists('onlineClassGetDbConn')) {
    function onlineClassGetDbConn()
    {
        return (isset($GLOBALS['conn']) && $GLOBALS['conn']) ? $GLOBALS['conn'] : null;
    }
}

if (!function_exists('onlineClassInvalidateVideoSettingsCache')) {
    function onlineClassInvalidateVideoSettingsCache(): void
    {
        // Force reload on next read
        onlineClassGetVideoSettings(null, true);
    }
}

if (!function_exists('ensureOnlineClassVideoSettingsTable')) {
    function ensureOnlineClassVideoSettingsTable($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS online_class_video_settings (
            id INT PRIMARY KEY,
            provider VARCHAR(30) NOT NULL DEFAULT 'official',
            custom_domain VARCHAR(255) NULL,
            video_mode VARCHAR(10) NOT NULL DEFAULT 'open',
            jwt_enabled TINYINT(1) NOT NULL DEFAULT 0,
            jwt_app_id VARCHAR(100) NULL,
            jwt_app_secret VARCHAR(255) NULL,
            updated_by VARCHAR(255) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('ensureOnlineClassVideoSettingsTable failed: ' . $conn->error);
            return false;
        }

        $jwtIdCol = @$conn->query("SHOW COLUMNS FROM online_class_video_settings LIKE 'jwt_app_id'");
        if (!$jwtIdCol || $jwtIdCol->num_rows === 0) {
            @$conn->query('ALTER TABLE online_class_video_settings ADD COLUMN jwt_app_id VARCHAR(100) NULL AFTER jwt_enabled');
        }
        $jwtSecretCol = @$conn->query("SHOW COLUMNS FROM online_class_video_settings LIKE 'jwt_app_secret'");
        if (!$jwtSecretCol || $jwtSecretCol->num_rows === 0) {
            @$conn->query('ALTER TABLE online_class_video_settings ADD COLUMN jwt_app_secret VARCHAR(255) NULL AFTER jwt_app_id');
        }

        $check = $conn->query('SELECT id FROM online_class_video_settings WHERE id = 1 LIMIT 1');
        if ($check && $check->num_rows === 0) {
            $defaults = onlineClassInferDefaultVideoSettings();
            $stmt = $conn->prepare(
                'INSERT INTO online_class_video_settings (id, provider, custom_domain, video_mode, jwt_enabled)
                 VALUES (1, ?, ?, ?, ?)'
            );
            if ($stmt) {
                $stmt->bind_param(
                    'sssi',
                    $defaults['provider'],
                    $defaults['custom_domain'],
                    $defaults['video_mode'],
                    $defaults['jwt_enabled']
                );
                $stmt->execute();
                $stmt->close();
            }
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('onlineClassInferDefaultVideoSettings')) {
    /**
     * @return array{provider:string,custom_domain:?string,video_mode:string,jwt_enabled:int}
     */
    function onlineClassInferDefaultVideoSettings(): array
    {
        onlineClassLoadVideoConfig();

        $provider = 'official';
        $customDomain = null;
        $configuredDomain = defined('ONLINE_CLASS_JITSI_DOMAIN')
            ? strtolower(trim((string) ONLINE_CLASS_JITSI_DOMAIN))
            : 'meet.jit.si';
        $configuredDomain = preg_replace('#^https?://#i', '', $configuredDomain) ?? '';
        $configuredDomain = rtrim($configuredDomain, '/');

        if ($configuredDomain === strtolower(onlineClassNielitJitsiDomain())) {
            $provider = 'nielit_gcp';
        } elseif ($configuredDomain !== '' && $configuredDomain !== 'meet.jit.si' && $configuredDomain !== '8x8.vc') {
            $provider = 'custom';
            $customDomain = $configuredDomain;
        }

        $videoMode = defined('ONLINE_CLASS_VIDEO_MODE')
            ? strtolower(trim((string) ONLINE_CLASS_VIDEO_MODE))
            : 'open';
        $jwtEnabled = (defined('ONLINE_CLASS_JITSI_JWT_ENABLED') && ONLINE_CLASS_JITSI_JWT_ENABLED) ? 1 : 0;

        return [
            'provider' => $provider,
            'custom_domain' => $customDomain,
            'video_mode' => in_array($videoMode, ['open', 'embed'], true) ? $videoMode : 'open',
            'jwt_enabled' => $jwtEnabled,
        ];
    }
}

if (!function_exists('onlineClassGetVideoSettings')) {
    /**
     * @return array{provider:string,custom_domain:string,video_mode:string,jwt_enabled:int,updated_by?:string,updated_at?:string}
     */
    function onlineClassGetVideoSettings($conn = null, bool $forceReload = false): array
    {
        static $cache = null;
        if ($cache !== null && !$forceReload) {
            return $cache;
        }

        $defaults = onlineClassInferDefaultVideoSettings();
        $settings = [
            'provider' => $defaults['provider'],
            'custom_domain' => (string) ($defaults['custom_domain'] ?? ''),
            'video_mode' => $defaults['video_mode'],
            'jwt_enabled' => (int) $defaults['jwt_enabled'],
            'jwt_app_id' => '',
            'jwt_app_secret' => '',
        ];

        $db = $conn ?: onlineClassGetDbConn();
        if ($db && ensureOnlineClassVideoSettingsTable($db)) {
            $result = $db->query('SELECT provider, custom_domain, video_mode, jwt_enabled, jwt_app_id, jwt_app_secret, updated_by, updated_at
                                   FROM online_class_video_settings WHERE id = 1 LIMIT 1');
            if ($result && ($row = $result->fetch_assoc())) {
                $provider = trim((string) ($row['provider'] ?? ''));
                if (isset(onlineClassVideoProviderOptions()[$provider])) {
                    $settings['provider'] = $provider;
                }
                $settings['custom_domain'] = trim((string) ($row['custom_domain'] ?? ''));
                $mode = strtolower(trim((string) ($row['video_mode'] ?? 'open')));
                $settings['video_mode'] = in_array($mode, ['open', 'embed'], true) ? $mode : 'open';
                $settings['jwt_enabled'] = (int) ($row['jwt_enabled'] ?? 0);
                $settings['jwt_app_id'] = trim((string) ($row['jwt_app_id'] ?? ''));
                $settings['jwt_app_secret'] = trim((string) ($row['jwt_app_secret'] ?? ''));
                $settings['updated_by'] = (string) ($row['updated_by'] ?? '');
                $settings['updated_at'] = (string) ($row['updated_at'] ?? '');
            }
        }

        $cache = $settings;
        return $cache;
    }
}

if (!function_exists('onlineClassResolveProviderDomain')) {
    function onlineClassResolveProviderDomain(string $provider, string $customDomain = ''): string
    {
        $options = onlineClassVideoProviderOptions();
        if (!isset($options[$provider])) {
            $provider = 'official';
        }

        if ($provider === 'disabled') {
            return '';
        }

        if ($provider === 'custom') {
            $customDomain = trim($customDomain);
            $customDomain = preg_replace('#^https?://#i', '', $customDomain) ?? '';
            return rtrim($customDomain, '/');
        }

        return (string) ($options[$provider]['domain'] ?? 'meet.jit.si');
    }
}

if (!function_exists('saveOnlineClassVideoSettings')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string}
     */
    function saveOnlineClassVideoSettings($conn, array $data, string $updatedBy = 'admin'): array
    {
        if (!ensureOnlineClassVideoSettingsTable($conn)) {
            return ['success' => false, 'message' => 'Could not prepare video settings table.'];
        }

        $provider = trim((string) ($data['provider'] ?? 'official'));
        if (!isset(onlineClassVideoProviderOptions()[$provider])) {
            return ['success' => false, 'message' => 'Invalid video provider selected.'];
        }

        $customDomain = trim((string) ($data['custom_domain'] ?? ''));
        $customDomain = preg_replace('#^https?://#i', '', $customDomain) ?? '';
        $customDomain = rtrim($customDomain, '/');

        if ($provider === 'custom' && $customDomain === '') {
            return ['success' => false, 'message' => 'Enter a custom Jitsi domain or choose another provider.'];
        }

        $videoMode = strtolower(trim((string) ($data['video_mode'] ?? 'open')));
        if (!in_array($videoMode, ['open', 'embed'], true)) {
            $videoMode = 'open';
        }

        if ($provider === 'official' && $videoMode === 'embed') {
            return ['success' => false, 'message' => 'Jitsi Official only supports full-page (open) mode.'];
        }

        $jwtEnabled = !empty($data['jwt_enabled']) ? 1 : 0;
        if ($provider === 'official' || $provider === 'disabled') {
            $jwtEnabled = 0;
        } elseif (in_array($provider, ['nielit_gcp', 'custom'], true)) {
            // Self-hosted: JWT required to block public room access
            $jwtEnabled = 1;
        }

        $jwtAppId = trim((string) ($data['jwt_app_id'] ?? ''));
        if ($jwtAppId === '') {
            $jwtAppId = 'nielit_portal';
        }

        $existing = onlineClassGetVideoSettings($conn, true);
        $jwtSecret = trim((string) ($data['jwt_app_secret'] ?? ''));
        if ($jwtSecret === '') {
            $jwtSecret = (string) ($existing['jwt_app_secret'] ?? '');
        }

        onlineClassLoadVideoConfig();
        if ($jwtSecret === '' && defined('ONLINE_CLASS_JITSI_JWT_APP_SECRET')) {
            $jwtSecret = trim((string) ONLINE_CLASS_JITSI_JWT_APP_SECRET);
        }

        if ($jwtEnabled && $jwtSecret === '') {
            return ['success' => false, 'message' => 'JWT is enabled — enter the JWT App Secret (same value as JWT_APP_SECRET in your Jitsi server .env).'];
        }

        if (!$jwtEnabled) {
            $jwtAppId = (string) ($existing['jwt_app_id'] ?? $jwtAppId);
            $jwtSecret = (string) ($existing['jwt_app_secret'] ?? $jwtSecret);
        }

        $stmt = $conn->prepare(
            'INSERT INTO online_class_video_settings (id, provider, custom_domain, video_mode, jwt_enabled, jwt_app_id, jwt_app_secret, updated_by)
             VALUES (1, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                provider = VALUES(provider),
                custom_domain = VALUES(custom_domain),
                video_mode = VALUES(video_mode),
                jwt_enabled = VALUES(jwt_enabled),
                jwt_app_id = VALUES(jwt_app_id),
                jwt_app_secret = VALUES(jwt_app_secret),
                updated_by = VALUES(updated_by)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not save video settings.'];
        }

        $stmt->bind_param('sssisss', $provider, $customDomain, $videoMode, $jwtEnabled, $jwtAppId, $jwtSecret, $updatedBy);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Could not save video settings.'];
        }

        onlineClassInvalidateVideoSettingsCache();

        return ['success' => true, 'message' => 'Video server settings saved.'];
    }
}

if (!function_exists('onlineClassVideoEnabled')) {
    function onlineClassVideoEnabled(): bool
    {
        $settings = onlineClassGetVideoSettings();
        return ($settings['provider'] ?? 'official') !== 'disabled'
            && onlineClassJitsiDomain() !== '';
    }
}

if (!function_exists('onlineClassJitsiDomain')) {
    function onlineClassJitsiDomain(): string
    {
        $settings = onlineClassGetVideoSettings();
        $provider = (string) ($settings['provider'] ?? 'official');

        if ($provider === 'disabled') {
            return '';
        }

        $domain = onlineClassResolveProviderDomain(
            $provider,
            (string) ($settings['custom_domain'] ?? '')
        );

        if ($domain !== '') {
            return $domain;
        }

        onlineClassLoadVideoConfig();
        $fallback = defined('ONLINE_CLASS_JITSI_DOMAIN') ? trim((string) ONLINE_CLASS_JITSI_DOMAIN) : 'meet.jit.si';
        $fallback = preg_replace('#^https?://#i', '', $fallback) ?? '';
        $fallback = rtrim($fallback, '/');
        return $fallback !== '' ? $fallback : 'meet.jit.si';
    }
}

if (!function_exists('onlineClassVideoMode')) {
    /**
     * @return 'open'|'embed'
     */
    function onlineClassVideoMode(): string
    {
        $settings = onlineClassGetVideoSettings();
        $mode = strtolower(trim((string) ($settings['video_mode'] ?? 'open')));
        $domain = strtolower(onlineClassJitsiDomain());

        if ($domain === '' || $domain === 'meet.jit.si' || $domain === '8x8.vc') {
            return 'open';
        }

        return $mode === 'embed' ? 'embed' : 'open';
    }
}

if (!function_exists('onlineClassGetJwtAppId')) {
    function onlineClassGetJwtAppId(): string
    {
        $settings = onlineClassGetVideoSettings();
        $appId = trim((string) ($settings['jwt_app_id'] ?? ''));
        if ($appId !== '') {
            return $appId;
        }

        onlineClassLoadVideoConfig();
        if (defined('ONLINE_CLASS_JITSI_JWT_APP_ID')) {
            $appId = trim((string) ONLINE_CLASS_JITSI_JWT_APP_ID);
            if ($appId !== '') {
                return $appId;
            }
        }

        return 'nielit_portal';
    }
}

if (!function_exists('onlineClassGetJwtAppSecret')) {
    function onlineClassGetJwtAppSecret(): string
    {
        $settings = onlineClassGetVideoSettings();
        $secret = trim((string) ($settings['jwt_app_secret'] ?? ''));
        if ($secret !== '') {
            return $secret;
        }

        onlineClassLoadVideoConfig();
        if (defined('ONLINE_CLASS_JITSI_JWT_APP_SECRET')) {
            return trim((string) ONLINE_CLASS_JITSI_JWT_APP_SECRET);
        }

        return '';
    }
}

if (!function_exists('onlineClassJitsiJwtSecretConfigured')) {
    function onlineClassJitsiJwtSecretConfigured(): bool
    {
        return onlineClassGetJwtAppSecret() !== '';
    }
}

if (!function_exists('onlineClassJitsiJwtEnabled')) {
    function onlineClassJitsiJwtEnabled(): bool
    {
        $settings = onlineClassGetVideoSettings();
        if (empty($settings['jwt_enabled'])) {
            return false;
        }

        return onlineClassJitsiJwtSecretConfigured();
    }
}

if (!function_exists('onlineClassJitsiBase64UrlEncode')) {
    function onlineClassJitsiBase64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('onlineClassJitsiToolbarButtons')) {
    /**
     * Toolbar buttons for embedded Jitsi — hosts get recording & room controls.
     *
     * @return list<string>
     */
    function onlineClassJitsiToolbarButtons(bool $isModerator = false): array
    {
        $base = [
            'microphone',
            'camera',
            'desktop',
            'fullscreen',
            'fodeviceselection',
            'hangup',
            'chat',
            'raisehand',
            'tileview',
            'videoquality',
            'settings',
        ];

        if (!$isModerator) {
            return $base;
        }

        return [
            'microphone',
            'camera',
            'desktop',
            'fullscreen',
            'fodeviceselection',
            'hangup',
            'chat',
            'recording',
            'livestreaming',
            'raisehand',
            'participants-pane',
            'tileview',
            'security',
            'invite',
            'videoquality',
            'stats',
            'shortcuts',
            'profile',
            'settings',
        ];
    }
}

if (!function_exists('onlineClassIsSelfHostedJitsi')) {
    function onlineClassIsSelfHostedJitsi(): bool
    {
        $domain = strtolower(onlineClassJitsiDomain());
        return $domain !== '' && !in_array($domain, ['meet.jit.si', '8x8.vc'], true);
    }
}

if (!function_exists('onlineClassRequiresJitsiJwt')) {
    /** Self-hosted rooms must use JWT so only portal-authenticated users can connect. */
    function onlineClassRequiresJitsiJwt(): bool
    {
        return onlineClassIsSelfHostedJitsi();
    }
}

if (!function_exists('onlineClassJitsiConfigOverwrite')) {
    /**
     * @return array<string,mixed>
     */
    function onlineClassJitsiConfigOverwrite(bool $isModerator = false): array
    {
        $config = [
            'startWithAudioMuted' => true,
            'prejoinPageEnabled' => true,
            'disableDeepLinking' => true,
            'enableLobby' => false,
            'enableGuestDomain' => false,
            'guestDialOutEnabled' => false,
            'guestDialOutUrl' => '',
        ];

        if ($isModerator) {
            $config['disableRecording'] = false;
            $config['fileRecordingsEnabled'] = true;
            $config['liveStreamingEnabled'] = true;
            // Local recording works without Jibri (cloud recording needs Jibri on the server)
            $config['localRecording'] = [
                'disable' => false,
                'notifyAllParticipants' => true,
            ];
        }

        return $config;
    }
}

if (!function_exists('onlineClassGenerateJitsiJwt')) {
    /**
     * HS256 JWT for self-hosted Jitsi (moderator = admin/host).
     * Secret must match JWT_APP_SECRET on the Jitsi server.
     */
    function onlineClassGenerateJitsiJwt(
        string $roomName,
        string $displayName = '',
        bool $isModerator = false,
        string $userId = ''
    ): string {
        if (!onlineClassJitsiJwtEnabled()) {
            return '';
        }

        $secret = onlineClassGetJwtAppSecret();
        $appId = onlineClassGetJwtAppId();
        if ($secret === '' || $appId === '') {
            return '';
        }

        $roomName = trim($roomName);
        $now = time();
        $user = [
            'name' => $displayName !== '' ? $displayName : 'Participant',
            'moderator' => $isModerator,
        ];
        if ($userId !== '') {
            $user['id'] = $userId;
        }

        // Room-specific token: only valid for this class (blocks reuse on other rooms)
        $payload = [
            'iss' => $appId,
            'aud' => 'jitsi',
            'sub' => strtolower(onlineClassJitsiDomain()),
            'room' => $roomName,
            'exp' => $now + 3600,
            'nbf' => $now - 30,
            'context' => [
                'user' => $user,
            ],
        ];

        if ($isModerator) {
            $payload['context']['features'] = [
                'recording' => true,
                'livestreaming' => true,
                'transcription' => true,
                'outbound-call' => false,
            ];
        } else {
            $payload['context']['features'] = [
                'recording' => false,
                'livestreaming' => false,
                'transcription' => false,
                'outbound-call' => false,
            ];
        }

        $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $header = onlineClassJitsiBase64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], $jsonFlags));
        $body = onlineClassJitsiBase64UrlEncode(json_encode($payload, $jsonFlags));
        $signature = onlineClassJitsiBase64UrlEncode(hash_hmac('sha256', $header . '.' . $body, $secret, true));

        return $header . '.' . $body . '.' . $signature;
    }
}

if (!function_exists('onlineClassJitsiEmbedOptions')) {
    /**
     * Options array for JitsiMeetExternalAPI in join_class.php.
     *
     * @return array<string,mixed>
     */
    function onlineClassJitsiEmbedOptions(
        string $roomName,
        string $displayName = '',
        bool $isModerator = false,
        string $userId = ''
    ): array {
        $options = [
            'roomName' => trim($roomName),
            'width' => '100%',
            'height' => '100%',
            'userInfo' => ['displayName' => $displayName !== '' ? $displayName : 'Participant'],
            'configOverwrite' => onlineClassJitsiConfigOverwrite($isModerator),
            'interfaceConfigOverwrite' => [
                'TOOLBAR_BUTTONS' => onlineClassJitsiToolbarButtons($isModerator),
                'SHOW_JITSI_WATERMARK' => false,
            ],
        ];

        $jwt = onlineClassGenerateJitsiJwt($roomName, $displayName, $isModerator, $userId);
        if ($jwt !== '') {
            $options['jwt'] = $jwt;
        }

        return $options;
    }
}

if (!function_exists('onlineClassJitsiStatus')) {
    /**
     * Current video backend status for admin display.
     *
     * @return array<string,mixed>
     */
    function onlineClassJitsiStatus(): array
    {
        $settings = onlineClassGetVideoSettings();
        $provider = (string) ($settings['provider'] ?? 'official');
        $options = onlineClassVideoProviderOptions();
        $domain = onlineClassJitsiDomain();
        $isPublic = in_array(strtolower($domain), ['meet.jit.si', '8x8.vc'], true);
        $jwtRequested = !empty($settings['jwt_enabled']);
        $jwtActive = onlineClassJitsiJwtEnabled();

        $jwtSecretConfigured = onlineClassJitsiJwtSecretConfigured();

        return [
            'provider' => $provider,
            'provider_label' => $options[$provider]['label'] ?? $provider,
            'provider_description' => $options[$provider]['description'] ?? '',
            'custom_domain' => (string) ($settings['custom_domain'] ?? ''),
            'video_enabled' => onlineClassVideoEnabled(),
            'domain' => $domain,
            'base_url' => $domain !== '' ? 'https://' . $domain : '',
            'video_mode' => onlineClassVideoMode(),
            'is_self_hosted' => $domain !== '' && !$isPublic,
            'jwt_requested' => $jwtRequested,
            'jwt_enabled' => $jwtActive,
            'jwt_secret_configured' => $jwtSecretConfigured,
            'jwt_app_id' => onlineClassGetJwtAppId(),
            'updated_by' => (string) ($settings['updated_by'] ?? ''),
            'updated_at' => (string) ($settings['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('onlineClassExternalRoomUrl')) {
    /**
     * Full-page Jitsi room URL (free when using meet.jit.si without iframe embed).
     */
    function onlineClassExternalRoomUrl(
        string $roomName,
        string $displayName = '',
        bool $isModerator = false,
        string $userId = ''
    ): string {
        $domain = onlineClassJitsiDomain();
        if ($domain === '') {
            return '';
        }

        $roomName = trim($roomName);
        $url = 'https://' . $domain . '/' . rawurlencode($roomName);

        $jwt = onlineClassGenerateJitsiJwt($roomName, $displayName, $isModerator, $userId);

        $parts = [];
        if ($jwt !== '') {
            // Jitsi reads JWT from the URL hash (preferred); query-string jwt is unreliable on newer clients
            $parts[] = 'jwt=' . $jwt;
        } elseif ($displayName !== '') {
            $parts[] = 'userInfo.displayName="' . str_replace(['"', '#'], '', $displayName) . '"';
        }
        $parts[] = 'config.startWithAudioMuted=true';
        $parts[] = 'config.disableDeepLinking=true';

        if ($isModerator) {
            $parts[] = 'config.disableRecording=false';
            $parts[] = 'config.fileRecordingsEnabled=true';
            $parts[] = 'config.liveStreamingEnabled=true';
            $parts[] = 'config.localRecording.disable=false';
        }

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
            $row['jitsi_room_url'] = onlineClassExternalRoomUrl($row['room_name']);
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
