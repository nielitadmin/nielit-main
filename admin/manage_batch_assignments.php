<?php
session_start();

$is_logged_in = isset($_SESSION['admin_logged_in']) || isset($_SESSION['admin']);
if (!$is_logged_in) {
    header('Location: login.php');
    exit();
}
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'master_admin') {
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/batch_assignment_helper.php';

batch_assignment_ensure_schema($conn);
$active_theme = loadActiveTheme($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_assignment_id'])) {
    $removeId = (int) $_POST['remove_assignment_id'];
    $removed = $conn->prepare('UPDATE admin_batch_assignments SET is_active = 0 WHERE id = ? AND is_active = 1');
    $ok = false;
    if ($removed && $removeId > 0) {
        $removed->bind_param('i', $removeId);
        $ok = $removed->execute() && $removed->affected_rows > 0;
        $removed->close();
    }
    $_SESSION['batch_assignment_flash'] = $ok ? 'Batch assignment removed.' : 'Assignment was already removed.';
    $_SESSION['batch_assignment_flash_type'] = $ok ? 'assignment' : 'warning';
    header('Location: manage_batch_assignments.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_ids'])) {
    $saved = batch_assignment_save($conn, (int) ($_POST['admin_id'] ?? 0), (array) $_POST['batch_ids']);
    $_SESSION['batch_assignment_flash'] = $saved['message'] ?? '';
    $_SESSION['batch_assignment_flash_type'] = $saved['type'] ?? 'info';
    header('Location: manage_batch_assignments.php');
    exit();
}
$assignmentFlash = $_SESSION['batch_assignment_flash'] ?? '';
$assignmentFlashType = $_SESSION['batch_assignment_flash_type'] ?? 'info';
unset($_SESSION['batch_assignment_flash'], $_SESSION['batch_assignment_flash_type']);
$savedAssignments = batch_assignment_rows($conn);

$coordinators_result = $conn->query("SELECT id, username, email FROM admin WHERE role = 'course_coordinator' AND is_active = 1 ORDER BY username");
$batches_result = $conn->query("SELECT b.id, b.batch_name, b.batch_code, COALESCE(c.course_name, '') AS course_name
    FROM batches b
    LEFT JOIN courses c ON b.course_id = c.id
    ORDER BY c.course_name, b.batch_name");
$stats = batch_assignment_stats($conn);

$coordinators = [];
if ($coordinators_result) {
    while ($row = $coordinators_result->fetch_assoc()) {
        $coordinators[] = $row;
    }
}
$batches = [];
if ($batches_result) {
    while ($row = $batches_result->fetch_assoc()) {
        $batches[] = $row;
    }
} elseif ($conn->error) {
    $fallback = $conn->query("SELECT id, batch_name, batch_code FROM batches ORDER BY batch_name");
    if ($fallback) {
        while ($row = $fallback->fetch_assoc()) {
            $row['course_name'] = '';
            $row['status'] = '';
            $batches[] = $row;
        }
    }
}
$selected_admin_id = (int) ($_GET['admin_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Assignments - NIELIT Admin</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        :root {
            --ca-primary: var(--primary-color, #0c2340);
            --ca-secondary: var(--secondary-color, #123a66);
            --ca-gradient: linear-gradient(135deg, var(--ca-primary) 0%, var(--ca-secondary) 100%);
        }
        .modern-card {
            background: var(--ca-gradient);
            border: none;
            border-radius: 16px;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .stats-card {
            background: var(--bg-card, #fff);
            border-radius: 14px;
            padding: 1.5rem;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(15, 23, 42, 0.06);
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
        .icon-info { background: linear-gradient(135deg, var(--ca-secondary) 0%, #38bdf8 100%); }
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
        }
        .badge-modern {
            padding: 0.4rem 0.85rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
        }
        .badge-manual { background: linear-gradient(135deg, var(--ca-secondary) 0%, #38bdf8 100%); color: #fff; }
        #assignBatchesModal .modal-header {
            background: var(--ca-gradient);
            color: #fff;
            border: none;
        }
        #batchesContainer {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            padding: 1rem;
            border-radius: 0.375rem;
        }
        .empty-state { text-align: center; padding: 3rem; color: #94a3b8; }
        .empty-state i { font-size: 3.5rem; margin-bottom: 1rem; opacity: 0.35; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-layer-group"></i> Batch Assignments</h4>
                <small>Give course coordinators access to specific batches</small>
            </div>
            <div class="topbar-right">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignBatchesModal">
                    <i class="fas fa-plus"></i> Assign Batches
                </button>
            </div>
        </div>
        <div class="admin-main">
            <div class="content-body">
                <?php if ($assignmentFlash !== ''): ?>
                <div class="alert alert-<?php echo $assignmentFlashType === 'error' ? 'danger' : ($assignmentFlashType === 'warning' ? 'warning' : 'success'); ?>">
                    <?php echo htmlspecialchars($assignmentFlash); ?>
                </div>
                <?php endif; ?>
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stats-number" id="statCoordinators"><?php echo (int) ($stats['total_coordinators_with_assignments'] ?? 0); ?></div>
                                    <div class="stats-label">Coordinators</div>
                                    <small class="text-muted">With batch assignments</small>
                                </div>
                                <div class="stats-icon icon-primary"><i class="fas fa-user-tie"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stats-number" id="statAssignments"><?php echo (int) ($stats['total_assignments'] ?? 0); ?></div>
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
                                    <div class="stats-number" id="statBatches"><?php echo (int) ($stats['total_batches_assigned'] ?? 0); ?></div>
                                    <div class="stats-label">Batches Assigned</div>
                                    <small class="text-muted">Unique batches</small>
                                </div>
                                <div class="stats-icon icon-info"><i class="fas fa-layer-group"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modern-table content-card">
                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Current Batch Assignments</h5>
                        <button class="btn btn-outline-primary btn-sm" type="button" onclick="refreshAssignments()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <div class="table-responsive">
                        <div id="assignments-table-container">
                            <?php if (!$savedAssignments): ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard-list"></i>
                                <h5>No Batch Assignments Found</h5>
                                <p>Use Assign Batches to give a coordinator access to a batch.</p>
                            </div>
                            <?php else: ?>
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Coordinator</th>
                                    <th>Batch</th>
                                    <th>Course</th>
                                    <th>Type</th>
                                    <th>Assigned By</th>
                                    <th>Assigned Date</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($savedAssignments as $savedRow): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($savedRow['admin_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($savedRow['admin_email']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($savedRow['batch_name']); ?></strong>
                                        <?php if (!empty($savedRow['batch_code'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($savedRow['batch_code']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($savedRow['course_name'] ?: '—'); ?></td>
                                    <td><span class="badge-modern badge-manual"><?php echo htmlspecialchars($savedRow['assignment_type'] ?: 'Manual'); ?></span></td>
                                    <td><?php echo htmlspecialchars($savedRow['assigned_by_name'] ?: 'System'); ?></td>
                                    <td><?php echo htmlspecialchars($savedRow['assigned_at'] ?: ''); ?></td>
                                    <td>
                                        <form method="post" action="manage_batch_assignments.php" class="d-inline">
                                            <input type="hidden" name="remove_assignment_id" value="<?php echo (int) $savedRow['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-times"></i> Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="modern-card mt-4">
                    <div class="card-body">
                        <h6><i class="fas fa-lightbulb"></i> How Batch Assignments Work:</h6>
                        <ul class="mb-0">
                            <li><strong>Course coordinators</strong> see batches they created, plus any batch you assign here.</li>
                            <li><strong>Master admins</strong> still see every batch.</li>
                            <li>Assigning a batch does not move students. It only opens that batch for the coordinator.</li>
                            <li>Removing an assignment hides that batch again, unless the coordinator created it.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="assignBatchesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Batches to Coordinator</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignBatchesForm" method="post" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Course Coordinator *</label>
                        <select name="admin_id" class="form-select" required id="coordinatorSelect">
                            <option value="">-- Select Coordinator --</option>
                            <?php foreach ($coordinators as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>" <?php echo $selected_admin_id === (int) $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['username']); ?> (<?php echo htmlspecialchars($c['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Batches to Assign *</label>
                        <div id="batchesContainer">
                            <div class="text-muted text-center py-3" id="selectCoordinatorFirst" style="display:none;"></div>
                            <?php foreach ($batches as $batch): ?>
                                <div class="form-check mb-2 batch-option" data-batch-id="<?php echo (int) $batch['id']; ?>">
                                    <input class="form-check-input" type="checkbox" name="batch_ids[]" value="<?php echo (int) $batch['id']; ?>" id="batch_<?php echo (int) $batch['id']; ?>">
                                    <label class="form-check-label" for="batch_<?php echo (int) $batch['id']; ?>">
                                        <strong><?php echo htmlspecialchars($batch['batch_name']); ?></strong>
                                        <?php if (!empty($batch['batch_code'])): ?>
                                            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($batch['batch_code']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($batch['course_name'])): ?>
                                            <span class="text-muted ms-1"><?php echo htmlspecialchars($batch['course_name']); ?></span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$batches): ?>
                                <div class="text-muted">No batches have been created yet.</div>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-info-circle"></i> Batches already assigned to the selected coordinator stay hidden.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="assignSubmit">Assign Batches</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
var assignedByAdmin = {};

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function toast(kind, message) {
    if (typeof ToastNotification !== 'undefined') {
        var t = new ToastNotification();
        (t[kind] || t.info).call(t, message);
        return;
    }
    window.alert(message);
}

function loadBatchesForCoordinator(adminId) {
    var hint = document.getElementById('selectCoordinatorFirst');
    var options = document.querySelectorAll('.batch-option');
    var taken = assignedByAdmin[String(adminId)] || [];
    var visible = 0;
    if (!options.length) {
        hint.style.display = 'block';
        hint.innerHTML = 'No batches have been created yet.';
        return;
    }
    options.forEach(function (option) {
        var input = option.querySelector('input');
        var id = parseInt(option.getAttribute('data-batch-id'), 10);
        var show = !adminId || taken.indexOf(id) === -1;
        option.style.display = show ? '' : 'none';
        if (!show && input) {
            input.checked = false;
        }
        if (show) {
            visible++;
        }
    });
    if (adminId && visible === 0) {
        hint.style.display = 'block';
        hint.innerHTML = '<i class="fas fa-check"></i> Every batch is already assigned to this coordinator';
        return;
    }
    hint.style.display = 'none';
}

function renderAssignments(assignments) {
    assignedByAdmin = {};
    assignments.forEach(function (row) {
        var key = String(row.admin_id);
        if (!assignedByAdmin[key]) {
            assignedByAdmin[key] = [];
        }
        assignedByAdmin[key].push(parseInt(row.batch_id, 10));
    });
    loadBatchesForCoordinator(document.getElementById('coordinatorSelect').value);

    var container = document.getElementById('assignments-table-container');
    if (!assignments.length) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-clipboard-list"></i><h5>No Batch Assignments Found</h5><p>Use Assign Batches to give a coordinator access to a batch.</p></div>';
        return;
    }
    var html = '<table class="table"><thead><tr><th>Coordinator</th><th>Batch</th><th>Course</th><th>Type</th><th>Assigned By</th><th>Assigned Date</th><th>Actions</th></tr></thead><tbody>';
    assignments.forEach(function (row) {
        var when = row.assigned_at ? new Date(String(row.assigned_at).replace(' ', 'T')) : null;
        var dateText = when && !isNaN(when.getTime())
            ? when.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
            : escapeHtml(row.assigned_at || '');
        var code = row.batch_code ? ' <span class="badge bg-secondary">' + escapeHtml(row.batch_code) + '</span>' : '';
        html += '<tr>'
            + '<td><strong>' + escapeHtml(row.admin_name) + '</strong><br><small class="text-muted">' + escapeHtml(row.admin_email) + '</small></td>'
            + '<td><strong>' + escapeHtml(row.batch_name) + '</strong>' + code + '</td>'
            + '<td>' + escapeHtml(row.course_name || '—') + '</td>'
            + '<td><span class="badge-modern badge-manual">' + escapeHtml(row.assignment_type || 'Manual') + '</span></td>'
            + '<td>' + escapeHtml(row.assigned_by_name || 'System') + '</td>'
            + '<td>' + dateText + '</td>'
            + '<td><form method="post" action="manage_batch_assignments.php" class="d-inline"><input type="hidden" name="remove_assignment_id" value="' + parseInt(row.id, 10) + '"><button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i> Remove</button></form></td>'
            + '</tr>';
    });
    html += '</tbody></table>';
    container.innerHTML = html;
}

function refreshStats() {
    var body = new URLSearchParams();
    body.set('action', 'get_stats');
    fetch('ajax_batch_assignments.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.stats) {
                return;
            }
            document.getElementById('statCoordinators').textContent = data.stats.total_coordinators_with_assignments || 0;
            document.getElementById('statAssignments').textContent = data.stats.total_assignments || 0;
            document.getElementById('statBatches').textContent = data.stats.total_batches_assigned || 0;
        })
        .catch(function () {});
}

function refreshAssignments() {
    var body = new URLSearchParams();
    body.set('action', 'get_assignments');
    fetch('ajax_batch_assignments.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                toast('error', data.message || 'Could not load assignments');
                return;
            }
            renderAssignments(data.assignments || []);
        })
        .catch(function () {
            toast('error', 'Network error while loading assignments');
        });
}

function removeAssignment(id) {
    if (!window.confirm('Remove this batch assignment?')) {
        return;
    }
    var body = new URLSearchParams();
    body.set('action', 'remove_assignment');
    body.set('assignment_id', String(id));
    fetch('ajax_batch_assignments.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            toast(data.success ? 'success' : 'error', data.message || 'Could not remove assignment');
            if (data.success) {
                refreshAssignments();
                refreshStats();
            }
        })
        .catch(function () {
            toast('error', 'Network error while removing the assignment');
        });
}

document.getElementById('coordinatorSelect').addEventListener('change', function () {
    loadBatchesForCoordinator(this.value);
});
var assignModal = document.getElementById('assignBatchesModal');
if (assignModal) {
    assignModal.addEventListener('shown.bs.modal', function () {
        loadBatchesForCoordinator(document.getElementById('coordinatorSelect').value);
    });
}
loadBatchesForCoordinator(document.getElementById('coordinatorSelect').value);

document.getElementById('assignBatchesForm').addEventListener('submit', function (event) {
    event.preventDefault();
    var form = event.target;
    var selected = form.querySelectorAll('input[name="batch_ids[]"]:checked');
    if (!form.admin_id.value || selected.length === 0) {
        toast('warning', 'Select a coordinator and at least one batch.');
        return;
    }
    var button = document.getElementById('assignSubmit');
    button.disabled = true;
    var body = new FormData(form);
    body.append('action', 'assign_batches');
    fetch('ajax_batch_assignments.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var kind = data.type === 'error' ? 'error' : (data.type === 'warning' ? 'warning' : (data.success ? 'success' : 'info'));
            toast(kind, data.message || 'Assignment finished');
            if (data.success) {
                form.reset();
                loadBatchesForCoordinator('');
                var modalEl = document.getElementById('assignBatchesModal');
                if (window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                refreshAssignments();
                refreshStats();
            }
        })
        .catch(function () {
            toast('error', 'Network error while assigning batches');
        })
        .finally(function () {
            button.disabled = false;
        });
});

refreshAssignments();
</script>
</body>
</html>
