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
            UNIQUE KEY uq_lib_accession (accession_no),
            KEY idx_lib_status (status),
            KEY idx_lib_title (title)
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
            granted_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_lib_admin (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        foreach (['books' => $books, 'issues' => $issues, 'access' => $access] as $label => $sql) {
            if (!$conn->query($sql)) {
                error_log('ensureLibraryTables ' . $label . ' failed: ' . $conn->error);
                return false;
            }
        }

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

if (!function_exists('libraryGrantAccess')) {
    /** @return array{success:bool,message:string} */
    function libraryGrantAccess($conn, int $adminId, string $grantedBy): array
    {
        ensureLibraryTables($conn);
        if ($adminId <= 0) {
            return ['success' => false, 'message' => 'Select a valid account.'];
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
            'INSERT INTO library_module_access (admin_id, granted_by) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param('is', $adminId, $grantedBy);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not grant access.'];
        }
        return ['success' => true, 'message' => 'Library access granted to ' . $row['username'] . '.'];
    }
}

if (!function_exists('libraryRevokeAccess')) {
    /** @return array{success:bool,message:string} */
    function libraryRevokeAccess($conn, int $adminId): array
    {
        ensureLibraryTables($conn);
        if ($adminId <= 0) {
            return ['success' => false, 'message' => 'Invalid account.'];
        }
        $stmt = $conn->prepare('DELETE FROM library_module_access WHERE admin_id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('i', $adminId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not revoke access.'];
        }
        return ['success' => true, 'message' => 'Library access revoked.'];
    }
}

if (!function_exists('listLibraryAccessCandidates')) {
    /** @return list<array<string,mixed>> */
    function listLibraryAccessCandidates($conn): array
    {
        ensureLibraryTables($conn);
        $sql = "SELECT a.id, a.username, a.role, a.email, lma.id AS grant_id, lma.granted_by, lma.created_at AS granted_at
                FROM admin a
                LEFT JOIN library_module_access lma ON lma.admin_id = a.id
                WHERE a.role IN ('course_coordinator','faculty')
                ORDER BY a.role ASC, a.username ASC";
        $rows = [];
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('libraryStats')) {
    /** @return array{total:int,available:int,issued:int,overdue:int} */
    function libraryStats($conn): array
    {
        ensureLibraryTables($conn);
        $stats = ['total' => 0, 'available' => 0, 'issued' => 0, 'overdue' => 0];
        $res = $conn->query("SELECT status, COUNT(*) AS c FROM library_books GROUP BY status");
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
        $over = $conn->query("SELECT COUNT(*) AS c FROM library_issues WHERE status = 'issued' AND due_date < CURDATE()");
        if ($over) {
            $stats['overdue'] = (int) ($over->fetch_assoc()['c'] ?? 0);
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
        if (!isset(libraryBookStatuses()[$status])) {
            $status = 'available';
        }
        $createdBy = trim((string) ($data['created_by'] ?? ''));

        $dup = $conn->prepare('SELECT id FROM library_books WHERE accession_no = ? AND id <> ? LIMIT 1');
        $checkId = $id ?? 0;
        if ($dup) {
            $dup->bind_param('si', $accession, $checkId);
            $dup->execute();
            $exists = $dup->get_result()->fetch_assoc();
            $dup->close();
            if ($exists) {
                return ['success' => false, 'message' => 'Accession number already exists.'];
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
            $stmt = $conn->prepare(
                'UPDATE library_books SET accession_no=?, title=?, author=?, publisher=?, isbn=?, category=?, edition=?, pub_year=?,
                 purchase_date=NULLIF(?, \'\'), bill_no=?, price=NULLIF(?, 0), source=?, shelf_location=?, remarks=?, status=?
                 WHERE id=?'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
            $stmt->bind_param(
                'ssssssssssdssssi',
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
             purchase_date, bill_no, price, source, shelf_location, remarks, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, \'\'), ?, NULLIF(?, 0), ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $insertStatus = 'available';
        $stmt->bind_param(
            'ssssssssssdsssss',
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
            $createdBy
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
        return $row ?: null;
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
        return $row ?: null;
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
        $sql = 'SELECT * FROM library_books WHERE 1=1';
        $types = '';
        $params = [];
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        if ($status !== '' && $status !== 'all' && isset(libraryBookStatuses()[$status])) {
            $sql .= ' AND status = ?';
            $types .= 's';
            $params[] = $status;
        }
        if (!empty($filters['available_only'])) {
            $sql .= " AND status = 'available'";
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= ' AND (accession_no LIKE ? OR title LIKE ? OR author LIKE ? OR isbn LIKE ? OR category LIKE ?)';
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY accession_no ASC';
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

if (!function_exists('lookupLibraryStudent')) {
    /** @return array<string,mixed>|null */
    function lookupLibraryStudent($conn, string $studentId): ?array
    {
        $studentId = trim($studentId);
        if ($studentId === '') {
            return null;
        }
        $stmt = $conn->prepare('SELECT student_id, name, email FROM students WHERE student_id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
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
        if ($type === 'student') {
            $studentId = trim((string) ($data['student_id'] ?? ''));
            $student = lookupLibraryStudent($conn, $studentId);
            if (!$student) {
                return ['success' => false, 'message' => 'Student ID not found.'];
            }
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
            $stmt = $conn->prepare(
                'INSERT INTO library_issues (book_id, borrower_type, student_id, faculty_id, issue_date, due_date, status, remarks, issued_by)
                 VALUES (?, ?, NULLIF(?, \'\'), NULLIF(?, 0), ?, ?, \'issued\', ?, ?)'
            );
            if (!$stmt) {
                throw new Exception($conn->error);
            }
            $stmt->bind_param('ississss', $bookId, $type, $studentId, $facultyId, $issueDate, $dueDate, $remarks, $issuedBy);
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
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

        return [
            'success' => true,
            'message' => 'Issued accession ' . $book['accession_no'] . ' successfully.',
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

        return ['success' => true, 'message' => 'Book returned and stock updated.'];
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
        $sql = "SELECT i.*, b.accession_no, b.title, b.author,
                       s.name AS student_name, s.email AS student_email,
                       f.name AS faculty_name, f.designation AS faculty_designation,
                       f.department AS faculty_department, f.staff_category
                FROM library_issues i
                INNER JOIN library_books b ON b.id = i.book_id
                LEFT JOIN students s ON s.student_id = i.student_id
                LEFT JOIN faculty f ON f.id = i.faculty_id
                WHERE 1=1";
        $types = '';
        $params = [];

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

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= ' AND (b.accession_no LIKE ? OR b.title LIKE ? OR i.student_id LIKE ? OR s.name LIKE ? OR f.name LIKE ?)';
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
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
