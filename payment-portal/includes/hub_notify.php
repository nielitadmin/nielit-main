<?php

function hub_notify_source(mysqli $conn, array $checkout, string $eventStatus, array $extra = []): void
{
    $notifyUrl = trim((string) ($checkout['notify_url'] ?? ''));
    if ($notifyUrl === '') {
        return;
    }
    $secret = (string) ($checkout['source_secret'] ?? '');
    if ($secret === '') {
        return;
    }
    $payload = array_merge([
        'event' => $eventStatus,
        'source' => $checkout['source_code'] ?? '',
        'reference' => $checkout['source_reference'],
        'status' => $eventStatus,
        'amount' => (int) $checkout['amount_paise'],
        'currency' => $checkout['currency'],
        'hub_checkout_token' => $checkout['token'],
    ], $extra);
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $sig = hub_sign_body($body, $secret);
    $ch = curl_init($notifyUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Payment-Signature: ' . $sig,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_exec($ch);
    curl_close($ch);
}

function hub_notify_payment_event(mysqli $conn, array $payment, array $checkout, string $eventStatus, array $extra = []): void
{
    hub_notify_source($conn, $checkout, $eventStatus, array_merge([
        'invoice_number' => $payment['invoice_number'] ?? '',
        'razorpay_payment_id' => $payment['razorpay_payment_id'] ?? '',
        'payment_id' => (int) ($payment['id'] ?? 0),
        'refunded_amount' => (int) ($payment['refunded_amount_paise'] ?? 0),
    ], $extra));
}
