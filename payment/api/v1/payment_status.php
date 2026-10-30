<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$sourceCode = strtolower(trim((string) ($_GET['source'] ?? '')));
$reference = trim((string) ($_GET['reference'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

if ($token !== '') {
    $checkout = hub_checkout_find_by_token($hub_conn, $token);
    if (!$checkout) {
        hub_json_response(['ok' => false, 'error' => 'checkout not found'], 404);
    }
    hub_json_response(['ok' => true, 'status' => hub_payment_status_payload($hub_conn, $checkout)]);
}

if ($sourceCode === '' || $reference === '') {
    hub_json_response(['ok' => false, 'error' => 'token or source+reference required'], 400);
}

$source = hub_source_by_code($hub_conn, $sourceCode);
if (!$source) {
    hub_json_response(['ok' => false, 'error' => 'unknown source'], 403);
}

$query = http_build_query(['source' => $sourceCode, 'reference' => $reference]);
$sig = hub_header_signature();
if (!hub_verify_signature($query, (string) $source['shared_secret'], $sig)) {
    hub_json_response(['ok' => false, 'error' => 'invalid signature'], 401);
}

$stmt = $hub_conn->prepare('SELECT c.*, s.code AS source_code, s.display_name AS source_name, s.shared_secret AS source_secret
    FROM hub_checkouts c
    INNER JOIN hub_sources s ON s.id = c.source_id
    WHERE c.source_id = ? AND c.source_reference = ?
    ORDER BY c.id DESC LIMIT 1');
if (!$stmt) {
    hub_json_response(['ok' => false, 'error' => 'lookup failed'], 500);
}
$sid = (int) $source['id'];
$stmt->bind_param('is', $sid, $reference);
$stmt->execute();
$checkout = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$checkout) {
    hub_json_response(['ok' => false, 'error' => 'checkout not found'], 404);
}

hub_json_response(['ok' => true, 'status' => hub_payment_status_payload($hub_conn, $checkout)]);
