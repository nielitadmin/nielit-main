<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$msg = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'layout') {
        $json = (string) ($_POST['invoice_layout_json'] ?? '');
        if (hub_invoice_layout_save($hub_conn, $json)) {
            $msg = 'Custom layout saved. Turn on “Use custom drag-and-drop layout” under Money receipt letterhead to show payers this design.';
        } else {
            $error = 'Could not save layout — invalid JSON.';
        }
    }
}

$layout = hub_invoice_layout_get_or_default($hub_conn);
$defaultJson = json_encode(hub_invoice_layout_default(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$layoutJson = json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$palette = hub_invoice_palette();
$paletteJson = json_encode($palette, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tpl = hub_invoice_template_get($hub_conn);
$useDesigner = hub_receipt_uses_designer_layout($hub_conn);
$samplePaymentId = 0;
$q = $hub_conn->query("SELECT id FROM hub_payments WHERE invoice_number IS NOT NULL AND invoice_number != '' ORDER BY id DESC LIMIT 1");
if ($q && ($r = $q->fetch_assoc())) {
    $samplePaymentId = (int) $r['id'];
}

hub_admin_layout_start('Money receipt designer', 'invoices', 'Optional custom layout — standard blue receipt is used by default');
?>
<link href="<?php echo hub_asset('assets/css/hub-invoice-designer.css'); ?>" rel="stylesheet">
    <div class="alert alert-warning py-2 small mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Advanced:</strong> One <strong>A4 page</strong> (794 × 1123 px). Optional custom canvas layout.
    <strong>Live payer receipts use the standard blue template</strong> (single A4 with QR + system notice) unless you enable custom layout on
    <a href="<?php echo hub_asset('admin/money_receipt_template.php'); ?>">Money receipt letterhead</a>.
    Custom canvas is <?php echo $useDesigner ? '<strong class="text-success">enabled</strong> for receipts' : '<strong class="text-secondary">disabled</strong>'; ?>.
</div>
<?php if ($msg !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo hub_asset('admin/money_receipt_template.php'); ?>"><i class="bi bi-text-left me-1"></i> Money receipt letterhead</a>
    <?php if ($samplePaymentId > 0): ?>
        <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="<?php echo hub_asset('pay/receipt.php?id=' . $samplePaymentId . '&sig=' . urlencode(hub_invoice_public_signature($samplePaymentId, (string) $tpl['invoice_public_secret']))); ?>"><i class="bi bi-eye me-1"></i> Preview live receipt</a>
        <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="<?php echo hub_asset('pay/receipt.php?id=' . $samplePaymentId . '&preview=custom'); ?>"><i class="bi bi-layout-text-window-reverse me-1"></i> Preview custom canvas</a>
    <?php endif; ?>
</div>

<form method="post" id="hubDesignerForm">
    <input type="hidden" name="action" value="layout">
    <input type="hidden" name="invoice_layout_json" id="hubLayoutJson" value="">
    <div
        id="hubInvoiceDesigner"
        class="hub-designer-wrap"
        data-layout="<?php echo htmlspecialchars($layoutJson, ENT_QUOTES, 'UTF-8'); ?>"
        data-default="<?php echo htmlspecialchars($defaultJson, ENT_QUOTES, 'UTF-8'); ?>"
        data-palette="<?php echo htmlspecialchars($paletteJson, ENT_QUOTES, 'UTF-8'); ?>"
        data-logo="<?php echo htmlspecialchars(hub_brand_logo_url(), ENT_QUOTES, 'UTF-8'); ?>"
    >
        <div class="hub-card-panel hub-designer-palette">
            <h2 class="h6 fw-bold mb-2">Add blocks</h2>
            <p class="small text-secondary">Drag blocks inside the A4 page; resize from the bottom-right corner. Include <strong>QR code</strong> and <strong>System generated notice</strong> on the canvas (or they are added automatically when missing).</p>
            <div id="hubPalette">
                <?php foreach ($palette as $i => $item): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-add="<?php echo (int) $i; ?>">
                        <i class="bi bi-plus-lg me-1"></i><?php echo htmlspecialchars((string) $item['label']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <hr>
            <label class="form-label small">Zoom</label>
            <input type="range" class="form-range hub-designer-scale" id="hubDesScale" min="40" max="100" value="72">
            <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2" id="hubDesFitA4">Fit one A4 page (default layout)</button>
            <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2" id="hubDesReset">Reset to default layout</button>
            <p id="hubDesPageHint" class="small text-secondary mt-2 mb-0"></p>
        </div>
        <div class="hub-designer-canvas-outer">
            <div id="hubDesignerCanvas" class="hub-designer-canvas hub-designer-canvas--receipt"></div>
        </div>
        <div class="hub-card-panel hub-designer-props" id="hubDesignerProps">
            <h2 class="h6 fw-bold mb-2">Properties</h2>
        </div>
    </div>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save me-1"></i> Save custom layout</button>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
<script src="<?php echo hub_asset('assets/js/hub-invoice-designer.js'); ?>"></script>
<?php
hub_admin_layout_end();
