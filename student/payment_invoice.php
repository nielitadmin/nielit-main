<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/payment_gateway_helper.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$studentCode = (string) $_SESSION['student_id'];
$paymentId = (int) ($_GET['id'] ?? 0);
payment_gateway_ensure_schema($conn);

$stmt = $conn->prepare("SELECT p.*, s.name, s.email
    FROM online_course_payments p
    LEFT JOIN students s ON s.student_id = p.student_code
    WHERE p.id = ? AND p.student_code = ? AND p.status = 'paid'
    LIMIT 1");
$row = null;
if ($stmt) {
    $stmt->bind_param('is', $paymentId, $studentCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$row) {
    http_response_code(404);
    echo 'Invoice not found.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo htmlspecialchars((string) $row['invoice_number']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 32px; }
        h1 { margin-bottom: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        td { padding: 8px 0; vertical-align: top; }
        .muted { color: #64748b; }
        .box { border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print invoice</button>
    <div class="box">
        <h1>Fee invoice</h1>
        <p class="muted">NIELIT Bhubaneswar</p>
        <table>
            <tr><td>Invoice</td><td><strong><?php echo htmlspecialchars((string) $row['invoice_number']); ?></strong></td></tr>
            <tr><td>Student</td><td><?php echo htmlspecialchars((string) ($row['name'] ?? $studentCode)); ?> (<?php echo htmlspecialchars($studentCode); ?>)</td></tr>
            <tr><td>Course</td><td><?php echo htmlspecialchars((string) $row['course_name']); ?></td></tr>
            <tr><td>Amount</td><td><strong>₹<?php echo number_format((float) $row['amount'], 2); ?></strong></td></tr>
            <tr><td>Paid on</td><td><?php echo htmlspecialchars((string) $row['paid_at']); ?></td></tr>
            <tr><td>Gateway</td><td>Razorpay</td></tr>
            <tr><td>Payment ID</td><td><?php echo htmlspecialchars((string) $row['gateway_payment_id']); ?></td></tr>
            <tr><td>Status</td><td>Paid</td></tr>
        </table>
    </div>
</body>
</html>
