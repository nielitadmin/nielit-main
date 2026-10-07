<?php
/**
 * Shared student API response field helpers.
 */

if (!function_exists('apiNormalizeDate')) {
    function apiNormalizeDate($value): ?string {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        return $value;
    }
}

if (!function_exists('apiCourseDurationValue')) {
    function apiCourseDurationValue(array $row): ?string {
        $duration = trim((string) ($row['course_duration'] ?? $row['duration'] ?? ''));
        return $duration !== '' ? $duration : null;
    }
}

if (!function_exists('apiFormatEnrollmentRecord')) {
    function apiFormatEnrollmentRecord(array $row): array {
        $formatted = [
            'enrollment_id' => isset($row['enrollment_id']) ? (int) $row['enrollment_id'] : (isset($row['id']) ? (int) $row['id'] : null),
            'student_record_id' => isset($row['student_record_id']) ? (int) $row['student_record_id'] : null,
            'student_id' => (string) ($row['student_id'] ?? ''),
            'course_id' => isset($row['course_id']) ? (int) $row['course_id'] : null,
            'course_code' => (string) ($row['course_code'] ?? ''),
            'course_name' => (string) ($row['course_name'] ?? ''),
            'course_duration' => apiCourseDurationValue($row),
            'batch_id' => !empty($row['batch_id']) ? (int) $row['batch_id'] : null,
            'batch_code' => (string) ($row['batch_code'] ?? ''),
            'batch_name' => (string) ($row['batch_name'] ?? ''),
            'batch_start_date' => apiNormalizeDate($row['batch_start_date'] ?? $row['start_date'] ?? null),
            'batch_end_date' => apiNormalizeDate($row['batch_end_date'] ?? $row['end_date'] ?? null),
            'training_center' => (string) ($row['training_center'] ?? ''),
            'status' => (string) ($row['status'] ?? 'active'),
        ];

        if (!empty($row['scheme_name']) || !empty($row['scheme_code'])) {
            $formatted['scheme_name'] = (string) ($row['scheme_name'] ?? '');
            $formatted['scheme_code'] = (string) ($row['scheme_code'] ?? '');
        }

        return $formatted;
    }
}

if (!function_exists('apiAppendCourseBatchFields')) {
    function apiAppendCourseBatchFields(array $student): array {
        $student['course_duration'] = apiCourseDurationValue($student);
        $student['batch_start_date'] = apiNormalizeDate($student['batch_start_date'] ?? null);
        $student['batch_end_date'] = apiNormalizeDate($student['batch_end_date'] ?? null);
        unset($student['duration']);
        return $student;
    }
}

if (!function_exists('apiMapStudentListRow')) {
    function apiMapStudentListRow(array $row, bool $includePassword = false): array {
        $student = [
            'student_id' => $row['student_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'mobile' => $row['mobile'],
            'course_id' => $row['course_id'],
            'course_name' => $row['course_name'],
            'course_duration' => apiCourseDurationValue($row),
            'batch_id' => $row['batch_id'] ?? null,
            'batch_code' => $row['batch_code'] ?? null,
            'batch_name' => $row['batch_name'] ?? null,
            'batch_start_date' => apiNormalizeDate($row['batch_start_date'] ?? null),
            'batch_end_date' => apiNormalizeDate($row['batch_end_date'] ?? null),
            'training_center' => $row['training_center'],
            'created_at' => $row['created_at'],
            'status' => $row['status'],
        ];

        if ($includePassword) {
            $student['password'] = $row['password'];
        }

        return $student;
    }
}
