    </main>

    <!-- Footer -->
    <footer class="student-footer pt-5">
        <div class="container pb-4">
            <div class="row gy-4">
                <div class="col-lg-4 col-md-6">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled footer-links">
                        <li><a href="dashboard.php"><i class="fas fa-chevron-right me-2 small"></i>Dashboard</a></li>
                        <li><a href="profile.php"><i class="fas fa-chevron-right me-2 small"></i>My Profile</a></li>
                        <li><a href="attendance.php"><i class="fas fa-chevron-right me-2 small"></i>Attendance</a></li>
                        <li><a href="fees.php"><i class="fas fa-chevron-right me-2 small"></i>Fee Details</a></li>
                        <li><a href="support.php"><i class="fas fa-chevron-right me-2 small"></i>Support</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-6">
                    <h5>Important Links</h5>
                    <ul class="list-unstyled footer-links">
                        <li><a href="https://www.nielit.gov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>NIELIT Official</a></li>
                        <li><a href="https://india.gov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>India.gov.in</a></li>
                        <li><a href="http://meity.gov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>MeitY</a></li>
                        <li><a href="../public/courses.php"><i class="fas fa-chevron-right me-2 small"></i>Courses</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-12">
                    <h5>Contact Us</h5>
                    <p class="small text-muted mb-3"><?php echo htmlspecialchars(INSTITUTE_NAME_EN); ?></p>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-phone-alt me-2 text-warning"></i> 0674-2960354</li>
                        <li class="mb-2"><i class="fas fa-envelope me-2 text-warning"></i> dir-bbsr@nielit.gov.in</li>
                        <li class="mb-2"><i class="fas fa-clock me-2 text-warning"></i> Mon-Fri: 09:00 AM – 5:30 PM</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="copyright-bar text-center text-muted small">
            <div class="container">
                <div class="row">
                    <div class="col-md-6 text-md-start">
                        © <?php echo date('Y'); ?> NIELIT Bhubaneswar. All Rights Reserved.
                    </div>
                    <div class="col-md-6 text-md-end">
                        Student Portal v1.0
                    </div>
                </div>
                <?php if (isset($conn) && $conn instanceof mysqli && function_exists('renderVisitorCountFooter')): ?>
                <div class="mt-2" style="font-size: 0.78rem; opacity: 0.75;">
                    <?php renderVisitorCountFooter($conn); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <?php
    $studentPortalJsPath = __DIR__ . '/../../assets/js/student-portal.js';
    $studentToastJsPath = __DIR__ . '/../../assets/js/toast-notifications.js';
    $studentPortalJsVer = is_file($studentPortalJsPath) ? filemtime($studentPortalJsPath) : time();
    $studentToastJsVer = is_file($studentToastJsPath) ? filemtime($studentToastJsPath) : time();
    ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/toast-notifications.js?v=<?php echo $studentToastJsVer; ?>"></script>
    <script src="../assets/js/student-portal.js?v=<?php echo $studentPortalJsVer; ?>"></script>
</body>
</html>
