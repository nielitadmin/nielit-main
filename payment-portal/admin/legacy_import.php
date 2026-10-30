<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_require();

$msg = '';
$error = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv']['tmp_name'])) {
    $raw = (string) file_get_contents((string) $_FILES['csv']['tmp_name']);
    $result = hub_legacy_import_csv($hub_conn, $raw);
    if (!empty($result['ok'])) {
        $msg = 'Imported ' . (int) $result['imported'] . ' row(s). Skipped ' . (int) $result['skipped'] . ' already-paid reference(s).';
        if (!empty($result['errors'])) {
            $error = implode(' ', array_slice($result['errors'], 0, 5));
        }
    } else {
        $error = (string) ($result['error'] ?? 'Import failed');
    }
}

hub_admin_layout_start('Legacy import', 'legacy', 'One-time copy of old MIS payments into the hub register');
?>
<?php if ($msg !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="hub-card-panel mb-3">
    <p class="mb-2">Upload a CSV exported from MIS <code>online_course_payments</code> (or built to match the columns below). Rows are marked <strong>legacy</strong> in the register; invoice numbers from the file are kept when provided.</p>
    <p class="small text-secondary mb-0">Required columns: <code>source</code>, <code>reference</code>, <code>amount_inr</code>, <code>paid_at</code> (YYYY-MM-DD). Optional: payer fields, centre, purpose, invoice_number.</p>
</div>

<form method="post" enctype="multipart/form-data" class="hub-card-panel mb-4">
    <label class="form-label">CSV file</label>
    <input type="file" name="csv" accept=".csv,text/csv" class="form-control mb-3" required>
    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i> Import</button>
</form>

<div class="hub-card-panel">
    <h2 class="h6 fw-bold">Example row</h2>
    <code class="small user-select-all d-block">source,reference,amount_inr,paid_at,payer_name,payer_external_id,centre,purpose,invoice_number<br>
mis,MIS-LEGACY-1001,5000.00,2025-06-15,Riyan Nayak,NIELIT/2026/BBSR/0001,Bhubaneswar,Course fee,INV-OLD-1001</code>
</div>
<?php
hub_admin_layout_end();
