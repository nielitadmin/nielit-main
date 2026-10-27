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

    function batch_assignment_actor_id() {
        if (!empty($_SESSION['admin_id'])) {
            return (int) $_SESSION['admin_id'];
        }
        if (isset($_SESSION['admin']) && is_array($_SESSION['admin']) && !empty($_SESSION['admin']['id'])) {
            return (int) $_SESSION['admin']['id'];
        }
        return 0;
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
