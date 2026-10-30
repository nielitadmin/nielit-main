<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$stats = hub_dashboard_stats($hub_conn);
$recent = hub_dashboard_recent_payments($hub_conn, 8);
$gatewayOk = hub_razorpay_ready($hub_conn);

hub_admin_layout_start('Dashboard', 'dashboard', 'Live overview of collections and gateway health');
?>
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

<div class="row g-3">
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
                            <td><?php echo hub_status_badge((string) $r['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recent === []): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-4">No payments recorded yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="hub-card-panel mb-3">
            <h2 class="h6 text-uppercase text-secondary fw-semibold">Gateway</h2>
            <?php if ($gatewayOk): ?>
                <p class="mb-2"><span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Razorpay configured</span></p>
                <p class="small text-secondary mb-0">Mode: <?php echo htmlspecialchars(hub_razorpay_config($hub_conn)['mode']); ?></p>
            <?php else: ?>
                <p class="mb-3"><span class="badge text-bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Not configured</span></p>
                <a class="btn btn-primary w-100" href="<?php echo hub_asset('admin/gateway.php'); ?>">Add API keys</a>
            <?php endif; ?>
        </div>
        <div class="hub-card-panel">
            <h2 class="h6 text-uppercase text-secondary fw-semibold">Quick links</h2>
            <div class="d-grid gap-2">
                <a class="btn btn-outline-secondary btn-sm text-start" href="<?php echo hub_asset('admin/sources.php'); ?>"><i class="bi bi-key me-2"></i>MIS / mock secrets</a>
                <a class="btn btn-outline-secondary btn-sm text-start" href="<?php echo hub_asset('admin/outcomes.php'); ?>"><i class="bi bi-journal-text me-2"></i>Outcome reference</a>
                <a class="btn btn-outline-secondary btn-sm text-start" href="<?php echo hub_asset('/'); ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-2"></i>Public portal page</a>
            </div>
        </div>
    </div>
</div>
<?php
hub_admin_layout_end();
