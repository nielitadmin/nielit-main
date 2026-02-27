<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch student and course details
$sql = "SELECT * FROM students WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Try to get course details
$course_name = $student['course'];
$duration = 'N/A';
$course_fees = 0;

$sql_course = "SELECT course_name, fees, duration FROM courses WHERE course_code = ?";
$stmt_course = $conn->prepare($sql_course);
if ($stmt_course) {
    $stmt_course->bind_param("s", $student['course']);
    $stmt_course->execute();
    $result_course = $stmt_course->get_result();
    if ($row_course = $result_course->fetch_assoc()) {
        $course_name = $row_course['course_name'];
        $course_fees = $row_course['fees'];
        $duration = $row_course['duration'];
    }
}

// Fetch payment history
$sql_payments = "SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC";
$stmt_payments = $conn->prepare($sql_payments);
$payment_records = [];
$total_paid = 0;

if ($stmt_payments) {
    $stmt_payments->bind_param("s", $student_id);
    $stmt_payments->execute();
    $result_payments = $stmt_payments->get_result();
    while ($row = $result_payments->fetch_assoc()) {
        $payment_records[] = $row;
        $total_paid += $row['amount'];
    }
}

$course_fees = $student['fees'] ?? 0;
$balance = $course_fees - $total_paid;

$page_title = "Fee Details";
include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-rupee-sign"></i> Fee Details</h2>
            <p class="text-muted">View your fee structure and payment history</p>
        </div>
    </div>

    <!-- Fee Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="stat-card bg-primary-gradient">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-content">
                    <h3>₹<?php echo number_format($course_fees, 2); ?></h3>
                    <p>Total Course Fee</p>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="stat-card bg-success-gradient">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>₹<?php echo number_format($total_paid, 2); ?></h3>
                    <p>Amount Paid</p>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="stat-card <?php echo $balance > 0 ? 'bg-warning-gradient' : 'bg-info-gradient'; ?>">
                <div class="stat-icon">
                    <i class="fas fa-<?php echo $balance > 0 ? 'exclamation-circle' : 'check-double'; ?>"></i>
                </div>
                <div class="stat-content">
                    <h3>₹<?php echo number_format($balance, 2); ?></h3>
                    <p><?php echo $balance > 0 ? 'Balance Due' : 'Fully Paid'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Fee Structure -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list-alt"></i> Fee Structure</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th>Course Name</th>
                            <td><?php echo htmlspecialchars($course_name); ?></td>
                        </tr>
                        <tr>
                            <th>Duration</th>
                            <td><?php echo htmlspecialchars($duration); ?></td>
                        </tr>
                        <tr>
                            <th>Course Fee</th>
                            <td><strong>₹<?php echo number_format($course_fees, 2); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Registration Fee</th>
                            <td>Included</td>
                        </tr>
                        <tr>
                            <th>Examination Fee</th>
                            <td>Included</td>
                        </tr>
                        <tr class="border-top">
                            <th>Total Payable</th>
                            <td><strong class="text-primary">₹<?php echo number_format($course_fees, 2); ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Payment Status</h5>
                </div>
                <div class="card-body text-center">
                    <?php
                    $payment_percentage = $course_fees > 0 ? round(($total_paid / $course_fees) * 100, 1) : 0;
                    ?>
                    <div class="mb-3">
                        <svg width="200" height="200">
                            <circle cx="100" cy="100" r="80" fill="none" stroke="#e9ecef" stroke-width="20"/>
                            <circle cx="100" cy="100" r="80" fill="none" 
                                    stroke="<?php echo $payment_percentage >= 100 ? '#28a745' : '#ffc107'; ?>" 
                                    stroke-width="20"
                                    stroke-dasharray="<?php echo ($payment_percentage / 100) * 502.4; ?> 502.4"
                                    transform="rotate(-90 100 100)"/>
                            <text x="100" y="100" text-anchor="middle" dy="10" 
                                  font-size="36" font-weight="bold" 
                                  fill="<?php echo $payment_percentage >= 100 ? '#28a745' : '#ffc107'; ?>">
                                <?php echo $payment_percentage; ?>%
                            </text>
                        </svg>
                    </div>
                    <?php if ($balance > 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i> 
                            You have a pending balance of <strong>₹<?php echo number_format($balance, 2); ?></strong>
                        </div>
                        <a href="make_payment.php" class="btn btn-primary">
                            <i class="fas fa-credit-card"></i> Make Payment
                        </a>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> 
                            All fees paid! Thank you.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Payment History</h5>
                </div>
                <div class="card-body">
                    <?php if (count($payment_records) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Transaction ID</th>
                                        <th>Payment Mode</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Receipt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_records as $payment): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_mode'] ?? 'Online'); ?></td>
                                        <td><strong>₹<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                        <td>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check"></i> Paid
                                            </span>
                                        </td>
                                        <td>
                                            <a href="download_receipt.php?id=<?php echo $payment['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No payment records found</h5>
                            <p class="text-muted">Your payment history will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Receipt (if exists) -->
    <?php if (!empty($student['payment_receipt'])): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-invoice"></i> Initial Payment Receipt</h5>
                </div>
                <div class="card-body text-center">
                    <?php
                    $receipt_path = $student['payment_receipt'];
                    $receipt_ext = strtolower(pathinfo($receipt_path, PATHINFO_EXTENSION));
                    ?>
                    <?php if (in_array($receipt_ext, ['jpg', 'jpeg', 'png'])): ?>
                        <img src="../<?php echo htmlspecialchars($receipt_path); ?>" 
                             alt="Payment Receipt" 
                             class="img-fluid" 
                             style="max-width: 500px;">
                    <?php else: ?>
                        <iframe src="../<?php echo htmlspecialchars($receipt_path); ?>" 
                                style="width:100%; height:600px; border:1px solid #ddd;"></iframe>
                    <?php endif; ?>
                    <div class="mt-3">
                        <a href="../<?php echo htmlspecialchars($receipt_path); ?>" 
                           download 
                           class="btn btn-primary">
                            <i class="fas fa-download"></i> Download Receipt
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
