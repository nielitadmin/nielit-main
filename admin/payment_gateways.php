<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/payment_gateway_helper.php';

if (!isset($_SESSION['admin']) || ($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit();
}

payment_gateway_ensure_schema($conn);
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = isset($_POST['razorpay_enabled']) ? 1 : 0;
    $keyId = trim((string) ($_POST['key_id'] ?? ''));
    $keySecret = trim((string) ($_POST['key_secret'] ?? ''));
    if ($enabled && $keyId === '') {
        $error_message = 'Enter the Razorpay Key ID before turning the gateway on.';
    } elseif ($enabled && $keySecret === '' && trim((string) (payment_gateway_get($conn)['key_secret'] ?? '')) === '') {
        $error_message = 'Enter the Razorpay Key Secret before turning the gateway on.';
    } elseif (payment_gateway_save_razorpay($conn, $enabled, $keyId, $keySecret)) {
        $success_message = 'Payment gateway settings saved.';
    } else {
        $error_message = 'Could not save the payment gateway settings.';
    }
}

$gateway = payment_gateway_get($conn) ?: [];
$hasSecret = trim((string) ($gateway['key_secret'] ?? '')) !== '';
$page_title = 'Payment Gateways';
$active_theme = loadActiveTheme($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <?php adminEmitHeadAssets($active_theme); ?>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-credit-card"></i> Payment Gateways</h1>
        <p>Turn on Razorpay and enter the keys from the Razorpay dashboard. Course fees are collected only for courses that have online payment switched on.</p>
    </div>

    <?php if ($success_message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width:720px;">
        <div class="card-body">
            <form method="post">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="razorpay_enabled" id="razorpay_enabled" value="1" <?php echo !empty($gateway['is_enabled']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="razorpay_enabled">Enable Razorpay for course fee payments</label>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="key_id">Key ID</label>
                    <input class="form-control" id="key_id" name="key_id" value="<?php echo htmlspecialchars((string) ($gateway['key_id'] ?? '')); ?>" placeholder="rzp_test_... or rzp_live_..." autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="key_secret">Key Secret</label>
                    <input class="form-control" id="key_secret" name="key_secret" type="password" value="" placeholder="<?php echo $hasSecret ? 'Saved. Leave blank to keep the current secret.' : 'Enter the key secret'; ?>" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary">Save gateway</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
