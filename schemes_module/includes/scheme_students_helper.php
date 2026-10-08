<?php
/**
 * Scheme student totals from batch roster (batch_students + students.batch_id).
 */

if (!function_exists('scheme_has_batch_students_table')) {
    function scheme_has_batch_students_table($conn) {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        if (!($conn instanceof mysqli)) {
            $cached = false;
            return false;
        }
        $check = @$conn->query("SHOW TABLES LIKE 'batch_students'");
        $cached = ($check && $check->num_rows > 0);
        return $cached;
    }

    function schemeStudentStatusFilter($alias = 'st') {
        return "LOWER(COALESCE({$alias}.status, '')) NOT IN ('rejected', 'inactive')";
    }

    function getSchemeStudentCountSubquery($conn) {
        $exists = '';
        if (scheme_has_batch_students_table($conn)) {
            $exists = " OR EXISTS (
                SELECT 1 FROM batch_students bs
                WHERE bs.batch_id = b.id
                  AND (bs.student_record_id = st.id OR bs.student_id = st.id)
            )";
        }
        return "(SELECT COUNT(DISTINCT st.id)
                 FROM batches b
                 INNER JOIN students st ON (st.batch_id = b.id{$exists})
                 WHERE b.scheme_id = s.id
                   AND " . schemeStudentStatusFilter('st') . ")";
    }

    function getSchemeBatchesWithStudents($conn, $scheme_id) {
        require_once __DIR__ . '/../../batch_module/includes/batch_functions.php';
        $scheme_id = (int) $scheme_id;
        $rows = [];
        if ($scheme_id <= 0) {
            return $rows;
        }

        $sql = "SELECT b.id, b.batch_name, b.batch_code, b.status, b.start_date, b.end_date,
                       b.course_id, b.seats_total, c.course_name, c.course_code
                FROM batches b
                LEFT JOIN courses c ON c.id = b.course_id
                WHERE b.scheme_id = ?
                ORDER BY c.course_name ASC, b.batch_name ASC, b.id DESC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $rows;
        }
        $stmt->bind_param('i', $scheme_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $batchId = (int) ($row['id'] ?? 0);
            $row['student_count'] = $batchId > 0 ? getBatchEnrolledCount($batchId, $conn) : 0;
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    function getSchemeStudentsFromBatches($conn, $scheme_id, $batch_id = 0, $course_id = 0) {
        require_once __DIR__ . '/../../batch_module/includes/batch_functions.php';
        $scheme_id = (int) $scheme_id;
        $batch_id = (int) $batch_id;
        $course_id = (int) $course_id;
        if ($scheme_id <= 0) {
            return [];
        }

        $batches = getSchemeBatchesWithStudents($conn, $scheme_id);
        $students = [];
        $seenByBatch = [];

        foreach ($batches as $batch) {
            $thisBatchId = (int) $batch['id'];
            if ($batch_id > 0 && $thisBatchId !== $batch_id) {
                continue;
            }
            $batchCourseId = (int) ($batch['course_id'] ?? 0);
            if ($course_id > 0 && $batchCourseId !== $course_id) {
                continue;
            }
            foreach (getBatchStudents($thisBatchId, $conn) as $student) {
                $recordId = (int) ($student['id'] ?? 0);
                $key = $thisBatchId . ':' . $recordId;
                if ($recordId <= 0 || isset($seenByBatch[$key])) {
                    continue;
                }
                $seenByBatch[$key] = true;
                $studentCourseId = $batchCourseId > 0 ? $batchCourseId : (int) ($student['course_id'] ?? 0);
                if ($course_id > 0 && $studentCourseId !== $course_id) {
                    continue;
                }
                $students[] = [
                    'id' => $recordId,
                    'student_id' => $student['student_id'] ?? '',
                    'name' => $student['name'] ?? '',
                    'email' => $student['email'] ?? '',
                    'mobile' => $student['mobile'] ?? '',
                    'status' => $student['status'] ?? '',
                    'course_id' => $studentCourseId,
                    'course_name' => $batch['course_name'] ?? ($student['course_name'] ?? 'Course'),
                    'batch_id' => $thisBatchId,
                    'batch_name' => $batch['batch_name'] ?? '',
                    'batch_code' => $batch['batch_code'] ?? '',
                ];
            }
        }

        return $students;
    }

    function getSchemeCoursesWithStudents($conn, $scheme_id) {
        $scheme_id = (int) $scheme_id;
        $grouped = [];
        foreach (getSchemeStudentsFromBatches($conn, $scheme_id) as $student) {
            $courseId = (int) ($student['course_id'] ?? 0);
            $label = trim((string) ($student['course_name'] ?? ''));
            if ($label === '') {
                $label = 'Unassigned course';
            }
            if (!isset($grouped[$courseId])) {
                $grouped[$courseId] = [
                    'course_id' => $courseId,
                    'course_name' => $label,
                    'student_count' => 0,
                    'batch_ids' => [],
                    'unique_students' => [],
                ];
            }
            $recordId = (int) $student['id'];
            $grouped[$courseId]['unique_students'][$recordId] = true;
            $grouped[$courseId]['batch_ids'][(int) $student['batch_id']] = true;
        }

        $rows = [];
        foreach ($grouped as $row) {
            $row['student_count'] = count($row['unique_students']);
            $row['batch_count'] = count($row['batch_ids']);
            unset($row['unique_students'], $row['batch_ids']);
            $rows[] = $row;
        }
        usort($rows, static function ($a, $b) {
            return strcasecmp((string) $a['course_name'], (string) $b['course_name']);
        });
        return $rows;
    }

    function countUniqueSchemeStudents($conn, $scheme_id) {
        $seen = [];
        foreach (getSchemeStudentsFromBatches($conn, $scheme_id) as $student) {
            $seen[(int) $student['id']] = true;
        }
        return count($seen);
    }
}
