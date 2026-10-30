<?php

function hub_pay_message_page(string $title, string $message, string $type = 'warning'): void
{
    hub_public_layout_start($title, 'hero');
    $alert = $type === 'danger' ? 'danger' : ($type === 'success' ? 'success' : 'warning');
    ?>
    <div class="hub-public-hero">
        <nav class="hub-public-nav">
            <a class="brand" href="<?php echo hub_asset('/'); ?>"><i class="bi bi-wallet2 text-primary"></i> NIELIT Payment</a>
        </nav>
        <div class="container py-5">
            <div class="hub-pay-card text-center">
                <div class="alert alert-<?php echo htmlspecialchars($alert); ?>"><?php echo htmlspecialchars($message); ?></div>
                <p class="text-secondary small mb-0">Return to the website where you started payment and try again if needed.</p>
            </div>
        </div>
    </div>
    <?php
    hub_public_layout_end();
    exit;
}
