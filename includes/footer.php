<?php
require_once __DIR__ . '/institute_branding.php';
require_once __DIR__ . '/visitor_counter.php';
?>
<!-- Footer Section - Modern Navy Theme -->
<footer style="background: #050e1a; color: rgba(255,255,255,0.62); font-size: 0.9rem;">
    <div style="padding: 70px 0 50px;">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <div style="margin-bottom: 24px;">
                        <div style="font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.4rem; color: #fff; display: block; margin-bottom: 4px;">NIELIT</div>
                        <div style="font-size: 0.8rem; color: rgba(255,255,255,0.45);">Bhubaneswar</div>
                    </div>
                    <p style="color: rgba(255,255,255,0.5); font-size: 0.85rem; line-height: 1.7; margin-top: 14px;">
                        <?php echo htmlspecialchars(INSTITUTE_NAME_EN); ?> under <?php echo htmlspecialchars(MINISTRY_NAME_EN, ENT_QUOTES, 'UTF-8'); ?>.
                    </p>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 style="font-family: 'Sora', sans-serif; font-weight: 700; font-size: 0.92rem; color: #fff; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 22px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.08);">
                        Important Links
                    </h5>
                    <ul class="list-unstyled">
                        <li><a href="https://india.gov.in/" class="text-decoration-none" style="color: rgba(255,255,255,0.55); transition: all 0.2s; display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: 0.88rem;" onmouseover="this.style.color='#f59e0b'; this.style.paddingLeft='4px';" onmouseout="this.style.color='rgba(255,255,255,0.55)'; this.style.paddingLeft='0';" target="_blank">India.gov.in</a></li>
                        <li><a href="https://www.mygov.in/" class="text-decoration-none" style="color: rgba(255,255,255,0.55); transition: all 0.2s; display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: 0.88rem;" onmouseover="this.style.color='#f59e0b'; this.style.paddingLeft='4px';" onmouseout="this.style.color='rgba(255,255,255,0.55)'; this.style.paddingLeft='0';" target="_blank">MyGov.in</a></li>
                        <li><a href="https://www.nielit.gov.in/" class="text-decoration-none" style="color: rgba(255,255,255,0.55); transition: all 0.2s; display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: 0.88rem;" onmouseover="this.style.color='#f59e0b'; this.style.paddingLeft='4px';" onmouseout="this.style.color='rgba(255,255,255,0.55)'; this.style.paddingLeft='0';" target="_blank">NIELIT Headquarters</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 style="font-family: 'Sora', sans-serif; font-weight: 700; font-size: 0.92rem; color: #fff; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 22px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.08);">
                        Contact Us
                    </h5>
                    <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                        <i class="fas fa-map-marker-alt" style="color: #f59e0b; margin-top: 2px; flex-shrink: 0;"></i>
                        <div>OCAC Tower, Bhubaneswar, Odisha</div>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                        <i class="fas fa-phone-alt" style="color: #f59e0b; margin-top: 2px; flex-shrink: 0;"></i>
                        <div><a href="tel:0674-2960354" class="text-decoration-none" style="color: rgba(255,255,255,0.55);">0674-2960354</a></div>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 10px; font-size: 0.85rem;">
                        <i class="fas fa-envelope" style="color: #f59e0b; margin-top: 2px; flex-shrink: 0;"></i>
                        <div><a href="mailto:dir-bbsr@nielit.gov.in" class="text-decoration-none" style="color: rgba(255,255,255,0.55);">dir-bbsr@nielit.gov.in</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Bottom -->
    <div style="border-top: 1px solid rgba(255,255,255,0.06); padding: 20px 0; font-size: 0.82rem; color: rgba(255,255,255,0.3); text-align: center;">
        <p style="margin: 0 0 8px;">Design & Developed By NIELIT Bhubaneswar © <?php echo date('Y'); ?> | All Rights Reserved</p>
        <?php if (isset($conn) && $conn instanceof mysqli): ?>
            <p style="margin: 0; color: rgba(255,255,255,0.45); font-size: 0.78rem;">
                <?php renderVisitorCountFooter($conn); ?>
            </p>
        <?php endif; ?>
    </div>
</footer>
