<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hub_setting_set($hub_conn, 'razorpay_mode', trim((string) ($_POST['razorpay_mode'] ?? 'test')));
    hub_setting_set($hub_conn, 'razorpay_key_id', trim((string) ($_POST['razorpay_key_id'] ?? '')));
    if (trim((string) ($_POST['razorpay_key_secret'] ?? '')) !== '') {
        hub_setting_set($hub_conn, 'razorpay_key_secret', trim((string) $_POST['razorpay_key_secret']));
    }
    if (trim((string) ($_POST['razorpay_webhook_secret'] ?? '')) !== '') {
        hub_setting_set($hub_conn, 'razorpay_webhook_secret', trim((string) $_POST['razorpay_webhook_secret']));
    }
    $msg = 'Gateway settings saved.';
}
$cfg = hub_razorpay_config($hub_conn);

hub_admin_layout_start('Razorpay gateway', 'gateway', 'API keys and webhook configuration');
?>
<?php if ($msg !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<?php if (!hub_razorpay_ready($hub_conn)): ?>
<div class="alert alert-light border mb-4">
    <strong>First-time setup.</strong> Enter Razorpay Key ID and Key secret from your Razorpay Dashboard.
    You can also put them in <code>config/secrets.php</code> on the server—they sync here when the database fields are empty.
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="hub-card-panel">
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Mode</label>
                    <select class="form-select" name="razorpay_mode">
                        <option value="test" <?php echo $cfg['mode'] === 'test' ? 'selected' : ''; ?>>Test</option>
                        <option value="live" <?php echo $cfg['mode'] === 'live' ? 'selected' : ''; ?>>Live</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Key ID</label>
                    <input class="form-control font-monospace" name="razorpay_key_id" value="<?php echo htmlspecialchars($cfg['key_id']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Key secret</label>
                    <input class="form-control" type="password" name="razorpay_key_secret" placeholder="Leave blank to keep current">
                </div>
                <div class="col-12">
                    <label class="form-label">Webhook secret</label>
                    <input class="form-control" type="password" name="razorpay_webhook_secret" placeholder="Leave blank to keep current">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Save settings</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="hub-card-panel">
            <h2 class="h6 text-uppercase text-secondary">Webhook URL</h2>
            <code class="hub-secret-box d-block mb-3"><?php echo htmlspecialchars(hub_url('api/v1/razorpay/webhook.php')); ?></code>
            <p class="small text-secondary mb-2">Subscribe to events:</p>
            <ul class="list-unstyled small">
                <li><code>payment.captured</code></li>
                <li><code>payment.failed</code></li>
                <li><code>refund.processed</code></li>
            </ul>
        </div>
    </div>
</div>
<?php
hub_admin_layout_end();
