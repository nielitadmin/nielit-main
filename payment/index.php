<?php

require_once __DIR__ . '/../includes/bootstrap.php';

hub_public_layout_start(HUB_APP_NAME, 'hero');
?>
<div class="hub-public-hero flex-grow-1">
    <nav class="hub-public-nav">
        <a class="brand" href="<?php echo hub_asset('/'); ?>"><i class="bi bi-wallet2 text-primary"></i> NIELIT Payment Hub</a>
        <a class="btn btn-primary" href="<?php echo hub_asset('admin/login.php'); ?>"><i class="bi bi-lock me-1"></i> Staff login</a>
    </nav>
    <div class="container py-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <p class="text-uppercase fw-semibold text-primary small mb-2">payment.nielitbhubaneswar.in</p>
                <h1 class="display-5 fw-bold mb-3" style="font-family: var(--hub-font-display);"><?php echo htmlspecialchars(HUB_APP_NAME); ?></h1>
                <p class="lead text-secondary">MIS, mock tests, and other NIELIT Bhubaneswar websites redirect payers here. One Razorpay account, one invoice register, full refund trail.</p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <span class="badge rounded-pill text-bg-light border px-3 py-2"><i class="bi bi-credit-card me-1"></i> UPI · Card · Netbanking</span>
                    <span class="badge rounded-pill text-bg-light border px-3 py-2"><i class="bi bi-file-earmark-pdf me-1"></i> Invoices</span>
                    <span class="badge rounded-pill text-bg-light border px-3 py-2"><i class="bi bi-arrow-counterclockwise me-1"></i> Refunds</span>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hub-card-panel">
                    <h2 class="h5 fw-bold">For integrating websites</h2>
                    <p class="text-secondary small">POST signed JSON to the checkout API; redirect the browser to the returned pay URL.</p>
                    <code class="d-block hub-secret-box mb-3"><?php echo htmlspecialchars(hub_url('api/v1/checkout.php')); ?></code>
                    <p class="small text-secondary mb-0">Staff configure sources, gateway keys, and refunds from the <a href="<?php echo hub_asset('admin/login.php'); ?>">admin dashboard</a>.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
hub_public_layout_end();
