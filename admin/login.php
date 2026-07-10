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

// Ensure all custom roles exist in the enum (runs silently)
$conn->query("ALTER TABLE admin MODIFY COLUMN role ENUM('master_admin','course_coordinator','nsqf_course_manager','data_entry_operator','report_viewer','front_office_desk','placement_coordinator') NOT NULL DEFAULT 'course_coordinator'");

$error_message = "";
$success_message = "";
$show_otp_form = false;

function sendOTP($toEmail, $otp, $username = null) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

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

        $mail->send();
        logOTP($toEmail, $otp, 'Admin Login', $username, 'sent');
        return true;
    } catch (Exception $e) {
        logOTP($toEmail, $otp, 'Admin Login', $username, 'failed');
        return false;
    }
}

function startAdminLoginOtpFlow(array $admin): bool
{
    global $success_message, $error_message, $show_otp_form;

    $_SESSION['temp_admin_username'] = $admin['username'];
    $_SESSION['temp_admin_email'] = $admin['email'] ?? $admin['username'];
    $otp = rand(100000, 999999);
    $_SESSION['login_otp'] = $otp;
    $_SESSION['otp_generated_time'] = time();

    $sent = sendOTP($_SESSION['temp_admin_email'], $otp, $admin['username']);

    if ($sent) {
        $success_message = 'OTP sent successfully to your registered email.';
        $show_otp_form = true;
        return true;
    }

    $error_message = 'Failed to send OTP email. Please contact support.';
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
                } else {
                    startAdminLoginOtpFlow($admin);
                }
            } else {
                $error_message = 'No admin account is linked to this Google email.';
            }
            $stmt->close();
        }
    }
}

if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM admin WHERE LOWER(username) = LOWER(?) LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            if (isset($admin['is_active']) && !$admin['is_active']) {
                $error_message = 'Your admin account is inactive. Please contact support.';
            } else {
                startAdminLoginOtpFlow($admin);
            }
        } else {
            $error_message = "Invalid username or password.";
        }
    } else {
        $error_message = "Invalid username or password.";
    }
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
        if ($sent) {
            $success_message = "OTP resent successfully.";
            $show_otp_form = true;
        } else {
            $error_message = "Failed to resend OTP. Please contact support.";
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
    <title>Admin Login - NIELIT Bhubaneswar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('assets/css/admin-login.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <?php if (GOOGLE_OAUTH_ENABLED): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
</head>
<body>

<div class="login-page">
    <aside class="banner-panel" aria-label="Institute highlights">
        <div class="banner-carousel">
            <?php foreach ($hero_slides as $i => $slide): ?>
            <div class="banner-slide<?php echo $i === 0 ? ' active' : ''; ?>">
                <img
                    src="<?php echo htmlspecialchars((string) $slide['url'], ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars((string) $slide['alt'], ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $i === 0 ? 'fetchpriority="high"' : 'loading="lazy" decoding="async"'; ?>
                >
            </div>
            <?php endforeach; ?>
        </div>

        <div class="banner-shapes" aria-hidden="true">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
        </div>

        <div class="banner-overlay">
            <div class="banner-content">
                <span class="banner-badge"><i class="fas fa-shield-alt"></i> Secure Admin Portal</span>
                <h1 class="brand-title">NIELIT Bhubaneswar</h1>
                <p>Management system for courses, batches, students, and institutional operations — Ministry of Electronics & IT, Govt. of India.</p>
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
        <div class="banner-dots" aria-label="Banner navigation">
            <?php foreach ($hero_slides as $i => $slide): ?>
            <button type="button" class="banner-dot<?php echo $i === 0 ? ' active' : ''; ?>" aria-label="Show banner <?php echo $i + 1; ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </aside>

    <main class="login-panel">
        <div class="login-panel-inner">
            <div class="login-card">
                <div class="login-header">
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
                    <h2>Admin Portal</h2>
                    <p>NIELIT Bhubaneswar Management System</p>
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
                    <form method="POST" action="login.php" id="otpForm">
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
                            <small><i class="fas fa-info-circle"></i> OTP sent to your registered email</small>
                        </div>

                        <button type="submit" name="verify_otp" class="btn btn-primary w-100 btn-lg" id="verifyBtn">
                            <span class="btn-text"><i class="fas fa-check"></i> Verify OTP</span>
                            <span class="spinner"></span>
                        </button>
                    </form>

                    <form method="POST" action="login.php" class="mt-3 text-center">
                        <button type="submit" name="resend_otp" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Resend OTP
                        </button>
                    </form>
                    <?php else: ?>

                    <?php if (GOOGLE_OAUTH_ENABLED): ?>
                    <form method="POST" action="login.php" id="googleLoginForm" class="google-signin-wrap">
                        <input type="hidden" name="google_credential" id="googleCredentialInput">
                        <div id="googleSignInContainer"></div>
                    </form>
                    <div class="divider">or sign in with username</div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Google Sign-In can be enabled by adding your Client ID in <code>config/google.php</code>.
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <div class="form-group">
                            <label for="username" class="form-label">
                                <i class="fas fa-user"></i> Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username"
                                   placeholder="Enter your username" required autofocus>
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
                            <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Login to Dashboard</span>
                            <span class="spinner"></span>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="login-footer">
                    <p><i class="fas fa-shield-alt"></i> Secure Admin Access with OTP Verification</p>
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
<script src="<?php echo htmlspecialchars(app_url('assets/js/admin-login.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

</body>
</html>
<?php $conn->close(); ?>
