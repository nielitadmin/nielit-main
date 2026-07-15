<?php
/**
 * Training Partner quarterly admissions — manual entry data layer.
 */

require_once __DIR__ . '/report_monitor_helper.php';

if (!function_exists('tp_admissions_ensure_table')) {

    function tp_admissions_ensure_table($conn) {
        if (report_monitor_table_exists($conn, 'training_partner_quarterly_admissions')) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS training_partner_quarterly_admissions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            partner_name VARCHAR(200) NOT NULL,
            course_name VARCHAR(255) NOT NULL,
            category_key VARCHAR(64) NOT NULL,
            financial_year_start INT NOT NULL,
            q1_students INT UNSIGNED NOT NULL DEFAULT 0,
            q2_students INT UNSIGNED NOT NULL DEFAULT 0,
            q3_students INT UNSIGNED NOT NULL DEFAULT 0,
            q4_students INT UNSIGNED NOT NULL DEFAULT 0,
            remarks TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT NULL DEFAULT NULL,
            updated_by INT NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tp_qa_fy (financial_year_start),
            KEY idx_tp_qa_category (category_key),
            KEY idx_tp_qa_partner (partner_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return (bool) $conn->query($sql);
    }

    function tp_admissions_get_category_options() {
        $options = [];
        foreach (get_report_monitor_category_groups() as $key => $group) {
            $options[$key] = $group['label'];
        }
        $options['uncategorized'] = 'Uncategorized';
        return $options;
    }

    function tp_admissions_detect_quarter_counts($q1, $q2, $q3, $q4) {
        foreach (['Q1' => $q1, 'Q2' => $q2, 'Q3' => $q3, 'Q4' => $q4] as $quarter => $count) {
            if ((int) $count > 0) {
                return ['quarter' => $quarter, 'students_trained' => (int) $count];
            }
        }

        return ['quarter' => '', 'students_trained' => 0];
    }

    function tp_admissions_validate_entry(array $data) {
        $errors = [];
        $partnerName = trim((string) ($data['partner_name'] ?? ''));
        $courseName = trim((string) ($data['course_name'] ?? ''));
        $categoryKey = trim((string) ($data['category_key'] ?? ''));
        $fyStartYear = (int) ($data['financial_year_start'] ?? 0);
        $quarter = strtoupper(trim((string) ($data['quarter'] ?? '')));
        $studentsTrained = max(0, (int) ($data['students_trained'] ?? 0));

        if ($partnerName === '') {
            $errors[] = 'Training partner name is required.';
        }
        if ($courseName === '') {
            $errors[] = 'Course name is required.';
        }
        if ($fyStartYear < 2020 || $fyStartYear > 2100) {
            $errors[] = 'Invalid financial year.';
        }

        $allowedCategories = array_keys(tp_admissions_get_category_options());
        if (!in_array($categoryKey, $allowedCategories, true)) {
            $errors[] = 'Please select a valid category.';
        }

        if (!in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
            $errors[] = 'Please select a quarter (Q1, Q2, Q3, or Q4).';
        }

        if ($studentsTrained <= 0) {
            $errors[] = 'Enter the number of students trained.';
        }

        $q1 = $q2 = $q3 = $q4 = 0;
        if ($quarter === 'Q1') {
            $q1 = $studentsTrained;
        } elseif ($quarter === 'Q2') {
            $q2 = $studentsTrained;
        } elseif ($quarter === 'Q3') {
            $q3 = $studentsTrained;
        } elseif ($quarter === 'Q4') {
            $q4 = $studentsTrained;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'normalized' => [
                'partner_name' => $partnerName,
                'course_name' => $courseName,
                'category_key' => $categoryKey,
                'financial_year_start' => $fyStartYear,
                'quarter' => $quarter,
                'students_trained' => $studentsTrained,
                'q1_students' => $q1,
                'q2_students' => $q2,
                'q3_students' => $q3,
                'q4_students' => $q4,
                'remarks' => '',
            ],
        ];
    }

    function tp_admissions_normalize_row(array $row) {
        $q1 = (int) ($row['q1_students'] ?? 0);
        $q2 = (int) ($row['q2_students'] ?? 0);
        $q3 = (int) ($row['q3_students'] ?? 0);
        $q4 = (int) ($row['q4_students'] ?? 0);
        $detected = tp_admissions_detect_quarter_counts($q1, $q2, $q3, $q4);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'partner_name' => (string) ($row['partner_name'] ?? ''),
            'course_name' => (string) ($row['course_name'] ?? ''),
            'category_key' => (string) ($row['category_key'] ?? ''),
            'category_label' => report_monitor_category_label($row['category_key'] ?? ''),
            'financial_year_start' => (int) ($row['financial_year_start'] ?? 0),
            'quarter' => $detected['quarter'],
            'students_trained' => $detected['students_trained'],
            'Q1' => $q1,
            'Q2' => $q2,
            'Q3' => $q3,
            'Q4' => $q4,
            'total' => $q1 + $q2 + $q3 + $q4,
            'remarks' => (string) ($row['remarks'] ?? ''),
            'is_active' => (int) ($row['is_active'] ?? 1),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    function tp_admissions_list($conn, int $fyStartYear, bool $activeOnly = true) {
        tp_admissions_ensure_table($conn);

        $sql = 'SELECT * FROM training_partner_quarterly_admissions WHERE financial_year_start = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY partner_name ASC, course_name ASC, id ASC';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $fyStartYear);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = tp_admissions_normalize_row($row);
        }
        $stmt->close();

        return $rows;
    }

    function tp_admissions_get_by_id($conn, int $id) {
        tp_admissions_ensure_table($conn);

        $stmt = $conn->prepare('SELECT * FROM training_partner_quarterly_admissions WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ? tp_admissions_normalize_row($row) : null;
    }

    function tp_admissions_save($conn, array $data, ?int $adminId = null, ?int $id = null) {
        tp_admissions_ensure_table($conn);

        $validation = tp_admissions_validate_entry($data);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => implode(' ', $validation['errors'])];
        }

        $entry = $validation['normalized'];
        $updatedBy = ($adminId !== null && $adminId > 0) ? (int) $adminId : 0;
        $remarks = $entry['remarks'];

        if ($id !== null && $id > 0) {
            $stmt = $conn->prepare(
                'UPDATE training_partner_quarterly_admissions
                 SET partner_name = ?, course_name = ?, category_key = ?, financial_year_start = ?,
                     q1_students = ?, q2_students = ?, q3_students = ?, q4_students = ?,
                     remarks = ?, updated_by = ?
                 WHERE id = ? AND is_active = 1'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Could not prepare update query.'];
            }
            $stmt->bind_param(
                'sssiiiiisii',
                $entry['partner_name'],
                $entry['course_name'],
                $entry['category_key'],
                $entry['financial_year_start'],
                $entry['q1_students'],
                $entry['q2_students'],
                $entry['q3_students'],
                $entry['q4_students'],
                $remarks,
                $updatedBy,
                $id
            );
        } else {
            $createdBy = $updatedBy;
            $stmt = $conn->prepare(
                'INSERT INTO training_partner_quarterly_admissions
                    (partner_name, course_name, category_key, financial_year_start,
                     q1_students, q2_students, q3_students, q4_students, remarks, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Could not prepare insert query.'];
            }
            $stmt->bind_param(
                'sssiiiiisii',
                $entry['partner_name'],
                $entry['course_name'],
                $entry['category_key'],
                $entry['financial_year_start'],
                $entry['q1_students'],
                $entry['q2_students'],
                $entry['q3_students'],
                $entry['q4_students'],
                $remarks,
                $createdBy,
                $updatedBy
            );
        }

        $ok = $stmt->execute();
        $error = $stmt->error;
        $newId = $id ?? (int) $conn->insert_id;
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to save entry: ' . $error];
        }

        return [
            'success' => true,
            'message' => $id ? 'Training partner entry updated.' : 'Training partner entry added.',
            'id' => $newId,
        ];
    }

    function tp_admissions_delete($conn, int $id, ?int $adminId = null) {
        tp_admissions_ensure_table($conn);

        $updatedBy = ($adminId !== null && $adminId > 0) ? (int) $adminId : 0;
        $stmt = $conn->prepare(
            'UPDATE training_partner_quarterly_admissions
             SET is_active = 0, updated_by = ?
             WHERE id = ? AND is_active = 1'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not prepare delete query.'];
        }

        $stmt->bind_param('ii', $updatedBy, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to remove entry.'];
        }

        return ['success' => true, 'message' => 'Training partner entry removed.'];
    }

    function tp_admissions_get_category_totals($conn, int $fyStartYear) {
        tp_admissions_ensure_table($conn);

        $totals = [];
        foreach (array_keys(tp_admissions_get_category_options()) as $key) {
            $totals[$key] = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0, 'total' => 0];
        }

        $stmt = $conn->prepare(
            'SELECT category_key,
                    SUM(q1_students) AS q1_total,
                    SUM(q2_students) AS q2_total,
                    SUM(q3_students) AS q3_total,
                    SUM(q4_students) AS q4_total
             FROM training_partner_quarterly_admissions
             WHERE financial_year_start = ? AND is_active = 1
             GROUP BY category_key'
        );
        if (!$stmt) {
            return $totals;
        }

        $stmt->bind_param('i', $fyStartYear);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $key = (string) ($row['category_key'] ?? '');
            if (!isset($totals[$key])) {
                $totals[$key] = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0, 'total' => 0];
            }
            $totals[$key]['Q1'] = (int) ($row['q1_total'] ?? 0);
            $totals[$key]['Q2'] = (int) ($row['q2_total'] ?? 0);
            $totals[$key]['Q3'] = (int) ($row['q3_total'] ?? 0);
            $totals[$key]['Q4'] = (int) ($row['q4_total'] ?? 0);
            $totals[$key]['total'] = $totals[$key]['Q1'] + $totals[$key]['Q2'] + $totals[$key]['Q3'] + $totals[$key]['Q4'];
        }
        $stmt->close();

        return $totals;
    }

    function tp_admissions_get_partner_summary_row($conn, int $fyStartYear) {
        $entries = tp_admissions_list($conn, $fyStartYear, true);
        $row = [
            'key' => 'training_partner_programs',
            'label' => 'Training Partner Programs (Manual Entry)',
            'Q1' => 0,
            'Q2' => 0,
            'Q3' => 0,
            'Q4' => 0,
            'total' => 0,
            'is_training_partner_row' => true,
        ];

        foreach ($entries as $entry) {
            $row['Q1'] += (int) $entry['Q1'];
            $row['Q2'] += (int) $entry['Q2'];
            $row['Q3'] += (int) $entry['Q3'];
            $row['Q4'] += (int) $entry['Q4'];
            $row['total'] += (int) $entry['total'];
        }

        return $row;
    }

    function report_monitor_merge_training_partner_admissions($conn, array $summaryRows, int $fyStartYear) {
        $tpTotals = tp_admissions_get_category_totals($conn, $fyStartYear);

        foreach ($summaryRows as &$row) {
            $key = (string) ($row['key'] ?? '');
            if (!isset($tpTotals[$key])) {
                continue;
            }
            $tp = $tpTotals[$key];
            if (($tp['total'] ?? 0) <= 0) {
                continue;
            }

            $row['Q1'] = (int) ($row['Q1'] ?? 0) + (int) $tp['Q1'];
            $row['Q2'] = (int) ($row['Q2'] ?? 0) + (int) $tp['Q2'];
            $row['Q3'] = (int) ($row['Q3'] ?? 0) + (int) $tp['Q3'];
            $row['Q4'] = (int) ($row['Q4'] ?? 0) + (int) $tp['Q4'];
            $row['total'] = (int) ($row['total'] ?? 0) + (int) $tp['total'];
            $row['tp_Q1'] = (int) $tp['Q1'];
            $row['tp_Q2'] = (int) $tp['Q2'];
            $row['tp_Q3'] = (int) $tp['Q3'];
            $row['tp_Q4'] = (int) $tp['Q4'];
            $row['tp_total'] = (int) $tp['total'];
            $row['includes_training_partner'] = true;
        }
        unset($row);

        $partnerRow = tp_admissions_get_partner_summary_row($conn, $fyStartYear);
        if (($partnerRow['total'] ?? 0) > 0) {
            $summaryRows[] = $partnerRow;
        }

        return $summaryRows;
    }
}
