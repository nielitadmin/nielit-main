<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/config.php';

$course = [];

if (isset($_GET['id'])) {
    $course_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $course = $result->fetch_assoc();
    } else {
        echo "Course not found!";
        exit();
    }
} else {
    echo "Course ID not specified!";
    exit();
}

// Handle Remove PDF
if (isset($_GET['remove_pdf']) && $_GET['remove_pdf'] == $course_id) {
    if (!empty($course['description_pdf']) && file_exists(__DIR__ . '/../' . $course['description_pdf'])) {
        unlink(__DIR__ . '/../' . $course['description_pdf']);
    }
    
    $stmt_remove = $conn->prepare("UPDATE courses SET description_pdf = NULL WHERE id = ?");
    $stmt_remove->bind_param("i", $course_id);
    
    if ($stmt_remove->execute()) {
        $_SESSION['message'] = "PDF removed successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error removing PDF.";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: edit_course.php?id=$course_id");
    exit();
}

// Handle Remove Flyer
if (isset($_GET['remove_flyer']) && $_GET['remove_flyer'] == $course_id) {
    if (!empty($course['course_flyer']) && file_exists(__DIR__ . '/../' . $course['course_flyer'])) {
        unlink(__DIR__ . '/../' . $course['course_flyer']);
    }
    
    $stmt_remove = $conn->prepare("UPDATE courses SET course_flyer = NULL WHERE id = ?");
    $stmt_remove->bind_param("i", $course_id);
    
    if ($stmt_remove->execute()) {
        $_SESSION['message'] = "Flyer removed successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error removing flyer.";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: edit_course.php?id=$course_id");
    exit();
}

if (isset($_POST['update_course'])) {
    $course_name = $_POST['course_name'];
    $course_code = strtoupper($_POST['course_code'] ?? '');
    $course_abbreviation = strtoupper($_POST['course_abbreviation'] ?? '');
    $eligibility = $_POST['eligibility'];
    $duration = $_POST['duration'];
    $training_fees = $_POST['training_fees'];
    $category = $_POST['category'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $description_url = $_POST['description_url'];
    $apply_link = $_POST['apply_link'];
    $course_coordinator = $_POST['course_coordinator'];
    $training_center = $_POST['training_center'];
    $centre_id = !empty($_POST['centre_id']) ? intval($_POST['centre_id']) : null;
    $link_published = isset($_POST['link_published']) ? 1 : 0;
    $enrollment_status = $_POST['enrollment_status'] ?? 'ongoing';
    $description_pdf = $course['description_pdf'];
    $course_flyer = $course['course_flyer'] ?? '';

    if (isset($_FILES['description_pdf']) && $_FILES['description_pdf']['error'] == 0) {
        $pdf_file = $_FILES['description_pdf'];
        $extension = pathinfo($pdf_file['name'], PATHINFO_EXTENSION);

        if (strtolower($extension) == 'pdf' && $pdf_file['type'] == 'application/pdf') {
            $random_name = uniqid('course_', true) . '.' . $extension;
            $upload_dir = __DIR__ . '/../course_pdf/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $pdf_full_path = $upload_dir . $random_name;
            $pdf_relative_path = 'course_pdf/' . $random_name;

            if (move_uploaded_file($pdf_file['tmp_name'], $pdf_full_path)) {
                // Delete old PDF if exists
                if (!empty($course['description_pdf']) && file_exists(__DIR__ . '/../' . $course['description_pdf'])) {
                    unlink(__DIR__ . '/../' . $course['description_pdf']);
                }
                $description_pdf = $pdf_relative_path;
            } else {
                $_SESSION['error'] = "Error uploading PDF file. Please check folder permissions.";
            }
        } else {
            $_SESSION['error'] = "Only PDF files are allowed.";
        }
    }

    // Handle course flyer upload (JPG/PNG)
    if (isset($_FILES['course_flyer']) && $_FILES['course_flyer']['error'] == 0) {
        $flyer_file = $_FILES['course_flyer'];
        $extension = strtolower(pathinfo($flyer_file['name'], PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png']) && in_array($flyer_file['type'], ['image/jpeg', 'image/png'])) {
            $random_name = uniqid('flyer_', true) . '.' . $extension;
            $upload_dir = __DIR__ . '/../course_flyers/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $flyer_full_path = $upload_dir . $random_name;
            $flyer_relative_path = 'course_flyers/' . $random_name;

            if (move_uploaded_file($flyer_file['tmp_name'], $flyer_full_path)) {
                // Delete old flyer if exists
                if (!empty($course['course_flyer']) && file_exists(__DIR__ . '/../' . $course['course_flyer'])) {
                    unlink(__DIR__ . '/../' . $course['course_flyer']);
                }
                $course_flyer = $flyer_relative_path;
            } else {
                $_SESSION['error'] = "Error uploading flyer image. Please check folder permissions.";
            }
        } else {
            $_SESSION['error'] = "Only JPG and PNG files are allowed for flyer.";
        }
    }

    $update_sql = "UPDATE courses SET 
        course_name = ?, 
        course_code = ?,
        course_abbreviation = ?,
        eligibility = ?, 
        duration = ?, 
        training_fees = ?, 
        category = ?, 
        start_date = ?, 
        end_date = ?, 
        description_url = ?, 
        description_pdf = ?, 
        course_flyer = ?,
        apply_link = ?,
        course_coordinator = ?,
        training_center = ?,
        centre_id = ?,
        link_published = ?,
        enrollment_status = ?
        WHERE id = ?";

    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssssssssssssssiisi",
        $course_name,
        $course_code,
        $course_abbreviation,
        $eligibility,
        $duration,
        $training_fees,
        $category,
        $start_date,
        $end_date,
        $description_url,
        $description_pdf,
        $course_flyer,
        $apply_link,
        $course_coordinator,
        $training_center,
        $centre_id,
        $link_published,
        $enrollment_status,
        $course_id
    );

    if ($stmt->execute()) {
        // Handle scheme associations (only if table exists)
        // First, delete existing associations
        $delete_schemes_sql = "DELETE FROM course_schemes WHERE course_id = ?";
        $stmt_delete = $conn->prepare($delete_schemes_sql);
        
        if ($stmt_delete) {
            $stmt_delete->bind_param("i", $course_id);
            $stmt_delete->execute();
            $stmt_delete->close();
            
            // Then, insert new associations if any schemes are selected
            if (isset($_POST['schemes']) && !empty($_POST['schemes'])) {
                $insert_scheme_sql = "INSERT INTO course_schemes (course_id, scheme_id) VALUES (?, ?)";
                $stmt_insert = $conn->prepare($insert_scheme_sql);
                
                if ($stmt_insert) {
                    foreach ($_POST['schemes'] as $scheme_id) {
                        $stmt_insert->bind_param("ii", $course_id, $scheme_id);
                        $stmt_insert->execute();
                    }
                    $stmt_insert->close();
                }
            }
        } else {
            // course_schemes table doesn't exist - schemes module not installed
            error_log("course_schemes table not found during update: " . $conn->error);
        }
        
        // Auto-generate QR code ONLY if it doesn't exist yet
        if (!empty($apply_link) && !empty($course_code) && empty($course['qr_code_path'])) {
            require_once __DIR__ . '/../includes/qr_helper.php';
            $qr_result = generateCourseQRCode($course_id, $course_code);
            
            if ($qr_result['success']) {
                // Update course with QR path
                $stmt_update = $conn->prepare("UPDATE courses SET qr_code_path = ?, qr_generated_at = NOW() WHERE id = ?");
                $stmt_update->bind_param("si", $qr_result['path'], $course_id);
                $stmt_update->execute();
                
                $_SESSION['message'] = "Course updated successfully! QR code generated.";
            } else {
                $_SESSION['message'] = "Course updated successfully! But QR code generation failed.";
            }
        } else {
            $_SESSION['message'] = "Course updated successfully!";
        }
        header("Location: edit_course.php?id=$course_id");
        exit();
    } else {
        echo "Update failed: " . $stmt->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css?v=<?php echo time(); ?>">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-edit"></i> Edit Course</h4>
                <small><?php echo htmlspecialchars($course['course_name']); ?></small>
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
            <?php if (isset($_SESSION['message'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        toast.success('<?php echo addslashes($_SESSION['message']); ?>', 5000);
                    });
                </script>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        toast.error('<?php echo addslashes($_SESSION['error']); ?>', 5000);
                    });
                </script>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Edit Course Form -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-edit"></i> Course Details
                    </h5>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                
                <form action="edit_course.php?id=<?php echo $course['id']; ?>" method="POST" enctype="multipart/form-data">
                    <!-- Course Name and Codes Row -->
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div class="form-group">
                            <label class="form-label">Course Name *</label>
                            <input type="text" class="form-control" name="course_name" value="<?php echo htmlspecialchars($course['course_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Course Code * <small>(e.g., PPI-2026)</small></label>
                            <input type="text" class="form-control" name="course_code" value="<?php echo htmlspecialchars($course['course_code'] ?? ''); ?>" maxlength="20" required style="text-transform: uppercase;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Student ID Code * <small>(e.g., PPI)</small></label>
                            <input type="text" class="form-control" name="course_abbreviation" value="<?php echo htmlspecialchars($course['course_abbreviation'] ?? ''); ?>" maxlength="10" required style="text-transform: uppercase;" placeholder="PPI">
                            <small class="text-muted">For ID: NIELIT/2026/<strong><?php echo htmlspecialchars($course['course_abbreviation'] ?? 'XXX'); ?></strong>/0001</small>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category" id="edit_category" required onchange="handleCategoryChange('<?php echo $course['category']; ?>')">
                                <option value="">--Select Category--</option>
                                <option value="Long Term NSQF" <?php if ($course['category'] == 'Long Term NSQF') echo 'selected'; ?>>Long Term NSQF</option>
                                <option value="Short Term NSQF" <?php if ($course['category'] == 'Short Term NSQF') echo 'selected'; ?>>Short Term NSQF</option>
                                <option value="Short-Term Non-NSQF" <?php if ($course['category'] == 'Short-Term Non-NSQF') echo 'selected'; ?>>Short-Term Non-NSQF</option>
                                <option value="Internship Program" <?php if ($course['category'] == 'Internship Program') echo 'selected'; ?>>Internship Program</option>
                            </select>
                        </div>
                        
                        <!-- NSQF Template Selection (hidden by default) -->
                        <div class="form-group" id="template_selection_group" style="display: none;">
                            <label class="form-label">Course Template *</label>
                            <select class="form-select" id="course_name_template" onchange="handleTemplateSelection()">
                                <option value="">-- Select Course Template --</option>
                            </select>
                            <small class="text-muted">Select from pre-defined NSQF course templates</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Eligibility *</label>
                            <textarea class="form-control" name="eligibility" id="edit_eligibility" rows="2" required placeholder="Will auto-populate from template for NSQF courses"><?php echo htmlspecialchars($course['eligibility']); ?></textarea>
                            <small class="text-muted">For NSQF courses, this will be filled automatically from the selected template</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Duration *</label>
                            <input type="text" class="form-control" name="duration" value="<?php echo htmlspecialchars($course['duration']); ?>" placeholder="e.g., 6 Months" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Training Fees *</label>
                            <input type="text" class="form-control" name="training_fees" value="<?php echo htmlspecialchars($course['training_fees']); ?>" placeholder="e.g., 15000" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Course Coordinator *</label>
                            <input type="text" class="form-control" name="course_coordinator" value="<?php echo htmlspecialchars($course['course_coordinator'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Training Centre *</label>
                            <select class="form-select" name="centre_id" required>
                                <option value="">--Select Training Centre--</option>
                                <?php
                                // Fetch all active centres
                                $centres_query = "SELECT id, name, code FROM centres WHERE is_active = 1 ORDER BY name";
                                $centres_result = $conn->query($centres_query);
                                
                                if ($centres_result && $centres_result->num_rows > 0) {
                                    while ($centre = $centres_result->fetch_assoc()) {
                                        $selected = ($course['centre_id'] == $centre['id']) ? 'selected' : '';
                                        echo '<option value="' . $centre['id'] . '" ' . $selected . '>' . htmlspecialchars($centre['name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <input type="hidden" name="training_center" value="<?php echo htmlspecialchars($course['training_center'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($course['start_date']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($course['end_date']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Course Description URL</label>
                        <input type="url" class="form-control" name="description_url" value="<?php echo htmlspecialchars($course['description_url']); ?>" placeholder="https://...">
                    </div>
                    
                    <!-- Schemes/Projects Selection -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-project-diagram"></i> Schemes/Projects
                        </label>
                        <?php
                        // Fetch all active schemes
                        $schemes_query = "SELECT * FROM schemes WHERE status = 'Active' ORDER BY scheme_name";
                        $schemes_result = $conn->query($schemes_query);
                        
                        // Get currently selected schemes for this course
                        $selected_schemes_query = "SELECT scheme_id FROM course_schemes WHERE course_id = ?";
                        $stmt_schemes = $conn->prepare($selected_schemes_query);
                        
                        $selected_schemes = [];
                        if ($stmt_schemes) {
                            $stmt_schemes->bind_param("i", $course_id);
                            $stmt_schemes->execute();
                            $selected_result = $stmt_schemes->get_result();
                            while ($row = $selected_result->fetch_assoc()) {
                                $selected_schemes[] = $row['scheme_id'];
                            }
                            $stmt_schemes->close();
                        } else {
                            // Table doesn't exist yet - schemes module not installed
                            error_log("course_schemes table not found: " . $conn->error);
                        }
                        ?>
                        
                        <div style="background: #f8f9fa; padding: 16px; border-radius: 6px; border: 1px solid #dee2e6;">
                            <?php if ($schemes_result && $schemes_result->num_rows > 0): ?>
                                <?php while ($scheme = $schemes_result->fetch_assoc()): ?>
                                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                                        <input type="checkbox" 
                                               name="schemes[]" 
                                               value="<?php echo $scheme['id']; ?>"
                                               <?php echo in_array($scheme['id'], $selected_schemes) ? 'checked' : ''; ?>
                                               style="width: 18px; height: 18px;">
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($scheme['scheme_name']); ?></span>
                                        <span style="color: #6c757d; font-size: 12px;">(<?php echo htmlspecialchars($scheme['scheme_code']); ?>)</span>
                                    </label>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="color: #6c757d; margin: 0;">
                                    <i class="fas fa-info-circle"></i> No schemes available. 
                                    <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php" style="color: #007bff;">Create schemes</a>
                                </p>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">Select one or more schemes/projects for this course</small>
                    </div>
                    
                    <hr style="margin: 24px 0; border-color: #e3f2fd;">
                    <h6 style="color: #0d47a1; margin-bottom: 16px;"><i class="fas fa-link"></i> Registration Link & QR Code</h6>
                    
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Apply Link</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="url" class="form-control" name="apply_link" id="edit_apply_link" value="<?php echo htmlspecialchars($course['apply_link'] ?? ''); ?>" placeholder="https://..." readonly>
                                <?php if (empty($course['apply_link'])): ?>
                                <button type="button" class="btn btn-success" onclick="generateApplyLinkEdit()" style="white-space: nowrap;">
                                    <i class="fas fa-magic"></i> Generate Link
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn btn-warning" onclick="regenerateApplyLink()" style="white-space: nowrap;">
                                    <i class="fas fa-sync-alt"></i> Regenerate
                                </button>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">Registration link for students</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Link Status</label>
                            <div style="padding-top: 8px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" name="link_published" id="edit_link_published" value="1" <?php echo ($course['link_published'] ?? 0) ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                                    <span id="edit_publish_status" style="<?php echo ($course['link_published'] ?? 0) ? 'color: #28a745; font-weight: bold;' : 'color: #dc3545; font-weight: bold;'; ?>">
                                        <?php echo ($course['link_published'] ?? 0) ? '✓ Active' : '✗ Inactive'; ?>
                                    </span>
                                </label>
                            </div>
                            <small class="text-muted"><?php echo ($course['link_published'] ?? 0) ? 'Students can register' : 'Registration disabled'; ?></small>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-top: 16px;">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-users"></i> Enrollment Status *
                            </label>
                            <select class="form-select" name="enrollment_status" id="enrollment_status" required>
                                <option value="ongoing" <?php echo ($course['enrollment_status'] ?? 'ongoing') == 'ongoing' ? 'selected' : ''; ?>>
                                    <i class="fas fa-check-circle"></i> Enrollment Ongoing
                                </option>
                                <option value="closed" <?php echo ($course['enrollment_status'] ?? 'ongoing') == 'closed' ? 'selected' : ''; ?>>
                                    <i class="fas fa-times-circle"></i> Enrollment Closed
                                </option>
                            </select>
                            <small class="text-muted">
                                <span id="enrollment_help_text">
                                    <?php if (($course['enrollment_status'] ?? 'ongoing') == 'ongoing'): ?>
                                        Course is accepting new enrollments
                                    <?php else: ?>
                                        Course is not accepting new enrollments
                                    <?php endif; ?>
                                </span>
                            </small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status Preview</label>
                            <div style="padding-top: 8px;">
                                <span id="enrollment_preview" style="
                                    padding: 8px 16px; 
                                    border-radius: 20px; 
                                    font-size: 14px; 
                                    font-weight: 600;
                                    display: inline-block;
                                    <?php if (($course['enrollment_status'] ?? 'ongoing') == 'ongoing'): ?>
                                        background: #d4edda; 
                                        color: #155724; 
                                        border: 1px solid #c3e6cb;
                                    <?php else: ?>
                                        background: #f8d7da; 
                                        color: #721c24; 
                                        border: 1px solid #f5c6cb;
                                    <?php endif; ?>
                                ">
                                    <?php if (($course['enrollment_status'] ?? 'ongoing') == 'ongoing'): ?>
                                        <i class="fas fa-check-circle"></i> Enrollment Open
                                    <?php else: ?>
                                        <i class="fas fa-times-circle"></i> Enrollment Closed
                                    <?php endif; ?>
                                </span>
                            </div>
                            <small class="text-muted">How it appears to students</small>
                        </div>
                    </div>
                    
                    <div style="background: #e3f2fd; padding: 12px; border-radius: 6px; margin-top: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><i class="fas fa-info-circle"></i> Link Preview:</strong> 
                                <span id="link_preview_edit"><?php echo !empty($course['apply_link']) ? htmlspecialchars($course['apply_link']) : 'Click "Generate Link" to create registration URL'; ?></span>
                            </div>
                            <?php if (!empty($course['apply_link'])): ?>
                            <span style="padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; <?php echo ($course['link_published'] ?? 0) ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;'; ?>">
                                <?php echo ($course['link_published'] ?? 0) ? '✓ ACTIVE' : '✗ INACTIVE'; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($course['apply_link']) && !($course['link_published'] ?? 0)): ?>
                    <div style="background: #fff3cd; padding: 12px; border-radius: 6px; margin-top: 12px; border-left: 4px solid #ffc107;">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> This registration link is currently INACTIVE. Students cannot register until you activate it by checking the "Link Status" checkbox above and saving the course.
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($course['qr_code_path'])): ?>
                    <div style="background: #e3f2fd; padding: 16px; border-radius: 6px; margin-top: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <strong><i class="fas fa-qrcode"></i> Current QR Code:</strong>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-warning btn-sm" onclick="regenerateQRCode()" id="regenerate_qr_btn">
                                    <i class="fas fa-sync-alt"></i> Regenerate QR
                                </button>
                                <a href="<?php echo APP_URL . '/' . htmlspecialchars($course['qr_code_path']); ?>" 
                                   download="<?php echo htmlspecialchars($course['course_code'] ?? 'course'); ?>_QR.png" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-download"></i> Download QR
                                </a>
                            </div>
                        </div>
                        <div style="margin-top: 8px;">
                            <img src="<?php echo APP_URL . '/' . htmlspecialchars($course['qr_code_path']); ?>" 
                                 alt="QR Code" 
                                 id="qr_code_image"
                                 style="max-width: 150px; border: 2px solid #0d47a1; border-radius: 4px;">
                        </div>
                        <small class="text-muted">Generated: <span id="qr_generated_time"><?php echo $course['qr_generated_at'] ? date('d M Y, h:i A', strtotime($course['qr_generated_at'])) : 'N/A'; ?></span></small>
                    </div>
                    <?php endif; ?>
                    
                    <div style="background: #fff3cd; padding: 12px; border-radius: 6px; margin-top: 12px; border-left: 4px solid #ffc107;">
                        <i class="fas fa-lightbulb"></i> <strong>Note:</strong> QR code will be automatically generated only if it doesn't exist. To regenerate, use the "Generate Link" button above.
                    </div>
                    
                    <div class="form-group" style="margin-top: 16px;">
                        <label class="form-label">
                            <i class="fas fa-file-pdf"></i> Upload Course Description PDF
                        </label>
                        <input type="file" class="form-control" name="description_pdf" accept=".pdf" id="pdf_upload">
                        <?php if (!empty($course['description_pdf'])): ?>
                        <div style="background: #e8f5e9; padding: 10px; border-radius: 4px; margin-top: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <i class="fas fa-file-pdf" style="color: #d32f2f;"></i>
                                <strong>Current PDF:</strong> 
                                <a href="<?php echo APP_URL . '/' . htmlspecialchars($course['description_pdf']); ?>" 
                                   target="_blank" 
                                   style="color: #1976d2; text-decoration: none;">
                                    <?php echo basename($course['description_pdf']); ?>
                                </a>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <a href="<?php echo APP_URL . '/' . htmlspecialchars($course['description_pdf']); ?>" 
                                   target="_blank" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button type="button" 
                                        class="btn btn-danger btn-sm" 
                                        onclick="confirmRemovePDF(<?php echo $course['id']; ?>)">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                        <small class="text-muted">Upload a new PDF to replace the current one, or click Remove to delete it</small>
                        <?php else: ?>
                        <small class="text-muted">No PDF uploaded yet. Select a PDF file to upload.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group" style="margin-top: 16px;">
                        <label class="form-label">
                            <i class="fas fa-image"></i> Upload Course Flyer (JPG/PNG)
                        </label>
                        <input type="file" class="form-control" name="course_flyer" accept=".jpg,.jpeg,.png" id="flyer_upload">
                        <?php if (!empty($course['course_flyer'])): ?>
                        <div style="background: #e3f2fd; padding: 10px; border-radius: 4px; margin-top: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <div>
                                    <i class="fas fa-image" style="color: #1976d2;"></i>
                                    <strong>Current Flyer:</strong> 
                                    <span style="color: #1976d2;"><?php echo basename($course['course_flyer']); ?></span>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="<?php echo APP_URL . '/' . htmlspecialchars($course['course_flyer']); ?>" 
                                       target="_blank" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="<?php echo APP_URL . '/' . htmlspecialchars($course['course_flyer']); ?>" 
                                       download 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <button type="button" 
                                            class="btn btn-danger btn-sm" 
                                            onclick="confirmRemoveFlyer(<?php echo $course['id']; ?>)">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                            <div style="margin-top: 8px;">
                                <img src="<?php echo APP_URL . '/' . htmlspecialchars($course['course_flyer']); ?>" 
                                     alt="Course Flyer Preview" 
                                     style="max-width: 300px; max-height: 400px; border: 2px solid #0d47a1; border-radius: 4px; display: block;">
                            </div>
                        </div>
                        <small class="text-muted">Upload a new image to replace the current flyer, or click Remove to delete it</small>
                        <?php else: ?>
                        <small class="text-muted">No flyer uploaded yet. Upload a JPG or PNG image (recommended size: 800x1200px)</small>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 16px;">
                        <button type="submit" name="update_course" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Course
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js?v=<?php echo time(); ?>"></script>
<script>
// Modern Confirm Dialog Function (Styled like Delete Course modal)
function showModernConfirm(options) {
    return new Promise((resolve) => {
        // Create modal overlay
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            animation: fadeIn 0.2s ease;
        `;
        
        // Create modal content
        const modal = document.createElement('div');
        modal.style.cssText = `
            background: white;
            border-radius: 12px;
            padding: 32px;
            max-width: 440px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
            text-align: center;
        `;
        
        // Icon based on type
        const iconColor = options.type === 'warning' ? '#ff9800' : '#f44336';
        const iconHtml = `
            <div style="
                width: 64px;
                height: 64px;
                background: ${iconColor}22;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
            ">
                <i class="fas fa-exclamation-triangle" style="font-size: 32px; color: ${iconColor};"></i>
            </div>
        `;
        
        // Title
        const titleHtml = `
            <h3 style="
                margin: 0 0 12px;
                font-size: 22px;
                font-weight: 600;
                color: #2c3e50;
            ">${options.title || 'Confirm Action'}</h3>
        `;
        
        // Message
        const messageHtml = `
            <p style="
                margin: 0 0 28px;
                font-size: 15px;
                color: #6c757d;
                line-height: 1.6;
            ">${options.message || 'Are you sure?'}</p>
        `;
        
        // Buttons
        const buttonsHtml = `
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button id="cancelBtn" style="
                    padding: 12px 28px;
                    border: none;
                    border-radius: 6px;
                    font-size: 15px;
                    font-weight: 500;
                    cursor: pointer;
                    background: #6c757d;
                    color: white;
                    transition: all 0.2s;
                ">
                    <i class="fas fa-times"></i> ${options.cancelText || 'Cancel'}
                </button>
                <button id="confirmBtn" style="
                    padding: 12px 28px;
                    border: none;
                    border-radius: 6px;
                    font-size: 15px;
                    font-weight: 500;
                    cursor: pointer;
                    background: #f44336;
                    color: white;
                    transition: all 0.2s;
                ">
                    <i class="fas fa-check"></i> ${options.confirmText || 'OK'}
                </button>
            </div>
        `;
        
        modal.innerHTML = iconHtml + titleHtml + messageHtml + buttonsHtml;
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        // Add animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes slideUp {
                from { transform: translateY(20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            #cancelBtn:hover { background: #5a6268; transform: translateY(-1px); }
            #confirmBtn:hover { background: #d32f2f; transform: translateY(-1px); }
        `;
        document.head.appendChild(style);
        
        // Event listeners
        const confirmBtn = document.getElementById('confirmBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        
        const cleanup = (result) => {
            overlay.style.animation = 'fadeOut 0.2s ease';
            setTimeout(() => {
                document.body.removeChild(overlay);
                document.head.removeChild(style);
                resolve(result);
            }, 200);
        };
        
        confirmBtn.onclick = () => cleanup(true);
        cancelBtn.onclick = () => cleanup(false);
        overlay.onclick = (e) => {
            if (e.target === overlay) cleanup(false);
        };
    });
}

// Regenerate QR Code Function with Modern Confirmation
window.regenerateQRCode = async function() {
    const courseCodeInput = document.querySelector('input[name="course_code"]');
    const courseCode = courseCodeInput.value.trim();
    const courseId = <?php echo $course['id']; ?>;
    const regenerateBtn = document.getElementById('regenerate_qr_btn');
    
    if (!courseCode) {
        toast.warning('Please enter course code first!');
        courseCodeInput.focus();
        return;
    }
    
    // Modern confirm regeneration with styled modal
    const confirmed = await showModernConfirm({
        title: 'Regenerate QR Code?',
        message: 'Are you sure you want to regenerate the QR code? The old QR code will be replaced with a new one.',
        confirmText: 'OK',
        cancelText: 'Cancel',
        type: 'warning'
    });
    
    if (!confirmed) {
        return;
    }
    
    // Disable button and show loading
    regenerateBtn.disabled = true;
    regenerateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Regenerating...';
    
    // Show loading toast
    const loadingToast = toast.loading('Regenerating QR code...');
    
    // Send AJAX request
    const formData = new FormData();
    formData.append('course_id', courseId);
    formData.append('course_name', '');
    formData.append('course_code', courseCode);
    formData.append('force_regenerate', '1');
    
    fetch('generate_link_qr.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        toast.remove(loadingToast);
        
        if (data.success) {
            toast.success('QR code regenerated successfully!');
            
            const qrImage = document.getElementById('qr_code_image');
            if (qrImage && data.qr_code_url) {
                qrImage.src = data.qr_code_url + '?t=' + new Date().getTime();
            }
            
            const timeSpan = document.getElementById('qr_generated_time');
            if (timeSpan) {
                const now = new Date();
                timeSpan.textContent = now.toLocaleString('en-US', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
            }
            
            setTimeout(() => location.reload(), 1500);
        } else {
            toast.error('Error: ' + data.message);
        }
    })
    .catch(error => {
        toast.remove(loadingToast);
        toast.error('Error regenerating QR code: ' + error);
    })
    .finally(() => {
        regenerateBtn.disabled = false;
        regenerateBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Regenerate QR';
    });
};

// PDF Upload Validation
document.addEventListener('DOMContentLoaded', function() {
    const pdfUpload = document.getElementById('pdf_upload');
    if (pdfUpload) {
        pdfUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Check file size (max 10MB)
                const maxSize = 10 * 1024 * 1024; // 10MB in bytes
                if (file.size > maxSize) {
                    toast.error('PDF file is too large! Maximum size is 10MB.');
                    this.value = '';
                    return;
                }
                
                // Check file type
                if (file.type !== 'application/pdf') {
                    toast.error('Please select a valid PDF file!');
                    this.value = '';
                    return;
                }
                
                toast.success('PDF file selected: ' + file.name);
            }
        });
    }
});

// Generate Apply Link and QR Code for Edit Page (AJAX)
function generateApplyLinkEdit() {
    const courseNameInput = document.querySelector('input[name="course_name"]');
    const courseCodeInput = document.querySelector('input[name="course_code"]');
    const linkInput = document.getElementById('edit_apply_link');
    const previewSpan = document.getElementById('link_preview_edit');
    const generateBtn = event.target;
    
    const courseName = courseNameInput.value.trim();
    const courseCode = courseCodeInput.value.trim();
    const courseId = <?php echo $course['id']; ?>;
    
    if (!courseName) {
        toast.warning('Please enter course name first!');
        courseNameInput.focus();
        return;
    }
    
    if (!courseCode) {
        toast.warning('Please enter course code first!');
        courseCodeInput.focus();
        return;
    }
    
    // Disable button and show loading
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    
    // Show loading toast
    const loadingToast = toast.loading('Generating registration link and QR code...');
    
    // Send AJAX request
    const formData = new FormData();
    formData.append('course_id', courseId);
    formData.append('course_name', courseName);
    formData.append('course_code', courseCode);
    
    fetch('generate_link_qr.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Remove loading toast
        toast.remove(loadingToast);
        
        if (data.success) {
            // Update link field and preview
            linkInput.value = data.apply_link;
            previewSpan.textContent = data.apply_link;
            
            // If QR code was generated, reload the page to show it
            if (data.qr_code_path) {
                toast.success(data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                toast.info(data.message);
            }
        } else {
            toast.error('Error: ' + data.message);
        }
    })
    .catch(error => {
        toast.remove(loadingToast);
        toast.error('Error generating link: ' + error);
    })
    .finally(() => {
        // Re-enable button
        generateBtn.disabled = false;
        generateBtn.innerHTML = '<i class="fas fa-magic"></i> Generate Link';
    });
}

// Toggle link status label
document.addEventListener('DOMContentLoaded', function() {
    const publishCheckbox = document.getElementById('edit_link_published');
    if (publishCheckbox) {
        publishCheckbox.addEventListener('change', function() {
            const statusSpan = document.getElementById('edit_publish_status');
            const smallText = statusSpan.parentElement.parentElement.nextElementSibling;
            
            if (this.checked) {
                statusSpan.textContent = '✓ Active';
                statusSpan.style.color = '#28a745';
                statusSpan.style.fontWeight = 'bold';
                smallText.textContent = 'Students can register';
                toast.success('Registration link activated!');
            } else {
                statusSpan.textContent = '✗ Inactive';
                statusSpan.style.color = '#dc3545';
                statusSpan.style.fontWeight = 'bold';
                smallText.textContent = 'Registration disabled';
                toast.warning('Registration link deactivated!');
            }
        });
    }
    
    // Handle enrollment status changes
    const enrollmentSelect = document.getElementById('enrollment_status');
    if (enrollmentSelect) {
        enrollmentSelect.addEventListener('change', function() {
            const preview = document.getElementById('enrollment_preview');
            const helpText = document.getElementById('enrollment_help_text');
            
            if (this.value === 'ongoing') {
                preview.innerHTML = '<i class="fas fa-check-circle"></i> Enrollment Open';
                preview.style.background = '#d4edda';
                preview.style.color = '#155724';
                preview.style.border = '1px solid #c3e6cb';
                helpText.textContent = 'Course is accepting new enrollments';
                toast.success('Enrollment status set to ONGOING');
            } else {
                preview.innerHTML = '<i class="fas fa-times-circle"></i> Enrollment Closed';
                preview.style.background = '#f8d7da';
                preview.style.color = '#721c24';
                preview.style.border = '1px solid #f5c6cb';
                helpText.textContent = 'Course is not accepting new enrollments';
                toast.warning('Enrollment status set to CLOSED');
            }
        });
    }
});

// NSQF Template Integration Functions
function handleCategoryChange(currentCategory) {
    const categorySelect = document.getElementById('edit_category');
    const templateGroup = document.getElementById('template_selection_group');
    const courseNameInput = document.querySelector('input[name="course_name"]');
    const eligibilityField = document.getElementById('edit_eligibility');
    
    const selectedCategory = categorySelect.value;
    
    // Check if user is NSQF Course Manager
    const isNSQFManager = <?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'nsqf_course_manager') ? 'true' : 'false'; ?>;
    
    if (selectedCategory === 'Long Term NSQF' || selectedCategory === 'Short Term NSQF') {
        if (!isNSQFManager) {
            // Course Coordinators must select from templates
            templateGroup.style.display = 'block';
            courseNameInput.readOnly = true;
            courseNameInput.placeholder = 'Will be filled from template selection';
            eligibilityField.readOnly = true;
            eligibilityField.placeholder = 'Will auto-populate from template';
            
            // Fetch NSQF templates
            fetchNSQFTemplates(selectedCategory);
        } else {
            // NSQF managers can create new courses directly
            templateGroup.style.display = 'none';
            courseNameInput.readOnly = false;
            courseNameInput.placeholder = 'Enter course name';
            eligibilityField.readOnly = false;
            eligibilityField.placeholder = 'Enter eligibility criteria';
        }
    } else {
        // Non-NSQF courses - normal input
        templateGroup.style.display = 'none';
        courseNameInput.readOnly = false;
        courseNameInput.placeholder = 'Enter course name';
        eligibilityField.readOnly = false;
        eligibilityField.placeholder = 'Enter eligibility criteria';
    }
}

// Function to fetch NSQF templates via AJAX
function fetchNSQFTemplates(category) {
    fetch('get_nsqf_templates.php?category=' + encodeURIComponent(category))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateTemplateDropdown(data.templates);
            } else {
                console.error('Error fetching templates:', data.message);
                toast.error('Error loading course templates. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toast.error('Error loading course templates. Please check your connection.');
        });
}

// Function to populate template dropdown
function populateTemplateDropdown(templates) {
    const templateSelect = document.getElementById('course_name_template');
    
    // Clear existing options except first
    templateSelect.innerHTML = '<option value="">-- Select Course Template --</option>';
    
    // Add template options
    templates.forEach(template => {
        const option = document.createElement('option');
        option.value = template.id;
        option.textContent = template.course_name;
        option.dataset.eligibility = template.eligibility;
        templateSelect.appendChild(option);
    });
}

// Function to handle template selection
function handleTemplateSelection() {
    const templateSelect = document.getElementById('course_name_template');
    const selectedOption = templateSelect.options[templateSelect.selectedIndex];
    const eligibilityField = document.getElementById('edit_eligibility');
    const courseNameInput = document.querySelector('input[name="course_name"]');
    
    if (selectedOption.value && selectedOption.dataset.eligibility) {
        // Auto-populate eligibility from template
        eligibilityField.value = selectedOption.dataset.eligibility;
        
        // Set the actual course name for form submission
        courseNameInput.value = selectedOption.textContent;
        
        toast.success('Template selected: ' + selectedOption.textContent);
    } else {
        // Clear fields if no template selected
        eligibilityField.value = '';
        courseNameInput.value = '';
    }
}

// Initialize template system on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if current course is NSQF and initialize template system
    const currentCategory = '<?php echo $course['category']; ?>';
    if (currentCategory === 'Long Term NSQF' || currentCategory === 'Short Term NSQF') {
        handleCategoryChange(currentCategory);
    }
});

// Regenerate Apply Link (with confirmation)
async function regenerateApplyLink() {
    const confirmed = await showModernConfirm({
        title: 'Regenerate Registration Link?',
        message: 'This will create a new registration link and QR code. The old link will stop working. Are you sure?',
        confirmText: 'Yes, Regenerate',
        cancelText: 'Cancel',
        type: 'warning'
    });
    
    if (confirmed) {
        generateApplyLinkEdit();
    }
}

// Confirm Remove PDF
async function confirmRemovePDF(courseId) {
    const confirmed = await showModernConfirm({
        title: 'Remove PDF?',
        message: 'Are you sure you want to remove the course description PDF? This action cannot be undone.',
        confirmText: 'Yes, Remove',
        cancelText: 'Cancel',
        type: 'warning'
    });
    
    if (confirmed) {
        window.location.href = 'edit_course.php?id=' + courseId + '&remove_pdf=' + courseId;
    }
}

// Confirm Remove Flyer
async function confirmRemoveFlyer(courseId) {
    const confirmed = await showModernConfirm({
        title: 'Remove Flyer?',
        message: 'Are you sure you want to remove the course flyer image? This action cannot be undone.',
        confirmText: 'Yes, Remove',
        cancelText: 'Cancel',
        type: 'warning'
    });
    
    if (confirmed) {
        window.location.href = 'edit_course.php?id=' + courseId + '&remove_flyer=' + courseId;
    }
}
</script>

</body>
</html>
<?php $conn->close(); ?>