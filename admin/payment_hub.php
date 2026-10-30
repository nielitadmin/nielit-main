<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/payment_gateway_helper.php';
require_once __DIR__ . '/../includes/payment_hub_helper.php';

if (!isset($_SESSION['admin']) || ($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit();
}

payment_gateway_ensure_schema($conn);
payment_hub_import_file_config($conn);

$success_message = '';
$error_message = '';
$test_message = '';
$test_error = '';

$cfg = payment_hub_config();
$urls = payment_hub_integration_urls();
$hubRow = payment_hub_load_db_row($conn);
$hasSecret = trim((string) ($hubRow['shared_secret'] ?? '')) !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'test') {
        $test = payment_hub_test_connection(payment_hub_config());
        if (!empty($test['ok'])) {
            $test_message = (string) ($test['message'] ?? 'OK');
        } else {
            $test_error = (string) ($test['error'] ?? 'Test failed');
        }
    } else {
        $postedSecret = trim((string) ($_POST['shared_secret'] ?? ''));
        if (!empty($_POST['enabled']) && $postedSecret === '' && !$hasSecret) {
            $error_message = 'Paste the MIS shared secret from the payment hub (Admin → Sources) before enabling.';
        } elseif (payment_hub_save_settings($conn, array_merge($_POST, ['keep_invoice_secret' => true]))) {
            $success_message = 'Central payment portal settings saved.';
            $cfg = payment_hub_config();
            $hubRow = payment_hub_load_db_row($conn);
            $hasSecret = trim((string) ($hubRow['shared_secret'] ?? '')) !== '';
        } else {
            $error_message = 'Could not save settings.';
        }
    }
}

$portalPublic = payment_hub_public_url($cfg);
$page_title = 'Central Payment Portal';
$active_theme = loadActiveTheme($conn);
$ready = payment_hub_is_ready();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .payment-hub-page { margin-left: 260px; padding: 28px 32px 48px; min-height: 100vh; box-sizing: border-box; max-width: 960px; }
        .hub-link-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
        .hub-link-card a { word-break: break-all; }
        .mono { font-family: ui-monospace, monospace; font-size: 0.9rem; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="payment-hub-page">
    <div class="page-header">
        <h1><i class="fas fa-university"></i> Central Payment Portal</h1>
        <p class="text-muted mb-0">Connect MIS course fees to <strong>payment.nielitbhubaneswar.in</strong>. Razorpay keys live only on the payment hub.</p>
    </div>

    <?php if ($success_message !== ''): ?><div class="alert alert-success mt-3"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
    <?php if ($error_message !== ''): ?><div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
    <?php if ($test_message !== ''): ?><div class="alert alert-success mt-3"><?php echo htmlspecialchars($test_message); ?></div><?php endif; ?>
    <?php if ($test_error !== ''): ?><div class="alert alert-warning mt-3"><?php echo htmlspecialchars($test_error); ?></div><?php endif; ?>

    <div class="hub-link-card mt-3">
        <h2 class="h6 fw-bold">Payment hub (staff register)</h2>
        <p class="small text-muted mb-2">Open the hub admin to copy the MIS shared secret, configure Razorpay, and view payments.</p>
        <a class="btn btn-outline-primary btn-sm me-2" href="<?php echo htmlspecialchars(payment_hub_admin_url('admin/dashboard.php')); ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Open payment hub admin</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($portalPublic . '/'); ?>" target="_blank" rel="noopener">Public landing page</a>
        <?php if ($ready): ?>
            <span class="badge bg-success ms-2">MIS integration active</span>
        <?php else: ?>
            <span class="badge bg-secondary ms-2">Not enabled yet</span>
        <?php endif; ?>
    </div>

    <div class="hub-link-card">
        <h2 class="h6 fw-bold">URLs to register on the hub (Sources → MIS)</h2>
        <p class="small text-muted">Allowed return URL must include your MIS site prefix. Notify URL must be reachable from the payment server.</p>
        <table class="table table-sm mb-0">
            <tr><td class="text-muted" style="width:140px">Notify URL</td><td class="mono"><?php echo htmlspecialchars($urls['notify_url']); ?></td></tr>
            <tr><td class="text-muted">Return URL</td><td class="mono"><?php echo htmlspecialchars($urls['return_url']); ?></td></tr>
            <tr><td class="text-muted">This MIS site</td><td class="mono"><?php echo htmlspecialchars(rtrim((string) APP_URL, '/') . '/'); ?></td></tr>
        </table>
    </div>

    <form method="post" class="card p-4 mt-3">
        <input type="hidden" name="action" value="save">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="enabled" value="1" id="hub_enabled" <?php echo !empty($cfg['enabled']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="hub_enabled"><strong>Use central portal for online course fees</strong></label>
            <div class="small text-muted">When on, students pay on the hub; local Razorpay keys on Payment Gateways are not used for course checkout.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Portal base URL</label>
            <input class="form-control mono" name="api_base" value="<?php echo htmlspecialchars((string) $cfg['api_base']); ?>" placeholder="https://payment.nielitbhubaneswar.in">
            <div class="form-text">Local XAMPP: <?php echo htmlspecialchars(rtrim((string) APP_URL, '/') . '/payment-portal'); ?></div>
        </div>
        <div class="mb-3">
            <label class="form-label">Source code on hub</label>
            <input class="form-control mono" name="source" value="<?php echo htmlspecialchars((string) $cfg['source']); ?>" maxlength="32">
        </div>
        <div class="mb-3">
            <label class="form-label">Shared secret (from hub Admin → Sources → MIS)</label>
            <input class="form-control mono" type="password" name="shared_secret" autocomplete="new-password" placeholder="<?php echo $hasSecret ? 'Saved — leave blank to keep' : 'Paste secret here'; ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Invoice public secret (optional)</label>
            <input class="form-control mono" type="password" name="invoice_public_secret" autocomplete="new-password" placeholder="<?php echo trim((string) ($cfg['invoice_public_secret'] ?? '')) !== '' ? 'Saved — leave blank to keep' : 'From hub Invoice & GST'; ?>">
            <div class="form-text">Lets “View invoice” open the hub PDF for students.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Save settings</button>
        </div>
    </form>

    <form method="post" class="mt-2">
        <input type="hidden" name="action" value="test">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Test connection to checkout API</button>
    </form>

    <div class="alert alert-light border mt-4 small">
        <strong>Test a fee payment:</strong> Enable a course for online payment, log in as a student → Fee Details → Pay via Payment Portal.
        After Razorpay test payment, the student should return paid and see the hub invoice.
    </div>
</div>
</body>
</html>
