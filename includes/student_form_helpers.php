<?php
/**
 * Helpers for per-enrollment student admission form downloads (multi-course).
 */

if (!function_exists('fetchStudentEnrollmentByRecordId')) {

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
        $stmt = $conn->prepare('SELECT id FROM courses WHERE TRIM(course_name) = ? ORDER BY id ASC LIMIT 1');
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
                  AND TRIM(s.course) != TRIM(c.course_name)
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
                INNER JOIN courses c ON TRIM(s.course) = TRIM(c.course_name)
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
                          AND TRIM(c_old.course_name) = TRIM(s.course)
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
                           AND TRIM(s.course) = TRIM({$courseNameSql})
                       )))";
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
                AND TRIM(IFNULL({$alias}.course, '')) = (SELECT TRIM(course_name) FROM courses WHERE id = ? LIMIT 1)
            )
        )";
    }

    /** Same match with a known course id inlined (stats queries). */
    function studentCourseMatchSql(string $alias, int $courseId): string
    {
        $courseId = (int) $courseId;
        if ($courseId <= 0) {
            return '1=0';
        }
        return "(
            {$alias}.course_id = {$courseId}
            OR (
                ({$alias}.course_id IS NULL OR {$alias}.course_id = 0)
                AND TRIM(IFNULL({$alias}.course, '')) = (SELECT TRIM(course_name) FROM courses WHERE id = {$courseId} LIMIT 1)
            )
        )";
    }

    /** Hide rows that were removed from a course (archive pending restore). */
    function studentPendingCourseRemovalExcludeSql(string $alias = 's'): string
    {
        return "NOT EXISTS (
            SELECT 1 FROM student_course_removals r
            WHERE r.student_id = {$alias}.student_id
              AND r.course_id = {$alias}.course_id
              AND r.restored_at IS NULL
        )";
    }

    /** OR of course matches for coordinator assigned courses. */
    function studentAssignedCoursesMatchSql(string $alias, array $courseIds): string
    {
        $parts = [];
        foreach ($courseIds as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) {
                $parts[] = studentCourseMatchSql($alias, $cid);
            }
        }
        return $parts !== [] ? '(' . implode(' OR ', $parts) . ')' : '1=0';
    }

    /** Bind types + values for studentCourseMatchPreparedSql (two ints: course id twice). */
    function studentCourseMatchBindValues(int $courseId): array
    {
        $courseId = (int) $courseId;
        return [$courseId, $courseId];
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
