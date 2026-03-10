<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// DEBUG: Log what's in session when this page loads
error_log("=== registration_success.php loaded ===");
error_log("SESSION keys: " . implode(', ', array_keys($_SESSION)));
error_log("student_id in session: " . ($_SESSION['student_id'] ?? 'NOT SET'));
error_log("success in session: " . (isset($_SESSION['success']) ? 'SET' : 'NOT SET'));

// Check if registration was successful
if (!isset($_SESSION['success']) || !isset($_SESSION['student_id'])) {
    error_log("Session check FAILED - redirecting back to courses");
    // Redirect to courses page (not register.php which needs course param)
    header("Location: " . APP_URL . "/public/courses.php");
    exit();
}

$student_id      = $_SESSION['student_id'];
$password        = $_SESSION['student_password']  ?? '';
$success_message = $_SESSION['success'];
$student_email   = $_SESSION['student_email']     ?? null;
$course_name     = $_SESSION['course_name']       ?? null;
$training_center = $_SESSION['training_center']   ?? null;

// Clear session variables after reading them
unset(
    $_SESSION['student_id'],
    $_SESSION['student_password'],
    $_SESSION['success'],
    $_SESSION['student_email'],
    $_SESSION['course_name'],
    $_SESSION['training_center']
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - NIELIT Bhubaneswar</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #0d47a1;
            --secondary-blue: #1565c0;
            --accent-gold: #ffc107;
            --light-bg: #f8f9fa;
            --text-dark: #212529;
            --text-muted: #6c757d;
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
        }

        .nav-link:hover { color: var(--accent-gold) !important; }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            border-radius: 8px;
        }

        .dropdown-item:hover {
            background-color: #e3f2fd;
            color: var(--primary-blue);
        }

        footer {
            background-color: #1a202c;
            color: #cbd5e0;
        }

        footer h5 {
            color: #fff;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        footer a {
            color: #cbd5e0;
            text-decoration: none;
            display: block;
            margin-bottom: 8px;
            transition: color 0.2s;
        }

        footer a:hover { color: var(--accent-gold); }

        .copyright-bar {
            background-color: #111827;
            padding: 15px 0;
            border-top: 1px solid #2d3748;
        }

        .success-container { min-height: 70vh; padding: 60px 0; }

        .success-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 50px;
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }

        .success-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
        }

        .success-icon-header {
            width: 100px; height: 100px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
            animation: scaleIn 0.6s ease-out;
        }

        @keyframes scaleIn {
            from { transform: scale(0); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }

        .success-icon-header i { font-size: 50px; color: white; }

        .success-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }

        .success-message {
            font-size: 1.1rem;
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: 40px;
        }

        .credentials-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-radius: 12px;
            padding: 35px;
            margin: 35px 0;
            border: 2px solid #bae6fd;
        }

        .credential-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 2px solid rgba(186, 230, 253, 0.5);
        }

        .credential-item:last-child { border-bottom: none; }

        .credential-label {
            font-weight: 700;
            color: var(--primary-blue);
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .credential-value {
            font-size: 20px;
            font-weight: 800;
            color: #0c4a6e;
            font-family: 'Courier New', monospace;
            background: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 2px solid #e0f2fe;
        }

        .alert-box {
            border-radius: 12px;
            padding: 20px 24px;
            margin: 25px 0;
            border-left: 5px solid;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .alert-success-box {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-left-color: #10b981;
        }

        .alert-warning-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left-color: #f59e0b;
        }

        .alert-box i { margin-right: 12px; font-size: 18px; }
        .alert-box p { margin: 0; font-size: 14px; line-height: 1.6; }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 40px;
        }

        .btn-modern {
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(13, 71, 161, 0.3);
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 71, 161, 0.4);
            color: white;
        }

        .btn-outline-modern {
            background: white;
            color: var(--primary-blue);
            border: 2px solid var(--primary-blue);
        }

        .btn-outline-modern:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
        }

        .copy-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .copy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        @media (max-width: 768px) {
            .success-card { padding: 35px 25px; }
            .success-title { font-size: 2rem; }
            .action-buttons { flex-direction: column; }
            .btn-modern { width: 100%; justify-content: center; }
            .credential-item { flex-direction: column; gap: 12px; text-align: center; }
            .credential-value { font-size: 16px; }
        }
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="top-bar">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex align-items-center justify-content-md-start justify-content-center">
                <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo" class="me-3" style="height: 50px;">
                <div>
                    <div class="fw-bold text-primary d-none d-sm-block">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर</div>
                    <div class="fw-bold text-dark">National Institute of Electronics &amp; Information Technology, Bhubaneswar</div>
                </div>
            </div>
            <div class="col-md-4 d-flex justify-content-md-end justify-content-center">
                <div class="text-end me-3 d-none d-lg-block">
                    <small class="d-block fw-bold text-secondary">Ministry of Electronics &amp; IT</small>
                    <small class="d-block text-secondary">Government of India</small>
                </div>
                <img src="<?php echo APP_URL; ?>/assets/images/National-Emblem.png" alt="Gov India" style="height: 50px;">
            </div>
        </div>
    </div>
</div>

<!-- NAVBAR -->
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
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Student Zone</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/public/courses.php">Courses Offered</a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/student/login.php">Student Portal</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link" href="<?php echo APP_URL; ?>/public/contact.php">Contact</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- SUCCESS CONTENT -->
<section class="success-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="success-card">
                    <div class="success-icon-header">
                        <i class="fas fa-check"></i>
                    </div>

                    <h1 class="success-title text-center">Registration Successful!</h1>
                    <p class="success-message text-center">
                        Congratulations! Your registration has been completed successfully.
                        Please save your credentials below for future login.
                    </p>

                    <div class="credentials-box">
                        <div class="credential-item">
                            <span class="credential-label">Student ID</span>
                            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                                <span class="credential-value" id="studentId"><?php echo htmlspecialchars($student_id); ?></span>
                                <button class="copy-btn" onclick="copyToClipboard('studentId', this)">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>

                        <div class="credential-item">
                            <span class="credential-label">Password</span>
                            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                                <span class="credential-value" id="studentPassword"><?php echo htmlspecialchars($password); ?></span>
                                <button class="copy-btn" onclick="copyToClipboard('studentPassword', this)">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>

                        <?php if ($course_name): ?>
                        <div class="credential-item">
                            <span class="credential-label">Course</span>
                            <span class="credential-value" style="font-size:14px;font-family:Arial,sans-serif;">
                                <?php echo htmlspecialchars($course_name); ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <?php if ($training_center): ?>
                        <div class="credential-item">
                            <span class="credential-label">Training Centre</span>
                            <span class="credential-value" style="font-size:14px;font-family:Arial,sans-serif;">
                                <?php echo htmlspecialchars($training_center); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($student_email): ?>
                    <div class="alert-box alert-success-box">
                        <i class="fas fa-envelope-circle-check" style="color:#10b981;"></i>
                        <p style="color:#065f46;">
                            <strong>Email Sent:</strong> A confirmation email with your credentials has been sent to
                            <strong><?php echo htmlspecialchars($student_email); ?></strong>.
                            Please check your inbox (and spam folder).
                        </p>
                    </div>
                    <?php endif; ?>

                    <div class="alert-box alert-warning-box">
                        <i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i>
                        <p style="color:#78350f;">
                            <strong>Important:</strong> Please save these credentials securely.
                            You will need them to access your student portal.
                            Take a screenshot or write them down in a safe place.
                        </p>
                    </div>

                    <div class="action-buttons">
                        <a href="<?php echo APP_URL; ?>/student/login.php" class="btn-modern btn-primary-modern">
                            <i class="fas fa-sign-in-alt"></i> Login to Portal
                        </a>
                        <a href="<?php echo APP_URL; ?>/index.php" class="btn-modern btn-outline-modern">
                            <i class="fas fa-home"></i> Go to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
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
                    <li><a href="<?php echo APP_URL; ?>/public/courses.php"><i class="fas fa-chevron-right me-2 small"></i>Courses</a></li>
                    <li><a href="<?php echo APP_URL; ?>/student/login.php"><i class="fas fa-chevron-right me-2 small"></i>Student Portal</a></li>
                    <li><a href="<?php echo APP_URL; ?>/public/contact.php"><i class="fas fa-chevron-right me-2 small"></i>Contact Us</a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-md-12">
                <h5>Contact Info</h5>
                <p class="small text-muted mb-3">National Institute of Electronics &amp; Information Technology, Bhubaneswar</p>
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
                <div class="col-md-6 text-md-start">© 2025 NIELIT Bhubaneswar. All Rights Reserved.</div>
                <div class="col-md-6 text-md-end">Designed &amp; Developed by NIELIT Team</div>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function copyToClipboard(elementId, btn) {
    const text = document.getElementById(elementId).textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.style.background = '#059669';
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.style.background = '';
        }, 2000);
    }).catch(() => {
        // Fallback for older browsers
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    });
}
</script>
</body>
</html>