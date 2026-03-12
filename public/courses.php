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
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <!-- Filter Card -->
                <div class="filter-card" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2.5rem; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1); border: 1px solid rgba(255, 255, 255, 0.2);">
                    
                    <!-- Filter Header -->
                    <div class="text-center mb-4">
                        <div class="filter-icon" style="width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                            <i class="fas fa-map-marker-alt" style="font-size: 2rem; color: white;"></i>
                        </div>
                        <h3 style="color: #2d3748; font-weight: 700; margin-bottom: 0.5rem; font-family: 'Poppins', sans-serif;">Find Your Training Centre</h3>
                        <p style="color: #718096; margin: 0; font-size: 1.1rem;">Discover courses available at different NIELIT centres across India</p>
                    </div>

                    <!-- Filter Form -->
                    <div class="filter-form">
                        <label for="centreFilter" class="form-label" style="font-weight: 600; color: #4a5568; margin-bottom: 1rem; display: flex; align-items: center; font-size: 1.1rem;">
                            <i class="fas fa-filter me-2" style="color: #667eea;"></i>
                            Select Training Centre
                        </label>
                        
                        <div class="select-wrapper" style="position: relative;">
                            <select id="centreFilter" class="form-select modern-select" onchange="filterByCentre(this.value)" style="
                                padding: 1rem 1.5rem;
                                font-size: 1.1rem;
                                border: 2px solid #e2e8f0;
                                border-radius: 15px;
                                background: white;
                                color: #2d3748;
                                font-weight: 500;
                                transition: all 0.3s ease;
                                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
                                appearance: none;
                                background-image: url('data:image/svg+xml,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;%23667eea&quot;><path d=&quot;M7 10l5 5 5-5z&quot;/></svg>');
                                background-repeat: no-repeat;
                                background-position: right 1rem center;
                                background-size: 1.5rem;
                                padding-right: 3.5rem;
                            ">
                                <option value="0" <?php echo $centre_filter == 0 ? 'selected' : ''; ?>>🌟 All Training Centres</option>
                                <?php 
                                if ($result_centres && $result_centres->num_rows > 0) {
                                    // Reset the result pointer
                                    $result_centres->data_seek(0);
                                    while ($centre = $result_centres->fetch_assoc()) {
                                        $selected = ($centre_filter == $centre['id']) ? 'selected' : '';
                                        echo '<option value="' . $centre['id'] . '" ' . $selected . '>📍 ' . htmlspecialchars($centre['name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Filter Stats -->
                        <div class="filter-stats mt-4" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border-radius: 15px; border: 1px solid #e2e8f0;">
                            <div class="stat-item text-center">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #667eea; margin-bottom: 0.25rem;">
                                    <?php 
                                    $total_courses = 0;
                                    if ($result_long_term) $total_courses += $result_long_term->num_rows;
                                    if ($result_short_term) $total_courses += $result_short_term->num_rows;
                                    if ($result_non_nsqf) $total_courses += $result_non_nsqf->num_rows;
                                    if ($result_internship) $total_courses += $result_internship->num_rows;
                                    echo $total_courses;
                                    ?>
                                </div>
                                <div style="font-size: 0.9rem; color: #718096; font-weight: 500;">Available Courses</div>
                            </div>
                            <div class="stat-divider" style="width: 1px; height: 40px; background: linear-gradient(to bottom, transparent, #cbd5e0, transparent);"></div>
                            <div class="stat-item text-center">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #48bb78; margin-bottom: 0.25rem;">
                                    <?php 
                                    $centres_result = $conn->query("SELECT COUNT(*) as count FROM centres WHERE is_active = 1");
                                    $centres_count = $centres_result ? $centres_result->fetch_assoc()['count'] : 0;
                                    echo $centres_count;
                                    ?>
                                </div>
                                <div style="font-size: 0.9rem; color: #718096; font-weight: 500;">Training Centres</div>
                            </div>
                            <div class="stat-divider" style="width: 1px; height: 40px; background: linear-gradient(to bottom, transparent, #cbd5e0, transparent);"></div>
                            <div class="stat-item text-center">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #ed8936; margin-bottom: 0.25rem;">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div style="font-size: 0.9rem; color: #718096; font-weight: 500;">Quality Training</div>
                            </div>
                        </div>

                        <!-- Quick Filter Buttons -->
                        <div class="quick-filters mt-4">
                            <div style="font-size: 0.9rem; color: #718096; margin-bottom: 1rem; font-weight: 500;">
                                <i class="fas fa-bolt me-1" style="color: #f6ad55;"></i>
                                Quick Filters:
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="quick-filter-btn" onclick="scrollToSection('long-term')" style="
                                    padding: 0.5rem 1rem;
                                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                    color: white;
                                    border: none;
                                    border-radius: 25px;
                                    font-size: 0.85rem;
                                    font-weight: 500;
                                    transition: all 0.3s ease;
                                    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
                                ">
                                    <i class="fas fa-certificate me-1"></i> Long Term NSQF
                                </button>
                                <button class="quick-filter-btn" onclick="scrollToSection('short-term')" style="
                                    padding: 0.5rem 1rem;
                                    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
                                    color: white;
                                    border: none;
                                    border-radius: 25px;
                                    font-size: 0.85rem;
                                    font-weight: 500;
                                    transition: all 0.3s ease;
                                    box-shadow: 0 4px 15px rgba(72, 187, 120, 0.2);
                                ">
                                    <i class="fas fa-award me-1"></i> Short Term NSQF
                                </button>
                                <button class="quick-filter-btn" onclick="scrollToSection('non-nsqf')" style="
                                    padding: 0.5rem 1rem;
                                    background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
                                    color: white;
                                    border: none;
                                    border-radius: 25px;
                                    font-size: 0.85rem;
                                    font-weight: 500;
                                    transition: all 0.3s ease;
                                    box-shadow: 0 4px 15px rgba(237, 137, 54, 0.2);
                                ">
                                    <i class="fas fa-laptop-code me-1"></i> Non-NSQF
                                </button>
                                <button class="quick-filter-btn" onclick="scrollToSection('internship')" style="
                                    padding: 0.5rem 1rem;
                                    background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);
                                    color: white;
                                    border: none;
                                    border-radius: 25px;
                                    font-size: 0.85rem;
                                    font-weight: 500;
                                    transition: all 0.3s ease;
                                    box-shadow: 0 4px 15px rgba(159, 122, 234, 0.2);
                                ">
                                    <i class="fas fa-rocket me-1"></i> Internships
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Modern Select Hover Effects */
.modern-select:hover {
    border-color: #667eea !important;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15) !important;
    transform: translateY(-2px);
}

.modern-select:focus {
    border-color: #667eea !important;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1), 0 8px 25px rgba(102, 126, 234, 0.15) !important;
    outline: none !important;
    transform: translateY(-2px);
}

/* Quick Filter Button Hover Effects */
.quick-filter-btn:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
}

/* Filter Card Animation */
.filter-card {
    animation: filterCardSlideIn 0.8s ease-out;
}

@keyframes filterCardSlideIn {
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
    .filter-card {
        padding: 2rem 1.5rem !important;
        margin: 0 1rem;
    }
    
    .filter-stats {
        flex-direction: column !important;
        gap: 1rem;
    }
    
    .stat-divider {
        display: none !important;
    }
    
    .quick-filters .d-flex {
        justify-content: center;
    }
}
</style>

<script>
function filterByCentre(centreId) {
    const url = new URL(window.location.href);
    if (centreId == 0) {
        url.searchParams.delete('centre');
    } else {
        url.searchParams.set('centre', centreId);
    }
    window.location.href = url.toString();
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
