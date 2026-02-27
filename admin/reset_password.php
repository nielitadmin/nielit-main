<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['admin'])) {
    die("Access denied. Please log in as admin.");
}

$message = "";
$new_password = "";

function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id']);

    if (empty($student_id)) {
        $message = "Please enter a Student ID.";
    } else {
        // Generate random password like bin2hex(random_bytes(8))
        $new_password = bin2hex(random_bytes(8)); // 16 hex chars

        // Hash the password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update in database
        $stmt = $conn->prepare("UPDATE students SET password = ? WHERE student_id = ?");
        $stmt->bind_param("ss", $hashed_password, $student_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = "Password reset successfully for Student ID: $student_id";
        } else {
            $message = "Student ID not found.";
            $new_password = "";  // Clear password if update failed
        }

        $stmt->close();
    }
}

    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Student Password - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
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
                <a href="add_admin.php" class="nav-link">
                    <i class="fas fa-user-shield"></i> Add Admin
                </a>
            </div>
            <div class="nav-item">
                <a href="reset_password.php" class="nav-link active">
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
                <h4><i class="fas fa-key"></i> Reset Student Password</h4>
                <small>Generate new password for student account</small>
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
            <?php if ($message): ?>
                <div class="alert <?php echo $new_password ? 'alert-success' : 'alert-warning'; ?>">
                    <i class="fas fa-<?php echo $new_password ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($new_password): ?>
                <div class="content-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none;">
                    <div style="text-align: center; padding: 20px;">
                        <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 16px;"></i>
                        <h3 style="margin: 0 0 16px 0;">Password Reset Successful!</h3>
                        <p style="margin: 0 0 24px 0; opacity: 0.9;">New password for Student ID: <strong><?php echo htmlspecialchars($student_id); ?></strong></p>
                        <div style="background: rgba(255,255,255,0.2); padding: 20px; border-radius: 12px; margin-bottom: 16px;">
                            <p style="margin: 0 0 8px 0; font-size: 14px; opacity: 0.9;">New Password:</p>
                            <h2 style="margin: 0; font-family: monospace; letter-spacing: 2px;"><?php echo htmlspecialchars($new_password); ?></h2>
                        </div>
                        <p style="margin: 0; font-size: 13px; opacity: 0.8;">
                            <i class="fas fa-info-circle"></i> Make sure to share this password securely with the student.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Reset Password Form -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-key"></i> Reset Password
                    </h5>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-id-card"></i> Student ID *
                        </label>
                        <input type="text" class="form-control" name="student_id" placeholder="Enter Student ID" required autofocus>
                        <small style="color: #64748b; font-size: 12px; display: block; margin-top: 8px;">
                            <i class="fas fa-info-circle"></i> Enter the student ID to generate a new random password
                        </small>
                    </div>
                    
                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Reset Password
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Information Card -->
            <div class="content-card" style="background: rgba(37, 99, 235, 0.05); border-left: 4px solid #2563eb;">
                <h6 style="color: #2563eb; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-shield-alt"></i> Security Information
                </h6>
                <ul style="margin: 0; padding-left: 20px; color: #64748b; font-size: 14px;">
                    <li>A new random password will be automatically generated</li>
                    <li>The password is 16 characters long and highly secure</li>
                    <li>Make sure to share the password securely with the student</li>
                    <li>Advise the student to change their password after first login</li>
                </ul>
            </div>
        </div>
    </main>
</div>

</body>
</html>
