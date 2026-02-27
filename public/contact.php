<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - NIELIT Bhubaneswar</title>

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
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">About</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/public/management.php">Management</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/public/news.php">News</a></li>
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
                    <li class="nav-item"><a class="nav-link active" href="<?php echo APP_URL; ?>/public/contact.php">Contact</a></li>
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
            <h1 class="mb-0">Contact Us</h1>
            <p class="lead mb-0 mt-2">Get in touch with NIELIT Bhubaneswar</p>
        </div>
    </section>

    <!-- Contact Information -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <!-- Contact Details -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h3 class="mb-4 text-primary"><i class="fas fa-info-circle me-2"></i>Contact Information</h3>
                            
                            <div class="contact-item mb-4">
                                <div class="d-flex align-items-start">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-map-marker-alt text-primary"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-2">Address</h5>
                                        <p class="text-muted mb-0">
                                            3rd Floor, OCAC Tower<br>
                                            Acharya Vihar<br>
                                            Bhubaneswar - 751013<br>
                                            Odisha, India
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="contact-item mb-4">
                                <div class="d-flex align-items-start">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-phone-alt text-primary"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-2">Phone</h5>
                                        <p class="text-muted mb-0">
                                            <a href="tel:06742960354" class="text-decoration-none">0674-2960354</a>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="contact-item mb-4">
                                <div class="d-flex align-items-start">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-envelope text-primary"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-2">Email</h5>
                                        <p class="text-muted mb-0">
                                            <a href="mailto:dir-bbsr@nielit.gov.in" class="text-decoration-none">dir-bbsr@nielit.gov.in</a>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="contact-item">
                                <div class="d-flex align-items-start">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-clock text-primary"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-2">Working Hours</h5>
                                        <p class="text-muted mb-0">
                                            <strong>Monday - Friday:</strong> 09:00 AM - 05:30 PM<br>
                                            <span class="text-danger">Saturday & Sunday: Closed</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-0">
                            <div class="map-container" style="height: 100%; min-height: 500px;">
                                <iframe 
                                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3766.964689486328!2d85.8283624!3d20.2990535!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3a1909c30a470d79%3A0xbc2f6caa4b6f64a4!2sNIELIT%20(National%20Institute%20of%20Electronics%20and%20Information%20Technology)%2C%20Bhubaneswar!5e0!3m2!1sen!2sin!4v1718117088431!5m2!1sen!2sin" 
                                    width="100%" 
                                    height="100%" 
                                    style="border:0; border-radius: 8px;" 
                                    allowfullscreen="" 
                                    loading="lazy" 
                                    referrerpolicy="no-referrer-when-downgrade">
                                </iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Contact Cards -->
            <div class="row g-4 mt-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm text-center h-100 hover-lift">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <i class="fas fa-graduation-cap text-primary" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Admissions</h5>
                            <p class="card-text text-muted small">For course admissions and enrollment queries</p>
                            <a href="<?php echo APP_URL; ?>/public/courses.php" class="btn btn-outline-primary btn-sm">View Courses</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm text-center h-100 hover-lift">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <i class="fas fa-user-circle text-success" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Student Portal</h5>
                            <p class="card-text text-muted small">Access your student account and resources</p>
                            <a href="<?php echo APP_URL; ?>/student/login.php" class="btn btn-outline-success btn-sm">Login</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm text-center h-100 hover-lift">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <i class="fas fa-user-shield text-warning" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Admin Portal</h5>
                            <p class="card-text text-muted small">For administrative access and management</p>
                            <a href="<?php echo APP_URL; ?>/admin/login.php" class="btn btn-outline-warning btn-sm">Admin Login</a>
                        </div>
                    </div>
                </div>
            </div>
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
