<?php
/**
 * Admin support tickets.
 * All admins can raise tickets. Master Admin sees the pending inbox and can reply.
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
require_once __DIR__ . '/../includes/session_manager.php';
require_once __DIR__ . '/../includes/support_ticket_helper.php';

$isMaster = admin_can_manage_support_tickets();
$adminUser = (string) ($_SESSION['admin'] ?? '');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$active_theme = loadActiveTheme($conn);
ensureSupportTicketsTable($conn);

$filterStatus = strtolower(trim((string) ($_GET['status'] ?? ($isMaster ? 'pending' : 'all'))));
$allowedFilters = ['pending', 'all', 'open', 'in_progress', 'resolved', 'closed'];
if (!in_array($filterStatus, $allowedFilters, true)) {
    $filterStatus = $isMaster ? 'pending' : 'all';
}
$filterType = strtolower(trim((string) ($_GET['type'] ?? 'all')));
if (!in_array($filterType, ['all', 'student', 'admin'], true)) {
    $filterType = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $redirect = 'manage_support_tickets.php?' . http_build_query(array_filter([
        'status' => $filterStatus !== 'all' ? $filterStatus : null,
        'type' => $filterType !== 'all' ? $filterType : null,
    ]));
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $_SESSION['message'] = 'Invalid security token. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . $redirect);
        exit();
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $result = createSupportTicket($conn, [
            'requester_type' => 'admin',
            'admin_username' => $adminUser,
            'subject' => $_POST['subject'] ?? '',
            'category' => $_POST['category'] ?? 'other',
            'priority' => $_POST['priority'] ?? 'medium',
            'message' => $_POST['message'] ?? '',
        ], $_FILES['attachments'] ?? []);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        header('Location: ' . ($isMaster ? $redirect : 'manage_support_tickets.php'));
        exit();
    }
}

$listFilters = [];
if ($isMaster) {
    $listFilters['status'] = $filterStatus;
    if ($filterType !== 'all') {
        $listFilters['requester_type'] = $filterType;
    }
} else {
    $listFilters['admin_username'] = $adminUser;
    if ($filterStatus !== 'all') {
        $listFilters['status'] = $filterStatus;
    }
}

$tickets = listSupportTickets($conn, $listFilters);
$pendingCount = $isMaster ? countPendingSupportTickets($conn) : 0;
$categories = supportTicketCategories('admin');
$priorities = supportTicketPriorities();
$statuses = supportTicketStatuses();

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isMaster ? 'Pending Tickets' : 'Support Tickets'; ?> - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .st-muted { color: #64748b; font-size: 0.875rem; }
        .st-filters { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .st-priority-high { font-weight: 700; }
        #ticketsTable td { vertical-align: middle; }
        .st-form-card { max-width: 720px; }
        .st-form-card .form-group { margin-bottom: 1rem; }
        .st-form-card label { font-weight: 500; color: #334155; margin-bottom: 6px; display: block; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-headset"></i> <?php echo $isMaster ? 'Pending Tickets' : 'Support Tickets'; ?></h4>
                <small>
                    <?php if ($isMaster): ?>
                        Student and admin tickets with priority. Reply and update status from the ticket.
                    <?php else: ?>
                        Raise a ticket for Master Admin and track its priority and status.
                    <?php endif; ?>
                </small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($adminUser); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars(function_exists('get_role_display_name') ? get_role_display_name() : 'Administrator'); ?></span>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($adminUser, 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-main">
            <?php if (!$isMaster): ?>
            <div class="content-card st-form-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;"><i class="fas fa-plus-circle"></i> Raise a ticket</h5>
                </div>
                <div style="padding:1rem 1.25rem 1.25rem;">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="create">
                        <div class="form-group">
                            <label for="st_subject">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="st_subject" name="subject" required maxlength="255">
                        </div>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;">
                            <div class="form-group" style="flex:1;min-width:180px;">
                                <label for="st_category">Category</label>
                                <select class="form-control" id="st_category" name="category" required>
                                    <?php foreach ($categories as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="width:180px;">
                                <label for="st_priority">Priority</label>
                                <select class="form-control" id="st_priority" name="priority" required>
                                    <?php foreach ($priorities as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === 'medium' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="st_message">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="st_message" name="message" rows="4" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="st_files">Attachments</label>
                            <input type="file" class="form-control" id="st_files" name="attachments[]" multiple
                                   accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,application/pdf,image/jpeg,image/png,image/webp,image/gif">
                            <div class="st-muted" style="margin-top:4px;">PDF, JPEG, PNG, WEBP or GIF. Up to 5 files, 10 MB each.</div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit ticket</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="content-card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <h5 class="card-title" style="margin:0;">
                        <i class="fas fa-<?php echo $isMaster ? 'inbox' : 'ticket-alt'; ?>"></i>
                        <?php
                        if ($isMaster && $filterStatus === 'pending') {
                            echo 'Pending tickets';
                        } elseif ($isMaster) {
                            echo 'All tickets';
                        } else {
                            echo 'My tickets';
                        }
                        ?>
                        <span class="st-muted">(<?php echo count($tickets); ?>)</span>
                        <?php if ($isMaster && $pendingCount > 0 && $filterStatus !== 'pending'): ?>
                            <span class="badge bg-danger"><?php echo (int) $pendingCount; ?> pending</span>
                        <?php endif; ?>
                    </h5>
                    <div class="st-filters">
                        <?php if ($isMaster): ?>
                        <form method="get" style="margin:0;display:flex;gap:8px;flex-wrap:wrap;">
                            <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All statuses</option>
                                <?php foreach ($statuses as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $filterStatus === $value ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="type" class="form-control form-control-sm" onchange="this.form.submit()">
                                <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>Students + Admins</option>
                                <option value="student" <?php echo $filterType === 'student' ? 'selected' : ''; ?>>Students</option>
                                <option value="admin" <?php echo $filterType === 'admin' ? 'selected' : ''; ?>>Admins</option>
                            </select>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table" id="ticketsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Raised by</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted" style="padding:2rem;">
                                        <?php echo $isMaster ? 'No tickets in this list.' : 'You have not raised any tickets yet.'; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tickets as $ticket): ?>
                                    <?php
                                    $pClass = supportTicketPriorityBadgeClass((string) $ticket['priority']);
                                    $sClass = supportTicketStatusBadgeClass((string) $ticket['status']);
                                    $catMap = supportTicketCategories((string) ($ticket['requester_type'] ?? 'student'));
                                    ?>
                                    <tr>
                                        <td>#<?php echo (int) $ticket['id']; ?></td>
                                        <td><?php echo htmlspecialchars(supportTicketRequesterLabel($ticket)); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars((string) $ticket['subject']); ?>
                                            <?php if ((int) ($ticket['attachment_count'] ?? 0) > 0): ?>
                                                <i class="fas fa-paperclip st-muted" title="<?php echo (int) $ticket['attachment_count']; ?> file(s)"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($catMap[$ticket['category']] ?? ucfirst((string) $ticket['category'])); ?></td>
                                        <td class="<?php echo ($ticket['priority'] ?? '') === 'high' ? 'st-priority-high' : ''; ?>">
                                            <span class="badge bg-<?php echo $pClass; ?>">
                                                <?php echo htmlspecialchars($priorities[$ticket['priority']] ?? ucfirst((string) $ticket['priority'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $sClass; ?>">
                                                <?php echo htmlspecialchars($statuses[$ticket['status']] ?? ucfirst(str_replace('_', ' ', (string) $ticket['status']))); ?>
                                            </span>
                                        </td>
                                        <td><?php echo !empty($ticket['created_at']) ? date('d M Y, h:i A', strtotime($ticket['created_at'])) : '—'; ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-primary" href="view_support_ticket.php?id=<?php echo (int) $ticket['id']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($isMaster): ?>
            <div class="content-card st-form-card" style="margin-top:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;"><i class="fas fa-plus-circle"></i> Raise a ticket (optional)</h5>
                </div>
                <div style="padding:1rem 1.25rem 1.25rem;">
                    <p class="st-muted">Use this if you need to log an internal note as a ticket.</p>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="create">
                        <div class="form-group">
                            <label for="st_subject_m">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="st_subject_m" name="subject" required maxlength="255">
                        </div>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;">
                            <div class="form-group" style="flex:1;min-width:180px;">
                                <label for="st_category_m">Category</label>
                                <select class="form-control" id="st_category_m" name="category" required>
                                    <?php foreach ($categories as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="width:180px;">
                                <label for="st_priority_m">Priority</label>
                                <select class="form-control" id="st_priority_m" name="priority" required>
                                    <?php foreach ($priorities as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === 'medium' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="st_message_m">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="st_message_m" name="message" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="st_files_m">Attachments</label>
                            <input type="file" class="form-control" id="st_files_m" name="attachments[]" multiple
                                   accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,application/pdf,image/jpeg,image/png,image/webp,image/gif">
                            <div class="st-muted" style="margin-top:4px;">PDF, JPEG, PNG, WEBP or GIF. Up to 5 files, 10 MB each.</div>
                        </div>
                        <button type="submit" class="btn btn-secondary"><i class="fas fa-paper-plane"></i> Submit ticket</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
<?php if ($message !== ''): ?>
showToast(<?php echo json_encode($message); ?>, <?php echo json_encode($message_type === 'danger' ? 'error' : $message_type); ?>);
<?php endif; ?>
</script>
</body>
</html>
