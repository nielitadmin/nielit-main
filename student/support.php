<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/support_ticket_helper.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (string) $_SESSION['student_id'];
$success_message = '';
$error_message = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

ensureSupportTicketsTable($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $result = createSupportTicket($conn, [
            'requester_type' => 'student',
            'student_id' => $student_id,
            'subject' => $_POST['subject'] ?? '',
            'category' => $_POST['category'] ?? 'other',
            'priority' => $_POST['priority'] ?? 'medium',
            'message' => $_POST['message'] ?? '',
        ]);
        if ($result['success']) {
            $success_message = $result['message'] . " We'll get back to you soon.";
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $error_message = $result['message'];
        }
    }
}

$tickets = listSupportTickets($conn, ['student_id' => $student_id]);
$categories = supportTicketCategories('student');
$priorities = supportTicketPriorities();

$page_title = 'Support';
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-headset"></i> Support Center</h2>
            <p class="text-muted">Raise a ticket for the admin team. Include a priority so urgent issues are handled first.</p>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card help-card">
                <div class="card-body text-center">
                    <i class="fas fa-phone fa-3x text-primary mb-3"></i>
                    <h5>Call Us</h5>
                    <p class="text-muted">0674-2960354</p>
                    <p class="small">Mon-Fri: 9:00 AM - 5:30 PM</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card help-card">
                <div class="card-body text-center">
                    <i class="fas fa-envelope fa-3x text-success mb-3"></i>
                    <h5>Email Us</h5>
                    <p class="text-muted">dir-bbsr@nielit.gov.in</p>
                    <p class="small">Response within 24 hours</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card help-card">
                <div class="card-body text-center">
                    <i class="fas fa-map-marker-alt fa-3x text-danger mb-3"></i>
                    <h5>Visit Us</h5>
                    <p class="text-muted">NIELIT Bhubaneswar</p>
                    <p class="small">Odisha, India</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Submit New Ticket</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" required maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="form-select" id="priority" name="priority" required>
                                <?php foreach ($priorities as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === 'medium' ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">High = urgent (login blocked, fee payment failed). Medium = normal. Low = general query.</div>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                        </div>

                        <button type="submit" name="submit_ticket" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane"></i> Submit Ticket
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-question-circle"></i> Frequently Asked Questions</h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item mb-2">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    How do I reset my password?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Go to the login page and click on "Forgot Password". Enter your registered email to receive reset instructions.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item mb-2">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    When will I receive my certificate?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Certificates are issued within 30 days of course completion, provided all requirements are met.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item mb-2">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    How can I check my attendance?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Visit the Attendance section in your student portal to view your complete attendance record.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item mb-2">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    How do I make fee payments?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Go to the Fees section and click on "Make Payment" to pay online or view payment instructions.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-ticket-alt"></i> My Support Tickets</h5>
                </div>
                <div class="card-body">
                    <?php if (count($tickets) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ticket ID</th>
                                        <th>Subject</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td>#<?php echo (int) $ticket['id']; ?></td>
                                        <td><?php echo htmlspecialchars((string) $ticket['subject']); ?></td>
                                        <td><?php echo htmlspecialchars($categories[$ticket['category']] ?? ucfirst((string) $ticket['category'])); ?></td>
                                        <td>
                                            <?php $priority_class = supportTicketPriorityBadgeClass((string) $ticket['priority']); ?>
                                            <span class="badge bg-<?php echo $priority_class; ?>">
                                                <?php echo htmlspecialchars($priorities[$ticket['priority']] ?? ucfirst((string) $ticket['priority'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php $status_class = supportTicketStatusBadgeClass((string) $ticket['status']); ?>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $ticket['status']))); ?>
                                            </span>
                                        </td>
                                        <td><?php echo !empty($ticket['created_at']) ? date('d M Y', strtotime($ticket['created_at'])) : '—'; ?></td>
                                        <td>
                                            <a href="view_ticket.php?id=<?php echo (int) $ticket['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">No support tickets yet. Submit a ticket if you need help.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.help-card {
    transition: transform 0.3s, box-shadow 0.3s;
    height: 100%;
}
.help-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}
</style>

<?php include 'includes/footer.php'; ?>
