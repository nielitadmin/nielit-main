<?php
/**
 * Background registration confirmation email (non-blocking for the student).
 */
declare(strict_types=1);

ignore_user_abort(true);
set_time_limit(60);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = (string) ($_POST['payload'] ?? '');
if ($raw === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_payload']);
    exit;
}

$decoded = json_decode(base64_decode($raw, true) ?: '', true);
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit;
}

if (!verifyRegistrationEmailJobToken($decoded)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'invalid_token']);
    exit;
}

$sent = sendRegistrationEmail(
    (string) ($decoded['email'] ?? ''),
    (string) ($decoded['name'] ?? ''),
    (string) ($decoded['student_id'] ?? ''),
    (string) ($decoded['password'] ?? ''),
    (string) ($decoded['course_name'] ?? ''),
    (string) ($decoded['training_center'] ?? '')
);

echo json_encode(['ok' => (bool) $sent]);
