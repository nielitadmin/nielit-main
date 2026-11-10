<?php
/**
 * Helpers for per-enrollment student admission form downloads (multi-course).
 */

if (!function_exists('fetchStudentEnrollmentByRecordId')) {

    /** Collation for cross-table string compares (Hostinger/MySQL 8.4 mixes uca1400 with unicode_ci). */
    function sqlUtf8mb4Collate(): string
    {
        return 'utf8mb4_unicode_ci';
    }

    function sqlTrimEqualsCollated(string $leftExpr, string $rightExpr): string
    {
        $c = sqlUtf8mb4Collate();
        return "TRIM({$leftExpr}) COLLATE {$c} = TRIM({$rightExpr}) COLLATE {$c}";
    }

    function sqlTrimNotEqualsCollated(string $leftExpr, string $rightExpr): string
    {
        $c = sqlUtf8mb4Collate();
        return "TRIM({$leftExpr}) COLLATE {$c} != TRIM({$rightExpr}) COLLATE {$c}";
    }

    function sqlStudentCourseNameMatchesCourseId(string $studentCourseExpr, string $courseIdSql): string
    {
        $c = sqlUtf8mb4Collate();
        return "TRIM({$studentCourseExpr}) COLLATE {$c} = (SELECT TRIM(course_name) COLLATE {$c} FROM courses WHERE id = {$courseIdSql} LIMIT 1)";
    }

    function studentCourseEnrollmentOrBatchExistsSql(string $alias, string $courseIdSql): string
    {
        return "(EXISTS (
                SELECT 1 FROM student_enrollments se
                WHERE se.student_record_id = {$alias}.id AND se.course_id = {$courseIdSql}
            )
            OR EXISTS (
                SELECT 1 FROM batch_students bs
                INNER JOIN batches b ON b.id = bs.batch_id
                WHERE b.course_id = {$courseIdSql}
                  AND (bs.student_record_id = {$alias}.id OR bs.student_id = {$alias}.id)
            ))";
    }

    /**
     * All courses.id values that represent the same program (duplicate rows: same name and/or course_code).
     *
     * @return int[]
     */
    function getEquivalentCourseIds(mysqli $conn, int $courseId): array
    {
        $courseId = (int) $courseId;
        if ($courseId <= 0) {
            return [];
        }
        $stmt = $conn->prepare(
            'SELECT TRIM(course_name) AS course_name, TRIM(IFNULL(course_code, "")) AS course_code FROM courses WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            return [$courseId];
        }
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return [$courseId];
        }
        $name = trim((string) ($row['course_name'] ?? ''));
        $code = trim((string) ($row['course_code'] ?? ''));
        $ids = [$courseId];
        $collate = sqlUtf8mb4Collate();
        if ($name !== '') {
            $q = $conn->prepare("SELECT id FROM courses WHERE TRIM(course_name) COLLATE {$collate} = ? COLLATE {$collate}");
            if ($q) {
                $q->bind_param('s', $name);
                $q->execute();
                $res = $q->get_result();
                while ($r = $res->fetch_assoc()) {
                    $ids[] = (int) $r['id'];
                }
                $q->close();
            }
        }
        if ($code !== '') {
            $q = $conn->prepare("SELECT id FROM courses WHERE TRIM(course_code) COLLATE {$collate} = ? COLLATE {$collate}");
            if ($q) {
                $q->bind_param('s', $code);
                $q->execute();
                $res = $q->get_result();
                while ($r = $res->fetch_assoc()) {
                    $ids[] = (int) $r['id'];
                }
                $q->close();
            }
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        sort($ids, SORT_NUMERIC);
        return $ids !== [] ? $ids : [$courseId];
    }

    /** Student row belongs to any of the given course ids (duplicate course rows included). */
    function studentCourseMatchSqlFromIds(string $alias, array $courseIds): string
    {
        $courseIds = array_values(array_unique(array_filter(array_map('intval', $courseIds), static fn (int $id): bool => $id > 0)));
        if ($courseIds === []) {
            return '1=0';
        }
        $in = implode(',', $courseIds);
        $c = sqlUtf8mb4Collate();
        $nameMatch = "(
            ({$alias}.course_id IS NULL OR {$alias}.course_id = 0)
            AND TRIM(IFNULL({$alias}.course, '')) COLLATE {$c} IN (
                SELECT TRIM(course_name) COLLATE {$c} FROM courses WHERE id IN ({$in})
            )
        )";
        $enrollment = "EXISTS (
            SELECT 1 FROM student_enrollments se
            WHERE se.student_record_id = {$alias}.id AND se.course_id IN ({$in})
        )";
        $batch = "EXISTS (
            SELECT 1 FROM batch_students bs
            INNER JOIN batches b ON b.id = bs.batch_id
            WHERE b.course_id IN ({$in})
              AND (bs.student_record_id = {$alias}.id OR bs.student_id = {$alias}.id)
        )";
        return "(
            {$alias}.course_id IN ({$in})
            OR {$nameMatch}
            OR {$enrollment}
            OR {$batch}
        )";
    }

    function studentCourseMatchSqlForFilter(mysqli $conn, string $alias, int $courseId): string
    {
        return studentCourseMatchSqlFromIds($alias, getEquivalentCourseIds($conn, $courseId));
    }

    function fetchStudentEnrollmentByRecordId(mysqli $conn, int $recordId): ?array {
        if ($recordId <= 0) {
            return null;
        }
        $sql = "SELECT s.*, c.course_name AS joined_course_name, c.course_code,
                       bc.course_name AS batch_course_name
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
                LEFT JOIN batches b ON b.id = s.batch_id
                LEFT JOIN courses bc ON bc.id = b.course_id
                WHERE s.id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $recordId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? normalizeEnrollmentRowForForm($row) : null;
    }

    function fetchStudentEnrollmentsByStudentId(mysqli $conn, string $studentId): array {
        $studentId = trim($studentId);
        if ($studentId === '') {
            return [];
        }
        $sql = "SELECT s.*, c.course_name AS joined_course_name, c.course_code,
                       bc.course_name AS batch_course_name
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
                LEFT JOIN batches b ON b.id = s.batch_id
                LEFT JOIN courses bc ON bc.id = b.course_id
                WHERE s.student_id = ?
                AND LOWER(s.status) NOT IN ('rejected', 'cancelled')
                ORDER BY s.registration_date DESC, s.id DESC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = normalizeEnrollmentRowForForm($row);
        }
        $stmt->close();
        return $rows;
    }

    function normalizeEnrollmentRowForForm(array $row): array {
        $legacy = trim((string) ($row['course'] ?? ''));
        $joined = trim((string) ($row['joined_course_name'] ?? ''));
        $batchCourse = trim((string) ($row['batch_course_name'] ?? ''));

        // Transfers via Edit Student often update the course name but leave course_id on the old course.
        if ($legacy !== '' && $joined !== '' && strcasecmp($legacy, $joined) !== 0) {
            $row['course'] = $legacy;
        } elseif ($batchCourse !== '') {
            $row['course'] = $batchCourse;
        } elseif ($joined !== '') {
            $row['course'] = $joined;
        } elseif ($legacy !== '') {
            $row['course'] = $legacy;
        }

        return $row;
    }

    function findCourseIdByName(mysqli $conn, string $courseName): int
    {
        $courseName = trim($courseName);
        if ($courseName === '') {
            return 0;
        }
        $collate = sqlUtf8mb4Collate();
        $stmt = $conn->prepare("SELECT id FROM courses WHERE TRIM(course_name) COLLATE {$collate} = ? COLLATE {$collate} ORDER BY id ASC LIMIT 1");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('s', $courseName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($row['id'])) {
            return (int) $row['id'];
        }
        $like = $courseName;
        $stmt = $conn->prepare('SELECT id FROM courses WHERE course_name LIKE ? ORDER BY id ASC LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['id'] ?? 0);
    }

    /**
     * Keep students.course_id (and student_enrollments) in sync when a student is moved to another course.
     */
    /**
     * Fix rows where students.course was updated but students.course_id was left on an old course
     * (common after Edit Student matched on student_id only).
     */
    /**
     * Reset students.course text from courses.course_name when course_id is valid but text was copied wrongly.
     */
    function repairStudentCourseTextFromCourseId(mysqli $conn, ?string $studentIdStr = null): int
    {
        $sql = "UPDATE students s
                INNER JOIN courses c ON c.id = s.course_id
                SET s.course = c.course_name
                WHERE s.course_id IS NOT NULL AND s.course_id > 0
                  AND TRIM(IFNULL(s.course, '')) != ''
                  AND " . sqlTrimNotEqualsCollated('s.course', 'c.course_name') . "
                  AND LOWER(IFNULL(s.status, '')) NOT IN ('inactive', 'rejected')";
        if ($studentIdStr !== null && $studentIdStr !== '') {
            $sql .= ' AND s.student_id = ?';
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return 0;
            }
            $stmt->bind_param('s', $studentIdStr);
            $stmt->execute();
            $n = $stmt->affected_rows;
            $stmt->close();
            return (int) $n;
        }
        $ok = $conn->query($sql);
        return $ok ? (int) $conn->affected_rows : 0;
    }

    function repairStudentsCourseIdFromCourseName(mysqli $conn, ?string $studentIdStr = null): int
    {
        $col = @$conn->query("SHOW COLUMNS FROM students LIKE 'course_id'");
        if (!$col || $col->num_rows === 0) {
            return 0;
        }
        // Never reassign a row to another course when course_id still points at a different valid course
        // (Edit Student used to copy the FDAP course name onto every enrollment).
        $sql = "UPDATE students s
                INNER JOIN courses c ON " . sqlTrimEqualsCollated('s.course', 'c.course_name') . "
                SET s.course_id = c.id
                WHERE (s.course_id IS NULL OR s.course_id = 0 OR s.course_id != c.id)
                  AND TRIM(IFNULL(s.course, '')) != ''
                  AND LOWER(IFNULL(s.status, '')) NOT IN ('inactive', 'rejected')
                  AND (
                    s.course_id IS NULL OR s.course_id = 0
                    OR NOT EXISTS (SELECT 1 FROM courses c_chk WHERE c_chk.id = s.course_id)
                    OR EXISTS (
                        SELECT 1 FROM courses c_old
                        WHERE c_old.id = s.course_id
                          AND " . sqlTrimEqualsCollated('c_old.course_name', 's.course') . "
                    )
                  )";
        if ($studentIdStr !== null && $studentIdStr !== '') {
            $sql .= ' AND s.student_id = ?';
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return 0;
            }
            $stmt->bind_param('s', $studentIdStr);
            $stmt->execute();
            $n = $stmt->affected_rows;
            $stmt->close();
            return (int) $n;
        }
        $ok = $conn->query($sql);
        return $ok ? (int) $conn->affected_rows : 0;
    }

    /**
     * Fix students.course_id / course text from student_enrollments or batch assignment when columns drifted.
     */
    function repairStudentsCourseIdFromEnrollmentLinks(mysqli $conn, ?string $studentIdStr = null): int
    {
        $fixed = 0;
        $statusFilter = "LOWER(IFNULL(s.status, '')) NOT IN ('inactive', 'rejected')";
        $studentFilter = '';
        if ($studentIdStr !== null && $studentIdStr !== '') {
            $studentFilter = ' AND s.student_id = ?';
        }

        $enrTable = @$conn->query("SHOW TABLES LIKE 'student_enrollments'");
        if ($enrTable && $enrTable->num_rows > 0) {
            $sql = "UPDATE students s
                    INNER JOIN student_enrollments se ON se.student_record_id = s.id
                    INNER JOIN courses c ON c.id = se.course_id
                    SET s.course_id = se.course_id, s.course = c.course_name
                    WHERE (s.course_id IS NULL OR s.course_id = 0 OR s.course_id != se.course_id)
                      AND {$statusFilter}{$studentFilter}";
            if ($studentFilter !== '') {
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('s', $studentIdStr);
                    $stmt->execute();
                    $fixed += (int) $stmt->affected_rows;
                    $stmt->close();
                }
            } else {
                if ($conn->query($sql)) {
                    $fixed += (int) $conn->affected_rows;
                }
            }
        }

        $bsTable = @$conn->query("SHOW TABLES LIKE 'batch_students'");
        if ($bsTable && $bsTable->num_rows > 0) {
            $sql = "UPDATE students s
                    INNER JOIN batch_students bs ON (bs.student_record_id = s.id OR bs.student_id = s.id)
                    INNER JOIN batches b ON b.id = bs.batch_id
                    INNER JOIN courses c ON c.id = b.course_id
                    SET s.course_id = b.course_id, s.course = c.course_name
                    WHERE (s.course_id IS NULL OR s.course_id = 0 OR s.course_id != b.course_id)
                      AND {$statusFilter}{$studentFilter}";
            if ($studentFilter !== '') {
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('s', $studentIdStr);
                    $stmt->execute();
                    $fixed += (int) $stmt->affected_rows;
                    $stmt->close();
                }
            } else {
                if ($conn->query($sql)) {
                    $fixed += (int) $conn->affected_rows;
                }
            }
        }

        return $fixed;
    }

    /**
     * SQL subquery: distinct students for one course (course_id match, else course name text).
     *
     * @param string $courseIdSql e.g. courses.id or c.id
     * @param string $courseNameSql e.g. courses.course_name or c.course_name
     */
    function sqlSubqueryDistinctStudentsPerCourse(string $courseIdSql, string $courseNameSql): string
    {
        $pending = function_exists('studentPendingCourseRemovalExcludeSql')
            ? ' AND ' . studentPendingCourseRemovalExcludeSql('s')
            : '';
        return "(SELECT COUNT(DISTINCT s.student_id) FROM students s
                WHERE LOWER(IFNULL(s.status, '')) NOT IN ('inactive', 'rejected'){$pending}
                  AND (s.course_id = {$courseIdSql}
                       OR (
                           (s.course_id IS NULL OR s.course_id = 0)
                           AND TRIM(IFNULL(s.course, '')) != ''
                           AND " . sqlTrimEqualsCollated('s.course', $courseNameSql) . "
                       )
                       OR " . studentCourseEnrollmentOrBatchExistsSql('s', $courseIdSql) . "
                       ))";
    }

    /**
     * Prepared statement fragment: student row belongs to one courses.id (id + name fallback).
     */
    function studentCourseMatchPreparedSql(string $alias = 's'): string
    {
        return "(
            {$alias}.course_id = ?
            OR (
                ({$alias}.course_id IS NULL OR {$alias}.course_id = 0)
                AND " . sqlStudentCourseNameMatchesCourseId("IFNULL({$alias}.course, '')", '?') . "
            )
            OR " . studentCourseEnrollmentOrBatchExistsSql($alias, '?') . "
        )";
    }

    /** Same match with a known course id inlined (stats queries). Pass $conn to include duplicate course rows. */
    function studentCourseMatchSql(string $alias, int $courseId, ?mysqli $conn = null): string
    {
        $courseId = (int) $courseId;
        if ($courseId <= 0) {
            return '1=0';
        }
        if ($conn instanceof mysqli) {
            return studentCourseMatchSqlForFilter($conn, $alias, $courseId);
        }
        return studentCourseMatchSqlFromIds($alias, [$courseId]);
    }

    /** Exclude students removed from a specific course (archive), regardless of row course_id drift. */
    function studentPendingCourseRemovalForCourseSql(string $alias, string $courseIdSql): string
    {
        $c = sqlUtf8mb4Collate();
        return "NOT EXISTS (
            SELECT 1 FROM student_course_removals r
            WHERE r.student_id COLLATE {$c} = {$alias}.student_id COLLATE {$c}
              AND r.course_id = {$courseIdSql}
              AND r.restored_at IS NULL
        )";
    }

    /**
     * Drop pending removal archives when an active students row still exists (stale after edit/restore).
     */
    function repairStaleCourseRemovalArchives(mysqli $conn): int
    {
        if (!function_exists('ensureStudentCourseRemovalArchive') || !ensureStudentCourseRemovalArchive($conn)) {
            return 0;
        }
        $c = sqlUtf8mb4Collate();
        $sql = "DELETE r FROM student_course_removals r
                INNER JOIN students s
                  ON s.student_id COLLATE {$c} = r.student_id COLLATE {$c}
                 AND s.course_id = r.course_id
                WHERE r.restored_at IS NULL
                  AND LOWER(IFNULL(s.status, '')) NOT IN ('inactive', 'rejected')";
        $ok = $conn->query($sql);
        if (!$ok) {
            return 0;
        }
        $n = (int) $conn->affected_rows;
        $enrTable = @$conn->query("SHOW TABLES LIKE 'student_enrollments'");
        if ($enrTable && $enrTable->num_rows > 0) {
        $sql2 = "DELETE r FROM student_course_removals r
                 INNER JOIN student_enrollments se ON se.course_id = r.course_id
                 INNER JOIN students s ON s.id = se.student_record_id
                   AND s.student_id COLLATE {$c} = r.student_id COLLATE {$c}
                 WHERE r.restored_at IS NULL
                   AND LOWER(IFNULL(s.status, '')) NOT IN ('inactive', 'rejected')
                   AND LOWER(IFNULL(se.status, '')) NOT IN ('inactive', 'rejected', 'cancelled')";
        $ok2 = $conn->query($sql2);
        if ($ok2) {
            $n += (int) $conn->affected_rows;
        }
        }
        $sql3 = "DELETE r FROM student_course_removals r
                 INNER JOIN students s ON s.student_id COLLATE {$c} = r.student_id COLLATE {$c}
                 INNER JOIN courses cr ON cr.id = r.course_id
                 INNER JOIN courses cs ON cs.id = s.course_id
                 WHERE r.restored_at IS NULL
                   AND LOWER(IFNULL(s.status, '')) NOT IN ('inactive', 'rejected')
                   AND (
                       " . sqlTrimEqualsCollated('cr.course_name', 'cs.course_name') . "
                       OR (
                           TRIM(IFNULL(cr.course_code, '')) != ''
                           AND " . sqlTrimEqualsCollated('cr.course_code', 'cs.course_code') . "
                       )
                   )";
        $ok3 = $conn->query($sql3);
        if ($ok3) {
            $n += (int) $conn->affected_rows;
        }
        return $n;
    }

    /** Pending removal for any duplicate course id when filtering by one courses.id. */
    function studentPendingCourseRemovalExcludeSqlForCourseFilter(mysqli $conn, string $alias, int $filterCourseId): string
    {
        $ids = getEquivalentCourseIds($conn, $filterCourseId);
        if ($ids === []) {
            return studentPendingCourseRemovalExcludeSql($alias);
        }
        $in = implode(',', $ids);
        $c = sqlUtf8mb4Collate();
        return "NOT EXISTS (
            SELECT 1 FROM student_course_removals r
            WHERE r.student_id COLLATE {$c} = {$alias}.student_id COLLATE {$c}
              AND r.course_id IN ({$in})
              AND r.restored_at IS NULL
        )";
    }

    /** Hide rows that were removed from a course (archive pending restore). */
    function studentPendingCourseRemovalExcludeSql(string $alias = 's'): string
    {
        $c = sqlUtf8mb4Collate();
        return "NOT EXISTS (
            SELECT 1 FROM student_course_removals r
            WHERE r.student_id COLLATE {$c} = {$alias}.student_id COLLATE {$c}
              AND r.course_id = {$alias}.course_id
              AND r.restored_at IS NULL
        )";
    }

    /** OR of course matches for coordinator assigned courses. */
    function studentAssignedCoursesMatchSql(string $alias, array $courseIds, ?mysqli $conn = null): string
    {
        if ($conn instanceof mysqli) {
            $merged = [];
            foreach ($courseIds as $cid) {
                $merged = array_merge($merged, getEquivalentCourseIds($conn, (int) $cid));
            }
            $merged = array_values(array_unique(array_filter(array_map('intval', $merged), static fn (int $id): bool => $id > 0)));
            return studentCourseMatchSqlFromIds($alias, $merged);
        }
        $parts = [];
        foreach ($courseIds as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) {
                $parts[] = studentCourseMatchSql($alias, $cid);
            }
        }
        return $parts !== [] ? '(' . implode(' OR ', $parts) . ')' : '1=0';
    }

    /** Bind types + values for studentCourseMatchPreparedSql (course id repeated per ? placeholder). */
    function studentCourseMatchBindValues(int $courseId): array
    {
        $courseId = (int) $courseId;
        return [$courseId, $courseId, $courseId, $courseId];
    }

    /**
     * Build students.php query string after edit, keeping list filters and course context.
     */
    function studentsListReturnQueryFromEdit(array $source, int $fallbackCourseId = 0): string
    {
        $params = [];
        $map = [
            'filter_course' => ['list_filter_course', 'filter_course'],
            'filter_status' => ['list_filter_status', 'filter_status'],
            'filter_gender' => ['list_filter_gender', 'filter_gender'],
            'filter_scheme' => ['list_filter_scheme', 'filter_scheme'],
            'start_date' => ['list_start_date', 'start_date'],
            'end_date' => ['list_end_date', 'end_date'],
            'page' => ['list_page', 'page'],
            'per_page' => ['list_per_page', 'per_page'],
        ];
        foreach ($map as $paramKey => $sourceKeys) {
            $val = '';
            foreach ($sourceKeys as $sk) {
                if (isset($source[$sk]) && $source[$sk] !== '') {
                    $val = $source[$sk];
                    break;
                }
            }
            if ($val !== '' && $val !== 'All') {
                $params[$paramKey] = $val;
            }
        }
        if (empty($params['filter_course']) && $fallbackCourseId > 0) {
            $params['filter_course'] = $fallbackCourseId;
        }
        if (!empty($source['list_filter_category']) && is_array($source['list_filter_category'])) {
            $params['filter_category'] = $source['list_filter_category'];
        }
        return $params === [] ? '' : '?' . http_build_query($params);
    }

    function syncStudentRecordCourse(mysqli $conn, int $recordId, string $courseName): bool
    {
        $recordId = (int) $recordId;
        $courseName = trim($courseName);
        if ($recordId <= 0 || $courseName === '') {
            return false;
        }
        $courseId = findCourseIdByName($conn, $courseName);
        $hasCourseId = false;
        $col = @$conn->query("SHOW COLUMNS FROM students LIKE 'course_id'");
        if ($col && $col->num_rows > 0) {
            $hasCourseId = true;
        }
        if ($hasCourseId && $courseId > 0) {
            $stmt = $conn->prepare('UPDATE students SET course = ?, course_id = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('sii', $courseName, $courseId, $recordId);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare('UPDATE students SET course = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $courseName, $recordId);
                $stmt->execute();
                $stmt->close();
            }
        }
        if ($courseId > 0) {
            $enrTable = @$conn->query("SHOW TABLES LIKE 'student_enrollments'");
            if ($enrTable && $enrTable->num_rows > 0) {
                $enr = $conn->prepare('UPDATE student_enrollments SET course_id = ? WHERE student_record_id = ?');
                if ($enr) {
                    $enr->bind_param('ii', $courseId, $recordId);
                    $enr->execute();
                    $enr->close();
                }
            }
        }
        return true;
    }

    function resolveStudentEnrollmentForForm(mysqli $conn, array $params): ?array {
        if (!empty($params['record_id'])) {
            return fetchStudentEnrollmentByRecordId($conn, (int)$params['record_id']);
        }

        $studentId = trim($params['id'] ?? $params['student_id'] ?? '');
        $courseId = (int)($params['course_id'] ?? 0);
        if ($studentId === '') {
            return null;
        }

        if ($courseId > 0) {
            $sql = "SELECT s.*, c.course_name AS joined_course_name, c.course_code,
                           bc.course_name AS batch_course_name
                    FROM students s
                    LEFT JOIN courses c ON c.id = s.course_id
                    LEFT JOIN batches b ON b.id = s.batch_id
                    LEFT JOIN courses bc ON bc.id = b.course_id
                    WHERE s.student_id = ? AND s.course_id = ?
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('si', $studentId, $courseId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    return normalizeEnrollmentRowForForm($row);
                }
            }
        }

        $enrollments = fetchStudentEnrollmentsByStudentId($conn, $studentId);
        return $enrollments[0] ?? null;
    }

    function fetchEducationRecordsForStudentId(mysqli $conn, string $studentId): array {
        $records = [];
        $sql = 'SELECT * FROM education_details WHERE student_id = ? ORDER BY id ASC';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $records;
        }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $records[] = $row;
        }
        $stmt->close();
        return $records;
    }

    function buildAdmissionFormFilename(array $student): string {
        $sid = preg_replace('/[^A-Za-z0-9\-]/', '_', $student['student_id'] ?? 'student');
        $course = $student['course'] ?? ($student['joined_course_name'] ?? 'course');
        $courseSlug = preg_replace('/[^A-Za-z0-9\-]/', '_', substr($course, 0, 40));
        return 'NIELIT_Admission_Form_' . $sid . '_' . $courseSlug . '.pdf';
    }

    function enrollmentBelongsToStudentId(array $enrollment, string $studentId): bool {
        return trim($enrollment['student_id'] ?? '') === trim($studentId);
    }

    /**
     * When a form is opened from a batch, print that batch's course.
     * A transfer can leave the old course name (for example O-Level) on the student row.
     */
    function applyBatchCourseToAdmissionForm(mysqli $conn, array $student, int $batchId): array
    {
        if ($batchId <= 0) {
            return $student;
        }
        $stmt = $conn->prepare(
            'SELECT c.course_name
             FROM batches b
             LEFT JOIN courses c ON c.id = b.course_id
             WHERE b.id = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return $student;
        }
        $stmt->bind_param('i', $batchId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $courseName = trim((string) ($row['course_name'] ?? ''));
        if ($courseName !== '') {
            $student['course'] = $courseName;
            $student['joined_course_name'] = $courseName;
            $student['batch_course_name'] = $courseName;
        }
        return $student;
    }
}
