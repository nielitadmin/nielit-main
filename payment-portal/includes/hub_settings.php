<?php

function hub_setting_get(mysqli $conn, string $key): ?string
{
    $stmt = $conn->prepare('SELECT setting_value FROM hub_settings WHERE setting_key = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (string) $row['setting_value'] : null;
}

function hub_setting_set(mysqli $conn, string $key, string $value): bool
{
    $stmt = $conn->prepare('INSERT INTO hub_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $key, $value);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function hub_razorpay_config(mysqli $conn): array
{
    return [
        'mode' => hub_setting_get($conn, 'razorpay_mode') ?: 'test',
        'key_id' => hub_setting_get($conn, 'razorpay_key_id') ?: '',
        'key_secret' => hub_setting_get($conn, 'razorpay_key_secret') ?: '',
        'webhook_secret' => hub_setting_get($conn, 'razorpay_webhook_secret') ?: '',
    ];
}

function hub_razorpay_ready(mysqli $conn): bool
{
    $c = hub_razorpay_config($conn);
    return $c['key_id'] !== '' && $c['key_secret'] !== '';
}

/**
 * Copy Razorpay values from secrets.php into hub_settings when DB fields are still empty.
 */
function hub_razorpay_sync_from_secrets(mysqli $conn, array $secrets): void
{
    $pairs = [
        'razorpay_mode' => 'razorpay_mode',
        'razorpay_key_id' => 'razorpay_key_id',
        'razorpay_key_secret' => 'razorpay_key_secret',
        'razorpay_webhook_secret' => 'razorpay_webhook_secret',
    ];
    foreach ($pairs as $settingKey => $secretKey) {
        $fromSecret = trim((string) ($secrets[$secretKey] ?? ''));
        if ($fromSecret === '') {
            continue;
        }
        $current = hub_setting_get($conn, $settingKey);
        if ($current === null || $current === '') {
            hub_setting_set($conn, $settingKey, $fromSecret);
        }
    }
}

function hub_setup_status(mysqli $conn): array
{
    $gateway = hub_razorpay_ready($conn);
    $sourceCount = 0;
    $q = $conn->query('SELECT COUNT(*) AS c FROM hub_sources WHERE is_active = 1');
    if ($q && ($r = $q->fetch_assoc())) {
        $sourceCount = (int) $r['c'];
    }
    $cfg = hub_razorpay_config($conn);
    $webhookOk = trim((string) ($cfg['webhook_secret'] ?? '')) !== '';

    return [
        'gateway_ready' => $gateway,
        'sources_ready' => $sourceCount > 0,
        'source_count' => $sourceCount,
        'webhook_ready' => $webhookOk,
        'complete' => $gateway && $sourceCount > 0,
    ];
}
