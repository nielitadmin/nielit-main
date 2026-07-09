<?php
/**
 * Assign course & manage schemes — used by Student Record Inspector.
 */

if (!function_exists('inspectorGetCoursesForAssign')) {
    function inspectorGetCoursesForAssign(mysqli $conn): array
    {
        $courses = [];
        $res = $conn->query('SELECT id, course_name, course_code FROM courses ORDER BY course_name');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $courses[] = $row;
            }
        }
        return $courses;
    }
}

if (!function_exists('inspectorBuildEnrollmentContext')) {
    /**
     * @param array<int, array<string, mixed>> $studentRows
     * @param array<int, array<string, mixed>> $hiddenStudentRows
     * @param array<int, array<string, mixed>> $accountRows
     * @param array<int, array<string, mixed>> $enrollmentRows
     * @param array<string, array<int, array<string, mixed>>> $relatedRecords
     */
    function inspectorBuildEnrollmentContext(
        mysqli $conn,
        array $studentRows,
        array $hiddenStudentRows,
        array $accountRows,
        array $enrollmentRows,
        array $relatedRecords
    ): array {
        $primaryStudentId = '';
        $primaryName = '';
        $items = [];
        $seen = [];

        $allStudents = array_merge($studentRows, $hiddenStudentRows, $relatedRecords['students_all'] ?? []);
        foreach ($allStudents as $row) {
            if ($primaryStudentId === '' && !empty($row['student_id'])) {
                $primaryStudentId = (string)$row['student_id'];
                $primaryName = (string)($row['name'] ?? '');
            }
            $courseId = (int)($row['course_id'] ?? 0);
            $recordId = (int)($row['id'] ?? 0);
            if ($courseId <= 0 || $recordId <= 0) {
                continue;
            }
            $sid = (string)($row['student_id'] ?? $primaryStudentId);
            $key = $sid . ':' . $courseId . ':' . (int)($row['scheme_id'] ?? 0);
            if (isset($seen[$key])) {
                $existingIdx = $seen[$key];
                if ($recordId > (int)($items[$existingIdx]['student_record_id'] ?? 0)) {
                    $linkedSchemes = getSchemesForCourse($conn, $courseId);
                    $items[$existingIdx] = [
                        'student_id' => $sid,
                        'student_record_id' => $recordId,
                        'course_id' => $courseId,
                        'course_name' => (string)($row['course_name'] ?? ('Course #' . $courseId)),
                        'scheme_id' => (int)($row['scheme_id'] ?? 0),
                        'has_linked_schemes' => !empty($linkedSchemes),
                        'status' => (string)($row['status'] ?? ''),
                    ];
                }
                continue;
            }
            $seen[$key] = count($items);
            $linkedSchemes = getSchemesForCourse($conn, $courseId);
            $items[] = [
                'student_id' => (string)$row['student_id'],
                'student_record_id' => $recordId,
                'course_id' => $courseId,
                'course_name' => (string)($row['course_name'] ?? ('Course #' . $courseId)),
                'scheme_id' => (int)($row['scheme_id'] ?? 0),
                'has_linked_schemes' => !empty($linkedSchemes),
                'status' => (string)($row['status'] ?? ''),
            ];
        }

        if ($primaryStudentId === '' && !empty($accountRows)) {
            $primaryStudentId = (string)($accountRows[0]['student_id'] ?? '');
            $primaryName = (string)($accountRows[0]['name'] ?? '');
        }

        foreach (array_merge($enrollmentRows, $relatedRecords['enrollments_all'] ?? []) as $row) {
            if ($primaryStudentId === '' && !empty($row['student_id'])) {
                $primaryStudentId = (string)$row['student_id'];
                $primaryName = (string)($row['name'] ?? '');
            }
            $courseId = (int)($row['course_id'] ?? 0);
            $recordId = (int)($row['student_record_id'] ?? 0);
            if ($courseId <= 0) {
                continue;
            }
            $sid = (string)($row['student_id'] ?? $primaryStudentId);
            $schemeKey = (int)($row['scheme_id'] ?? 0);
            $key = $sid . ':' . $courseId . ':' . $schemeKey;
            if (isset($seen[$key])) {
                $existingIdx = $seen[$key];
                if ($recordId > (int)($items[$existingIdx]['student_record_id'] ?? 0)) {
                    $linkedSchemes = getSchemesForCourse($conn, $courseId);
                    $items[$existingIdx] = [
                        'student_id' => $sid,
                        'student_record_id' => $recordId,
                        'course_id' => $courseId,
                        'course_name' => (string)($row['course_name'] ?? ('Course #' . $courseId)),
                        'scheme_id' => $schemeKey,
                        'has_linked_schemes' => !empty($linkedSchemes),
                        'status' => (string)($row['status'] ?? 'orphan'),
                        'is_orphan' => $recordId <= 0,
                    ];
                }
                continue;
            }
            $seen[$key] = count($items);
            $linkedSchemes = getSchemesForCourse($conn, $courseId);
            $items[] = [
                'student_id' => $sid,
                'student_record_id' => $recordId,
                'course_id' => $courseId,
                'course_name' => (string)($row['course_name'] ?? ('Course #' . $courseId)),
                'scheme_id' => 0,
                'has_linked_schemes' => !empty($linkedSchemes),
                'status' => (string)($row['status'] ?? 'orphan'),
                'is_orphan' => $recordId <= 0,
            ];
        }

        return [
            'primary_student_id' => $primaryStudentId,
            'primary_name' => $primaryName,
            'course_items' => array_values($items),
        ];
    }
}

if (!function_exists('inspectorHandleEnrollmentPost')) {
    /**
     * @return array{message: string, type: string}|null
     */
    function inspectorHandleEnrollmentPost(mysqli $conn, string $adminRole): ?array
    {
        if ($adminRole === 'front_office_desk') {
            return ['message' => 'Access denied. Front Office Desk cannot manage enrollments here.', 'type' => 'danger'];
        }

        $adminName = $_SESSION['admin'] ?? 'Admin';

        if (isset($_POST['assign_course'])) {
            $studentIdStr = trim($_POST['student_id'] ?? '');
            $courseId = (int)($_POST['course_id'] ?? 0);
            $schemeId = normalizeEnrollmentSchemeId($_POST['scheme_id'] ?? null);

            if ($studentIdStr === '' || $courseId <= 0) {
                return ['message' => 'Please select a student and a course.', 'type' => 'warning'];
            }

            $result = adminAssignCourseToStudent($conn, $studentIdStr, $courseId, null, $adminName, $schemeId);
            return [
                'message' => $result['message'],
                'type' => $result['success'] ? 'success' : 'danger',
            ];
        }

        if (isset($_POST['assign_course_bulk'])) {
            $studentIds = $_POST['student_ids'] ?? [];
            if (!is_array($studentIds)) {
                $studentIds = array_filter(array_map('trim', explode(',', (string)$studentIds)));
            }
            $studentIds = array_values(array_unique(array_filter(array_map('trim', $studentIds))));
            $courseId = (int)($_POST['course_id'] ?? 0);
            $schemeId = normalizeEnrollmentSchemeId($_POST['scheme_id'] ?? null);

            if ($studentIds === [] || $courseId <= 0) {
                return ['message' => 'Please select at least one student and a course.', 'type' => 'warning'];
            }

            $assigned = 0;
            $skipped = 0;
            $failed = [];

            foreach ($studentIds as $studentIdStr) {
                $result = adminAssignCourseToStudent($conn, $studentIdStr, $courseId, null, $adminName, $schemeId);
                if ($result['success']) {
                    $assigned++;
                    continue;
                }
                $msg = (string)($result['message'] ?? 'Assignment failed.');
                if (stripos($msg, 'already enrolled') !== false) {
                    $skipped++;
                    continue;
                }
                $failed[] = $studentIdStr . ': ' . $msg;
            }

            $parts = [];
            if ($assigned > 0) {
                $parts[] = $assigned . ' student(s) assigned (status: Pending)';
            }
            if ($skipped > 0) {
                $parts[] = $skipped . ' already enrolled — skipped';
            }
            if ($failed !== []) {
                $parts[] = count($failed) . ' failed';
            }

            $message = $parts !== [] ? implode('. ', $parts) . '.' : 'No students were assigned.';
            if ($failed !== []) {
                $message .= ' ' . implode(' | ', array_slice($failed, 0, 3));
                if (count($failed) > 3) {
                    $message .= ' …';
                }
            }

            $type = $assigned > 0 ? 'success' : ($failed !== [] ? 'danger' : 'warning');
            return ['message' => $message, 'type' => $type];
        }

        if (isset($_POST['assign_scheme'])) {
            $studentRecordId = (int)($_POST['student_record_id'] ?? 0);
            $schemeId = normalizeEnrollmentSchemeId($_POST['scheme_id'] ?? null);

            if ($studentRecordId <= 0) {
                return ['message' => 'Invalid student record.', 'type' => 'warning'];
            }

            $result = adminUpdateStudentScheme($conn, $studentRecordId, $schemeId);
            return [
                'message' => $result['message'],
                'type' => $result['success'] ? 'success' : 'danger',
            ];
        }

        if (isset($_POST['sync_student_schemes'])) {
            $studentIdStr = trim($_POST['student_id'] ?? '');
            $courseId = (int)($_POST['course_id'] ?? 0);
            $schemeIds = $_POST['scheme_ids'] ?? [];

            if ($studentIdStr === '' || $courseId <= 0) {
                return ['message' => 'Invalid student or course.', 'type' => 'warning'];
            }

            $result = adminSyncStudentSchemes(
                $conn,
                $studentIdStr,
                $courseId,
                is_array($schemeIds) ? $schemeIds : [],
                $adminName
            );

            return [
                'message' => $result['message'],
                'type' => !empty($result['warning']) ? 'warning' : ($result['success'] ? 'success' : 'danger'),
            ];
        }

        if (isset($_POST['cleanup_orphan_schemes'])) {
            $studentIdStr = trim($_POST['student_id'] ?? '');
            $courseId = (int)($_POST['course_id'] ?? 0);

            if ($studentIdStr === '' || $courseId <= 0) {
                return ['message' => 'Invalid student or course.', 'type' => 'warning'];
            }

            $removed = cleanupOrphanSchemeEnrollments($conn, $studentIdStr, $courseId);
            return [
                'message' => $removed > 0
                    ? "Removed {$removed} empty duplicate enrollment row(s)."
                    : 'No empty duplicate rows to remove.',
                'type' => 'success',
            ];
        }

        return null;
    }
}
