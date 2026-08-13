<?php
/**
 * Send library due / overdue reminder emails.
 *
 * CLI:  php scripts/library_due_reminders.php
 * HTTP: /scripts/library_due_reminders.php?key=...
 */
date_default_timezone_set('Asia/Kolkata');

$config = __DIR__ . '/../config/config.php';
$db = __DIR__ . '/../config/database.php';
if (is_file($config)) {
    require_once $config;
} elseif (is_file($db)) {
    require_once $db;
} else {
    fwrite(STDERR, "Config not found\n");
    exit(1);
}

require_once __DIR__ . '/../includes/library_helper.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    fwrite(STDERR, "No database connection\n");
    exit(1);
}

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    $key = (string) ($_GET['key'] ?? '');
    $okKey = $key !== '' && hash_equals(libraryReminderCronKey(), $key);
    $okAdmin = false;
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    if (!empty($_SESSION['admin'])) {
        $okAdmin = admin_can_access_library($conn);
    }
    if (!$okKey && !$okAdmin) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden\n";
        exit;
    }
}

$result = sendLibraryDueReminders($conn);
$line = 'sent=' . (int) $result['sent'] . ' skipped=' . (int) $result['skipped'] . ' failed=' . (int) $result['failed'];
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}
echo $line . "\n";
