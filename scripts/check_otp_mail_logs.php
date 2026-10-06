<?php
require_once __DIR__ . '/../config/database.php';

function fetchRows($conn, $table, $limit = 10) {
    $rows = [];
    $res = $conn->query("SELECT * FROM `" . $conn->real_escape_string($table) . "` ORDER BY created_at DESC LIMIT " . (int)$limit);
    if ($res) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
    }
    return $rows;
}

echo "Checking DB tables for OTP/mail logs\n";

$tables = ['otp_logs', 'mail_send_logs'];
foreach ($tables as $t) {
    $count = 0;
    $res = $conn->query("SELECT COUNT(*) as c FROM `" . $conn->real_escape_string($t) . "`");
    if ($res) {
        $row = $res->fetch_assoc();
        $count = (int)($row['c'] ?? 0);
    } else {
        echo "Table {$t} does not exist or query failed: " . $conn->error . "\n";
        continue;
    }
    echo "Table {$t}: {$count} rows\n";
    $rows = fetchRows($conn, $t, 5);
    if (!empty($rows)) {
        foreach ($rows as $r) {
            echo json_encode($r) . "\n";
        }
    } else {
        echo "(no recent rows)\n";
    }
    echo "---\n";
}

echo "Done.\n";

?>
