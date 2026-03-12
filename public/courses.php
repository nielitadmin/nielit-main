<?php
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

// Fetch courses for each category - ONLY SHOW PUBLISHED COURSES
// Courses with link_published = 1 OR NULL (for backward compatibility)
// Apply centre filter if selected
// JOIN with centres table to get centre information
$sql_long_term = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                  FROM courses 
                  LEFT JOIN centres ON courses.centre_id = centres.id 
                  WHERE courses.category = 'Long Term NSQF' AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;
$sql_short_term = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                   FROM courses 
                   LEFT JOIN centres ON courses.centre_id = centres.id 
                   WHERE courses.category = 'Short Term NSQF' AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;
$sql_non_nsqf = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                 FROM courses 
                 LEFT JOIN centres ON courses.centre_id = centres.id 
                 WHERE courses.category = 'Short-Term Non-NSQF' AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;
$sql_internship = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                   FROM courses 
                   LEFT JOIN centres ON courses.centre_id = centres.id 
                   WHERE courses.category = 'Internship Program' AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// Execute the queries
$result_long_term = $conn->query($sql_long_term);
$result_short_term = $conn->query($sql_short_term);
$result_non_nsqf = $conn->query($sql_non_nsqf);
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
</head>
<body>

<!-- Top Bar (Government Header) -->
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
        <span class="badge bg-warning text-dark me-2">NEW</span> 
        Admissions Open! Explore our NSQF-aligned courses and internship programs. Apply now for upcoming batches.
    </div>
</div>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1 class="text-center">
            <i class="fas fa-graduation-cap"></i> Courses Offered
        </h1>
        <p class="text-center">
            NIELIT Bhubaneswar offers various long-term and short-term courses designed to equip students with industry-standard skills. 
            Explore our comprehensive range of NSQF-aligned courses, internship programs, and boot camps.
        </p>
    </div>
</div>

<!-- Filter Section -->
<section class="py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); position: relative; overflow: hidden;">
    <!-- Background Pattern -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.1; background-image: url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%23ffffff" fill-opacity="0.3"><circle cx="30" cy="30" r="2"/></g></svg>'); background-size: 60px 60px;"></div>
    
    <div class="container position-relative">
        <!-- Section Header -->
        <div class="text-center mb-5">
            <div class="filter-icon" style="width: 80px; height: 80px; background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(20px); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; border: 2px solid rgba(255, 255, 255, 0.3);">
                <i class="fas fa-map-marker-alt" style="font-size: 2rem; color: white;"></i>
            </div>
            <h2 style="color: white; font-weight: 700; margin-bottom: 0.5rem; font-family: 'Poppins', sans-serif;">Choose Your Training Centre</h2>
            <p style="color: rgba(255, 255, 255, 0.9); margin: 0; font-size: 1.2rem;">Select a NIELIT centre to view available courses</p>
        </div>

        <!-- Training Centre Cards -->
        <div class="row g-4 justify-content-center">
            <!-- All Centres Card - Left -->
            <div class="col-lg-4 col-md-6 order-lg-1 order-2">
                <div class="centre-card <?php echo $centre_filter == 0 ? 'active' : ''; ?>" onclick="selectCentre(0)" style="
                    background: rgba(255, 255, 255, 0.95);
                    backdrop-filter: blur(20px);
                    border-radius: 20px;
                    padding: 2rem;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    border: 2px solid rgba(255, 255, 255, 0.2);
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                    position: relative;
                    overflow: hidden;
                ">
                    <!-- Card Background Pattern -->
                    <div style="position: absolute; top: -50%; right: -50%; width: 100%; height: 100%; background: linear-gradient(45deg, transparent, rgba(102, 126, 234, 0.1)); border-radius: 50%; transform: rotate(45deg);"></div>
                    
                    <div class="text-center position-relative">
                        <div class="centre-icon" style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);">
                            <i class="fas fa-globe" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                        <h4 style="color: #2d3748; font-weight: 700; margin-bottom: 0.5rem;">All Training Centres</h4>
                        <p style="color: #718096; margin-bottom: 1rem; font-size: 0.9rem;">View courses from all NIELIT centres across India</p>
                        
                        <!-- Stats -->
                        <div class="centre-stats" style="display: flex; justify-content: space-around; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                            <div class="stat-item text-center">
                                <div style="font-size: 1.2rem; font-weight: 700; color: #667eea;">
                                    <?php 
                                    $total_courses = 0;
                                    if ($result_long_term) $total_courses += $result_long_term->num_rows;
                                    if ($result_short_term) $total_courses += $result_short_term->num_rows;
                                    if ($result_non_nsqf) $total_courses += $result_non_nsqf->num_rows;
                                    if ($result_internship) $total_courses += $result_internship->num_rows;
                                    echo $total_courses;
                                    ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #718096;">Courses</div>
                            </div>
                            <div class="stat-item text-center">
                                <div style="font-size: 1.2rem; font-weight: 700; color: #48bb78;">
                                    <?php 
                                    $centres_result = $conn->query("SELECT COUNT(*) as count FROM centres WHERE is_active = 1");
                                    $centres_count = $centres_result ? $centres_result->fetch_assoc()['count'] : 0;
                                    echo $centres_count;
                                    ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #718096;">Centres</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php 
            // Find NIELIT Bhubaneswar centre and display it in the center
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
            
            // Display NIELIT Bhubaneswar in center with special styling
            if ($bhubaneswar_centre) {
                $is_active = ($centre_filter == $bhubaneswar_centre['id']) ? 'active' : '';
                
                // Get course count for Bhubaneswar centre
                $course_count_query = "SELECT COUNT(*) as count FROM courses WHERE centre_id = " . $bhubaneswar_centre['id'] . " AND (link_published = 1 OR link_published IS NULL)";
                $course_count_result = $conn->query($course_count_query);
                $course_count = $course_count_result ? $course_count_result->fetch_assoc()['count'] : 0;
                
                $centre_name = htmlspecialchars($bhubaneswar_centre['name']);
                $centre_location = '';
                if (!empty($bhubaneswar_centre['city']) && !empty($bhubaneswar_centre['state'])) {
                    $centre_location = htmlspecialchars($bhubaneswar_centre['city']) . ', ' . htmlspecialchars($bhubaneswar_centre['state']);
                }
                
                echo '<div class="col-lg-4 col-md-6 order-lg-2 order-1">
                    <div class="centre-card featured-centre ' . $is_active . '" onclick="selectCentre(' . $bhubaneswar_centre['id'] . ')" style="
                        background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 100%);
                        backdrop-filter: blur(20px);
                        border-radius: 25px;
                        padding: 2.5rem;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        border: 3px solid rgba(72, 187, 120, 0.3);
                        box-shadow: 0 15px 40px rgba(72, 187, 120, 0.15);
                        position: relative;
                        overflow: hidden;
                        transform: scale(1.05);
                    ">
                        <!-- Featured Badge -->
                        <div style="position: absolute; top: 15px; right: 15px; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-star me-1"></i>Featured
                        </div>
                        
                        <!-- Card Background Pattern -->
                        <div style="position: absolute; top: -50%; right: -50%; width: 100%; height: 100%; background: linear-gradient(45deg, transparent, rgba(72, 187, 120, 0.1)); border-radius: 50%; transform: rotate(45deg);"></div>
                        
                        <div class="text-center position-relative">
                            <div class="centre-icon" style="width: 80px; height: 80px; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; box-shadow: 0 12px 30px rgba(72, 187, 120, 0.4);">
                                <i class="fas fa-university" style="font-size: 2rem; color: white;"></i>
                            </div>
                            <h3 style="color: #2d3748; font-weight: 800; margin-bottom: 0.5rem; font-size: 1.3rem; line-height: 1.3;">' . $centre_name . '</h3>';
                
                if ($centre_location) {
                    echo '<p style="color: #48bb78; margin-bottom: 1.5rem; font-size: 0.95rem; font-weight: 600;">
                            <i class="fas fa-map-pin me-2" style="color: #ed8936;"></i>' . $centre_location . '
                          </p>';
                }
                
                echo '<div style="background: linear-gradient(135deg, #f0fff4 0%, #e6fffa 100%); padding: 1rem; border-radius: 15px; margin-bottom: 1rem; border: 1px solid rgba(72, 187, 120, 0.2);">
                        <p style="color: #2d5016; margin: 0; font-size: 0.85rem; font-weight: 500;">
                            <i class="fas fa-info-circle me-2" style="color: #48bb78;"></i>
                            Your local NIELIT training centre with comprehensive courses and expert faculty.
                        </p>
                    </div>
                    
                    <div class="centre-stats" style="display: flex; justify-content: space-around; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid rgba(72, 187, 120, 0.1);">
                        <div class="stat-item text-center">
                            <div style="font-size: 1.5rem; font-weight: 700; color: #48bb78;">' . $course_count . '</div>
                            <div style="font-size: 0.8rem; color: #718096; font-weight: 500;">Available Courses</div>
                        </div>
                        <div class="stat-item text-center">
                            <div style="font-size: 1.5rem; font-weight: 700; color: #ed8936;">
                                <i class="fas fa-award"></i>
                            </div>
                            <div style="font-size: 0.8rem; color: #718096; font-weight: 500;">Premium Quality</div>
                        </div>
                        <div class="stat-item text-center">
                            <div style="font-size: 1.5rem; font-weight: 700; color: #667eea;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div style="font-size: 0.8rem; color: #718096; font-weight: 500;">Expert Faculty</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
            }
            
            // Display other centres on the right
            if (!empty($other_centres)) {
                $first_other_centre = $other_centres[0];
                $is_active = ($centre_filter == $first_other_centre['id']) ? 'active' : '';
                
                // Get course count for this centre
                $course_count_query = "SELECT COUNT(*) as count FROM courses WHERE centre_id = " . $first_other_centre['id'] . " AND (link_published = 1 OR link_published IS NULL)";
                $course_count_result = $conn->query($course_count_query);
                $course_count = $course_count_result ? $course_count_result->fetch_assoc()['count'] : 0;
                
                $centre_name = htmlspecialchars($first_other_centre['name']);
                $centre_location = '';
                if (!empty($first_other_centre['city']) && !empty($first_other_centre['state'])) {
                    $centre_location = htmlspecialchars($first_other_centre['city']) . ', ' . htmlspecialchars($first_other_centre['state']);
                }
                
                echo '<div class="col-lg-4 col-md-6 order-lg-3 order-3">
                    <div class="centre-card ' . $is_active . '" onclick="selectCentre(' . $first_other_centre['id'] . ')" style="
                        background: rgba(255, 255, 255, 0.95);
                        backdrop-filter: blur(20px);
                        border-radius: 20px;
                        padding: 2rem;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        border: 2px solid rgba(255, 255, 255, 0.2);
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                        position: relative;
                        overflow: hidden;
                        height: 100%;
                    ">
                        <!-- Card Background Pattern -->
                        <div style="position: absolute; top: -50%; right: -50%; width: 100%; height: 100%; background: linear-gradient(45deg, transparent, rgba(159, 122, 234, 0.1)); border-radius: 50%; transform: rotate(45deg);"></div>
                        
                        <div class="text-center position-relative">
                            <div class="centre-icon" style="width: 60px; height: 60px; background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; box-shadow: 0 8px 20px rgba(159, 122, 234, 0.3);">
                                <i class="fas fa-building" style="font-size: 1.5rem; color: white;"></i>
                            </div>
                            <h4 style="color: #2d3748; font-weight: 700; margin-bottom: 0.5rem; font-size: 1.1rem; line-height: 1.3;">' . $centre_name . '</h4>';
                
                if ($centre_location) {
                    echo '<p style="color: #718096; margin-bottom: 1rem; font-size: 0.85rem;">
                            <i class="fas fa-map-pin me-1" style="color: #ed8936;"></i>' . $centre_location . '
                          </p>';
                }
                
                echo '<div class="centre-stats" style="display: flex; justify-content: space-around; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                        <div class="stat-item text-center">
                            <div style="font-size: 1.2rem; font-weight: 700; color: #9f7aea;">' . $course_count . '</div>
                            <div style="font-size: 0.75rem; color: #718096;">Courses</div>
                        </div>
                        <div class="stat-item text-center">
                            <div style="font-size: 1.2rem; font-weight: 700; color: #ed8936;">
                                <i class="fas fa-star"></i>
                            </div>
                            <div style="font-size: 0.75rem; color: #718096;">Quality</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
            }
            
            // Display remaining centres in a second row if there are more
            if (count($other_centres) > 1) {
                echo '<div class="row g-4 mt-2 justify-content-center">';
                for ($i = 1; $i < count($other_centres); $i++) {
                    $centre = $other_centres[$i];
                    $is_active = ($centre_filter == $centre['id']) ? 'active' : '';
                    
                    // Get course count for this centre
                    $course_count_query = "SELECT COUNT(*) as count FROM courses WHERE centre_id = " . $centre['id'] . " AND (link_published = 1 OR link_published IS NULL)";
                    $course_count_result = $conn->query($course_count_query);
                    $course_count = $course_count_result ? $course_count_result->fetch_assoc()['count'] : 0;
                    
                    $centre_name = htmlspecialchars($centre['name']);
                    $centre_location = '';
                    if (!empty($centre['city']) && !empty($centre['state'])) {
                        $centre_location = htmlspecialchars($centre['city']) . ', ' . htmlspecialchars($centre['state']);
                    }
                    
                    echo '<div class="col-lg-4 col-md-6">
                        <div class="centre-card ' . $is_active . '" onclick="selectCentre(' . $centre['id'] . ')" style="
                            background: rgba(255, 255, 255, 0.95);
                            backdrop-filter: blur(20px);
                            border-radius: 20px;
                            padding: 2rem;
                            cursor: pointer;
                            transition: all 0.3s ease;
                            border: 2px solid rgba(255, 255, 255, 0.2);
                            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                            position: relative;
                            overflow: hidden;
                            height: 100%;
                        ">
                            <!-- Card Background Pattern -->
                            <div style="position: absolute; top: -50%; right: -50%; width: 100%; height: 100%; background: linear-gradient(45deg, transparent, rgba(237, 137, 54, 0.1)); border-radius: 50%; transform: rotate(45deg);"></div>
                            
                            <div class="text-center position-relative">
                                <div class="centre-icon" style="width: 60px; height: 60px; background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; box-shadow: 0 8px 20px rgba(237, 137, 54, 0.3);">
                                    <i class="fas fa-building" style="font-size: 1.5rem; color: white;"></i>
                                </div>
                                <h4 style="color: #2d3748; font-weight: 700; margin-bottom: 0.5rem; font-size: 1.1rem; line-height: 1.3;">' . $centre_name . '</h4>';
                    
                    if ($centre_location) {
                        echo '<p style="color: #718096; margin-bottom: 1rem; font-size: 0.85rem;">
                                <i class="fas fa-map-pin me-1" style="color: #ed8936;"></i>' . $centre_location . '
                              </p>';
                    }
                    
                    echo '<div class="centre-stats" style="display: flex; justify-content: space-around; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                            <div class="stat-item text-center">
                                <div style="font-size: 1.2rem; font-weight: 700; color: #ed8936;">' . $course_count . '</div>
                                <div style="font-size: 0.75rem; color: #718096;">Courses</div>
                            </div>
                            <div class="stat-item text-center">
                                <div style="font-size: 1.2rem; font-weight: 700; color: #48bb78;">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div style="font-size: 0.75rem; color: #718096;">Quality</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
                }
                echo '</div>';
            }
            ?>

        <!-- Quick Action Buttons -->
        <div class="text-center mt-5">
            <div style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(20px); border-radius: 15px; padding: 1.5rem; border: 1px solid rgba(255, 255, 255, 0.2);">
                <h5 style="color: white; margin-bottom: 1rem; font-weight: 600;">Quick Navigation</h5>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <button class="quick-nav-btn" onclick="scrollToSection('long-term')" style="
                        padding: 0.75rem 1.5rem;
                        background: rgba(255, 255, 255, 0.2);
                        color: white;
                        border: 1px solid rgba(255, 255, 255, 0.3);
                        border-radius: 25px;
                        font-size: 0.9rem;
                        font-weight: 500;
                        transition: all 0.3s ease;
                        backdrop-filter: blur(10px);
                    ">
                        <i class="fas fa-certificate me-2"></i> Long Term NSQF
                    </button>
                    <button class="quick-nav-btn" onclick="scrollToSection('short-term')" style="
                        padding: 0.75rem 1.5rem;
                        background: rgba(255, 255, 255, 0.2);
                        color: white;
                        border: 1px solid rgba(255, 255, 255, 0.3);
                        border-radius: 25px;
                        font-size: 0.9rem;
                        font-weight: 500;
                        transition: all 0.3s ease;
                        backdrop-filter: blur(10px);
                    ">
                        <i class="fas fa-award me-2"></i> Short Term NSQF
                    </button>
                    <button class="quick-nav-btn" onclick="scrollToSection('non-nsqf')" style="
                        padding: 0.75rem 1.5rem;
                        background: rgba(255, 255, 255, 0.2);
                        color: white;
                        border: 1px solid rgba(255, 255, 255, 0.3);
                        border-radius: 25px;
                        font-size: 0.9rem;
                        font-weight: 500;
                        transition: all 0.3s ease;
                        backdrop-filter: blur(10px);
                    ">
                        <i class="fas fa-laptop-code me-2"></i> Non-NSQF
                    </button>
                    <button class="quick-nav-btn" onclick="scrollToSection('internship')" style="
                        padding: 0.75rem 1.5rem;
                        background: rgba(255, 255, 255, 0.2);
                        color: white;
                        border: 1px solid rgba(255, 255, 255, 0.3);
                        border-radius: 25px;
                        font-size: 0.9rem;
                        font-weight: 500;
                        transition: all 0.3s ease;
                        backdrop-filter: blur(10px);
                    ">
                        <i class="fas fa-rocket me-2"></i> Internships
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Centre Card Hover Effects */
.centre-card {
    transform: translateY(0);
}

.centre-card:hover {
    transform: translateY(-8px) !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15) !important;
    border-color: rgba(102, 126, 234, 0.3) !important;
}

.centre-card.active {
    border-color: #667eea !important;
    box-shadow: 0 15px 35px rgba(102, 126, 234, 0.2) !important;
    transform: translateY(-5px) !important;
}

.centre-card.active::before {
    content: '';
    position: absolute;
    top: 10px;
    right: 10px;
    width: 20px;
    height: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.centre-card.active::after {
    content: '✓';
    position: absolute;
    top: 15px;
    right: 15px;
    color: white;
    font-size: 10px;
    font-weight: bold;
    z-index: 11;
}

/* Quick Navigation Button Hover */
.quick-nav-btn:hover {
    background: rgba(255, 255, 255, 0.3) !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 255, 255, 0.1);
}

/* Card Animation */
.centre-card {
    animation: cardSlideIn 0.6s ease-out;
}

@keyframes cardSlideIn {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .centre-card {
        margin-bottom: 1rem;
    }
    
    .quick-nav-btn {
        font-size: 0.8rem !important;
        padding: 0.6rem 1.2rem !important;
    }
}

/* Stagger animation for cards */
.centre-card:nth-child(1) { animation-delay: 0.1s; }
.centre-card:nth-child(2) { animation-delay: 0.2s; }
.centre-card:nth-child(3) { animation-delay: 0.3s; }
.centre-card:nth-child(4) { animation-delay: 0.4s; }
.centre-card:nth-child(5) { animation-delay: 0.5s; }
.centre-card:nth-child(6) { animation-delay: 0.6s; }
</style>

<script>
// Centre selection function for card-based interface
function selectCentre(centreId) {
    const url = new URL(window.location.href);
    if (centreId == 0) {
        url.searchParams.delete('centre');
    } else {
        url.searchParams.set('centre', centreId);
    }
    
    // Add loading animation
    const clickedCard = event.currentTarget;
    clickedCard.style.transform = 'scale(0.95)';
    clickedCard.style.opacity = '0.7';
    
    setTimeout(() => {
        window.location.href = url.toString();
    }, 200);
}

// Legacy function for backward compatibility
function filterByCentre(centreId) {
    selectCentre(centreId);
}

// Quick filter scroll functions
function scrollToSection(sectionType) {
    let targetId = '';
    switch(sectionType) {
        case 'long-term':
            targetId = 'long-term-section';
            break;
        case 'short-term':
            targetId = 'short-term-section';
            break;
        case 'non-nsqf':
            targetId = 'non-nsqf-section';
            break;
        case 'internship':
            targetId = 'internship-section';
            break;
    }
    
    const element = document.getElementById(targetId);
    if (element) {
        element.scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// Add smooth scroll animation for better UX
document.addEventListener('DOMContentLoaded', function() {
    // Add scroll animation to course cards
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe all course cards
    document.querySelectorAll('.course-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });

    // Add hover effects to centre cards
    document.querySelectorAll('.centre-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'translateY(-8px)';
                this.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.15)';
            }
        });

        card.addEventListener('mouseleave', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 10px 30px rgba(0, 0, 0, 0.1)';
            }
        });
    });
});
</script>

<!-- Courses Offered Section -->
<section class="py-5">
    <div class="container">
        
        <!-- Long-Term NSQF Courses -->
        <div class="course-section" id="long-term-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-certificate"></i>
                    Long Term NSQF Courses
                </h3>
            </div>
            
            <?php if ($result_long_term && $result_long_term->num_rows > 0): ?>
                <div class="row">
                    <?php while ($row = $result_long_term->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="course-card">
                            <div class="course-card-header">
                                <h4><?php echo htmlspecialchars($row["course_name"]); ?></h4>
                            </div>
                            <div class="course-card-body">
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
                                            <span class="info-label">Start Date</span>
                                            <span class="info-value"><?php echo date('d M Y', strtotime($row["start_date"])); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <div>
                                            <span class="info-label">End Date</span>
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
                                    <a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-primary-modern btn-modern">
                                        <i class="fas fa-paper-plane"></i> Apply Now
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No Long Term NSQF Courses Available</h4>
                    <p>Please check back later for upcoming courses.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Short-Term NSQF Courses -->
        <div class="course-section" id="short-term-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-award"></i>
                    Short Term NSQF Courses
                </h3>
            </div>
            
            <?php if ($result_short_term && $result_short_term->num_rows > 0): ?>
                <div class="row">
                    <?php while ($row = $result_short_term->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="course-card">
                            <div class="course-card-header">
                                <h4><?php echo htmlspecialchars($row["course_name"]); ?></h4>
                            </div>
                            <div class="course-card-body">
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
                                            <span class="info-label">Start Date</span>
                                            <span class="info-value"><?php echo date('d M Y', strtotime($row["start_date"])); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <div>
                                            <span class="info-label">End Date</span>
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
                                    <a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-primary-modern btn-modern">
                                        <i class="fas fa-paper-plane"></i> Apply Now
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No Short Term NSQF Courses Available</h4>
                    <p>Please check back later for upcoming courses.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Short-Term Non-NSQF Courses -->
        <div class="course-section" id="non-nsqf-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-laptop-code"></i>
                    Short-Term Non-NSQF Courses
                </h3>
            </div>
            
            <?php if ($result_non_nsqf && $result_non_nsqf->num_rows > 0): ?>
                <div class="row">
                    <?php while ($row = $result_non_nsqf->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="course-card">
                            <div class="course-card-header">
                                <h4><?php echo htmlspecialchars($row["course_name"]); ?></h4>
                            </div>
                            <div class="course-card-body">
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
                                            <span class="info-label">Start Date</span>
                                            <span class="info-value"><?php echo date('d M Y', strtotime($row["start_date"])); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <div>
                                            <span class="info-label">End Date</span>
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
                                    <a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-primary-modern btn-modern">
                                        <i class="fas fa-paper-plane"></i> Apply Now
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No Short-Term Non-NSQF Courses Available</h4>
                    <p>Please check back later for upcoming courses.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Internship Programs & Boot Camps -->
        <div class="course-section" id="internship-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-rocket"></i>
                    Internship Programs & Boot Camps
                </h3>
            </div>
            
            <?php if ($result_internship && $result_internship->num_rows > 0): ?>
                <div class="row">
                    <?php while ($row = $result_internship->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="course-card">
                            <div class="course-card-header">
                                <h4><?php echo htmlspecialchars($row["course_name"]); ?></h4>
                            </div>
                            <div class="course-card-body">
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
                                            <span class="info-label">Start Date</span>
                                            <span class="info-value"><?php echo date('d M Y', strtotime($row["start_date"])); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <div>
                                            <span class="info-label">End Date</span>
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
                                    <a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-success-modern btn-modern">
                                        <i class="fas fa-paper-plane"></i> Apply Now
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No Internship Programs Available</h4>
                    <p>Please check back later for upcoming programs.</p>
                </div>
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
                    <li class="mb-2"><i class="fas fa-phone-alt me-2 text-warning"></i> 0674-2960354</li>
                    <li class="mb-2"><i class="fas fa-envelope me-2 text-warning"></i> dir-bbsr@nielit.gov.in</li>
                    <li class="mb-2"><i class="fas fa-clock me-2 text-warning"></i> Mon-Fri: 09:00 AM – 5:30 PM</li>
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

</body>
</html>

<?php
// Close the database connection
$conn->close();
?>
