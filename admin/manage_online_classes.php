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
require_once __DIR__ . '/../includes/teaching_access.php';

$role = $_SESSION['admin_role'] ?? '';
$blocked = in_array($role, ['nsqf_course_manager', 'front_office_desk', 'placement_coordinator', 'faculty'], true);
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
ensureOnlineClassVideoSettingsTable($conn);

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
            'recording_url' => $_POST['recording_url'] ?? '',
            'platform' => $_POST['platform'] ?? 'NIELIT Classroom',
            'status' => ($_POST['status'] ?? 'scheduled') === 'cancelled' ? 'cancelled' : 'scheduled',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'created_by' => (string) ($_SESSION['admin'] ?? 'admin'),
        ];
        $result = saveOnlineClass($conn, $payload, $editId > 0 ? $editId : null);
        $_SESSION['message'] = $result['message'];
        if (!empty($result['join_url'])) {
            $_SESSION['message'] .= ' Join link: ' . $result['join_url'];
        }
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

    if ($action === 'save_video_settings') {
        $result = saveOnlineClassVideoSettings($conn, [
            'provider' => $_POST['video_provider'] ?? 'official',
            'custom_domain' => $_POST['custom_domain'] ?? '',
            'video_mode' => $_POST['video_mode'] ?? 'open',
            'jwt_enabled' => isset($_POST['jwt_enabled']) ? 1 : 0,
            'jwt_app_id' => $_POST['jwt_app_id'] ?? '',
            'jwt_app_secret' => $_POST['jwt_app_secret'] ?? '',
        ], (string) ($_SESSION['admin'] ?? 'admin'));
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
$jitsiStatus = onlineClassJitsiStatus();
$videoSettings = onlineClassGetVideoSettings($conn);
$videoProviderOptions = onlineClassVideoProviderOptions();

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
        .oc-actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .oc-link-truncate {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
            margin-top: 4px;
        }
        .oc-modal .form-group { margin-bottom: 1rem; }
        .oc-modal label { font-weight: 500; color: #334155; margin-bottom: 6px; display: block; }
        .oc-help { font-size: 0.8rem; color: #64748b; margin-top: 4px; }
        .badge-live { background: #dc2626; color: #fff; animation: oc-pulse 1.5s ease-in-out infinite; }
        @keyframes oc-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        #onlineClassesTable th,
        #onlineClassesTable td { white-space: nowrap; }
        #onlineClassesTable td.oc-wrap { white-space: normal; min-width: 140px; }
        #onlineClassesTable td.oc-link-cell { white-space: normal; min-width: 180px; }
        .oc-settings-card { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 8px 24px rgba(15,23,42,.05); }
        .oc-provider-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .oc-provider-option {
            border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; cursor: pointer;
            transition: border-color .15s, box-shadow .15s; background: #f8fafc;
        }
        .oc-provider-option.is-selected { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 1px #2563eb; }
        .oc-provider-option input { margin-right: 8px; }
        .oc-provider-option strong { display: block; margin-bottom: 4px; color: #0f172a; }
        .oc-provider-option span { font-size: .82rem; color: #64748b; line-height: 1.35; }
        .oc-settings-meta { font-size: .85rem; color: #64748b; }
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

            <div class="oc-settings-card">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h5 style="margin:0 0 4px;"><i class="fas fa-sliders-h"></i> Video Server Controls</h5>
                        <div class="oc-settings-meta">
                            Active: <strong><?php echo htmlspecialchars($jitsiStatus['provider_label']); ?></strong>
                            <?php if ($jitsiStatus['video_enabled'] && $jitsiStatus['base_url'] !== ''): ?>
                                · <code><?php echo htmlspecialchars($jitsiStatus['base_url']); ?></code>
                            <?php endif; ?>
                            · Mode: <strong><?php echo htmlspecialchars($jitsiStatus['video_mode']); ?></strong>
                            · JWT: <strong><?php echo $jitsiStatus['jwt_enabled'] ? 'On' : 'Off'; ?></strong>
                            <?php if (!empty($jitsiStatus['updated_at'])): ?>
                                · Updated <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($jitsiStatus['updated_at']))); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($jitsiStatus['video_enabled'] && $jitsiStatus['base_url'] !== ''): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($jitsiStatus['base_url']); ?>" target="_blank" rel="noopener">
                            <i class="fas fa-external-link-alt"></i> Open Jitsi
                        </a>
                    <?php endif; ?>
                </div>

                <form method="post" id="videoSettingsForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="save_video_settings">

                    <label class="font-weight-bold mb-2 d-block">Choose video backend</label>
                    <div class="oc-provider-grid mb-3">
                        <?php foreach ($videoProviderOptions as $key => $opt): ?>
                            <label class="oc-provider-option <?php echo ($videoSettings['provider'] ?? '') === $key ? 'is-selected' : ''; ?>">
                                <input type="radio" name="video_provider" value="<?php echo htmlspecialchars($key); ?>"
                                    <?php echo ($videoSettings['provider'] ?? 'official') === $key ? 'checked' : ''; ?>>
                                <strong><?php echo htmlspecialchars($opt['label']); ?></strong>
                                <span><?php echo htmlspecialchars($opt['description']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-row" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;">
                        <div class="form-group" id="customDomainWrap" style="flex:1;min-width:240px;display:none;">
                            <label for="oc_custom_domain">Custom Jitsi domain</label>
                            <input type="text" class="form-control" id="oc_custom_domain" name="custom_domain"
                                   value="<?php echo htmlspecialchars($videoSettings['custom_domain'] ?? ''); ?>"
                                   placeholder="meet.example.com">
                        </div>
                        <div class="form-group" style="min-width:180px;">
                            <label for="oc_video_mode">Join style</label>
                            <select class="form-control" id="oc_video_mode" name="video_mode">
                                <option value="open" <?php echo ($videoSettings['video_mode'] ?? 'open') === 'open' ? 'selected' : ''; ?>>
                                    Full-page (open)
                                </option>
                                <option value="embed" <?php echo ($videoSettings['video_mode'] ?? '') === 'embed' ? 'selected' : ''; ?>>
                                    Embedded in portal
                                </option>
                            </select>
                        </div>
                        <div class="form-group" id="jwtToggleWrap" style="padding-bottom:8px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                                <input type="checkbox" name="jwt_enabled" id="jwt_enabled" value="1"
                                    <?php echo !empty($videoSettings['jwt_enabled']) ? 'checked' : ''; ?>>
                                Enable JWT (secure rooms)
                            </label>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Video Settings
                            </button>
                        </div>
                    </div>

                    <div id="jwtFieldsWrap" class="form-row mt-2" style="display:none;gap:16px;flex-wrap:wrap;">
                        <div class="form-group" style="min-width:200px;">
                            <label for="jwt_app_id">JWT App ID</label>
                            <input type="text" class="form-control" id="jwt_app_id" name="jwt_app_id"
                                   value="<?php echo htmlspecialchars($videoSettings['jwt_app_id'] ?? 'nielit_portal'); ?>"
                                   placeholder="nielit_portal">
                            <div class="oc-help">Must match <code>JWT_APP_ID</code> in Jitsi server <code>.env</code></div>
                        </div>
                        <div class="form-group" style="flex:1;min-width:280px;">
                            <label for="jwt_app_secret">JWT App Secret</label>
                            <input type="password" class="form-control" id="jwt_app_secret" name="jwt_app_secret"
                                   value=""
                                   placeholder="<?php echo !empty($jitsiStatus['jwt_secret_configured']) ? 'Saved — leave blank to keep current secret' : 'Paste JWT_APP_SECRET from Jitsi server .env'; ?>"
                                   autocomplete="new-password">
                            <div class="oc-help">Same value as <code>JWT_APP_SECRET</code> in <code>/opt/jitsi-meet/.env</code></div>
                        </div>
                    </div>

                    <?php if (!empty($videoSettings['jwt_enabled']) && empty($jitsiStatus['jwt_secret_configured'])): ?>
                        <div class="alert alert-warning mt-2 mb-0" style="font-size:.9rem;">
                            JWT is enabled but the secret is not saved yet. Paste your
                            <code>JWT_APP_SECRET</code> from the Jitsi server <code>.env</code> above and click Save.
                        </div>
                    <?php elseif (!empty($videoSettings['jwt_enabled']) && !empty($jitsiStatus['jwt_secret_configured'])): ?>
                        <div class="alert alert-success mt-2 mb-0" style="font-size:.9rem;">
                            <i class="fas fa-check-circle"></i> JWT is configured and active.
                        </div>
                    <?php endif; ?>
                </form>
            </div>

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
                    <table class="modern-table" id="onlineClassesTable">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Batch</th>
                                <th>When</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Site Join Link</th>
                                <th>Recording</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($classes)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted" style="padding:2rem;white-space:normal;">
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
                                    $joinUrl = (string) ($oc['join_url'] ?? $oc['meeting_url'] ?? '');
                                    $jitsiUrl = (string) ($oc['jitsi_room_url'] ?? '');
                                    ?>
                                    <tr>
                                        <td class="oc-wrap">
                                            <strong><?php echo htmlspecialchars($oc['title'] ?? ''); ?></strong>
                                            <?php if (!empty($oc['platform'])): ?>
                                                <div class="oc-muted"><?php echo htmlspecialchars($oc['platform']); ?></div>
                                            <?php endif; ?>
                                            <?php if (empty($oc['is_active'])): ?>
                                                <span class="badge badge-secondary">Hidden</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="oc-wrap">
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
                                        <td class="oc-link-cell">
                                            <?php if ($joinUrl !== ''): ?>
                                                <div class="oc-actions">
                                                    <a href="<?php echo htmlspecialchars($joinUrl); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primary" title="Open classroom">
                                                        <i class="fas fa-video"></i> Open
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-secondary" title="Copy link"
                                                            onclick="navigator.clipboard.writeText(<?php echo htmlspecialchars(json_encode($joinUrl), ENT_QUOTES); ?>).then(function(){alert('Join link copied');})">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                                <span class="oc-muted oc-link-truncate" title="<?php echo htmlspecialchars($joinUrl); ?>">
                                                    <?php echo htmlspecialchars($joinUrl); ?>
                                                </span>
                                                <?php if ($jitsiUrl !== ''): ?>
                                                    <div class="oc-muted oc-link-truncate mt-1" title="<?php echo htmlspecialchars($jitsiUrl); ?>">
                                                        Jitsi: <?php echo htmlspecialchars($jitsiUrl); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="oc-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($oc['recording_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($oc['recording_url']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-secondary" title="Open recording">
                                                    <i class="fas fa-play-circle"></i> Recording
                                                </a>
                                            <?php else: ?>
                                                <span class="oc-muted">—</span>
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
                            <label for="oc_platform">Label (optional)</label>
                            <input type="text" class="form-control" id="oc_platform" name="platform" maxlength="50" value="NIELIT Classroom" placeholder="NIELIT Classroom">
                        </div>
                    </div>

                    <div class="form-group" id="oc_join_link_wrap" style="display:none;">
                        <label>Site Join Link (auto-generated)</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" class="form-control" id="oc_join_url_display" readonly>
                            <button type="button" class="btn btn-secondary" onclick="copyJoinLink()"><i class="fas fa-copy"></i></button>
                            <a class="btn btn-success" id="oc_open_join" href="#" target="_blank"><i class="fas fa-video"></i></a>
                        </div>
                        <div class="oc-help">Students join through your website — no Zoom/Meet link to paste.</div>
                    </div>

                    <div class="alert alert-info" id="oc_new_link_hint" style="font-size:0.9rem;">
                        <i class="fas fa-magic"></i>
                        When you save, a unique classroom link is created on this site automatically
                        (e.g. <code>…/student/join_class?t=…</code>).
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
function syncVideoSettingsUi() {
    var provider = document.querySelector('input[name="video_provider"]:checked');
    var value = provider ? provider.value : 'official';
    var customWrap = document.getElementById('customDomainWrap');
    var jwtWrap = document.getElementById('jwtToggleWrap');
    var jwtFieldsWrap = document.getElementById('jwtFieldsWrap');
    var jwtEnabledInput = document.getElementById('jwt_enabled');
    var modeSelect = document.getElementById('oc_video_mode');

    document.querySelectorAll('.oc-provider-option').forEach(function (el) {
        var input = el.querySelector('input[type="radio"]');
        el.classList.toggle('is-selected', input && input.checked);
    });

    if (customWrap) {
        customWrap.style.display = value === 'custom' ? 'block' : 'none';
    }
    if (jwtWrap) {
        jwtWrap.style.display = (value === 'official' || value === 'disabled') ? 'none' : 'block';
    }
    if (jwtFieldsWrap) {
        var jwtOn = jwtEnabledInput && jwtEnabledInput.checked;
        jwtFieldsWrap.style.display = (jwtOn && value !== 'official' && value !== 'disabled') ? 'flex' : 'none';
    }
    if (modeSelect) {
        if (value === 'official' || value === 'disabled') {
            modeSelect.value = 'open';
            modeSelect.disabled = true;
        } else {
            modeSelect.disabled = false;
        }
    }
}

document.querySelectorAll('input[name="video_provider"]').forEach(function (radio) {
    radio.addEventListener('change', syncVideoSettingsUi);
});
var jwtEnabledEl = document.getElementById('jwt_enabled');
if (jwtEnabledEl) {
    jwtEnabledEl.addEventListener('change', syncVideoSettingsUi);
}
syncVideoSettingsUi();

function toDatetimeLocal(mysqlDt) {
    if (!mysqlDt) return '';
    // "YYYY-MM-DD HH:MM:SS" → "YYYY-MM-DDTHH:MM"
    var s = String(mysqlDt).replace(' ', 'T');
    if (s.length >= 16) return s.substring(0, 16);
    return s;
}

function copyJoinLink() {
    var el = document.getElementById('oc_join_url_display');
    if (!el || !el.value) return;
    navigator.clipboard.writeText(el.value).then(function () { alert('Join link copied'); });
}

function openClassModal(row) {
    var form = document.getElementById('classForm');
    form.reset();
    document.getElementById('oc_id').value = '0';
    document.getElementById('oc_is_active').checked = true;
    document.getElementById('oc_status').value = 'scheduled';
    document.getElementById('oc_duration').value = '60';
    document.getElementById('oc_platform').value = 'NIELIT Classroom';
    document.getElementById('classModalTitle').textContent = 'Schedule Online Class';
    document.getElementById('oc_join_link_wrap').style.display = 'none';
    document.getElementById('oc_new_link_hint').style.display = 'block';

    if (row && row.id) {
        document.getElementById('classModalTitle').textContent = 'Edit Online Class';
        document.getElementById('oc_id').value = row.id;
        document.getElementById('oc_title').value = row.title || '';
        document.getElementById('oc_batch_id').value = row.batch_id || '';
        document.getElementById('oc_scheduled_at').value = toDatetimeLocal(row.scheduled_at);
        document.getElementById('oc_duration').value = row.duration_minutes || 60;
        document.getElementById('oc_platform').value = row.platform || 'NIELIT Classroom';
        document.getElementById('oc_recording_url').value = row.recording_url || '';
        document.getElementById('oc_description').value = row.description || '';
        document.getElementById('oc_status').value = (row.status === 'cancelled') ? 'cancelled' : 'scheduled';
        document.getElementById('oc_is_active').checked = String(row.is_active) === '1' || row.is_active === true || row.is_active === 1;

        var joinUrl = row.join_url || row.meeting_url || '';
        if (joinUrl) {
            document.getElementById('oc_join_link_wrap').style.display = 'block';
            document.getElementById('oc_new_link_hint').style.display = 'none';
            document.getElementById('oc_join_url_display').value = joinUrl;
            document.getElementById('oc_open_join').href = joinUrl;
        }
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
