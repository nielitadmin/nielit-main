<?php
/**
 * Dynamic module/semester results and certified-candidate tracking for NSQF courses.
 */

require_once __DIR__ . '/../batch_module/includes/batch_result_helper.php';

if (!function_exists('ensureNsqfExamResultsSchema')) {
    function ensureNsqfExamResultsSchema(mysqli $conn): bool
    {
        ensureBatchResultSchema($conn);

        $sqlComponents = "CREATE TABLE IF NOT EXISTS course_exam_components (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id INT NOT NULL,
            component_code VARCHAR(32) NOT NULL,
            component_label VARCHAR(120) NOT NULL,
            component_type ENUM('module','semester') NOT NULL DEFAULT 'module',
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            required_for_cert TINYINT(1) NOT NULL DEFAULT 1,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_course_code (course_id, component_code),
            KEY idx_course_active (course_id, is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($sqlComponents)) {
            return false;
        }

        $sqlResults = "CREATE TABLE IF NOT EXISTS batch_student_exam_results (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_student_id INT NOT NULL,
            component_id INT UNSIGNED NOT NULL,
            result_status VARCHAR(40) NOT NULL DEFAULT 'exam_not_applied',
            marked_certified TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            updated_by INT NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_bs_component (batch_student_id, component_id),
            KEY idx_component (component_id),
            KEY idx_status (result_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        return (bool) $conn->query($sqlResults);
    }
}

if (!function_exists('canManageNsqfExamResults')) {
    function canManageNsqfExamResults(?string $role): bool
    {
        return in_array((string) $role, [
            'master_admin',
            'nsqf_course_manager',
            'course_coordinator',
        ], true);
    }
}

if (!function_exists('canViewNsqfExamResults')) {
    function canViewNsqfExamResults(?string $role): bool
    {
        return canManageNsqfExamResults($role)
            || in_array((string) $role, ['front_office_desk', 'report_viewer'], true);
    }
}

if (!function_exists('nsqfExamResultStatusOptions')) {
    function nsqfExamResultStatusOptions(): array
    {
        return batch_result_status_options();
    }
}

if (!function_exists('nsqf_normalize_exam_result_status')) {
    function nsqf_normalize_exam_result_status($value): string
    {
        return batch_result_normalize_status($value);
    }
}

if (!function_exists('nsqf_default_olevel_components')) {
    /** @return array<int, array{code:string,label:string,type:string}> */
    function nsqf_default_olevel_components(): array
    {
        $labels = [
            'M1' => 'Module 1 (IT Tools)',
            'M2' => 'Module 2 (Web Design)',
            'M3' => 'Module 3 (Python)',
            'M4' => 'Module 4 (IoT)',
        ];
        $out = [];
        $i = 0;
        foreach ($labels as $code => $label) {
            $out[] = [
                'code' => $code,
                'label' => $label,
                'type' => 'module',
                'sort' => $i++,
            ];
        }
        return $out;
    }
}

if (!function_exists('nsqf_exam_course_access_clause')) {
    /**
     * @return array{sql:string, types:string, params:array<int,mixed>}|null null = no extra filter (master admin)
     */
    function nsqf_exam_course_access_clause(mysqli $conn, string $role, int $adminId): ?array
    {
        $role = (string) $role;
        if ($role === 'master_admin') {
            return null;
        }
        if ($role === 'nsqf_course_manager') {
            return ['sql' => ' AND c.is_nsqf = 1', 'types' => '', 'params' => []];
        }
        if ($role === 'course_coordinator' && $adminId > 0) {
            return [
                'sql' => ' AND c.id IN (
                    SELECT course_id FROM admin_course_assignments WHERE admin_id = ? AND is_active = 1
                )',
                'types' => 'i',
                'params' => [$adminId],
            ];
        }
        return ['sql' => ' AND 1=0', 'types' => '', 'params' => []];
    }
}

if (!function_exists('nsqf_exam_user_can_access_course')) {
    function nsqf_exam_user_can_access_course(mysqli $conn, string $role, int $adminId, int $courseId): bool
    {
        if ($courseId <= 0) {
            return false;
        }
        $clause = nsqf_exam_course_access_clause($conn, $role, $adminId);
        $sql = 'SELECT c.id FROM courses c WHERE c.id = ?';
        $types = 'i';
        $params = [$courseId];
        if ($clause !== null) {
            $sql .= $clause['sql'];
            $types .= $clause['types'];
            $params = array_merge($params, $clause['params']);
        }
        $sql .= ' LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('nsqf_list_exam_courses')) {
    /** @return array<int, array<string, mixed>> */
    function nsqf_list_exam_courses(mysqli $conn, string $role, int $adminId): array
    {
        ensureNsqfExamResultsSchema($conn);
        $clause = nsqf_exam_course_access_clause($conn, $role, $adminId);
        $sql = "SELECT c.id, c.course_name, c.course_code, c.is_nsqf
                FROM courses c
                WHERE 1=1";
        $types = '';
        $params = [];
        if ($role !== 'master_admin') {
            if ($clause === null || ($clause['sql'] ?? '') === ' AND 1=0') {
                return [];
            }
            $sql .= $clause['sql'];
            $types .= $clause['types'];
            $params = $clause['params'];
        }
        $sql .= ' ORDER BY c.course_name ASC';
        $stmt = $types !== '' ? $conn->prepare($sql) : $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
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

if (!function_exists('nsqf_get_course_exam_components')) {
    /** @return array<int, array<string, mixed>> */
    function nsqf_get_course_exam_components(mysqli $conn, int $courseId, bool $activeOnly = true): array
    {
        ensureNsqfExamResultsSchema($conn);
        if ($courseId <= 0) {
            return [];
        }
        $sql = 'SELECT * FROM course_exam_components WHERE course_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
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
}

if (!function_exists('nsqf_seed_olevel_components_if_empty')) {
    function nsqf_seed_olevel_components_if_empty(mysqli $conn, int $courseId): int
    {
        $existing = nsqf_get_course_exam_components($conn, $courseId, false);
        if ($existing !== []) {
            return 0;
        }
        return nsqf_save_course_exam_components($conn, $courseId, nsqf_default_olevel_components());
    }
}

if (!function_exists('nsqf_save_course_exam_components')) {
    /**
     * Replace active component set for a course.
     *
     * @param array<int, array<string, mixed>> $components
     */
    function nsqf_save_course_exam_components(mysqli $conn, int $courseId, array $components): int
    {
        ensureNsqfExamResultsSchema($conn);
        if ($courseId <= 0) {
            return 0;
        }
        $conn->query('UPDATE course_exam_components SET is_active = 0 WHERE course_id = ' . (int) $courseId);
        $saved = 0;
        $sort = 0;
        foreach ($components as $comp) {
            $code = strtoupper(trim((string) ($comp['code'] ?? $comp['component_code'] ?? '')));
            $label = trim((string) ($comp['label'] ?? $comp['component_label'] ?? $code));
            $type = strtolower(trim((string) ($comp['type'] ?? $comp['component_type'] ?? 'module')));
            if (!in_array($type, ['module', 'semester'], true)) {
                $type = 'module';
            }
            if ($code === '' || $label === '') {
                continue;
            }
            $required = !empty($comp['required_for_cert']) || !isset($comp['required_for_cert']) ? 1 : 0;
            $stmt = $conn->prepare(
                'INSERT INTO course_exam_components
                    (course_id, component_code, component_label, component_type, sort_order, required_for_cert, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE
                    component_label = VALUES(component_label),
                    component_type = VALUES(component_type),
                    sort_order = VALUES(sort_order),
                    required_for_cert = VALUES(required_for_cert),
                    is_active = 1'
            );
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('isssii', $courseId, $code, $label, $type, $sort, $required);
            if ($stmt->execute()) {
                $saved++;
            }
            $stmt->close();
            $sort++;
        }
        return $saved;
    }
}

if (!function_exists('nsqf_list_batches_for_exam_panel')) {
    /** @return array<int, array<string, mixed>> */
    function nsqf_list_batches_for_exam_panel(mysqli $conn, int $courseId, string $role, int $adminId): array
    {
        if ($courseId <= 0) {
            return [];
        }
        require_once __DIR__ . '/batch_assignment_helper.php';
        $hasCreated = $conn->query("SHOW COLUMNS FROM batches LIKE 'created_by'");
        $useCoord = $role === 'course_coordinator' && $adminId > 0 && $hasCreated && $hasCreated->num_rows > 0;
        if ($useCoord) {
            $sql = 'SELECT b.id, b.batch_name, b.batch_code, b.status
                    FROM batches b
                    WHERE b.course_id = ? AND ' . batch_assignment_coordinator_sql($conn, 'b') . '
                    ORDER BY b.id DESC';
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('iii', $courseId, $adminId, $adminId);
        } else {
            $sql = 'SELECT b.id, b.batch_name, b.batch_code, b.status FROM batches b WHERE b.course_id = ? ORDER BY b.id DESC';
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('i', $courseId);
        }
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

if (!function_exists('nsqf_get_batch_students_for_exam')) {
    /** @return array<int, array<string, mixed>> */
    function nsqf_get_batch_students_for_exam(mysqli $conn, int $batchId): array
    {
        if ($batchId <= 0) {
            return [];
        }
        require_once __DIR__ . '/../batch_module/includes/batch_functions.php';
        repairBatchStudentsJunction($conn, $batchId);
        $sql = "SELECT bs.id AS batch_student_id,
                       s.id AS student_record_id,
                       s.student_id, s.name, s.mobile,
                       bs.result_status AS overall_result_status
                FROM batch_students bs
                INNER JOIN students s ON s.id = COALESCE(bs.student_record_id, bs.student_id)
                WHERE bs.batch_id = ?
                ORDER BY s.name ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $batchId);
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

if (!function_exists('nsqf_get_batch_module_results_map')) {
    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    function nsqf_get_batch_module_results_map(mysqli $conn, int $batchId, array $componentIds): array
    {
        if ($batchId <= 0 || $componentIds === []) {
            return [];
        }
        $students = nsqf_get_batch_students_for_exam($conn, $batchId);
        $bsIds = array_values(array_filter(array_map(static fn ($r) => (int) ($r['batch_student_id'] ?? 0), $students)));
        if ($bsIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($bsIds), '?'));
        $sql = "SELECT r.batch_student_id, r.component_id, r.result_status, r.marked_certified
                FROM batch_student_exam_results r
                INNER JOIN batch_students bs ON bs.id = r.batch_student_id
                WHERE bs.batch_id = ? AND r.batch_student_id IN ({$placeholders})";
        $types = 'i' . str_repeat('i', count($bsIds));
        $params = array_merge([$batchId], $bsIds);
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $map = [];
        while ($row = $res->fetch_assoc()) {
            $bsId = (int) ($row['batch_student_id'] ?? 0);
            $compId = (int) ($row['component_id'] ?? 0);
            $map[$bsId][$compId] = $row;
        }
        $stmt->close();
        return $map;
    }
}

if (!function_exists('nsqf_save_batch_student_component_result')) {
    function nsqf_save_batch_student_component_result(
        mysqli $conn,
        int $batchId,
        int $batchStudentId,
        int $componentId,
        string $status,
        int $adminId,
        bool $syncOverall = true
    ): array {
        ensureNsqfExamResultsSchema($conn);
        if ($batchId <= 0 || $batchStudentId <= 0 || $componentId <= 0) {
            return ['success' => false, 'message' => 'Invalid batch, student, or module.'];
        }
        $chk = $conn->prepare(
            'SELECT bs.id FROM batch_students bs
             INNER JOIN course_exam_components cec ON cec.id = ?
             INNER JOIN batches b ON b.id = bs.batch_id AND b.course_id = cec.course_id
             WHERE bs.id = ? AND bs.batch_id = ? LIMIT 1'
        );
        if (!$chk) {
            return ['success' => false, 'message' => 'Could not validate enrollment.'];
        }
        $chk->bind_param('iii', $componentId, $batchStudentId, $batchId);
        $chk->execute();
        $valid = (bool) $chk->get_result()->fetch_row();
        $chk->close();
        if (!$valid) {
            return ['success' => false, 'message' => 'Module does not belong to this batch course.'];
        }
        $status = nsqf_normalize_exam_result_status($status);
        $markedCert = $status === 'pass' ? 1 : 0;
        $stmt = $conn->prepare(
            'INSERT INTO batch_student_exam_results
                (batch_student_id, component_id, result_status, marked_certified, updated_at, updated_by)
             VALUES (?, ?, ?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE
                result_status = VALUES(result_status),
                marked_certified = VALUES(marked_certified),
                updated_at = NOW(),
                updated_by = VALUES(updated_by)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => $conn->error ?: 'Save failed.'];
        }
        $stmt->bind_param('iisii', $batchStudentId, $componentId, $status, $markedCert, $adminId);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => $err ?: 'Save failed.'];
        }
        $stmt->close();
        if ($syncOverall) {
            nsqf_sync_overall_result_from_modules($conn, $batchId, $batchStudentId, $adminId);
        }
        $options = nsqfExamResultStatusOptions();
        return [
            'success' => true,
            'message' => 'Module result saved.',
            'result_status' => $status,
            'result_status_label' => $options[$status] ?? $status,
        ];
    }
}

if (!function_exists('nsqf_student_modules_certification_state')) {
    /**
     * @param array<int, array<string, mixed>> $components
     * @param array<int, array<string, mixed>> $resultsByComponent component_id => row
     * @return array{all_required_pass:bool,passed_count:int,required_count:int,pending:bool}
     */
    function nsqf_student_modules_certification_state(array $components, array $resultsByComponent): array
    {
        $required = 0;
        $passed = 0;
        $hasGap = false;
        foreach ($components as $comp) {
            if (empty($comp['required_for_cert'])) {
                continue;
            }
            $required++;
            $cid = (int) ($comp['id'] ?? 0);
            $st = nsqf_normalize_exam_result_status($resultsByComponent[$cid]['result_status'] ?? 'exam_not_applied');
            if ($st === 'pass') {
                $passed++;
            } else {
                $hasGap = true;
            }
        }
        if ($required === 0) {
            return ['all_required_pass' => false, 'passed_count' => $passed, 'required_count' => 0, 'pending' => true];
        }
        return [
            'all_required_pass' => !$hasGap && $passed === $required,
            'passed_count' => $passed,
            'required_count' => $required,
            'pending' => $hasGap,
        ];
    }
}

if (!function_exists('nsqf_sync_overall_result_from_modules')) {
    function nsqf_sync_overall_result_from_modules(mysqli $conn, int $batchId, int $batchStudentId, int $adminId): void
    {
        $bs = $conn->prepare('SELECT batch_id FROM batch_students WHERE id = ? LIMIT 1');
        if (!$bs) {
            return;
        }
        $bs->bind_param('i', $batchStudentId);
        $bs->execute();
        $bRow = $bs->get_result()->fetch_assoc();
        $bs->close();
        $batchId = (int) ($bRow['batch_id'] ?? $batchId);
        $bStmt = $conn->prepare('SELECT course_id FROM batches WHERE id = ? LIMIT 1');
        if (!$bStmt) {
            return;
        }
        $bStmt->bind_param('i', $batchId);
        $bStmt->execute();
        $batchRow = $bStmt->get_result()->fetch_assoc();
        $bStmt->close();
        $courseId = (int) ($batchRow['course_id'] ?? 0);
        $components = nsqf_get_course_exam_components($conn, $courseId);
        if ($components === []) {
            return;
        }
        $map = nsqf_get_batch_module_results_map($conn, $batchId, array_column($components, 'id'));
        $byComp = $map[$batchStudentId] ?? [];
        $state = nsqf_student_modules_certification_state($components, $byComp);
        $sStmt = $conn->prepare('SELECT student_record_id, student_id FROM batch_students WHERE id = ? LIMIT 1');
        if (!$sStmt) {
            return;
        }
        $sStmt->bind_param('i', $batchStudentId);
        $sStmt->execute();
        $sRow = $sStmt->get_result()->fetch_assoc();
        $sStmt->close();
        $recordId = (int) ($sRow['student_record_id'] ?? $sRow['student_id'] ?? 0);
        if ($recordId <= 0) {
            return;
        }
        if ($state['all_required_pass']) {
            saveBatchStudentResultStatus($conn, $batchId, $recordId, 'pass', $adminId);
        } elseif ($state['required_count'] > 0 && $state['passed_count'] === 0) {
            $anyFailed = false;
            foreach ($byComp as $r) {
                if (nsqf_normalize_exam_result_status($r['result_status'] ?? '') === 'failed') {
                    $anyFailed = true;
                    break;
                }
            }
            if ($anyFailed) {
                saveBatchStudentResultStatus($conn, $batchId, $recordId, 'failed', $adminId);
            }
        }
    }
}

if (!function_exists('nsqf_list_certified_candidates')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function nsqf_list_certified_candidates(
        mysqli $conn,
        int $courseId,
        int $batchId = 0,
        string $role = 'master_admin',
        int $adminId = 0
    ): array {
        $components = nsqf_get_course_exam_components($conn, $courseId);
        if ($components === []) {
            return [];
        }
        $batches = $batchId > 0
            ? [['id' => $batchId]]
            : nsqf_list_batches_for_exam_panel($conn, $courseId, $role, $adminId);
        $certified = [];
        foreach ($batches as $batch) {
            $bid = (int) ($batch['id'] ?? 0);
            $students = nsqf_get_batch_students_for_exam($conn, $bid);
            $map = nsqf_get_batch_module_results_map($conn, $bid, array_column($components, 'id'));
            foreach ($students as $stu) {
                $bsId = (int) ($stu['batch_student_id'] ?? 0);
                $state = nsqf_student_modules_certification_state($components, $map[$bsId] ?? []);
                if ($state['all_required_pass']) {
                    $certified[] = array_merge($stu, [
                        'batch_id' => $bid,
                        'passed_modules' => $state['passed_count'],
                        'required_modules' => $state['required_count'],
                    ]);
                }
            }
        }
        return $certified;
    }
}