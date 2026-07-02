<?php
require_once __DIR__ . '/../../includes/visitor_counter.php';
?>
<footer class="pt-5" style="background-color: #1a202c; color: #cbd5e0; font-size: 0.95rem;">
    <div class="container pb-4">
        <div class="row gy-4">
            <div class="col-lg-4 col-md-6">
                <h5 style="color: #fff; font-weight: 600; margin-bottom: 1.5rem;">Important Links</h5>
                <ul class="list-unstyled">
                    <li><a href="https://india.gov.in/" target="_blank" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>National Portal of India</a></li>
                    <li><a href="https://www.mygov.in/" target="_blank" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>MyGov</a></li>
                    <li><a href="https://rtionline.gov.in/" target="_blank" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>RTI Online</a></li>
                    <li><a href="http://meity.gov.in/" target="_blank" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>MeitY</a></li>
                    <li><a href="https://www.nielit.gov.in/" target="_blank" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>NIELIT HQ</a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-md-6">
                <h5 style="color: #fff; font-weight: 600; margin-bottom: 1.5rem;">Quick Explore</h5>
                <ul class="list-unstyled">
                    <li><a href="<?php echo APP_URL; ?>/index.php" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>Home</a></li>
                    <li><a href="<?php echo APP_URL; ?>/public/courses.php" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>Courses</a></li>
                    <li><a href="<?php echo APP_URL; ?>/student/login.php" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>Student Portal</a></li>
                    <li><a href="<?php echo APP_URL; ?>/public/contact.php" style="color: #cbd5e0; text-decoration: none; display: block; margin-bottom: 8px;"><i class="fas fa-chevron-right me-2 small"></i>Contact Us</a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-md-12">
                <h5 style="color: #fff; font-weight: 600; margin-bottom: 1.5rem;">Contact Info</h5>
                <p class="small text-muted mb-3"><?php echo htmlspecialchars(INSTITUTE_NAME_EN); ?></p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-phone-alt me-2 text-warning"></i> 0674-2960354</li>
                    <li class="mb-2"><i class="fas fa-envelope me-2 text-warning"></i> dir-bbsr@nielit.gov.in</li>
                    <li class="mb-2"><i class="fas fa-clock me-2 text-warning"></i> Mon-Fri: 09:00 AM – 5:30 PM</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="copyright-bar text-center text-muted small" style="background-color: #111827; padding: 15px 0; border-top: 1px solid #2d3748;">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-md-start">© <?php echo date('Y'); ?> NIELIT Bhubaneswar. All Rights Reserved.</div>
                <div class="col-md-6 text-md-end">Designed &amp; Developed by NIELIT Bhubaneswar IT Team</div>
            </div>
            <?php if (isset($conn) && $conn instanceof mysqli): ?>
            <div class="mt-2" style="font-size: 0.78rem; opacity: 0.75;">
                <?php renderVisitorCountFooter($conn); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
