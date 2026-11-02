<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hub_invoice_template_save($hub_conn, $_POST);
    $msg = 'Money receipt letterhead saved.';
}

$tpl = hub_invoice_template_get($hub_conn);
$useDesigner = hub_receipt_uses_designer_layout($hub_conn);
$samplePaymentId = 0;
$q = $hub_conn->query("SELECT id FROM hub_payments WHERE invoice_number IS NOT NULL AND invoice_number != '' ORDER BY id DESC LIMIT 1");
if ($q && ($r = $q->fetch_assoc())) {
    $samplePaymentId = (int) $r['id'];
}

hub_admin_layout_start('Money receipt letterhead', 'invoice_settings', 'Organisation details for the blue money receipt (not a tax invoice)');
?>
<div class="alert alert-primary py-2 small mb-3">
    <i class="bi bi-receipt me-1"></i>
    Payers see the <strong>standard blue money receipt</strong> (logo, amount, QR code, system notice). Enable <strong>custom drag-and-drop layout</strong> only if you need a different printable layout.
</div>
<div class="mb-3 d-flex flex-wrap gap-2">
    <a class="btn btn-outline-primary btn-sm" href="<?php echo hub_asset('admin/money_receipt_designer.php'); ?>"><i class="bi bi-magic me-1"></i> Money receipt designer</a>
    <?php if ($samplePaymentId > 0): ?>
        <a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" href="<?php echo hub_asset('pay/receipt.php?id=' . $samplePaymentId . '&sig=' . urlencode(hub_invoice_public_signature($samplePaymentId, (string) $tpl['invoice_public_secret']))); ?>"><i class="bi bi-eye me-1"></i> Preview live receipt</a>
    <?php endif; ?>
</div>
<?php if ($msg !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="hub-card-panel">
            <form method="post" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Document title</label>
                    <input class="form-control" name="invoice_title" value="<?php echo htmlspecialchars((string) $tpl['invoice_title']); ?>" placeholder="Money Receipt">
                    <div class="form-text">Use “Money Receipt” — this is not a tax invoice.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Organisation name</label>
                    <input class="form-control" name="invoice_org_name" value="<?php echo htmlspecialchars((string) $tpl['invoice_org_name']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Centre / unit line</label>
                    <input class="form-control" name="invoice_org_unit" value="<?php echo htmlspecialchars((string) $tpl['invoice_org_unit']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" name="invoice_org_address" rows="3"><?php echo htmlspecialchars((string) $tpl['invoice_org_address']); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">GSTIN</label>
                    <input class="form-control font-monospace" name="invoice_org_gstin" value="<?php echo htmlspecialchars((string) $tpl['invoice_org_gstin']); ?>" placeholder="Optional">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input class="form-control" name="invoice_org_phone" value="<?php echo htmlspecialchars((string) $tpl['invoice_org_phone']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Email on receipt</label>
                    <input class="form-control" type="email" name="invoice_org_email" value="<?php echo htmlspecialchars((string) $tpl['invoice_org_email']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Extra footer note</label>
                    <textarea class="form-control" name="invoice_footer" rows="2"><?php echo htmlspecialchars((string) $tpl['invoice_footer']); ?></textarea>
                    <div class="form-text">Shown below the fixed line: “<?php echo htmlspecialchars(hub_receipt_system_notice()); ?>”</div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="invoice_show_gst" value="1" id="invoice_show_gst" <?php echo ($tpl['invoice_show_gst'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="invoice_show_gst">Show GST breakup placeholder (configure rates with accounts later)</label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check border rounded p-3 bg-light">
                        <input class="form-check-input" type="checkbox" name="receipt_use_designer_layout" value="1" id="receipt_use_designer_layout" <?php echo $useDesigner ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="receipt_use_designer_layout">
                            <strong>Use custom drag-and-drop layout</strong> for payer receipts (from Money receipt designer)
                        </label>
                        <div class="form-text mb-0">Leave unchecked to use the standard blue receipt with QR code. Most centres should leave this off.</div>
                    </div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Save letterhead</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="hub-card-panel mb-3">
            <h2 class="h6 text-uppercase text-secondary">Standard receipt includes</h2>
            <ul class="small text-secondary mb-0 ps-3">
                <li>NIELIT logo and letterhead from this page</li>
                <li>Receipt number, amount in figures and words</li>
                <li>Mode of transaction (UPI, card, etc.)</li>
                <li>QR code linking to the signed receipt URL</li>
                <li><?php echo htmlspecialchars(hub_receipt_system_notice()); ?></li>
            </ul>
        </div>
        <div class="hub-card-panel">
            <h2 class="h6 text-uppercase text-secondary">Public money receipt links</h2>
            <p class="small text-secondary">Staff can copy a signed link from each payment. Payers need the signature query parameter; links are not guessable.</p>
            <p class="small mb-0">Format: <code class="hub-secret-box d-block mt-2"><?php echo htmlspecialchars(hub_url('pay/receipt.php?id=PAYMENT_ID&sig=…')); ?></code></p>
        </div>
    </div>
</div>
<?php
hub_admin_layout_end();
