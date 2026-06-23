<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management - NIELIT Bhubaneswar</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php 
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/theme_loader.php';
    require_once __DIR__ . '/../includes/navigation_helper.php';
    require_once __DIR__ . '/../includes/url_helper.php';
    
    // Load active theme
    $active_theme = loadActiveTheme($conn);
    $theme_logo = getThemeLogo($active_theme);
    
    // Inject theme CSS
    injectThemeCSS($active_theme);
    ?>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/public-theme.css">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
</head>
<body>

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8 d-flex align-items-center justify-content-md-start justify-content-center text-header-group">
                    <img src="<?php echo APP_URL . '/' . $theme_logo; ?>" alt="NIELIT Logo" class="me-3" style="height: 50px;">
                    <div>
                        <div class="fw-bold text-primary d-none d-sm-block"><?php echo htmlspecialchars(INSTITUTE_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars(INSTITUTE_NAME_EN); ?></div>
                    </div>
                </div>
                <div class="col-md-4 d-flex justify-content-md-end justify-content-center gov-logos">
                    <div class="text-end me-3 d-none d-lg-block">
                        <small class="d-block text-secondary d-none d-md-block"><?php echo htmlspecialchars(MINISTRY_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></small>
                        <small class="d-block fw-bold text-secondary"><?php echo htmlspecialchars(MINISTRY_NAME_EN, ENT_QUOTES, 'UTF-8'); ?></small>
                    </div>
                    <img src="<?php echo APP_URL; ?>/assets/images/National-Emblem.png" alt="Gov India" style="height: 50px;">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navbar -->
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
                            <li><a class="dropdown-item active" href="<?php echo app_url('public/management'); ?>">Management</a></li>
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

    <!-- Notice Ticker -->
    <div class="notice-bar">
        <div class="notice-content">
            <span class="badge bg-warning text-dark me-2">NEW</span> 
            Admissions Open! NIELIT Bhubaneswar offers NSQF-aligned courses with modern facilities. Visit our Baleshwar Extension Center today.
        </div>
    </div>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container text-center">
            <h1 class="mb-0">Organizational Structure & Management</h1>
            <p class="lead mb-0 mt-2">NIELIT Organizational Hierarchy</p>
        </div>
    </section>

<!-- Main Content -->
<main class="py-5">
    <div class="container">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-10">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <img src="<?php echo APP_URL; ?>/organisation-map_10.png" alt="NIELIT Organizational Chart" class="img-fluid rounded shadow-sm">
                        </div>

                        <div class="content-section">
                            <p class="lead">
                                The organizational structure of NIELIT is governed by the <strong>Governing Council</strong>, supported by several committees such as the <strong>Management Board</strong>, <strong>Academic Advisory Committee</strong>, <strong>Finance/Account Committee</strong>, and the <strong>Executive Committee of the Centres</strong>.
                            </p>
                            <p>
                                At the core of operational management lies the <strong>Director General</strong>, who oversees the strategic and administrative functions across all centers. Each NIELIT centre is headed by a <strong>Director / Director-in-Charge</strong>, responsible for managing and executing the activities of the respective locations including Bhubaneswar.
                            </p>

                            <h4 class="mt-5 mb-3 text-primary"><i class="fas fa-sitemap me-2"></i>Key Functional Bodies</h4>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-box p-3 bg-light rounded">
                                        <h6 class="fw-bold text-secondary"><i class="fas fa-users me-2"></i>Governing Council</h6>
                                        <p class="mb-0 small">Apex decision-making body</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box p-3 bg-light rounded">
                                        <h6 class="fw-bold text-secondary"><i class="fas fa-briefcase me-2"></i>Management Board</h6>
                                        <p class="mb-0 small">Assists in management and administration</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box p-3 bg-light rounded">
                                        <h6 class="fw-bold text-secondary"><i class="fas fa-graduation-cap me-2"></i>Academic Advisory Committee</h6>
                                        <p class="mb-0 small">Provides academic direction</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box p-3 bg-light rounded">
                                        <h6 class="fw-bold text-secondary"><i class="fas fa-calculator me-2"></i>Finance/Account Committee</h6>
                                        <p class="mb-0 small">Oversees financial matters</p>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-box p-3 bg-light rounded">
                                        <h6 class="fw-bold text-secondary"><i class="fas fa-network-wired me-2"></i>Executive Committee</h6>
                                        <p class="mb-0 small">Coordinates between Director General and centres</p>
                                    </div>
                                </div>
                            </div>

                            <h4 class="mt-5 mb-3 text-primary"><i class="fas fa-map-marker-alt me-2"></i>NIELIT Centres Across India</h4>
                            <p>
                                There are 34 centers including Agartala, Ajmer, Aurangabad, Bhubaneswar, Chandigarh, Chennai, Delhi, Gangtok, Guwahati, Imphal, Jammu, Kolkata, Lucknow, Patna, Ranchi, Shillong, Shimla, Srinagar, and others.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Footer -->
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
                    <li><a href="#"><i class="fas fa-chevron-right me-2 small"></i>About Us</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right me-2 small"></i>Privacy Policy</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right me-2 small"></i>Terms & Conditions</a></li>
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
</body>
</html>
