<?php
/**
 * Background worker for recruitment emails.
 * Called via a short fire-and-forget HTTP request so apply/status pages do not wait on SMTP.
 */
declare(strict_types=1);

ignore_user_abort(true);
set_time_limit(90);

if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

if (function_exists('session_write_close')) {
    session_write_close();
}

$cli = (PHP_SAPI === 'cli');
$token = $cli
    ? recruitmentMailWorkerSecret()
    : (string) ($_POST['token'] ?? ($_GET['token'] ?? ''));

if (!$cli) {
    header('Content-Type: application/json; charset=utf-8');
    if (!hash_equals(recruitmentMailWorkerSecret(), $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
}

ensureRecruitmentTables($conn);
$processed = recruitmentProcessMailQueue($conn, 10);

if ($cli) {
    echo 'processed=' . $processed . PHP_EOL;
    exit;
}

echo json_encode(['ok' => true, 'processed' => $processed]);
