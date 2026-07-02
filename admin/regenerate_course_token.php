<?php
/**
 * AJAX: new registration token + fresh QR PNG for one course.
 */
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

function token_api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/qr_helper.php';

    if (!isset($_SESSION['admin'])) {
        token_api_json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $course_id = (int) ($_POST['course_id'] ?? 0);
    if ($course_id <= 0) {
        token_api_json(['success' => false, 'message' => 'Invalid course ID'], 400);
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        token_api_json(['success' => false, 'message' => 'Database connection unavailable'], 500);
    }

    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    do {
        $token = '';
        for ($i = 0; $i < 8; $i++) {
            $token .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $check = $conn->prepare('SELECT id FROM courses WHERE registration_token = ? LIMIT 1');
        $exists = false;
        if ($check) {
            $check->bind_param('s', $token);
            $check->execute();
            $check->store_result();
            $exists = $check->num_rows > 0;
            $check->close();
        }
    } while ($exists);

    $stmt = $conn->prepare('UPDATE courses SET registration_token = ? WHERE id = ?');
    if (!$stmt) {
        token_api_json(['success' => false, 'message' => 'Could not update registration token'], 500);
    }
    $stmt->bind_param('si', $token, $course_id);
    if (!$stmt->execute()) {
        $stmt->close();
        token_api_json(['success' => false, 'message' => 'Failed to save new registration token'], 500);
    }
    $stmt->close();

    $sync = syncCourseRegistrationLinkAndQr($conn, $course_id, true);
    if (empty($sync['success'])) {
        token_api_json([
            'success' => false,
            'message' => 'Token updated, but QR sync failed: ' . ($sync['message'] ?? 'unknown error'),
            'registration_token' => $token,
            'apply_link' => $sync['apply_link'] ?? null,
        ], 500);
    }

    token_api_json([
        'success' => true,
        'message' => 'Registration link and QR code regenerated.',
        'registration_token' => $token,
        'apply_link' => $sync['apply_link'] ?? null,
        'qr_code_path' => $sync['qr_code_path'] ?? null,
        'qr_code_url' => $sync['qr_code_url'] ?? null,
        'qr_target_url' => $sync['qr_target_url'] ?? null,
    ]);
} catch (Throwable $e) {
    token_api_json(['success' => false, 'message' => 'Server error while regenerating link.'], 500);
}
