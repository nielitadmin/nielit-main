<?php
/**
 * Public registration pages — top bar + navbar (matches register.php).
 * Set $registration_page_title before including.
 */
require_once __DIR__ . '/../../includes/institute_branding.php';
$pageTitle = $registration_page_title ?? 'Student Registration';
$navActive = $registration_nav_active ?? 'registration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - NIELIT Bhubaneswar</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #0a1628;
            --accent-gold: #f59e0b;
            --light-bg: #fafaf8;
            --text-dark: #0f172a;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-dark);
        }
        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; }
        .top-bar {
            background-color: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 8px 0;
            font-size: 0.85rem;
        }
        .gov-logos img { height: 45px; width: auto; }
        .navbar {
            background-color: var(--primary-blue);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            padding: 0.5rem 1rem;
        }
        .navbar-brand { font-weight: 700; font-size: 1.2rem; color: #fff !important; }
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active { color: var(--accent-gold) !important; }
    </style>
    <?php if (!empty($registration_extra_head)) { echo $registration_extra_head; } ?>
</head>
<body>

<div class="top-bar">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex align-items-center justify-content-md-start justify-content-center text-header-group">
                <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo" class="me-3" style="height: 50px;">
                <div>
                    <div class="fw-bold text-primary d-none d-sm-block"><?php echo htmlspecialchars(INSTITUTE_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="fw-bold text-dark"><?php echo htmlspecialchars(INSTITUTE_NAME_EN); ?></div>
                </div>
            </div>
            <div class="col-md-4 d-flex justify-content-md-end justify-content-center gov-logos">
                <div class="text-end me-3 d-none d-lg-block">
                    <small class="d-block text-secondary d-none d-md-block"><?php echo htmlspecialchars(MINISTRY_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></small>
                    <small class="d-block fw-bold text-secondary"><?php echo htmlspecialchars(MINISTRY_NAME_EN, ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
                <img src="<?php echo APP_URL; ?>/assets/images/National-Emblem.png" alt="Gov India" style="height: 50px;">
            </div>
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="<?php echo APP_URL; ?>/index.php">
            <i class="fas fa-university me-2"></i> NIELIT
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link<?php echo $navActive === 'home' ? ' active' : ''; ?>" href="<?php echo APP_URL; ?>/index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link<?php echo $navActive === 'courses' ? ' active' : ''; ?>" href="<?php echo APP_URL; ?>/public/courses.php">Courses</a></li>
                <li class="nav-item"><a class="nav-link<?php echo $navActive === 'registration' ? ' active' : ''; ?>" href="#">Registration</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/student/login.php">Student Portal</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/public/contact.php">Contact</a></li>
            </ul>
        </div>
    </div>
</nav>
