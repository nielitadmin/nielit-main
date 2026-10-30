<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$filters = hub_payments_filters_from_request($_GET);
$sources = hub_source_list($hub_conn);
$statuses = hub_payment_statuses();
$rows = hub_payments_search($hub_conn, $filters, 200, 0);
$total = hub_payments_count($hub_conn, $filters);

$query = $_GET;
$query['export'] = '1';
$exportQs = http_build_query($filters);

hub_admin_layout_start('Payments', 'payments', 'Filter the register and export CSV for accounts');
$invoiceSecret = (string) hub_invoice_template_get($hub_conn)['invoice_public_secret'];
?>
<div class="hub-card-panel mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label small mb-0">From</label>
            <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-0">To</label>
            <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-0">Website</label>
            <select class="form-select form-select-sm" name="source">
                <option value="">All sources</option>
                <?php foreach ($sources as $src): ?>
                    <option value="<?php echo htmlspecialchars((string) $src['code']); ?>" <?php echo $filters['source'] === $src['code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $src['display_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-0">Centre</label>
            <input class="form-control form-control-sm" name="centre" placeholder="Bhubaneswar" value="<?php echo htmlspecialchars($filters['centre']); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-0">Status</label>
            <select class="form-select form-select-sm" name="status">
                <option value="">Any</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $filters['status'] === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars(hub_payment_status_label($st)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small mb-0">Search</label>
            <input class="form-control form-control-sm" name="q" placeholder="Name, student id, invoice, Razorpay id, reference…" value="<?php echo htmlspecialchars($filters['q']); ?>">
        </div>
        <div class="col-md-4 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-sm btn-primary">Apply filters</button>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo hub_asset('admin/payments.php'); ?>">Clear</a>
            <a class="btn btn-sm btn-outline-success" href="<?php echo hub_asset('admin/export_payments.php?' . $exportQs); ?>"><i class="bi bi-download me-1"></i> Export CSV</a>
        </div>
    </form>
    <p class="small text-secondary mb-0 mt-2">Showing <?php echo count($rows); ?> of <?php echo (int) $total; ?> matching rows (max 200 on screen; export includes up to 5000).</p>
</div>

<div class="hub-card-panel">
    <div class="hub-table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Reference</th>
                    <th>Payer</th>
                    <th>Centre</th>
                    <th>Amount</th>
                    <th>Invoice</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="small"><?php echo htmlspecialchars((string) ($r['paid_at'] ?? $r['created_at'])); ?></td>
                    <td><span class="badge text-bg-light border"><?php echo htmlspecialchars((string) $r['source_code']); ?></span></td>
                    <td class="font-monospace small"><?php echo htmlspecialchars((string) $r['source_reference']); ?></td>
                    <td class="small">
                        <?php echo htmlspecialchars(hub_payment_payer_field($r, 'name')); ?>
                        <?php if (hub_payment_payer_field($r, 'external_id') !== ''): ?>
                            <br><span class="text-secondary"><?php echo htmlspecialchars(hub_payment_payer_field($r, 'external_id')); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?php echo htmlspecialchars(hub_payment_context_field($r, 'centre', '—')); ?></td>
                    <td><?php echo hub_money_paise((int) $r['amount_paise']); ?></td>
                    <td class="small">
                        <?php if (!empty($r['invoice_number'])): ?>
                            <a target="_blank" rel="noopener" href="<?php echo hub_asset('pay/invoice.php?id=' . (int) $r['id'] . '&sig=' . urlencode(hub_invoice_public_signature((int) $r['id'], $invoiceSecret))); ?>"><?php echo htmlspecialchars((string) $r['invoice_number']); ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo hub_status_badge((string) $r['status']); ?><?php if (!empty($r['is_legacy'])): ?> <span class="badge text-bg-secondary">legacy</span><?php endif; ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?php echo hub_asset('admin/payment.php?id=' . (int) $r['id']); ?>">Details</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="9" class="text-center text-secondary py-5">No payments match these filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
hub_admin_layout_end();
