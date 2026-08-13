<?php
/**
 * Library Management — stock (accession) register and issue/return registers.
 */

if (!function_exists('ensureLibraryTables')) {
    function ensureLibraryTables($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $books = "CREATE TABLE IF NOT EXISTS library_books (
            id INT PRIMARY KEY AUTO_INCREMENT,
            accession_no VARCHAR(50) NOT NULL,
            centre_id INT NULL,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) NULL,
            publisher VARCHAR(255) NULL,
            isbn VARCHAR(40) NULL,
            category VARCHAR(100) NULL,
            edition VARCHAR(50) NULL,
            pub_year VARCHAR(10) NULL,
            purchase_date DATE NULL,
            bill_no VARCHAR(80) NULL,
            price DECIMAL(10,2) NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'Purchased',
            shelf_location VARCHAR(100) NULL,
            remarks TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'available',
            created_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_lib_centre_accession (centre_id, accession_no),
            KEY idx_lib_status (status),
            KEY idx_lib_title (title),
            KEY idx_lib_centre (centre_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $issues = "CREATE TABLE IF NOT EXISTS library_issues (
            id INT PRIMARY KEY AUTO_INCREMENT,
            book_id INT NOT NULL,
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_li_book (book_id),
            KEY idx_li_type (borrower_type),
            KEY idx_li_status (status),
            KEY idx_li_student (student_id),
            KEY idx_li_faculty (faculty_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $access = "CREATE TABLE IF NOT EXISTS library_module_access (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_id INT NOT NULL,
            centre_id INT NOT NULL DEFAULT 0,
            granted_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_lib_admin_centre (admin_id, centre_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        foreach (['books' => $books, 'issues' => $issues, 'access' => $access] as $label => $sql) {
            if (!$conn->query($sql)) {
                error_log('ensureLibraryTables ' . $label . ' failed: ' . $conn->error);
                return false;
            }
        }

        $col = $conn->query("SHOW COLUMNS FROM library_issues LIKE 'last_reminder_on'");
        if ($col && $col->num_rows === 0) {
            if (!$conn->query("ALTER TABLE library_issues ADD COLUMN last_reminder_on DATE NULL, ADD COLUMN last_reminder_kind VARCHAR(20) NULL")) {
                error_log('ensureLibraryTables reminder columns failed: ' . $conn->error);
            }
        }

        $bookCentre = $conn->query("SHOW COLUMNS FROM library_books LIKE 'centre_id'");
        if ($bookCentre && $bookCentre->num_rows === 0) {
            @$conn->query("ALTER TABLE library_books ADD COLUMN centre_id INT NULL AFTER accession_no");
            @$conn->query("ALTER TABLE library_books ADD KEY idx_lib_centre (centre_id)");
        }
        $newBookUq = $conn->query("SHOW INDEX FROM library_books WHERE Key_name = 'uq_lib_centre_accession'");
        if ($newBookUq && $newBookUq->num_rows === 0) {
            $oldBookUq = $conn->query("SHOW INDEX FROM library_books WHERE Key_name = 'uq_lib_accession'");
            if ($oldBookUq && $oldBookUq->num_rows > 0) {
                @$conn->query("ALTER TABLE library_books DROP INDEX uq_lib_accession");
            }
            @$conn->query("ALTER TABLE library_books ADD UNIQUE KEY uq_lib_centre_accession (centre_id, accession_no)");
        }

        $accCentre = $conn->query("SHOW COLUMNS FROM library_module_access LIKE 'centre_id'");
        if ($accCentre && $accCentre->num_rows === 0) {
            @$conn->query("ALTER TABLE library_module_access ADD COLUMN centre_id INT NOT NULL DEFAULT 0 AFTER admin_id");
            $oldAccUq = $conn->query("SHOW INDEX FROM library_module_access WHERE Key_name = 'uq_lib_admin'");
            if ($oldAccUq && $oldAccUq->num_rows > 0) {
                @$conn->query("ALTER TABLE library_module_access DROP INDEX uq_lib_admin");
            }
            @$conn->query("ALTER TABLE library_module_access ADD UNIQUE KEY uq_lib_admin_centre (admin_id, centre_id)");
        }

        libraryExpandLegacyAllCentreGrants($conn);

        $ready = true;
        return true;
    }
}

if (!function_exists('admin_can_access_library')) {
    function admin_can_access_library($conn = null, ?string $role = null, ?int $adminId = null): bool
    {
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
        ensureLibraryTables($conn);
        $stmt = $conn->prepare('SELECT id FROM library_module_access WHERE admin_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('library_require_access')) {
    function library_require_access($conn): void
    {
        if (admin_can_access_library($conn)) {
            return;
        }
        $_SESSION['message'] = 'Access denied. Library module is not assigned to your account.';
        $_SESSION['message_type'] = 'danger';
        $role = (string) ($_SESSION['admin_role'] ?? '');
        $url = function_exists('get_admin_post_login_url') ? get_admin_post_login_url($role) : 'dashboard.php';
        header('Location: ' . $url);
        exit();
    }
}

if (!function_exists('libraryBookStatuses')) {
    /** @return array<string,string> */
    function libraryBookStatuses(): array
    {
        return [
            'available' => 'Available',
            'issued' => 'Issued',
            'lost' => 'Lost',
            'damaged' => 'Damaged',
        ];
    }
}

if (!function_exists('libraryBookSources')) {
    /** @return array<string,string> */
    function libraryBookSources(): array
    {
        return [
            'Purchased' => 'Purchased',
            'Gift' => 'Gift',
            'Other' => 'Other',
        ];
    }
}

if (!function_exists('libraryDefaultDueDays')) {
    function libraryDefaultDueDays(string $borrowerType): int
    {
        return $borrowerType === 'staff' ? 30 : 14;
    }
}

if (!function_exists('libraryExpandLegacyAllCentreGrants')) {
    function libraryExpandLegacyAllCentreGrants($conn): void
    {
        $centres = [];
        $res = $conn->query("SELECT id FROM centres WHERE is_active = 1");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $centres[] = (int) $row['id'];
            }
        }
        if ($centres === []) {
            return;
        }
        $legacy = $conn->query("SELECT id, admin_id, granted_by FROM library_module_access WHERE centre_id = 0");
        if (!$legacy) {
            return;
        }
        $ins = $conn->prepare('INSERT IGNORE INTO library_module_access (admin_id, centre_id, granted_by) VALUES (?, ?, ?)');
        $del = $conn->prepare('DELETE FROM library_module_access WHERE id = ?');
        if (!$ins || !$del) {
            return;
        }
        while ($row = $legacy->fetch_assoc()) {
            $adminId = (int) $row['admin_id'];
            $by = (string) ($row['granted_by'] ?? '');
            foreach ($centres as $cid) {
                $ins->bind_param('iis', $adminId, $cid, $by);
                $ins->execute();
            }
            $lid = (int) $row['id'];
            $del->bind_param('i', $lid);
            $del->execute();
        }
        $ins->close();
        $del->close();
    }
}

if (!function_exists('libraryGrantedCentreIds')) {
    /**
     * null = all centres (Master Admin, cron, or legacy all-centre grant).
     * @return list<int>|null
     */
    function libraryGrantedCentreIds($conn): ?array
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
        ensureLibraryTables($conn);
        $stmt = $conn->prepare('SELECT centre_id FROM library_module_access WHERE admin_id = ?');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $adminId);
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

if (!function_exists('libraryCanAccessCentre')) {
    function libraryCanAccessCentre($conn, int $centreId): bool
    {
        $ids = libraryGrantedCentreIds($conn);
        if ($ids === null) {
            return true;
        }
        return $centreId > 0 && in_array($centreId, $ids, true);
    }
}

if (!function_exists('libraryAppendCentreFilter')) {
    /**
     * @param list<mixed> $params
     */
    function libraryAppendCentreFilter(string $column, string &$sql, string &$types, array &$params, $conn, int $filterCentre = 0): void
    {
        $ids = libraryGrantedCentreIds($conn);
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

if (!function_exists('listLibraryCentres')) {
    /** @return list<array<string,mixed>> */
    function listLibraryCentres($conn, bool $onlyGranted = true): array
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
        $ids = libraryGrantedCentreIds($conn);
        if ($ids === null) {
            return $rows;
        }
        $allowed = array_flip($ids);
        return array_values(array_filter($rows, static function ($row) use ($allowed) {
            return isset($allowed[(int) $row['id']]);
        }));
    }
}

if (!function_exists('libraryCentreLabel')) {
    function libraryCentreLabel(array $row): string
    {
        $name = trim((string) ($row['centre_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $cid = (int) ($row['centre_id'] ?? 0);
        return $cid > 0 ? ('Centre #' . $cid) : 'No centre';
    }
}

if (!function_exists('libraryGrantAccess')) {
    /** @return array{success:bool,message:string} */
    function libraryGrantAccess($conn, int $adminId, string $grantedBy, int $centreId = 0): array
    {
        ensureLibraryTables($conn);
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
            return ['success' => false, 'message' => 'Library access can only be granted to Course Coordinators or Faculty.'];
        }
        $stmt = $conn->prepare(
            'INSERT INTO library_module_access (admin_id, centre_id, granted_by) VALUES (?, ?, ?)
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
        return ['success' => true, 'message' => 'Library access granted to ' . $row['username'] . ' for ' . $centre['name'] . '.'];
    }
}

if (!function_exists('libraryRevokeAccess')) {
    /** @return array{success:bool,message:string} */
    function libraryRevokeAccess($conn, int $adminId, int $centreId = 0): array
    {
        ensureLibraryTables($conn);
        if ($adminId <= 0) {
            return ['success' => false, 'message' => 'Invalid account.'];
        }
        if ($centreId > 0) {
            $stmt = $conn->prepare('DELETE FROM library_module_access WHERE admin_id = ? AND centre_id = ?');
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error.'];
            }
            $stmt->bind_param('ii', $adminId, $centreId);
        } else {
            $stmt = $conn->prepare('DELETE FROM library_module_access WHERE admin_id = ?');
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
        return ['success' => true, 'message' => $centreId > 0 ? 'Library access revoked for that centre.' : 'Library access revoked.'];
    }
}

if (!function_exists('listLibraryAccessCandidates')) {
    /** @return list<array<string,mixed>> */
    function listLibraryAccessCandidates($conn): array
    {
        ensureLibraryTables($conn);
        $admins = [];
        $res = $conn->query("SELECT id, username, role, email FROM admin WHERE role IN ('course_coordinator','faculty') ORDER BY role ASC, username ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $row['grants'] = [];
                $admins[(int) $row['id']] = $row;
            }
        }
        $gRes = $conn->query(
            "SELECT lma.id AS grant_id, lma.admin_id, lma.centre_id, lma.granted_by, lma.created_at AS granted_at,
                    c.name AS centre_name
             FROM library_module_access lma
             LEFT JOIN centres c ON c.id = lma.centre_id
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

if (!function_exists('libraryStats')) {
    /** @return array{total:int,available:int,issued:int,overdue:int} */
    function libraryStats($conn): array
    {
        ensureLibraryTables($conn);
        $stats = ['total' => 0, 'available' => 0, 'issued' => 0, 'overdue' => 0];
        $sql = 'SELECT status, COUNT(*) AS c FROM library_books b WHERE 1=1';
        $types = '';
        $params = [];
        libraryAppendCentreFilter('b.centre_id', $sql, $types, $params, $conn);
        $sql .= ' GROUP BY status';
        if ($types !== '') {
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
                    }
                }
                $stmt->close();
            }
        } else {
            $res = $conn->query($sql);
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
                }
            }
        }

        $overSql = "SELECT COUNT(*) AS c FROM library_issues i
                    INNER JOIN library_books b ON b.id = i.book_id
                    WHERE i.status = 'issued' AND i.due_date < CURDATE()";
        $overTypes = '';
        $overParams = [];
        libraryAppendCentreFilter('b.centre_id', $overSql, $overTypes, $overParams, $conn);
        if ($overTypes !== '') {
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
        } else {
            $over = $conn->query($overSql);
            if ($over) {
                $stats['overdue'] = (int) ($over->fetch_assoc()['c'] ?? 0);
            }
        }
        return $stats;
    }
}

if (!function_exists('saveLibraryBook')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string,id?:int}
     */
    function saveLibraryBook($conn, array $data, ?int $id = null): array
    {
        ensureLibraryTables($conn);
        $accession = strtoupper(trim((string) ($data['accession_no'] ?? '')));
        $title = trim((string) ($data['title'] ?? ''));
        if ($accession === '' || $title === '') {
            return ['success' => false, 'message' => 'Accession number and title are required.'];
        }

        $author = trim((string) ($data['author'] ?? ''));
        $publisher = trim((string) ($data['publisher'] ?? ''));
        $isbn = trim((string) ($data['isbn'] ?? ''));
        $category = trim((string) ($data['category'] ?? ''));
        $edition = trim((string) ($data['edition'] ?? ''));
        $pubYear = trim((string) ($data['pub_year'] ?? ''));
        $purchaseDate = trim((string) ($data['purchase_date'] ?? ''));
        $billNo = trim((string) ($data['bill_no'] ?? ''));
        $priceRaw = trim((string) ($data['price'] ?? ''));
        $price = $priceRaw === '' ? 0.0 : (float) $priceRaw;
        $source = (string) ($data['source'] ?? 'Purchased');
        if (!isset(libraryBookSources()[$source])) {
            $source = 'Purchased';
        }
        $shelf = trim((string) ($data['shelf_location'] ?? ''));
        $remarks = trim((string) ($data['remarks'] ?? ''));
        $status = (string) ($data['status'] ?? 'available');
        if (!isset(libraryBookStatuses()[$status]) || $status === 'issued') {
            $status = 'available';
        }
        $createdBy = trim((string) ($data['created_by'] ?? ''));
        $centreId = (int) ($data['centre_id'] ?? 0);
        if ($centreId <= 0) {
            return ['success' => false, 'message' => 'Select a training centre.'];
        }
        if (!libraryCanAccessCentre($conn, $centreId)) {
            return ['success' => false, 'message' => 'You do not have library access for that centre.'];
        }

        $dup = $conn->prepare('SELECT id FROM library_books WHERE accession_no = ? AND centre_id <=> ? AND id <> ? LIMIT 1');
        $checkId = $id ?? 0;
        if ($dup) {
            $dup->bind_param('sii', $accession, $centreId, $checkId);
            $dup->execute();
            $exists = $dup->get_result()->fetch_assoc();
            $dup->close();
            if ($exists) {
                return ['success' => false, 'message' => 'Accession number already exists at this centre.'];
            }
        }

        if ($id !== null && $id > 0) {
            $current = getLibraryBook($conn, $id);
            if (!$current) {
                return ['success' => false, 'message' => 'Book not found.'];
            }
            if (($current['status'] ?? '') === 'issued') {
                $status = 'issued';
            }
            if (!libraryCanAccessCentre($conn, (int) ($current['centre_id'] ?? 0))) {
                return ['success' => false, 'message' => 'You cannot edit stock for this centre.'];
            }
            $stmt = $conn->prepare(
                'UPDATE library_books SET accession_no=?, title=?, author=?, publisher=?, isbn=?, category=?, edition=?, pub_year=?,
                 purchase_date=NULLIF(?, \'\'), bill_no=?, price=NULLIF(?, 0), source=?, shelf_location=?, remarks=?, status=?, centre_id=?
                 WHERE id=?'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
            $stmt->bind_param(
                'ssssssssssdssssii',
                $accession,
                $title,
                $author,
                $publisher,
                $isbn,
                $category,
                $edition,
                $pubYear,
                $purchaseDate,
                $billNo,
                $price,
                $source,
                $shelf,
                $remarks,
                $status,
                $centreId,
                $id
            );
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            if (!$ok) {
                return ['success' => false, 'message' => 'Could not update book. ' . $err];
            }
            return ['success' => true, 'id' => $id, 'message' => 'Stock entry updated.'];
        }

        $stmt = $conn->prepare(
            'INSERT INTO library_books (accession_no, title, author, publisher, isbn, category, edition, pub_year,
             purchase_date, bill_no, price, source, shelf_location, remarks, status, created_by, centre_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, \'\'), ?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $insertStatus = 'available';
        $stmt->bind_param(
            'ssssssssssdsssssi',
            $accession,
            $title,
            $author,
            $publisher,
            $isbn,
            $category,
            $edition,
            $pubYear,
            $purchaseDate,
            $billNo,
            $price,
            $source,
            $shelf,
            $remarks,
            $insertStatus,
            $createdBy,
            $centreId
        );
        $ok = $stmt->execute();
        $newId = (int) $stmt->insert_id;
        $err = $stmt->error;
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not add book. ' . $err];
        }
        return ['success' => true, 'id' => $newId, 'message' => 'Book added to stock register.'];
    }
}

if (!function_exists('getLibraryBook')) {
    /** @return array<string,mixed>|null */
    function getLibraryBook($conn, int $id): ?array
    {
        ensureLibraryTables($conn);
        if ($id <= 0) {
            return null;
        }
        $stmt = $conn->prepare('SELECT * FROM library_books WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $allowed = libraryGrantedCentreIds($conn);
        if ($allowed !== null && !in_array((int) ($row['centre_id'] ?? 0), $allowed, true)) {
            return null;
        }
        return $row;
    }
}

if (!function_exists('getLibraryBookByAccession')) {
    /** @return array<string,mixed>|null */
    function getLibraryBookByAccession($conn, string $accessionNo): ?array
    {
        ensureLibraryTables($conn);
        $accessionNo = strtoupper(trim($accessionNo));
        if ($accessionNo === '') {
            return null;
        }
        $stmt = $conn->prepare('SELECT * FROM library_books WHERE accession_no = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $accessionNo);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $allowed = libraryGrantedCentreIds($conn);
        if ($allowed !== null && !in_array((int) ($row['centre_id'] ?? 0), $allowed, true)) {
            return null;
        }
        return $row;
    }
}

if (!function_exists('listLibraryBooks')) {
    /**
     * @param array{status?:string,q?:string,available_only?:bool} $filters
     * @return list<array<string,mixed>>
     */
    function listLibraryBooks($conn, array $filters = []): array
    {
        ensureLibraryTables($conn);
        $sql = 'SELECT b.*, c.name AS centre_name, c.code AS centre_code
                FROM library_books b
                LEFT JOIN centres c ON c.id = b.centre_id
                WHERE 1=1';
        $types = '';
        $params = [];
        $filterCentre = (int) ($filters['centre_id'] ?? 0);
        libraryAppendCentreFilter('b.centre_id', $sql, $types, $params, $conn, $filterCentre);
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        if ($status !== '' && $status !== 'all' && isset(libraryBookStatuses()[$status])) {
            $sql .= ' AND b.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        if (!empty($filters['available_only'])) {
            $sql .= " AND b.status = 'available'";
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= ' AND (b.accession_no LIKE ? OR b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.category LIKE ?)';
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY c.name ASC, b.accession_no ASC';
        $rows = [];
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        } else {
            $res = $conn->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }
}

if (!function_exists('libraryStudentLookupTypes')) {
    /** @return array<string,string> */
    function libraryStudentLookupTypes(): array
    {
        return [
            'student_id' => 'Student ID',
            'mobile' => 'Mobile number',
            'aadhar' => 'Aadhaar number',
        ];
    }
}

if (!function_exists('libraryNormalizeDigits')) {
    function libraryNormalizeDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}

if (!function_exists('libraryDedupeStudentRows')) {
    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    function libraryDedupeStudentRows(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $sid = trim((string) ($row['student_id'] ?? ''));
            if ($sid === '' || isset($seen[$sid])) {
                continue;
            }
            $seen[$sid] = true;
            $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('lookupLibraryStudents')) {
    /**
     * Find students by Student ID, mobile, or Aadhaar.
     *
     * @return list<array{student_id:string,name:string,email?:string,mobile?:string}>
     */
    function lookupLibraryStudents($conn, string $query, string $by = 'student_id'): array
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }
        $query = trim($query);
        $by = isset(libraryStudentLookupTypes()[$by]) ? $by : 'student_id';
        if ($query === '') {
            return [];
        }

        $rows = [];
        static $hasAccounts = null;
        if ($hasAccounts === null) {
            $accCheck = $conn->query("SHOW TABLES LIKE 'student_accounts'");
            $hasAccounts = ($accCheck && $accCheck->num_rows > 0);
        }

        if ($by === 'student_id') {
            $stmt = $conn->prepare('SELECT student_id, name, email FROM students WHERE student_id = ? ORDER BY id DESC LIMIT 8');
            if ($stmt) {
                $stmt->bind_param('s', $query);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $rows[] = $row;
                    }
                }
                $stmt->close();
            }
            if ($rows === [] && $hasAccounts) {
                $stmt = $conn->prepare('SELECT student_id, name, email FROM student_accounts WHERE student_id = ? LIMIT 5');
                if ($stmt) {
                    $stmt->bind_param('s', $query);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res) {
                        while ($row = $res->fetch_assoc()) {
                            $rows[] = $row;
                        }
                    }
                    $stmt->close();
                }
            }
            return libraryDedupeStudentRows($rows);
        }

        if ($by === 'mobile') {
            $mobile = libraryNormalizeDigits($query);
            if (strlen($mobile) < 10) {
                return [];
            }
            if (strlen($mobile) > 10) {
                $mobile = substr($mobile, -10);
            }
            $sql = "SELECT student_id, name, email FROM students
                    WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(mobile,''), ' ', ''), '-', ''), '+', ''), CHAR(9), ''), 10) = ?
                    ORDER BY id DESC LIMIT 15";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('s', $mobile);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $rows[] = $row;
                    }
                }
                $stmt->close();
            }
            if ($hasAccounts) {
                $stmt = $conn->prepare(
                    "SELECT student_id, name, email FROM student_accounts
                     WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(mobile,''), ' ', ''), '-', ''), '+', ''), CHAR(9), ''), 10) = ?
                     ORDER BY id DESC LIMIT 10"
                );
                if ($stmt) {
                    $stmt->bind_param('s', $mobile);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res) {
                        while ($row = $res->fetch_assoc()) {
                            $rows[] = $row;
                        }
                    }
                    $stmt->close();
                }
            }
            return libraryDedupeStudentRows($rows);
        }

        $aadhar = libraryNormalizeDigits($query);
        if (strlen($aadhar) !== 12) {
            return [];
        }
        $stmt = $conn->prepare(
            "SELECT student_id, name, email FROM students
             WHERE REPLACE(REPLACE(REPLACE(IFNULL(aadhar,''), ' ', ''), '-', ''), CHAR(9), '') = ?
             ORDER BY id DESC LIMIT 15"
        );
        if ($stmt) {
            $stmt->bind_param('s', $aadhar);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
            $stmt->close();
        }
        if ($hasAccounts) {
            $stmt = $conn->prepare(
                'SELECT student_id, name, email FROM student_accounts
                 WHERE REPLACE(REPLACE(REPLACE(IFNULL(aadhar,\'\'), \' \', \'\'), \'-\', \'\'), CHAR(9), \'\') = ?
                 ORDER BY id DESC LIMIT 10'
            );
            if ($stmt) {
                $stmt->bind_param('s', $aadhar);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $rows[] = $row;
                    }
                }
                $stmt->close();
            }
        }
        return libraryDedupeStudentRows($rows);
    }
}

if (!function_exists('lookupLibraryStudent')) {
    /** @return array<string,mixed>|null */
    function lookupLibraryStudent($conn, string $studentId): ?array
    {
        $rows = lookupLibraryStudents($conn, $studentId, 'student_id');
        return $rows[0] ?? null;
    }
}

if (!function_exists('resolveLibraryStudentBorrower')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string,student?:array<string,mixed>}
     */
    function resolveLibraryStudentBorrower($conn, array $data): array
    {
        $by = (string) ($data['student_lookup'] ?? 'student_id');
        if (!isset(libraryStudentLookupTypes()[$by])) {
            $by = 'student_id';
        }
        $query = trim((string) ($data['student_query'] ?? ''));
        $picked = trim((string) ($data['student_id'] ?? ''));
        if ($query === '') {
            $query = $picked;
        }

        $matches = lookupLibraryStudents($conn, $query, $by);
        if ($matches === [] && $picked !== '' && ($picked !== $query || $by !== 'student_id')) {
            $matches = lookupLibraryStudents($conn, $picked, 'student_id');
        }
        if ($matches === []) {
            $labels = libraryStudentLookupTypes();
            return ['success' => false, 'message' => 'Student not found. Check the ' . ($labels[$by] ?? 'Student ID') . '.'];
        }
        if (count($matches) === 1) {
            return ['success' => true, 'message' => '', 'student' => $matches[0]];
        }
        foreach ($matches as $row) {
            if (strcasecmp((string) ($row['student_id'] ?? ''), $picked) === 0) {
                return ['success' => true, 'message' => '', 'student' => $row];
            }
        }
        return ['success' => false, 'message' => 'Multiple students match. Select the correct student from the list.'];
    }
}

if (!function_exists('listLibraryStaff')) {
    /** @return list<array<string,mixed>> */
    function listLibraryStaff($conn): array
    {
        $rows = [];
        $res = $conn->query("SELECT id, name, designation, department, staff_category, email
                             FROM faculty
                             WHERE is_active = 1
                             ORDER BY name ASC");
        if (!$res) {
            $res = $conn->query("SELECT id, name, designation, department, email
                                 FROM faculty
                                 WHERE is_active = 1
                                 ORDER BY name ASC");
        }
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('issueLibraryBook')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string}
     */
    function issueLibraryBook($conn, array $data): array
    {
        ensureLibraryTables($conn);
        $bookId = (int) ($data['book_id'] ?? 0);
        $type = (($data['borrower_type'] ?? '') === 'staff') ? 'staff' : 'student';
        $issueDate = trim((string) ($data['issue_date'] ?? date('Y-m-d')));
        $dueDate = trim((string) ($data['due_date'] ?? ''));
        if ($dueDate === '') {
            $dueDate = date('Y-m-d', strtotime($issueDate . ' +' . libraryDefaultDueDays($type) . ' days'));
        }
        $remarks = trim((string) ($data['remarks'] ?? ''));
        $issuedBy = trim((string) ($data['issued_by'] ?? ''));

        $book = getLibraryBook($conn, $bookId);
        if (!$book) {
            return ['success' => false, 'message' => 'Book not found.'];
        }
        if (($book['status'] ?? '') !== 'available') {
            return ['success' => false, 'message' => 'This copy is not available (status: ' . ($book['status'] ?? '') . ').'];
        }

        $studentId = '';
        $facultyId = 0;
        $issueId = 0;
        if ($type === 'student') {
            $resolved = resolveLibraryStudentBorrower($conn, $data);
            if (empty($resolved['success']) || empty($resolved['student'])) {
                return ['success' => false, 'message' => (string) ($resolved['message'] ?? 'Student not found.')];
            }
            $student = $resolved['student'];
            $studentId = (string) $student['student_id'];
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
                    'INSERT INTO library_issues (book_id, borrower_type, student_id, faculty_id, issue_date, due_date, status, remarks, issued_by)
                     VALUES (?, \'student\', ?, NULL, ?, ?, \'issued\', ?, ?)'
                );
                if (!$stmt) {
                    throw new Exception($conn->error);
                }
                $stmt->bind_param('isssss', $bookId, $studentId, $issueDate, $dueDate, $remarks, $issuedBy);
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO library_issues (book_id, borrower_type, student_id, faculty_id, issue_date, due_date, status, remarks, issued_by)
                     VALUES (?, \'staff\', NULL, ?, ?, ?, \'issued\', ?, ?)'
                );
                if (!$stmt) {
                    throw new Exception($conn->error);
                }
                $stmt->bind_param('iissss', $bookId, $facultyId, $issueDate, $dueDate, $remarks, $issuedBy);
            }
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            $issueId = (int) $stmt->insert_id;
            $stmt->close();

            $upd = $conn->prepare("UPDATE library_books SET status = 'issued' WHERE id = ? AND status = 'available'");
            if (!$upd) {
                throw new Exception($conn->error);
            }
            $upd->bind_param('i', $bookId);
            $upd->execute();
            $affected = $upd->affected_rows;
            $upd->close();
            if ($affected < 1) {
                throw new Exception('Book was issued by someone else. Try again.');
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Could not issue book. ' . $e->getMessage()];
        }

        $msg = 'Issued accession ' . $book['accession_no'] . ' successfully.';
        if (!empty($issueId)) {
            $mailNote = libraryNotifyBorrower($conn, $issueId, 'issue');
            if ($mailNote !== '') {
                $msg .= ' ' . $mailNote;
            }
        }
        return [
            'success' => true,
            'message' => $msg,
        ];
    }
}

if (!function_exists('returnLibraryBook')) {
    /** @return array{success:bool,message:string} */
    function returnLibraryBook($conn, int $issueId, string $returnedBy, ?string $returnDate = null): array
    {
        ensureLibraryTables($conn);
        if ($issueId <= 0) {
            return ['success' => false, 'message' => 'Invalid issue record.'];
        }
        $returnDate = $returnDate ?: date('Y-m-d');

        $stmt = $conn->prepare('SELECT * FROM library_issues WHERE id = ? LIMIT 1');
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
            return ['success' => false, 'message' => 'This book is already returned.'];
        }

        $bookId = (int) $issue['book_id'];
        $bookRow = getLibraryBook($conn, $bookId);
        if (!$bookRow) {
            return ['success' => false, 'message' => 'You cannot return a book from another centre.'];
        }
        $conn->begin_transaction();
        try {
            $upd = $conn->prepare(
                "UPDATE library_issues SET status = 'returned', return_date = ?, returned_by = ? WHERE id = ? AND status = 'issued'"
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

            $book = $conn->prepare("UPDATE library_books SET status = 'available' WHERE id = ? AND status = 'issued'");
            if (!$book) {
                throw new Exception($conn->error);
            }
            $book->bind_param('i', $bookId);
            $book->execute();
            $book->close();
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $msg = 'Book returned and stock updated.';
        $mailNote = libraryNotifyBorrower($conn, $issueId, 'return');
        if ($mailNote !== '') {
            $msg .= ' ' . $mailNote;
        }
        return ['success' => true, 'message' => $msg];
    }
}

if (!function_exists('libraryHydrateIssueRows')) {
    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    function libraryHydrateIssueRows($conn, array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $studentIds = [];
        $facultyIds = [];
        foreach ($rows as $r) {
            $sid = trim((string) ($r['student_id'] ?? ''));
            if ($sid !== '') {
                $studentIds[$sid] = $sid;
            }
            $fid = (int) ($r['faculty_id'] ?? 0);
            if ($fid > 0) {
                $facultyIds[$fid] = $fid;
            }
        }

        $students = [];
        $studentList = array_values($studentIds);
        if ($studentList !== []) {
            $placeholders = implode(',', array_fill(0, count($studentList), '?'));
            $stmt = $conn->prepare("SELECT student_id, name, email FROM students WHERE student_id IN ($placeholders)");
            if ($stmt) {
                $stmt->bind_param(str_repeat('s', count($studentList)), ...$studentList);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($res) {
                        while ($s = $res->fetch_assoc()) {
                            $students[(string) $s['student_id']] = $s;
                        }
                    }
                }
                $stmt->close();
            }
        }

        $faculty = [];
        $facultyList = array_values($facultyIds);
        if ($facultyList !== []) {
            $placeholders = implode(',', array_fill(0, count($facultyList), '?'));
            $stmt = $conn->prepare("SELECT id, name, designation, department FROM faculty WHERE id IN ($placeholders)");
            if ($stmt) {
                $stmt->bind_param(str_repeat('i', count($facultyList)), ...$facultyList);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($res) {
                        while ($f = $res->fetch_assoc()) {
                            $faculty[(int) $f['id']] = $f;
                        }
                    }
                }
                $stmt->close();
            }
        }

        foreach ($rows as &$r) {
            $sid = trim((string) ($r['student_id'] ?? ''));
            if ($sid !== '' && isset($students[$sid])) {
                $r['student_name'] = $students[$sid]['name'] ?? '';
                $r['student_email'] = $students[$sid]['email'] ?? '';
            } else {
                $r['student_name'] = $r['student_name'] ?? '';
                $r['student_email'] = $r['student_email'] ?? '';
            }
            $fid = (int) ($r['faculty_id'] ?? 0);
            if ($fid > 0 && isset($faculty[$fid])) {
                $r['faculty_name'] = $faculty[$fid]['name'] ?? '';
                $r['faculty_designation'] = $faculty[$fid]['designation'] ?? '';
                $r['faculty_department'] = $faculty[$fid]['department'] ?? '';
            } else {
                $r['faculty_name'] = $r['faculty_name'] ?? '';
                $r['faculty_designation'] = $r['faculty_designation'] ?? '';
                $r['faculty_department'] = $r['faculty_department'] ?? '';
            }
        }
        unset($r);

        return $rows;
    }
}

if (!function_exists('listLibraryIssues')) {
    /**
     * @param array{borrower_type?:string,status?:string,q?:string} $filters
     * @return list<array<string,mixed>>
     */
    function listLibraryIssues($conn, array $filters = []): array
    {
        ensureLibraryTables($conn);
        $sql = "SELECT i.*, b.accession_no, b.title, b.author, b.centre_id, c.name AS centre_name
                FROM library_issues i
                LEFT JOIN library_books b ON b.id = i.book_id
                LEFT JOIN centres c ON c.id = b.centre_id
                WHERE 1=1";
        $types = '';
        $params = [];
        $filterCentre = (int) ($filters['centre_id'] ?? 0);
        libraryAppendCentreFilter('b.centre_id', $sql, $types, $params, $conn, $filterCentre);

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
        $rows = [];
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log('listLibraryIssues prepare failed: ' . $conn->error);
                return [];
            }
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) {
                error_log('listLibraryIssues execute failed: ' . $stmt->error);
                $stmt->close();
                return [];
            }
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
            $stmt->close();
        } else {
            $res = $conn->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
            } else {
                error_log('listLibraryIssues query failed: ' . $conn->error);
            }
        }

        $rows = libraryHydrateIssueRows($conn, $rows);
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $ql = strtolower($q);
            $rows = array_values(array_filter($rows, static function ($r) use ($ql) {
                $hay = strtolower(trim(implode(' ', [
                    (string) ($r['accession_no'] ?? ''),
                    (string) ($r['title'] ?? ''),
                    (string) ($r['author'] ?? ''),
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

if (!function_exists('listOrphanIssuedLibraryBooks')) {
    /** @return list<array<string,mixed>> */
    function listOrphanIssuedLibraryBooks($conn): array
    {
        ensureLibraryTables($conn);
        $sql = "SELECT b.*, c.name AS centre_name
                FROM library_books b
                LEFT JOIN library_issues i ON i.book_id = b.id AND i.status = 'issued'
                LEFT JOIN centres c ON c.id = b.centre_id
                WHERE b.status = 'issued' AND i.id IS NULL";
        $types = '';
        $params = [];
        libraryAppendCentreFilter('b.centre_id', $sql, $types, $params, $conn);
        $sql .= ' ORDER BY b.accession_no ASC';
        $rows = [];
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
            $stmt->close();
            return $rows;
        }
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('returnLibraryCopy')) {
    /** @return array{success:bool,message:string} */
    function returnLibraryCopy($conn, int $bookId, string $returnedBy, ?string $returnDate = null): array
    {
        ensureLibraryTables($conn);
        $book = getLibraryBook($conn, $bookId);
        if (!$book) {
            return ['success' => false, 'message' => 'Book not found.'];
        }

        $stmt = $conn->prepare("SELECT id FROM library_issues WHERE book_id = ? AND status = 'issued' ORDER BY id DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $bookId);
            $stmt->execute();
            $open = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($open) {
                return returnLibraryBook($conn, (int) $open['id'], $returnedBy, $returnDate);
            }
        }

        if (($book['status'] ?? '') !== 'issued') {
            return ['success' => false, 'message' => 'This copy is not currently issued.'];
        }

        $upd = $conn->prepare("UPDATE library_books SET status = 'available' WHERE id = ? AND status = 'issued'");
        if (!$upd) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $upd->bind_param('i', $bookId);
        $ok = $upd->execute();
        $upd->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not update stock.'];
        }
        return [
            'success' => true,
            'message' => 'Accession ' . $book['accession_no'] . ' marked available. Return date is today.',
        ];
    }
}

if (!function_exists('libraryStatusBadgeClass')) {
    function libraryStatusBadgeClass(string $status): string
    {
        if ($status === 'available' || $status === 'returned') {
            return 'success';
        }
        if ($status === 'issued') {
            return 'warning';
        }
        if ($status === 'overdue') {
            return 'danger';
        }
        return 'secondary';
    }
}

if (!function_exists('libraryIssueIsOverdue')) {
    function libraryIssueIsOverdue(array $row): bool
    {
        if (($row['status'] ?? '') !== 'issued') {
            return false;
        }
        $due = (string) ($row['due_date'] ?? '');
        return $due !== '' && $due < date('Y-m-d');
    }
}

if (!function_exists('libraryBorrowerLabel')) {
    function libraryBorrowerLabel(array $row): string
    {
        if (($row['borrower_type'] ?? '') === 'staff') {
            $name = trim((string) ($row['faculty_name'] ?? ''));
            $des = trim((string) ($row['faculty_designation'] ?? ''));
            if ($name !== '' && $des !== '') {
                return $name . ' (' . $des . ')';
            }
            return $name !== '' ? $name : 'Staff #' . (int) ($row['faculty_id'] ?? 0);
        }
        $name = trim((string) ($row['student_name'] ?? ''));
        $sid = trim((string) ($row['student_id'] ?? ''));
        if ($name !== '' && $sid !== '') {
            return $name . ' (' . $sid . ')';
        }
        return $sid !== '' ? $sid : 'Student';
    }
}

if (!function_exists('libraryReminderCronKey')) {
    function libraryReminderCronKey(): string
    {
        if (!defined('SMTP_USERNAME')) {
            $emailCfg = __DIR__ . '/../config/email.php';
            if (is_file($emailCfg)) {
                require_once $emailCfg;
            }
        }
        return hash('sha256', (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'nielit') . '|library-reminders|' . (defined('APP_URL') ? APP_URL : ''));
    }
}

if (!function_exists('libraryFormatDate')) {
    function libraryFormatDate(?string $date): string
    {
        $date = trim((string) $date);
        if ($date === '' || $date === '0000-00-00') {
            return '—';
        }
        $ts = strtotime($date);
        return $ts ? date('d M Y', $ts) : $date;
    }
}

if (!function_exists('libraryBorrowerContact')) {
    /** @return array{name:string,email:string,label:string} */
    function libraryBorrowerContact($conn, array $issue): array
    {
        $name = '';
        $email = '';
        $label = '';
        if (($issue['borrower_type'] ?? '') === 'staff') {
            $fid = (int) ($issue['faculty_id'] ?? 0);
            if ($fid > 0) {
                $stmt = $conn->prepare('SELECT name, email FROM faculty WHERE id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $fid);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($row) {
                        $name = trim((string) ($row['name'] ?? ''));
                        $email = trim((string) ($row['email'] ?? ''));
                        $label = $name;
                    }
                }
            }
        } else {
            $student = lookupLibraryStudent($conn, (string) ($issue['student_id'] ?? ''));
            if ($student) {
                $name = trim((string) ($student['name'] ?? ''));
                $email = trim((string) ($student['email'] ?? ''));
                $sid = trim((string) ($student['student_id'] ?? ''));
                $label = ($name !== '' && $sid !== '') ? ($name . ' (' . $sid . ')') : ($name !== '' ? $name : $sid);
            }
        }
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/@(workshop\.nielit\.local|localhost)$/i', $email))) {
            $email = '';
        }
        return ['name' => $name !== '' ? $name : 'Borrower', 'email' => $email, 'label' => $label];
    }
}

if (!function_exists('libraryNoticeSubject')) {
    function libraryNoticeSubject(string $kind): string
    {
        if ($kind === 'return') {
            return 'Book returned - NIELIT Bhubaneswar Library';
        }
        if ($kind === 'due_soon') {
            return 'Reminder: library book due soon - NIELIT Bhubaneswar';
        }
        if ($kind === 'overdue') {
            return 'Reminder: library book is overdue - NIELIT Bhubaneswar';
        }
        return 'Book issued - NIELIT Bhubaneswar Library';
    }
}

if (!function_exists('libraryNoticeIntro')) {
    function libraryNoticeIntro(string $kind): string
    {
        if ($kind === 'return') {
            return 'Your library book has been returned. Thank you.';
        }
        if ($kind === 'due_soon') {
            return 'This is a reminder to return your library book by the due date.';
        }
        if ($kind === 'overdue') {
            return 'Your library book is overdue. Please return it to the library as soon as possible.';
        }
        return 'A library book has been issued to you. Please return it on or before the due date.';
    }
}

if (!function_exists('libraryNoticeHtml')) {
    /** @param array<string,string> $data */
    function libraryNoticeHtml(string $kind, array $data): string
    {
        $h = static function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        };
        $name = $h((string) ($data['name'] ?? 'Borrower'));
        $title = $h((string) ($data['title'] ?? ''));
        $accession = $h((string) ($data['accession_no'] ?? ''));
        $issueDate = $h((string) ($data['issue_date'] ?? '—'));
        $dueDate = $h((string) ($data['due_date'] ?? '—'));
        $returnDate = $h((string) ($data['return_date'] ?? '—'));
        $centre = $h((string) ($data['centre_name'] ?? '—'));
        $heading = $kind === 'return' ? 'Book returned' : ($kind === 'overdue' ? 'Overdue reminder' : ($kind === 'due_soon' ? 'Due date reminder' : 'Book issued'));
        $intro = $h(libraryNoticeIntro($kind));
        $year = date('Y');
        $returnRow = ($kind === 'return')
            ? '<tr><td style="color:#666;font-weight:600;width:35%;">Returned:</td><td style="color:#333;">' . $returnDate . '</td></tr>'
            : '';
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{$heading}</title></head>
<body style="margin:0;padding:0;font-family:Arial,sans-serif;background-color:#f4f4f4;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
<tr><td style="background:linear-gradient(135deg,#0a1628 0%,#112240 100%);padding:28px;text-align:center;">
<h1 style="color:#ffffff;margin:0;font-size:24px;">{$heading}</h1>
<p style="color:#e3f2fd;margin:8px 0 0;font-size:14px;">NIELIT Bhubaneswar Library</p>
</td></tr>
<tr><td style="padding:32px 28px;">
<p style="color:#333;font-size:16px;line-height:1.6;margin:0 0 16px;">Dear <strong>{$name}</strong>,</p>
<p style="color:#555;font-size:15px;line-height:1.7;margin:0 0 20px;">{$intro}</p>
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fa;border-left:4px solid #1a56db;margin:16px 0;">
<tr><td style="padding:18px;">
<table cellpadding="6" cellspacing="0" width="100%">
<tr><td style="color:#666;font-weight:600;width:35%;">Accession:</td><td style="color:#333;">{$accession}</td></tr>
<tr><td style="color:#666;font-weight:600;">Title:</td><td style="color:#333;">{$title}</td></tr>
<tr><td style="color:#666;font-weight:600;">Centre:</td><td style="color:#333;">{$centre}</td></tr>
<tr><td style="color:#666;font-weight:600;">Issued:</td><td style="color:#333;">{$issueDate}</td></tr>
<tr><td style="color:#666;font-weight:600;">Due date:</td><td style="color:#333;">{$dueDate}</td></tr>
{$returnRow}
</table>
</td></tr>
</table>
<p style="color:#64748b;font-size:13px;margin:24px 0 0;">If you have already returned this book, please ignore this message.</p>
</td></tr>
<tr><td style="background:#f8f9fa;padding:16px;text-align:center;color:#64748b;font-size:12px;">© {$year} NIELIT Bhubaneswar</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }
}

if (!function_exists('libraryNoticeText')) {
    /** @param array<string,string> $data */
    function libraryNoticeText(string $kind, array $data): string
    {
        $lines = [
            'Dear ' . ($data['name'] ?? 'Borrower') . ',',
            '',
            libraryNoticeIntro($kind),
            '',
            'Accession: ' . ($data['accession_no'] ?? ''),
            'Title: ' . ($data['title'] ?? ''),
            'Centre: ' . ($data['centre_name'] ?? ''),
            'Issued: ' . ($data['issue_date'] ?? ''),
            'Due date: ' . ($data['due_date'] ?? ''),
        ];
        if ($kind === 'return') {
            $lines[] = 'Returned: ' . ($data['return_date'] ?? '');
        }
        $lines[] = '';
        $lines[] = 'NIELIT Bhubaneswar Library';
        return implode("\n", $lines);
    }
}

if (!function_exists('sendLibraryNoticeEmail')) {
    /** @param array<string,string> $data */
    function sendLibraryNoticeEmail(string $kind, array $data): bool
    {
        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $helper = __DIR__ . '/email_helper.php';
        if (!is_file($helper)) {
            return false;
        }
        require_once $helper;
        if (!function_exists('sendPhpMailerWithSmtpFallback')) {
            return false;
        }
        $name = (string) ($data['name'] ?? 'Borrower');
        $subject = libraryNoticeSubject($kind);
        $html = libraryNoticeHtml($kind, $data);
        $text = libraryNoticeText($kind, $data);
        $result = sendPhpMailerWithSmtpFallback(static function ($mail) use ($email, $name, $subject, $html, $text) {
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($email, $name);
            $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;
        }, ['timeout' => 12]);
        return !empty($result['ok']);
    }
}

if (!function_exists('libraryLoadIssueWithBook')) {
    /** @return array<string,mixed>|null */
    function libraryLoadIssueWithBook($conn, int $issueId): ?array
    {
        if ($issueId <= 0) {
            return null;
        }
        $stmt = $conn->prepare(
            'SELECT i.*, b.accession_no, b.title, b.author, b.centre_id, c.name AS centre_name
             FROM library_issues i
             LEFT JOIN library_books b ON b.id = i.book_id
             LEFT JOIN centres c ON c.id = b.centre_id
             WHERE i.id = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $issueId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $allowed = libraryGrantedCentreIds($conn);
        if ($allowed !== null && !in_array((int) ($row['centre_id'] ?? 0), $allowed, true)) {
            return null;
        }
        return $row;
    }
}

if (!function_exists('libraryNotifyBorrower')) {
    function libraryNotifyBorrower($conn, int $issueId, string $kind): string
    {
        $issue = libraryLoadIssueWithBook($conn, $issueId);
        if (!$issue) {
            return '';
        }
        $contact = libraryBorrowerContact($conn, $issue);
        if ($contact['email'] === '') {
            return 'No email is on file for the borrower.';
        }
        $payload = [
            'name' => $contact['name'],
            'email' => $contact['email'],
            'accession_no' => (string) ($issue['accession_no'] ?? ''),
            'title' => (string) ($issue['title'] ?? ''),
            'issue_date' => libraryFormatDate($issue['issue_date'] ?? ''),
            'due_date' => libraryFormatDate($issue['due_date'] ?? ''),
            'return_date' => libraryFormatDate($issue['return_date'] ?? ''),
            'centre_name' => libraryCentreLabel($issue),
        ];
        $ok = sendLibraryNoticeEmail($kind, $payload);
        if ($ok && in_array($kind, ['due_soon', 'overdue'], true)) {
            $today = date('Y-m-d');
            $upd = $conn->prepare('UPDATE library_issues SET last_reminder_on = ?, last_reminder_kind = ? WHERE id = ?');
            if ($upd) {
                $upd->bind_param('ssi', $today, $kind, $issueId);
                $upd->execute();
                $upd->close();
            }
        }
        return $ok ? ('Email sent to ' . $contact['email'] . '.') : 'Notification email could not be sent.';
    }
}

if (!function_exists('libraryReminderKindForIssue')) {
    function libraryReminderKindForIssue(array $issue): string
    {
        $due = (string) ($issue['due_date'] ?? '');
        return ($due !== '' && $due < date('Y-m-d')) ? 'overdue' : 'due_soon';
    }
}

if (!function_exists('librarySendIssueReminder')) {
    /** @return array{success:bool,message:string} */
    function librarySendIssueReminder($conn, int $issueId): array
    {
        ensureLibraryTables($conn);
        $issue = libraryLoadIssueWithBook($conn, $issueId);
        if (!$issue || ($issue['status'] ?? '') !== 'issued') {
            return ['success' => false, 'message' => 'Issued record not found.'];
        }
        $kind = libraryReminderKindForIssue($issue);
        $note = libraryNotifyBorrower($conn, $issueId, $kind);
        $ok = strpos($note, 'Email sent to') === 0;
        return ['success' => $ok, 'message' => $note !== '' ? $note : 'Could not send reminder.'];
    }
}

if (!function_exists('sendLibraryDueReminders')) {
    /** @return array{sent:int,skipped:int,failed:int} */
    function sendLibraryDueReminders($conn, int $limit = 40): array
    {
        ensureLibraryTables($conn);
        $limit = max(1, min(80, $limit));
        $sql = "SELECT i.id
                FROM library_issues i
                INNER JOIN library_books b ON b.id = i.book_id
                WHERE i.status = 'issued'
                  AND i.due_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
                  AND (i.last_reminder_on IS NULL OR i.last_reminder_on < CURDATE())";
        $types = '';
        $params = [];
        libraryAppendCentreFilter('b.centre_id', $sql, $types, $params, $conn);
        $sql .= " ORDER BY i.due_date ASC, i.id ASC LIMIT {$limit}";
        $ids = [];
        if ($types !== '') {
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
        } else {
            $res = $conn->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $ids[] = (int) $row['id'];
                }
            }
        }
        $sent = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $issue = libraryLoadIssueWithBook($conn, $id);
            if (!$issue) {
                $skipped++;
                continue;
            }
            $contact = libraryBorrowerContact($conn, $issue);
            if ($contact['email'] === '') {
                $skipped++;
                continue;
            }
            $kind = libraryReminderKindForIssue($issue);
            $note = libraryNotifyBorrower($conn, $id, $kind);
            if (strpos($note, 'Email sent to') === 0) {
                $sent++;
            } else {
                $failed++;
            }
        }
        return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
    }
}
