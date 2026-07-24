<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/staff_profile_helper.php';

ensureStaffProfileSchema($conn);

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id'])) {
    if (!init_admin_session($_SESSION['admin'])) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
}

refresh_session_permissions();

$active_theme = loadActiveTheme($conn);

$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'] ?? ($_SESSION['role'] ?? '');

if (!in_array($admin_role, ['master_admin'], true)) {
    header('Location: dashboard.php');
    exit();
}

// Get filter parameters
$filter_category = $_GET['category'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_staff':
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $designation = trim($_POST['designation'] ?? '');
                $department = trim($_POST['department'] ?? '');
                $staff_category = trim($_POST['staff_category'] ?? '');
                $public_bio = trim($_POST['public_bio'] ?? '');
                $display_order = (int) ($_POST['display_order'] ?? 0);
                $show_on_website = isset($_POST['show_on_website']) ? 1 : 0;
                $show_on_contact = isset($_POST['show_on_contact']) ? 1 : 0;

                // UNIQUE(email) allows many NULLs but only one empty string
                if ($email === '') {
                    $email = null;
                }
                if ($phone === '') {
                    $phone = null;
                }
                
                if (empty($name)) {
                    $error_message = "Staff name is required.";
                    break;
                }
                if (empty($staff_category)) {
                    $error_message = "Please select a staff category.";
                    break;
                }

                $stmt = $conn->prepare(
                    "INSERT INTO faculty
                    (name, email, phone, designation, department, staff_category, public_bio, display_order, show_on_website, show_on_contact, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                if (!$stmt) {
                    $error_message = "Error preparing add request: " . $conn->error;
                    break;
                }

                $stmt->bind_param(
                    "sssssssiiii",
                    $name,
                    $email,
                    $phone,
                    $designation,
                    $department,
                    $staff_category,
                    $public_bio,
                    $display_order,
                    $show_on_website,
                    $show_on_contact,
                    $admin_id
                );
                if ($stmt->execute()) {
                    $success_message = "Staff member added successfully!";
                    $newFacultyId = (int) $conn->insert_id;

                    if (!empty($_FILES['profile_photo']['name'])) {
                        $photoResult = uploadStaffProfilePhoto($conn, $newFacultyId, $_FILES['profile_photo']);
                        if (!$photoResult['success']) {
                            $success_message .= ' (Photo: ' . $photoResult['message'] . ')';
                        }
                    }

                    // Auto-send confirmation email if email is provided
                    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $email_sent = sendFacultyConfirmationEmail($email, $name, $designation, $department);
                        $success_message = $email_sent
                            ? "Staff member added successfully! Confirmation email sent to <strong>$email</strong>."
                            : "Staff member added successfully! (Email could not be sent — check SMTP settings.)";

                        if ($email_sent) {
                            $column_check = $conn->query("SHOW COLUMNS FROM faculty LIKE 'email_confirmed_at'");
                            if ($column_check && $column_check->num_rows > 0) {
                                $update_stmt = $conn->prepare("UPDATE faculty SET email_confirmed_at = NOW() WHERE id = ?");
                                if ($update_stmt) {
                                    $update_stmt->bind_param("i", $newFacultyId);
                                    $update_stmt->execute();
                                    $update_stmt->close();
                                }
                            }
                        }
                    }
                } else {
                    if ($conn->errno === 1062) {
                        $error_message = empty($_POST['email'])
                            ? "Could not save without email due to a database constraint. Please enter an email address, or contact support to run the faculty email cleanup migration."
                            : "A staff member with this email already exists. Use a different email or edit the existing record.";
                    } else {
                        $error_message = "Error adding staff member: " . $stmt->error;
                    }
                }
                $stmt->close();
                break;
                
            case 'update_staff':
                $faculty_id = (int) ($_POST['faculty_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $designation = trim($_POST['designation'] ?? '');
                $department = trim($_POST['department'] ?? '');
                $staff_category = trim($_POST['staff_category'] ?? '');
                $public_bio = trim($_POST['public_bio'] ?? '');
                $display_order = (int) ($_POST['display_order'] ?? 0);
                $show_on_website = isset($_POST['show_on_website']) ? 1 : 0;
                $show_on_contact = isset($_POST['show_on_contact']) ? 1 : 0;
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                if ($email === '') {
                    $email = null;
                }
                if ($phone === '') {
                    $phone = null;
                }

                if ($faculty_id <= 0 || $name === '' || $staff_category === '') {
                    $error_message = "Please fill all required staff fields.";
                    break;
                }
                
                $stmt = $conn->prepare(
                    "UPDATE faculty SET
                        name = ?, email = ?, phone = ?, designation = ?, department = ?,
                        staff_category = ?, public_bio = ?, display_order = ?,
                        show_on_website = ?, show_on_contact = ?, is_active = ?
                     WHERE id = ?"
                );
                if (!$stmt) {
                    $error_message = "Error preparing update request: " . $conn->error;
                    break;
                }
                $stmt->bind_param(
                    "sssssssiiiii",
                    $name,
                    $email,
                    $phone,
                    $designation,
                    $department,
                    $staff_category,
                    $public_bio,
                    $display_order,
                    $show_on_website,
                    $show_on_contact,
                    $is_active,
                    $faculty_id
                );
                if ($stmt->execute()) {
                    $success_message = "Staff member updated successfully!";
                    if (!empty($_FILES['profile_photo']['name'])) {
                        $photoResult = uploadStaffProfilePhoto($conn, $faculty_id, $_FILES['profile_photo']);
                        if (!$photoResult['success']) {
                            $error_message = $photoResult['message'];
                            $success_message = '';
                        } elseif (!empty($photoResult['message'])) {
                            $success_message .= ' Photo updated.';
                        }
                    }
                } else {
                    $error_message = ($conn->errno === 1062)
                        ? "Another staff member already uses this email address."
                        : "Error updating staff member: " . $stmt->error;
                }
                $stmt->close();
                break;
                
            case 'delete_staff':
                $faculty_id = $_POST['faculty_id'];
                
                // Check if current admin can delete this staff member
                $check_stmt = $conn->prepare("SELECT created_by FROM faculty WHERE id = ?");
                $check_stmt->bind_param("i", $faculty_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $faculty_data = $check_result->fetch_assoc();
                $check_stmt->close();
                
                if (!$faculty_data) {
                    $error_message = "Staff member not found.";
                    break;
                }
                
                // Check permissions - only allow deletion if:
                // 1. Master admin can delete any staff member
                // 2. Course coordinator can only delete staff they created
                if ($admin_role !== 'master_admin' && $faculty_data['created_by'] != $admin_id) {
                    $error_message = "You can only delete staff members you have added.";
                    break;
                }
                
                $stmt = $conn->prepare("UPDATE faculty SET is_active = 0 WHERE id = ?");
                $stmt->bind_param("i", $faculty_id);
                if ($stmt->execute()) {
                    $success_message = "Staff member deactivated successfully!";
                } else {
                    $error_message = "Error deactivating staff member.";
                }
                break;
        }
    }
}

// Build query with filters
$where_conditions = [];
$bind_params = [];
$bind_types = '';

// Filter by category
if ($filter_category !== 'all') {
    $where_conditions[] = "staff_category = ?";
    $bind_params[] = $filter_category;
    $bind_types .= 's';
}

// Filter by status
if ($filter_status !== 'all') {
    $where_conditions[] = "is_active = ?";
    $bind_params[] = ($filter_status === 'active') ? 1 : 0;
    $bind_types .= 'i';
}

// Role-based filtering
if ($admin_role === 'master_admin') {
    // Master admins can see all staff
    $base_query = "SELECT * FROM faculty";
} else {
    // Course coordinators see only staff they created + global staff (created_by = 0 or NULL)
    $where_conditions[] = "(created_by = ? OR created_by = 0 OR created_by IS NULL)";
    $bind_params[] = $admin_id;
    $bind_types .= 'i';
    $base_query = "SELECT * FROM faculty";
}

// Combine conditions
if (!empty($where_conditions)) {
    $base_query .= " WHERE " . implode(" AND ", $where_conditions);
}

$orderBy = 'ORDER BY is_active DESC, staff_category ASC, name ASC';
if (facultyTableHasColumn($conn, 'display_order')) {
    $orderBy = 'ORDER BY is_active DESC, display_order ASC, staff_category ASC, name ASC';
}
$base_query .= ' ' . $orderBy;

// Execute query
if (!empty($bind_params)) {
    $stmt = $conn->prepare($base_query);
    $stmt->bind_param($bind_types, ...$bind_params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($base_query);
}

$faculty_members = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $faculty_members[] = $row;
    }
}

// Get category counts for filter badges
$category_counts = [];
$count_query = "SELECT staff_category, COUNT(*) as count FROM faculty WHERE is_active = 1 GROUP BY staff_category";
$count_result = $conn->query($count_query);
if ($count_result) {
    while ($row = $count_result->fetch_assoc()) {
        $category_counts[$row['staff_category']] = $row['count'];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff & Faculty Directory - NIELIT Admin</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">

<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-users-cog"></i> Staff & Faculty Directory</h4>
                <small>Manage faculty and staff with photos, positions, and public website visibility</small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role"><?php echo $admin_role === 'master_admin' ? 'Master Administrator' : 'Course Coordinator'; ?></span>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?></div>
                </div>
            </div>
        </div>

        <div class="admin-main">
            <!-- Filter Section -->
            <div class="content-card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-filter"></i> Filter Staff</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Staff Category</label>
                            <select name="category" class="form-select">
                                <option value="all" <?php echo $filter_category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                <option value="Faculty Staff" <?php echo $filter_category === 'Faculty Staff' ? 'selected' : ''; ?>>
                                    Faculty Staff <?php echo isset($category_counts['Faculty Staff']) ? '(' . $category_counts['Faculty Staff'] . ')' : ''; ?>
                                </option>
                                <option value="Scientists" <?php echo $filter_category === 'Scientists' ? 'selected' : ''; ?>>
                                    Scientists <?php echo isset($category_counts['Scientists']) ? '(' . $category_counts['Scientists'] . ')' : ''; ?>
                                </option>
                                <option value="Non S&T" <?php echo $filter_category === 'Non S&T' ? 'selected' : ''; ?>>
                                    Non S&T <?php echo isset($category_counts['Non S&T']) ? '(' . $category_counts['Non S&T'] . ')' : ''; ?>
                                </option>
                                <option value="Scientific and Technical Staff" <?php echo $filter_category === 'Scientific and Technical Staff' ? 'selected' : ''; ?>>
                                    Scientific & Technical Staff <?php echo isset($category_counts['Scientific and Technical Staff']) ? '(' . $category_counts['Scientific and Technical Staff'] . ')' : ''; ?>
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active Only</option>
                                <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="manage_faculty.php" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Staff Button -->
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center;">
                <div class="text-muted small">
                    Tick <strong>Show on Our Team</strong> / <strong>Show on Contact</strong> so people appear on the public website.
                    <a href="<?php echo htmlspecialchars(app_url('public/team'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Preview Our Team</a>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                    <i class="fas fa-plus"></i> Add Staff Member
                </button>
            </div>

            <?php if (isset($success_message)): ?>
                <!-- success shown via toast -->
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <!-- error shown via toast -->
            <?php endif; ?>

            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-users"></i> Staff Members
                        <?php if ($filter_category !== 'all'): ?>
                            <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($filter_category); ?></span>
                        <?php endif; ?>
                        <?php if ($filter_status !== 'all'): ?>
                            <span class="badge bg-secondary ms-2"><?php echo ucfirst($filter_status); ?></span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Contact</th>
                                    <th>Position</th>
                                    <th>Website</th>
                                    <th>Status</th>
                                    <th>Profile</th>
                                    <th>Send Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faculty_members as $faculty): ?>
                                <tr data-faculty-id="<?php echo (int)$faculty['id']; ?>">
                                    <td>
                                        <?php $adminPhoto = staffPhotoUrl($faculty); ?>
                                        <?php if ($adminPhoto !== ''): ?>
                                            <img src="<?php echo htmlspecialchars($adminPhoto, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:50%;">
                                        <?php else: ?>
                                            <div style="width:42px;height:42px;border-radius:50%;background:#e8eef7;display:flex;align-items:center;justify-content:center;color:#64748b;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($faculty['name']); ?></strong>
                                        <?php if ($faculty['created_by'] == $admin_id): ?>
                                        <small class="text-muted d-block" style="font-size: 10px;">
                                            <i class="fas fa-user-plus"></i> Added by me
                                        </small>
                                        <?php elseif (empty($faculty['created_by']) || $faculty['created_by'] == 0): ?>
                                        <small class="text-muted d-block" style="font-size: 10px;">
                                            <i class="fas fa-globe"></i> Global staff
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $category_colors = [
                                            'Faculty Staff' => 'bg-primary',
                                            'Scientists' => 'bg-success', 
                                            'Non S&T' => 'bg-warning',
                                            'Scientific and Technical Staff' => 'bg-info'
                                        ];
                                        $badge_color = $category_colors[$faculty['staff_category']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $badge_color; ?>">
                                            <?php echo htmlspecialchars($faculty['staff_category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small"><?php echo htmlspecialchars((string) ($faculty['email'] ?? '')); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars((string) ($faculty['phone'] ?? '')); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars((string) ($faculty['designation'] ?? '')); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars((string) ($faculty['department'] ?? '')); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($faculty['show_on_website'])): ?>
                                            <span class="badge bg-primary mb-1">Team</span>
                                        <?php endif; ?>
                                        <?php if (!empty($faculty['show_on_contact'])): ?>
                                            <span class="badge bg-info text-dark">Contact</span>
                                        <?php endif; ?>
                                        <?php if (empty($faculty['show_on_website']) && empty($faculty['show_on_contact'])): ?>
                                            <span class="text-muted small">Hidden</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $faculty['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $faculty['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $profilePct = staffProfileCompletionPercent($faculty);
                                        $shareLink = buildStaffProfileShareLink($conn, (int) $faculty['id']);
                                        $staffProfileUrl = $shareLink['url'];
                                        $staffLinkSecondsRemaining = (int) ($shareLink['seconds_remaining'] ?? 0);
                                        $staffLinkExpiresTs = ($staffProfileUrl !== '' && $staffLinkSecondsRemaining > 0)
                                            ? time() + $staffLinkSecondsRemaining
                                            : 0;
                                        $staffLinkExpired = !empty($shareLink['is_expired']);
                                        ?>
                                        <a href="staff_profile.php?id=<?php echo (int)$faculty['id']; ?>" class="btn btn-sm btn-info" title="Edit full NIELIT Centre profile">
                                            <i class="fas fa-id-card"></i> Profile
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-primary mt-1" title="Copy link for staff to fill profile"
                                                data-profile-url="<?php echo htmlspecialchars($staffProfileUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-staff-name="<?php echo htmlspecialchars($faculty['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="copyStaffProfileLink(this)"
                                                <?php echo $staffProfileUrl === '' ? 'disabled' : ''; ?>>
                                            <i class="fas fa-link"></i> Copy Link
                                        </button>
                                        <?php if ($staffProfileUrl !== '' && $staffLinkExpiresTs > 0): ?>
                                        <div class="small text-primary mt-1 staff-link-timer" data-expires-ts="<?php echo (int) $staffLinkExpiresTs; ?>">
                                            <i class="fas fa-clock"></i> Expires in <span data-timer-text>--:--</span>
                                        </div>
                                        <?php elseif ($staffLinkExpired): ?>
                                        <div class="small text-danger mt-1">Link expired — open Profile to regenerate.</div>
                                        <?php elseif ($staffProfileUrl === ''): ?>
                                        <div class="small text-muted mt-1">No link — open Profile to generate.</div>
                                        <?php endif; ?>
                                        <div class="small text-muted mt-1"><?php echo $profilePct; ?>% complete</div>
                                        <a href="generate_staff_profile_pdf.php?id=<?php echo (int)$faculty['id']; ?>" target="_blank" class="btn btn-sm btn-outline-danger mt-1" title="Download profile PDF">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </a>
                                    </td>
                                    <td>
                                        <?php if (!empty($faculty['email'])): ?>
                                            <button class="btn btn-sm btn-warning"
                                                    onclick="resendStaffEmail(<?php echo (int)$faculty['id']; ?>, '<?php echo addslashes($faculty['name']); ?>', this)"
                                                    title="Resend confirmation email to <?php echo htmlspecialchars($faculty['name']); ?>">
                                                <i class="fas fa-paper-plane"></i> Resend Email
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 12px;">No email</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick='editStaff(<?php echo json_encode($faculty); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php
                                        $can_delete = $faculty['is_active'] && ($admin_role === 'master_admin' || $faculty['created_by'] == $admin_id);
                                        ?>
                                        <?php if ($can_delete): ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deactivateStaff(<?php echo $faculty['id']; ?>, '<?php echo addslashes(htmlspecialchars($faculty['name'], ENT_QUOTES)); ?>')"
                                                title="Deactivate <?php echo htmlspecialchars($faculty['name']); ?>">
                                            <i class="fas fa-ban"></i> Deactivate
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($faculty_members)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-users" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                                            <p>No staff members found matching your criteria.</p>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                                                <i class="fas fa-plus"></i> Add First Staff Member
                                            </button>
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
    </main>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_staff">
                    
                    <div class="mb-3">
                        <label for="staff_category" class="form-label">Staff Category *</label>
                        <select class="form-select" id="staff_category" name="staff_category" required>
                            <option value="">Select Category</option>
                            <option value="Faculty Staff">Faculty Staff</option>
                            <option value="Scientists">Scientists</option>
                            <option value="Non S&T">Non S&T</option>
                            <option value="Scientific and Technical Staff">Scientific and Technical Staff</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Official Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Mobile Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" maxlength="10" pattern="[0-9]{10}">
                    </div>
                    
                    <div class="mb-3">
                        <label for="designation" class="form-label">Designation / Position</label>
                        <input type="text" class="form-control" id="designation" name="designation" placeholder="e.g., Professor, Assistant Professor, Scientist">
                    </div>
                    
                    <div class="mb-3">
                        <label for="department" class="form-label">Department / School</label>
                        <input type="text" class="form-control" id="department" name="department" placeholder="e.g., Computer Science, IT, Research">
                    </div>

                    <div class="mb-3">
                        <label for="profile_photo" class="form-label">Photo</label>
                        <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png">
                        <div class="form-text">JPG or PNG, max 5MB. Shown on Our Team and Contact pages.</div>
                    </div>

                    <div class="mb-3">
                        <label for="public_bio" class="form-label">Short public bio</label>
                        <textarea class="form-control" id="public_bio" name="public_bio" rows="2" maxlength="500" placeholder="Optional short line shown on the website"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="display_order" class="form-label">Display order</label>
                        <input type="number" class="form-control" id="display_order" name="display_order" value="0" min="0">
                        <div class="form-text">Lower numbers appear first on the website.</div>
                    </div>

                    <div class="mb-2 form-check">
                        <input type="checkbox" class="form-check-input" id="show_on_website" name="show_on_website" value="1">
                        <label class="form-check-label" for="show_on_website">Show on Our Team page</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="show_on_contact" name="show_on_contact" value="1">
                        <label class="form-check-label" for="show_on_contact">Show as key contact on Contact page</label>
                    </div>
                    
                    <p class="text-muted small mb-0">
                        <i class="fas fa-info-circle"></i> After adding, open <strong>Profile</strong> to fill academic & research details and download PDF.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Staff Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editStaffForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_staff">
                    <input type="hidden" name="faculty_id" id="edit_staff_id">
                    
                    <div class="mb-3">
                        <label for="edit_staff_category" class="form-label">Staff Category *</label>
                        <select class="form-select" id="edit_staff_category" name="staff_category" required>
                            <option value="">Select Category</option>
                            <option value="Faculty Staff">Faculty Staff</option>
                            <option value="Scientists">Scientists</option>
                            <option value="Non S&T">Non S&T</option>
                            <option value="Scientific and Technical Staff">Scientific and Technical Staff</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Official Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Mobile Number</label>
                        <input type="text" class="form-control" id="edit_phone" name="phone" maxlength="10">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_designation" class="form-label">Designation / Position</label>
                        <input type="text" class="form-control" id="edit_designation" name="designation">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_department" class="form-label">Department / School</label>
                        <input type="text" class="form-control" id="edit_department" name="department">
                    </div>

                    <div class="mb-3">
                        <label for="edit_profile_photo" class="form-label">Photo</label>
                        <div id="edit_photo_preview_wrap" class="mb-2" style="display:none;">
                            <img id="edit_photo_preview" src="" alt="Current photo" style="width:72px;height:72px;object-fit:cover;border-radius:50%;">
                        </div>
                        <input type="file" class="form-control" id="edit_profile_photo" name="profile_photo" accept="image/jpeg,image/png">
                        <div class="form-text">Leave empty to keep the current photo.</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_public_bio" class="form-label">Short public bio</label>
                        <textarea class="form-control" id="edit_public_bio" name="public_bio" rows="2" maxlength="500"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="edit_display_order" class="form-label">Display order</label>
                        <input type="number" class="form-control" id="edit_display_order" name="display_order" value="0" min="0">
                    </div>
                    
                    <div class="mb-3">
                        <a href="#" id="edit_open_full_profile" class="btn btn-sm btn-outline-info" target="_blank">
                            <i class="fas fa-id-card"></i> Open full profile (academic & research)
                        </a>
                    </div>

                    <div class="mb-2 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_show_on_website" name="show_on_website" value="1">
                        <label class="form-check-label" for="edit_show_on_website">Show on Our Team page</label>
                    </div>
                    <div class="mb-2 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_show_on_contact" name="show_on_contact" value="1">
                        <label class="form-check-label" for="edit_show_on_contact">Show as key contact on Contact page</label>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Staff Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
function copyStaffProfileLink(buttonOrUrl, staffName) {
    let url = '';
    let name = staffName || 'staff member';

    if (buttonOrUrl && typeof buttonOrUrl === 'object' && buttonOrUrl.dataset) {
        url = (buttonOrUrl.dataset.profileUrl || '').trim();
        name = (buttonOrUrl.dataset.staffName || name).trim();
    } else {
        url = String(buttonOrUrl || '').trim();
    }

    if (!url) {
        toast.error('Profile link is not available yet. Open Profile and click Generate link.');
        return;
    }

    const copyFallback = function () {
        const tmp = document.createElement('textarea');
        tmp.value = url;
        tmp.setAttribute('readonly', '');
        tmp.style.position = 'fixed';
        tmp.style.left = '-9999px';
        document.body.appendChild(tmp);
        tmp.select();
        tmp.setSelectionRange(0, tmp.value.length);
        let copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (e) {
            copied = false;
        }
        document.body.removeChild(tmp);
        if (copied) {
            toast.success('Profile link copied for ' + name);
        } else {
            window.prompt('Copy this profile link:', url);
        }
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(function () {
            toast.success('Profile link copied for ' + name);
        }).catch(copyFallback);
    } else {
        copyFallback();
    }
}

function formatProfileLinkCountdown(totalSeconds) {
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    if (h > 0) {
        return h + 'h ' + String(m).padStart(2, '0') + 'm ' + String(s).padStart(2, '0') + 's';
    }
    return String(m).padStart(2, '0') + 'm ' + String(s).padStart(2, '0') + 's';
}

function initStaffProfileLinkTimers() {
    document.querySelectorAll('.staff-link-timer[data-expires-ts]').forEach(function (container) {
        const expiresTs = parseInt(container.getAttribute('data-expires-ts') || '0', 10);
        const textEl = container.querySelector('[data-timer-text]');
        if (!expiresTs || !textEl) return;

        function tick() {
            const left = Math.max(0, expiresTs - Math.floor(Date.now() / 1000));
            if (left <= 0) {
                textEl.textContent = 'Expired';
                container.classList.remove('text-primary');
                container.classList.add('text-danger');
                const copyBtn = container.parentElement.querySelector('[data-profile-url]');
                if (copyBtn) copyBtn.disabled = true;
                if (!container.dataset.expiredToastShown) {
                    container.dataset.expiredToastShown = '1';
                    toast.warning('Profile link expired. Open Profile to regenerate.', 5000);
                }
                return true;
            }
            textEl.textContent = formatProfileLinkCountdown(left);
            return false;
        }

        if (tick()) return;
        const interval = setInterval(function () {
            if (tick()) clearInterval(interval);
        }, 1000);
    });
}

document.addEventListener('DOMContentLoaded', initStaffProfileLinkTimers);

function editStaff(staff) {
    document.getElementById('edit_staff_id').value = staff.id;
    document.getElementById('edit_name').value = staff.name;
    document.getElementById('edit_email').value = staff.email || '';
    document.getElementById('edit_phone').value = staff.phone || '';
    document.getElementById('edit_designation').value = staff.designation || '';
    document.getElementById('edit_department').value = staff.department || '';
    document.getElementById('edit_staff_category').value = staff.staff_category || '';
    document.getElementById('edit_public_bio').value = staff.public_bio || '';
    document.getElementById('edit_display_order').value = staff.display_order || 0;
    document.getElementById('edit_show_on_website').checked = staff.show_on_website == 1;
    document.getElementById('edit_show_on_contact').checked = staff.show_on_contact == 1;
    document.getElementById('edit_is_active').checked = staff.is_active == 1;
    const profileLink = document.getElementById('edit_open_full_profile');
    if (profileLink) {
        profileLink.href = 'staff_profile.php?id=' + encodeURIComponent(staff.id);
    }
    const previewWrap = document.getElementById('edit_photo_preview_wrap');
    const previewImg = document.getElementById('edit_photo_preview');
    if (previewWrap && previewImg) {
        const photo = (staff.profile_photo || '').trim();
        if (photo) {
            previewImg.src = photo.indexOf('http') === 0 ? photo : '<?php echo rtrim(APP_URL, '/'); ?>/' + photo.replace(/^\/+/, '');
            previewWrap.style.display = 'block';
        } else {
            previewImg.src = '';
            previewWrap.style.display = 'none';
        }
    }
    new bootstrap.Modal(document.getElementById('editStaffModal')).show();
}

function facultyAjaxParseJson(response) {
    return response.text().then(function (text) {
        var trimmed = (text || '').replace(/^\uFEFF/, '').trim();
        try {
            return JSON.parse(trimmed);
        } catch (firstError) {
            var start = trimmed.indexOf('{');
            var end = trimmed.lastIndexOf('}');
            if (start >= 0 && end > start) {
                try {
                    return JSON.parse(trimmed.slice(start, end + 1));
                } catch (secondError) {
                    // fall through
                }
            }
            throw new Error('Invalid server response');
        }
    });
}

function deactivateStaff(staffId, staffName) {
    showConfirm({
        title: 'Deactivate Staff Member',
        message: `Deactivate <strong>${staffName}</strong>? They will no longer appear in active staff lists.`,
        type: 'warning',
        confirmText: 'Deactivate',
        cancelText: 'Cancel'
    }).then(confirmed => {
        if (!confirmed) return;

        // Show loading toast
        const loadingToast = toast.loading(`Deactivating ${staffName}...`);

        fetch('<?php echo APP_URL; ?>/admin/faculty_action_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ action: 'deactivate', faculty_id: staffId })
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return facultyAjaxParseJson(r); })
        .then(result => {
            // Remove loading toast
            toast.remove(loadingToast);
            
            if (result.success) {
                toast.deleted(`${staffName} has been deactivated.`);
                const row = document.querySelector('tr[data-faculty-id="' + staffId + '"]');
                if (row) {
                    row.style.transition = 'opacity 0.4s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 400);
                } else {
                    setTimeout(() => location.reload(), 1200);
                }
            } else {
                toast.error('Error: ' + (result.message || 'Could not deactivate staff member'));
            }
        })
        .catch(err => {
            toast.remove(loadingToast);
            toast.error('Request failed: ' + err.message);
        });
    });
}

function deleteStaffPermanent(staffId, staffName) {
    showConfirm({
        title: 'Delete Staff Member',
        message: `Permanently delete <strong>${staffName}</strong>? This cannot be undone.`,
        type: 'danger',
        confirmText: 'Delete',
        cancelText: 'Cancel'
    }).then(confirmed => {
        if (!confirmed) return;

        fetch('<?php echo APP_URL; ?>/admin/faculty_action_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ action: 'delete', faculty_id: staffId })
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return facultyAjaxParseJson(r); })
        .then(result => {
            if (result.success) {
                toast.deleted(`${staffName} has been permanently deleted.`);
                const row = document.querySelector(`tr[data-faculty-id="${staffId}"]`);
                if (row) {
                    row.style.transition = 'opacity 0.4s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 400);
                } else {
                    setTimeout(() => location.reload(), 1200);
                }
            } else {
                toast.error('Error: ' + (result.message || 'Could not delete staff member'));
            }
        })
        .catch(err => toast.error('Request failed: ' + err.message));
    });
}

function resendStaffEmail(staffId, staffName, btn) {
    showConfirm({
        title: 'Send Email',
        message: `Send confirmation email to <strong>${staffName}</strong>?`,
        type: 'info',
        confirmText: 'Send Email',
        cancelText: 'Cancel'
    }).then(confirmed => {
        if (!confirmed) return;

        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        btn.disabled = true;

        // Show loading toast
        const loadingToast = toast.loading(`Sending email to ${staffName}...`);

        fetch('<?php echo APP_URL; ?>/batch_module/admin/resend_faculty_email_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ action: 'resend_email', faculty_id: staffId })
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return facultyAjaxParseJson(r); })
        .then(result => {
            // Remove loading toast
            toast.remove(loadingToast);
            
            if (result.success) {
                btn.innerHTML = '<i class="fas fa-check"></i> Sent!';
                btn.classList.remove('btn-warning');
                btn.classList.add('btn-success');
                toast.success(`Email sent to ${staffName} successfully!`);
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-warning');
                    btn.disabled = false;
                }, 3000);
            } else {
                toast.error('Error: ' + (result.message || 'Unknown error'));
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            toast.remove(loadingToast);
            toast.error('Failed to send email: ' + err.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });
}

// Show success/error from PHP page reload (add/update actions)
<?php if (isset($success_message)): ?>
document.addEventListener('DOMContentLoaded', () => toast.success(<?php echo json_encode($success_message); ?>));
<?php endif; ?>
<?php if (isset($error_message)): ?>
document.addEventListener('DOMContentLoaded', () => toast.error(<?php echo json_encode($error_message); ?>));
<?php endif; ?>

// Enhanced form submission with loading states
document.addEventListener('DOMContentLoaded', function() {
    // Add Staff Form
    const addForm = document.querySelector('#addStaffModal form');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Staff Member...';
            submitBtn.disabled = true;
            
            // Show loading toast
            const loadingToast = toast.loading('Adding staff member...');
            
            // The form will submit normally, but we show the loading state
            setTimeout(() => {
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }, 1000);
        });
    }
    
    // Edit Staff Form
    const editForm = document.querySelector('#editStaffModal form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            
            // Show loading toast
            const loadingToast = toast.loading('Updating staff member...');
            
            // The form will submit normally, but we show the loading state
            setTimeout(() => {
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }, 1000);
        });
    }
});
</script>

</body>
</html>