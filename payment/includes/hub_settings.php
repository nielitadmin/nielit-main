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
