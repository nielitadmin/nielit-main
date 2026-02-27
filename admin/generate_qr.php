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

require_once '../config/database.php';
require_once '../includes/qr_helper.php';

// Get course ID from POST
$course_id = $_POST['course_id'] ?? 0;

if (empty($course_id) || !is_numeric($course_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
    exit();
}

// Fetch course details
$stmt = $conn->prepare("SELECT id, course_name, course_code, qr_code_path FROM courses WHERE id = ?");
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

// Generate new QR code
$qr_result = generateCourseQRCode($course_id, $course['course_code']);

if ($qr_result['success']) {
    // Update database with QR path and registration link
    $stmt_update = $conn->prepare("UPDATE courses SET qr_code_path = ?, registration_link = ?, qr_generated_at = NOW() WHERE id = ?");
    $stmt_update->bind_param("ssi", $qr_result['path'], $qr_result['url'], $course_id);
    
    if ($stmt_update->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'QR Code generated successfully!',
            'qr_path' => $qr_result['path'],
            'registration_link' => $qr_result['url'],
            'filename' => $qr_result['filename']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'QR Code generated but database update failed'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => $qr_result['message']
    ]);
}

$conn->close();
?>
