<?php
// Include maintenance check FIRST
require_once __DIR__ . '/../includes/maintenance_check.php';

// Include the database connection
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';

// Load active theme
$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);

// Get centre filter from URL parameter
$centre_filter = isset($_GET['centre']) ? intval($_GET['centre']) : 0;

// Fetch active centres for filter dropdown
$sql_centres = "SELECT id, name FROM centres WHERE is_active = 1 ORDER BY name ASC";
$result_centres = $conn->query($sql_centres);

// Build WHERE clause for centre filter
$centre_condition = "";
if ($centre_filter > 0) {
    $centre_condition = " AND courses.centre_id = " . $centre_filter;
}

// Fetch courses for the 6 required categories - ONLY SHOW PUBLISHED COURSES
// Courses with link_published = 1 OR NULL (for backward compatibility)
// Apply centre filter if selected
// JOIN with centres table to get centre information

// 1. Degree / Diploma / PG
$sql_degree_pg = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                  FROM courses 
                  LEFT JOIN centres ON courses.centre_id = centres.id 
                  WHERE courses.category = 'Degree / Diploma / PG' AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// 2. Skill Based (Long Term) Courses > 500 hrs
$sql_skill_long = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                   FROM courses 
                   LEFT JOIN centres ON courses.centre_id = centres.id 
                   WHERE courses.category = 'Skill Based (Long Term) >500 hrs' AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// 3. Skill Based (Short Term) Courses >90 hrs to <=500 hrs
$sql_skill_short = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                    FROM courses 
                    LEFT JOIN centres ON courses.centre_id = centres.id 
                    WHERE courses.category = 'Skill Based (Short Term) 90-500 hrs' AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// 4. Short Term Courses / Digital Competency Courses <= 90 hours
$sql_short_digital = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                      FROM courses 
                      LEFT JOIN centres ON courses.centre_id = centres.id 
                      WHERE courses.category = 'Short Term / Digital Competency <=90 hrs' AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// 5. NIELIT HQ's Digital Literacy Courses (CCC / ECC / CCCP / BCC / ACC)
$sql_digital_lit = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                    FROM courses 
                    LEFT JOIN centres ON courses.centre_id = centres.id 
                    WHERE courses.category = 'NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)' AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// 6. Internship Program (now handled as sub-category)
$sql_internship = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                   FROM courses 
                   LEFT JOIN centres ON courses.centre_id = centres.id 
                   WHERE courses.category = 'Internship Program' 
                   AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// Execute only the 6 required queries
$result_degree_pg = $conn->query($sql_degree_pg);
$result_skill_long = $conn->query($sql_skill_long);
$result_skill_short = $conn->query($sql_skill_short);
$result_short_digital = $conn->query($sql_short_digital);
$result_digital_lit = $conn->query($sql_digital_lit);
$result_internship = $conn->query($sql_internship);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses Offered - NIELIT Bhubaneswar</title>
    
    <?php injectThemeCSS($active_theme); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/assets/css/public-theme.css" rel="stylesheet">
    <link rel="icon" href="<?php echo APP_URL . '/' . getThemeFavicon($active_theme); ?>" type="image/x-icon">

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

        /* Compact Collapsible Course Cards */
        .course-card {
            margin-bottom: 1rem;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .course-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }

        .course-card-header {
            background: linear-gradient(135deg, #0a1628 0%, #112240 100%) !important;
            border-bottom: 1px solid rgba(245,158,11,0.25);
            padding: 1rem 1.25rem;
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .course-card-header:hover {
            background: linear-gradient(135deg, #112240 0%, #1a3a5a 100%) !important;
        }

        .course-card-header h4 {
            color: #fff !important;
            font-size: 1.1rem;
            margin: 0;
            flex: 1;
        }

        .course-card-toggle {
            color: var(--gold);
            font-size: 1.2rem;
            transition: transform 0.3s ease;
            margin-left: 1rem;
        }

        .course-card.expanded .course-card-toggle {
            transform: rotate(180deg);
        }

        .course-card-header .enrollment-status-badge {
            margin-left: 1rem;
        }

        .course-card-header .enrollment-status-badge .status-badge {
            font-size: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .course-card-header .enrollment-status-badge .status-badge.status-ongoing {
            background: linear-gradient(135deg, #d1fae5 0%, #bbf7d0 100%);
            color: #0a1628;
            border-color: rgba(10, 22, 40, 0.08);
        }

        .course-card-header .enrollment-status-badge .status-badge.status-closed {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #0a1628;
            border-color: rgba(10, 22, 40, 0.08);
        }

        .course-card-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.4s ease;
            padding: 0 1.25rem;
        }

        .course-card.expanded .course-card-body {
            max-height: 2000px;
            padding: 1.25rem;
        }

        .course-card-footer {
            background: #f8fafc !important;
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.4s ease;
        }

        .course-card.expanded .course-card-footer {
            max-height: 200px;
            padding: 1rem 1.25rem;
        }

        .section-header {
            border-left-color: var(--gold) !important;
        }

        .section-header i {
            color: var(--gold) !important;
        }

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

        /* Compact header info */
        .course-header-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .course-quick-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.8);
        }

        .course-quick-info i {
            color: var(--gold);
        }

        /* Inline closing date next to status badge */
        .enrollment-status-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-left: 1rem;
        }

        .enrollment-closing-date {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.75);
            white-space: nowrap;
        }

        .enrollment-closing-date i {
            color: var(--gold);
            margin-right: 2px;
        }
    </style>
</head>
<body>

<!-- Top Bar (Government Header) -->
<div class="top-bar">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex align-items-center justify-content-md-start justify-content-center text-header-group">
                <img src="<?php echo APP_URL . '/' . $theme_logo; ?>" alt="NIELIT Logo" class="me-3" style="height: 50px;">
                <div>
                    <div class="fw-bold d-none d-sm-block" style="color: var(--blue); font-size: 0.82rem; font-family: 'Sora', sans-serif;">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर</div>
                    <div class="fw-bold" style="color: var(--navy); font-size: 0.95rem; font-family: 'Sora', sans-serif;">National Institute of Electronics & Information Technology, Bhubaneswar</div>
                </div>
            </div>
            <div class="col-md-4 d-flex justify-content-md-end justify-content-center gov-logos">
                <div class="text-end me-3 d-none d-lg-block">
                    <small class="d-block fw-bold" style="color: var(--navy);">Ministry of Electronics & IT</small>
                    <small class="d-block" style="color: var(--muted);">Government of India</small>
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
                <li class="nav-item"><a class="nav-link active" href="<?php echo APP_URL; ?>/public/courses.php">Courses Offered</a></li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Student Zone</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/student/login.php">Student Portal</a></li>
                    </ul>
                </li>

                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/public/management.php">Management</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/public/news.php">News</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/public/contact.php">Contact</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Notice Ticker -->
<div class="notice-bar">
    <div class="notice-content">
        <span class="notice-label">NEW</span>
        Admissions Open! Explore our NSQF-aligned courses and internship programs. Apply now for upcoming batches.
    </div>
</div>

<!-- Page Header -->
<section class="py-3" style="background: linear-gradient(135deg, #0a1628 0%, #112240 100%); position: relative; overflow: hidden;">
    <div style="position: absolute; inset: 0; opacity: 0.08; background-image: url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%23ffffff" fill-opacity="0.3"><circle cx="30" cy="30" r="2"/></g></svg>'); background-size: 60px 60px;"></div>
    <div class="container position-relative py-2">
        <div class="text-center mx-auto" style="max-width: 760px;">
            <span class="section-eyebrow" style="color: var(--gold-light); font-size: 0.75rem;">Academic Programs</span>
            <h1 style="color: #fff; font-family: 'Sora', sans-serif; font-size: clamp(1.8rem, 3vw, 2.5rem); font-weight: 800; line-height: 1.1; letter-spacing: -1px; margin-bottom: 8px;">Courses Offered</h1>
            <p style="color: rgba(255,255,255,0.78); font-size: 0.9rem; line-height: 1.4; margin: 0;">
                Explore our NSQF-aligned courses, internship programs, and skill development courses.
            </p>
        </div>
    </div>
</section>

<!-- Filter Section - Compact Horizontal Layout -->
<section class="py-2" style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-3">
                <label style="font-weight: 600; color: #0a1628; margin-bottom: 0; font-size: 0.9rem;">
                    <i class="fas fa-map-marker-alt" style="color: #f59e0b; margin-right: 0.5rem;"></i>
                    Training Centre:
                </label>
            </div>
            <div class="col-md-9">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button class="btn btn-sm <?php echo $centre_filter == 0 ? 'btn-primary' : 'btn-outline-secondary'; ?>" onclick="selectCentre(0)" style="border-radius: 20px; padding: 0.4rem 1rem; font-size: 0.85rem;">
                        <i class="fas fa-globe"></i> All Centres
                    </button>
                    <?php 
                    // Find NIELIT Bhubaneswar centre
                    $bhubaneswar_centre = null;
                    $other_centres = [];
                    
                    if ($result_centres && $result_centres->num_rows > 0) {
                        $result_centres->data_seek(0);
                        while ($centre = $result_centres->fetch_assoc()) {
                            if (stripos($centre['name'], 'bhubaneswar') !== false || stripos($centre['name'], 'bbsr') !== false) {
                                $bhubaneswar_centre = $centre;
                            } else {
                                $other_centres[] = $centre;
                            }
                        }
                    }
                    
                    // Display NIELIT Bhubaneswar first
                    if ($bhubaneswar_centre) {
                        $is_active = ($centre_filter == $bhubaneswar_centre['id']);
                        echo '<button class="btn btn-sm ' . ($is_active ? 'btn-primary' : 'btn-outline-secondary') . '" onclick="selectCentre(' . $bhubaneswar_centre['id'] . ')" style="border-radius: 20px; padding: 0.4rem 1rem; font-size: 0.85rem;">';
                        echo '<i class="fas fa-building"></i> ' . htmlspecialchars($bhubaneswar_centre['name']);
                        echo '</button>';
                    }
                    
                    // Display other centres
                    foreach ($other_centres as $centre) {
                        $is_active = ($centre_filter == $centre['id']);
                        echo '<button class="btn btn-sm ' . ($is_active ? 'btn-primary' : 'btn-outline-secondary') . '" onclick="selectCentre(' . $centre['id'] . ')" style="border-radius: 20px; padding: 0.4rem 1rem; font-size: 0.85rem;">';
                        echo '<i class="fas fa-building"></i> ' . htmlspecialchars($centre['name']);
                        echo '</button>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick Navigation Buttons -->
<section class="py-2" style="background: #fff; border-bottom: 1px solid #e2e8f0;">
    <div class="container">
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="#skill-long" class="btn btn-sm btn-outline-primary" style="border-radius: 20px; font-size: 0.8rem;">
                <i class="fas fa-laptop-code"></i> Skill Based (Long Term) >500 hrs
            </a>
            <a href="#skill-short" class="btn btn-sm btn-outline-primary" style="border-radius: 20px; font-size: 0.8rem;">
                <i class="fas fa-code"></i> Skill Based (Short Term) 90-500 hrs
            </a>
            <a href="#short-digital" class="btn btn-sm btn-outline-primary" style="border-radius: 20px; font-size: 0.8rem;">
                <i class="fas fa-desktop"></i> Short Term / Digital Competency <=90 hrs
            </a>
            <a href="#internship" class="btn btn-sm btn-outline-primary" style="border-radius: 20px; font-size: 0.8rem;">
                <i class="fas fa-briefcase"></i> Internship Program
            </a>
            <a href="#degree-diploma" class="btn btn-sm btn-outline-primary" style="border-radius: 20px; font-size: 0.8rem;">
                <i class="fas fa-graduation-cap"></i> Degree / Diploma / PG
            </a>
            <a href="#digital-literacy" class="btn btn-sm btn-outline-primary" style="border-radius: 20px; font-size: 0.8rem;">
                <i class="fas fa-keyboard"></i> NIELIT HQ Digital Literacy
            </a>
        </div>
    </div>
</section>

<!-- Courses Offered Section -->
                                </div>
                            </div>
                            <div class="stat-item text-center">
                                <div style="font-size: 1.2rem; font-weight: 700; color: #10b981; margin-bottom: 0.25rem;">
                                    <?php 
                                    $centres_result = $conn->query("SELECT COUNT(*) as count FROM centres WHERE is_active = 1");
                                    $centres_count = $centres_result ? $centres_result->fetch_assoc()['count'] : 0;
                                    echo $centres_count;
                                    ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b; line-height: 1.2;">Centres</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



<!-- Courses Offered Section -->
<section class="py-5">
    <div class="container">

        <!-- 1. Skill Based (Long Term) Courses > 500 hrs -->
        <div class="course-section" id="skill-long-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-laptop-code"></i>
                    Skill Based (Long Term) Courses (&gt; 500 hrs)
                </h3>
            </div>
            <?php if ($result_skill_long && $result_skill_long->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while ($row = $result_skill_long->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="course-card" onclick="toggleCourseCard(this)">
                            <div class="course-card-header">
                                <div class="course-header-info">
                                    <h4><?php echo htmlspecialchars($row["course_name"]); ?><?php if (!empty($row["is_nsqf"]) && $row["is_nsqf"]==1): ?> <span class="badge bg-info" style="font-size:0.7rem;">NSQF</span><?php endif; ?></h4>
                                    <div class="course-quick-info">
                                        <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($row["duration"]); ?></span>
                                        <?php if (!empty($row["training_fees"])): ?>
                                        <span><i class="fas fa-rupee-sign"></i> ₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="enrollment-status-badge">
                                    <?php 
                                    $enrollment_status = $row['enrollment_status'] ?? 'ongoing';
                                    $enrollment_closing_date = $row['enrollment_closing_date'] ?? null;
                                    $today = date('Y-m-d');
                                    $is_closed = false;
                                    if ($enrollment_status == 'closed') { $is_closed = true; }
                                    elseif (!empty($enrollment_closing_date) && $today > $enrollment_closing_date) { $is_closed = true; }
                                    if ($is_closed): ?>
                                        <span class="status-badge status-closed"><i class="fas fa-times-circle"></i> Enrollment Closed<?php if (!empty($enrollment_closing_date)): ?><br><small style="color:#92400e;">Closed on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small><?php endif; ?></span>
                                    <?php else: ?>
                                        <span class="status-badge status-ongoing"><i class="fas fa-check-circle"></i> Enrollment Open<?php if (!empty($enrollment_closing_date)): ?><br><small style="color:#065f46;">Closes on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small><?php endif; ?></span>
                                    <?php endif; ?>
                                </div>
                                <i class="fas fa-chevron-down course-card-toggle"></i>
                            </div>
                            <div class="course-card-body <?php echo ($is_closed) ? 'course-disabled' : ''; ?>">
                                <div class="course-info-grid">
                                    <?php if (!empty($row["eligibility"])): ?>
                                    <div class="info-item"><i class="fas fa-user-graduate"></i><div><span class="info-label">Eligibility</span><span class="info-value"><?php echo htmlspecialchars($row["eligibility"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <div class="info-item"><i class="fas fa-clock"></i><div><span class="info-label">Duration</span><span class="info-value"><?php echo htmlspecialchars($row["duration"]); ?></span></div></div>
                                    <?php if (!empty($row["training_fees"])): ?>
                                    <div class="info-item"><i class="fas fa-rupee-sign"></i><div><span class="info-label">Training Fees</span><span class="info-value">₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["start_date"])): ?>
                                    <div class="info-item"><i class="fas fa-calendar-alt"></i><div><span class="info-label">Course Start Date</span><span class="info-value"><?php echo date('d M Y', strtotime($row["start_date"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["end_date"])): ?>
                                    <div class="info-item"><i class="fas fa-calendar-check"></i><div><span class="info-label">Course End Date</span><span class="info-value"><?php echo date('d M Y', strtotime($row["end_date"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["course_coordinator"])): ?>
                                    <div class="info-item"><i class="fas fa-user-tie"></i><div><span class="info-label">Coordinator</span><span class="info-value"><?php echo htmlspecialchars($row["course_coordinator"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["course_description"])): ?>
                                    <div class="info-item" style="grid-column:1/-1;"><i class="fas fa-info-circle"></i><div><span class="info-label">Description</span><span class="info-value"><?php echo nl2br(htmlspecialchars($row["course_description"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_name"])): ?>
                                    <div class="info-item"><i class="fas fa-map-marker-alt"></i><div><span class="info-label">Training Centre</span><span class="info-value"><?php echo htmlspecialchars($row["centre_name"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_city"]) && !empty($row["centre_state"])): ?>
                                    <div class="info-item"><i class="fas fa-location-dot"></i><div><span class="info-label">Location</span><span class="info-value"><?php echo htmlspecialchars($row["centre_city"]) . ', ' . htmlspecialchars($row["centre_state"]); ?></span></div></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="course-card-footer">
                                <?php if (!empty($row["description_url"])): ?><a href="<?php echo htmlspecialchars($row["description_url"]); ?>" target="_blank" class="btn-outline-modern btn-modern"><i class="fas fa-info-circle"></i> View Details</a><?php endif; ?>
                                <?php if (!empty($row["description_pdf"])): ?><a href="<?php echo APP_URL . '/' . htmlspecialchars($row["description_pdf"]); ?>" target="_blank" class="btn-outline-modern btn-modern"><i class="fas fa-file-pdf"></i> Download PDF</a><?php endif; ?>
                                <?php if (!empty($row["apply_link"]) && (!isset($row["link_published"]) || $row["link_published"] == 1)): ?>
                                    <?php if ($is_closed): ?><button class="btn-disabled btn-modern" disabled><i class="fas fa-times-circle"></i> Enrollment Closed</button>
                                    <?php else: ?><a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-success-modern btn-modern"><i class="fas fa-paper-plane"></i> Apply Now</a><?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><h4>No Skill Based (Long Term) Courses Available</h4><p>Please check back later.</p></div>
            <?php endif; ?>
        </div>

        <!-- 2. Skill Based (Short Term) Courses 90-500 hrs -->
        <div class="course-section" id="skill-short-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-code"></i>
                    Skill Based (Short Term) Courses (90-500 hrs)
                </h3>
            </div>
            <?php if ($result_skill_short && $result_skill_short->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while ($row = $result_skill_short->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="course-card" onclick="toggleCourseCard(this)">
                            <div class="course-card-header">
                                <div class="course-header-info">
                                    <h4><?php echo htmlspecialchars($row["course_name"]); ?><?php if (!empty($row["is_nsqf"]) && $row["is_nsqf"]==1): ?> <span class="badge bg-info" style="font-size:0.7rem;">NSQF</span><?php endif; ?></h4>
                                    <div class="course-quick-info">
                                        <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($row["duration"]); ?></span>
                                        <?php if (!empty($row["training_fees"])): ?>
                                        <span><i class="fas fa-rupee-sign"></i> ₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="enrollment-status-badge">
                                    <?php 
                                    $enrollment_status = $row['enrollment_status'] ?? 'ongoing';
                                    $enrollment_closing_date = $row['enrollment_closing_date'] ?? null;
                                    $today = date('Y-m-d');
                                    $is_closed = false;
                                    if ($enrollment_status == 'closed') { $is_closed = true; }
                                    elseif (!empty($enrollment_closing_date) && $today > $enrollment_closing_date) { $is_closed = true; }
                                    if ($is_closed): ?>
                                        <span class="status-badge status-closed"><i class="fas fa-times-circle"></i> Enrollment Closed<?php if (!empty($enrollment_closing_date)): ?><br><small style="color:#92400e;">Closed on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small><?php endif; ?></span>
                                    <?php else: ?>
                                        <span class="status-badge status-ongoing"><i class="fas fa-check-circle"></i> Enrollment Open<?php if (!empty($enrollment_closing_date)): ?><br><small style="color:#065f46;">Closes on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small><?php endif; ?></span>
                                    <?php endif; ?>
                                </div>
                                <i class="fas fa-chevron-down course-card-toggle"></i>
                            </div>
                            <div class="course-card-body <?php echo ($is_closed) ? 'course-disabled' : ''; ?>">
                                <div class="course-info-grid">
                                    <?php if (!empty($row["eligibility"])): ?>
                                    <div class="info-item"><i class="fas fa-user-graduate"></i><div><span class="info-label">Eligibility</span><span class="info-value"><?php echo htmlspecialchars($row["eligibility"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <div class="info-item"><i class="fas fa-clock"></i><div><span class="info-label">Duration</span><span class="info-value"><?php echo htmlspecialchars($row["duration"]); ?></span></div></div>
                                    <?php if (!empty($row["training_fees"])): ?>
                                    <div class="info-item"><i class="fas fa-rupee-sign"></i><div><span class="info-label">Training Fees</span><span class="info-value">₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["start_date"])): ?>
                                    <div class="info-item"><i class="fas fa-calendar-alt"></i><div><span class="info-label">Course Start Date</span><span class="info-value"><?php echo date('d M Y', strtotime($row["start_date"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["end_date"])): ?>
                                    <div class="info-item"><i class="fas fa-calendar-check"></i><div><span class="info-label">Course End Date</span><span class="info-value"><?php echo date('d M Y', strtotime($row["end_date"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["course_coordinator"])): ?>
                                    <div class="info-item"><i class="fas fa-user-tie"></i><div><span class="info-label">Coordinator</span><span class="info-value"><?php echo htmlspecialchars($row["course_coordinator"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["course_description"])): ?>
                                    <div class="info-item" style="grid-column:1/-1;"><i class="fas fa-info-circle"></i><div><span class="info-label">Description</span><span class="info-value"><?php echo nl2br(htmlspecialchars($row["course_description"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_name"])): ?>
                                    <div class="info-item"><i class="fas fa-map-marker-alt"></i><div><span class="info-label">Training Centre</span><span class="info-value"><?php echo htmlspecialchars($row["centre_name"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_city"]) && !empty($row["centre_state"])): ?>
                                    <div class="info-item"><i class="fas fa-location-dot"></i><div><span class="info-label">Location</span><span class="info-value"><?php echo htmlspecialchars($row["centre_city"]) . ', ' . htmlspecialchars($row["centre_state"]); ?></span></div></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="course-card-footer">
                                <?php if (!empty($row["description_url"])): ?><a href="<?php echo htmlspecialchars($row["description_url"]); ?>" target="_blank" class="btn-outline-modern btn-modern"><i class="fas fa-info-circle"></i> View Details</a><?php endif; ?>
                                <?php if (!empty($row["description_pdf"])): ?><a href="<?php echo APP_URL . '/' . htmlspecialchars($row["description_pdf"]); ?>" target="_blank" class="btn-outline-modern btn-modern"><i class="fas fa-file-pdf"></i> Download PDF</a><?php endif; ?>
                                <?php if (!empty($row["apply_link"]) && (!isset($row["link_published"]) || $row["link_published"] == 1)): ?>
                                    <?php if ($is_closed): ?><button class="btn-disabled btn-modern" disabled><i class="fas fa-times-circle"></i> Enrollment Closed</button>
                                    <?php else: ?><a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-success-modern btn-modern"><i class="fas fa-paper-plane"></i> Apply Now</a><?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><h4>No Skill Based (Short Term) Courses Available</h4><p>Please check back later.</p></div>
            <?php endif; ?>
        </div>

        <!-- 3. Short Term / Digital Competency Courses <= 90 hrs -->
        <div class="course-section" id="short-digital-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-desktop"></i>
                    Short Term / Digital Competency Courses (&lt;= 90 hrs)
                </h3>
            </div>
            <?php if ($result_short_digital && $result_short_digital->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while ($row = $result_short_digital->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="course-card" onclick="toggleCourseCard(this)">
                            <div class="course-card-header">
                                <div class="course-header-info">
                                    <h4><?php echo htmlspecialchars($row["course_name"]); ?><?php if (!empty($row["is_nsqf"]) && $row["is_nsqf"]==1): ?> <span class="badge bg-info" style="font-size:0.7rem;">NSQF</span><?php endif; ?></h4>
                                    <div class="course-quick-info">
                                        <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($row["duration"]); ?></span>
                                        <?php if (!empty($row["training_fees"])): ?>
                                        <span><i class="fas fa-rupee-sign"></i> ₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="enrollment-status-badge">
                                    <?php 
                                    $enrollment_status = $row['enrollment_status'] ?? 'ongoing';
                                    $enrollment_closing_date = $row['enrollment_closing_date'] ?? null;
                                    $today = date('Y-m-d');
                                    $is_closed = false;
                                    if ($enrollment_status == 'closed') { $is_closed = true; }
                                    elseif (!empty($enrollment_closing_date) && $today > $enrollment_closing_date) { $is_closed = true; }
                                    if ($is_closed): ?>
                                        <span class="status-badge status-closed"><i class="fas fa-times-circle"></i> Enrollment Closed<?php if (!empty($enrollment_closing_date)): ?><br><small style="color:#92400e;">Closed on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small><?php endif; ?></span>
                                    <?php else: ?>
                                        <span class="status-badge status-ongoing"><i class="fas fa-check-circle"></i> Enrollment Open<?php if (!empty($enrollment_closing_date)): ?><br><small style="color:#065f46;">Closes on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small><?php endif; ?></span>
                                    <?php endif; ?>
                                </div>
                                <i class="fas fa-chevron-down course-card-toggle"></i>
                            </div>
                            <div class="course-card-body <?php echo ($is_closed) ? 'course-disabled' : ''; ?>">
                                <div class="course-info-grid">
                                    <?php if (!empty($row["eligibility"])): ?>
                                    <div class="info-item"><i class="fas fa-user-graduate"></i><div><span class="info-label">Eligibility</span><span class="info-value"><?php echo htmlspecialchars($row["eligibility"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <div class="info-item"><i class="fas fa-clock"></i><div><span class="info-label">Duration</span><span class="info-value"><?php echo htmlspecialchars($row["duration"]); ?></span></div></div>
                                    <?php if (!empty($row["training_fees"])): ?>
                                    <div class="info-item"><i class="fas fa-rupee-sign"></i><div><span class="info-label">Training Fees</span><span class="info-value">₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["start_date"])): ?>
                                    <div class="info-item"><i class="fas fa-calendar-alt"></i><div><span class="info-label">Course Start Date</span><span class="info-value"><?php echo date('d M Y', strtotime($row["start_date"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["end_date"])): ?>
                                    <div class="info-item"><i class="fas fa-calendar-check"></i><div><span class="info-label">Course End Date</span><span class="info-value"><?php echo date('d M Y', strtotime($row["end_date"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["course_coordinator"])): ?>
                                    <div class="info-item"><i class="fas fa-user-tie"></i><div><span class="info-label">Coordinator</span><span class="info-value"><?php echo htmlspecialchars($row["course_coordinator"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["course_description"])): ?>
                                    <div class="info-item" style="grid-column:1/-1;"><i class="fas fa-info-circle"></i><div><span class="info-label">Description</span><span class="info-value"><?php echo nl2br(htmlspecialchars($row["course_description"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_name"])): ?>
                                    <div class="info-item"><i class="fas fa-map-marker-alt"></i><div><span class="info-label">Training Centre</span><span class="info-value"><?php echo htmlspecialchars($row["centre_name"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_city"]) && !empty($row["centre_state"])): ?>
                                    <div class="info-item"><i class="fas fa-location-dot"></i><div><span class="info-label">Location</span><span class="info-value"><?php echo htmlspecialchars($row["centre_city"]) . ', ' . htmlspecialchars($row["centre_state"]); ?></span></div></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="course-card-footer">
                                <?php if (!empty($row["description_url"])): ?><a href="<?php echo htmlspecialchars($row["description_url"]); ?>" target="_blank" class="btn-outline-modern btn-modern"><i class="fas fa-info-circle"></i> View Details</a><?php endif; ?>
                                <?php if (!empty($row["description_pdf"])): ?><a href="<?php echo APP_URL . '/' . htmlspecialchars($row["description_pdf"]); ?>" target="_blank" class="btn-outline-modern btn-modern"><i class="fas fa-file-pdf"></i> Download PDF</a><?php endif; ?>
                                <?php if (!empty($row["apply_link"]) && (!isset($row["link_published"]) || $row["link_published"] == 1)): ?>
                                    <?php if ($is_closed): ?><button class="btn-disabled btn-modern" disabled><i class="fas fa-times-circle"></i> Enrollment Closed</button>
                                    <?php else: ?><a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-success-modern btn-modern"><i class="fas fa-paper-plane"></i> Apply Now</a><?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><h4>No Short Term / Digital Competency Courses Available</h4><p>Please check back later.</p></div>
            <?php endif; ?>
        </div>

        <!-- 4. Internship Programs & Boot Camps -->
        <div class="course-section" id="internship-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-rocket"></i>
                    Internship Programs &amp; Boot Camps
                </h3>
            </div>
            <?php if ($result_internship && $result_internship->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while ($row = $result_internship->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="course-card" onclick="toggleCourseCard(this)">
                            <div class="course-card-header">
                                <div class="course-header-info">
                                    <h4><?php echo htmlspecialchars($row["course_name"]); ?></h4>
                                    <div class="course-quick-info">
                                        <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($row["duration"]); ?></span>
                                        <?php if (!empty($row["training_fees"])): ?>
                                        <span><i class="fas fa-rupee-sign"></i> ₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <!-- Enrollment Status Badge -->
                                <div class="enrollment-status-badge">
                                    <?php 
                                    $enrollment_status = $row['enrollment_status'] ?? 'ongoing';
                                    $enrollment_closing_date = $row['enrollment_closing_date'] ?? null;
                                    $today = date('Y-m-d');
                                    $is_closed = false;
                                    if ($enrollment_status == 'closed') {
                                        $is_closed = true;
                                    } elseif (!empty($enrollment_closing_date) && $today > $enrollment_closing_date) {
                                        $is_closed = true;
                                    }
                                    if ($is_closed): 
                                    ?>
                                        <span class="status-badge status-closed">
                                            <i class="fas fa-times-circle"></i> Enrollment Closed<?php if (!empty($enrollment_closing_date)): ?><br><small style="color:#92400e;">Closed on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small><?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-ongoing">
                                            <i class="fas fa-check-circle"></i> Enrollment Open<?php if (!empty($enrollment_closing_date)): ?><br><small style="color:#065f46;">Closes on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small><?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <i class="fas fa-chevron-down course-card-toggle"></i>
                            </div>
                            <div class="course-card-body <?php echo ($is_closed) ? 'course-disabled' : ''; ?>">
                                <div class="course-info-grid">
                                    <div class="info-item">
                                        <i class="fas fa-user-graduate"></i>
                                        <div>
                                            <span class="info-label">Eligibility</span>
                                            <span class="info-value"><?php echo htmlspecialchars($row["eligibility"]); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-clock"></i>
                                        <div>
                                            <span class="info-label">Duration</span>
                                            <span class="info-value"><?php echo htmlspecialchars($row["duration"]); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-rupee-sign"></i>
                                        <div>
                                            <span class="info-label">Training Fees</span>
                                            <span class="info-value">₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <div>
                                            <span class="info-label">Course Start Date</span>
                                            <span class="info-value"><?php echo date('d M Y', strtotime($row["start_date"])); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <div>
                                            <span class="info-label">Course End Date</span>
                                            <span class="info-value"><?php echo date('d M Y', strtotime($row["end_date"])); ?></span>
                                        </div>
                                    </div>
                                    <?php if (!empty($row["course_coordinator"])): ?>
                                    <div class="info-item">
                                        <i class="fas fa-user-tie"></i>
                                        <div>
                                            <span class="info-label">Coordinator</span>
                                            <span class="info-value"><?php echo htmlspecialchars($row["course_coordinator"]); ?></span>
                                        </div>
                                    </div>
                                    <?php if (!empty($row["course_description"])): ?>
                                    <div class="info-item" style="grid-column: 1 / -1;">
                                        <i class="fas fa-info-circle"></i>
                                        <div>
                                            <span class="info-label">Description</span>
                                            <span class="info-value"><?php echo nl2br(htmlspecialchars($row["course_description"])); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_name"])): ?>
                                    <div class="info-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <div>
                                            <span class="info-label">Training Centre</span>
                                            <span class="info-value"><?php echo htmlspecialchars($row["centre_name"]); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_city"]) && !empty($row["centre_state"])): ?>
                                    <div class="info-item">
                                        <i class="fas fa-location-dot"></i>
                                        <div>
                                            <span class="info-label">Location</span>
                                            <span class="info-value"><?php echo htmlspecialchars($row["centre_city"]) . ', ' . htmlspecialchars($row["centre_state"]); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="course-card-footer">
                                <?php if (!empty($row["description_url"])): ?>
                                    <a href="<?php echo htmlspecialchars($row["description_url"]); ?>" target="_blank" class="btn-outline-modern btn-modern">
                                        <i class="fas fa-info-circle"></i> View Details
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($row["description_pdf"])): ?>
                                    <a href="<?php echo APP_URL . '/' . htmlspecialchars($row["description_pdf"]); ?>" target="_blank" class="btn-outline-modern btn-modern">
                                        <i class="fas fa-file-pdf"></i> Download PDF
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($row["apply_link"]) && (!isset($row["link_published"]) || $row["link_published"] == 1)): ?>
                                    <?php if ($is_closed): ?>
                                        <button class="btn-disabled btn-modern" disabled title="Enrollment is closed for this course">
                                            <i class="fas fa-times-circle"></i> Enrollment Closed
                                        </button>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-success-modern btn-modern">
                                            <i class="fas fa-paper-plane"></i> Apply Now
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><h4>No Internship Programs Available</h4><p>Please check back later.</p></div>
            <?php endif; ?>
        </div>

        <!-- 5. Degree / Diploma / PG Courses -->
        <div class="course-section" id="degree-pg-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-graduation-cap"></i>
                    Degree / Diploma / Postgraduate Courses
                </h3>
            </div>
            <?php if ($result_degree_pg && $result_degree_pg->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while ($row = $result_degree_pg->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="course-card" onclick="toggleCourseCard(this)">
                            <div class="course-card-header">
                                <div class="course-header-info">
                                    <h4><?php echo htmlspecialchars($row["course_name"]); ?><?php if (!empty($row["is_nsqf"]) && $row["is_nsqf"]==1): ?> <span class="badge bg-info" style="font-size:0.7rem;">NSQF</span><?php endif; ?></h4>
                                    <div class="course-quick-info">
                                        <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($row["duration"]); ?></span>
                                        <?php if (!empty($row["training_fees"])): ?>
                                        <span><i class="fas fa-rupee-sign"></i> ₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="enrollment-status-badge">
                                    <?php 
                                    $enrollment_status = $row['enrollment_status'] ?? 'ongoing';
                                    $enrollment_closing_date = $row['enrollment_closing_date'] ?? null;
                                    $today = date('Y-m-d');
                                    $is_closed = false;
                                    if ($enrollment_status == 'closed') { $is_closed = true; }
                                    elseif (!empty($enrollment_closing_date) && $today > $enrollment_closing_date) { $is_closed = true; }
                                    if ($is_closed): ?>
                                        <span class="status-badge status-closed"><i class="fas fa-times-circle"></i> Enrollment Closed<?php if (!empty($enrollment_closing_date)): ?><br><small style="color:#92400e;">Closed on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small><?php endif; ?></span>
                                    <?php else: ?>
                                        <span class="status-badge status-ongoing"><i class="fas fa-check-circle"></i> Enrollment Open<?php if (!empty($enrollment_closing_date)): ?><br><small style="color:#065f46;">Closes on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small><?php endif; ?></span>
                                    <?php endif; ?>
                                </div>
                                <i class="fas fa-chevron-down course-card-toggle"></i>
                            </div>
                            <div class="course-card-body <?php echo ($is_closed) ? 'course-disabled' : ''; ?>">
                                <div class="course-info-grid">
                                    <?php if (!empty($row["eligibility"])): ?>
                                    <div class="info-item"><i class="fas fa-user-graduate"></i><div><span class="info-label">Eligibility</span><span class="info-value"><?php echo htmlspecialchars($row["eligibility"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <div class="info-item"><i class="fas fa-clock"></i><div><span class="info-label">Duration</span><span class="info-value"><?php echo htmlspecialchars($row["duration"]); ?></span></div></div>
                                    <?php if (!empty($row["training_fees"])): ?>
                                    <div class="info-item"><i class="fas fa-rupee-sign"></i><div><span class="info-label">Training Fees</span><span class="info-value">₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["start_date"])): ?>
                                    <div class="info-item"><i class="fas fa-calendar-alt"></i><div><span class="info-label">Course Start Date</span><span class="info-value"><?php echo date('d M Y', strtotime($row["start_date"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["end_date"])): ?>
                                    <div class="info-item"><i class="fas fa-calendar-check"></i><div><span class="info-label">Course End Date</span><span class="info-value"><?php echo date('d M Y', strtotime($row["end_date"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["course_coordinator"])): ?>
                                    <div class="info-item"><i class="fas fa-user-tie"></i><div><span class="info-label">Coordinator</span><span class="info-value"><?php echo htmlspecialchars($row["course_coordinator"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["course_description"])): ?>
                                    <div class="info-item" style="grid-column:1/-1;"><i class="fas fa-info-circle"></i><div><span class="info-label">Description</span><span class="info-value"><?php echo nl2br(htmlspecialchars($row["course_description"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_name"])): ?>
                                    <div class="info-item"><i class="fas fa-map-marker-alt"></i><div><span class="info-label">Training Centre</span><span class="info-value"><?php echo htmlspecialchars($row["centre_name"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_city"]) && !empty($row["centre_state"])): ?>
                                    <div class="info-item"><i class="fas fa-location-dot"></i><div><span class="info-label">Location</span><span class="info-value"><?php echo htmlspecialchars($row["centre_city"]) . ', ' . htmlspecialchars($row["centre_state"]); ?></span></div></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="course-card-footer">
                                <?php if (!empty($row["description_url"])): ?><a href="<?php echo htmlspecialchars($row["description_url"]); ?>" target="_blank" class="btn-outline-modern btn-modern"><i class="fas fa-info-circle"></i> View Details</a><?php endif; ?>
                                <?php if (!empty($row["description_pdf"])): ?><a href="<?php echo APP_URL . '/' . htmlspecialchars($row["description_pdf"]); ?>" target="_blank" class="btn-outline-modern btn-modern"><i class="fas fa-file-pdf"></i> Download PDF</a><?php endif; ?>
                                <?php if (!empty($row["apply_link"]) && (!isset($row["link_published"]) || $row["link_published"] == 1)): ?>
                                    <?php if ($is_closed): ?><button class="btn-disabled btn-modern" disabled><i class="fas fa-times-circle"></i> Enrollment Closed</button>
                                    <?php else: ?><a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-primary-modern btn-modern"><i class="fas fa-paper-plane"></i> Apply Now</a><?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><h4>No Degree / Diploma / PG Courses Available</h4><p>Please check back later.</p></div>
            <?php endif; ?>
        </div>

        <!-- 6. NIELIT HQ Digital Literacy Courses (CCC / ECC / BCC / ACC) -->
        <div class="course-section" id="digital-lit-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-keyboard"></i>
                    NIELIT HQ Digital Literacy Courses (CCC / ECC / BCC / ACC)
                </h3>
            </div>
            <?php if ($result_digital_lit && $result_digital_lit->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while ($row = $result_digital_lit->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="course-card" onclick="toggleCourseCard(this)">
                            <div class="course-card-header">
                                <div class="course-header-info">
                                    <h4><?php echo htmlspecialchars($row["course_name"]); ?><?php if (!empty($row["is_nsqf"]) && $row["is_nsqf"]==1): ?> <span class="badge bg-info" style="font-size:0.7rem;">NSQF</span><?php endif; ?></h4>
                                    <div class="course-quick-info">
                                        <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($row["duration"]); ?></span>
                                        <?php if (!empty($row["training_fees"])): ?>
                                        <span><i class="fas fa-rupee-sign"></i> ₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="enrollment-status-badge">
                                    <?php 
                                    $enrollment_status = $row['enrollment_status'] ?? 'ongoing';
                                    $enrollment_closing_date = $row['enrollment_closing_date'] ?? null;
                                    $today = date('Y-m-d');
                                    $is_closed = false;
                                    if ($enrollment_status == 'closed') { $is_closed = true; }
                                    elseif (!empty($enrollment_closing_date) && $today > $enrollment_closing_date) { $is_closed = true; }
                                    if ($is_closed): ?>
                                        <span class="status-badge status-closed"><i class="fas fa-times-circle"></i> Enrollment Closed<?php if (!empty($enrollment_closing_date)): ?><br><small style="color:#92400e;">Closed on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small><?php endif; ?></span>
                                    <?php else: ?>
                                        <span class="status-badge status-ongoing"><i class="fas fa-check-circle"></i> Enrollment Open<?php if (!empty($enrollment_closing_date)): ?><br><small style="color:#065f46;">Closes on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small><?php endif; ?></span>
                                    <?php endif; ?>
                                </div>
                                <i class="fas fa-chevron-down course-card-toggle"></i>
                            </div>
                            <div class="course-card-body <?php echo ($is_closed) ? 'course-disabled' : ''; ?>">
                                <div class="course-info-grid">
                                    <?php if (!empty($row["eligibility"])): ?>
                                    <div class="info-item"><i class="fas fa-user-graduate"></i><div><span class="info-label">Eligibility</span><span class="info-value"><?php echo htmlspecialchars($row["eligibility"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <div class="info-item"><i class="fas fa-clock"></i><div><span class="info-label">Duration</span><span class="info-value"><?php echo htmlspecialchars($row["duration"]); ?></span></div></div>
                                    <?php if (!empty($row["training_fees"])): ?>
                                    <div class="info-item"><i class="fas fa-rupee-sign"></i><div><span class="info-label">Training Fees</span><span class="info-value">₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["start_date"])): ?>
                                    <div class="info-item"><i class="fas fa-calendar-alt"></i><div><span class="info-label">Course Start Date</span><span class="info-value"><?php echo date('d M Y', strtotime($row["start_date"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["end_date"])): ?>
                                    <div class="info-item"><i class="fas fa-calendar-check"></i><div><span class="info-label">Course End Date</span><span class="info-value"><?php echo date('d M Y', strtotime($row["end_date"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["course_coordinator"])): ?>
                                    <div class="info-item"><i class="fas fa-user-tie"></i><div><span class="info-label">Coordinator</span><span class="info-value"><?php echo htmlspecialchars($row["course_coordinator"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["course_description"])): ?>
                                    <div class="info-item" style="grid-column:1/-1;"><i class="fas fa-info-circle"></i><div><span class="info-label">Description</span><span class="info-value"><?php echo nl2br(htmlspecialchars($row["course_description"])); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_name"])): ?>
                                    <div class="info-item"><i class="fas fa-map-marker-alt"></i><div><span class="info-label">Training Centre</span><span class="info-value"><?php echo htmlspecialchars($row["centre_name"]); ?></span></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_city"]) && !empty($row["centre_state"])): ?>
                                    <div class="info-item"><i class="fas fa-location-dot"></i><div><span class="info-label">Location</span><span class="info-value"><?php echo htmlspecialchars($row["centre_city"]) . ', ' . htmlspecialchars($row["centre_state"]); ?></span></div></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="course-card-footer">
                                <?php if (!empty($row["description_url"])): ?><a href="<?php echo htmlspecialchars($row["description_url"]); ?>" target="_blank" class="btn-outline-modern btn-modern"><i class="fas fa-info-circle"></i> View Details</a><?php endif; ?>
                                <?php if (!empty($row["description_pdf"])): ?><a href="<?php echo APP_URL . '/' . htmlspecialchars($row["description_pdf"]); ?>" target="_blank" class="btn-outline-modern btn-modern"><i class="fas fa-file-pdf"></i> Download PDF</a><?php endif; ?>
                                <?php if (!empty($row["apply_link"]) && (!isset($row["link_published"]) || $row["link_published"] == 1)): ?>
                                    <?php if ($is_closed): ?><button class="btn-disabled btn-modern" disabled><i class="fas fa-times-circle"></i> Enrollment Closed</button>
                                    <?php else: ?><a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-success-modern btn-modern"><i class="fas fa-paper-plane"></i> Apply Now</a><?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><h4>No Digital Literacy Courses Available</h4><p>Please check back later.</p></div>
            <?php endif; ?>
        </div>
    </div>
</section>
            <?php if ($result_skill_long && $result_skill_long->num_rows > 0): ?>
                <div class="row">
                    <?php while ($row = $result_skill_long->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="course-card">
                            <div class="course-card-header">
                                <h4><?php echo htmlspecialchars($row["course_name"]); ?> <?php if (!empty($row["is_nsqf"]) && $row["is_nsqf"]==1): ?><span class="badge bg-info" style="margin-left:8px;">NSQF</span><?php endif; ?></h4>
                                <!-- Enrollment Status Badge -->
                                <div class="enrollment-status-badge" style="margin-top: 8px;">
                                    <?php 
                                    $enrollment_status = $row['enrollment_status'] ?? 'ongoing';
                                    $enrollment_closing_date = $row['enrollment_closing_date'] ?? null;
                                    $today = date('Y-m-d');
                                    $is_closed = false;
                                    if ($enrollment_status == 'closed') {
                                        $is_closed = true;
                                    } elseif (!empty($enrollment_closing_date) && $today > $enrollment_closing_date) {
                                        $is_closed = true;
                                    }
                                    if ($is_closed): 
                                    ?>
                                        <span class="status-badge status-closed">
                                            <i class="fas fa-times-circle"></i> Enrollment Closed
                                            <?php if (!empty($enrollment_closing_date)): ?>
                                                <br><small style="color:#b58900;">Closed on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-ongoing">
                                            <i class="fas fa-check-circle"></i> Enrollment Open
                                            <?php if (!empty($enrollment_closing_date)): ?>
                                                <br><small style="color:#388e3c;">Closes on <?php echo date('d M Y', strtotime($enrollment_closing_date)); ?></small>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="course-card-body <?php echo ($is_closed) ? 'course-disabled' : ''; ?>">
                                <div class="course-info-grid">
                                    <div class="info-item">
                                        <i class="fas fa-user-graduate"></i>
                                        <div>
                                            <span class="info-label">Eligibility</span>
                                            <span class="info-value"><?php echo htmlspecialchars($row["eligibility"]); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-clock"></i>
                                        <div>
                                            <span class="info-label">Duration</span>
                                            <span class="info-value"><?php echo htmlspecialchars($row["duration"]); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-rupee-sign"></i>
                                        <div>
                                            <span class="info-label">Training Fees</span>
                                            <span class="info-value">₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <div>
                                            <span class="info-label">Course Start Date</span>
                                            <span class="info-value"><?php echo date('d M Y', strtotime($row["start_date"])); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <div>
                                            <span class="info-label">Course End Date</span>
                                            <span class="info-value"><?php echo date('d M Y', strtotime($row["end_date"])); ?></span>
                                        </div>
                                    </div>
                                    <?php if (!empty($row["course_coordinator"])): ?>
                                    <div class="info-item">
                                        <i class="fas fa-user-tie"></i>
                                        <div>
                                            <span class="info-label">Coordinator</span>
                                            <span class="info-value"><?php echo htmlspecialchars($row["course_coordinator"]); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["course_description"])): ?>
                                    <div class="info-item" style="grid-column: 1 / -1;">
                                        <i class="fas fa-info-circle"></i>
                                        <div>
                                            <span class="info-label">Description</span>
                                            <span class="info-value"><?php echo nl2br(htmlspecialchars($row["course_description"])); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_name"])): ?>
                                    <div class="info-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <div>
                                            <span class="info-label">Training Centre</span>
                                            <span class="info-value"><?php echo htmlspecialchars($row["centre_name"]); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($row["centre_city"]) && !empty($row["centre_state"])): ?>
                                    <div class="info-item">
                                        <i class="fas fa-location-dot"></i>
                                        <div>
                                            <span class="info-label">Location</span>
                                            <span class="info-value"><?php echo htmlspecialchars($row["centre_city"]) . ', ' . htmlspecialchars($row["centre_state"]); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="course-card-footer">
                                <?php if (!empty($row["description_url"])): ?>
                                    <a href="<?php echo htmlspecialchars($row["description_url"]); ?>" target="_blank" class="btn-outline-modern btn-modern">
                                        <i class="fas fa-info-circle"></i> View Details
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($row["description_pdf"])): ?>
                                    <a href="<?php echo APP_URL . '/' . htmlspecialchars($row["description_pdf"]); ?>" target="_blank" class="btn-outline-modern btn-modern">
                                        <i class="fas fa-file-pdf"></i> Download PDF
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($row["apply_link"]) && (!isset($row["link_published"]) || $row["link_published"] == 1)): ?>
                                    <?php if ($is_closed): ?>
                                        <button class="btn-disabled btn-modern" disabled title="Enrollment is closed for this course">
                                            <i class="fas fa-times-circle"></i> Enrollment Closed
                                        </button>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-success-modern btn-modern">
                                            <i class="fas fa-paper-plane"></i> Apply Now
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><h4>No Skill Based (Long Term) Courses Available</h4><p>Please check back later.</p></div>
            <?php endif; ?>
        </div>
    </div>
</section>






<!-- Footer (Matching Index.php) -->
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
                    <li><a href="<?php echo APP_URL; ?>/index.php"><i class="fas fa-chevron-right me-2 small"></i>Home</a></li>
                    <li><a href="<?php echo APP_URL; ?>/public/courses.php"><i class="fas fa-chevron-right me-2 small"></i>Courses Offered</a></li>
                    <li><a href="<?php echo APP_URL; ?>/public/management.php"><i class="fas fa-chevron-right me-2 small"></i>Management</a></li>
                    <li><a href="<?php echo APP_URL; ?>/public/news.php"><i class="fas fa-chevron-right me-2 small"></i>News</a></li>
                    <li><a href="<?php echo APP_URL; ?>/public/contact.php"><i class="fas fa-chevron-right me-2 small"></i>Contact Us</a></li>
                </ul>
            </div>

            <div class="col-lg-4 col-md-12">
                    <h5>Contact Info</h5>
                <p class="small text-muted mb-3">National Institute of Electronics & Information Technology, Bhubaneswar</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-phone-alt me-2" style="color: var(--gold);"></i> 0674-2960354</li>
                    <li class="mb-2"><i class="fas fa-envelope me-2" style="color: var(--gold);"></i> dir-bbsr@nielit.gov.in</li>
                    <li class="mb-2"><i class="fas fa-clock me-2" style="color: var(--gold);"></i> Mon-Fri: 09:00 AM – 5:30 PM</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="copyright-bar">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-md-start text-center">
                    © 2025 NIELIT Bhubaneswar. All Rights Reserved.
                </div>
                <div class="col-md-6 text-md-end text-center">
                    Designed & Developed by NIELIT Team
                </div>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Toggle course card expansion
function toggleCourseCard(card) {
    // Prevent toggle when clicking on links or buttons
    if (event.target.tagName === 'A' || event.target.tagName === 'BUTTON' || event.target.closest('a') || event.target.closest('button')) {
        return;
    }
    
    card.classList.toggle('expanded');
}

// Centre filter function
function selectCentre(centreId) {
    window.location.href = '<?php echo APP_URL; ?>/public/courses.php?centre=' + centreId;
}
</script>

</body>
</html>

<?php
// Close the database connection
$conn->close();
?>

