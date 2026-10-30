<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hub_setting_set($hub_conn, 'razorpay_mode', trim((string) ($_POST['razorpay_mode'] ?? 'test')));
    hub_setting_set($hub_conn, 'razorpay_key_id', trim((string) ($_POST['razorpay_key_id'] ?? '')));

    if (!empty($_POST['clear_key_secret'])) {
        hub_setting_set($hub_conn, 'razorpay_key_secret', '');
    } elseif (trim((string) ($_POST['razorpay_key_secret'] ?? '')) !== '') {
        hub_setting_set($hub_conn, 'razorpay_key_secret', trim((string) $_POST['razorpay_key_secret']));
    }

    if (!empty($_POST['clear_webhook_secret'])) {
        hub_setting_set($hub_conn, 'razorpay_webhook_secret', '');
    } elseif (trim((string) ($_POST['razorpay_webhook_secret'] ?? '')) !== '') {
        hub_setting_set($hub_conn, 'razorpay_webhook_secret', trim((string) $_POST['razorpay_webhook_secret']));
    }

    $msg = 'Gateway settings saved.';
}
$cfg = hub_razorpay_config($hub_conn);
$ready = hub_razorpay_ready($hub_conn);
$webhookUrl = hub_url('api/v1/razorpay/webhook.php');

function hub_gateway_field_status(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'not-set';
    }
    return 'set';
}

hub_admin_layout_start('Razorpay gateway', 'gateway', 'API keys and webhook configuration');
?>
<?php if ($msg !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div class="hub-card-panel mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Saved configuration</h2>
            <p class="small text-secondary mb-0">Values stored in the payment hub database (same as used for checkout and webhooks).</p>
        </div>
        <?php if ($ready): ?>
            <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>API keys ready</span>
        <?php else: ?>
            <span class="badge text-bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>API keys incomplete</span>
        <?php endif; ?>
    </div>
    <div class="table-responsive hub-table-wrap">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Setting</th><th>Status</th><th>Saved value</th></tr></thead>
            <tbody>
                <tr>
                    <td class="fw-semibold">Mode</td>
                    <td><span class="badge text-bg-light border"><?php echo hub_gateway_field_status($cfg['mode']) === 'set' ? 'Set' : 'Default'; ?></span></td>
                    <td><code><?php echo htmlspecialchars($cfg['mode']); ?></code></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Key ID</td>
                    <td><?php echo hub_gateway_field_status($cfg['key_id']) === 'set' ? '<span class="badge text-bg-success">Set</span>' : '<span class="badge text-bg-danger">Missing</span>'; ?></td>
                    <td><code class="user-select-all"><?php echo $cfg['key_id'] !== '' ? htmlspecialchars($cfg['key_id']) : '—'; ?></code></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Key secret</td>
                    <td><?php echo hub_gateway_field_status($cfg['key_secret']) === 'set' ? '<span class="badge text-bg-success">Set</span>' : '<span class="badge text-bg-danger">Missing</span>'; ?></td>
                    <td>
                        <?php if ($cfg['key_secret'] !== ''): ?>
                            <code class="hub-secret-view user-select-all" data-hub-secret="<?php echo htmlspecialchars($cfg['key_secret'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(hub_gateway_mask_secret($cfg['key_secret'])); ?></code>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 hub-secret-toggle" aria-label="Show key secret">Show</button>
                        <?php else: ?>
                            <span class="text-secondary">— not saved —</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="fw-semibold">Webhook secret</td>
                    <td><?php echo hub_gateway_field_status($cfg['webhook_secret']) === 'set' ? '<span class="badge text-bg-success">Set</span>' : '<span class="badge text-bg-warning text-dark">Not set</span>'; ?></td>
                    <td>
                        <?php if ($cfg['webhook_secret'] !== ''): ?>
                            <code class="hub-secret-view user-select-all" data-hub-secret="<?php echo htmlspecialchars($cfg['webhook_secret'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(hub_gateway_mask_secret($cfg['webhook_secret'])); ?></code>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 hub-secret-toggle" aria-label="Show webhook secret">Show</button>
                        <?php else: ?>
                            <span class="text-secondary">— not saved — paste same secret as Razorpay webhook —</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="fw-semibold">Webhook URL</td>
                    <td><span class="badge text-bg-light border">Fixed</span></td>
                    <td><code class="user-select-all"><?php echo htmlspecialchars($webhookUrl); ?></code></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php if (!$ready): ?>
<div class="alert alert-light border mb-4">
    <strong>First-time setup.</strong> Enter Razorpay Key ID and Key secret from your Razorpay Dashboard.
    You can also put them in <code>config/secrets.php</code> on the server—they sync here when the database fields are empty.
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="hub-card-panel">
            <h2 class="h6 fw-bold mb-2">Edit settings</h2>
            <div class="alert alert-light border small py-2 mb-3">
                <strong>How to update:</strong>
                <ul class="mb-0 ps-3">
                    <li><strong>Key ID / Mode</strong> — change in the fields below, then Save.</li>
                    <li><strong>Key secret or webhook secret</strong> — type the <em>new</em> value below and Save (leave empty to keep the current saved value).</li>
                    <li><strong>Remove a secret</strong> — tick <em>Clear saved…</em>, then Save.</li>
                    <li>After Razorpay <strong>Regenerate</strong> keys, update <strong>both</strong> Key ID and Key secret here.</li>
                </ul>
            </div>
            <form method="post" class="row g-3" id="hubGatewayForm">
                <div class="col-md-6">
                    <label class="form-label">Mode</label>
                    <select class="form-select" name="razorpay_mode">
                        <option value="test" <?php echo $cfg['mode'] === 'test' ? 'selected' : ''; ?>>Test</option>
                        <option value="live" <?php echo $cfg['mode'] === 'live' ? 'selected' : ''; ?>>Live</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Key ID</label>
                    <input class="form-control font-monospace" name="razorpay_key_id" value="<?php echo htmlspecialchars($cfg['key_id']); ?>" autocomplete="off">
                </div>
                <div class="col-12">
                    <label class="form-label d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span>Key secret <?php echo $cfg['key_secret'] !== '' ? '<span class="badge text-bg-light border fw-normal">saved</span>' : '<span class="badge text-bg-danger fw-normal">missing</span>'; ?></span>
                        <span>
                            <button type="button" class="btn btn-link btn-sm p-0 hub-form-secret-toggle" data-target="razorpay_key_secret">Show while typing</button>
                            <button type="button" class="btn btn-link btn-sm p-0 hub-focus-replace" data-target="razorpay_key_secret">Enter new value</button>
                        </span>
                    </label>
                    <input class="form-control font-monospace hub-form-secret" id="razorpay_key_secret" type="password" name="razorpay_key_secret" value="" autocomplete="new-password" placeholder="Leave empty to keep current — or paste new API key secret">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="clear_key_secret" value="1" id="clear_key_secret">
                        <label class="form-check-label text-danger" for="clear_key_secret">Clear saved key secret (remove from hub)</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span>Webhook secret <?php echo $cfg['webhook_secret'] !== '' ? '<span class="badge text-bg-light border fw-normal">saved</span>' : '<span class="badge text-bg-warning text-dark fw-normal">not set</span>'; ?></span>
                        <span>
                            <button type="button" class="btn btn-link btn-sm p-0 hub-form-secret-toggle" data-target="razorpay_webhook_secret">Show while typing</button>
                            <button type="button" class="btn btn-link btn-sm p-0 hub-focus-replace" data-target="razorpay_webhook_secret">Enter new value</button>
                        </span>
                    </label>
                    <input class="form-control font-monospace hub-form-secret" id="razorpay_webhook_secret" type="password" name="razorpay_webhook_secret" value="" autocomplete="new-password" placeholder="Leave empty to keep current — or paste webhook secret (same as Razorpay)">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="clear_webhook_secret" value="1" id="clear_webhook_secret">
                        <label class="form-check-label text-danger" for="clear_webhook_secret">Clear saved webhook secret</label>
                    </div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Save settings</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="hub-card-panel">
            <h2 class="h6 text-uppercase text-secondary">Webhook URL (Razorpay Dashboard)</h2>
            <code class="hub-secret-box d-block mb-3 user-select-all"><?php echo htmlspecialchars($webhookUrl); ?></code>
            <p class="small text-secondary mb-2">Subscribe to events:</p>
            <ul class="list-unstyled small mb-0">
                <li><code>payment.captured</code></li>
                <li><code>payment.failed</code></li>
                <li><code>refund.processed</code></li>
            </ul>
        </div>
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('.hub-secret-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var code = btn.previousElementSibling;
            if (!code || !code.dataset.hubSecret) return;
            var showing = btn.getAttribute('data-showing') === '1';
            if (showing) {
                code.textContent = code.getAttribute('data-masked') || code.textContent;
                btn.textContent = 'Show';
                btn.setAttribute('data-showing', '0');
            } else {
                code.setAttribute('data-masked', code.textContent);
                code.textContent = code.dataset.hubSecret;
                btn.textContent = 'Hide';
                btn.setAttribute('data-showing', '1');
            }
        });
    });
    document.querySelectorAll('.hub-form-secret-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var el = document.getElementById(btn.getAttribute('data-target'));
            if (!el) return;
            var show = el.type === 'password';
            el.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide while typing' : 'Show while typing';
        });
    });
    document.querySelectorAll('.hub-focus-replace').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var el = document.getElementById(btn.getAttribute('data-target'));
            if (!el) return;
            el.value = '';
            el.type = 'text';
            el.focus();
            el.placeholder = 'Paste new value, then Save settings';
        });
    });
})();
</script>
<?php
hub_admin_layout_end();
