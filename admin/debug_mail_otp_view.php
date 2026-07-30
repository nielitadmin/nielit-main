<?php
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'master_admin') {
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';

function fetch($conn, $table, $limit = 50) {
    $rows = [];
    $q = "SELECT * FROM `" . $conn->real_escape_string($table) . "` ORDER BY created_at DESC LIMIT " . (int)$limit;
    $res = $conn->query($q);
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    return [$rows, $conn->error ?: ''];
}

list($otps, $otp_err) = fetch($conn, 'otp_logs', 50);
list($mails, $mail_err) = fetch($conn, 'mail_send_logs', 50);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Debug: Mail & OTP Logs</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;font-size:14px;padding:20px}table{border-collapse:collapse;width:100%;margin-bottom:24px}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f6f7fb}</style>
</head>
<body>
    <h2>OTP Logs (last 50)</h2>
    <?php if ($otp_err): ?><div style="color:red">Error querying otp_logs: <?php echo htmlspecialchars($otp_err); ?></div><?php endif; ?>
    <table>
        <tr><th>ID</th><th>Email</th><th>OTP</th><th>Purpose</th><th>Username</th><th>Status</th><th>Created At</th></tr>
        <?php foreach ($otps as $r): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['id']); ?></td>
                <td><?php echo htmlspecialchars($r['email']); ?></td>
                <td><?php echo htmlspecialchars($r['otp_code']); ?></td>
                <td><?php echo htmlspecialchars($r['purpose']); ?></td>
                <td><?php echo htmlspecialchars($r['username']); ?></td>
                <td><?php echo htmlspecialchars($r['status']); ?></td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Mail Send Logs (last 50)</h2>
    <?php if ($mail_err): ?><div style="color:red">Error querying mail_send_logs: <?php echo htmlspecialchars($mail_err); ?></div><?php endif; ?>
    <table>
        <tr><th>ID</th><th>Profile</th><th>Recipient</th><th>Subject</th><th>Status</th><th>Error</th><th>Created At</th></tr>
        <?php foreach ($mails as $r): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['id']); ?></td>
                <td><?php echo htmlspecialchars($r['profile_label']); ?></td>
                <td><?php echo htmlspecialchars($r['recipient']); ?></td>
                <td><?php echo htmlspecialchars($r['subject']); ?></td>
                <td><?php echo htmlspecialchars($r['status']); ?></td>
                <td><?php echo htmlspecialchars($r['error']); ?></td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p>Use the Admin Login page to trigger OTP sends, then refresh this page to see captured rows.</p>
</body>
</html>
