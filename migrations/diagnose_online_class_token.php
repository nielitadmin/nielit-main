<?php
/**
 * Quick local/prod diagnostic for online class join tokens.
 * CLI: php migrations/diagnose_online_class_token.php [token]
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/online_class_helper.php';

$isCli = (PHP_SAPI === 'cli');
$nl = $isCli ? "\n" : "<br>\n";
$token = trim((string) ($argv[1] ?? ($_GET['t'] ?? '')));

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font-family:monospace;padding:16px;">';
}

ensureOnlineClassesTable($conn);

$col = $conn->query("SHOW COLUMNS FROM online_classes LIKE 'join_token'");
echo 'join_token column: ' . (($col && $col->num_rows > 0) ? 'YES' : 'NO') . $nl;

$res = $conn->query('SELECT id, title, join_token, meeting_url, is_active, status, scheduled_at FROM online_classes ORDER BY id DESC LIMIT 20');
echo 'rows:' . $nl;
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo '  #' . $row['id'] . ' token=' . ($row['join_token'] ?: '(empty)') . ' title=' . $row['title'] . $nl;
        echo '     meeting_url=' . $row['meeting_url'] . $nl;
    }
}

if ($token !== '') {
    echo $nl . 'Lookup token: ' . $token . $nl;
    $found = getOnlineClassByJoinToken($conn, $token);
    echo $found ? ('FOUND id=' . $found['id'] . ' title=' . $found['title']) : 'NOT FOUND';
    echo $nl;
}

if (!$isCli) {
    echo '</pre>';
}
