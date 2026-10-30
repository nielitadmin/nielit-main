<?php

require_once __DIR__ . '/../../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'HEAD') {
    hub_json_response(['ok' => true, 'service' => 'nielit-payment-hub-webhook']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hub_json_response(['ok' => false, 'error' => 'POST required'], 405);
}

$raw = hub_read_raw_body();
$sig = (string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

if (!hub_razorpay_verify_webhook($hub_conn, $raw, $sig)) {
    hub_json_response(['ok' => false, 'error' => 'invalid webhook signature'], 400);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    hub_json_response(['ok' => false, 'error' => 'invalid payload'], 400);
}

$eventId = hub_webhook_event_id($payload, $raw);
$eventType = (string) ($payload['event'] ?? 'unknown');
if (!hub_webhook_store($hub_conn, $eventId, $eventType, $raw)) {
    hub_json_response(['ok' => true, 'duplicate' => true]);
}

hub_webhook_process($hub_conn, $payload);

$mark = $hub_conn->prepare('UPDATE hub_webhook_events SET processed = 1 WHERE event_id = ?');
if ($mark) {
    $mark->bind_param('s', $eventId);
    $mark->execute();
    $mark->close();
}

hub_json_response(['ok' => true]);
