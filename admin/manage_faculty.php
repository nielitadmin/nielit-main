<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/session_manager.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login_new.php');
    exit();
}

if (!isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id'])) {
    if (!init_admin_session($_SESSION['admin'])) {
        session_unset();
        session_destroy();
        header('Location: login_new.php');
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_faculty':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $designation = trim($_POST['designation']);
                $department = trim($_POST['department']);
                
                if (!empty($name)) {
                    $stmt = $conn->prepare("INSERT INTO faculty (name, email, phone, designation, department, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssi", $name, $email, $phone, $designation, $department, $admin_id);
                    if ($stmt->execute()) {
                        // Auto-send confirmation email if email is provided
                        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $email_sent = sendFacultyConfirmationEmail($email, $name, $designation, $department);
                            $success_message = $email_sent
                                ? "Faculty member added successfully! Confirmation email sent to <strong>$email</strong>."
                                : "Faculty member added successfully! (Email could not be sent — check SMTP settings.)";
                        } else {
                            $success_message = "Faculty member added successfully!";
                        }
                    } else {
                        $error_message = "Error adding faculty member.";
                    }
                }
                break;
                
            case 'update_faculty':
                $faculty_id = $_POST['faculty_id'];
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $designation = trim($_POST['designation']);
                $department = trim($_POST['department']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $stmt = $conn->prepare("UPDATE faculty SET name = ?, email = ?, phone = ?, designation = ?, department = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param("sssssii", $name, $email, $phone, $designation, $department, $is_active, $faculty_id);
                if ($stmt->execute()) {
                    $success_message = "Faculty member updated successfully!";
                } else {
                    $error_message = "Error updating faculty member.";
                }
                break;
                
            case 'delete_faculty':
                $faculty_id = $_POST['faculty_id'];
                
                // Check if current admin can delete this faculty
                $check_stmt = $conn->prepare("SELECT created_by FROM faculty WHERE id = ?");
                $check_stmt->bind_param("i", $faculty_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $faculty_data = $check_result->fetch_assoc();
                $check_stmt->close();
                
                if (!$faculty_data) {
                    $error_message = "Faculty member not found.";
                    break;
                }
                
                // Check permissions - only allow deletion if:
                // 1. Master admin can delete any faculty
                // 2. Course coordinator can only delete faculty they created
                if ($admin_role !== 'master_admin' && $faculty_data['created_by'] != $admin_id) {
                    $error_message = "You can only delete faculty members you have added.";
                    break;
                }
                
                $stmt = $conn->prepare("UPDATE faculty SET is_active = 0 WHERE id = ?");
                $stmt->bind_param("i", $faculty_id);
                if ($stmt->execute()) {
                    $success_message = "Faculty member deactivated successfully!";
                } else {
                    $error_message = "Error deactivating faculty member.";
                }
                break;
        }
    }
}

// Fetch all faculty members - filter based on role
$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'] ?? ($_SESSION['role'] ?? '');

if ($admin_role === 'master_admin') {
    // Master admins can see all faculty
    $result = $conn->query("SELECT * FROM faculty ORDER BY is_active DESC, name ASC");
} else {
    // Course coordinators see only faculty they created + global faculty (created_by = 0 or NULL)
    $stmt = $conn->prepare("SELECT * FROM faculty 
                           WHERE (created_by = ? OR created_by = 0 OR created_by IS NULL)
                           ORDER BY is_active DESC, name ASC");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

$faculty_members = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $faculty_members[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Faculty - NIELIT Admin</title>
    <?php injectThemeCSS($active_theme); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-chalkboard-teacher"></i> Faculty Management</h4>
                <small>Add, edit, and deactivate faculty records</small>
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
            <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
                    <i class="fas fa-plus"></i> Add Faculty
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
                    <h5 class="card-title"><i class="fas fa-users"></i> Faculty Members</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Designation</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Send Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faculty_members as $faculty): ?>
                                <tr data-faculty-id="<?php echo (int)$faculty['id']; ?>">
                                    <td><?php echo htmlspecialchars($faculty['name']); ?></td>
                                    <td><?php echo htmlspecialchars($faculty['email']); ?></td>
                                    <td><?php echo htmlspecialchars($faculty['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($faculty['designation']); ?></td>
                                    <td><?php echo htmlspecialchars($faculty['department']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $faculty['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $faculty['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($faculty['email'])): ?>
                                            <button class="btn btn-sm btn-warning"
                                                    onclick="resendFacultyEmail(<?php echo (int)$faculty['id']; ?>, '<?php echo addslashes($faculty['name']); ?>', this)"
                                                    title="Resend confirmation email to <?php echo htmlspecialchars($faculty['name']); ?>">
                                                <i class="fas fa-paper-plane"></i> Resend Email
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 12px;">No email</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick='editFaculty(<?php echo json_encode($faculty); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php
                                        $can_delete = $faculty['is_active'] && ($admin_role === 'master_admin' || $faculty['created_by'] == $admin_id);
                                        ?>
                                        <?php if ($can_delete): ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deactivateFaculty(<?php echo $faculty['id']; ?>, '<?php echo addslashes(htmlspecialchars($faculty['name'], ENT_QUOTES)); ?>')"
                                                title="Deactivate <?php echo htmlspecialchars($faculty['name']); ?>">
                                            <i class="fas fa-ban"></i> Deactivate
                                        </button>
                                        <?php endif; ?>

                                        <?php if ($faculty['created_by'] == $admin_id): ?>
                                        <small class="text-muted d-block" style="font-size: 10px;">My Faculty</small>
                                        <?php elseif (empty($faculty['created_by']) || $faculty['created_by'] == 0): ?>
                                        <small class="text-muted d-block" style="font-size: 10px;">Global</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Faculty Modal -->
<div class="modal fade" id="addFacultyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Faculty Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_faculty">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label for="designation" class="form-label">Designation</label>
                        <input type="text" class="form-control" id="designation" name="designation" placeholder="e.g., Professor, Assistant Professor">
                    </div>
                    
                    <div class="mb-3">
                        <label for="department" class="form-label">Department</label>
                        <input type="text" class="form-control" id="department" name="department" placeholder="e.g., Computer Science, IT">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Faculty Modal -->
<div class="modal fade" id="editFacultyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Faculty Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editFacultyForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_faculty">
                    <input type="hidden" name="faculty_id" id="edit_faculty_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="edit_phone" name="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_designation" class="form-label">Designation</label>
                        <input type="text" class="form-control" id="edit_designation" name="designation">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_department" class="form-label">Department</label>
                        <input type="text" class="form-control" id="edit_department" name="department">
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
function editFaculty(faculty) {
    document.getElementById('edit_faculty_id').value = faculty.id;
    document.getElementById('edit_name').value = faculty.name;
    document.getElementById('edit_email').value = faculty.email || '';
    document.getElementById('edit_phone').value = faculty.phone || '';
    document.getElementById('edit_designation').value = faculty.designation || '';
    document.getElementById('edit_department').value = faculty.department || '';
    document.getElementById('edit_is_active').checked = faculty.is_active == 1;
    new bootstrap.Modal(document.getElementById('editFacultyModal')).show();
}

function deactivateFaculty(facultyId, facultyName) {
    showConfirm({
        title: 'Deactivate Faculty',
        message: `Deactivate <strong>${facultyName}</strong>? They will no longer appear in active faculty lists.`,
        type: 'warning',
        confirmText: 'Deactivate',
        cancelText: 'Cancel'
    }).then(confirmed => {
        if (!confirmed) return;

        // Show loading toast
        const loadingToast = toast.loading(`Deactivating ${facultyName}...`);

        fetch('<?php echo APP_URL; ?>/admin/faculty_action_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'deactivate', faculty_id: facultyId })
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(result => {
            // Remove loading toast
            toast.remove(loadingToast);
            
            if (result.success) {
                toast.deleted(`${facultyName} has been deactivated.`);
                const row = document.querySelector('tr[data-faculty-id="' + facultyId + '"]');
                if (row) {
                    row.style.transition = 'opacity 0.4s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 400);
                } else {
                    setTimeout(() => location.reload(), 1200);
                }
            } else {
                toast.error('Error: ' + (result.message || 'Could not deactivate faculty'));
            }
        })
        .catch(err => {
            toast.remove(loadingToast);
            toast.error('Request failed: ' + err.message);
        });
    });
}

function deleteFacultyPermanent(facultyId, facultyName) {
    showConfirm({
        title: 'Delete Faculty',
        message: `Permanently delete <strong>${facultyName}</strong>? This cannot be undone.`,
        type: 'danger',
        confirmText: 'Delete',
        cancelText: 'Cancel'
    }).then(confirmed => {
        if (!confirmed) return;

        fetch('<?php echo APP_URL; ?>/admin/faculty_action_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', faculty_id: facultyId })
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(result => {
            if (result.success) {
                toast.deleted(`${facultyName} has been permanently deleted.`);
                const row = document.querySelector(`tr[data-faculty-id="${facultyId}"]`);
                if (row) {
                    row.style.transition = 'opacity 0.4s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 400);
                } else {
                    setTimeout(() => location.reload(), 1200);
                }
            } else {
                showToast('Error: ' + (result.message || 'Could not delete faculty'), 'error');
            }
        })
        .catch(err => showToast('Request failed: ' + err.message, 'error'));
    });
}

function resendFacultyEmail(facultyId, facultyName, btn) {
    showConfirm({
        title: 'Send Email',
        message: `Send confirmation email to <strong>${facultyName}</strong>?`,
        type: 'info',
        confirmText: 'Send Email',
        cancelText: 'Cancel'
    }).then(confirmed => {
        if (!confirmed) return;

        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        btn.disabled = true;

        // Show loading toast
        const loadingToast = toast.loading(`Sending email to ${facultyName}...`);

        fetch('<?php echo APP_URL; ?>/batch_module/admin/resend_faculty_email_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'resend_email', faculty_id: facultyId })
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(result => {
            // Remove loading toast
            toast.remove(loadingToast);
            
            if (result.success) {
                btn.innerHTML = '<i class="fas fa-check"></i> Sent!';
                btn.classList.remove('btn-warning');
                btn.classList.add('btn-success');
                toast.success(`Email sent to ${facultyName} successfully!`);
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
    // Add Faculty Form
    const addForm = document.querySelector('#addFacultyModal form');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Faculty...';
            submitBtn.disabled = true;
            
            // Show loading toast
            const loadingToast = toast.loading('Adding faculty member...');
            
            // The form will submit normally, but we show the loading state
            setTimeout(() => {
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }, 1000);
        });
    }
    
    // Edit Faculty Form
    const editForm = document.querySelector('#editFacultyModal form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            
            // Show loading toast
            const loadingToast = toast.loading('Updating faculty member...');
            
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