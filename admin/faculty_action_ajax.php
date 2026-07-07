<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/staff_profile_helper.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['admin']) || !isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$admin_id   = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'] ?? '';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['action']) || !isset($data['faculty_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$faculty_id = intval($data['faculty_id']);
$action     = $data['action'];

// Fetch faculty to check permissions
$check = $conn->prepare("SELECT id, name, created_by FROM faculty WHERE id = ?");
$check->bind_param("i", $faculty_id);
$check->execute();
$faculty = $check->get_result()->fetch_assoc();
$check->close();

if (!$faculty) {
    echo json_encode(['success' => false, 'message' => 'Faculty member not found']);
    exit();
}

// Permission check: master_admin can do anything; coordinator only their own
if ($admin_role !== 'master_admin' && $faculty['created_by'] != $admin_id) {
    echo json_encode(['success' => false, 'message' => 'You can only modify faculty members you have added']);
    exit();
}

switch ($action) {
    case 'deactivate':
        $stmt = $conn->prepare("UPDATE faculty SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $faculty_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Faculty deactivated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
        break;

    case 'delete':
        // Only master_admin can permanently delete
        if ($admin_role !== 'master_admin') {
            echo json_encode(['success' => false, 'message' => 'Only master admins can permanently delete faculty']);
            exit();
        }
        $stmt = $conn->prepare("DELETE FROM faculty WHERE id = ?");
        $stmt->bind_param("i", $faculty_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Faculty permanently deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
        break;

    case 'regenerate_profile_link':
        if ($admin_role !== 'master_admin') {
            echo json_encode(['success' => false, 'message' => 'Only master admin can generate staff profile links']);
            exit();
        }

        $result = regenerateStaffProfileToken($conn, $faculty_id);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

$conn->close();
?>
