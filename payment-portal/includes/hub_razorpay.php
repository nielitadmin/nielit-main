<?php

function hub_razorpay_api(mysqli $conn, string $method, string $path, array $body = null): array
{
    $cfg = hub_razorpay_config($conn);
    if ($cfg['key_id'] === '' || $cfg['key_secret'] === '') {
        return ['ok' => false, 'error' => 'Razorpay is not configured on the payment portal'];
    }

    $url = 'https://api.razorpay.com/v1/' . ltrim($path, '/');
    $jsonBody = $body !== null ? json_encode($body) : null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Could not initialize cURL on this server'];
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $cfg['key_id'] . ':' . $cfg['key_secret']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody ?? '{}');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            $curlErr = curl_error($ch);
            curl_close($ch);
            error_log('Payment hub Razorpay cURL: ' . $curlErr);
            return ['ok' => false, 'error' => 'Could not reach Razorpay (' . $curlErr . ')', 'http' => 0];
        }
        curl_close($ch);
    } else {
        $authHeader = 'Authorization: Basic ' . base64_encode($cfg['key_id'] . ':' . $cfg['key_secret']) . "\r\n";
        $headers = $authHeader . "Content-Type: application/json\r\n";
        $opts = [
            'http' => [
                'method' => strtoupper($method),
                'header' => $headers,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ];
        if (strtoupper($method) === 'POST') {
            $opts['http']['content'] = $jsonBody ?? '{}';
        }
        $ctx = stream_context_create($opts);
        $raw = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        if ($raw === false) {
            error_log('Payment hub Razorpay HTTP: request failed (no cURL)');
            return ['ok' => false, 'error' => 'Could not reach Razorpay (enable PHP cURL on the server)', 'http' => $code];
        }
    }

    $data = json_decode((string) $raw, true);
    if ($code >= 200 && $code < 300 && is_array($data)) {
        return ['ok' => true, 'data' => $data];
    }

    $description = 'Razorpay request failed';
    if (is_array($data)) {
        $description = (string) ($data['error']['description'] ?? $data['error']['reason'] ?? $description);
    } elseif ($code === 401) {
        $description = 'Authentication failed — check Key ID and Key secret (test vs live must match)';
    } elseif ($code === 0) {
        $description = 'No response from Razorpay';
    }

    error_log('Payment hub Razorpay API ' . $code . ': ' . $description);

    return ['ok' => false, 'error' => $description, 'http' => $code];
}

/** Razorpay receipt max 40 chars; must be unique per order attempt. */
function hub_razorpay_order_receipt(array $checkout, bool $retry = false): string
{
    $id = (int) $checkout['id'];
    $token = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($checkout['token'] ?? '')));
    $suffix = substr($token, 0, 12);
    $receipt = 'hub' . $id . 't' . $suffix;
    if ($retry) {
        $receipt .= 'r' . dechex(time());
    }
    return substr($receipt, 0, 40);
}

function hub_razorpay_create_order(mysqli $conn, array $checkout): array
{
    $amount = (int) $checkout['amount_paise'];
    if ($amount < 100) {
        return ['ok' => false, 'error' => 'Amount must be at least ₹1.00 (100 paise)'];
    }
    $currency = strtoupper(trim((string) ($checkout['currency'] ?? 'INR')));
    if ($currency === '') {
        $currency = 'INR';
    }

    $notes = [
        'hub_checkout_id' => (string) $checkout['id'],
        'hub_token' => substr((string) $checkout['token'], 0, 64),
        'source' => substr((string) ($checkout['source_code'] ?? ''), 0, 64),
        'reference' => substr((string) $checkout['source_reference'], 0, 256),
    ];

    $payload = [
        'amount' => $amount,
        'currency' => $currency,
        'receipt' => hub_razorpay_order_receipt($checkout),
        'notes' => $notes,
    ];

    $result = hub_razorpay_api($conn, 'POST', 'orders', $payload);
    if (!$result['ok'] && stripos((string) ($result['error'] ?? ''), 'receipt') !== false) {
        $payload['receipt'] = hub_razorpay_order_receipt($checkout, true);
        $result = hub_razorpay_api($conn, 'POST', 'orders', $payload);
    }

    return $result;
}

/** Creates a ₹1 test order; does not affect live checkouts. */
function hub_razorpay_test_connection(mysqli $conn): array
{
    return hub_razorpay_api($conn, 'POST', 'orders', [
        'amount' => 100,
        'currency' => 'INR',
        'receipt' => substr('hubping' . dechex(time()) . bin2hex(random_bytes(2)), 0, 40),
        'notes' => ['hub_test' => '1'],
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
