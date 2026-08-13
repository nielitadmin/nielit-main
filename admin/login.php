<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../libraries/PHPMailer/src/Exception.php';
require __DIR__ . '/../libraries/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../libraries/PHPMailer/src/SMTP.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_once __DIR__ . '/../includes/otp_logger.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/hero_banner_helper.php';
require_once __DIR__ . '/../includes/google_auth.php';

require_once __DIR__ . '/../includes/phpmailer_smtp.php';
require_once __DIR__ . '/../includes/teaching_access.php';

if (!isset($loginPortal)) {
    $loginPortal = (isset($_GET['portal']) && $_GET['portal'] === 'faculty') ? 'faculty' : 'admin';
}
$isFacultyPortal = ($loginPortal === 'faculty');
$loginFormAction = htmlspecialchars(
    $isFacultyPortal ? app_url('faculty/login') : app_url('admin/login'),
    ENT_QUOTES,
    'UTF-8'
);

// Already signed in — skip login and go to the role landing page
if (is_session_valid()) {
    header('Location: ' . get_admin_post_login_url());
    exit;
}

// Ensure all custom roles exist in the enum (runs silently)
ensureAdminRoleEnum($conn);

$error_message = "";
$success_message = "";
$show_otp_form = false;

function maskEmailAddress($email) {
    $email = trim((string) $email);
    if ($email === '' || strpos($email, '@') === false) {
        return 'your registered email';
    }
    [$local, $domain] = explode('@', $email, 2);
    $localVisible = substr($local, 0, min(2, strlen($local)));
    $domainParts = explode('.', $domain);
    $domainName = $domainParts[0] ?? '';
    $domainVisible = substr($domainName, 0, min(1, strlen($domainName)));
    $tld = count($domainParts) > 1 ? '.' . implode('.', array_slice($domainParts, 1)) : '';
    return $localVisible . str_repeat('*', max(3, strlen($local) - strlen($localVisible)))
        . '@'
        . $domainVisible . str_repeat('*', max(2, strlen($domainName) - strlen($domainVisible)))
        . $tld;
}

function sendOTP($toEmail, $otp, $username = null) {
    $toEmail = trim((string) $toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('Admin login OTP aborted: invalid recipient email [' . $toEmail . ']');
        logOTP($toEmail ?: 'invalid', (string) $otp, 'Admin Login', $username, 'failed');
        return [
            'ok' => false,
            'error' => 'Admin account has no valid email address configured.',
        ];
    }

    $result = sendPhpMailerWithSmtpFallback(static function ($mail) use ($toEmail, $otp) {
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP for Admin Login - NIELIT Bhubaneswar';
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f8fafc; border-radius: 10px;">
            <div style="background: linear-gradient(135deg, #0a1628 0%, #112240 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h2 style="color: white; margin: 0;">NIELIT Bhubaneswar</h2>
                <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0;">Admin Login Verification</p>
            </div>
            <div style="background: white; padding: 30px; border-radius: 0 0 10px 10px;">
                <p style="font-size: 16px; color: #1e293b;">Dear Admin,</p>
                <p style="font-size: 14px; color: #64748b;">Your One-Time Password (OTP) for admin login is:</p>
                <div style="background: #f1f5f9; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;">
                    <h1 style="color: #0a1628; margin: 0; font-size: 36px; letter-spacing: 8px;">' . htmlspecialchars($otp) . '</h1>
                </div>
                <p style="font-size: 13px; color: #64748b;">This OTP is valid for 10 minutes. Do not share this code with anyone.</p>
                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">
                <p style="font-size: 12px; color: #94a3b8; margin: 0;">
                    National Institute of Electronics and Information Technology<br>
                    Bhubaneswar Center<br>
                    Ministry of Electronics & IT, Govt. of India
                </p>
            </div>
        </div>';
        $mail->AltBody = 'Your OTP for Admin Login is: ' . $otp . ' (valid 10 minutes).';
    }, ['timeout' => 25]);

    if (!empty($result['ok'])) {
        logOTP($toEmail, $otp, 'Admin Login', $username, 'sent');
        return ['ok' => true, 'error' => ''];
    }

    error_log('Admin login OTP email failed to ' . $toEmail . ': ' . ($result['error'] ?? 'unknown'));
    logOTP($toEmail, $otp, 'Admin Login', $username, 'failed');
    return [
        'ok' => false,
        'error' => $result['error'] ?: 'SMTP send failed.',
    ];
}

function startAdminLoginOtpFlow(array $admin): bool
{
    global $success_message, $error_message, $show_otp_form;

    $_SESSION['temp_admin_username'] = $admin['username'];
    $_SESSION['temp_admin_email'] = $admin['email'] ?? $admin['username'];
    $otp = rand(100000, 999999);
    $_SESSION['login_otp'] = $otp;
    $_SESSION['otp_generated_time'] = time();

    // Log OTP as queued (so it appears in admin logs immediately) and get log id
    $logId = logOTP($_SESSION['temp_admin_email'], $otp, 'Admin Login', $admin['username'], 'queued');

    $sent = sendOTP($_SESSION['temp_admin_email'], $otp, $admin['username']);
    $ok = is_array($sent) ? !empty($sent['ok']) : (bool) $sent;
    $detail = is_array($sent) ? trim((string) ($sent['error'] ?? '')) : '';

    // Update logged OTP status if we have an inserted id
    if ($logId) {
        updateOtpStatus($logId, $ok ? 'sent' : 'failed');
    }

    if ($ok) {
        $success_message = 'OTP sent successfully to ' . maskEmailAddress($_SESSION['temp_admin_email']) . '. Check inbox and spam.';
        $show_otp_form = true;
        return true;
    }

    $error_message = 'Failed to send OTP email. Please contact support.';
    if ($detail !== '') {
        // Safe short hint for admins (no password). Helps diagnose Hostinger SMTP issues.
        $safe = preg_replace('/\s+/', ' ', $detail);
        $safe = substr($safe, 0, 220);
        $error_message .= ' Details: ' . htmlspecialchars($safe, ENT_QUOTES, 'UTF-8');
    }
    return false;
}

function resolveHeroSlidesForLogin($conn): array
{
    $slides = getHeroBannerSlidesForIndex($conn);

    foreach ($slides as &$slide) {
        $url = (string) ($slide['url'] ?? '');
        if ($url !== '' && strpos($url, 'data:') !== 0) {
            $slide['url'] = app_url($url);
        }
    }
    unset($slide);

    return $slides;
}

if (isset($_POST['google_credential'])) {
    if (!GOOGLE_OAUTH_ENABLED) {
        $error_message = 'Google Sign-In is not configured yet.';
    } else {
        $payload = verifyGoogleIdToken((string) $_POST['google_credential'], GOOGLE_CLIENT_ID);
        if ($payload === null) {
            $error_message = 'Google sign-in verification failed. Please try again.';
        } else {
            $googleEmail = strtolower(trim((string) $payload['email']));
            $stmt = $conn->prepare('SELECT * FROM admin WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $stmt->bind_param('s', $googleEmail);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $admin = $result->fetch_assoc();
                if (isset($admin['is_active']) && !$admin['is_active']) {
                    $error_message = 'Your admin account is inactive. Please contact support.';
                } elseif ($isFacultyPortal && ($admin['role'] ?? '') !== 'faculty') {
                    $error_message = 'This login is for faculty accounts only. Please use Admin Login.';
                } elseif (!$isFacultyPortal && ($admin['role'] ?? '') === 'faculty') {
                    $error_message = 'Please use the Faculty Login page for faculty accounts.';
                } elseif (init_admin_session($admin['username'])) {
                    // Google already verified the email identity — skip OTP and log in.
                    unset(
                        $_SESSION['login_otp'],
                        $_SESSION['otp_generated_time'],
                        $_SESSION['temp_admin_username'],
                        $_SESSION['temp_admin_email']
                    );
                    header('Location: ' . get_admin_post_login_url());
                    exit();
                } else {
                    $error_message = 'Failed to initialize session. Please contact support.';
                }
            } else {
                $error_message = $isFacultyPortal
                    ? 'No faculty account is linked to this Google email.'
                    : 'No admin account is linked to this Google email.';
            }
            $stmt->close();
        }
    }
}

if (isset($_POST['login'])) {
    $loginId = trim((string) ($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        "SELECT * FROM admin
         WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)
         LIMIT 1"
    );
    $stmt->bind_param("ss", $loginId, $loginId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            if (isset($admin['is_active']) && !$admin['is_active']) {
                $error_message = 'Your admin account is inactive. Please contact support.';
            } elseif ($isFacultyPortal && ($admin['role'] ?? '') !== 'faculty') {
                $error_message = 'This login is for faculty accounts only. Please use Admin Login.';
            } elseif (!$isFacultyPortal && ($admin['role'] ?? '') === 'faculty') {
                $error_message = 'Please use the Faculty Login page for faculty accounts.';
            } else {
                startAdminLoginOtpFlow($admin);
            }
        } else {
            $error_message = "Invalid username/email or password.";
        }
    } else {
        $error_message = "Invalid username/email or password.";
    }
    $stmt->close();
}

if (isset($_POST['verify_otp'])) {
    $input_otp = $_POST['otp'] ?? '';

    if (!isset($_SESSION['login_otp']) || !isset($_SESSION['otp_generated_time'])) {
        $error_message = "No OTP generated. Please login again.";
    } elseif ((time() - $_SESSION['otp_generated_time']) > 600) {
        $error_message = "OTP expired. Please login again.";
        unset($_SESSION['login_otp'], $_SESSION['otp_generated_time'], $_SESSION['temp_admin_username'], $_SESSION['temp_admin_email']);
    } elseif ($input_otp == $_SESSION['login_otp']) {
        $username = $_SESSION['temp_admin_username'];

        if (init_admin_session($username)) {
            unset($_SESSION['login_otp'], $_SESSION['otp_generated_time'], $_SESSION['temp_admin_username'], $_SESSION['temp_admin_email']);
            header('Location: ' . get_admin_post_login_url());
            exit();
        } else {
            $error_message = "Failed to initialize session. Please contact support.";
            unset($_SESSION['login_otp'], $_SESSION['otp_generated_time'], $_SESSION['temp_admin_username'], $_SESSION['temp_admin_email']);
        }
    } else {
        $error_message = "Invalid OTP.";
        $show_otp_form = true;
    }
}

if (isset($_POST['resend_otp'])) {
    if (!isset($_SESSION['temp_admin_email'])) {
        $error_message = "Session expired. Please login again.";
    } else {
        $otp = rand(100000, 999999);
        $_SESSION['login_otp'] = $otp;
        $_SESSION['otp_generated_time'] = time();

        $sent = sendOTP($_SESSION['temp_admin_email'], $otp, $_SESSION['temp_admin_username']);
        $ok = is_array($sent) ? !empty($sent['ok']) : (bool) $sent;
        $detail = is_array($sent) ? trim((string) ($sent['error'] ?? '')) : '';
        if ($ok) {
            $success_message = 'OTP resent successfully to ' . maskEmailAddress($_SESSION['temp_admin_email']) . '. Check inbox and spam.';
            $show_otp_form = true;
        } else {
            $error_message = 'Failed to resend OTP. Please contact support.';
            if ($detail !== '') {
                $safe = preg_replace('/\s+/', ' ', $detail);
                $safe = substr($safe, 0, 220);
                $error_message .= ' Details: ' . htmlspecialchars($safe, ENT_QUOTES, 'UTF-8');
            }
            $show_otp_form = true;
        }
    }
}

$hero_slides = resolveHeroSlidesForLogin($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isFacultyPortal ? 'Faculty Login' : 'Admin Login'; ?> - NIELIT Bhubaneswar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('assets/css/admin-login.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <?php if (GOOGLE_OAUTH_ENABLED): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
</head>
<body>

<div class="login-page">
    <aside class="banner-panel" aria-label="Institute highlights" id="bannerPanel">
        <div class="banner-carousel" id="bannerCarousel">
            <?php foreach ($hero_slides as $i => $slide): ?>
            <div class="banner-slide<?php echo $i === 0 ? ' active' : ''; ?>" data-index="<?php echo $i; ?>">
                <div class="banner-parallax-layer">
                    <img
                        src="<?php echo htmlspecialchars((string) $slide['url'], ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?php echo htmlspecialchars((string) $slide['alt'], ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo $i === 0 ? 'fetchpriority="high"' : 'loading="lazy" decoding="async"'; ?>
                    >
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="banner-vignette" aria-hidden="true"></div>

        <div class="banner-shapes" aria-hidden="true">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>

        <div class="banner-overlay">
            <div class="banner-content">
                <span class="banner-badge"><i class="fas fa-<?php echo $isFacultyPortal ? 'chalkboard-teacher' : 'shield-alt'; ?>"></i> <?php echo $isFacultyPortal ? 'Faculty Portal' : 'Secure Admin Portal'; ?></span>
                <h1 class="brand-title">NIELIT Bhubaneswar</h1>
                <p><?php echo $isFacultyPortal
                    ? 'Faculty access to edit class timetable and your own course action plans — Ministry of Electronics & IT, Govt. of India.'
                    : 'Management system for courses, batches, students, and institutional operations — Ministry of Electronics & IT, Govt. of India.'; ?></p>
                <div class="banner-stats">
                    <div class="banner-stat">
                        <strong>NSQF</strong>
                        <span>Aligned Programs</span>
                    </div>
                    <div class="banner-stat">
                        <strong>24×7</strong>
                        <span>Secure Access</span>
                    </div>
                    <div class="banner-stat">
                        <strong>OTP</strong>
                        <span>Verified Login</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (count($hero_slides) > 1): ?>
        <div class="banner-controls">
            <div class="banner-progress" aria-hidden="true"><span class="banner-progress-bar" id="bannerProgressBar"></span></div>
            <div class="banner-dots" aria-label="Banner navigation">
                <?php foreach ($hero_slides as $i => $slide): ?>
                <button type="button" class="banner-dot<?php echo $i === 0 ? ' active' : ''; ?>" aria-label="Show banner <?php echo $i + 1; ?>"></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </aside>

    <main class="login-panel" id="loginPanel">
        <div class="panel-aurora" aria-hidden="true"></div>
        <div class="panel-grid" aria-hidden="true"></div>
        <div class="gradient-mesh" aria-hidden="true">
            <span class="mesh-blob mesh-blob-1"></span>
            <span class="mesh-blob mesh-blob-2"></span>
            <span class="mesh-blob mesh-blob-3"></span>
            <span class="mesh-blob mesh-blob-4"></span>
            <span class="mesh-blob mesh-blob-5"></span>
        </div>
        <div class="panel-3d-scene" aria-hidden="true">
            <div class="float-orb float-orb-1"></div>
            <div class="float-orb float-orb-2"></div>
            <div class="float-orb float-orb-3"></div>
            <div class="float-ring float-ring-1"></div>
            <div class="float-ring float-ring-2"></div>
            <div class="float-cube float-cube-1"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            <div class="float-cube float-cube-2"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            <div class="float-diamond float-diamond-1"></div>
            <div class="float-diamond float-diamond-2"></div>
        </div>
        <div class="panel-particles" id="panelParticles" aria-hidden="true"></div>
        <div class="panel-light-beam" aria-hidden="true"></div>

        <div class="login-panel-inner" id="loginPanelInner">
            <div class="login-card">
                <div class="login-card-accent" aria-hidden="true"></div>
                <div class="login-header">
                    <span class="security-badge"><i class="fas fa-lock"></i> OTP Secured</span>
                    <div class="mascot-container">
                        <div class="mascot">
                            <div class="mascot-face">
                                <div class="mascot-eyes" id="mascotEyes">
                                    <div class="eye left-eye"><div class="pupil"></div></div>
                                    <div class="eye right-eye"><div class="pupil"></div></div>
                                </div>
                                <div class="nose"></div>
                                <div class="mouth" id="mascotMouth"></div>
                                <div class="mascot-hands" id="mascotHands">
                                    <div class="hand left-hand">✋</div>
                                    <div class="hand right-hand">✋</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <h2><?php echo $isFacultyPortal ? 'Faculty Portal' : 'Admin Portal'; ?></h2>
                    <p><?php echo $isFacultyPortal ? 'Edit timetable &amp; your course action plans' : 'NIELIT Bhubaneswar Management System'; ?></p>
                </div>

                <div class="login-body">
                    <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_otp_form): ?>
                    <form method="POST" action="<?php echo $loginFormAction; ?>" id="otpForm">
                        <div class="form-group">
                            <label for="otp-1" class="form-label">
                                <i class="fas fa-key"></i> Enter OTP
                            </label>
                            <div class="otp-input-group">
                                <input type="text" class="otp-input" id="otp-1" maxlength="1" pattern="\d" inputmode="numeric" required autofocus>
                                <input type="text" class="otp-input" id="otp-2" maxlength="1" pattern="\d" inputmode="numeric" required>
                                <input type="text" class="otp-input" id="otp-3" maxlength="1" pattern="\d" inputmode="numeric" required>
                                <input type="text" class="otp-input" id="otp-4" maxlength="1" pattern="\d" inputmode="numeric" required>
                                <input type="text" class="otp-input" id="otp-5" maxlength="1" pattern="\d" inputmode="numeric" required>
                                <input type="text" class="otp-input" id="otp-6" maxlength="1" pattern="\d" inputmode="numeric" required>
                            </div>
                            <input type="hidden" name="otp" id="otp-hidden">
                            <small><i class="fas fa-info-circle"></i> OTP sent to <?php echo htmlspecialchars(maskEmailAddress($_SESSION['temp_admin_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> — also check Spam</small>
                        </div>

                        <button type="submit" name="verify_otp" class="btn btn-primary w-100 btn-lg" id="verifyBtn">
                            <span class="btn-text"><i class="fas fa-check"></i> Verify OTP</span>
                            <span class="spinner"></span>
                        </button>
                    </form>

                    <form method="POST" action="<?php echo $loginFormAction; ?>" class="mt-3 text-center">
                        <button type="submit" name="resend_otp" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Resend OTP
                        </button>
                    </form>
                    <?php else: ?>

                    <?php if (GOOGLE_OAUTH_ENABLED): ?>
                    <form method="POST" action="<?php echo $loginFormAction; ?>" id="googleLoginForm" class="google-signin-wrap">
                        <input type="hidden" name="google_credential" id="googleCredentialInput">
                        <div class="google-signin-stack" id="googleSignInStack">
                            <button type="button" class="btn-google" tabindex="-1" aria-hidden="true">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                Continue with Google
                            </button>
                            <div id="googleSignInContainer" class="google-signin-overlay" aria-label="Continue with Google"></div>
                        </div>
                    </form>
                    <div class="divider">or sign in with username / email</div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Google Sign-In can be enabled by adding your Client ID in <code>config/google.php</code>.
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo $loginFormAction; ?>">
                        <div class="form-group">
                            <label for="username" class="form-label">
                                <i class="fas fa-user"></i> Username or Email
                            </label>
                            <input type="text" class="form-control" id="username" name="username"
                                   placeholder="Enter username or email" required autofocus>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock"></i> Password
                            </label>
                            <div class="password-wrap">
                                <input type="password" class="form-control" id="password" name="password"
                                       placeholder="Enter your password" required>
                                <span onclick="togglePassword()" class="password-toggle" role="button" tabindex="0" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                </span>
                            </div>
                        </div>

                        <button type="submit" name="login" class="btn btn-primary w-100 btn-lg" id="loginBtn">
                            <span class="btn-text"><i class="fas fa-sign-in-alt"></i> <?php echo $isFacultyPortal ? 'Login to Faculty Portal' : 'Login to Dashboard'; ?></span>
                            <span class="spinner"></span>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="login-footer">
                    <p class="footer-security"><span class="footer-security-icon"><i class="fas fa-shield-alt"></i></span> Secure access with OTP verification</p>
                    <p>
                        <?php if ($isFacultyPortal): ?>
                            <a href="<?php echo htmlspecialchars(app_url('admin/login'), ENT_QUOTES, 'UTF-8'); ?>">Admin Login</a>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars(app_url('faculty/login'), ENT_QUOTES, 'UTF-8'); ?>">Faculty Login</a>
                        <?php endif; ?>
                        &nbsp;·&nbsp;
                        <a href="<?php echo htmlspecialchars(app_url('student/login'), ENT_QUOTES, 'UTF-8'); ?>">Student Login</a>
                    </p>
                    <p>&copy; <?php echo date('Y'); ?> NIELIT Bhubaneswar. All rights reserved.</p>
                </div>
            </div>

            <a href="<?php echo htmlspecialchars(app_url(''), ENT_QUOTES, 'UTF-8'); ?>" class="back-home">
                <i class="fas fa-arrow-left"></i> Back to main website
            </a>
        </div>
    </main>
</div>

<script>
window.ADMIN_LOGIN_CONFIG = {
    googleClientId: <?php echo json_encode(GOOGLE_CLIENT_ID); ?>,
    googleEnabled: <?php echo json_encode(GOOGLE_OAUTH_ENABLED); ?>,
    showOtpForm: <?php echo json_encode($show_otp_form); ?>
};
</script>
<script src="<?php echo htmlspecialchars(asset_url('assets/js/admin-login.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

</body>
</html>
<?php $conn->close(); ?>
