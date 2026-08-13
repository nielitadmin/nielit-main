<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/support_ticket_helper.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (string) $_SESSION['student_id'];
$ticketId = (int) ($_GET['id'] ?? 0);
$success_message = '';
$error_message = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

ensureSupportTicketsTable($conn);
$ticket = getSupportTicket($conn, $ticketId);

if (!$ticket || (string) ($ticket['student_id'] ?? '') !== $student_id || ($ticket['requester_type'] ?? 'student') !== 'student') {
    header('Location: support.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reply'])) {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $error_message = 'Invalid security token. Please try again.';
    } elseif (in_array((string) $ticket['status'], ['closed'], true)) {
        $error_message = 'This ticket is closed. Please raise a new ticket if you still need help.';
    } else {
        $result = addSupportTicketReply(
            $conn,
            $ticketId,
            'student',
            (string) ($_SESSION['student_name'] ?? $student_id),
            (string) ($_POST['reply_message'] ?? '')
        );
        if ($result['success']) {
            $success_message = 'Your reply was added.';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $ticket = getSupportTicket($conn, $ticketId) ?: $ticket;
        } else {
            $error_message = $result['message'];
        }
    }
}

$replies = listSupportTicketReplies($conn, $ticketId);
$categories = supportTicketCategories('student');
$priorities = supportTicketPriorities();
$page_title = 'Ticket #' . $ticketId;
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="mb-3">
        <a href="support.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Support</a>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fas fa-ticket-alt"></i> Ticket #<?php echo (int) $ticket['id']; ?></h5>
            <div>
                <span class="badge bg-<?php echo supportTicketPriorityBadgeClass((string) $ticket['priority']); ?>">
                    Priority: <?php echo htmlspecialchars($priorities[$ticket['priority']] ?? ucfirst((string) $ticket['priority'])); ?>
                </span>
                <span class="badge bg-<?php echo supportTicketStatusBadgeClass((string) $ticket['status']); ?>">
                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $ticket['status']))); ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <h4><?php echo htmlspecialchars((string) $ticket['subject']); ?></h4>
            <p class="text-muted mb-3">
                <?php echo htmlspecialchars($categories[$ticket['category']] ?? ucfirst((string) $ticket['category'])); ?>
                &middot;
                Raised <?php echo !empty($ticket['created_at']) ? date('d M Y, h:i A', strtotime($ticket['created_at'])) : '—'; ?>
            </p>
            <div class="p-3 rounded" style="background:#f8fafc; white-space:pre-wrap;"><?php echo htmlspecialchars((string) $ticket['message']); ?></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Conversation</h5></div>
        <div class="card-body">
            <?php if (empty($replies)): ?>
                <p class="text-muted mb-0">No replies yet. The admin team will respond here.</p>
            <?php else: ?>
                <?php foreach ($replies as $reply): ?>
                    <div class="mb-3 p-3 rounded" style="background:<?php echo ($reply['author_type'] ?? '') === 'admin' ? '#eff6ff' : '#f8fafc'; ?>;">
                        <div class="d-flex justify-content-between">
                            <strong>
                                <?php echo ($reply['author_type'] ?? '') === 'admin' ? 'Admin' : 'You'; ?>
                                <span class="text-muted fw-normal"> · <?php echo htmlspecialchars((string) ($reply['author_name'] ?? '')); ?></span>
                            </strong>
                            <small class="text-muted"><?php echo !empty($reply['created_at']) ? date('d M Y, h:i A', strtotime($reply['created_at'])) : ''; ?></small>
                        </div>
                        <div class="mt-2" style="white-space:pre-wrap;"><?php echo htmlspecialchars((string) $reply['message']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ((string) $ticket['status'] !== 'closed'): ?>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Add a reply</h5></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="mb-3">
                    <textarea class="form-control" name="reply_message" rows="4" required placeholder="Add more details if needed..."></textarea>
                </div>
                <button type="submit" name="add_reply" class="btn btn-primary"><i class="fas fa-reply"></i> Send reply</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
