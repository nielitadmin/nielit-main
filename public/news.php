<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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
    require_once __DIR__ . '/../includes/news_helper.php';

    // Load active theme
    $active_theme = loadActiveTheme($conn);
    $theme_logo = getThemeLogo($active_theme);

    $news_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $selected_news = $news_id > 0 ? getPublicNewsArticle($conn, $news_id) : null;
    $news_items = listPublicNews($conn, 100);
    $page_title = $selected_news
        ? (string) $selected_news['title'] . ' - News - NIELIT Bhubaneswar'
        : 'News - NIELIT Bhubaneswar';

    // Inject theme CSS
    injectThemeCSS($active_theme);
    emitPublicThemeHead($conn);
    ?>
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/public-theme.css">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
    <style>
        .news-list-card { transition: transform .2s ease, box-shadow .2s ease; }
        .news-list-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(15, 23, 42, .12) !important; }
        .news-list-thumb {
            height: 180px; object-fit: cover; width: 100%;
            background: linear-gradient(135deg, #e8eef7, #f8fafc);
        }
        .news-list-thumb-placeholder {
            height: 180px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #e8eef7, #f8fafc); color: #64748b; font-size: 2.5rem;
        }
        .news-article-hero,
        .news-article-carousel {
            max-height: 420px; width: 100%; border-radius: 12px; overflow: hidden;
            background: #e8eef7;
        }
        .news-article-carousel .carousel-item,
        .news-article-carousel .news-slide-image,
        .news-article-hero .news-slide-image {
            height: 420px; object-fit: cover; width: 100%;
        }
        .news-list-carousel,
        .news-list-carousel .carousel-inner,
        .news-list-carousel .carousel-item {
            height: 180px;
        }
        .news-list-carousel .news-slide-image {
            height: 180px; object-fit: cover;
        }
        .news-list-carousel .carousel-control-prev,
        .news-list-carousel .carousel-control-next {
            width: 12%;
        }
        .news-list-carousel .carousel-indicators {
            margin-bottom: .4rem;
        }
        .news-list-carousel .carousel-indicators [data-bs-target] {
            width: 7px; height: 7px; border-radius: 50%;
        }
        .news-article-body { line-height: 1.75; font-size: 1.05rem; white-space: pre-wrap; }
        .news-article-body p { margin-bottom: 1rem; }
        .news-target-highlight {
            animation: newsFlash 1.6s ease;
            border-color: rgba(26, 86, 219, .45) !important;
        }
        @keyframes newsFlash {
            0% { box-shadow: 0 0 0 0 rgba(26, 86, 219, .35); }
            70% { box-shadow: 0 0 0 12px rgba(26, 86, 219, 0); }
            100% { box-shadow: none; }
        }
    </style>
<?php
require_once __DIR__ . '/../includes/public_skeleton_helper.php';
public_skeleton_render_head();
?>
</head>
<body class="public-page-loading public-site">
<?php public_skeleton_render_loader('generic'); ?>

    <!-- Top Bar -->
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
                            <li><a class="dropdown-item" href="<?php echo app_url('public/management'); ?>">Management</a></li>
                            <li><a class="dropdown-item" href="<?php echo app_url('public/team'); ?>">Our Team</a></li>
                            <li><a class="dropdown-item active" href="<?php echo app_url('public/news'); ?>">News</a></li>
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
            <?php if ($selected_news): ?>
                <h1 class="mb-0"><?php echo htmlspecialchars($selected_news['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="lead mb-0 mt-2">
                    <?php if (!empty($selected_news['category'])): ?>
                        <span class="badge <?php echo htmlspecialchars(newsCategoryBadgeClass($selected_news['category']), ENT_QUOTES, 'UTF-8'); ?> me-2">
                            <?php echo htmlspecialchars($selected_news['category'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    <?php endif; ?>
                    <?php echo date('F j, Y', strtotime((string) $selected_news['created_at'])); ?>
                </p>
            <?php else: ?>
                <h1 class="mb-0">Latest News & Announcements</h1>
                <p class="lead mb-0 mt-2">Stay updated with NIELIT Bhubaneswar</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- News Content -->
    <section class="py-5">
        <div class="container">
            <?php if ($news_id > 0 && !$selected_news): ?>
                <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This news item is unavailable or no longer active.
                    </div>
                    <a href="<?php echo htmlspecialchars(newsPublicUrl(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-dark">
                        Back to all news
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($selected_news): ?>
                <?php
                $detail_images = newsArticleImageUrls($selected_news);
                $detail_html = (string) ($selected_news['content'] ?? '');
                $looks_like_html = $detail_html !== strip_tags($detail_html);
                ?>
                <div class="mb-4">
                    <a href="<?php echo htmlspecialchars(newsPublicUrl(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> All News
                    </a>
                </div>

                <article class="card border-0 shadow-sm mb-5">
                    <div class="card-body p-4 p-md-5">
                        <?php if (!empty($detail_images)): ?>
                            <div class="mb-4">
                                <?php
                                echo renderNewsImageSlideshow(
                                    'newsDetailCarousel' . (int) $selected_news['id'],
                                    $detail_images,
                                    [
                                        'alt' => (string) $selected_news['title'],
                                        'class' => 'news-article-carousel',
                                        'interval' => 4500,
                                        'fade' => true,
                                    ]
                                );
                                ?>
                            </div>
                        <?php endif; ?>

                        <div class="news-article-body text-dark">
                            <?php
                            if ($looks_like_html) {
                                echo $detail_html;
                            } else {
                                echo nl2br(htmlspecialchars($detail_html, ENT_QUOTES, 'UTF-8'));
                            }
                            ?>
                        </div>
                    </div>
                </article>

                <?php
                $related = array_values(array_filter($news_items, static function (array $item) use ($selected_news): bool {
                    return (int) $item['id'] !== (int) $selected_news['id'];
                }));
                $related = array_slice($related, 0, 3);
                if (!empty($related)):
                ?>
                <h3 class="h5 mb-3">More Updates</h3>
                <div class="row g-4">
                    <?php foreach ($related as $item): ?>
                        <?php
                        $imgUrls = newsArticleImageUrls($item);
                        $cat = (string) ($item['category'] ?? '');
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm news-list-card">
                                <?php if (!empty($imgUrls)): ?>
                                    <?php
                                    echo renderNewsImageSlideshow(
                                        'newsRelatedCarousel' . (int) $item['id'],
                                        $imgUrls,
                                        [
                                            'alt' => (string) $item['title'],
                                            'class' => 'news-list-carousel',
                                            'interval' => 4000,
                                            'controls' => count($imgUrls) > 1,
                                            'indicators' => count($imgUrls) > 1,
                                        ]
                                    );
                                    ?>
                                <?php else: ?>
                                    <div class="news-list-thumb-placeholder"><i class="fas fa-newspaper"></i></div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <?php if ($cat !== ''): ?>
                                        <span class="badge <?php echo htmlspecialchars(newsCategoryBadgeClass($cat), ENT_QUOTES, 'UTF-8'); ?> mb-2"><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <h5 class="card-title h6"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                    <p class="card-text text-muted small"><?php echo htmlspecialchars(newsExcerpt($item['content'] ?? '', 100), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <a href="<?php echo htmlspecialchars(newsPublicUrl((int) $item['id']), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary btn-sm">
                                        Read More
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="row g-4">
                    <?php if (empty($news_items)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-newspaper text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3 text-muted">No News Available</h4>
                                <p class="text-muted mb-0">Check back later for updates and announcements.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($news_items as $item): ?>
                            <?php
                            $item_id = (int) $item['id'];
                            $imgUrls = newsArticleImageUrls($item);
                            $cat = (string) ($item['category'] ?? '');
                            $is_featured = !empty($item['is_featured']);
                            ?>
                            <div class="col-md-6 col-lg-4" id="news-<?php echo $item_id; ?>">
                                <div class="card h-100 border-0 shadow-sm news-list-card">
                                    <?php if (!empty($imgUrls)): ?>
                                        <?php
                                        echo renderNewsImageSlideshow(
                                            'newsListCarousel' . $item_id,
                                            $imgUrls,
                                            [
                                                'alt' => (string) $item['title'],
                                                'class' => 'news-list-carousel',
                                                'interval' => 4000,
                                                'controls' => count($imgUrls) > 1,
                                                'indicators' => count($imgUrls) > 1,
                                            ]
                                        );
                                        ?>
                                    <?php else: ?>
                                        <div class="news-list-thumb-placeholder"><i class="fas fa-newspaper"></i></div>
                                    <?php endif; ?>
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                            <?php if ($is_featured): ?>
                                                <span class="badge bg-warning text-dark"><i class="fas fa-star me-1"></i>Featured</span>
                                            <?php endif; ?>
                                            <?php if ($cat !== ''): ?>
                                                <span class="badge <?php echo htmlspecialchars(newsCategoryBadgeClass($cat), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php endif; ?>
                                            <small class="text-muted ms-auto">
                                                <?php echo date('M d, Y', strtotime((string) $item['created_at'])); ?>
                                            </small>
                                        </div>
                                        <h5 class="card-title"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                        <p class="card-text text-muted flex-grow-1">
                                            <?php echo htmlspecialchars(newsExcerpt($item['content'] ?? '', 140), ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <a href="<?php echo htmlspecialchars(newsPublicUrl($item_id), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary btn-sm align-self-start">
                                            <i class="fas fa-eye me-1"></i>Read More
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php
                    $brochure_candidates = [
                        __DIR__ . '/../news/Brochure.pdf',
                        __DIR__ . '/../assets/forms/Brochure.pdf',
                    ];
                    $brochure_web = '';
                    foreach ($brochure_candidates as $candidate) {
                        if (is_file($candidate)) {
                            $brochure_web = app_url(ltrim(str_replace('\\', '/', substr($candidate, strlen(dirname(__DIR__)) + 1)), '/'));
                            break;
                        }
                    }
                    if ($brochure_web !== ''):
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm news-list-card">
                            <div class="news-list-thumb-placeholder"><i class="fas fa-file-pdf text-danger"></i></div>
                            <div class="card-body d-flex flex-column">
                                <div class="mb-2">
                                    <span class="badge bg-primary">Document</span>
                                </div>
                                <h5 class="card-title">Course Brochure</h5>
                                <p class="card-text text-muted flex-grow-1">Download our comprehensive course brochure with details about all programs offered.</p>
                                <a href="<?php echo htmlspecialchars($brochure_web, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary btn-sm align-self-start" target="_blank" rel="noopener">
                                    <i class="fas fa-download me-1"></i>Download PDF
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
    <script>
    (function () {
        var hash = window.location.hash || '';
        if (!hash || hash.indexOf('#news-') !== 0) return;
        var el = document.querySelector(hash);
        if (!el) return;
        var card = el.querySelector('.news-list-card') || el;
        card.classList.add('news-target-highlight');
        setTimeout(function () {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 80);
    })();
    </script>
<?php public_skeleton_render_script(); ?>
</body>
</html>
