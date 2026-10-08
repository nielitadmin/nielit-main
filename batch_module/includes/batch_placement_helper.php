<?php
/**
 * Batch student placement tracking — company, role, package, location.
 */

if (!function_exists('batch_placement_status_options')) {
    function batch_placement_status_options() {
        return [
            'not_placed' => 'Not placed',
            'in_process' => 'In process',
            'placed' => 'Placed',
            'higher_studies' => 'Higher studies',
        ];
    }

    function batch_placement_package_type_options() {
        return [
            'annual' => 'Annual (LPA)',
            'monthly' => 'Monthly',
        ];
    }

    function batch_placement_column_exists($conn, $column) {
        $column = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM batch_students LIKE '{$column}'");
        return $result && $result->num_rows > 0;
    }

    function ensureBatchPlacementSchema($conn) {
        $check = $conn->query("SHOW TABLES LIKE 'batch_students'");
        if (!$check || $check->num_rows === 0) {
            return false;
        }

        $after = batch_placement_column_exists($conn, 'certificate_uploaded_by')
            ? 'certificate_uploaded_by'
            : (batch_placement_column_exists($conn, 'attendance_percentage')
                ? 'attendance_percentage'
                : null);

        $columns = [
            'placement_status' => "VARCHAR(40) NOT NULL DEFAULT 'not_placed'",
            'placement_company' => 'VARCHAR(255) NULL DEFAULT NULL',
            'placement_role' => 'VARCHAR(255) NULL DEFAULT NULL',
            'placement_package_amount' => 'DECIMAL(12,2) NULL DEFAULT NULL',
            'placement_package_type' => "VARCHAR(20) NOT NULL DEFAULT 'annual'",
            'placement_location' => 'VARCHAR(255) NULL DEFAULT NULL',
            'placement_date' => 'DATE NULL DEFAULT NULL',
            'placement_remarks' => 'TEXT NULL DEFAULT NULL',
            'placement_updated_at' => 'TIMESTAMP NULL DEFAULT NULL',
            'placement_updated_by' => 'INT NULL DEFAULT NULL',
        ];

        foreach ($columns as $name => $definition) {
            if (batch_placement_column_exists($conn, $name)) {
                continue;
            }
            $afterClause = $after ? " AFTER `{$after}`" : '';
            $conn->query("ALTER TABLE batch_students ADD COLUMN `{$name}` {$definition}{$afterClause}");
            $after = $name;
        }

        ensureStudentPlacementSchema($conn);
        return true;
    }

    function student_placement_column_exists($conn, $column) {
        $column = $conn->real_escape_string($column);
        $result = @$conn->query("SHOW COLUMNS FROM students LIKE '{$column}'");
        return $result && $result->num_rows > 0;
    }

    function ensureStudentPlacementSchema($conn) {
        $check = @$conn->query("SHOW TABLES LIKE 'students'");
        if (!$check || $check->num_rows === 0) {
            return false;
        }

        $columns = [
            'placement_status' => "VARCHAR(40) NOT NULL DEFAULT 'not_placed'",
            'placement_company' => 'VARCHAR(255) NULL DEFAULT NULL',
            'placement_role' => 'VARCHAR(255) NULL DEFAULT NULL',
            'placement_package_amount' => 'DECIMAL(12,2) NULL DEFAULT NULL',
            'placement_package_type' => "VARCHAR(20) NOT NULL DEFAULT 'annual'",
            'placement_location' => 'VARCHAR(255) NULL DEFAULT NULL',
            'placement_date' => 'DATE NULL DEFAULT NULL',
            'placement_remarks' => 'TEXT NULL DEFAULT NULL',
            'placement_updated_at' => 'TIMESTAMP NULL DEFAULT NULL',
            'placement_updated_by' => 'INT NULL DEFAULT NULL',
            'placement_verification_status' => "VARCHAR(20) NOT NULL DEFAULT 'none'",
            'placement_pending_json' => 'TEXT NULL DEFAULT NULL',
            'placement_rejection_note' => 'TEXT NULL DEFAULT NULL',
            'placement_verified_by' => 'INT NULL DEFAULT NULL',
            'placement_verified_at' => 'DATETIME NULL DEFAULT NULL',
        ];

        $after = student_placement_column_exists($conn, 'status') ? 'status' : null;
        foreach ($columns as $name => $definition) {
            if (student_placement_column_exists($conn, $name)) {
                continue;
            }
            $afterClause = $after ? " AFTER `{$after}`" : '';
            @$conn->query("ALTER TABLE students ADD COLUMN `{$name}` {$definition}{$afterClause}");
            $after = $name;
        }

        return true;
    }

    function batch_placement_decode_pending($json) {
        $raw = trim((string) $json);
        if ($raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    function batch_placement_encode_pending(array $data) {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    function canManageBatchPlacement($role) {
        return in_array($role, [
            'master_admin',
            'course_coordinator',
            'placement_coordinator',
        ], true);
    }

    function canViewBatchPlacements($role) {
        return in_array($role, [
            'master_admin',
            'placement_coordinator',
            'course_coordinator',
        ], true);
    }

    function batch_placement_select_sql() {
        if (!function_exists('batch_placement_column_exists')) {
            return '';
        }
        return ', bs.placement_status, bs.placement_company, bs.placement_role,
                bs.placement_package_amount, bs.placement_package_type,
                bs.placement_location, bs.placement_date, bs.placement_remarks,
                bs.placement_updated_at';
    }

    function batch_placement_get_batch_student_row($conn, $batch_id, $student_record_id) {
        require_once __DIR__ . '/batch_functions.php';
        repairBatchStudentsJunction($conn, (int) $batch_id);

        $batch_id = (int) $batch_id;
        $student_record_id = (int) $student_record_id;
        if ($batch_id <= 0 || $student_record_id <= 0) {
            return null;
        }

        $sql = "SELECT bs.id AS batch_student_id, bs.placement_status, bs.placement_company,
                       bs.placement_role, bs.placement_package_amount, bs.placement_package_type,
                       bs.placement_location, bs.placement_date, bs.placement_remarks,
                       s.id AS student_record_id, s.student_id, s.name
                FROM students s
                LEFT JOIN batch_students bs ON bs.batch_id = ? AND (bs.student_record_id = s.id OR bs.student_id = s.id)
                WHERE s.id = ?
                AND (s.batch_id = ? OR bs.id IS NOT NULL)
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('iii', $batch_id, $student_record_id, $batch_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    function batch_placement_normalize_input(array $input) {
        $statuses = array_keys(batch_placement_status_options());
        $packageTypes = array_keys(batch_placement_package_type_options());

        $status = strtolower(trim((string) ($input['placement_status'] ?? 'not_placed')));
        if (!in_array($status, $statuses, true)) {
            $status = 'not_placed';
        }

        $packageType = strtolower(trim((string) ($input['placement_package_type'] ?? 'annual')));
        if (!in_array($packageType, $packageTypes, true)) {
            $packageType = 'annual';
        }

        $amountRaw = trim((string) ($input['placement_package_amount'] ?? ''));
        $amount = $amountRaw === '' ? null : (float) $amountRaw;

        $dateRaw = trim((string) ($input['placement_date'] ?? ''));
        $placementDate = null;
        if ($dateRaw !== '') {
            $ts = strtotime($dateRaw);
            if ($ts !== false) {
                $placementDate = date('Y-m-d', $ts);
            }
        }

        return [
            'placement_status' => $status,
            'placement_company' => trim((string) ($input['placement_company'] ?? '')),
            'placement_role' => trim((string) ($input['placement_role'] ?? '')),
            'placement_package_amount' => $amount,
            'placement_package_type' => $packageType,
            'placement_location' => trim((string) ($input['placement_location'] ?? '')),
            'placement_date' => $placementDate,
            'placement_remarks' => trim((string) ($input['placement_remarks'] ?? '')),
        ];
    }

    function batch_placement_validate_for_status(array $data) {
        if ($data['placement_status'] !== 'placed') {
            return ['valid' => true, 'message' => ''];
        }
        if ($data['placement_company'] === '') {
            return ['valid' => false, 'message' => 'Company name is required when status is Placed.'];
        }
        return ['valid' => true, 'message' => ''];
    }

    function saveBatchStudentPlacement($conn, $batch_id, $student_record_id, array $input, $admin_id) {
        ensureBatchPlacementSchema($conn);
        require_once __DIR__ . '/batch_functions.php';

        $batch_id = (int) $batch_id;
        $student_record_id = (int) $student_record_id;
        $admin_id = (int) $admin_id;

        if ($batch_id <= 0 || $student_record_id <= 0) {
            return ['success' => false, 'message' => 'Invalid batch or student.'];
        }

        $studentRow = batch_placement_get_batch_student_row($conn, $batch_id, $student_record_id);
        if (!$studentRow) {
            return ['success' => false, 'message' => 'Student is not enrolled in this batch.'];
        }

        $batchStudentId = (int) ($studentRow['batch_student_id'] ?? 0);
        if ($batchStudentId <= 0) {
            repairBatchStudentsJunction($conn, $batch_id);
            $studentRow = batch_placement_get_batch_student_row($conn, $batch_id, $student_record_id);
            $batchStudentId = (int) ($studentRow['batch_student_id'] ?? 0);
        }
        if ($batchStudentId <= 0) {
            return ['success' => false, 'message' => 'Could not locate batch enrollment record.'];
        }

        $data = batch_placement_normalize_input($input);
        $validation = batch_placement_validate_for_status($data);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        $updatedBySql = $admin_id > 0 ? 'placement_updated_by = ?' : 'placement_updated_by = NULL';
        $sql = "UPDATE batch_students SET
            placement_status = ?,
            placement_company = ?,
            placement_role = ?,
            placement_package_amount = ?,
            placement_package_type = ?,
            placement_location = ?,
            placement_date = ?,
            placement_remarks = ?,
            placement_updated_at = NOW(),
            {$updatedBySql}
            WHERE id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error while saving placement.'];
        }

        $company = $data['placement_company'] !== '' ? $data['placement_company'] : null;
        $role = $data['placement_role'] !== '' ? $data['placement_role'] : null;
        $location = $data['placement_location'] !== '' ? $data['placement_location'] : null;
        $remarks = $data['placement_remarks'] !== '' ? $data['placement_remarks'] : null;
        $status = $data['placement_status'];
        $packageType = $data['placement_package_type'];
        $placementDate = $data['placement_date'];
        $amount = $data['placement_package_amount'];
        $amountParam = $amount === null ? null : (string) $amount;

        if ($admin_id > 0) {
            $stmt->bind_param(
                'ssssssssii',
                $status,
                $company,
                $role,
                $amountParam,
                $packageType,
                $location,
                $placementDate,
                $remarks,
                $admin_id,
                $batchStudentId
            );
        } else {
            $stmt->bind_param(
                'ssssssssi',
                $status,
                $company,
                $role,
                $amountParam,
                $packageType,
                $location,
                $placementDate,
                $remarks,
                $batchStudentId
            );
        }

        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to save placement: ' . $error];
        }

        applyOfficialPlacementToStudentRecord($conn, $student_record_id, $data, $admin_id, 'approved');

        return [
            'success' => true,
            'message' => 'Placement details saved successfully.',
            'placement' => $data,
        ];
    }

    function applyOfficialPlacementToStudentRecord($conn, $student_record_id, array $data, $admin_id = 0, $verification = 'approved') {
        ensureStudentPlacementSchema($conn);
        $student_record_id = (int) $student_record_id;
        if ($student_record_id <= 0 || !student_placement_column_exists($conn, 'placement_status')) {
            return false;
        }

        $admin_id = (int) $admin_id;
        $company = $data['placement_company'] !== '' ? $data['placement_company'] : null;
        $role = $data['placement_role'] !== '' ? $data['placement_role'] : null;
        $location = $data['placement_location'] !== '' ? $data['placement_location'] : null;
        $remarks = $data['placement_remarks'] !== '' ? $data['placement_remarks'] : null;
        $status = $data['placement_status'];
        $packageType = $data['placement_package_type'];
        $placementDate = $data['placement_date'];
        $amountParam = $data['placement_package_amount'] === null ? null : (string) $data['placement_package_amount'];
        $updatedBy = $admin_id > 0 ? $admin_id : null;
        $verifiedBy = $admin_id > 0 ? $admin_id : null;

        $sql = "UPDATE students SET
                    placement_status = ?,
                    placement_company = ?,
                    placement_role = ?,
                    placement_package_amount = ?,
                    placement_package_type = ?,
                    placement_location = ?,
                    placement_date = ?,
                    placement_remarks = ?,
                    placement_updated_at = NOW(),
                    placement_updated_by = ?,
                    placement_verification_status = ?,
                    placement_pending_json = NULL,
                    placement_rejection_note = NULL,
                    placement_verified_by = ?,
                    placement_verified_at = NOW()
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param(
            'ssssssssisii',
            $status,
            $company,
            $role,
            $amountParam,
            $packageType,
            $location,
            $placementDate,
            $remarks,
            $updatedBy,
            $verification,
            $verifiedBy,
            $student_record_id
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function getBatchPlacementStats($conn, $batch_id) {
        ensureBatchPlacementSchema($conn);
        $batch_id = (int) $batch_id;
        $stats = [
            'total' => 0,
            'placed' => 0,
            'in_process' => 0,
            'not_placed' => 0,
            'higher_studies' => 0,
        ];

        if ($batch_id <= 0 || !batch_placement_column_exists($conn, 'placement_status')) {
            return $stats;
        }

        require_once __DIR__ . '/batch_functions.php';
        $students = getBatchStudents($batch_id, $conn);
        $stats['total'] = count($students);

        foreach ($students as $student) {
            $status = strtolower(trim((string) ($student['placement_status'] ?? 'not_placed')));
            if ($status === 'placed') {
                $stats['placed']++;
            } elseif ($status === 'in_process') {
                $stats['in_process']++;
            } elseif ($status === 'higher_studies') {
                $stats['higher_studies']++;
            } else {
                $stats['not_placed']++;
            }
        }

        return $stats;
    }

    function batch_placement_status_badge_class($status) {
        $status = strtolower(trim((string) $status));
        switch ($status) {
            case 'placed':
                return 'success';
            case 'in_process':
                return 'warning';
            case 'higher_studies':
                return 'info';
            default:
                return 'secondary';
        }
    }

    function batch_placement_format_package($amount, $type) {
        if ($amount === null || $amount === '') {
            return '';
        }
        $formatted = number_format((float) $amount, 2);
        if ($type === 'monthly') {
            return '₹' . $formatted . '/month';
        }
        return '₹' . $formatted . ' LPA';
    }

    function getStudentPortalPlacements($conn, $student_id) {
        $rows = getStudentPlacementEnrollments($conn, $student_id);
        return array_values(array_filter($rows, static function ($row) {
            $status = strtolower(trim((string) ($row['official_placement_status'] ?? $row['placement_status'] ?? 'not_placed')));
            $verification = strtolower(trim((string) ($row['placement_verification_status'] ?? 'none')));
            return $verification === 'approved' && $status !== '' && $status !== 'not_placed';
        }));
    }

    /**
     * All batch enrollments a student may view or self-update in the portal.
     *
     * @return array<int, array<string, mixed>>
     */
    function getStudentPlacementEnrollments($conn, $student_id) {
        ensureBatchPlacementSchema($conn);
        ensureStudentPlacementSchema($conn);
        $student_id = trim((string) $student_id);
        if ($student_id === '') {
            return [];
        }

        $hasStudentPlacement = student_placement_column_exists($conn, 'placement_status');
        $hasVerification = student_placement_column_exists($conn, 'placement_verification_status');
        $hasPending = student_placement_column_exists($conn, 'placement_pending_json');
        $hasBatchPlacement = batch_placement_column_exists($conn, 'placement_status');

        $studentCols = $hasStudentPlacement
            ? "s.placement_status AS s_placement_status, s.placement_company AS s_placement_company,
               s.placement_role AS s_placement_role, s.placement_package_amount AS s_placement_package_amount,
               s.placement_package_type AS s_placement_package_type, s.placement_location AS s_placement_location,
               s.placement_date AS s_placement_date, s.placement_remarks AS s_placement_remarks,
               s.placement_updated_at AS s_placement_updated_at"
            : "NULL AS s_placement_status, NULL AS s_placement_company, NULL AS s_placement_role,
               NULL AS s_placement_package_amount, NULL AS s_placement_package_type, NULL AS s_placement_location,
               NULL AS s_placement_date, NULL AS s_placement_remarks, NULL AS s_placement_updated_at";

        $verifyCols = $hasVerification
            ? "s.placement_verification_status, s.placement_rejection_note, s.placement_verified_at"
            : "'none' AS placement_verification_status, NULL AS placement_rejection_note, NULL AS placement_verified_at";
        $pendingCols = $hasPending ? "s.placement_pending_json" : "NULL AS placement_pending_json";

        $batchCols = $hasBatchPlacement
            ? "bs.id AS batch_student_id, bs.placement_status AS bs_placement_status,
               bs.placement_company AS bs_placement_company, bs.placement_role AS bs_placement_role,
               bs.placement_package_amount AS bs_placement_package_amount,
               bs.placement_package_type AS bs_placement_package_type,
               bs.placement_location AS bs_placement_location, bs.placement_date AS bs_placement_date,
               bs.placement_remarks AS bs_placement_remarks, bs.placement_updated_at AS bs_placement_updated_at"
            : "NULL AS batch_student_id, NULL AS bs_placement_status, NULL AS bs_placement_company,
               NULL AS bs_placement_role, NULL AS bs_placement_package_amount, NULL AS bs_placement_package_type,
               NULL AS bs_placement_location, NULL AS bs_placement_date, NULL AS bs_placement_remarks,
               NULL AS bs_placement_updated_at";

        $sql = "SELECT s.id AS student_record_id, s.student_id, s.name, s.batch_id, s.course_id,
                       {$studentCols}, {$verifyCols}, {$pendingCols}, {$batchCols},
                       b.batch_name, b.batch_code,
                       COALESCE(c.course_name, b.batch_name, 'Course') AS course_name
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
                LEFT JOIN batches b ON b.id = s.batch_id
                LEFT JOIN batch_students bs ON (
                    (s.batch_id IS NOT NULL AND s.batch_id > 0 AND bs.batch_id = s.batch_id
                        AND (bs.student_record_id = s.id OR bs.student_id = s.id))
                )
                WHERE s.student_id = ?
                AND LOWER(COALESCE(s.status, '')) NOT IN ('rejected', 'inactive')
                ORDER BY c.course_name ASC, s.id DESC";

        $rows = [];
        $seen = [];
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recordId = (int) ($row['student_record_id'] ?? 0);
            if ($recordId <= 0 || isset($seen[$recordId])) {
                continue;
            }
            $seen[$recordId] = true;

            $officialStatus = strtolower(trim((string) ($row['s_placement_status'] ?? '')));
            if ($officialStatus === '') {
                $officialStatus = strtolower(trim((string) ($row['bs_placement_status'] ?? 'not_placed')));
            }
            if ($officialStatus === '') {
                $officialStatus = 'not_placed';
            }

            $verification = strtolower(trim((string) ($row['placement_verification_status'] ?? 'none')));
            if ($verification === '') {
                $verification = 'none';
            }
            $pending = batch_placement_decode_pending($row['placement_pending_json'] ?? '');

            $display = [
                'placement_status' => $officialStatus,
                'placement_company' => $row['s_placement_company'] ?? $row['bs_placement_company'] ?? null,
                'placement_role' => $row['s_placement_role'] ?? $row['bs_placement_role'] ?? null,
                'placement_package_amount' => $row['s_placement_package_amount'] ?? $row['bs_placement_package_amount'] ?? null,
                'placement_package_type' => $row['s_placement_package_type'] ?? $row['bs_placement_package_type'] ?? 'annual',
                'placement_location' => $row['s_placement_location'] ?? $row['bs_placement_location'] ?? null,
                'placement_date' => $row['s_placement_date'] ?? $row['bs_placement_date'] ?? null,
                'placement_remarks' => $row['s_placement_remarks'] ?? $row['bs_placement_remarks'] ?? null,
            ];
            if ($verification === 'pending' && is_array($pending)) {
                $display = array_merge($display, $pending);
            }

            $rows[] = array_merge($display, [
                'batch_student_id' => (int) ($row['batch_student_id'] ?? 0),
                'batch_id' => (int) ($row['batch_id'] ?? 0),
                'student_record_id' => $recordId,
                'student_login_id' => (string) ($row['student_id'] ?? ''),
                'student_name' => (string) ($row['name'] ?? ''),
                'course_id' => (int) ($row['course_id'] ?? 0),
                'placement_updated_at' => $row['s_placement_updated_at'] ?? $row['bs_placement_updated_at'] ?? null,
                'batch_name' => $row['batch_name'] ?? '',
                'batch_code' => $row['batch_code'] ?? '',
                'course_name' => $row['course_name'] ?? 'Course',
                'placement_verification_status' => $verification,
                'placement_rejection_note' => $row['placement_rejection_note'] ?? '',
                'placement_verified_at' => $row['placement_verified_at'] ?? null,
                'placement_pending' => $pending,
                'official_placement_status' => $officialStatus,
            ]);
        }
        $stmt->close();

        return $rows;
    }

    function saveStudentSelfPlacement($conn, $student_login_id, $batch_id, $student_record_id, array $input) {
        ensureStudentPlacementSchema($conn);
        $student_login_id = trim((string) $student_login_id);
        $student_record_id = (int) $student_record_id;

        if ($student_login_id === '' || $student_record_id <= 0) {
            return ['success' => false, 'message' => 'Invalid placement request.'];
        }

        $stmt = $conn->prepare('SELECT id, batch_id FROM students WHERE id = ? AND student_id = ? LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('is', $student_record_id, $student_login_id);
        $stmt->execute();
        $owned = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$owned) {
            return ['success' => false, 'message' => 'You can only update your own placement details.'];
        }

        $data = batch_placement_normalize_input($input);
        $validation = batch_placement_validate_for_status($data);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        $pendingJson = batch_placement_encode_pending($data);
        if (!student_placement_column_exists($conn, 'placement_pending_json')) {
            return ['success' => false, 'message' => 'Placement verification is not ready. Please contact the administrator.'];
        }

        $upd = $conn->prepare(
            "UPDATE students SET
                placement_pending_json = ?,
                placement_verification_status = 'pending',
                placement_rejection_note = NULL,
                placement_updated_at = NOW()
             WHERE id = ?"
        );
        if (!$upd) {
            return ['success' => false, 'message' => 'Could not submit placement for verification.'];
        }
        $upd->bind_param('si', $pendingJson, $student_record_id);
        $ok = $upd->execute();
        $upd->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Could not submit placement for verification.'];
        }

        return [
            'success' => true,
            'message' => 'Submitted for placement officer verification. Status will update after approval.',
            'placement' => $data,
            'verification_status' => 'pending',
        ];
    }

    function listPendingStudentPlacements($conn) {
        ensureStudentPlacementSchema($conn);
        if (!student_placement_column_exists($conn, 'placement_verification_status')) {
            return [];
        }

        $sql = "SELECT s.id AS student_record_id, s.student_id, s.name, s.batch_id, s.course_id,
                       s.placement_verification_status, s.placement_pending_json, s.placement_updated_at,
                       b.batch_name, b.batch_code,
                       COALESCE(c.course_name, b.batch_name, 'Course') AS course_name
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
                LEFT JOIN batches b ON b.id = s.batch_id
                WHERE s.placement_verification_status = 'pending'
                AND LOWER(COALESCE(s.status, '')) NOT IN ('rejected', 'inactive')
                ORDER BY s.placement_updated_at DESC, s.id DESC";
        $result = $conn->query($sql);
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $pending = batch_placement_decode_pending($row['placement_pending_json'] ?? '');
                $row['placement_pending'] = $pending;
                $row['placement_status'] = $pending['placement_status'] ?? 'not_placed';
                $row['placement_company'] = $pending['placement_company'] ?? '';
                $row['placement_role'] = $pending['placement_role'] ?? '';
                $row['placement_package_amount'] = $pending['placement_package_amount'] ?? null;
                $row['placement_package_type'] = $pending['placement_package_type'] ?? 'annual';
                $row['placement_location'] = $pending['placement_location'] ?? '';
                $row['placement_date'] = $pending['placement_date'] ?? '';
                $row['placement_remarks'] = $pending['placement_remarks'] ?? '';
                $rows[] = $row;
            }
        }
        return $rows;
    }

    function verifyStudentPlacement($conn, $student_record_id, $approve, $admin_id, $note = '') {
        ensureStudentPlacementSchema($conn);
        $student_record_id = (int) $student_record_id;
        $admin_id = (int) $admin_id;
        $approve = (bool) $approve;
        $note = trim((string) $note);

        if ($student_record_id <= 0) {
            return ['success' => false, 'message' => 'Invalid student record.'];
        }

        $stmt = $conn->prepare('SELECT id, batch_id, placement_pending_json, placement_verification_status FROM students WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('i', $student_record_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return ['success' => false, 'message' => 'Student enrollment not found.'];
        }

        if (!$approve) {
            $upd = $conn->prepare(
                "UPDATE students SET
                    placement_verification_status = 'rejected',
                    placement_rejection_note = ?,
                    placement_verified_by = ?,
                    placement_verified_at = NOW()
                 WHERE id = ?"
            );
            if (!$upd) {
                return ['success' => false, 'message' => 'Could not reject placement.'];
            }
            $upd->bind_param('sii', $note, $admin_id, $student_record_id);
            $upd->execute();
            $upd->close();
            return ['success' => true, 'message' => 'Placement submission rejected. The student can update and resubmit.'];
        }

        $pending = batch_placement_decode_pending($row['placement_pending_json'] ?? '');
        if (!is_array($pending)) {
            return ['success' => false, 'message' => 'No pending placement details to approve.'];
        }
        $data = batch_placement_normalize_input($pending);
        $batchId = (int) ($row['batch_id'] ?? 0);
        if ($batchId > 0) {
            $saved = saveBatchStudentPlacement($conn, $batchId, $student_record_id, $data, $admin_id);
            if (!empty($saved['success'])) {
                return ['success' => true, 'message' => 'Placement verified and updated.'];
            }
        }

        applyOfficialPlacementToStudentRecord($conn, $student_record_id, $data, $admin_id, 'approved');
        return ['success' => true, 'message' => 'Placement verified and updated.'];
    }
}
