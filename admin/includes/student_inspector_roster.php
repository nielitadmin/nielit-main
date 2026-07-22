<?php
/**
 * Copy batch roster to another course/scheme — Student Record Inspector.
 */

if (!function_exists('inspectorGetRosterSourceBatches')) {
    /**
     * Source batches for roster copy (Active + Completed; locked batches included).
     *
     * @return array<int, array<string, mixed>>
     */
    function inspectorGetRosterSourceBatches(mysqli $conn): array
    {
        $sql = "SELECT b.id, b.batch_name, b.batch_code, b.course_id, b.scheme_id, b.status,
                       c.course_name, c.course_code,
                       s.scheme_name, s.scheme_code,
                       (SELECT COUNT(DISTINCT COALESCE(bs.student_record_id, bs.student_id))
                        FROM batch_students bs WHERE bs.batch_id = b.id) AS enrolled_count
                FROM batches b
                LEFT JOIN courses c ON c.id = b.course_id
                LEFT JOIN schemes s ON s.id = b.scheme_id
                WHERE LOWER(TRIM(COALESCE(b.status, ''))) IN ('active', 'completed')
                ORDER BY
                    CASE LOWER(TRIM(COALESCE(b.status, '')))
                        WHEN 'active' THEN 0
                        WHEN 'completed' THEN 1
                        ELSE 2
                    END ASC,
                    c.course_name ASC,
                    b.batch_name ASC";

        $res = $conn->query($sql);
        if (!$res) {
            return [];
        }

        $batches = [];
        while ($row = $res->fetch_assoc()) {
            $batches[] = $row;
        }
        return $batches;
    }
}

if (!function_exists('inspectorFindStudentRecordId')) {
    function inspectorFindStudentRecordId(mysqli $conn, string $studentIdStr, int $courseId, ?int $schemeId): ?int
    {
        $studentIdStr = trim($studentIdStr);
        if ($studentIdStr === '' || $courseId <= 0) {
            return null;
        }

        $schemeId = normalizeEnrollmentSchemeId($schemeId);

        if (hasSchemeEnrollmentColumns($conn)) {
            if ($schemeId === null) {
                $stmt = $conn->prepare(
                    "SELECT id FROM students
                     WHERE student_id = ? AND course_id = ? AND scheme_id IS NULL
                     AND LOWER(status) NOT IN ('rejected', 'inactive')
                     LIMIT 1"
                );
                if (!$stmt) {
                    return null;
                }
                $stmt->bind_param('si', $studentIdStr, $courseId);
            } else {
                $stmt = $conn->prepare(
                    "SELECT id FROM students
                     WHERE student_id = ? AND course_id = ? AND scheme_id = ?
                     AND LOWER(status) NOT IN ('rejected', 'inactive')
                     LIMIT 1"
                );
                if (!$stmt) {
                    return null;
                }
                $stmt->bind_param('sii', $studentIdStr, $courseId, $schemeId);
            }
        } else {
            $stmt = $conn->prepare(
                "SELECT id FROM students
                 WHERE student_id = ? AND course_id = ?
                 AND LOWER(status) NOT IN ('rejected', 'inactive')
                 LIMIT 1"
            );
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('si', $studentIdStr, $courseId);
        }

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int)$row['id'] : null;
    }
}

if (!function_exists('inspectorEnsureBatchSchemeForRoster')) {
    /**
     * Legacy batches are often created without scheme_id; set it before roster assign.
     */
    function inspectorEnsureBatchSchemeForRoster(mysqli $conn, int $batchId, ?int $schemeId): bool
    {
        $schemeId = normalizeEnrollmentSchemeId($schemeId);
        if ($batchId <= 0 || $schemeId === null || !hasSchemeEnrollmentColumns($conn)) {
            return true;
        }

        $stmt = $conn->prepare('SELECT scheme_id FROM batches WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $batchId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return false;
        }

        $current = normalizeEnrollmentSchemeId($row['scheme_id'] ?? null);
        if ($current === null) {
            $upd = $conn->prepare('UPDATE batches SET scheme_id = ? WHERE id = ?');
            if (!$upd) {
                return false;
            }
            $upd->bind_param('ii', $schemeId, $batchId);
            $ok = $upd->execute();
            $upd->close();
            return $ok;
        }

        return schemeIdsMatch($current, $schemeId);
    }
}

if (!function_exists('inspectorGetTargetBatchesForRoster')) {
    /**
     * Active batches for a target course, compatible with the selected scheme.
     * Includes batches with no scheme set (common for older batches).
     *
     * @return array<int, array<string, mixed>>
     */
    function inspectorGetTargetBatchesForRoster(mysqli $conn, int $courseId, ?int $schemeId): array
    {
        if ($courseId <= 0) {
            return [];
        }

        $schemeId = normalizeEnrollmentSchemeId($schemeId);
        $hasScheme = hasSchemeEnrollmentColumns($conn);
        $courseSchemes = getSchemesForCourse($conn, $courseId);
        $courseRequiresScheme = !empty($courseSchemes);

        if ($courseRequiresScheme && $schemeId === null) {
            return [];
        }

        $sql = "SELECT b.id, b.batch_name, b.batch_code, b.seats_total, b.scheme_id,
                       s.scheme_name, s.scheme_code,
                       (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id) AS enrolled_count
                FROM batches b
                LEFT JOIN schemes s ON s.id = b.scheme_id
                WHERE b.course_id = ?
                AND LOWER(TRIM(COALESCE(b.status, ''))) = 'active'
                ORDER BY b.batch_name ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $res = $stmt->get_result();
        $all = [];
        while ($row = $res->fetch_assoc()) {
            $all[] = $row;
        }
        $stmt->close();

        if (!$hasScheme || $schemeId === null) {
            return $all;
        }

        $filtered = [];
        foreach ($all as $row) {
            $batchScheme = normalizeEnrollmentSchemeId($row['scheme_id'] ?? null);
            if ($batchScheme === null || schemeIdsMatch($batchScheme, $schemeId)) {
                $row['needs_scheme_set'] = ($batchScheme === null);
                $filtered[] = $row;
            }
        }

        return $filtered;
    }
}

if (!function_exists('inspectorGetBatchRosterPreview')) {
    /**
     * @return array{success: bool, message?: string, batch?: array, students?: array}
     */
    function inspectorGetBatchRosterPreview(mysqli $conn, int $batchId): array
    {
        if ($batchId <= 0) {
            return ['success' => false, 'message' => 'Invalid batch.'];
        }

        require_once __DIR__ . '/../../batch_module/includes/batch_functions.php';

        $batch = getBatchById($batchId, $conn);
        if (!$batch) {
            return ['success' => false, 'message' => 'Batch not found.'];
        }

        $students = getBatchStudents($batchId, $conn);
        $list = [];
        foreach ($students as $row) {
            $list[] = [
                'student_record_id' => (int)($row['id'] ?? 0),
                'student_id' => (string)($row['student_id'] ?? ''),
                'name' => (string)($row['name'] ?? ''),
                'mobile' => (string)($row['mobile'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
            ];
        }

        return [
            'success' => true,
            'batch' => [
                'id' => (int)$batch['id'],
                'batch_name' => (string)($batch['batch_name'] ?? ''),
                'batch_code' => (string)($batch['batch_code'] ?? ''),
                'course_id' => (int)($batch['course_id'] ?? 0),
                'course_name' => (string)($batch['course_name'] ?? ''),
                'scheme_id' => (int)($batch['scheme_id'] ?? 0),
                'scheme_name' => (string)($batch['scheme_name'] ?? ''),
                'student_count' => count($list),
            ],
            'students' => $list,
        ];
    }
}

if (!function_exists('inspectorCopyBatchRoster')) {
    /**
     * Enroll all students from a source batch into a target course/scheme and optionally assign to a target batch.
     *
     * @return array{success: bool, message: string, type?: string, stats?: array}
     */
    function inspectorCopyBatchRoster(
        mysqli $conn,
        int $sourceBatchId,
        int $targetCourseId,
        ?int $targetSchemeId,
        ?int $targetBatchId,
        string $adminName
    ): array {
        if (!isMultiCourseSystemInstalled($conn)) {
            return ['success' => false, 'message' => 'Multi-course system is not installed.', 'type' => 'danger'];
        }

        if ($sourceBatchId <= 0 || $targetCourseId <= 0) {
            return ['success' => false, 'message' => 'Please select a source batch and target course.', 'type' => 'warning'];
        }

        require_once __DIR__ . '/../../batch_module/includes/batch_functions.php';

        $sourceBatch = getBatchById($sourceBatchId, $conn);
        if (!$sourceBatch) {
            return ['success' => false, 'message' => 'Source batch not found.', 'type' => 'danger'];
        }

        $targetSchemeId = normalizeEnrollmentSchemeId($targetSchemeId);
        $targetBatchId = ($targetBatchId !== null && $targetBatchId > 0) ? $targetBatchId : null;

        $courseSchemes = getSchemesForCourse($conn, $targetCourseId);
        if (!empty($courseSchemes) && $targetSchemeId === null) {
            return ['success' => false, 'message' => 'Please select a scheme/project for the target course.', 'type' => 'warning'];
        }
        if ($targetSchemeId !== null && !validateSchemeForCourse($conn, $targetCourseId, $targetSchemeId)) {
            return ['success' => false, 'message' => 'Invalid scheme/project for the selected target course.', 'type' => 'danger'];
        }

        if ($targetBatchId !== null) {
            if (!inspectorEnsureBatchSchemeForRoster($conn, $targetBatchId, $targetSchemeId)) {
                return ['success' => false, 'message' => 'Target batch scheme does not match the selected scheme.', 'type' => 'danger'];
            }

            $targetBatch = getBatchByIdForEnrollment($conn, $targetBatchId);
            if (!$targetBatch) {
                return ['success' => false, 'message' => 'Target batch not found.', 'type' => 'danger'];
            }
            if ((int)$targetBatch['course_id'] !== $targetCourseId) {
                return ['success' => false, 'message' => 'Target batch does not belong to the selected course.', 'type' => 'danger'];
            }
            $batchScheme = normalizeEnrollmentSchemeId($targetBatch['scheme_id'] ?? null);
            if (hasSchemeEnrollmentColumns($conn) && ($targetSchemeId !== null || $batchScheme !== null)) {
                if (!canAssignStudentSchemeToBatch($targetSchemeId, $batchScheme)) {
                    return ['success' => false, 'message' => 'Target batch scheme does not match the selected scheme.', 'type' => 'danger'];
                }
            }
        }

        $students = getBatchStudents($sourceBatchId, $conn);
        if (empty($students)) {
            return ['success' => false, 'message' => 'Source batch has no students to copy.', 'type' => 'warning'];
        }

        $enrolled = 0;
        $assignedOnly = 0;
        $skipped = 0;
        $failed = [];

        foreach ($students as $stu) {
            $studentIdStr = trim((string)($stu['student_id'] ?? ''));
            $displayName = (string)($stu['name'] ?? $studentIdStr ?: 'Unknown');

            if ($studentIdStr === '') {
                $failed[] = $displayName . ': missing Student ID';
                continue;
            }

            $account = resolveStudentAccount($conn, $studentIdStr);
            $aadhar = $account ? normalizeAadhar((string)($account['aadhar'] ?? '')) : normalizeAadhar((string)($stu['aadhar'] ?? ''));

            if ($aadhar === '') {
                $failed[] = $displayName . ': no Aadhar on file — cannot create enrollment';
                continue;
            }

            if (isAadharEnrolledInCourseScheme($conn, $aadhar, $targetCourseId, $targetSchemeId)) {
                if ($targetBatchId !== null) {
                    $recordId = inspectorFindStudentRecordId($conn, $studentIdStr, $targetCourseId, $targetSchemeId);
                    if ($recordId) {
                        $batchResult = assignEnrollmentToBatch($conn, $recordId, $targetBatchId, $adminName);
                        if ($batchResult['success']) {
                            if (stripos($batchResult['message'], 'already') !== false) {
                                $skipped++;
                            } else {
                                $assignedOnly++;
                            }
                        } else {
                            $failed[] = $displayName . ': ' . $batchResult['message'];
                        }
                    } else {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
                continue;
            }

            $result = adminAssignCourseToStudent(
                $conn,
                $studentIdStr,
                $targetCourseId,
                $targetBatchId,
                $adminName,
                $targetSchemeId
            );

            if ($result['success']) {
                $enrolled++;
            } else {
                $failed[] = $displayName . ': ' . $result['message'];
            }
        }

        $parts = [];
        if ($enrolled > 0) {
            $parts[] = "{$enrolled} newly enrolled";
        }
        if ($assignedOnly > 0) {
            $parts[] = "{$assignedOnly} already enrolled — added to target batch";
        }
        if ($skipped > 0) {
            $parts[] = "{$skipped} skipped (already in target course/batch)";
        }
        if (!empty($failed)) {
            $parts[] = count($failed) . ' failed';
        }

        $courseStmt = $conn->prepare('SELECT course_name FROM courses WHERE id = ? LIMIT 1');
        $targetCourseName = 'course #' . $targetCourseId;
        if ($courseStmt) {
            $courseStmt->bind_param('i', $targetCourseId);
            $courseStmt->execute();
            $courseRow = $courseStmt->get_result()->fetch_assoc();
            $courseStmt->close();
            if ($courseRow) {
                $targetCourseName = (string)$courseRow['course_name'];
            }
        }

        $schemeLabel = '';
        if ($targetSchemeId) {
            $sn = $conn->prepare('SELECT scheme_name FROM schemes WHERE id = ? LIMIT 1');
            if ($sn) {
                $sn->bind_param('i', $targetSchemeId);
                $sn->execute();
                $sr = $sn->get_result()->fetch_assoc();
                $sn->close();
                if ($sr) {
                    $schemeLabel = ' (' . $sr['scheme_name'] . ')';
                }
            }
        }

        $message = 'Roster copy from "' . ($sourceBatch['batch_name'] ?? 'batch') . '" to '
            . $targetCourseName . $schemeLabel . ': ';
        $message .= !empty($parts) ? implode('; ', $parts) . '.' : 'No changes made.';

        if (!empty($failed)) {
            $message .= ' Errors: ' . implode('; ', array_slice($failed, 0, 5));
            if (count($failed) > 5) {
                $message .= ' …and ' . (count($failed) - 5) . ' more.';
            }
        }

        $type = 'success';
        if ($enrolled === 0 && $assignedOnly === 0 && !empty($failed)) {
            $type = 'danger';
        } elseif (!empty($failed) || ($enrolled === 0 && $assignedOnly === 0 && $skipped > 0)) {
            $type = 'warning';
        }

        return [
            'success' => $enrolled > 0 || $assignedOnly > 0,
            'message' => $message,
            'type' => $type,
            'stats' => [
                'enrolled' => $enrolled,
                'assigned_only' => $assignedOnly,
                'skipped' => $skipped,
                'failed' => count($failed),
            ],
        ];
    }
}

if (!function_exists('inspectorHandleRosterPost')) {
    /**
     * @return array{message: string, type: string}|null
     */
    function inspectorHandleRosterPost(mysqli $conn, string $adminRole): ?array
    {
        if ($adminRole !== 'master_admin') {
            return ['message' => 'Access denied.', 'type' => 'danger'];
        }

        if (!isset($_POST['copy_batch_roster'])) {
            return null;
        }

        $sourceBatchId = (int)($_POST['source_batch_id'] ?? 0);
        $targetCourseId = (int)($_POST['target_course_id'] ?? 0);
        $targetSchemeId = normalizeEnrollmentSchemeId($_POST['target_scheme_id'] ?? null);
        $targetBatchId = (int)($_POST['target_batch_id'] ?? 0);
        $assignToBatch = !empty($_POST['assign_to_batch']);
        $adminName = $_SESSION['admin'] ?? 'Admin';

        $result = inspectorCopyBatchRoster(
            $conn,
            $sourceBatchId,
            $targetCourseId,
            $targetSchemeId,
            $assignToBatch ? $targetBatchId : null,
            $adminName
        );

        return [
            'message' => $result['message'],
            'type' => $result['type'] ?? ($result['success'] ? 'success' : 'danger'),
        ];
    }
}
