<?php
session_start();

// Check if admin is logged in (compatible with both old and new login systems)
$is_logged_in = isset($_SESSION['admin_logged_in']) || isset($_SESSION['admin']);

if (!$is_logged_in) {
    header('Location: login_new.php');
    exit();
}

// Check if admin has master admin role
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'master_admin') {
    header('Location: dashboard.php');
    exit();
}

require_once '../config/database.php';
require_once '../includes/theme_loader.php';

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
    <link href="../assets/css/admin-theme.css" rel="stylesheet">
    <link href="../assets/css/toast-notifications.css" rel="stylesheet">
    <link rel="icon" href="<?php echo getThemeFavicon($active_theme); ?>" type="image/x-icon">
    <style>
        .modern-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 20px; color: white; transition: all 0.3s ease; position: relative; overflow: hidden; }
        .modern-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%); pointer-events: none; }
        .modern-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .stats-card { background: white; border-radius: 15px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: none; transition: all 0.3s ease; position: relative; overflow: hidden; }
        .stats-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: linear-gradient(to bottom, #667eea, #764ba2); }
        .stats-card:hover { transform: translateY(-3px); box-shadow: 0 15px 35px rgba(0,0,0,0.15); }
        .stats-number { font-size: 2.5rem; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .stats-label { color: #6c757d; font-size: 0.9rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .stats-icon { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; }
        .icon-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .icon-success { background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); }
        .icon-info    { background: linear-gradient(135deg, #3498db 0%, #85c1e9 100%); }
        .icon-warning { background: linear-gradient(135deg, #f39c12 0%, #f7dc6f 100%); }
        .modern-table { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: none; }
        .modern-table .table { margin: 0; }
        .modern-table .table thead th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 1.2rem 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.85rem; }
        .modern-table .table tbody td { padding: 1rem; border-color: #f8f9fa; vertical-align: middle; }
        .modern-table .table tbody tr:hover { background-color: #f8f9fa; }
        .text-break { word-break: break-word; }
        .table td { max-width: 200px; }
        .table td:nth-child(2) { max-width: 180px; } /* Email column */
        .table td:nth-child(3) { max-width: 220px; } /* Course name column */
        .modern-btn { border-radius: 10px; padding: 0.6rem 1.5rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.85rem; transition: all 0.3s ease; border: none; cursor: pointer; }
        .modern-btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .modern-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102,126,234,0.3); color: white; }
        .modern-btn-danger { background: linear-gradient(135deg, #e74c3c 0%, #f1948a 100%); color: white; }
        .form-control, .form-select { border-radius: 10px; border: 2px solid #e9ecef; padding: 0.75rem 1rem; transition: all 0.3s ease; }
        .form-control:focus, .form-select:focus { border-color: #667eea; box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25); }
        .badge-modern { padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-auto   { background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); color: white; }
        .badge-manual { background: linear-gradient(135deg, #3498db 0%, #85c1e9 100%); color: white; }
        .page-header  { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem 0; margin-bottom: 2rem; border-radius: 0 0 30px 30px; }
        .empty-state  { text-align: center; padding: 3rem; color: #6c757d; }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.3; }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-content">

        <div class="page-header">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1"><i class="fas fa-user-tie"></i> Course Assignments</h1>
                        <p class="mb-0 opacity-75">Manage course coordinator assignments and permissions</p>
                    </div>
                    <div>
                        <button class="modern-btn modern-btn-primary" data-bs-toggle="modal" data-bs-target="#assignCoursesModal">
                            <i class="fas fa-plus"></i> Assign Courses
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-body">

            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stats-number"><?php echo $stats['total_coordinators_with_assignments']; ?></div>
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
                                <div class="stats-number"><?php echo $stats['total_assignments']; ?></div>
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
                                <div class="stats-number"><?php echo $stats['total_courses_assigned']; ?></div>
                                <div class="stats-label">Courses Assigned</div>
                                <small class="text-muted">Unique courses</small>
                            </div>
                            <div class="stats-icon icon-info"><i class="fas fa-graduation-cap"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="modern-table">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Current Course Assignments</h5>
                        <button class="btn btn-outline-primary btn-sm" onclick="refreshAssignments()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <div id="assignments-loading" style="display: none; text-align: center; padding: 2rem;">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-2">Loading assignments...</p>
                    </div>
                    <div id="assignments-table-container">
                        <!-- Table content will be loaded here via AJAX -->
                        <div class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                            <p class="mt-2">Loading assignments...</p>
                        </div>
                    </div>
            </div>

            <!-- Info Box -->
            <div class="modern-card mt-4">
                <div class="card-body">
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
    </main>
</div>

<!-- Assign Courses Modal -->
<div class="modal fade" id="assignCoursesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/toast-notifications.js"></script>
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
        
        var coordinator = this.querySelector('select[name="admin_id"]').value;
        var courses = this.querySelectorAll('input[name="course_ids[]"]:checked');
        var t = new ToastNotification();
        
        console.log('Coordinator selected:', coordinator); // Debug log
        console.log('Courses selected:', courses.length); // Debug log
        
        if (!coordinator) {
            console.log('No coordinator selected'); // Debug log
            t.warning('Please select a coordinator');
            return false;
        }
        if (courses.length === 0) {
            console.log('No courses selected'); // Debug log
            t.warning('Please select at least one course');
            return false;
        }
        
        console.log('Submitting form via AJAX'); // Debug log
        // Submit via AJAX
        submitAssignmentForm(this);
    });

}); // end DOMContentLoaded

// ── AJAX Functions ────────────────────────────────────────────────────────────

// Submit assignment form via AJAX
function submitAssignmentForm(form) {
    console.log('submitAssignmentForm called'); // Debug log
    
    var formData = new FormData(form);
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
            console.log('Assignment successful, showing notification'); // Debug log
            var t = new ToastNotification();
            if (data.type === 'warning') {
                t.warning(data.message);
            } else {
                t.assigned(data.message);
            }
            
            // Also show alert for debugging
            alert('SUCCESS: ' + data.message);
            
            // Close modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('assignCoursesModal'));
            modal.hide();
            
            // Refresh assignments table and stats
            refreshAssignments();
            refreshStats();
            
        } else {
            console.log('Assignment failed:', data.message); // Debug log
            var t = new ToastNotification();
            t.error(data.message || 'Failed to assign courses');
            
            // Also show alert for debugging
            alert('ERROR: ' + (data.message || 'Failed to assign courses'));
        }
    })
    .catch(error => {
        console.error('Fetch error:', error); // Debug log
        var t = new ToastNotification();
        t.error('Network error occurred. Please try again.');
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
        var t = new ToastNotification();
        
        if (data.success) {
            t.deleted(data.message);
            // Remove the row from table
            var row = document.querySelector(`button[onclick*="${assignmentId}"]`).closest('tr');
            if (row) {
                row.style.transition = 'opacity 0.3s ease';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
            // Refresh stats
            refreshStats();
        } else {
            t.error(data.message || 'Failed to remove assignment');
        }
    } catch (error) {
        console.error('Error:', error);
        var t = new ToastNotification();
        t.error('Network error occurred. Please try again.');
    }
}

// Refresh assignments table
function refreshAssignments() {
    var container = document.getElementById('assignments-table-container');
    var loading = document.getElementById('assignments-loading');
    
    loading.style.display = 'block';
    container.style.opacity = '0.5';
    
    fetch('ajax_course_assignments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_assignments'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateAssignmentsTable(data.assignments);
        } else {
            console.error('Failed to refresh assignments:', data.message);
        }
    })
    .catch(error => {
        console.error('Error refreshing assignments:', error);
    })
    .finally(() => {
        loading.style.display = 'none';
        container.style.opacity = '1';
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
    fetch('ajax_course_assignments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_stats'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateStats(data.stats);
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