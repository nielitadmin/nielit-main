<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIELIT Bhubaneswar | Ministry of Electronics & IT</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php 
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/includes/theme_loader.php';
    require_once __DIR__ . '/includes/navigation_helper.php';
    
    // Load active theme
    $active_theme = loadActiveTheme($conn);
    $theme_logo = getThemeLogo($active_theme);
    
    // Load navigation menu from database (with fallback to hardcoded menu)
    $navigation_menu_html = '';
    if (navigationMenuTableExists($conn)) {
        $menu_items = getNavigationMenu($conn);
        $current_page = basename($_SERVER['PHP_SELF']);
        $navigation_menu_html = renderNavigationMenu($menu_items, $current_page);
    }
    
    // Use fallback if no menu items found
    if (empty($navigation_menu_html)) {
        $navigation_menu_html = getFallbackNavigationMenu();
    }
    
    // Inject theme CSS
    injectThemeCSS($active_theme);
    
    // Load homepage content sections from database with caching
    // Cache Strategy:
    // - Cache duration: 1 hour (3600 seconds)
    // - Cache storage: PHP session
    // - Cache key: 'homepage_content_cache'
    // - Cache invalidation: Automatic after 1 hour, or manual via cache clearing (Task 15.4)
    // - Performance benefit: Reduces database queries on every page load
    $banners = [];
    $announcements_content = [];
    $featured_courses = [];
    $text_blocks = [];
    $image_blocks = [];
    
    // Cache configuration
    $cache_duration = 3600; // 1 hour in seconds
    $cache_key = 'homepage_content_cache';
    $cache_time_key = 'homepage_content_cache_time';
    
    // Check if cached content exists and is not expired
    $use_cache = false;
    if (isset($_SESSION[$cache_key]) && isset($_SESSION[$cache_time_key])) {
        $cache_age = time() - $_SESSION[$cache_time_key];
        if ($cache_age < $cache_duration) {
            $use_cache = true;
        }
    }
    
    if ($use_cache) {
        // Use cached content
        $cached_data = $_SESSION[$cache_key];
        $banners = $cached_data['banners'];
        $announcements_content = $cached_data['announcements_content'];
        $featured_courses = $cached_data['featured_courses'];
        $text_blocks = $cached_data['text_blocks'];
        $image_blocks = $cached_data['image_blocks'];
    } else {
        // Cache is invalid or doesn't exist - query database
        try {
            // Query homepage_content table for active sections ordered by display_order
            $content_sql = "SELECT * FROM homepage_content WHERE is_active = 1 ORDER BY display_order ASC";
            $content_result = $conn->query($content_sql);
            
            if ($content_result) {
                // Group sections by type for easier rendering
                while ($section = $content_result->fetch_assoc()) {
                    switch ($section['section_type']) {
                        case 'banner':
                            $banners[] = $section;
                            break;
                        case 'announcement':
                            $announcements_content[] = $section;
                            break;
                        case 'featured_course':
                            $featured_courses[] = $section;
                            break;
                        case 'text_block':
                            $text_blocks[] = $section;
                            break;
                        case 'image_block':
                            $image_blocks[] = $section;
                            break;
                    }
                }
            }
            
            // Store results in cache
            $_SESSION[$cache_key] = [
                'banners' => $banners,
                'announcements_content' => $announcements_content,
                'featured_courses' => $featured_courses,
                'text_blocks' => $text_blocks,
                'image_blocks' => $image_blocks
            ];
            $_SESSION[$cache_time_key] = time();
            
        } catch (Exception $e) {
            // Log error and continue with empty arrays (fallback to hardcoded content)
            error_log("Homepage content query failed: " . $e->getMessage());
        }
    }
    ?>

    <style>
        :root {
            --primary-blue: var(--primary-color, #0d47a1); /* Use theme primary color */
            --secondary-blue: var(--secondary-color, #1565c0); /* Use theme secondary color */
            --accent-gold: var(--accent-color, #ffc107); /* Use theme accent color */
            --light-bg: #f8f9fa;
            --text-dark: #212529;
            --text-muted: #6c757d;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-dark);
            padding-top: 0; /* Bootstrap 5 navbar handling */
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }

        /* ===== TOP BAR (Gov Info) ===== */
        .top-bar {
            background-color: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 8px 0;
            font-size: 0.85rem;
        }
        
        .gov-logos img {
            height: 45px;
            width: auto;
        }

        .ministry-text {
            font-weight: 600;
            color: var(--text-dark);
            line-height: 1.2;
        }

        /* ===== MAIN NAVBAR ===== */
        .navbar {
            background-color: var(--primary-blue);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            padding: 0.5rem 1rem;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: #fff !important;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 5px;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--accent-gold) !important;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            border-radius: 8px;
            margin-top: 10px;
        }

        .dropdown-item:hover {
            background-color: #e3f2fd;
            color: var(--primary-blue);
        }

        /* ===== NOTICE TICKER ===== */
        .notice-bar {
            background: linear-gradient(90deg, #1565c0 0%, #42a5f5 100%);
            color: white;
            padding: 10px 0;
            overflow: hidden;
            white-space: nowrap;
            position: relative;
        }

        .notice-content {
            display: inline-block;
            padding-left: 100%;
            animation: ticker 25s linear infinite;
            font-weight: 500;
        }

        @keyframes ticker {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(-100%, 0, 0); }
        }

        /* ===== HERO CAROUSEL ===== */
        .carousel-item {
            height: 500px;
        }
        
        .carousel-item img {
            height: 100%;
            object-fit: cover;
            filter: brightness(0.9); /* Slight dim for better text readability if added */
        }

        /* ===== FEATURE CARDS ===== */
        .feature-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #eee;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-color: var(--primary-blue);
        }

        .feature-icon {
            font-size: 2rem;
            color: var(--secondary-blue);
            margin-bottom: 15px;
        }

        /* ===== FOOTER ===== */
        footer {
            background-color: #1a202c; /* Darker modern footer */
            color: #cbd5e0;
            font-size: 0.95rem;
        }

        footer h5 {
            color: #fff;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        footer h5::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 40px;
            height: 3px;
            background-color: var(--accent-gold);
        }

        footer a {
            color: #cbd5e0;
            text-decoration: none;
            transition: color 0.2s;
            display: block;
            margin-bottom: 8px;
        }

        footer a:hover {
            color: var(--accent-gold);
            padding-left: 5px;
        }

        .copyright-bar {
            background-color: #111827;
            padding: 15px 0;
            border-top: 1px solid #2d3748;
        }

        /* ===== LEVEL INDICATORS ===== */
        .level-indicator {
            animation: fadeInDown 0.6s ease-out;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== INFO DETAIL CARDS (LEVEL 3) ===== */
        .info-detail-card {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            height: 100%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .info-detail-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
        }

        .info-detail-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
            border-color: var(--primary-blue);
        }

        .card-icon-header {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 8px 16px rgba(13, 71, 161, 0.3);
        }

        .card-icon-header i {
            font-size: 32px;
            color: white;
        }

        .info-detail-card .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 16px;
        }

        .info-detail-card .card-text {
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .detail-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .detail-list li {
            padding: 10px 0;
            color: var(--text-dark);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .detail-list li:last-child {
            border-bottom: none;
        }

        .detail-list i {
            color: #10b981;
            font-size: 1rem;
        }

        /* ===== QUICK LINKS GRID ===== */
        .quick-links-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 20px;
        }

        .quick-link-btn {
            background: linear-gradient(135deg, #f8fafc 0%, #e3f2fd 100%);
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 10px;
            text-align: center;
            text-decoration: none;
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .quick-link-btn i {
            font-size: 1.3rem;
            margin-bottom: 4px;
        }

        .quick-link-btn:hover {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(13, 71, 161, 0.3);
            border-color: var(--primary-blue);
        }

        /* ===== ENHANCED FEATURE CARDS ===== */
        .feature-card {
            position: relative;
            overflow: hidden;
        }

        .feature-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--accent-gold) 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover::after {
            transform: scaleX(1);
        }

        /* ===== MOBILE TWEAKS ===== */
        @media (max-width: 768px) {
            .carousel-item { height: 250px; }
            .gov-logos { justify-content: center !important; margin-top: 10px; }
            .text-header-group { text-align: center; }
            .feature-card { text-align: center; }
            
            .info-detail-card {
                padding: 24px;
                margin-bottom: 20px;
            }
            
            .card-icon-header {
                width: 60px;
                height: 60px;
            }
            
            .card-icon-header i {
                font-size: 28px;
            }
            
            .quick-links-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-link-btn {
                padding: 16px;
            }
        }
    </style>
</head>
<body>

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

    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-university me-2"></i> NIELIT
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php echo $navigation_menu_html; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="notice-bar">
        <div class="notice-content">
            <span class="badge bg-warning text-dark me-2">NEW</span> 
            Admissions Open! NIELIT Bhubaneswar offers NSQF-aligned courses with modern facilities. Visit our Balasore Extension Center today.
        </div>
    </div>

    <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="<?php echo APP_URL; ?>/assets/images/banners/bhubaneswar_banner.jpg" class="d-block w-100" alt="NIELIT Campus">
            </div>
            <div class="carousel-item">
                <img src="<?php echo APP_URL; ?>/assets/images/banners/bhubaneswar_banner_2.jpg" class="d-block w-100" alt="NIELIT Lab">
            </div>
            <div class="carousel-item">
                <img src="https://via.placeholder.com/1920x600/356c9f/ffffff?text=NIELIT+Events" class="d-block w-100" alt="Events">
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
        </button>
    </div>

    <?php
    // Check if ALL content arrays are empty to determine if we should show fallback content
    // Requirement 12.4: Display hardcoded content if no database content exists
    $has_database_content = !empty($banners) || !empty($announcements_content) || 
                           !empty($featured_courses) || !empty($text_blocks) || 
                           !empty($image_blocks);
    ?>

    <?php if ($has_database_content): ?>
        <!-- ===== DYNAMIC BANNER SECTIONS ===== -->
        <?php if (!empty($banners)): ?>
            <?php foreach ($banners as $banner): ?>
            <section class="py-5" style="background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);">
                <div class="container">
                    <div class="text-center">
                        <h2 class="fw-bold mb-3" style="color: var(--primary-blue);">
                            <?php echo htmlspecialchars($banner['section_title']); ?>
                        </h2>
                        <div class="lead">
                            <?php echo $banner['section_content']; ?>
                        </div>
                    </div>
                </div>
            </section>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- ===== DYNAMIC ANNOUNCEMENT SECTIONS ===== -->
        <?php if (!empty($announcements_content)): ?>
        <section class="py-4" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
            <div class="container">
                <div class="row g-3">
                    <?php foreach ($announcements_content as $announcement): ?>
                    <div class="col-md-<?php echo count($announcements_content) <= 2 ? '6' : '4'; ?>">
                        <div class="alert alert-warning h-100 mb-0" role="alert">
                            <h6 class="alert-heading fw-bold">
                                <i class="fas fa-bullhorn"></i>
                                <?php echo htmlspecialchars($announcement['section_title']); ?>
                            </h6>
                            <div class="small">
                                <?php echo $announcement['section_content']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ===== DYNAMIC FEATURED COURSES ===== -->
        <?php if (!empty($featured_courses)): ?>
        <section class="py-5 bg-white">
            <div class="container">
                <div class="text-center mb-4">
                    <h3 class="fw-bold" style="color: var(--primary-blue);">
                        <i class="fas fa-star"></i> Featured Courses
                    </h3>
                </div>
                <div class="row g-4">
                    <?php foreach ($featured_courses as $course): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="feature-card">
                            <div class="feature-icon"><i class="fas fa-graduation-cap"></i></div>
                            <h5 class="fw-bold"><?php echo htmlspecialchars($course['section_title']); ?></h5>
                            <div class="text-muted small">
                                <?php echo $course['section_content']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ===== DYNAMIC TEXT BLOCKS ===== -->
        <?php if (!empty($text_blocks)): ?>
            <?php foreach ($text_blocks as $text_block): ?>
            <section class="py-5 bg-light">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-10">
                            <h3 class="fw-bold mb-3" style="color: var(--primary-blue);">
                                <?php echo htmlspecialchars($text_block['section_title']); ?>
                            </h3>
                            <div class="text-muted" style="line-height: 1.8;">
                                <?php echo $text_block['section_content']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- ===== DYNAMIC IMAGE BLOCKS ===== -->
        <?php if (!empty($image_blocks)): ?>
            <?php foreach ($image_blocks as $image_block): ?>
            <section class="py-5 bg-white">
                <div class="container">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold" style="color: var(--primary-blue);">
                            <?php echo htmlspecialchars($image_block['section_title']); ?>
                        </h3>
                    </div>
                    <div class="row justify-content-center">
                        <div class="col-lg-10">
                            <?php echo $image_block['section_content']; ?>
                        </div>
                    </div>
                </div>
            </section>
            <?php endforeach; ?>
        <?php endif; ?>
    
    <?php else: ?>
        <!-- ===== FALLBACK: HARDCODED CONTENT (When no database content exists) ===== -->
        <!-- ===== LEVEL 1: WELCOME SECTION (Fallback/Default Content) ===== -->
    <section class="py-5 bg-white">
        <div class="container">
            <div class="row justify-content-center text-center mb-5">
                <div class="col-lg-10">
                    <h2 class="fw-bold mb-3" style="font-size: 2.5rem; background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        Welcome to NIELIT Bhubaneswar
                    </h2>
                    <p class="text-muted lead mb-4" style="font-size: 1.15rem; line-height: 1.8;">
                        Established in 2021, we are a premier center dedicated to skilling and reskilling professionals in Information, Electronics, and Communication Technology (IECT).
                    </p>
                    <div style="height: 4px; width: 80px; background: linear-gradient(90deg, #ffc107 0%, #ff9800 100%); margin: 0 auto; border-radius: 2px;"></div>
                </div>
            </div>

            <!-- KEY FEATURES -->
            <div class="mb-5">
                <div class="text-center mb-4">
                    <h3 class="fw-bold" style="color: var(--primary-blue);">Key Features</h3>
                </div>
                <div class="row g-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card text-center">
                            <div class="feature-icon"><i class="fas fa-laptop-code"></i></div>
                            <h5 class="fw-bold">Skill Development</h5>
                            <p class="text-muted small">Focused on NSQF-aligned courses to boost employability in the tech sector.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card text-center">
                            <div class="feature-icon"><i class="fas fa-map-marked-alt"></i></div>
                            <h5 class="fw-bold">Regional Scope</h5>
                            <p class="text-muted small">Operating extensively across Odisha and Chhattisgarh to reach aspiring students.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card text-center">
                            <div class="feature-icon"><i class="fas fa-building"></i></div>
                            <h5 class="fw-bold">Modern Facilities</h5>
                            <p class="text-muted small">State-of-the-art labs, classrooms, and conference halls at OCAC Tower.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card text-center">
                            <div class="feature-icon"><i class="fas fa-network-wired"></i></div>
                            <h5 class="fw-bold">Balasore Extension</h5>
                            <p class="text-muted small">Expanding our footprint to provide quality education in the Balasore region.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DETAILED INFORMATION -->
            <div class="mt-5">
                <div class="text-center mb-4">
                    <h3 class="fw-bold" style="color: var(--primary-blue);">Detailed Information</h3>
                </div>
                <div class="row g-4">
                    <!-- About Us Card -->
                    <div class="col-lg-4">
                        <div class="info-detail-card">
                            <div class="card-icon-header">
                                <i class="fas fa-university"></i>
                            </div>
                            <h4 class="card-title">About NIELIT</h4>
                            <p class="card-text">
                                NIELIT Bhubaneswar is an autonomous scientific society under the Ministry of Electronics & IT, Government of India. We focus on human resource development in IECT through quality education and training.
                            </p>
                            <ul class="detail-list">
                                <li><i class="fas fa-check-circle"></i> Government of India Initiative</li>
                                <li><i class="fas fa-check-circle"></i> NSQF Aligned Programs</li>
                                <li><i class="fas fa-check-circle"></i> Industry-Ready Training</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Our Mission Card -->
                    <div class="col-lg-4">
                        <div class="info-detail-card">
                            <div class="card-icon-header">
                                <i class="fas fa-bullseye"></i>
                            </div>
                            <h4 class="card-title">Our Mission</h4>
                            <p class="card-text">
                                To empower youth with cutting-edge technology skills, making them industry-ready and contributing to India's digital transformation through quality education and practical training.
                            </p>
                            <ul class="detail-list">
                                <li><i class="fas fa-check-circle"></i> Skill Enhancement</li>
                                <li><i class="fas fa-check-circle"></i> Employment Generation</li>
                                <li><i class="fas fa-check-circle"></i> Digital India Support</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Quick Links Card -->
                    <div class="col-lg-4">
                        <div class="info-detail-card">
                            <div class="card-icon-header">
                                <i class="fas fa-link"></i>
                            </div>
                            <h4 class="card-title">Quick Access</h4>
                            <p class="card-text">
                                Explore our offerings and get started with your learning journey. Access courses, register online, and connect with us for any queries.
                            </p>
                            <div class="quick-links-grid">
                                <a href="public/courses.php" class="quick-link-btn">
                                    <i class="fas fa-book"></i> View Courses
                                </a>
                                <a href="student/login.php" class="quick-link-btn">
                                    <i class="fas fa-sign-in-alt"></i> Student Portal
                                </a>
                                <a href="public/contact.php" class="quick-link-btn">
                                    <i class="fas fa-envelope"></i> Contact Us
                                </a>
                                <a href="public/news.php" class="quick-link-btn">
                                    <i class="fas fa-newspaper"></i> News & Events
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; // End of fallback content check ?>

    <!-- ===== ANNOUNCEMENTS SECTION ===== -->
    <?php
    // Fetch active announcements for public (all or students)
    $announcements_sql = "SELECT * FROM announcements 
                          WHERE is_active = 1 
                          AND (target_audience = 'all' OR target_audience = 'students')
                          ORDER BY created_at DESC 
                          LIMIT 3";
    $announcements_result = $conn->query($announcements_sql);
    ?>
    
    <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
    <section class="py-5" style="background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);">
        <div class="container">
            <div class="text-center mb-4">
                <h3 class="fw-bold" style="color: var(--primary-blue);">
                    <i class="fas fa-bullhorn"></i> Latest Announcements
                </h3>
            </div>
            <div class="row g-3">
                <?php while ($announcement = $announcements_result->fetch_assoc()): 
                    $alert_class = [
                        'info' => 'alert-info',
                        'success' => 'alert-success',
                        'warning' => 'alert-warning',
                        'danger' => 'alert-danger'
                    ];
                    $icon_class = [
                        'info' => 'fa-info-circle',
                        'success' => 'fa-check-circle',
                        'warning' => 'fa-exclamation-triangle',
                        'danger' => 'fa-exclamation-circle'
                    ];
                    $type = $announcement['type'];
                ?>
                <div class="col-md-4">
                    <div class="alert <?php echo $alert_class[$type]; ?> h-100 mb-0" role="alert">
                        <h6 class="alert-heading fw-bold">
                            <i class="fas <?php echo $icon_class[$type]; ?>"></i>
                            <?php echo htmlspecialchars($announcement['title']); ?>
                        </h6>
                        <p class="mb-2 small"><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></p>
                        <hr>
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> 
                            <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                        </small>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

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
                        <li><a href="public/contact.php"><i class="fas fa-chevron-right me-2 small"></i>Contact Us</a></li>
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