<?php
/**
 * Public homepage assistant chat API (JSON POST).
 */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/portal_assistant_helper.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

if (!portal_assistant_rate_limit_ok()) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many messages. Please wait a while and try again.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    $payload = $_POST;
}

$message = trim((string) ($payload['message'] ?? ''));
$history = $payload['history'] ?? [];
if (!is_array($history)) {
    $history = [];
}

if ($message === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Message is required']);
    exit;
}

$conn = isset($conn) && $conn instanceof mysqli ? $conn : null;
$result = portal_assistant_chat($message, $history, $conn);

echo json_encode([
    'ok' => true,
    'reply' => $result['reply'],
    'mode' => $result['mode'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);