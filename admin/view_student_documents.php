<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");
    exit();
}

// Get student ID
if (!isset($_GET['id'])) {
    header("Location: students.php");
    exit();
}

$student_id = $_GET['id'];

// Get filter parameters to preserve them
$filter_course = isset($_GET['filter_course']) ? $_GET['filter_course'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build return URL with filters
$return_url = 'students.php';
$return_params = [];
if (!empty($filter_course) && $filter_course != 'All') {
    $return_params[] = 'filter_course=' . urlencode($filter_course);
}
if (!empty($start_date)) {
    $return_params[] = 'start_date=' . urlencode($start_date);
}
if (!empty($end_date)) {
    $return_params[] = 'end_date=' . urlencode($end_date);
}
if (!empty($return_params)) {
    $return_url .= '?' . implode('&', $return_params);
}

// Fetch student data
$sql = "SELECT * FROM students WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['message'] = "Student not found!";
    header("Location: students.php");
    exit();
}

$student = $result->fetch_assoc();

// Fetch education details from separate table
$sql_education = "SELECT * FROM education_details WHERE student_id = ? ORDER BY id ASC";
$stmt_education = $conn->prepare($sql_education);
$education_records = [];
if ($stmt_education) {
    $stmt_education->bind_param("s", $student_id);
    $stmt_education->execute();
    $education_result = $stmt_education->get_result();
    while ($row = $education_result->fetch_assoc()) {
        $education_records[] = $row;
    }
    $stmt_education->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Documents - <?php echo htmlspecialchars($student['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <style>
        .document-card {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s;
            border: 2px solid #e2e8f0;
            height: 100%;
        }
        .document-card:hover {
            border-color: #0d47a1;
            box-shadow: 0 8px 24px rgba(13, 71, 161, 0.15);
            transform: translateY(-4px);
        }
        .document-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        .pdf-icon { color: #dc3545; }
        .image-icon { color: #28a745; }
        .no-doc-icon { color: #94a3b8; }
        
        .document-preview {
            max-height: 200px;
            border-radius: 8px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .doc-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
        }
        
        .doc-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 12px;
        }
        
        .doc-status.uploaded {
            background: #d1fae5;
            color: #065f46;
        }
        
        .doc-status.missing {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }
        
        .info-item {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #0d47a1;
        }
        
        .info-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 16px;
            color: #1e293b;
            font-weight: 500;
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
                <a href="students.php" class="nav-link active">
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
                <h4><i class="fas fa-folder-open"></i> Student Documents</h4>
                <small><?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['student_id']); ?>)</small>
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
            <!-- Action Buttons -->
            <div class="content-card" style="margin-bottom: 24px;">
                <div style="display: flex; gap: 12px; justify-content: flex-start;">
                    <a href="<?php echo $return_url; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Students
                    </a>
                    <a href="edit_student.php?id=<?php echo $student_id; ?><?php 
                        if (!empty($filter_course) && $filter_course != 'All') echo '&filter_course=' . urlencode($filter_course);
                        if (!empty($start_date)) echo '&start_date=' . urlencode($start_date);
                        if (!empty($end_date)) echo '&end_date=' . urlencode($end_date);
                    ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Student
                    </a>
                    <a href="download_student_form.php?id=<?php echo $student_id; ?>" class="btn btn-success" target="_blank">
                        <i class="fas fa-download"></i> Download Form
                    </a>
                </div>
            </div>

            <!-- Documents Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 24px;">
                <!-- Passport Photo -->
                <div class="document-card">
                    <h5 class="doc-title"><i class="fas fa-image"></i> Passport Photo</h5>
                    <?php if (!empty($student['passport_photo']) && file_exists(__DIR__ . '/../' . $student['passport_photo'])): ?>
                        <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                        <br>
                        <img src="../<?php echo htmlspecialchars($student['passport_photo']); ?>" 
                             alt="Passport Photo" 
                             class="document-preview">
                        <br>
                        <a href="../<?php echo htmlspecialchars($student['passport_photo']); ?>" 
                           target="_blank" 
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i> View Full Size
                        </a>
                    <?php else: ?>
                        <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                        <br>
                        <i class="fas fa-image-slash document-icon no-doc-icon"></i>
                        <p style="color: #64748b; margin-top: 12px;">No photo available</p>
                    <?php endif; ?>
                </div>

                <!-- Signature -->
                <div class="document-card">
                    <h5 class="doc-title"><i class="fas fa-signature"></i> Signature</h5>
                    <?php if (!empty($student['signature']) && file_exists(__DIR__ . '/../' . $student['signature'])): ?>
                        <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                        <br>
                        <img src="../<?php echo htmlspecialchars($student['signature']); ?>" 
                             alt="Signature" 
                             class="document-preview">
                        <br>
                        <a href="../<?php echo htmlspecialchars($student['signature']); ?>" 
                           target="_blank" 
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i> View Full Size
                        </a>
                    <?php else: ?>
                        <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                        <br>
                        <i class="fas fa-signature document-icon no-doc-icon"></i>
                        <p style="color: #64748b; margin-top: 12px;">No signature available</p>
                    <?php endif; ?>
                </div>

                <!-- Educational Documents -->
                <div class="document-card">
                    <h5 class="doc-title"><i class="fas fa-file-pdf"></i> Educational Documents</h5>
                    <?php if (!empty($student['documents']) && file_exists(__DIR__ . '/../' . $student['documents'])): ?>
                        <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                        <br>
                        <i class="fas fa-file-pdf document-icon pdf-icon"></i>
                        <p style="font-weight: 600; color: #1e293b; margin: 12px 0;">PDF Document</p>
                        <div style="display: flex; gap: 8px; justify-content: center;">
                            <a href="../<?php echo htmlspecialchars($student['documents']); ?>" 
                               target="_blank" 
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="../<?php echo htmlspecialchars($student['documents']); ?>" 
                               download 
                               class="btn btn-success btn-sm">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    <?php else: ?>
                        <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                        <br>
                        <i class="fas fa-file-pdf document-icon no-doc-icon"></i>
                        <p style="color: #64748b; margin-top: 12px;">No documents available</p>
                    <?php endif; ?>
                </div>

                <!-- Payment Receipt -->
                <div class="document-card">
                    <h5 class="doc-title"><i class="fas fa-receipt"></i> Payment Receipt</h5>
                    <?php 
                    if (!empty($student['payment_receipt']) && file_exists(__DIR__ . '/../' . $student['payment_receipt'])):
                        $receipt_ext = strtolower(pathinfo($student['payment_receipt'], PATHINFO_EXTENSION));
                    ?>
                        <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                        <br>
                        <?php if (in_array($receipt_ext, ['jpg', 'jpeg', 'png'])): ?>
                            <img src="../<?php echo htmlspecialchars($student['payment_receipt']); ?>" 
                                 alt="Payment Receipt" 
                                 class="document-preview">
                            <br>
                            <a href="../<?php echo htmlspecialchars($student['payment_receipt']); ?>" 
                               target="_blank" 
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View Full Size
                            </a>
                        <?php elseif ($receipt_ext === 'pdf'): ?>
                            <i class="fas fa-file-pdf document-icon pdf-icon"></i>
                            <p style="font-weight: 600; color: #1e293b; margin: 12px 0;">PDF Receipt</p>
                            <div style="display: flex; gap: 8px; justify-content: center;">
                                <a href="../<?php echo htmlspecialchars($student['payment_receipt']); ?>" 
                                   target="_blank" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="../<?php echo htmlspecialchars($student['payment_receipt']); ?>" 
                                   download 
                                   class="btn btn-success btn-sm">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                        <br>
                        <i class="fas fa-receipt document-icon no-doc-icon"></i>
                        <p style="color: #64748b; margin-top: 12px;">No receipt available</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Photo & Signature Section (Mandatory) -->
            <div class="content-card" style="margin-bottom: 24px;">
                <div class="card-header" style="background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%); border-left: 5px solid #dc2626; padding: 16px; border-radius: 8px 8px 0 0; margin: -24px -24px 24px -24px;">
                    <h5 class="card-title" style="margin: 0; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-images"></i> Photo & Signature
                        <span style="background: #dc2626; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-left: auto;">Mandatory</span>
                    </h5>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
                    <!-- Passport Photo -->
                    <div class="document-card">
                        <h5 class="doc-title"><i class="fas fa-image"></i> Passport Photo</h5>
                        <?php if (!empty($student['passport_photo']) && file_exists(__DIR__ . '/../' . $student['passport_photo'])): ?>
                            <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                            <br>
                            <img src="../<?php echo htmlspecialchars($student['passport_photo']); ?>" 
                                 alt="Passport Photo" 
                                 class="document-preview">
                            <br>
                            <a href="../<?php echo htmlspecialchars($student['passport_photo']); ?>" 
                               target="_blank" 
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View Full Size
                            </a>
                        <?php else: ?>
                            <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                            <br>
                            <i class="fas fa-image-slash document-icon no-doc-icon"></i>
                            <p style="color: #64748b; margin-top: 12px;">No photo available</p>
                        <?php endif; ?>
                    </div>

                    <!-- Signature -->
                    <div class="document-card">
                        <h5 class="doc-title"><i class="fas fa-signature"></i> Signature</h5>
                        <?php if (!empty($student['signature']) && file_exists(__DIR__ . '/../' . $student['signature'])): ?>
                            <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                            <br>
                            <img src="../<?php echo htmlspecialchars($student['signature']); ?>" 
                                 alt="Signature" 
                                 class="document-preview">
                            <br>
                            <a href="../<?php echo htmlspecialchars($student['signature']); ?>" 
                               target="_blank" 
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View Full Size
                            </a>
                        <?php else: ?>
                            <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                            <br>
                            <i class="fas fa-signature document-icon no-doc-icon"></i>
                            <p style="color: #64748b; margin-top: 12px;">No signature available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Identity Proof Section (Mandatory) -->
            <div class="content-card" style="margin-bottom: 24px;">
                <div class="card-header" style="background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%); border-left: 5px solid #dc2626; padding: 16px; border-radius: 8px 8px 0 0; margin: -24px -24px 24px -24px;">
                    <h5 class="card-title" style="margin: 0; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-id-card"></i> Identity Proof
                        <span style="background: #dc2626; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-left: auto;">Mandatory</span>
                    </h5>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
                    <!-- Aadhar Card -->
                    <div class="document-card">
                        <h5 class="doc-title"><i class="fas fa-id-card"></i> Aadhar Card</h5>
                        <?php if (!empty($student['aadhar_card_doc']) && file_exists(__DIR__ . '/../' . $student['aadhar_card_doc'])): 
                            $aadhar_ext = strtolower(pathinfo($student['aadhar_card_doc'], PATHINFO_EXTENSION));
                        ?>
                            <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                            <br>
                            <?php if (in_array($aadhar_ext, ['jpg', 'jpeg', 'png'])): ?>
                                <img src="../<?php echo htmlspecialchars($student['aadhar_card_doc']); ?>" 
                                     alt="Aadhar Card" 
                                     class="document-preview">
                                <br>
                                <a href="../<?php echo htmlspecialchars($student['aadhar_card_doc']); ?>" 
                                   target="_blank" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Full Size
                                </a>
                            <?php elseif ($aadhar_ext === 'pdf'): ?>
                                <i class="fas fa-file-pdf document-icon pdf-icon"></i>
                                <p style="font-weight: 600; color: #1e293b; margin: 12px 0;">PDF Document</p>
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="../<?php echo htmlspecialchars($student['aadhar_card_doc']); ?>" 
                                       target="_blank" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="../<?php echo htmlspecialchars($student['aadhar_card_doc']); ?>" 
                                       download 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                            <br>
                            <i class="fas fa-id-card document-icon no-doc-icon"></i>
                            <p style="color: #64748b; margin-top: 12px;">No Aadhar card available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Educational Qualifications Section -->
            <div class="content-card" style="margin-bottom: 24px;">
                <div class="card-header" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 5px solid #3b82f6; padding: 16px; border-radius: 8px 8px 0 0; margin: -24px -24px 24px -24px;">
                    <h5 class="card-title" style="margin: 0; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-graduation-cap"></i> Educational Qualifications
                        <span style="background: #dc2626; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-left: 8px;">10th Required</span>
                        <span style="background: #3b82f6; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Others Optional</span>
                    </h5>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
                    <!-- 10th Marksheet -->
                    <div class="document-card">
                        <h5 class="doc-title"><i class="fas fa-certificate"></i> 10th Marksheet</h5>
                        <?php if (!empty($student['tenth_marksheet_doc']) && file_exists(__DIR__ . '/../' . $student['tenth_marksheet_doc'])): 
                            $tenth_ext = strtolower(pathinfo($student['tenth_marksheet_doc'], PATHINFO_EXTENSION));
                        ?>
                            <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                            <br>
                            <?php if (in_array($tenth_ext, ['jpg', 'jpeg', 'png'])): ?>
                                <img src="../<?php echo htmlspecialchars($student['tenth_marksheet_doc']); ?>" 
                                     alt="10th Marksheet" 
                                     class="document-preview">
                                <br>
                                <a href="../<?php echo htmlspecialchars($student['tenth_marksheet_doc']); ?>" 
                                   target="_blank" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Full Size
                                </a>
                            <?php elseif ($tenth_ext === 'pdf'): ?>
                                <i class="fas fa-file-pdf document-icon pdf-icon"></i>
                                <p style="font-weight: 600; color: #1e293b; margin: 12px 0;">PDF Document</p>
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="../<?php echo htmlspecialchars($student['tenth_marksheet_doc']); ?>" 
                                       target="_blank" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="../<?php echo htmlspecialchars($student['tenth_marksheet_doc']); ?>" 
                                       download 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                            <br>
                            <i class="fas fa-certificate document-icon no-doc-icon"></i>
                            <p style="color: #64748b; margin-top: 12px;">No 10th marksheet available</p>
                        <?php endif; ?>
                    </div>

                    <!-- 12th Marksheet -->
                    <div class="document-card">
                        <h5 class="doc-title"><i class="fas fa-certificate"></i> 12th Marksheet/Diploma</h5>
                        <?php if (!empty($student['twelfth_marksheet_doc']) && file_exists(__DIR__ . '/../' . $student['twelfth_marksheet_doc'])): 
                            $twelfth_ext = strtolower(pathinfo($student['twelfth_marksheet_doc'], PATHINFO_EXTENSION));
                        ?>
                            <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                            <br>
                            <?php if (in_array($twelfth_ext, ['jpg', 'jpeg', 'png'])): ?>
                                <img src="../<?php echo htmlspecialchars($student['twelfth_marksheet_doc']); ?>" 
                                     alt="12th Marksheet" 
                                     class="document-preview">
                                <br>
                                <a href="../<?php echo htmlspecialchars($student['twelfth_marksheet_doc']); ?>" 
                                   target="_blank" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Full Size
                                </a>
                            <?php elseif ($twelfth_ext === 'pdf'): ?>
                                <i class="fas fa-file-pdf document-icon pdf-icon"></i>
                                <p style="font-weight: 600; color: #1e293b; margin: 12px 0;">PDF Document</p>
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="../<?php echo htmlspecialchars($student['twelfth_marksheet_doc']); ?>" 
                                       target="_blank" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="../<?php echo htmlspecialchars($student['twelfth_marksheet_doc']); ?>" 
                                       download 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                            <br>
                            <i class="fas fa-certificate document-icon no-doc-icon"></i>
                            <p style="color: #64748b; margin-top: 12px;">No 12th marksheet available</p>
                        <?php endif; ?>
                    </div>

                    <!-- Graduation Certificate -->
                    <div class="document-card">
                        <h5 class="doc-title"><i class="fas fa-user-graduate"></i> Graduation Certificate</h5>
                        <?php if (!empty($student['graduation_certificate_doc']) && file_exists(__DIR__ . '/../' . $student['graduation_certificate_doc'])): 
                            $grad_ext = strtolower(pathinfo($student['graduation_certificate_doc'], PATHINFO_EXTENSION));
                        ?>
                            <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                            <br>
                            <?php if (in_array($grad_ext, ['jpg', 'jpeg', 'png'])): ?>
                                <img src="../<?php echo htmlspecialchars($student['graduation_certificate_doc']); ?>" 
                                     alt="Graduation Certificate" 
                                     class="document-preview">
                                <br>
                                <a href="../<?php echo htmlspecialchars($student['graduation_certificate_doc']); ?>" 
                                   target="_blank" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Full Size
                                </a>
                            <?php elseif ($grad_ext === 'pdf'): ?>
                                <i class="fas fa-file-pdf document-icon pdf-icon"></i>
                                <p style="font-weight: 600; color: #1e293b; margin: 12px 0;">PDF Document</p>
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="../<?php echo htmlspecialchars($student['graduation_certificate_doc']); ?>" 
                                       target="_blank" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="../<?php echo htmlspecialchars($student['graduation_certificate_doc']); ?>" 
                                       download 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                            <br>
                            <i class="fas fa-user-graduate document-icon no-doc-icon"></i>
                            <p style="color: #64748b; margin-top: 12px;">No graduation certificate available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Additional Documents Section (Optional) -->
            <div class="content-card" style="margin-bottom: 24px;">
                <div class="card-header" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 5px solid #3b82f6; padding: 16px; border-radius: 8px 8px 0 0; margin: -24px -24px 24px -24px;">
                    <h5 class="card-title" style="margin: 0; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-folder-plus"></i> Additional Documents
                        <span style="background: #3b82f6; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-left: auto;">Optional</span>
                    </h5>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
                    <!-- Caste Certificate -->
                    <div class="document-card">
                        <h5 class="doc-title"><i class="fas fa-file-alt"></i> Caste Certificate</h5>
                        <?php if (!empty($student['caste_certificate_doc']) && file_exists(__DIR__ . '/../' . $student['caste_certificate_doc'])): 
                            $caste_ext = strtolower(pathinfo($student['caste_certificate_doc'], PATHINFO_EXTENSION));
                        ?>
                            <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                            <br>
                            <?php if (in_array($caste_ext, ['jpg', 'jpeg', 'png'])): ?>
                                <img src="../<?php echo htmlspecialchars($student['caste_certificate_doc']); ?>" 
                                     alt="Caste Certificate" 
                                     class="document-preview">
                                <br>
                                <a href="../<?php echo htmlspecialchars($student['caste_certificate_doc']); ?>" 
                                   target="_blank" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Full Size
                                </a>
                            <?php elseif ($caste_ext === 'pdf'): ?>
                                <i class="fas fa-file-pdf document-icon pdf-icon"></i>
                                <p style="font-weight: 600; color: #1e293b; margin: 12px 0;">PDF Document</p>
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="../<?php echo htmlspecialchars($student['caste_certificate_doc']); ?>" 
                                       target="_blank" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="../<?php echo htmlspecialchars($student['caste_certificate_doc']); ?>" 
                                       download 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                            <br>
                            <i class="fas fa-file-alt document-icon no-doc-icon"></i>
                            <p style="color: #64748b; margin-top: 12px;">No caste certificate available</p>
                        <?php endif; ?>
                    </div>

                    <!-- Other Documents -->
                    <div class="document-card">
                        <h5 class="doc-title"><i class="fas fa-folder"></i> Other Documents</h5>
                        <?php if (!empty($student['other_documents_doc']) && file_exists(__DIR__ . '/../' . $student['other_documents_doc'])): 
                            $other_ext = strtolower(pathinfo($student['other_documents_doc'], PATHINFO_EXTENSION));
                        ?>
                            <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                            <br>
                            <?php if (in_array($other_ext, ['jpg', 'jpeg', 'png'])): ?>
                                <img src="../<?php echo htmlspecialchars($student['other_documents_doc']); ?>" 
                                     alt="Other Documents" 
                                     class="document-preview">
                                <br>
                                <a href="../<?php echo htmlspecialchars($student['other_documents_doc']); ?>" 
                                   target="_blank" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Full Size
                                </a>
                            <?php elseif ($other_ext === 'pdf'): ?>
                                <i class="fas fa-file-pdf document-icon pdf-icon"></i>
                                <p style="font-weight: 600; color: #1e293b; margin: 12px 0;">PDF Document</p>
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="../<?php echo htmlspecialchars($student['other_documents_doc']); ?>" 
                                       target="_blank" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="../<?php echo htmlspecialchars($student['other_documents_doc']); ?>" 
                                       download 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                            <br>
                            <i class="fas fa-folder document-icon no-doc-icon"></i>
                            <p style="color: #64748b; margin-top: 12px;">No other documents available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Payment Information Section (Optional) -->
            <div class="content-card" style="margin-bottom: 24px;">
                <div class="card-header" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 5px solid #3b82f6; padding: 16px; border-radius: 8px 8px 0 0; margin: -24px -24px 24px -24px;">
                    <h5 class="card-title" style="margin: 0; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-receipt"></i> Payment Information
                        <span style="background: #3b82f6; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-left: auto;">Optional</span>
                    </h5>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
                    <!-- Payment Receipt -->
                    <div class="document-card">
                        <h5 class="doc-title"><i class="fas fa-receipt"></i> Payment Receipt</h5>
                        <?php 
                        if (!empty($student['payment_receipt']) && file_exists(__DIR__ . '/../' . $student['payment_receipt'])):
                            $receipt_ext = strtolower(pathinfo($student['payment_receipt'], PATHINFO_EXTENSION));
                        ?>
                            <span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
                            <br>
                            <?php if (in_array($receipt_ext, ['jpg', 'jpeg', 'png'])): ?>
                                <img src="../<?php echo htmlspecialchars($student['payment_receipt']); ?>" 
                                     alt="Payment Receipt" 
                                     class="document-preview">
                                <br>
                                <a href="../<?php echo htmlspecialchars($student['payment_receipt']); ?>" 
                                   target="_blank" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Full Size
                                </a>
                            <?php elseif ($receipt_ext === 'pdf'): ?>
                                <i class="fas fa-file-pdf document-icon pdf-icon"></i>
                                <p style="font-weight: 600; color: #1e293b; margin: 12px 0;">PDF Receipt</p>
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="../<?php echo htmlspecialchars($student['payment_receipt']); ?>" 
                                       target="_blank" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="../<?php echo htmlspecialchars($student['payment_receipt']); ?>" 
                                       download 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="doc-status missing"><i class="fas fa-times-circle"></i> Not Uploaded</span>
                            <br>
                            <i class="fas fa-receipt document-icon no-doc-icon"></i>
                            <p style="color: #64748b; margin-top: 12px;">No receipt available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Student Information Summary -->
            <div class="content-card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-user"></i> Personal Information
                    </h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered" style="margin-top: 16px;">
                        <tbody>
                            <tr>
                                <td style="width: 25%; background: #f8fafc; font-weight: 600;">Student ID</td>
                                <td style="width: 25%;"><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td style="width: 25%; background: #f8fafc; font-weight: 600;">Full Name</td>
                                <td style="width: 25%;"><?php echo htmlspecialchars($student['name']); ?></td>
                            </tr>
                            <tr>
                                <td style="background: #f8fafc; font-weight: 600;">Father's Name</td>
                                <td><?php echo !empty($student['father_name']) ? htmlspecialchars($student['father_name']) : '-'; ?></td>
                                <td style="background: #f8fafc; font-weight: 600;">Mother's Name</td>
                                <td><?php echo !empty($student['mother_name']) ? htmlspecialchars($student['mother_name']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <td style="background: #f8fafc; font-weight: 600;">Date of Birth</td>
                                <td><?php echo !empty($student['dob']) ? date('d M Y', strtotime($student['dob'])) : '-'; ?></td>
                                <td style="background: #f8fafc; font-weight: 600;">Age</td>
                                <td><?php echo !empty($student['age']) ? htmlspecialchars($student['age']) . ' years' : '-'; ?></td>
                            </tr>
                            <tr>
                                <td style="background: #f8fafc; font-weight: 600;">Gender</td>
                                <td><?php echo !empty($student['gender']) ? htmlspecialchars($student['gender']) : '-'; ?></td>
                                <td style="background: #f8fafc; font-weight: 600;">Category</td>
                                <td><?php echo !empty($student['category']) ? htmlspecialchars($student['category']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <td style="background: #f8fafc; font-weight: 600;">PWD Status</td>
                                <td>
                                    <?php if (!empty($student['pwd_status']) && $student['pwd_status'] == 'Yes'): ?>
                                        <span class="badge" style="background: #3b82f6; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px;">
                                            <i class="fas fa-wheelchair"></i> Yes
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #94a3b8; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px;">No</span>
                                    <?php endif; ?>
                                </td>
                                <td style="background: #f8fafc; font-weight: 600;">Distinguishing Marks</td>
                                <td><?php echo !empty($student['distinguishing_marks']) ? htmlspecialchars($student['distinguishing_marks']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <td style="background: #f8fafc; font-weight: 600;">Position</td>
                                <td><?php echo !empty($student['position']) ? htmlspecialchars($student['position']) : '-'; ?></td>
                                <td style="background: #f8fafc; font-weight: 600;">Aadhar Number</td>
                                <td><?php echo !empty($student['aadhar']) ? htmlspecialchars($student['aadhar']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <td style="background: #f8fafc; font-weight: 600;">APAAR ID</td>
                                <td><?php echo !empty($student['apaar_id']) ? htmlspecialchars($student['apaar_id']) : 'Not Provided'; ?></td>
                                <td style="background: #f8fafc; font-weight: 600;">Religion</td>
                                <td><?php echo !empty($student['religion']) ? htmlspecialchars($student['religion']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <td style="background: #f8fafc; font-weight: 600;">Marital Status</td>
                                <td><?php echo !empty($student['marital_status']) ? htmlspecialchars($student['marital_status']) : '-'; ?></td>
                                <td style="background: #f8fafc; font-weight: 600;">Nationality</td>
                                <td><?php echo !empty($student['nationality']) ? htmlspecialchars($student['nationality']) : '-'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="content-card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-address-book"></i> Contact Information
                    </h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered" style="margin-top: 16px;">
                        <tbody>
                            <tr>
                                <td style="width: 25%; background: #f8fafc; font-weight: 600;">Email Address</td>
                                <td style="width: 25%;"><?php echo htmlspecialchars($student['email']); ?></td>
                                <td style="width: 25%; background: #f8fafc; font-weight: 600;">Mobile Number</td>
                                <td style="width: 25%;"><?php echo htmlspecialchars($student['mobile']); ?></td>
                            </tr>
                            <tr>
                                <td style="background: #f8fafc; font-weight: 600;">Address</td>
                                <td colspan="3"><?php echo !empty($student['address']) ? htmlspecialchars($student['address']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <td style="background: #f8fafc; font-weight: 600;">City</td>
                                <td><?php echo !empty($student['city']) ? htmlspecialchars($student['city']) : '-'; ?></td>
                                <td style="background: #f8fafc; font-weight: 600;">State</td>
                                <td><?php echo !empty($student['state']) ? htmlspecialchars($student['state']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <td style="background: #f8fafc; font-weight: 600;">Pincode</td>
                                <td colspan="3"><?php echo !empty($student['pincode']) ? htmlspecialchars($student['pincode']) : '-'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Course & Academic Information -->
            <div class="content-card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-graduation-cap"></i> Course & Academic Information
                    </h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered" style="margin-top: 16px;">
                        <tbody>
                            <tr>
                                <td style="width: 25%; background: #f8fafc; font-weight: 600;">Course</td>
                                <td style="width: 25%;"><?php echo htmlspecialchars($student['course']); ?></td>
                                <td style="width: 25%; background: #f8fafc; font-weight: 600;">Status</td>
                                <td style="width: 25%;">
                                    <span class="badge badge-<?php echo strtolower($student['status']) == 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td style="background: #f8fafc; font-weight: 600;">College Name</td>
                                <td><?php echo !empty($student['college_name']) ? htmlspecialchars($student['college_name']) : '-'; ?></td>
                                <td style="background: #f8fafc; font-weight: 600;">Training Center</td>
                                <td><?php echo !empty($student['training_center']) ? htmlspecialchars($student['training_center']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <td style="background: #f8fafc; font-weight: 600;">Registration Date</td>
                                <td><?php echo date('d M Y', strtotime($student['created_at'])); ?></td>
                                <td style="background: #f8fafc; font-weight: 600;">UTR Number</td>
                                <td><?php echo !empty($student['utr_number']) ? htmlspecialchars($student['utr_number']) : '-'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Educational Qualifications -->
            <div class="content-card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-book-open"></i> Educational Qualifications
                    </h5>
                </div>
                
                <?php if (!empty($education_records) && is_array($education_records)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" style="margin-top: 16px;">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th style="width: 5%; text-align: center;">Sl.</th>
                                    <th style="width: 15%;">Exam Passed</th>
                                    <th style="width: 15%;">Exam Name</th>
                                    <th style="width: 12%; text-align: center;">Year</th>
                                    <th style="width: 28%;">Institute/Board</th>
                                    <th style="width: 15%;">Stream</th>
                                    <th style="width: 10%; text-align: center;">%/CGPA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $row_count = 0;
                                foreach ($education_records as $edu): 
                                    $row_count++;
                                ?>
                                <tr>
                                    <td style="text-align: center;"><?php echo $row_count; ?></td>
                                    <td><?php echo !empty($edu['exam_passed']) ? htmlspecialchars($edu['exam_passed']) : '-'; ?></td>
                                    <td><?php echo !empty($edu['exam_name']) ? htmlspecialchars($edu['exam_name']) : '-'; ?></td>
                                    <td style="text-align: center;"><?php echo !empty($edu['year_of_passing']) ? htmlspecialchars($edu['year_of_passing']) : '-'; ?></td>
                                    <td><?php echo !empty($edu['institute_name']) ? htmlspecialchars($edu['institute_name']) : '-'; ?></td>
                                    <td><?php echo !empty($edu['stream']) ? htmlspecialchars($edu['stream']) : '-'; ?></td>
                                    <td style="text-align: center;"><?php echo !empty($edu['percentage']) ? htmlspecialchars($edu['percentage']) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="padding: 32px; text-align: center; color: #64748b;">
                        <i class="fas fa-book-open" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p style="margin: 0; font-size: 16px;">No educational qualifications recorded</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

</body>
</html>
