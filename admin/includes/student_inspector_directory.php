<?php
/**
 * Student Quick Directory — limited profile fields for Student Record Inspector.
 */

if (!function_exists('inspectorDirectoryCategoryOptions')) {
    /** @return string[] */
    function inspectorDirectoryCategoryOptions(): array
    {
        return ['General', 'OBC', 'SC', 'ST', 'EWS'];
    }
}

if (!function_exists('inspectorDirectoryStatusOptions')) {
    /** @return array<string, string> */
    function inspectorDirectoryStatusOptions(): array
    {
        return [
            'all' => 'All (except inactive)',
            'pending' => 'Pending',
            'active' => 'Active / Approved',
            'rejected' => 'Rejected',
            'inactive' => 'Inactive / Removed',
        ];
    }
}

if (!function_exists('inspectorDirectoryStatusBadgeClass')) {
    function inspectorDirectoryStatusBadgeClass(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'active', 'approved' => 'bg-success',
            'pending' => 'bg-warning text-dark',
            'rejected' => 'bg-danger',
            'inactive' => 'bg-secondary',
            default => 'bg-light text-dark border',
        };
    }
}

if (!function_exists('inspectorDirectoryStatusLabel')) {
    function inspectorDirectoryStatusLabel(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return 'Unknown';
        }
        if ($status === 'approved') {
            return 'Approved';
        }

        return ucfirst($status);
    }
}

if (!function_exists('inspectorDirectoryApplyStatusFilter')) {
    /**
     * @param array<int, string> $params
     */
    function inspectorDirectoryApplyStatusFilter(string $status, array &$where, string &$types, array &$params): void
    {
        $status = strtolower(trim($status));
        if ($status === '' || $status === 'all') {
            $where[] = "LOWER(s.status) NOT IN ('inactive')";
            return;
        }
        if ($status === 'active' || $status === 'approved') {
            $where[] = "LOWER(s.status) IN ('approved', 'active')";
            return;
        }
        if ($status === 'inactive') {
            $where[] = "LOWER(s.status) IN ('inactive')";
            return;
        }
        $where[] = 'LOWER(s.status) = ?';
        $types .= 's';
        $params[] = $status;
    }
}

if (!function_exists('inspectorDirectoryApplyCourseFilter')) {
    /**
     * Match primary course on students row and any assigned course via student_enrollments.
     *
     * @param array<int, string|int> $params
     */
    function inspectorDirectoryApplyCourseFilter(mysqli $conn, int $courseId, array &$where, string &$types, array &$params): void
    {
        if ($courseId <= 0) {
            return;
        }

        if (function_exists('isMultiCourseSystemInstalled') && isMultiCourseSystemInstalled($conn)) {
            $where[] = "(s.course_id = ? OR s.id IN (
                SELECT se.student_record_id
                FROM student_enrollments se
                WHERE se.course_id = ? AND se.student_record_id IS NOT NULL
            ) OR (s.account_id IS NOT NULL AND s.account_id IN (
                SELECT se.account_id
                FROM student_enrollments se
                WHERE se.course_id = ? AND se.account_id IS NOT NULL
            )))";
            $types .= 'iii';
            $params[] = $courseId;
            $params[] = $courseId;
            $params[] = $courseId;
            return;
        }

        $where[] = 's.course_id = ?';
        $types .= 'i';
        $params[] = $courseId;
    }
}

if (!function_exists('inspectorDirectoryEmptyHint')) {
    function inspectorDirectoryEmptyHint(mysqli $conn, array $criteria): string
    {
        $courseId = (int)($criteria['course_id'] ?? 0);
        if ($courseId <= 0) {
            return 'Try changing filters or set Status to All (except inactive).';
        }

        $stmt = $conn->prepare(
            "SELECT LOWER(TRIM(status)) AS st, COUNT(*) AS cnt
             FROM students
             WHERE course_id = ? AND LOWER(TRIM(status)) NOT IN ('inactive')
             GROUP BY LOWER(TRIM(status))
             ORDER BY cnt DESC"
        );
        if (!$stmt) {
            return 'Try Status: All or Active / Approved — many students are stored as active, not pending.';
        }
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $res = $stmt->get_result();
        $parts = [];
        while ($row = $res->fetch_assoc()) {
            $label = (string)($row['st'] ?? '');
            if ($label === 'approved' || $label === 'active') {
                $label = 'approved/active';
            }
            $parts[] = (int)$row['cnt'] . ' ' . $label;
        }
        $stmt->close();

        if ($parts === []) {
            return 'No students exist for this course in the database.';
        }

        $selected = strtolower(trim((string)($criteria['status'] ?? 'all')));
        $hint = 'This course has: ' . implode(', ', $parts) . '.';
        if ($selected === 'pending') {
            $hint .= ' None are pending — use Status <strong>Active / Approved</strong> or <strong>All</strong>.';
        } elseif ($selected !== '' && $selected !== 'all') {
            $hint .= ' Try a different status filter.';
        }

        return $hint;
    }
}

if (!function_exists('inspectorFormatStudentAddress')) {
    function inspectorFormatStudentAddress(array $row): string
    {
        $parts = array_filter([
            trim((string)($row['address'] ?? '')),
            trim((string)($row['city'] ?? '')),
            trim((string)($row['state'] ?? '')),
            trim((string)($row['pincode'] ?? '')),
        ], static fn($v) => $v !== '');

        return $parts !== [] ? implode(', ', $parts) : '—';
    }
}

if (!function_exists('inspectorStudentPhotoUrl')) {
    function inspectorStudentPhotoUrl(?string $relativePath): ?string
    {
        $relativePath = trim(str_replace('\\', '/', (string)$relativePath));
        if ($relativePath === '') {
            return null;
        }

        $projectRoot = dirname(__DIR__, 2);
        $fullPath = $projectRoot . '/' . ltrim($relativePath, '/');
        if (!is_file($fullPath)) {
            return null;
        }

        // Admin pages are under /admin — relative path works on localhost and production.
        return '../' . ltrim($relativePath, '/');
    }
}

if (!function_exists('inspectorStudentPhotoExists')) {
    function inspectorStudentPhotoExists(?string $relativePath): bool
    {
        $relativePath = trim(str_replace('\\', '/', (string)$relativePath));
        if ($relativePath === '') {
            return false;
        }

        $fullPath = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
        return is_file($fullPath);
    }
}

if (!function_exists('inspectorDirectoryCriteriaFromRequest')) {
    /** @return array<string, mixed> */
    function inspectorDirectoryCriteriaFromRequest(array $source): array
    {
        return [
            'course_id' => max(0, (int)($source['dir_course_id'] ?? 0)),
            'batch_id' => max(0, (int)($source['dir_batch_id'] ?? 0)),
            'category' => trim((string)($source['dir_category'] ?? '')),
            'status' => trim((string)($source['dir_status'] ?? '')),
            'date_from' => trim((string)($source['dir_date_from'] ?? '')),
            'date_to' => trim((string)($source['dir_date_to'] ?? '')),
            'name' => trim((string)($source['dir_name'] ?? '')),
            'mobile' => trim((string)($source['dir_mobile'] ?? '')),
        ];
    }
}

if (!function_exists('inspectorDirectoryHasCriteria')) {
    function inspectorDirectoryHasCriteria(array $criteria): bool
    {
        if (($criteria['course_id'] ?? 0) > 0) {
            return true;
        }
        if (($criteria['batch_id'] ?? 0) > 0) {
            return true;
        }
        if (($criteria['category'] ?? '') !== '') {
            return true;
        }
        if (($criteria['status'] ?? '') !== '' && strtolower($criteria['status']) !== 'all') {
            return true;
        }
        if (($criteria['date_from'] ?? '') !== '' || ($criteria['date_to'] ?? '') !== '') {
            return true;
        }
        if (($criteria['name'] ?? '') !== '') {
            return true;
        }
        if (($criteria['mobile'] ?? '') !== '') {
            return true;
        }

        return false;
    }
}

if (!function_exists('inspectorDirectoryHiddenFields')) {
    function inspectorDirectoryHiddenFields(array $criteria): string
    {
        $html = '';
        foreach ([
            'dir_course_id' => (string)($criteria['course_id'] ?? 0),
            'dir_batch_id' => (string)($criteria['batch_id'] ?? 0),
            'dir_category' => (string)($criteria['category'] ?? ''),
            'dir_status' => (string)($criteria['status'] ?? ''),
            'dir_date_from' => (string)($criteria['date_from'] ?? ''),
            'dir_date_to' => (string)($criteria['date_to'] ?? ''),
            'dir_name' => (string)($criteria['name'] ?? ''),
            'dir_mobile' => (string)($criteria['mobile'] ?? ''),
        ] as $key => $value) {
            if ($value === '' || $value === '0') {
                continue;
            }
            $html .= '<input type="hidden" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="'
                . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
        }

        return $html;
    }
}

if (!function_exists('inspectorPageQueryString')) {
    function inspectorPageQueryString(array $searchParams, array $directoryCriteria = []): string
    {
        $searchQs = function_exists('inspectorSearchParams') ? inspectorSearchParams($searchParams) : '';
        $dirQs = inspectorDirectorySearchParams($directoryCriteria);
        if ($searchQs === '') {
            return $dirQs;
        }
        if ($dirQs === '') {
            return $searchQs;
        }

        return $searchQs . '&' . $dirQs;
    }
}

if (!function_exists('inspectorDirectorySearchParams')) {
    function inspectorDirectorySearchParams(array $criteria): string
    {
        $parts = [];
        foreach ([
            'dir_course_id' => (string)($criteria['course_id'] ?? 0),
            'dir_batch_id' => (string)($criteria['batch_id'] ?? 0),
            'dir_category' => (string)($criteria['category'] ?? ''),
            'dir_status' => (string)($criteria['status'] ?? ''),
            'dir_date_from' => (string)($criteria['date_from'] ?? ''),
            'dir_date_to' => (string)($criteria['date_to'] ?? ''),
            'dir_name' => (string)($criteria['name'] ?? ''),
            'dir_mobile' => (string)($criteria['mobile'] ?? ''),
        ] as $key => $value) {
            if ($value === '' || $value === '0') {
                continue;
            }
            $parts[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return implode('&', $parts);
    }
}

if (!function_exists('inspectorGetDirectoryBatches')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function inspectorGetDirectoryBatches(mysqli $conn, int $courseId): array
    {
        if ($courseId <= 0) {
            return [];
        }

        $sql = "SELECT b.id, b.batch_name, b.batch_code,
                       (SELECT COUNT(DISTINCT COALESCE(bs.student_record_id, bs.student_id))
                        FROM batch_students bs WHERE bs.batch_id = b.id) AS enrolled_count
                FROM batches b
                WHERE b.course_id = ?
                ORDER BY b.batch_name ASC";

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

if (!function_exists('inspectorFetchDirectoryProfiles')) {
    /**
     * @param array<string, mixed> $criteria
     * @param int[] $recordIds
     * @return array<int, array<string, mixed>>
     */
    function inspectorFetchDirectoryProfiles(mysqli $conn, array $criteria = [], array $recordIds = []): array
    {
        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($recordIds)) {
            $recordIds = array_values(array_unique(array_filter(array_map('intval', $recordIds))));
            if ($recordIds === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
            $where[] = "s.id IN ({$placeholders})";
            $types .= str_repeat('i', count($recordIds));
            $params = array_merge($params, $recordIds);
        } else {
            inspectorDirectoryApplyCourseFilter(
                $conn,
                (int)($criteria['course_id'] ?? 0),
                $where,
                $types,
                $params
            );
            if (($criteria['batch_id'] ?? 0) > 0) {
                $where[] = 's.batch_id = ?';
                $types .= 'i';
                $params[] = (int)$criteria['batch_id'];
            }
            if (($criteria['category'] ?? '') !== '') {
                $where[] = 's.category = ?';
                $types .= 's';
                $params[] = $criteria['category'];
            }
            inspectorDirectoryApplyStatusFilter((string)($criteria['status'] ?? 'all'), $where, $types, $params);
            if (($criteria['name'] ?? '') !== '') {
                $where[] = 's.name LIKE ?';
                $types .= 's';
                $params[] = '%' . $criteria['name'] . '%';
            }
            if (($criteria['mobile'] ?? '') !== '') {
                $where[] = "REPLACE(REPLACE(s.mobile,' ',''),'-','') LIKE ?";
                $types .= 's';
                $params[] = '%' . preg_replace('/[\s\-]/', '', $criteria['mobile']) . '%';
            }
            if (($criteria['date_from'] ?? '') !== '') {
                $where[] = 'DATE(COALESCE(s.registration_date, s.created_at)) >= ?';
                $types .= 's';
                $params[] = $criteria['date_from'];
            }
            if (($criteria['date_to'] ?? '') !== '') {
                $where[] = 'DATE(COALESCE(s.registration_date, s.created_at)) <= ?';
                $types .= 's';
                $params[] = $criteria['date_to'];
            }
        }

        $assignedCoursesSelect = '';
        if (function_exists('isMultiCourseSystemInstalled') && isMultiCourseSystemInstalled($conn)) {
            $assignedCoursesSelect = ", (
                SELECT GROUP_CONCAT(DISTINCT CONCAT(c2.course_name, ' (', c2.course_code, ')')
                    ORDER BY c2.course_name SEPARATOR ' | ')
                FROM student_enrollments se2
                INNER JOIN courses c2 ON c2.id = se2.course_id
                WHERE (se2.student_record_id = s.id
                    OR (s.account_id IS NOT NULL AND se2.account_id = s.account_id))
                  AND LOWER(COALESCE(se2.status, 'active')) NOT IN ('inactive')
            ) AS assigned_courses";
        }

        $limit = empty($recordIds) ? 300 : max(50, count($recordIds));
        $sql = "SELECT s.id, s.student_id, s.name, s.mobile, s.category, s.class_standard, s.passport_photo,
                       s.address, s.city, s.state, s.pincode, s.status, s.course_id,
                       c.course_name, c.course_code,
                       COALESCE(s.registration_date, s.created_at) AS apply_date
                       {$assignedCoursesSelect}
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY apply_date DESC, s.id DESC
                LIMIT " . (int)$limit;

        $stmt = $conn->prepare($sql);
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

if (!function_exists('inspectorCollectDirectoryRecordIds')) {
    /**
     * @param array<int, array<string, mixed>> ...$rowSets
     * @return int[]
     */
    function inspectorCollectDirectoryRecordIds(array ...$rowSets): array
    {
        $ids = [];
        foreach ($rowSets as $rows) {
            foreach ($rows as $row) {
                if (!empty($row['id'])) {
                    $ids[] = (int)$row['id'];
                } elseif (!empty($row['student_record_id'])) {
                    $ids[] = (int)$row['student_record_id'];
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
