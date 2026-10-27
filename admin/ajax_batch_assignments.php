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
        assignBatches($conn);
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

function batch_assignment_rows($conn) {
    $result = $conn->query("SELECT aba.id, aba.admin_id, aba.batch_id, aba.assigned_at,
            COALESCE(aba.assignment_type, 'Manual') AS assignment_type,
            a.username AS admin_name, a.email AS admin_email,
            b.batch_name, b.batch_code,
            COALESCE(c.course_name, '') AS course_name,
            COALESCE(ma.username, 'System') AS assigned_by_name
        FROM admin_batch_assignments aba
        JOIN admin a ON aba.admin_id = a.id
        JOIN batches b ON aba.batch_id = b.id
        LEFT JOIN courses c ON b.course_id = c.id
        LEFT JOIN admin ma ON aba.assigned_by = ma.id
        WHERE aba.is_active = 1
        ORDER BY aba.assigned_at DESC, a.username, b.batch_name");
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function batch_assignment_stats($conn) {
    $result = $conn->query("SELECT
        COUNT(DISTINCT admin_id) AS total_coordinators_with_assignments,
        COUNT(id) AS total_assignments,
        COUNT(DISTINCT batch_id) AS total_batches_assigned
        FROM admin_batch_assignments WHERE is_active = 1");
    $stats = $result ? $result->fetch_assoc() : null;
    return $stats ?: [
        'total_coordinators_with_assignments' => 0,
        'total_assignments' => 0,
        'total_batches_assigned' => 0,
    ];
}

function assignBatches($conn) {
    $admin_id = (int) ($_POST['admin_id'] ?? 0);
    $batch_ids = $_POST['batch_ids'] ?? [];
    $assigned_by = batch_assignment_actor_id();

    if ($admin_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid course coordinator.']);
        return;
    }
    if (!is_array($batch_ids) || $batch_ids === []) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one batch to assign.']);
        return;
    }
    if ($assigned_by <= 0) {
        echo json_encode(['success' => false, 'message' => 'Session error: Please log out and log back in.']);
        return;
    }

    $role = $conn->prepare("SELECT id FROM admin WHERE id = ? AND role = 'course_coordinator' AND is_active = 1");
    $role->bind_param('i', $admin_id);
    $role->execute();
    if (!$role->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'That user is not an active course coordinator.']);
        return;
    }

    $success = $duplicates = $errors = 0;
    $duplicate_names = [];
    $assigned_names = [];

    foreach ($batch_ids as $bid) {
        $bid = (int) $bid;
        if ($bid <= 0) {
            continue;
        }
        $nameStmt = $conn->prepare("SELECT batch_name FROM batches WHERE id = ?");
        $nameStmt->bind_param('i', $bid);
        $nameStmt->execute();
        $batch = $nameStmt->get_result()->fetch_assoc();
        if (!$batch) {
            $errors++;
            continue;
        }
        $batch_name = $batch['batch_name'] ?: ('Batch #' . $bid);

        $exist = $conn->prepare("SELECT id, is_active FROM admin_batch_assignments WHERE admin_id = ? AND batch_id = ?");
        $exist->bind_param('ii', $admin_id, $bid);
        $exist->execute();
        $existing = $exist->get_result()->fetch_assoc();

        if ($existing && (int) $existing['is_active'] === 1) {
            $duplicates++;
            $duplicate_names[] = $batch_name;
            continue;
        }

        if ($existing) {
            $sql = "UPDATE admin_batch_assignments
                    SET is_active = 1, assigned_at = NOW(), assigned_by = ?, assignment_type = 'Manual'
                    WHERE admin_id = ? AND batch_id = ?";
            $st = $conn->prepare($sql);
            $st->bind_param('iii', $assigned_by, $admin_id, $bid);
        } else {
            $sql = "INSERT INTO admin_batch_assignments
                    (admin_id, batch_id, is_active, assigned_by, assigned_at, assignment_type)
                    VALUES (?, ?, 1, ?, NOW(), 'Manual')";
            $st = $conn->prepare($sql);
            $st->bind_param('iii', $admin_id, $bid, $assigned_by);
        }

        if ($st && $st->execute()) {
            $success++;
            $assigned_names[] = $batch_name;
        } else {
            $errors++;
        }
    }

    $parts = [];
    $type = 'info';
    if ($success) {
        $parts[] = "Successfully assigned $success batch(es) to the coordinator.";
        $type = 'assignment';
    }
    if ($duplicates) {
        $parts[] = count($duplicate_names) === 1
            ? "'{$duplicate_names[0]}' is already assigned to this coordinator."
            : "$duplicates batches are already assigned: " . implode(', ', $duplicate_names) . '.';
        if (!$success) {
            $type = 'warning';
        }
    }
    if ($errors) {
        $parts[] = "Failed to assign $errors batch(es).";
        if (!$success && !$duplicates) {
            $type = 'error';
        }
    }

    echo json_encode([
        'success' => $success > 0,
        'message' => implode(' ', $parts),
        'type' => $type,
        'assigned_batches' => $assigned_names,
        'duplicate_batches' => $duplicate_names,
    ]);
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
