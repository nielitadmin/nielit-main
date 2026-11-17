<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/theme_loader.php';
require_once __DIR__ . '/../../includes/url_helper.php';
require_once __DIR__ . '/../../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../../includes/admin_assets.php';
require_once __DIR__ . '/../../includes/session_manager.php';
require_once __DIR__ . '/../includes/batch_functions.php';
require_once __DIR__ . '/../../includes/batch_assignment_helper.php';
require_once __DIR__ . '/../../includes/institute_branding.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../../admin/login.php");
    exit();
}

if (!isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id'])) {
    if (!init_admin_session($_SESSION['admin'])) {
        session_unset();
        session_destroy();
        header("Location: ../../admin/login.php");
        exit();
    }
}

refresh_session_permissions();

require_once __DIR__ . '/../../includes/teaching_access.php';
admin_redirect_faculty_from_restricted_page();

$admin_role = $_SESSION['admin_role'] ?? '';
$is_placement_coordinator = ($admin_role === 'placement_coordinator');
$can_see_all_batches = in_array($admin_role, ['master_admin', 'placement_coordinator'], true);

$message = '';
$message_type = 'success';

// Handle batch actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($is_placement_coordinator) {
            $message = 'Placement coordinators can view batches and record placements, but cannot create or edit batches.';
            $message_type = 'danger';
        } else {
        switch ($_POST['action']) {
            case 'create_batch':
                // Get course code for batch code generation
                $course_sql = "SELECT course_code FROM courses WHERE id = ?";
                $stmt = $conn->prepare($course_sql);
                $stmt->bind_param("i", $_POST['course_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $course = $result->fetch_assoc();
                $stmt->close();
                
                $batch_code = generateBatchCode($course['course_code'], $conn);
                
                // Get current admin ID for created_by field
                $admin_id = $_SESSION['admin_id'] ?? null;
                if (!$admin_id && isset($_SESSION['admin'])) {
                    $admin_username = $_SESSION['admin'];
                    $admin_query = "SELECT id FROM admin WHERE username = ?";
                    $admin_stmt = $conn->prepare($admin_query);
                    $admin_stmt->bind_param("s", $admin_username);
                    $admin_stmt->execute();
                    $admin_result = $admin_stmt->get_result();
                    if ($admin_row = $admin_result->fetch_assoc()) {
                        $admin_id = $admin_row['id'];
                        $_SESSION['admin_id'] = $admin_id;
                    }
                }
                
                // Validate and sanitize seats_total
                $seats_total = intval($_POST['seats_total']);
                if ($seats_total < 1) {
                    $message = "Total seats must be at least 1";
                    $message_type = 'danger';
                } else {
                    $data = [
                        'course_id' => $_POST['course_id'],
                        'batch_name' => $_POST['batch_name'],
                        'batch_description' => $_POST['batch_description'] ?? null,
                        'batch_code' => $batch_code,
                        'start_date' => $_POST['start_date'],
                        'end_date' => $_POST['end_date'],
                        'training_fees' => $_POST['training_fees'],
                        'seats_total' => $seats_total,
                        'batch_coordinator' => $_POST['batch_coordinator'],
                        'status' => $_POST['status'],
                        'created_by' => $admin_id
                    ];
                    
                    $result = createBatch($data, $conn);
                    if ($result) {
                        $message = "Batch created successfully! Batch Code: " . $batch_code;
                        $message_type = 'success';
                    } else {
                        $message = "Error creating batch";
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'update_batch':
                $batch_id_upd = (int) ($_POST['batch_id'] ?? 0);
                if ($batch_id_upd <= 0) {
                    $message = 'Invalid batch.';
                    $message_type = 'danger';
                    break;
                }
                if (isBatchLocked($batch_id_upd, $conn)) {
                    $message = 'Cannot update: this batch is locked.';
                    $message_type = 'danger';
                    break;
                }
                $seats_total = (int) ($_POST['seats_total'] ?? 0);
                if ($seats_total < 1) {
                    $message = 'Total seats must be at least 1';
                    $message_type = 'danger';
                } else {
                    $data = [
                        'batch_name' => trim((string) ($_POST['batch_name'] ?? '')),
                        'batch_description' => $_POST['batch_description'] ?? null,
                        'start_date' => $_POST['start_date'],
                        'end_date' => $_POST['end_date'],
                        'training_fees' => $_POST['training_fees'],
                        'seats_total' => $seats_total,
                        'batch_coordinator' => trim((string) ($_POST['batch_coordinator'] ?? '')),
                        'status' => $_POST['status'],
                        'scheme_id' => !empty($_POST['scheme_id']) ? (int) $_POST['scheme_id'] : null,
                        'admission_order_ref' => $_POST['admission_order_ref'] ?? null,
                        'admission_order_date' => $_POST['admission_order_date'] ?? null,
                        'examination_month' => $_POST['examination_month'] ?? null,
                        'class_time' => $_POST['class_time'] ?? '9:00 AM to 1:30 PM',
                        'copy_to_list' => $_POST['copy_to_list'] ?? null,
                        'location' => $_POST['location'] ?? 'NIELIT Bhubaneswar',
                    ];
                    $result = updateBatch($batch_id_upd, $data, $conn);
                    $message = $result ? 'Batch updated successfully.' : 'Error updating batch.';
                    $message_type = $result ? 'success' : 'danger';
                }
                break;
        }
        }
    }
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if ($is_placement_coordinator) {
        $message = 'Placement coordinators cannot delete batches.';
        $message_type = 'danger';
    } else {
    $result = deleteBatch($_GET['delete'], $conn);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'danger';
    }
}

// Handle lock/unlock actions (Master Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lock_action'])) {
    $is_master_admin = ($_SESSION['admin_role'] === 'master_admin');
    $current_admin_id = $_SESSION['admin_id'] ?? null;
    
    if (!$current_admin_id && isset($_SESSION['admin'])) {
        $admin_username = $_SESSION['admin'];
        $admin_query = "SELECT id FROM admin WHERE username = ?";
        $admin_stmt = $conn->prepare($admin_query);
        $admin_stmt->bind_param("s", $admin_username);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        if ($admin_row = $admin_result->fetch_assoc()) {
            $current_admin_id = $admin_row['id'];
            $_SESSION['admin_id'] = $current_admin_id;
        }
    }
    
    if ($is_master_admin && $current_admin_id) {
        $batch_id = $_POST['batch_id'];
        $action = $_POST['lock_action'];
        
        if ($action === 'lock') {
            $result = lockBatch($batch_id, $current_admin_id, $conn);
        } elseif ($action === 'unlock') {
            $result = unlockBatch($batch_id, $current_admin_id, $conn);
        }
        
        if (isset($result)) {
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'danger';
        }
    } else {
        $message = "Access denied. Only Master Admins can lock/unlock batches.";
        $message_type = 'danger';
    }
}

// Resolve role and admin ID FIRST (needed for both courses and batches queries)
$is_master_admin = ($_SESSION['admin_role'] === 'master_admin');
$current_admin_id = $_SESSION['admin_id'] ?? null;

// Get current admin ID from DB if not in session
if (!$current_admin_id && isset($_SESSION['admin'])) {
    $admin_username = $_SESSION['admin'];
    $admin_query = "SELECT id FROM admin WHERE username = ?";
    $admin_stmt = $conn->prepare($admin_query);
    $admin_stmt->bind_param("s", $admin_username);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    if ($admin_row = $admin_result->fetch_assoc()) {
        $current_admin_id = $admin_row['id'];
        $_SESSION['admin_id'] = $current_admin_id;
    }
}

// Get courses for dropdown - filtered by assignment for coordinators only
if ($can_see_all_batches) {
    // Master admin sees all courses
    $courses_sql = "SELECT id, course_name, course_code, course_description FROM courses ORDER BY course_name";
    $courses_result = $conn->query($courses_sql);
} else {
    // Course coordinators only see their assigned courses
    $courses_sql = "SELECT c.id, c.course_name, c.course_code, c.course_description 
                    FROM courses c
                    INNER JOIN admin_course_assignments aca ON c.id = aca.course_id
                    WHERE aca.admin_id = ? AND aca.is_active = 1
                    ORDER BY c.course_name";
    $courses_stmt = $conn->prepare($courses_sql);
    
    if ($courses_stmt === false) {
        // Fallback: show all courses if assignments table doesn't exist
        $courses_result = $conn->query("SELECT id, course_name, course_code, course_description FROM courses ORDER BY course_name");
    } else {
        $courses_stmt->bind_param("i", $current_admin_id);
        $courses_stmt->execute();
        $courses_result = $courses_stmt->get_result();
    }
}
$courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}

// (role and admin ID already resolved above)

$filter_q = trim((string) ($_GET['q'] ?? ''));
$filter_course_id = max(0, (int) ($_GET['course_id'] ?? 0));
$filter_status = trim((string) ($_GET['status'] ?? ''));
$filter_lock = trim((string) ($_GET['lock'] ?? ''));
$batch_status_options = ['Active', 'Completed', 'Cancelled'];
if ($filter_status !== '' && !in_array($filter_status, $batch_status_options, true)) {
    $filter_status = '';
}
if (!in_array($filter_lock, ['', 'locked', 'unlocked'], true)) {
    $filter_lock = '';
}

$lock_col = $conn->query("SHOW COLUMNS FROM batches LIKE 'is_locked'");
$has_lock_column = ($lock_col && $lock_col->num_rows > 0);
$is_locked_select = $has_lock_column
    ? 'CASE WHEN b.is_locked = 1 THEN 1 ELSE 0 END AS is_locked'
    : '0 AS is_locked';

$whereParts = [];
$filterTypes = '';
$filterParams = [];

if (!$can_see_all_batches && $current_admin_id) {
    $whereParts[] = batch_assignment_coordinator_sql($conn, 'b');
    $filterTypes .= 'ii';
    $filterParams[] = $current_admin_id;
    $filterParams[] = $current_admin_id;
}

if ($filter_course_id > 0) {
    $whereParts[] = 'b.course_id = ?';
    $filterTypes .= 'i';
    $filterParams[] = $filter_course_id;
}

if ($filter_status !== '') {
    $whereParts[] = 'b.status = ?';
    $filterTypes .= 's';
    $filterParams[] = $filter_status;
}

if ($has_lock_column) {
    if ($filter_lock === 'locked') {
        $whereParts[] = 'b.is_locked = 1';
    } elseif ($filter_lock === 'unlocked') {
        $whereParts[] = '(b.is_locked = 0 OR b.is_locked IS NULL)';
    }
}

if ($filter_q !== '') {
    $like = '%' . $filter_q . '%';
    $whereParts[] = '(b.batch_name LIKE ? OR b.batch_code LIKE ? OR b.batch_coordinator LIKE ?
        OR c.course_name LIKE ? OR c.course_code LIKE ?)';
    $filterTypes .= 'sssss';
    for ($i = 0; $i < 5; $i++) {
        $filterParams[] = $like;
    }
}

$whereSql = $whereParts === [] ? '1=1' : implode(' AND ', $whereParts);

$batches_sql = "SELECT b.*, c.course_name, c.course_code, c.course_description,
                (SELECT COUNT(*) FROM students WHERE batch_id = b.id) AS enrolled_count,
                {$is_locked_select}
                FROM batches b
                LEFT JOIN courses c ON b.course_id = c.id
                WHERE {$whereSql}
                ORDER BY b.created_at DESC";

if ($filterTypes !== '') {
    $stmt = $conn->prepare($batches_sql);
    if ($stmt) {
        $stmt->bind_param($filterTypes, ...$filterParams);
        $stmt->execute();
        $batches_result = $stmt->get_result();
    } else {
        $batches_result = false;
    }
} else {
    $batches_result = $conn->query($batches_sql);
}

$filters_active = $filter_q !== '' || $filter_course_id > 0 || $filter_status !== '' || $filter_lock !== '';

// Check if query failed (tables don't exist)
if (!$batches_result) {
    die('<div style="font-family: Arial; padding: 40px; text-align: center;">
        <h2 style="color: #e74c3c;">⚠️ Database Tables Not Found!</h2>
        <p style="font-size: 18px; margin: 20px 0;">The batch management tables haven\'t been created yet.</p>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px auto; max-width: 600px; text-align: left;">
            <h3>📋 Quick Fix:</h3>
            <ol style="line-height: 2;">
                <li>Open <strong>phpMyAdmin</strong></li>
                <li>Select your database: <strong>nielit_bhubaneswar</strong></li>
                <li>Click the <strong>"Import"</strong> tab</li>
                <li>Choose file: <strong>batch_module/database_batch_system.sql</strong></li>
                <li>Click <strong>"Go"</strong></li>
                <li>Refresh this page</li>
            </ol>
        </div>
        <p style="color: #7f8c8d;">Database Error: ' . htmlspecialchars($conn->error) . '</p>
        <a href="../../admin/dashboard.php" style="display: inline-block; margin-top: 20px; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a>
    </div>');
}

$batches = [];
repairBatchStudentsJunction($conn);
while ($row = $batches_result->fetch_assoc()) {
    $row['enrolled_count'] = getBatchEnrolledCount((int)$row['id'], $conn);
    $batches[] = $row;
}

$schemes = [];
$schemeTable = $conn->query("SHOW TABLES LIKE 'schemes'");
if ($schemeTable && $schemeTable->num_rows > 0) {
    $schemesRes = $conn->query("SELECT id, scheme_name, scheme_code FROM schemes WHERE status = 'Active' ORDER BY scheme_name");
    if ($schemesRes) {
        while ($sRow = $schemesRes->fetch_assoc()) {
            $schemes[] = $sRow;
        }
    }
}
$location_options = nielit_batch_location_options($conn, 'NIELIT Bhubaneswar');

$batch_edit_data = [];
foreach ($batches as $batch) {
    $bid = (int) ($batch['id'] ?? 0);
    if ($bid <= 0) {
        continue;
    }
    $batch_edit_data[$bid] = [
        'id' => $bid,
        'batch_code' => (string) ($batch['batch_code'] ?? ''),
        'course_name' => (string) ($batch['course_name'] ?? ''),
        'course_code' => (string) ($batch['course_code'] ?? ''),
        'batch_name' => (string) ($batch['batch_name'] ?? ''),
        'batch_description' => (string) ($batch['batch_description'] ?? ''),
        'start_date' => (string) ($batch['start_date'] ?? ''),
        'end_date' => (string) ($batch['end_date'] ?? ''),
        'training_fees' => (string) ($batch['training_fees'] ?? ''),
        'seats_total' => (int) ($batch['seats_total'] ?? 0),
        'seats_filled' => (int) ($batch['enrolled_count'] ?? 0),
        'batch_coordinator' => (string) ($batch['batch_coordinator'] ?? ''),
        'status' => (string) ($batch['status'] ?? 'Active'),
        'scheme_id' => (int) ($batch['scheme_id'] ?? 0),
        'admission_order_ref' => (string) ($batch['admission_order_ref'] ?? ''),
        'admission_order_date' => (string) ($batch['admission_order_date'] ?? ''),
        'examination_month' => (string) ($batch['examination_month'] ?? ''),
        'class_time' => (string) ($batch['class_time'] ?? '9:00 AM to 1:30 PM'),
        'copy_to_list' => (string) ($batch['copy_to_list'] ?? ''),
        'location' => normalize_nielit_batch_location($batch['location'] ?? 'NIELIT Bhubaneswar'),
        'is_locked' => !empty($batch['is_locked']),
    ];
}

$filter_query = $_GET;
unset($filter_query['delete']);
$manage_batches_action = 'manage_batches.php' . ($filter_query !== [] ? '?' . http_build_query($filter_query) : '');

$active_theme = loadActiveTheme($conn);
$reopen_create_modal = $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'create_batch'
    && $message_type === 'danger';
$reopen_edit_batch_id = $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'update_batch'
    && $message_type === 'danger'
    ? (int) ($_POST['batch_id'] ?? 0)
    : 0;
if ($reopen_edit_batch_id > 0 && isset($batch_edit_data[$reopen_edit_batch_id])) {
    $batch_edit_data[$reopen_edit_batch_id] = array_merge($batch_edit_data[$reopen_edit_batch_id], [
        'batch_name' => trim((string) ($_POST['batch_name'] ?? '')),
        'batch_description' => trim((string) ($_POST['batch_description'] ?? '')),
        'start_date' => trim((string) ($_POST['start_date'] ?? '')),
        'end_date' => trim((string) ($_POST['end_date'] ?? '')),
        'training_fees' => trim((string) ($_POST['training_fees'] ?? '')),
        'seats_total' => (int) ($_POST['seats_total'] ?? 0),
        'batch_coordinator' => trim((string) ($_POST['batch_coordinator'] ?? '')),
        'status' => trim((string) ($_POST['status'] ?? 'Active')),
        'scheme_id' => (int) ($_POST['scheme_id'] ?? 0),
        'admission_order_ref' => trim((string) ($_POST['admission_order_ref'] ?? '')),
        'admission_order_date' => trim((string) ($_POST['admission_order_date'] ?? '')),
        'examination_month' => trim((string) ($_POST['examination_month'] ?? '')),
        'class_time' => trim((string) ($_POST['class_time'] ?? '9:00 AM to 1:30 PM')),
        'copy_to_list' => trim((string) ($_POST['copy_to_list'] ?? '')),
        'location' => trim((string) ($_POST['location'] ?? 'NIELIT Bhubaneswar')),
    ]);
}
$create_form_defaults = [
    'course_id' => $reopen_create_modal ? (int) ($_POST['course_id'] ?? 0) : ($filter_course_id > 0 ? $filter_course_id : 0),
    'batch_name' => $reopen_create_modal ? trim((string) ($_POST['batch_name'] ?? '')) : '',
    'batch_description' => $reopen_create_modal ? trim((string) ($_POST['batch_description'] ?? '')) : '',
    'start_date' => $reopen_create_modal ? trim((string) ($_POST['start_date'] ?? '')) : '',
    'end_date' => $reopen_create_modal ? trim((string) ($_POST['end_date'] ?? '')) : '',
    'training_fees' => $reopen_create_modal ? trim((string) ($_POST['training_fees'] ?? '')) : '',
    'seats_total' => $reopen_create_modal ? trim((string) ($_POST['seats_total'] ?? '')) : '30',
    'batch_coordinator' => $reopen_create_modal ? trim((string) ($_POST['batch_coordinator'] ?? '')) : '',
    'status' => $reopen_create_modal ? trim((string) ($_POST['status'] ?? 'Active')) : 'Active',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Batches - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        /* Prevent course dropdown from painting as a clipped white box inside the card */
        .content-card.manage-batches-card {
            overflow: visible;
        }
        .manage-batches-card .card-body {
            overflow: visible;
            padding: 1.5rem;
        }
        select[name="course_id"] {
            width: 100%;
        }
        select[name="course_id"] option,
        select[name="status"] option {
            color: #0f172a !important;
            background-color: #ffffff !important;
            white-space: normal;
        }
        .batch-filter-bar {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .batch-filter-bar .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        .batch-filter-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .batch-list-card-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .batch-list-card-header .card-title {
            margin: 0;
        }
        #createBatchModal .modal-header {
            background: linear-gradient(135deg, #0c2340 0%, #123a66 100%);
            color: #fff;
            border-bottom: none;
        }
        #createBatchModal .modal-header .btn-close {
            filter: invert(1);
        }
        #createBatchModal .modal-title i {
            color: #fbbf24;
            margin-right: 0.35rem;
        }
        #createBatchModal .modal-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        #createBatchModal select option,
        #editBatchModal select option {
            color: #0f172a;
            background: #fff;
        }
        #editBatchModal .modal-header {
            background: linear-gradient(135deg, #0c4a6e 0%, #0369a1 100%);
            color: #fff;
            border-bottom: none;
        }
        #editBatchModal .modal-header .btn-close {
            filter: invert(1);
        }
        #editBatchModal .modal-title i {
            color: #7dd3fc;
            margin-right: 0.35rem;
        }
        #editBatchModal .modal-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        #editBatchModal .edit-batch-meta {
            background: #f1f5f9;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">

<div class="admin-wrapper">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../../admin/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-layer-group"></i> 
                    <?php if ($is_placement_coordinator): ?>
                        Batches
                    <?php elseif ($is_master_admin): ?>
                        Batch Management
                    <?php else: ?>
                        My Batches
                    <?php endif; ?>
                </h4>
                <small>
                    <?php if ($is_placement_coordinator): ?>
                        View all batches and record student placements
                    <?php elseif ($is_master_admin): ?>
                        Create and manage all course batches
                    <?php else: ?>
                        Create and manage your course batches
                    <?php endif; ?>
                </small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars(get_role_display_name()); ?></span>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="admin-main">
            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Role-based Information Banner -->
            <?php if ($is_placement_coordinator): ?>
                <div class="alert alert-info">
                    <i class="fas fa-briefcase"></i>
                    <strong>Placement Coordinator:</strong> Open a batch to record student placement details (company, role, package, location).
                </div>
            <?php elseif (!$is_master_admin): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Course Coordinator View:</strong> You can only see and manage batches that you created. 
                    The course dropdown shows only your assigned courses. Master Admins can see and manage all batches.
                </div>
            <?php endif; ?>

            <!-- Existing Batches -->
            <div class="content-card">
                <div class="card-header batch-list-card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list"></i>
                        <?php if ($can_see_all_batches): ?>
                            All Batches
                        <?php else: ?>
                            My Batches
                        <?php endif; ?>
                        <span class="badge badge-primary"><?php echo count($batches); ?></span>
                        <?php if ($filters_active): ?>
                            <span class="badge badge-secondary">Filtered</span>
                        <?php endif; ?>
                    </h5>
                    <?php if (!$is_placement_coordinator): ?>
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createBatchModal">
                            <i class="fas fa-plus"></i> New Batch
                        </button>
                    <?php endif; ?>
                </div>

                <div class="batch-filter-bar">
                    <form method="get" action="" class="row g-2 align-items-end">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label" for="batch_filter_q">Search</label>
                            <input type="search" class="form-control form-control-sm" name="q" id="batch_filter_q"
                                   value="<?php echo htmlspecialchars($filter_q, ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="Batch name, code, course, coordinator…">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label" for="batch_filter_course">Course</label>
                            <select class="form-control form-control-sm" name="course_id" id="batch_filter_course">
                                <option value="0">All courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int) $course['id']; ?>"
                                        <?php echo (int) $course['id'] === $filter_course_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name'] . (!empty($course['course_code']) ? ' (' . $course['course_code'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label" for="batch_filter_status">Batch status</label>
                            <select class="form-control form-control-sm" name="status" id="batch_filter_status">
                                <option value="">All statuses</option>
                                <?php foreach ($batch_status_options as $st): ?>
                                    <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo $filter_status === $st ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($st); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label" for="batch_filter_lock">Lock</label>
                            <select class="form-control form-control-sm" name="lock" id="batch_filter_lock">
                                <option value="" <?php echo $filter_lock === '' ? 'selected' : ''; ?>>All</option>
                                <option value="locked" <?php echo $filter_lock === 'locked' ? 'selected' : ''; ?>>Locked only</option>
                                <option value="unlocked" <?php echo $filter_lock === 'unlocked' ? 'selected' : ''; ?>>Unlocked only</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4 batch-filter-actions">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            <?php if ($filters_active): ?>
                                <a href="manage_batches.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <?php if (!empty($batches)): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:70px;">Sl No.</th>
                                    <th>Batch Code</th>
                                    <th>Batch Name</th>
                                    <th>Course</th>
                                    <th>Duration</th>
                                    <th>Seats</th>
                                    <th>Fees</th>
                                    <th>Status</th>
                                    <th>Lock Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batches as $index => $batch): ?>
                                    <tr>
                                        <td class="text-center"><?php echo (int) $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($batch['batch_code']); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($batch['batch_name']); ?>
                                            <?php if (!empty($batch['batch_description'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($batch['batch_description']); ?></small>
                                            <?php endif; ?>
                                            <?php if ($batch['is_locked']): ?>
                                                <br><small class="text-muted"><i class="fas fa-lock"></i> Locked</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            echo htmlspecialchars($batch['course_name']); 
                                            if (!empty($batch['course_description'])) {
                                                echo '<br><small class="text-muted">(' . htmlspecialchars($batch['course_description']) . ')</small>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo date('d M Y', strtotime($batch['start_date'])); ?><br>
                                            <small class="text-muted">to <?php echo date('d M Y', strtotime($batch['end_date'])); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $is_batch_full = $batch['enrolled_count'] >= $batch['seats_total'];
                                            $is_batch_almost_full = $batch['enrolled_count'] >= ($batch['seats_total'] * 0.9);
                                            ?>
                                            <span class="badge <?php echo $is_batch_full ? 'badge-danger' : ($is_batch_almost_full ? 'badge-warning' : 'badge-info'); ?>">
                                                <?php echo $batch['enrolled_count']; ?> / <?php echo $batch['seats_total']; ?>
                                            </span>
                                            <?php if ($is_batch_full): ?>
                                                <br><span class="badge badge-danger" style="margin-top: 4px;">
                                                    <i class="fas fa-ban"></i> FULL
                                                </span>
                                            <?php elseif ($is_batch_almost_full): ?>
                                                <br><span class="badge badge-warning" style="margin-top: 4px;">
                                                    <i class="fas fa-exclamation-triangle"></i> Almost Full
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>₹<?php echo number_format($batch['training_fees'], 2); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $batch['status'] === 'Active' ? 'success' : 
                                                    ($batch['status'] === 'Completed' ? 'secondary' : 'danger'); 
                                            ?>">
                                                <?php echo $batch['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($batch['is_locked']): ?>
                                                <span class="badge badge-danger">
                                                    <i class="fas fa-lock"></i> LOCKED
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-unlock"></i> UNLOCKED
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="batch_details.php?id=<?php echo $batch['id']; ?>" class="btn btn-primary btn-sm" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($batch['is_locked']): ?>
                                                <!-- Locked batch actions -->
                                                <?php if ($is_master_admin): ?>
                                                    <!-- Master Admin can unlock -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="batch_id" value="<?php echo $batch['id']; ?>">
                                                        <input type="hidden" name="lock_action" value="unlock">
                                                        <button type="submit" class="btn btn-success btn-sm" title="Unlock Batch" onclick="return confirm('Are you sure you want to unlock this batch? This will allow modifications.')">
                                                            <i class="fas fa-unlock"></i>
                                                        </button>
                                                    </form>
                                                    <button class="btn btn-secondary btn-sm" disabled title="Cannot edit locked batch">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-secondary btn-sm" disabled title="Cannot delete locked batch">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Course Coordinator cannot unlock -->
                                                    <button class="btn btn-secondary btn-sm" disabled title="Batch is locked - only Master Admin can unlock">
                                                        <i class="fas fa-lock"></i>
                                                    </button>
                                                    <button class="btn btn-secondary btn-sm" disabled title="Cannot edit locked batch">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-secondary btn-sm" disabled title="Cannot delete locked batch">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- Unlocked batch actions -->
                                                <?php if ($is_master_admin): ?>
                                                    <!-- Master Admin can lock -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="batch_id" value="<?php echo $batch['id']; ?>">
                                                        <input type="hidden" name="lock_action" value="lock">
                                                        <button type="submit" class="btn btn-warning btn-sm" title="Lock Batch" onclick="return confirm('Are you sure you want to lock this batch? This will prevent all modifications.')">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if (!$is_placement_coordinator): ?>
                                                <button type="button"
                                                        class="btn btn-info btn-sm edit-batch-btn"
                                                        title="Edit batch"
                                                        data-batch-id="<?php echo (int) $batch['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="javascript:void(0);" 
                                                   class="btn btn-danger btn-sm delete-batch-btn" 
                                                   title="Delete Batch"
                                                   data-batch-id="<?php echo $batch['id']; ?>"
                                                   data-batch-name="<?php echo htmlspecialchars($batch['batch_name']); ?>"
                                                   data-batch-code="<?php echo htmlspecialchars($batch['batch_code']); ?>"
                                                   data-url="?delete=<?php echo $batch['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.3;"></i>
                        <?php if ($filters_active): ?>
                            <p style="margin: 0; font-size: 16px;">No batches match your filters.</p>
                            <p style="margin: 8px 0 0 0; font-size: 14px; opacity: 0.7;">
                                <a href="manage_batches.php">Clear filters</a> or try another search.
                            </p>
                        <?php elseif ($is_placement_coordinator): ?>
                            <p style="margin: 0; font-size: 16px;">No batches found yet.</p>
                        <?php elseif ($is_master_admin): ?>
                            <p style="margin: 0; font-size: 16px;">No batches found.</p>
                            <?php if (!$is_placement_coordinator): ?>
                                <button type="button" class="btn btn-success btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#createBatchModal">
                                    <i class="fas fa-plus"></i> Create your first batch
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <p style="margin: 0; font-size: 16px;">You haven't created any batches yet.</p>
                            <button type="button" class="btn btn-success btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#createBatchModal">
                                <i class="fas fa-plus"></i> Create your first batch
                            </button>
                            <p style="margin: 8px 0 0 0; font-size: 14px; opacity: 0.7;">You can only see batches that you created.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php if (!$is_placement_coordinator): ?>
<div class="modal fade" id="createBatchModal" tabindex="-1" aria-labelledby="createBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?php echo htmlspecialchars($manage_batches_action, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="create_batch">
                <div class="modal-header">
                    <h5 class="modal-title" id="createBatchModalLabel">
                        <i class="fas fa-plus-circle"></i> Create New Batch
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Add a batch for your course. Batch code is generated automatically after you save.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-select" name="course_id" required>
                                <option value="">Select course</option>
                                <?php foreach ($courses as $course): ?>
                                    <?php
                                    $cname = trim((string) ($course['course_name'] ?? ''));
                                    $cdesc = trim((string) ($course['course_description'] ?? ''));
                                    $ccode = trim((string) ($course['course_code'] ?? ''));
                                    $fullLabel = $cname;
                                    if ($cdesc !== '') {
                                        $fullLabel .= ' (' . $cdesc . ')';
                                    } elseif ($ccode !== '') {
                                        $fullLabel .= ' (' . $ccode . ')';
                                    }
                                    $shortLabel = $fullLabel;
                                    if (function_exists('mb_strlen') && mb_strlen($shortLabel) > 90) {
                                        $shortLabel = mb_substr($shortLabel, 0, 87) . '…';
                                    } elseif (strlen($shortLabel) > 90) {
                                        $shortLabel = substr($shortLabel, 0, 87) . '…';
                                    }
                                    $sel = (int) $course['id'] === (int) $create_form_defaults['course_id'];
                                    ?>
                                    <option value="<?php echo (int) $course['id']; ?>" <?php echo $sel ? 'selected' : ''; ?>
                                            title="<?php echo htmlspecialchars($fullLabel, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($shortLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$is_master_admin && empty($courses)): ?>
                                <div class="form-text text-danger">No courses assigned — contact Master Admin.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Batch name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="batch_name" required
                                   placeholder="e.g., DBC Batch 25"
                                   value="<?php echo htmlspecialchars($create_form_defaults['batch_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea class="form-control" name="batch_description" rows="2"
                                      placeholder="Evening batch, special intake, etc."><?php echo htmlspecialchars($create_form_defaults['batch_description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" required
                                   value="<?php echo htmlspecialchars($create_form_defaults['start_date'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="end_date" required
                                   value="<?php echo htmlspecialchars($create_form_defaults['end_date'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Training fees (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="training_fees"
                                   placeholder="15000.00" required
                                   value="<?php echo htmlspecialchars($create_form_defaults['training_fees'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total seats <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="seats_total" min="1" step="1" required
                                   value="<?php echo htmlspecialchars($create_form_defaults['seats_total'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Batch coordinator <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="batch_coordinator" required
                                   placeholder="Dr. Kumar Singh"
                                   value="<?php echo htmlspecialchars($create_form_defaults['batch_coordinator'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" required>
                                <?php foreach ($batch_status_options as $st): ?>
                                    <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo $create_form_defaults['status'] === $st ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($st); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Create batch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editBatchModal" tabindex="-1" aria-labelledby="editBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?php echo htmlspecialchars($manage_batches_action, ENT_QUOTES, 'UTF-8'); ?>" id="editBatchForm">
                <input type="hidden" name="action" value="update_batch">
                <input type="hidden" name="batch_id" id="edit_batch_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBatchModalLabel">
                        <i class="fas fa-edit"></i> Edit Batch
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="edit-batch-meta mb-3">
                        <strong>Course:</strong> <span id="edit_meta_course">—</span>
                        &nbsp;·&nbsp;
                        <strong>Batch code:</strong> <code id="edit_meta_code">—</code>
                        &nbsp;·&nbsp;
                        <strong>Enrolled:</strong> <span id="edit_meta_seats">0</span>
                    </div>
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="edit-tab-general-btn" data-bs-toggle="tab"
                                    data-bs-target="#edit-tab-general" type="button" role="tab">General</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="edit-tab-admission-btn" data-bs-toggle="tab"
                                    data-bs-target="#edit-tab-admission" type="button" role="tab">Admission order</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="edit-tab-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Batch name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="batch_name" id="edit_batch_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Batch coordinator <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="batch_coordinator" id="edit_batch_coordinator" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="batch_description" id="edit_batch_description" rows="2"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Start date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="start_date" id="edit_start_date" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">End date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="end_date" id="edit_end_date" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Training fees (₹) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" name="training_fees" id="edit_training_fees" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Total seats <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="seats_total" id="edit_seats_total" min="1" required>
                                    <div class="form-text">Filled: <span id="edit_seats_filled_hint">0</span></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" name="status" id="edit_status" required>
                                        <?php foreach ($batch_status_options as $st): ?>
                                            <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($st); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Scheme / project</label>
                                    <select class="form-select" name="scheme_id" id="edit_scheme_id">
                                        <option value="">— No scheme —</option>
                                        <?php foreach ($schemes as $scheme): ?>
                                            <option value="<?php echo (int) $scheme['id']; ?>">
                                                <?php echo htmlspecialchars($scheme['scheme_name'] . ' (' . $scheme['scheme_code'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="edit-tab-admission" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Admission order ref.</label>
                                    <input type="text" class="form-control" name="admission_order_ref" id="edit_admission_order_ref"
                                           placeholder="NIELIT/BBSR/AO/2026/001">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Order date</label>
                                    <input type="date" class="form-control" name="admission_order_date" id="edit_admission_order_date">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Location</label>
                                    <select class="form-select" name="location" id="edit_location">
                                        <?php foreach ($location_options as $loc): ?>
                                            <option value="<?php echo htmlspecialchars($loc, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($loc); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Examination month</label>
                                    <input type="text" class="form-control" name="examination_month" id="edit_examination_month"
                                           placeholder="March 2026">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Class time</label>
                                    <input type="text" class="form-control" name="class_time" id="edit_class_time"
                                           placeholder="9:00 AM to 1:30 PM">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Copy to (recipients)</label>
                                    <textarea class="form-control" name="copy_to_list" id="edit_copy_to_list" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <a href="#" class="btn btn-outline-primary btn-sm" id="edit_view_details_link">
                        <i class="fas fa-eye"></i> Batch details
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script type="application/json" id="batch-edit-json"><?php echo json_encode($batch_edit_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
// Show toast notification if there's a session message
<?php if (!empty($message)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const messageType = '<?php echo $message_type; ?>';
        const message = '<?php echo addslashes($message); ?>';
        
        // Map message types to toast types
        const toastType = messageType === 'danger' ? 'error' : messageType;
        
        toast[toastType](message);
    });
<?php endif; ?>

document.addEventListener('DOMContentLoaded', function() {
    const batchEditRaw = document.getElementById('batch-edit-json');
    const batchEditData = batchEditRaw ? JSON.parse(batchEditRaw.textContent || '{}') : {};

    function setVal(id, val) {
        const el = document.getElementById(id);
        if (el) {
            el.value = val == null ? '' : String(val);
        }
    }

    function openEditBatchModal(batchId) {
        const b = batchEditData[String(batchId)] || batchEditData[batchId];
        if (!b || !b.id) {
            showToast('Could not load batch data.', 'error');
            return;
        }
        setVal('edit_batch_id', b.id);
        const courseLabel = (b.course_name || '') + (b.course_code ? ' (' + b.course_code + ')' : '');
        const metaCourse = document.getElementById('edit_meta_course');
        const metaCode = document.getElementById('edit_meta_code');
        const metaSeats = document.getElementById('edit_meta_seats');
        if (metaCourse) metaCourse.textContent = courseLabel || '—';
        if (metaCode) metaCode.textContent = b.batch_code || '—';
        if (metaSeats) metaSeats.textContent = (b.seats_filled || 0) + ' / ' + (b.seats_total || 0);
        setVal('edit_batch_name', b.batch_name);
        setVal('edit_batch_coordinator', b.batch_coordinator);
        setVal('edit_batch_description', b.batch_description);
        setVal('edit_start_date', b.start_date);
        setVal('edit_end_date', b.end_date);
        setVal('edit_training_fees', b.training_fees);
        setVal('edit_seats_total', b.seats_total);
        const hint = document.getElementById('edit_seats_filled_hint');
        if (hint) hint.textContent = String(b.seats_filled || 0);
        setVal('edit_status', b.status);
        setVal('edit_scheme_id', b.scheme_id ? String(b.scheme_id) : '');
        setVal('edit_admission_order_ref', b.admission_order_ref);
        setVal('edit_admission_order_date', b.admission_order_date);
        setVal('edit_location', b.location);
        setVal('edit_examination_month', b.examination_month);
        setVal('edit_class_time', b.class_time || '9:00 AM to 1:30 PM');
        setVal('edit_copy_to_list', b.copy_to_list);
        const detailsLink = document.getElementById('edit_view_details_link');
        if (detailsLink) detailsLink.href = 'batch_details.php?id=' + encodeURIComponent(b.id);
        const tabBtn = document.getElementById('edit-tab-general-btn');
        if (tabBtn && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        }
        const modalEl = document.getElementById('editBatchModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            (new bootstrap.Modal(modalEl)).show();
        }
    }

    document.querySelectorAll('.edit-batch-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openEditBatchModal(this.getAttribute('data-batch-id'));
        });
    });

    <?php if ($reopen_create_modal && !$is_placement_coordinator): ?>
    const createModalEl = document.getElementById('createBatchModal');
    if (createModalEl && typeof bootstrap !== 'undefined') {
        (new bootstrap.Modal(createModalEl)).show();
    }
    <?php endif; ?>
    <?php if ($reopen_edit_batch_id > 0 && !$is_placement_coordinator): ?>
    openEditBatchModal(<?php echo (int) $reopen_edit_batch_id; ?>);
    <?php endif; ?>

    const deleteButtons = document.querySelectorAll('.delete-batch-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            const batchName = this.getAttribute('data-batch-name');
            const batchCode = this.getAttribute('data-batch-code');
            const batchId = this.getAttribute('data-batch-id');
            const url = this.getAttribute('data-url');
            
            const confirmed = await showConfirm({
                title: 'Delete Batch',
                message: `Are you sure you want to delete batch <strong>${batchName}</strong> (${batchCode})? This action cannot be undone and will affect all students assigned to this batch.`,
                confirmText: 'Delete',
                cancelText: 'Cancel',
                type: 'danger'
            });
            
            if (confirmed) {
                // Show loading toast
                const loadingToast = toast.loading('Deleting batch...');
                
                // Redirect to delete URL
                window.location.href = url;
            }
        });
    });
});
</script>

</body>
</html>
