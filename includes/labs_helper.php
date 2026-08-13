<?php
/**
 * Lab Instruments and IT / Computer Lab — stock, parts, issue/return, per-centre grants.
 */

require_once __DIR__ . '/library_helper.php';

if (!function_exists('labModules')) {
    /** @return array<string,array<string,mixed>> */
    function labModules(): array
    {
        return [
            'instrument' => [
                'key' => 'instrument',
                'title' => 'Lab Instruments',
                'short' => 'Instruments',
                'item_label' => 'instrument',
                'item_plural' => 'instruments',
                'code_label' => 'Asset No',
                'name_label' => 'Instrument name',
                'home' => 'lab_instruments.php',
                'stock' => 'lab_instruments_stock.php',
                'student' => 'lab_instruments_student_issues.php',
                'staff' => 'lab_instruments_staff_issues.php',
                'icon' => 'microchip',
                'stock_icon' => 'toolbox',
                'has_parts' => false,
                'due_student' => 7,
                'due_staff' => 14,
                'categories' => [
                    'Drone' => 'Drone',
                    'IoT' => 'IoT',
                    'Robotics' => 'Robotics',
                    'Embedded' => 'Embedded',
                    'Electronics' => 'Electronics',
                    'Sensors' => 'Sensors',
                    '3D Printer' => '3D Printer',
                    'Other' => 'Other',
                ],
            ],
            'itlab' => [
                'key' => 'itlab',
                'title' => 'IT / Computer Lab',
                'short' => 'IT Lab',
                'item_label' => 'system',
                'item_plural' => 'systems',
                'code_label' => 'System No',
                'name_label' => 'System / PC name',
                'home' => 'it_lab.php',
                'stock' => 'it_lab_systems.php',
                'student' => 'it_lab_student_issues.php',
                'staff' => 'it_lab_staff_issues.php',
                'icon' => 'desktop',
                'stock_icon' => 'keyboard',
                'has_parts' => true,
                'due_student' => 7,
                'due_staff' => 14,
                'categories' => [
                    'Desktop' => 'Desktop',
                    'Laptop' => 'Laptop',
                    'Server' => 'Server',
                    'Printer' => 'Printer',
                    'Network' => 'Network',
                    'Other' => 'Other',
                ],
            ],
        ];
    }
}

if (!function_exists('labModule')) {
    /** @return array<string,mixed>|null */
    function labModule(string $key): ?array
    {
        $all = labModules();
        return $all[$key] ?? null;
    }
}

if (!function_exists('labItemStatuses')) {
    /** @return array<string,string> */
    function labItemStatuses(): array
    {
        return [
            'available' => 'Available',
            'issued' => 'Issued',
            'under_repair' => 'Under repair',
            'damaged' => 'Damaged',
            'lost' => 'Lost',
        ];
    }
}

if (!function_exists('labPartTypes')) {
    /** @return array<string,string> */
    function labPartTypes(): array
    {
        return [
            'Keyboard' => 'Keyboard',
            'Mouse' => 'Mouse',
            'Monitor' => 'Monitor',
            'CPU Cabinet' => 'CPU Cabinet',
            'Motherboard' => 'Motherboard',
            'RAM' => 'RAM',
            'HDD' => 'HDD',
            'SSD' => 'SSD',
            'SMPS' => 'SMPS / Adapter',
            'LAN Card' => 'LAN / Wi-Fi',
            'Webcam' => 'Webcam',
            'Speaker' => 'Speaker',
            'Other' => 'Other',
        ];
    }
}

if (!function_exists('labPartStatuses')) {
    /** @return array<string,string> */
    function labPartStatuses(): array
    {
        return [
            'working' => 'Working',
            'damaged' => 'Damaged',
            'replaced' => 'Replaced',
            'missing' => 'Missing',
            'spare' => 'Spare',
        ];
    }
}

if (!function_exists('labDefaultDueDays')) {
    function labDefaultDueDays(string $module, string $borrowerType): int
    {
        $cfg = labModule($module);
        if (!$cfg) {
            return $borrowerType === 'staff' ? 14 : 7;
        }
        return $borrowerType === 'staff' ? (int) $cfg['due_staff'] : (int) $cfg['due_student'];
    }
}

if (!function_exists('ensureLabTables')) {
    function ensureLabTables($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $items = "CREATE TABLE IF NOT EXISTS lab_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            module VARCHAR(20) NOT NULL,
            code VARCHAR(50) NOT NULL,
            centre_id INT NULL,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(100) NULL,
            make_name VARCHAR(120) NULL,
            model_name VARCHAR(120) NULL,
            serial_no VARCHAR(120) NULL,
            lab_name VARCHAR(120) NULL,
            location_note VARCHAR(120) NULL,
            specs TEXT NULL,
            purchase_date DATE NULL,
            price DECIMAL(10,2) NULL,
            remarks TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'available',
            created_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_lab_module_centre_code (module, centre_id, code),
            KEY idx_lab_module (module),
            KEY idx_lab_status (status),
            KEY idx_lab_centre (centre_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $issues = "CREATE TABLE IF NOT EXISTS lab_item_issues (
            id INT PRIMARY KEY AUTO_INCREMENT,
            item_id INT NOT NULL,
            borrower_type VARCHAR(20) NOT NULL,
            student_id VARCHAR(50) NULL,
            faculty_id INT NULL,
            issue_date DATE NOT NULL,
            due_date DATE NOT NULL,
            return_date DATE NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'issued',
            remarks TEXT NULL,
            issued_by VARCHAR(255) NULL,
            returned_by VARCHAR(255) NULL,
            last_reminder_on DATE NULL,
            last_reminder_kind VARCHAR(20) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_lii_item (item_id),
            KEY idx_lii_type (borrower_type),
            KEY idx_lii_status (status),
            KEY idx_lii_student (student_id),
            KEY idx_lii_faculty (faculty_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $parts = "CREATE TABLE IF NOT EXISTS lab_item_parts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            item_id INT NOT NULL,
            part_type VARCHAR(80) NOT NULL,
            brand VARCHAR(120) NULL,
            serial_no VARCHAR(120) NULL,
            remarks TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'working',
            created_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_lip_item (item_id),
            KEY idx_lip_type (part_type),
            KEY idx_lip_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $access = "CREATE TABLE IF NOT EXISTS lab_module_access (
            id INT PRIMARY KEY AUTO_INCREMENT,
            module VARCHAR(20) NOT NULL,
            admin_id INT NOT NULL,
            centre_id INT NOT NULL DEFAULT 0,
            granted_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_lab_mod_admin_centre (module, admin_id, centre_id),
            KEY idx_lma_admin (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        foreach (['items' => $items, 'issues' => $issues, 'parts' => $parts, 'access' => $access] as $label => $sql) {
            if (!$conn->query($sql)) {
                error_log('ensureLabTables ' . $label . ' failed: ' . $conn->error);
                return false;
            }
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('admin_can_access_lab')) {
    function admin_can_access_lab($conn, string $module, ?string $role = null, ?int $adminId = null): bool
    {
        if (!labModule($module)) {
            return false;
        }
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        if ($role === 'master_admin') {
            return true;
        }
        if (!in_array($role, ['course_coordinator', 'faculty'], true)) {
            return false;
        }
        $adminId = $adminId ?? (int) ($_SESSION['admin_id'] ?? 0);
        if ($adminId <= 0 || !($conn instanceof mysqli)) {
            return false;
        }
        ensureLabTables($conn);
        $stmt = $conn->prepare('SELECT id FROM lab_module_access WHERE module = ? AND admin_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $module, $adminId);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('lab_require_access')) {
    function lab_require_access($conn, string $module): void
    {
        if (admin_can_access_lab($conn, $module)) {
            return;
        }
        $_SESSION['message'] = 'Access denied. This lab module is not assigned to your account.';
        $_SESSION['message_type'] = 'danger';
        $role = (string) ($_SESSION['admin_role'] ?? '');
        $url = function_exists('get_admin_post_login_url') ? get_admin_post_login_url($role) : 'dashboard.php';
        header('Location: ' . $url);
        exit();
    }
}

if (!function_exists('labGrantedCentreIds')) {
    /** @return list<int>|null */
    function labGrantedCentreIds($conn, string $module): ?array
    {
        $role = (string) ($_SESSION['admin_role'] ?? '');
        if ($role === 'master_admin') {
            return null;
        }
        $adminId = (int) ($_SESSION['admin_id'] ?? 0);
        if ($adminId <= 0) {
            return null;
        }
        if (!($conn instanceof mysqli)) {
            return [];
        }
        ensureLabTables($conn);
        $stmt = $conn->prepare('SELECT centre_id FROM lab_module_access WHERE module = ? AND admin_id = ?');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('si', $module, $adminId);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        $hasAll = false;
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cid = (int) ($row['centre_id'] ?? 0);
                if ($cid === 0) {
                    $hasAll = true;
                } else {
                    $ids[] = $cid;
                }
            }
        }
        $stmt->close();
        if ($hasAll) {
            return null;
        }
        return array_values(array_unique($ids));
    }
}

if (!function_exists('labCanAccessCentre')) {
    function labCanAccessCentre($conn, string $module, int $centreId): bool
    {
        $ids = labGrantedCentreIds($conn, $module);
        if ($ids === null) {
            return true;
        }
        return $centreId > 0 && in_array($centreId, $ids, true);
    }
}

if (!function_exists('labAppendCentreFilter')) {
    /**
     * @param list<mixed> $params
     */
    function labAppendCentreFilter(string $column, string &$sql, string &$types, array &$params, $conn, string $module, int $filterCentre = 0): void
    {
        $ids = labGrantedCentreIds($conn, $module);
        if ($filterCentre > 0) {
            if ($ids !== null && !in_array($filterCentre, $ids, true)) {
                $sql .= ' AND 1=0';
                return;
            }
            $sql .= ' AND ' . $column . ' = ?';
            $types .= 'i';
            $params[] = $filterCentre;
            return;
        }
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $sql .= ' AND 1=0';
            return;
        }
        $sql .= ' AND ' . $column . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $types .= str_repeat('i', count($ids));
        foreach ($ids as $id) {
            $params[] = $id;
        }
    }
}

if (!function_exists('labListCentres')) {
    /** @return list<array<string,mixed>> */
    function labListCentres($conn, string $module, bool $onlyGranted = true): array
    {
        $rows = [];
        $res = $conn->query("SELECT id, name, code, city FROM centres WHERE is_active = 1 ORDER BY name ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        if (!$onlyGranted) {
            return $rows;
        }
        $ids = labGrantedCentreIds($conn, $module);
        if ($ids === null) {
            return $rows;
        }
        $allowed = array_flip($ids);
        return array_values(array_filter($rows, static function ($row) use ($allowed) {
            return isset($allowed[(int) $row['id']]);
        }));
    }
}

if (!function_exists('labGrantAccess')) {
    /** @return array{success:bool,message:string} */
    function labGrantAccess($conn, string $module, int $adminId, string $grantedBy, int $centreId = 0): array
    {
        $cfg = labModule($module);
        if (!$cfg) {
            return ['success' => false, 'message' => 'Unknown lab module.'];
        }
        ensureLabTables($conn);
        if ($adminId <= 0) {
            return ['success' => false, 'message' => 'Select a valid account.'];
        }
        if ($centreId <= 0) {
            return ['success' => false, 'message' => 'Select a training centre.'];
        }
        $cStmt = $conn->prepare('SELECT id, name FROM centres WHERE id = ? AND is_active = 1 LIMIT 1');
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
        $check = $conn->prepare("SELECT id, username, role FROM admin WHERE id = ? LIMIT 1");
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
            return ['success' => false, 'message' => 'Access can only be granted to Course Coordinators or Faculty.'];
        }
        $stmt = $conn->prepare(
            'INSERT INTO lab_module_access (module, admin_id, centre_id, granted_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param('siis', $module, $adminId, $centreId, $grantedBy);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not grant access.'];
        }
        return ['success' => true, 'message' => $cfg['short'] . ' access granted to ' . $row['username'] . ' for ' . $centre['name'] . '.'];
    }
}

if (!function_exists('labRevokeAccess')) {
    /** @return array{success:bool,message:string} */
    function labRevokeAccess($conn, string $module, int $adminId, int $centreId = 0): array
    {
        ensureLabTables($conn);
        if ($adminId <= 0) {
            return ['success' => false, 'message' => 'Invalid account.'];
        }
        if ($centreId > 0) {
            $stmt = $conn->prepare('DELETE FROM lab_module_access WHERE module = ? AND admin_id = ? AND centre_id = ?');
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error.'];
            }
            $stmt->bind_param('sii', $module, $adminId, $centreId);
        } else {
            $stmt = $conn->prepare('DELETE FROM lab_module_access WHERE module = ? AND admin_id = ?');
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error.'];
            }
            $stmt->bind_param('si', $module, $adminId);
        }
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not revoke access.'];
        }
        return ['success' => true, 'message' => $centreId > 0 ? 'Access revoked for that centre.' : 'Access revoked.'];
    }
}

if (!function_exists('listLabAccessCandidates')) {
    /** @return list<array<string,mixed>> */
    function listLabAccessCandidates($conn, string $module): array
    {
        ensureLabTables($conn);
        $admins = [];
        $res = $conn->query("SELECT id, username, role, email FROM admin WHERE role IN ('course_coordinator','faculty') ORDER BY role ASC, username ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $row['grants'] = [];
                $admins[(int) $row['id']] = $row;
            }
        }
        $stmt = $conn->prepare(
            "SELECT lma.id AS grant_id, lma.admin_id, lma.centre_id, lma.granted_by, lma.created_at AS granted_at,
                    c.name AS centre_name
             FROM lab_module_access lma
             LEFT JOIN centres c ON c.id = lma.centre_id
             WHERE lma.module = ?
             ORDER BY c.name ASC"
        );
        if ($stmt) {
            $stmt->bind_param('s', $module);
            $stmt->execute();
            $gRes = $stmt->get_result();
            if ($gRes) {
                while ($g = $gRes->fetch_assoc()) {
                    $aid = (int) $g['admin_id'];
                    if (!isset($admins[$aid])) {
                        continue;
                    }
                    $admins[$aid]['grants'][] = $g;
                }
            }
            $stmt->close();
        }
        return array_values($admins);
    }
}

if (!function_exists('labStats')) {
    /** @return array{total:int,available:int,issued:int,overdue:int,repair:int,damaged_parts:int} */
    function labStats($conn, string $module): array
    {
        ensureLabTables($conn);
        $stats = ['total' => 0, 'available' => 0, 'issued' => 0, 'overdue' => 0, 'repair' => 0, 'damaged_parts' => 0];
        $sql = 'SELECT status, COUNT(*) AS c FROM lab_items b WHERE b.module = ?';
        $types = 's';
        $params = [$module];
        labAppendCentreFilter('b.centre_id', $sql, $types, $params, $conn, $module);
        $sql .= ' GROUP BY status';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $c = (int) $row['c'];
                    $stats['total'] += $c;
                    if (($row['status'] ?? '') === 'available') {
                        $stats['available'] = $c;
                    }
                    if (($row['status'] ?? '') === 'issued') {
                        $stats['issued'] = $c;
                    }
                    if (($row['status'] ?? '') === 'under_repair') {
                        $stats['repair'] = $c;
                    }
                }
            }
            $stmt->close();
        }

        $overSql = "SELECT COUNT(*) AS c FROM lab_item_issues i
                    INNER JOIN lab_items b ON b.id = i.item_id
                    WHERE b.module = ? AND i.status = 'issued' AND i.due_date < CURDATE()";
        $overTypes = 's';
        $overParams = [$module];
        labAppendCentreFilter('b.centre_id', $overSql, $overTypes, $overParams, $conn, $module);
        $stmt = $conn->prepare($overSql);
        if ($stmt) {
            $stmt->bind_param($overTypes, ...$overParams);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                $stats['overdue'] = (int) ($res->fetch_assoc()['c'] ?? 0);
            }
            $stmt->close();
        }

        $cfg = labModule($module);
        if (!empty($cfg['has_parts'])) {
            $pSql = "SELECT COUNT(*) AS c FROM lab_item_parts p
                     INNER JOIN lab_items b ON b.id = p.item_id
                     WHERE b.module = ? AND p.status IN ('damaged','missing')";
            $pTypes = 's';
            $pParams = [$module];
            labAppendCentreFilter('b.centre_id', $pSql, $pTypes, $pParams, $conn, $module);
            $pStmt = $conn->prepare($pSql);
            if ($pStmt) {
                $pStmt->bind_param($pTypes, ...$pParams);
                $pStmt->execute();
                $pRes = $pStmt->get_result();
                if ($pRes) {
                    $stats['damaged_parts'] = (int) ($pRes->fetch_assoc()['c'] ?? 0);
                }
                $pStmt->close();
            }
        }
        return $stats;
    }
}

if (!function_exists('saveLabItem')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string,id?:int}
     */
    function saveLabItem($conn, string $module, array $data, ?int $id = null): array
    {
        $cfg = labModule($module);
        if (!$cfg) {
            return ['success' => false, 'message' => 'Unknown lab module.'];
        }
        ensureLabTables($conn);
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || $name === '') {
            return ['success' => false, 'message' => $cfg['code_label'] . ' and name are required.'];
        }
        $centreId = (int) ($data['centre_id'] ?? 0);
        if ($centreId <= 0) {
            return ['success' => false, 'message' => 'Select a training centre.'];
        }
        if (!labCanAccessCentre($conn, $module, $centreId)) {
            return ['success' => false, 'message' => 'You do not have access for that centre.'];
        }

        $category = trim((string) ($data['category'] ?? ''));
        $make = trim((string) ($data['make_name'] ?? ''));
        $model = trim((string) ($data['model_name'] ?? ''));
        $serial = trim((string) ($data['serial_no'] ?? ''));
        $labName = trim((string) ($data['lab_name'] ?? ''));
        $location = trim((string) ($data['location_note'] ?? ''));
        $specs = trim((string) ($data['specs'] ?? ''));
        $purchaseDate = trim((string) ($data['purchase_date'] ?? ''));
        $priceRaw = trim((string) ($data['price'] ?? ''));
        $price = $priceRaw === '' ? 0.0 : (float) $priceRaw;
        $remarks = trim((string) ($data['remarks'] ?? ''));
        $status = (string) ($data['status'] ?? 'available');
        if (!isset(labItemStatuses()[$status]) || $status === 'issued') {
            $status = 'available';
        }
        $createdBy = trim((string) ($data['created_by'] ?? ''));

        $dup = $conn->prepare('SELECT id FROM lab_items WHERE module = ? AND code = ? AND centre_id <=> ? AND id <> ? LIMIT 1');
        $checkId = $id ?? 0;
        if ($dup) {
            $dup->bind_param('ssii', $module, $code, $centreId, $checkId);
            $dup->execute();
            $exists = $dup->get_result()->fetch_assoc();
            $dup->close();
            if ($exists) {
                return ['success' => false, 'message' => $cfg['code_label'] . ' already exists at this centre.'];
            }
        }

        if ($id !== null && $id > 0) {
            $current = getLabItem($conn, $module, $id);
            if (!$current) {
                return ['success' => false, 'message' => 'Record not found.'];
            }
            if (($current['status'] ?? '') === 'issued') {
                $status = 'issued';
            }
            $stmt = $conn->prepare(
                'UPDATE lab_items SET code=?, centre_id=?, name=?, category=?, make_name=?, model_name=?, serial_no=?,
                 lab_name=?, location_note=?, specs=?, purchase_date=NULLIF(?, \'\'), price=NULLIF(?, 0), remarks=?, status=?
                 WHERE id=? AND module=?'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
            $stmt->bind_param(
                'sisssssssssdssis',
                $code,
                $centreId,
                $name,
                $category,
                $make,
                $model,
                $serial,
                $labName,
                $location,
                $specs,
                $purchaseDate,
                $price,
                $remarks,
                $status,
                $id,
                $module
            );
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            if (!$ok) {
                return ['success' => false, 'message' => 'Could not update. ' . $err];
            }
            return ['success' => true, 'id' => $id, 'message' => 'Stock entry updated.'];
        }

        $insertStatus = 'available';
        $stmt = $conn->prepare(
            'INSERT INTO lab_items (module, code, centre_id, name, category, make_name, model_name, serial_no,
             lab_name, location_note, specs, purchase_date, price, remarks, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, \'\'), NULLIF(?, 0), ?, ?, ?)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param(
            'ssisssssssssdsss',
            $module,
            $code,
            $centreId,
            $name,
            $category,
            $make,
            $model,
            $serial,
            $labName,
            $location,
            $specs,
            $purchaseDate,
            $price,
            $remarks,
            $insertStatus,
            $createdBy
        );
        $ok = $stmt->execute();
        $newId = (int) $stmt->insert_id;
        $err = $stmt->error;
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not add. ' . $err];
        }

        if (!empty($cfg['has_parts']) && !empty($data['add_default_parts']) && $newId > 0) {
            foreach (['Keyboard', 'Mouse', 'Monitor'] as $ptype) {
                saveLabPart($conn, $module, [
                    'item_id' => $newId,
                    'part_type' => $ptype,
                    'status' => 'working',
                    'created_by' => $createdBy,
                ]);
            }
        }
        return ['success' => true, 'id' => $newId, 'message' => ucfirst($cfg['item_label']) . ' added to stock.'];
    }
}

if (!function_exists('getLabItem')) {
    /** @return array<string,mixed>|null */
    function getLabItem($conn, string $module, int $id): ?array
    {
        ensureLabTables($conn);
        if ($id <= 0) {
            return null;
        }
        $stmt = $conn->prepare(
            'SELECT b.*, c.name AS centre_name FROM lab_items b
             LEFT JOIN centres c ON c.id = b.centre_id
             WHERE b.id = ? AND b.module = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $id, $module);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        if (!labCanAccessCentre($conn, $module, (int) ($row['centre_id'] ?? 0)) && (int) ($row['centre_id'] ?? 0) !== 0) {
            $ids = labGrantedCentreIds($conn, $module);
            if ($ids !== null) {
                return null;
            }
        }
        $allowed = labGrantedCentreIds($conn, $module);
        if ($allowed !== null && !in_array((int) ($row['centre_id'] ?? 0), $allowed, true)) {
            return null;
        }
        return $row;
    }
}

if (!function_exists('listLabItems')) {
    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    function listLabItems($conn, string $module, array $filters = []): array
    {
        ensureLabTables($conn);
        $sql = 'SELECT b.*, c.name AS centre_name FROM lab_items b
                LEFT JOIN centres c ON c.id = b.centre_id
                WHERE b.module = ?';
        $types = 's';
        $params = [$module];
        labAppendCentreFilter('b.centre_id', $sql, $types, $params, $conn, $module, (int) ($filters['centre_id'] ?? 0));
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        if ($status !== '' && $status !== 'all' && isset(labItemStatuses()[$status])) {
            $sql .= ' AND b.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        if (!empty($filters['available_only'])) {
            $sql .= " AND b.status = 'available'";
        }
        $cat = trim((string) ($filters['category'] ?? ''));
        if ($cat !== '') {
            $sql .= ' AND b.category = ?';
            $types .= 's';
            $params[] = $cat;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= ' AND (b.code LIKE ? OR b.name LIKE ? OR b.serial_no LIKE ? OR b.category LIKE ? OR b.lab_name LIKE ?)';
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY c.name ASC, b.code ASC';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('listLabParts')) {
    /** @return list<array<string,mixed>> */
    function listLabParts($conn, int $itemId): array
    {
        if ($itemId <= 0) {
            return [];
        }
        $stmt = $conn->prepare('SELECT * FROM lab_item_parts WHERE item_id = ? ORDER BY part_type ASC, id ASC');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('labPartsSummary')) {
    /** @param list<int> $itemIds */
    function labPartsSummary($conn, array $itemIds): array
    {
        $out = [];
        if ($itemIds === []) {
            return $out;
        }
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $stmt = $conn->prepare("SELECT item_id, part_type, status FROM lab_item_parts WHERE item_id IN ($placeholders)");
        if (!$stmt) {
            return $out;
        }
        $stmt->bind_param(str_repeat('i', count($itemIds)), ...$itemIds);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $iid = (int) $row['item_id'];
                if (!isset($out[$iid])) {
                    $out[$iid] = [];
                }
                $out[$iid][] = $row;
            }
        }
        $stmt->close();
        return $out;
    }
}

if (!function_exists('saveLabPart')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string}
     */
    function saveLabPart($conn, string $module, array $data, ?int $id = null): array
    {
        ensureLabTables($conn);
        $itemId = (int) ($data['item_id'] ?? 0);
        $item = getLabItem($conn, $module, $itemId);
        if (!$item) {
            return ['success' => false, 'message' => 'System not found.'];
        }
        $partType = trim((string) ($data['part_type'] ?? ''));
        if ($partType === '') {
            return ['success' => false, 'message' => 'Select a part type (keyboard, mouse, …).'];
        }
        $brand = trim((string) ($data['brand'] ?? ''));
        $serial = trim((string) ($data['serial_no'] ?? ''));
        $remarks = trim((string) ($data['remarks'] ?? ''));
        $status = (string) ($data['status'] ?? 'working');
        if (!isset(labPartStatuses()[$status])) {
            $status = 'working';
        }
        $createdBy = trim((string) ($data['created_by'] ?? ''));

        if ($id !== null && $id > 0) {
            $stmt = $conn->prepare(
                'UPDATE lab_item_parts SET part_type=?, brand=?, serial_no=?, remarks=?, status=? WHERE id=? AND item_id=?'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error.'];
            }
            $stmt->bind_param('sssssii', $partType, $brand, $serial, $remarks, $status, $id, $itemId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok
                ? ['success' => true, 'message' => 'Part updated.']
                : ['success' => false, 'message' => 'Could not update part.'];
        }

        $stmt = $conn->prepare(
            'INSERT INTO lab_item_parts (item_id, part_type, brand, serial_no, remarks, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('issssss', $itemId, $partType, $brand, $serial, $remarks, $status, $createdBy);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok
            ? ['success' => true, 'message' => $partType . ' added to ' . $item['code'] . '.']
            : ['success' => false, 'message' => 'Could not add part.'];
    }
}

if (!function_exists('deleteLabPart')) {
    /** @return array{success:bool,message:string} */
    function deleteLabPart($conn, string $module, int $partId): array
    {
        if ($partId <= 0) {
            return ['success' => false, 'message' => 'Invalid part.'];
        }
        $stmt = $conn->prepare(
            'SELECT p.id, p.item_id FROM lab_item_parts p
             INNER JOIN lab_items b ON b.id = p.item_id
             WHERE p.id = ? AND b.module = ? LIMIT 1'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('is', $partId, $module);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !getLabItem($conn, $module, (int) $row['item_id'])) {
            return ['success' => false, 'message' => 'Part not found.'];
        }
        $del = $conn->prepare('DELETE FROM lab_item_parts WHERE id = ?');
        if (!$del) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $del->bind_param('i', $partId);
        $ok = $del->execute();
        $del->close();
        return $ok ? ['success' => true, 'message' => 'Part removed.'] : ['success' => false, 'message' => 'Could not remove part.'];
    }
}

if (!function_exists('issueLabItem')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string}
     */
    function issueLabItem($conn, string $module, array $data): array
    {
        $cfg = labModule($module);
        if (!$cfg) {
            return ['success' => false, 'message' => 'Unknown lab module.'];
        }
        ensureLabTables($conn);
        $itemId = (int) ($data['item_id'] ?? 0);
        $type = (($data['borrower_type'] ?? '') === 'staff') ? 'staff' : 'student';
        $issueDate = trim((string) ($data['issue_date'] ?? date('Y-m-d')));
        $dueDate = trim((string) ($data['due_date'] ?? ''));
        if ($dueDate === '') {
            $dueDate = date('Y-m-d', strtotime($issueDate . ' +' . labDefaultDueDays($module, $type) . ' days'));
        }
        $remarks = trim((string) ($data['remarks'] ?? ''));
        $issuedBy = trim((string) ($data['issued_by'] ?? ''));

        $item = getLabItem($conn, $module, $itemId);
        if (!$item) {
            return ['success' => false, 'message' => ucfirst($cfg['item_label']) . ' not found.'];
        }
        if (($item['status'] ?? '') !== 'available') {
            return ['success' => false, 'message' => 'This ' . $cfg['item_label'] . ' is not available (status: ' . ($item['status'] ?? '') . ').'];
        }

        $studentId = '';
        $facultyId = 0;
        $issueId = 0;
        if ($type === 'student') {
            $resolved = resolveLibraryStudentBorrower($conn, $data);
            if (empty($resolved['success']) || empty($resolved['student'])) {
                return ['success' => false, 'message' => (string) ($resolved['message'] ?? 'Student not found.')];
            }
            $studentId = (string) $resolved['student']['student_id'];
        } else {
            $facultyId = (int) ($data['faculty_id'] ?? 0);
            if ($facultyId <= 0) {
                return ['success' => false, 'message' => 'Select a staff / faculty member.'];
            }
            $fac = $conn->prepare('SELECT id FROM faculty WHERE id = ? AND is_active = 1 LIMIT 1');
            if (!$fac) {
                return ['success' => false, 'message' => 'Database error.'];
            }
            $fac->bind_param('i', $facultyId);
            $fac->execute();
            $foundFac = (bool) $fac->get_result()->fetch_assoc();
            $fac->close();
            if (!$foundFac) {
                return ['success' => false, 'message' => 'Staff / faculty member not found.'];
            }
        }

        $conn->begin_transaction();
        try {
            if ($type === 'student') {
                $stmt = $conn->prepare(
                    'INSERT INTO lab_item_issues (item_id, borrower_type, student_id, faculty_id, issue_date, due_date, status, remarks, issued_by)
                     VALUES (?, \'student\', ?, NULL, ?, ?, \'issued\', ?, ?)'
                );
                if (!$stmt) {
                    throw new Exception($conn->error);
                }
                $stmt->bind_param('isssss', $itemId, $studentId, $issueDate, $dueDate, $remarks, $issuedBy);
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO lab_item_issues (item_id, borrower_type, student_id, faculty_id, issue_date, due_date, status, remarks, issued_by)
                     VALUES (?, \'staff\', NULL, ?, ?, ?, \'issued\', ?, ?)'
                );
                if (!$stmt) {
                    throw new Exception($conn->error);
                }
                $stmt->bind_param('iissss', $itemId, $facultyId, $issueDate, $dueDate, $remarks, $issuedBy);
            }
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            $issueId = (int) $stmt->insert_id;
            $stmt->close();

            $upd = $conn->prepare("UPDATE lab_items SET status = 'issued' WHERE id = ? AND status = 'available'");
            if (!$upd) {
                throw new Exception($conn->error);
            }
            $upd->bind_param('i', $itemId);
            $upd->execute();
            $affected = $upd->affected_rows;
            $upd->close();
            if ($affected < 1) {
                throw new Exception('Already issued by someone else. Try again.');
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Could not issue. ' . $e->getMessage()];
        }

        $msg = 'Issued ' . $cfg['code_label'] . ' ' . $item['code'] . ' successfully.';
        if (!empty($issueId)) {
            $mailNote = labNotifyBorrower($conn, $module, $issueId, 'issue');
            if ($mailNote !== '') {
                $msg .= ' ' . $mailNote;
            }
        }
        return ['success' => true, 'message' => $msg];
    }
}

if (!function_exists('returnLabItem')) {
    /** @return array{success:bool,message:string} */
    function returnLabItem($conn, string $module, int $issueId, string $returnedBy, ?string $returnDate = null): array
    {
        ensureLabTables($conn);
        if ($issueId <= 0) {
            return ['success' => false, 'message' => 'Invalid issue record.'];
        }
        $returnDate = $returnDate ?: date('Y-m-d');
        $stmt = $conn->prepare('SELECT * FROM lab_item_issues WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('i', $issueId);
        $stmt->execute();
        $issue = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$issue) {
            return ['success' => false, 'message' => 'Issue record not found.'];
        }
        if (($issue['status'] ?? '') !== 'issued') {
            return ['success' => false, 'message' => 'This item is already returned.'];
        }
        $itemId = (int) $issue['item_id'];
        $itemRow = getLabItem($conn, $module, $itemId);
        if (!$itemRow) {
            return ['success' => false, 'message' => 'You cannot return an item from another centre.'];
        }
        $conn->begin_transaction();
        try {
            $upd = $conn->prepare(
                "UPDATE lab_item_issues SET status = 'returned', return_date = ?, returned_by = ? WHERE id = ? AND status = 'issued'"
            );
            if (!$upd) {
                throw new Exception($conn->error);
            }
            $upd->bind_param('ssi', $returnDate, $returnedBy, $issueId);
            $upd->execute();
            if ($upd->affected_rows < 1) {
                $upd->close();
                throw new Exception('Could not update issue record.');
            }
            $upd->close();
            $book = $conn->prepare("UPDATE lab_items SET status = 'available' WHERE id = ? AND status = 'issued'");
            if (!$book) {
                throw new Exception($conn->error);
            }
            $book->bind_param('i', $itemId);
            $book->execute();
            $book->close();
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
        $msg = 'Returned and stock updated.';
        $mailNote = labNotifyBorrower($conn, $module, $issueId, 'return');
        if ($mailNote !== '') {
            $msg .= ' ' . $mailNote;
        }
        return ['success' => true, 'message' => $msg];
    }
}

if (!function_exists('returnLabItemCopy')) {
    /** @return array{success:bool,message:string} */
    function returnLabItemCopy($conn, string $module, int $itemId, string $returnedBy): array
    {
        $item = getLabItem($conn, $module, $itemId);
        if (!$item) {
            return ['success' => false, 'message' => 'Record not found.'];
        }
        $stmt = $conn->prepare("SELECT id FROM lab_item_issues WHERE item_id = ? AND status = 'issued' ORDER BY id DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $open = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($open) {
                return returnLabItem($conn, $module, (int) $open['id'], $returnedBy);
            }
        }
        if (($item['status'] ?? '') !== 'issued') {
            return ['success' => false, 'message' => 'This item is not marked issued.'];
        }
        $upd = $conn->prepare("UPDATE lab_items SET status = 'available' WHERE id = ?");
        if (!$upd) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $upd->bind_param('i', $itemId);
        $ok = $upd->execute();
        $upd->close();
        return $ok
            ? ['success' => true, 'message' => $item['code'] . ' marked available. Return date is today.']
            : ['success' => false, 'message' => 'Could not update stock.'];
    }
}

if (!function_exists('listLabIssues')) {
    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    function listLabIssues($conn, string $module, array $filters = []): array
    {
        ensureLabTables($conn);
        $sql = "SELECT i.*, b.code, b.name, b.category, b.centre_id, b.serial_no, c.name AS centre_name
                FROM lab_item_issues i
                LEFT JOIN lab_items b ON b.id = i.item_id
                LEFT JOIN centres c ON c.id = b.centre_id
                WHERE b.module = ?";
        $types = 's';
        $params = [$module];
        labAppendCentreFilter('b.centre_id', $sql, $types, $params, $conn, $module, (int) ($filters['centre_id'] ?? 0));
        $type = strtolower(trim((string) ($filters['borrower_type'] ?? '')));
        if ($type === 'student' || $type === 'staff') {
            $sql .= ' AND i.borrower_type = ?';
            $types .= 's';
            $params[] = $type;
        }
        $status = strtolower(trim((string) ($filters['status'] ?? 'issued')));
        if ($status === 'overdue') {
            $sql .= " AND i.status = 'issued' AND i.due_date < CURDATE()";
        } elseif ($status === 'issued' || $status === 'returned') {
            $sql .= ' AND i.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        $sql .= ' ORDER BY i.issue_date DESC, i.id DESC';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();
        $rows = libraryHydrateIssueRows($conn, $rows);
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $ql = strtolower($q);
            $rows = array_values(array_filter($rows, static function ($r) use ($ql) {
                $hay = strtolower(trim(implode(' ', [
                    (string) ($r['code'] ?? ''),
                    (string) ($r['name'] ?? ''),
                    (string) ($r['category'] ?? ''),
                    (string) ($r['student_id'] ?? ''),
                    (string) ($r['student_name'] ?? ''),
                    (string) ($r['faculty_name'] ?? ''),
                    (string) ($r['centre_name'] ?? ''),
                ])));
                return $hay !== '' && strpos($hay, $ql) !== false;
            }));
        }
        return $rows;
    }
}

if (!function_exists('listOrphanIssuedLabItems')) {
    /** @return list<array<string,mixed>> */
    function listOrphanIssuedLabItems($conn, string $module): array
    {
        ensureLabTables($conn);
        $sql = "SELECT b.*, c.name AS centre_name
                FROM lab_items b
                LEFT JOIN lab_item_issues i ON i.item_id = b.id AND i.status = 'issued'
                LEFT JOIN centres c ON c.id = b.centre_id
                WHERE b.module = ? AND b.status = 'issued' AND i.id IS NULL";
        $types = 's';
        $params = [$module];
        labAppendCentreFilter('b.centre_id', $sql, $types, $params, $conn, $module);
        $sql .= ' ORDER BY b.code ASC';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('labStatusBadgeClass')) {
    function labStatusBadgeClass(string $status): string
    {
        if (in_array($status, ['available', 'returned', 'working'], true)) {
            return 'success';
        }
        if (in_array($status, ['issued', 'spare', 'replaced'], true)) {
            return 'warning';
        }
        if (in_array($status, ['overdue', 'damaged', 'lost', 'missing'], true)) {
            return 'danger';
        }
        if ($status === 'under_repair') {
            return 'info';
        }
        return 'secondary';
    }
}

if (!function_exists('labLoadIssue')) {
    /** @return array<string,mixed>|null */
    function labLoadIssue($conn, string $module, int $issueId): ?array
    {
        if ($issueId <= 0) {
            return null;
        }
        $stmt = $conn->prepare(
            'SELECT i.*, b.code, b.name, b.category, b.centre_id, c.name AS centre_name
             FROM lab_item_issues i
             LEFT JOIN lab_items b ON b.id = i.item_id
             LEFT JOIN centres c ON c.id = b.centre_id
             WHERE i.id = ? AND b.module = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $issueId, $module);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $allowed = labGrantedCentreIds($conn, $module);
        if ($allowed !== null && !in_array((int) ($row['centre_id'] ?? 0), $allowed, true)) {
            return null;
        }
        return $row;
    }
}

if (!function_exists('labNotifyBorrower')) {
    function labNotifyBorrower($conn, string $module, int $issueId, string $kind): string
    {
        $cfg = labModule($module);
        if (!$cfg) {
            return '';
        }
        $issue = labLoadIssue($conn, $module, $issueId);
        if (!$issue) {
            return '';
        }
        $contact = libraryBorrowerContact($conn, $issue);
        if ($contact['email'] === '') {
            return 'No email is on file for the borrower.';
        }
        $helper = __DIR__ . '/email_helper.php';
        if (!is_file($helper)) {
            return 'Notification email could not be sent.';
        }
        require_once $helper;
        if (!function_exists('sendPhpMailerWithSmtpFallback')) {
            return 'Notification email could not be sent.';
        }
        $noun = $cfg['item_label'];
        $codeLabel = $cfg['code_label'];
        $title = $cfg['short'];
        $subjectMap = [
            'return' => ucfirst($noun) . ' returned - NIELIT ' . $title,
            'due_soon' => 'Reminder: ' . $noun . ' due soon - NIELIT',
            'overdue' => 'Reminder: ' . $noun . ' is overdue - NIELIT',
            'issue' => ucfirst($noun) . ' issued - NIELIT ' . $title,
        ];
        $introMap = [
            'return' => 'Your ' . $noun . ' has been returned. Thank you.',
            'due_soon' => 'Please return the issued ' . $noun . ' by the due date.',
            'overdue' => 'The issued ' . $noun . ' is overdue. Please return it as soon as possible.',
            'issue' => 'A ' . $noun . ' has been issued to you. Please return it on or before the due date.',
        ];
        $kind = isset($subjectMap[$kind]) ? $kind : 'issue';
        $payload = [
            'name' => $contact['name'],
            'email' => $contact['email'],
            'accession_no' => (string) ($issue['code'] ?? ''),
            'title' => (string) ($issue['name'] ?? ''),
            'issue_date' => libraryFormatDate($issue['issue_date'] ?? ''),
            'due_date' => libraryFormatDate($issue['due_date'] ?? ''),
            'return_date' => libraryFormatDate($issue['return_date'] ?? ''),
            'centre_name' => libraryCentreLabel($issue),
        ];
        $data = $payload;
        $data['name'] = $contact['name'];
        $html = libraryNoticeHtml($kind, $payload);
        $html = str_replace('NIELIT Bhubaneswar Library', 'NIELIT ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), $html);
        $html = str_replace('Accession:', htmlspecialchars($codeLabel, ENT_QUOTES, 'UTF-8') . ':', $html);
        $html = str_replace('library book', $noun, $html);
        $html = str_replace('Book ', ucfirst($noun) . ' ', $html);
        $text = libraryNoticeText($kind, $payload);
        $subject = $subjectMap[$kind];
        $email = $contact['email'];
        $name = $contact['name'];
        $result = sendPhpMailerWithSmtpFallback(static function ($mail) use ($email, $name, $subject, $html, $text) {
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($email, $name);
            $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;
        }, ['timeout' => 12]);
        $ok = !empty($result['ok']);
        if ($ok && in_array($kind, ['due_soon', 'overdue'], true)) {
            $today = date('Y-m-d');
            $upd = $conn->prepare('UPDATE lab_item_issues SET last_reminder_on = ?, last_reminder_kind = ? WHERE id = ?');
            if ($upd) {
                $upd->bind_param('ssi', $today, $kind, $issueId);
                $upd->execute();
                $upd->close();
            }
        }
        unset($introMap, $data);
        return $ok ? ('Email sent to ' . $contact['email'] . '.') : 'Notification email could not be sent.';
    }
}

if (!function_exists('labSendIssueReminder')) {
    /** @return array{success:bool,message:string} */
    function labSendIssueReminder($conn, string $module, int $issueId): array
    {
        $issue = labLoadIssue($conn, $module, $issueId);
        if (!$issue || ($issue['status'] ?? '') !== 'issued') {
            return ['success' => false, 'message' => 'Issued record not found.'];
        }
        $kind = libraryReminderKindForIssue($issue);
        $note = labNotifyBorrower($conn, $module, $issueId, $kind);
        $ok = strpos($note, 'Email sent to') === 0;
        return ['success' => $ok, 'message' => $note !== '' ? $note : 'Could not send reminder.'];
    }
}

if (!function_exists('sendLabDueReminders')) {
    /** @return array{sent:int,skipped:int,failed:int} */
    function sendLabDueReminders($conn, string $module, int $limit = 40): array
    {
        ensureLabTables($conn);
        $limit = max(1, min(80, $limit));
        $sql = "SELECT i.id
                FROM lab_item_issues i
                INNER JOIN lab_items b ON b.id = i.item_id
                WHERE b.module = ?
                  AND i.status = 'issued'
                  AND i.due_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
                  AND (i.last_reminder_on IS NULL OR i.last_reminder_on < CURDATE())";
        $types = 's';
        $params = [$module];
        labAppendCentreFilter('b.centre_id', $sql, $types, $params, $conn, $module);
        $sql .= " ORDER BY i.due_date ASC, i.id ASC LIMIT {$limit}";
        $ids = [];
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $ids[] = (int) $row['id'];
                }
            }
            $stmt->close();
        }
        $sent = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $note = labNotifyBorrower($conn, $module, $id, libraryReminderKindForIssue(labLoadIssue($conn, $module, $id) ?: []));
            if (strpos($note, 'Email sent to') === 0) {
                $sent++;
            } elseif ($note === 'No email is on file for the borrower.' || $note === '') {
                $skipped++;
            } else {
                $failed++;
            }
        }
        return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
    }
}

if (!function_exists('labReminderCronKey')) {
    function labReminderCronKey(): string
    {
        if (!defined('SMTP_USERNAME')) {
            $emailCfg = __DIR__ . '/../config/email.php';
            if (is_file($emailCfg)) {
                require_once $emailCfg;
            }
        }
        return hash('sha256', (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'nielit') . '|lab-reminders|' . (defined('APP_URL') ? APP_URL : ''));
    }
}
