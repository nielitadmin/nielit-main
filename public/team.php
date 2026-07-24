<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Team - NIELIT Bhubaneswar</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php
    require_once __DIR__ . '/../includes/maintenance_check.php';
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/theme_loader.php';
    require_once __DIR__ . '/../includes/navigation_helper.php';
    require_once __DIR__ . '/../includes/url_helper.php';
    require_once __DIR__ . '/../includes/public_theme_helper.php';
    require_once __DIR__ . '/../includes/staff_profile_helper.php';

    $active_theme = loadActiveTheme($conn);
    $theme_logo = getThemeLogo($active_theme);
    $team_staff = listPublicTeamStaff($conn);
    $team_grouped = groupStaffByCategory($team_staff);

    injectThemeCSS($active_theme);
    emitPublicThemeHead($conn);
    ?>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/public-theme.css">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
    <style>
        <?php echo publicStaffCardCss(); ?>
        .team-category-title {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 1rem;
            padding-bottom: .5rem;
            border-bottom: 2px solid rgba(26, 86, 219, .2);
        }
    </style>
<?php
require_once __DIR__ . '/../includes/public_skeleton_helper.php';
public_skeleton_render_head();
?>
</head>
<body class="public-page-loading public-site">
<?php public_skeleton_render_loader('generic'); ?>

    <div class="top-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8 d-flex align-items-center justify-content-md-start justify-content-center text-header-group">
                    <img src="<?php echo APP_URL . '/' . $theme_logo; ?>" alt="NIELIT Logo" class="me-3" style="height: 50px;">
                    <div>
                        <div class="fw-bold text-primary d-none d-sm-block top-bar-title-hi"><?php echo htmlspecialchars(INSTITUTE_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="fw-bold text-dark top-bar-title-en"><?php echo htmlspecialchars(INSTITUTE_NAME_EN); ?></div>
                    </div>
                </div>
                <div class="col-md-4 d-flex justify-content-md-end justify-content-center gov-logos">
                    <div class="text-end me-3 d-none d-lg-block">
                        <small class="d-block text-secondary d-none d-md-block top-bar-ministry-hi"><?php echo htmlspecialchars(MINISTRY_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></small>
                        <small class="d-block fw-bold top-bar-ministry-en"><?php echo htmlspecialchars(MINISTRY_NAME_EN, ENT_QUOTES, 'UTF-8'); ?></small>
                    </div>
                    <img src="<?php echo APP_URL; ?>/assets/images/National-Emblem.png" alt="Gov India" class="national-emblem" style="height: 50px;">
                </div>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo app_url('index'); ?>">
                <i class="fas fa-university me-2"></i> NIELIT
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="<?php echo app_url('index'); ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars(getJobFairPortalUrl()); ?>" target="_blank" rel="noopener">Job Fair</a></li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">PM SHRI KV JNV</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo app_url('Membership_Form/index'); ?>">Membership Form</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Student Zone</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo app_url('public/courses'); ?>">Courses Offered</a></li>
                            <li><a class="dropdown-item" href="<?php echo app_url('student/login'); ?>">Student Portal</a></li>
                            <li><a class="dropdown-item" href="<?php echo app_url('public/courses'); ?>">Course Registration</a></li>
                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars(getMockTestPortalUrl()); ?>" target="_blank" rel="noopener">Mock Test Portal</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" data-bs-toggle="dropdown">About</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo app_url('public/management'); ?>">Management</a></li>
                            <li><a class="dropdown-item active" href="<?php echo app_url('public/team'); ?>">Our Team</a></li>
                            <li><a class="dropdown-item" href="<?php echo app_url('public/news'); ?>">News</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Admin</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo app_url('admin/login'); ?>">Admin Login</a></li>
                            <li><a class="dropdown-item" href="/Salary_Slip/login.php">Salary Slip</a></li>
                            <li><a class="dropdown-item" href="/Nielit_Project/index.php">Certificate</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo app_url('public/contact'); ?>">Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="notice-bar">
        <div class="notice-content">
            <span class="badge bg-warning text-dark me-2">NEW</span>
            Admissions Open! NIELIT Bhubaneswar offers NSQF-aligned courses with modern facilities. Visit our Baleshwar Extension Center today.
        </div>
    </div>

    <section class="page-header">
        <div class="container text-center">
            <h1 class="mb-0">Our Team</h1>
            <p class="lead mb-0 mt-2">Faculty and staff of NIELIT Bhubaneswar</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <?php if (empty($team_staff)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 text-muted">Team profiles coming soon</h4>
                    <p class="text-muted mb-0">Faculty and staff details will appear here once published by the admin.</p>
                </div>
            <?php else: ?>
                <?php foreach ($team_grouped as $category => $members): ?>
                    <div class="mb-5">
                        <h2 class="h4 team-category-title">
                            <i class="fas fa-users me-2 text-primary"></i><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>
                        </h2>
                        <div class="row g-4">
                            <?php foreach ($members as $member): ?>
                                <div class="col-sm-6 col-lg-4 col-xl-3">
                                    <?php echo renderPublicStaffCard($member); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="<?php echo app_url('public/contact'); ?>" class="btn btn-outline-primary me-2">
                    <i class="fas fa-envelope me-1"></i> Contact Us
                </a>
                <a href="<?php echo app_url('public/management'); ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-sitemap me-1"></i> Management
                </a>
            </div>
        </div>
    </section>

    <footer class="pt-5">
        <div class="container pb-4">
            <div class="row gy-4">
                <div class="col-lg-4 col-md-6">
                    <h5>Important Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="https://india.gov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>National Portal of India</a></li>
                        <li><a href="https://www.mygov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>MyGov</a></li>
                        <li><a href="https://rtionline.gov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>RTI Online</a></li>
                        <li><a href="http://meity.gov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>MeitY</a></li>
                        <li><a href="https://www.nielit.gov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>NIELIT HQ</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-6">
                    <h5>Quick Explore</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo app_url('public/management'); ?>"><i class="fas fa-chevron-right me-2 small"></i>Management</a></li>
                        <li><a href="<?php echo app_url('public/team'); ?>"><i class="fas fa-chevron-right me-2 small"></i>Our Team</a></li>
                        <li><a href="<?php echo app_url('public/contact'); ?>"><i class="fas fa-chevron-right me-2 small"></i>Contact Us</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-12">
                    <h5>Contact Info</h5>
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
                        Designed & Developed by NIELIT Bhubaneswar IT Team
                    </div>
                </div>
                <?php if (isset($conn) && $conn instanceof mysqli): ?>
                <div class="mt-2" style="font-size: 0.78rem; opacity: 0.75;">
                    <?php renderVisitorCountFooter($conn); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php public_skeleton_render_script(); ?>
</body>
</html>
