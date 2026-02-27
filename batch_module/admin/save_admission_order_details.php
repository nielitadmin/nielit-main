<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['batch_id']) || empty($data['batch_id'])) {
    echo json_encode(['success' => false, 'message' => 'Batch ID is required']);
    exit();
}

$batch_id = intval($data['batch_id']);

// Prepare update query
$sql = "UPDATE batches SET 
        admission_order_ref = ?,
        admission_order_date = ?,
        location = ?,
        examination_month = ?,
        class_time = ?,
        batch_coordinator = ?,
        scheme_incharge = ?,
        copy_to_list = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}

// Bind parameters
$stmt->bind_param("ssssssssi",
    $data['admission_order_ref'],
    $data['admission_order_date'],
    $data['location'],
    $data['examination_month'],
    $data['class_time'],
    $data['batch_coordinator'],
    $data['scheme_incharge'],
    $data['copy_to_list'],
    $batch_id
);

// Execute query
if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Admission order details saved successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error saving details: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
