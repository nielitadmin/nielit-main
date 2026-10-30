<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$msg = '';
$error = '';
$checkoutApi = hub_url('api/v1/checkout.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $code = (string) ($_POST['code'] ?? '');
        $name = (string) ($_POST['display_name'] ?? '');
        $prefixes = hub_source_prefixes_from_text((string) ($_POST['return_prefixes'] ?? ''));
        $result = hub_source_create($hub_conn, $code, $name, $prefixes);
        if (!empty($result['ok'])) {
            $msg = 'Source created. Copy the shared secret into that website now.';
        } else {
            $error = (string) ($result['error'] ?? 'Create failed');
        }
    }

    if ($action === 'update') {
        $id = (int) ($_POST['source_id'] ?? 0);
        $name = (string) ($_POST['display_name'] ?? '');
        $prefixes = hub_source_prefixes_from_text((string) ($_POST['return_prefixes'] ?? ''));
        $active = !empty($_POST['is_active']);
        $result = hub_source_update($hub_conn, $id, $name, $prefixes, $active);
        if (!empty($result['ok'])) {
            $msg = 'Source updated.';
        } else {
            $error = (string) ($result['error'] ?? 'Update failed');
        }
    }

    if ($action === 'rotate') {
        $id = (int) ($_POST['source_id'] ?? 0);
        $result = hub_source_rotate_secret($hub_conn, $id);
        if (!empty($result['ok'])) {
            $msg = 'New shared secret generated. Update the integrating site immediately.';
        } else {
            $error = (string) ($result['error'] ?? 'Rotate failed');
        }
    }
}

$sources = hub_source_list_full($hub_conn);

hub_admin_layout_start('Registered sources', 'sources', 'Add any NIELIT website that sends signed checkouts to this hub');
?>
<?php if ($msg !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="hub-card-panel mb-4">
    <h2 class="h5 fw-bold mb-2"><i class="bi bi-plus-circle me-1"></i> Add new website</h2>
    <p class="small text-secondary mb-3">Each site gets a unique <strong>source code</strong> and <strong>shared secret</strong>. MIS, mock test, HRMS, or future portals — add one entry per integrating website.</p>
    <form method="post" class="row g-3">
        <input type="hidden" name="action" value="create">
        <div class="col-md-3">
            <label class="form-label">Source code</label>
            <input class="form-control font-monospace" name="code" required pattern="[a-z][a-z0-9_]{1,31}" placeholder="e.g. hrms" autocomplete="off">
            <div class="form-text">Lowercase, unique. Used in checkout JSON as <code>"source"</code>.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Display name</label>
            <input class="form-control" name="display_name" required placeholder="e.g. HRMS portal">
        </div>
        <div class="col-md-5">
            <label class="form-label">Allowed return URL prefixes</label>
            <textarea class="form-control font-monospace small" name="return_prefixes" rows="2" required placeholder="https://hrms.nielitbhubaneswar.in/"></textarea>
            <div class="form-text">One HTTPS URL prefix per line (payer return URLs must start with these).</div>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Create source</button>
        </div>
    </form>
</div>

<p class="text-secondary mb-3">All sites POST to the same checkout API with their own secret. Sign the raw JSON body with HMAC-SHA256 header <code>X-Payment-Signature</code> (or <code>X-Hub-Signature</code>).</p>
<code class="hub-secret-box d-block mb-4 user-select-all"><?php echo htmlspecialchars($checkoutApi); ?></code>

<?php if ($sources === []): ?>
    <div class="alert alert-light border">No sources yet.</div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($sources as $src): ?>
    <div class="col-12">
        <div class="hub-card-panel <?php echo (int) $src['is_active'] === 1 ? '' : 'opacity-75'; ?>">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="hub-kpi-icon bg-primary-subtle text-primary mb-0"><i class="bi bi-globe2"></i></span>
                    <div>
                        <h2 class="h5 mb-0"><?php echo htmlspecialchars((string) $src['display_name']); ?></h2>
                        <code><?php echo htmlspecialchars((string) $src['code']); ?></code>
                        <?php if ((int) $src['is_active'] === 1): ?>
                            <span class="badge text-bg-success ms-1">Active</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary ms-1">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="post" class="d-inline" onsubmit="return confirm('Generate a new secret? The old secret stops working immediately.');">
                    <input type="hidden" name="action" value="rotate">
                    <input type="hidden" name="source_id" value="<?php echo (int) $src['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-repeat me-1"></i> Rotate secret</button>
                </form>
            </div>

            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="source_id" value="<?php echo (int) $src['id']; ?>">
                <div class="col-lg-4">
                    <label class="form-label">Display name</label>
                    <input class="form-control" name="display_name" value="<?php echo htmlspecialchars((string) $src['display_name']); ?>">
                </div>
                <div class="col-lg-5">
                    <label class="form-label">Allowed return URL prefixes</label>
                    <textarea class="form-control font-monospace small" name="return_prefixes" rows="3"><?php echo htmlspecialchars(hub_source_prefixes_to_text($src)); ?></textarea>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Status</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active_<?php echo (int) $src['id']; ?>" <?php echo (int) $src['is_active'] === 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="active_<?php echo (int) $src['id']; ?>">Accept checkouts</label>
                    </div>
                    <button class="btn btn-sm btn-primary mt-3 w-100" type="submit">Save changes</button>
                </div>
            </form>

            <div class="mt-3 pt-3 border-top">
                <p class="small text-secondary mb-1">Shared secret — copy into this website’s config only</p>
                <code class="hub-secret-box d-block user-select-all"><?php echo htmlspecialchars((string) $src['shared_secret']); ?></code>
                <p class="small text-secondary mt-2 mb-0">Checkout JSON must include <code>"source": "<?php echo htmlspecialchars((string) $src['code']); ?>"</code> and a valid signature.</p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php
hub_admin_layout_end();
