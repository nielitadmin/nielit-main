<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIELIT Bhubaneswar | Ministry of Electronics & IT</title>

    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php 
    require_once __DIR__ . '/includes/maintenance_check.php';
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/includes/theme_loader.php';
    require_once __DIR__ . '/includes/navigation_helper.php';
    require_once __DIR__ . '/includes/url_helper.php';
    
    $active_theme = loadActiveTheme($conn);
    $theme_logo = getThemeLogo($active_theme);
    
    $navigation_menu_html = '';
    if (navigationMenuTableExists($conn)) {
        $menu_items = getNavigationMenu($conn);
        $current_page = basename($_SERVER['PHP_SELF']);
        $navigation_menu_html = renderNavigationMenu($menu_items, $current_page);
    }
    if (empty($navigation_menu_html)) {
        $navigation_menu_html = getFallbackNavigationMenu();
    }
    $job_fair_portal_url = getJobFairPortalUrl();
    $mock_test_portal_url = getMockTestPortalUrl();
    injectThemeCSS($active_theme);
    
    $banners = []; $announcements_content = []; $featured_courses = [];
    $text_blocks = []; $image_blocks = []; $news_items = [];
    
    // Create news table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS news (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        content LONGTEXT NOT NULL,
        category VARCHAR(100),
        image_url VARCHAR(500),
        is_featured TINYINT(1) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_by VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    @$conn->query($create_table_sql);
    
    // Fetch news items
    $news_sql = "SELECT * FROM news WHERE is_active = 1 ORDER BY is_featured DESC, created_at DESC LIMIT 6";
    $news_result = $conn->query($news_sql);
    if ($news_result) {
        while ($row = $news_result->fetch_assoc()) {
            $news_items[] = $row;
        }
    }
    
    $cache_duration = 3600; $cache_key = 'homepage_content_cache'; $cache_time_key = 'homepage_content_cache_time';
    $use_cache = false;
    if (isset($_SESSION[$cache_key]) && isset($_SESSION[$cache_time_key])) {
        if (time() - $_SESSION[$cache_time_key] < $cache_duration) $use_cache = true;
    }
    if ($use_cache) {
        $cached_data = $_SESSION[$cache_key];
        extract($cached_data);
    } else {
        try {
            $content_result = $conn->query("SELECT * FROM homepage_content WHERE is_active = 1 ORDER BY display_order ASC");
            if ($content_result) {
                while ($section = $content_result->fetch_assoc()) {
                    switch ($section['section_type']) {
                        case 'banner': $banners[] = $section; break;
                        case 'announcement': $announcements_content[] = $section; break;
                        case 'featured_course': $featured_courses[] = $section; break;
                        case 'text_block': $text_blocks[] = $section; break;
                        case 'image_block': $image_blocks[] = $section; break;
                    }
                }
            }
            $_SESSION[$cache_key] = compact('banners','announcements_content','featured_courses','text_blocks','image_blocks');
            $_SESSION[$cache_time_key] = time();
        } catch (Exception $e) { error_log("Homepage content query failed: " . $e->getMessage()); }
    }
    // Decorative background uses CSS gradients; no SVG asset needed
    ?>

    <style>
        :root {
            --navy: #0a1628;
            --navy-mid: #112240;
            --blue: #1a56db;
            --blue-light: #3b82f6;
            --gold: #f59e0b;
            --gold-light: #fcd34d;
            --cream: #fafaf8;
            --text: #0f172a;
            --muted: #64748b;
            --border: rgba(0,0,0,0.08);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--cream);
            color: var(--text);
            overflow-x: hidden;
        }

        h1,h2,h3,h4,h5,h6 { font-family: 'Sora', sans-serif; }

        /* ===== TOP BAR ===== */
        .top-bar {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 10px 0;
        }
        .top-bar img { height: 48px; }
        .top-bar .inst-name-hi { font-size: 0.82rem; color: var(--blue); font-weight: 600; font-family: 'Sora', sans-serif; }
        .top-bar .inst-name-en { font-size: 0.95rem; font-weight: 700; color: var(--navy); font-family: 'Sora', sans-serif; }
        .top-bar .ministry-badge {
            background: var(--navy);
            color: #fff;
            font-size: 0.72rem;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 500;
            white-space: nowrap;
        }

        /* ===== NAVBAR ===== */
        .navbar {
            background: var(--navy);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 3px solid var(--gold);
        }
        .navbar .container { padding: 0 1rem; }
        .navbar-brand {
            font-family: 'Sora', sans-serif;
            font-weight: 800;
            font-size: 1.25rem;
            color: #fff !important;
            padding: 18px 0;
            letter-spacing: -0.5px;
        }
        .navbar-brand span { color: var(--gold); }
        .nav-link {
            color: rgba(255,255,255,0.82) !important;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 22px 14px !important;
            position: relative;
            transition: color 0.2s;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0; left: 14px; right: 14px;
            height: 3px;
            background: var(--gold);
            transform: scaleX(0);
            transition: transform 0.25s ease;
        }
        .nav-link:hover, .nav-link.active { color: #fff !important; }
        .nav-link:hover::after, .nav-link.active::after { transform: scaleX(1); }
        .dropdown-menu {
            background: var(--navy-mid);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 8px;
            margin-top: 0;
        }
        .dropdown-item { color: rgba(255,255,255,0.8); border-radius: 6px; padding: 9px 14px; font-size: 0.88rem; }
        .dropdown-item:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .navbar-toggler { border: 1px solid rgba(255,255,255,0.3); }
        .navbar-toggler-icon { filter: invert(1); }

        /* ===== NOTICE TICKER ===== */
        .notice-bar {
            background: var(--gold);
            color: var(--navy);
            padding: 9px 0;
            overflow: hidden;
            white-space: nowrap;
        }
        .notice-label {
            background: var(--navy);
            color: var(--gold);
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 20px;
            margin-right: 12px;
            font-family: 'Sora', sans-serif;
            letter-spacing: 0.5px;
        }
        .notice-content {
            display: inline-block;
            padding-left: 100%;
            animation: ticker 28s linear infinite;
            font-weight: 500;
            font-size: 0.88rem;
        }
        @keyframes ticker {
            0% { transform: translate3d(0,0,0); }
            100% { transform: translate3d(-100%,0,0); }
        }

        /* ===== HERO ===== */
        .hero-section {
            position: relative;
            height: 92vh;
            min-height: 560px;
            max-height: 780px;
            overflow: hidden;
            background: var(--navy);
        }
        .hero-carousel, .hero-carousel .carousel-inner, .hero-carousel .carousel-item {
            height: 100%;
        }
        .hero-carousel .carousel-item img {
            height: 100%;
            width: 100%;
            object-fit: cover;
            opacity: 0.45;
            filter: saturate(0.6);
        }
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(10,22,40,0.85) 0%, rgba(10,22,40,0.3) 100%);
            display: flex;
            align-items: center;
            z-index: 2;
            pointer-events: none;
        }
        .hero-content { max-width: 720px; pointer-events: auto; }
        .hero-eyebrow {
            display: inline-block;
            background: rgba(245,158,11,0.15);
            border: 1px solid rgba(245,158,11,0.4);
            color: var(--gold-light);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 20px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-bottom: 20px;
            font-family: 'Sora', sans-serif;
        }
        .hero-title {
            font-size: clamp(2.4rem, 5vw, 4rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.1;
            letter-spacing: -1.5px;
            margin-bottom: 20px;
        }
        .hero-title em {
            font-style: normal;
            color: var(--gold);
        }
        .hero-sub {
            color: rgba(255,255,255,0.72);
            font-size: 1.08rem;
            line-height: 1.7;
            margin-bottom: 36px;
            max-width: 540px;
        }
        .hero-btns { display: flex; gap: 14px; flex-wrap: wrap; }
        .btn-hero-primary {
            background: var(--gold);
            color: var(--navy);
            font-weight: 700;
            font-family: 'Sora', sans-serif;
            padding: 14px 30px;
            border-radius: 50px;
            border: none;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.25s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-hero-primary:hover {
            background: var(--gold-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(245,158,11,0.4);
            color: var(--navy);
        }
        .btn-hero-outline {
            background: transparent;
            color: #fff;
            font-weight: 600;
            font-family: 'Sora', sans-serif;
            padding: 13px 28px;
            border-radius: 50px;
            border: 2px solid rgba(255,255,255,0.4);
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.25s;
        }
        .btn-hero-outline:hover {
            border-color: #fff;
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .hero-stats {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            z-index: 3;
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(255,255,255,0.12);
        }
        .hero-stat {
            padding: 22px 0;
            text-align: center;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .hero-stat:last-child { border-right: none; }
        .hero-stat .number {
            font-family: 'Sora', sans-serif;
            font-size: 1.9rem;
            font-weight: 800;
            color: var(--gold);
            display: block;
            line-height: 1;
        }
        .hero-stat .label {
            font-size: 0.78rem;
            color: rgba(255,255,255,0.6);
            margin-top: 4px;
            font-weight: 500;
        }
        .carousel-control-prev, .carousel-control-next {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            bottom: auto;
            z-index: 5;
            pointer-events: auto;
        }
        .carousel-control-prev { left: 24px; }
        .carousel-control-next { right: 24px; }
        .hero-carousel .carousel-indicators {
            bottom: 90px;
            margin-bottom: 0;
            z-index: 4;
        }
        .hero-carousel .carousel-indicators [data-bs-target] {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            margin: 0 4px;
            background-color: rgba(255,255,255,0.55);
            border: 0;
        }
        .hero-carousel .carousel-indicators .active { background-color: var(--gold); }

        /* ===== SECTION TITLES ===== */
        .section-eyebrow {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--blue);
            font-family: 'Sora', sans-serif;
            margin-bottom: 10px;
            display: block;
        }
        .section-title {
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            font-weight: 800;
            color: var(--navy);
            letter-spacing: -0.8px;
            line-height: 1.15;
            margin-bottom: 16px;
        }
        .section-divider {
            width: 48px;
            height: 4px;
            background: var(--gold);
            border-radius: 2px;
            margin: 0 auto 16px;
        }
        .section-divider.left { margin-left: 0; }

        /* ===== WELCOME STRIP ===== */
        .welcome-strip {
            background: var(--navy);
            padding: 60px 0;
            overflow: hidden;
            position: relative;
        }
        .welcome-strip::before {
            content: '';
            position: absolute;
            right: -80px; top: -80px;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(245,158,11,0.12) 0%, transparent 70%);
            pointer-events: none;
        }
        .welcome-strip .section-title { color: #fff; }
        .welcome-strip .section-eyebrow { color: var(--gold-light); }
        .welcome-strip p { color: rgba(255,255,255,0.68); line-height: 1.8; font-size: 1rem; }
        .stat-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 50px;
            padding: 10px 20px;
            color: #fff;
            font-size: 0.88rem;
            font-weight: 500;
            margin: 6px 4px;
        }
        .stat-pill i { color: var(--gold); font-size: 0.9rem; }
        .stat-pill a { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
        .stat-pill:hover { transform: translateY(-4px); box-shadow: 0 10px 24px rgba(0,0,0,0.2); }
        .stat-pill[role="link"] { cursor: pointer; }
        /* Decorative premium CSS background for white sections */
        .section-white-pattern {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            background:
                radial-gradient(circle at 15% 20%, rgba(26,86,219,0.07) 0, rgba(26,86,219,0) 33%),
                radial-gradient(circle at 85% 30%, rgba(245,158,11,0.09) 0, rgba(245,158,11,0) 36%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }
        .section-white-pattern::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(90deg, rgba(17,34,64,0.045) 1px, transparent 1px),
                linear-gradient(0deg, rgba(17,34,64,0.045) 1px, transparent 1px);
            background-size: 42px 42px;
            opacity: 0.55;
            pointer-events: none;
            z-index: 0;
        }
        .section-white-pattern::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 44px;
            width: min(820px, 88%);
            height: 120px;
            transform: translateX(-50%);
            background: linear-gradient(180deg, rgba(16,44,93,0.08) 0%, rgba(16,44,93,0) 100%);
            clip-path: polygon(0 100%, 7% 52%, 13% 72%, 21% 38%, 30% 66%, 39% 26%, 49% 62%, 60% 32%, 70% 66%, 80% 46%, 89% 76%, 100% 100%);
            pointer-events: none;
            z-index: 0;
        }
        .section-white-pattern > * {
            position: relative;
            z-index: 1;
        }

        /* ===== FEATURE CARDS ===== */
        .features-section { padding: 90px 0; background: #fff; }
        .feat-card {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 36px 28px;
            height: 100%;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
            position: relative;
            overflow: hidden;
        }
        .feat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--blue) 0%, var(--gold) 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .feat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 24px 48px rgba(0,0,0,0.1);
            background: #fff;
            border-color: transparent;
        }
        .feat-card:hover::before { opacity: 1; }
        .feat-icon-wrap {
            width: 58px; height: 58px;
            background: var(--navy);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 22px;
        }
        .feat-icon-wrap i { font-size: 1.5rem; color: var(--gold); }
        .feat-card h5 {
            font-family: 'Sora', sans-serif;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--navy);
            margin-bottom: 10px;
        }
        .feat-card p { color: var(--muted); font-size: 0.9rem; line-height: 1.7; margin: 0; }

        /* ===== JOB FAIR PORTAL ===== */
        .jobfair-section {
            padding: 88px 0;
            background: linear-gradient(135deg, var(--navy) 0%, #163a6b 52%, var(--navy-mid) 100%);
            position: relative;
            overflow: hidden;
        }
        .jobfair-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 12% 18%, rgba(245,158,11,0.16) 0, transparent 34%),
                radial-gradient(circle at 88% 72%, rgba(59,130,246,0.18) 0, transparent 36%);
            pointer-events: none;
        }
        .jobfair-section > .container { position: relative; z-index: 1; }
        .jobfair-panel {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 28px;
            padding: 40px;
            backdrop-filter: blur(14px);
        }
        .jobfair-eyebrow {
            display: inline-block;
            background: rgba(245,158,11,0.15);
            border: 1px solid rgba(245,158,11,0.35);
            color: var(--gold-light);
            font-size: 0.74rem;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 20px;
            letter-spacing: 1.1px;
            text-transform: uppercase;
            margin-bottom: 16px;
            font-family: 'Sora', sans-serif;
        }
        .jobfair-title {
            color: #fff;
            font-size: clamp(1.8rem, 3.4vw, 2.7rem);
            font-weight: 800;
            letter-spacing: -0.8px;
            line-height: 1.15;
            margin-bottom: 14px;
        }
        .jobfair-lead {
            color: rgba(255,255,255,0.78);
            font-size: 1.02rem;
            line-height: 1.75;
            margin-bottom: 24px;
            max-width: 640px;
        }
        .jobfair-alert {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            background: rgba(245,158,11,0.12);
            border: 1px solid rgba(245,158,11,0.28);
            color: #fde68a;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .jobfair-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 28px;
        }
        .jobfair-btn-primary,
        .jobfair-btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 22px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            font-family: 'Sora', sans-serif;
            font-size: 0.92rem;
            transition: all 0.25s ease;
        }
        .jobfair-btn-primary {
            background: var(--gold);
            color: var(--navy);
        }
        .jobfair-btn-primary:hover {
            background: var(--gold-light);
            color: var(--navy);
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(245,158,11,0.28);
        }
        .jobfair-btn-secondary {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255,255,255,0.35);
        }
        .jobfair-btn-secondary:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border-color: #fff;
        }
        .jobfair-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .jobfair-stat {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 18px;
            padding: 18px 16px;
            text-align: center;
        }
        .jobfair-stat strong {
            display: block;
            color: var(--gold);
            font-family: 'Sora', sans-serif;
            font-size: 1.7rem;
            line-height: 1;
            margin-bottom: 6px;
        }
        .jobfair-stat span {
            color: rgba(255,255,255,0.72);
            font-size: 0.8rem;
            font-weight: 500;
        }
        @media (max-width: 991px) {
            .jobfair-panel { padding: 28px 22px; }
            .jobfair-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        /* ===== MOCK TEST PORTAL ===== */
        .mocktest-section {
            padding: 72px 0 88px;
            background: var(--cream);
            border-top: 1px solid var(--border);
        }
        .mocktest-panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 38px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
        }
        .mocktest-eyebrow {
            display: inline-block;
            background: rgba(26, 86, 219, 0.08);
            border: 1px solid rgba(26, 86, 219, 0.18);
            color: var(--blue);
            font-size: 0.74rem;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 20px;
            letter-spacing: 1.1px;
            text-transform: uppercase;
            margin-bottom: 16px;
            font-family: 'Sora', sans-serif;
        }
        .mocktest-title {
            color: var(--navy);
            font-size: clamp(1.7rem, 3vw, 2.4rem);
            font-weight: 800;
            letter-spacing: -0.7px;
            line-height: 1.15;
            margin-bottom: 14px;
        }
        .mocktest-lead {
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.75;
            margin-bottom: 22px;
            max-width: 700px;
        }
        .mocktest-features {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        .mocktest-feature {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            background: var(--cream);
            border-radius: 14px;
            padding: 14px;
            font-size: 0.88rem;
            color: var(--text);
            line-height: 1.5;
        }
        .mocktest-feature i {
            color: var(--blue);
            margin-top: 2px;
        }
        .mocktest-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .mocktest-btn-primary,
        .mocktest-btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 22px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            font-family: 'Sora', sans-serif;
            font-size: 0.92rem;
            transition: all 0.25s ease;
        }
        .mocktest-btn-primary {
            background: var(--blue);
            color: #fff;
        }
        .mocktest-btn-primary:hover {
            background: var(--blue-light);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(26, 86, 219, 0.22);
        }
        .mocktest-btn-secondary {
            background: #fff;
            color: var(--navy);
            border: 1px solid var(--border);
        }
        .mocktest-btn-secondary:hover {
            border-color: var(--blue);
            color: var(--blue);
        }
        .mocktest-stats {
            display: grid;
            gap: 12px;
        }
        .mocktest-stat {
            background: linear-gradient(135deg, var(--navy) 0%, #163a6b 100%);
            border-radius: 18px;
            padding: 18px 16px;
            text-align: center;
        }
        .mocktest-stat strong {
            display: block;
            color: var(--gold);
            font-family: 'Sora', sans-serif;
            font-size: 1.55rem;
            line-height: 1;
            margin-bottom: 6px;
        }
        .mocktest-stat span {
            color: rgba(255,255,255,0.78);
            font-size: 0.8rem;
            font-weight: 500;
        }
        @media (max-width: 767px) {
            .mocktest-panel { padding: 26px 20px; }
            .mocktest-features { grid-template-columns: 1fr; }
        }

        /* ===== INFO CARDS (DETAILED) ===== */
        .info-section { padding: 90px 0; background: var(--cream); }
        .info-card {
            background: #fff;
            border-radius: 24px;
            padding: 40px 36px;
            height: 100%;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .info-card::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--navy) 0%, var(--blue) 100%);
            border-radius: 0 0 24px 24px;
        }
        .info-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .info-icon-box {
            width: 68px; height: 68px;
            background: var(--navy);
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 24px;
        }
        .info-icon-box i { font-size: 1.8rem; color: var(--gold); }
        .info-card h4 {
            font-family: 'Sora', sans-serif;
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--navy);
            margin-bottom: 14px;
        }
        .info-card p { color: var(--muted); line-height: 1.75; font-size: 0.92rem; margin-bottom: 20px; }
        .check-list { list-style: none; padding: 0; margin: 0; }
        .check-list li {
            display: flex; align-items: center; gap: 10px;
            font-size: 0.9rem;
            padding: 9px 0;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text);
        }
        .check-list li:last-child { border-bottom: none; }
        .check-list i { color: #22c55e; font-size: 0.85rem; flex-shrink: 0; }

        /* ===== QUICK LINKS ===== */
        .quick-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 22px;
        }
        .quick-btn {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 10px;
            color: var(--navy);
            font-weight: 600;
            font-size: 0.82rem;
            font-family: 'Sora', sans-serif;
            text-decoration: none;
            transition: all 0.25s;
        }
        .quick-btn i { font-size: 1.2rem; color: var(--blue); }
        .quick-btn:hover {
            background: var(--navy);
            color: var(--gold);
            border-color: var(--navy);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(10,22,40,0.2);
        }
        .quick-btn:hover i { color: var(--gold); }

        /* ===== ANNOUNCEMENTS ===== */
        .announcements-section { padding: 80px 0; background: var(--navy); }
        .announcements-section .section-title { color: #fff; }
        .announcements-section .section-eyebrow { color: var(--gold-light); }
        .announce-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 28px 24px;
            height: 100%;
            transition: all 0.3s;
        }
        .announce-card:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-4px);
        }
        .announce-type {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 14px;
        }
        .type-info { background: rgba(59,130,246,0.2); color: #93c5fd; }
        .type-success { background: rgba(34,197,94,0.2); color: #86efac; }
        .type-warning { background: rgba(245,158,11,0.14); color: #f59e0b; border: 1px solid rgba(245,158,11,0.35); }
        .type-danger { background: rgba(239,68,68,0.2); color: #fca5a5; }
        .announce-card h6 {
            color: #fff;
            font-family: 'Sora', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        .announce-card p { color: rgba(255,255,255,0.62); font-size: 0.88rem; line-height: 1.6; margin: 0; }
        .announce-card .date-tag {
            color: rgba(255,255,255,0.38);
            font-size: 0.78rem;
            margin-top: 14px;
            display: block;
        }

        /* ===== NEWS CARDS ===== */
        .news-section { padding: 80px 0; position: relative; overflow: hidden; }
        .news-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -15%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(26,86,219,0.08) 0%, transparent 70%);
            pointer-events: none;
        }
        .news-container { position: relative; z-index: 1; }
        .news-card {
            background: #fff;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 16px;
            overflow: hidden;
            height: 100%;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.320, 1);
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .news-card:hover {
            box-shadow: 0 20px 48px rgba(0,0,0,0.12);
            transform: translateY(-8px);
            border-color: rgba(26,86,219,0.2);
        }
        .news-card-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #e8f0fe 0%, #f0f4ff 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: rgba(26,86,219,0.2);
        }
        .news-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .news-card-body {
            padding: 24px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .news-card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .news-category {
            display: inline-block;
            background: linear-gradient(135deg, #1a56db 0%, #1e40af 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .news-date {
            font-size: 0.85rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .news-featured-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 4px 12px rgba(245,158,11,0.3);
            z-index: 10;
        }
        .news-card-title {
            font-family: 'Sora', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 10px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .news-card-excerpt {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 16px;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .news-read-more {
            align-self: flex-start;
            color: #1a56db;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        .news-read-more:hover {
            color: #1e40af;
            gap: 10px;
        }
        .news-card:hover .news-read-more {
            color: #1a56db;
        }

        /* ===== DYNAMIC SECTIONS ===== */
        .dynamic-banner { padding: 80px 0; background: linear-gradient(135deg, #e8f0fe 0%, #fafaf8 100%); }
        .dynamic-banner h2 { font-family: 'Sora', sans-serif; font-weight: 800; color: var(--navy); }
        .dynamic-course { padding: 80px 0; background: #fff; }
        .course-card {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 28px;
            height: 100%;
            transition: all 0.3s;
        }
        .course-card:hover { transform: translateY(-6px); box-shadow: 0 16px 32px rgba(0,0,0,0.1); }
        .course-card h5 { font-family: 'Sora', sans-serif; font-weight: 700; color: var(--navy); margin-bottom: 10px; }
        .course-icon { font-size: 2rem; color: var(--blue); margin-bottom: 16px; }

        /* ===== FOOTER ===== */
        footer {
            background: #050e1a;
            color: rgba(255,255,255,0.62);
            font-size: 0.9rem;
        }
        .footer-main { padding: 70px 0 50px; }
        .footer-brand { margin-bottom: 24px; }
        .footer-brand .brand-name {
            font-family: 'Sora', sans-serif;
            font-weight: 800;
            font-size: 1.4rem;
            color: #fff;
            display: block;
            margin-bottom: 4px;
        }
        .footer-brand .brand-sub { font-size: 0.8rem; color: rgba(255,255,255,0.45); }
        .footer-brand p { color: rgba(255,255,255,0.5); font-size: 0.85rem; line-height: 1.7; margin-top: 14px; }
        .footer-contact-item {
            display: flex; align-items: flex-start; gap: 10px;
            margin-bottom: 12px; font-size: 0.85rem;
        }
        .footer-contact-item i { color: var(--gold); margin-top: 2px; flex-shrink: 0; }
        footer h5 {
            font-family: 'Sora', sans-serif;
            font-weight: 700;
            font-size: 0.92rem;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 22px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        footer a {
            color: rgba(255,255,255,0.55);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 0;
            font-size: 0.88rem;
            transition: all 0.2s;
        }
        footer a:hover { color: var(--gold); padding-left: 4px; }
        footer a i { font-size: 0.65rem; }
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.06);
            padding: 20px 0;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.3);
        }
        .footer-bottom a { color: rgba(255,255,255,0.4); display: inline; padding: 0; font-size: 0.82rem; }
        .footer-bottom a:hover { color: var(--gold); padding-left: 0; }
        .footer-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.4);
        }
        .footer-badge span { color: var(--gold); }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp 0.7s ease forwards; }
        .fade-up-delay-1 { animation-delay: 0.1s; opacity: 0; animation-fill-mode: forwards; }
        .fade-up-delay-2 { animation-delay: 0.2s; opacity: 0; animation-fill-mode: forwards; }
        .fade-up-delay-3 { animation-delay: 0.35s; opacity: 0; animation-fill-mode: forwards; }

        /* ===== TYPING EFFECT ===== */
        .hero-title {
            font-size: clamp(2.4rem, 5vw, 4rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.15;
            letter-spacing: -1.5px;
            margin-bottom: 20px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
        .hero-title em {
            font-style: normal;
            color: var(--gold);
        }
        .typing-text {
            display: block;
            color: #fff;
            font-family: 'Sora', sans-serif;
            font-weight: 800;
            line-height: 1.15;
            min-height: 1.2em;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        #typing-line2 {
            display: block;
        }
        
        .typing-cursor {
            display: inline-block;
            width: 3px;
            height: 1em;
            background-color: var(--gold);
            margin-left: 4px;
            animation: blink 0.8s infinite;
            vertical-align: middle;
        }
        
        @keyframes blink {
            0%, 49% { opacity: 1; }
            50%, 100% { opacity: 0; }
        }

        /* ===== MOBILE ===== */
        @media (max-width: 768px) {
            .hero-section { height: 70vh; }
            .hero-stats { display: none; }
            .hero-carousel .carousel-indicators { bottom: 16px; }
            .top-bar .inst-name-hi { display: none; }
            .info-card { padding: 28px 22px; }
            .quick-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ===== TOP BAR ===== -->
<div class="top-bar">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex align-items-center gap-3">
                <img src="<?php echo APP_URL . '/' . $theme_logo; ?>" alt="NIELIT Logo">
                <div>
                    <div class="inst-name-hi">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर</div>
                    <div class="inst-name-en">National Institute of Electronics & Information Technology, Bhubaneswar</div>
                </div>
            </div>
            <div class="col-md-4 d-flex justify-content-md-end justify-content-center align-items-center gap-3 mt-2 mt-md-0">
                <div class="text-end d-none d-md-block">
                    <div class="ministry-badge">Ministry of Electronics & IT &nbsp; | &nbsp; Govt. of India</div>
                </div>
                <img src="<?php echo APP_URL; ?>/assets/images/National-Emblem.png" alt="Gov India" style="height: 48px;">
            </div>
        </div>
    </div>
</div>

<!-- ===== NAVBAR ===== -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="<?php echo relative_url(); ?>">
            <i class="fas fa-university me-2" style="color:var(--gold);"></i> NIELIT <span>Bhubaneswar</span>
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

<!-- ===== NOTICE TICKER ===== -->
<div class="notice-bar">
    <div class="notice-content">
        <span class="notice-label">NOTICE</span>
        Admissions Open! NIELIT Bhubaneswar offers NSQF-aligned courses with modern facilities. Visit our Balasore Extension Center today. &nbsp;&nbsp;&nbsp; |&nbsp;&nbsp;&nbsp;
        <span class="notice-label">NEW</span>
        Online Registrations are now live — Apply before the deadline!
    </div>
</div>

<!-- ===== HERO ===== -->
<section class="hero-section">
    <div id="heroCarousel" class="carousel slide carousel-fade hero-carousel" data-bs-ride="carousel" data-bs-interval="3000" data-bs-wrap="true" data-bs-touch="true">
        <div class="carousel-inner">
            <?php
            // Load all banner images from assets/images/banners (including subfolders)
            $banner_dir = __DIR__ . '/assets/images/banners';
            $banner_files = [];
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
            if (is_dir($banner_dir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($banner_dir, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file_info) {
                    if (!$file_info->isFile()) {
                        continue;
                    }
                    $ext = strtolower($file_info->getExtension());
                    if (in_array($ext, $allowed_extensions, true)) {
                        $banner_files[] = $file_info->getPathname();
                    }
                }
                natsort($banner_files);
                $banner_files = array_values($banner_files);
            }

            // Build encoded relative URLs so names with spaces/special chars work correctly
            $banner_urls = [];
            foreach ($banner_files as $file_path) {
                $relative_path = str_replace('\\', '/', substr($file_path, strlen(__DIR__) + 1));
                $banner_urls[] = implode('/', array_map('rawurlencode', explode('/', $relative_path)));
            }

            // Fallback slide (inline SVG) if no local banner files exist
            if (empty($banner_urls)) {
                $fallback_svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1920 900'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0%' stop-color='#0a1628'/><stop offset='100%' stop-color='#112240'/></linearGradient></defs><rect width='1920' height='900' fill='url(#g)'/><text x='50%' y='50%' fill='#fcd34d' text-anchor='middle' font-family='Arial,sans-serif' font-size='72' font-weight='700'>NIELIT Bhubaneswar</text></svg>";
                $banner_urls[] = 'data:image/svg+xml,' . rawurlencode($fallback_svg);
            }
            ?>
            <?php foreach ($banner_urls as $i => $url): ?>
            <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>">
                <img src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" alt="NIELIT Banner <?php echo $i + 1; ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($banner_urls) > 1): ?>
        <div class="carousel-indicators">
            <?php foreach ($banner_urls as $i => $url): ?>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?php echo $i; ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>" aria-current="<?php echo $i === 0 ? 'true' : 'false'; ?>" aria-label="Slide <?php echo $i + 1; ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>

    <div class="hero-overlay">
        <div class="container">
            <div class="hero-content">
                <div class="hero-eyebrow fade-up">Ministry of Electronics & IT · Est. 2021</div>
                <h1 class="hero-title fade-up fade-up-delay-1">
                    <div style="display: flex; align-items: flex-start; gap: 4px;">
                        <span id="typing-line1" class="typing-text"></span><span id="cursor1" class="typing-cursor"></span>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 4px; opacity: 0; transition: opacity 0.3s;" id="line2-container">
                        <span id="typing-line2" class="typing-text"></span><span id="cursor2" class="typing-cursor"></span>
                    </div>
                </h1>
                <p class="hero-sub fade-up fade-up-delay-2">
                    NIELIT Bhubaneswar — your gateway to NSQF-aligned technology education across Odisha and Chhattisgarh. Skills that power India's future.
                </p>
                <div class="hero-btns fade-up fade-up-delay-3">
                    <a href="<?php echo relative_url('public/courses'); ?>" class="btn-hero-primary">
                        Explore Courses <i class="fas fa-arrow-right fa-sm"></i>
                    </a>
                    <a href="<?php echo relative_url('student/login'); ?>" class="btn-hero-outline">
                        Student Portal
                    </a>
                    <a href="<?php echo htmlspecialchars($job_fair_portal_url); ?>" class="btn-hero-outline" target="_blank" rel="noopener">
                        Job Fair Portal
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="hero-stats">
        <div class="container">
            <div class="row g-0">
                <div class="col-3 hero-stat">
                    <span class="number">15+</span>
                    <span class="label">Courses Offered</span>
                </div>
                <div class="col-3 hero-stat">
                    <span class="number">2</span>
                    <span class="label">Centers Active</span>
                </div>
                <div class="col-3 hero-stat">
                    <span class="number">5000+</span>
                    <span class="label">Students Trained</span>
                </div>
                <div class="col-3 hero-stat">
                    <span class="number">100%</span>
                    <span class="label">Govt. Certified</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== WELCOME STRIP ===== -->
<section class="welcome-strip">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="section-eyebrow">Welcome to NIELIT Bhubaneswar</span>
                <h2 class="section-title mb-4">Excellence in Technology Education Since 2021.</h2>
                <p>We are a premier autonomous scientific society under MeitY, Government of India — dedicated to developing human resources in Information, Electronics, and Communication Technology (IECT) through industry-aligned programs.</p>
            </div>
            <div class="col-lg-6">
                <div>
                    <a class="stat-pill" href="https://www.google.com/maps/search/?api=1&query=OCAC+Tower+Bhubaneswar" target="_blank" rel="noopener" title="Open OCAC Tower in Google Maps" role="link">
                        <i class="fas fa-map-marker-alt"></i> OCAC Tower, Bhubaneswar
                    </a>
                    <a class="stat-pill" href="https://www.google.com/maps/search/?api=1&query=Balasore+Extension+Center+Balasore" target="_blank" rel="noopener" title="Open Balasore Extension Center in Google Maps" role="link">
                        <i class="fas fa-map-marker-alt"></i> Balasore Extension Center
                    </a>
                    <div class="stat-pill"><i class="fas fa-clock"></i> Mon–Fri: 09:00 AM – 5:30 PM</div>
                    <div class="stat-pill"><i class="fas fa-phone-alt"></i> 0674-2960354</div>
                    <a class="stat-pill" href="mailto:dir-bbsr@nielit.gov.in" title="Send email to NIELIT Bhubaneswar">
                        <i class="fas fa-envelope"></i> dir-bbsr@nielit.gov.in
                    </a>
                    <a class="stat-pill" href="https://www.nielit.gov.in/content/nsqf-it" target="_blank" rel="noopener" title="NSQF information on NIELIT" role="link">
                        <i class="fas fa-shield-alt"></i> NSQF Aligned Programs
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== JOB FAIR PORTAL ===== -->
<section class="jobfair-section" id="job-fair">
    <div class="container">
        <div class="jobfair-panel">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <span class="jobfair-eyebrow">National Job Fair Initiative</span>
                    <h2 class="jobfair-title">NIELIT Bhubaneswar Job Fair Portal</h2>
                    <p class="jobfair-lead">
                        A centralized government platform for transparent, seamless, and large-scale recruitment drives across NIELIT regional centres. Empowering youth and connecting employers with skilled talent.
                    </p>
                    <div class="jobfair-alert">
                        <i class="fas fa-bullhorn mt-1"></i>
                        <div>
                            Registration is open for upcoming Mega Job Fairs. Candidates can complete profiles and check in at the venue. Recruiters can upload offer letters directly through the portal.
                        </div>
                    </div>
                    <div class="jobfair-actions">
                        <a href="<?php echo htmlspecialchars($job_fair_portal_url); ?>" class="jobfair-btn-primary" target="_blank" rel="noopener">
                            <i class="fas fa-briefcase"></i> Visit Job Fair Portal
                        </a>
                        <a href="<?php echo htmlspecialchars($job_fair_portal_url); ?>" class="jobfair-btn-secondary" target="_blank" rel="noopener">
                            <i class="fas fa-sign-in-alt"></i> Login to Portal
                        </a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="jobfair-stats">
                        <div class="jobfair-stat">
                            <strong>1+</strong>
                            <span>Centers</span>
                        </div>
                        <div class="jobfair-stat">
                            <strong>10+</strong>
                            <span>Youth Enrolled</span>
                        </div>
                        <div class="jobfair-stat">
                            <strong>4+</strong>
                            <span>Corporates</span>
                        </div>
                        <div class="jobfair-stat">
                            <strong>Live</strong>
                            <span>Active Drives</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== MOCK TEST PORTAL ===== -->
<section class="mocktest-section" id="mock-test">
    <div class="container">
        <div class="mocktest-panel">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <span class="mocktest-eyebrow">Exam Preparation</span>
                    <h2 class="mocktest-title">NIELIT Mock Assessment Platform</h2>
                    <p class="mocktest-lead">
                        A secure mock test platform for NIELIT Bhubaneswar candidates and authorized training partners. Practice NSQF-aligned assessments, download admit cards, and build exam readiness before official certification tests.
                    </p>
                    <div class="mocktest-features">
                        <div class="mocktest-feature">
                            <i class="fas fa-user-graduate"></i>
                            <span>Candidate portal for mock exams, admit cards, and digital scorecards</span>
                        </div>
                        <div class="mocktest-feature">
                            <i class="fas fa-school"></i>
                            <span>Training partner dashboard for batch scheduling and exam slots</span>
                        </div>
                        <div class="mocktest-feature">
                            <i class="fas fa-shield-alt"></i>
                            <span>Secure examination environment with transparent evaluation</span>
                        </div>
                        <div class="mocktest-feature">
                            <i class="fas fa-chart-line"></i>
                            <span>Performance analytics to identify weak areas before the real exam</span>
                        </div>
                    </div>
                    <div class="mocktest-actions">
                        <a href="<?php echo htmlspecialchars($mock_test_portal_url); ?>" class="mocktest-btn-primary" target="_blank" rel="noopener">
                            <i class="fas fa-laptop-code"></i> Open Mock Test Portal
                        </a>
                        <a href="<?php echo htmlspecialchars($mock_test_portal_url); ?>" class="mocktest-btn-secondary" target="_blank" rel="noopener">
                            <i class="fas fa-sign-in-alt"></i> Candidate Login
                        </a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="mocktest-stats">
                        <div class="mocktest-stat">
                            <strong>50K+</strong>
                            <span>Candidates</span>
                        </div>
                        <div class="mocktest-stat">
                            <strong>200+</strong>
                            <span>Exam Sessions</span>
                        </div>
                        <div class="mocktest-stat">
                            <strong>99.9%</strong>
                            <span>System Uptime</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== FEATURES SECTION ===== -->
<section class="features-section section-white-pattern">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-eyebrow">What We Offer</span>
            <h2 class="section-title">Built for the Future of Work.</h2>
            <div class="section-divider mx-auto"></div>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="feat-card">
                    <div class="feat-icon-wrap"><i class="fas fa-laptop-code"></i></div>
                    <h5>Skill Development</h5>
                    <p>NSQF-aligned courses to boost employability in the rapidly evolving technology sector.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feat-card">
                    <div class="feat-icon-wrap"><i class="fas fa-map-marked-alt"></i></div>
                    <h5>Regional Scope</h5>
                    <p>Operating extensively across Odisha and Chhattisgarh to reach every aspiring student.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feat-card">
                    <div class="feat-icon-wrap"><i class="fas fa-building"></i></div>
                    <h5>Modern Facilities</h5>
                    <p>State-of-the-art labs, smart classrooms, and conference halls at OCAC Tower.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feat-card">
                    <div class="feat-icon-wrap"><i class="fas fa-network-wired"></i></div>
                    <h5>Balasore Extension</h5>
                    <p>Expanding our footprint to deliver quality education across the Balasore region.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== INFO DETAILED SECTION ===== -->
<section class="info-section section-white-pattern">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-eyebrow">Learn More</span>
            <h2 class="section-title">Everything You Need to Know.</h2>
            <div class="section-divider mx-auto"></div>
        </div>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="info-card">
                    <div class="info-icon-box"><i class="fas fa-university"></i></div>
                    <h4>About NIELIT</h4>
                    <p>An autonomous scientific society under MeitY, Govt. of India — focused on human resource development in IECT through quality education and practical training programs.</p>
                    <ul class="check-list">
                        <li><i class="fas fa-check-circle"></i> Government of India Initiative</li>
                        <li><i class="fas fa-check-circle"></i> NSQF Aligned Programs</li>
                        <li><i class="fas fa-check-circle"></i> Industry-Ready Training</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="info-card">
                    <div class="info-icon-box"><i class="fas fa-bullseye"></i></div>
                    <h4>Our Mission</h4>
                    <p>To empower youth with cutting-edge technology skills, making them industry-ready and contributing to India's digital transformation through quality education and practical training.</p>
                    <ul class="check-list">
                        <li><i class="fas fa-check-circle"></i> Skill Enhancement & Certification</li>
                        <li><i class="fas fa-check-circle"></i> Employment Generation</li>
                        <li><i class="fas fa-check-circle"></i> Digital India Mission Support</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="info-card">
                    <div class="info-icon-box"><i class="fas fa-link"></i></div>
                    <h4>Quick Access</h4>
                    <p>Explore our offerings and start your learning journey. Access courses, register online, and connect with us for queries.</p>
                    <div class="quick-grid">
                        <a href="<?php echo relative_url('public/courses'); ?>" class="quick-btn">
                            <i class="fas fa-book"></i> View Courses
                        </a>
                        <a href="<?php echo relative_url('student/login'); ?>" class="quick-btn">
                            <i class="fas fa-sign-in-alt"></i> Student Portal
                        </a>
                        <a href="<?php echo relative_url('public/contact'); ?>" class="quick-btn">
                            <i class="fas fa-envelope"></i> Contact Us
                        </a>
                        <a href="<?php echo relative_url('public/news'); ?>" class="quick-btn">
                            <i class="fas fa-newspaper"></i> News & Events
                        </a>
                        <a href="<?php echo htmlspecialchars($job_fair_portal_url); ?>" class="quick-btn" target="_blank" rel="noopener">
                            <i class="fas fa-briefcase"></i> Job Fair Portal
                        </a>
                        <a href="<?php echo htmlspecialchars($mock_test_portal_url); ?>" class="quick-btn" target="_blank" rel="noopener">
                            <i class="fas fa-laptop-code"></i> Mock Test Portal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// ===== DYNAMIC DB CONTENT =====
$has_database_content = !empty($banners) || !empty($announcements_content) || !empty($featured_courses) || !empty($text_blocks) || !empty($image_blocks);

if ($has_database_content):
    if (!empty($banners)):
        foreach ($banners as $banner): ?>
<section class="dynamic-banner">
    <div class="container text-center">
        <h2 class="fw-bold mb-3"><?php echo htmlspecialchars($banner['section_title']); ?></h2>
        <div class="lead text-muted"><?php echo $banner['section_content']; ?></div>
    </div>
</section>
<?php   endforeach;
    endif;

    if (!empty($featured_courses)): ?>
<section class="dynamic-course section-white-pattern">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-eyebrow">Courses</span>
            <h2 class="section-title">Featured Courses.</h2>
            <div class="section-divider mx-auto"></div>
        </div>
        <div class="row g-4">
            <?php foreach ($featured_courses as $course): ?>
            <div class="col-md-6 col-lg-4">
                <div class="course-card">
                    <div class="course-icon"><i class="fas fa-graduation-cap"></i></div>
                    <h5><?php echo htmlspecialchars($course['section_title']); ?></h5>
                    <div class="text-muted small"><?php echo $course['section_content']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; endif; ?>

<!-- ===== LATEST NEWS SECTION ===== -->
<?php if (!empty($news_items)): ?>
<section class="news-section section-white-pattern">
    <div class="container news-container">
        <div class="text-center mb-5">
            <span class="section-eyebrow">Stay Informed</span>
            <h2 class="section-title">Latest News & Updates.</h2>
            <div class="section-divider mx-auto"></div>
        </div>
        
        <div class="row g-4">
            <?php foreach ($news_items as $index => $news): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="news-card">
                        <?php if ($news['is_featured']): ?>
                            <div class="news-featured-badge">
                                <i class="fas fa-star"></i> Featured
                            </div>
                        <?php endif; ?>
                        
                        <div class="news-card-image">
                            <?php if (!empty($news['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>">
                            <?php else: ?>
                                <i class="fas fa-newspaper"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="news-card-body">
                            <div class="news-card-meta">
                                <?php if (!empty($news['category'])): ?>
                                    <span class="news-category"><?php echo htmlspecialchars($news['category']); ?></span>
                                <?php endif; ?>
                                <span class="news-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('M d, Y', strtotime($news['created_at'])); ?>
                                </span>
                            </div>
                            
                            <h3 class="news-card-title"><?php echo htmlspecialchars($news['title']); ?></h3>
                            
                            <p class="news-card-excerpt">
                                <?php echo htmlspecialchars(mb_substr(strip_tags($news['content']), 0, 120)); ?>
                            </p>
                            
                            <a href="<?php echo relative_url('public/news'); ?>#news-<?php echo $news['id']; ?>" class="news-read-more">
                                Read More <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-5">
            <a href="<?php echo relative_url('public/news'); ?>" class="btn btn-primary btn-lg" style="border-radius: 10px; padding: 12px 32px; font-weight: 600;">
                <i class="fas fa-newspaper me-2"></i>View All News
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== ANNOUNCEMENTS ===== -->
<?php
$announcements_sql = "SELECT * FROM announcements WHERE is_active = 1 AND (target_audience = 'all' OR target_audience = 'students') ORDER BY created_at DESC LIMIT 3";
$announcements_result = $conn->query($announcements_sql);

if ($announcements_result && $announcements_result->num_rows > 0): ?>
<section class="announcements-section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-eyebrow" style="color:var(--gold-light);">Latest Updates</span>
            <h2 class="section-title">Announcements.</h2>
            <div class="section-divider mx-auto"></div>
        </div>
        <div class="row g-4">
            <?php
            $type_class = ['info'=>'type-info','success'=>'type-success','warning'=>'type-warning','danger'=>'type-danger'];
            $type_icon  = ['info'=>'fa-info-circle','success'=>'fa-check-circle','warning'=>'fa-exclamation-triangle','danger'=>'fa-exclamation-circle'];
            while ($ann = $announcements_result->fetch_assoc()):
                $t = $ann['type'];
            ?>
            <div class="col-md-4">
                <div class="announce-card">
                    <div class="announce-type <?php echo $type_class[$t] ?? 'type-info'; ?>">
                        <i class="fas <?php echo $type_icon[$t] ?? 'fa-info-circle'; ?>"></i>
                        <?php echo ucfirst($t); ?>
                    </div>
                    <h6><?php echo htmlspecialchars($ann['title']); ?></h6>
                    <p><?php echo nl2br(htmlspecialchars($ann['message'])); ?></p>
                    <span class="date-tag"><i class="fas fa-clock me-1"></i><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></span>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== FOOTER ===== -->
<footer>
    <div class="footer-main">
        <div class="container">
            <div class="row gy-5">
                <div class="col-lg-4">
                    <div class="footer-brand">
                        <span class="brand-name">NIELIT Bhubaneswar</span>
                        <span class="brand-sub">National Institute of Electronics & Information Technology</span>
                        <p>An autonomous scientific society under the Ministry of Electronics & IT, Government of India — dedicated to technology education and skill development.</p>
                    </div>
                    <div class="footer-contact-item">
                        <i class="fas fa-map-marker-alt mt-1"></i>
                        <span>OCAC Tower, Acharya Vihar, Bhubaneswar, Odisha</span>
                    </div>
                    <div class="footer-contact-item">
                        <i class="fas fa-phone-alt"></i>
                        <span>0674-2960354</span>
                    </div>
                    <div class="footer-contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>dir-bbsr@nielit.gov.in</span>
                    </div>
                    <div class="footer-contact-item">
                        <i class="fas fa-clock"></i>
                        <span>Mon–Fri: 09:00 AM – 5:30 PM</span>
                    </div>
                </div>

                <div class="col-6 col-lg-2">
                    <h5>Important Links</h5>
                    <a href="https://india.gov.in/" target="_blank"><i class="fas fa-chevron-right"></i> National Portal</a>
                    <a href="https://www.mygov.in/" target="_blank"><i class="fas fa-chevron-right"></i> MyGov</a>
                    <a href="https://rtionline.gov.in/" target="_blank"><i class="fas fa-chevron-right"></i> RTI Online</a>
                    <a href="http://meity.gov.in/" target="_blank"><i class="fas fa-chevron-right"></i> MeitY</a>
                    <a href="https://www.nielit.gov.in/" target="_blank"><i class="fas fa-chevron-right"></i> NIELIT HQ</a>
                </div>

                <div class="col-6 col-lg-2">
                    <h5>Quick Links</h5>
                    <a href="#"><i class="fas fa-chevron-right"></i> About Us</a>
                    <a href="<?php echo relative_url('public/courses'); ?>"><i class="fas fa-chevron-right"></i> Courses</a>
                    <a href="<?php echo relative_url('public/news'); ?>"><i class="fas fa-chevron-right"></i> News & Events</a>
                    <a href="<?php echo relative_url('public/contact'); ?>"><i class="fas fa-chevron-right"></i> Contact Us</a>
                    <a href="<?php echo htmlspecialchars($job_fair_portal_url); ?>" target="_blank" rel="noopener"><i class="fas fa-chevron-right"></i> Job Fair Portal</a>
                    <a href="<?php echo htmlspecialchars($mock_test_portal_url); ?>" target="_blank" rel="noopener"><i class="fas fa-chevron-right"></i> Mock Test Portal</a>
                    <a href="#"><i class="fas fa-chevron-right"></i> Privacy Policy</a>
                    <a href="#"><i class="fas fa-chevron-right"></i> Terms & Conditions</a>
                </div>

                <div class="col-lg-4">
                    <h5>Student Access</h5>
                    <a href="<?php echo relative_url('student/login'); ?>"><i class="fas fa-chevron-right"></i> Student Login</a>
                    <a href="<?php echo htmlspecialchars($mock_test_portal_url); ?>" target="_blank" rel="noopener"><i class="fas fa-chevron-right"></i> Mock Test Portal</a>
                    <a href="https://student.nielit.gov.in/" target="_blank" rel="noopener"><i class="fas fa-chevron-right"></i> Admit Card</a>
                    <a href="https://student.nielit.gov.in/" target="_blank" rel="noopener"><i class="fas fa-chevron-right"></i> Results</a>
                    <a href="#"><i class="fas fa-chevron-right"></i> Certificate Verification</a>
                    <div style="margin-top: 24px;">
                        <div class="footer-badge"><span>●</span> Balasore Extension Active</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>© 2025 NIELIT Bhubaneswar. All Rights Reserved.</span>
            <span>Designed & Developed by <a href="#">NIELIT Team</a></span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Scroll animation observer
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                entry.target.style.transition = 'all 0.6s ease';
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.feat-card, .info-card, .course-card, .announce-card, .news-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(24px)';
        observer.observe(el);
    });

    // Ensure hero carousel controls, keyboard navigation, and auto-slide work consistently
    const heroCarouselEl = document.getElementById('heroCarousel');
    if (heroCarouselEl && window.bootstrap && bootstrap.Carousel) {
        const heroCarousel = bootstrap.Carousel.getOrCreateInstance(heroCarouselEl, {
            interval: 3000,
            ride: 'carousel',
            pause: false,
            touch: true,
            keyboard: true,
            wrap: true
        });
        heroCarousel.cycle();
    }

    // Typing Effect Animation - Multiple Pickup Lines
    const line1El = document.getElementById('typing-line1');
    const line2El = document.getElementById('typing-line2');
    const cursor1 = document.getElementById('cursor1');
    const cursor2 = document.getElementById('cursor2');
    const line2Container = document.getElementById('line2-container');
    
    // Array of pickup lines
    const pickupLines = [
        { line1: 'Code Tomorrow.', line2: 'Transform Today.' },
        { line1: 'Learn Today.', line2: 'Lead Tomorrow.' },
        { line1: 'Skills Today.', line2: 'Success Tomorrow.' },
        { line1: 'Train Now.', line2: 'Transform Forever.' },
        { line1: 'Build Skills.', line2: 'Change Futures.' },
        { line1: 'Code Smart.', line2: 'Grow Strong.' },
        { line1: 'Start Today.', line2: 'Thrive Tomorrow.' },
        { line1: 'Master Tech.', line2: 'Master Life.' },
        { line1: 'Learn Together.', line2: 'Grow Stronger.' },
        { line1: 'Shape Future.', line2: 'Start Now.' },
        { line1: 'Innovation Starts.', line2: 'Here & Now.' }
    ];
    
    let currentLineSet = 0;
    let line1Index = 0;
    let line2Index = 0;
    let currentLine = 1;
    let isDeleting = false;
    let typingSpeed = 80;
    let pauseTime = 2000;

    function typeEffect() {
        const currentSet = pickupLines[currentLineSet];
        const line1Text = currentSet.line1;
        const line2Text = currentSet.line2;

        if (!isDeleting) {
            // Typing Phase
            if (currentLine === 1) {
                if (line1Index <= line1Text.length) {
                    let displayText = line1Text.substring(0, line1Index);
                    // Highlight last word in gold
                    const words = displayText.split(' ');
                    if (words.length > 0) {
                        words[words.length - 1] = '<em>' + words[words.length - 1] + '</em>';
                        displayText = words.join(' ');
                    }
                    line1El.innerHTML = displayText;
                    
                    if (line1Index === line1Text.length) {
                        // Finished typing line 1, move to line 2
                        currentLine = 2;
                        cursor1.style.display = 'none';
                        line2Container.style.opacity = '1';
                        cursor2.style.display = 'inline-block';
                        line2Index = 0;
                        setTimeout(typeEffect, 500);
                    } else {
                        line1Index++;
                        setTimeout(typeEffect, typingSpeed);
                    }
                }
            } else if (currentLine === 2) {
                if (line2Index <= line2Text.length) {
                    let displayText = line2Text.substring(0, line2Index);
                    // Highlight last word in gold
                    const words = displayText.split(' ');
                    if (words.length > 0) {
                        words[words.length - 1] = '<em>' + words[words.length - 1] + '</em>';
                        displayText = words.join(' ');
                    }
                    line2El.innerHTML = displayText;
                    
                    if (line2Index === line2Text.length) {
                        // Finished typing line 2, pause then delete
                        setTimeout(() => {
                            isDeleting = true;
                            typeEffect();
                        }, pauseTime);
                    } else {
                        line2Index++;
                        setTimeout(typeEffect, typingSpeed);
                    }
                }
            }
        } else {
            // Deleting Phase
            if (currentLine === 2) {
                if (line2Index > 0) {
                    line2Index--;
                    let displayText = line2Text.substring(0, line2Index);
                    const words = displayText.split(' ');
                    if (words.length > 0) {
                        words[words.length - 1] = '<em>' + words[words.length - 1] + '</em>';
                        displayText = words.join(' ');
                    }
                    line2El.innerHTML = displayText;
                    setTimeout(typeEffect, 50);
                } else {
                    // Move to line 1 delete
                    currentLine = 1;
                    cursor1.style.display = 'inline-block';
                    cursor2.style.display = 'none';
                    line2Container.style.opacity = '0';
                    line1Index = line1Text.length;
                    setTimeout(typeEffect, 50);
                }
            } else if (currentLine === 1) {
                if (line1Index > 0) {
                    line1Index--;
                    let displayText = line1Text.substring(0, line1Index);
                    const words = displayText.split(' ');
                    if (words.length > 0) {
                        words[words.length - 1] = '<em>' + words[words.length - 1] + '</em>';
                        displayText = words.join(' ');
                    }
                    line1El.innerHTML = displayText;
                    setTimeout(typeEffect, 50);
                } else {
                    // Move to next pickup line
                    currentLineSet = (currentLineSet + 1) % pickupLines.length;
                    isDeleting = false;
                    currentLine = 1;
                    line1Index = 0;
                    line2Index = 0;
                    line1El.innerHTML = '';
                    line2El.innerHTML = '';
                    setTimeout(typeEffect, 500);
                }
            }
        }
    }

    // Start typing effect when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            typeEffect();
        });
    } else {
        typeEffect();
    }
</script>
</body>
</html>