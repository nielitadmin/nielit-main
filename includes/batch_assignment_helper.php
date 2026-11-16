<?php
/**
 * Batch assignments for course coordinators.
 * Same idea as admin_course_assignments: a master admin grants specific batches.
 */

if (!function_exists('batch_assignment_ensure_schema')) {
    function batch_assignment_ensure_schema($conn) {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $sql = "CREATE TABLE IF NOT EXISTS admin_batch_assignments (
            id INT NOT NULL AUTO_INCREMENT,
            admin_id INT NOT NULL,
            batch_id INT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            assigned_by INT NULL,
            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            assignment_type VARCHAR(32) NOT NULL DEFAULT 'Manual',
            PRIMARY KEY (id),
            UNIQUE KEY uniq_admin_batch (admin_id, batch_id),
            KEY idx_batch_active (batch_id, is_active),
            KEY idx_admin_active (admin_id, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        return (bool) $conn->query($sql);
    }

    function batch_assignment_actor_id($conn = null) {
        if (!empty($_SESSION['admin_id'])) {
            return (int) $_SESSION['admin_id'];
        }
        if (isset($_SESSION['admin']) && is_array($_SESSION['admin']) && !empty($_SESSION['admin']['id'])) {
            return (int) $_SESSION['admin']['id'];
        }
        $username = '';
        if (isset($_SESSION['admin']) && is_string($_SESSION['admin'])) {
            $username = trim($_SESSION['admin']);
        } elseif (!empty($_SESSION['admin_username'])) {
            $username = trim((string) $_SESSION['admin_username']);
        }
        if ($username === '' || !$conn instanceof mysqli) {
            return 0;
        }
        $stmt = $conn->prepare('SELECT id FROM admin WHERE LOWER(username) = LOWER(?) LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            $_SESSION['admin_id'] = $id;
        }
        return $id;
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

    function batch_assignment_save($conn, $adminId, array $batchIds) {
        $adminId = (int) $adminId;
        $assignedBy = batch_assignment_actor_id($conn);
        if ($adminId <= 0) {
            return ['success' => false, 'type' => 'error', 'message' => 'Please select a valid course coordinator.'];
        }
        if ($batchIds === []) {
            return ['success' => false, 'type' => 'error', 'message' => 'Please select at least one batch to assign.'];
        }

        $role = $conn->prepare("SELECT id FROM admin WHERE id = ? AND role = 'course_coordinator' AND is_active = 1");
        if (!$role) {
            return ['success' => false, 'type' => 'error', 'message' => 'Could not check the coordinator.'];
        }
        $role->bind_param('i', $adminId);
        $role->execute();
        if (!$role->get_result()->fetch_assoc()) {
            $role->close();
            return ['success' => false, 'type' => 'error', 'message' => 'That user is not an active course coordinator.'];
        }
        $role->close();

        $success = $duplicates = $errors = 0;
        $duplicateNames = [];
        foreach ($batchIds as $batchId) {
            $batchId = (int) $batchId;
            if ($batchId <= 0) {
                continue;
            }
            $nameStmt = $conn->prepare('SELECT batch_name FROM batches WHERE id = ?');
            if (!$nameStmt) {
                $errors++;
                continue;
            }
            $nameStmt->bind_param('i', $batchId);
            $nameStmt->execute();
            $batch = $nameStmt->get_result()->fetch_assoc();
            $nameStmt->close();
            if (!$batch) {
                $errors++;
                continue;
            }
            $batchName = $batch['batch_name'] ?: ('Batch #' . $batchId);

            $exist = $conn->prepare('SELECT id, is_active FROM admin_batch_assignments WHERE admin_id = ? AND batch_id = ?');
            if (!$exist) {
                $errors++;
                continue;
            }
            $exist->bind_param('ii', $adminId, $batchId);
            $exist->execute();
            $existing = $exist->get_result()->fetch_assoc();
            $exist->close();
            if ($existing && (int) $existing['is_active'] === 1) {
                $duplicates++;
                $duplicateNames[] = $batchName;
                continue;
            }

            if ($existing) {
                if ($assignedBy > 0) {
                    $st = $conn->prepare("UPDATE admin_batch_assignments
                        SET is_active = 1, assigned_at = NOW(), assigned_by = ?, assignment_type = 'Manual'
                        WHERE admin_id = ? AND batch_id = ?");
                    $st->bind_param('iii', $assignedBy, $adminId, $batchId);
                } else {
                    $st = $conn->prepare("UPDATE admin_batch_assignments
                        SET is_active = 1, assigned_at = NOW(), assignment_type = 'Manual'
                        WHERE admin_id = ? AND batch_id = ?");
                    $st->bind_param('ii', $adminId, $batchId);
                }
            } elseif ($assignedBy > 0) {
                $st = $conn->prepare("INSERT INTO admin_batch_assignments
                    (admin_id, batch_id, is_active, assigned_by, assigned_at, assignment_type)
                    VALUES (?, ?, 1, ?, NOW(), 'Manual')");
                $st->bind_param('iii', $adminId, $batchId, $assignedBy);
            } else {
                $st = $conn->prepare("INSERT INTO admin_batch_assignments
                    (admin_id, batch_id, is_active, assigned_at, assignment_type)
                    VALUES (?, ?, 1, NOW(), 'Manual')");
                $st->bind_param('ii', $adminId, $batchId);
            }

            if ($st && $st->execute()) {
                $success++;
            } else {
                $errors++;
            }
            if ($st) {
                $st->close();
            }
        }

        $parts = [];
        $type = 'info';
        if ($success) {
            $parts[] = "Successfully assigned $success batch(es) to the coordinator.";
            $type = 'assignment';
        }
        if ($duplicates) {
            $parts[] = count($duplicateNames) === 1
                ? "'{$duplicateNames[0]}' is already assigned to this coordinator."
                : "$duplicates batches are already assigned: " . implode(', ', $duplicateNames) . '.';
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
        return [
            'success' => $success > 0,
            'type' => $type,
            'message' => implode(' ', $parts),
        ];
    }

    /**
     * SQL fragment so a coordinator sees batches they created and batches assigned to them.
     * Bind the admin id twice (created_by, then assignment admin_id).
     */
    function batch_assignment_coordinator_sql($conn, $batchAlias = 'b') {
        batch_assignment_ensure_schema($conn);
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', (string) $batchAlias);
        if ($alias === '') {
            $alias = 'b';
        }
        return "({$alias}.created_by = ? OR {$alias}.id IN (
            SELECT aba.batch_id FROM admin_batch_assignments aba
            WHERE aba.admin_id = ? AND aba.is_active = 1
        ))";
    }
}
