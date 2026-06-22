<!-- Header Section - Modern Navy & Gold Theme -->
<?php require_once __DIR__ . '/institute_branding.php'; ?>
<header class="header py-3" style="background: #fff; border-bottom: 1px solid rgba(0,0,0,0.08);">
    <div class="container-fluid">
        <div class="row align-items-center">
            <!-- Left Logo and Name -->
            <div class="col-md-8 d-flex align-items-center gap-3">
                <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="Institute Logo" class="logo" style="height: 48px; width: auto;">
                <div>
                    <h5 class="mb-0 hindi-text" style="color: #0a1628; font-weight: 600; font-size: 0.82rem;"><?php echo htmlspecialchars(INSTITUTE_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></h5>
                    <h6 class="mb-0" style="color: #0a1628; font-weight: 700; font-size: 0.95rem;"><?php echo htmlspecialchars(INSTITUTE_NAME_EN); ?></h6>
                </div>
            </div>
            <!-- Right Government Info and Emblem -->
            <div class="col-md-4 text-md-end text-center d-flex align-items-center justify-content-md-end justify-content-center gap-3 mt-2 mt-md-0">
                <div class="text-end">
                    <h6 class="mb-0 hindi-text ministry-text d-none d-md-block" style="color: #0a1628; font-weight: 600; font-size: 0.68rem;"><?php echo htmlspecialchars(MINISTRY_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></h6>
                    <h6 class="mb-0 ministry-text" style="color: #0a1628; font-weight: 600; font-size: 0.72rem;"><?php echo htmlspecialchars(MINISTRY_NAME_EN, ENT_QUOTES, 'UTF-8'); ?></h6>
                </div>
                <img src="<?php echo APP_URL; ?>/assets/images/National-Emblem.png" alt="Government Emblem" class="gov-logo" style="height: 48px; width: auto;">
            </div>
        </div>
    </div>
</header>

<!-- Sliding Information Section -->
<div class="sliding-info" style="background: #f59e0b; color: #0a1628; padding: 10px 0; font-weight: 500; overflow: hidden;">
    <div class="container">
        <p style="margin: 0; animation: ticker 20s linear infinite; white-space: nowrap;">
            📢 NIELIT Bhubaneswar offers NSQF-aligned courses with modern facilities & industry-standard training. Enroll now!
        </p>
    </div>
</div>

<style>
    @keyframes ticker {
        0% { transform: translateX(100%); }
        100% { transform: translateX(-100%); }
    }
</style>
