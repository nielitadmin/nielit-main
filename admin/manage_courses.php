<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/course_category_options.php';
require_once __DIR__ . '/../includes/institute_branding.php';

function findDuplicateCourseCode($conn, $course_code, $exclude_id = null) {
    $course_code = strtoupper(trim($course_code));

    if ($exclude_id !== null) {
        $sql = "SELECT id, course_name, course_code
                FROM courses
                WHERE id != ? AND UPPER(course_code) = ?
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $exclude_id, $course_code);
    } else {
        $sql = "SELECT id, course_name, course_code
                FROM courses
                WHERE UPPER(course_code) = ?
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $course_code);
    }

    $duplicate = null;
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $duplicate = $result->fetch_assoc() ?: null;
        $stmt->close();
    }

    return $duplicate;
}

// Load active theme
$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);

// Handle Add/Edit/Delete Course
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $course_name = $_POST['course_name'];
        $course_code = strtoupper(trim($_POST['course_code']));
        $course_abbreviation = '';
        $course_type = $_POST['course_type'];
        $nsqf_type = normalize_course_sub_category($_POST['nsqf_type'] ?? get_default_non_nsqf_sub_category());
        
        // Check if NSQF manager is trying to create non-NSQF course
        if ($_SESSION['admin_role'] === 'nsqf_course_manager' && 
            !is_nsqf_course_sub_category($nsqf_type)) {
            $error = "You can only create NSQF courses.";
            goto skip_add;
        }

        $duplicate = findDuplicateCourseCode($conn, $course_code);
        if ($duplicate) {
            $error = "Course Code '{$course_code}' is already used by '{$duplicate['course_name']}'. Please make a different one.";
            $show_duplicate_popup = true;
            goto skip_add;
        }
        
        $training_center = $_POST['training_center'];
        $centre_id = !empty($_POST['centre_id']) ? intval($_POST['centre_id']) : NULL;
        $duration = $_POST['duration'];
        $fees = $_POST['fees'];
        $description = $_POST['description'];
        $eligibility = $_POST['eligibility'] ?? '';
        $nsqf_type = normalize_course_sub_category($_POST['nsqf_type'] ?? get_default_non_nsqf_sub_category());
        $is_nsqf = is_nsqf_course_sub_category($nsqf_type) ? 1 : 0;
        $custom_link = $_POST['custom_link'] ?? '';
        $link_published = isset($_POST['link_published']) ? 1 : 0;
        $enrollment_closing_date = !empty($_POST['enrollment_closing_date']) ? $_POST['enrollment_closing_date'] : null;
        // Generate registration link if provided
        if (!empty($custom_link)) {
            $registration_link = $custom_link;
        } else {
            $registration_link = '';
        }
        $stmt = $conn->prepare("INSERT INTO courses (centre_id, course_name, course_code, course_abbreviation, course_type, training_center, duration, fees, description, eligibility, registration_link, is_nsqf, link_published, enrollment_closing_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("issssssdsssiss", $centre_id, $course_name, $course_code, $course_abbreviation, $course_type, $training_center, $duration, $fees, $description, $eligibility, $registration_link, $is_nsqf, $link_published, $enrollment_closing_date);
        
        if ($stmt->execute()) {
            $course_id = $conn->insert_id;
            
            // Auto-assign course to course coordinator who created it
            if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'course_coordinator' && isset($_SESSION['admin_id'])) {
                $admin_id = $_SESSION['admin_id'];
                $assigned_by = $_SESSION['admin_id']; // Self-assigned
                
                $assign_stmt = $conn->prepare("INSERT INTO admin_course_assignments (admin_id, course_id, is_active, assigned_by, assignment_type) VALUES (?, ?, 1, ?, 'Auto-Assigned')");
                $assign_stmt->bind_param("iii", $admin_id, $course_id, $assigned_by);
                $assign_stmt->execute();
                $assign_stmt->close();
            }
            
            // Auto-generate QR code if registration link exists
            if (!empty($registration_link)) {
                require_once '../includes/qr_helper.php';
                
                // Fetch registration token for this course
                $stmt_token = $conn->prepare("SELECT registration_token FROM courses WHERE id = ?");
                $stmt_token->bind_param("i", $course_id);
                $stmt_token->execute();
                $token_result = $stmt_token->get_result();
                $token_row = $token_result->fetch_assoc();
                $registration_token = $token_row['registration_token'] ?? '';
                
                $qr_result = generateCourseQRCode($course_id, $course_code, $registration_token);
                
                if ($qr_result['success']) {
                    // Update course with QR path
                    $stmt_update = $conn->prepare("UPDATE courses SET qr_code_path = ?, qr_generated_at = NOW() WHERE id = ?");
                    $stmt_update->bind_param("si", $qr_result['path'], $course_id);
                    $stmt_update->execute();
                    
                    $success = "Course added successfully! Registration link and QR code generated. Course automatically assigned to you.";
                } else {
                    $success = "Course added successfully! But QR code generation failed. Course automatically assigned to you.";
                }
            } else {
                $success = "Course added successfully! Generate registration link to create QR code. Course automatically assigned to you.";
            }
        } else {
            $error = "Error: " . $conn->error;
        }
        
        skip_add:
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['course_id']);
        
        // Check if course coordinator has permission to edit this course
        if ($_SESSION['admin_role'] === 'course_coordinator') {
            $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_course_assignments WHERE admin_id = ? AND course_id = ? AND is_active = 1");
            $check_stmt->bind_param("ii", $_SESSION['admin_id'], $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_data = $check_result->fetch_assoc();
            
            if ($check_data['count'] == 0) {
                $error = "You don't have permission to edit this course.";
                goto skip_edit;
            }
        }
        
        $course_name = $_POST['course_name'];
        $course_code = strtoupper(trim($_POST['course_code']));
        $course_type = $_POST['course_type'];
        $nsqf_type = normalize_course_sub_category($_POST['nsqf_type'] ?? get_default_non_nsqf_sub_category());
        
        // Check if NSQF manager is trying to edit to non-NSQF course
        if ($_SESSION['admin_role'] === 'nsqf_course_manager' && 
            !is_nsqf_course_sub_category($nsqf_type)) {
            $error = "You can only manage NSQF courses.";
            goto skip_edit;
        }

        $duplicate = findDuplicateCourseCode($conn, $course_code, $id);
        if ($duplicate) {
            $error = "Course Code '{$course_code}' is already used by '{$duplicate['course_name']}'. Please make a different one.";
            $show_duplicate_popup = true;
            goto skip_edit;
        }

        $abbr_stmt = $conn->prepare("SELECT course_abbreviation FROM courses WHERE id = ? LIMIT 1");
        $course_abbreviation = '';
        if ($abbr_stmt) {
            $abbr_stmt->bind_param("i", $id);
            $abbr_stmt->execute();
            $abbr_row = $abbr_stmt->get_result()->fetch_assoc();
            $course_abbreviation = $abbr_row['course_abbreviation'] ?? '';
            $abbr_stmt->close();
        }
        
        $training_center = $_POST['training_center'];
        $centre_id = !empty($_POST['centre_id']) ? intval($_POST['centre_id']) : NULL;
        $duration = $_POST['duration'];
        $fees = $_POST['fees'];
        $description = $_POST['description'];
        $eligibility = $_POST['eligibility'] ?? '';
        $nsqf_type = normalize_course_sub_category($_POST['nsqf_type'] ?? get_default_non_nsqf_sub_category());
        $is_nsqf = is_nsqf_course_sub_category($nsqf_type) ? 1 : 0;
        $custom_link = $_POST['custom_link'] ?? '';
        $link_published = isset($_POST['link_published']) ? 1 : 0;
        $enrollment_closing_date = !empty($_POST['enrollment_closing_date']) ? $_POST['enrollment_closing_date'] : null;
        // Use provided link or keep existing
        $registration_link = !empty($custom_link) ? $custom_link : '';
        $stmt = $conn->prepare("UPDATE courses SET centre_id=?, course_name=?, course_code=?, course_abbreviation=?, course_type=?, training_center=?, duration=?, fees=?, description=?, eligibility=?, registration_link=?, is_nsqf=?, link_published=?, enrollment_closing_date=? WHERE id=?");
        $stmt->bind_param("issssssdsssissi", $centre_id, $course_name, $course_code, $course_abbreviation, $course_type, $training_center, $duration, $fees, $description, $eligibility, $registration_link, $is_nsqf, $link_published, $enrollment_closing_date, $id);
        
        if ($stmt->execute()) {
            // Regenerate QR code if registration link exists
            if (!empty($registration_link)) {
                require_once '../includes/qr_helper.php';
                
                // Get old QR path and fetch registration token
                $stmt_get = $conn->prepare("SELECT qr_code_path, registration_token FROM courses WHERE id = ?");
                $stmt_get->bind_param("i", $id);
                $stmt_get->execute();
                $result_get = $stmt_get->get_result();
                $old_course = $result_get->fetch_assoc();
                
                // Delete old QR if exists
                if (!empty($old_course['qr_code_path'])) {
                    deleteQRCode($old_course['qr_code_path']);
                }
                
                // Generate new QR with token
                $qr_result = generateCourseQRCode($id, $course_code, $old_course['registration_token']);
                
                if ($qr_result['success']) {
                    $stmt_update = $conn->prepare("UPDATE courses SET qr_code_path = ?, qr_generated_at = NOW() WHERE id = ?");
                    $stmt_update->bind_param("si", $qr_result['path'], $id);
                    $stmt_update->execute();
                    
                    $success = "Course updated successfully! QR code regenerated.";
                } else {
                    $success = "Course updated successfully! But QR code generation failed.";
                }
            } else {
                $success = "Course updated successfully!";
            }
        } else {
            $error = "Error: " . $conn->error;
        }
        
        skip_edit:
    }
    
    if ($action === 'delete') {
        $id = $_POST['course_id'];
        
        // Check if course coordinator has permission to delete this course
        if ($_SESSION['admin_role'] === 'course_coordinator') {
            $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_course_assignments WHERE admin_id = ? AND course_id = ? AND is_active = 1");
            $check_stmt->bind_param("ii", $_SESSION['admin_id'], $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_data = $check_result->fetch_assoc();
            
            if ($check_data['count'] == 0) {
                $error = "You don't have permission to delete this course.";
                goto skip_delete;
            }
        }
        
        $stmt = $conn->prepare("UPDATE courses SET status='inactive' WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Course deactivated successfully!";
        } else {
            $error = "Error: " . $conn->error;
        }
        
        skip_delete:
    }
}

// Get filter parameters
$filter_type = $_GET['type'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';

// Check user role for filtering
$is_master_admin = ($_SESSION['admin_role'] === 'master_admin');
$is_nsqf_manager = ($_SESSION['admin_role'] === 'nsqf_course_manager');
$current_admin_id = $_SESSION['admin_id'] ?? 0;

// Build query with filters - with error handling for missing tables
$query = "";
$params = [];
$types = "";

// Check if centres table exists
$centres_exists = false;
$check_centres = $conn->query("SHOW TABLES LIKE 'centres'");
if ($check_centres && $check_centres->num_rows > 0) {
    $centres_exists = true;
}

// Check if admin_course_assignments table exists
$assignments_exists = false;
$check_assignments = $conn->query("SHOW TABLES LIKE 'admin_course_assignments'");
if ($check_assignments && $check_assignments->num_rows > 0) {
    $assignments_exists = true;
}

if ($is_master_admin) {
    // Master admin sees all courses
    if ($centres_exists) {
        $query = "SELECT courses.*, centres.name AS centre_name, centres.code AS centre_code 
                  FROM courses 
                  LEFT JOIN centres ON courses.centre_id = centres.id 
                  WHERE 1=1";
    } else {
        $query = "SELECT courses.*, NULL AS centre_name, NULL AS centre_code 
                  FROM courses 
                  WHERE 1=1";
    }
} elseif ($is_nsqf_manager) {
    // NSQF Course Manager sees only NSQF courses
        if ($centres_exists) {
        $query = "SELECT courses.*, centres.name AS centre_name, centres.code AS centre_code 
                  FROM courses 
                  LEFT JOIN centres ON courses.centre_id = centres.id 
                  WHERE courses.is_nsqf = 1";
    } else {
        $query = "SELECT courses.*, NULL AS centre_name, NULL AS centre_code 
                  FROM courses 
                  WHERE courses.is_nsqf = 1";
    }
} else {
    // Course coordinators see only their assigned courses (if table exists)
    if ($assignments_exists && $centres_exists) {
        $query = "SELECT courses.*, centres.name AS centre_name, centres.code AS centre_code 
                  FROM courses 
                  LEFT JOIN centres ON courses.centre_id = centres.id 
                  INNER JOIN admin_course_assignments aca ON courses.id = aca.course_id 
                  WHERE aca.admin_id = ? AND aca.is_active = 1";
        $params[] = $current_admin_id;
        $types .= "i";
    } elseif ($assignments_exists) {
        $query = "SELECT courses.*, NULL AS centre_name, NULL AS centre_code 
                  FROM courses 
                  INNER JOIN admin_course_assignments aca ON courses.id = aca.course_id 
                  WHERE aca.admin_id = ? AND aca.is_active = 1";
        $params[] = $current_admin_id;
        $types .= "i";
    } else {
        // Fallback: show all courses if assignment table doesn't exist
        if ($centres_exists) {
            $query = "SELECT courses.*, centres.name AS centre_name, centres.code AS centre_code 
                      FROM courses 
                      LEFT JOIN centres ON courses.centre_id = centres.id 
                      WHERE 1=1";
        } else {
            $query = "SELECT courses.*, NULL AS centre_name, NULL AS centre_code 
                      FROM courses 
                      WHERE 1=1";
        }
    }
}

if ($filter_type !== 'all') {
    $query .= " AND courses.course_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($filter_status !== 'all') {
    $query .= " AND courses.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$query .= " ORDER BY courses.created_at DESC";

// Include QR helper for checking QR code existence
require_once '../includes/qr_helper.php';

// Fetch active centres for dropdown (if table exists)
$centres = [];
if ($centres_exists) {
    $centres_query = "SELECT id, name, code FROM centres WHERE is_active = 1 ORDER BY name ASC";
    $centres_result = $conn->query($centres_query);
    if ($centres_result) {
        while ($centre = $centres_result->fetch_assoc()) {
            $centres[] = $centre;
        }
    }
}

// Execute query with filters and error handling
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Query preparation failed: " . $conn->error . "<br>Query: " . $query);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $courses = $stmt->get_result();
} else {
    $courses = $conn->query($query);
    if (!$courses) {
        die("Query execution failed: " . $conn->error . "<br>Query: " . $query);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - NIELIT Admin</title>
    <?php injectThemeCSS($active_theme); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin-theme.css" rel="stylesheet">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
    <style>
        .eligibility-textarea {
            resize: vertical;
            min-height: 130px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2"><i class="fas fa-graduation-cap"></i> 
                            <?php if ($is_master_admin): ?>
                                Manage Courses
                            <?php elseif ($is_nsqf_manager): ?>
                                Manage NSQF Courses
                            <?php else: ?>
                                My Assigned Courses
                            <?php endif; ?>
                        </h1>
                        <?php if ($is_nsqf_manager): ?>
                            <small class="text-muted">You can manage NSQF courses only</small>
                        <?php elseif (!$is_master_admin): ?>
                            <small class="text-muted">You can view and manage courses assigned to you</small>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                        <i class="fas fa-plus"></i> Add New Course
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($show_duplicate_popup) && isset($error)): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            alert(<?php echo json_encode($error); ?>);
                        });
                    </script>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-filter"></i> Filter by Category</label>
                                <select name="type" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Categories</option>
                                    <option value="NSQF" <?= $filter_type === 'NSQF' ? 'selected' : '' ?>>NSQF</option>
                                    <option value="NON-NSQF" <?= $filter_type === 'NON-NSQF' ? 'selected' : '' ?>>Non-NSQF</option>
                                    <option value="Internship Program" <?= $filter_type === 'Internship Program' ? 'selected' : '' ?>>Internship Program</option>
                                    <option value="Regular" <?= $filter_type === 'Regular' ? 'selected' : '' ?>>Regular</option>
                                    <option value="Internship" <?= $filter_type === 'Internship' ? 'selected' : '' ?>>Internship</option>
                                    <option value="Bootcamp" <?= $filter_type === 'Bootcamp' ? 'selected' : '' ?>>Bootcamp</option>
                                    <option value="Workshop" <?= $filter_type === 'Workshop' ? 'selected' : '' ?>>Workshop</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-toggle-on"></i> Filter by Status</label>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                                    <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <?php if ($filter_type !== 'all' || $filter_status !== 'all'): ?>
                                    <a href="manage_courses.php" class="btn btn-secondary w-100">
                                        <i class="fas fa-redo"></i> Clear Filters
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-secondary w-100" disabled>
                                        <i class="fas fa-filter"></i> No Filters Applied
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-list"></i> All Courses
                            <?php 
                            $total_courses = $courses->num_rows;
                            if ($filter_type !== 'all' || $filter_status !== 'all') {
                                echo '<span class="badge bg-primary ms-2">' . $total_courses . ' results</span>';
                            } else {
                                echo '<span class="badge bg-secondary ms-2">' . $total_courses . ' total</span>';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Course Name</th>
                                        <th>Course Code</th>
                                        <th>Type</th>
                                        <th>Centre</th>
                                        <th>Training Centre</th>
                                        <th>Duration</th>
                                        <th>Fees</th>
                                        <th>Registration Link</th>
                                        <th>QR Code</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($course = $courses->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $course['id'] ?></td>
                                        <td><?= htmlspecialchars($course['course_name']) ?></td>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($course['course_code']) ?></span></td>
                                        <td><?= htmlspecialchars($course['course_type']) ?></td>
                                        <td>
                                            <?php if (!empty($course['centre_name'])): ?>
                                                <span class="badge bg-info"><?= htmlspecialchars($course['centre_name']) ?></span>
                                                <?php if (!empty($course['centre_code'])): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($course['centre_code']) ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($course['training_center']) ?></td>
                                        <td><?= htmlspecialchars($course['duration']) ?></td>
                                        <td>₹<?= number_format((float)($course['training_fees'] ?? 0), 2) ?></td>
                                        <td>
                                            <div class="input-group input-group-sm" style="max-width: 300px;">
                                                <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($course['apply_link'] ?? '') ?>" id="link_<?= $course['id'] ?>" readonly>
                                                <button class="btn btn-outline-secondary" type="button" onclick="copyLink(<?= $course['id'] ?>)" title="Copy Link">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                                <a href="<?= htmlspecialchars($course['apply_link'] ?? '#') ?>" target="_blank" class="btn btn-outline-primary" title="Open Link">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($course['qr_code_path']) && qrCodeExists($course['qr_code_path'])): ?>
                                                <button class="btn btn-sm btn-success" onclick="viewQRCode(<?= $course['id'] ?>, '<?= htmlspecialchars($course['qr_code_path']) ?>', '<?= htmlspecialchars($course['course_name']) ?>')" title="View QR Code">
                                                    <i class="fas fa-qrcode"></i>
                                                </button>
                                                <a href="../<?= htmlspecialchars($course['qr_code_path']) ?>" download class="btn btn-sm btn-info" title="Download QR">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-warning" onclick="generateQRCode(<?= $course['id'] ?>)" id="qr_btn_<?= $course['id'] ?>" title="Generate QR Code">
                                                    <i class="fas fa-sync"></i> Generate
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (($course['enrollment_status'] ?? 'ongoing') === 'ongoing'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- Course coordinators only see courses they're assigned to, so they can edit all visible courses -->
                                            <button class="btn btn-sm btn-info" onclick="editCourse(<?= htmlspecialchars(json_encode($course)) ?>)" title="Edit Course">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($is_master_admin): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to deactivate this course?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Deactivate Course">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    
                                    <?php if ($courses->num_rows == 0): ?>
                                    <tr>
                                        <td colspan="13" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                                                <h5>
                                                    <?php if ($is_master_admin): ?>
                                                        No courses found
                                                    <?php elseif ($is_nsqf_manager): ?>
                                                        No NSQF courses found
                                                    <?php else: ?>
                                                        No courses assigned to you yet
                                                    <?php endif; ?>
                                                </h5>
                                                <p>
                                                    <?php if ($is_master_admin): ?>
                                                        Click "Add New Course" to create your first course.
                                                    <?php elseif ($is_nsqf_manager): ?>
                                                        Click "Add New Course" to create your first NSQF course. You can only create NSQF courses.
                                                    <?php else: ?>
                                                        Contact your administrator to get courses assigned to you, or create a new course using the "Add New Course" button.
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Course</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <!-- Course Details Section -->
                        <h6 class="mb-3"><i class="fas fa-info-circle"></i> Course Details</h6>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category *</label>
                                <select name="course_type" class="form-control" required id="add_course_category" onchange="handleAddCategoryChange()">
                                    <?php echo render_course_category_options(); ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sub-Category *</label>
                                <select name="nsqf_type" id="add_nsqf_type" class="form-select" required onchange="handleAddSubCategoryChange()">
                                    <?php echo render_course_sub_category_options(); ?>
                                </select>
                                <small class="text-muted">Select whether this course follows NSQF framework or is a special program</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Eligibility *</label>
                            <textarea name="eligibility" id="add_eligibility" class="form-control eligibility-textarea" rows="5" required placeholder="Will auto-populate from template for NSQF courses"></textarea>
                            <small class="text-muted">Drag the bottom-right corner to resize. For NSQF courses, this will be filled automatically from the selected template.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration *</label>
                                <input type="text" name="duration" id="add_duration" class="form-control" required placeholder="e.g., 6 months">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Training Fees (₹) *</label>
                                <input type="number" name="fees" id="add_fees" class="form-control" step="0.01" required>
                            </div>
                        </div>

                        <hr>
                        
                        <!-- NSQF Template Selection (shown only for NSQF courses) -->
                        <div id="add_template_selection_group" style="display: none; margin-bottom: 16px;">
                            <div class="mb-3">
                                <label class="form-label">NSQF Course Name *</label>
                                <select name="course_name_template" id="add_course_name_template" class="form-control" onchange="handleAddTemplateSelection()">
                                    <option value="">-- Select NSQF Course Name --</option>
                                </select>
                                <small class="text-muted">Select a course name from NSQF templates</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course Name *</label>
                                <input type="text" name="course_name" id="add_course_name" class="form-control" required onkeyup="autoGenerateAddCourseCode()" placeholder="Enter the full course name as it will appear on certificates">
                                <small class="text-muted">This will appear on certificates and documents</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Course Code * 
                                    <small>(Auto-generated)</small>
                                    <button type="button" class="btn btn-sm btn-link" onclick="regenerateAddCourseCode()" title="Regenerate code">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </label>
                                <input type="text" name="course_code" id="add_course_code" class="form-control" maxlength="20" required style="text-transform: uppercase;" readonly>
                                <small class="text-muted">
                                    <input type="checkbox" id="add_manual_code" onchange="toggleAddManualCode()"> 
                                    <label for="add_manual_code">Edit manually</label>
                                </small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Training Centre</label>
                                <select name="centre_id" class="form-control">
                                    <option value="">-- Select Centre --</option>
                                    <?php foreach ($centres as $centre): ?>
                                        <option value="<?= $centre['id'] ?>"><?= htmlspecialchars(normalize_nielit_centre_name($centre['name'])) ?> (<?= htmlspecialchars($centre['code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Optional: Associate course with a training centre</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Training Centre *</label>
                                <select name="training_center" class="form-control" required>
                                    <option value="">-- Select Training Centre --</option>
                                    <?php foreach ($centres as $centre): ?>
                                        <option value="<?= htmlspecialchars(normalize_nielit_centre_name($centre['name'])) ?>"><?= htmlspecialchars(normalize_nielit_centre_name($centre['name'])) ?> (<?= htmlspecialchars($centre['code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>

                        <hr>
                        <h6 class="mb-3"><i class="fas fa-link"></i> Registration Link Settings</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Apply Link</label>
                                <div class="input-group">
                                    <input type="text" name="custom_link" id="add_apply_link" class="form-control" placeholder="Will be auto-generated" readonly>
                                    <button type="button" class="btn btn-success" onclick="generateApplyLink('add')">
                                        <i class="fas fa-magic"></i> Generate Link
                                    </button>
                                </div>
                                <small class="text-muted">Click "Generate Link" to create registration URL automatically</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Publish Status</label>
                                <div class="form-check form-switch" style="padding-top: 8px;">
                                    <input class="form-check-input" type="checkbox" id="add_link_published" name="link_published" value="1">
                                    <label class="form-check-label" for="add_link_published">
                                        <span id="add_publish_status">Unpublished</span>
                                    </label>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div class="modal fade" id="editCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Course</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="course_id" id="edit_course_id">
                        
                        <!-- Course Details Section -->
                        <h6 class="mb-3"><i class="fas fa-info-circle"></i> Course Details</h6>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category *</label>
                                <select name="course_type" id="edit_course_type" class="form-control" required onchange="handleEditCategoryChange()">
                                    <?php echo render_course_category_options(); ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sub-Category *</label>
                                <select name="nsqf_type" id="edit_nsqf_type" class="form-select" required onchange="handleEditSubCategoryChange()">
                                    <?php echo render_course_sub_category_options(); ?>
                                </select>
                                <small class="text-muted">Select whether this course follows NSQF framework or is a special program</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Eligibility *</label>
                            <textarea name="eligibility" id="edit_eligibility" class="form-control eligibility-textarea" rows="5" required placeholder="Will auto-populate from template for NSQF courses"></textarea>
                            <small class="text-muted">Drag the bottom-right corner to resize. For NSQF courses, this will be filled automatically from the selected template.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration *</label>
                                <input type="text" name="duration" id="edit_duration" class="form-control" required placeholder="e.g., 6 months">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Training Fees (₹) *</label>
                                <input type="number" name="fees" id="edit_fees" class="form-control" step="0.01" required>
                            </div>
                        </div>

                        <hr>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course Name *</label>
                                <input type="text" name="course_name" id="edit_course_name" class="form-control" required placeholder="Enter the full course name as it will appear on certificates">
                                <small class="text-muted">This will appear on certificates and documents</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course Code *</label>
                                <input type="text" name="course_code" id="edit_course_code" class="form-control" maxlength="20" required style="text-transform: uppercase;">
                            </div>
                        </div>

                        <!-- NSQF Template Selection (shown only for NSQF courses) -->
                        <div id="edit_template_selection_group" style="display: none; margin-top: 16px;">
                            <div class="mb-3">
                                <label class="form-label">NSQF Course Name *</label>
                                <select name="course_name_template" id="edit_course_name_template" class="form-control" onchange="handleEditTemplateSelection()">
                                    <option value="">-- Select NSQF Course Name --</option>
                                </select>
                                <small class="text-muted">Select a course name from NSQF templates</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Training Centre</label>
                                <select name="centre_id" id="edit_centre_id" class="form-control">
                                    <option value="">-- Select Centre --</option>
                                    <?php foreach ($centres as $centre): ?>
                                        <option value="<?= $centre['id'] ?>"><?= htmlspecialchars(normalize_nielit_centre_name($centre['name'])) ?> (<?= htmlspecialchars($centre['code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Optional: Associate course with a training centre</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Training Centre *</label>
                                <select name="training_center" id="edit_training_center" class="form-control" required>
                                    <option value="">-- Select Training Centre --</option>
                                    <?php foreach ($centres as $centre): ?>
                                        <option value="<?= htmlspecialchars(normalize_nielit_centre_name($centre['name'])) ?>"><?= htmlspecialchars(normalize_nielit_centre_name($centre['name'])) ?> (<?= htmlspecialchars($centre['code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>

                        <hr>
                        <h6 class="mb-3"><i class="fas fa-link"></i> Registration Link Settings</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Apply Link</label>
                                <div class="input-group">
                                    <input type="text" name="custom_link" id="edit_apply_link" class="form-control" placeholder="Will be auto-generated" readonly>
                                    <button type="button" class="btn btn-success" onclick="generateApplyLink('edit')">
                                        <i class="fas fa-magic"></i> Generate Link
                                    </button>
                                </div>
                                <small class="text-muted">Click "Generate Link" to create registration URL automatically</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Publish Status</label>
                                <div class="form-check form-switch" style="padding-top: 8px;">
                                    <input class="form-check-input" type="checkbox" id="edit_link_published" name="link_published" value="1">
                                    <label class="form-check-label" for="edit_link_published">
                                        <span id="edit_publish_status">Unpublished</span>
                                    </label>
                                </div>
                                <small class="text-muted">Toggle to show/hide on website</small>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <strong><i class="fas fa-info-circle"></i> Current Link:</strong> <span id="current_link_edit">Not set</span>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-lightbulb"></i> <strong>Note:</strong> If you change the link, QR code will be regenerated automatically.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- QR Code View Modal -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-qrcode"></i> QR Code - <span id="qr_course_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="qr_code_image" src="" alt="QR Code" style="max-width: 100%; height: auto; border: 2px solid #0a1628; border-radius: 8px; padding: 10px;">
                    <div class="mt-3">
                        <p class="text-muted mb-2">Scan this QR code to register for the course</p>
                        <a id="qr_download_link" href="" download class="btn btn-primary">
                            <i class="fas fa-download"></i> Download QR Code
                        </a>
                        <button type="button" class="btn btn-warning" onclick="regenerateCurrentQR()">
                            <i class="fas fa-sync"></i> Regenerate
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const addNsqfTypeSelect = document.getElementById('add_nsqf_type');
        if (addNsqfTypeSelect) {
            addNsqfTypeSelect.addEventListener('change', function() {
                handleNsqfTypeChange('add', this.value);
            });
        }

        const editNsqfTypeSelect = document.getElementById('edit_nsqf_type');
        if (editNsqfTypeSelect) {
            editNsqfTypeSelect.addEventListener('change', function() {
                handleNsqfTypeChange('edit', this.value);
            });
        }
        
        const initialAddNsqfType = addNsqfTypeSelect ? addNsqfTypeSelect.value : '';
        const initialEditNsqfType = editNsqfTypeSelect ? editNsqfTypeSelect.value : '';
        if (initialAddNsqfType) {
            handleNsqfTypeChange('add', initialAddNsqfType);
        }
        if (initialEditNsqfType) {
            handleNsqfTypeChange('edit', initialEditNsqfType);
        }
        
        // Function to handle sub-category (NSQF type) change and show template dropdown for NSQF courses
        function handleNsqfTypeChange(mode, nsqfType) {
            const courseNameInput = document.getElementById(`${mode}_course_name`);
            const courseNameTemplate = document.getElementById(`${mode}_course_name_template`);
            const eligibilityField = document.getElementById(`${mode}_eligibility`);
            
            if (nsqfType === 'NSQF Course') {
                courseNameInput.style.display = 'none';
                courseNameTemplate.style.display = 'block';
                courseNameTemplate.required = true;
                courseNameInput.required = false;
                courseNameTemplate.value = '';
                courseNameInput.value = '';

                fetchNSQFTemplates(getSelectedTemplateCategory(mode), mode);

                if (eligibilityField) {
                    eligibilityField.readOnly = true;
                    eligibilityField.placeholder = 'Will be filled from selected template';
                }
            } else {
                // Non-NSQF courses - show regular input
                courseNameInput.style.display = 'block';
                courseNameTemplate.style.display = 'none';
                courseNameInput.required = true;
                courseNameTemplate.required = false;
                courseNameTemplate.value = '';
                
                if (eligibilityField) {
                    eligibilityField.readOnly = false;
                    eligibilityField.placeholder = 'Enter eligibility criteria';
                }
            }
        }
        
        // Function to fetch NSQF templates via AJAX
        function fetchNSQFTemplates(category, mode) {
            fetch('get_nsqf_templates.php?category=' + encodeURIComponent(category))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateTemplateDropdown(data.templates, mode);
                    } else {
                        console.error('Error fetching templates:', data.message);
                        alert('Error loading course templates. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading course templates. Please check your connection.');
                });
        }
        
        // Function to populate template dropdown
        function populateTemplateDropdown(templates, mode) {
            const templateSelect = document.getElementById(`${mode}_course_name_template`);
            
            // Clear existing options except first
            templateSelect.innerHTML = '<option value="">-- Select NSQF Course Name --</option>';
            
            // Add template options
            templates.forEach(template => {
                const option = document.createElement('option');
                option.value = template.id;
                option.textContent = template.course_name;
                option.dataset.eligibility = template.eligibility;
                templateSelect.appendChild(option);
            });
            
            // Add change event to populate eligibility
            templateSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const eligibilityField = document.getElementById(`${mode}_eligibility`);
                const courseNameInput = document.getElementById(`${mode}_course_name`);
                
                if (selectedOption.dataset.eligibility && eligibilityField) {
                    eligibilityField.value = selectedOption.dataset.eligibility;
                }
                
                // Set the actual course name for form submission
                if (courseNameInput) {
                    courseNameInput.value = selectedOption.textContent;
                }
            });
        }
        
        // Store current QR course ID for regeneration
        let currentQRCourseId = null;

        // Generate Apply Link
        function generateApplyLink(mode) {
            const courseNameInput = document.querySelector(`#${mode === 'add' ? 'addCourseModal' : 'editCourseModal'} input[name="course_name"]`);
            const courseIdInput = mode === 'edit' ? document.getElementById('edit_course_id') : null;
            const courseCodeInput = document.getElementById(`${mode}_course_code`);
            const linkInput = document.getElementById(`${mode}_apply_link`);
            const previewSpan = document.getElementById(`link_preview_${mode}`);
            
            const courseName = courseNameInput.value.trim();
            const courseCode = courseCodeInput ? courseCodeInput.value.trim().toUpperCase() : '';
            
            if (!courseName) {
                alert('Please enter course name first!');
                courseNameInput.focus();
                return;
            }
            
            if (!courseCode) {
                alert('Please enter course code first!');
                if (courseCodeInput) courseCodeInput.focus();
                return;
            }
            
            // Generate link based on course code (not course name)
            const baseUrl = window.location.origin + window.location.pathname.replace('manage_courses.php', '');
            const registrationLink = baseUrl + '../student/register.php?course=' + encodeURIComponent(courseCode);
            
            linkInput.value = registrationLink;
            if (previewSpan) {
                previewSpan.textContent = registrationLink;
            }
            
            // Show success message
            alert('Registration link generated! QR code will be created automatically when you save.');
        }

        // Toggle publish status label
        document.getElementById('add_link_published').addEventListener('change', function() {
            document.getElementById('add_publish_status').textContent = this.checked ? 'Published' : 'Unpublished';
            document.getElementById('add_publish_status').className = this.checked ? 'text-success fw-bold' : '';
        });

        document.getElementById('edit_link_published').addEventListener('change', function() {
            document.getElementById('edit_publish_status').textContent = this.checked ? 'Published' : 'Unpublished';
            document.getElementById('edit_publish_status').className = this.checked ? 'text-success fw-bold' : '';
        });

        // Generate QR Code via AJAX
        function generateQRCode(courseId) {
            const btn = document.getElementById('qr_btn_' + courseId);
            const originalHTML = btn.innerHTML;
            
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            
            // Send AJAX request
            fetch('generate_qr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'course_id=' + courseId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('QR Code generated successfully!');
                    
                    // Reload page to show new QR code buttons
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            })
            .catch(error => {
                alert('Error generating QR Code: ' + error);
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            });
        }

        // View QR Code in modal
        function viewQRCode(courseId, qrPath, courseName) {
            currentQRCourseId = courseId;
            document.getElementById('qr_course_name').textContent = courseName;
            document.getElementById('qr_code_image').src = '../' + qrPath;
            document.getElementById('qr_download_link').href = '../' + qrPath;
            
            const modal = new bootstrap.Modal(document.getElementById('qrCodeModal'));
            modal.show();
        }

        // Regenerate current QR code
        function regenerateCurrentQR() {
            if (currentQRCourseId) {
                if (confirm('Are you sure you want to regenerate this QR code? The old QR code will be replaced.')) {
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('qrCodeModal')).hide();
                    
                    // Generate new QR
                    generateQRCode(currentQRCourseId);
                }
            }
        }

        // Copy link to clipboard
        function copyLink(courseId) {
            const linkInput = document.getElementById('link_' + courseId);
            linkInput.select();
            linkInput.setSelectionRange(0, 99999); // For mobile devices
            
            navigator.clipboard.writeText(linkInput.value).then(() => {
                // Show success feedback
                const btn = event.target.closest('button');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i>';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-outline-secondary');
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                }, 2000);
            }).catch(err => {
                alert('Failed to copy link: ' + err);
            });
        }

        function editCourse(course) {
            document.getElementById('edit_course_id').value = course.id;
            document.getElementById('edit_course_name').value = course.course_name;
            document.getElementById('edit_course_code').value = course.course_code;
            document.getElementById('edit_course_type').value = course.course_type;
            const editNsqfTypeSelect = document.getElementById('edit_nsqf_type');
            if (editNsqfTypeSelect) {
                editNsqfTypeSelect.value = (course.is_nsqf == 1 || course.is_nsqf === '1') ? 'NSQF Course' : '<?php echo addslashes(get_default_non_nsqf_sub_category()); ?>';
                handleNsqfTypeChange('edit', editNsqfTypeSelect.value);
            }
            document.getElementById('edit_training_center').value = course.training_center;
            document.getElementById('edit_centre_id').value = course.centre_id || '';
            document.getElementById('edit_duration').value = course.duration || '';
            document.getElementById('edit_fees').value = course.fees || '';
            document.getElementById('edit_description').value = course.description || '';
            document.getElementById('edit_eligibility').value = course.eligibility || '';
            document.getElementById('edit_is_nsqf').checked = (course.is_nsqf == 1 || course.is_nsqf === '1');
            
            // Set registration link
            document.getElementById('edit_apply_link').value = course.registration_link || '';
            document.getElementById('current_link_edit').textContent = course.registration_link || 'Not set';
            
            // Set publish status
            const isPublished = course.link_published == 1;
            document.getElementById('edit_link_published').checked = isPublished;
            document.getElementById('edit_publish_status').textContent = isPublished ? 'Published' : 'Unpublished';
            document.getElementById('edit_publish_status').className = isPublished ? 'text-success fw-bold' : '';
            
            new bootstrap.Modal(document.getElementById('editCourseModal')).show();
        }
    </script>

    <script>
    /**
     * AUTO-GENERATE COURSE CODE FEATURE - ADD COURSE MODAL
     * Generates meaningful course codes based on course name
     * Automatically handles duplicates by adding sequential numbers
     */

    // Function to generate course code from course name
    function generateCodeFromName(courseName) {
        if (!courseName || courseName.trim() === '') {
            return { code: '', abbreviation: '' };
        }
        
        // Remove special characters and extra spaces
        courseName = courseName.trim().toUpperCase();
        
        // Common words to ignore
        const ignoreWords = ['THE', 'OF', 'IN', 'ON', 'AT', 'TO', 'FOR', 'AND', 'OR', 'A', 'AN'];
        
        // Split into words
        const words = courseName.split(/\s+/).filter(word => word.length > 0);
        
        let code = '';
        let abbreviation = '';
        
        // Strategy 1: Try to extract acronym from significant words
        const significantWords = words.filter(word => !ignoreWords.includes(word));
        
        if (significantWords.length > 0) {
            // Take first letter of each significant word (up to 5 letters)
            abbreviation = significantWords
                .slice(0, 5)
                .map(word => word[0])
                .join('');
            
            // If abbreviation is too short, add more letters from first word
            if (abbreviation.length < 3 && significantWords[0].length >= 3) {
                abbreviation = significantWords[0].substring(0, 3).toUpperCase();
            }
        } else {
            // Fallback: use first 3-4 letters of first word
            abbreviation = words[0].substring(0, Math.min(4, words[0].length)).toUpperCase();
        }
        
        // Add current year
        const currentYear = new Date().getFullYear();
        code = abbreviation + '-' + currentYear;
        
        return {
            code: code,
            abbreviation: abbreviation
        };
    }

    // Function to check if code exists and get next available number
    async function getUniqueCode(baseCode, baseAbbreviation, currentCourseId = null) {
        try {
            // Check if base code exists
            const response = await fetch('check_course_code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    code: baseCode,
                    abbreviation: baseAbbreviation,
                    exclude_id: currentCourseId
                })
            });
            
            const result = await response.json();
            
            if (!result.exists) {
                // Code is unique, return as is
                return {
                    code: baseCode,
                    abbreviation: baseAbbreviation
                };
            }
            
            // Code exists, find next available number
            let counter = 1;
            let uniqueCode = '';
            let uniqueAbbr = '';
            let found = false;
            
            while (!found && counter <= 99) {
                const paddedNumber = counter.toString().padStart(2, '0');
                uniqueCode = baseAbbreviation + paddedNumber + '-' + new Date().getFullYear();
                uniqueAbbr = baseAbbreviation + paddedNumber;
                
                const checkResponse = await fetch('check_course_code.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        code: uniqueCode,
                        abbreviation: uniqueAbbr,
                        exclude_id: currentCourseId
                    })
                });
                
                const checkResult = await checkResponse.json();
                
                if (!checkResult.exists) {
                    found = true;
                    return {
                        code: uniqueCode,
                        abbreviation: uniqueAbbr
                    };
                }
                
                counter++;
            }
            
            // If we reach here, return with high number
            return {
                code: baseAbbreviation + '99-' + new Date().getFullYear(),
                abbreviation: baseAbbreviation + '99'
            };
            
        } catch (error) {
            console.error('Error checking code uniqueness:', error);
            // Return base code if check fails
            return {
                code: baseCode,
                abbreviation: baseAbbreviation
            };
        }
    }

    // Auto-generate as user types (with debounce) - ADD MODAL
    let addTypingTimer;
    const addTypingDelay = 500; // milliseconds

    async function autoGenerateAddCourseCode() {
        clearTimeout(addTypingTimer);
        addTypingTimer = setTimeout(async function() {
            const courseNameInput = document.getElementById('add_course_name');
            const courseCodeInput = document.getElementById('add_course_code');
            const manualCheckbox = document.getElementById('add_manual_code');
            
            // Only auto-generate if not in manual mode
            if (!manualCheckbox.checked && courseNameInput && courseCodeInput) {
                const courseName = courseNameInput.value;
                const generated = generateCodeFromName(courseName);
                
                if (generated.code) {
                    const uniqueCode = await getUniqueCode(generated.code, generated.abbreviation, null);
                    courseCodeInput.value = uniqueCode.code;
                }
            }
        }, addTypingDelay);
    }

    // Regenerate button click - ADD MODAL
    async function regenerateAddCourseCode() {
        const courseNameInput = document.getElementById('add_course_name');
        const courseCodeInput = document.getElementById('add_course_code');
        if (courseNameInput && courseCodeInput) {
            const courseName = courseNameInput.value;
            const generated = generateCodeFromName(courseName);
            
            if (generated.code) {
                const uniqueCode = await getUniqueCode(generated.code, generated.abbreviation, null);
                courseCodeInput.value = uniqueCode.code;
                toast.success('Course code generated: ' + uniqueCode.code, 3000);
            } else {
                toast.error('Please enter a course name first', 3000);
            }
        }
    }

    // Toggle manual editing mode - ADD MODAL
    function toggleAddManualCode() {
        const manualCheckbox = document.getElementById('add_manual_code');
        const courseCodeInput = document.getElementById('add_course_code');
        if (manualCheckbox && courseCodeInput) {
            if (manualCheckbox.checked) {
                courseCodeInput.removeAttribute('readonly');
                courseCodeInput.focus();
                toast.info('Manual editing enabled', 3000);
            } else {
                courseCodeInput.setAttribute('readonly', 'readonly');
                regenerateAddCourseCode();
            }
        }
    }

    /**
     * CATEGORY AND SUB-CATEGORY HANDLERS
     * Handle changes to category and sub-category dropdowns
     */
    
    function getSelectedTemplateCategory(mode) {
        const categorySelect = document.getElementById(mode === 'add' ? 'add_course_category' : 'edit_course_type');
        return categorySelect ? categorySelect.value : '';
    }

    // Handle Add Category Change
    function handleAddCategoryChange() {
        const categorySelect = document.getElementById('add_course_category');
        const subCategorySelect = document.getElementById('add_nsqf_type');
        const eligibilityField = document.getElementById('add_eligibility');
        const durationField = document.getElementById('add_duration');
        
        // Re-fetch NSQF templates when category changes while NSQF sub-category is selected
        if (subCategorySelect && subCategorySelect.value === 'NSQF Course') {
            fetchNSQFTemplates(getSelectedTemplateCategory('add'), 'add');
        }

        // Set appropriate placeholders based on category
        if (categorySelect && eligibilityField && durationField) {
            const category = categorySelect.value;
            
            // Set placeholder for eligibility based on category
            switch(category) {
                case 'Degree / Diploma / PG':
                    eligibilityField.placeholder = 'e.g., 10+2 or equivalent';
                    durationField.placeholder = 'e.g., 3 Years';
                    break;
                case 'Skill Based (Long Term) >500 hrs':
                    eligibilityField.placeholder = 'e.g., 10th Pass or equivalent';
                    durationField.placeholder = 'e.g., 600 Hours';
                    break;
                case 'Skill Based (Short Term) 90-500 hrs':
                    eligibilityField.placeholder = 'e.g., 8th Pass or equivalent';
                    durationField.placeholder = 'e.g., 120 Hours';
                    break;
                case 'Short Term / Digital Competency <=90 hrs':
                    eligibilityField.placeholder = 'e.g., Basic Computer Knowledge';
                    durationField.placeholder = 'e.g., 60 Hours';
                    break;
                case 'NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)':
                    eligibilityField.placeholder = 'e.g., 8th Pass';
                    durationField.placeholder = 'e.g., 80 Hours';
                    break;
                default:
                    eligibilityField.placeholder = 'Enter eligibility criteria';
                    durationField.placeholder = 'e.g., 6 Months';
            }
        }
    }
    
    // Handle Add Sub-Category Change
    function handleAddSubCategoryChange() {
        const subCategorySelect = document.getElementById('add_nsqf_type');
        const templateGroup = document.getElementById('add_template_selection_group');
        const courseNameInput = document.getElementById('add_course_name');
        const eligibilityField = document.getElementById('add_eligibility');
        
        if (subCategorySelect && templateGroup) {
            const subCategory = subCategorySelect.value;
            
            // Show template selection only for NSQF courses
            if (subCategory === 'NSQF Course') {
                templateGroup.style.display = 'block';
                courseNameInput.style.display = 'none';
                eligibilityField.setAttribute('readonly', 'readonly');
                eligibilityField.placeholder = 'Will be filled from NSQF template';
                fetchNSQFTemplates(getSelectedTemplateCategory('add'), 'add');
            } else {
                templateGroup.style.display = 'none';
                courseNameInput.style.display = 'block';
                eligibilityField.removeAttribute('readonly');
                
                // Set placeholder based on sub-category
                switch(subCategory) {
                    case 'Internship Program':
                        eligibilityField.placeholder = 'e.g., Currently enrolled in relevant course';
                        break;
                    case 'Awareness Program':
                        eligibilityField.placeholder = 'e.g., Open to all';
                        break;
                    case 'FDP Program':
                        eligibilityField.placeholder = 'e.g., Faculty members from recognized institutions';
                        break;
                    case 'Workshop':
                        eligibilityField.placeholder = 'e.g., Basic knowledge of the subject';
                        break;
                    case 'Govt/Corporate Training':
                        eligibilityField.placeholder = 'e.g., As per organization requirements';
                        break;
                    default:
                        eligibilityField.placeholder = 'Enter eligibility criteria';
                }
            }
        }
    }
    
    // Handle Edit Category Change
    function handleEditCategoryChange() {
        const categorySelect = document.getElementById('edit_course_type');
        const subCategorySelect = document.getElementById('edit_nsqf_type');
        const eligibilityField = document.getElementById('edit_eligibility');
        const durationField = document.getElementById('edit_duration');
        
        // Set appropriate placeholders based on category
        if (categorySelect && eligibilityField && durationField) {
            const category = categorySelect.value;
            
            // Set placeholder for eligibility based on category
            switch(category) {
                case 'Degree / Diploma / PG':
                    eligibilityField.placeholder = 'e.g., 10+2 or equivalent';
                    durationField.placeholder = 'e.g., 3 Years';
                    break;
                case 'Skill Based (Long Term) >500 hrs':
                    eligibilityField.placeholder = 'e.g., 10th Pass or equivalent';
                    durationField.placeholder = 'e.g., 600 Hours';
                    break;
                case 'Skill Based (Short Term) 90-500 hrs':
                    eligibilityField.placeholder = 'e.g., 8th Pass or equivalent';
                    durationField.placeholder = 'e.g., 120 Hours';
                    break;
                case 'Short Term / Digital Competency <=90 hrs':
                    eligibilityField.placeholder = 'e.g., Basic Computer Knowledge';
                    durationField.placeholder = 'e.g., 60 Hours';
                    break;
                case 'NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)':
                    eligibilityField.placeholder = 'e.g., 8th Pass';
                    durationField.placeholder = 'e.g., 80 Hours';
                    break;
                default:
                    eligibilityField.placeholder = 'Enter eligibility criteria';
                    durationField.placeholder = 'e.g., 6 Months';
            }
        }
    }
    
    // Handle Edit Sub-Category Change
    function handleEditSubCategoryChange() {
        const subCategorySelect = document.getElementById('edit_nsqf_type');
        const templateGroup = document.getElementById('edit_template_selection_group');
        const courseNameInput = document.getElementById('edit_course_name');
        const eligibilityField = document.getElementById('edit_eligibility');
        
        if (subCategorySelect && templateGroup) {
            const subCategory = subCategorySelect.value;
            
            // Show template selection only for NSQF courses
            if (subCategory === 'NSQF Course') {
                templateGroup.style.display = 'block';
                courseNameInput.style.display = 'none';
                eligibilityField.setAttribute('readonly', 'readonly');
                eligibilityField.placeholder = 'Will be filled from NSQF template';
                fetchNSQFTemplates(getSelectedTemplateCategory('edit'), 'edit');
            } else {
                templateGroup.style.display = 'none';
                courseNameInput.style.display = 'block';
                eligibilityField.removeAttribute('readonly');
                
                // Set placeholder based on sub-category
                switch(subCategory) {
                    case 'Internship Program':
                        eligibilityField.placeholder = 'e.g., Currently enrolled in relevant course';
                        break;
                    case 'Awareness Program':
                        eligibilityField.placeholder = 'e.g., Open to all';
                        break;
                    case 'FDP Program':
                        eligibilityField.placeholder = 'e.g., Faculty members from recognized institutions';
                        break;
                    case 'Workshop':
                        eligibilityField.placeholder = 'e.g., Basic knowledge of the subject';
                        break;
                    case 'Govt/Corporate Training':
                        eligibilityField.placeholder = 'e.g., As per organization requirements';
                        break;
                    default:
                        eligibilityField.placeholder = 'Enter eligibility criteria';
                }
            }
        }
    }
    
    // Handle Add Template Selection
    function handleAddTemplateSelection() {
        const templateSelect = document.getElementById('add_course_name_template');
        const courseNameInput = document.getElementById('add_course_name');
        const eligibilityField = document.getElementById('add_eligibility');
        
        if (templateSelect && courseNameInput && eligibilityField) {
            const selectedOption = templateSelect.options[templateSelect.selectedIndex];
            
            if (selectedOption.value) {
                courseNameInput.value = selectedOption.textContent;
                if (selectedOption.dataset.eligibility) {
                    eligibilityField.value = selectedOption.dataset.eligibility;
                }
                
                // Auto-generate course code
                autoGenerateAddCourseCode();
            }
        }
    }
    
    // Handle Edit Template Selection
    function handleEditTemplateSelection() {
        const templateSelect = document.getElementById('edit_course_name_template');
        const courseNameInput = document.getElementById('edit_course_name');
        const eligibilityField = document.getElementById('edit_eligibility');
        
        if (templateSelect && courseNameInput && eligibilityField) {
            const selectedOption = templateSelect.options[templateSelect.selectedIndex];
            
            if (selectedOption.value) {
                courseNameInput.value = selectedOption.textContent;
                if (selectedOption.dataset.eligibility) {
                    eligibilityField.value = selectedOption.dataset.eligibility;
                }
            }
        }
    }
    </script>
</body>
</html>
