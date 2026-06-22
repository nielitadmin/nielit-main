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

        return true;
    }

    function canManageBatchPlacement($role) {
        return in_array($role, ['master_admin', 'placement_coordinator'], true);
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
            placement_updated_by = ?
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

        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to save placement: ' . $error];
        }

        return [
            'success' => true,
            'message' => 'Placement details saved successfully.',
            'placement' => $data,
        ];
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
        ensureBatchPlacementSchema($conn);
        $student_id = trim((string) $student_id);
        if ($student_id === '' || !batch_placement_column_exists($conn, 'placement_status')) {
            return [];
        }

        $sql = "SELECT bs.placement_status, bs.placement_company, bs.placement_role,
                       bs.placement_package_amount, bs.placement_package_type,
                       bs.placement_location, bs.placement_date, bs.placement_remarks,
                       bs.placement_updated_at,
                       b.batch_name, b.batch_code, b.id AS batch_id,
                       COALESCE(c.course_name, b.batch_name, 'Course') AS course_name
                FROM batch_students bs
                INNER JOIN students s ON (bs.student_record_id = s.id OR bs.student_id = s.id)
                LEFT JOIN batches b ON b.id = bs.batch_id
                LEFT JOIN courses c ON c.id = b.course_id
                WHERE s.student_id = ?
                AND bs.placement_status IS NOT NULL
                AND bs.placement_status != ''
                AND bs.placement_status != 'not_placed'
                ORDER BY COALESCE(bs.placement_date, bs.placement_updated_at) DESC, bs.id DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}
