<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];
$success_message = '';
$error_message = '';

// Handle support ticket submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject']);
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $message = trim($_POST['message']);
    
    if (!empty($subject) && !empty($message)) {
        $sql = "INSERT INTO support_tickets (student_id, subject, category, priority, message, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'open', NOW())";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssss", $student_id, $subject, $category, $priority, $message);
            if ($stmt->execute()) {
                $success_message = "Support ticket submitted successfully! We'll get back to you soon.";
            } else {
                $error_message = "Failed to submit ticket. Please try again.";
            }
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

// Fetch student's support tickets
$sql_tickets = "SELECT * FROM support_tickets WHERE student_id = ? ORDER BY created_at DESC";
$stmt_tickets = $conn->prepare($sql_tickets);
$tickets = [];
if ($stmt_tickets) {
    $stmt_tickets->bind_param("s", $student_id);
    $stmt_tickets->execute();
    $result_tickets = $stmt_tickets->get_result();
    while ($row = $result_tickets->fetch_assoc()) {
        $tickets[] = $row;
    }
}

$page_title = "Support";
include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-headset"></i> Support Center</h2>
            <p class="text-muted">Get help with your queries and issues</p>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Quick Help Cards -->
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
        <!-- Submit New Ticket -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Submit New Ticket</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="subject">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category <span class="text-danger">*</span></label>
                            <select class="form-control" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="technical">Technical Issue</option>
                                <option value="academic">Academic Query</option>
                                <option value="fees">Fees Related</option>
                                <option value="certificate">Certificate Issue</option>
                                <option value="attendance">Attendance Query</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="priority">Priority <span class="text-danger">*</span></label>
                            <select class="form-control" id="priority" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                        </div>
                        
                        <button type="submit" name="submit_ticket" class="btn btn-primary btn-block">
                            <i class="fas fa-paper-plane"></i> Submit Ticket
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-question-circle"></i> Frequently Asked Questions</h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="faqAccordion">
                        <div class="card mb-2">
                            <div class="card-header p-0">
                                <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#faq1">
                                    <i class="fas fa-chevron-right"></i> How do I reset my password?
                                </button>
                            </div>
                            <div id="faq1" class="collapse" data-parent="#faqAccordion">
                                <div class="card-body">
                                    Go to the login page and click on "Forgot Password". Enter your registered email to receive reset instructions.
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-2">
                            <div class="card-header p-0">
                                <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#faq2">
                                    <i class="fas fa-chevron-right"></i> When will I receive my certificate?
                                </button>
                            </div>
                            <div id="faq2" class="collapse" data-parent="#faqAccordion">
                                <div class="card-body">
                                    Certificates are issued within 30 days of course completion, provided all requirements are met.
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-2">
                            <div class="card-header p-0">
                                <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#faq3">
                                    <i class="fas fa-chevron-right"></i> How can I check my attendance?
                                </button>
                            </div>
                            <div id="faq3" class="collapse" data-parent="#faqAccordion">
                                <div class="card-body">
                                    Visit the Attendance section in your student portal to view your complete attendance record.
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-2">
                            <div class="card-header p-0">
                                <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#faq4">
                                    <i class="fas fa-chevron-right"></i> How do I make fee payments?
                                </button>
                            </div>
                            <div id="faq4" class="collapse" data-parent="#faqAccordion">
                                <div class="card-body">
                                    Go to the Fees section and click on "Make Payment" to pay online or view payment instructions.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- My Tickets -->
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
                                        <td>#<?php echo $ticket['id']; ?></td>
                                        <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                        <td><?php echo ucfirst($ticket['category']); ?></td>
                                        <td>
                                            <?php
                                            $priority_class = 'secondary';
                                            if ($ticket['priority'] == 'high') $priority_class = 'danger';
                                            elseif ($ticket['priority'] == 'medium') $priority_class = 'warning';
                                            ?>
                                            <span class="badge badge-<?php echo $priority_class; ?>">
                                                <?php echo ucfirst($ticket['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = 'secondary';
                                            if ($ticket['status'] == 'open') $status_class = 'primary';
                                            elseif ($ticket['status'] == 'in_progress') $status_class = 'warning';
                                            elseif ($ticket['status'] == 'resolved') $status_class = 'success';
                                            elseif ($ticket['status'] == 'closed') $status_class = 'secondary';
                                            ?>
                                            <span class="badge badge-<?php echo $status_class; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($ticket['created_at'])); ?></td>
                                        <td>
                                            <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
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
                            <p class="text-muted">No support tickets yet. Submit a ticket if you need help!</p>
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

.accordion .btn-link {
    text-decoration: none;
    color: #333;
}

.accordion .btn-link:hover {
    color: var(--primary-color);
}
</style>

<?php include 'includes/footer.php'; ?>
