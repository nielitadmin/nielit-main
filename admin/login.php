<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../libraries/PHPMailer/src/Exception.php';
require __DIR__ . '/../libraries/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../libraries/PHPMailer/src/SMTP.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session_manager.php';

$error_message = "";
$success_message = "";
$show_otp_form = false;

function sendOTP($toEmail, $otp) {
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
            <div style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h2 style="color: white; margin: 0;">NIELIT Bhubaneswar</h2>
                <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0;">Admin Login Verification</p>
            </div>
            <div style="background: white; padding: 30px; border-radius: 0 0 10px 10px;">
                <p style="font-size: 16px; color: #1e293b;">Dear Admin,</p>
                <p style="font-size: 14px; color: #64748b;">Your One-Time Password (OTP) for admin login is:</p>
                <div style="background: #f1f5f9; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;">
                    <h1 style="color: #2563eb; margin: 0; font-size: 36px; letter-spacing: 8px;">' . htmlspecialchars($otp) . '</h1>
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
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Handle login form submission
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
            $_SESSION['temp_admin_username'] = $admin['username'];
            $_SESSION['temp_admin_email'] = $admin['email'] ?? $admin['username'];
            $otp = rand(100000, 999999);
            $_SESSION['login_otp'] = $otp;
            $_SESSION['otp_generated_time'] = time();

            $sent = sendOTP($_SESSION['temp_admin_email'], $otp);

            if ($sent) {
                $success_message = "OTP sent successfully to your registered email.";
                $show_otp_form = true;
            } else {
                $error_message = "Failed to send OTP email. Please contact support.";
            }
        } else {
            $error_message = "Invalid username or password.";
        }
    } else {
        $error_message = "Invalid username or password.";
    }
}

// Handle OTP verification
if (isset($_POST['verify_otp'])) {
    $input_otp = $_POST['otp'] ?? '';

    if (!isset($_SESSION['login_otp']) || !isset($_SESSION['otp_generated_time'])) {
        $error_message = "No OTP generated. Please login again.";
    } elseif ((time() - $_SESSION['otp_generated_time']) > 600) {
        $error_message = "OTP expired. Please login again.";
        unset($_SESSION['login_otp'], $_SESSION['otp_generated_time'], $_SESSION['temp_admin_username'], $_SESSION['temp_admin_email']);
    } elseif ($input_otp == $_SESSION['login_otp']) {
        // OTP verified successfully - initialize RBAC session
        $username = $_SESSION['temp_admin_username'];
        
        // Initialize admin session with RBAC data (loads role, assigned courses, admin_id)
        if (init_admin_session($username)) {
            // Clean up temporary session variables
            unset($_SESSION['login_otp'], $_SESSION['otp_generated_time'], $_SESSION['temp_admin_username'], $_SESSION['temp_admin_email']);
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            // Session initialization failed
            $error_message = "Failed to initialize session. Please contact support.";
            unset($_SESSION['login_otp'], $_SESSION['otp_generated_time'], $_SESSION['temp_admin_username'], $_SESSION['temp_admin_email']);
        }
    } else {
        $error_message = "Invalid OTP.";
        $show_otp_form = true;
    }
}

// Handle resend OTP
if (isset($_POST['resend_otp'])) {
    if (!isset($_SESSION['temp_admin_email'])) {
        $error_message = "Session expired. Please login again.";
    } else {
        $otp = rand(100000, 999999);
        $_SESSION['login_otp'] = $otp;
        $_SESSION['otp_generated_time'] = time();

        $sent = sendOTP($_SESSION['temp_admin_email'], $otp);
        if ($sent) {
            $success_message = "OTP resent successfully.";
            $show_otp_form = true;
        } else {
            $error_message = "Failed to resend OTP. Please contact support.";
            $show_otp_form = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo" class="login-logo">
            <h2><i class="fas fa-shield-alt"></i> Admin Login</h2>
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
                <!-- OTP Verification Form -->
                <form method="POST" action="login.php">
                    <div class="form-group">
                        <label for="otp" class="form-label">
                            <i class="fas fa-key"></i> Enter OTP
                        </label>
                        <input type="text" class="form-control" id="otp" name="otp" 
                               placeholder="Enter 6-digit OTP" required autofocus 
                               pattern="\d{6}" maxlength="6">
                        <small style="color: #64748b; font-size: 12px; display: block; margin-top: 8px;">
                            <i class="fas fa-info-circle"></i> OTP sent to your registered email
                        </small>
                    </div>
                    
                    <button type="submit" name="verify_otp" class="btn btn-primary w-100 btn-lg">
                        <i class="fas fa-check"></i> Verify OTP
                    </button>
                </form>
                
                <form method="POST" action="login.php" class="mt-3 text-center">
                    <button type="submit" name="resend_otp" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Resend OTP
                    </button>
                </form>
            <?php else: ?>
                <!-- Login Form -->
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
                        <div style="position: relative;">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter your password" required style="padding-right: 45px;">
                            <span onclick="togglePassword()" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b;">
                                <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary w-100 btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="login-footer">
            <p><i class="fas fa-shield-alt"></i> Secure Admin Access with OTP Verification</p>
            <p>&copy; 2025 NIELIT Bhubaneswar. All rights reserved.</p>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById("password");
    const toggleIcon = document.getElementById("togglePasswordIcon");

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
</script>

</body>
</html>
<?php $conn->close(); ?>
