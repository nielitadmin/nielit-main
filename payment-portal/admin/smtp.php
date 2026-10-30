<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require_superadmin();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['form_action'] ?? '');

    if ($action === 'save_smtp') {
        hub_setting_set($hub_conn, 'smtp_host', trim((string) ($_POST['smtp_host'] ?? '')));
        hub_setting_set($hub_conn, 'smtp_port', trim((string) ($_POST['smtp_port'] ?? '587')));
        hub_setting_set($hub_conn, 'smtp_username', trim((string) ($_POST['smtp_username'] ?? '')));
        $pass = (string) ($_POST['smtp_password'] ?? '');
        if ($pass !== '') {
            hub_setting_set($hub_conn, 'smtp_password', $pass);
        }
        hub_setting_set($hub_conn, 'smtp_from_email', trim((string) ($_POST['smtp_from_email'] ?? '')));
        hub_setting_set($hub_conn, 'smtp_from_name', trim((string) ($_POST['smtp_from_name'] ?? '')));
        hub_setting_set($hub_conn, 'smtp_encryption', trim((string) ($_POST['smtp_encryption'] ?? 'tls')));
        $message = 'SMTP settings saved.';
    }

    if ($action === 'test_email') {
        $testTo = trim((string) ($_POST['test_email'] ?? ''));
        if ($testTo !== '' && filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $appName = defined('HUB_BRAND_SHORT') ? HUB_BRAND_SHORT : 'NIELIT Payment Hub';
            $testHtml = '<div style="font-family:sans-serif;padding:24px;"><h2>✅ SMTP Test Successful</h2><p>This is a test email from <strong>' . htmlspecialchars($appName) . '</strong>.</p><p>Your SMTP settings are configured correctly.</p><p style="color:#94a3b8;font-size:12px;">Sent at ' . date('Y-m-d H:i:s T') . '</p></div>';
            if (hub_send_email($hub_conn, $testTo, 'SMTP Test — ' . $appName, $testHtml)) {
                $message = 'Test email sent to ' . htmlspecialchars($testTo) . '. Check your inbox.';
            } else {
                $message = 'Failed to send test email. Check your SMTP settings and server logs.';
                $messageType = 'danger';
            }
        } else {
            $message = 'Enter a valid email address for testing.';
            $messageType = 'danger';
        }
    }
}

$cfg = hub_smtp_config($hub_conn);
$ready = hub_smtp_ready($hub_conn);

hub_admin_layout_start('SMTP settings', 'smtp', 'Email configuration for OTP login');
?>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show py-2" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="hub-card-panel">
            <div class="d-flex align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0">SMTP configuration</h2>
                <?php if ($ready): ?>
                    <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Configured</span>
                <?php else: ?>
                    <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle me-1"></i>Not configured</span>
                <?php endif; ?>
            </div>
            <p class="text-secondary small mb-4">Configure your SMTP server to send OTP emails for admin login. Common options: Gmail, Outlook, SendGrid, Mailgun, or your own mail server.</p>

            <form method="post" class="vstack gap-3">
                <input type="hidden" name="form_action" value="save_smtp">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">SMTP host</label>
                        <input class="form-control" type="text" name="smtp_host" value="<?php echo htmlspecialchars($cfg['host']); ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Port</label>
                        <input class="form-control" type="number" name="smtp_port" value="<?php echo (int) $cfg['port']; ?>" placeholder="587">
                    </div>
                </div>
                <div>
                    <label class="form-label">Encryption</label>
                    <select class="form-select" name="smtp_encryption">
                        <option value="tls" <?php echo $cfg['encryption'] === 'tls' ? 'selected' : ''; ?>>TLS (port 587) — recommended</option>
                        <option value="ssl" <?php echo $cfg['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL (port 465)</option>
                        <option value="none" <?php echo $cfg['encryption'] === 'none' ? 'selected' : ''; ?>>None (port 25)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">SMTP username</label>
                    <input class="form-control" type="text" name="smtp_username" value="<?php echo htmlspecialchars($cfg['username']); ?>" placeholder="your-email@gmail.com" autocomplete="off">
                </div>
                <div>
                    <label class="form-label">SMTP password</label>
                    <input class="form-control" type="password" name="smtp_password" placeholder="<?php echo $cfg['password'] !== '' ? '••••••••  (saved — leave blank to keep)' : 'App password or SMTP password'; ?>" autocomplete="new-password">
                    <?php if ($cfg['host'] !== '' && stripos($cfg['host'], 'gmail') !== false): ?>
                        <div class="form-text"><i class="bi bi-info-circle me-1"></i>For Gmail, use an <a href="https://support.google.com/accounts/answer/185833" target="_blank" rel="noopener">App Password</a>, not your regular password.</div>
                    <?php endif; ?>
                </div>
                <hr class="my-1">
                <div>
                    <label class="form-label">From email</label>
                    <input class="form-control" type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($cfg['from_email']); ?>" placeholder="noreply@nielitbhubaneswar.in">
                </div>
                <div>
                    <label class="form-label">From name</label>
                    <input class="form-control" type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($cfg['from_name']); ?>" placeholder="NIELIT Payment Hub">
                </div>
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-floppy me-1"></i>Save SMTP settings
                </button>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="hub-card-panel mb-3">
            <h3 class="h6 fw-bold mb-3"><i class="bi bi-send me-2"></i>Send test email</h3>
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="form_action" value="test_email">
                <div>
                    <label class="form-label">Recipient email</label>
                    <input class="form-control" type="email" name="test_email" required placeholder="test@example.com">
                </div>
                <button class="btn btn-outline-success w-100" type="submit" <?php echo !$ready ? 'disabled' : ''; ?>>
                    <i class="bi bi-envelope-check me-1"></i>Send test
                </button>
                <?php if (!$ready): ?>
                    <p class="small text-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Save SMTP settings first to send a test.</p>
                <?php endif; ?>
            </form>
        </div>

        <div class="hub-card-panel">
            <h3 class="h6 text-uppercase text-secondary fw-semibold">Quick setup guides</h3>
            <div class="vstack gap-2 small">
                <div class="p-2 bg-light rounded">
                    <strong>Gmail</strong><br>
                    Host: <code>smtp.gmail.com</code> · Port: <code>587</code> · TLS<br>
                    Use <a href="https://support.google.com/accounts/answer/185833" target="_blank" rel="noopener">App Password</a>
                </div>
                <div class="p-2 bg-light rounded">
                    <strong>Outlook / Office 365</strong><br>
                    Host: <code>smtp.office365.com</code> · Port: <code>587</code> · TLS
                </div>
                <div class="p-2 bg-light rounded">
                    <strong>SendGrid</strong><br>
                    Host: <code>smtp.sendgrid.net</code> · Port: <code>587</code> · TLS<br>
                    Username: <code>apikey</code>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
hub_admin_layout_end();
