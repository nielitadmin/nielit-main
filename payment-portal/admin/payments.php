<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$rows = [];
$result = $hub_conn->query("SELECT p.*, s.code AS source_code FROM hub_payments p INNER JOIN hub_sources s ON s.id = p.source_id ORDER BY p.id DESC LIMIT 200");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

hub_admin_layout_start('Payments', 'payments', 'Search, view details, and issue refunds');
?>
<div class="hub-card-panel">
    <div class="hub-table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Reference</th>
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
                    <td><?php echo hub_money_paise((int) $r['amount_paise']); ?></td>
                    <td class="small"><?php echo htmlspecialchars((string) ($r['invoice_number'] ?? '—')); ?></td>
                    <td><?php echo hub_status_badge((string) $r['status']); ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?php echo hub_asset('admin/payment.php?id=' . (int) $r['id']); ?>">Details</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="7" class="text-center text-secondary py-5">No payments yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
hub_admin_layout_end();
