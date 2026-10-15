<!-- Header Section - Modern Navy & Gold Theme -->
<?php
require_once __DIR__ . '/institute_branding.php';
require_once __DIR__ . '/centre_chrome_helper.php';
$chromeConn = (isset($conn) && $conn instanceof mysqli) ? $conn : ((isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) ? $GLOBALS['conn'] : null);
$siteChrome = ($chromeConn instanceof mysqli) ? centre_chrome_get_main($chromeConn) : centre_chrome_defaults([], '', 0);
$headerLeftLogo = centre_chrome_public_url((string) ($siteChrome['left_logo_path'] ?? 'assets/images/bhubaneswar_logo.png'));
$headerRightLogo = centre_chrome_public_url((string) ($siteChrome['right_logo_path'] ?? 'assets/images/National-Emblem.png'));
$headerNameHi = (string) ($siteChrome['header_name_hi'] ?? (defined('INSTITUTE_NAME_HI') ? INSTITUTE_NAME_HI : ''));
$headerNameEn = (string) ($siteChrome['header_name_en'] ?? (defined('INSTITUTE_NAME_EN') ? INSTITUTE_NAME_EN : ''));
$headerMinistryHi = (string) ($siteChrome['ministry_name_hi'] ?? (defined('MINISTRY_NAME_HI') ? MINISTRY_NAME_HI : ''));
$headerMinistryEn = (string) ($siteChrome['ministry_name_en'] ?? (defined('MINISTRY_NAME_EN') ? MINISTRY_NAME_EN : ''));
$headerTicker = (string) ($siteChrome['ticker_text'] ?? '');
?>
<header class="header py-3" style="background: #fff; border-bottom: 1px solid rgba(0,0,0,0.08);">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex align-items-center gap-3">
                <img src="<?php echo htmlspecialchars($headerLeftLogo); ?>" alt="Institute Logo" class="logo" style="height: 48px; width: auto;">
                <div>
                    <?php if ($headerNameHi !== ''): ?>
                    <h5 class="mb-0 hindi-text" style="color: #0a1628; font-weight: 600; font-size: 0.82rem;"><?php echo htmlspecialchars($headerNameHi, ENT_QUOTES, 'UTF-8'); ?></h5>
                    <?php endif; ?>
                    <?php if ($headerNameEn !== ''): ?>
                    <h6 class="mb-0" style="color: #0a1628; font-weight: 700; font-size: 0.95rem;"><?php echo htmlspecialchars($headerNameEn, ENT_QUOTES, 'UTF-8'); ?></h6>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 text-md-end text-center d-flex align-items-center justify-content-md-end justify-content-center gap-3 mt-2 mt-md-0">
                <div class="text-end">
                    <?php if ($headerMinistryHi !== ''): ?>
                    <h6 class="mb-0 hindi-text ministry-text d-none d-md-block" style="color: #0a1628; font-weight: 600; font-size: 0.68rem;"><?php echo htmlspecialchars($headerMinistryHi, ENT_QUOTES, 'UTF-8'); ?></h6>
                    <?php endif; ?>
                    <?php if ($headerMinistryEn !== ''): ?>
                    <h6 class="mb-0 ministry-text" style="color: #0a1628; font-weight: 600; font-size: 0.72rem;"><?php echo htmlspecialchars($headerMinistryEn, ENT_QUOTES, 'UTF-8'); ?></h6>
                    <?php endif; ?>
                </div>
                <img src="<?php echo htmlspecialchars($headerRightLogo); ?>" alt="Government Emblem" class="gov-logo" style="height: 48px; width: auto;">
            </div>
        </div>
    </div>
</header>

<?php if ($headerTicker !== ''): ?>
<div class="sliding-info" style="background: #f59e0b; color: #0a1628; padding: 10px 0; font-weight: 500; overflow: hidden;">
    <div class="container">
        <p style="margin: 0; animation: ticker 20s linear infinite; white-space: nowrap;">
            📢 <?php echo htmlspecialchars($headerTicker, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </div>
</div>
<style>
    @keyframes ticker {
        0% { transform: translateX(100%); }
        100% { transform: translateX(-100%); }
    }
</style>
<?php endif; ?>
