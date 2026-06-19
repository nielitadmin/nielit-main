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

    function report_monitor_get_overall_stats($conn, array $courseIds = [], $centreId = 0) {
        $stats = [
            'total_courses' => 0,
            'active_courses' => 0,
            'total_batches' => 0,
            'active_batches' => 0,
            'total_applications' => 0,
            'pending_applications' => 0,
            'approved_students' => 0,
            'batch_enrolled_students' => 0,
            'unassigned_students' => 0,
        ];

        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $courseSql = "SELECT COUNT(*) AS total,
                             SUM(CASE WHEN LOWER(COALESCE(c.status, 'active')) = 'active' THEN 1 ELSE 0 END) AS active
                      FROM courses c WHERE 1=1{$scopeFilter['sql']}";
        $result = report_monitor_bind_and_execute($conn, $courseSql, $scopeFilter['types'], $scopeFilter['values']);
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_courses'] = (int) ($row['total'] ?? 0);
            $stats['active_courses'] = (int) ($row['active'] ?? 0);
        }

        if (report_monitor_table_exists($conn, 'batches')) {
            $batchScope = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
            $batchSql = "SELECT COUNT(*) AS total,
                                SUM(CASE WHEN LOWER(COALESCE(b.status, 'active')) IN ('active', 'ongoing', 'open') THEN 1 ELSE 0 END) AS active
                         FROM batches b
                         INNER JOIN courses c ON c.id = b.course_id
                         WHERE 1=1{$batchScope['sql']}";
            $batchResult = report_monitor_bind_and_execute($conn, $batchSql, $batchScope['types'], $batchScope['values']);
            if ($batchResult && $row = $batchResult->fetch_assoc()) {
                $stats['total_batches'] = (int) ($row['total'] ?? 0);
                $stats['active_batches'] = (int) ($row['active'] ?? 0);
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

    function report_monitor_get_centre_stats($conn, array $courseIds = [], $centreId = 0) {
        $rows = [];
        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $batchCondition = report_monitor_student_batch_enrolled_condition($conn, 's');
        $activeCondition = report_monitor_student_active_sql('s');

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

    function report_monitor_get_category_stats($conn, array $courseIds = [], $centreId = 0) {
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

        $courseSql = "SELECT {$categoryExpr} AS raw_category, COUNT(*) AS total
                      FROM courses c WHERE 1=1{$scopeFilter['sql']}
                      GROUP BY raw_category";
        $courseResult = report_monitor_bind_and_execute($conn, $courseSql, $scopeFilter['types'], $scopeFilter['values']);
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

        $batchCondition = report_monitor_student_batch_enrolled_condition($conn, 's');
        $activeCondition = report_monitor_student_active_sql('s');
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
                $groups[$key]['batch_enrolled'] += (int) $row['batch_enrolled'];
            }
        }

        $rows = array_values($groups);
        usort($rows, static function ($a, $b) {
            return $b['applications'] <=> $a['applications'];
        });
        return $rows;
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
            $enrollSql = "SELECT DATE_FORMAT(COALESCE(bs.enrollment_date, b.created_at), '%Y-%m') AS month_key,
                                 COUNT(DISTINCT bs.id) AS total
                          FROM batch_students bs
                          INNER JOIN batches b ON b.id = bs.batch_id
                          INNER JOIN courses c ON c.id = b.course_id
                          WHERE COALESCE(bs.enrollment_date, b.created_at) >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL ? MONTH), '%Y-%m-01')
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

    function report_monitor_get_batch_details($conn, array $courseIds = [], $centreId = 0) {
        if (!report_monitor_table_exists($conn, 'batches')) {
            return [];
        }

        $rows = [];
        $categoryExpr = report_monitor_course_category_sql('c');
        $scopeFilter = report_monitor_build_scope_filter($conn, $courseIds, $centreId, 'c');
        $types = $scopeFilter['types'];
        $values = $scopeFilter['values'];

        $hasBatchFaculty = report_monitor_table_exists($conn, 'batch_faculty') && report_monitor_table_exists($conn, 'faculty');
        $facultySelect = $hasBatchFaculty
            ? "(SELECT GROUP_CONCAT(DISTINCT f.name ORDER BY f.name SEPARATOR ', ')
               FROM batch_faculty bf
               INNER JOIN faculty f ON f.id = bf.faculty_id
               WHERE bf.batch_id = b.id) AS faculty_names"
            : "'' AS faculty_names";

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
                    {$facultySelect}
                FROM batches b
                INNER JOIN courses c ON c.id = b.course_id
                LEFT JOIN centres cen ON cen.id = c.centre_id
                WHERE 1=1{$scopeFilter['sql']}
                ORDER BY b.created_at DESC, b.id DESC
                LIMIT 100";

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
            ];
        }

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
}
