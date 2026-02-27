<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");
    exit();
}

// Database connection
require_once __DIR__ . '/../config/config.php';

// PHPMailer for sending OTP
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../libraries/PHPMailer/src/Exception.php';
require __DIR__ . '/../libraries/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../libraries/PHPMailer/src/SMTP.php';

// Handle form submission
$success_message = "";
$error_message = "";
$show_otp_form = false;

// Function to send OTP email
function sendOTPEmail($toEmail, $otp, $username) {
    $mail = new PHPMailer(true);
    try {
        // SMTP Configuration with timeout settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Performance optimization - set timeouts
        $mail->Timeout = 10; // Connection timeout (10 seconds)
        $mail->SMTPKeepAlive = false; // Don't keep connection alive
        $mail->SMTPAutoTLS = true; // Auto TLS
        
        // Disable debug output for faster processing
        $mail->SMTPDebug = 0;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Email Verification - New Admin Account | NIELIT Bhubaneswar';
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f8fafc; border-radius: 10px;">
            <div style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h2 style="color: white; margin: 0;">NIELIT Bhubaneswar</h2>
                <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0;">Admin Account Verification</p>
            </div>
            <div style="background: white; padding: 30px; border-radius: 0 0 10px 10px;">
                <p style="font-size: 16px; color: #1e293b;">Dear ' . htmlspecialchars($username) . ',</p>
                <p style="font-size: 14px; color: #64748b;">A new administrator account has been created for you. Please verify your email address using the OTP below:</p>
                <div style="background: #f1f5f9; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;">
                    <h1 style="color: #2563eb; margin: 0; font-size: 36px; letter-spacing: 8px;">' . htmlspecialchars($otp) . '</h1>
                </div>
                <p style="font-size: 13px; color: #64748b;">This OTP is valid for 10 minutes. Do not share this code with anyone.</p>
                <div style="background: #fef3c7; padding: 15px; border-radius: 8px; border-left: 4px solid #f59e0b; margin: 20px 0;">
                    <p style="margin: 0; font-size: 13px; color: #92400e;">
                        <strong>Security Note:</strong> If you did not request this admin account, please contact the system administrator immediately.
                    </p>
                </div>
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
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Step 1: Send OTP
if (isset($_POST['send_otp'])) {
    $new_username = trim($_POST['username'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $new_email = trim($_POST['email'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');

    if ($new_username && $new_password && $new_email && $new_phone) {
        // Check if username already exists
        $check_stmt = $conn->prepare("SELECT id FROM admin WHERE LOWER(username) = LOWER(?)");
        $check_stmt->bind_param("s", $new_username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Username already exists. Please choose a different username.";
        } else {
            // Check if email already exists
            $check_email_stmt = $conn->prepare("SELECT id FROM admin WHERE LOWER(email) = LOWER(?)");
            $check_email_stmt->bind_param("s", $new_email);
            $check_email_stmt->execute();
            $check_email_result = $check_email_stmt->get_result();
            
            if ($check_email_result->num_rows > 0) {
                $error_message = "Email already exists. Please use a different email address.";
            } else {
                // Generate OTP
                $otp = rand(100000, 999999);
                
                // Store data in session temporarily
                $_SESSION['temp_admin_data'] = [
                    'username' => $new_username,
                    'password' => $new_password,
                    'email' => $new_email,
                    'phone' => $new_phone,
                    'otp' => $otp,
                    'otp_time' => time()
                ];
                
                // Send OTP email
                if (sendOTPEmail($new_email, $otp, $new_username)) {
                    $success_message = "OTP sent successfully to " . htmlspecialchars($new_email) . ". Please check your email.";
                    $show_otp_form = true;
                } else {
                    $error_message = "Failed to send OTP email. Please check email configuration or try again.";
                }
            }
        }
    } else {
        $error_message = "All fields are required.";
    }
}

// Step 2: Verify OTP and Create Admin
if (isset($_POST['verify_otp'])) {
    $input_otp = trim($_POST['otp'] ?? '');
    
    if (!isset($_SESSION['temp_admin_data'])) {
        $error_message = "Session expired. Please start again.";
    } elseif ((time() - $_SESSION['temp_admin_data']['otp_time']) > 600) {
        $error_message = "OTP expired. Please request a new OTP.";
        unset($_SESSION['temp_admin_data']);
    } elseif ($input_otp == $_SESSION['temp_admin_data']['otp']) {
        // OTP verified, create admin account
        $admin_data = $_SESSION['temp_admin_data'];
        $hashed_password = password_hash($admin_data['password'], PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO admin (username, password, phone, email) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $admin_data['username'], $hashed_password, $admin_data['phone'], $admin_data['email']);

        if ($stmt->execute()) {
            $success_message = "New admin '" . htmlspecialchars($admin_data['username']) . "' added successfully! Email verified.";
            unset($_SESSION['temp_admin_data']);
            $show_otp_form = false;
        } else {
            $error_message = "Failed to add admin. Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $error_message = "Invalid OTP. Please try again.";
        $show_otp_form = true;
    }
}

// Resend OTP
if (isset($_POST['resend_otp'])) {
    if (!isset($_SESSION['temp_admin_data'])) {
        $error_message = "Session expired. Please start again.";
    } else {
        $admin_data = $_SESSION['temp_admin_data'];
        $otp = rand(100000, 999999);
        
        $_SESSION['temp_admin_data']['otp'] = $otp;
        $_SESSION['temp_admin_data']['otp_time'] = time();
        
        if (sendOTPEmail($admin_data['email'], $otp, $admin_data['username'])) {
            $success_message = "OTP resent successfully to " . htmlspecialchars($admin_data['email']);
            $show_otp_form = true;
        } else {
            $error_message = "Failed to resend OTP. Please try again.";
        }
    }
}

// Check if we should show OTP form from session
if (isset($_SESSION['temp_admin_data']) && !$show_otp_form && empty($success_message) && empty($error_message)) {
    $show_otp_form = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <style>
        .otp-input {
            font-size: 24px;
            letter-spacing: 10px;
            text-align: center;
            font-weight: 700;
            padding: 16px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
        }
        
        .step.active .step-circle {
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(13, 71, 161, 0.4);
        }
        
        .step.completed .step-circle {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .step-label {
            font-weight: 600;
            color: #64748b;
        }
        
        .step.active .step-label {
            color: #0d47a1;
        }
        
        .step.completed .step-label {
            color: #10b981;
        }
        
        .step-arrow {
            color: #cbd5e0;
            font-size: 20px;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2563eb;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-box i {
            color: #2563eb;
            margin-right: 8px;
        }
        
        .countdown-timer {
            font-size: 14px;
            color: #64748b;
            margin-top: 10px;
            text-align: center;
        }
        
        .countdown-timer.warning {
            color: #f59e0b;
            font-weight: 600;
        }
        
        .countdown-timer.expired {
            color: #ef4444;
            font-weight: 700;
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-logo">
            <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo">
            <h5>NIELIT Admin</h5>
            <small>Bhubaneswar</small>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="students.php" class="nav-link">
                    <i class="fas fa-users"></i> Students
                </a>
            </div>
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/manage_batches.php" class="nav-link">
                    <i class="fas fa-layer-group"></i> Batches
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php" class="nav-link">
                    <i class="fas fa-project-diagram"></i> Schemes/Projects
                </a>
            </div>
            
            <div class="nav-divider"></div>
            <div class="nav-section-title">System Settings</div>
            
            <div class="nav-item">
                <a href="manage_centres.php" class="nav-link">
                    <i class="fas fa-building"></i> Training Centres
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_themes.php" class="nav-link">
                    <i class="fas fa-palette"></i> Themes
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_homepage.php" class="nav-link">
                    <i class="fas fa-home"></i> Homepage Content
                </a>
            </div>
            
            <div class="nav-divider"></div>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/approve_students.php" class="nav-link">
                    <i class="fas fa-user-check"></i> Approve Students
                </a>
            </div>
            <div class="nav-item">
                <a href="add_admin.php" class="nav-link active">
                    <i class="fas fa-user-shield"></i> Add Admin
                </a>
            </div>
            <div class="nav-item">
                <a href="reset_password.php" class="nav-link">
                    <i class="fas fa-key"></i> Reset Password
                </a>
            </div>
            
            <div class="nav-divider"></div>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/index.php" class="nav-link">
                    <i class="fas fa-globe"></i> View Website
                </a>
            </div>
            <div class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-user-shield"></i> Add New Admin</h4>
                <small>Create a new administrator account with email verification</small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role">Administrator</span>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="admin-main">
            <!-- Success/Error Messages -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message) && !$show_otp_form): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo !$show_otp_form ? 'active' : 'completed'; ?>">
                    <div class="step-circle">
                        <?php echo !$show_otp_form ? '1' : '<i class="fas fa-check"></i>'; ?>
                    </div>
                    <span class="step-label">Admin Details</span>
                </div>
                <div class="step-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div class="step <?php echo $show_otp_form ? 'active' : ''; ?>">
                    <div class="step-circle">2</div>
                    <span class="step-label">Email Verification</span>
                </div>
            </div>

            <?php if (!$show_otp_form): ?>
            <!-- Step 1: Add Admin Form -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-user-plus"></i> Step 1: Enter Admin Details
                    </h5>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Email Verification Required:</strong> An OTP will be sent to the provided email address for verification before creating the admin account.
                </div>
                
                <form method="POST" action="add_admin.php" id="adminForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user"></i> Username *
                            </label>
                            <input type="text" class="form-control" name="username" required autofocus 
                                   pattern="[a-zA-Z0-9_]{3,20}" 
                                   title="Username must be 3-20 characters (letters, numbers, underscore only)">
                            <small class="text-muted">3-20 characters, letters, numbers, and underscore only</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i> Email *
                            </label>
                            <input type="email" class="form-control" name="email" required>
                            <small class="text-muted">OTP will be sent to this email</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock"></i> Password *
                            </label>
                            <input type="password" class="form-control" name="password" id="password" required 
                                   minlength="8"
                                   pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                   title="Password must be at least 8 characters with uppercase, lowercase, and number">
                            <small class="text-muted">Min 8 characters with uppercase, lowercase, and number</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-phone"></i> Phone *
                            </label>
                            <input type="text" class="form-control" name="phone" required 
                                   pattern="[0-9]{10}" 
                                   title="Phone must be 10 digits">
                            <small class="text-muted">10-digit phone number</small>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 16px;">
                        <button type="submit" name="send_otp" class="btn btn-primary" id="sendOtpBtn">
                            <i class="fas fa-paper-plane"></i> Send OTP
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
            
            <?php else: ?>
            <!-- Step 2: OTP Verification Form -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-shield-alt"></i> Step 2: Verify Email
                    </h5>
                </div>
                
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
                <?php endif; ?>
                
                <div class="info-box">
                    <i class="fas fa-envelope"></i>
                    <strong>OTP Sent!</strong> We've sent a 6-digit verification code to 
                    <strong><?php echo htmlspecialchars($_SESSION['temp_admin_data']['email'] ?? ''); ?></strong>
                </div>
                
                <form method="POST" action="add_admin.php" id="otpForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-key"></i> Enter 6-Digit OTP *
                        </label>
                        <input type="text" class="form-control otp-input" name="otp" 
                               required autofocus 
                               pattern="[0-9]{6}" 
                               maxlength="6"
                               placeholder="000000"
                               title="Enter 6-digit OTP">
                        <div class="countdown-timer" id="countdown">
                            <i class="fas fa-clock"></i> OTP valid for: <span id="timer">10:00</span>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 16px;">
                        <button type="submit" name="verify_otp" class="btn btn-success">
                            <i class="fas fa-check-circle"></i> Verify & Create Admin
                        </button>
                        <button type="submit" name="resend_otp" class="btn btn-warning">
                            <i class="fas fa-redo"></i> Resend OTP
                        </button>
                        <a href="add_admin.php" class="btn btn-secondary" onclick="return confirm('Are you sure? This will cancel the process.');">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Security Information -->
            <div class="content-card" style="background: rgba(37, 99, 235, 0.05); border-left: 4px solid #2563eb; margin-top: 20px;">
                <h6 style="color: #2563eb; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-shield-alt"></i> Security Information
                </h6>
                <ul style="margin: 0; padding-left: 20px; color: #64748b; font-size: 14px;">
                    <li>Email verification ensures the admin has access to the provided email</li>
                    <li>OTP is valid for 10 minutes only</li>
                    <li>Each OTP can only be used once</li>
                    <li>Admin account will only be created after successful email verification</li>
                    <li>All admin actions are logged for security audit</li>
                </ul>
            </div>
        </div>
    </main>
</div>

<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
// Show loading state when sending OTP
const adminForm = document.getElementById('adminForm');
const sendOtpBtn = document.getElementById('sendOtpBtn');

if (adminForm && sendOtpBtn) {
    adminForm.addEventListener('submit', function(e) {
        if (sendOtpBtn.name === 'send_otp') {
            sendOtpBtn.disabled = true;
            sendOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending OTP...';
            showToast('Sending OTP email, please wait...', 'info');
        }
    });
}

// OTP Countdown Timer
<?php if ($show_otp_form && isset($_SESSION['temp_admin_data'])): ?>
const otpTime = <?php echo $_SESSION['temp_admin_data']['otp_time']; ?>;
const currentTime = <?php echo time(); ?>;
let remainingSeconds = 600 - (currentTime - otpTime);

function updateCountdown() {
    if (remainingSeconds <= 0) {
        document.getElementById('timer').textContent = 'EXPIRED';
        document.getElementById('countdown').classList.add('expired');
        document.querySelector('button[name="verify_otp"]').disabled = true;
        return;
    }
    
    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;
    document.getElementById('timer').textContent = 
        minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
    
    // Warning when less than 2 minutes
    if (remainingSeconds < 120) {
        document.getElementById('countdown').classList.add('warning');
    }
    
    remainingSeconds--;
    setTimeout(updateCountdown, 1000);
}

updateCountdown();
<?php endif; ?>

// Auto-format OTP input
const otpInput = document.querySelector('.otp-input');
if (otpInput) {
    otpInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
}

// Password strength indicator
const passwordInput = document.getElementById('password');
if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasLength = password.length >= 8;
        
        if (hasUpper && hasLower && hasNumber && hasLength) {
            this.style.borderColor = '#10b981';
        } else {
            this.style.borderColor = '#f59e0b';
        }
    });
}

// Show toast notifications
<?php if (!empty($success_message) && !$show_otp_form): ?>
toast.success('<?php echo addslashes($success_message); ?>');
<?php endif; ?>

<?php if (!empty($error_message)): ?>
toast.error('<?php echo addslashes($error_message); ?>');
<?php endif; ?>
</script>

</body>
</html>
