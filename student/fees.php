<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];
require_once __DIR__ . '/includes/load_enrollments.php';

$student = $student_profile;
if (!$student) {
    header("Location: login.php");
    exit;
}

$course_name = $student['course_name'] ?? $student['course'] ?? 'N/A';
$duration = 'N/A';
$course_fees = 0;

$enrollment_fee_rows = [];
$total_course_fees = 0;
foreach ($student_enrollment_rows as $erow) {
    $rowFee = (float)($erow['fees'] ?? 0);
    if ($rowFee <= 0) {
        $rowFee = (float)($erow['catalog_fees'] ?? 0);
    }
    $enrollment_fee_rows[] = [
        'course_name' => $erow['course_name'] ?? ($erow['course'] ?? 'N/A'),
        'scheme_name' => $erow['scheme_name'] ?? '',
        'duration' => $erow['duration'] ?? 'N/A',
        'fees' => $rowFee,
    ];
    $total_course_fees += $rowFee;
}

if (count($enrollment_fee_rows) === 1) {
    $course_name = $enrollment_fee_rows[0]['course_name'];
    $duration = $enrollment_fee_rows[0]['duration'];
    $course_fees = $enrollment_fee_rows[0]['fees'];
} elseif (count($enrollment_fee_rows) > 1) {
    $course_fees = $total_course_fees;
    $course_name = count($enrollment_fee_rows) . ' courses';
} else {
    $sql_course = "SELECT course_name, fees, duration FROM courses WHERE course_code = ?";
    $stmt_course = $conn->prepare($sql_course);
    if ($stmt_course) {
        $stmt_course->bind_param("s", $student['course']);
        $stmt_course->execute();
        $result_course = $stmt_course->get_result();
        if ($row_course = $result_course->fetch_assoc()) {
            $course_name = $row_course['course_name'];
            $course_fees = (float)$row_course['fees'];
            $duration = $row_course['duration'];
        }
    }
    $course_fees = (float)($student['fees'] ?? $course_fees);
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

if ($course_fees <= 0) {
    $course_fees = (float)($student['fees'] ?? 0);
}
$balance = $course_fees - $total_paid;

$onlineFeeCourses = [];
$seenOnlineCourse = [];
foreach ($student_enrollment_rows as $erow) {
    $onlineCourseId = (int) ($erow['course_id'] ?? 0);
    if ($onlineCourseId <= 0 || isset($seenOnlineCourse[$onlineCourseId])) {
        continue;
    }
    if ((int) ($erow['online_payment_enabled'] ?? 0) !== 1) {
        continue;
    }
    $onlineAmount = (float) ($erow['catalog_fees'] ?? 0);
    if ($onlineAmount <= 0) {
        $onlineAmount = (float) ($erow['fees'] ?? 0);
    }
    if ($onlineAmount <= 0) {
        continue;
    }
    $seenOnlineCourse[$onlineCourseId] = true;
    $alreadyPaid = payment_student_paid_amount($conn, (string) $student_id, $onlineCourseId);
    $onlineFeeCourses[] = [
        'course_id' => $onlineCourseId,
        'record_id' => (int) ($erow['id'] ?? 0),
        'course_name' => (string) ($erow['course_name'] ?? 'Course'),
        'amount' => $onlineAmount,
        'paid' => $alreadyPaid,
        'due' => max(0, round($onlineAmount - $alreadyPaid, 2)),
    ];
}
$onlineInvoices = payment_student_invoices($conn, (string) $student_id);
$onlineGateways = payment_gateway_active_list($conn);
$onlineGatewayReady = !empty($onlineGateways);
if (count($payment_records) === 0) {
    foreach ($onlineInvoices as $invoiceRow) {
        $total_paid += (float) $invoiceRow['amount'];
    }
    $balance = $course_fees - $total_paid;
}

$page_title = "Fee Details";
include 'includes/header.php';
?>

<div class="container py-4">
    <?php if (!empty($_GET['paid'])): ?>
        <div class="alert alert-success">Payment received. Your invoice is listed below.</div>
    <?php elseif (!empty($_GET['pay_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars((string) $_GET['pay_error']); ?></div>
    <?php endif; ?>
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
                    <p><?php echo count($enrollment_fee_rows) > 1 ? 'Total Fees (All Courses)' : 'Total Course Fee'; ?></p>
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

    <?php if (count($enrollment_fee_rows) > 1): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-layer-group"></i> Fee by Course</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Scheme / Project</th>
                                    <th>Duration</th>
                                    <th>Course Fee</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollment_fee_rows as $feeRow): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($feeRow['course_name']); ?></td>
                                    <td><?php echo $feeRow['scheme_name'] !== '' ? htmlspecialchars($feeRow['scheme_name']) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($feeRow['duration']); ?></td>
                                    <td><strong>₹<?php echo number_format($feeRow['fees'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                    <?php if ($balance > 0 || !empty($onlineFeeCourses)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i> 
                            You have a pending balance of <strong>₹<?php echo number_format(max(0, $balance), 2); ?></strong>
                        </div>
                        <?php if (!empty($onlineFeeCourses)): ?>
                            <?php foreach ($onlineFeeCourses as $onlineCourse): ?>
                                <div class="border rounded p-3 mb-2 text-start">
                                    <strong><?php echo htmlspecialchars($onlineCourse['course_name']); ?></strong>
                                    <div>Fee ₹<?php echo number_format($onlineCourse['amount'], 2); ?>
                                        · Paid ₹<?php echo number_format($onlineCourse['paid'], 2); ?></div>
                                    <?php if ($onlineCourse['due'] <= 0): ?>
                                        <span class="badge bg-success mt-2">Paid</span>
                                    <?php elseif ($onlineGatewayReady): ?>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <?php foreach ($onlineGateways as $gatewayOption): ?>
                                                <button type="button"
                                                        class="btn btn-primary btn-sm pay-course-fee"
                                                        data-gateway="<?php echo htmlspecialchars((string) $gatewayOption['provider']); ?>"
                                                        data-course-id="<?php echo (int) $onlineCourse['course_id']; ?>"
                                                        data-record-id="<?php echo (int) $onlineCourse['record_id']; ?>">
                                                    <i class="fas fa-credit-card"></i>
                                                    Pay ₹<?php echo number_format($onlineCourse['due'], 2); ?> with <?php echo htmlspecialchars((string) $gatewayOption['name']); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="small text-muted mt-2">Online payment is not open yet. Please contact the centre.</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <div id="coursePaymentMessage" class="small mt-2"></div>
                        <?php else: ?>
                        <a href="make_payment.php" class="btn btn-primary">
                            <i class="fas fa-credit-card"></i> Make Payment
                        </a>
                        <?php endif; ?>

                        <?php
                        $qrFile = __DIR__ . '/../uploads/payment_qr.png';
                        if (file_exists($qrFile)): ?>
                        <div class="mt-3 text-center">
                            <p class="mb-1"><strong>Or scan QR to pay</strong></p>
                            <img src="<?php echo APP_URL; ?>/uploads/payment_qr.png" alt="Payment QR" style="max-width:240px;" class="img-fluid mx-auto">
                        </div>
                        <?php endif; ?>

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
                    <?php elseif (empty($onlineInvoices)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No payment records found</h5>
                            <p class="text-muted">Your payment history will appear here.</p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($onlineInvoices)): ?>
                        <h6 class="mt-4">Invoices</h6>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Course</th>
                                        <th>Paid on</th>
                                        <th>Amount</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($onlineInvoices as $invoice): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $invoice['invoice_number']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $invoice['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $invoice['paid_at']); ?></td>
                                        <td>₹<?php echo number_format((float) $invoice['amount'], 2); ?></td>
                                        <td><a class="btn btn-sm btn-outline-primary" href="payment_invoice.php?id=<?php echo (int) $invoice['id']; ?>">View invoice</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
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

<?php if ($onlineGatewayReady && !empty($onlineFeeCourses)): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
<script>
document.querySelectorAll('.pay-course-fee').forEach(function (button) {
    button.addEventListener('click', function () {
        const message = document.getElementById('coursePaymentMessage');
        button.disabled = true;
        const body = new URLSearchParams();
        body.set('course_id', button.getAttribute('data-course-id') || '');
        body.set('record_id', button.getAttribute('data-record-id') || '');
        body.set('gateway', button.getAttribute('data-gateway') || 'razorpay');
        fetch('create_course_order.php', { method: 'POST', body: body })
            .then(function (response) { return response.json(); })
            .then(function (order) {
                if (!order.ok) {
                    throw new Error(order.error || 'Could not start payment');
                }
                if (order.mode === 'redirect' && order.url) {
                    window.location.href = order.url;
                    return;
                }
                if (order.mode === 'payu' && order.action && order.fields) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = order.action;
                    Object.keys(order.fields).forEach(function (name) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = order.fields[name];
                        form.appendChild(input);
                    });
                    document.body.appendChild(form);
                    form.submit();
                    return;
                }
                if (order.mode === 'cashfree' && window.Cashfree) {
                    const cashfree = Cashfree({ mode: order.sandbox ? 'sandbox' : 'production' });
                    cashfree.checkout({ paymentSessionId: order.payment_session_id, redirectTarget: '_self' });
                    return;
                }
                const checkout = new Razorpay({
                    key: order.key_id,
                    amount: order.amount,
                    currency: order.currency,
                    name: order.name,
                    description: order.description,
                    order_id: order.order_id,
                    prefill: order.prefill || {},
                    handler: function (payment) {
                        const paid = new URLSearchParams();
                        paid.set('razorpay_order_id', payment.razorpay_order_id);
                        paid.set('razorpay_payment_id', payment.razorpay_payment_id);
                        paid.set('razorpay_signature', payment.razorpay_signature);
                        fetch('verify_course_payment.php', { method: 'POST', body: paid })
                            .then(function (response) { return response.json(); })
                            .then(function (result) {
                                if (!result.ok) {
                                    throw new Error(result.error || 'Payment could not be verified');
                                }
                                window.location.reload();
                            })
                            .catch(function (error) {
                                message.textContent = error.message;
                                button.disabled = false;
                            });
                    },
                    modal: {
                        ondismiss: function () { button.disabled = false; }
                    }
                });
                checkout.on('payment.failed', function (response) {
                    message.textContent = (response.error && response.error.description) || 'Payment failed';
                    button.disabled = false;
                });
                checkout.open();
            })
            .catch(function (error) {
                message.textContent = error.message;
                button.disabled = false;
            });
    });
});
</script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
