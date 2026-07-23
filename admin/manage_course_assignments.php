<?php
session_start();

// Check if admin is logged in (compatible with both old and new login systems)
$is_logged_in = isset($_SESSION['admin_logged_in']) || isset($_SESSION['admin']);

if (!$is_logged_in) {
    header('Location: login.php');
    exit();
}

// Check if admin has master admin role
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'master_admin') {
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';

// ── Flash helper ──────────────────────────────────────────────────────────────
function setFlash($msg, $type) {
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type']    = $type;
}

// ── Handle assign courses POST ────────────────────────────────────────────────
// AJAX handling - no longer needed as we use AJAX endpoint
// Keeping for backward compatibility if JavaScript is disabled
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_courses'])) {
    $admin_id    = intval($_POST['admin_id']);
    $course_ids  = isset($_POST['course_ids']) ? $_POST['course_ids'] : [];
    $assigned_by = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;

    if ($admin_id <= 0) {
        setFlash("Please select a valid course coordinator.", "warning");
    } elseif (empty($course_ids)) {
        setFlash("Please select at least one course to assign.", "warning");
    } elseif (!$assigned_by) {
        setFlash("Session error: Please log out and log back in.", "error");
    } else {
        $has_assignment_type = false;
        try {
            $chk = $conn->query("SHOW COLUMNS FROM admin_course_assignments LIKE 'assignment_type'");
            $has_assignment_type = ($chk->num_rows > 0);
        } catch (Exception $e) {}

        $success_count = $error_count = $duplicate_count = 0;
        $duplicate_courses = [];

        foreach ($course_ids as $cid) {
            $cid = intval($cid);
            try {
                $s = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
                $s->bind_param("i", $cid);
                $s->execute();
                $course_name = $s->get_result()->fetch_assoc()['course_name'] ?? "Course #$cid";

                $s2 = $conn->prepare("SELECT id, is_active FROM admin_course_assignments WHERE admin_id = ? AND course_id = ?");
                $s2->bind_param("ii", $admin_id, $cid);
                $s2->execute();
                $existing = $s2->get_result()->fetch_assoc();

                if ($existing) {
                    if ($existing['is_active'] == 1) {
                        $duplicate_count++;
                        $duplicate_courses[] = $course_name;
                        continue;
                    }
                    $sql = $has_assignment_type
                        ? "UPDATE admin_course_assignments SET is_active=1, assigned_at=NOW(), assigned_by=?, assignment_type='Manual' WHERE admin_id=? AND course_id=?"
                        : "UPDATE admin_course_assignments SET is_active=1, assigned_at=NOW(), assigned_by=? WHERE admin_id=? AND course_id=?";
                } else {
                    $sql = $has_assignment_type
                        ? "INSERT INTO admin_course_assignments (admin_id,course_id,is_active,assigned_by,assigned_at,assignment_type) VALUES (?,?,1,?,NOW(),'Manual')"
                        : "INSERT INTO admin_course_assignments (admin_id,course_id,is_active,assigned_by,assigned_at) VALUES (?,?,1,?,NOW())";
                }
                $st = $conn->prepare($sql);
                if ($existing) {
                    $st->bind_param("iii", $assigned_by, $admin_id, $cid);
                } else {
                    $st->bind_param("iii", $admin_id, $cid, $assigned_by);
                }
                $st->execute() ? $success_count++ : $error_count++;
            } catch (Exception $e) { $error_count++; }
        }

        $parts = [];
        $type  = 'info';
        if ($success_count)   { $parts[] = "Successfully assigned $success_count course(s) to the coordinator!"; $type = 'assignment'; }
        if ($duplicate_count) {
            $parts[] = count($duplicate_courses) === 1
                ? "'{$duplicate_courses[0]}' is already assigned to this coordinator."
                : "$duplicate_count courses already assigned: " . implode(', ', $duplicate_courses) . ".";
            if (!$success_count) $type = 'warning';
        }
        if ($error_count) { $parts[] = "Failed to assign $error_count course(s)."; if (!$success_count && !$duplicate_count) $type = 'error'; }

        setFlash(implode(' ', $parts), $type);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// ── Handle remove assignment POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_assignment'])) {
    $assignment_id = intval($_POST['assignment_id']);

    $s = $conn->prepare("SELECT a.username AS admin_name, c.course_name
                         FROM admin_course_assignments aca
                         JOIN admin a ON aca.admin_id = a.id
                         JOIN courses c ON aca.course_id = c.id
                         WHERE aca.id = ? AND aca.is_active = 1");
    $s->bind_param("i", $assignment_id);
    $s->execute();
    $details = $s->get_result()->fetch_assoc();

    if ($details) {
        $r = $conn->prepare("UPDATE admin_course_assignments SET is_active = 0 WHERE id = ?");
        $r->bind_param("i", $assignment_id);
        $r->execute()
            ? setFlash("Successfully removed '{$details['course_name']}' from '{$details['admin_name']}'!", "delete")
            : setFlash("Failed to remove course assignment.", "error");
    } else {
        setFlash("Assignment not found or already removed.", "warning");
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// ── Read flash message (after redirect) ──────────────────────────────────────
$message      = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : '';
$message_type = isset($_SESSION['flash_type'])    ? $_SESSION['flash_type']    : '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// ── Load page data ────────────────────────────────────────────────────────────
$active_theme = loadActiveTheme($conn);

$coordinators_result = $conn->query("SELECT id, username, email FROM admin WHERE role = 'course_coordinator' AND is_active = 1 ORDER BY username");
$courses_result      = $conn->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name");
$assignments_result  = $conn->query("SELECT aca.*, 
                                     a.username AS admin_name, 
                                     a.email AS admin_email,
                                     c.course_name, 
                                     c.course_code, 
                                     COALESCE(ma.username, 'System') AS assigned_by_name,
                                     COALESCE(aca.assignment_type, 'Manual') AS assignment_type,
                                     aca.assigned_at
                                     FROM admin_course_assignments aca
                                     JOIN admin a ON aca.admin_id = a.id
                                     JOIN courses c ON aca.course_id = c.id
                                     LEFT JOIN admin ma ON aca.assigned_by = ma.id
                                     WHERE aca.is_active = 1
                                     ORDER BY aca.assigned_at DESC, a.username, c.course_name");
$stats_result = $conn->query("SELECT
    COUNT(DISTINCT aca.admin_id) AS total_coordinators_with_assignments,
    COUNT(aca.id) AS total_assignments,
    COUNT(DISTINCT aca.course_id) AS total_courses_assigned
    FROM admin_course_assignments aca WHERE aca.is_active = 1");
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Assignments - NIELIT Admin</title>
    <?php injectThemeCSS($active_theme); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/assets/css/admin-theme.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin-theme.css') ?: time(); ?>" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css" rel="stylesheet">
    <link rel="icon" href="<?php echo getThemeFaviconUrl($active_theme); ?>" type="image/x-icon">
    <style>
        :root {
            --ca-primary: var(--primary-color, #0c2340);
            --ca-secondary: var(--secondary-color, #123a66);
            --ca-accent: var(--accent-color, #f59e0b);
            --ca-gradient: linear-gradient(135deg, var(--ca-primary) 0%, var(--ca-secondary) 100%);
        }
        .modern-card {
            background: var(--ca-gradient);
            border: none;
            border-radius: 16px;
            color: #fff;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            position: relative;
            overflow: hidden;
        }
        .modern-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
            pointer-events: none;
        }
        .modern-card:hover { transform: translateY(-3px); box-shadow: 0 16px 32px rgba(15, 23, 42, 0.18); }
        .stats-card {
            background: var(--bg-card, #fff);
            border-radius: 14px;
            padding: 1.5rem;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(15, 23, 42, 0.06);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            position: relative;
            overflow: hidden;
        }
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px; height: 100%;
            background: var(--ca-gradient);
        }
        .stats-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12); }
        .stats-number {
            font-size: 2.25rem;
            font-weight: 700;
            background: var(--ca-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stats-label {
            color: var(--text-secondary, #64748b);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .stats-icon {
            width: 56px; height: 56px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.35rem; color: #fff;
        }
        .icon-primary { background: var(--ca-gradient); }
        .icon-success { background: linear-gradient(135deg, #059669 0%, #34d399 100%); }
        .icon-info    { background: linear-gradient(135deg, var(--ca-secondary) 0%, #38bdf8 100%); }
        .modern-table {
            background: var(--bg-card, #fff);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(15, 23, 42, 0.06);
        }
        .modern-table .table { margin: 0; }
        .modern-table .table thead th {
            background: var(--ca-gradient);
            color: #fff;
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 0.8rem;
        }
        .modern-table .table tbody td {
            padding: 0.9rem 1rem;
            border-color: rgba(15, 23, 42, 0.06);
            vertical-align: middle;
            color: var(--text-primary, #1e293b);
        }
        .modern-table .table tbody tr:hover { background-color: rgba(15, 23, 42, 0.03); }
        .text-break { word-break: break-word; }
        .modern-btn {
            border-radius: 10px;
            padding: 0.55rem 1.25rem;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .modern-btn-primary {
            background: var(--ca-gradient);
            color: #fff;
        }
        .modern-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.2);
            color: #fff;
        }
        .modern-btn-danger { background: linear-gradient(135deg, #dc2626 0%, #f87171 100%); color: #fff; }
        .form-control:focus, .form-select:focus {
            border-color: var(--ca-secondary);
            box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--ca-secondary) 25%, transparent);
        }
        .badge-modern {
            padding: 0.4rem 0.85rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .badge-auto   { background: linear-gradient(135deg, #059669 0%, #34d399 100%); color: #fff; }
        .badge-manual { background: linear-gradient(135deg, var(--ca-secondary) 0%, #38bdf8 100%); color: #fff; }
        #assignCoursesModal .modal-header {
            background: var(--ca-gradient) !important;
            color: #fff;
            border: none;
        }
        #coursesContainer {
            border-color: color-mix(in srgb, var(--ca-primary) 18%, #e2e8f0) !important;
        }
        .empty-state { text-align: center; padding: 3rem; color: var(--text-muted, #94a3b8); }
        .empty-state i { font-size: 3.5rem; margin-bottom: 1rem; opacity: 0.35; color: var(--ca-secondary); }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(sidebarThemeBodyClass(getActiveSidebarTheme($conn))); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-user-tie"></i> Course Assignments</h4>
                <small>Manage course coordinator assignments and permissions</small>
            </div>
            <div class="topbar-right">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignCoursesModal">
                    <i class="fas fa-plus"></i> Assign Courses
                </button>
            </div>
        </div>

        <div class="admin-main">
        <div class="content-body">

            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stats-number"><?php echo (int) ($stats['total_coordinators_with_assignments'] ?? 0); ?></div>
                                <div class="stats-label">Coordinators</div>
                                <small class="text-muted">With assignments</small>
                            </div>
                            <div class="stats-icon icon-primary"><i class="fas fa-user-tie"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stats-number"><?php echo (int) ($stats['total_assignments'] ?? 0); ?></div>
                                <div class="stats-label">Total Assignments</div>
                                <small class="text-muted">Active assignments</small>
                            </div>
                            <div class="stats-icon icon-success"><i class="fas fa-link"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stats-number"><?php echo (int) ($stats['total_courses_assigned'] ?? 0); ?></div>
                                <div class="stats-label">Courses Assigned</div>
                                <small class="text-muted">Unique courses</small>
                            </div>
                            <div class="stats-icon icon-info"><i class="fas fa-graduation-cap"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="modern-table content-card">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Current Course Assignments</h5>
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshAssignments()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="table-responsive">
                    <div id="assignments-loading" style="display: none; text-align: center; padding: 2rem;">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-2">Loading assignments...</p>
                    </div>
                    <div id="assignments-table-container">
                        <div class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                            <p class="mt-2">Loading assignments...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="modern-card mt-4">
                <div class="card-body" style="position:relative;z-index:1;">
                    <h6><i class="fas fa-lightbulb"></i> How Course Assignments Work:</h6>
                    <ul class="mb-3">
                        <li><strong>Course Coordinators</strong> can only see students enrolled in their assigned courses</li>
                        <li><strong>Master Admins</strong> can see all students regardless of assignments</li>
                        <li>Assignments help organize course management and restrict access appropriately</li>
                        <li>Coordinators will see filtered statistics and student lists based on their assignments</li>
                    </ul>
                    <button class="modern-btn modern-btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignCoursesModal">
                        <i class="fas fa-plus"></i> Quick Assign
                    </button>
                </div>
            </div>

        </div>
        </div>
    </main>
</div>

<!-- Assign Courses Modal -->
<div class="modal fade" id="assignCoursesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Courses to Coordinator</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="assignCoursesForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Course Coordinator *</label>
                        <select name="admin_id" class="form-select" required id="coordinatorSelect">
                            <option value="">-- Select Coordinator --</option>
                            <?php $coordinators_result->data_seek(0); while ($c = $coordinators_result->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['username']); ?> (<?php echo htmlspecialchars($c['email']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Courses to Assign *</label>
                        <div id="coursesContainer" style="max-height:300px;overflow-y:auto;border:1px solid #dee2e6;padding:1rem;border-radius:0.375rem;">
                            <div class="text-muted text-center py-3" id="selectCoordinatorFirst">
                                <i class="fas fa-arrow-up"></i> Please select a coordinator first
                            </div>
                            <?php $courses_result->data_seek(0); while ($course = $courses_result->fetch_assoc()): ?>
                                <div class="form-check mb-2 course-option" data-course-id="<?php echo $course['id']; ?>" style="display:none;">
                                    <input class="form-check-input" type="checkbox"
                                           name="course_ids[]"
                                           value="<?php echo $course['id']; ?>"
                                           id="course_<?php echo $course['id']; ?>">
                                    <label class="form-check-label" for="course_<?php echo $course['id']; ?>">
                                        <strong><?php echo htmlspecialchars($course['course_name']); ?></strong>
                                        <?php if ($course['course_code']): ?>
                                            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-info-circle"></i> Only unassigned courses will be shown for the selected coordinator
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_courses" class="btn btn-primary">Assign Courses</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>

// ── Show flash toast on page load ─────────────────────────────────────────────
<?php if ($message):
    switch ($message_type) {
        case 'assignment': $js_method = 'assigned'; break;
        case 'delete':     $js_method = 'deleted';  break;
        case 'success':    $js_method = 'success';  break;
        case 'warning':    $js_method = 'warning';  break;
        case 'error':      $js_method = 'error';    break;
        default:           $js_method = 'info';
    }
?>
window.addEventListener('DOMContentLoaded', function() {
    var t = new ToastNotification();
    t.<?php echo $js_method; ?>('<?php echo addslashes($message); ?>');
});
<?php endif; ?>

// ── Course loader function ────────────────────────────────────────────────────
function loadCoursesForCoordinator(adminId) {
    var selectMsg     = document.getElementById('selectCoordinatorFirst');
    var courseOptions = document.querySelectorAll('.course-option');

    if (!adminId) {
        selectMsg.innerHTML   = '<i class="fas fa-arrow-up"></i> Please select a coordinator first';
        selectMsg.style.display = 'block';
        courseOptions.forEach(function(o) {
            o.style.display = 'none';
            o.querySelector('input').checked = false;
        });
        return;
    }

    // Show all courses immediately so user is never stuck
    selectMsg.style.display = 'none';
    courseOptions.forEach(function(o) {
        o.style.display = 'block';
        o.querySelector('input').checked = false;
    });

    // Filter out already-assigned courses
    fetch('get_assigned_courses.php?admin_id=' + adminId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var assigned  = data.assigned_courses || [];
            var available = 0;
            courseOptions.forEach(function(o) {
                var id = parseInt(o.dataset.courseId);
                if (assigned.indexOf(id) !== -1) {
                    o.style.display = 'none';
                    o.querySelector('input').checked = false;
                } else {
                    available++;
                }
            });
            if (available === 0) {
                selectMsg.innerHTML   = '<div class="text-warning"><i class="fas fa-check-circle"></i> All courses are already assigned to this coordinator</div>';
                selectMsg.style.display = 'block';
            }
        })
        .catch(function() {
            // fetch failed — courses already visible, no problem
        });
}

// ── Event listeners ───────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing...');
    
    // Test toast notifications on page load
    setTimeout(function() {
        console.log('Testing toast notifications...');
        if (typeof ToastNotification !== 'undefined') {
            console.log('ToastNotification class is available');
            // Test with a subtle info message
            var t = new ToastNotification();
            t.info('Course Assignment system loaded successfully');
        } else {
            console.log('ToastNotification class is NOT available, using fallback');
            // Test fallback system
            showToast('success', 'Course Assignment system loaded successfully');
        }
    }, 1000);

    // Load assignments table via AJAX on page load
    refreshAssignments();

    // Dropdown change
    document.getElementById('coordinatorSelect').addEventListener('change', function() {
        loadCoursesForCoordinator(this.value);
    });

    // Auto-load when modal opens (handles single-coordinator pre-selection)
    document.getElementById('assignCoursesModal').addEventListener('shown.bs.modal', function() {
        var adminId = document.getElementById('coordinatorSelect').value;
        if (adminId) loadCoursesForCoordinator(adminId);
    });

    // Reset on modal close
    document.getElementById('assignCoursesModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('assignCoursesForm').reset();
        var selectMsg = document.getElementById('selectCoordinatorFirst');
        selectMsg.innerHTML   = '<i class="fas fa-arrow-up"></i> Please select a coordinator first';
        selectMsg.style.display = 'block';
        document.querySelectorAll('.course-option').forEach(function(o) {
            o.style.display = 'none';
            o.querySelector('input').checked = false;
        });
    });

    // Form validation
    document.getElementById('assignCoursesForm').addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default form submission
        
        console.log('Form submission intercepted'); // Debug log
        console.log('Form element:', this); // Debug log
        
        var coordinatorSelect = this.querySelector('select[name="admin_id"]');
        var coordinator = coordinatorSelect ? coordinatorSelect.value : 'NOT_FOUND';
        var courses = this.querySelectorAll('input[name="course_ids[]"]:checked');
        
        console.log('Coordinator select element:', coordinatorSelect); // Debug log
        console.log('Coordinator selected:', coordinator); // Debug log
        console.log('Courses selected:', courses.length); // Debug log
        
        // Also try alternative selector
        var altCoordinator = document.getElementById('coordinatorSelect');
        console.log('Alternative coordinator element:', altCoordinator); // Debug log
        console.log('Alternative coordinator value:', altCoordinator ? altCoordinator.value : 'NOT_FOUND'); // Debug log
        
        if (!coordinator || coordinator === '') {
            console.log('No coordinator selected'); // Debug log
            
            // Show warning notification
            if (typeof ToastNotification !== 'undefined') {
                var t = new ToastNotification();
                t.warning('Please select a coordinator');
            } else if (typeof toast !== 'undefined') {
                toast.warning('Please select a coordinator');
            } else {
                showToast('warning', 'Please select a coordinator');
            }
            return false;
        }
        if (courses.length === 0) {
            console.log('No courses selected'); // Debug log
            
            // Show warning notification
            if (typeof ToastNotification !== 'undefined') {
                var t = new ToastNotification();
                t.warning('Please select at least one course');
            } else if (typeof toast !== 'undefined') {
                toast.warning('Please select at least one course');
            } else {
                showToast('warning', 'Please select at least one course');
            }
            return false;
        }
        
        console.log('Submitting form via AJAX'); // Debug log
        // Submit via AJAX
        submitAssignmentForm(this);
    });

}); // end DOMContentLoaded

// ── AJAX Functions ────────────────────────────────────────────────────────────

// Fallback toast function if ToastNotification class is not available
function showToast(type, message) {
    console.log('showToast called with type:', type, 'message:', message);
    
    // Create toast container if it doesn't exist
    var container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        document.body.appendChild(container);
    }
    
    // Create toast element
    var toast = document.createElement('div');
    var bgColor, icon;
    
    switch(type) {
        case 'success':
        case 'assigned':
            bgColor = '#10b981';
            icon = 'check-circle';
            break;
        case 'warning':
            bgColor = '#f59e0b';
            icon = 'exclamation-triangle';
            break;
        case 'error':
            bgColor = '#ef4444';
            icon = 'times-circle';
            break;
        case 'delete':
            bgColor = '#dc2626';
            icon = 'trash';
            break;
        default:
            bgColor = '#3b82f6';
            icon = 'info-circle';
    }
    
    toast.style.cssText = `
        background: ${bgColor};
        color: white;
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 12px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 320px;
        max-width: 400px;
        animation: slideInRight 0.4s ease;
        font-size: 14px;
        font-weight: 500;
        line-height: 1.4;
        word-wrap: break-word;
    `;
    
    toast.innerHTML = `<i class="fas fa-${icon}" style="font-size: 16px; flex-shrink: 0;"></i> <span>${message}</span>`;
    container.appendChild(toast);
    
    console.log('Toast created and added to container');
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.4s ease';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400);
    }, 5000);
}

// Add CSS animations for toast
if (!document.getElementById('toast-animations')) {
    var style = document.createElement('style');
    style.id = 'toast-animations';
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}

// Submit assignment form via AJAX
function submitAssignmentForm(form) {
    console.log('submitAssignmentForm called'); // Debug log
    
    // Try multiple ways to get the coordinator value
    var coordinatorSelect1 = form.querySelector('select[name="admin_id"]');
    var coordinatorSelect2 = document.getElementById('coordinatorSelect');
    var coordinatorValue = coordinatorSelect1 ? coordinatorSelect1.value : (coordinatorSelect2 ? coordinatorSelect2.value : '');
    
    console.log('Coordinator method 1:', coordinatorSelect1 ? coordinatorSelect1.value : 'NULL');
    console.log('Coordinator method 2:', coordinatorSelect2 ? coordinatorSelect2.value : 'NULL');
    console.log('Final coordinator value:', coordinatorValue);
    
    var formData = new FormData(form);
    
    // Override the admin_id in FormData to ensure it's set correctly
    if (coordinatorValue) {
        formData.set('admin_id', coordinatorValue);
    }
    
    formData.append('action', 'assign_courses');
    
    console.log('FormData created, action added'); // Debug log
    
    var submitBtn = form.querySelector('button[type="submit"]');
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
    submitBtn.disabled = true;
    
    console.log('Button state changed, making fetch request'); // Debug log
    
    fetch('ajax_course_assignments.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Fetch response received:', response.status); // Debug log
        return response.json();
    })
    .then(data => {
        console.log('JSON data received:', data); // Debug log
        
        var t = new ToastNotification();
        
        if (data.success) {
            // Show success message
            console.log('Assignment successful, showing notification');
            
            // Initialize toast notification properly - try multiple approaches
            try {
                if (typeof ToastNotification !== 'undefined') {
                    var t = new ToastNotification();
                    if (data.type === 'warning') {
                        t.warning(data.message);
                    } else {
                        t.assigned(data.message); // Use assigned method for course assignments
                    }
                } else {
                    // Fallback - create toast manually
                    showToast(data.type === 'warning' ? 'warning' : 'success', data.message);
                }
            } catch (error) {
                console.error('Toast notification error:', error);
                // Final fallback - browser alert
                alert('Success: ' + data.message);
            }
            
            // Close modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('assignCoursesModal'));
            if (modal) {
                modal.hide();
            }
            
            // Refresh assignments table and stats
            refreshAssignments();
            refreshStats();
            
        } else {
            console.log('Assignment failed:', data.message);
            
            // Show error notification with better error handling
            try {
                if (typeof ToastNotification !== 'undefined') {
                    var t = new ToastNotification();
                    t.error(data.message || 'Failed to assign courses');
                } else {
                    showToast('error', data.message || 'Failed to assign courses');
                }
            } catch (error) {
                console.error('Toast notification error:', error);
                alert('Error: ' + (data.message || 'Failed to assign courses'));
            }
        }
    })
    .catch(error => {
        console.error('Fetch error:', error); // Debug log
        
        // Show error notification with better error handling
        try {
            if (typeof ToastNotification !== 'undefined') {
                var t = new ToastNotification();
                t.error('Network error occurred. Please try again.');
            } else {
                showToast('error', 'Network error occurred. Please try again.');
            }
        } catch (toastError) {
            console.error('Toast notification error:', toastError);
            alert('Network error occurred. Please try again.');
        }
    })
    .finally(() => {
        console.log('Restoring button state'); // Debug log
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Remove assignment via AJAX
async function removeAssignmentAjax(assignmentId) {
    var formData = new FormData();
    formData.append('action', 'remove_assignment');
    formData.append('assignment_id', assignmentId);
    
    try {
        const response = await fetch('ajax_course_assignments.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Show success notification
            if (typeof ToastNotification !== 'undefined') {
                var t = new ToastNotification();
                t.success(data.message);
            } else if (typeof toast !== 'undefined') {
                toast.success(data.message);
            } else {
                showToast('success', data.message);
            }
            
            // Refresh the entire assignments table
            refreshAssignments();
            refreshStats();
        } else {
            // Show error notification
            if (typeof ToastNotification !== 'undefined') {
                var t = new ToastNotification();
                t.error(data.message || 'Failed to remove assignment');
            } else if (typeof toast !== 'undefined') {
                toast.error(data.message || 'Failed to remove assignment');
            } else {
                showToast('error', data.message || 'Failed to remove assignment');
            }
        }
    } catch (error) {
        console.error('Error:', error);
        
        // Show error notification
        if (typeof ToastNotification !== 'undefined') {
            var t = new ToastNotification();
            t.error('Network error occurred. Please try again.');
        } else if (typeof toast !== 'undefined') {
            toast.error('Network error occurred. Please try again.');
        } else {
            showToast('error', 'Network error occurred. Please try again.');
        }
    }
}

// Refresh assignments table
function refreshAssignments() {
    console.log('Refreshing assignments table...');
    
    var container = document.getElementById('assignments-table-container');
    var loading = document.getElementById('assignments-loading');
    
    if (loading) loading.style.display = 'block';
    if (container) container.style.opacity = '0.5';
    
    fetch('ajax_course_assignments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_assignments'
    })
    .then(response => {
        console.log('Assignments response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Assignments data received:', data);
        if (data.success) {
            updateAssignmentsTable(data.assignments);
            console.log('Assignments table updated successfully');
        } else {
            console.error('Failed to refresh assignments:', data.message);
            // Show error notification
            if (typeof ToastNotification !== 'undefined') {
                var t = new ToastNotification();
                t.error('Failed to refresh assignments: ' + (data.message || 'Unknown error'));
            } else {
                showToast('error', 'Failed to refresh assignments');
            }
        }
    })
    .catch(error => {
        console.error('Error refreshing assignments:', error);
        // Show error notification
        if (typeof ToastNotification !== 'undefined') {
            var t = new ToastNotification();
            t.error('Network error while refreshing assignments');
        } else {
            showToast('error', 'Network error while refreshing assignments');
        }
    })
    .finally(() => {
        if (loading) loading.style.display = 'none';
        if (container) container.style.opacity = '1';
        console.log('Assignments refresh completed');
    });
}

// Update assignments table with new data
function updateAssignmentsTable(assignments) {
    var container = document.getElementById('assignments-table-container');
    
    if (assignments.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h5>No Course Assignments Found</h5>
                <p class="text-muted">Use the "Assign Courses" button to create assignments.</p>
                <button class="modern-btn modern-btn-primary" data-bs-toggle="modal" data-bs-target="#assignCoursesModal">
                    <i class="fas fa-plus"></i> Create First Assignment
                </button>
            </div>
        `;
        return;
    }
    
    var tableHTML = `
        <table class="table">
            <thead>
                <tr>
                    <th>Coordinator</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Course Code</th>
                    <th>Type</th>
                    <th>Assigned By</th>
                    <th>Assigned Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    assignments.forEach(assignment => {
        var badgeClass = (assignment.assignment_type === 'Auto-Assigned') ? 'badge-auto' : 'badge-manual';
        var assignedDate = new Date(assignment.assigned_at);
        var formattedDate = assignedDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        var formattedTime = assignedDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        
        tableHTML += `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="stats-icon icon-primary me-2" style="width:35px;height:35px;font-size:0.9rem;">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div>
                            <strong>${escapeHtml(assignment.admin_name)}</strong>
                            <br><small class="text-muted">ID: ${assignment.admin_id}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="text-break">
                        <i class="fas fa-envelope text-muted me-1"></i>
                        <span>${escapeHtml(assignment.admin_email)}</span>
                    </div>
                </td>
                <td>
                    <div>
                        <strong>${escapeHtml(assignment.course_name)}</strong>
                        <br><small class="text-muted">Course ID: ${assignment.course_id}</small>
                    </div>
                </td>
                <td>
                    <span class="badge bg-primary rounded-pill">${escapeHtml(assignment.course_code)}</span>
                </td>
                <td>
                    <span class="badge-modern ${badgeClass}">${escapeHtml(assignment.assignment_type)}</span>
                </td>
                <td>
                    <div>
                        <i class="fas fa-user-cog text-muted me-1"></i>
                        <strong>${escapeHtml(assignment.assigned_by_name || 'System')}</strong>
                        ${assignment.assigned_by ? `<br><small class="text-muted">Admin ID: ${assignment.assigned_by}</small>` : ''}
                    </div>
                </td>
                <td>
                    <div>
                        <i class="fas fa-calendar text-muted me-1"></i>
                        <strong>${formattedDate}</strong>
                        <br><small class="text-muted">
                            <i class="fas fa-clock me-1"></i>${formattedTime}
                        </small>
                    </div>
                </td>
                <td>
                    <button type="button" class="modern-btn modern-btn-danger btn-sm"
                        onclick="removeAssignment(${assignment.id}, '${escapeHtml(assignment.admin_name).replace(/'/g, "\\'")}', '${escapeHtml(assignment.course_name).replace(/'/g, "\\'")}')"
                        title="Remove Assignment">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </td>
            </tr>
        `;
    });
    
    tableHTML += `
            </tbody>
        </table>
    `;
    
    container.innerHTML = tableHTML;
}

// Refresh statistics
function refreshStats() {
    console.log('Refreshing statistics...');
    
    fetch('ajax_course_assignments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_stats'
    })
    .then(response => {
        console.log('Stats response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Stats data received:', data);
        if (data.success) {
            updateStats(data.stats);
            console.log('Statistics updated successfully');
        } else {
            console.error('Failed to refresh stats:', data.message);
        }
    })
    .catch(error => {
        console.error('Error refreshing stats:', error);
    });
}

// Update statistics display
function updateStats(stats) {
    var statsNumbers = document.querySelectorAll('.stats-number');
    if (statsNumbers.length >= 3) {
        statsNumbers[0].textContent = stats.total_coordinators_with_assignments || 0;
        statsNumbers[1].textContent = stats.total_assignments || 0;
        statsNumbers[2].textContent = stats.total_courses_assigned || 0;
    }
}

// HTML escape function
function escapeHtml(text) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// ── Remove assignment (global scope — called from onclick in table) ────────────
async function removeAssignment(assignmentId, coordinatorName, courseName) {
    var confirmed = await showConfirm({
        title:       'Remove Course Assignment',
        message:     'Are you sure you want to remove "' + courseName + '" from "' + coordinatorName + '"?',
        type:        'danger',
        confirmText: 'Remove Assignment',
        cancelText:  'Keep Assignment'
    });
    if (confirmed) {
        removeAssignmentAjax(assignmentId);
    }
}
</script>
</body>
</html>