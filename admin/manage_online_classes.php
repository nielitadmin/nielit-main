<?php
/**
 * Admin: Manage Online Classes
 * Schedule sessions with join links + optional Google Drive recording links.
 */
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/online_class_helper.php';

$role = $_SESSION['admin_role'] ?? '';
$blocked = in_array($role, ['nsqf_manager', 'front_office', 'placement_coordinator'], true);
if ($blocked) {
    $_SESSION['message'] = 'Access denied.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . relative_url('dashboard.php'));
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$active_theme = loadActiveTheme($conn);
ensureOnlineClassesTable($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $_SESSION['message'] = 'Invalid security token. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: manage_online_classes.php');
        exit();
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $editId = (int) ($_POST['id'] ?? 0);
        $payload = [
            'batch_id' => (int) ($_POST['batch_id'] ?? 0),
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'scheduled_at' => $_POST['scheduled_at'] ?? '',
            'duration_minutes' => (int) ($_POST['duration_minutes'] ?? 60),
            'meeting_url' => $_POST['meeting_url'] ?? '',
            'recording_url' => $_POST['recording_url'] ?? '',
            'platform' => $_POST['platform'] ?? '',
            'status' => ($_POST['status'] ?? 'scheduled') === 'cancelled' ? 'cancelled' : 'scheduled',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'created_by' => (string) ($_SESSION['admin'] ?? 'admin'),
        ];
        $result = saveOnlineClass($conn, $payload, $editId > 0 ? $editId : null);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        header('Location: manage_online_classes.php');
        exit();
    }

    if ($action === 'delete') {
        $deleteId = (int) ($_POST['id'] ?? 0);
        $result = deleteOnlineClass($conn, $deleteId);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        header('Location: manage_online_classes.php');
        exit();
    }
}

$filterBatch = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : 0;
$allBatchesForSelect = [];
$batchSql = "SELECT b.id, b.batch_name, b.batch_code, b.status, c.course_name
             FROM batches b
             LEFT JOIN courses c ON c.id = b.course_id
             ORDER BY b.status = 'Active' DESC, b.start_date DESC";
$batchRes = $conn->query($batchSql);
if ($batchRes) {
    while ($b = $batchRes->fetch_assoc()) {
        $allBatchesForSelect[] = $b;
    }
}

$classes = listOnlineClassesAdmin($conn, $filterBatch > 0 ? $filterBatch : null, 'all');

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Classes - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .oc-muted { color: #64748b; font-size: 0.875rem; }
        .oc-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .oc-link-truncate {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            vertical-align: bottom;
        }
        .oc-modal .form-group { margin-bottom: 1rem; }
        .oc-modal label { font-weight: 500; color: #334155; margin-bottom: 6px; display: block; }
        .oc-help { font-size: 0.8rem; color: #64748b; margin-top: 4px; }
        .badge-live { background: #dc2626; color: #fff; animation: oc-pulse 1.5s ease-in-out infinite; }
        @keyframes oc-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-video"></i> Online Classes</h4>
                <small>Schedule live sessions, share join links, and attach Google Drive recordings</small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role">Administrator</span>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr((string) $_SESSION['admin'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-main">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>

            <div class="content-card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <h5 class="card-title" style="margin:0;">
                        <i class="fas fa-list"></i> Scheduled Classes
                        <span class="oc-muted">(<?php echo count($classes); ?>)</span>
                    </h5>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <form method="get" style="margin:0;display:flex;gap:8px;align-items:center;">
                            <select name="batch_id" class="form-control" style="min-width:220px;" onchange="this.form.submit()">
                                <option value="0">All batches</option>
                                <?php foreach ($allBatchesForSelect as $b): ?>
                                    <option value="<?php echo (int) $b['id']; ?>" <?php echo $filterBatch === (int) $b['id'] ? 'selected' : ''; ?>>
                                        <?php
                                        echo htmlspecialchars(($b['batch_name'] ?? '') . ' (' . ($b['batch_code'] ?? '') . ')');
                                        if (!empty($b['course_name'])) {
                                            echo ' — ' . htmlspecialchars($b['course_name']);
                                        }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <button type="button" class="btn btn-primary" onclick="openClassModal()">
                            <i class="fas fa-plus"></i> Schedule Class
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table" id="onlineClassesTable">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Batch</th>
                                <th>When</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Join / Recording</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($classes)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted" style="padding:2rem;">
                                        No online classes yet. Click <strong>Schedule Class</strong> to create one.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($classes as $oc): ?>
                                    <?php
                                    $ds = $oc['display_status'] ?? 'upcoming';
                                    $badgeClass = onlineClassStatusBadgeClass($ds);
                                    if ($ds === 'live') {
                                        $badgeClass = 'badge-live';
                                    }
                                    $when = !empty($oc['scheduled_at'])
                                        ? date('d M Y, h:i A', strtotime($oc['scheduled_at']))
                                        : '—';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($oc['title'] ?? ''); ?></strong>
                                            <?php if (!empty($oc['platform'])): ?>
                                                <div class="oc-muted"><?php echo htmlspecialchars($oc['platform']); ?></div>
                                            <?php endif; ?>
                                            <?php if (empty($oc['is_active'])): ?>
                                                <span class="badge badge-secondary">Hidden</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($oc['batch_name'] ?? '—'); ?>
                                            <div class="oc-muted"><?php echo htmlspecialchars($oc['batch_code'] ?? ''); ?>
                                                <?php if (!empty($oc['course_name'])): ?>
                                                    · <?php echo htmlspecialchars($oc['course_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($when); ?></td>
                                        <td><?php echo (int) ($oc['duration_minutes'] ?? 60); ?> min</td>
                                        <td>
                                            <span class="badge <?php echo htmlspecialchars($badgeClass); ?>">
                                                <?php echo htmlspecialchars(onlineClassStatusLabel($ds)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($oc['meeting_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($oc['meeting_url']); ?>" target="_blank" rel="noopener noreferrer" class="oc-link-truncate" title="<?php echo htmlspecialchars($oc['meeting_url']); ?>">
                                                    <i class="fas fa-external-link-alt"></i> Join
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($oc['recording_url'])): ?>
                                                <div>
                                                    <a href="<?php echo htmlspecialchars($oc['recording_url']); ?>" target="_blank" rel="noopener noreferrer" class="oc-link-truncate" title="<?php echo htmlspecialchars($oc['recording_url']); ?>">
                                                        <i class="fas fa-play-circle"></i> Recording
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <div class="oc-muted">No recording yet</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="oc-actions">
                                                <button type="button" class="btn btn-sm btn-primary" title="Edit"
                                                        onclick='openClassModal(<?php echo json_encode($oc, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="post" style="margin:0;display:inline;" onsubmit="return confirm('Delete this online class?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int) $oc['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade oc-modal" id="classModal" tabindex="-1" role="dialog" aria-labelledby="classModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" id="classForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="oc_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title" id="classModalTitle">Schedule Online Class</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                    <div class="form-row" style="display:flex;gap:16px;flex-wrap:wrap;">
                        <div class="form-group" style="flex:1;min-width:220px;">
                            <label for="oc_title">Class Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="oc_title" name="title" required maxlength="255" placeholder="e.g. Module 3 — Networking Basics">
                        </div>
                        <div class="form-group" style="flex:1;min-width:220px;">
                            <label for="oc_batch_id">Batch <span class="text-danger">*</span></label>
                            <select class="form-control" id="oc_batch_id" name="batch_id" required>
                                <option value="">Select batch…</option>
                                <?php foreach ($allBatchesForSelect as $b): ?>
                                    <option value="<?php echo (int) $b['id']; ?>">
                                        <?php
                                        echo htmlspecialchars(($b['batch_name'] ?? '') . ' (' . ($b['batch_code'] ?? '') . ')');
                                        if (!empty($b['course_name'])) {
                                            echo ' — ' . htmlspecialchars($b['course_name']);
                                        }
                                        if (($b['status'] ?? '') !== 'Active') {
                                            echo ' [' . htmlspecialchars($b['status'] ?? '') . ']';
                                        }
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" style="display:flex;gap:16px;flex-wrap:wrap;">
                        <div class="form-group" style="flex:1;min-width:200px;">
                            <label for="oc_scheduled_at">Date &amp; Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="oc_scheduled_at" name="scheduled_at" required>
                        </div>
                        <div class="form-group" style="width:140px;">
                            <label for="oc_duration">Duration (min)</label>
                            <input type="number" class="form-control" id="oc_duration" name="duration_minutes" value="60" min="15" max="480" step="15">
                        </div>
                        <div class="form-group" style="flex:1;min-width:160px;">
                            <label for="oc_platform">Platform (optional)</label>
                            <input type="text" class="form-control" id="oc_platform" name="platform" maxlength="50" placeholder="Zoom / Meet / Jitsi…">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="oc_meeting_url">Meeting / Join Link <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="oc_meeting_url" name="meeting_url" required placeholder="https://meet.google.com/... or Zoom / Jitsi link">
                        <div class="oc-help">Paste any meeting URL. Students will open this to join live.</div>
                    </div>

                    <div class="form-group">
                        <label for="oc_recording_url">Recording Link (Google Drive)</label>
                        <input type="url" class="form-control" id="oc_recording_url" name="recording_url" placeholder="https://drive.google.com/file/d/...">
                        <div class="oc-help">After class, paste the Drive sharing link. Leave blank until the recording is ready.</div>
                    </div>

                    <div class="form-group">
                        <label for="oc_description">Notes (optional)</label>
                        <textarea class="form-control" id="oc_description" name="description" rows="3" placeholder="Topics, prep materials, instructions…"></textarea>
                    </div>

                    <div class="form-row" style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
                        <div class="form-group" style="margin:0;">
                            <label for="oc_status">Manual status</label>
                            <select class="form-control" id="oc_status" name="status" style="min-width:160px;">
                                <option value="scheduled">Scheduled (auto upcoming/live/done)</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;padding-top:22px;">
                            <label style="font-weight:500;display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" id="oc_is_active" name="is_active" value="1" checked>
                                Visible to students
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toDatetimeLocal(mysqlDt) {
    if (!mysqlDt) return '';
    // "YYYY-MM-DD HH:MM:SS" → "YYYY-MM-DDTHH:MM"
    var s = String(mysqlDt).replace(' ', 'T');
    if (s.length >= 16) return s.substring(0, 16);
    return s;
}

function openClassModal(row) {
    var form = document.getElementById('classForm');
    form.reset();
    document.getElementById('oc_id').value = '0';
    document.getElementById('oc_is_active').checked = true;
    document.getElementById('oc_status').value = 'scheduled';
    document.getElementById('oc_duration').value = '60';
    document.getElementById('classModalTitle').textContent = 'Schedule Online Class';

    if (row && row.id) {
        document.getElementById('classModalTitle').textContent = 'Edit Online Class';
        document.getElementById('oc_id').value = row.id;
        document.getElementById('oc_title').value = row.title || '';
        document.getElementById('oc_batch_id').value = row.batch_id || '';
        document.getElementById('oc_scheduled_at').value = toDatetimeLocal(row.scheduled_at);
        document.getElementById('oc_duration').value = row.duration_minutes || 60;
        document.getElementById('oc_platform').value = row.platform || '';
        document.getElementById('oc_meeting_url').value = row.meeting_url || '';
        document.getElementById('oc_recording_url').value = row.recording_url || '';
        document.getElementById('oc_description').value = row.description || '';
        document.getElementById('oc_status').value = (row.status === 'cancelled') ? 'cancelled' : 'scheduled';
        document.getElementById('oc_is_active').checked = String(row.is_active) === '1' || row.is_active === true || row.is_active === 1;
    }

    if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery('#classModal').modal('show');
    } else {
        var el = document.getElementById('classModal');
        el.style.display = 'block';
        el.classList.add('show');
    }
}
</script>
</body>
</html>
