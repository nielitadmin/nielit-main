<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

hub_admin_layout_start('Payment outcomes', 'outcomes', 'Every checkout and payment status explained');
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="hub-card-panel h-100">
            <h2 class="h5 mb-3"><i class="bi bi-link-45deg me-2 text-primary"></i>Checkout link</h2>
            <div class="hub-table-wrap">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Code</th><th>Meaning</th><th>MIS action</th></tr></thead>
                    <tbody>
                    <?php foreach (hub_checkout_statuses() as $st): ?>
                        <tr>
                            <td><?php echo hub_status_badge($st, 'checkout'); ?></td>
                            <td><?php echo htmlspecialchars(hub_checkout_status_label($st)); ?></td>
                            <td class="small text-secondary"><?php
                                if ($st === 'paid') {
                                    echo 'Unlock course / test';
                                } elseif ($st === 'failed' || $st === 'cancelled') {
                                    echo 'Allow retry';
                                } elseif ($st === 'expired') {
                                    echo 'Create new checkout';
                                } else {
                                    echo 'Wait';
                                }
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="hub-card-panel h-100">
            <h2 class="h5 mb-3"><i class="bi bi-receipt me-2 text-success"></i>Payment record</h2>
            <div class="hub-table-wrap">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Code</th><th>Meaning</th></tr></thead>
                    <tbody>
                    <?php foreach (hub_payment_statuses() as $st): ?>
                        <tr>
                            <td><?php echo hub_status_badge($st); ?></td>
                            <td><?php echo htmlspecialchars(hub_payment_status_label($st)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="hub-card-panel">
            <h2 class="h5 mb-3"><i class="bi bi-broadcast me-2"></i>Notify events (signed POST to source)</h2>
            <p class="text-secondary">Header <code>X-Payment-Signature</code> · JSON field <code>event</code> / <code>status</code>:</p>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach (['paid', 'failed', 'cancelled', 'refunded', 'partial_refund'] as $ev): ?>
                    <span class="badge rounded-pill text-bg-dark px-3 py-2"><?php echo htmlspecialchars($ev); ?></span>
                <?php endforeach; ?>
            </div>
            <hr>
            <h3 class="h6">Refunds</h3>
            <ul class="text-secondary mb-0">
                <li>Issue from <strong>Payments → Details → Refund via Razorpay</strong></li>
                <li>Credit notes: <code>NIELIT-BBSR-CN-YYYY-YY-######</code> — original invoice unchanged</li>
                <li>Webhook <code>refund.processed</code> confirms settlement</li>
            </ul>
        </div>
    </div>
</div>
<?php
hub_admin_layout_end();
