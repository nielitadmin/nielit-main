<?php
/**
 * AJAX Endpoint for QR Code Generation
 */
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

function qr_generate_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!isset($_SESSION['admin']) && empty($_SESSION['admin_logged_in'])) {
        qr_generate_json(['success' => false, 'message' => 'Unauthorized access'], 401);
    }

    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/qr_helper.php';

    $course_id = (int) ($_POST['course_id'] ?? 0);

    if ($course_id <= 0) {
        qr_generate_json(['success' => false, 'message' => 'Invalid course ID'], 400);
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        qr_generate_json(['success' => false, 'message' => 'Database connection unavailable'], 500);
    }

    $sync = syncCourseRegistrationLinkAndQr($conn, $course_id, true);

    if (!empty($sync['success'])) {
        qr_generate_json([
            'success' => true,
            'message' => 'QR Code generated successfully!',
            'qr_path' => $sync['qr_code_path'] ?? '',
            'registration_link' => $sync['apply_link'] ?? '',
            'filename' => !empty($sync['qr_code_path']) ? basename($sync['qr_code_path']) : '',
        ]);
    }

    qr_generate_json([
        'success' => false,
        'message' => $sync['message'] ?? 'QR Code generation failed',
    ], 500);
} catch (Throwable $e) {
    qr_generate_json([
        'success' => false,
        'message' => 'Server error while generating QR code.',
    ], 500);
}
