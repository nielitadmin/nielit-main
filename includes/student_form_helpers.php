<?php
/**
 * Helpers for per-enrollment student admission form downloads (multi-course).
 */

if (!function_exists('fetchStudentEnrollmentByRecordId')) {

    function fetchStudentEnrollmentByRecordId(mysqli $conn, int $recordId): ?array {
        if ($recordId <= 0) {
            return null;
        }
        $sql = "SELECT s.*, c.course_name AS joined_course_name, c.course_code
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
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
        $sql = "SELECT s.*, c.course_name AS joined_course_name, c.course_code
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
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
        if (!empty($row['joined_course_name'])) {
            $row['course'] = $row['joined_course_name'];
        }
        return $row;
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
            $sql = "SELECT s.*, c.course_name AS joined_course_name, c.course_code
                    FROM students s
                    LEFT JOIN courses c ON c.id = s.course_id
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
}
