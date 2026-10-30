<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hub_invoice_template_save($hub_conn, $_POST);
    $msg = 'Invoice template saved.';
}

$tpl = hub_invoice_template_get($hub_conn);
$samplePaymentId = 0;
$q = $hub_conn->query("SELECT id FROM hub_payments WHERE invoice_number IS NOT NULL AND invoice_number != '' ORDER BY id DESC LIMIT 1");
if ($q && ($r = $q->fetch_assoc())) {
    $samplePaymentId = (int) $r['id'];
}

hub_admin_layout_start('Invoice template', 'invoices', 'Letterhead, GST fields, and receipt wording');
?>
<div class="mb-3">
    <a class="btn btn-outline-primary btn-sm" href="<?php echo hub_asset('admin/invoice_designer.php'); ?>"><i class="bi bi-magic me-1"></i> Open drag &amp; drop designer</a>
</div>
<?php if ($msg !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="hub-card-panel">
            <form method="post" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Document title</label>
                    <input class="form-control" name="invoice_title" value="<?php echo htmlspecialchars((string) $tpl['invoice_title']); ?>">
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
                    <label class="form-label">Email on invoice</label>
                    <input class="form-control" type="email" name="invoice_org_email" value="<?php echo htmlspecialchars((string) $tpl['invoice_org_email']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Footer note</label>
                    <textarea class="form-control" name="invoice_footer" rows="2"><?php echo htmlspecialchars((string) $tpl['invoice_footer']); ?></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="invoice_show_gst" value="1" id="invoice_show_gst" <?php echo ($tpl['invoice_show_gst'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="invoice_show_gst">Show GST breakup placeholder (configure rates with accounts later)</label>
                    </div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Save template</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="hub-card-panel mb-3">
            <h2 class="h6 text-uppercase text-secondary">Preview</h2>
            <p class="small text-secondary">Opens the latest paid invoice using the current template.</p>
            <?php if ($samplePaymentId > 0): ?>
                <a class="btn btn-outline-primary w-100 mb-2" target="_blank" rel="noopener" href="<?php echo hub_asset('pay/invoice.php?id=' . $samplePaymentId . '&sig=' . urlencode(hub_invoice_public_signature($samplePaymentId, (string) $tpl['invoice_public_secret']))); ?>"><i class="bi bi-eye me-1"></i> Preview sample invoice</a>
            <?php else: ?>
                <p class="small text-secondary mb-0">No paid payments yet — complete a test payment to preview.</p>
            <?php endif; ?>
        </div>
        <div class="hub-card-panel">
            <h2 class="h6 text-uppercase text-secondary">Public invoice links</h2>
            <p class="small text-secondary">Staff can copy a signed link from each payment. Payers need the signature query parameter; links are not guessable.</p>
            <p class="small mb-0">Format: <code class="hub-secret-box d-block mt-2"><?php echo htmlspecialchars(hub_url('pay/invoice.php?id=PAYMENT_ID&sig=…')); ?></code></p>
        </div>
    </div>
</div>
<?php
hub_admin_layout_end();
