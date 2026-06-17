<?php
/**
 * Multi-Course Student System Helper
 * One Aadhar = One Student ID = Many course enrollments = Many batches
 */

if (!function_exists('isMultiCourseSystemInstalled')) {

    function isMultiCourseSystemInstalled(mysqli $conn): bool {
        static $installed = null;
        if ($installed !== null) {
            return $installed;
        }
        $r = $conn->query("SHOW TABLES LIKE 'student_accounts'");
        $installed = ($r && $r->num_rows > 0);
        return $installed;
    }

    function normalizeAadhar(string $aadhar): string {
        return preg_replace('/\D/', '', trim($aadhar));
    }

    function getDefaultCentreCode(): string {
        return defined('STUDENT_ID_CENTRE_CODE') ? STUDENT_ID_CENTRE_CODE : 'BBSR';
    }

    /**
     * Generate institute-wide ID: NIELIT/2026/BBSR/0001
     */
    function generateGlobalStudentID(mysqli $conn, string $centreCode = null): ?string {
        $centreCode = strtoupper($centreCode ?: getDefaultCentreCode());
        $year = date('Y');
        $prefix = "NIELIT/{$year}/{$centreCode}/";

        $maxSeq = 0;

        if (isMultiCourseSystemInstalled($conn)) {
            $stmt = $conn->prepare("SELECT student_id FROM student_accounts WHERE student_id LIKE ? ORDER BY student_id DESC LIMIT 1");
            if ($stmt) {
                $like = $prefix . '%';
                $stmt->bind_param('s', $like);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $parts = explode('/', $row['student_id']);
                    $maxSeq = max($maxSeq, (int)end($parts));
                }
                $stmt->close();
            }
        }

        $stmt2 = $conn->prepare("SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY student_id DESC LIMIT 1");
        if ($stmt2) {
            $like = $prefix . '%';
            $stmt2->bind_param('s', $like);
            $stmt2->execute();
            $res = $stmt2->get_result();
            if ($row = $res->fetch_assoc()) {
                $parts = explode('/', $row['student_id']);
                $maxSeq = max($maxSeq, (int)end($parts));
            }
            $stmt2->close();
        }

        $next = $maxSeq + 1;
        if ($next <= 9999) {
            return sprintf('%s%04d', $prefix, $next);
        }
        return sprintf('%s%05d', $prefix, $next);
    }

    function globalStudentIDExists(mysqli $conn, string $studentId): bool {
        if (isMultiCourseSystemInstalled($conn)) {
            $stmt = $conn->prepare('SELECT id FROM student_accounts WHERE student_id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $studentId);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $stmt->close();
                    return true;
                }
                $stmt->close();
            }
        }
        $stmt = $conn->prepare('SELECT id FROM students WHERE student_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    function getNextGlobalStudentID(mysqli $conn, int $maxRetries = 5): ?string {
        for ($i = 0; $i < $maxRetries; $i++) {
            $id = generateGlobalStudentID($conn);
            if ($id && !globalStudentIDExists($conn, $id)) {
                return $id;
            }
            usleep(100000);
        }
        return null;
    }

    function findAccountByAadhar(mysqli $conn, string $aadhar): ?array {
        $aadhar = normalizeAadhar($aadhar);
        if ($aadhar === '') {
            return null;
        }

        if (isMultiCourseSystemInstalled($conn)) {
            $stmt = $conn->prepare('SELECT * FROM student_accounts WHERE aadhar = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $aadhar);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    return $row;
                }
            }
        }

        $stmt = $conn->prepare("SELECT * FROM students WHERE REPLACE(REPLACE(aadhar,' ',''),'-','') = ? ORDER BY id ASC LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $aadhar);
        $stmt->execute();
        $legacy = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$legacy) {
            return null;
        }

        return [
            'id' => null,
            'student_id' => $legacy['student_id'],
            'aadhar' => $aadhar,
            'name' => $legacy['name'],
            'email' => $legacy['email'],
            'mobile' => $legacy['mobile'],
            'password' => $legacy['password'],
            'legacy_only' => true,
        ];
    }

    function hasSchemeEnrollmentColumns(mysqli $conn): bool {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        $r = $conn->query("SHOW COLUMNS FROM students LIKE 'scheme_id'");
        $has = ($r && $r->num_rows > 0);
        return $has;
    }

    function normalizeEnrollmentSchemeId($schemeId): ?int {
        $id = (int)$schemeId;
        return $id > 0 ? $id : null;
    }

    function getSchemesForCourse(mysqli $conn, int $courseId): array {
        if ($courseId <= 0) {
            return [];
        }
        $table = $conn->query("SHOW TABLES LIKE 'course_schemes'");
        if (!$table || $table->num_rows === 0) {
            return [];
        }
        $sql = "SELECT s.id, s.scheme_name, s.scheme_code, s.status
                FROM schemes s
                INNER JOIN course_schemes cs ON cs.scheme_id = s.id
                WHERE cs.course_id = ?
                AND LOWER(s.status) = 'active'
                ORDER BY s.scheme_name ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    function validateSchemeForCourse(mysqli $conn, int $courseId, ?int $schemeId): bool {
        if ($schemeId === null || $schemeId <= 0) {
            return true;
        }
        $schemes = getSchemesForCourse($conn, $courseId);
        foreach ($schemes as $s) {
            if ((int)$s['id'] === $schemeId) {
                return true;
            }
        }
        return false;
    }

    function schemeIdsMatch(?int $a, ?int $b): bool {
        return ($a === null && $b === null) || ($a !== null && $b !== null && $a === $b);
    }

    /**
     * Whether a student enrollment can be assigned to a batch (scheme rules).
     * Students without a scheme may join any batch for their course; scheme is inherited from the batch.
     */
    function canAssignStudentSchemeToBatch(?int $studentScheme, ?int $batchScheme): bool {
        if ($studentScheme === null) {
            return true;
        }
        return schemeIdsMatch($studentScheme, $batchScheme);
    }

    function isAadharEnrolledInCourseScheme(mysqli $conn, string $aadhar, int $courseId, ?int $schemeId = null): bool {
        $aadhar = normalizeAadhar($aadhar);
        if ($aadhar === '' || $courseId <= 0) {
            return false;
        }
        $schemeId = normalizeEnrollmentSchemeId($schemeId);

        if (isMultiCourseSystemInstalled($conn) && hasSchemeEnrollmentColumns($conn)) {
            if ($schemeId === null) {
                $sql = "SELECT se.id FROM student_enrollments se
                        INNER JOIN student_accounts sa ON sa.id = se.account_id
                        WHERE sa.aadhar = ? AND se.course_id = ?
                        AND se.scheme_id IS NULL
                        AND se.status NOT IN ('rejected', 'cancelled', 'inactive')
                        LIMIT 1";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('si', $aadhar, $courseId);
                    $stmt->execute();
                    $found = $stmt->get_result()->num_rows > 0;
                    $stmt->close();
                    if ($found) {
                        return true;
                    }
                }
            } else {
                $sql = "SELECT se.id FROM student_enrollments se
                        INNER JOIN student_accounts sa ON sa.id = se.account_id
                        WHERE sa.aadhar = ? AND se.course_id = ? AND se.scheme_id = ?
                        AND se.status NOT IN ('rejected', 'cancelled', 'inactive')
                        LIMIT 1";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('sii', $aadhar, $courseId, $schemeId);
                    $stmt->execute();
                    $found = $stmt->get_result()->num_rows > 0;
                    $stmt->close();
                    if ($found) {
                        return true;
                    }
                }
            }
        }

        if (hasSchemeEnrollmentColumns($conn)) {
            if ($schemeId === null) {
                $stmt = $conn->prepare("SELECT id FROM students
                    WHERE REPLACE(REPLACE(aadhar,' ',''),'-','') = ?
                    AND course_id = ? AND scheme_id IS NULL
                    AND LOWER(status) NOT IN ('rejected', 'inactive')
                    LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('si', $aadhar, $courseId);
                    $stmt->execute();
                    $found = $stmt->get_result()->num_rows > 0;
                    $stmt->close();
                    return $found;
                }
            } else {
                $stmt = $conn->prepare("SELECT id FROM students
                    WHERE REPLACE(REPLACE(aadhar,' ',''),'-','') = ?
                    AND course_id = ? AND scheme_id = ?
                    AND LOWER(status) NOT IN ('rejected', 'inactive')
                    LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('sii', $aadhar, $courseId, $schemeId);
                    $stmt->execute();
                    $found = $stmt->get_result()->num_rows > 0;
                    $stmt->close();
                    return $found;
                }
            }
            return false;
        }

        return isAadharEnrolledInCourseLegacy($conn, $aadhar, $courseId);
    }

    function isAadharEnrolledInCourseLegacy(mysqli $conn, string $aadhar, int $courseId): bool {
        $aadhar = normalizeAadhar($aadhar);
        if ($aadhar === '' || $courseId <= 0) {
            return false;
        }

        if (isMultiCourseSystemInstalled($conn)) {
            $sql = "SELECT se.id FROM student_enrollments se
                    INNER JOIN student_accounts sa ON sa.id = se.account_id
                    WHERE sa.aadhar = ? AND se.course_id = ?
                    AND se.status NOT IN ('rejected', 'cancelled', 'inactive')
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('si', $aadhar, $courseId);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $stmt->close();
                    return true;
                }
                $stmt->close();
            }
        }

        $stmt = $conn->prepare("SELECT id FROM students
            WHERE REPLACE(REPLACE(aadhar,' ',''),'-','') = ?
            AND course_id = ?
            AND LOWER(status) NOT IN ('rejected')
            LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $aadhar, $courseId);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $found;
    }

    function isAadharEnrolledInCourse(mysqli $conn, string $aadhar, int $courseId): bool {
        if (hasSchemeEnrollmentColumns($conn)) {
            return false;
        }
        return isAadharEnrolledInCourseLegacy($conn, $aadhar, $courseId);
    }

    function getEnrolledSchemeIdsForCourse(mysqli $conn, string $aadhar, int $courseId): array {
        $aadhar = normalizeAadhar($aadhar);
        $ids = [];
        if ($aadhar === '' || $courseId <= 0) {
            return $ids;
        }

        $stmt = $conn->prepare("SELECT DISTINCT scheme_id FROM students
            WHERE REPLACE(REPLACE(aadhar,' ',''),'-','') = ?
            AND course_id = ?
            AND LOWER(status) NOT IN ('rejected')
            AND scheme_id IS NOT NULL");
        if ($stmt) {
            $stmt->bind_param('si', $aadhar, $courseId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int)$row['scheme_id'];
            }
            $stmt->close();
        }

        if (isMultiCourseSystemInstalled($conn)) {
            $sql = "SELECT DISTINCT se.scheme_id FROM student_enrollments se
                    INNER JOIN student_accounts sa ON sa.id = se.account_id
                    WHERE sa.aadhar = ? AND se.course_id = ?
                    AND se.status NOT IN ('rejected', 'cancelled', 'inactive')
                    AND se.scheme_id IS NOT NULL";
            $enrStmt = $conn->prepare($sql);
            if ($enrStmt) {
                $enrStmt->bind_param('si', $aadhar, $courseId);
                $enrStmt->execute();
                $res = $enrStmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $ids[] = (int)$row['scheme_id'];
                }
                $enrStmt->close();
            }
        }

        $ids = array_values(array_unique(array_filter($ids, function ($id) {
            return $id > 0;
        })));

        $nullEnrollment = false;
        $nullStmt = $conn->prepare("SELECT id FROM students
            WHERE REPLACE(REPLACE(aadhar,' ',''),'-','') = ?
            AND course_id = ? AND scheme_id IS NULL
            AND LOWER(status) NOT IN ('rejected') LIMIT 1");
        if ($nullStmt) {
            $nullStmt->bind_param('si', $aadhar, $courseId);
            $nullStmt->execute();
            $nullEnrollment = $nullStmt->get_result()->num_rows > 0;
            $nullStmt->close();
        }
        if ($nullEnrollment) {
            $ids[] = 0;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Create or update student_enrollments row for a students record (keeps scheme list in sync).
     */
    function syncStudentEnrollmentRecord(mysqli $conn, int $studentRecordId): void {
        if (!isMultiCourseSystemInstalled($conn)) {
            return;
        }
        $stmt = $conn->prepare('SELECT id, account_id, course_id, scheme_id, status FROM students WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $studentRecordId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || empty($row['course_id'])) {
            return;
        }

        $accountId = (int)($row['account_id'] ?? 0);
        if ($accountId <= 0) {
            $legacy = $conn->prepare('SELECT student_id, aadhar FROM students WHERE id = ? LIMIT 1');
            if ($legacy) {
                $legacy->bind_param('i', $studentRecordId);
                $legacy->execute();
                $leg = $legacy->get_result()->fetch_assoc();
                $legacy->close();
                if ($leg) {
                    $account = resolveStudentAccount($conn, (string)$leg['student_id']);
                    if ($account) {
                        $accountId = (int)$account['id'];
                        linkStudentRecordToAccount($conn, $studentRecordId, $accountId);
                    }
                }
            }
        }
        if ($accountId <= 0) {
            return;
        }

        ensureSchemeEnrollmentUniqueIndex($conn);
        $courseId = (int)$row['course_id'];
        $schemeId = normalizeEnrollmentSchemeId($row['scheme_id'] ?? null);
        $status = (string)($row['status'] ?? 'pending');

        $check = $conn->prepare('SELECT id FROM student_enrollments WHERE student_record_id = ? LIMIT 1');
        if ($check) {
            $check->bind_param('i', $studentRecordId);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();
            if ($exists) {
                if (hasSchemeEnrollmentColumns($conn)) {
                    $schemeBind = $schemeId;
                    $upd = $conn->prepare('UPDATE student_enrollments SET scheme_id = ?, status = ?, account_id = ?, course_id = ? WHERE student_record_id = ?');
                    if ($upd) {
                        $upd->bind_param('isiii', $schemeBind, $status, $accountId, $courseId, $studentRecordId);
                        $upd->execute();
                        $upd->close();
                    }
                }
                return;
            }
        }

        createStudentEnrollment($conn, $accountId, $courseId, $studentRecordId, $status, $schemeId);
    }

    /**
     * All scheme enrollments for a student ID + course (from students rows — source of truth for admin list).
     */
    function getEnrolledSchemesForStudentCourse(mysqli $conn, string $studentIdStr, int $courseId): array {
        $studentIdStr = trim($studentIdStr);
        if ($studentIdStr === '' || $courseId <= 0 || !hasSchemeEnrollmentColumns($conn)) {
            return [];
        }
        $sql = "SELECT DISTINCT sch.id, sch.scheme_name, sch.scheme_code, s.id AS student_record_id
                FROM students s
                INNER JOIN schemes sch ON sch.id = s.scheme_id
                WHERE s.student_id = ? AND s.course_id = ?
                AND LOWER(s.status) NOT IN ('rejected', 'inactive')
                ORDER BY sch.scheme_name ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('si', $studentIdStr, $courseId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    /**
     * Scheme enrollments that were soft-removed (status inactive) — can be restored.
     */
    function getInactiveSchemeEnrollmentsForStudentCourse(mysqli $conn, string $studentIdStr, int $courseId): array {
        $studentIdStr = trim($studentIdStr);
        if ($studentIdStr === '' || $courseId <= 0 || !hasSchemeEnrollmentColumns($conn)) {
            return [];
        }
        $sql = "SELECT s.id AS student_record_id, sch.id, sch.scheme_name, sch.scheme_code
                FROM students s
                INNER JOIN schemes sch ON sch.id = s.scheme_id
                WHERE s.student_id = ? AND s.course_id = ?
                AND LOWER(s.status) = 'inactive'
                ORDER BY sch.scheme_name ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('si', $studentIdStr, $courseId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    /**
     * Whether an enrollment row has any batch assignment (primary or batch_students).
     */
    function enrollmentRecordHasBatches(mysqli $conn, int $recordId): bool {
        if ($recordId <= 0) {
            return false;
        }

        $stmt = $conn->prepare('SELECT batch_id FROM students WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $recordId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($row['batch_id'])) {
                return true;
            }
        }

        $hasRecordCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
        $useRecordCol = ($hasRecordCol && $hasRecordCol->num_rows > 0);

        if ($useRecordCol) {
            $bs = $conn->prepare('SELECT id FROM batch_students WHERE student_record_id = ? OR student_id = ? LIMIT 1');
            if ($bs) {
                $bs->bind_param('ii', $recordId, $recordId);
                $bs->execute();
                $has = $bs->get_result()->num_rows > 0;
                $bs->close();
                if ($has) {
                    return true;
                }
            }
        } else {
            $bs = $conn->prepare('SELECT id FROM batch_students WHERE student_id = ? LIMIT 1');
            if ($bs) {
                $bs->bind_param('i', $recordId);
                $bs->execute();
                $has = $bs->get_result()->num_rows > 0;
                $bs->close();
                if ($has) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Remove a scheme-specific enrollment row (unchecked in Manage Schemes).
     */
    function adminRemoveSchemeEnrollment(mysqli $conn, int $studentRecordId, int $schemeId): array {
        if ($studentRecordId <= 0 || $schemeId <= 0) {
            return ['success' => false, 'message' => 'Invalid enrollment or scheme.'];
        }

        $stmt = $conn->prepare('SELECT s.id, s.scheme_id, sch.scheme_name
            FROM students s
            LEFT JOIN schemes sch ON sch.id = s.scheme_id
            WHERE s.id = ? AND LOWER(s.status) NOT IN ("rejected")
            LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'message' => $conn->error];
        }
        $stmt->bind_param('i', $studentRecordId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return ['success' => false, 'message' => 'Enrollment record not found.'];
        }
        if ((int)($row['scheme_id'] ?? 0) !== $schemeId) {
            return ['success' => false, 'message' => 'Scheme enrollment record mismatch.'];
        }

        $schemeLabel = $row['scheme_name'] ?? 'scheme';

        if (enrollmentRecordHasBatches($conn, $studentRecordId)) {
            return [
                'success' => false,
                'message' => 'Cannot remove "' . $schemeLabel . '": student is assigned to a batch. Remove from batch first.',
            ];
        }

        if (isMultiCourseSystemInstalled($conn)) {
            $enr = $conn->prepare("UPDATE student_enrollments SET status = 'inactive' WHERE student_record_id = ?");
            if ($enr) {
                $enr->bind_param('i', $studentRecordId);
                $enr->execute();
                $enr->close();
            }
        }

        $upd = $conn->prepare("UPDATE students SET status = 'inactive' WHERE id = ? AND scheme_id = ?");
        if (!$upd) {
            return ['success' => false, 'message' => $conn->error];
        }
        $upd->bind_param('ii', $studentRecordId, $schemeId);
        if (!$upd->execute() || $upd->affected_rows <= 0) {
            $err = $upd->error;
            $upd->close();
            return ['success' => false, 'message' => $err ?: 'Could not remove scheme enrollment.'];
        }
        $upd->close();

        return [
            'success' => true,
            'message' => 'Removed "' . $schemeLabel . '" enrollment. You can restore it from the Schemes dialog.',
        ];
    }

    /**
     * Restore a soft-removed scheme enrollment row.
     */
    function adminRestoreSchemeEnrollment(mysqli $conn, int $studentRecordId): array {
        if ($studentRecordId <= 0) {
            return ['success' => false, 'message' => 'Invalid enrollment record.'];
        }

        $stmt = $conn->prepare('SELECT s.id, s.student_id, s.course_id, s.scheme_id, s.status, sch.scheme_name
            FROM students s
            LEFT JOIN schemes sch ON sch.id = s.scheme_id
            WHERE s.id = ? LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'message' => $conn->error];
        }
        $stmt->bind_param('i', $studentRecordId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return ['success' => false, 'message' => 'Enrollment record not found.'];
        }
        if (strtolower($row['status'] ?? '') !== 'inactive') {
            return ['success' => false, 'message' => 'This enrollment is not in removed state.'];
        }

        $schemeId = (int)($row['scheme_id'] ?? 0);
        $courseId = (int)($row['course_id'] ?? 0);
        $studentIdStr = (string)$row['student_id'];

        if ($schemeId > 0 && $courseId > 0) {
            $dup = $conn->prepare("SELECT id FROM students
                WHERE student_id = ? AND course_id = ? AND scheme_id = ?
                AND id != ? AND LOWER(status) NOT IN ('rejected', 'inactive')
                LIMIT 1");
            if ($dup) {
                $dup->bind_param('siii', $studentIdStr, $courseId, $schemeId, $studentRecordId);
                $dup->execute();
                if ($dup->get_result()->num_rows > 0) {
                    $dup->close();
                    return ['success' => false, 'message' => 'An active enrollment for this scheme already exists.'];
                }
                $dup->close();
            }
        }

        $upd = $conn->prepare("UPDATE students SET status = 'active' WHERE id = ?");
        if (!$upd) {
            return ['success' => false, 'message' => $conn->error];
        }
        $upd->bind_param('i', $studentRecordId);
        if (!$upd->execute()) {
            $err = $upd->error;
            $upd->close();
            return ['success' => false, 'message' => $err];
        }
        $upd->close();

        if (isMultiCourseSystemInstalled($conn)) {
            $enr = $conn->prepare("UPDATE student_enrollments SET status = 'active' WHERE student_record_id = ?");
            if ($enr) {
                $enr->bind_param('i', $studentRecordId);
                $enr->execute();
                $enr->close();
            }
        }

        syncStudentEnrollmentRecord($conn, $studentRecordId);

        $label = $row['scheme_name'] ?? 'scheme';
        return ['success' => true, 'message' => 'Restored "' . $label . '" enrollment successfully.'];
    }

    /**
     * Restore every soft-removed scheme enrollment for one student + course.
     */
    function adminRestoreAllInactiveEnrollmentsForStudentCourse(mysqli $conn, string $studentIdStr, int $courseId): array {
        $studentIdStr = trim($studentIdStr);
        if ($studentIdStr === '' || $courseId <= 0) {
            return ['success' => false, 'message' => 'Invalid student or course.', 'restored' => 0];
        }

        $inactive = getInactiveSchemeEnrollmentsForStudentCourse($conn, $studentIdStr, $courseId);
        if (empty($inactive)) {
            return ['success' => false, 'message' => 'No removed enrollments found for this student.', 'restored' => 0];
        }

        $restored = 0;
        $errors = [];
        foreach ($inactive as $row) {
            $result = adminRestoreSchemeEnrollment($conn, (int)$row['student_record_id']);
            if ($result['success']) {
                $restored++;
            } else {
                $errors[] = $result['message'];
            }
        }

        if ($restored > 0) {
            $msg = "Restored {$restored} scheme enrollment(s).";
            if (!empty($errors)) {
                $msg .= ' ' . $errors[0];
            }
            return ['success' => true, 'message' => $msg, 'restored' => $restored];
        }

        return ['success' => false, 'message' => $errors[0] ?? 'Could not restore enrollments.', 'restored' => 0];
    }

    /**
     * Count student+course groups that only have inactive (removed) enrollments.
     */
    function countFullyRemovedStudentCourses(mysqli $conn, array $courseIds = []): int {
        $courseFilter = '';
        if (!empty($courseIds)) {
            $ids = implode(',', array_map('intval', $courseIds));
            $courseFilter = " AND s.course_id IN ($ids)";
        }

        $sql = "SELECT COUNT(*) AS c FROM (
            SELECT s.student_id, s.course_id
            FROM students s
            WHERE LOWER(s.status) = 'inactive'
            $courseFilter
            AND NOT EXISTS (
                SELECT 1 FROM students s2
                WHERE s2.student_id = s.student_id AND s2.course_id = s.course_id
                AND LOWER(s2.status) NOT IN ('rejected', 'inactive')
            )
            GROUP BY s.student_id, s.course_id
        ) removed_groups";

        $res = $conn->query($sql);
        if (!$res) {
            return 0;
        }
        $row = $res->fetch_assoc();
        return (int)($row['c'] ?? 0);
    }

    /**
     * Add missing scheme enrollments; remove unchecked ones; reuse orphan rows before creating new rows.
     */
    function adminSyncStudentSchemes(mysqli $conn, string $studentIdStr, int $courseId, array $schemeIds, string $adminName = 'Admin', bool $confirmRemovals = false): array {
        $studentIdStr = trim($studentIdStr);
        if ($studentIdStr === '' || $courseId <= 0) {
            return ['success' => false, 'message' => 'Invalid student or course.'];
        }

        ensureSchemeEnrollmentUniqueIndex($conn);

        $targetIds = [];
        foreach ($schemeIds as $sid) {
            $normalized = normalizeEnrollmentSchemeId($sid);
            if ($normalized !== null) {
                $targetIds[$normalized] = $normalized;
            }
        }
        $targetIds = array_values($targetIds);

        if (empty($targetIds) && !empty(getSchemesForCourse($conn, $courseId))) {
            return ['success' => false, 'message' => 'Please select at least one scheme/project.'];
        }

        foreach ($targetIds as $schemeId) {
            if (!validateSchemeForCourse($conn, $courseId, $schemeId)) {
                return ['success' => false, 'message' => 'One or more schemes are not linked to this course.'];
            }
        }

        $enrolled = getEnrolledSchemesForStudentCourse($conn, $studentIdStr, $courseId);
        $enrolledIds = array_map(function ($row) {
            return (int)$row['id'];
        }, $enrolled);
        $enrolledBySchemeId = [];
        foreach ($enrolled as $row) {
            $enrolledBySchemeId[(int)$row['id']] = (int)$row['student_record_id'];
        }

        $toAdd = array_values(array_diff($targetIds, $enrolledIds));
        $toRemove = array_values(array_diff($enrolledIds, $targetIds));
        $skippedRemovals = [];

        if (!empty($toRemove) && !$confirmRemovals) {
            $skippedRemovals = $toRemove;
            $toRemove = [];
        }

        $removed = 0;
        $removeErrors = [];
        foreach ($toRemove as $schemeId) {
            $recordId = $enrolledBySchemeId[$schemeId] ?? 0;
            if ($recordId <= 0) {
                continue;
            }
            $result = adminRemoveSchemeEnrollment($conn, $recordId, $schemeId);
            if ($result['success']) {
                $removed++;
            } else {
                $removeErrors[] = $result['message'];
            }
        }

        $orphanStmt = $conn->prepare("SELECT id FROM students
            WHERE student_id = ? AND course_id = ?
            AND scheme_id IS NULL
            AND LOWER(status) NOT IN ('rejected')
            ORDER BY id ASC");
        $orphans = [];
        if ($orphanStmt) {
            $orphanStmt->bind_param('si', $studentIdStr, $courseId);
            $orphanStmt->execute();
            $res = $orphanStmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $orphans[] = (int)$row['id'];
            }
            $orphanStmt->close();
        }

        $added = 0;
        $errors = [];
        foreach ($toAdd as $schemeId) {
            $restoreStmt = $conn->prepare("SELECT id FROM students
                WHERE student_id = ? AND course_id = ? AND scheme_id = ?
                AND LOWER(status) = 'inactive' LIMIT 1");
            if ($restoreStmt) {
                $restoreStmt->bind_param('sii', $studentIdStr, $courseId, $schemeId);
                $restoreStmt->execute();
                $inactiveRow = $restoreStmt->get_result()->fetch_assoc();
                $restoreStmt->close();
                if ($inactiveRow) {
                    $result = adminRestoreSchemeEnrollment($conn, (int)$inactiveRow['id']);
                    if ($result['success']) {
                        $added++;
                        continue;
                    }
                    $errors[] = $result['message'];
                    continue;
                }
            }

            if (!empty($orphans)) {
                $orphanId = (int)array_shift($orphans);
                $result = adminUpdateStudentScheme($conn, $orphanId, $schemeId);
            } else {
                $result = adminAssignCourseToStudent($conn, $studentIdStr, $courseId, null, $adminName, $schemeId);
            }

            if ($result['success']) {
                $added++;
            } elseif (stripos($result['message'], 'already') !== false) {
                continue;
            } else {
                $errors[] = $result['message'];
            }
        }

        $removedOrphans = cleanupOrphanSchemeEnrollments($conn, $studentIdStr, $courseId);

        $parts = [];
        if ($added > 0) {
            $parts[] = $added . ' scheme enrollment(s) added';
        }
        if ($removed > 0) {
            $parts[] = $removed . ' removed';
        }
        if ($removedOrphans > 0) {
            $parts[] = $removedOrphans . ' empty duplicate row(s) cleaned up';
        }

        if (!empty($parts)) {
            $msg = ucfirst(implode(', ', $parts)) . '.';
            if (!empty($removeErrors)) {
                $msg .= ' ' . $removeErrors[0];
            }
            if (!empty($skippedRemovals)) {
                $msg .= ' Unchecked scheme(s) were not removed — confirmation is required.';
            }
            return [
                'success' => ($added > 0 || $removed > 0 || $removedOrphans > 0) && empty($removeErrors),
                'warning' => !empty($removeErrors) && ($added > 0 || $removed > 0 || $removedOrphans > 0),
                'message' => $msg,
            ];
        }
        if (!empty($errors)) {
            return ['success' => false, 'message' => $errors[0]];
        }
        if (!empty($removeErrors)) {
            return ['success' => false, 'message' => $removeErrors[0]];
        }

        $msg = 'Scheme enrollments are up to date.';
        if ($removedOrphans > 0) {
            $msg .= " Removed {$removedOrphans} empty duplicate row(s).";
        }
        if (!empty($skippedRemovals)) {
            $msg .= ' Unchecked scheme(s) were not removed — confirmation is required.';
            return ['success' => false, 'message' => $msg];
        }
        return ['success' => true, 'message' => $msg];
    }

    /**
     * Remove duplicate enrollment rows with no scheme and no batch assigned.
     */
    function cleanupOrphanSchemeEnrollments(mysqli $conn, string $studentIdStr, int $courseId): int {
        if (!hasSchemeEnrollmentColumns($conn)) {
            return 0;
        }

        $enrolled = getEnrolledSchemesForStudentCourse($conn, $studentIdStr, $courseId);
        if (empty($enrolled)) {
            return 0;
        }

        $stmt = $conn->prepare("SELECT id FROM students
            WHERE student_id = ? AND course_id = ?
            AND scheme_id IS NULL
            AND (batch_id IS NULL OR batch_id = 0)
            AND LOWER(status) NOT IN ('rejected')");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('si', $studentIdStr, $courseId);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int)$row['id'];
        }
        $stmt->close();

        $removed = 0;
        foreach ($ids as $rid) {
            if (enrollmentRecordHasBatches($conn, $rid)) {
                continue;
            }
            if (isMultiCourseSystemInstalled($conn)) {
                $delEnr = $conn->prepare('DELETE FROM student_enrollments WHERE student_record_id = ?');
                if ($delEnr) {
                    $delEnr->bind_param('i', $rid);
                    $delEnr->execute();
                    $delEnr->close();
                }
            }
            $del = $conn->prepare('DELETE FROM students WHERE id = ? AND scheme_id IS NULL AND (batch_id IS NULL OR batch_id = 0)');
            if ($del) {
                $del->bind_param('i', $rid);
                if ($del->execute() && $del->affected_rows > 0) {
                    $removed++;
                }
                $del->close();
            }
        }

        return $removed;
    }

    /**
     * Ensure student_enrollments allows multiple schemes per course (migration may not have run on production).
     */
    function ensureSchemeEnrollmentUniqueIndex(mysqli $conn): void {
        if (!isMultiCourseSystemInstalled($conn) || !hasSchemeEnrollmentColumns($conn)) {
            return;
        }
        $idx = $conn->query("SHOW INDEX FROM student_enrollments WHERE Key_name = 'uk_account_course'");
        if ($idx && $idx->num_rows > 0) {
            $conn->query("ALTER TABLE student_enrollments DROP INDEX uk_account_course");
        }
        $idx2 = $conn->query("SHOW INDEX FROM student_enrollments WHERE Key_name = 'uk_account_course_scheme'");
        if (!$idx2 || $idx2->num_rows === 0) {
            $conn->query("ALTER TABLE student_enrollments ADD UNIQUE KEY uk_account_course_scheme (account_id, course_id, scheme_id)");
        }
    }

    function createStudentAccount(mysqli $conn, array $data): ?int {
        $sql = "INSERT INTO student_accounts (student_id, aadhar, name, email, mobile, password, dob, gender, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param(
            'ssssssss',
            $data['student_id'],
            $data['aadhar'],
            $data['name'],
            $data['email'],
            $data['mobile'],
            $data['password'],
            $data['dob'],
            $data['gender']
        );
        if (!$stmt->execute()) {
            error_log('createStudentAccount: ' . $stmt->error);
            $stmt->close();
            return null;
        }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    function createStudentEnrollment(mysqli $conn, int $accountId, int $courseId, int $studentRecordId, string $status = 'pending', ?int $schemeId = null): ?int {
        if (!isMultiCourseSystemInstalled($conn)) {
            return null;
        }
        ensureSchemeEnrollmentUniqueIndex($conn);

        $schemeId = normalizeEnrollmentSchemeId($schemeId);
        if (hasSchemeEnrollmentColumns($conn)) {
            $sql = "INSERT INTO student_enrollments (account_id, course_id, scheme_id, student_record_id, status, registered_at)
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return null;
            }
            $schemeBind = $schemeId;
            $stmt->bind_param('iiiis', $accountId, $courseId, $schemeBind, $studentRecordId, $status);
        } else {
            $sql = "INSERT INTO student_enrollments (account_id, course_id, student_record_id, status, registered_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('iiis', $accountId, $courseId, $studentRecordId, $status);
        }
        if (!$stmt->execute()) {
            error_log('createStudentEnrollment: ' . $stmt->error);
            $stmt->close();
            return null;
        }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    function linkStudentRecordToAccount(mysqli $conn, int $studentRecordId, int $accountId): bool {
        $check = $conn->query("SHOW COLUMNS FROM students LIKE 'account_id'");
        if (!$check || $check->num_rows === 0) {
            return false;
        }
        $stmt = $conn->prepare('UPDATE students SET account_id = ? WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $accountId, $studentRecordId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function getAccountById(mysqli $conn, int $accountId): ?array {
        if ($accountId <= 0 || !isMultiCourseSystemInstalled($conn)) {
            return null;
        }
        $stmt = $conn->prepare('SELECT * FROM student_accounts WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Resolve student_accounts row by Student ID string (handles Aadhar link, account_id on students, legacy rows).
     */
    function resolveStudentAccount(mysqli $conn, string $studentIdStr): ?array {
        $studentIdStr = trim($studentIdStr);
        if ($studentIdStr === '') {
            return null;
        }

        $account = getAccountByStudentId($conn, $studentIdStr);
        if ($account) {
            return $account;
        }

        $stmt = $conn->prepare(
            'SELECT sa.* FROM student_accounts sa
             INNER JOIN students s ON s.account_id = sa.id
             WHERE s.student_id = ? LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('s', $studentIdStr);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return $row;
            }
        }

        $legacyStmt = $conn->prepare('SELECT * FROM students WHERE student_id = ? ORDER BY id ASC LIMIT 1');
        if (!$legacyStmt) {
            return null;
        }
        $legacyStmt->bind_param('s', $studentIdStr);
        $legacyStmt->execute();
        $legacy = $legacyStmt->get_result()->fetch_assoc();
        $legacyStmt->close();

        if (!$legacy) {
            return null;
        }

        if (!empty($legacy['account_id'])) {
            $account = getAccountById($conn, (int)$legacy['account_id']);
            if ($account) {
                return $account;
            }
        }

        $aadhar = normalizeAadhar($legacy['aadhar'] ?? '');
        if ($aadhar !== '') {
            $byAadhar = findAccountByAadhar($conn, $aadhar);
            if ($byAadhar && !empty($byAadhar['id'])) {
                linkStudentRecordToAccount($conn, (int)$legacy['id'], (int)$byAadhar['id']);
                return $byAadhar;
            }
        }

        $accountId = ensureLegacyAccountFromStudent($conn, $legacy);
        if ($accountId) {
            return getAccountById($conn, $accountId);
        }

        if ($aadhar !== '') {
            $byAadhar = findAccountByAadhar($conn, $aadhar);
            if ($byAadhar && !empty($byAadhar['id'])) {
                linkStudentRecordToAccount($conn, (int)$legacy['id'], (int)$byAadhar['id']);
                return $byAadhar;
            }
        }

        $fallback = $conn->prepare('SELECT * FROM student_accounts WHERE student_id = ? LIMIT 1');
        if ($fallback) {
            $fallback->bind_param('s', $legacy['student_id']);
            $fallback->execute();
            $row = $fallback->get_result()->fetch_assoc();
            $fallback->close();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    function getAccountByStudentId(mysqli $conn, string $studentId): ?array {
        if (!isMultiCourseSystemInstalled($conn)) {
            return null;
        }
        $stmt = $conn->prepare('SELECT * FROM student_accounts WHERE student_id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    function getEnrollmentsForStudentId(mysqli $conn, string $studentId): array {
        $enrollments = [];

        if (isMultiCourseSystemInstalled($conn)) {
            $sql = "SELECT se.*, c.course_name, c.course_code, c.duration,
                           b.batch_name, b.batch_code, bs.batch_id,
                           sch.scheme_name, sch.scheme_code
                    FROM student_enrollments se
                    INNER JOIN student_accounts sa ON sa.id = se.account_id
                    INNER JOIN courses c ON c.id = se.course_id
                    LEFT JOIN students s ON s.id = se.student_record_id
                    LEFT JOIN batch_students bs ON bs.student_record_id = se.student_record_id
                    LEFT JOIN batches b ON b.id = bs.batch_id
                    LEFT JOIN schemes sch ON sch.id = se.scheme_id
                    WHERE sa.student_id = ?
                    ORDER BY se.registered_at DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('s', $studentId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $enrollments[] = $row;
                }
                $stmt->close();
                if (!empty($enrollments)) {
                    return $enrollments;
                }
            }
        }

        $sql = "SELECT s.*, c.course_name, c.course_code, c.duration, b.batch_name, b.batch_code
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
                LEFT JOIN batches b ON b.id = s.batch_id
                WHERE s.student_id = ?
                ORDER BY s.registration_date DESC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $studentId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $enrollments[] = $row;
            }
            $stmt->close();
        }
        return $enrollments;
    }

    function assignEnrollmentToBatch(mysqli $conn, int $studentRecordId, int $batchId, string $adminName = ''): array {
        $batch = getBatchByIdForEnrollment($conn, $batchId);
        if (!$batch) {
            return ['success' => false, 'message' => 'Batch not found.'];
        }

        $studentScheme = null;
        $batchScheme = normalizeEnrollmentSchemeId($batch['scheme_id'] ?? null);

        $stuStmt = $conn->prepare('SELECT id, course_id, scheme_id, batch_id FROM students WHERE id = ? LIMIT 1');
        if ($stuStmt) {
            $stuStmt->bind_param('i', $studentRecordId);
            $stuStmt->execute();
            $studentRow = $stuStmt->get_result()->fetch_assoc();
            $stuStmt->close();
            if (!$studentRow) {
                return ['success' => false, 'message' => 'Student enrollment record not found.'];
            }
            if ((int)$studentRow['course_id'] !== (int)$batch['course_id']) {
                return ['success' => false, 'message' => 'Batch course does not match student enrollment.'];
            }
            $studentScheme = normalizeEnrollmentSchemeId($studentRow['scheme_id'] ?? null);
            if (hasSchemeEnrollmentColumns($conn) && ($studentScheme !== null || $batchScheme !== null)) {
                if (!canAssignStudentSchemeToBatch($studentScheme, $batchScheme)) {
                    return ['success' => false, 'message' => 'Student scheme/project does not match this batch.'];
                }
            }
        }

        $hasRecordCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
        $useRecordCol = ($hasRecordCol && $hasRecordCol->num_rows > 0);

        if ($useRecordCol) {
            $check = $conn->prepare('SELECT id FROM batch_students WHERE batch_id = ? AND (student_record_id = ? OR student_id = ?) LIMIT 1');
            if ($check) {
                $check->bind_param('iii', $batchId, $studentRecordId, $studentRecordId);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $check->close();
                    return ['success' => true, 'message' => 'Student is already in this batch.'];
                }
                $check->close();
            }
            $stmt = $conn->prepare('INSERT INTO batch_students (batch_id, student_id, student_record_id, enrollment_date) VALUES (?, ?, ?, NOW())');
            if (!$stmt) {
                return ['success' => false, 'message' => $conn->error];
            }
            $stmt->bind_param('iii', $batchId, $studentRecordId, $studentRecordId);
        } else {
            $check = $conn->prepare('SELECT id FROM batch_students WHERE student_id = ? AND batch_id = ? LIMIT 1');
            if ($check) {
                $check->bind_param('ii', $studentRecordId, $batchId);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $check->close();
                    return ['success' => true, 'message' => 'Student is already in this batch.'];
                }
                $check->close();
            }
            $stmt = $conn->prepare('INSERT INTO batch_students (batch_id, student_id, enrollment_date) VALUES (?, ?, NOW())');
            if (!$stmt) {
                return ['success' => false, 'message' => $conn->error];
            }
            $stmt->bind_param('ii', $batchId, $studentRecordId);
        }

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => $err];
        }
        $stmt->close();

        $existingBatchId = !empty($studentRow['batch_id']) ? (int)$studentRow['batch_id'] : 0;
        if ($existingBatchId <= 0) {
            $upd = $conn->prepare("UPDATE students SET batch_id = ?, status = 'active', approved_by = ?, approved_at = NOW() WHERE id = ?");
            if ($upd) {
                $upd->bind_param('isi', $batchId, $adminName, $studentRecordId);
                $upd->execute();
                $upd->close();
            }
        } else {
            $statusUpd = $conn->prepare("UPDATE students SET status = 'active', approved_by = ?, approved_at = NOW() WHERE id = ?");
            if ($statusUpd) {
                $statusUpd->bind_param('si', $adminName, $studentRecordId);
                $statusUpd->execute();
                $statusUpd->close();
            }
        }

        if (hasSchemeEnrollmentColumns($conn) && $studentScheme === null && $batchScheme !== null) {
            $schemeUpd = $conn->prepare('UPDATE students SET scheme_id = ? WHERE id = ?');
            if ($schemeUpd) {
                $schemeUpd->bind_param('ii', $batchScheme, $studentRecordId);
                $schemeUpd->execute();
                $schemeUpd->close();
            }
        }

        if (isMultiCourseSystemInstalled($conn)) {
            $enr = $conn->prepare("UPDATE student_enrollments SET batch_id = COALESCE(batch_id, ?), status = 'active', approved_by = ?, approved_at = NOW() WHERE student_record_id = ?");
            if ($enr) {
                $enr->bind_param('isi', $batchId, $adminName, $studentRecordId);
                $enr->execute();
                $enr->close();
            }
            if (hasSchemeEnrollmentColumns($conn) && $studentScheme === null && $batchScheme !== null) {
                $enrScheme = $conn->prepare('UPDATE student_enrollments SET scheme_id = ? WHERE student_record_id = ?');
                if ($enrScheme) {
                    $enrScheme->bind_param('ii', $batchScheme, $studentRecordId);
                    $enrScheme->execute();
                    $enrScheme->close();
                }
            }
        }

        $batchUpd = $conn->prepare('UPDATE batches SET seats_filled = seats_filled + 1 WHERE id = ?');
        if ($batchUpd) {
            $batchUpd->bind_param('i', $batchId);
            $batchUpd->execute();
            $batchUpd->close();
        }

        return ['success' => true, 'message' => 'Student assigned to batch successfully.'];
    }

    function getBatchByIdForEnrollment(mysqli $conn, int $batchId): ?array {
        if ($batchId <= 0) {
            return null;
        }
        $sql = "SELECT b.id, b.course_id, b.scheme_id, b.batch_name, b.status
                FROM batches b WHERE b.id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $batchId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Set or change scheme/project on an existing student enrollment (must match course_schemes).
     */
    function adminUpdateStudentScheme(mysqli $conn, int $studentRecordId, ?int $schemeId): array {
        if (!hasSchemeEnrollmentColumns($conn)) {
            return ['success' => false, 'message' => 'Scheme support is not installed on this system.'];
        }

        $stmt = $conn->prepare('SELECT id, student_id, course_id, scheme_id, batch_id, aadhar FROM students WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'message' => $conn->error];
        }
        $stmt->bind_param('i', $studentRecordId);
        $stmt->execute();
        $studentRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$studentRow) {
            return ['success' => false, 'message' => 'Student record not found.'];
        }

        $courseId = (int)($studentRow['course_id'] ?? 0);
        if ($courseId <= 0) {
            return ['success' => false, 'message' => 'Student has no course assigned.'];
        }

        $schemeId = normalizeEnrollmentSchemeId($schemeId);
        $courseSchemes = getSchemesForCourse($conn, $courseId);

        if (!empty($courseSchemes)) {
            if ($schemeId === null) {
                return ['success' => false, 'message' => 'Please select a scheme/project linked to this course.'];
            }
            if (!validateSchemeForCourse($conn, $courseId, $schemeId)) {
                return ['success' => false, 'message' => 'Selected scheme is not linked to this course. Edit the course to add schemes.'];
            }
        } else {
            $schemeId = null;
        }

        $currentScheme = normalizeEnrollmentSchemeId($studentRow['scheme_id'] ?? null);
        if ($currentScheme === $schemeId) {
            return ['success' => true, 'message' => 'Scheme/project is already set for this student.'];
        }

        $aadhar = normalizeAadhar($studentRow['aadhar'] ?? '');
        if ($aadhar !== '' && $schemeId !== null) {
            $otherId = (int)$studentRow['id'];
            $dup = $conn->prepare('SELECT id FROM students WHERE REPLACE(REPLACE(aadhar," ",""),"-","") = ? AND course_id = ? AND scheme_id = ? AND id != ? AND LOWER(status) NOT IN ("rejected") LIMIT 1');
            if ($dup) {
                $dup->bind_param('siii', $aadhar, $courseId, $schemeId, $otherId);
                $dup->execute();
                if ($dup->get_result()->num_rows > 0) {
                    $dup->close();
                    return ['success' => false, 'message' => 'This student already has another enrollment under the selected scheme for this course.'];
                }
                $dup->close();
            }
        }

        if (!empty($studentRow['batch_id'])) {
            $batch = getBatchByIdForEnrollment($conn, (int)$studentRow['batch_id']);
            if ($batch) {
                $batchScheme = normalizeEnrollmentSchemeId($batch['scheme_id'] ?? null);
                if (!canAssignStudentSchemeToBatch($schemeId, $batchScheme)) {
                    return ['success' => false, 'message' => 'Selected scheme does not match the student\'s batch. Remove from batch first or pick a matching scheme.'];
                }
            }
        }

        if ($schemeId === null) {
            $upd = $conn->prepare('UPDATE students SET scheme_id = NULL WHERE id = ?');
            if (!$upd) {
                return ['success' => false, 'message' => $conn->error];
            }
            $upd->bind_param('i', $studentRecordId);
        } else {
            $upd = $conn->prepare('UPDATE students SET scheme_id = ? WHERE id = ?');
            if (!$upd) {
                return ['success' => false, 'message' => $conn->error];
            }
            $upd->bind_param('ii', $schemeId, $studentRecordId);
        }
        if (!$upd->execute()) {
            $err = $upd->error;
            $upd->close();
            return ['success' => false, 'message' => $err];
        }
        $upd->close();

        if (isMultiCourseSystemInstalled($conn)) {
            if ($schemeId === null) {
                $enr = $conn->prepare('UPDATE student_enrollments SET scheme_id = NULL WHERE student_record_id = ?');
                if ($enr) {
                    $enr->bind_param('i', $studentRecordId);
                    $enr->execute();
                    $enr->close();
                }
            } else {
                $enr = $conn->prepare('UPDATE student_enrollments SET scheme_id = ? WHERE student_record_id = ?');
                if ($enr) {
                    $enr->bind_param('ii', $schemeId, $studentRecordId);
                    $enr->execute();
                    $enr->close();
                }
            }
        }

        syncStudentEnrollmentRecord($conn, $studentRecordId);

        return ['success' => true, 'message' => 'Scheme/project updated successfully.'];
    }

    function adminAssignCourseToStudent(mysqli $conn, string $studentIdStr, int $courseId, ?int $batchId, string $adminName, ?int $schemeId = null): array {
        if (!isMultiCourseSystemInstalled($conn)) {
            return ['success' => false, 'message' => 'Multi-course system is not installed. Run migrations/install_multi_course_system.php first.'];
        }

        $studentIdStr = trim($studentIdStr);
        $account = resolveStudentAccount($conn, $studentIdStr);

        if (!$account) {
            $check = $conn->prepare('SELECT aadhar FROM students WHERE student_id = ? LIMIT 1');
            if ($check) {
                $check->bind_param('s', $studentIdStr);
                $check->execute();
                $row = $check->get_result()->fetch_assoc();
                $check->close();
                if ($row && normalizeAadhar($row['aadhar'] ?? '') === '') {
                    return ['success' => false, 'message' => 'Student has no Aadhar on file. Edit the student profile, add Aadhar, then try again.'];
                }
            }
            $exists = $conn->prepare('SELECT id FROM students WHERE student_id = ? LIMIT 1');
            if ($exists) {
                $exists->bind_param('s', $studentIdStr);
                $exists->execute();
                $found = $exists->get_result()->num_rows > 0;
                $exists->close();
                if (!$found) {
                    return ['success' => false, 'message' => 'Student ID not found.'];
                }
            }
            return ['success' => false, 'message' => 'Could not resolve student account. Please ensure the student has a valid Aadhar number.'];
        }

        $accountId = (int)$account['id'];

        $schemeId = normalizeEnrollmentSchemeId($schemeId);
        $courseSchemes = getSchemesForCourse($conn, $courseId);
        if (!empty($courseSchemes) && $schemeId === null) {
            return ['success' => false, 'message' => 'Please select a scheme/project for this course.'];
        }
        if ($schemeId !== null && !validateSchemeForCourse($conn, $courseId, $schemeId)) {
            return ['success' => false, 'message' => 'Invalid scheme/project for the selected course.'];
        }

        if ($schemeId !== null) {
            $inactiveStmt = $conn->prepare("SELECT id FROM students
                WHERE student_id = ? AND course_id = ? AND scheme_id = ?
                AND LOWER(status) = 'inactive' LIMIT 1");
            if ($inactiveStmt) {
                $inactiveStmt->bind_param('sii', $studentIdStr, $courseId, $schemeId);
                $inactiveStmt->execute();
                $inactiveRow = $inactiveStmt->get_result()->fetch_assoc();
                $inactiveStmt->close();
                if ($inactiveRow) {
                    return adminRestoreSchemeEnrollment($conn, (int)$inactiveRow['id']);
                }
            }
        }

        if (isAadharEnrolledInCourseScheme($conn, $account['aadhar'], $courseId, $schemeId)) {
            $schemeLabel = $schemeId ? 'this scheme for this course' : 'this course';
            return ['success' => false, 'message' => "Student is already enrolled in {$schemeLabel}."];
        }

        $tpl = $conn->prepare('SELECT * FROM students WHERE student_id = ? ORDER BY id DESC LIMIT 1');
        if (!$tpl) {
            return ['success' => false, 'message' => 'Database error loading student profile.'];
        }
        $tpl->bind_param('s', $studentIdStr);
        $tpl->execute();
        $profile = $tpl->get_result()->fetch_assoc();
        $tpl->close();
        if (!$profile) {
            return ['success' => false, 'message' => 'Student profile not found.'];
        }

        $courseStmt = $conn->prepare('SELECT course_name FROM courses WHERE id = ?');
        $courseStmt->bind_param('i', $courseId);
        $courseStmt->execute();
        $courseRow = $courseStmt->get_result()->fetch_assoc();
        $courseStmt->close();
        if (!$courseRow) {
            return ['success' => false, 'message' => 'Course not found.'];
        }

        $status = 'active';
        $hasAccountCol = $conn->query("SHOW COLUMNS FROM students LIKE 'account_id'");
        $accountColSql = ($hasAccountCol && $hasAccountCol->num_rows > 0) ? ', account_id' : '';
        $accountValSql = ($hasAccountCol && $hasAccountCol->num_rows > 0) ? ', ?' : '';
        $hasSchemeCol = hasSchemeEnrollmentColumns($conn);
        $schemeColSql = $hasSchemeCol ? ', scheme_id' : '';
        $schemeValSql = $hasSchemeCol ? ', ?' : '';

        $sql = "INSERT INTO students (
            course, course_id, training_center, name, father_name, mother_name,
            dob, age, mobile, aadhar, apaar_id, gender, religion, marital_status,
            category, pwd_status, distinguishing_marks, position, nationality, email,
            state, city, pincode, address, college_name, education_details,
            passport_photo, signature, left_thumb_impression, payment_receipt, utr_number, payment_date,
            student_id, password,
            aadhar_card_doc, caste_certificate_doc, tenth_marksheet_doc,
            twelfth_marksheet_doc, graduation_certificate_doc, other_documents_doc,
            status{$schemeColSql}{$accountColSql}, registration_date
        ) VALUES (
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,
            ?{$schemeValSql}{$accountValSql}, NOW()
        )";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'message' => $conn->error];
        }

        $accountId = (int)$account['id'];
        $bindTypes = 'si' . str_repeat('s', 5) . 'i' . str_repeat('s', 32) . 's';
        if ($hasSchemeCol) {
            $bindTypes .= 'i';
        }
        if ($hasAccountCol && $hasAccountCol->num_rows > 0) {
            $bindTypes .= 'i';
        }

        $args = [
            $courseRow['course_name'], $courseId, $profile['training_center'], $profile['name'], $profile['father_name'],
            $profile['mother_name'], $profile['dob'], $profile['age'], $profile['mobile'], $account['aadhar'],
            $profile['apaar_id'], $profile['gender'], $profile['religion'], $profile['marital_status'],
            $profile['category'], $profile['pwd_status'], $profile['distinguishing_marks'], $profile['position'],
            $profile['nationality'], $profile['email'], $profile['state'], $profile['city'], $profile['pincode'],
            $profile['address'], $profile['college_name'], $profile['education_details'],
            $profile['passport_photo'], $profile['signature'], $profile['left_thumb_impression'],
            $profile['payment_receipt'], $profile['utr_number'], $profile['payment_date'],
            $account['student_id'], $account['password'],
            $profile['aadhar_card_doc'] ?? '', $profile['caste_certificate_doc'] ?? '',
            $profile['tenth_marksheet_doc'] ?? '', $profile['twelfth_marksheet_doc'] ?? '',
            $profile['graduation_certificate_doc'] ?? '', $profile['other_documents_doc'] ?? '',
            $status,
        ];
        if ($hasSchemeCol) {
            $args[] = $schemeId;
        }
        if ($hasAccountCol && $hasAccountCol->num_rows > 0) {
            $args[] = $accountId;
        }
        $stmt->bind_param($bindTypes, ...$args);

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to create enrollment record: ' . $err];
        }
        $studentRecordId = (int)$stmt->insert_id;
        $stmt->close();

        $enrollmentId = createStudentEnrollment($conn, $accountId, $courseId, $studentRecordId, $status, $schemeId);
        if ($enrollmentId === null && isMultiCourseSystemInstalled($conn)) {
            $conn->query('DELETE FROM students WHERE id = ' . (int)$studentRecordId);
            return ['success' => false, 'message' => 'Failed to link scheme enrollment. Please contact admin to run scheme enrollment migration, then try again.'];
        }

        if ($batchId) {
            return assignEnrollmentToBatch($conn, $studentRecordId, $batchId, $adminName);
        }

        $schemeName = '';
        if ($schemeId) {
            $sn = $conn->prepare('SELECT scheme_name FROM schemes WHERE id = ? LIMIT 1');
            if ($sn) {
                $sn->bind_param('i', $schemeId);
                $sn->execute();
                $sr = $sn->get_result()->fetch_assoc();
                $sn->close();
                $schemeName = $sr['scheme_name'] ?? '';
            }
        }

        $msg = 'Student assigned to course successfully.';
        if ($schemeName !== '') {
            $msg = "Student assigned to {$courseRow['course_name']} ({$schemeName}) successfully.";
        }
        return ['success' => true, 'message' => $msg];
    }

    function ensureLegacyAccountFromStudent(mysqli $conn, array $legacyStudent): ?int {
        if (!isMultiCourseSystemInstalled($conn)) {
            return null;
        }
        $aadhar = normalizeAadhar($legacyStudent['aadhar'] ?? '');
        if ($aadhar === '') {
            return null;
        }
        $existing = findAccountByAadhar($conn, $aadhar);
        if ($existing && !empty($existing['id'])) {
            return (int)$existing['id'];
        }

        $accountId = createStudentAccount($conn, [
            'student_id' => $legacyStudent['student_id'],
            'aadhar' => $aadhar,
            'name' => $legacyStudent['name'],
            'email' => $legacyStudent['email'],
            'mobile' => $legacyStudent['mobile'],
            'password' => $legacyStudent['password'],
            'dob' => $legacyStudent['dob'] ?? null,
            'gender' => $legacyStudent['gender'] ?? 'Male',
        ]);

        if (!$accountId) {
            $lookup = $conn->prepare('SELECT id FROM student_accounts WHERE aadhar = ? OR student_id = ? LIMIT 1');
            if ($lookup) {
                $lookup->bind_param('ss', $aadhar, $legacyStudent['student_id']);
                $lookup->execute();
                $found = $lookup->get_result()->fetch_assoc();
                $lookup->close();
                if ($found) {
                    $accountId = (int)$found['id'];
                }
            }
        }

        if ($accountId && !empty($legacyStudent['id'])) {
            linkStudentRecordToAccount($conn, (int)$legacyStudent['id'], $accountId);
            $courseId = (int)($legacyStudent['course_id'] ?? 0);
            if ($courseId > 0) {
                $legacyScheme = normalizeEnrollmentSchemeId($legacyStudent['scheme_id'] ?? null);
                createStudentEnrollment($conn, $accountId, $courseId, (int)$legacyStudent['id'], $legacyStudent['status'] ?? 'pending', $legacyScheme);
            }
        }
        return $accountId;
    }
}
