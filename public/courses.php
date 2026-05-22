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

// 1. Degree / Diploma Courses / PG
$sql_degree_pg = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                  FROM courses 
                  LEFT JOIN centres ON courses.centre_id = centres.id 
                  WHERE (courses.category = 'Degree / Diploma Courses / PG' OR courses.course_type = 'Degree / Diploma Courses / PG' OR courses.category = 'Degree / Diploma / PG' OR courses.course_type = 'Degree / Diploma / PG') AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// 2. Skill Based (Long Term) Courses > 500 hrs
$sql_skill_long = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                   FROM courses 
                   LEFT JOIN centres ON courses.centre_id = centres.id 
                   WHERE (courses.category = 'Skill Based (Long Term) Courses > 500 hrs' OR courses.course_type = 'Skill Based (Long Term) Courses > 500 hrs' OR courses.category = 'Skill Based (Long Term) >500 hrs' OR courses.course_type = 'Skill Based (Long Term) >500 hrs') AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// 3. Skill Based (Short Term) Courses >90 hrs to <=500 hrs
$sql_skill_short = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                    FROM courses 
                    LEFT JOIN centres ON courses.centre_id = centres.id 
                    WHERE (courses.category = 'Skill Based (Short Term) Courses >90 hrs to <=500 hrs' OR courses.course_type = 'Skill Based (Short Term) Courses >90 hrs to <=500 hrs' OR courses.category = 'Skill Based (Short Term) 90-500 hrs' OR courses.course_type = 'Skill Based (Short Term) 90-500 hrs') AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// 4. Short Term Courses / Digital Competency Courses <= 90 hours
$sql_short_digital = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                      FROM courses 
                      LEFT JOIN centres ON courses.centre_id = centres.id 
                      WHERE (courses.category = 'Short Term Courses / Digital Competency Courses <= 90 hours' OR courses.course_type = 'Short Term Courses / Digital Competency Courses <= 90 hours' OR courses.category = 'Short Term / Digital Competency <=90 hrs' OR courses.course_type = 'Short Term / Digital Competency <=90 hrs') AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// 5. NIELIT HQ's Digital Literacy Courses (CCC / ECC / CCCP / BCC / ACC)
$sql_digital_lit = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                    FROM courses 
                    LEFT JOIN centres ON courses.centre_id = centres.id 
                    WHERE (courses.category = 'NIELIT HQ\\'s Digital Literacy Courses (CCC / ECC / CCCP / BCC / ACC)' OR courses.course_type = 'NIELIT HQ\\'s Digital Literacy Courses (CCC / ECC / CCCP / BCC / ACC)' OR courses.category = 'NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)' OR courses.course_type = 'NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)') AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// 6. Internship Program
$sql_internship = "SELECT courses.*, centres.name as centre_name, centres.city as centre_city, centres.state as centre_state 
                   FROM courses 
                   LEFT JOIN centres ON courses.centre_id = centres.id 
                   WHERE (courses.category = 'Internship Program' OR courses.course_type = 'Internship Program') AND (courses.link_published = 1 OR courses.link_published IS NULL)" . $centre_condition;

// Execute the queries - only the 6 specified categories
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

        .course-card-header {
            background: linear-gradient(135deg, #0a1628 0%, #112240 100%) !important;
            border-bottom: 1px solid rgba(245,158,11,0.25);
        }

        .course-card-header h4 {
            color: #fff !important;
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

        .course-card-footer {
            background: #f8fafc !important;
        }

        .section-header {
            border-left-color: var(--gold) !important;
        }

        .course-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .course-info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <!-- Courses Offered Section -->
    <section class="py-5">
        <div class="container">
            <!-- Internship Programs & Boot Camps -->
            <div class="course-section" id="internship-section">
                <div class="section-header mb-4">
                    <h3><i class="fas fa-rocket"></i> Internship Programs & Boot Camps</h3>
                </div>
                <?php if ($result_internship && $result_internship->num_rows > 0): ?>
                    <div class="row g-4">
                        <?php while ($row = $result_internship->fetch_assoc()): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="course-card">
                                    <div class="course-card-header">
                                        <h4><?php echo htmlspecialchars($row["course_name"]); ?></h4>
                                    </div>
                                    <div class="course-card-body p-3">
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
                                                    <span class="info-label">Fees</span>
                                                    <span class="info-value">â‚¹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="course-card-footer p-3">
                                        <?php if (!empty($row["apply_link"])): ?>
                                            <a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn btn-primary btn-sm w-100">
                                                <i class="fas fa-paper-plane"></i> Apply Now
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><h5>No Internship Programs Available</h5></div>
                <?php endif; ?>
            </div>

            <hr class="my-5">

            <!-- Degree / Diploma / PG -->
            <div class="course-section" id="degree-pg-section">
                <div class="section-header mb-4">
                    <h3><i class="fas fa-graduation-cap"></i> Degree / Diploma / Postgraduate Courses</h3>
                </div>
                <?php if ($result_degree_pg && $result_degree_pg->num_rows > 0): ?>
                    <div class="row g-4">
                        <?php while ($row = $result_degree_pg->fetch_assoc()): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="course-card">
                                    <div class="course-card-header">
                                        <h4><?php echo htmlspecialchars($row["course_name"]); ?></h4>
                                    </div>
                                    <div class="course-card-body p-3">
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
                                                    <span class="info-label">Fees</span>
                                                    <span class="info-value">â‚¹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="course-card-footer p-3">
                                        <?php if (!empty($row["apply_link"])): ?>
                                            <a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn btn-primary btn-sm w-100">
                                                <i class="fas fa-paper-plane"></i> Apply Now
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><h5>No Degree / Diploma / PG Courses Available</h5></div>
                <?php endif; ?>
            </div>

            <!-- Skill Based (Long Term) -->
            <div class="course-section" id="skill-long-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-tools"></i>
                    Skill Based (Long Term) Courses (> 500 hrs)
                </h3>
            </div>
            <?php if ($result_skill_long && $result_skill_long->num_rows > 0): ?>
                <div class="row">
                    <?php while ($row = $result_skill_long->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="course-card">
                                <div class="course-card-header"><h4><?php echo htmlspecialchars($row["course_name"]); ?> <?php if (!empty($row["is_nsqf"]) && $row["is_nsqf"]==1): ?><span class="badge bg-info" style="margin-left:8px;">NSQF</span><?php endif; ?></h4></div>
                                <div class="course-card-body">
                                    <div class="course-info-grid">
                                        <div class="info-item"><i class="fas fa-clock"></i><div><span class="info-label">Duration</span><span class="info-value"><?php echo htmlspecialchars($row["duration"]); ?></span></div></div>
                                        <div class="info-item"><i class="fas fa-rupee-sign"></i><div><span class="info-label">Fees</span><span class="info-value">â‚¹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span></div></div>
                                    </div>
                                </div>
                                <div class="course-card-footer">
                                    <?php if (!empty($row["apply_link"])): ?><a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-primary-modern btn-modern"><i class="fas fa-paper-plane"></i> Apply Now</a><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><h4>No Skill Based (Long) Courses Available</h4><p>Check back later.</p></div>
            <?php endif; ?>
        </div>

        <!-- Skill Based (Short Term) -->
        <div class="course-section" id="skill-short-section">
            <div class="section-header"><h3><i class="fas fa-tools"></i>Skill Based (Short Term) Courses (90-500 hrs)</h3></div>
            <?php if ($result_skill_short && $result_skill_short->num_rows > 0): ?>
                <div class="row">
                    <?php while ($row = $result_skill_short->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="course-card">
                                <div class="course-card-header"><h4><?php echo htmlspecialchars($row["course_name"]); ?> <?php if (!empty($row["is_nsqf"]) && $row["is_nsqf"]==1): ?><span class="badge bg-info" style="margin-left:8px;">NSQF</span><?php endif; ?></h4></div>
                                <div class="course-card-body">
                                    <div class="course-info-grid">
                                        <div class="info-item"><i class="fas fa-clock"></i><div><span class="info-label">Duration</span><span class="info-value"><?php echo htmlspecialchars($row["duration"]); ?></span></div></div>
                                        <div class="info-item"><i class="fas fa-rupee-sign"></i><div><span class="info-label">Fees</span><span class="info-value">â‚¹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span></div></div>
                                    </div>
                                </div>
                                <div class="course-card-footer"><?php if (!empty($row["apply_link"])): ?><a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-primary-modern btn-modern"><i class="fas fa-paper-plane"></i> Apply Now</a><?php endif; ?></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><h4>No Skill Based (Short) Courses Available</h4><p>Check back later.</p></div>
            <?php endif; ?>
        </div>

        <!-- Short Term / Digital Competency -->
        <div class="course-section" id="short-digital-section">
            <div class="section-header"><h3><i class="fas fa-laptop-code"></i>Short Term / Digital Competency Courses (<= 90 hrs)</h3></div>
            <?php if ($result_short_digital && $result_short_digital->num_rows > 0): ?>
                <div class="row">
                    <?php while ($row = $result_short_digital->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="course-card">
                                <div class="course-card-header"><h4><?php echo htmlspecialchars($row["course_name"]); ?> <?php if (!empty($row["is_nsqf"]) && $row["is_nsqf"]==1): ?><span class="badge bg-info" style="margin-left:8px;">NSQF</span><?php endif; ?></h4></div>
                                <div class="course-card-body"><div class="course-info-grid"><div class="info-item"><i class="fas fa-clock"></i><div><span class="info-label">Duration</span><span class="info-value"><?php echo htmlspecialchars($row["duration"]); ?></span></div></div></div></div>
                                <div class="course-card-footer"><?php if (!empty($row["apply_link"])): ?><a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-primary-modern btn-modern"><i class="fas fa-paper-plane"></i> Apply Now</a><?php endif; ?></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><h4>No Short Term / Digital Competency Courses Available</h4><p>Check back later.</p></div>
            <?php endif; ?>
        </div>

        <!-- NIELIT HQ Digital Literacy Courses -->
        <div class="course-section" id="digital-lit-section">
            <div class="section-header"><h3><i class="fas fa-keyboard"></i>NIELIT HQ Digital Literacy Courses (CCC / ECC / BCC / ACC)</h3></div>
            <?php if ($result_digital_lit && $result_digital_lit->num_rows > 0): ?>
                <div class="row">
                    <?php while ($row = $result_digital_lit->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="course-card">
                                <div class="course-card-header"><h4><?php echo htmlspecialchars($row["course_name"]); ?> <?php if (!empty($row["is_nsqf"]) && $row["is_nsqf"]==1): ?><span class="badge bg-info" style="margin-left:8px;">NSQF</span><?php endif; ?></h4></div>
                                <div class="course-card-body"><div class="course-info-grid"><div class="info-item"><i class="fas fa-user-graduate"></i><div><span class="info-label">Eligibility</span><span class="info-value"><?php echo htmlspecialchars($row["eligibility"]); ?></span></div></div></div></div>
                                <div class="course-card-footer"><?php if (!empty($row["apply_link"])): ?><a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-primary-modern btn-modern"><i class="fas fa-paper-plane"></i> Apply Now</a><?php endif; ?></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><h4>No Digital Literacy Courses Available</h4><p>Check back later.</p></div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectCentre(centreId) {
            const url = new URL(window.location.href);
            if (centreId == 0) {
                url.searchParams.delete('centre');
            } else {
                url.searchParams.set('centre', centreId);
            }
            window.location.href = url.toString();
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
