<?php
session_start();
header('Content-Type: application/json');

$is_logged_in = isset($_SESSION['admin_logged_in']) || isset($_SESSION['admin']);
if (!$is_logged_in) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'master_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/batch_assignment_helper.php';

batch_assignment_ensure_schema($conn);

$action = $_POST['action'] ?? '';
switch ($action) {
    case 'assign_batches':
        $saved = batch_assignment_save($conn, (int) ($_POST['admin_id'] ?? 0), (array) ($_POST['batch_ids'] ?? []));
        echo json_encode($saved);
        break;
    case 'remove_assignment':
        removeBatchAssignment($conn);
        break;
    case 'get_assignments':
        echo json_encode(['success' => true, 'assignments' => batch_assignment_rows($conn)]);
        break;
    case 'get_stats':
        echo json_encode(['success' => true, 'stats' => batch_assignment_stats($conn)]);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function removeBatchAssignment($conn) {
    $assignment_id = (int) ($_POST['assignment_id'] ?? 0);
    if ($assignment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
        return;
    }

    $s = $conn->prepare("SELECT a.username AS admin_name, b.batch_name
        FROM admin_batch_assignments aba
        JOIN admin a ON aba.admin_id = a.id
        JOIN batches b ON aba.batch_id = b.id
        WHERE aba.id = ? AND aba.is_active = 1");
    $s->bind_param('i', $assignment_id);
    $s->execute();
    $details = $s->get_result()->fetch_assoc();
    if (!$details) {
        echo json_encode(['success' => false, 'message' => 'Assignment not found or already removed']);
        return;
    }

    $r = $conn->prepare("UPDATE admin_batch_assignments SET is_active = 0 WHERE id = ?");
    $r->bind_param('i', $assignment_id);
    if ($r->execute()) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully removed '{$details['batch_name']}' from '{$details['admin_name']}'",
        ]);
        return;
    }
    echo json_encode(['success' => false, 'message' => 'Failed to remove batch assignment']);
}
