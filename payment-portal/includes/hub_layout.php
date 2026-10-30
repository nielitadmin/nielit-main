<?php

function hub_asset(string $path): string
{
    return htmlspecialchars(hub_url(ltrim($path, '/')));
}

function hub_brand_logo_url(): string
{
    return hub_url('assets/images/nielit-logo.png');
}

function hub_layout_head_meta(string $pageTitle): void
{
    $description = defined('HUB_TAGLINE') ? HUB_TAGLINE : 'NIELIT Bhubaneswar central payment portal';
    ?>
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <link rel="icon" href="<?php echo hub_asset('assets/images/favicon.svg'); ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?php echo hub_asset('assets/images/nielit-logo.png'); ?>">
    <?php
}

function hub_layout_assets(bool $landingExtras = false): void
{
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Instrument+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo hub_asset('assets/css/hub-theme.css'); ?>" rel="stylesheet">
    <?php
    if ($landingExtras) {
        echo '<link href="' . hub_asset('assets/css/hub-landing.css') . '" rel="stylesheet">' . "\n";
    }
}

function hub_brand_mark_html(string $class = 'hub-brand-logo'): string
{
    $alt = defined('HUB_BRAND_SHORT') ? HUB_BRAND_SHORT : 'NIELIT Payment Hub';
    return '<img class="' . htmlspecialchars($class) . '" src="' . htmlspecialchars(hub_brand_logo_url()) . '" alt="' . htmlspecialchars($alt) . '" width="48" height="48" loading="eager" decoding="async">';
}

function hub_admin_nav_items(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'href' => 'admin/dashboard.php'],
        'payments' => ['label' => 'Payments', 'icon' => 'bi-credit-card-2-front', 'href' => 'admin/payments.php'],
        'invoices' => ['label' => 'Invoice template', 'icon' => 'bi-receipt-cutoff', 'href' => 'admin/invoice_template.php'],
        'outcomes' => ['label' => 'Outcomes', 'icon' => 'bi-diagram-3', 'href' => 'admin/outcomes.php'],
        'sources' => ['label' => 'Sources', 'icon' => 'bi-boxes', 'href' => 'admin/sources.php'],
        'gateway' => ['label' => 'Gateway', 'icon' => 'bi-shield-lock', 'href' => 'admin/gateway.php'],
    ];
}

function hub_admin_layout_start(string $pageTitle, string $activeNav = 'dashboard', ?string $subtitle = null): void
{
    hub_admin_session_start();
    $user = (string) ($_SESSION['hub_admin_user'] ?? 'Admin');
    $nav = hub_admin_nav_items();
    ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?> — <?php echo htmlspecialchars(HUB_BRAND_SHORT); ?></title>
    <?php hub_layout_head_meta($pageTitle); hub_layout_assets(); ?>
</head>
<body class="hub-admin-body">
<div class="hub-admin-shell">
    <aside class="hub-sidebar" id="hubSidebar">
        <div class="hub-sidebar-brand">
            <span class="hub-brand-mark"><?php echo hub_brand_mark_html('hub-brand-logo'); ?></span>
            <div>
                <strong>Payment Hub</strong>
                <small>NIELIT Bhubaneswar</small>
            </div>
        </div>
        <nav class="hub-sidebar-nav">
            <?php foreach ($nav as $key => $item): ?>
                <a class="hub-nav-link<?php echo $key === $activeNav ? ' active' : ''; ?>" href="<?php echo hub_asset($item['href']); ?>">
                    <i class="bi <?php echo htmlspecialchars($item['icon']); ?>"></i>
                    <?php echo htmlspecialchars($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="hub-sidebar-foot">
            <a class="hub-nav-link text-danger-emphasis" href="<?php echo hub_asset('admin/logout.php'); ?>">
                <i class="bi bi-box-arrow-right"></i> Sign out
            </a>
        </div>
    </aside>
    <div class="hub-main">
        <header class="hub-topbar">
            <button type="button" class="btn btn-link hub-menu-toggle d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#hubSidebarMobile" aria-label="Menu">
                <i class="bi bi-list fs-4"></i>
            </button>
            <div class="hub-topbar-title">
                <h1 class="h4 mb-0"><?php echo htmlspecialchars($pageTitle); ?></h1>
                <?php if ($subtitle !== null && $subtitle !== ''): ?>
                    <p class="text-secondary small mb-0"><?php echo htmlspecialchars($subtitle); ?></p>
                <?php endif; ?>
            </div>
            <div class="hub-topbar-user">
                <span class="hub-avatar"><?php echo htmlspecialchars(strtoupper(substr($user, 0, 1))); ?></span>
                <span class="d-none d-sm-inline"><?php echo htmlspecialchars($user); ?></span>
            </div>
        </header>
        <main class="hub-content container-fluid py-4">
    <?php
}

function hub_admin_layout_end(): void
{
    ?>
        </main>
        <footer class="hub-footer">
            <div class="container-fluid py-3 text-center text-secondary small">
                <?php echo htmlspecialchars(HUB_BRAND_SHORT); ?> · Secure gateway &amp; invoice control
            </div>
        </footer>
    </div>
</div>
<div class="offcanvas offcanvas-start hub-sidebar-offcanvas" tabindex="-1" id="hubSidebarMobile">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title"><?php echo htmlspecialchars(HUB_BRAND_SHORT); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="hub-sidebar-nav px-2">
            <?php foreach (hub_admin_nav_items() as $key => $item): ?>
                <a class="hub-nav-link" href="<?php echo hub_asset($item['href']); ?>">
                    <i class="bi <?php echo htmlspecialchars($item['icon']); ?>"></i>
                    <?php echo htmlspecialchars($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
    <?php
}

function hub_auth_layout_start(string $pageTitle = 'Sign in'): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?> — <?php echo htmlspecialchars(HUB_BRAND_SHORT); ?></title>
    <?php hub_layout_head_meta($pageTitle); hub_layout_assets(); ?>
</head>
<body class="hub-auth-body">
    <?php
}

function hub_auth_layout_end(): void
{
    ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
    <?php
}

function hub_public_layout_start(string $pageTitle, string $variant = 'default', bool $isLanding = false): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?> — <?php echo htmlspecialchars(HUB_BRAND_SHORT); ?></title>
    <?php hub_layout_head_meta($pageTitle); hub_layout_assets($isLanding); ?>
</head>
<body class="hub-public-body hub-public-<?php echo htmlspecialchars($variant); ?><?php echo $isLanding ? ' hub-landing-page' : ''; ?>">
    <?php
}

function hub_public_layout_end(bool $isLanding = false): void
{
    ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php if ($isLanding): ?>
<script src="<?php echo hub_asset('assets/js/hub-landing.js'); ?>"></script>
<?php endif; ?>
</body>
</html>
    <?php
}

function hub_status_badge(string $status, string $kind = 'payment'): string
{
    $map = [
        'paid' => 'success',
        'failed' => 'danger',
        'refunded' => 'dark',
        'partial_refund' => 'warning',
        'created' => 'secondary',
        'attempted' => 'info',
        'cancelled' => 'secondary',
        'expired' => 'secondary',
    ];
    $class = $map[$status] ?? 'secondary';
    $label = $kind === 'checkout' ? hub_checkout_status_label($status) : hub_payment_status_label($status);
    return '<span class="badge text-bg-' . $class . ' hub-badge">' . htmlspecialchars($label) . '</span>';
}

function hub_money_paise(int $paise): string
{
    return '₹ ' . number_format($paise / 100, 2);
}
