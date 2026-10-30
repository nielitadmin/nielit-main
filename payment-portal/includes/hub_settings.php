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
        'mode' => trim((string) (hub_setting_get($conn, 'razorpay_mode') ?: 'test')),
        'key_id' => trim((string) (hub_setting_get($conn, 'razorpay_key_id') ?: '')),
        'key_secret' => trim((string) (hub_setting_get($conn, 'razorpay_key_secret') ?: '')),
        'webhook_secret' => trim((string) (hub_setting_get($conn, 'razorpay_webhook_secret') ?: '')),
    ];
}

function hub_razorpay_ready(mysqli $conn): bool
{
    $c = hub_razorpay_config($conn);
    return $c['key_id'] !== '' && $c['key_secret'] !== '';
}

/** Warn when hub “mode” does not match Razorpay Key ID prefix (common auth failure). */
function hub_razorpay_mode_key_warning(string $mode, string $keyId): ?string
{
    $keyId = trim($keyId);
    if ($keyId === '') {
        return null;
    }
    $testKey = stripos($keyId, 'rzp_test_') === 0;
    $liveKey = stripos($keyId, 'rzp_live_') === 0;
    if ($mode === 'live' && $testKey) {
        return 'Mode is Live but Key ID is a test key (rzp_test_…). Use Live API keys from Razorpay, or set Mode to Test.';
    }
    if ($mode === 'test' && $liveKey) {
        return 'Mode is Test but Key ID is a live key (rzp_live_…). Use Test API keys, or set Mode to Live for real payments.';
    }
    if (!$testKey && !$liveKey) {
        return 'Key ID should start with rzp_test_ or rzp_live_. Check for typos or extra spaces.';
    }
    return null;
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
    $modeWarning = hub_razorpay_mode_key_warning($cfg['mode'], $cfg['key_id']);

    return [
        'gateway_ready' => $gateway,
        'sources_ready' => $sourceCount > 0,
        'source_count' => $sourceCount,
        'webhook_ready' => $webhookOk,
        'gateway_mode_warning' => $modeWarning,
        'complete' => $gateway && $sourceCount > 0 && $webhookOk && $modeWarning === null,
    ];
}

function hub_gateway_mask_secret(string $value): string
{
    $value = trim($value);
    $len = strlen($value);
    if ($len === 0) {
        return '—';
    }
    if ($len <= 6) {
        return str_repeat('•', $len) . ' (' . $len . ' chars)';
    }
    return substr($value, 0, 4) . str_repeat('•', min(14, $len - 8)) . substr($value, -4) . ' (' . $len . ' chars)';
}
