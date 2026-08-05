<?php
require_once __DIR__ . '/../../includes/institute_branding.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Student Portal'; ?> - NIELIT Bhubaneswar</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <?php
    $studentPortalCssPath = __DIR__ . '/../../assets/css/student-portal.css';
    $studentToastCssPath = __DIR__ . '/../../assets/css/toast-notifications.css';
    $studentPortalCssVer = is_file($studentPortalCssPath) ? filemtime($studentPortalCssPath) : time();
    $studentToastCssVer = is_file($studentToastCssPath) ? filemtime($studentToastCssPath) : time();
    ?>
    <link href="../assets/css/student-portal.css?v=<?php echo $studentPortalCssVer; ?>" rel="stylesheet">
    <link href="../assets/css/toast-notifications.css?v=<?php echo $studentToastCssVer; ?>" rel="stylesheet">
    <script>
        document.documentElement.classList.add('student-portal-loading');
    </script>
</head>
<body class="student-portal-loading">
    <?php include __DIR__ . '/skeleton_loader.php'; ?>
    <!-- Top Header -->
    <div class="top-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8 d-flex align-items-center justify-content-md-start justify-content-center">
                    <img src="../assets/images/bhubaneswar_logo.png" alt="NIELIT Logo" class="me-3" style="height: 50px;">
                    <div>
                        <div class="fw-bold text-primary d-none d-sm-block" style="font-size: 0.85rem;"><?php echo htmlspecialchars(INSTITUTE_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?php echo htmlspecialchars(INSTITUTE_NAME_EN); ?></div>
                    </div>
                </div>
                <div class="col-md-4 d-flex justify-content-md-end justify-content-center">
                    <div class="text-end me-3 d-none d-lg-block">
                        <small class="d-block text-secondary d-none d-md-block"><?php echo htmlspecialchars(MINISTRY_NAME_HI, ENT_QUOTES, 'UTF-8'); ?></small>
                        <small class="d-block fw-bold text-secondary"><?php echo htmlspecialchars(MINISTRY_NAME_EN, ENT_QUOTES, 'UTF-8'); ?></small>
                    </div>
                    <img src="../assets/images/National-Emblem.png" alt="Gov India" style="height: 50px;">
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top student-navbar">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-user-graduate me-2"></i> Student Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#studentNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="studentNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="attendance.php">
                            <i class="fas fa-calendar-check"></i> Attendance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="online_classes.php">
                            <i class="fas fa-video"></i> Online Classes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="class_timetable.php">
                            <i class="fas fa-calendar-alt"></i> Timetable
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="fees.php">
                            <i class="fas fa-rupee-sign"></i> Fees
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['student_name'] ?? 'Account'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="edit_profile.php"><i class="fas fa-user-edit me-2"></i> Edit Profile</a></li>
                            <li><a class="dropdown-item" href="certificates.php"><i class="fas fa-certificate me-2"></i> Certificates</a></li>
                            <li><a class="dropdown-item" href="placement.php"><i class="fas fa-briefcase me-2"></i> My Placement</a></li>
                            <li><a class="dropdown-item" href="support.php"><i class="fas fa-headset me-2"></i> Support</a></li>
                            <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i> Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
