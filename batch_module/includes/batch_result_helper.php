<?php
/**
 * Batch student exam / result status tracking.
 */

if (!function_exists('batch_result_status_options')) {
    function batch_result_status_options(): array
    {
        return [
            'exam_not_applied' => 'Exam-not applied',
            'absent' => 'Absent',
            'pass' => 'Pass / Certified',
            'failed' => 'Failed',
        ];
    }

    function batch_result_column_exists($conn, string $column): bool
    {
        $column = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM batch_students LIKE '{$column}'");
        return $result && $result->num_rows > 0;
    }

    function ensureBatchResultSchema($conn): bool
    {
        $check = $conn->query("SHOW TABLES LIKE 'batch_students'");
        if (!$check || $check->num_rows === 0) {
            return false;
        }

        $after = null;
        foreach (['placement_updated_by', 'certificate_uploaded_by', 'attendance_percentage'] as $candidate) {
            if (batch_result_column_exists($conn, $candidate)) {
                $after = $candidate;
                break;
            }
        }

        $columns = [
            'result_status' => "VARCHAR(40) NOT NULL DEFAULT 'exam_not_applied'",
            'result_updated_at' => 'TIMESTAMP NULL DEFAULT NULL',
            'result_updated_by' => 'INT NULL DEFAULT NULL',
        ];

        foreach ($columns as $name => $definition) {
            if (batch_result_column_exists($conn, $name)) {
                continue;
            }
            $afterClause = $after ? " AFTER `{$after}`" : '';
            $conn->query("ALTER TABLE batch_students ADD COLUMN `{$name}` {$definition}{$afterClause}");
            $after = $name;
        }

        return true;
    }

    function canManageBatchResultStatus(?string $role): bool
    {
        return in_array((string) $role, [
            'master_admin',
            'course_coordinator',
            'placement_coordinator',
        ], true);
    }

    function canViewBatchResultStatus(?string $role): bool
    {
        return in_array((string) $role, [
            'master_admin',
            'course_coordinator',
            'placement_coordinator',
            'front_office_desk',
        ], true);
    }

    function batch_result_status_badge_class(string $status): string
    {
        switch (strtolower(trim($status))) {
            case 'pass':
            case 'certified':
            case 'pass_certified':
                return 'success';
            case 'failed':
                return 'danger';
            case 'absent':
                return 'warning';
            case 'exam_not_applied':
            default:
                return 'secondary';
        }
    }

    function batch_result_normalize_status($value): string
    {
        $status = strtolower(trim((string) $value));
        $status = str_replace([' ', '-'], '_', $status);
        // Pass and Certified are the same outcome.
        if (in_array($status, ['certified', 'pass_certified'], true)) {
            $status = 'pass';
        }
        $allowed = array_keys(batch_result_status_options());
        if (!in_array($status, $allowed, true)) {
            return 'exam_not_applied';
        }
        return $status;
    }

    function batch_result_get_batch_student_row($conn, int $batch_id, int $student_record_id): ?array
    {
        require_once __DIR__ . '/batch_functions.php';
        repairBatchStudentsJunction($conn, $batch_id);

        if ($batch_id <= 0 || $student_record_id <= 0) {
            return null;
        }

        $sql = "SELECT bs.id AS batch_student_id, bs.result_status,
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

    function saveBatchStudentResultStatus($conn, int $batch_id, int $student_record_id, $status, int $admin_id = 0): array
    {
        ensureBatchResultSchema($conn);
        if (!batch_result_column_exists($conn, 'result_status')) {
            return ['success' => false, 'message' => 'Result status column is not available.'];
        }

        $row = batch_result_get_batch_student_row($conn, $batch_id, $student_record_id);
        if (!$row) {
            return ['success' => false, 'message' => 'Student is not enrolled in this batch.'];
        }

        $status = batch_result_normalize_status($status);
        $batch_student_id = (int) ($row['batch_student_id'] ?? 0);

        if ($batch_student_id <= 0) {
            require_once __DIR__ . '/batch_functions.php';
            repairBatchStudentsJunction($conn, $batch_id);
            $row = batch_result_get_batch_student_row($conn, $batch_id, $student_record_id);
            $batch_student_id = (int) ($row['batch_student_id'] ?? 0);
            if ($batch_student_id <= 0) {
                return ['success' => false, 'message' => 'Could not find batch enrollment row for this student.'];
            }
        }

        $stmt = $conn->prepare('UPDATE batch_students
            SET result_status = ?, result_updated_at = NOW(), result_updated_by = ?
            WHERE id = ? AND batch_id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => $conn->error ?: 'Could not prepare update.'];
        }
        $stmt->bind_param('siii', $status, $admin_id, $batch_student_id, $batch_id);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => $err ?: 'Could not save result status.'];
        }
        $stmt->close();

        $options = batch_result_status_options();
        return [
            'success' => true,
            'message' => 'Result status updated.',
            'result_status' => $status,
            'result_status_label' => $options[$status] ?? $status,
            'badge_class' => batch_result_status_badge_class($status),
        ];
    }

    function getBatchResultStats($conn, int $batch_id): array
    {
        $out = [
            'total' => 0,
            'exam_not_applied' => 0,
            'absent' => 0,
            'pass' => 0,
            'failed' => 0,
        ];
        if ($batch_id <= 0 || !function_exists('getBatchStudents')) {
            return $out;
        }
        if (!batch_result_column_exists($conn, 'result_status')) {
            return $out;
        }
        $students = getBatchStudents($batch_id, $conn);
        $out['total'] = count($students);
        foreach ($students as $student) {
            $status = batch_result_normalize_status($student['result_status'] ?? 'exam_not_applied');
            if (isset($out[$status])) {
                $out[$status]++;
            }
        }
        return $out;
    }
}
