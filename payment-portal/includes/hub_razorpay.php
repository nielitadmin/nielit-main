<?php

function hub_razorpay_api(mysqli $conn, string $method, string $path, array $body = null): array
{
    $cfg = hub_razorpay_config($conn);
    if ($cfg['key_id'] === '' || $cfg['key_secret'] === '') {
        return ['ok' => false, 'error' => 'Razorpay is not configured on the payment portal'];
    }
    $url = 'https://api.razorpay.com/v1/' . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $cfg['key_id'] . ':' . $cfg['key_secret']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body ?? []));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string) $raw, true);
    if ($code >= 200 && $code < 300 && is_array($data)) {
        return ['ok' => true, 'data' => $data];
    }
    return ['ok' => false, 'error' => is_array($data) ? ($data['error']['description'] ?? 'Razorpay error') : 'Razorpay request failed', 'http' => $code];
}

function hub_razorpay_create_order(mysqli $conn, array $checkout): array
{
    $amount = (int) $checkout['amount_paise'];
    $currency = (string) $checkout['currency'];
    $receipt = 'hub_' . (int) $checkout['id'];
    $notes = [
        'hub_checkout_id' => (string) $checkout['id'],
        'hub_token' => (string) $checkout['token'],
        'source' => (string) ($checkout['source_code'] ?? ''),
        'reference' => (string) $checkout['source_reference'],
    ];
    return hub_razorpay_api($conn, 'POST', 'orders', [
        'amount' => $amount,
        'currency' => $currency,
        'receipt' => $receipt,
        'notes' => $notes,
    ]);
}

function hub_razorpay_verify_payment_signature(mysqli $conn, string $orderId, string $paymentId, string $signature): bool
{
    $cfg = hub_razorpay_config($conn);
    if ($cfg['key_secret'] === '') {
        return false;
    }
    $payload = $orderId . '|' . $paymentId;
    $expected = hash_hmac('sha256', $payload, $cfg['key_secret']);
    return hash_equals($expected, $signature);
}

function hub_razorpay_verify_webhook(mysqli $conn, string $body, string $signatureHeader): bool
{
    $cfg = hub_razorpay_config($conn);
    if ($cfg['webhook_secret'] === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $body, $cfg['webhook_secret']);
    return hash_equals($expected, $signatureHeader);
}

function hub_razorpay_fetch_payment(mysqli $conn, string $paymentId): array
{
    return hub_razorpay_api($conn, 'GET', 'payments/' . rawurlencode($paymentId));
}
