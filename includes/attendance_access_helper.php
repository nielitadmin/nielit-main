<?php
/**
 * Grant Student Attendance (QR + fingerprint) to faculty / course coordinators per centre.
 */

if (!function_exists('ensureAttendanceAccessTables')) {
    function ensureAttendanceAccessTables($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $sql = "CREATE TABLE IF NOT EXISTS attendance_module_access (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_id INT NOT NULL,
            centre_id INT NOT NULL DEFAULT 0,
            granted_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_att_admin_centre (admin_id, centre_id),
            KEY idx_att_admin (admin_id),
            KEY idx_att_centre (centre_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($sql)) {
            error_log('ensureAttendanceAccessTables failed: ' . $conn->error);
            return false;
        }
        $ready = true;
        return true;
    }
}

if (!function_exists('attendanceCurrentAdminNumericId')) {
    function attendanceCurrentAdminNumericId($conn = null): int
    {
        $id = (int) ($_SESSION['admin_id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
        $username = trim((string) ($_SESSION['admin'] ?? ''));
        if ($username === '' || !($conn instanceof mysqli)) {
            return 0;
        }
        $stmt = $conn->prepare('SELECT id FROM admin WHERE username = ? LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['id'] ?? 0);
    }
}

if (!function_exists('attendanceAdminCentreIds')) {
    /**
     * null = unrestricted (Master Admin). [] = no centres. [1, 2] = those centres.
     * @return list<int>|null
     */
    function attendanceAdminCentreIds($conn = null, ?string $role = null, ?int $adminId = null): ?array
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        if ($role === 'master_admin') {
            return null;
        }
        $adminId = $adminId ?? attendanceCurrentAdminNumericId($conn);
        if ($adminId <= 0 || !($conn instanceof mysqli)) {
            return [];
        }
        ensureAttendanceAccessTables($conn);
        $stmt = $conn->prepare('SELECT centre_id FROM attendance_module_access WHERE admin_id = ? AND centre_id > 0');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['centre_id'];
        }
        $stmt->close();
        return $ids;
    }
}

if (!function_exists('admin_can_access_attendance')) {
    function admin_can_access_attendance($conn = null, ?string $role = null, ?int $adminId = null): bool
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        if ($role === 'master_admin') {
            return true;
        }
        if (!in_array($role, ['course_coordinator', 'faculty'], true)) {
            return false;
        }
        $ids = attendanceAdminCentreIds($conn, $role, $adminId);
        return is_array($ids) && $ids !== [];
    }
}

if (!function_exists('attendance_require_access')) {
    function attendance_require_access($conn, bool $json = false): void
    {
        if (admin_can_access_attendance($conn)) {
            return;
        }
        $message = 'Access denied. Student Attendance is not assigned to your account. Ask a Master Admin to grant it for your centre.';
        if ($json) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = 'danger';
        $role = (string) ($_SESSION['admin_role'] ?? '');
        $url = function_exists('get_admin_post_login_url') ? get_admin_post_login_url($role) : 'dashboard.php';
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('attendanceRestrictCentreRows')) {
    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    function attendanceRestrictCentreRows($conn, array $rows): array
    {
        $ids = attendanceAdminCentreIds($conn);
        if ($ids === null) {
            return $rows;
        }
        if ($ids === []) {
            return [];
        }
        $allow = array_flip($ids);
        $out = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['id'] ?? $row['centre_id'] ?? 0);
            if (isset($allow[$cid])) {
                $out[] = $row;
            }
        }
        return $out;
    }
}

if (!function_exists('attendanceRestrictCourseRows')) {
    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    function attendanceRestrictCourseRows($conn, array $rows): array
    {
        $ids = attendanceAdminCentreIds($conn);
        if ($ids === null) {
            return $rows;
        }
        if ($ids === []) {
            return [];
        }
        $allow = array_flip($ids);
        $out = [];
        foreach ($rows as $row) {
            if (isset($allow[(int) ($row['centre_id'] ?? 0)])) {
                $out[] = $row;
            }
        }
        return $out;
    }
}

if (!function_exists('attendanceAdminCanUseCourse')) {
    function attendanceAdminCanUseCourse($conn, int $courseId): bool
    {
        if ($courseId <= 0) {
            return false;
        }
        $ids = attendanceAdminCentreIds($conn);
        if ($ids === null) {
            return true;
        }
        if ($ids === [] || !($conn instanceof mysqli)) {
            return false;
        }
        $col = $conn->query("SHOW COLUMNS FROM courses LIKE 'centre_id'");
        if (!$col || $col->num_rows === 0) {
            return true;
        }
        $stmt = $conn->prepare('SELECT centre_id FROM courses WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row && in_array((int) ($row['centre_id'] ?? 0), $ids, true);
    }
}

if (!function_exists('attendanceAppendGrantedCentreFilter')) {
    /**
     * Limit report SQL to centres the current admin is granted.
     * @param array<int,mixed> $params
     */
    function attendanceAppendGrantedCentreFilter($conn, string &$where_clause, array &$params, string &$types): void
    {
        $ids = attendanceAdminCentreIds($conn);
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $where_clause .= ' AND 1=0';
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where_clause .= " AND c.centre_id IN ({$placeholders})";
        foreach ($ids as $id) {
            $params[] = $id;
            $types .= 'i';
        }
    }
}

if (!function_exists('attendanceGrantAccess')) {
    /** @return array{success:bool,message:string} */
    function attendanceGrantAccess($conn, int $adminId, string $grantedBy, int $centreId = 0): array
    {
        ensureAttendanceAccessTables($conn);
        if ($adminId <= 0) {
            return ['success' => false, 'message' => 'Select a valid account.'];
        }
        if ($centreId <= 0) {
            return ['success' => false, 'message' => 'Select a training centre.'];
        }
        $cStmt = $conn->prepare('SELECT id, name FROM centres WHERE id = ? LIMIT 1');
        if (!$cStmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $cStmt->bind_param('i', $centreId);
        $cStmt->execute();
        $centre = $cStmt->get_result()->fetch_assoc();
        $cStmt->close();
        if (!$centre) {
            return ['success' => false, 'message' => 'Centre not found.'];
        }
        $check = $conn->prepare('SELECT id, username, role FROM admin WHERE id = ? LIMIT 1');
        if (!$check) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $check->bind_param('i', $adminId);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$row) {
            return ['success' => false, 'message' => 'Admin account not found.'];
        }
        $role = (string) ($row['role'] ?? '');
        if (!in_array($role, ['course_coordinator', 'faculty'], true)) {
            return ['success' => false, 'message' => 'Attendance access can only be granted to Course Coordinators or Faculty.'];
        }
        $stmt = $conn->prepare(
            'INSERT INTO attendance_module_access (admin_id, centre_id, granted_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param('iis', $adminId, $centreId, $grantedBy);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not grant access.'];
        }
        return ['success' => true, 'message' => 'Student Attendance granted to ' . $row['username'] . ' for ' . $centre['name'] . '.'];
    }
}

if (!function_exists('attendanceRevokeAccess')) {
    /** @return array{success:bool,message:string} */
    function attendanceRevokeAccess($conn, int $adminId, int $centreId = 0): array
    {
        ensureAttendanceAccessTables($conn);
        if ($adminId <= 0) {
            return ['success' => false, 'message' => 'Invalid account.'];
        }
        if ($centreId > 0) {
            $stmt = $conn->prepare('DELETE FROM attendance_module_access WHERE admin_id = ? AND centre_id = ?');
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error.'];
            }
            $stmt->bind_param('ii', $adminId, $centreId);
        } else {
            $stmt = $conn->prepare('DELETE FROM attendance_module_access WHERE admin_id = ?');
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error.'];
            }
            $stmt->bind_param('i', $adminId);
        }
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not revoke access.'];
        }
        return ['success' => true, 'message' => $centreId > 0 ? 'Attendance access revoked for that centre.' : 'Attendance access revoked.'];
    }
}

if (!function_exists('listAttendanceAccessCandidates')) {
    /** @return list<array<string,mixed>> */
    function listAttendanceAccessCandidates($conn): array
    {
        ensureAttendanceAccessTables($conn);
        $admins = [];
        $res = $conn->query("SELECT id, username, role, email FROM admin WHERE role IN ('course_coordinator','faculty') ORDER BY role ASC, username ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $row['grants'] = [];
                $admins[(int) $row['id']] = $row;
            }
        }
        $gRes = $conn->query(
            "SELECT ama.id AS grant_id, ama.admin_id, ama.centre_id, ama.granted_by, ama.created_at AS granted_at,
                    c.name AS centre_name
             FROM attendance_module_access ama
             LEFT JOIN centres c ON c.id = ama.centre_id
             ORDER BY c.name ASC"
        );
        if ($gRes) {
            while ($g = $gRes->fetch_assoc()) {
                $aid = (int) $g['admin_id'];
                if (!isset($admins[$aid])) {
                    continue;
                }
                $admins[$aid]['grants'][] = $g;
            }
        }
        return array_values($admins);
    }
}

if (!function_exists('listAttendanceGrantCentres')) {
    /** @return list<array<string,mixed>> */
    function listAttendanceGrantCentres($conn): array
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }
        $t = $conn->query("SHOW TABLES LIKE 'centres'");
        if (!$t || $t->num_rows === 0) {
            return [];
        }
        $sql = 'SELECT id, name, code FROM centres';
        $active = $conn->query("SHOW COLUMNS FROM centres LIKE 'is_active'");
        if ($active && $active->num_rows > 0) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';
        $r = $conn->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }
}
