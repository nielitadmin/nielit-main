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

require_once __DIR__ . '/../includes/payment_hub_helper.php';

payment_gateway_ensure_schema($conn);
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['gateways'] ?? [];
    $saved = 0;
    if (is_array($posted)) {
        foreach (payment_gateway_catalog() as $provider => $meta) {
            $row = $posted[$provider] ?? [];
            if (!is_array($row)) {
                continue;
            }
            $enabled = !empty($row['enabled']) ? 1 : 0;
            $keyId = trim((string) ($row['key_id'] ?? ''));
            $keySecret = trim((string) ($row['key_secret'] ?? ''));
            $currentSecret = trim((string) (payment_gateway_get($conn, $provider)['key_secret'] ?? ''));
            if ($enabled && ($keyId === '' || ($keySecret === '' && $currentSecret === ''))) {
                $error_message = 'Enter both keys for ' . $meta['name'] . ' before turning it on.';
                break;
            }
            if (payment_gateway_save($conn, $provider, $enabled, $keyId, $keySecret)) {
                $saved++;
            }
        }
    }
    if ($error_message === '' && $saved > 0) {
        $success_message = 'Payment gateways saved. Students see only the gateways that are switched on and have both keys.';
    } elseif ($error_message === '') {
        $error_message = 'Could not save the payment gateways.';
    }
}

$gateways = payment_gateway_list($conn);
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
    <style>
        .payment-gateway-page {
            margin-left: 260px;
            padding: 28px 32px 48px;
            min-height: 100vh;
            box-sizing: border-box;
            max-width: 100%;
        }
        .payment-gateway-page .page-header h1 {
            margin: 0 0 8px;
        }
        .payment-gateway-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 16px;
            margin-top: 18px;
        }
        .payment-gateway-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 16px 8px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }
        .payment-gateway-card h2 {
            font-size: 1.15rem;
            margin: 0 0 4px;
        }
        @media (max-width: 991.98px) {
            .payment-gateway-page {
                margin-left: 0;
                padding: 88px 16px 32px;
            }
        }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="payment-gateway-page">
    <div class="page-header">
        <h1><i class="fas fa-credit-card"></i> Payment Gateways</h1>
        <p class="text-muted mb-0">Enter the keys for any gateway you want to use. A course fee is collected online only when that course has online payment switched on.</p>
    </div>

    <?php if ($success_message !== ''): ?>
        <div class="alert alert-success mt-3"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if (payment_hub_is_ready()): ?>
        <div class="alert alert-info mt-3 mb-0">
            <strong>Central payment portal is active.</strong> Course fees use Razorpay keys on the hub only —
            <a href="<?php echo htmlspecialchars(payment_hub_admin_url('admin/gateway.php')); ?>" target="_blank" rel="noopener">Payment hub → Gateway</a>
            (not the Razorpay fields below).
        </div>
    <?php else: ?>
        <div class="alert alert-secondary mt-3 small mb-0">
            To collect course fees on <code>payment.nielitbhubaneswar.in</code>, open
            <a href="<?php echo htmlspecialchars(APP_URL . '/admin/payment_hub.php'); ?>">Central Payment Portal</a> and enable the integration.
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="payment-gateway-grid">
            <?php foreach ($gateways as $gateway): ?>
                <?php
                $provider = (string) $gateway['provider'];
                $hasSecret = trim((string) ($gateway['key_secret'] ?? '')) !== '';
                ?>
                <div class="payment-gateway-card">
                    <h2><?php echo htmlspecialchars((string) $gateway['name']); ?></h2>
                    <p class="text-muted small"><?php echo htmlspecialchars((string) $gateway['hint']); ?></p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="gateways[<?php echo htmlspecialchars($provider); ?>][enabled]" id="enabled_<?php echo htmlspecialchars($provider); ?>" value="1" <?php echo !empty($gateway['is_enabled']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enabled_<?php echo htmlspecialchars($provider); ?>">Use <?php echo htmlspecialchars((string) $gateway['name']); ?></label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="key_<?php echo htmlspecialchars($provider); ?>"><?php echo htmlspecialchars((string) $gateway['key_label']); ?></label>
                        <input class="form-control" id="key_<?php echo htmlspecialchars($provider); ?>" name="gateways[<?php echo htmlspecialchars($provider); ?>][key_id]" value="<?php echo htmlspecialchars((string) ($gateway['key_id'] ?? '')); ?>" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="secret_<?php echo htmlspecialchars($provider); ?>"><?php echo htmlspecialchars((string) $gateway['secret_label']); ?></label>
                        <input class="form-control" id="secret_<?php echo htmlspecialchars($provider); ?>" name="gateways[<?php echo htmlspecialchars($provider); ?>][key_secret]" type="password" value="" placeholder="<?php echo $hasSecret ? 'Saved. Leave blank to keep it.' : 'Enter the secret'; ?>" autocomplete="new-password">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Save gateways</button>
    </form>
</div>
</body>
</html>
