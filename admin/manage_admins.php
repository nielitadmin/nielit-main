<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if admin is logged in
if (!isset($_SESSION['admin']) || !isset($_SESSION['admin_role'])) {
    header("Location: login.php");
    exit();
}

// Only master_admin can access this page
if ($_SESSION['admin_role'] !== 'master_admin') {
    $_SESSION['message'] = "Access denied. Only Master Admins can manage admin accounts.";
    $_SESSION['message_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';

// PHPMailer for OTP
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../libraries/PHPMailer/src/Exception.php';
require __DIR__ . '/../libraries/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../libraries/PHPMailer/src/SMTP.php';

$success_message = "";
$error_message = "";
$show_otp_form = false;
$show_password_form = false;

// Function to send OTP
function sendResetOTP($toEmail, $otp, $username) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->Timeout = 10;
        $mail->SMTPKeepAlive = false;
        $mail->SMTPAutoTLS = true;
        $mail->SMTPDebug = 0;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - NIELIT Bhubaneswar Admin';
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f8fafc; border-radius: 10px;">
            <div style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h2 style="color: white; margin: 0;">NIELIT Bhubaneswar</h2>
                <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0;">Admin Password Reset</p>
            </div>
            <div style="background: white; padding: 30px; border-radius: 0 0 10px 10px;">
                <p style="font-size: 16px; color: #1e293b;">Dear ' . htmlspecialchars($username) . ',</p>
                <p style="font-size: 14px; color: #64748b;">A password reset has been requested for your admin account. Your OTP is:</p>
                <div style="background: #f1f5f9; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;">
                    <h1 style="color: #2563eb; margin: 0; font-size: 36px; letter-spacing: 8px;">' . htmlspecialchars($otp) . '</h1>
                </div>
                <p style="font-size: 13px; color: #64748b;">This OTP is valid for 10 minutes. Do not share this code with anyone.</p>
                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">
                <p style="font-size: 12px; color: #94a3b8; margin: 0;">
                    National Institute of Electronics and Information Technology<br>
                    Bhubaneswar Center
                </p>
            </div>
        </div>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Password reset OTP email failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Step 1: Send OTP
if (isset($_POST['send_reset_otp'])) {
    $admin_id = intval($_POST['admin_id']);
    
    if ($admin_id == $_SESSION['admin_id']) {
        $error_message = "You cannot reset your own password here. Use 'Change Password' instead.";
    } else {
        // Get admin details
        $stmt = $conn->prepare("SELECT username, email FROM admin WHERE id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            
            // Generate OTP
            $otp = rand(100000, 999999);
            
            // Store in session
            $_SESSION['reset_admin_id'] = $admin_id;
            $_SESSION['reset_admin_username'] = $admin['username'];
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_otp_time'] = time();
            
            // Send OTP
            if (sendResetOTP($admin['email'], $otp, $admin['username'])) {
                $success_message = "OTP sent to " . htmlspecialchars($admin['email']);
                $show_otp_form = true;
            } else {
                $error_message = "Failed to send OTP. Please try again.";
            }
        } else {
            $error_message = "Admin not found.";
        }
        
        $stmt->close();
    }
}

// Step 2: Verify OTP
if (isset($_POST['verify_reset_otp'])) {
    $input_otp = trim($_POST['otp'] ?? '');
    
    if (!isset($_SESSION['reset_otp']) || !isset($_SESSION['reset_otp_time'])) {
        $error_message = "Session expired. Please start again.";
    } elseif ((time() - $_SESSION['reset_otp_time']) > 600) {
        $error_message = "OTP expired. Please request a new one.";
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_time'], $_SESSION['reset_admin_id'], $_SESSION['reset_admin_username']);
    } elseif ($input_otp == $_SESSION['reset_otp']) {
        // OTP verified - show password form
        $show_password_form = true;
        $success_message = "OTP verified! Now enter the new password.";
    } else {
        $error_message = "Invalid OTP. Please try again.";
        $show_otp_form = true;
    }
}

// Step 3: Set new password
if (isset($_POST['set_new_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!isset($_SESSION['reset_admin_id'])) {
        $error_message = "Session expired. Please start again.";
    } elseif (empty($new_password) || empty($confirm_password)) {
        $error_message = "Please fill in both password fields.";
        $show_password_form = true;
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
        $show_password_form = true;
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
        $show_password_form = true;
    } else {
        $admin_id = $_SESSION['reset_admin_id'];
        $username = $_SESSION['reset_admin_username'];
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE admin SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $admin_id);
        
        if ($stmt->execute()) {
            $success_message = "Password reset successfully for admin: " . htmlspecialchars($username);
            // Clear session
            unset($_SESSION['reset_otp'], $_SESSION['reset_otp_time'], $_SESSION['reset_admin_id'], $_SESSION['reset_admin_username']);
        } else {
            $error_message = "Failed to update password: " . $conn->error;
            $show_password_form = true;
        }
        
        $stmt->close();
    }
}

// Resend OTP
if (isset($_POST['resend_reset_otp'])) {
    if (!isset($_SESSION['reset_admin_id'])) {
        $error_message = "Session expired. Please start again.";
    } else {
        $admin_id = $_SESSION['reset_admin_id'];
        
        $stmt = $conn->prepare("SELECT username, email FROM admin WHERE id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            $otp = rand(100000, 999999);
            
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_otp_time'] = time();
            
            if (sendResetOTP($admin['email'], $otp, $admin['username'])) {
                $success_message = "OTP resent successfully.";
                $show_otp_form = true;
            } else {
                $error_message = "Failed to resend OTP.";
            }
        }
        
        $stmt->close();
    }
}

// Cancel password reset
if (isset($_POST['cancel_reset'])) {
    unset($_SESSION['reset_otp'], $_SESSION['reset_otp_time'], $_SESSION['reset_admin_id'], $_SESSION['reset_admin_username']);
    header("Location: manage_admins.php");
    exit();
}

// Check if we should show forms from session
if (isset($_SESSION['reset_otp']) && !$show_otp_form && !$show_password_form && empty($success_message) && empty($error_message)) {
    $show_otp_form = true;
}


// Handle role update
if (isset($_POST['update_role'])) {
    $admin_id = intval($_POST['admin_id']);
    $new_role = $_POST['role'];
    
    // Prevent changing own role
    if ($admin_id == $_SESSION['admin_id']) {
        $error_message = "You cannot change your own role.";
    } else {
        $stmt = $conn->prepare("UPDATE admin SET role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("si", $new_role, $admin_id);
        
        if ($stmt->execute()) {
            $success_message = "Admin role updated successfully!";
        } else {
            $error_message = "Failed to update role: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle admin deletion
if (isset($_POST['delete_admin'])) {
    $admin_id = intval($_POST['admin_id']);
    
    // Prevent deleting own account
    if ($admin_id == $_SESSION['admin_id']) {
        $error_message = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM admin WHERE id = ?");
        $stmt->bind_param("i", $admin_id);
        
        if ($stmt->execute()) {
            $success_message = "Admin account deleted successfully!";
        } else {
            $error_message = "Failed to delete admin: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all admins
$admins_query = "SELECT id, username, email, phone, role, created_at, updated_at FROM admin ORDER BY created_at DESC";
$admins_result = $conn->query($admins_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <style>
        .role-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        
        .role-master {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .role-coordinator {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        
        .admin-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .admin-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .admin-card.current-user {
            border: 2px solid #10b981;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(5, 150, 105, 0.05) 100%);
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .admin-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 14px;
        }
        
        .info-item i {
            color: #0d47a1;
            width: 20px;
        }
        
        .admin-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal-dialog {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 20px 30px;
        }
        
        .modal-title {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }
        
        .modal-body {
            padding: 30px;
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
            
            <?php if ($_SESSION['admin_role'] === 'master_admin'): ?>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php" class="nav-link">
                    <i class="fas fa-project-diagram"></i> Schemes/Projects
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($_SESSION['admin_role'] === 'master_admin'): ?>
            <div class="nav-divider"></div>
            <div class="nav-section-title">System Settings</div>
            
            <div class="nav-item">
                <a href="manage_centres.php" class="nav-link">
                    <i class="fas fa-building"></i> Training Centres
                </a>
            </div>
            
            <div class="nav-divider"></div>
            <?php endif; ?>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/approve_students.php" class="nav-link">
                    <i class="fas fa-user-check"></i> Approve Students
                </a>
            </div>
            
            <?php if ($_SESSION['admin_role'] === 'master_admin'): ?>
            <div class="nav-item">
                <a href="add_admin.php" class="nav-link">
                    <i class="fas fa-user-plus"></i> Add Admin
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_admins.php" class="nav-link active">
                    <i class="fas fa-users-cog"></i> Manage Admins
                </a>
            </div>
            <?php endif; ?>
            
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
                <h4><i class="fas fa-users-cog"></i> Manage Admin Accounts</h4>
                <small>View and manage all administrator accounts</small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role">Master Administrator</span>
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

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid" style="margin-bottom: 24px;">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $admins_result->num_rows; ?></h3>
                    <p class="stat-label">Total Admins</p>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <?php
                    $master_count = 0;
                    $coord_count = 0;
                    $temp_result = $conn->query("SELECT role FROM admin");
                    while ($row = $temp_result->fetch_assoc()) {
                        if ($row['role'] === 'master_admin') $master_count++;
                        else $coord_count++;
                    }
                    ?>
                    <h3 class="stat-value"><?php echo $master_count; ?></h3>
                    <p class="stat-label">Master Admins</p>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $coord_count; ?></h3>
                    <p class="stat-label">Course Coordinators</p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="margin-bottom: 24px;">
                <a href="add_admin.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add New Admin
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <!-- Admin List -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list"></i> All Administrator Accounts
                    </h5>
                </div>
                
                <div style="padding: 20px;">
                    <?php if ($admins_result && $admins_result->num_rows > 0): ?>
                        <?php while ($admin = $admins_result->fetch_assoc()): ?>
                            <div class="admin-card <?php echo ($admin['id'] == $_SESSION['admin_id']) ? 'current-user' : ''; ?>">
                                <div class="admin-header">
                                    <div>
                                        <h5 style="margin: 0; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                                            <i class="fas fa-user-circle" style="color: #0d47a1;"></i>
                                            <?php echo htmlspecialchars($admin['username']); ?>
                                            <?php if ($admin['id'] == $_SESSION['admin_id']): ?>
                                                <span style="background: #10b981; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">YOU</span>
                                            <?php endif; ?>
                                        </h5>
                                    </div>
                                    <div>
                                        <span class="role-badge <?php echo $admin['role'] === 'master_admin' ? 'role-master' : 'role-coordinator'; ?>">
                                            <i class="fas <?php echo $admin['role'] === 'master_admin' ? 'fa-crown' : 'fa-user-tie'; ?>"></i>
                                            <?php echo $admin['role'] === 'master_admin' ? 'Master Admin' : 'Course Coordinator'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="admin-info">
                                    <div class="info-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($admin['email']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo htmlspecialchars($admin['phone']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-plus"></i>
                                        <span>Created: <?php echo date('d M Y, h:i A', strtotime($admin['created_at'])); ?></span>
                                    </div>
                                    <?php if ($admin['updated_at']): ?>
                                    <div class="info-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <span>Updated: <?php echo date('d M Y, h:i A', strtotime($admin['updated_at'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                <div class="admin-actions">
                                    <form method="POST" style="display: inline-block; margin: 0;">
                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                        <select name="role" class="form-select" style="display: inline-block; width: auto; padding: 8px 12px; margin-right: 8px;">
                                            <option value="master_admin" <?php echo $admin['role'] === 'master_admin' ? 'selected' : ''; ?>>Master Admin</option>
                                            <option value="course_coordinator" <?php echo $admin['role'] === 'course_coordinator' ? 'selected' : ''; ?>>Course Coordinator</option>
                                        </select>
                                        <button type="submit" name="update_role" class="btn btn-warning btn-sm">
                                            <i class="fas fa-sync-alt"></i> Update Role
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline-block; margin: 0;">
                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                        <button type="submit" name="send_reset_otp" class="btn btn-info btn-sm">
                                            <i class="fas fa-key"></i> Reset Password
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline-block; margin: 0;" onsubmit="return confirm('Are you sure you want to delete this admin account? This action cannot be undone.');">
                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                        <button type="submit" name="delete_admin" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <div class="admin-actions">
                                    <div style="color: #64748b; font-size: 14px;">
                                        <i class="fas fa-info-circle"></i> You cannot modify your own account
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #64748b;">
                            <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.3;"></i>
                            <p style="margin: 0; font-size: 16px;">No admin accounts found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Role Information -->
            <div class="content-card" style="margin-top: 24px; background: rgba(37, 99, 235, 0.05); border-left: 4px solid #2563eb;">
                <h6 style="color: #2563eb; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-info-circle"></i> Role Permissions
                </h6>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h6 style="color: #10b981; margin: 0 0 8px 0;">
                            <i class="fas fa-crown"></i> Master Admin
                        </h6>
                        <ul style="margin: 0; padding-left: 20px; color: #64748b; font-size: 14px;">
                            <li>Full access to all features</li>
                            <li>Can add, edit, and delete admin accounts</li>
                            <li>Can change admin roles</li>
                            <li>Access to all system settings</li>
                            <li>Manage training centres and themes</li>
                        </ul>
                    </div>
                    <div>
                        <h6 style="color: #3b82f6; margin: 0 0 8px 0;">
                            <i class="fas fa-user-tie"></i> Course Coordinator
                        </h6>
                        <ul style="margin: 0; padding-left: 20px; color: #64748b; font-size: 14px;">
                            <li>Dashboard access</li>
                            <li>Manage students</li>
                            <li>Manage courses</li>
                            <li>Manage batches</li>
                            <li>Approve students</li>
                            <li>Reset own password</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- OTP Verification Modal -->
<?php if ($show_otp_form): ?>
<div class="modal" style="display: flex !important;" id="otpModal">
    <div class="modal-dialog" style="max-width: 500px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);">
            <h5 class="modal-title" style="color: white;"><i class="fas fa-shield-alt"></i> Verify OTP</h5>
        </div>
        <form method="POST">
            <div class="modal-body">
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
                <?php endif; ?>
                
                <div class="info-box" style="background: #e3f2fd; border-left: 4px solid #2563eb; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-envelope" style="color: #2563eb;"></i>
                    <strong>OTP Sent!</strong> Check the email of admin: <strong><?php echo htmlspecialchars($_SESSION['reset_admin_username'] ?? ''); ?></strong>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-key"></i> Enter 6-Digit OTP *
                    </label>
                    <input type="text" class="form-control" name="otp" required autofocus 
                           pattern="[0-9]{6}" maxlength="6" placeholder="000000"
                           style="font-size: 24px; letter-spacing: 10px; text-align: center; font-weight: 700;">
                    <small style="color: #64748b; font-size: 12px; display: block; margin-top: 8px;">
                        <i class="fas fa-clock"></i> OTP valid for 10 minutes
                    </small>
                </div>
            </div>
            <div style="padding: 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; gap: 12px;">
                <button type="submit" name="verify_reset_otp" class="btn btn-success">
                    <i class="fas fa-check-circle"></i> Verify OTP
                </button>
                <button type="submit" name="resend_reset_otp" class="btn btn-warning" formnovalidate>
                    <i class="fas fa-redo"></i> Resend OTP
                </button>
                <button type="submit" name="cancel_reset" class="btn btn-secondary" formnovalidate>
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Set New Password Modal -->
<?php if ($show_password_form): ?>
<div class="modal" style="display: flex !important;" id="passwordModal">
    <div class="modal-dialog" style="max-width: 500px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <h5 class="modal-title" style="color: white;"><i class="fas fa-lock"></i> Set New Password</h5>
        </div>
        <form method="POST">
            <div class="modal-body">
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
                <?php endif; ?>
                
                <div class="info-box" style="background: #d1fae5; border-left: 4px solid #10b981; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                    <strong>OTP Verified!</strong> Now set a new password for: <strong><?php echo htmlspecialchars($_SESSION['reset_admin_username'] ?? ''); ?></strong>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> New Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" class="form-control" name="new_password" id="new_password" required 
                               minlength="8" placeholder="Enter new password" style="padding-right: 45px;">
                        <span onclick="togglePasswordVisibility('new_password', 'toggleNewPassword')" 
                              style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b;">
                            <i class="fas fa-eye" id="toggleNewPassword"></i>
                        </span>
                    </div>
                    <small style="color: #64748b; font-size: 12px; display: block; margin-top: 8px;">
                        <i class="fas fa-info-circle"></i> Minimum 8 characters
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Confirm Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required 
                               minlength="8" placeholder="Confirm new password" style="padding-right: 45px;">
                        <span onclick="togglePasswordVisibility('confirm_password', 'toggleConfirmPassword')" 
                              style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b;">
                            <i class="fas fa-eye" id="toggleConfirmPassword"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div style="padding: 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; gap: 12px;">
                <button type="submit" name="set_new_password" class="btn btn-success">
                    <i class="fas fa-check-circle"></i> Set Password
                </button>
                <button type="submit" name="cancel_reset" class="btn btn-secondary" formnovalidate>
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
// Toggle password visibility
function togglePasswordVisibility(inputId, iconId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = document.getElementById(iconId);
    
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

// Show toast notifications
<?php if (!empty($success_message)): ?>
toast.success('<?php echo addslashes(strip_tags($success_message)); ?>');
<?php endif; ?>

<?php if (!empty($error_message)): ?>
toast.error('<?php echo addslashes($error_message); ?>');
<?php endif; ?>
</script>

</body>
</html>
<?php $conn->close(); ?>
