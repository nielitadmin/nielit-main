<?php
// Start the session
session_start();

// Include the database connection
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/institute_branding.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';
require_once __DIR__ . '/../includes/google_auth.php';

if (function_exists('repairEnrollmentStatusMismatch')) {
    repairEnrollmentStatusMismatch($conn);
}

// Check if the student is already logged in
if (isset($_SESSION['student_id'])) {
    if (!empty($_SESSION['oc_join_redirect_token'])) {
        $t = (string) $_SESSION['oc_join_redirect_token'];
        unset($_SESSION['oc_join_redirect_token']);
        header('Location: join_class.php?t=' . rawurlencode($t));
        exit;
    }
    header("Location: dashboard.php");
    exit;
}

/**
 * Finish student login after identity is verified (password or Google).
 */
function completeStudentPortalLogin(mysqli $conn, string $student_id, string $display_name, ?string &$error_message): bool
{
    $enrollments = getEnrollmentsForStudentId($conn, $student_id);
    $has_active = false;
    $all_rejected = !empty($enrollments);

    foreach ($enrollments as $enrollment) {
        $status = strtolower($enrollment['status'] ?? '');
        if (in_array($status, ['active', 'approved'], true)) {
            $has_active = true;
            $all_rejected = false;
        }
        if (!in_array($status, ['rejected', 'cancelled'], true)) {
            $all_rejected = false;
        }
    }

    if (!$has_active) {
        $activeCheck = $conn->prepare("SELECT 1 FROM students
            WHERE student_id = ? AND LOWER(status) IN ('active', 'approved') LIMIT 1");
        if ($activeCheck) {
            $activeCheck->bind_param('s', $student_id);
            $activeCheck->execute();
            if ($activeCheck->get_result()->num_rows > 0) {
                $has_active = true;
                repairEnrollmentStatusMismatch($conn);
            }
            $activeCheck->close();
        }
    }

    if (!empty($enrollments) && $all_rejected && !$has_active) {
        $error_message = "Your registration has been rejected. Please contact admin for more information.";
        return false;
    }

    $_SESSION['student_id'] = $student_id;
    $_SESSION['student_name'] = $display_name;
    if (file_exists(__DIR__ . '/../includes/activity_logger.php')) {
        require_once __DIR__ . '/../includes/activity_logger.php';
        logActivity($conn, [
            'actor_type' => 'student',
            'actor_id' => (string) $student_id,
            'actor_name' => $display_name ?: $student_id,
            'action' => 'student_login',
            'entity_type' => 'student',
            'entity_id' => (string) $student_id,
            'entity_name' => $display_name ?: $student_id,
            'description' => 'Student "' . ($display_name ?: $student_id) . '" logged in.',
        ]);
    }
    if (!empty($_SESSION['oc_join_redirect_token'])) {
        $t = (string) $_SESSION['oc_join_redirect_token'];
        unset($_SESSION['oc_join_redirect_token']);
        header('Location: join_class.php?t=' . rawurlencode($t));
        exit;
    }
    header("Location: dashboard.php");
    exit;
}

$error_message = null;

// Google Sign-In
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['google_credential'])) {
    if (!GOOGLE_OAUTH_ENABLED) {
        $error_message = 'Google Sign-In is not configured yet.';
    } else {
        $payload = verifyGoogleIdToken((string) $_POST['google_credential'], GOOGLE_CLIENT_ID);
        if ($payload === null) {
            $error_message = 'Google sign-in verification failed. Please try again.';
        } else {
            $googleEmail = strtolower(trim((string) ($payload['email'] ?? '')));
            $match = $googleEmail !== '' ? findStudentLoginByEmail($conn, $googleEmail) : null;
            if (!$match || empty($match['student_id'])) {
                $error_message = 'No student account is linked to this Google email. Use the email from your registration, or log in with Student ID and password.';
            } else {
                completeStudentPortalLogin(
                    $conn,
                    (string) $match['student_id'],
                    (string) ($match['name'] ?? ''),
                    $error_message
                );
            }
        }
    }
}

// Password login (Student ID or Email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['google_credential'])) {
    $login_id = trim((string) ($_POST['login_id'] ?? $_POST['student_id'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $authenticated = false;
    $student_id = '';
    $display_name = '';

    if ($login_id === '' || $password === '') {
        $error_message = 'Please enter your Student ID or email and password.';
    } elseif (strpos($login_id, '@') !== false) {
        $candidates = findStudentLoginCandidatesByEmail($conn, $login_id);
        foreach ($candidates as $candidate) {
            $hash = (string) ($candidate['password'] ?? '');
            if ($hash !== '' && password_verify($password, $hash)) {
                $authenticated = true;
                $student_id = (string) $candidate['student_id'];
                $display_name = (string) ($candidate['name'] ?? '');
                break;
            }
        }
        // Also try any leftover legacy rows with same email that share password across course rows
        if (!$authenticated) {
            $emailKey = strtolower(trim($login_id));
            $stmt = $conn->prepare(
                'SELECT student_id, password, name FROM students WHERE LOWER(TRIM(email)) = ? ORDER BY id DESC'
            );
            if ($stmt) {
                $stmt->bind_param('s', $emailKey);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    if (!empty($row['password']) && password_verify($password, $row['password'])) {
                        $authenticated = true;
                        $student_id = (string) $row['student_id'];
                        $display_name = (string) ($row['name'] ?? '');
                        break;
                    }
                }
                $stmt->close();
            }
        }
    } else {
        $account = getAccountByStudentId($conn, $login_id);
        if ($account && !empty($account['password']) && password_verify($password, $account['password'])) {
            $authenticated = true;
            $student_id = (string) ($account['student_id'] ?? $login_id);
            $display_name = (string) ($account['name'] ?? '');
        } else {
            $sql = "SELECT student_id, password, name, status FROM students WHERE student_id = ? ORDER BY id DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $login_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    if (!empty($row['password']) && password_verify($password, $row['password'])) {
                        $authenticated = true;
                        $student_id = (string) $row['student_id'];
                        $display_name = (string) ($row['name'] ?? '');
                        break;
                    }
                }
                $stmt->close();
            }
        }
    }

    if (!$authenticated) {
        if ($error_message === null) {
            $error_message = "Invalid Student ID / Email or Password.";
        }
    } else {
        completeStudentPortalLogin($conn, $student_id, $display_name, $error_message);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - NIELIT Bhubaneswar</title>
    <?php
    $faviconFile = __DIR__ . '/../assets/images/favicon.ico';
    $faviconVer = is_file($faviconFile) ? filemtime($faviconFile) : time();
    ?>
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico?v=<?php echo $faviconVer; ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico?v=<?php echo $faviconVer; ?>" type="image/x-icon">
    <?php if (GOOGLE_OAUTH_ENABLED): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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

        body {
            font-family: 'DM Sans', 'Inter', sans-serif;
            background-color: var(--cream);
            color: var(--text);
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Sora', 'Poppins', sans-serif;
        }

        /* Top Bar */
        .top-bar {
            background-color: #fff;
            border-bottom: 1px solid var(--border);
            padding: 8px 0;
            font-size: 0.85rem;
        }

        /* Navbar */
        .navbar {
            background-color: var(--navy);
            border-bottom: 3px solid var(--gold);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            padding: 0.5rem 1rem;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: #fff !important;
        }

        .navbar-brand span {
            color: var(--gold);
        }

        .nav-link {
            color: rgba(255,255,255,0.82) !important;
            font-weight: 500;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: #fff !important;
        }

        .dropdown-menu {
            background-color: var(--navy-mid);
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            border-radius: 8px;
            margin-top: 10px;
        }

        .dropdown-item {
            color: rgba(255,255,255,0.82) !important;
        }

        .dropdown-item:hover {
            background-color: rgba(245,158,11,0.2);
            color: var(--gold) !important;
        }

        /* Login Section */
        .login-section {
            min-height: calc(100vh - 400px);
            display: flex;
            align-items: center;
            padding: 60px 0;
        }

        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .login-header i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.9;
            color: var(--gold);
        }

        .login-header h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-header p {
            margin: 0;
            opacity: 0.9;
        }

        .login-body {
            padding: 40px;
        }

        .form-label {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .form-control {
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 0.2rem rgba(26, 86, 219, 0.1);
        }

        .input-group-text {
            background: white;
            border: 2px solid var(--border);
            border-left: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .input-group .form-control {
            border-right: none;
        }

        .input-group-text:hover {
            color: var(--blue);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--blue) 0%, var(--navy) 100%);
            border: none;
            color: white;
            padding: 14px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(26, 86, 219, 0.4);
            background: linear-gradient(135deg, var(--blue-light) 0%, var(--blue) 100%);
            color: white;
        }

        .login-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 1.25rem 0;
            color: var(--muted);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .google-signin-wrap {
            margin-bottom: 0.25rem;
        }

        .google-signin-stack {
            position: relative;
            width: 100%;
            min-height: 44px;
        }

        .google-signin-stack .btn-google {
            width: 100%;
            pointer-events: none;
        }

        .google-signin-overlay {
            position: absolute;
            inset: 0;
            opacity: 0.02;
            overflow: hidden;
        }

        .google-signin-overlay > div,
        .google-signin-overlay iframe {
            width: 100% !important;
            height: 100% !important;
        }

        .btn-google {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 12px 16px;
            border-radius: 8px;
            border: 2px solid var(--border);
            background: #fff;
            color: var(--text);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .btn-google svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .alert-danger {
            background-color: #fee;
            color: #c33;
        }

        .info-cards {
            margin-top: 40px;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            border: 1px solid var(--border);
            transition: all 0.3s;
            height: 100%;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border-color: var(--blue);
        }

        .info-card i {
            font-size: 2.5rem;
            color: var(--blue);
            margin-bottom: 15px;
        }

        .info-card h5 {
            font-weight: 600;
            margin-bottom: 10px;
        }

        .info-card p {
            color: var(--muted);
            font-size: 0.9rem;
            margin: 0;
        }

        /* Footer */
        footer {
            background-color: #050e1a;
            color: rgba(255,255,255,0.62);
            font-size: 0.95rem;
            margin-top: 60px;
        }

        footer h5 {
            color: #fff;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
        }

        footer h5::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 40px;
            height: 3px;
            background-color: var(--gold);
        }

        footer a {
            color: #cbd5e0;
            text-decoration: none;
            transition: color 0.2s;
            display: block;
            margin-bottom: 8px;
        }

        footer a:hover {
            color: var(--accent-gold);
            padding-left: 5px;
        }

        .copyright-bar {
            background-color: #111827;
            padding: 15px 0;
            border-top: 1px solid #2d3748;
        }

        @media (max-width: 768px) {
            .login-header {
                padding: 30px 20px;
            }

            .login-header h2 {
                font-size: 1.5rem;
            }

            .login-header i {
                font-size: 48px;
            }

            .login-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Top Bar -->
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
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-university me-2"></i> NIELIT
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../public/courses.php">Courses</a></li>
                    <li class="nav-item"><a class="nav-link active" href="login.php">Student Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="../public/contact.php">Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Login Section -->
    <section class="login-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="login-card">
                        <div class="login-header">
                            <i class="fas fa-user-graduate"></i>
                            <h2>Student Portal</h2>
                            <p>Login to access your dashboard</p>
                        </div>

                        <div class="login-body">
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (GOOGLE_OAUTH_ENABLED): ?>
                            <form method="POST" action="login.php" id="googleLoginForm" class="google-signin-wrap">
                                <input type="hidden" name="google_credential" id="googleCredentialInput">
                                <div class="google-signin-stack" id="googleSignInStack">
                                    <button type="button" class="btn-google" tabindex="-1" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                        Continue with Google
                                    </button>
                                    <div id="googleSignInContainer" class="google-signin-overlay" aria-label="Continue with Google"></div>
                                </div>
                            </form>
                            <div class="login-divider">or</div>
                            <?php endif; ?>

                            <form method="POST" action="login.php" id="passwordLoginForm">
                                <div class="mb-4">
                                    <label for="login_id" class="form-label">
                                        <i class="fas fa-id-card me-2"></i>Student ID or Email
                                    </label>
                                    <input type="text"
                                           class="form-control"
                                           id="login_id"
                                           name="login_id"
                                           placeholder="NIELIT/2026/... or you@email.com"
                                           required
                                           autofocus
                                           autocomplete="username"
                                           value="<?php echo htmlspecialchars((string) ($_POST['login_id'] ?? $_POST['student_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <div class="mb-4">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password"
                                               class="form-control"
                                               id="password"
                                               name="password"
                                               placeholder="Enter your Password"
                                               required
                                               autocomplete="current-password">
                                        <span class="input-group-text" onclick="togglePassword()" role="button" tabindex="0" aria-label="Toggle password visibility">
                                            <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                        </span>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-login w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login to Portal
                                </button>
                            </form>

                            <div class="text-center mt-4">
                                <p class="text-muted small mb-2">Need help?</p>
                                <a href="../public/contact.php" class="text-decoration-none">
                                    <i class="fas fa-headset me-1"></i>Contact Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="info-cards">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="info-card">
                            <i class="fas fa-tachometer-alt"></i>
                            <h5>Dashboard</h5>
                            <p>View your course progress, attendance, and important updates</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-card">
                            <i class="fas fa-certificate"></i>
                            <h5>Certificates</h5>
                            <p>Download your course certificates and achievements</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-card">
                            <i class="fas fa-rupee-sign"></i>
                            <h5>Fee Management</h5>
                            <p>Check fee status and download payment receipts</p>
                        </div>
                    </div>
                </div>
            </div>
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
                        <li><a href="../index.php"><i class="fas fa-chevron-right me-2 small"></i>Home</a></li>
                        <li><a href="../public/courses.php"><i class="fas fa-chevron-right me-2 small"></i>Courses</a></li>
                        <li><a href="../public/contact.php"><i class="fas fa-chevron-right me-2 small"></i>Contact Us</a></li>
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
        window.STUDENT_LOGIN_CONFIG = {
            googleClientId: <?php echo json_encode(GOOGLE_CLIENT_ID); ?>,
            googleEnabled: <?php echo json_encode(GOOGLE_OAUTH_ENABLED); ?>
        };

        function togglePassword() {
            const passwordInput = document.getElementById("password");
            const toggleIcon = document.getElementById("togglePasswordIcon");
            if (!passwordInput || !toggleIcon) return;

            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordInput.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        }

        function submitGoogleCredential(credential) {
            const form = document.getElementById('googleLoginForm');
            const input = document.getElementById('googleCredentialInput');
            if (!form || !input) return;
            input.value = credential;
            form.submit();
        }

        window.handleStudentGoogleSignIn = function (response) {
            if (response && response.credential) {
                submitGoogleCredential(response.credential);
            }
        };

        function initStudentGoogleSignIn() {
            const config = window.STUDENT_LOGIN_CONFIG || {};
            if (!config.googleEnabled || !config.googleClientId) return;
            if (!window.google || !google.accounts || !google.accounts.id) return;

            const stack = document.getElementById('googleSignInStack');
            const container = document.getElementById('googleSignInContainer');
            if (!stack || !container) return;

            google.accounts.id.initialize({
                client_id: config.googleClientId,
                callback: window.handleStudentGoogleSignIn,
                auto_select: false,
                cancel_on_tap_outside: true
            });

            const buttonWidth = Math.min(400, Math.max(Math.floor(stack.getBoundingClientRect().width), 280));
            google.accounts.id.renderButton(container, {
                type: 'standard',
                theme: 'outline',
                size: 'large',
                text: 'continue_with',
                shape: 'rectangular',
                width: buttonWidth,
                logo_alignment: 'left'
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const config = window.STUDENT_LOGIN_CONFIG || {};
            if (!config.googleEnabled) return;

            let tries = 0;
            const timer = setInterval(function () {
                tries += 1;
                if (window.google && google.accounts) {
                    clearInterval(timer);
                    initStudentGoogleSignIn();
                } else if (tries > 40) {
                    clearInterval(timer);
                }
            }, 100);
        });
    </script>
</body>
</html>
