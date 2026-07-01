<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/session_manager.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Initialize session if role is missing (backward compatibility)
if (!isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id'])) {
    if (!init_admin_session($_SESSION['admin'])) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
}

// Always refresh role from DB
refresh_session_permissions();

// Load active theme (matches dashboard.php pattern)
$active_theme = loadActiveTheme($conn);
$theme_logo   = getThemeLogo($active_theme);

// Role flags
$admin_role            = $_SESSION['admin_role'] ?? '';
$is_course_coordinator = ($admin_role === 'course_coordinator');
$is_front_office       = ($admin_role === 'front_office_desk');
$is_master_admin       = ($admin_role === 'master_admin');
$is_nsqf_manager       = ($admin_role === 'nsqf_course_manager');

// Require course_id
if (!isset($_GET['course_id'])) {
    $_SESSION['message']      = "Course ID is missing.";
    $_SESSION['message_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}

$course_id = (int)$_GET['course_id'];
$admin_id  = $_SESSION['admin_id'] ?? null;

// ─── HANDLE: Delete batch ─────────────────────────────────────────────────────
if (isset($_GET['delete_batch'])) {
    $batch_id = (int)$_GET['delete_batch'];
    $stmt = $conn->prepare("DELETE FROM batches WHERE id = ?");
    $stmt->bind_param("i", $batch_id);
    if ($stmt->execute()) {
        $_SESSION['message']      = "Batch deleted successfully.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message']      = "Error deleting batch: " . $stmt->error;
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    header("Location: manage_batches.php?course_id=" . $course_id);
    exit();
}

// ─── HANDLE: Add batch ────────────────────────────────────────────────────────
if (isset($_POST['add_batch'])) {
    $batch_name        = trim($_POST['batch_name']);
    $batch_code        = strtoupper(trim($_POST['batch_code'] ?? ''));
    $start_date        = $_POST['start_date'];
    $end_date          = $_POST['end_date'];
    $training_fees     = $_POST['training_fees'];
    $seats_available   = (int)$_POST['seats_available'];
    $batch_coordinator = trim($_POST['batch_coordinator']);
    $status            = $_POST['status'] ?? 'Active';

    // Check if created_by column exists
    $col_check        = $conn->query("SHOW COLUMNS FROM batches LIKE 'created_by'");
    $has_created_by   = ($col_check && $col_check->num_rows > 0);
    $col_check2       = $conn->query("SHOW COLUMNS FROM batches LIKE 'batch_code'");
    $has_batch_code   = ($col_check2 && $col_check2->num_rows > 0);
    $col_check3       = $conn->query("SHOW COLUMNS FROM batches LIKE 'status'");
    $has_status_col   = ($col_check3 && $col_check3->num_rows > 0);

    if ($has_created_by && $has_batch_code && $has_status_col) {
        $sql  = "INSERT INTO batches (course_id, batch_name, batch_code, start_date, end_date, training_fees, seats_available, batch_coordinator, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssdissi",
            $course_id, $batch_name, $batch_code, $start_date, $end_date,
            $training_fees, $seats_available, $batch_coordinator, $status, $admin_id);
    } elseif ($has_batch_code && $has_status_col) {
        $sql  = "INSERT INTO batches (course_id, batch_name, batch_code, start_date, end_date, training_fees, seats_available, batch_coordinator, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssdiis",
            $course_id, $batch_name, $batch_code, $start_date, $end_date,
            $training_fees, $seats_available, $batch_coordinator, $status);
    } else {
        // Fallback: minimal columns
        $sql  = "INSERT INTO batches (course_id, batch_name, start_date, end_date, training_fees, seats_available, batch_coordinator)
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssis",
            $course_id, $batch_name, $start_date, $end_date,
            $training_fees, $seats_available, $batch_coordinator);
    }

    if ($stmt->execute()) {
        $_SESSION['message']      = "Batch added successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message']      = "Error adding batch: " . $stmt->error;
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    header("Location: manage_batches.php?course_id=" . $course_id);
    exit();
}

// ─── HANDLE: Update batch status ─────────────────────────────────────────────
if (isset($_POST['update_batch_status'])) {
    $batch_id      = (int)$_POST['batch_id'];
    $update_status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE batches SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $update_status, $batch_id);
    if ($stmt->execute()) {
        $_SESSION['message']      = "Batch status updated successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message']      = "Error updating batch: " . $stmt->error;
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    header("Location: manage_batches.php?course_id=" . $course_id);
    exit();
}

// ─── Fetch course details ─────────────────────────────────────────────────────
$course_name        = '';
$course_description = '';
$stmt = $conn->prepare("SELECT course_name, course_description FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$stmt->bind_result($course_name, $course_description);
$stmt->fetch();
$stmt->close();

if (empty($course_name)) {
    $_SESSION['message']      = "Course not found.";
    $_SESSION['message_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}

// ─── Fetch batches ────────────────────────────────────────────────────────────
$batches = [];

// Check if created_by column exists (for filtering coordinator's own batches)
$col_check      = $conn->query("SHOW COLUMNS FROM batches LIKE 'created_by'");
$has_created_by = ($col_check && $col_check->num_rows > 0);

if ($is_course_coordinator && $admin_id && $has_created_by) {
    $stmt = $conn->prepare("SELECT b.*, 
        (SELECT COUNT(*) FROM students WHERE students.batch_id = b.id) as student_count
        FROM batches b WHERE b.course_id = ? AND b.created_by = ? ORDER BY b.id DESC");
    $stmt->bind_param("ii", $course_id, $admin_id);
} else {
    $stmt = $conn->prepare("SELECT b.*,
        (SELECT COUNT(*) FROM students WHERE students.batch_id = b.id) as student_count
        FROM batches b WHERE b.course_id = ? ORDER BY b.id DESC");
    $stmt->bind_param("i", $course_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $batches[] = $row;
}
$stmt->close();

// Check which optional columns exist in batches table
$col_batch_code = $conn->query("SHOW COLUMNS FROM batches LIKE 'batch_code'");
$has_batch_code = ($col_batch_code && $col_batch_code->num_rows > 0);
$col_status     = $conn->query("SHOW COLUMNS FROM batches LIKE 'status'");
$has_status_col = ($col_status && $col_status->num_rows > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Batches - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar (same dynamic include as dashboard.php) -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-layer-group"></i> Manage Batches</h4>
                <small>
                    Course: <strong><?php echo htmlspecialchars($course_name); ?></strong>
                    <?php if (!empty($course_description)): ?>
                        (<?php echo htmlspecialchars($course_description); ?>)
                    <?php endif; ?>
                </small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role">
                            <?php
                            switch ($admin_role) {
                                case 'master_admin':       echo 'Master Administrator'; break;
                                case 'front_office_desk':  echo 'Front Office Desk';    break;
                                case 'nsqf_course_manager':echo 'NSQF Course Manager';  break;
                                default:                   echo 'Course Coordinator';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="admin-main">

            <!-- Toast notifications -->
            <?php if (isset($_SESSION['message'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const type = '<?php echo addslashes($_SESSION['message_type'] ?? 'success'); ?>';
                    const msg  = '<?php echo addslashes($_SESSION['message']); ?>';
                    const toastType = type === 'danger' ? 'error' : type;
                    if (typeof toast !== 'undefined') toast[toastType](msg);
                });
            </script>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <!-- Coordinator info banner -->
            <?php if ($is_course_coordinator): ?>
            <div class="content-card" style="margin-bottom:1.5rem;">
                <div class="card-body" style="background:linear-gradient(135deg,#e3f2fd 0%,#f3e5f5 100%);border-left:4px solid #2196f3;padding:1rem 1.5rem;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="color:#1976d2;font-size:1.5rem;"><i class="fas fa-info-circle"></i></div>
                        <div>
                            <h6 style="margin:0;color:#1565c0;font-weight:600;">Course Coordinator View</h6>
                            <p style="margin:4px 0 0;color:#424242;font-size:14px;">
                                <?php if ($has_created_by): ?>
                                    You are viewing batches <strong>you created</strong> for this course.
                                    New batches you add will be automatically linked to your account.
                                <?php else: ?>
                                    You are viewing all batches for this course.
                                    <em>(Run the batch ownership migration to enable coordinator-specific batch management.)</em>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Existing Batches -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list"></i> Existing Batches
                        <small style="color:#64748b;font-weight:normal;">
                            (<?php echo count($batches); ?> batch<?php echo count($batches) !== 1 ? 'es' : ''; ?>)
                        </small>
                    </h5>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <?php if (!empty($batches)): ?>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Sl.</th>
                                <th>Batch Name</th>
                                <?php if ($has_batch_code): ?><th>Batch Code</th><?php endif; ?>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Fees</th>
                                <th>Seats</th>
                                <th>Students</th>
                                <th>Coordinator</th>
                                <?php if ($has_status_col): ?><th>Status</th><?php endif; ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batches as $i => $batch): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($batch['batch_name']); ?></strong></td>
                                <?php if ($has_batch_code): ?>
                                <td>
                                    <?php if (!empty($batch['batch_code'])): ?>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($batch['batch_code']); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td><?php echo date('d M Y', strtotime($batch['start_date'])); ?></td>
                                <td><?php echo date('d M Y', strtotime($batch['end_date']));   ?></td>
                                <td>₹<?php echo is_numeric($batch['training_fees']) ? number_format($batch['training_fees']) : htmlspecialchars($batch['training_fees']); ?></td>
                                <td><span class="badge badge-info"><?php echo (int)$batch['seats_available']; ?> seats</span></td>
                                <td>
                                    <?php
                                    $sc = (int)($batch['student_count'] ?? 0);
                                    $bc = $sc > 0 ? 'badge-success' : 'badge-secondary';
                                    ?>
                                    <a href="students.php?filter_batch=<?php echo $batch['id']; ?>"
                                       class="badge <?php echo $bc; ?>"
                                       style="text-decoration:none;font-size:13px;padding:5px 10px;">
                                        <i class="fas fa-users"></i> <?php echo $sc; ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($batch['batch_coordinator']); ?></td>
                                <?php if ($has_status_col): ?>
                                <td>
                                    <?php
                                    $bs  = $batch['status'] ?? 'Active';
                                    $bsc = ($bs === 'Active') ? 'badge-success' : 'badge-secondary';
                                    ?>
                                    <span class="badge <?php echo $bsc; ?>"><?php echo htmlspecialchars($bs); ?></span>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <!-- Edit status (quick toggle) -->
                                    <?php if ($has_status_col): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="batch_id" value="<?php echo $batch['id']; ?>">
                                        <input type="hidden" name="update_batch_status" value="1">
                                        <select name="status" class="form-select"
                                                style="display:inline-block;width:auto;padding:4px 8px;font-size:12px;"
                                                onchange="this.form.submit()">
                                            <option value="Active"   <?php if (($batch['status'] ?? '') === 'Active')   echo 'selected'; ?>>Active</option>
                                            <option value="Inactive" <?php if (($batch['status'] ?? '') === 'Inactive') echo 'selected'; ?>>Inactive</option>
                                            <option value="Completed"<?php if (($batch['status'] ?? '') === 'Completed')echo 'selected'; ?>>Completed</option>
                                        </select>
                                    </form>
                                    <?php endif; ?>

                                    <a href="javascript:void(0);"
                                       class="btn btn-danger btn-sm delete-batch-btn"
                                       data-batch-id="<?php echo $batch['id']; ?>"
                                       data-batch-name="<?php echo htmlspecialchars($batch['batch_name']); ?>"
                                       data-url="manage_batches.php?course_id=<?php echo $course_id; ?>&delete_batch=<?php echo $batch['id']; ?>"
                                       title="Delete Batch">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="text-align:center;padding:3rem;color:#64748b;">
                    <i class="fas fa-inbox" style="font-size:3.5rem;margin-bottom:1rem;display:block;opacity:0.3;"></i>
                    <p style="margin:0;font-size:16px;">No batches found. Add a new batch below.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Add New Batch Form -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-plus-circle"></i> Add New Batch
                    </h5>
                </div>

                <form method="POST" action="manage_batches.php?course_id=<?php echo $course_id; ?>" style="padding:0.5rem 0;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                        <div class="form-group">
                            <label class="form-label">Batch Name *</label>
                            <input type="text" class="form-control" name="batch_name" required
                                   placeholder="e.g., Batch A - Jan 2026">
                        </div>

                        <?php if ($has_batch_code): ?>
                        <div class="form-group">
                            <label class="form-label">Batch Code *</label>
                            <input type="text" class="form-control" name="batch_code" required
                                   style="text-transform:uppercase;" placeholder="e.g., PPI-B1-2026"
                                   maxlength="20">
                            <small style="color:#64748b;">Unique identifier for this batch</small>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Batch Coordinator *</label>
                            <input type="text" class="form-control" name="batch_coordinator" required
                                   placeholder="Coordinator name">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Training Fees *</label>
                            <input type="text" class="form-control" name="training_fees"
                                   placeholder="e.g., 15000" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Seats Available *</label>
                            <input type="number" class="form-control" name="seats_available"
                                   placeholder="e.g., 30" min="1" required>
                        </div>

                        <?php if ($has_status_col): ?>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex;gap:12px;margin-top:1.5rem;">
                        <button type="submit" name="add_batch" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Batch
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

        </div><!-- /.admin-main -->
    </main>
</div><!-- /.admin-wrapper -->

<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Delete batch with modern confirm
    document.querySelectorAll('.delete-batch-btn').forEach(function (btn) {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const batchName = this.dataset.batchName;
            const url       = this.dataset.url;

            const confirmed = await showConfirm({
                title:       'Delete Batch',
                message:     `Are you sure you want to delete batch <strong>${batchName}</strong>? This cannot be undone.`,
                confirmText: 'Delete',
                cancelText:  'Cancel',
                type:        'danger'
            });

            if (confirmed) {
                toast.loading('Deleting batch…');
                window.location.href = url;
            }
        });
    });

});
</script>
</body>
</html>
<?php $conn->close(); ?>