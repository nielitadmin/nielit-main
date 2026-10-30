<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$msg = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'layout') {
        $json = (string) ($_POST['invoice_layout_json'] ?? '');
        if (hub_invoice_layout_save($hub_conn, $json)) {
            $msg = 'Invoice layout saved. New invoices use this design.';
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
$samplePaymentId = 0;
$q = $hub_conn->query("SELECT id FROM hub_payments WHERE invoice_number IS NOT NULL AND invoice_number != '' ORDER BY id DESC LIMIT 1");
if ($q && ($r = $q->fetch_assoc())) {
    $samplePaymentId = (int) $r['id'];
}

hub_admin_layout_start('Invoice designer', 'invoices', 'Drag and drop blocks like Canva — save layout for all PDF invoices');
?>
<link href="<?php echo hub_asset('assets/css/hub-invoice-designer.css'); ?>" rel="stylesheet">
<?php if ($msg !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo hub_asset('admin/invoice_template.php'); ?>"><i class="bi bi-text-left me-1"></i> Letterhead text &amp; GST</a>
    <?php if ($samplePaymentId > 0): ?>
        <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="<?php echo hub_asset('pay/invoice.php?id=' . $samplePaymentId . '&sig=' . urlencode(hub_invoice_public_signature($samplePaymentId, (string) $tpl['invoice_public_secret']))); ?>"><i class="bi bi-eye me-1"></i> Preview live invoice</a>
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
            <p class="small text-secondary">Drag on the page; resize from bottom-right corner.</p>
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
            <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2" id="hubDesReset">Reset to default layout</button>
        </div>
        <div class="hub-designer-canvas-outer">
            <div id="hubDesignerCanvas" class="hub-designer-canvas"></div>
        </div>
        <div class="hub-card-panel hub-designer-props" id="hubDesignerProps">
            <h2 class="h6 fw-bold mb-2">Properties</h2>
        </div>
    </div>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save me-1"></i> Save layout</button>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
<script src="<?php echo hub_asset('assets/js/hub-invoice-designer.js'); ?>"></script>
<?php
hub_admin_layout_end();
