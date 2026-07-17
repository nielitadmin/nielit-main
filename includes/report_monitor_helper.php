<?php
/**
 * Report Monitor — unified analytics data layer.
 */

require_once __DIR__ . '/course_category_options.php';

if (!function_exists('get_report_monitor_category_groups')) {

    function report_monitor_table_exists($conn, $table) {
        $table = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '{$table}'");
        return $result && $result->num_rows > 0;
    }

    /**
     * Canonical report groups requested for the monitor dashboard.
     */
    function get_report_monitor_category_groups() {
        return [
            'long_term_skill' => [
                'label' => 'Skill Based (Long Term) Courses (> 500 hrs)',
                'values' => [
                    'Skill Based (Long Term) >500 hrs',
                    'Skill Based (Long Term) Courses (> 500 hrs)',
                    'Skill Based (Long Term) Courses > 500 hrs',
                    'Long Term NSQF',
                ],
            ],
            'short_term_skill' => [
                'label' => 'Skill Based (Short Term) Courses (90-500 hrs)',
                'values' => [
                    'Skill Based (Short Term) 90-500 hrs',
                    'Skill Based (Short Term) Courses (90-500 hrs)',
                    'Skill Based (Short Term) Courses >90 hrs to <=500 hrs',
                    'Short Term NSQF',
                ],
            ],
            'digital_competency' => [
                'label' => 'Short Term / Digital Competency Courses (<= 90 hrs)',
                'values' => [
                    'Short Term / Digital Competency <=90 hrs',
                    'Short Term / Digital Competency Courses (<= 90 hrs)',
                    'Short Term Courses / Digital Competency Courses <= 90 hours',
                ],
            ],
            'internship_bootcamp' => [
                'label' => 'Internship Programs & Boot Camps',
                'values' => [
                    'Internship Program',
                    'Internship',
                    'Bootcamp',
                    'Awareness Program',
                    'Workshop',
                    'FDP Program',
                ],
            ],
            'degree_diploma' => [
                'label' => 'Degree / Diploma / Postgraduate Courses',
                'values' => [
                    'Degree / Diploma / PG',
                    'Degree / Diploma Courses / PG',
                ],
            ],
            'nielit_literacy' => [
                'label' => 'NIELIT HQ Digital Literacy Courses (CCC / ECC / BCC / ACC)',
                'values' => [
                    'NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)',
                    "NIELIT HQ's Digital Literacy Courses (CCC / ECC / CCCP / BCC / ACC)",
                    'NIELIT HQ Digital Literacy Courses (CCC/ECC/BCC/ACC)',
                ],
            ],
            'govt_corporate' => [
                'label' => 'Govt/Corporate Training',
                'values' => [
                    'Govt/Corporate Training',
                    'GOVT/CORPORATE Training',
                ],
            ],
            'nsqf_other' => [
                'label' => 'NSQF / Other Programs',
                'values' => [
                    'NSQF',
                    'Regular',
                    'NON-NSQF',
                ],
            ],
        ];
    }

    function report_monitor_category_value_map() {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        foreach (get_report_monitor_category_groups() as $key => $group) {
            foreach ($group['values'] as $value) {
                $map[$value] = $key;
            }
        }
        foreach (get_legacy_course_categories() as $legacy) {
            if (!isset($map[$legacy])) {
                $map[$legacy] = report_monitor_guess_group_key($legacy);
            }
        }
        return $map;
    }

    function report_monitor_guess_group_key($raw) {
        $raw = trim((string) $raw);
        $upper = strtoupper($raw);
        if ($raw === '') {
            return 'uncategorized';
        }
        if (strpos($upper, 'LONG TERM') !== false || strpos($upper, 'LONG TERM NSQF') !== false) {
            return 'long_term_skill';
        }
        if (strpos($upper, 'SHORT TERM') !== false && strpos($upper, 'DIGITAL') === false) {
            return 'short_term_skill';
        }
        if (strpos($upper, 'DIGITAL') !== false || strpos($upper, '<= 90') !== false || strpos($upper, '<=90') !== false) {
            return 'digital_competency';
        }
        if (strpos($upper, 'INTERNSHIP') !== false || strpos($upper, 'BOOTCAMP') !== false || strpos($upper, 'WORKSHOP') !== false || strpos($upper, 'AWARENESS') !== false || strpos($upper, 'FDP') !== false) {
            return 'internship_bootcamp';
        }
        if (strpos($upper, 'DEGREE') !== false || strpos($upper, 'DIPLOMA') !== false || strpos($upper, 'POSTGRADUATE') !== false || strpos($upper, ' PG') !== false) {
            return 'degree_diploma';
        }
        if (strpos($upper, 'NIELIT HQ') !== false || strpos($upper, 'CCC') !== false || strpos($upper, 'ECC') !== false || strpos($upper, 'BCC') !== false) {
            return 'nielit_literacy';
        }
        if (strpos($upper, 'GOVT') !== false || strpos($upper, 'CORPORATE') !== false) {
            return 'govt_corporate';
        }
        if (strpos($upper, 'NSQF') !== false) {
            return 'nsqf_other';
        }
        return 'uncategorized';
    }

    function report_monitor_resolve_category_group($rawCategory) {
        $raw = trim((string) $rawCategory);
        if ($raw === '') {
            return 'uncategorized';
        }
        $map = report_monitor_category_value_map();
        if (isset($map[$raw])) {
            return $map[$raw];
        }
        return report_monitor_guess_group_key($raw);
    }

    function report_monitor_category_label($groupKey) {
        $groups = get_report_monitor_category_groups();
        if (isset($groups[$groupKey]['label'])) {
            return $groups[$groupKey]['label'];
        }
        if ($groupKey === 'uncategorized') {
            return 'Uncategorized';
        }
        return ucwords(str_replace('_', ' ', $groupKey));
    }

    function report_monitor_course_category_sql($alias = 'c') {
        return "COALESCE(NULLIF(TRIM({$alias}.category), ''), NULLIF(TRIM({$alias}.course_type), ''), 'Uncategorized')";
    }

    function report_monitor_student_active_sql($alias = 's') {
        return "LOWER(COALESCE({$alias}.status, '')) NOT IN ('rejected', 'inactive')";
    }

    /**
     * SQL boolean: course counts as active (status column or enrollment_status on older DBs).
     */
    function report_monitor_course_is_active_sql(mysqli $conn, $alias = 'c') {
        static $hasStatusColumn = null;
        if ($hasStatusColumn === null) {
            $check = $conn->query("SHOW COLUMNS FROM courses LIKE 'status'");
            $hasStatusColumn = ($check && $check->num_rows > 0);
        }
        if ($hasStatusColumn) {
            return "LOWER(COALESCE({$alias}.status, 'active')) = 'active'";
        }
        return "LOWER(COALESCE({$alias}.enrollment_status, 'ongoing')) = 'ongoing'";
    }

    function report_monitor_student_batch_enrolled_condition($conn, $studentAlias = 's') {
        $parts = ["({$studentAlias}.batch_id IS NOT NULL AND {$studentAlias}.batch_id > 0)"];
        if (report_monitor_table_exists($conn, 'batch_students')) {
            $parts[] = "EXISTS (
                SELECT 1 FROM batch_students bs
                WHERE bs.student_record_id = {$studentAlias}.id OR bs.student_id = {$studentAlias}.id
            )";
        }
        return '(' . implode(' OR ', $parts) . ')';
    }

    function report_monitor_build_course_filter($conn, array $courseIds, $alias = 'c') {
        if (empty($courseIds)) {
            return ['sql' => '', 'types' => '', 'values' => []];
        }
        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
        return [
            'sql' => " AND {$alias}.id IN ({$placeholders})",
            'types' => str_repeat('i', count($courseIds)),
            'values' => array_map('intval', $courseIds),
        ];
    }

    /** Scope filter by assigned courses and/or selected centre (course alias). */
    function report_monitor_build_scope_filter($conn, array $courseIds = [], $centreId = 0, $courseAlias = 'c') {
        $sql = '';
        $types = '';
        $values = [];

        if (!empty($courseIds)) {
            $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
            $sql .= " AND {$courseAlias}.id IN ({$placeholders})";
            $types .= str_repeat('i', count($courseIds));
            $values = array_merge($values, array_map('intval', $courseIds));
        }

        if ($centreId > 0) {
            $centreName = report_monitor_get_centre_name($conn, $centreId);
            if ($centreName !== '') {
                $sql .= " AND ({$courseAlias}.centre_id = ? OR ({$courseAlias}.centre_id IS NULL AND TRIM({$courseAlias}.training_center) = ?))";
                $types .= 'is';
                $values[] = (int) $centreId;
                $values[] = $centreName;
            } else {
                $sql .= " AND {$courseAlias}.centre_id = ?";
                $types .= 'i';
                $values[] = (int) $centreId;
            }
        }

        return ['sql' => $sql, 'types' => $types, 'values' => $values];
    }

    function report_monitor_get_centre_name($conn, $centreId) {
        if ($centreId <= 0 || !report_monitor_table_exists($conn, 'centres')) {
            return '';
        }
        $stmt = $conn->prepare('SELECT name FROM centres WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('i', $centreId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['name'] ?? '';
    }

    /** Parse YYYY-MM month filter; empty/all means lifetime totals. */
    function report_monitor_parse_month_filter($monthParam) {
        $monthParam = trim((string) $monthParam);
        if ($monthParam === '' || strtolower($monthParam) === 'all') {
            return ['active' => false];
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            return ['active' => false];
        }
        $startDate = DateTime::createFromFormat('Y-m-d', $monthParam . '-01');
        if (!$startDate) {
            return ['active' => false];
        }
        $nextStart = (clone $startDate)->modify('+1 month');
        return [
            'active' => true,
            'year_month' => $monthParam,
            'start' => $startDate->format('Y-m-d 00:00:00'),
            'next_start' => $nextStart->format('Y-m-d 00:00:00'),
            'label' => $startDate->format('F Y'),
        ];
    }

    function report_monitor_get_month_options($monthsBack = 24) {
        $options = [['value' => 'all', 'label' => 'All time (lifetime)']];
        $cursor = new DateTime('first day of this month');
        for ($i = 0; $i < $monthsBack; $i++) {
            $options[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $cursor->format('F Y'),
            ];
            $cursor->modify('-1 month');
        }
        return $options;
    }

    function report_monitor_batch_month_column_sql($batchAlias = 'b') {
        return report_monitor_batch_start_sql($batchAlias);
    }

    function report_monitor_batch_start_sql($batchAlias = 'b') {
        return "COALESCE(NULLIF({$batchAlias}.start_date, '0000-00-00'), {$batchAlias}.created_at)";
    }

    function report_monitor_batch_end_sql($batchAlias = 'b') {
        $startExpr = report_monitor_batch_start_sql($batchAlias);
        return "COALESCE(NULLIF({$batchAlias}.end_date, '0000-00-00'), {$startExpr})";
    }

    function report_monitor_append_batch_period_overlap(&$sql, &$types, array &$values, $batchAlias = 'b', array $monthFilter = []) {
        if (empty($monthFilter['active'])) {
            return;
        }

        $batchStart = report_monitor_batch_start_sql($batchAlias);
        $batchEnd = report_monitor_batch_end_sql($batchAlias);
        $sql .= " AND {$batchStart} <= ? AND {$batchEnd} >= ?";
        $types .= 'ss';
        $values[] = $monthFilter['end'];
        $values[] = $monthFilter['start'];
    }

    function report_monitor_append_month_between(&$sql, &$types, array &$values, $columnExpr, array $monthFilter) {
        if (empty($monthFilter['active'])) {
            return;
        }
        $sql .= " AND {$columnExpr} >= ? AND {$columnExpr} < ?";
        $types .= 'ss';
        $values[] = $monthFilter['start'];
        $values[] = $monthFilter['next_start'];
    }

    function report_monitor_enrollment_timestamp_sql($conn, $bsAlias = 'bs', $studentAlias = 'st') {
        $parts = [
            "NULLIF({$bsAlias}.enrollment_date, '0000-00-00')",
            "NULLIF({$bsAlias}.enrollment_date, '0000-00-00 00:00:00')",
            "NULLIF({$studentAlias}.approved_at, '0000-00-00 00:00:00')",
            "{$studentAlias}.created_at",
        ];

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    function report_monitor_bind_and_execute($conn, $sql, $types = '', array $values = []) {
        if ($types === '' || empty($values)) {
            return $conn->query($sql);
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        return $stmt->get_result();
    }

    function report_monitor_get_overall_stats($conn, array $courseIds = [], $centreId = 0, array $monthFilter = []) {
        $stats = [
            'total_courses' => 0,
            'active_courses' => 0,
            'total_batches' => 0,
            'active_batches' => 0,
            'completed_batches' => 0,
            'total_applications' => 0,
            'pending_applications' => 0,
            'approved_students' => 0,
            'batch_enrolled_students' => 0,
            'unassigned_students' => 0,
        ];

        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $courseActiveSql = report_monitor_course_is_active_sql($conn, 'c');
        $courseSql = "SELECT COUNT(*) AS total,
                             SUM(CASE WHEN {$courseActiveSql} THEN 1 ELSE 0 END) AS active
                      FROM courses c WHERE 1=1{$scopeFilter['sql']}";
        $result = report_monitor_bind_and_execute($conn, $courseSql, $scopeFilter['types'], $scopeFilter['values']);
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_courses'] = (int) ($row['total'] ?? 0);
            $stats['active_courses'] = (int) ($row['active'] ?? 0);
        }

        if (report_monitor_table_exists($conn, 'batches')) {
            $batchScope = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
            $batchSql = "SELECT COUNT(*) AS total,
                                SUM(CASE WHEN LOWER(COALESCE(b.status, 'active')) IN ('active', 'ongoing', 'open') THEN 1 ELSE 0 END) AS active,
                                SUM(CASE WHEN LOWER(COALESCE(b.status, 'active')) IN ('completed', 'closed', 'finished', 'cancelled') THEN 1 ELSE 0 END) AS completed
                         FROM batches b
                         INNER JOIN courses c ON c.id = b.course_id
                         WHERE 1=1{$batchScope['sql']}";

            $batchTypes = $batchScope['types'];
            $batchValues = $batchScope['values'];

            // Apply month/quarter filter to batches (by start_date/created_at expression)
            $batchMonthExpr = report_monitor_batch_month_column_sql('b');
            report_monitor_append_batch_period_overlap($batchSql, $batchTypes, $batchValues, 'b', $monthFilter);

            $batchResult = report_monitor_bind_and_execute($conn, $batchSql, $batchTypes, $batchValues);
            if ($batchResult && $row = $batchResult->fetch_assoc()) {
                $stats['total_batches'] = (int) ($row['total'] ?? 0);
                $stats['active_batches'] = (int) ($row['active'] ?? 0);
                $stats['completed_batches'] = (int) ($row['completed'] ?? 0);
            }
        }

        $batchCondition = report_monitor_student_batch_enrolled_condition($conn, 's');
        $activeCondition = report_monitor_student_active_sql('s');

        $studentSql = "SELECT
                            COUNT(DISTINCT s.id) AS total_applications,
                            SUM(CASE WHEN LOWER(COALESCE(s.status, '')) = 'pending' THEN 1 ELSE 0 END) AS pending_applications,
                            SUM(CASE WHEN LOWER(COALESCE(s.status, '')) = 'active' THEN 1 ELSE 0 END) AS approved_students,
                            SUM(CASE WHEN {$batchCondition} THEN 1 ELSE 0 END) AS batch_enrolled_students
                       FROM students s
                       INNER JOIN courses c ON c.id = s.course_id
                       WHERE {$activeCondition}{$scopeFilter['sql']}";
        $studentResult = report_monitor_bind_and_execute($conn, $studentSql, $scopeFilter['types'], $scopeFilter['values']);
        if ($studentResult && $row = $studentResult->fetch_assoc()) {
            $stats['total_applications'] = (int) ($row['total_applications'] ?? 0);
            $stats['pending_applications'] = (int) ($row['pending_applications'] ?? 0);
            $stats['approved_students'] = (int) ($row['approved_students'] ?? 0);
            $stats['batch_enrolled_students'] = (int) ($row['batch_enrolled_students'] ?? 0);
            $stats['unassigned_students'] = max(0, $stats['total_applications'] - $stats['batch_enrolled_students']);
        }

        return $stats;
    }

    function report_monitor_get_centre_stats($conn, array $courseIds = [], $centreId = 0, array $monthFilter = []) {
        $rows = [];
        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $batchCondition = report_monitor_student_batch_enrolled_condition($conn, 's');
        $activeCondition = report_monitor_student_active_sql('s');
        $batchStartExpr = report_monitor_batch_start_sql('b');
        $batchEndExpr = report_monitor_batch_end_sql('b');

        if (!empty($monthFilter['active'])) {
            $monthStart = $monthFilter['start'];
            $monthEnd = $monthFilter['end'];
            $monthNext = $monthFilter['next_start'];

            $sql = "SELECT
                        COALESCE(cen.id, 0) AS centre_id,
                        COALESCE(NULLIF(TRIM(cen.name), ''), NULLIF(TRIM(c.training_center), ''), 'Unassigned Centre') AS centre_name,
                        COALESCE(cen.code, '') AS centre_code,
                        COUNT(DISTINCT CASE
                            WHEN (s.id IS NOT NULL AND s.created_at >= ? AND s.created_at < ?)
                              OR (b.id IS NOT NULL AND {$batchStartExpr} <= ? AND {$batchEndExpr} >= ?)
                            THEN c.id END) AS course_count,
                        COUNT(DISTINCT CASE
                            WHEN b.id IS NOT NULL
                            AND {$batchStartExpr} <= ? AND {$batchEndExpr} >= ?
                            AND LOWER(COALESCE(b.status, 'active')) = 'active'
                            THEN b.id END) AS batch_count,
                        COUNT(DISTINCT CASE
                            WHEN s.id IS NOT NULL AND s.created_at >= ? AND s.created_at < ?
                            THEN s.id END) AS applications,
                        SUM(CASE
                            WHEN s.id IS NOT NULL
                            AND s.created_at >= ?
                            AND s.created_at < ?
                            AND {$batchCondition}
                            THEN 1 ELSE 0 END) AS batch_enrolled
                    FROM courses c
                    LEFT JOIN centres cen ON cen.id = c.centre_id
                    LEFT JOIN batches b ON b.course_id = c.id
                    LEFT JOIN students s ON s.course_id = c.id AND {$activeCondition}
                    WHERE 1=1{$scopeFilter['sql']}
                    GROUP BY centre_id, centre_name, centre_code
                    HAVING course_count > 0 OR batch_count > 0 OR applications > 0 OR batch_enrolled > 0
                    ORDER BY applications DESC, centre_name ASC";
            $types = str_repeat('s', 10) . $scopeFilter['types'];
            $values = array_merge(
                [$monthStart, $monthNext, $monthEnd, $monthStart, $monthEnd, $monthStart, $monthStart, $monthNext, $monthStart, $monthNext],
                $scopeFilter['values']
            );

            $result = report_monitor_bind_and_execute($conn, $sql, $types, $values);
        } else {
            $sql = "SELECT
                        COALESCE(cen.id, 0) AS centre_id,
                        COALESCE(NULLIF(TRIM(cen.name), ''), NULLIF(TRIM(c.training_center), ''), 'Unassigned Centre') AS centre_name,
                        COALESCE(cen.code, '') AS centre_code,
                        COUNT(DISTINCT c.id) AS course_count,
                        COUNT(DISTINCT b.id) AS batch_count,
                        COUNT(DISTINCT s.id) AS applications,
                        SUM(CASE WHEN {$batchCondition} THEN 1 ELSE 0 END) AS batch_enrolled
                    FROM courses c
                    LEFT JOIN centres cen ON cen.id = c.centre_id
                    LEFT JOIN batches b ON b.course_id = c.id
                    LEFT JOIN students s ON s.course_id = c.id AND {$activeCondition}
                    WHERE 1=1{$scopeFilter['sql']}
                    GROUP BY centre_id, centre_name, centre_code
                    ORDER BY applications DESC, centre_name ASC";

            $result = report_monitor_bind_and_execute($conn, $sql, $scopeFilter['types'], $scopeFilter['values']);
        }

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'centre_id' => (int) $row['centre_id'],
                    'centre_name' => $row['centre_name'],
                    'centre_code' => $row['centre_code'],
                    'course_count' => (int) $row['course_count'],
                    'batch_count' => (int) $row['batch_count'],
                    'applications' => (int) $row['applications'],
                    'batch_enrolled' => (int) $row['batch_enrolled'],
                    'unassigned' => max(0, (int) $row['applications'] - (int) $row['batch_enrolled']),
                ];
            }
        }
        return $rows;
    }

    function report_monitor_get_category_stats($conn, array $courseIds = [], $centreId = 0, array $monthFilter = []) {
        $groups = [];
        foreach (get_report_monitor_category_groups() as $key => $group) {
            $groups[$key] = [
                'key' => $key,
                'label' => $group['label'],
                'courses' => 0,
                'applications' => 0,
                'approved' => 0,
                'batch_enrolled' => 0,
                'pending' => 0,
            ];
        }
        $groups['uncategorized'] = [
            'key' => 'uncategorized',
            'label' => 'Uncategorized',
            'courses' => 0,
            'applications' => 0,
            'approved' => 0,
            'batch_enrolled' => 0,
            'pending' => 0,
        ];

        $categoryExpr = report_monitor_course_category_sql('c');
        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $batchCondition = report_monitor_student_batch_enrolled_condition($conn, 's');
        $activeCondition = report_monitor_student_active_sql('s');
        $batchMonthExpr = report_monitor_batch_month_column_sql('b');

        if (!empty($monthFilter['active'])) {
            $monthStart = $monthFilter['start'];
            $monthNext = $monthFilter['next_start'];

            $courseSql = "SELECT {$categoryExpr} AS raw_category, COUNT(DISTINCT c.id) AS total
                          FROM courses c
                          LEFT JOIN students s ON s.course_id = c.id AND {$activeCondition}
                              AND s.created_at >= ? AND s.created_at < ?
                          LEFT JOIN batches b ON b.course_id = c.id
                              AND {$batchMonthExpr} >= ? AND {$batchMonthExpr} < ?
                          WHERE 1=1{$scopeFilter['sql']}
                          AND (s.id IS NOT NULL OR b.id IS NOT NULL)
                          GROUP BY raw_category";
            $courseTypes = 'ssss' . $scopeFilter['types'];
            $courseValues = array_merge([$monthStart, $monthNext, $monthStart, $monthNext], $scopeFilter['values']);
            $courseResult = report_monitor_bind_and_execute($conn, $courseSql, $courseTypes, $courseValues);

            // Monthly view: keep Applied/Approved/Pending/In Batches on the same registration cohort.
            $studentSql = "SELECT {$categoryExpr} AS raw_category,
                                  COUNT(DISTINCT s.id) AS applications,
                                  SUM(CASE WHEN LOWER(COALESCE(s.status, '')) = 'pending' THEN 1 ELSE 0 END) AS pending,
                                  SUM(CASE WHEN LOWER(COALESCE(s.status, '')) = 'active' THEN 1 ELSE 0 END) AS approved,
                                  SUM(CASE WHEN {$batchCondition} THEN 1 ELSE 0 END) AS batch_enrolled
                           FROM students s
                           INNER JOIN courses c ON c.id = s.course_id
                           WHERE {$activeCondition}{$scopeFilter['sql']}
                           AND s.created_at >= ? AND s.created_at < ?
                           GROUP BY raw_category";
            $studentTypes = $scopeFilter['types'] . 'ss';
            $studentValues = array_merge($scopeFilter['values'], [$monthStart, $monthNext]);
            $studentResult = report_monitor_bind_and_execute($conn, $studentSql, $studentTypes, $studentValues);
            $enrollResult = false;
        } else {
            $courseSql = "SELECT {$categoryExpr} AS raw_category, COUNT(*) AS total
                          FROM courses c WHERE 1=1{$scopeFilter['sql']}
                          GROUP BY raw_category";
            $courseResult = report_monitor_bind_and_execute($conn, $courseSql, $scopeFilter['types'], $scopeFilter['values']);

            $studentSql = "SELECT {$categoryExpr} AS raw_category,
                                  COUNT(DISTINCT s.id) AS applications,
                                  SUM(CASE WHEN LOWER(COALESCE(s.status, '')) = 'pending' THEN 1 ELSE 0 END) AS pending,
                                  SUM(CASE WHEN LOWER(COALESCE(s.status, '')) = 'active' THEN 1 ELSE 0 END) AS approved,
                                  SUM(CASE WHEN {$batchCondition} THEN 1 ELSE 0 END) AS batch_enrolled
                           FROM students s
                           INNER JOIN courses c ON c.id = s.course_id
                           WHERE {$activeCondition}{$scopeFilter['sql']}
                           GROUP BY raw_category";
            $studentResult = report_monitor_bind_and_execute($conn, $studentSql, $scopeFilter['types'], $scopeFilter['values']);
            $enrollResult = false;
        }

        if ($courseResult) {
            while ($row = $courseResult->fetch_assoc()) {
                $key = report_monitor_resolve_category_group($row['raw_category']);
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'key' => $key,
                        'label' => report_monitor_category_label($key),
                        'courses' => 0,
                        'applications' => 0,
                        'approved' => 0,
                        'batch_enrolled' => 0,
                        'pending' => 0,
                    ];
                }
                $groups[$key]['courses'] += (int) $row['total'];
            }
        }

        if ($studentResult) {
            while ($row = $studentResult->fetch_assoc()) {
                $key = report_monitor_resolve_category_group($row['raw_category']);
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'key' => $key,
                        'label' => report_monitor_category_label($key),
                        'courses' => 0,
                        'applications' => 0,
                        'approved' => 0,
                        'batch_enrolled' => 0,
                        'pending' => 0,
                    ];
                }
                $groups[$key]['applications'] += (int) $row['applications'];
                $groups[$key]['pending'] += (int) $row['pending'];
                $groups[$key]['approved'] += (int) $row['approved'];
                if ($enrollResult === false) {
                    $groups[$key]['batch_enrolled'] += (int) $row['batch_enrolled'];
                }
            }
        }

        if ($enrollResult) {
            while ($row = $enrollResult->fetch_assoc()) {
                $key = report_monitor_resolve_category_group($row['raw_category']);
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'key' => $key,
                        'label' => report_monitor_category_label($key),
                        'courses' => 0,
                        'applications' => 0,
                        'approved' => 0,
                        'batch_enrolled' => 0,
                        'pending' => 0,
                    ];
                }
                $groups[$key]['batch_enrolled'] += (int) $row['batch_enrolled'];
            }
        }

        $rows = array_values($groups);
        usort($rows, static function ($a, $b) {
            return $b['applications'] <=> $a['applications'];
        });
        return $rows;
    }

    /** Faculty names for a batch: linked faculty, else batch coordinator text. */
    function report_monitor_batch_faculty_names_sql($batchAlias = 'b') {
        return "COALESCE(
            NULLIF((
                SELECT GROUP_CONCAT(DISTINCT f.name ORDER BY f.name SEPARATOR ', ')
                FROM batch_faculty bf
                INNER JOIN faculty f ON f.id = bf.faculty_id
                WHERE bf.batch_id = {$batchAlias}.id
            ), ''),
            NULLIF(TRIM({$batchAlias}.batch_coordinator), ''),
            NULLIF(TRIM({$batchAlias}.batch_coordinator), '0')
        )";
    }

    function report_monitor_get_course_stats($conn, array $courseIds = [], $centreId = 0, array $monthFilter = []) {
        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $activeCondition = report_monitor_student_active_sql('s');
        $batchCondition = report_monitor_student_batch_enrolled_condition($conn, 's');
        $batchMonthExpr = report_monitor_batch_month_column_sql('b');
        $courseMap = [];

        if (!empty($monthFilter['active'])) {
            $monthStart = $monthFilter['start'];
            $monthNext = $monthFilter['next_start'];

            $studentSql = "SELECT c.id,
                                  c.course_name,
                                  c.course_code,
                                  COUNT(DISTINCT s.id) AS applications,
                                  SUM(CASE WHEN LOWER(COALESCE(s.status, '')) = 'pending' THEN 1 ELSE 0 END) AS pending,
                                  SUM(CASE WHEN LOWER(COALESCE(s.status, '')) = 'active' THEN 1 ELSE 0 END) AS approved,
                                  SUM(CASE WHEN {$batchCondition} THEN 1 ELSE 0 END) AS batch_enrolled
                           FROM students s
                           INNER JOIN courses c ON c.id = s.course_id
                           WHERE {$activeCondition}{$scopeFilter['sql']}
                           AND s.created_at >= ? AND s.created_at < ?
                           GROUP BY c.id, c.course_name, c.course_code";
            $studentTypes = $scopeFilter['types'] . 'ss';
            $studentValues = array_merge($scopeFilter['values'], [$monthStart, $monthNext]);
            $studentResult = report_monitor_bind_and_execute($conn, $studentSql, $studentTypes, $studentValues);
        } else {
            $studentSql = "SELECT c.id,
                                  c.course_name,
                                  c.course_code,
                                  COUNT(DISTINCT s.id) AS applications,
                                  SUM(CASE WHEN LOWER(COALESCE(s.status, '')) = 'pending' THEN 1 ELSE 0 END) AS pending,
                                  SUM(CASE WHEN LOWER(COALESCE(s.status, '')) = 'active' THEN 1 ELSE 0 END) AS approved,
                                  SUM(CASE WHEN {$batchCondition} THEN 1 ELSE 0 END) AS batch_enrolled
                           FROM students s
                           INNER JOIN courses c ON c.id = s.course_id
                           WHERE {$activeCondition}{$scopeFilter['sql']}
                           GROUP BY c.id, c.course_name, c.course_code";
            $studentResult = report_monitor_bind_and_execute($conn, $studentSql, $scopeFilter['types'], $scopeFilter['values']);
        }

        if ($studentResult) {
            while ($row = $studentResult->fetch_assoc()) {
                $courseId = (int) $row['id'];
                $courseMap[$courseId] = [
                    'course_id' => $courseId,
                    'course_name' => $row['course_name'],
                    'course_code' => $row['course_code'] ?? '',
                    'applications' => (int) $row['applications'],
                    'approved' => (int) $row['approved'],
                    'pending' => (int) $row['pending'],
                    'batch_enrolled' => (int) $row['batch_enrolled'],
                    'batch_count' => 0,
                ];
            }
        }

        if (report_monitor_table_exists($conn, 'batches')) {
            $batchSql = "SELECT c.id,
                                c.course_name,
                                c.course_code,
                                COUNT(DISTINCT b.id) AS batch_count
                         FROM batches b
                         INNER JOIN courses c ON c.id = b.course_id
                         WHERE 1=1{$scopeFilter['sql']}";
            $batchTypes = $scopeFilter['types'];
            $batchValues = $scopeFilter['values'];
            report_monitor_append_batch_period_overlap($batchSql, $batchTypes, $batchValues, 'b', $monthFilter);
            $batchSql .= ' GROUP BY c.id, c.course_name, c.course_code';

            $batchResult = report_monitor_bind_and_execute($conn, $batchSql, $batchTypes, $batchValues);
            if ($batchResult) {
                while ($row = $batchResult->fetch_assoc()) {
                    $courseId = (int) $row['id'];
                    if (!isset($courseMap[$courseId])) {
                        $courseMap[$courseId] = [
                            'course_id' => $courseId,
                            'course_name' => $row['course_name'],
                            'course_code' => $row['course_code'] ?? '',
                            'applications' => 0,
                            'approved' => 0,
                            'pending' => 0,
                            'batch_enrolled' => 0,
                            'batch_count' => 0,
                        ];
                    }
                    $courseMap[$courseId]['batch_count'] = (int) $row['batch_count'];
                }
            }
        }

        $rows = array_values($courseMap);
        usort($rows, static function ($a, $b) {
            $appCompare = $b['applications'] <=> $a['applications'];
            return $appCompare !== 0 ? $appCompare : strcasecmp($a['course_name'], $b['course_name']);
        });

        return $rows;
    }

    /** Month keys and display labels for an FY period (quarter or full year). */
    function report_monitor_build_graph_month_axis(array $graphMonths): array {
        $monthKeys = [];
        $labels = [];
        foreach ($graphMonths as $graphMonth) {
            $monthKeys[] = sprintf('%04d-%02d', (int) $graphMonth['year'], (int) $graphMonth['month']);
            $labels[] = date('M Y', mktime(0, 0, 0, (int) $graphMonth['month'], 1, (int) $graphMonth['year']));
        }
        return ['month_keys' => $monthKeys, 'labels' => $labels];
    }

    /** Overall monthly trend for the selected FY quarter or full financial year. */
    function report_monitor_get_period_monthly($conn, array $courseIds = [], $centreId = 0, array $monthFilter = [], array $graphMonths = []) {
        $axis = report_monitor_build_graph_month_axis($graphMonths);
        $monthKeys = $axis['month_keys'];
        $labels = $axis['labels'];

        $applyMap = array_fill_keys($monthKeys, 0);
        $enrollMap = array_fill_keys($monthKeys, 0);
        $batchMap = array_fill_keys($monthKeys, 0);

        if (empty($monthFilter['active']) || empty($monthKeys)) {
            return [
                'labels' => $labels,
                'applications' => array_values($applyMap),
                'batch_enrollments' => array_values($enrollMap),
                'batches_created' => array_values($batchMap),
            ];
        }

        $monthStart = $monthFilter['start'];
        $monthNext = $monthFilter['next_start'];
        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $activeCondition = report_monitor_student_active_sql('s');
        $batchCondition = report_monitor_student_batch_enrolled_condition($conn, 's');
        $batchMonthExpr = report_monitor_batch_month_column_sql('b');

        $applySql = "SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS month_key,
                            COUNT(DISTINCT s.id) AS total
                     FROM students s
                     INNER JOIN courses c ON c.id = s.course_id
                     WHERE {$activeCondition}{$scopeFilter['sql']}
                     AND s.created_at >= ? AND s.created_at < ?
                     GROUP BY month_key";
        $applyTypes = $scopeFilter['types'] . 'ss';
        $applyValues = array_merge($scopeFilter['values'], [$monthStart, $monthNext]);
        $applyResult = report_monitor_bind_and_execute($conn, $applySql, $applyTypes, $applyValues);
        if ($applyResult) {
            while ($row = $applyResult->fetch_assoc()) {
                if (isset($applyMap[$row['month_key']])) {
                    $applyMap[$row['month_key']] = (int) $row['total'];
                }
            }
        }

        $enrollSql = "SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS month_key,
                             SUM(CASE WHEN {$batchCondition} THEN 1 ELSE 0 END) AS total
                      FROM students s
                      INNER JOIN courses c ON c.id = s.course_id
                      WHERE {$activeCondition}{$scopeFilter['sql']}
                      AND s.created_at >= ? AND s.created_at < ?
                      GROUP BY month_key";
        $enrollTypes = $scopeFilter['types'] . 'ss';
        $enrollValues = array_merge($scopeFilter['values'], [$monthStart, $monthNext]);
        $enrollResult = report_monitor_bind_and_execute($conn, $enrollSql, $enrollTypes, $enrollValues);
        if ($enrollResult) {
            while ($row = $enrollResult->fetch_assoc()) {
                if (isset($enrollMap[$row['month_key']])) {
                    $enrollMap[$row['month_key']] = (int) $row['total'];
                }
            }
        }

        if (report_monitor_table_exists($conn, 'batches')) {
            $batchSql = "SELECT DATE_FORMAT({$batchMonthExpr}, '%Y-%m') AS month_key,
                                COUNT(DISTINCT b.id) AS total
                         FROM batches b
                         INNER JOIN courses c ON c.id = b.course_id
                         WHERE {$batchMonthExpr} >= ? AND {$batchMonthExpr} < ?{$scopeFilter['sql']}
                         GROUP BY month_key";
            $batchTypes = 'ss' . $scopeFilter['types'];
            $batchValues = array_merge([$monthStart, $monthNext], $scopeFilter['values']);
            $batchResult = report_monitor_bind_and_execute($conn, $batchSql, $batchTypes, $batchValues);
            if ($batchResult) {
                while ($row = $batchResult->fetch_assoc()) {
                    if (isset($batchMap[$row['month_key']])) {
                        $batchMap[$row['month_key']] = (int) $row['total'];
                    }
                }
            }
        }

        $applications = [];
        $batchEnrollments = [];
        $batchesCreated = [];
        foreach ($monthKeys as $monthKey) {
            $applications[] = $applyMap[$monthKey];
            $batchEnrollments[] = $enrollMap[$monthKey];
            $batchesCreated[] = $batchMap[$monthKey];
        }

        return [
            'labels' => $labels,
            'applications' => $applications,
            'batch_enrollments' => $batchEnrollments,
            'batches_created' => $batchesCreated,
        ];
    }

    function report_monitor_get_category_quarter_summary($conn, array $courseIds = [], $centreId = 0, int $fyStartYear = null) {
        if (!report_monitor_table_exists($conn, 'students')) {
            return [];
        }

        if ($fyStartYear === null) {
            $fyStartYear = report_monitor_get_financial_year_start();
        }

        $quarters = [
            'Q1' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q1'),
            'Q2' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q2'),
            'Q3' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q3'),
            'Q4' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q4'),
        ];

        $fyStart = $quarters['Q1']['start_date'];
        $fyEnd = $quarters['Q4']['next_start'];

        $categoryKeys = array_keys(get_report_monitor_category_groups());
        $rows = [];
        foreach ($categoryKeys as $key) {
            $rows[$key] = [
                'key' => $key,
                'label' => report_monitor_category_label($key),
                'Q1' => 0,
                'Q2' => 0,
                'Q3' => 0,
                'Q4' => 0,
                'total' => 0,
            ];
        }
        $rows['uncategorized'] = [
            'key' => 'uncategorized',
            'label' => 'Uncategorized',
            'Q1' => 0,
            'Q2' => 0,
            'Q3' => 0,
            'Q4' => 0,
            'total' => 0,
        ];

        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $activeCondition = report_monitor_student_active_sql('s');
        $categoryExpr = report_monitor_course_category_sql('c');
        $quarterCase = "CASE\n";
        foreach ($quarters as $quarterKey => $range) {
            $quarterCase .= "    WHEN s.created_at >= '" . $conn->real_escape_string($range['start_date']) . "' AND s.created_at < '" . $conn->real_escape_string($range['next_start']) . "' THEN '{$quarterKey}'\n";
        }
        $quarterCase .= "    ELSE '' END";

        $batchCondition = report_monitor_student_batch_enrolled_condition($conn, 's');
        $sql = "SELECT {$quarterCase} AS quarter_key,
                       {$categoryExpr} AS raw_category,
                       SUM(CASE WHEN {$batchCondition} THEN 1 ELSE 0 END) AS total
                FROM students s
                INNER JOIN courses c ON c.id = s.course_id
                WHERE {$activeCondition}
                  AND s.created_at >= ? AND s.created_at < ?
                  {$scopeFilter['sql']}
                GROUP BY quarter_key, raw_category";

        $types = 'ss' . $scopeFilter['types'];
        $values = array_merge([$fyStart, $fyEnd], $scopeFilter['values']);
        $result = report_monitor_bind_and_execute($conn, $sql, $types, $values);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $quarter = $row['quarter_key'];
                if (!in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
                    continue;
                }
                $categoryKey = report_monitor_resolve_category_group($row['raw_category']);
                if (!isset($rows[$categoryKey])) {
                    $categoryKey = 'uncategorized';
                }
                $count = (int) $row['total'];
                $rows[$categoryKey][$quarter] += $count;
                $rows[$categoryKey]['total'] += $count;
            }
        }

        return array_values($rows);
    }

    function report_monitor_format_display_date(?string $date): string {
        if ($date === null || $date === '' || $date === '0000-00-00' || strpos($date, '0000-00-00') === 0) {
            return '';
        }

        $timestamp = strtotime($date);
        return $timestamp ? date('j M Y', $timestamp) : '';
    }

    function report_monitor_format_batch_period_label(?string $startDate, ?string $endDate): string {
        $startLabel = report_monitor_format_display_date($startDate);
        $endLabel = report_monitor_format_display_date($endDate);

        if ($startLabel === '' && $endLabel === '') {
            return '—';
        }
        if ($endLabel === '' || $startLabel === $endLabel) {
            return $startLabel !== '' ? $startLabel : $endLabel;
        }

        return $startLabel . ' to ' . $endLabel;
    }

    function report_monitor_get_course_batch_meta_map($conn, array $courseIds = [], $centreId = 0, int $fyStartYear = null): array {
        if (!report_monitor_table_exists($conn, 'batches')) {
            return [];
        }

        if ($fyStartYear === null) {
            $fyStartYear = report_monitor_get_financial_year_start();
        }

        $fyRange = report_monitor_get_financial_quarter_range($fyStartYear, 'FY');
        $monthFilter = [
            'active' => true,
            'start' => $fyRange['start_date'] . ' 00:00:00',
            'end' => $fyRange['end_date'] . ' 23:59:59',
        ];

        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $batchStartExpr = report_monitor_batch_start_sql('b');
        $batchEndExpr = report_monitor_batch_end_sql('b');
        $hasSchemes = report_monitor_table_exists($conn, 'schemes');
        $schemeJoin = $hasSchemes ? 'LEFT JOIN schemes s ON s.id = b.scheme_id' : '';
        $schemeSelect = $hasSchemes
            ? "GROUP_CONCAT(DISTINCT NULLIF(TRIM(s.scheme_name), '') ORDER BY s.scheme_name SEPARATOR ', ')"
            : "''";

        $sql = "SELECT
                    c.id AS course_id,
                    {$schemeSelect} AS scheme_names,
                    MIN({$batchStartExpr}) AS batch_start,
                    MAX({$batchEndExpr}) AS batch_end
                FROM batches b
                INNER JOIN courses c ON c.id = b.course_id
                {$schemeJoin}
                WHERE 1=1{$scopeFilter['sql']}";

        $types = $scopeFilter['types'];
        $values = $scopeFilter['values'];
        report_monitor_append_batch_period_overlap($sql, $types, $values, 'b', $monthFilter);
        $sql .= ' GROUP BY c.id';

        $result = report_monitor_bind_and_execute($conn, $sql, $types, $values);
        if (!$result) {
            return [];
        }

        $metaMap = [];
        while ($row = $result->fetch_assoc()) {
            $courseId = (int) ($row['course_id'] ?? 0);
            if ($courseId <= 0) {
                continue;
            }

            $schemeNames = trim((string) ($row['scheme_names'] ?? ''));
            $metaMap[$courseId] = [
                'scheme_names' => $schemeNames !== '' ? $schemeNames : '—',
                'batch_start' => $row['batch_start'] ?? null,
                'batch_end' => $row['batch_end'] ?? null,
                'batch_period_label' => report_monitor_format_batch_period_label(
                    $row['batch_start'] ?? null,
                    $row['batch_end'] ?? null
                ),
            ];
        }

        return $metaMap;
    }

    function report_monitor_get_category_course_quarter_summary($conn, array $courseIds = [], $centreId = 0, int $fyStartYear = null) {
        if (!report_monitor_table_exists($conn, 'students')) {
            return [];
        }

        if ($fyStartYear === null) {
            $fyStartYear = report_monitor_get_financial_year_start();
        }

        $quarters = [
            'Q1' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q1'),
            'Q2' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q2'),
            'Q3' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q3'),
            'Q4' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q4'),
        ];

        $fyStart = $quarters['Q1']['start_date'];
        $fyEnd = $quarters['Q4']['next_start'];

        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $activeCondition = report_monitor_student_active_sql('s');
        $categoryExpr = report_monitor_course_category_sql('c');
        $batchCondition = report_monitor_student_batch_enrolled_condition($conn, 's');

        $sql = "SELECT c.id AS course_id,
                       c.course_name,
                       c.course_code,
                       COALESCE(NULLIF(TRIM(ce.name), ''), 'Unassigned Centre') AS centre_name,
                       {$categoryExpr} AS raw_category,
                       CASE
                           WHEN s.created_at >= ? AND s.created_at < ? THEN 'Q1'
                           WHEN s.created_at >= ? AND s.created_at < ? THEN 'Q2'
                           WHEN s.created_at >= ? AND s.created_at < ? THEN 'Q3'
                           WHEN s.created_at >= ? AND s.created_at < ? THEN 'Q4'
                           ELSE ''
                       END AS quarter_key,
                       SUM(CASE WHEN {$batchCondition} THEN 1 ELSE 0 END) AS total
                FROM students s
                INNER JOIN courses c ON c.id = s.course_id
                LEFT JOIN centres ce ON ce.id = c.centre_id
                WHERE {$activeCondition}
                  AND s.created_at >= ? AND s.created_at < ?
                  {$scopeFilter['sql']}
                GROUP BY c.id, c.course_name, c.course_code, centre_name, raw_category, quarter_key";

        $types = str_repeat('s', 10) . $scopeFilter['types'];
        $values = array_merge([
            $quarters['Q1']['start_date'], $quarters['Q1']['next_start'],
            $quarters['Q2']['start_date'], $quarters['Q2']['next_start'],
            $quarters['Q3']['start_date'], $quarters['Q3']['next_start'],
            $quarters['Q4']['start_date'], $quarters['Q4']['next_start'],
            $fyStart, $fyEnd,
        ], $scopeFilter['values']);
        $result = report_monitor_bind_and_execute($conn, $sql, $types, $values);

        if (!$result) {
            return [];
        }

        $grouped = [];
        while ($row = $result->fetch_assoc()) {
            $categoryKey = report_monitor_resolve_category_group($row['raw_category']);
            $courseId = (int) $row['course_id'];

            if (!isset($grouped[$categoryKey])) {
                $grouped[$categoryKey] = [];
            }
            if (!isset($grouped[$categoryKey][$courseId])) {
                $grouped[$categoryKey][$courseId] = [
                    'course_id' => $courseId,
                    'course_name' => $row['course_name'],
                    'course_code' => $row['course_code'] ?? '',
                    'centre_name' => $row['centre_name'],
                    'Q1' => 0,
                    'Q2' => 0,
                    'Q3' => 0,
                    'Q4' => 0,
                    'total' => 0,
                ];
            }

            $quarterKey = $row['quarter_key'] ?? '';
            if (in_array($quarterKey, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
                $count = (int) $row['total'];
                $grouped[$categoryKey][$courseId][$quarterKey] += $count;
                $grouped[$categoryKey][$courseId]['total'] += $count;
            }
        }

        foreach ($grouped as $categoryKey => $courses) {
            $courses = array_values($courses);
            usort($courses, static function ($a, $b) {
                $compare = $b['total'] <=> $a['total'];
                return $compare !== 0 ? $compare : strcasecmp($a['course_name'], $b['course_name']);
            });
            $grouped[$categoryKey] = array_values(array_filter($courses, static function ($courseRow) {
                return (int) ($courseRow['total'] ?? 0) > 0;
            }));
        }

        $courseBatchMeta = report_monitor_get_course_batch_meta_map($conn, $courseIds, $centreId, $fyStartYear);
        foreach ($grouped as $categoryKey => $courses) {
            foreach ($courses as $index => $courseRow) {
                $courseId = (int) ($courseRow['course_id'] ?? 0);
                $meta = $courseBatchMeta[$courseId] ?? [
                    'scheme_names' => '—',
                    'batch_period_label' => '—',
                ];
                $grouped[$categoryKey][$index]['scheme_names'] = $meta['scheme_names'];
                $grouped[$categoryKey][$index]['batch_period_label'] = $meta['batch_period_label'];
            }
        }

        return $grouped;
    }

    function report_monitor_get_internship_course_quarter_summary($conn, array $courseIds = [], $centreId = 0, int $fyStartYear = null) {
        $grouped = report_monitor_get_category_course_quarter_summary($conn, $courseIds, $centreId, $fyStartYear);
        return $grouped['internship_bootcamp'] ?? [];
    }

    /** Course-wise monthly registered / admission / batch counts for an FY period. */
    function report_monitor_get_course_monthly_progress($conn, array $courseIds = [], $centreId = 0, array $monthFilter = [], array $graphMonths = [], $chartLimit = 8) {
        $axis = report_monitor_build_graph_month_axis($graphMonths);
        $monthKeys = $axis['month_keys'];
        $labels = $axis['labels'];
        $monthCount = count($monthKeys);

        if (empty($monthFilter['active']) || $monthCount === 0) {
            return [
                'labels' => $labels,
                'month_keys' => $monthKeys,
                'courses' => [],
                'chart_courses' => [],
            ];
        }

        $monthStart = $monthFilter['start'];
        $monthNext = $monthFilter['next_start'];
        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $activeCondition = report_monitor_student_active_sql('s');
        $batchCondition = report_monitor_student_batch_enrolled_condition($conn, 's');
        $batchMonthExpr = report_monitor_batch_month_column_sql('b');
        $courseMap = [];

        $initCourse = static function (array &$map, array $row, int $monthCount): void {
            $courseId = (int) $row['id'];
            if (!isset($map[$courseId])) {
                $map[$courseId] = [
                    'course_id' => $courseId,
                    'course_name' => $row['course_name'],
                    'course_code' => $row['course_code'] ?? '',
                    'registered' => array_fill(0, $monthCount, 0),
                    'admissions' => array_fill(0, $monthCount, 0),
                    'batches' => array_fill(0, $monthCount, 0),
                    'total_registered' => 0,
                    'total_admissions' => 0,
                    'total_batches' => 0,
                ];
            }
        };

        $monthIndex = array_flip($monthKeys);

        $studentSql = "SELECT c.id,
                              c.course_name,
                              c.course_code,
                              DATE_FORMAT(s.created_at, '%Y-%m') AS month_key,
                              COUNT(DISTINCT s.id) AS applications,
                              SUM(CASE WHEN {$batchCondition} THEN 1 ELSE 0 END) AS batch_enrolled
                       FROM students s
                       INNER JOIN courses c ON c.id = s.course_id
                       WHERE {$activeCondition}{$scopeFilter['sql']}
                       AND s.created_at >= ? AND s.created_at < ?
                       GROUP BY c.id, c.course_name, c.course_code, month_key";
        $studentTypes = $scopeFilter['types'] . 'ss';
        $studentValues = array_merge($scopeFilter['values'], [$monthStart, $monthNext]);
        $studentResult = report_monitor_bind_and_execute($conn, $studentSql, $studentTypes, $studentValues);
        if ($studentResult) {
            while ($row = $studentResult->fetch_assoc()) {
                $key = $row['month_key'] ?? '';
                if (!isset($monthIndex[$key])) {
                    continue;
                }
                $initCourse($courseMap, $row, $monthCount);
                $idx = $monthIndex[$key];
                $courseId = (int) $row['id'];
                $registered = (int) $row['applications'];
                $admissions = (int) $row['batch_enrolled'];
                $courseMap[$courseId]['registered'][$idx] = $registered;
                $courseMap[$courseId]['admissions'][$idx] = $admissions;
                $courseMap[$courseId]['total_registered'] += $registered;
                $courseMap[$courseId]['total_admissions'] += $admissions;
            }
        }

        if (report_monitor_table_exists($conn, 'batches')) {
            $batchSql = "SELECT c.id,
                                c.course_name,
                                c.course_code,
                                DATE_FORMAT({$batchMonthExpr}, '%Y-%m') AS month_key,
                                COUNT(DISTINCT b.id) AS batch_count
                         FROM batches b
                         INNER JOIN courses c ON c.id = b.course_id
                         WHERE {$batchMonthExpr} >= ? AND {$batchMonthExpr} < ?{$scopeFilter['sql']}
                         GROUP BY c.id, c.course_name, c.course_code, month_key";
            $batchTypes = 'ss' . $scopeFilter['types'];
            $batchValues = array_merge([$monthStart, $monthNext], $scopeFilter['values']);
            $batchResult = report_monitor_bind_and_execute($conn, $batchSql, $batchTypes, $batchValues);
            if ($batchResult) {
                while ($row = $batchResult->fetch_assoc()) {
                    $key = $row['month_key'] ?? '';
                    if (!isset($monthIndex[$key])) {
                        continue;
                    }
                    $initCourse($courseMap, $row, $monthCount);
                    $idx = $monthIndex[$key];
                    $courseId = (int) $row['id'];
                    $batchCount = (int) $row['batch_count'];
                    $courseMap[$courseId]['batches'][$idx] = $batchCount;
                    $courseMap[$courseId]['total_batches'] += $batchCount;
                }
            }
        }

        $courses = array_values($courseMap);
        usort($courses, static function ($a, $b) {
            $regCompare = $b['total_registered'] <=> $a['total_registered'];
            return $regCompare !== 0 ? $regCompare : strcasecmp($a['course_name'], $b['course_name']);
        });

        $courses = array_values(array_filter($courses, static function ($row) {
            return ($row['total_registered'] ?? 0) > 0
                || ($row['total_admissions'] ?? 0) > 0
                || ($row['total_batches'] ?? 0) > 0;
        }));

        return [
            'labels' => $labels,
            'month_keys' => $monthKeys,
            'courses' => $courses,
            'chart_courses' => array_slice($courses, 0, max(1, (int) $chartLimit)),
        ];
    }

    function report_monitor_get_batch_monthly($conn, $months = 12, array $courseIds = [], $centreId = 0) {
        $labels = [];
        $batchCounts = [];
        $enrollmentCounts = [];
        $applicationCounts = [];

        $monthKeys = [];
        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $monthKeys[] = (new DateTime('first day of this month'))->modify('-' . $offset . ' months')->format('Y-m');
        }

        $batchMap = array_fill_keys($monthKeys, 0);
        $enrollMap = array_fill_keys($monthKeys, 0);
        $applyMap = array_fill_keys($monthKeys, 0);

        if (report_monitor_table_exists($conn, 'batches')) {
            $batchScope = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
            $batchSql = "SELECT DATE_FORMAT(b.created_at, '%Y-%m') AS month_key, COUNT(*) AS total
                         FROM batches b
                         INNER JOIN courses c ON c.id = b.course_id
                         WHERE b.created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL ? MONTH), '%Y-%m-01')
                         {$batchScope['sql']}
                         GROUP BY month_key";
            $types = 'i' . $batchScope['types'];
            $values = array_merge([(int) $months - 1], $batchScope['values']);
            $result = report_monitor_bind_and_execute($conn, $batchSql, $types, $values);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    if (isset($batchMap[$row['month_key']])) {
                        $batchMap[$row['month_key']] = (int) $row['total'];
                    }
                }
            }
        }

        if (report_monitor_table_exists($conn, 'batch_students')) {
            $batchScope = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
            $enrollmentTs = report_monitor_enrollment_timestamp_sql($conn, 'bs', 'st');
            $activeConditionSt = report_monitor_student_active_sql('st');
            $enrollSql = "SELECT DATE_FORMAT({$enrollmentTs}, '%Y-%m') AS month_key,
                                 COUNT(DISTINCT COALESCE(bs.student_record_id, bs.student_id, bs.id)) AS total
                          FROM batch_students bs
                          INNER JOIN batches b ON b.id = bs.batch_id
                          INNER JOIN courses c ON c.id = b.course_id
                          INNER JOIN students st ON st.id = COALESCE(bs.student_record_id, bs.student_id)
                          WHERE {$activeConditionSt}
                          AND {$enrollmentTs} >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL ? MONTH), '%Y-%m-01')
                          {$batchScope['sql']}
                          GROUP BY month_key";
            $types = 'i' . $batchScope['types'];
            $values = array_merge([(int) $months - 1], $batchScope['values']);
            $result = report_monitor_bind_and_execute($conn, $enrollSql, $types, $values);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    if (isset($enrollMap[$row['month_key']])) {
                        $enrollMap[$row['month_key']] = (int) $row['total'];
                    }
                }
            }
        }

        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $activeCondition = report_monitor_student_active_sql('s');
        $applySql = "SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS month_key, COUNT(*) AS total
                     FROM students s
                     INNER JOIN courses c ON c.id = s.course_id
                     WHERE {$activeCondition}
                     AND s.created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL ? MONTH), '%Y-%m-01')
                     {$scopeFilter['sql']}
                     GROUP BY month_key";
        $types = 'i' . $scopeFilter['types'];
        $values = array_merge([(int) $months - 1], $scopeFilter['values']);
        $result = report_monitor_bind_and_execute($conn, $applySql, $types, $values);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (isset($applyMap[$row['month_key']])) {
                    $applyMap[$row['month_key']] = (int) $row['total'];
                }
            }
        }

        foreach ($monthKeys as $monthKey) {
            $labels[] = (new DateTime($monthKey . '-01'))->format('M Y');
            $batchCounts[] = $batchMap[$monthKey];
            $enrollmentCounts[] = $enrollMap[$monthKey];
            $applicationCounts[] = $applyMap[$monthKey];
        }

        return [
            'labels' => $labels,
            'batches_created' => $batchCounts,
            'batch_enrollments' => $enrollmentCounts,
            'applications' => $applicationCounts,
        ];
    }

    function report_monitor_table_has_column($conn, $table, $column) {
        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return $result && $result->num_rows > 0;
    }

    function report_monitor_get_batch_details($conn, array $courseIds = [], $centreId = 0, array $monthFilter = []) {
        if (!report_monitor_table_exists($conn, 'batches')) {
            return [];
        }

        $rows = [];
        $categoryExpr = report_monitor_course_category_sql('c');
        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $types = $scopeFilter['types'];
        $values = $scopeFilter['values'];
        $batchMonthExpr = report_monitor_batch_month_column_sql('b');

        $hasBatchFaculty = report_monitor_table_exists($conn, 'batch_faculty') && report_monitor_table_exists($conn, 'faculty');
        $facultySelect = $hasBatchFaculty
            ? report_monitor_batch_faculty_names_sql('b') . ' AS faculty_names'
            : "COALESCE(NULLIF(TRIM(b.batch_coordinator), ''), NULLIF(TRIM(b.batch_coordinator), '0')) AS faculty_names";
        $hasScannedColumn = report_monitor_table_has_column($conn, 'batches', 'scanned_admission_order');
        $scannedSelect = $hasScannedColumn
            ? 'b.scanned_admission_order AS scanned_admission_order'
            : 'NULL AS scanned_admission_order';

        $sql = "SELECT
                    b.id,
                    b.batch_name,
                    b.batch_code,
                    b.batch_coordinator,
                    b.start_date,
                    b.end_date,
                    b.seats_total,
                    b.seats_filled,
                    b.status,
                    b.created_at,
                    c.id AS course_id,
                    c.course_name,
                    {$categoryExpr} AS course_category,
                    COALESCE(cen.name, c.training_center, 'Unassigned') AS centre_name,
                    {$facultySelect},
                    {$scannedSelect}
                FROM batches b
                INNER JOIN courses c ON c.id = b.course_id
                LEFT JOIN centres cen ON cen.id = c.centre_id
                WHERE 1=1{$scopeFilter['sql']}";

        report_monitor_append_batch_period_overlap($sql, $types, $values, 'b', $monthFilter);
        $sql .= " ORDER BY b.created_at DESC, b.id DESC LIMIT 100";

        $result = report_monitor_bind_and_execute($conn, $sql, $types, $values);
        if (!$result) {
            return [];
        }

        require_once __DIR__ . '/../batch_module/includes/batch_functions.php';

        while ($row = $result->fetch_assoc()) {
            $enrolled = function_exists('getBatchEnrolledCount')
                ? getBatchEnrolledCount((int) $row['id'], $conn)
                : (int) ($row['seats_filled'] ?? 0);
            $seatsTotal = (int) ($row['seats_total'] ?? 0);
            $rows[] = [
                'id' => (int) $row['id'],
                'batch_name' => $row['batch_name'],
                'batch_code' => $row['batch_code'] ?? '',
                'batch_coordinator' => $row['batch_coordinator'] ?? '—',
                'faculty_names' => trim((string) ($row['faculty_names'] ?? '')) ?: '—',
                'course_name' => $row['course_name'],
                'course_category' => report_monitor_category_label(report_monitor_resolve_category_group($row['course_category'])),
                'centre_name' => $row['centre_name'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'seats_total' => $seatsTotal,
                'enrolled' => $enrolled,
                'fill_rate' => $seatsTotal > 0 ? round(($enrolled / $seatsTotal) * 100, 1) : 0,
                'status' => $row['status'] ?? 'active',
                'created_at' => $row['created_at'],
                'scanned_admission_order' => $row['scanned_admission_order'] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * Return number of students (registered in the given monthFilter) grouped by batch.
     * Students counted are those active and assigned to a batch (via students.batch_id or batch_students mapping).
     */
    function report_monitor_get_admissions_by_batch($conn, array $courseIds = [], $centreId = 0, array $monthFilter = []) {
        if (!report_monitor_table_exists($conn, 'students')) {
            return [];
        }

        $rows = [];
        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $types = $scopeFilter['types'];
        $values = $scopeFilter['values'];

        $activeCondition = report_monitor_student_active_sql('s');
        $enrollmentExpr = report_monitor_enrollment_timestamp_sql($conn, 'bs', 's');
        $batchStartExpr = report_monitor_batch_start_sql('b');
        $batchEndExpr = report_monitor_batch_end_sql('b');

        // Count students enrolled in the selected period and assigned to a batch active during that period.
        $sql = "SELECT
                    COALESCE(b.id, 0) AS batch_id,
                    COALESCE(b.batch_name, 'Unassigned Batch') AS batch_name,
                    COALESCE(b.batch_code, '') AS batch_code,
                    c.course_name,
                    COALESCE(cen.name, c.training_center, 'Unassigned') AS centre_name,
                    COUNT(DISTINCT COALESCE(bs.student_record_id, bs.student_id, s.id)) AS admissions
                FROM students s
                LEFT JOIN batch_students bs ON (bs.student_record_id = s.id OR bs.student_id = s.id)
                LEFT JOIN batches b ON b.id = COALESCE(bs.batch_id, s.batch_id)
                LEFT JOIN courses c ON c.id = COALESCE(b.course_id, s.course_id)
                LEFT JOIN centres cen ON cen.id = c.centre_id
                WHERE {$activeCondition} AND COALESCE(bs.batch_id, s.batch_id) IS NOT NULL";

        if (!empty($monthFilter['active'])) {
            $sql .= " AND {$batchStartExpr} <= ? AND {$batchEndExpr} >= ?";
            $sql .= " AND {$enrollmentExpr} >= ? AND {$enrollmentExpr} < ?";
            $types .= 'ssss';
            $values = array_merge($values, [
                $monthFilter['end'],
                $monthFilter['start'],
                $monthFilter['start'],
                $monthFilter['next_start'],
            ]);
        }

        // Apply scope filter (courses/centre)
        $sql .= " {$scopeFilter['sql']} GROUP BY batch_id, batch_name, batch_code, c.course_name, centre_name ORDER BY admissions DESC, batch_name ASC";

        $result = report_monitor_bind_and_execute($conn, $sql, $types, $values);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'batch_id' => (int) $row['batch_id'],
                    'batch_name' => $row['batch_name'],
                    'batch_code' => $row['batch_code'],
                    'course_name' => $row['course_name'],
                    'centre_name' => $row['centre_name'],
                    'admissions' => (int) $row['admissions'],
                ];
            }
        }

        return $rows;
    }

    function report_monitor_get_faculty_stats($conn, array $courseIds = [], $centreId = 0, array $monthFilter = []) {
        if (!report_monitor_table_exists($conn, 'batch_faculty') || !report_monitor_table_exists($conn, 'faculty')) {
            return [];
        }

        $categoryExpr = report_monitor_course_category_sql('c');
        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $batchMonthExpr = report_monitor_batch_month_column_sql('b');
        $hasBatchStudents = report_monitor_table_exists($conn, 'batch_students');

        $enrolledSelect = $hasBatchStudents
            ? '(SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id)'
            : '(SELECT COUNT(*) FROM students st WHERE st.batch_id = b.id AND LOWER(COALESCE(st.status, "")) NOT IN ("rejected", "inactive"))';

        $sql = "SELECT
                    f.id AS faculty_id,
                    f.name,
                    f.designation,
                    f.department,
                    b.id AS batch_id,
                    b.batch_name,
                    b.batch_code,
                    b.start_date,
                    b.end_date,
                    b.status AS batch_status,
                    c.course_name,
                    {$categoryExpr} AS raw_category,
                    COALESCE(cen.name, c.training_center, 'Unassigned') AS centre_name,
                    {$enrolledSelect} AS enrolled
                FROM faculty f
                INNER JOIN batch_faculty bf ON bf.faculty_id = f.id
                INNER JOIN batches b ON b.id = bf.batch_id
                INNER JOIN courses c ON c.id = b.course_id
                LEFT JOIN centres cen ON cen.id = c.centre_id
                WHERE f.is_active = 1{$scopeFilter['sql']}";
        $types = $scopeFilter['types'];
        $values = $scopeFilter['values'];
        report_monitor_append_batch_period_overlap($sql, $types, $values, 'b', $monthFilter);
        $sql .= ' ORDER BY f.name ASC, b.start_date DESC, b.id DESC';

        $result = report_monitor_bind_and_execute($conn, $sql, $types, $values);
        if (!$result) {
            return [];
        }

        $facultyMap = [];
        while ($row = $result->fetch_assoc()) {
            $facultyId = (int) $row['faculty_id'];
            if (!isset($facultyMap[$facultyId])) {
                $facultyMap[$facultyId] = [
                    'faculty_id' => $facultyId,
                    'name' => $row['name'],
                    'designation' => $row['designation'] ?? '',
                    'department' => $row['department'] ?? '',
                    'batch_count' => 0,
                    'course_count' => 0,
                    'students_trained' => 0,
                    'categories' => [],
                    'centres' => [],
                    'batches' => [],
                    '_course_ids' => [],
                    '_category_keys' => [],
                    '_centre_names' => [],
                ];
            }

            $categoryKey = report_monitor_resolve_category_group($row['raw_category']);
            $categoryLabel = report_monitor_category_label($categoryKey);
            $enrolled = (int) ($row['enrolled'] ?? 0);

            $facultyMap[$facultyId]['batch_count']++;
            $facultyMap[$facultyId]['students_trained'] += $enrolled;
            $facultyMap[$facultyId]['_category_keys'][$categoryKey] = $categoryLabel;
            $facultyMap[$facultyId]['_centre_names'][$row['centre_name']] = true;
            $facultyMap[$facultyId]['batches'][] = [
                'batch_id' => (int) $row['batch_id'],
                'batch_name' => $row['batch_name'],
                'batch_code' => $row['batch_code'] ?? '',
                'course_name' => $row['course_name'],
                'category' => $categoryLabel,
                'centre_name' => $row['centre_name'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'status' => $row['batch_status'] ?? '',
                'enrolled' => $enrolled,
            ];
        }

        $rows = [];
        foreach ($facultyMap as $faculty) {
            $faculty['categories'] = array_values($faculty['_category_keys']);
            $faculty['centres'] = array_keys($faculty['_centre_names']);
            $faculty['course_count'] = count(array_unique(array_column($faculty['batches'], 'course_name')));
            unset($faculty['_course_ids'], $faculty['_category_keys'], $faculty['_centre_names']);
            $rows[] = $faculty;
        }

        usort($rows, static function ($a, $b) {
            $batchCompare = $b['batch_count'] <=> $a['batch_count'];
            return $batchCompare !== 0 ? $batchCompare : strcasecmp($a['name'], $b['name']);
        });

        return $rows;
    }

    function report_monitor_get_centres_list($conn) {
        $centres = [['id' => 0, 'name' => 'All Centres', 'code' => '']];
        if (!report_monitor_table_exists($conn, 'centres')) {
            return $centres;
        }
        $result = $conn->query("SELECT id, name, code FROM centres WHERE is_active = 1 ORDER BY name ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $centres[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'code' => $row['code'] ?? '',
                ];
            }
        }
        return $centres;
    }

    function report_monitor_get_assigned_course_ids($conn, $adminId) {
        $ids = [];
        if ($adminId <= 0 || !report_monitor_table_exists($conn, 'admin_course_assignments')) {
            return $ids;
        }
        $stmt = $conn->prepare("SELECT course_id FROM admin_course_assignments WHERE admin_id = ? AND is_active = 1");
        if (!$stmt) {
            return $ids;
        }
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int) $row['course_id'];
        }
        $stmt->close();
        return $ids;
    }

    /** FY start year for a date (April–March). */
    function report_monitor_get_financial_year_start(?string $referenceDate = null): int {
        $dt = $referenceDate ? new DateTime($referenceDate) : new DateTime();
        $month = (int) $dt->format('n');
        $year = (int) $dt->format('Y');
        return $month >= 4 ? $year : $year - 1;
    }

    /** Current quarter within the Indian financial year (Apr–Mar). */
    function report_monitor_get_current_financial_quarter(): string {
        $month = (int) date('n');
        if ($month >= 4 && $month <= 6) {
            return 'Q1';
        }
        if ($month >= 7 && $month <= 9) {
            return 'Q2';
        }
        if ($month >= 10 && $month <= 12) {
            return 'Q3';
        }
        return 'Q4';
    }

    function report_monitor_format_financial_year_label(int $fyStartYear): string {
        $fyEndShort = substr((string) ($fyStartYear + 1), -2);
        return 'FY ' . $fyStartYear . '-' . $fyEndShort;
    }

    /**
     * Date range and graph months for an FY quarter (Q1 Apr–Jun … Q4 Jan–Mar).
     */
    function report_monitor_get_financial_quarter_range(int $fyStartYear, string $quarter): array {
        $fyLabel = report_monitor_format_financial_year_label($fyStartYear);
        $fyEndYear = $fyStartYear + 1;

        switch ($quarter) {
            case 'FY':
                $startDate = sprintf('%04d-04-01', $fyStartYear);
                $endDate = sprintf('%04d-03-31', $fyEndYear);
                $quarterLabel = 'Full Year (Apr-Mar)';
                $graphMonths = [];
                for ($month = 4; $month <= 12; $month++) {
                    $graphMonths[] = ['month' => $month, 'year' => $fyStartYear];
                }
                for ($month = 1; $month <= 3; $month++) {
                    $graphMonths[] = ['month' => $month, 'year' => $fyEndYear];
                }
                break;
            case 'Q2':
                $startDate = sprintf('%04d-07-01', $fyStartYear);
                $endDate = sprintf('%04d-09-30', $fyStartYear);
                $quarterLabel = 'Q2 (Jul-Sep)';
                $graphMonths = [
                    ['month' => 7, 'year' => $fyStartYear],
                    ['month' => 8, 'year' => $fyStartYear],
                    ['month' => 9, 'year' => $fyStartYear],
                ];
                break;
            case 'Q3':
                $startDate = sprintf('%04d-10-01', $fyStartYear);
                $endDate = sprintf('%04d-12-31', $fyStartYear);
                $quarterLabel = 'Q3 (Oct-Dec)';
                $graphMonths = [
                    ['month' => 10, 'year' => $fyStartYear],
                    ['month' => 11, 'year' => $fyStartYear],
                    ['month' => 12, 'year' => $fyStartYear],
                ];
                break;
            case 'Q4':
                $startDate = sprintf('%04d-01-01', $fyEndYear);
                $endDate = sprintf('%04d-03-31', $fyEndYear);
                $quarterLabel = 'Q4 (Jan-Mar)';
                $graphMonths = [
                    ['month' => 1, 'year' => $fyEndYear],
                    ['month' => 2, 'year' => $fyEndYear],
                    ['month' => 3, 'year' => $fyEndYear],
                ];
                break;
            case 'Q1':
            default:
                $startDate = sprintf('%04d-04-01', $fyStartYear);
                $endDate = sprintf('%04d-06-30', $fyStartYear);
                $quarterLabel = 'Q1 (Apr-Jun)';
                $graphMonths = [
                    ['month' => 4, 'year' => $fyStartYear],
                    ['month' => 5, 'year' => $fyStartYear],
                    ['month' => 6, 'year' => $fyStartYear],
                ];
                break;
        }

        $nextStart = date('Y-m-d', strtotime($endDate . ' +1 day'));

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'next_start' => $nextStart,
            'quarter_label' => $quarterLabel,
            'scope_label' => $fyLabel . ' · ' . $quarterLabel,
            'graph_months' => $graphMonths,
        ];
    }

    /**
     * Faculty-wise student training summary for the selected FY quarter.
     */
    function report_monitor_get_faculty_training_summary($conn, array $courseIds = [], $centreId = 0, array $monthFilter = []) {
        if (!report_monitor_table_exists($conn, 'batch_faculty') || !report_monitor_table_exists($conn, 'faculty')) {
            return [];
        }

        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $batchStartExpr = report_monitor_batch_start_sql('b');
        $batchEndExpr = report_monitor_batch_end_sql('b');
        $hasBatchStudents = report_monitor_table_exists($conn, 'batch_students');

        if ($hasBatchStudents) {
            $studentKey = 'COALESCE(NULLIF(bs.student_record_id, 0), bs.student_id)';
            $studentJoin = 'LEFT JOIN batch_students bs ON bs.batch_id = b.id';
        } else {
            $studentKey = 'st.id';
            $studentJoin = "LEFT JOIN students st ON st.batch_id = b.id AND LOWER(COALESCE(st.status, '')) NOT IN ('rejected', 'inactive')";
        }

        $batchMonthJoin = '';
        $types = '';
        $values = [];
        if (!empty($monthFilter['active'])) {
            $batchMonthJoin = " AND {$batchStartExpr} <= ? AND {$batchEndExpr} >= ?";
            $types .= 'ss';
            $values[] = $monthFilter['end'];
            $values[] = $monthFilter['start'];
        }

        $sql = "SELECT
                    f.id AS faculty_id,
                    f.name,
                    f.designation,
                    f.department,
                    COUNT(DISTINCT CASE WHEN c.id IS NOT NULL THEN b.id END) AS batch_count,
                    COUNT(DISTINCT CASE WHEN c.id IS NOT NULL THEN c.id END) AS course_count,
                    COUNT(DISTINCT CASE WHEN c.id IS NOT NULL THEN {$studentKey} END) AS students_trained
                FROM faculty f
                LEFT JOIN batch_faculty bf ON bf.faculty_id = f.id
                LEFT JOIN batches b ON b.id = bf.batch_id{$batchMonthJoin}
                LEFT JOIN courses c ON c.id = b.course_id{$scopeFilter['sql']}
                {$studentJoin}
                WHERE f.is_active = 1
                GROUP BY f.id, f.name, f.designation, f.department
                ORDER BY students_trained DESC, batch_count DESC, f.name ASC";
        $types .= $scopeFilter['types'];
        $values = array_merge($values, $scopeFilter['values']);

        $result = report_monitor_bind_and_execute($conn, $sql, $types, $values);
        if (!$result) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'faculty_id' => (int) $row['faculty_id'],
                'name' => $row['name'],
                'designation' => $row['designation'] ?? '',
                'department' => $row['department'] ?? '',
                'batch_count' => (int) $row['batch_count'],
                'course_count' => (int) $row['course_count'],
                'students_trained' => (int) $row['students_trained'],
            ];
        }

        return $rows;
    }

    function report_monitor_ensure_category_targets_table($conn) {
        if (report_monitor_table_exists($conn, 'report_category_admission_targets')) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS report_category_admission_targets (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            financial_year_start INT NOT NULL,
            centre_id INT NOT NULL DEFAULT 0,
            category_key VARCHAR(64) NOT NULL,
            annual_target INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_report_cat_target_scope (financial_year_start, centre_id, category_key),
            KEY idx_report_cat_target_year (financial_year_start),
            KEY idx_report_cat_target_centre (centre_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return (bool) $conn->query($sql);
    }

    function report_monitor_get_category_target_keys() {
        $keys = array_keys(get_report_monitor_category_groups());
        $keys[] = 'uncategorized';
        return $keys;
    }

    function report_monitor_get_social_category_groups() {
        return [
            'general' => ['label' => 'General'],
            'obc' => ['label' => 'OBC'],
            'sc' => ['label' => 'SC'],
            'st' => ['label' => 'ST'],
            'ews' => ['label' => 'EWS'],
            'pwd' => ['label' => 'PWD'],
        ];
    }

    function report_monitor_get_social_category_target_keys() {
        return array_keys(report_monitor_get_social_category_groups());
    }

    function report_monitor_social_category_label($key) {
        $groups = report_monitor_get_social_category_groups();
        return $groups[$key]['label'] ?? ucfirst((string) $key);
    }

    function report_monitor_resolve_social_category_key($rawCategory) {
        $upper = strtoupper(trim((string) $rawCategory));
        $map = [
            'GENERAL' => 'general',
            'OBC' => 'obc',
            'SC' => 'sc',
            'ST' => 'st',
            'EWS' => 'ews',
        ];

        return $map[$upper] ?? 'general';
    }

    function report_monitor_is_pwd_status($rawPwdStatus) {
        $value = strtoupper(trim((string) $rawPwdStatus));
        return in_array($value, ['YES', 'Y', '1', 'TRUE'], true);
    }

    function report_monitor_get_social_category_quarter_summary($conn, array $courseIds = [], $centreId = 0, int $fyStartYear = null) {
        if (!report_monitor_table_exists($conn, 'students')) {
            return [];
        }

        if ($fyStartYear === null) {
            $fyStartYear = report_monitor_get_financial_year_start();
        }

        $quarters = [
            'Q1' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q1'),
            'Q2' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q2'),
            'Q3' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q3'),
            'Q4' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q4'),
        ];

        $fyStart = $quarters['Q1']['start_date'];
        $fyEnd = $quarters['Q4']['next_start'];

        $rows = [];
        foreach (report_monitor_get_social_category_groups() as $key => $group) {
            $rows[$key] = [
                'key' => $key,
                'label' => $group['label'],
                'Q1' => 0,
                'Q2' => 0,
                'Q3' => 0,
                'Q4' => 0,
                'total' => 0,
            ];
        }

        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $activeCondition = report_monitor_student_active_sql('s');
        $batchCondition = report_monitor_student_batch_enrolled_condition($conn, 's');
        $quarterCase = "CASE\n";
        foreach ($quarters as $quarterKey => $range) {
            $quarterCase .= "    WHEN s.created_at >= '" . $conn->real_escape_string($range['start_date']) . "' AND s.created_at < '" . $conn->real_escape_string($range['next_start']) . "' THEN '{$quarterKey}'\n";
        }
        $quarterCase .= "    ELSE '' END";

        $sql = "SELECT {$quarterCase} AS quarter_key,
                       s.category AS raw_category,
                       s.pwd_status AS pwd_status,
                       SUM(CASE WHEN {$batchCondition} THEN 1 ELSE 0 END) AS total
                FROM students s
                INNER JOIN courses c ON c.id = s.course_id
                WHERE {$activeCondition}
                  AND s.created_at >= ? AND s.created_at < ?
                  {$scopeFilter['sql']}
                GROUP BY quarter_key, raw_category, pwd_status";

        $types = 'ss' . $scopeFilter['types'];
        $values = array_merge([$fyStart, $fyEnd], $scopeFilter['values']);
        $result = report_monitor_bind_and_execute($conn, $sql, $types, $values);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $quarter = $row['quarter_key'];
                if (!in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
                    continue;
                }

                $count = (int) ($row['total'] ?? 0);
                if ($count <= 0) {
                    continue;
                }

                $socialKey = report_monitor_resolve_social_category_key($row['raw_category'] ?? '');
                if (isset($rows[$socialKey])) {
                    $rows[$socialKey][$quarter] += $count;
                    $rows[$socialKey]['total'] += $count;
                }

                if (report_monitor_is_pwd_status($row['pwd_status'] ?? '')) {
                    $rows['pwd'][$quarter] += $count;
                    $rows['pwd']['total'] += $count;
                }
            }
        }

        return array_values($rows);
    }

    function report_monitor_get_social_category_course_quarter_summary($conn, array $courseIds = [], $centreId = 0, int $fyStartYear = null) {
        if (!report_monitor_table_exists($conn, 'students')) {
            return [];
        }

        if ($fyStartYear === null) {
            $fyStartYear = report_monitor_get_financial_year_start();
        }

        $quarters = [
            'Q1' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q1'),
            'Q2' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q2'),
            'Q3' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q3'),
            'Q4' => report_monitor_get_financial_quarter_range($fyStartYear, 'Q4'),
        ];

        $fyStart = $quarters['Q1']['start_date'];
        $fyEnd = $quarters['Q4']['next_start'];

        $grouped = [];
        foreach (array_keys(report_monitor_get_social_category_groups()) as $key) {
            $grouped[$key] = [];
        }

        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $activeCondition = report_monitor_student_active_sql('s');
        $batchCondition = report_monitor_student_batch_enrolled_condition($conn, 's');

        $sql = "SELECT c.id AS course_id,
                       c.course_name,
                       c.course_code,
                       s.category AS raw_category,
                       s.pwd_status AS pwd_status,
                       CASE
                           WHEN s.created_at >= ? AND s.created_at < ? THEN 'Q1'
                           WHEN s.created_at >= ? AND s.created_at < ? THEN 'Q2'
                           WHEN s.created_at >= ? AND s.created_at < ? THEN 'Q3'
                           WHEN s.created_at >= ? AND s.created_at < ? THEN 'Q4'
                           ELSE ''
                       END AS quarter_key,
                       SUM(CASE WHEN {$batchCondition} THEN 1 ELSE 0 END) AS total
                FROM students s
                INNER JOIN courses c ON c.id = s.course_id
                WHERE {$activeCondition}
                  AND s.created_at >= ? AND s.created_at < ?
                  {$scopeFilter['sql']}
                GROUP BY c.id, c.course_name, c.course_code, raw_category, pwd_status, quarter_key";

        $types = str_repeat('s', 10) . $scopeFilter['types'];
        $values = array_merge([
            $quarters['Q1']['start_date'], $quarters['Q1']['next_start'],
            $quarters['Q2']['start_date'], $quarters['Q2']['next_start'],
            $quarters['Q3']['start_date'], $quarters['Q3']['next_start'],
            $quarters['Q4']['start_date'], $quarters['Q4']['next_start'],
            $fyStart, $fyEnd,
        ], $scopeFilter['values']);
        $result = report_monitor_bind_and_execute($conn, $sql, $types, $values);

        if (!$result) {
            return $grouped;
        }

        $addCourseCount = static function (array &$bucket, array $row, string $quarterKey, int $count): void {
            $courseId = (int) $row['course_id'];
            if (!isset($bucket[$courseId])) {
                $bucket[$courseId] = [
                    'course_id' => $courseId,
                    'course_name' => $row['course_name'],
                    'course_code' => $row['course_code'] ?? '',
                    'Q1' => 0,
                    'Q2' => 0,
                    'Q3' => 0,
                    'Q4' => 0,
                    'total' => 0,
                ];
            }

            if (in_array($quarterKey, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
                $bucket[$courseId][$quarterKey] += $count;
                $bucket[$courseId]['total'] += $count;
            }
        };

        while ($row = $result->fetch_assoc()) {
            $quarterKey = $row['quarter_key'] ?? '';
            if (!in_array($quarterKey, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
                continue;
            }

            $count = (int) ($row['total'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $socialKey = report_monitor_resolve_social_category_key($row['raw_category'] ?? '');
            if (isset($grouped[$socialKey])) {
                $addCourseCount($grouped[$socialKey], $row, $quarterKey, $count);
            }

            if (report_monitor_is_pwd_status($row['pwd_status'] ?? '')) {
                $addCourseCount($grouped['pwd'], $row, $quarterKey, $count);
            }
        }

        foreach ($grouped as $socialKey => $courses) {
            $courses = array_values($courses);
            usort($courses, static function ($a, $b) {
                $compare = $b['total'] <=> $a['total'];
                return $compare !== 0 ? $compare : strcasecmp($a['course_name'], $b['course_name']);
            });
            $grouped[$socialKey] = array_values(array_filter($courses, static function ($courseRow) {
                return (int) ($courseRow['total'] ?? 0) > 0;
            }));
        }

        $courseBatchMeta = report_monitor_get_course_batch_meta_map($conn, $courseIds, $centreId, $fyStartYear);
        foreach ($grouped as $socialKey => $courses) {
            foreach ($courses as $index => $courseRow) {
                $courseId = (int) ($courseRow['course_id'] ?? 0);
                $meta = $courseBatchMeta[$courseId] ?? [
                    'scheme_names' => '—',
                    'batch_period_label' => '—',
                ];
                $grouped[$socialKey][$index]['scheme_names'] = $meta['scheme_names'];
                $grouped[$socialKey][$index]['batch_period_label'] = $meta['batch_period_label'];
            }
        }

        return $grouped;
    }

    function report_monitor_get_targets_by_keys($conn, int $fyStartYear, int $centreId, array $allowedKeys) {
        report_monitor_ensure_category_targets_table($conn);

        $targets = [];
        foreach ($allowedKeys as $key) {
            $targets[$key] = 0;
        }

        if (empty($allowedKeys)) {
            return $targets;
        }

        $stmt = $conn->prepare(
            'SELECT category_key, annual_target
             FROM report_category_admission_targets
             WHERE financial_year_start = ? AND centre_id = ?'
        );
        if (!$stmt) {
            return $targets;
        }

        $stmt->bind_param('ii', $fyStartYear, $centreId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $key = (string) ($row['category_key'] ?? '');
            if ($key !== '' && array_key_exists($key, $targets)) {
                $targets[$key] = max(0, (int) ($row['annual_target'] ?? 0));
            }
        }
        $stmt->close();

        return $targets;
    }

    function report_monitor_save_targets_by_keys($conn, int $fyStartYear, int $centreId, array $targetsByCategory, array $allowedKeys, ?int $adminId = null) {
        report_monitor_ensure_category_targets_table($conn);

        $updatedBy = ($adminId !== null && $adminId > 0) ? (int) $adminId : 0;
        $sql = 'INSERT INTO report_category_admission_targets
                    (financial_year_start, centre_id, category_key, annual_target, updated_by)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    annual_target = VALUES(annual_target),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP';

        foreach ($allowedKeys as $categoryKey) {
            $target = max(0, (int) ($targetsByCategory[$categoryKey] ?? 0));
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return ['success' => false, 'message' => 'Could not prepare target save query.'];
            }

            $stmt->bind_param('iisii', $fyStartYear, $centreId, $categoryKey, $target, $updatedBy);
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                return ['success' => false, 'message' => 'Failed to save category targets: ' . $error];
            }
            $stmt->close();
        }

        return ['success' => true, 'message' => 'Category admission targets saved successfully.'];
    }

    function report_monitor_get_category_targets($conn, int $fyStartYear, int $centreId = 0) {
        return report_monitor_get_targets_by_keys(
            $conn,
            $fyStartYear,
            $centreId,
            report_monitor_get_category_target_keys()
        );
    }

    function report_monitor_get_social_category_targets($conn, int $fyStartYear, int $centreId = 0) {
        return report_monitor_get_targets_by_keys(
            $conn,
            $fyStartYear,
            $centreId,
            report_monitor_get_social_category_target_keys()
        );
    }

    function report_monitor_save_category_targets($conn, int $fyStartYear, int $centreId, array $targetsByCategory, ?int $adminId = null, ?array $allowedKeys = null) {
        return report_monitor_save_targets_by_keys(
            $conn,
            $fyStartYear,
            $centreId,
            $targetsByCategory,
            $allowedKeys ?? report_monitor_get_category_target_keys(),
            $adminId
        );
    }

    function report_monitor_apply_category_targets(array $summaryRows, array $targetsMap) {
        $grandTotal = 0;
        $grandTarget = 0;

        foreach ($summaryRows as &$row) {
            $key = (string) ($row['key'] ?? '');
            $target = max(0, (int) ($targetsMap[$key] ?? 0));
            $total = (int) ($row['total'] ?? 0);

            $row['target'] = $target;
            $row['achievement_pct'] = $target > 0 ? round(($total / $target) * 100, 1) : null;
            $row['remaining'] = $target > 0 ? max(0, $target - $total) : null;

            $grandTotal += $total;
            $grandTarget += $target;
        }
        unset($row);

        return [
            'rows' => $summaryRows,
            'grand_total' => $grandTotal,
            'grand_target' => $grandTarget,
            'grand_achievement_pct' => $grandTarget > 0 ? round(($grandTotal / $grandTarget) * 100, 1) : null,
        ];
    }
}
