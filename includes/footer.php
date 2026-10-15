<?php
require_once __DIR__ . '/institute_branding.php';
require_once __DIR__ . '/visitor_counter.php';
require_once __DIR__ . '/centre_chrome_helper.php';
$chromeConn = (isset($conn) && $conn instanceof mysqli) ? $conn : ((isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) ? $GLOBALS['conn'] : null);
$footerChrome = ($chromeConn instanceof mysqli) ? centre_chrome_get_main($chromeConn) : centre_chrome_defaults([], '', 0);
$footerBrand = (string) ($footerChrome['footer_brand'] ?? 'NIELIT');
$footerLabel = (string) ($footerChrome['footer_centre_label'] ?? 'Bhubaneswar');
$footerAbout = (string) ($footerChrome['footer_about'] ?? '');
$footerAddress = (string) ($footerChrome['footer_address'] ?? 'OCAC Tower, Bhubaneswar, Odisha');
$footerPhone = (string) ($footerChrome['footer_phone'] ?? '0674-2960354');
$footerEmail = (string) ($footerChrome['footer_email'] ?? 'dir-bbsr@nielit.gov.in');
$footerCopyright = centre_chrome_format_copyright((string) ($footerChrome['footer_copyright'] ?? ''));
$footerPhoneHref = preg_replace('/[^0-9+]/', '', $footerPhone);
?>
<footer style="background: #050e1a; color: rgba(255,255,255,0.62); font-size: 0.9rem;">
    <div style="padding: 70px 0 50px;">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <div style="margin-bottom: 24px;">
                        <div style="font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.4rem; color: #fff; display: block; margin-bottom: 4px;"><?php echo htmlspecialchars($footerBrand, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if ($footerLabel !== ''): ?>
                        <div style="font-size: 0.8rem; color: rgba(255,255,255,0.45);"><?php echo htmlspecialchars($footerLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($footerAbout !== ''): ?>
                    <p style="color: rgba(255,255,255,0.5); font-size: 0.85rem; line-height: 1.7; margin-top: 14px;">
                        <?php echo htmlspecialchars($footerAbout, ENT_QUOTES, 'UTF-8'); ?>
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
                    <?php if ($footerAddress !== ''): ?>
                    <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                        <i class="fas fa-map-marker-alt" style="color: #f59e0b; margin-top: 2px; flex-shrink: 0;"></i>
                        <div><?php echo htmlspecialchars($footerAddress, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($footerPhone !== ''): ?>
                    <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                        <i class="fas fa-phone-alt" style="color: #f59e0b; margin-top: 2px; flex-shrink: 0;"></i>
                        <div><a href="tel:<?php echo htmlspecialchars((string) $footerPhoneHref, ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none" style="color: rgba(255,255,255,0.55);"><?php echo htmlspecialchars($footerPhone, ENT_QUOTES, 'UTF-8'); ?></a></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($footerEmail !== ''): ?>
                    <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                        <i class="fas fa-envelope" style="color: #f59e0b; margin-top: 2px; flex-shrink: 0;"></i>
                        <div><a href="mailto:<?php echo htmlspecialchars($footerEmail, ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none" style="color: rgba(255,255,255,0.55);"><?php echo htmlspecialchars($footerEmail, ENT_QUOTES, 'UTF-8'); ?></a></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div style="border-top: 1px solid rgba(255,255,255,0.06); padding: 20px 0; font-size: 0.82rem; color: rgba(255,255,255,0.3); text-align: center;">
        <?php if ($footerCopyright !== ''): ?>
        <p style="margin: 0 0 8px;"><?php echo htmlspecialchars($footerCopyright, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (isset($conn) && $conn instanceof mysqli): ?>
            <p style="margin: 0; color: rgba(255,255,255,0.45); font-size: 0.78rem;">
                <?php renderVisitorCountFooter($conn); ?>
            </p>
        <?php endif; ?>
    </div>
</footer>
