<?php
/**
 * Resend faculty confirmation email — clean JSON only.
 */
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/email_helper.php';

/**
 * @param array<string, mixed> $payload
 */
function faculty_resend_json_exit(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['admin']) || !isset($_SESSION['admin_id'])) {
    faculty_resend_json_exit(['success' => false, 'message' => 'Unauthorized'], 401);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    faculty_resend_json_exit(['success' => false, 'message' => 'Invalid JSON request body'], 400);
}

if (($data['action'] ?? '') !== 'resend_email') {
    faculty_resend_json_exit(['success' => false, 'message' => 'Invalid action'], 400);
}

$faculty_id = (int) ($data['faculty_id'] ?? 0);
if ($faculty_id <= 0) {
    faculty_resend_json_exit(['success' => false, 'message' => 'Faculty ID is required'], 400);
}

try {
    $stmt = $conn->prepare('SELECT id, name, email, designation, department FROM faculty WHERE id = ?');
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('i', $faculty_id);
    $stmt->execute();
    $faculty = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$faculty) {
        throw new Exception('Faculty member not found');
    }

    $email = trim((string) ($faculty['email'] ?? ''));
    if ($email === '') {
        throw new Exception('No email address on file for this faculty member');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address on file');
    }

    $email_sent = sendFacultyConfirmationEmail(
        $email,
        (string) ($faculty['name'] ?? ''),
        (string) ($faculty['designation'] ?? ''),
        (string) ($faculty['department'] ?? '')
    );

    if (!$email_sent) {
        throw new Exception('Failed to send email. Please try again later.');
    }

    $column_check = $conn->query("SHOW COLUMNS FROM faculty LIKE 'email_confirmed_at'");
    if ($column_check && $column_check->num_rows > 0) {
        $update_stmt = $conn->prepare('UPDATE faculty SET email_confirmed_at = NOW() WHERE id = ?');
        if ($update_stmt) {
            $update_stmt->bind_param('i', $faculty_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }

    faculty_resend_json_exit([
        'success' => true,
        'message' => 'Confirmation email sent successfully to ' . $email,
    ]);
} catch (Throwable $e) {
    faculty_resend_json_exit([
        'success' => false,
        'message' => $e->getMessage(),
    ], 400);
}
