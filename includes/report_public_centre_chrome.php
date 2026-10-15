<?php
/**
 * Public Report Monitor header/footer chrome for a locked centre (Raipur).
 */
require_once __DIR__ . '/institute_branding.php';
require_once __DIR__ . '/visitor_counter.php';

if (!function_exists('report_monitor_render_public_centre_header')) {
    function report_monitor_render_public_centre_header(array $branding): void
    {
        $logo = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/assets/images/bhubaneswar_logo.png';
        $emblem = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/assets/images/National-Emblem.png';
        $hi = (string) ($branding['header_name_hi'] ?? '');
        $en = (string) ($branding['header_name_en'] ?? '');
        $ticker = (string) ($branding['ticker_text'] ?? '');
        ?>
<header class="header py-3" style="background: #fff; border-bottom: 1px solid rgba(0,0,0,0.08);">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex align-items-center gap-3">
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="Institute Logo" class="logo" style="height: 48px; width: auto;">
                <div>
                    <?php if ($hi !== ''): ?>
                    <h5 class="mb-0 hindi-text" style="color: #0a1628; font-weight: 600; font-size: 0.82rem;"><?php echo htmlspecialchars($hi, ENT_QUOTES, 'UTF-8'); ?></h5>
                    <?php endif; ?>
                    <?php if ($en !== ''): ?>
                    <h6 class="mb-0" style="color: #0a1628; font-weight: 700; font-size: 0.95rem;"><?php echo htmlspecialchars($en, ENT_QUOTES, 'UTF-8'); ?></h6>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 text-md-end text-center d-flex align-items-center justify-content-md-end justify-content-center gap-3 mt-2 mt-md-0">
                <div class="text-end">
                    <h6 class="mb-0 hindi-text ministry-text d-none d-md-block" style="color: #0a1628; font-weight: 600; font-size: 0.68rem;"><?php echo htmlspecialchars(MINISTRY_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></h6>
                    <h6 class="mb-0 ministry-text" style="color: #0a1628; font-weight: 600; font-size: 0.72rem;"><?php echo htmlspecialchars(MINISTRY_NAME_EN, ENT_QUOTES, 'UTF-8'); ?></h6>
                </div>
                <img src="<?php echo htmlspecialchars($emblem); ?>" alt="Government Emblem" class="gov-logo" style="height: 48px; width: auto;">
            </div>
        </div>
    </div>
</header>
<?php if ($ticker !== ''): ?>
<div class="sliding-info" style="background: #f59e0b; color: #0a1628; padding: 10px 0; font-weight: 500; overflow: hidden;">
    <div class="container">
        <p style="margin: 0; animation: ticker 20s linear infinite; white-space: nowrap;">
            📢 <?php echo htmlspecialchars($ticker, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </div>
</div>
<style>
    @keyframes ticker {
        0% { transform: translateX(100%); }
        100% { transform: translateX(-100%); }
    }
</style>
<?php endif;
    }
}

if (!function_exists('report_monitor_render_public_centre_footer')) {
    function report_monitor_render_public_centre_footer(array $branding, $conn = null): void
    {
        $brand = (string) ($branding['footer_brand'] ?? 'NIELIT');
        $label = (string) ($branding['footer_centre_label'] ?? '');
        $about = (string) ($branding['footer_about'] ?? '');
        $address = (string) ($branding['footer_address'] ?? '');
        $phone = (string) ($branding['footer_phone'] ?? '');
        $email = (string) ($branding['footer_email'] ?? '');
        $copyright = function_exists('report_monitor_format_branding_copyright')
            ? report_monitor_format_branding_copyright((string) ($branding['footer_copyright'] ?? ''))
            : str_replace('{year}', (string) date('Y'), (string) ($branding['footer_copyright'] ?? ''));
        $phoneHref = preg_replace('/[^0-9+]/', '', $phone);
        ?>
<footer style="background: #050e1a; color: rgba(255,255,255,0.62); font-size: 0.9rem;">
    <div style="padding: 70px 0 50px;">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <div style="margin-bottom: 24px;">
                        <div style="font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.4rem; color: #fff; display: block; margin-bottom: 4px;"><?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if ($label !== ''): ?>
                        <div style="font-size: 0.8rem; color: rgba(255,255,255,0.45);"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($about !== ''): ?>
                    <p style="color: rgba(255,255,255,0.5); font-size: 0.85rem; line-height: 1.7; margin-top: 14px;">
                        <?php echo htmlspecialchars($about, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 style="font-family: 'Sora', sans-serif; font-weight: 700; font-size: 0.92rem; color: #fff; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 22px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.08);">
                        Important Links
                    </h5>
                    <ul class="list-unstyled">
                        <li><a href="https://india.gov.in/" class="text-decoration-none" style="color: rgba(255,255,255,0.55); display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: 0.88rem;" target="_blank" rel="noopener">India.gov.in</a></li>
                        <li><a href="https://www.mygov.in/" class="text-decoration-none" style="color: rgba(255,255,255,0.55); display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: 0.88rem;" target="_blank" rel="noopener">MyGov.in</a></li>
                        <li><a href="https://www.nielit.gov.in/" class="text-decoration-none" style="color: rgba(255,255,255,0.55); display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: 0.88rem;" target="_blank" rel="noopener">NIELIT Headquarters</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 style="font-family: 'Sora', sans-serif; font-weight: 700; font-size: 0.92rem; color: #fff; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 22px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.08);">
                        Contact Us
                    </h5>
                    <?php if ($address !== ''): ?>
                    <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                        <i class="fas fa-map-marker-alt" style="color: #f59e0b; margin-top: 2px; flex-shrink: 0;"></i>
                        <div><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($phone !== ''): ?>
                    <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                        <i class="fas fa-phone-alt" style="color: #f59e0b; margin-top: 2px; flex-shrink: 0;"></i>
                        <div><a href="tel:<?php echo htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none" style="color: rgba(255,255,255,0.55);"><?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></a></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($email !== ''): ?>
                    <div style="display: flex; align-items: flex-start; gap: 10px; font-size: 0.85rem;">
                        <i class="fas fa-envelope" style="color: #f59e0b; margin-top: 2px; flex-shrink: 0;"></i>
                        <div><a href="mailto:<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none" style="color: rgba(255,255,255,0.55);"><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></a></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div style="border-top: 1px solid rgba(255,255,255,0.06); padding: 20px 0; font-size: 0.82rem; color: rgba(255,255,255,0.3); text-align: center;">
        <?php if ($copyright !== ''): ?>
        <p style="margin: 0 0 8px;"><?php echo htmlspecialchars($copyright, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($conn instanceof mysqli && function_exists('renderVisitorCountFooter')): ?>
            <p style="margin: 0; color: rgba(255,255,255,0.45); font-size: 0.78rem;">
                <?php renderVisitorCountFooter($conn); ?>
            </p>
        <?php endif; ?>
    </div>
</footer>
        <?php
    }
}
