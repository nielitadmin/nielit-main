<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require_superadmin();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['form_action'] ?? '');

    if ($action === 'save_smtp') {
        hub_setting_set($hub_conn, 'smtp_host', trim((string) ($_POST['smtp_host'] ?? '')));
        hub_setting_set($hub_conn, 'smtp_port', trim((string) ($_POST['smtp_port'] ?? '465')));
        hub_setting_set($hub_conn, 'smtp_username', hub_smtp_normalize_mailbox((string) ($_POST['smtp_username'] ?? '')));
        $pass = (string) ($_POST['smtp_password'] ?? '');
        if ($pass !== '') {
            hub_setting_set($hub_conn, 'smtp_password', $pass);
        }
        $from = hub_smtp_normalize_mailbox((string) ($_POST['smtp_from_email'] ?? ''));
        $user = hub_smtp_normalize_mailbox((string) ($_POST['smtp_username'] ?? ''));
        if ($from === '' && $user !== '') {
            $from = $user;
        }
        if ($user !== '' && $from !== $user) {
            $from = $user;
        }
        hub_setting_set($hub_conn, 'smtp_from_email', $from);
        hub_setting_set($hub_conn, 'smtp_from_name', trim((string) ($_POST['smtp_from_name'] ?? '')));
        hub_setting_set($hub_conn, 'smtp_encryption', trim((string) ($_POST['smtp_encryption'] ?? 'ssl')));
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
                $detail = hub_mail_last_error();
                $message = 'Failed to send test email.' . ($detail !== '' ? ' ' . $detail : ' Check SMTP settings and server logs.');
                $messageType = 'danger';
            }
        } else {
            $message = 'Enter a valid email address for testing.';
            $messageType = 'danger';
        }
    }
}

$cfg = hub_smtp_form_config($hub_conn);
$ready = hub_smtp_ready($hub_conn);

hub_admin_layout_start('SMTP settings', 'smtp', 'Email configuration for OTP login');
?>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show py-2" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($cfg['using_defaults']) && !$ready): ?>
    <div class="alert alert-info py-2 small mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Suggested <strong>Hostinger / NIELIT</strong> values are shown below. Click each field to edit, add your mailbox <strong>SMTP password</strong>, then <strong>Save SMTP settings</strong>.
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
            <p class="text-secondary small mb-4">Used for admin OTP login emails. On Hostinger, create <code>admin@nielitbhubaneswar.in</code> (or your sending address) in hPanel → Emails, then use that account’s SMTP password here.</p>

            <form method="post" class="vstack gap-3">
                <input type="hidden" name="form_action" value="save_smtp">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">SMTP host</label>
                        <input class="form-control bg-white" type="text" name="smtp_host" value="<?php echo htmlspecialchars($cfg['host']); ?>" placeholder="smtp.hostinger.com" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Port</label>
                        <input class="form-control bg-white" type="number" name="smtp_port" value="<?php echo (int) $cfg['port']; ?>" placeholder="465" required>
                    </div>
                </div>
                <div>
                    <label class="form-label">Encryption</label>
                    <select class="form-select bg-white" name="smtp_encryption">
                        <option value="ssl" <?php echo $cfg['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL (port 465) — Hostinger</option>
                        <option value="tls" <?php echo $cfg['encryption'] === 'tls' ? 'selected' : ''; ?>>TLS (port 587)</option>
                        <option value="ssl" <?php echo $cfg['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL (port 465)</option>
                        <option value="none" <?php echo $cfg['encryption'] === 'none' ? 'selected' : ''; ?>>None (port 25)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">SMTP username</label>
                    <input class="form-control bg-white" type="text" name="smtp_username" value="<?php echo htmlspecialchars($cfg['username']); ?>" placeholder="admin@nielitbhubaneswar.in" autocomplete="off" required>
                </div>
                <div>
                    <label class="form-label">SMTP password</label>
                    <input class="form-control bg-white" type="password" name="smtp_password" placeholder="<?php echo $cfg['password'] !== '' ? '••••••••  (saved — leave blank to keep)' : 'Email account password or Gmail App Password'; ?>" autocomplete="new-password" <?php echo $cfg['password'] === '' ? 'required' : ''; ?>>
                    <?php if (stripos($cfg['host'], 'gmail') !== false): ?>
                        <div class="form-text"><i class="bi bi-info-circle me-1"></i>For Gmail, use an <a href="https://support.google.com/accounts/answer/185833" target="_blank" rel="noopener">App Password</a>, not your regular password.</div>
                    <?php elseif (stripos($cfg['host'], 'hostinger') !== false): ?>
                        <div class="form-text"><i class="bi bi-info-circle me-1"></i>Use the password for this mailbox from Hostinger → Emails (not your hPanel login).</div>
                    <?php endif; ?>
                </div>
                <hr class="my-1">
                <div>
                    <label class="form-label">From email</label>
                    <input class="form-control bg-white" type="email" name="smtp_from_email" value="<?php echo htmlspecialchars(hub_smtp_normalize_mailbox($cfg['from_email'])); ?>" placeholder="noreply@nielitbhubaneswar.in" required>
                    <div class="form-text">Must match the mailbox username on Hostinger (e.g. <code>noreply@</code>, not <code>noreplay@</code>).</div>
                </div>
                <div>
                    <label class="form-label">From name</label>
                    <input class="form-control bg-white" type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($cfg['from_name']); ?>" placeholder="NIELIT Payment Hub" required>
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
                    <input class="form-control bg-white" type="email" name="test_email" required placeholder="you@gmail.com" value="<?php echo htmlspecialchars((string) ($_POST['test_email'] ?? '')); ?>">
                </div>
                <button class="btn btn-outline-success w-100" type="submit" <?php echo !$ready ? 'disabled title="Save SMTP settings first"' : ''; ?>>
                    <i class="bi bi-envelope-check me-1"></i>Send test
                </button>
                <?php if (!$ready): ?>
                    <p class="small text-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Enter SMTP password and click <strong>Save SMTP settings</strong>, then send a test.</p>
                <?php endif; ?>
            </form>
        </div>

        <div class="hub-card-panel">
            <h3 class="h6 text-uppercase text-secondary fw-semibold">Quick setup guides</h3>
            <div class="vstack gap-2 small">
                <div class="p-2 bg-light rounded">
                    <strong>Hostinger (NIELIT)</strong><br>
                    Host: <code>smtp.hostinger.com</code> · Port: <code>465</code> · SSL<br>
                    Username: full address e.g. <code>noreply@nielitbhubaneswar.in</code>
                </div>
                <div class="p-2 bg-light rounded">
                    <strong>Gmail</strong><br>
                    Host: <code>smtp.gmail.com</code> · Port: <code>587</code> · TLS<br>
                    Use <a href="https://support.google.com/accounts/answer/185833" target="_blank" rel="noopener">App Password</a>
                </div>
                <div class="p-2 bg-light rounded">
                    <strong>Outlook / Office 365</strong><br>
                    Host: <code>smtp.office365.com</code> · Port: <code>587</code> · TLS
                </div>
            </div>
        </div>
    </div>
</div>
<?php
hub_admin_layout_end();
