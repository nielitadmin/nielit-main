<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News - NIELIT Bhubaneswar</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php 
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/theme_loader.php';
    
    // Load active theme
    $active_theme = loadActiveTheme($conn);
    $theme_logo = getThemeLogo($active_theme);
    
    // Inject theme CSS
    injectThemeCSS($active_theme);
    ?>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/public-theme.css">
    <link rel="icon" href="<?php echo APP_URL . '/' . getThemeFavicon($active_theme); ?>" type="image/x-icon">
</head>
<body>

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8 d-flex align-items-center justify-content-md-start justify-content-center text-header-group">
                    <img src="<?php echo APP_URL . '/' . $theme_logo; ?>" alt="NIELIT Logo" class="me-3" style="height: 50px;">
                    <div>
                        <div class="fw-bold text-primary d-none d-sm-block">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर</div>
                        <div class="fw-bold text-dark">National Institute of Electronics & Information Technology, Bhubaneswar</div>
                    </div>
                </div>
                <div class="col-md-4 d-flex justify-content-md-end justify-content-center gov-logos">
                    <div class="text-end me-3 d-none d-lg-block">
                        <small class="d-block fw-bold text-secondary">Ministry of Electronics & IT</small>
                        <small class="d-block text-secondary">Government of India</small>
                    </div>
                    <img src="<?php echo APP_URL; ?>/assets/images/National-Emblem.png" alt="Gov India" style="height: 50px;">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo APP_URL; ?>/index.php">
                <i class="fas fa-university me-2"></i> NIELIT
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/DGR/index.php">Job Fair</a></li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">PM SHRI KV JNV</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/Membership_Form/index.php">Membership Form</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Student Zone</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/public/courses.php">Courses Offered</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/student/login.php">Student Portal</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/student/register.php">Registration</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" data-bs-toggle="dropdown">About</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/public/management.php">Management</a></li>
                            <li><a class="dropdown-item active" href="<?php echo APP_URL; ?>/public/news.php">News</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Admin</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/login.php">Admin Login</a></li>
                            <li><a class="dropdown-item" href="/Salary_Slip/login.php">Salary Slip</a></li>
                            <li><a class="dropdown-item" href="/Nielit_Project/index.php">Certificate</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/public/contact.php">Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Notice Ticker -->
    <div class="notice-bar">
        <div class="notice-content">
            <span class="badge bg-warning text-dark me-2">NEW</span> 
            Admissions Open! NIELIT Bhubaneswar offers NSQF-aligned courses with modern facilities. Visit our Balasore Extension Center today.
        </div>
    </div>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container text-center">
            <h1 class="mb-0">Latest News & Announcements</h1>
            <p class="lead mb-0 mt-2">Stay updated with NIELIT Bhubaneswar</p>
        </div>
    </section>

    <!-- News Content -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <!-- News Card 1 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm hover-lift">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="news-icon me-3">
                                    <i class="fas fa-file-pdf text-danger"></i>
                                </div>
                                <div>
                                    <span class="badge bg-primary">Document</span>
                                    <small class="text-muted d-block mt-1">August 23, 2023</small>
                                </div>
                            </div>
                            <h5 class="card-title">Course Brochure</h5>
                            <p class="card-text text-muted">Download our comprehensive course brochure with details about all programs offered.</p>
                            <a href="<?php echo APP_URL; ?>/news/Brochure.pdf" class="btn btn-outline-primary btn-sm" target="_blank">
                                <i class="fas fa-download me-2"></i>Download PDF
                            </a>
                        </div>
                    </div>
                </div>

                <!-- News Card 2 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm hover-lift">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="news-icon me-3">
                                    <i class="fas fa-bullhorn text-warning"></i>
                                </div>
                                <div>
                                    <span class="badge bg-success">Announcement</span>
                                    <small class="text-muted d-block mt-1">May 15, 2024</small>
                                </div>
                            </div>
                            <h5 class="card-title">Internship Notice</h5>
                            <p class="card-text text-muted">New internship opportunities available for students in various technology domains.</p>
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-2"></i>View Details
                            </a>
                        </div>
                    </div>
                </div>

                <!-- News Card 3 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm hover-lift">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="news-icon me-3">
                                    <i class="fas fa-graduation-cap text-success"></i>
                                </div>
                                <div>
                                    <span class="badge bg-info">Courses</span>
                                    <small class="text-muted d-block mt-1">June 1, 2024</small>
                                </div>
                            </div>
                            <h5 class="card-title">New Courses Announced</h5>
                            <p class="card-text text-muted">Exciting new courses in AI, Machine Learning, and Cloud Computing now available.</p>
                            <a href="<?php echo APP_URL; ?>/public/courses.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-arrow-right me-2"></i>Explore Courses
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State (if no news) -->
            <!-- <div class="text-center py-5">
                <i class="fas fa-newspaper text-muted" style="font-size: 4rem;"></i>
                <h4 class="mt-3 text-muted">No News Available</h4>
                <p class="text-muted">Check back later for updates and announcements.</p>
            </div> -->
        </div>
    </section>

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
                        <li><a href="<?php echo APP_URL; ?>/public/contact.php"><i class="fas fa-chevron-right me-2 small"></i>Contact Us</a></li>
                    </ul>
                </div>

                <div class="col-lg-4 col-md-12">
                    <h5>Contact Info</h5>
                    <p class="small text-muted mb-3">National Institute of Electronics & Information Technology, Bhubaneswar</p>
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
                        © 2025 NIELIT Bhubaneswar. All Rights Reserved.
                    </div>
                    <div class="col-md-6 text-md-end">
                        Designed & Developed by NIELIT Team
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
