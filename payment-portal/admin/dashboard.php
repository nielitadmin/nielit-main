<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$stats = hub_dashboard_stats($hub_conn);
$recent = hub_dashboard_recent_payments($hub_conn, 8);
$setup = hub_setup_status($hub_conn);
$gatewayOk = $setup['gateway_ready'];
$cfg = hub_razorpay_config($hub_conn);

hub_admin_layout_start('Dashboard', 'dashboard', 'Collections, checkouts, and gateway status');
?>
<?php if (!$setup['complete']): ?>
<div class="alert alert-info border-0 shadow-sm mb-4 hub-setup-banner">
    <div class="d-flex flex-wrap align-items-start gap-3">
        <span class="hub-setup-icon flex-shrink-0"><i class="bi bi-list-check"></i></span>
        <div class="flex-grow-1">
            <h2 class="h6 fw-bold mb-2">Complete setup (this is normal for a new portal)</h2>
            <p class="small mb-3 mb-md-2 text-secondary">Nothing is broken—finish these steps to accept live payments.</p>
            <ol class="small mb-0 ps-3 hub-setup-steps">
                <li class="<?php echo $gatewayOk ? 'text-success' : ''; ?>">
                    <?php if ($gatewayOk): ?><i class="bi bi-check-circle-fill me-1"></i><?php endif; ?>
                    <strong>Razorpay keys</strong> — <?php echo $gatewayOk ? 'Configured' : 'Add Key ID and secret under Gateway'; ?>
                    <?php if (!$gatewayOk): ?>
                        <a class="ms-1" href="<?php echo hub_asset('admin/gateway.php'); ?>">Open Gateway</a>
                    <?php endif; ?>
                </li>
                <li class="<?php echo $setup['webhook_ready'] ? 'text-success' : ''; ?>">
                    <?php if ($setup['webhook_ready']): ?><i class="bi bi-check-circle-fill me-1"></i><?php endif; ?>
                    <strong>Webhook secret</strong> — match Razorpay Dashboard → Webhooks
                    <?php if (!$setup['webhook_ready']): ?>
                        <a class="ms-1" href="<?php echo hub_asset('admin/gateway.php'); ?>">Add in Gateway</a>
                    <?php endif; ?>
                </li>
                <li class="<?php echo $setup['sources_ready'] ? 'text-success' : ''; ?>">
                    <?php if ($setup['sources_ready']): ?><i class="bi bi-check-circle-fill me-1"></i><?php endif; ?>
                    <strong>Source secrets</strong> — <?php echo $setup['sources_ready'] ? (int) $setup['source_count'] . ' portal(s) registered' : 'Copy shared secrets into MIS / partner sites'; ?>
                    <a class="ms-1" href="<?php echo hub_asset('admin/sources.php'); ?>">View Sources</a>
                </li>
            </ol>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="hub-card-panel hub-kpi">
            <div class="hub-kpi-icon bg-success-subtle text-success"><i class="bi bi-cash-stack"></i></div>
            <div class="hub-kpi-label">Paid today</div>
            <div class="hub-kpi-value"><?php echo hub_money_paise((int) $stats['today_amount']); ?></div>
            <small class="text-secondary"><?php echo (int) $stats['today_count']; ?> transactions</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="hub-card-panel hub-kpi">
            <div class="hub-kpi-icon bg-primary-subtle text-primary"><i class="bi bi-calendar-month"></i></div>
            <div class="hub-kpi-label">This month</div>
            <div class="hub-kpi-value"><?php echo hub_money_paise((int) $stats['month_amount']); ?></div>
            <small class="text-secondary"><?php echo (int) $stats['month_count']; ?> successful</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="hub-card-panel hub-kpi">
            <div class="hub-kpi-icon bg-danger-subtle text-danger"><i class="bi bi-x-octagon"></i></div>
            <div class="hub-kpi-label">Failed today</div>
            <div class="hub-kpi-value"><?php echo (int) $stats['failed_today']; ?></div>
            <small class="text-secondary">Payment attempts</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="hub-card-panel hub-kpi">
            <div class="hub-kpi-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="hub-kpi-label">Open checkouts</div>
            <div class="hub-kpi-value"><?php echo (int) $stats['open_checkouts']; ?></div>
            <small class="text-secondary">Awaiting payer</small>
        </div>
    </div>
</div>

<div class="row g-3 align-items-start">
    <div class="col-lg-8">
        <div class="hub-card-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Recent payments</h2>
                <a class="btn btn-sm btn-outline-primary" href="<?php echo hub_asset('admin/payments.php'); ?>">View all</a>
            </div>
            <div class="hub-table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Time</th><th>Source</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td class="small"><?php echo htmlspecialchars((string) ($r['paid_at'] ?? $r['created_at'])); ?></td>
                            <td><span class="badge text-bg-light border"><?php echo htmlspecialchars((string) $r['source_code']); ?></span></td>
                            <td><?php echo hub_money_paise((int) $r['amount_paise']); ?></td>
                            <td><?php echo hub_status_badge((string) $r['status']); ?>
                                <a class="small ms-1" href="<?php echo hub_asset('admin/payment.php?id=' . (int) $r['id']); ?>">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recent === []): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-4">No payments yet—transactions appear here after the first successful checkout.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="hub-card-panel mb-3">
            <h2 class="h6 text-uppercase text-secondary fw-semibold">Razorpay gateway</h2>
            <?php if ($gatewayOk): ?>
                <p class="mb-2"><span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Ready to accept payments</span></p>
                <p class="small text-secondary mb-2">Mode: <strong><?php echo htmlspecialchars($cfg['mode']); ?></strong></p>
                <?php if (!$setup['webhook_ready']): ?>
                    <p class="small text-warning mb-2"><i class="bi bi-info-circle me-1"></i>Add webhook secret for automatic status updates.</p>
                <?php endif; ?>
                <a class="btn btn-outline-primary btn-sm w-100" href="<?php echo hub_asset('admin/gateway.php'); ?>">Manage keys</a>
            <?php else: ?>
                <p class="small text-secondary mb-2">Keys are not saved yet. This is expected until you connect your Razorpay account.</p>
                <p class="mb-3"><span class="badge text-bg-light border text-dark"><i class="bi bi-plug me-1"></i>Setup required</span></p>
                <a class="btn btn-primary w-100" href="<?php echo hub_asset('admin/gateway.php'); ?>"><i class="bi bi-shield-lock me-1"></i> Configure Razorpay</a>
            <?php endif; ?>
        </div>
        <div class="hub-card-panel">
            <h2 class="h6 text-uppercase text-secondary fw-semibold">Quick links</h2>
            <div class="d-grid gap-2">
                <a class="btn btn-outline-secondary btn-sm text-start" href="<?php echo hub_asset('admin/sources.php'); ?>"><i class="bi bi-boxes me-2"></i>Portal source secrets</a>
                <a class="btn btn-outline-secondary btn-sm text-start" href="<?php echo hub_asset('admin/gateway.php'); ?>"><i class="bi bi-credit-card me-2"></i>Razorpay &amp; webhook</a>
                <a class="btn btn-outline-secondary btn-sm text-start" href="<?php echo hub_asset('admin/outcomes.php'); ?>"><i class="bi bi-journal-text me-2"></i>Status codes reference</a>
                <a class="btn btn-outline-secondary btn-sm text-start" href="<?php echo hub_asset('/'); ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-2"></i>Public landing page</a>
            </div>
        </div>
    </div>
</div>
<?php
hub_admin_layout_end();
