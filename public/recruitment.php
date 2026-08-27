<?php
require_once __DIR__ . '/../includes/maintenance_check.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/navigation_helper.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/public_theme_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

ensureRecruitmentTables($conn);
$jobs = recruitmentListOpenJobs($conn);
$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);
$page_title = 'Recruitment - NIELIT Bhubaneswar';
injectThemeCSS($active_theme);
emitPublicThemeHead($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/public-theme.css">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
    <style>
        .rec-card { border:0; border-radius:16px; box-shadow:0 10px 28px rgba(15,23,42,.08); height:100%; }
        .rec-card:hover { transform:translateY(-3px); box-shadow:0 14px 32px rgba(15,23,42,.12); }
        .rec-meta { color:#64748b; font-size:.9rem; }
    </style>
<?php require_once __DIR__ . '/../includes/public_skeleton_helper.php'; public_skeleton_render_head(); ?>
</head>
<body class="public-page-loading public-site">
<?php public_skeleton_render_loader('generic'); ?>
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
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo app_url('index'); ?>"><i class="fas fa-university me-2"></i> NIELIT</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php echo getPublicSiteNavigationHtml($conn, 'recruitment.php'); ?>
                </ul>
            </div>
        </div>
    </nav>
    <section class="page-header">
        <div class="container text-center">
            <h1 class="mb-0">Recruitment</h1>
            <p class="lead mb-0 mt-2">Open job opportunities at NIELIT Bhubaneswar</p>
        </div>
    </section>
    <section class="py-5">
        <div class="container">
            <?php if (empty($jobs)): ?>
                <div class="alert alert-info">No job openings are accepting applications right now. Please check back later.</div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($jobs as $job): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card rec-card">
                                <div class="card-body d-flex flex-column">
                                    <div class="mb-2">
                                        <span class="badge bg-success">Open</span>
                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars((string) $job['post_type']); ?></span>
                                    </div>
                                    <h5 class="mb-2"><?php echo htmlspecialchars((string) $job['title']); ?></h5>
                                    <p class="rec-meta mb-1"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars(recruitmentDisplay($job['location'] ?? '')); ?></p>
                                    <p class="rec-meta mb-1"><i class="fas fa-users me-1"></i> <?php echo (int) $job['vacancies']; ?> vacancy(ies)</p>
                                    <p class="rec-meta mb-3"><i class="fas fa-calendar me-1"></i> Last date: <?php echo htmlspecialchars(recruitmentFormatDate($job['last_date'] ?? '')); ?></p>
                                    <p class="small text-muted flex-grow-1"><?php
                                        $elig = trim((string) ($job['eligibility'] ?? ''));
                                        echo htmlspecialchars(strlen($elig) > 140 ? substr($elig, 0, 137) . '…' : $elig);
                                    ?></p>
                                    <a class="btn btn-primary mt-2" href="<?php echo htmlspecialchars(app_url('public/recruitment_apply') . '?job=' . (int) $job['id']); ?>">
                                        View &amp; apply
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <footer class="footer-section pt-5">
        <div class="copyright-bar text-center text-muted small">
            <div class="container py-3">© <?php echo date('Y'); ?> NIELIT Bhubaneswar. All Rights Reserved.</div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php public_skeleton_render_script(); ?>
</body>
</html>
