<?php
/**
 * Faculty AJAX actions — always return clean JSON (no notices/HTML after the payload).
 */
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/staff_profile_helper.php';

/**
 * @param array<string, mixed> $payload
 */
function faculty_ajax_json_exit(array $payload, int $status = 200): void
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
    faculty_ajax_json_exit(['success' => false, 'message' => 'Unauthorized'], 401);
}

$admin_id = (int) $_SESSION['admin_id'];
$admin_role = (string) ($_SESSION['admin_role'] ?? '');

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    faculty_ajax_json_exit(['success' => false, 'message' => 'Invalid JSON request body'], 400);
}

if (!isset($data['action'], $data['faculty_id'])) {
    faculty_ajax_json_exit(['success' => false, 'message' => 'Missing required fields'], 400);
}

$faculty_id = (int) $data['faculty_id'];
$action = (string) $data['action'];

if ($faculty_id <= 0) {
    faculty_ajax_json_exit(['success' => false, 'message' => 'Invalid faculty id'], 400);
}

ensureStaffProfileSchema($conn);

$check = $conn->prepare('SELECT id, name, created_by FROM faculty WHERE id = ?');
if (!$check) {
    faculty_ajax_json_exit(['success' => false, 'message' => 'Database error'], 500);
}
$check->bind_param('i', $faculty_id);
$check->execute();
$faculty = $check->get_result()->fetch_assoc();
$check->close();

if (!$faculty) {
    faculty_ajax_json_exit(['success' => false, 'message' => 'Faculty member not found'], 404);
}

if ($admin_role !== 'master_admin' && (int) ($faculty['created_by'] ?? 0) !== $admin_id) {
    faculty_ajax_json_exit(['success' => false, 'message' => 'You can only modify faculty members you have added'], 403);
}

switch ($action) {
    case 'deactivate':
        $stmt = $conn->prepare('UPDATE faculty SET is_active = 0 WHERE id = ?');
        if (!$stmt) {
            faculty_ajax_json_exit(['success' => false, 'message' => 'Database error'], 500);
        }
        $stmt->bind_param('i', $faculty_id);
        $ok = $stmt->execute();
        $stmt->close();
        faculty_ajax_json_exit($ok
            ? ['success' => true, 'message' => 'Faculty deactivated successfully']
            : ['success' => false, 'message' => 'Database error: ' . $conn->error], $ok ? 200 : 500);

    case 'delete':
        if ($admin_role !== 'master_admin') {
            faculty_ajax_json_exit(['success' => false, 'message' => 'Only master admins can permanently delete faculty'], 403);
        }
        $stmt = $conn->prepare('DELETE FROM faculty WHERE id = ?');
        if (!$stmt) {
            faculty_ajax_json_exit(['success' => false, 'message' => 'Database error'], 500);
        }
        $stmt->bind_param('i', $faculty_id);
        $ok = $stmt->execute();
        $stmt->close();
        faculty_ajax_json_exit($ok
            ? ['success' => true, 'message' => 'Faculty permanently deleted']
            : ['success' => false, 'message' => 'Database error: ' . $conn->error], $ok ? 200 : 500);

    case 'regenerate_profile_link':
        if ($admin_role !== 'master_admin') {
            faculty_ajax_json_exit(['success' => false, 'message' => 'Only master admin can generate staff profile links'], 403);
        }
        $result = regenerateStaffProfileToken($conn, $faculty_id);
        faculty_ajax_json_exit($result, !empty($result['success']) ? 200 : 400);

    case 'toggle_website_visibility':
        $field = (string) ($data['field'] ?? '');
        if (!in_array($field, ['show_on_website', 'show_on_contact'], true)) {
            faculty_ajax_json_exit(['success' => false, 'message' => 'Invalid visibility field'], 400);
        }

        $enabled = !empty($data['enabled']) ? 1 : 0;
        $rowStmt = $conn->prepare('SELECT show_on_website, show_on_contact, display_order, public_bio FROM faculty WHERE id = ? LIMIT 1');
        if (!$rowStmt) {
            faculty_ajax_json_exit(['success' => false, 'message' => 'Database error'], 500);
        }
        $rowStmt->bind_param('i', $faculty_id);
        $rowStmt->execute();
        $row = $rowStmt->get_result()->fetch_assoc() ?: [];
        $rowStmt->close();

        $payload = [
            'show_on_website' => (int) ($row['show_on_website'] ?? 0),
            'show_on_contact' => (int) ($row['show_on_contact'] ?? 0),
            'display_order' => (int) ($row['display_order'] ?? 0),
            'public_bio' => (string) ($row['public_bio'] ?? ''),
        ];
        $payload[$field] = $enabled;

        $visibility = saveStaffWebsiteVisibility($conn, $faculty_id, $payload);
        if (empty($visibility['success'])) {
            faculty_ajax_json_exit($visibility, 400);
        }

        faculty_ajax_json_exit([
            'success' => true,
            'message' => $enabled
                ? ($field === 'show_on_website' ? 'Shown on Our Team page' : 'Shown on Contact page')
                : ($field === 'show_on_website' ? 'Hidden from Our Team page' : 'Hidden from Contact page'),
            'field' => $field,
            'enabled' => $enabled,
        ]);

    default:
        faculty_ajax_json_exit(['success' => false, 'message' => 'Unknown action'], 400);
}
