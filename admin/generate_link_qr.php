<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/qr_helper.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$course_id = $_POST['course_id'] ?? null;
$course_name = $_POST['course_name'] ?? '';
$course_code = $_POST['course_code'] ?? '';
$force_regenerate = isset($_POST['force_regenerate']) && $_POST['force_regenerate'] == '1';

if (empty($course_code)) {
    echo json_encode(['success' => false, 'message' => 'Course code is required']);
    exit();
}

// Generate registration link using course_code (not course_name)
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$baseUrl .= str_replace('admin/generate_link_qr.php', '', $_SERVER['REQUEST_URI']);
$apply_link = $baseUrl . 'student/register.php?course=' . urlencode($course_code);

// If course_id exists, update the database and generate QR
if ($course_id) {
    // Update the apply_link in database
    $stmt = $conn->prepare("UPDATE courses SET apply_link = ? WHERE id = ?");
    $stmt->bind_param("si", $apply_link, $course_id);
    
    if ($stmt->execute()) {
        // Check if we should regenerate QR code
        $should_generate_qr = false;
        
        if ($force_regenerate) {
            // Force regeneration requested
            $should_generate_qr = true;
            
            // Get and delete old QR code
            $stmt_get = $conn->prepare("SELECT qr_code_path FROM courses WHERE id = ?");
            $stmt_get->bind_param("i", $course_id);
            $stmt_get->execute();
            $result_get = $stmt_get->get_result();
            $old_course = $result_get->fetch_assoc();
            
            if (!empty($old_course['qr_code_path'])) {
                deleteQRCode($old_course['qr_code_path']);
            }
        } else {
            // Check if QR code already exists
            $stmt_check = $conn->prepare("SELECT qr_code_path FROM courses WHERE id = ?");
            $stmt_check->bind_param("i", $course_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $existing_course = $result_check->fetch_assoc();
            
            // Only generate if QR doesn't exist
            $should_generate_qr = empty($existing_course['qr_code_path']);
        }
        
        if ($should_generate_qr) {
            // Generate QR code
            $qr_result = generateCourseQRCode($course_id, $course_code);
            
            if ($qr_result['success']) {
                // Update QR code path in database
                $stmt_qr = $conn->prepare("UPDATE courses SET qr_code_path = ?, qr_generated_at = NOW() WHERE id = ?");
                $stmt_qr->bind_param("si", $qr_result['path'], $course_id);
                $stmt_qr->execute();
                
                echo json_encode([
                    'success' => true,
                    'message' => $force_regenerate ? 'QR code regenerated successfully!' : 'Link and QR code generated successfully!',
                    'apply_link' => $apply_link,
                    'qr_code_path' => $qr_result['path'],
                    'qr_code_url' => APP_URL . '/' . $qr_result['path']
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Link generated but QR code failed: ' . $qr_result['error'],
                    'apply_link' => $apply_link,
                    'qr_code_path' => null
                ]);
            }
        } else {
            // QR already exists, just return success
            echo json_encode([
                'success' => true,
                'message' => 'Link updated successfully! QR code already exists.',
                'apply_link' => $apply_link,
                'qr_code_path' => null
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update database']);
    }
} else {
    // For new courses (no ID yet), just return the link
    // QR will be generated when course is saved
    echo json_encode([
        'success' => true,
        'message' => 'Link generated! QR code will be created when you save the course.',
        'apply_link' => $apply_link,
        'qr_code_path' => null
    ]);
}

$conn->close();
?>
