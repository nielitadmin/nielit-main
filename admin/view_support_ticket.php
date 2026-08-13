<?php
/**
 * Admin ticket detail. Master Admin can reply and change status.
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
$ticketId = (int) ($_GET['id'] ?? 0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$active_theme = loadActiveTheme($conn);
ensureSupportTicketsTable($conn);
$ticket = getSupportTicket($conn, $ticketId);

if (!$ticket || !supportTicketCanView($ticket, $isMaster, $adminUser)) {
    $_SESSION['message'] = 'Ticket not found or you do not have access.';
    $_SESSION['message_type'] = 'danger';
    header('Location: manage_support_tickets.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $_SESSION['message'] = 'Invalid security token. Please try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: view_support_ticket.php?id=' . $ticketId);
        exit();
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'reply' && $isMaster) {
        $result = addSupportTicketReply(
            $conn,
            $ticketId,
            'admin',
            $adminUser,
            (string) ($_POST['reply_message'] ?? ''),
            (string) ($_POST['status'] ?? '')
        );
        if ($result['success'] && !empty($result['id'])) {
            saveSupportTicketAttachments($conn, $ticketId, $_FILES['attachments'] ?? [], (int) $result['id'], $adminUser);
        }
        $_SESSION['message'] = $result['success'] ? 'Reply sent.' : $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        header('Location: view_support_ticket.php?id=' . $ticketId);
        exit();
    }

    if ($action === 'status' && $isMaster) {
        $result = updateSupportTicketStatus($conn, $ticketId, (string) ($_POST['status'] ?? ''));
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        header('Location: view_support_ticket.php?id=' . $ticketId);
        exit();
    }

    if ($action === 'followup' && !$isMaster) {
        $result = addSupportTicketReply(
            $conn,
            $ticketId,
            'admin',
            $adminUser,
            (string) ($_POST['reply_message'] ?? ''),
            null,
            false
        );
        if ($result['success'] && !empty($result['id'])) {
            saveSupportTicketAttachments($conn, $ticketId, $_FILES['attachments'] ?? [], (int) $result['id'], $adminUser);
        }
        $_SESSION['message'] = $result['success'] ? 'Reply added.' : $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        header('Location: view_support_ticket.php?id=' . $ticketId);
        exit();
    }

    header('Location: view_support_ticket.php?id=' . $ticketId);
    exit();
}

$ticket = getSupportTicket($conn, $ticketId) ?: $ticket;
$replies = listSupportTicketReplies($conn, $ticketId);
$ticketFiles = listSupportTicketAttachments($conn, $ticketId);
$categories = supportTicketCategories((string) ($ticket['requester_type'] ?? 'student'));
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
    <title>Ticket #<?php echo $ticketId; ?> - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .st-muted { color: #64748b; }
        .st-msg { white-space: pre-wrap; background: #f8fafc; border-radius: 8px; padding: 1rem; }
        .st-reply { border-radius: 8px; padding: 0.9rem 1rem; margin-bottom: 0.75rem; }
        .st-reply-admin { background: #eff6ff; }
        .st-reply-student { background: #f8fafc; }
        .st-meta { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .st-form-group { margin-bottom: 1rem; }
        .st-form-group label { font-weight: 500; display:block; margin-bottom: 6px; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-ticket-alt"></i> Ticket #<?php echo (int) $ticket['id']; ?></h4>
                <small><?php echo htmlspecialchars((string) $ticket['subject']); ?></small>
            </div>
            <div class="topbar-right">
                <a href="manage_support_tickets.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to list</a>
            </div>
        </div>

        <div class="admin-main">
            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <h5 class="card-title" style="margin:0;">Ticket details</h5>
                    <div class="st-meta">
                        <span class="badge bg-<?php echo supportTicketPriorityBadgeClass((string) $ticket['priority']); ?>">
                            Priority: <?php echo htmlspecialchars($priorities[$ticket['priority']] ?? ucfirst((string) $ticket['priority'])); ?>
                        </span>
                        <span class="badge bg-<?php echo supportTicketStatusBadgeClass((string) $ticket['status']); ?>">
                            <?php echo htmlspecialchars($statuses[$ticket['status']] ?? ucfirst(str_replace('_', ' ', (string) $ticket['status']))); ?>
                        </span>
                    </div>
                </div>
                <div style="padding:1.1rem 1.25rem;">
                    <p class="st-muted mb-2">
                        Raised by <strong><?php echo htmlspecialchars(supportTicketRequesterLabel($ticket)); ?></strong>
                        <?php if (!empty($ticket['student_email'])): ?>
                            · <?php echo htmlspecialchars((string) $ticket['student_email']); ?>
                        <?php endif; ?>
                        · <?php echo !empty($ticket['created_at']) ? date('d M Y, h:i A', strtotime($ticket['created_at'])) : ''; ?>
                        · <?php echo htmlspecialchars($categories[$ticket['category']] ?? ucfirst((string) $ticket['category'])); ?>
                    </p>
                    <div class="st-msg"><?php echo htmlspecialchars((string) $ticket['message']); ?></div>
                    <?php supportTicketRenderAttachments($ticketFiles, 'download_support_attachment.php'); ?>
                </div>
            </div>

            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">Conversation</h5>
                </div>
                <div style="padding:1.1rem 1.25rem;">
                    <?php if (empty($replies)): ?>
                        <p class="st-muted mb-0">No replies yet.</p>
                    <?php else: ?>
                        <?php foreach ($replies as $reply): ?>
                            <div class="st-reply <?php echo ($reply['author_type'] ?? '') === 'admin' ? 'st-reply-admin' : 'st-reply-student'; ?>">
                                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                                    <strong>
                                        <?php echo ($reply['author_type'] ?? '') === 'student' ? 'Student' : 'Admin'; ?>
                                        <span class="st-muted" style="font-weight:400;"> · <?php echo htmlspecialchars((string) ($reply['author_name'] ?? '')); ?></span>
                                    </strong>
                                    <small class="st-muted"><?php echo !empty($reply['created_at']) ? date('d M Y, h:i A', strtotime($reply['created_at'])) : ''; ?></small>
                                </div>
                                <div style="white-space:pre-wrap;margin-top:6px;"><?php echo htmlspecialchars((string) $reply['message']); ?></div>
                                <?php supportTicketRenderAttachments(listSupportTicketAttachments($conn, $ticketId, (int) $reply['id']), 'download_support_attachment.php'); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isMaster): ?>
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">Reply &amp; update status</h5>
                </div>
                <div style="padding:1.1rem 1.25rem;">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="reply">
                        <div class="st-form-group">
                            <label for="reply_message">Reply</label>
                            <textarea class="form-control" id="reply_message" name="reply_message" rows="4" required></textarea>
                        </div>
                        <div class="st-form-group">
                            <label for="reply_files">Attachments</label>
                            <input type="file" class="form-control" id="reply_files" name="attachments[]" multiple
                                   accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,application/pdf,image/jpeg,image/png,image/webp,image/gif">
                            <div class="st-muted">Optional: PDF or image, up to 5 files, 10 MB each.</div>
                        </div>
                        <div class="st-form-group" style="max-width:240px;">
                            <label for="st_status">Set status</label>
                            <select class="form-control" id="st_status" name="status">
                                <?php
                                $current = (string) $ticket['status'];
                                $next = $current === 'open' ? 'in_progress' : $current;
                                foreach ($statuses as $value => $label):
                                ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === $next ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-reply"></i> Send reply</button>
                    </form>
                    <hr>
                    <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="status">
                        <div class="st-form-group" style="margin:0;max-width:240px;">
                            <label for="st_status_only">Status only</label>
                            <select class="form-control" id="st_status_only" name="status">
                                <?php foreach ($statuses as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === (string) $ticket['status'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-secondary">Update status</button>
                    </form>
                </div>
            </div>
            <?php elseif ((string) $ticket['status'] !== 'closed'): ?>
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">Add a follow-up</h5>
                </div>
                <div style="padding:1.1rem 1.25rem;">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="followup">
                        <div class="st-form-group">
                            <textarea class="form-control" name="reply_message" rows="4" required placeholder="Add more details..."></textarea>
                        </div>
                        <div class="st-form-group">
                            <input type="file" class="form-control" name="attachments[]" multiple
                                   accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,application/pdf,image/jpeg,image/png,image/webp,image/gif">
                            <div class="st-muted">Optional: PDF or image, up to 5 files, 10 MB each.</div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-reply"></i> Send</button>
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
