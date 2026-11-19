<?php
/**
 * Bulk-update existing students from Excel/CSV (Student Record Inspector).
 * Rows must include Student ID (or Aadhaar). Only non-empty cells update the database.
 */

require_once __DIR__ . '/student_inspector_list_import.php';

if (!function_exists('inspectorUpdateRecordsFromMatrix')) {
    function inspectorUpdateRecordsFromMatrix(array $matrix): array
    {
        $headerIndex = null;
        $map = null;
        $scanUntil = min(count($matrix), 25);
        for ($rowIndex = 0; $rowIndex < $scanUntil; $rowIndex++) {
            $labels = array_map('inspectorListHeaderKey', $matrix[$rowIndex]);
            $found = [];
            foreach ($labels as $index => $label) {
                $field = inspectorListHeaderField($label);
                if ($field !== null && !isset($found[$field])) {
                    $found[$field] = $index;
                }
            }
            $hasKey = isset($found['student_id']) || isset($found['aadhar']);
            if ($hasKey && (isset($found['name']) || $hasKey)) {
                $headerIndex = $rowIndex;
                $map = $found;
                break;
            }
        }

        $records = [];
        if ($map === null || $headerIndex === null) {
            return $records;
        }

        for ($i = $headerIndex + 1; $i < count($matrix); $i++) {
            $row = $matrix[$i];
            $record = inspectorListBlankRecord();
            foreach ($map as $field => $index) {
                $record = inspectorListAssignField($record, $field, (string) ($row[$index] ?? ''));
            }
            $sid = trim((string) ($record['student_id'] ?? ''));
            $aadhar = trim((string) ($record['aadhar'] ?? ''));
            if ($sid === '' && $aadhar === '') {
                continue;
            }
            $records[] = $record;
        }

        return $records;
    }
}

if (!function_exists('inspectorNormalizeEnrollmentStatus')) {
    function inspectorNormalizeEnrollmentStatus(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        $map = [
            'approved' => 'active',
            'active' => 'active',
            'pending' => 'pending',
            'rejected' => 'rejected',
            'inactive' => 'inactive',
        ];
        return $map[$value] ?? $value;
    }
}

if (!function_exists('inspectorFindEnrollmentRowsForUpdate')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function inspectorFindEnrollmentRowsForUpdate(mysqli $conn, array $record, int $courseId = 0): array
    {
        $rows = [];
        $studentId = trim((string) ($record['student_id'] ?? ''));
        $aadhar = trim((string) ($record['aadhar'] ?? ''));

        if ($studentId !== '') {
            $sql = 'SELECT id, student_id, name, course_id, account_id FROM students WHERE student_id = ?';
            $types = 's';
            $params = [$studentId];
            if ($courseId > 0) {
                $sql .= ' AND course_id = ?';
                $types .= 'i';
                $params[] = $courseId;
            }
            $sql .= ' ORDER BY id DESC';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
                $stmt->close();
            }
            return $rows;
        }

        if ($aadhar === '') {
            return $rows;
        }

        $sql = 'SELECT id, student_id, name, course_id, account_id FROM students WHERE aadhar = ?';
        $types = 's';
        $params = [$aadhar];
        if ($courseId > 0) {
            $sql .= ' AND course_id = ?';
            $types .= 'i';
            $params[] = $courseId;
        }
        $sql .= ' ORDER BY id DESC';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }

        return $rows;
    }
}

if (!function_exists('inspectorApplyRecordToEnrollment')) {
    function inspectorApplyRecordToEnrollment(
        mysqli $conn,
        int $recordId,
        array $record,
        bool $onlyFilledColumns
    ): array {
        $columnMap = [
            'name' => 'name',
            'father_name' => 'father_name',
            'mother_name' => 'mother_name',
            'dob' => 'dob',
            'age' => 'age',
            'mobile' => 'mobile',
            'email' => 'email',
            'aadhar' => 'aadhar',
            'gender' => 'gender',
            'religion' => 'religion',
            'marital_status' => 'marital_status',
            'category' => 'category',
            'nationality' => 'nationality',
            'position' => 'position',
            'state' => 'state',
            'city' => 'city',
            'pincode' => 'pincode',
            'address' => 'address',
            'college_name' => 'college_name',
            'nielit_registration_no' => 'nielit_registration_no',
            'status' => 'status',
        ];

        $optionalMap = [
            'apaar_id' => 'apaar_id',
            'pwd_status' => 'pwd_status',
        ];
        foreach ($optionalMap as $field => $column) {
            $check = $conn->query("SHOW COLUMNS FROM students LIKE '" . $conn->real_escape_string($column) . "'");
            if ($check && $check->num_rows > 0) {
                $columnMap[$field] = $column;
            }
        }

        $sets = [];
        $types = '';
        $values = [];

        foreach ($columnMap as $field => $column) {
            $raw = isset($record[$field]) ? trim((string) $record[$field]) : '';
            if ($onlyFilledColumns && $raw === '') {
                continue;
            }
            if ($field === 'dob' || $field === 'registration_date') {
                $raw = training_partner_parse_date($raw) ?? $raw;
            }
            if ($field === 'age' && $raw !== '') {
                $raw = (string) (int) preg_replace('/\D/', '', $raw);
            }
            if ($field === 'status') {
                $raw = inspectorNormalizeEnrollmentStatus($raw);
                if ($raw === '') {
                    continue;
                }
            }
            if ($onlyFilledColumns && $raw === '') {
                continue;
            }
            $sets[] = "`{$column}` = ?";
            $types .= 's';
            $values[] = $raw;
        }

        if ($sets === []) {
            return ['updated' => false, 'message' => 'No columns to update on this row.'];
        }

        $sql = 'UPDATE students SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $types .= 'i';
        $values[] = $recordId;
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['updated' => false, 'message' => 'Update failed: ' . $conn->error];
        }
        $stmt->bind_param($types, ...$values);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        if (!$ok) {
            return ['updated' => false, 'message' => 'Update failed: ' . $err];
        }

        if (function_exists('syncNielitRegistrationNoDefault') && trim((string) ($record['nielit_registration_no'] ?? '')) !== '') {
            syncNielitRegistrationNoDefault($conn, $recordId, null);
        }

        return ['updated' => true, 'message' => 'Updated enrollment #' . $recordId];
    }
}

if (!function_exists('inspectorSyncAccountFromRecord')) {
    function inspectorSyncAccountFromRecord(mysqli $conn, int $accountId, array $record, bool $onlyFilled): void
    {
        if ($accountId <= 0 || !function_exists('isMultiCourseSystemInstalled') || !isMultiCourseSystemInstalled($conn)) {
            return;
        }
        $fields = [
            'name' => trim((string) ($record['name'] ?? '')),
            'email' => trim((string) ($record['email'] ?? '')),
            'mobile' => trim((string) ($record['mobile'] ?? '')),
        ];
        $sets = [];
        $types = '';
        $values = [];
        foreach ($fields as $col => $val) {
            if ($onlyFilled && $val === '') {
                continue;
            }
            if (!$onlyFilled && $val === '') {
                continue;
            }
            $sets[] = "`{$col}` = ?";
            $types .= 's';
            $values[] = $val;
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE student_accounts SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $types .= 'i';
        $values[] = $accountId;
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('inspectorBulkUpdateStudentsFromExcel')) {
    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{message: string, type: string, rows: array<int, array<string, string>>}
     */
    function inspectorBulkUpdateStudentsFromExcel(mysqli $conn, array $post, array $files): array
    {
        $courseId = (int) ($post['update_course_id'] ?? 0);
        $onlyFilled = !isset($post['update_overwrite_blanks']) || (string) $post['update_overwrite_blanks'] !== '1';

        $upload = inspectorListMatricesFromUpload($files['student_update_file'] ?? []);
        if (!empty($upload['error'])) {
            return ['message' => (string) $upload['error'], 'type' => 'danger', 'rows' => []];
        }
        $records = [];
        foreach ($upload['matrices'] as $matrix) {
            if (!is_array($matrix)) {
                continue;
            }
            foreach (inspectorUpdateRecordsFromMatrix($matrix) as $record) {
                $records[] = $record;
            }
        }
        if ($records === []) {
            return [
                'message' => 'No update rows found. The sheet needs a header row with Student ID (or Aadhaar) plus the columns you want to change.',
                'type' => 'warning',
                'rows' => [],
            ];
        }
        if (count($records) > 2500) {
            return ['message' => 'Maximum 2500 rows per upload. Split the sheet and try again.', 'type' => 'warning', 'rows' => []];
        }

        $updatedEnrollments = 0;
        $skipped = 0;
        $rows = [];

        foreach ($records as $record) {
            $key = trim((string) ($record['student_id'] ?? ''));
            if ($key === '') {
                $key = 'Aadhaar ' . substr(trim((string) ($record['aadhar'] ?? '')), -4);
            }
            $enrollments = inspectorFindEnrollmentRowsForUpdate($conn, $record, $courseId);
            if ($enrollments === []) {
                $skipped++;
                $rows[] = [
                    'key' => $key,
                    'name' => (string) ($record['name'] ?? ''),
                    'result' => $courseId > 0
                        ? 'No enrollment found for this Student ID in the selected course.'
                        : 'No enrollment found for this Student ID / Aadhaar.',
                ];
                continue;
            }

            $rowUpdated = 0;
            $syncedAccounts = [];
            foreach ($enrollments as $enrollment) {
                $recordId = (int) ($enrollment['id'] ?? 0);
                if ($recordId <= 0) {
                    continue;
                }
                $apply = inspectorApplyRecordToEnrollment($conn, $recordId, $record, $onlyFilled);
                if (!empty($apply['updated'])) {
                    $rowUpdated++;
                    $updatedEnrollments++;
                    $accountId = (int) ($enrollment['account_id'] ?? 0);
                    if ($accountId > 0 && !isset($syncedAccounts[$accountId])) {
                        inspectorSyncAccountFromRecord($conn, $accountId, $record, $onlyFilled);
                        $syncedAccounts[$accountId] = true;
                    }
                }
            }

            if ($rowUpdated <= 0) {
                $skipped++;
                $rows[] = [
                    'key' => $key,
                    'name' => (string) ($record['name'] ?? ''),
                    'result' => $apply['message'] ?? 'Nothing updated.',
                ];
            } else {
                $rows[] = [
                    'key' => $key,
                    'name' => (string) ($record['name'] ?? ''),
                    'result' => 'Updated ' . $rowUpdated . ' enrollment row' . ($rowUpdated === 1 ? '' : 's') . '.',
                ];
            }
        }

        if ($updatedEnrollments > 0 && file_exists(__DIR__ . '/../../includes/activity_logger.php')) {
            require_once __DIR__ . '/../../includes/activity_logger.php';
            if (function_exists('logStudentAdminActivity')) {
                logStudentAdminActivity(
                    $conn,
                    'student_excel_update',
                    'Updated ' . $updatedEnrollments . ' enrollment row(s) from Excel upload.',
                    $records[0]['student_id'] ?? '',
                    'bulk excel update',
                    [
                        'source' => 'student_record_inspector',
                        'updated_enrollments' => $updatedEnrollments,
                        'skipped' => $skipped,
                        'course_filter' => $courseId,
                        'only_filled_columns' => $onlyFilled,
                    ]
                );
            }
        }

        if ($updatedEnrollments === 0) {
            return [
                'message' => 'No records were updated. Check Student IDs and that columns match the template.',
                'type' => 'warning',
                'rows' => $rows,
            ];
        }

        $message = $updatedEnrollments . ' enrollment row' . ($updatedEnrollments === 1 ? '' : 's') . ' updated from the sheet.';
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' row' . ($skipped === 1 ? '' : 's') . ' skipped or not found.';
        }

        return [
            'message' => $message,
            'type' => $skipped > 0 ? 'warning' : 'success',
            'rows' => $rows,
        ];
    }
}