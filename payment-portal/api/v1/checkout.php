<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hub_json_response(['ok' => false, 'error' => 'POST required'], 405);
}

$raw = hub_read_raw_body();
$data = json_decode($raw, true);
if (!is_array($data)) {
    hub_json_response(['ok' => false, 'error' => 'invalid JSON body'], 400);
}

$sourceCode = strtolower(trim((string) ($data['source'] ?? '')));
$source = hub_source_by_code($hub_conn, $sourceCode);
if (!$source) {
    hub_json_response(['ok' => false, 'error' => 'unknown or inactive source'], 403);
}

$sig = hub_header_signature();
if (!hub_verify_signature($raw, (string) $source['shared_secret'], $sig)) {
    hub_json_response(['ok' => false, 'error' => 'invalid signature'], 401);
}

$result = hub_checkout_create_from_request($hub_conn, $source, $data);
if (empty($result['ok'])) {
    hub_json_response(['ok' => false, 'error' => $result['error'] ?? 'checkout failed'], 422);
}

hub_json_response([
    'ok' => true,
    'checkout_token' => $result['token'],
    'pay_url' => $result['pay_url'],
    'expires_at' => $result['expires_at'],
]);
