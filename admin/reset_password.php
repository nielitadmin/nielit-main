<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['admin']) || !isset($_SESSION['admin_role'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$new_password = "";
$reset_type = $_POST['reset_type'] ?? 'student'; // 'student' or 'admin'
$is_master_admin = ($_SESSION['admin_role'] === 'master_admin');

function generateRandomPassword($length = 16) {
    return bin2hex(random_bytes($length / 2));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($reset_type === 'student') {
        // Reset Student Password
        $student_id = trim($_POST['student_id'] ?? '');

        if (empty($student_id)) {
            $message = "Please enter a Student ID.";
        } else {
            $new_password = generateRandomPassword(16);
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE students SET password = ? WHERE student_id = ?");
            $stmt->bind_param("ss", $hashed_password, $student_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = "Password reset successfully for Student ID: $student_id";
            } else {
                $message = "Student ID not found.";
                $new_password = "";
            }

            $stmt->close();
        }
    } elseif ($reset_type === 'admin' && $is_master_admin) {
        // Reset Admin Password (Master Admin only)
        $admin_username = trim($_POST['admin_username'] ?? '');

        if (empty($admin_username)) {
            $message = "Please enter an Admin Username.";
        } elseif (strtolower($admin_username) === strtolower($_SESSION['admin'])) {
            $message = "You cannot reset your own password. Use 'Change Password' instead.";
        } else {
            // Check if admin exists
            $check_stmt = $conn->prepare("SELECT id FROM admin WHERE LOWER(username) = LOWER(?)");
            $check_stmt->bind_param("s", $admin_username);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $new_password = generateRandomPassword(16);
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("UPDATE admin SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE LOWER(username) = LOWER(?)");
                $stmt->bind_param("ss", $hashed_password, $admin_username);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    $message = "Password reset successfully for Admin: $admin_username";
                } else {
                    $message = "Failed to reset admin password.";
                    $new_password = "";
                }

                $stmt->close();
            } else {
                $message = "Admin username not found.";
            }

            $check_stmt->close();
        }
    }
}
    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <style>
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: #64748b;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 15px;
        }
        
        .tab:hover {
            color: #0d47a1;
            background: rgba(13, 71, 161, 0.05);
        }
        
        .tab.active {
            color: #0d47a1;
            border-bottom-color: #0d47a1;
            background: rgba(13, 71, 161, 0.05);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .reset-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .badge-student {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        
        .badge-admin {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
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
            
            <?php if ($is_master_admin): ?>
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
            <?php endif; ?>
            
            <div class="nav-divider"></div>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/approve_students.php" class="nav-link">
                    <i class="fas fa-user-check"></i> Approve Students
                </a>
            </div>
            
            <?php if ($is_master_admin): ?>
            <div class="nav-item">
                <a href="add_admin.php" class="nav-link">
                    <i class="fas fa-user-plus"></i> Add Admin
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_admins.php" class="nav-link">
                    <i class="fas fa-users-cog"></i> Manage Admins
                </a>
            </div>
            <?php endif; ?>
            
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
