<?php

require_once __DIR__ . '/includes/bootstrap.php';

$checkoutApi = hub_url('api/v1/checkout.php');
hub_public_layout_start(HUB_APP_NAME, 'hero', true);
?>
<div class="hub-landing-bg" aria-hidden="true">
    <span class="orb orb-a"></span>
    <span class="orb orb-b"></span>
    <span class="orb orb-c"></span>
</div>
<div class="hub-public-hero flex-grow-1 hub-landing-shell">
    <nav class="hub-public-nav hub-animate" style="--hub-delay: 0ms">
        <a class="brand" href="<?php echo hub_asset('/'); ?>">
            <?php echo hub_brand_mark_html('hub-brand-logo'); ?>
            <span><?php echo htmlspecialchars(HUB_BRAND_SHORT); ?></span>
        </a>
        <a class="btn btn-primary hub-btn-glow" href="<?php echo hub_asset('admin/login.php'); ?>"><i class="bi bi-lock me-1"></i> Staff login</a>
    </nav>

    <div class="container py-4 py-lg-5">
        <div class="row align-items-center g-5 mb-5 mb-lg-6">
            <div class="col-lg-6 hub-landing-hero">
                <p class="hub-hero-badge hub-animate mb-3" style="--hub-delay: 60ms">
                    <i class="bi bi-shield-lock-fill"></i> payment.nielitbhubaneswar.in
                </p>
                <h1 class="display-5 hub-hero-title mb-3 hub-animate" style="--hub-delay: 120ms"><?php echo htmlspecialchars(HUB_APP_NAME); ?></h1>
                <p class="lead text-secondary hub-animate" style="--hub-delay: 200ms"><?php echo htmlspecialchars(HUB_TAGLINE); ?></p>
                <div class="hub-pay-methods mt-4 hub-animate" style="--hub-delay: 280ms">
                    <span class="hub-pay-chip"><i class="bi bi-phone text-primary"></i> UPI</span>
                    <span class="hub-pay-chip"><i class="bi bi-credit-card text-primary"></i> Cards</span>
                    <span class="hub-pay-chip"><i class="bi bi-bank text-primary"></i> Netbanking</span>
                    <span class="hub-pay-chip"><i class="bi bi-file-earmark-pdf text-primary"></i> Invoices</span>
                    <span class="hub-pay-chip"><i class="bi bi-arrow-counterclockwise text-primary"></i> Refunds</span>
                </div>
            </div>
            <div class="col-lg-6 hub-animate" style="--hub-delay: 160ms">
                <div class="hub-visual-orbit d-none d-md-flex">
                    <div class="hub-orbit-ring"></div>
                    <div class="hub-orbit-ring inner"></div>
                    <div class="hub-orbit-core">
                        <img src="<?php echo htmlspecialchars(hub_brand_logo_url()); ?>" alt="NIELIT Bhubaneswar" width="72" height="72">
                        <p class="small fw-semibold mb-0 text-secondary">Official fee gateway</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="hub-feature-grid mb-5">
            <div class="hub-feature-tile hub-animate">
                <div class="icon-wrap"><i class="bi bi-shield-check"></i></div>
                <h2 class="h6 fw-bold mb-1">Signed checkout</h2>
                <p class="small text-secondary mb-0">Each portal calls the API with a shared secret—no keys on student sites.</p>
            </div>
            <div class="hub-feature-tile hub-animate">
                <div class="icon-wrap"><i class="bi bi-receipt-cutoff"></i></div>
                <h2 class="h6 fw-bold mb-1">One invoice register</h2>
                <p class="small text-secondary mb-0">Numbered <?php echo htmlspecialchars(HUB_INVOICE_PREFIX); ?> series for every successful payment.</p>
            </div>
            <div class="hub-feature-tile hub-animate">
                <div class="icon-wrap"><i class="bi bi-arrow-repeat"></i></div>
                <h2 class="h6 fw-bold mb-1">Refund trail</h2>
                <p class="small text-secondary mb-0">Razorpay refunds, credit notes, and webhook history in one dashboard.</p>
            </div>
        </div>

        <div class="row g-4 g-lg-5 align-items-stretch">
            <div class="col-lg-5">
                <div class="hub-card-panel hub-flow-card h-100 hub-animate">
                    <h2 class="h5 fw-bold mb-3">How it works</h2>
                    <div class="hub-flow-steps">
                        <div class="hub-flow-step">
                            <span class="step-num">1</span>
                            <div>
                                <strong class="d-block">Student pays on MIS or partner site</strong>
                                <span class="small text-secondary">The site creates a signed checkout request.</span>
                            </div>
                        </div>
                        <div class="hub-flow-step">
                            <span class="step-num">2</span>
                            <div>
                                <strong class="d-block">Browser opens this hub</strong>
                                <span class="small text-secondary">Razorpay Checkout runs securely on payment.nielitbhubaneswar.in.</span>
                            </div>
                        </div>
                        <div class="hub-flow-step">
                            <span class="step-num">3</span>
                            <div>
                                <strong class="d-block">Receipt &amp; notify</strong>
                                <span class="small text-secondary">Invoice is issued; the source site gets a signed status callback.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="hub-card-panel hub-api-panel h-100 hub-animate">
                    <h2 class="h5 fw-bold">For integrating websites</h2>
                    <p class="text-secondary small">POST signed JSON to the checkout API, then redirect the payer to the returned pay URL.</p>
                    <div class="hub-secret-box hub-api-url mb-3" id="hubCheckoutApi"><?php echo htmlspecialchars($checkoutApi); ?></div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-hub-copy="<?php echo htmlspecialchars($checkoutApi); ?>">
                            <i class="bi bi-clipboard me-1"></i> <span class="hub-copy-label">Copy API URL</span>
                        </button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo hub_asset('admin/login.php'); ?>"><i class="bi bi-speedometer2 me-1"></i> Admin dashboard</a>
                    </div>
                    <p class="small text-secondary mb-0">Staff configure sources, gateway keys, and refunds from the admin area—Razorpay credentials stay on this server only.</p>
                </div>
            </div>
        </div>
    </div>

    <footer class="hub-landing-footer text-center text-secondary small py-4 mt-auto">
        <div class="container">
            <?php echo htmlspecialchars(HUB_BRAND_SHORT); ?> · NIELIT Bhubaneswar · <?php echo htmlspecialchars(HUB_INVOICE_PREFIX); ?> invoices
        </div>
    </footer>
</div>
<?php
hub_public_layout_end(true);
