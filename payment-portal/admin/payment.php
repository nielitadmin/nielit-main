<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$paymentId = (int) ($_GET['id'] ?? 0);
$payment = hub_payment_find($hub_conn, $paymentId);
$msg = '';
$error = '';

if (!$payment) {
    http_response_code(404);
    exit('Payment not found.');
}

if ((int) ($payment['razorpay_fee_paise'] ?? 0) === 0 && trim((string) ($payment['razorpay_payment_id'] ?? '')) !== '') {
    hub_payment_refresh_razorpay_fees($hub_conn, $paymentId);
    $payment = hub_payment_find($hub_conn, $paymentId) ?? $payment;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refund') {
    $amountRupees = trim((string) ($_POST['amount'] ?? ''));
    $amountPaise = null;
    if ($amountRupees !== '') {
        $amountPaise = (int) round((float) $amountRupees * 100);
    }
    $reason = trim((string) ($_POST['reason'] ?? 'Refund requested by accounts'));
    $user = (string) ($_SESSION['hub_admin_user'] ?? 'admin');
    $result = hub_refund_initiate($hub_conn, $paymentId, $amountPaise, $reason, $user);
    if (!empty($result['ok'])) {
        $msg = 'Refund submitted. Status: ' . ($result['status'] ?? 'pending');
        if (!empty($result['credit_note_number'])) {
            $msg .= ' · Credit note ' . $result['credit_note_number'];
        }
        $payment = hub_payment_find($hub_conn, $paymentId);
    } else {
        $error = (string) ($result['error'] ?? 'Refund failed');
    }
}

$checkout = hub_checkout_for_payment($hub_conn, $payment);
$refundable = hub_payment_refundable_paise($payment);
$refunds = hub_refunds_for_payment($hub_conn, $paymentId);
$payer = json_decode((string) ($payment['payer_json'] ?? ''), true);
if (!is_array($payer)) {
    $payer = [];
}
$breakdown = hub_payment_breakdown($payment);
$invoiceSecret = (string) hub_invoice_template_get($hub_conn)['invoice_public_secret'];

hub_admin_layout_start('Payment #' . $paymentId, 'payments', (string) $payment['source_reference']);
?>
<?php if ($msg !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="hub-card-panel">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h2 class="h5 mb-0">Summary</h2>
                <?php echo hub_status_badge((string) $payment['status']); ?>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item px-0"><span class="text-secondary small">Purpose</span><br><strong><?php echo htmlspecialchars($breakdown['purpose_label']); ?></strong></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">Source</span><strong><?php echo htmlspecialchars((string) $payment['source_code']); ?></strong></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">Collected from payer</span><strong><?php echo hub_money_paise($breakdown['collected_paise']); ?></strong></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">Institute / course fee</span><?php echo hub_money_paise($breakdown['fee_receive_paise']); ?></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">Platform fee (<?php echo htmlspecialchars(number_format($breakdown['platform_percent'], 1)); ?>%)</span><?php echo hub_money_paise($breakdown['platform_fee_paise']); ?></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">Razorpay processing fee</span><?php echo $breakdown['razorpay_fee_paise'] > 0 ? hub_money_paise($breakdown['razorpay_fee_paise']) : '—'; ?></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">GST on Razorpay fee</span><?php echo $breakdown['razorpay_tax_paise'] > 0 ? hub_money_paise($breakdown['razorpay_tax_paise']) : '—'; ?></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">Centre fee (bank books)</span><strong><?php echo hub_money_paise($breakdown['centre_bank_paise']); ?></strong></li>
                <li class="list-group-item d-flex justify-content-between px-0 border-bottom-0"><span class="text-secondary">Razorpay net settlement</span><strong class="text-success"><?php echo $breakdown['razorpay_net_settlement_paise'] > 0 ? hub_money_paise($breakdown['razorpay_net_settlement_paise']) : '—'; ?></strong></li>
                <li class="list-group-item px-0 pb-0"><span class="text-secondary small">Net settlement is what Razorpay credits to the linked account after gateway fee and GST on the full collected amount.</span></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">Refunded</span><?php echo hub_money_paise((int) $payment['refunded_amount_paise']); ?></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">Refundable</span><strong class="text-primary"><?php echo hub_money_paise($refundable); ?></strong></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">Money receipt</span>
                    <?php if (!empty($payment['invoice_number'])): ?>
                        <span><code><?php echo htmlspecialchars((string) $payment['invoice_number']); ?></code>
                        <a class="small ms-1" target="_blank" rel="noopener" href="<?php echo hub_asset('pay/invoice.php?id=' . $paymentId . '&sig=' . urlencode(hub_invoice_public_signature($paymentId, $invoiceSecret))); ?>">View / PDF</a></span>
                    <?php else: ?>
                        <code>—</code>
                    <?php endif; ?>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">Razorpay</span><small class="font-monospace"><?php echo htmlspecialchars((string) ($payment['razorpay_payment_id'] ?? '—')); ?></small></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">Payer</span><?php echo htmlspecialchars((string) ($payer['name'] ?? '—')); ?></li>
                <?php if ($checkout): ?>
                <li class="list-group-item d-flex justify-content-between px-0"><span class="text-secondary">Checkout</span><?php echo hub_status_badge((string) $checkout['status'], 'checkout'); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-7">
        <?php if ($refundable > 0 && (string) $payment['status'] !== 'failed'): ?>
        <div class="hub-card-panel mb-3">
            <h2 class="h5 mb-3"><i class="bi bi-arrow-counterclockwise me-2"></i>Issue refund</h2>
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="refund">
                <div class="col-md-6">
                    <label class="form-label">Amount (₹)</label>
                    <input class="form-control" name="amount" inputmode="decimal" placeholder="Full: <?php echo htmlspecialchars(number_format($refundable / 100, 2, '.', '')); ?>">
                    <div class="form-text">Leave blank to refund full refundable balance.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reason</label>
                    <input class="form-control" name="reason" value="Refund approved by accounts">
                </div>
                <div class="col-12">
                    <button class="btn btn-warning" type="submit"><i class="bi bi-send me-1"></i> Refund via Razorpay</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        <?php if ($refunds !== []): ?>
        <div class="hub-card-panel">
            <h2 class="h5 mb-3">Refund history</h2>
            <div class="hub-table-wrap">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>ID</th><th>Amount</th><th>Status</th><th>Credit note</th></tr></thead>
                    <tbody>
                    <?php foreach ($refunds as $r): ?>
                        <tr>
                            <td class="small font-monospace"><?php echo htmlspecialchars((string) $r['razorpay_refund_id']); ?></td>
                            <td><?php echo hub_money_paise((int) $r['amount_paise']); ?></td>
                            <td><?php echo htmlspecialchars((string) $r['status']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['credit_note_number'] ?? '—')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<p class="mt-3"><a href="<?php echo hub_asset('admin/payments.php'); ?>" class="btn btn-link">← Back to payments</a></p>
<?php
hub_admin_layout_end();
