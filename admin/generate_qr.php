<?php
/**
 * AJAX Endpoint for QR Code Generation
 * Generates QR code for specific course registration link
 */

session_start();
header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../config/config.php';
require_once '../includes/qr_helper.php';

// Get course ID from POST
$course_id = $_POST['course_id'] ?? 0;

if (empty($course_id) || !is_numeric($course_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
    exit();
}

// Fetch course details
$stmt = $conn->prepare("SELECT id, course_name, course_code, registration_token, qr_code_path FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Course not found']);
    exit();
}

$course = $result->fetch_assoc();

// Delete old QR code if exists
if (!empty($course['qr_code_path'])) {
    deleteQRCode($course['qr_code_path']);
}

// Generate new QR code with token
$sync = syncCourseRegistrationLinkAndQr($conn, (int) $course['id'], true);

if (!empty($sync['success'])) {
    echo json_encode([
        'success' => true,
        'message' => 'QR Code generated successfully!',
        'qr_path' => $sync['qr_code_path'] ?? '',
        'registration_link' => $sync['apply_link'] ?? '',
        'filename' => !empty($sync['qr_code_path']) ? basename($sync['qr_code_path']) : '',
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $sync['message'] ?? 'QR Code generation failed',
    ]);
}

$conn->close();
?>
