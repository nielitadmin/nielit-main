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

payment_student_expire_stale_online_attempts($conn, (string) $student_id);
if (payment_hub_is_ready()) {
    payment_hub_sync_student_attempts($conn, (string) $student_id);
}

$total_paid = payment_student_total_paid_reconciled($conn, (string) $student_id, $payment_records);
$balance = max(0, $course_fees - $total_paid);

$onlineFeeCourses = payment_student_build_online_fee_courses($conn, (string) $student_id, $student_enrollment_rows);
$onlineInvoices = payment_student_invoices($conn, (string) $student_id);
$onlinePaymentHistory = payment_student_online_payment_history($conn, (string) $student_id, 25);
$onlineGateways = payment_gateway_active_list($conn);
$onlineHubReady = payment_hub_is_ready();
$hubCfg = payment_hub_config();
$onlineGatewayReady = $onlineHubReady || !empty($onlineGateways);

$onlineDueTotal = 0;
$onlinePaidTotal = 0;
$onlineFeeTotal = 0;
foreach ($onlineFeeCourses as $oc) {
    $onlineDueTotal += (float) $oc['due'];
    $onlinePaidTotal += (float) $oc['paid'];
    $onlineFeeTotal += (float) $oc['amount'];
}
$usesOnlineFeePanel = $onlineFeeCourses !== [];
$displayFeeTotal = $usesOnlineFeePanel ? $onlineFeeTotal : $course_fees;
$displayPaid = $usesOnlineFeePanel ? $onlinePaidTotal : $total_paid;
$displayBalance = $usesOnlineFeePanel ? $onlineDueTotal : $balance;
$displayBalanceLabel = $usesOnlineFeePanel
    ? ($onlineDueTotal > 0 ? 'Online fee due' : 'Online fees paid')
    : ($balance > 0 ? 'Balance due' : 'Fully paid');

$page_title = "Fee Details";
include 'includes/header.php';
?>

<div class="container py-4">
    <?php if (!empty($_GET['paid'])): ?>
        <div class="alert alert-success">
            Payment received successfully.
            <?php if (!empty($_GET['invoice_id'])): ?>
                <a class="alert-link" href="payment_invoice.php?id=<?php echo (int) $_GET['invoice_id']; ?>">View your invoice</a>
            <?php else: ?>
                Your invoice appears in the list below.
            <?php endif; ?>
        </div>
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
                    <h3>₹<?php echo number_format($displayFeeTotal, 2); ?></h3>
                    <p><?php echo $usesOnlineFeePanel ? 'Online course fee' : (count($enrollment_fee_rows) > 1 ? 'Total Fees (All Courses)' : 'Total Course Fee'); ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="stat-card bg-success-gradient">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>₹<?php echo number_format($displayPaid, 2); ?></h3>
                    <p>Amount paid<?php echo $usesOnlineFeePanel ? ' (online)' : ''; ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="stat-card <?php echo $displayBalance > 0 ? 'bg-warning-gradient' : 'bg-info-gradient'; ?>">
                <div class="stat-icon">
                    <i class="fas fa-<?php echo $displayBalance > 0 ? 'exclamation-circle' : 'check-double'; ?>"></i>
                </div>
                <div class="stat-content">
                    <h3>₹<?php echo number_format($displayBalance, 2); ?></h3>
                    <p><?php echo htmlspecialchars($displayBalanceLabel); ?></p>
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
                    $payment_percentage = $displayFeeTotal > 0 ? round(($displayPaid / $displayFeeTotal) * 100, 1) : ($displayBalance <= 0 ? 100 : 0);
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
                    <?php if ($displayBalance > 0 || !empty($onlineFeeCourses)): ?>
                        <?php if ($displayBalance > 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            <?php if ($usesOnlineFeePanel): ?>
                                Online course fee due: <strong>₹<?php echo number_format($displayBalance, 2); ?></strong>
                            <?php else: ?>
                                Balance due: <strong>₹<?php echo number_format($displayBalance, 2); ?></strong>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($usesOnlineFeePanel): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Your online course fee is paid.</div>
                        <?php endif; ?>
                        <?php if (!empty($onlineFeeCourses)): ?>
                            <?php if ($onlineHubReady): ?>
                                <div class="alert alert-info text-start small mb-3">
                                    Online course fees are collected on the
                                    <a href="<?php echo htmlspecialchars(payment_hub_public_url($hubCfg)); ?>" target="_blank" rel="noopener">NIELIT Payment Portal</a>
                                    (secure Razorpay checkout). You will return here after payment.
                                </div>
                            <?php endif; ?>
                            <?php foreach ($onlineFeeCourses as $onlineCourse): ?>
                                <div class="border rounded p-3 mb-2 text-start">
                                    <strong><?php echo htmlspecialchars($onlineCourse['course_name']); ?></strong>
                                    <div>You pay ₹<?php echo number_format($onlineCourse['amount'], 2); ?>
                                        (₹<?php echo number_format((float) $onlineCourse['receive_amount'], 0); ?>
                                        + <?php echo htmlspecialchars(rtrim(rtrim(number_format((float) $onlineCourse['platform_percent'], 1), '0'), '.')); ?>% platform fee
                                        ₹<?php echo number_format((float) $onlineCourse['platform_fee'], 2); ?>)
                                        · Paid ₹<?php echo number_format($onlineCourse['paid'], 2); ?></div>
                                    <?php if ($onlineCourse['due'] <= 0): ?>
                                        <span class="badge bg-success mt-2">Paid</span>
                                    <?php elseif ($onlineGatewayReady): ?>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <?php if ($onlineHubReady): ?>
                                                <button type="button"
                                                        class="btn btn-primary btn-sm pay-course-fee"
                                                        data-gateway="razorpay"
                                                        data-course-id="<?php echo (int) $onlineCourse['course_id']; ?>"
                                                        data-record-id="<?php echo (int) $onlineCourse['record_id']; ?>">
                                                    <i class="fas fa-shield-alt"></i>
                                                    Pay ₹<?php echo number_format($onlineCourse['due'], 2); ?> via Payment Portal
                                                </button>
                                            <?php else: ?>
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
                                            <?php endif; ?>
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
                    <?php if (!empty($onlinePaymentHistory)): ?>
                        <h6 class="text-muted text-uppercase small fw-bold mb-2">Online payments (Payment Portal)</h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Course</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Invoice</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($onlinePaymentHistory as $op):
                                    $meta = payment_student_online_status_meta((string) ($op['status'] ?? ''));
                                    $dt = (string) ($op['paid_at'] ?? $op['created_at'] ?? '');
                                    $token = trim((string) ($op['gateway_order_id'] ?? ''));
                                    $canResume = ($op['status'] ?? '') === 'created' && $token !== '' && $token !== 'pending' && $onlineHubReady;
                                ?>
                                    <tr>
                                        <td class="small"><?php echo $dt !== '' ? htmlspecialchars(date('d M Y, h:i A', strtotime($dt))) : '—'; ?></td>
                                        <td><?php echo htmlspecialchars((string) ($op['course_name'] ?? 'Course')); ?></td>
                                        <td><strong>₹<?php echo number_format((float) ($op['amount'] ?? 0), 2); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo htmlspecialchars($meta['class']); ?>">
                                                <i class="fas fa-<?php echo htmlspecialchars($meta['icon']); ?>"></i>
                                                <?php echo htmlspecialchars($meta['label']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (($op['status'] ?? '') === 'paid'): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="payment_invoice.php?id=<?php echo (int) $op['id']; ?>">View invoice</a>
                                            <?php elseif ($canResume): ?>
                                                <a class="btn btn-sm btn-warning" href="<?php echo htmlspecialchars(payment_hub_pay_url($hubCfg, $token)); ?>">Continue payment</a>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if (count($payment_records) > 0): ?>
                        <h6 class="text-muted text-uppercase small fw-bold mb-2">Other payments</h6>
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
                                            <span class="badge bg-success">
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
                    <?php elseif (empty($onlinePaymentHistory)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No payment records yet</h5>
                            <p class="text-muted">When you pay course fees online, they will appear here with status Paid or Awaiting payment.</p>
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
