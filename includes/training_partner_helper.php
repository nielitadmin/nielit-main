<?php

if (!function_exists('training_partner_ensure_schema')) {
    function training_partner_ensure_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $conn->query("CREATE TABLE IF NOT EXISTS training_partners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_name VARCHAR(180) NOT NULL,
            partner_code VARCHAR(40) NOT NULL DEFAULT '',
            centre_id INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_tp_centre (centre_id),
            KEY idx_tp_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->query("CREATE TABLE IF NOT EXISTS training_partner_students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_id INT NOT NULL,
            scheme_id INT NOT NULL,
            student_name VARCHAR(180) NOT NULL,
            mobile VARCHAR(20) NOT NULL DEFAULT '',
            figure_month DATE NOT NULL,
            is_certified TINYINT(1) NOT NULL DEFAULT 0,
            is_placed TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            course_name VARCHAR(180) NOT NULL DEFAULT '',
            course_start_date DATE NULL,
            course_end_date DATE NULL,
            KEY idx_tp_student_period (figure_month),
            KEY idx_tp_student_partner (partner_id, scheme_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $studentColumns = [];
        $columnResult = $conn->query('SHOW COLUMNS FROM training_partner_students');
        if ($columnResult) {
            while ($column = $columnResult->fetch_assoc()) {
                $studentColumns[$column['Field']] = true;
            }
        }
        if (!isset($studentColumns['course_name'])) {
            $conn->query("ALTER TABLE training_partner_students ADD COLUMN course_name VARCHAR(180) NOT NULL DEFAULT '' AFTER scheme_id");
        }
        if (!isset($studentColumns['course_start_date'])) {
            $conn->query('ALTER TABLE training_partner_students ADD COLUMN course_start_date DATE NULL AFTER figure_month');
        }
        if (!isset($studentColumns['course_end_date'])) {
            $conn->query('ALTER TABLE training_partner_students ADD COLUMN course_end_date DATE NULL AFTER course_start_date');
        }
        $conn->query("CREATE TABLE IF NOT EXISTS training_partner_figures (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_id INT NOT NULL,
            figure_month DATE NOT NULL,
            enrolled INT NOT NULL DEFAULT 0,
            certified INT NOT NULL DEFAULT 0,
            placed INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tp_month (partner_id, figure_month),
            KEY idx_tp_month (figure_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    function training_partner_centres(mysqli $conn): array
    {
        $rows = [];
        $result = $conn->query("SELECT id, name FROM centres WHERE is_active = 1 ORDER BY name ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                ];
            }
        }
        return $rows;
    }

    function training_partner_list(mysqli $conn, bool $activeOnly = true): array
    {
        training_partner_ensure_schema($conn);
        $sql = "SELECT p.id, p.partner_name, p.partner_code, p.centre_id, p.is_active,
                       COALESCE(c.name, '') AS centre_name
                FROM training_partners p
                LEFT JOIN centres c ON c.id = p.centre_id
                " . ($activeOnly ? 'WHERE p.is_active = 1 ' : '') . "
                ORDER BY p.partner_name ASC";
        $result = $conn->query($sql);
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    function training_partner_save(mysqli $conn, int $id, string $name, string $code, int $centreId): array
    {
        training_partner_ensure_schema($conn);
        $name = trim($name);
        $code = strtoupper(trim($code));
        if ($name === '') {
            return ['ok' => false, 'error' => 'Enter the training partner name.'];
        }
        if ($id > 0) {
            $stmt = $conn->prepare('UPDATE training_partners SET partner_name = ?, partner_code = ?, centre_id = ?, is_active = 1 WHERE id = ?');
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not save the training partner.'];
            }
            $stmt->bind_param('ssii', $name, $code, $centreId, $id);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Could not save the training partner.'];
        }
        $stmt = $conn->prepare('INSERT INTO training_partners (partner_name, partner_code, centre_id) VALUES (?, ?, ?)');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save the training partner.'];
        }
        $stmt->bind_param('ssi', $name, $code, $centreId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Could not save the training partner.'];
    }

    function training_partner_deactivate(mysqli $conn, int $id): bool
    {
        training_partner_ensure_schema($conn);
        $stmt = $conn->prepare('UPDATE training_partners SET is_active = 0 WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function training_partner_quarter_options(): array
    {
        return [
            'Q1' => 'Q1 (Apr–Jun)',
            'Q2' => 'Q2 (Jul–Sep)',
            'Q3' => 'Q3 (Oct–Dec)',
            'Q4' => 'Q4 (Jan–Mar)',
        ];
    }

    function training_partner_current_quarter(): array
    {
        $month = (int) date('n');
        $year = (int) date('Y');
        if ($month >= 4 && $month <= 6) {
            return ['fy' => $year, 'quarter' => 'Q1'];
        }
        if ($month >= 7 && $month <= 9) {
            return ['fy' => $year, 'quarter' => 'Q2'];
        }
        if ($month >= 10) {
            return ['fy' => $year, 'quarter' => 'Q3'];
        }
        return ['fy' => $year - 1, 'quarter' => 'Q4'];
    }

    function training_partner_quarter_start(int $fyYear, string $quarter): string
    {
        $map = [
            'Q1' => sprintf('%04d-04-01', $fyYear),
            'Q2' => sprintf('%04d-07-01', $fyYear),
            'Q3' => sprintf('%04d-10-01', $fyYear),
            'Q4' => sprintf('%04d-01-01', $fyYear + 1),
        ];
        return $map[$quarter] ?? '';
    }

    function training_partner_quarter_from_date(string $date): array
    {
        $time = strtotime($date);
        if ($time === false) {
            return ['fy' => 0, 'quarter' => '', 'label' => ''];
        }
        $month = (int) date('n', $time);
        $year = (int) date('Y', $time);
        if ($month >= 4 && $month <= 6) {
            $fy = $year;
            $quarter = 'Q1';
        } elseif ($month >= 7 && $month <= 9) {
            $fy = $year;
            $quarter = 'Q2';
        } elseif ($month >= 10) {
            $fy = $year;
            $quarter = 'Q3';
        } else {
            $fy = $year - 1;
            $quarter = 'Q4';
        }
        $endShort = substr((string) ($fy + 1), -2);
        return [
            'fy' => $fy,
            'quarter' => $quarter,
            'label' => $quarter . ' FY ' . $fy . '-' . $endShort,
        ];
    }

    function training_partner_save_figure(mysqli $conn, int $partnerId, int $fyYear, string $quarter, int $enrolled, int $certified, int $placed, int $replaceId = 0): array
    {
        training_partner_ensure_schema($conn);
        $quarter = strtoupper(trim($quarter));
        $figureMonth = training_partner_quarter_start($fyYear, $quarter);
        if ($partnerId <= 0 || $figureMonth === '' || $fyYear < 2000) {
            return ['ok' => false, 'error' => 'Choose a training partner, financial year, and quarter.'];
        }
        $enrolled = max(0, $enrolled);
        $certified = max(0, $certified);
        $placed = max(0, $placed);
        $stmt = $conn->prepare('INSERT INTO training_partner_figures (partner_id, figure_month, enrolled, certified, placed)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE enrolled = VALUES(enrolled), certified = VALUES(certified), placed = VALUES(placed)');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save the figures.'];
        }
        $stmt->bind_param('isiii', $partnerId, $figureMonth, $enrolled, $certified, $placed);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok && $replaceId > 0) {
            $old = $conn->prepare('SELECT figure_month FROM training_partner_figures WHERE id = ? LIMIT 1');
            if ($old) {
                $old->bind_param('i', $replaceId);
                $old->execute();
                $oldRow = $old->get_result()->fetch_assoc();
                $old->close();
                if ($oldRow && (string) $oldRow['figure_month'] !== $figureMonth) {
                    training_partner_delete_figure($conn, $replaceId);
                }
            }
        }
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Could not save the figures.'];
    }

    function training_partner_delete_figure(mysqli $conn, int $id): bool
    {
        training_partner_ensure_schema($conn);
        $stmt = $conn->prepare('DELETE FROM training_partner_figures WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function training_partner_figures_list(mysqli $conn): array
    {
        training_partner_ensure_schema($conn);
        $sql = "SELECT f.id, f.partner_id, f.figure_month, f.enrolled, f.certified, f.placed,
                       p.partner_name, p.partner_code
                FROM training_partner_figures f
                INNER JOIN training_partners p ON p.id = f.partner_id
                WHERE p.is_active = 1
                ORDER BY f.figure_month DESC, p.partner_name ASC";
        $result = $conn->query($sql);
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    function training_partner_report_rows(mysqli $conn, array $monthFilter, int $centreId = 0): array
    {
        training_partner_ensure_schema($conn);
        $start = substr((string) ($monthFilter['start'] ?? ''), 0, 10);
        $next = substr((string) ($monthFilter['next_start'] ?? ''), 0, 10);
        if ($start === '' || $next === '') {
            return [];
        }
        $sql = "SELECT p.id, p.partner_name, p.partner_code,
                       COALESCE(SUM(f.enrolled), 0) AS enrolled,
                       COALESCE(SUM(f.certified), 0) AS certified,
                       COALESCE(SUM(f.placed), 0) AS placed
                FROM training_partners p
                INNER JOIN training_partner_figures f ON f.partner_id = p.id
                WHERE p.is_active = 1
                  AND f.figure_month >= ? AND f.figure_month < ?";
        $types = 'ss';
        $values = [$start, $next];
        if ($centreId > 0) {
            $sql .= ' AND p.centre_id = ?';
            $types .= 'i';
            $values[] = $centreId;
        }
        $sql .= ' GROUP BY p.id, p.partner_name, p.partner_code
                  ORDER BY p.partner_name ASC';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'id' => (int) $row['id'],
                    'scheme_name' => '',
                    'partner_name' => (string) $row['partner_name'],
                    'partner_code' => (string) $row['partner_code'],
                    'enrolled' => (int) $row['enrolled'],
                    'certified' => (int) $row['certified'],
                    'placed' => (int) $row['placed'],
                ];
            }
        }
        $stmt->close();

        $studentSql = "SELECT p.id, p.partner_name, p.partner_code,
                              COALESCE(sc.scheme_name, '') AS scheme_name,
                              SUM(st.is_certified) AS certified,
                              SUM(st.is_placed) AS placed
                       FROM training_partner_students st
                       INNER JOIN training_partners p ON p.id = st.partner_id
                       LEFT JOIN schemes sc ON sc.id = st.scheme_id
                       WHERE p.is_active = 1
                         AND st.figure_month >= ? AND st.figure_month < ?";
        $studentTypes = 'ss';
        $studentValues = [$start, $next];
        if ($centreId > 0) {
            $studentSql .= ' AND p.centre_id = ?';
            $studentTypes .= 'i';
            $studentValues[] = $centreId;
        }
        $studentSql .= ' GROUP BY p.id, p.partner_name, p.partner_code, sc.scheme_name
                         ORDER BY sc.scheme_name ASC, p.partner_name ASC';
        $studentStmt = $conn->prepare($studentSql);
        if ($studentStmt) {
            $studentStmt->bind_param($studentTypes, ...$studentValues);
            $studentStmt->execute();
            $studentResult = $studentStmt->get_result();
            if ($studentResult) {
                while ($row = $studentResult->fetch_assoc()) {
                    $rows[] = [
                        'id' => (int) $row['id'],
                        'scheme_name' => (string) $row['scheme_name'],
                        'partner_name' => (string) $row['partner_name'],
                        'partner_code' => (string) $row['partner_code'],
                        'enrolled' => 0,
                        'certified' => (int) $row['certified'],
                        'placed' => (int) $row['placed'],
                    ];
                }
            }
            $studentStmt->close();
        }
        return $rows;
    }

    function training_partner_schemes(mysqli $conn): array
    {
        $rows = [];
        $result = $conn->query("SELECT id, scheme_name, scheme_code FROM schemes WHERE LOWER(status) = 'active' ORDER BY scheme_name ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    function training_partner_find_or_create(mysqli $conn, string $name): int
    {
        training_partner_ensure_schema($conn);
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if ($name === '') {
            return 0;
        }
        $stmt = $conn->prepare('SELECT id FROM training_partners WHERE LOWER(partner_name) = LOWER(?) ORDER BY is_active DESC, id ASC LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $id = (int) $row['id'];
            $conn->query('UPDATE training_partners SET is_active = 1 WHERE id = ' . $id);
            return $id;
        }
        $insert = $conn->prepare('INSERT INTO training_partners (partner_name, partner_code, centre_id) VALUES (?, ?, 0)');
        if (!$insert) {
            return 0;
        }
        $code = '';
        $insert->bind_param('ss', $name, $code);
        $ok = $insert->execute();
        $id = $ok ? (int) $insert->insert_id : 0;
        $insert->close();
        return $id;
    }

    function training_partner_save_student(mysqli $conn, int $partnerId, int $schemeId, string $name, string $mobile, int $fyYear, string $quarter, int $certified, int $placed, string $courseName = '', ?string $courseStart = null, ?string $courseEnd = null): array
    {
        training_partner_ensure_schema($conn);
        $name = trim($name);
        $mobile = trim($mobile);
        $courseName = trim($courseName);
        $figureMonth = training_partner_quarter_start($fyYear, strtoupper(trim($quarter)));
        if ($schemeId <= 0) {
            return ['ok' => false, 'error' => 'Choose the scheme or project first.'];
        }
        if ($partnerId <= 0 || $figureMonth === '' || $name === '') {
            return ['ok' => false, 'error' => 'Choose the training partner, quarter, and student name.'];
        }
        $certified = $certified === 1 ? 1 : 0;
        $placed = $placed === 1 ? 1 : 0;
        $courseStart = $courseStart !== null && $courseStart !== '' ? $courseStart : null;
        $courseEnd = $courseEnd !== null && $courseEnd !== '' ? $courseEnd : null;
        $existing = $conn->prepare('SELECT id FROM training_partner_students WHERE partner_id = ? AND scheme_id = ? AND student_name = ? AND mobile = ? AND figure_month = ? LIMIT 1');
        if ($existing) {
            $existing->bind_param('iisss', $partnerId, $schemeId, $name, $mobile, $figureMonth);
            $existing->execute();
            $found = $existing->get_result()->fetch_assoc();
            $existing->close();
            if ($found) {
                $update = $conn->prepare('UPDATE training_partner_students SET course_name = ?, course_start_date = ?, course_end_date = ?, is_certified = ?, is_placed = ? WHERE id = ?');
                if (!$update) {
                    return ['ok' => false, 'error' => 'Could not save the student record.'];
                }
                $id = (int) $found['id'];
                $update->bind_param('sssiii', $courseName, $courseStart, $courseEnd, $certified, $placed, $id);
                $ok = $update->execute();
                $update->close();
                return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Could not save the student record.'];
            }
        }
        $stmt = $conn->prepare('INSERT INTO training_partner_students (partner_id, scheme_id, course_name, student_name, mobile, figure_month, course_start_date, course_end_date, is_certified, is_placed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save the student record.'];
        }
        $stmt->bind_param('iissssssii', $partnerId, $schemeId, $courseName, $name, $mobile, $figureMonth, $courseStart, $courseEnd, $certified, $placed);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Could not save the student record.'];
    }

    function training_partner_delete_student(mysqli $conn, int $id): bool
    {
        training_partner_ensure_schema($conn);
        $stmt = $conn->prepare('DELETE FROM training_partner_students WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function training_partner_students_list(mysqli $conn): array
    {
        training_partner_ensure_schema($conn);
        $sql = "SELECT st.id, st.partner_id, st.scheme_id, st.course_name, st.student_name, st.mobile, st.figure_month,
                       st.course_start_date, st.course_end_date, st.is_certified, st.is_placed,
                       p.partner_name, COALESCE(sc.scheme_name, '') AS scheme_name, COALESCE(sc.scheme_code, '') AS scheme_code
                FROM training_partner_students st
                INNER JOIN training_partners p ON p.id = st.partner_id
                LEFT JOIN schemes sc ON sc.id = st.scheme_id
                WHERE p.is_active = 1
                ORDER BY st.id DESC
                LIMIT 200";
        $result = $conn->query($sql);
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    function training_partner_trend_points(mysqli $conn, array $graphMonths, int $centreId = 0): array
    {
        $emptyCertified = [];
        $emptyPlaced = [];
        $monthKeys = [];
        foreach ($graphMonths as $graphMonth) {
            $monthKeys[] = sprintf('%04d-%02d', (int) ($graphMonth['year'] ?? 0), (int) ($graphMonth['month'] ?? 0));
            $emptyCertified[] = null;
            $emptyPlaced[] = null;
        }
        if ($monthKeys === []) {
            return ['certified' => [], 'placed' => []];
        }

        $start = $monthKeys[0] . '-01';
        $last = $monthKeys[count($monthKeys) - 1] . '-01';
        $next = date('Y-m-d', strtotime($last . ' +1 month'));
        $certified = $emptyCertified;
        $placed = $emptyPlaced;
        $quarterTotals = [];
        $figureStmt = $conn->prepare('SELECT f.figure_month, SUM(f.certified) AS certified, SUM(f.placed) AS placed
            FROM training_partner_figures f
            INNER JOIN training_partners p ON p.id = f.partner_id
            WHERE p.is_active = 1 AND f.figure_month >= ? AND f.figure_month < ?'
            . ($centreId > 0 ? ' AND p.centre_id = ?' : '')
            . ' GROUP BY f.figure_month');
        if (!$figureStmt) {
            return ['certified' => $certified, 'placed' => $placed];
        }
        if ($centreId > 0) {
            $figureStmt->bind_param('ssi', $start, $next, $centreId);
        } else {
            $figureStmt->bind_param('ss', $start, $next);
        }
        $figureStmt->execute();
        $figureResult = $figureStmt->get_result();
        if ($figureResult) {
            while ($row = $figureResult->fetch_assoc()) {
                $info = training_partner_quarter_from_date((string) $row['figure_month']);
                $key = (int) $info['fy'] . '-' . (string) $info['quarter'];
                $quarterTotals[$key] = [
                    'certified' => (int) $row['certified'],
                    'placed' => (int) $row['placed'],
                ];
            }
        }
        $figureStmt->close();

        $studentStmt = $conn->prepare('SELECT st.figure_month, SUM(st.is_certified) AS certified, SUM(st.is_placed) AS placed
            FROM training_partner_students st
            INNER JOIN training_partners p ON p.id = st.partner_id
            WHERE p.is_active = 1 AND st.figure_month >= ? AND st.figure_month < ?'
            . ($centreId > 0 ? ' AND p.centre_id = ?' : '')
            . ' GROUP BY st.figure_month');
        if ($studentStmt) {
            if ($centreId > 0) {
                $studentStmt->bind_param('ssi', $start, $next, $centreId);
            } else {
                $studentStmt->bind_param('ss', $start, $next);
            }
            $studentStmt->execute();
            $studentResult = $studentStmt->get_result();
            if ($studentResult) {
                while ($row = $studentResult->fetch_assoc()) {
                    $info = training_partner_quarter_from_date((string) $row['figure_month']);
                    $key = (int) $info['fy'] . '-' . (string) $info['quarter'];
                    if (!isset($quarterTotals[$key])) {
                        $quarterTotals[$key] = ['certified' => 0, 'placed' => 0];
                    }
                    $quarterTotals[$key]['certified'] += (int) $row['certified'];
                    $quarterTotals[$key]['placed'] += (int) $row['placed'];
                }
            }
            $studentStmt->close();
        }

        $indexesByQuarter = [];
        foreach ($monthKeys as $index => $monthKey) {
            $info = training_partner_quarter_from_date($monthKey . '-01');
            $key = (int) $info['fy'] . '-' . (string) $info['quarter'];
            $indexesByQuarter[$key][] = $index;
        }
        foreach ($indexesByQuarter as $key => $indexes) {
            if (!isset($quarterTotals[$key])) {
                continue;
            }
            foreach ($indexes as $index) {
                if ((int) $quarterTotals[$key]['certified'] > 0) {
                    $certified[$index] = (int) $quarterTotals[$key]['certified'];
                }
                if ((int) $quarterTotals[$key]['placed'] > 0) {
                    $placed[$index] = (int) $quarterTotals[$key]['placed'];
                }
            }
        }

        return ['certified' => $certified, 'placed' => $placed];
    }

    function training_partner_student_template(): void
    {
        $rows = [
            ['Student name', 'Mobile', 'Course name', 'Course start date', 'Course end date', 'Training partner', 'Certified', 'Placed'],
            ['Anita Das', '9876543210', 'Certified Data Entry and Office Assistant', '01-04-2025', '30-06-2025', 'Example Institute', 'Yes', 'No'],
            ['Ravi Kumar', '', 'Certified Web Developer', '01-04-2025', '30-06-2025', '', 'Yes', 'Yes'],
        ];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<?mso-application progid="Excel.Sheet"?>'
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            . '<Worksheet ss:Name="Students"><Table>';
        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $cell) {
                $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($cell, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>';
            }
            $xml .= '</Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="training-partner-students.xls"');
        echo $xml;
    }

    function training_partner_sheet_flag($value): int
    {
        $text = strtolower(trim((string) $value));
        return in_array($text, ['1', 'y', 'yes', 'true'], true) ? 1 : 0;
    }

    function training_partner_header_label($cell): string
    {
        $text = strtolower(trim((string) $cell));
        return (string) preg_replace('/[^a-z]/', '', $text);
    }

    function training_partner_parse_date($value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if (is_numeric($text)) {
            $serial = (float) $text;
            if ($serial > 20000 && $serial < 80000) {
                $timestamp = (int) round(($serial - 25569) * 86400);
                return gmdate('Y-m-d', $timestamp);
            }
            return null;
        }
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'd-M-Y', 'j-M-Y', 'd M Y'] as $format) {
            $parsed = DateTime::createFromFormat('!' . $format, $text);
            $errors = DateTime::getLastErrors();
            if ($parsed instanceof DateTime && ($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                return $parsed->format('Y-m-d');
            }
        }
        return null;
    }

    function training_partner_rows_from_matrix(array $matrix): array
    {
        $records = [];
        if ($matrix === []) {
            return $records;
        }
        $headerIndex = null;
        $nameCol = null;
        $mobileCol = null;
        $certifiedCol = null;
        $placedCol = null;
        $courseCol = null;
        $partnerCol = null;
        $startCol = null;
        $endCol = null;
        $scanUntil = min(count($matrix), 25);
        for ($rowIndex = 0; $rowIndex < $scanUntil; $rowIndex++) {
            $labels = array_map('training_partner_header_label', $matrix[$rowIndex]);
            $foundName = null;
            $foundMobile = null;
            $foundCertified = null;
            $foundPlaced = null;
            $foundCourse = null;
            $foundPartner = null;
            $foundStart = null;
            $foundEnd = null;
            foreach ($labels as $index => $label) {
                if ($label === '') {
                    continue;
                }
                $isName = in_array($label, ['studentname', 'nameofcandidate', 'candidatename', 'student', 'name'], true)
                    || (strpos($label, 'nameofcandidate') !== false);
                $isFather = strpos($label, 'father') !== false;
                $isEmployed = strpos($label, 'employ') !== false;
                if ($isName && !$isFather && !$isEmployed && strpos($label, 'institute') === false && strpos($label, 'partner') === false && strpos($label, 'course') === false) {
                    $foundName = $index;
                } elseif (strpos($label, 'watsapp') !== false || strpos($label, 'whatsapp') !== false || in_array($label, ['mobile', 'phone', 'mobileno', 'contactno'], true)) {
                    $foundMobile = $index;
                } elseif ($label === 'certified') {
                    $foundCertified = $index;
                } elseif ($label === 'placed') {
                    $foundPlaced = $index;
                } elseif (in_array($label, ['coursename', 'course'], true)) {
                    $foundCourse = $index;
                } elseif (in_array($label, ['nameofinstitute', 'trainngpartnername', 'trainingpartnername', 'trainingpartner', 'partnername', 'institutename', 'institute'], true) || strpos($label, 'trainingpartner') !== false || strpos($label, 'trainngpartner') !== false) {
                    $foundPartner = $index;
                } elseif (in_array($label, ['coursestartdate', 'coursestartingdate', 'startdate', 'startingdate', 'coursestart', 'batchstartdate', 'fromdate'], true)) {
                    $foundStart = $index;
                } elseif (in_array($label, ['courseenddate', 'courseendingdate', 'enddate', 'endingdate', 'courseend', 'batchenddate', 'todate'], true)) {
                    $foundEnd = $index;
                }
            }
            if ($foundName !== null) {
                $headerIndex = $rowIndex;
                $nameCol = $foundName;
                $mobileCol = $foundMobile;
                $certifiedCol = $foundCertified;
                $placedCol = $foundPlaced;
                $courseCol = $foundCourse;
                $partnerCol = $foundPartner;
                $startCol = $foundStart;
                $endCol = $foundEnd;
                break;
            }
        }
        if ($headerIndex === null || $nameCol === null) {
            return $records;
        }
        for ($i = $headerIndex + 1; $i < count($matrix); $i++) {
            $row = $matrix[$i];
            $name = trim((string) ($row[$nameCol] ?? ''));
            if ($name === '' || strcasecmp($name, 'name of candidate') === 0 || strcasecmp($name, 'student name') === 0) {
                continue;
            }
            $records[] = [
                'name' => $name,
                'mobile' => $mobileCol === null ? '' : trim((string) ($row[$mobileCol] ?? '')),
                'course_name' => $courseCol === null ? '' : trim((string) ($row[$courseCol] ?? '')),
                'partner_name' => $partnerCol === null ? '' : trim((string) ($row[$partnerCol] ?? '')),
                'course_start' => $startCol === null ? null : training_partner_parse_date($row[$startCol] ?? ''),
                'course_end' => $endCol === null ? null : training_partner_parse_date($row[$endCol] ?? ''),
                'certified' => $certifiedCol === null ? 0 : training_partner_sheet_flag($row[$certifiedCol] ?? ''),
                'placed' => $placedCol === null ? 0 : training_partner_sheet_flag($row[$placedCol] ?? ''),
            ];
        }
        return $records;
    }

    function training_partner_read_csv(string $path): array
    {
        $matrix = [];
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return $matrix;
        }
        while (($row = fgetcsv($handle)) !== false) {
            $matrix[] = $row;
        }
        fclose($handle);
        return $matrix;
    }

    function training_partner_read_spreadsheetml(string $xml): array
    {
        $matrix = [];
        if (!preg_match_all('/<Row\b[^>]*>(.*?)<\/Row>/s', $xml, $rows)) {
            return $matrix;
        }
        foreach ($rows[1] as $rowXml) {
            preg_match_all('/<Data\b[^>]*>(.*?)<\/Data>/s', $rowXml, $cells);
            $matrix[] = array_map(static function ($cell) {
                return html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }, $cells[1]);
        }
        return $matrix;
    }

    function training_partner_plain_number(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return $value;
        }
        $number = (float) $value;
        if (abs($number) >= 1000000000) {
            return number_format($number, 0, '', '');
        }
        if (floor($number) == $number) {
            return (string) (int) $number;
        }
        return $value;
    }

    function training_partner_matrix_from_sheet_xml(string $sheetXml, array $shared): array
    {
        $grid = [];
        if (!preg_match_all('/<c\b([^>]*?)(?:\/>|>(.*?)<\/c>)/s', $sheetXml, $cells, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($cells as $cell) {
            $attrs = $cell[1];
            $body = $cell[2] ?? '';
            if (!preg_match('/r="([A-Z]+)(\d+)"/', $attrs, $ref)) {
                continue;
            }
            $col = 0;
            foreach (str_split($ref[1]) as $letter) {
                $col = ($col * 26) + (ord($letter) - 64);
            }
            $col--;
            $rowIndex = (int) $ref[2] - 1;
            $value = '';
            if (strpos($attrs, 't="s"') !== false && preg_match('/<v>(\d+)<\/v>/', $body, $sharedRef)) {
                $value = $shared[(int) $sharedRef[1]] ?? '';
            } elseif (preg_match('/<v>(.*?)<\/v>/s', $body, $number)) {
                $value = training_partner_plain_number($number[1]);
            } elseif (preg_match('/<t\b[^>]*>(.*?)<\/t>/s', $body, $inline)) {
                $value = html_entity_decode($inline[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            $grid[$rowIndex][$col] = $value;
        }
        if ($grid === []) {
            return [];
        }
        ksort($grid);
        $matrix = [];
        $width = 0;
        foreach ($grid as $row) {
            $width = max($width, max(array_keys($row)) + 1);
        }
        $lastRow = max(array_keys($grid));
        for ($rowIndex = 0; $rowIndex <= $lastRow; $rowIndex++) {
            $line = array_fill(0, $width, '');
            foreach ($grid[$rowIndex] ?? [] as $col => $value) {
                $line[$col] = $value;
            }
            $matrix[] = $line;
        }
        return $matrix;
    }

    function training_partner_read_xlsx_sheets(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            return [];
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedXml) && $sharedXml !== '') {
            if (preg_match_all('/<si\b[^>]*>(.*?)<\/si>/s', $sharedXml, $items)) {
                foreach ($items[1] as $item) {
                    preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s', $item, $texts);
                    $shared[] = html_entity_decode(implode('', $texts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        }
        $sheetFiles = [];
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $relTargets = [];
        if (is_string($rels) && preg_match_all('/Id="([^"]+)"[^>]*Target="([^"]+)"/', $rels, $relMatches, PREG_SET_ORDER)) {
            foreach ($relMatches as $rel) {
                $relTargets[$rel[1]] = $rel[2];
            }
        }
        if (is_string($workbook) && preg_match_all('/<sheet\b[^>]*r:id="([^"]+)"[^>]*>/', $workbook, $sheetMatches)) {
            foreach ($sheetMatches[1] as $relId) {
                $target = $relTargets[$relId] ?? '';
                if ($target !== '' && strpos($target, 'worksheets/') !== false) {
                    $sheetFiles[] = 'xl/' . ltrim(str_replace('\\', '/', $target), '/');
                }
            }
        }
        if ($sheetFiles === []) {
            $sheetFiles[] = 'xl/worksheets/sheet1.xml';
        }
        $sheets = [];
        foreach ($sheetFiles as $sheetFile) {
            $sheetXml = $zip->getFromName($sheetFile);
            if (!is_string($sheetXml) || $sheetXml === '') {
                continue;
            }
            $matrix = training_partner_matrix_from_sheet_xml($sheetXml, $shared);
            if ($matrix !== []) {
                $sheets[] = $matrix;
            }
        }
        $zip->close();
        return $sheets;
    }

    function training_partner_import_students(mysqli $conn, string $path, string $originalName, int $partnerId, int $schemeId, int $fyYear, string $quarter, ?string $courseStart = null, ?string $courseEnd = null): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $sheets = [];
        if ($ext === 'csv') {
            $sheets[] = training_partner_read_csv($path);
        } elseif ($ext === 'xlsx') {
            $sheets = training_partner_read_xlsx_sheets($path);
        } elseif ($ext === 'xls') {
            $raw = file_get_contents($path);
            if (!is_string($raw) || strpos($raw, '<Workbook') === false) {
                return ['ok' => false, 'error' => 'Use the downloaded Excel template, or save the sheet as .xlsx.', 'saved' => 0];
            }
            $sheets[] = training_partner_read_spreadsheetml($raw);
        } else {
            return ['ok' => false, 'error' => 'Upload an Excel file (.xlsx or the .xls template) or a .csv file.', 'saved' => 0];
        }
        $records = [];
        foreach ($sheets as $matrix) {
            foreach (training_partner_rows_from_matrix($matrix) as $record) {
                $records[] = $record;
            }
        }
        if ($records === []) {
            return ['ok' => false, 'error' => 'No student names were found. The sheet needs a column named Student name or Name of candidate.', 'saved' => 0];
        }
        $defaultStart = training_partner_parse_date($courseStart);
        $defaultEnd = training_partner_parse_date($courseEnd);
        $saved = 0;
        $skipped = 0;
        $partnersCreated = 0;
        $partnerIds = [];
        foreach ($records as $record) {
            $rowPartnerId = $partnerId;
            $partnerName = trim((string) ($record['partner_name'] ?? ''));
            if ($partnerName !== '') {
                $key = strtolower($partnerName);
                if (!isset($partnerIds[$key])) {
                    $before = 0;
                    $check = $conn->prepare('SELECT id FROM training_partners WHERE LOWER(partner_name) = LOWER(?) LIMIT 1');
                    if ($check) {
                        $check->bind_param('s', $partnerName);
                        $check->execute();
                        $existingPartner = $check->get_result()->fetch_assoc();
                        $check->close();
                        $before = $existingPartner ? (int) $existingPartner['id'] : 0;
                    }
                    $partnerIds[$key] = training_partner_find_or_create($conn, $partnerName);
                    if ($before === 0 && $partnerIds[$key] > 0) {
                        $partnersCreated++;
                    }
                }
                $rowPartnerId = (int) $partnerIds[$key];
            }
            if ($rowPartnerId <= 0) {
                $skipped++;
                continue;
            }
            $result = training_partner_save_student(
                $conn,
                $rowPartnerId,
                $schemeId,
                $record['name'],
                $record['mobile'],
                $fyYear,
                $quarter,
                (int) $record['certified'],
                (int) $record['placed'],
                (string) ($record['course_name'] ?? ''),
                $record['course_start'] ?? $defaultStart,
                $record['course_end'] ?? $defaultEnd
            );
            if (!empty($result['ok'])) {
                $saved++;
            }
        }
        if ($saved === 0) {
            return ['ok' => false, 'error' => 'The sheet was read, but no student could be saved. Choose the scheme and quarter, and make sure each row has a training partner.', 'saved' => 0, 'skipped' => $skipped];
        }
        return ['ok' => true, 'saved' => $saved, 'skipped' => $skipped, 'partners_created' => $partnersCreated, 'sheets' => count($sheets)];
    }
}
