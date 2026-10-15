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
