<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login_new.php');
    exit();
}

require_once '../config/database.php';
require_once '../includes/theme_loader.php';

// Load active theme
$active_theme = loadActiveTheme($conn);
$theme_logo = getThemeLogo($active_theme);

// Handle Add/Edit/Delete Course
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $course_name = $_POST['course_name'];
        $course_code = strtoupper($_POST['course_code']);
        $course_abbreviation = strtoupper($_POST['course_abbreviation'] ?? '');
        $course_type = $_POST['course_type'];
        $training_center = $_POST['training_center'];
        $centre_id = !empty($_POST['centre_id']) ? intval($_POST['centre_id']) : NULL;
        $duration = $_POST['duration'];
        $fees = $_POST['fees'];
        $description = $_POST['description'];
        $custom_link = $_POST['custom_link'] ?? '';
        $link_published = isset($_POST['link_published']) ? 1 : 0;
        
        // Generate registration link if provided
        if (!empty($custom_link)) {
            $registration_link = $custom_link;
        } else {
            $registration_link = '';
        }
        
        $stmt = $conn->prepare("INSERT INTO courses (centre_id, course_name, course_code, course_abbreviation, course_type, training_center, duration, fees, description, registration_link, link_published, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("issssssdssi", $centre_id, $course_name, $course_code, $course_abbreviation, $course_type, $training_center, $duration, $fees, $description, $registration_link, $link_published);
        
        if ($stmt->execute()) {
            $course_id = $conn->insert_id;
            
            // Auto-generate QR code if registration link exists
            if (!empty($registration_link)) {
                require_once '../includes/qr_helper.php';
                $qr_result = generateCourseQRCode($course_id, $course_code);
                
                if ($qr_result['success']) {
                    // Update course with QR path
                    $stmt_update = $conn->prepare("UPDATE courses SET qr_code_path = ?, qr_generated_at = NOW() WHERE id = ?");
                    $stmt_update->bind_param("si", $qr_result['path'], $course_id);
                    $stmt_update->execute();
                    
                    $success = "Course added successfully! Registration link and QR code generated.";
                } else {
                    $success = "Course added successfully! But QR code generation failed.";
                }
            } else {
                $success = "Course added successfully! Generate registration link to create QR code.";
            }
        } else {
            $error = "Error: " . $conn->error;
        }
    }
    
    if ($action === 'edit') {
        $id = $_POST['course_id'];
        $course_name = $_POST['course_name'];
        $course_code = strtoupper($_POST['course_code']);
        $course_abbreviation = strtoupper($_POST['course_abbreviation'] ?? '');
        $course_type = $_POST['course_type'];
        $training_center = $_POST['training_center'];
        $centre_id = !empty($_POST['centre_id']) ? intval($_POST['centre_id']) : NULL;
        $duration = $_POST['duration'];
        $fees = $_POST['fees'];
        $description = $_POST['description'];
        $custom_link = $_POST['custom_link'] ?? '';
        $link_published = isset($_POST['link_published']) ? 1 : 0;
        
        // Use provided link or keep existing
        $registration_link = !empty($custom_link) ? $custom_link : '';
        
        $stmt = $conn->prepare("UPDATE courses SET centre_id=?, course_name=?, course_code=?, course_abbreviation=?, course_type=?, training_center=?, duration=?, fees=?, description=?, registration_link=?, link_published=? WHERE id=?");
        $stmt->bind_param("issssssdssii", $centre_id, $course_name, $course_code, $course_abbreviation, $course_type, $training_center, $duration, $fees, $description, $registration_link, $link_published, $id);
        
        if ($stmt->execute()) {
            // Regenerate QR code if registration link exists
            if (!empty($registration_link)) {
                require_once '../includes/qr_helper.php';
                
                // Get old QR path
                $stmt_get = $conn->prepare("SELECT qr_code_path FROM courses WHERE id = ?");
                $stmt_get->bind_param("i", $id);
                $stmt_get->execute();
                $result_get = $stmt_get->get_result();
                $old_course = $result_get->fetch_assoc();
                
                // Delete old QR if exists
                if (!empty($old_course['qr_code_path'])) {
                    deleteQRCode($old_course['qr_code_path']);
                }
                
                // Generate new QR
                $qr_result = generateCourseQRCode($id, $course_code);
                
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
    }
    
    if ($action === 'delete') {
        $id = $_POST['course_id'];
        $stmt = $conn->prepare("UPDATE courses SET status='inactive' WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Course deactivated successfully!";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// Get filter parameters
$filter_type = $_GET['type'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';

// Build query with filters - JOIN with centres table to get centre name
$query = "SELECT courses.*, centres.name AS centre_name, centres.code AS centre_code 
          FROM courses 
          LEFT JOIN centres ON courses.centre_id = centres.id 
          WHERE 1=1";
$params = [];
$types = "";

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

// Fetch active centres for dropdown
$centres_query = "SELECT id, name, code FROM centres WHERE is_active = 1 ORDER BY name ASC";
$centres_result = $conn->query($centres_query);
$centres = [];
if ($centres_result) {
    while ($centre = $centres_result->fetch_assoc()) {
        $centres[] = $centre;
    }
}

// Execute query with filters
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $courses = $stmt->get_result();
} else {
    $courses = $conn->query($query);
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
    <link rel="icon" href="<?php echo getThemeFavicon($active_theme); ?>" type="image/x-icon">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-graduation-cap"></i> Manage Courses</h1>
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

                <!-- Filter Section -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-filter"></i> Filter by Category</label>
                                <select name="type" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Categories</option>
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
                                        <th>Student ID Code</th>
                                        <th>Type</th>
                                        <th>Centre</th>
                                        <th>Training Center</th>
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
                                        <td>
                                            <?php if (!empty($course['course_abbreviation'])): ?>
                                                <span class="badge bg-success"><?= htmlspecialchars($course['course_abbreviation']) ?></span>
                                                <br><small class="text-muted">NIELIT/2026/<?= htmlspecialchars($course['course_abbreviation']) ?>/####</small>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Set</span>
                                            <?php endif; ?>
                                        </td>
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
                                        <td>₹<?= number_format($course['fees'], 2) ?></td>
                                        <td>
                                            <div class="input-group input-group-sm" style="max-width: 300px;">
                                                <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($course['registration_link']) ?>" id="link_<?= $course['id'] ?>" readonly>
                                                <button class="btn btn-outline-secondary" type="button" onclick="copyLink(<?= $course['id'] ?>)" title="Copy Link">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                                <a href="<?= htmlspecialchars($course['registration_link']) ?>" target="_blank" class="btn btn-outline-primary" title="Open Link">
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
                                            <?php if ($course['status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="editCourse(<?= htmlspecialchars(json_encode($course)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
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
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course Name *</label>
                                <input type="text" name="course_name" class="form-control" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Course Code * <small>(e.g., PPI, DBC15)</small></label>
                                <input type="text" name="course_code" id="add_course_code" class="form-control" maxlength="20" required style="text-transform: uppercase;">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Student ID Code * <small>(e.g., PPI)</small></label>
                                <input type="text" name="course_abbreviation" id="add_course_abbreviation" class="form-control" maxlength="10" required style="text-transform: uppercase;" placeholder="PPI">
                                <small class="text-muted">For ID: NIELIT/2026/<strong>PPI</strong>/0001</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course Type *</label>
                                <select name="course_type" class="form-control" required>
                                    <option value="Regular">Regular</option>
                                    <option value="Internship">Internship</option>
                                    <option value="Bootcamp">Bootcamp</option>
                                    <option value="Workshop">Workshop</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Training Centre</label>
                                <select name="centre_id" class="form-control">
                                    <option value="">-- Select Centre --</option>
                                    <?php foreach ($centres as $centre): ?>
                                        <option value="<?= $centre['id'] ?>"><?= htmlspecialchars($centre['name']) ?> (<?= htmlspecialchars($centre['code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Optional: Associate course with a training centre</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Training Center (Legacy) *</label>
                                <select name="training_center" class="form-control" required>
                                    <option value="NIELIT BHUBANESWAR">NIELIT BHUBANESWAR</option>
                                    <option value="NIELIT EXTENDED CENTER BALASORE">NIELIT BALASORE EXTENSION CENTRE</option>
                                </select>
                                <small class="text-muted">Legacy field - will be replaced by Training Centre dropdown above</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration</label>
                                <input type="text" name="duration" class="form-control" placeholder="e.g., 6 months">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fees (₹)</label>
                                <input type="number" name="fees" class="form-control" step="0.01">
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
                                <small class="text-muted">Toggle to show/hide on website</small>
                            </div>
                        </div>

                        <div class="alert alert-info" id="add_link_preview">
                            <strong><i class="fas fa-info-circle"></i> Preview:</strong> 
                            <span id="link_preview_add">Enter course name and click "Generate Link"</span>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-lightbulb"></i> <strong>Note:</strong> QR code will be generated automatically when you save the course with a registration link.
                        </div>
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
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course Name *</label>
                                <input type="text" name="course_name" id="edit_course_name" class="form-control" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Course Code *</label>
                                <input type="text" name="course_code" id="edit_course_code" class="form-control" maxlength="20" required style="text-transform: uppercase;">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Student ID Code *</label>
                                <input type="text" name="course_abbreviation" id="edit_course_abbreviation" class="form-control" maxlength="10" required style="text-transform: uppercase;" placeholder="PPI">
                                <small class="text-muted">For ID: NIELIT/2026/<strong id="edit_abbr_preview">XXX</strong>/0001</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course Type *</label>
                                <select name="course_type" id="edit_course_type" class="form-control" required>
                                    <option value="Regular">Regular</option>
                                    <option value="Internship">Internship</option>
                                    <option value="Bootcamp">Bootcamp</option>
                                    <option value="Workshop">Workshop</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Training Centre</label>
                                <select name="centre_id" id="edit_centre_id" class="form-control">
                                    <option value="">-- Select Centre --</option>
                                    <?php foreach ($centres as $centre): ?>
                                        <option value="<?= $centre['id'] ?>"><?= htmlspecialchars($centre['name']) ?> (<?= htmlspecialchars($centre['code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Optional: Associate course with a training centre</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Training Center (Legacy) *</label>
                                <select name="training_center" id="edit_training_center" class="form-control" required>
                                    <option value="NIELIT BHUBANESWAR">NIELIT BHUBANESWAR</option>
                                    <option value="NIELIT EXTENDED CENTER BALASORE">NIELIT BALASORE EXTENSION CENTRE</option>
                                </select>
                                <small class="text-muted">Legacy field - will be replaced by Training Centre dropdown above</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration</label>
                                <input type="text" name="duration" id="edit_duration" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fees (₹)</label>
                                <input type="number" name="fees" id="edit_fees" class="form-control" step="0.01">
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
                    <img id="qr_code_image" src="" alt="QR Code" style="max-width: 100%; height: auto; border: 2px solid #0d47a1; border-radius: 8px; padding: 10px;">
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

        // Update abbreviation preview as user types (Edit modal)
        document.getElementById('edit_course_abbreviation').addEventListener('input', function() {
            const abbr = this.value.toUpperCase() || 'XXX';
            document.getElementById('edit_abbr_preview').textContent = abbr;
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
            document.getElementById('edit_course_abbreviation').value = course.course_abbreviation || '';
            document.getElementById('edit_course_type').value = course.course_type;
            document.getElementById('edit_training_center').value = course.training_center;
            document.getElementById('edit_centre_id').value = course.centre_id || '';
            document.getElementById('edit_duration').value = course.duration || '';
            document.getElementById('edit_fees').value = course.fees || '';
            document.getElementById('edit_description').value = course.description || '';
            
            // Update abbreviation preview
            if (course.course_abbreviation) {
                document.getElementById('edit_abbr_preview').textContent = course.course_abbreviation.toUpperCase();
            }
            
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
</body>
</html>
