<?php
/**
 * Candidate Requirements module — document checklist and full candidate dossier.
 */

if (!function_exists('requirementsCanAccess')) {
    function requirementsCanAccess(?string $role = null): bool
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        return in_array($role, [
            'master_admin',
            'course_coordinator',
            'front_office_desk',
            'data_entry_operator',
            'report_viewer',
            'placement_coordinator',
        ], true);
    }
}

if (!function_exists('requirementsRequireAccess')) {
    function requirementsRequireAccess($conn = null): void
    {
        if (!isset($_SESSION['admin'])) {
            header('Location: login.php');
            exit();
        }
        $teaching = __DIR__ . '/teaching_access.php';
        if (is_file($teaching)) {
            require_once $teaching;
            if (function_exists('admin_redirect_faculty_from_restricted_page')) {
                admin_redirect_faculty_from_restricted_page();
            }
        }
        if (!requirementsCanAccess()) {
            $_SESSION['message'] = 'Access denied. The Requirements module is not available for your role.';
            $_SESSION['message_type'] = 'danger';
            $dash = function_exists('app_url') ? app_url('admin/dashboard') : 'dashboard.php';
            header('Location: ' . $dash);
            exit();
        }
    }
}

if (!function_exists('requirementsAssignedCourseIds')) {
    /**
     * @return list<int>|null  null = all courses (not a coordinator)
     */
    function requirementsAssignedCourseIds($conn): ?array
    {
        $role = (string) ($_SESSION['admin_role'] ?? '');
        if ($role !== 'course_coordinator') {
            return null;
        }
        $adminId = (int) ($_SESSION['admin_id'] ?? 0);
        if ($adminId <= 0 && isset($_SESSION['admin']) && $conn instanceof mysqli) {
            $stmt = $conn->prepare('SELECT id FROM admin WHERE username = ? LIMIT 1');
            if ($stmt) {
                $username = (string) $_SESSION['admin'];
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $adminId = (int) ($row['id'] ?? 0);
                if ($adminId > 0) {
                    $_SESSION['admin_id'] = $adminId;
                }
            }
        }
        if ($adminId <= 0 || !($conn instanceof mysqli)) {
            return [];
        }
        $stmt = $conn->prepare(
            'SELECT c.id FROM admin_course_assignments aca
             JOIN courses c ON c.id = aca.course_id
             WHERE aca.admin_id = ? AND aca.is_active = 1'
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
        $stmt->close();
        return $ids;
    }
}

if (!function_exists('requirementsTableExists')) {
    function requirementsTableExists($conn, string $table): bool
    {
        if (!($conn instanceof mysqli) || $table === '') {
            return false;
        }
        $safe = $conn->real_escape_string($table);
        $r = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return $r && $r->num_rows > 0;
    }
}

if (!function_exists('requirementsStudentColumns')) {
    /**
     * @return array<string,bool>
     */
    function requirementsStudentColumns($conn): array
    {
        static $cols = null;
        if ($cols !== null) {
            return $cols;
        }
        $cols = [];
        if (!($conn instanceof mysqli)) {
            return $cols;
        }
        $r = $conn->query('SHOW COLUMNS FROM students');
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $name = (string) ($row['Field'] ?? '');
                if ($name !== '') {
                    $cols[$name] = true;
                }
            }
        }
        return $cols;
    }
}

if (!function_exists('requirementsHasColumn')) {
    function requirementsHasColumn($conn, string $column): bool
    {
        $cols = requirementsStudentColumns($conn);
        return isset($cols[$column]);
    }
}

if (!function_exists('requirementsDocumentCatalog')) {
    /**
     * @return list<array{key:string,label:string,required:bool,icon:string,virtual?:bool}>
     */
    function requirementsDocumentCatalog($conn, bool $includeDge = false, bool $includeFingerprint = true): array
    {
        $items = [
            ['key' => 'passport_photo', 'label' => 'Passport photo', 'required' => true, 'icon' => 'fa-image'],
            ['key' => 'signature', 'label' => 'Signature', 'required' => true, 'icon' => 'fa-signature'],
            ['key' => 'left_thumb_impression', 'label' => 'Left thumb impression', 'required' => false, 'icon' => 'fa-fingerprint'],
            ['key' => 'aadhar_card_doc', 'label' => 'Aadhaar card', 'required' => true, 'icon' => 'fa-id-card'],
            ['key' => 'tenth_marksheet_doc', 'label' => '10th certificate / marksheet', 'required' => true, 'icon' => 'fa-file-alt'],
            ['key' => 'twelfth_marksheet_doc', 'label' => '12th marksheet', 'required' => false, 'icon' => 'fa-file-alt'],
            ['key' => 'twelfth_certificate_doc', 'label' => '12th certificate', 'required' => false, 'icon' => 'fa-certificate'],
            ['key' => 'caste_certificate_doc', 'label' => 'Caste certificate', 'required' => false, 'icon' => 'fa-scroll'],
            ['key' => 'graduation_certificate_doc', 'label' => 'Graduation certificate', 'required' => false, 'icon' => 'fa-graduation-cap'],
            ['key' => 'other_documents_doc', 'label' => 'Other documents', 'required' => false, 'icon' => 'fa-folder-open'],
            ['key' => 'payment_receipt', 'label' => 'Payment receipt', 'required' => false, 'icon' => 'fa-receipt'],
            ['key' => 'documents', 'label' => 'Legacy documents', 'required' => false, 'icon' => 'fa-file'],
        ];
        if ($includeDge) {
            $items[] = ['key' => 'bank_passbook_doc', 'label' => 'Bank passbook', 'required' => true, 'icon' => 'fa-university'];
            $items[] = ['key' => 'income_certificate_doc', 'label' => 'Income certificate', 'required' => true, 'icon' => 'fa-file-invoice-dollar'];
            $items[] = ['key' => 'aadhaar_bank_seeding_doc', 'label' => 'Aadhaar bank seeding proof', 'required' => true, 'icon' => 'fa-link'];
        }
        $out = [];
        foreach ($items as $item) {
            if (requirementsHasColumn($conn, $item['key'])) {
                $out[] = $item;
            }
        }
        if ($includeFingerprint) {
            $out[] = [
                'key' => '_fingerprint',
                'label' => 'Fingerprint enrolment',
                'required' => false,
                'icon' => 'fa-fingerprint',
                'virtual' => true,
            ];
        }
        return $out;
    }
}

if (!function_exists('requirementsProjectRoot')) {
    function requirementsProjectRoot(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('requirementsNormalizeRelPath')) {
    function requirementsNormalizeRelPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        if ($path === '' || strpos($path, '..') !== false) {
            return '';
        }
        return $path;
    }
}

if (!function_exists('requirementsDocExists')) {
    function requirementsDocExists(string $rel): bool
    {
        $rel = requirementsNormalizeRelPath($rel);
        if ($rel === '') {
            return false;
        }
        $fs = requirementsProjectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        return is_file($fs);
    }
}

if (!function_exists('requirementsDocUrl')) {
    function requirementsDocUrl(string $rel): string
    {
        $rel = requirementsNormalizeRelPath($rel);
        if ($rel === '') {
            return '';
        }
        $base = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
        return $base . '/' . $rel;
    }
}

if (!function_exists('requirementsEvaluateDocuments')) {
    /**
     * @param array<string,mixed> $student
     * @return array{
     *   items: list<array<string,mixed>>,
     *   required_total: int,
     *   required_ok: int,
     *   optional_ok: int,
     *   optional_total: int,
     *   percent: int,
     *   complete: bool
     * }
     */
    function requirementsEvaluateDocuments($conn, array $student, bool $hasFingerprint = false, bool $isDge = false): array
    {
        $catalog = requirementsDocumentCatalog($conn, $isDge, true);
        $items = [];
        $requiredTotal = 0;
        $requiredOk = 0;
        $optionalTotal = 0;
        $optionalOk = 0;
        foreach ($catalog as $spec) {
            $virtual = !empty($spec['virtual']);
            $present = false;
            $path = '';
            if ($virtual && $spec['key'] === '_fingerprint') {
                $present = $hasFingerprint;
            } else {
                $path = trim((string) ($student[$spec['key']] ?? ''));
                $present = $path !== '' && requirementsDocExists($path);
            }
            $item = $spec;
            $item['present'] = $present;
            $item['path'] = $path;
            $item['url'] = ($present && $path !== '') ? requirementsDocUrl($path) : '';
            $items[] = $item;
            if (!empty($spec['required'])) {
                $requiredTotal++;
                if ($present) {
                    $requiredOk++;
                }
            } else {
                $optionalTotal++;
                if ($present) {
                    $optionalOk++;
                }
            }
        }
        $percent = $requiredTotal > 0 ? (int) round(($requiredOk / $requiredTotal) * 100) : 100;
        return [
            'items' => $items,
            'required_total' => $requiredTotal,
            'required_ok' => $requiredOk,
            'optional_ok' => $optionalOk,
            'optional_total' => $optionalTotal,
            'percent' => $percent,
            'complete' => $requiredTotal > 0 && $requiredOk >= $requiredTotal,
        ];
    }
}

if (!function_exists('requirementsSqlDocCompleteExpr')) {
    function requirementsSqlDocCompleteExpr($conn): string
    {
        $parts = [];
        foreach (['passport_photo', 'signature', 'aadhar_card_doc', 'tenth_marksheet_doc'] as $col) {
            if (requirementsHasColumn($conn, $col)) {
                $parts[] = "(IFNULL(s.`{$col}`,'') <> '')";
            }
        }
        if ($parts === []) {
            return '1';
        }
        return '(' . implode(' AND ', $parts) . ')';
    }
}

if (!function_exists('requirementsListCourses')) {
    /**
     * @param list<int>|null $courseIds
     * @return list<array<string,mixed>>
     */
    function requirementsListCourses($conn, ?array $courseIds = null): array
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }
        $sql = 'SELECT id, course_name, IFNULL(course_code, \'\') AS course_code FROM courses';
        if (is_array($courseIds)) {
            if ($courseIds === []) {
                return [];
            }
            $ids = array_values(array_filter(array_map('intval', $courseIds)));
            if ($ids === []) {
                return [];
            }
            $sql .= ' WHERE id IN (' . implode(',', $ids) . ')';
        }
        $sql .= ' ORDER BY course_name';
        $res = $conn->query($sql);
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('requirementsCanViewStudent')) {
    function requirementsCanViewStudent($conn, string $studentId): bool
    {
        $studentId = trim($studentId);
        if ($studentId === '' || !($conn instanceof mysqli)) {
            return false;
        }
        $courseIds = requirementsAssignedCourseIds($conn);
        if ($courseIds === null) {
            $stmt = $conn->prepare('SELECT id FROM students WHERE LOWER(TRIM(student_id)) = LOWER(?) LIMIT 1');
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('s', $studentId);
            $stmt->execute();
            $ok = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $ok;
        }
        if ($courseIds === []) {
            return false;
        }
        $ph = implode(',', array_fill(0, count($courseIds), '?'));
        $types = 's' . str_repeat('i', count($courseIds));
        $sql = "SELECT id FROM students WHERE LOWER(TRIM(student_id)) = LOWER(?) AND course_id IN ({$ph}) LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $params = array_merge([$studentId], $courseIds);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('requirementsListCandidates')) {
    /**
     * @param array{q?:string,course_id?:int,status?:string,docs?:string,fingerprint?:string,page?:int,per_page?:int} $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int,stats:array<string,int>}
     */
    function requirementsListCandidates($conn, array $filters = []): array
    {
        $empty = [
            'rows' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 25,
            'pages' => 1,
            'stats' => ['total' => 0, 'complete' => 0, 'missing_docs' => 0, 'fingerprint' => 0],
        ];
        if (!($conn instanceof mysqli)) {
            return $empty;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        $courseId = (int) ($filters['course_id'] ?? 0);
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $docs = (string) ($filters['docs'] ?? '');
        $fpFilter = (string) ($filters['fingerprint'] ?? '');
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? 25);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $assigned = requirementsAssignedCourseIds($conn);
        if (is_array($assigned) && $assigned === []) {
            return $empty;
        }
        if (is_array($assigned) && $courseId > 0 && !in_array($courseId, $assigned, true)) {
            $courseId = 0;
        }

        $hasFpTable = requirementsTableExists($conn, 'student_fingerprint_templates');
        $docExpr = requirementsSqlDocCompleteExpr($conn);
        $fpSelect = $hasFpTable
            ? 'EXISTS (SELECT 1 FROM student_fingerprint_templates fp WHERE LOWER(TRIM(fp.student_id)) = LOWER(TRIM(s.student_id)))'
            : '0';
        $regSelect = requirementsHasColumn($conn, 'registration_date') ? 's.registration_date' : 'NULL AS registration_date';
        $photoSelect = requirementsHasColumn($conn, 'passport_photo') ? 's.passport_photo' : "'' AS passport_photo";
        $genderSelect = requirementsHasColumn($conn, 'gender') ? 's.gender' : "'' AS gender";
        $catSelect = requirementsHasColumn($conn, 'category') ? 's.category' : "'' AS category";

        $where = ['1=1'];
        $types = '';
        $params = [];

        if (is_array($assigned)) {
            $ph = implode(',', array_fill(0, count($assigned), '?'));
            $where[] = "s.student_id IN (SELECT s2.student_id FROM students s2 WHERE s2.course_id IN ({$ph}))";
            $types .= str_repeat('i', count($assigned));
            foreach ($assigned as $cid) {
                $params[] = $cid;
            }
        }
        if ($courseId > 0) {
            $where[] = 's.student_id IN (SELECT s3.student_id FROM students s3 WHERE s3.course_id = ?)';
            $types .= 'i';
            $params[] = $courseId;
        }
        if ($status !== '' && $status !== 'all') {
            $where[] = 'LOWER(TRIM(s.status)) = ?';
            $types .= 's';
            $params[] = $status;
        } else {
            $where[] = "LOWER(IFNULL(s.status,'')) NOT IN ('inactive')";
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $searchParts = ['s.student_id LIKE ?', 's.name LIKE ?', 's.mobile LIKE ?', 's.email LIKE ?'];
            $types .= 'ssss';
            array_push($params, $like, $like, $like, $like);
            if (requirementsHasColumn($conn, 'aadhar')) {
                $searchParts[] = 's.aadhar LIKE ?';
                $types .= 's';
                $params[] = $like;
            }
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        $extraWhere = [];
        if ($docs === 'complete') {
            $extraWhere[] = 'listed.docs_complete = 1';
        } elseif ($docs === 'missing') {
            $extraWhere[] = 'listed.docs_complete = 0';
        }
        if ($fpFilter === 'yes') {
            $extraWhere[] = 'listed.has_fingerprint = 1';
        } elseif ($fpFilter === 'no') {
            $extraWhere[] = 'listed.has_fingerprint = 0';
        }
        $extraSql = $extraWhere !== [] ? (' WHERE ' . implode(' AND ', $extraWhere)) : '';

        $from = "FROM students s
            INNER JOIN (
                SELECT student_id, MAX(id) AS max_id
                FROM students
                GROUP BY student_id
            ) latest ON latest.max_id = s.id
            LEFT JOIN courses c ON c.id = s.course_id
            WHERE " . implode(' AND ', $where);

        $selectBase = "SELECT s.id, s.student_id, s.name, s.mobile, s.email, s.status, s.course_id,
                {$genderSelect}, {$catSelect}, {$regSelect}, {$photoSelect},
                IFNULL(c.course_name,'') AS course_name,
                {$docExpr} AS docs_complete,
                ({$fpSelect}) AS has_fingerprint
            {$from}";

        $selectInner = "SELECT * FROM ({$selectBase}) listed{$extraSql}";

        $countSql = "SELECT COUNT(*) AS cnt FROM ({$selectInner}) listed";
        $stmt = $conn->prepare($countSql);
        $total = 0;
        if ($stmt) {
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $total = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $stmt->close();
        } else {
            error_log('requirementsListCandidates count SQL failed: ' . $conn->error);
        }

        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $listSql = $selectInner . ' ORDER BY listed.name ASC, listed.student_id ASC LIMIT ? OFFSET ?';
        $stmt = $conn->prepare($listSql);
        $rows = [];
        if ($stmt) {
            $listTypes = $types . 'ii';
            $listParams = array_merge($params, [$perPage, $offset]);
            $stmt->bind_param($listTypes, ...$listParams);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $row['docs_complete'] = (int) ($row['docs_complete'] ?? 0) === 1;
                $row['has_fingerprint'] = (int) ($row['has_fingerprint'] ?? 0) === 1;
                $row['photo_url'] = '';
                if (function_exists('biometricStudentPhotoUrl')) {
                    $row['photo_url'] = biometricStudentPhotoUrl($row);
                } elseif (!empty($row['passport_photo']) && requirementsDocExists((string) $row['passport_photo'])) {
                    $row['photo_url'] = requirementsDocUrl((string) $row['passport_photo']);
                }
                $rows[] = $row;
            }
            $stmt->close();
        }

        $stats = ['total' => 0, 'complete' => 0, 'missing_docs' => 0, 'fingerprint' => 0];
        $statsWhere = $where;
        $statsTypes = $types;
        $statsParams = $params;
        $statsSql = "SELECT
                COUNT(*) AS total,
                SUM(docs_complete) AS complete,
                SUM(CASE WHEN docs_complete = 0 THEN 1 ELSE 0 END) AS missing_docs,
                SUM(has_fingerprint) AS fingerprint
            FROM (
                SELECT s.id, {$docExpr} AS docs_complete, ({$fpSelect}) AS has_fingerprint
                FROM students s
                INNER JOIN (
                    SELECT student_id, MAX(id) AS max_id
                    FROM students
                    GROUP BY student_id
                ) latest ON latest.max_id = s.id
                WHERE " . implode(' AND ', $statsWhere) . '
            ) t';
        $stmt = $conn->prepare($statsSql);
        if ($stmt) {
            if ($statsTypes !== '') {
                $stmt->bind_param($statsTypes, ...$statsParams);
            }
            $stmt->execute();
            $hit = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($hit) {
                $stats = [
                    'total' => (int) ($hit['total'] ?? 0),
                    'complete' => (int) ($hit['complete'] ?? 0),
                    'missing_docs' => (int) ($hit['missing_docs'] ?? 0),
                    'fingerprint' => (int) ($hit['fingerprint'] ?? 0),
                ];
            }
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'stats' => $stats,
        ];
    }
}

if (!function_exists('requirementsLoadEnrollments')) {
    /**
     * @return list<array<string,mixed>>
     */
    function requirementsLoadEnrollments($conn, string $studentId): array
    {
        $studentId = trim($studentId);
        if ($studentId === '' || !($conn instanceof mysqli)) {
            return [];
        }
        $hasSchemeCol = requirementsHasColumn($conn, 'scheme_id');
        $hasScheme = $hasSchemeCol && requirementsTableExists($conn, 'schemes');
        $hasBatch = requirementsHasColumn($conn, 'batch_id');
        $hasCourseCol = requirementsHasColumn($conn, 'course');
        $hasReg = requirementsHasColumn($conn, 'registration_date');
        $schemeJoin = $hasScheme ? ' LEFT JOIN schemes sch ON sch.id = s.scheme_id ' : '';
        $schemeSelect = $hasScheme ? "IFNULL(sch.scheme_name,'') AS scheme_name, IFNULL(sch.scheme_code,'') AS scheme_code" : "'' AS scheme_name, '' AS scheme_code";
        $schemeIdSelect = $hasSchemeCol ? 's.scheme_id' : 'NULL AS scheme_id';
        $courseNameFallback = $hasCourseCol ? "IFNULL(s.course,'')" : "''";
        $courseCol = $hasCourseCol ? 's.course' : "'' AS course";
        $regCol = $hasReg ? 's.registration_date' : 'NULL AS registration_date';
        $batchJoin = '';
        $batchSelect = "'' AS batch_name, '' AS batch_code";
        if ($hasBatch && requirementsTableExists($conn, 'batches')) {
            $batchJoin = ' LEFT JOIN batches b ON b.id = s.batch_id ';
            $batchSelect = "IFNULL(b.batch_name,'') AS batch_name, IFNULL(b.batch_code,'') AS batch_code";
        }
        $sql = "SELECT s.id, s.student_id, s.course_id, {$courseCol}, s.status, {$schemeIdSelect}, {$regCol},
                       IFNULL(c.course_name, {$courseNameFallback}) AS course_name,
                       IFNULL(c.course_code,'') AS course_code,
                       {$schemeSelect}, {$batchSelect}
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
                {$schemeJoin}
                {$batchJoin}
                WHERE LOWER(TRIM(s.student_id)) = LOWER(?)
                ORDER BY s.id DESC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('requirementsLoadFingerprint')) {
    /**
     * @return array<string,mixed>|null
     */
    function requirementsLoadFingerprint($conn, string $studentId): ?array
    {
        if (!requirementsTableExists($conn, 'student_fingerprint_templates')) {
            return null;
        }
        $stmt = $conn->prepare(
            'SELECT id, student_id, finger_code, quality, enrolled_by, created_at, updated_at
             FROM student_fingerprint_templates
             WHERE LOWER(TRIM(student_id)) = LOWER(?)
             ORDER BY id DESC LIMIT 1'
        );
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

if (!function_exists('requirementsLoadPayments')) {
    /**
     * @return list<array<string,mixed>>
     */
    function requirementsLoadPayments($conn, string $studentId): array
    {
        if (!requirementsTableExists($conn, 'payments')) {
            return [];
        }
        $stmt = $conn->prepare('SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC, id DESC LIMIT 20');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('requirementsLoadCandidate')) {
    /**
     * @return array<string,mixed>|null
     */
    function requirementsLoadCandidate($conn, string $studentId): ?array
    {
        $studentId = trim($studentId);
        if ($studentId === '' || !($conn instanceof mysqli)) {
            return null;
        }
        $stmt = $conn->prepare(
            'SELECT s.*, IFNULL(c.course_name, IFNULL(s.course,\'\')) AS course_name,
                    IFNULL(c.course_code,\'\') AS course_code
             FROM students s
             LEFT JOIN courses c ON c.id = s.course_id
             WHERE LOWER(TRIM(s.student_id)) = LOWER(?)
             ORDER BY s.id DESC LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$student) {
            return null;
        }

        $enrollments = requirementsLoadEnrollments($conn, $studentId);
        $isDge = false;
        if (function_exists('isDgeProjectScheme')) {
            $schemeId = function_exists('normalizeEnrollmentSchemeId')
                ? normalizeEnrollmentSchemeId($student['scheme_id'] ?? null)
                : (int) ($student['scheme_id'] ?? 0);
            $isDge = isDgeProjectScheme($conn, $schemeId ?: null);
            if (!$isDge) {
                foreach ($enrollments as $en) {
                    $sid = function_exists('normalizeEnrollmentSchemeId')
                        ? normalizeEnrollmentSchemeId($en['scheme_id'] ?? null)
                        : (int) ($en['scheme_id'] ?? 0);
                    if (isDgeProjectScheme($conn, $sid ?: null)) {
                        $isDge = true;
                        break;
                    }
                }
            }
        }

        $fp = requirementsLoadFingerprint($conn, $studentId);
        $docs = requirementsEvaluateDocuments($conn, $student, $fp !== null, $isDge);

        $education = [];
        if (function_exists('fetchEducationRecordsForStudentId')) {
            $education = fetchEducationRecordsForStudentId($conn, $studentId);
        } else {
            $ed = $conn->prepare('SELECT * FROM education_details WHERE student_id = ? ORDER BY id ASC');
            if ($ed) {
                $ed->bind_param('s', $studentId);
                $ed->execute();
                $res = $ed->get_result();
                while ($row = $res->fetch_assoc()) {
                    $education[] = $row;
                }
                $ed->close();
            }
        }

        $attendance = [
            'total_classes' => 0,
            'present_count' => 0,
            'absent_count' => 0,
            'partial_count' => 0,
            'attendance_percentage' => 0.0,
            'classes_held' => 0,
        ];
        if (function_exists('getStudentPortalAttendance')) {
            $attendance = getStudentPortalAttendance($conn, $studentId);
        }

        $placements = [];
        if (function_exists('getStudentPortalPlacements')) {
            $placements = getStudentPortalPlacements($conn, $studentId);
        }

        $photoUrl = '';
        if (function_exists('biometricStudentPhotoUrl')) {
            $photoUrl = biometricStudentPhotoUrl($student);
        }
        if ($photoUrl === '' && !empty($student['passport_photo']) && requirementsDocExists((string) $student['passport_photo'])) {
            $photoUrl = requirementsDocUrl((string) $student['passport_photo']);
        }

        return [
            'student' => $student,
            'enrollments' => $enrollments,
            'education' => $education,
            'documents' => $docs,
            'fingerprint' => $fp,
            'attendance' => $attendance,
            'payments' => requirementsLoadPayments($conn, $studentId),
            'placements' => $placements,
            'is_dge' => $isDge,
            'photo_url' => $photoUrl,
        ];
    }
}

if (!function_exists('requirementsFormatDate')) {
    function requirementsFormatDate($value, string $format = 'd M Y'): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '—';
        }
        $ts = strtotime($value);
        return $ts ? date($format, $ts) : '—';
    }
}

if (!function_exists('requirementsDisplay')) {
    function requirementsDisplay($value, string $fallback = '—'): string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('requirementsStatusBadgeClass')) {
    function requirementsStatusBadgeClass(string $status): string
    {
        $status = strtolower(trim($status));
        if (in_array($status, ['active', 'approved'], true)) {
            return 'success';
        }
        if ($status === 'pending') {
            return 'warning';
        }
        if ($status === 'rejected') {
            return 'danger';
        }
        return 'secondary';
    }
}
