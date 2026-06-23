<?php
if (!function_exists('getJobFairPortalUrl')) {
    require_once __DIR__ . '/navigation_helper.php';
}
if (!function_exists('app_url')) {
    require_once __DIR__ . '/url_helper.php';
}
?>
<!-- Navigation Menu -->
<nav class="navbar navbar-expand-lg navbar-light" style="background-color: #356c9f;">
    <div class="container">
        <a class="navbar-brand" href="<?php echo app_url(); ?>">NIELIT Bhubaneswar</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?php echo app_url(); ?>">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo htmlspecialchars(getJobFairPortalUrl()); ?>" target="_blank" rel="noopener">Job Fair</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo htmlspecialchars(getMockTestPortalUrl()); ?>" target="_blank" rel="noopener">Mock Test</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        PM SHRI KV JNV
                    </a>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <a class="dropdown-item" href="<?php echo app_url('Membership_Form/index'); ?>">Membership Form</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown2" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Student Zone
                    </a>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdown2">
                        <a class="dropdown-item" href="<?php echo app_url('public/courses'); ?>">Courses Offered</a>
                        <a class="dropdown-item" href="<?php echo app_url('student/login'); ?>">Student Portal</a>
                        <a class="dropdown-item" href="<?php echo htmlspecialchars(getMockTestPortalUrl()); ?>" target="_blank" rel="noopener">Mock Test Portal</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown3" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Admin
                    </a>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdown3">
                        <a class="dropdown-item" href="<?php echo app_url('admin/login'); ?>">Admin Login</a>
                        <a class="dropdown-item" href="/Salary_Slip/login.php">Salary Slip</a>
                        <a class="dropdown-item" href="/Nielit_Project/index.php">Certificate</a>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo app_url('public/contact'); ?>">Contact</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
