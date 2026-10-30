<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
$checkout = hub_checkout_find_by_token($hub_conn, $token);
$cfg = hub_razorpay_config($hub_conn);

if (!$checkout) {
    hub_pay_message_page('Not found', 'This payment link is invalid or has been removed.', 'danger');
}

if (hub_checkout_is_expired($checkout)) {
    hub_checkout_mark($hub_conn, (int) $checkout['id'], 'expired');
    hub_pay_message_page('Expired', 'This payment link has expired. Please start payment again from the course or test page.', 'warning');
}

$checkoutStatus = (string) ($checkout['status'] ?? '');
if ($checkoutStatus === 'failed') {
    hub_pay_message_page('Failed', 'This payment attempt failed. Please try again from the course or test page.', 'danger');
}
if ($checkoutStatus === 'cancelled') {
    hub_pay_message_page('Cancelled', 'Payment was cancelled. You can try again when ready.', 'warning');
}

if ($checkoutStatus === 'paid') {
    header('Location: ' . $checkout['return_url'] . (strpos($checkout['return_url'], '?') !== false ? '&' : '?') . 'hub_token=' . urlencode($token));
    exit;
}

$payer = json_decode((string) $checkout['payer_json'], true);
if (!is_array($payer)) {
    $payer = [];
}

$orderId = (string) ($checkout['razorpay_order_id'] ?? '');
if ($orderId === '' && hub_razorpay_ready($hub_conn)) {
    $order = hub_razorpay_create_order($hub_conn, $checkout);
    if (!empty($order['ok'])) {
        $orderId = (string) ($order['data']['id'] ?? '');
        hub_checkout_mark($hub_conn, (int) $checkout['id'], 'attempted', $orderId);
        $checkout['razorpay_order_id'] = $orderId;
        $checkout['status'] = 'attempted';
    }
}

$amountRupees = number_format(((int) $checkout['amount_paise']) / 100, 2);
$returnUrl = (string) $checkout['return_url'];

hub_public_layout_start('Pay — ' . (string) $checkout['purpose'], 'hero');
?>
<div class="hub-public-hero flex-grow-1">
    <nav class="hub-public-nav">
        <a class="brand" href="<?php echo hub_asset('/'); ?>"><i class="bi bi-wallet2 text-primary"></i> NIELIT Payment</a>
    </nav>
    <div class="container py-4">
        <div class="hub-pay-card">
            <p class="text-uppercase small fw-semibold text-secondary mb-1">Secure payment</p>
            <h1 class="h4 fw-bold mb-2"><?php echo htmlspecialchars((string) $checkout['purpose']); ?></h1>
            <p class="text-secondary small mb-4"><?php echo htmlspecialchars((string) $checkout['description']); ?></p>
            <div class="hub-pay-amount mb-4">₹ <?php echo htmlspecialchars($amountRupees); ?></div>
            <ul class="list-unstyled small mb-4">
                <li class="mb-2"><i class="bi bi-building me-2 text-primary"></i><?php echo htmlspecialchars((string) ($checkout['source_name'] ?? '')); ?></li>
                <li><i class="bi bi-person me-2 text-primary"></i><?php echo htmlspecialchars((string) ($payer['name'] ?? 'Payer')); ?></li>
            </ul>
            <?php if (!hub_razorpay_ready($hub_conn)): ?>
                <div class="alert alert-warning mb-0">Razorpay is not configured on this portal yet.</div>
            <?php elseif ($orderId === ''): ?>
                <div class="alert alert-danger mb-0">Could not start checkout. Try again later.</div>
            <?php else: ?>
                <button type="button" class="btn btn-primary btn-lg w-100" id="payBtn">
                    <i class="bi bi-shield-lock me-2"></i>Proceed to Razorpay
                </button>
                <p class="text-center text-secondary small mt-3 mb-0">UPI · Cards · Netbanking</p>
                <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                <script>
                document.getElementById('payBtn').addEventListener('click', function () {
                    var options = {
                        key: <?php echo json_encode($cfg['key_id']); ?>,
                        amount: <?php echo (int) $checkout['amount_paise']; ?>,
                        currency: <?php echo json_encode($checkout['currency']); ?>,
                        name: 'NIELIT Bhubaneswar',
                        description: <?php echo json_encode((string) $checkout['purpose']); ?>,
                        order_id: <?php echo json_encode($orderId); ?>,
                        prefill: {
                            name: <?php echo json_encode((string) ($payer['name'] ?? '')); ?>,
                            email: <?php echo json_encode((string) ($payer['email'] ?? '')); ?>,
                            contact: <?php echo json_encode((string) ($payer['phone'] ?? '')); ?>
                        },
                        handler: function (response) {
                            var form = document.createElement('form');
                            form.method = 'POST';
                            form.action = <?php echo json_encode(hub_url('pay/complete.php?token=' . urlencode($token))); ?>;
                            ['razorpay_order_id','razorpay_payment_id','razorpay_signature'].forEach(function (k) {
                                var input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = k;
                                input.value = response[k];
                                form.appendChild(input);
                            });
                            document.body.appendChild(form);
                            form.submit();
                        },
                        modal: {
                            ondismiss: function () {
                                try {
                                    navigator.sendBeacon(<?php echo json_encode(hub_url('pay/cancel.php?token=' . urlencode($token))); ?>);
                                } catch (e) {}
                                window.location.href = <?php echo json_encode($returnUrl . (strpos($returnUrl, '?') !== false ? '&' : '?') . 'hub_status=cancelled&hub_token=' . urlencode($token)); ?>;
                            }
                        }
                    };
                    new Razorpay(options).open();
                });
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
hub_public_layout_end();
