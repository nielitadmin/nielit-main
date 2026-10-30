<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$sources = hub_source_list($hub_conn);
$details = [];
foreach ($sources as $s) {
    $full = hub_source_by_code($hub_conn, (string) $s['code']);
    if ($full) {
        $details[] = $full;
    }
}

hub_admin_layout_start('Registered sources', 'sources', 'MIS, mock test, and future websites');
?>
<p class="text-secondary mb-4">Copy each shared secret into the integrating site. Sign checkout API requests with HMAC-SHA256.</p>
<div class="row g-3">
    <?php foreach ($details as $src): ?>
    <div class="col-lg-6">
        <div class="hub-card-panel h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="hub-kpi-icon bg-primary-subtle text-primary mb-0"><i class="bi bi-globe2"></i></span>
                <div>
                    <h2 class="h5 mb-0"><?php echo htmlspecialchars((string) $src['display_name']); ?></h2>
                    <code><?php echo htmlspecialchars((string) $src['code']); ?></code>
                </div>
            </div>
            <p class="small text-secondary mb-1">Checkout API</p>
            <code class="hub-secret-box d-block mb-3"><?php echo htmlspecialchars(hub_url('api/v1/checkout.php')); ?></code>
            <p class="small text-secondary mb-1">Allowed return URL prefixes</p>
            <code class="hub-secret-box d-block mb-3"><?php echo htmlspecialchars((string) $src['allowed_return_prefixes']); ?></code>
            <p class="small text-secondary mb-1">Shared secret</p>
            <code class="hub-secret-box d-block"><?php echo htmlspecialchars((string) $src['shared_secret']); ?></code>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php
hub_admin_layout_end();
