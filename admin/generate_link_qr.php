<?php
/**
 * AJAX: sync registration link + QR code for a course.
 */
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

function qr_api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/qr_helper.php';

    if (!isset($_SESSION['admin'])) {
        qr_api_json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $course_id = (int) ($_POST['course_id'] ?? 0);
    $course_code = trim((string) ($_POST['course_code'] ?? ''));

    if ($course_code === '') {
        qr_api_json(['success' => false, 'message' => 'Course code is required'], 400);
    }

    if ($course_id <= 0) {
        qr_api_json([
            'success' => true,
            'message' => 'Save the course first, then generate the link and QR code.',
            'apply_link' => null,
            'qr_code_path' => null,
            'qr_code_url' => null,
        ]);
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        qr_api_json(['success' => false, 'message' => 'Database connection unavailable'], 500);
    }

    $result = syncCourseRegistrationLinkAndQr($conn, $course_id, true);

    qr_api_json([
        'success' => !empty($result['success']),
        'message' => $result['message'] ?? ($result['success'] ? 'Link and QR code synced.' : 'Sync failed'),
        'apply_link' => $result['apply_link'] ?? null,
        'qr_code_path' => $result['qr_code_path'] ?? null,
        'qr_code_url' => $result['qr_code_url'] ?? null,
        'qr_target_url' => $result['qr_target_url'] ?? null,
    ]);
} catch (Throwable $e) {
    qr_api_json([
        'success' => false,
        'message' => 'Server error while generating QR code.',
    ], 500);
}
