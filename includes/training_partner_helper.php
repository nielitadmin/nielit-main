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
            course_category VARCHAR(180) NOT NULL DEFAULT '',
            course_start_date DATE NULL,
            course_end_date DATE NULL,
            exam_date DATE NULL,
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
        if (!isset($studentColumns['course_category'])) {
            $conn->query("ALTER TABLE training_partner_students ADD COLUMN course_category VARCHAR(180) NOT NULL DEFAULT '' AFTER course_name");
        }
        if (!isset($studentColumns['course_start_date'])) {
            $conn->query('ALTER TABLE training_partner_students ADD COLUMN course_start_date DATE NULL AFTER figure_month');
        }
        if (!isset($studentColumns['course_end_date'])) {
            $conn->query('ALTER TABLE training_partner_students ADD COLUMN course_end_date DATE NULL AFTER course_start_date');
        }
        if (!isset($studentColumns['exam_date'])) {
            $conn->query('ALTER TABLE training_partner_students ADD COLUMN exam_date DATE NULL AFTER course_end_date');
        }
        if (!isset($studentColumns['upload_batch_id'])) {
            $conn->query("ALTER TABLE training_partner_students ADD COLUMN upload_batch_id VARCHAR(64) NOT NULL DEFAULT '' AFTER scheme_id");
            $conn->query("ALTER TABLE training_partner_students ADD INDEX idx_tp_batch_id (upload_batch_id)");
        }
        if (!isset($studentColumns['upload_file_name'])) {
            $conn->query("ALTER TABLE training_partner_students ADD COLUMN upload_file_name VARCHAR(255) NOT NULL DEFAULT '' AFTER upload_batch_id");
        }
        // Backfill existing records that have no upload_file_name (only when needed)
        $needsBackfill = $conn->query("SELECT 1 FROM training_partner_students WHERE upload_batch_id = '' OR upload_file_name = '' LIMIT 1");
        if ($needsBackfill && $needsBackfill->num_rows > 0) {
            $conn->query("UPDATE training_partner_students 
                          SET upload_batch_id = CONCAT('batch_', DATE_FORMAT(created_at, '%Y%m%d_%H')),
                              upload_file_name = 'Spreadsheet Upload'
                          WHERE upload_batch_id = '' OR upload_file_name = ''");
        }
        // Auto-heal mismatched end dates (only when needed)
        $needsEndHeal = $conn->query("SELECT 1 FROM training_partner_students 
                      WHERE course_start_date IS NOT NULL 
                        AND course_end_date IS NOT NULL 
                        AND course_end_date < course_start_date 
                        AND YEAR(course_end_date) < YEAR(course_start_date) LIMIT 1");
        if ($needsEndHeal && $needsEndHeal->num_rows > 0) {
            $conn->query("UPDATE training_partner_students 
                          SET course_end_date = CONCAT(YEAR(course_start_date), SUBSTRING(course_end_date, 5))
                          WHERE course_start_date IS NOT NULL 
                            AND course_end_date IS NOT NULL 
                            AND course_end_date < course_start_date 
                            AND YEAR(course_end_date) < YEAR(course_start_date)");
        }
        foreach (training_partner_detail_fields() as $field => $kind) {
            if (isset($studentColumns[$field])) {
                continue;
            }
            $sqlType = $kind === 'text' ? 'TEXT NULL' : ($kind === 'int' ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'VARCHAR(180) NOT NULL DEFAULT \'\'');
            if ($field === 'aadhaar' || $field === 'gender') {
                $sqlType = 'VARCHAR(20) NOT NULL DEFAULT \'\'';
            } elseif ($field === 'category') {
                $sqlType = 'VARCHAR(40) NOT NULL DEFAULT \'\'';
            } elseif ($field === 'apaar_id') {
                $sqlType = 'VARCHAR(50) NOT NULL DEFAULT \'\'';
            } elseif (in_array($field, ['batch_no', 'nielit_reg_no', 'hq_accr_number', 'religion'], true)) {
                $sqlType = 'VARCHAR(80) NOT NULL DEFAULT \'\'';
            } elseif (in_array($field, ['district', 'state'], true)) {
                $sqlType = 'VARCHAR(120) NOT NULL DEFAULT \'\'';
            }
            $conn->query('ALTER TABLE training_partner_students ADD COLUMN `' . $field . '` ' . $sqlType);
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

        // Auto-heal duplicate candidate Aadhaar numbers that were truncated by Excel scientific notation
        $knownDuplicates = [
            'KIRAN KUMAR MANDAL' => '282714569227',
            'ROJALINI BEHERA' => '592392915768',
            'MADHURI NAIK' => '873336311350',
            'BAISAKHI BHOI' => '808009367028',
            'SRABANI SETHY' => '608039715806',
            'SUBHAMAYEE SETHIA' => '377732797552',
        ];
        foreach ($knownDuplicates as $candName => $fullAadhaar) {
            $conn->query("UPDATE training_partner_students SET aadhaar = '{$fullAadhaar}' WHERE student_name = '{$candName}' AND (aadhaar = '' OR LENGTH(aadhaar) < 12)");
        }
    }

        function training_partner_course_categories(): array
    {
        return [
            'Skill Based (Long Term) Courses (> 500 hrs)',
            'Skill Based (Short Term) Courses (90-500 hrs)',
            'Short Term / Digital Competency Courses (<= 90 hrs)',
            'Degree / Diploma / Postgraduate Courses',
            'NIELIT HQ Digital Literacy Courses (CCC / ECC / BCC / ACC)',
        ];
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
                       COALESCE(c.name, '') AS centre_name,
                       COUNT(st.id) AS student_count,
                       SUM(CASE WHEN st.is_certified = 1 THEN 1 ELSE 0 END) AS certified_count,
                       SUM(CASE WHEN st.is_placed = 1 THEN 1 ELSE 0 END) AS placed_count
                FROM training_partners p
                LEFT JOIN centres c ON c.id = p.centre_id
                LEFT JOIN training_partner_students st ON st.partner_id = p.id
                " . ($activeOnly ? 'WHERE p.is_active = 1 ' : '') . "
                GROUP BY p.id, p.partner_name, p.partner_code, p.centre_id, p.is_active, c.name
                ORDER BY p.partner_name ASC";
        $result = $conn->query($sql);
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $figRes = $conn->query("SELECT partner_id, SUM(enrolled) AS fig_enrolled, SUM(certified) AS fig_certified, SUM(placed) AS fig_placed FROM training_partner_figures GROUP BY partner_id");
        if ($figRes) {
            $figMap = [];
            while ($fr = $figRes->fetch_assoc()) {
                $figMap[(int)$fr['partner_id']] = $fr;
            }
            foreach ($rows as &$r) {
                $pid = (int) $r['id'];
                if ((int) ($r['student_count'] ?? 0) === 0 && isset($figMap[$pid])) {
                    $r['student_count'] = (int) $figMap[$pid]['fig_enrolled'];
                    $r['certified_count'] = (int) $figMap[$pid]['fig_certified'];
                    $r['placed_count'] = (int) $figMap[$pid]['fig_placed'];
                }
            }
            unset($r);
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

    function training_partner_figure_month_from_course(?string $courseStart, ?string $courseEnd): string
    {
        $date = $courseStart ?: $courseEnd;
        $time = $date ? strtotime($date) : false;
        if ($time === false) {
            return date('Y-m-01');
        }
        return date('Y-m-01', $time);
    }

    function training_partner_course_overlaps(?string $courseStart, ?string $courseEnd, string $rangeStart, string $rangeNext): bool
    {
        $start = ($courseStart !== null && $courseStart !== '' && $courseStart !== '0000-00-00') ? $courseStart : null;
        $end = ($courseEnd !== null && $courseEnd !== '' && $courseEnd !== '0000-00-00') ? $courseEnd : null;
        if ($start === null && $end === null) {
            return false;
        }
        if ($start === null) {
            return $end >= $rangeStart && $end < $rangeNext;
        }
        if ($end === null) {
            $end = $start;
        }
        return $start < $rangeNext && $end >= $rangeStart;
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
        $rows = [];
        $studentSql = "SELECT p.id, p.partner_name, p.partner_code,
                              COALESCE(sc.scheme_name, '') AS scheme_name,
                              st.course_start_date, st.course_end_date, st.figure_month, st.is_certified, st.is_placed
                       FROM training_partner_students st
                       INNER JOIN training_partners p ON p.id = st.partner_id
                       LEFT JOIN schemes sc ON sc.id = st.scheme_id
                       WHERE p.is_active = 1
                         AND (
                            (st.course_start_date IS NOT NULL AND st.course_start_date <> '0000-00-00' AND st.course_start_date < ?
                                AND (st.course_end_date IS NULL OR st.course_end_date = '0000-00-00' OR st.course_end_date >= ?))
                            OR ((st.course_start_date IS NULL OR st.course_start_date = '0000-00-00')
                                AND st.course_end_date >= ? AND st.course_end_date < ?)
                            OR ((st.course_start_date IS NULL OR st.course_start_date = '0000-00-00')
                                AND (st.course_end_date IS NULL OR st.course_end_date = '0000-00-00')
                                AND st.figure_month >= ? AND st.figure_month < ?)
                         )";
        $studentTypes = 'ssssss';
        $studentValues = [$next, $start, $start, $next, $start, $next];
        if ($centreId > 0) {
            $studentSql .= ' AND p.centre_id = ?';
            $studentTypes .= 'i';
            $studentValues[] = $centreId;
        }
        $studentSql .= ' ORDER BY sc.scheme_name ASC, p.partner_name ASC, st.id ASC';
        $studentStmt = $conn->prepare($studentSql);
        $grouped = [];
        if ($studentStmt) {
            $studentStmt->bind_param($studentTypes, ...$studentValues);
            $studentStmt->execute();
            $studentResult = $studentStmt->get_result();
            if ($studentResult) {
                while ($row = $studentResult->fetch_assoc()) {
                    $cStart = ($row['course_start_date'] !== null && $row['course_start_date'] !== '' && $row['course_start_date'] !== '0000-00-00') ? $row['course_start_date'] : ($row['figure_month'] ?? null);
                    $cEnd = ($row['course_end_date'] !== null && $row['course_end_date'] !== '' && $row['course_end_date'] !== '0000-00-00') ? $row['course_end_date'] : $cStart;
                    if (!training_partner_course_overlaps($cStart, $cEnd, $start, $next)) {
                        continue;
                    }
                    $key = (int) $row['id'] . '|' . (string) $row['scheme_name'];
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [
                            'id' => (int) $row['id'],
                            'scheme_name' => (string) $row['scheme_name'],
                            'partner_name' => (string) $row['partner_name'],
                            'partner_code' => (string) $row['partner_code'],
                            'enrolled' => 0,
                            'certified' => 0,
                            'placed' => 0,
                        ];
                    }
                    $grouped[$key]['enrolled']++;
                    $grouped[$key]['certified'] += (int) $row['is_certified'];
                    $grouped[$key]['placed'] += (int) $row['is_placed'];
                }
            }
            $studentStmt->close();
        }

        // Also merge training_partner_figures if entered
        $figSql = "SELECT p.id, p.partner_name, p.partner_code, f.enrolled, f.certified, f.placed
                   FROM training_partner_figures f
                   INNER JOIN training_partners p ON p.id = f.partner_id
                   WHERE p.is_active = 1 AND f.figure_month >= ? AND f.figure_month < ?";
        $figTypes = 'ss';
        $figValues = [$start, $next];
        if ($centreId > 0) {
            $figSql .= ' AND p.centre_id = ?';
            $figTypes .= 'i';
            $figValues[] = $centreId;
        }
        $figStmt = $conn->prepare($figSql);
        if ($figStmt) {
            $figStmt->bind_param($figTypes, ...$figValues);
            $figStmt->execute();
            $figRes = $figStmt->get_result();
            if ($figRes) {
                while ($frow = $figRes->fetch_assoc()) {
                    $fkey = (int) $frow['id'] . '|figures';
                    if (!isset($grouped[$fkey])) {
                        $grouped[$fkey] = [
                            'id' => (int) $frow['id'],
                            'scheme_name' => 'Training Partner Figures',
                            'partner_name' => (string) $frow['partner_name'],
                            'partner_code' => (string) $frow['partner_code'],
                            'enrolled' => 0,
                            'certified' => 0,
                            'placed' => 0,
                        ];
                    }
                    $grouped[$fkey]['enrolled'] += (int) $frow['enrolled'];
                    $grouped[$fkey]['certified'] += (int) $frow['certified'];
                    $grouped[$fkey]['placed'] += (int) $frow['placed'];
                }
            }
            $figStmt->close();
        }
        return array_values($grouped);
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

    function training_partner_detail_fields(): array
    {
        return [
            'batch_no' => 'string',
            'nielit_reg_no' => 'string',
            'father_name' => 'string',
            'category' => 'string',
            'gender' => 'string',
            'aadhaar' => 'string',
            'apaar_id' => 'string',
            'religion' => 'string',
            'communication_address' => 'text',
            'currently_employed' => 'int',
            'venue_address' => 'text',
            'hq_accr_number' => 'string',
            'district' => 'string',
            'state' => 'string',
        ];
    }

    function training_partner_details_from_request(array $source): array
    {
        $details = ['_present' => []];
        foreach (training_partner_detail_fields() as $field => $kind) {
            $details['_present'][$field] = true;
            if ($kind === 'int') {
                $details[$field] = !empty($source[$field]) ? 1 : 0;
                continue;
            }
            $details[$field] = trim((string) ($source[$field] ?? ''));
        }
        return $details;
    }

    function training_partner_show($value): string
    {
        $text = trim((string) $value);
        return $text === '' ? '-' : $text;
    }

    function training_partner_show_date($value): string
    {
        $text = trim((string) $value);
        if ($text === '' || $text === '0000-00-00') {
            return '-';
        }
        $time = strtotime($text);
        return $time ? date('d M Y', $time) : $text;
    }

    function training_partner_show_aadhaar($value, string $candName = ''): string
    {
        $val = trim((string) $value);
        if ($val === '' || $val === '0') {
            return '-';
        }
        static $known = [
            'KIRAN KUMAR MANDAL' => '282714569227',
            'ROJALINI BEHERA' => '592392915768',
            'MADHURI NAIK' => '873336311350',
            'BAISAKHI BHOI' => '808009367028',
            'SRABANI SETHY' => '608039715806',
            'SUBHAMAYEE SETHIA' => '377732797552',
        ];
        if (strlen($val) < 12 && $candName !== '' && isset($known[$candName])) {
            $val = $known[$candName];
        }
        if (strlen($val) === 12 && ctype_digit($val)) {
            return substr($val, 0, 4) . ' ' . substr($val, 4, 4) . ' ' . substr($val, 8, 4);
        }
        return $val;
    }

    function training_partner_detail_value(string $field, array $details)
    {
        $kind = training_partner_detail_fields()[$field] ?? 'string';
        if ($kind === 'int') {
            return !empty($details[$field]) ? 1 : 0;
        }
        $value = trim((string) ($details[$field] ?? ''));
        if ($field === 'aadhaar') {
            if (preg_match('/^[+-]?\d+(?:\.\d+)?(?:[eE][+-]?\d+)$/', $value)) {
                $value = number_format((float) $value, 0, '', '');
            }
            $digits = preg_replace('/\D+/', '', $value);
            return $digits !== '' ? $digits : $value;
        }
        if ($field === 'apaar_id') {
            if (preg_match('/^[+-]?\d+(?:\.\d+)?(?:[eE][+-]?\d+)$/', $value)) {
                return number_format((float) $value, 0, '', '');
            }
            return $value;
        }
        return $value;
    }

    function training_partner_execute(mysqli $conn, string $sql, string $types, array $values): bool
    {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $refs = [];
        $refs[] = &$types;
        foreach ($values as $index => $value) {
            $refs[] = &$values[$index];
        }
        $bound = call_user_func_array([$stmt, 'bind_param'], $refs);
        $ok = $bound && $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function training_partner_save_student(mysqli $conn, int $partnerId, int $schemeId, string $name, string $mobile, int $certified, int $placed, string $courseName = '', ?string $courseStart = null, ?string $courseEnd = null, array $details = [], ?string $examDate = null, bool $saveExamDate = false, string $courseCategory = '', string $uploadBatchId = '', string $uploadFileName = ''): array
    {
        training_partner_ensure_schema($conn);
        $name = trim($name);
        $mobile = trim($mobile);
        $courseName = trim($courseName);
        $courseCategory = trim($courseCategory);
        if ($schemeId <= 0) {
            return ['ok' => false, 'error' => 'Choose the scheme or project first.'];
        }
        if ($partnerId <= 0 || $name === '') {
            return ['ok' => false, 'error' => 'Choose the training partner and student name.'];
        }
        $certified = $certified === 1 ? 1 : 0;
        $placed = $placed === 1 ? 1 : 0;
        $courseStart = $courseStart !== null && $courseStart !== '' ? $courseStart : null;
        $courseEnd = $courseEnd !== null && $courseEnd !== '' ? $courseEnd : null;
        if ($courseStart !== null && $courseEnd !== null && $courseEnd < $courseStart) {
            $sYear = (int) substr($courseStart, 0, 4);
            $eYear = (int) substr($courseEnd, 0, 4);
            if ($eYear < $sYear) {
                $adjustedEnd = $sYear . substr($courseEnd, 4);
                $courseEnd = ($adjustedEnd >= $courseStart) ? $adjustedEnd : $courseStart;
            }
        }
        $examDate = $examDate !== null && $examDate !== '' ? $examDate : null;
        $figureMonth = training_partner_figure_month_from_course($courseStart, $courseEnd);
        $present = is_array($details['_present'] ?? null) ? $details['_present'] : [];
        $existing = $conn->prepare('SELECT id FROM training_partner_students WHERE partner_id = ? AND scheme_id = ? AND student_name = ? AND mobile = ? ORDER BY id DESC LIMIT 1');
        if ($existing) {
            $existing->bind_param('iiss', $partnerId, $schemeId, $name, $mobile);
            $existing->execute();
            $found = $existing->get_result()->fetch_assoc();
            $existing->close();
            if ($found) {
                $sets = ['course_name = ?', 'course_category = ?', 'course_start_date = ?', 'course_end_date = ?', 'figure_month = ?', 'is_certified = ?', 'is_placed = ?'];
                $types = 'sssssii';
                $values = [$courseName, $courseCategory, $courseStart, $courseEnd, $figureMonth, $certified, $placed];
                if ($saveExamDate) {
                    $sets[] = 'exam_date = ?';
                    $types .= 's';
                    $values[] = $examDate;
                }
                if ($uploadBatchId !== '') {
                    $sets[] = 'upload_batch_id = ?';
                    $types .= 's';
                    $values[] = $uploadBatchId;
                }
                if ($uploadFileName !== '') {
                    $sets[] = 'upload_file_name = ?';
                    $types .= 's';
                    $values[] = $uploadFileName;
                }
                foreach (training_partner_detail_fields() as $field => $kind) {
                    if (empty($present[$field])) {
                        continue;
                    }
                    $sets[] = '`' . $field . '` = ?';
                    $types .= $kind === 'int' ? 'i' : 's';
                    $values[] = training_partner_detail_value($field, $details);
                }
                $types .= 'i';
                $values[] = (int) $found['id'];
                $ok = training_partner_execute($conn, 'UPDATE training_partner_students SET ' . implode(', ', $sets) . ' WHERE id = ?', $types, $values);
                $studentId = (int) $found['id'];
                return $ok ? ['ok' => true, 'id' => $studentId, 'is_update' => true] : ['ok' => false, 'error' => 'Could not save the student record.'];
            }
        }
        $columns = ['partner_id', 'scheme_id', 'upload_batch_id', 'upload_file_name', 'course_name', 'course_category', 'student_name', 'mobile', 'figure_month', 'course_start_date', 'course_end_date', 'exam_date', 'is_certified', 'is_placed'];
        $types = 'iissssssssssii';
        $values = [$partnerId, $schemeId, $uploadBatchId, $uploadFileName, $courseName, $courseCategory, $name, $mobile, $figureMonth, $courseStart, $courseEnd, $examDate, $certified, $placed];
        foreach (training_partner_detail_fields() as $field => $kind) {
            $columns[] = '`' . $field . '`';
            $types .= $kind === 'int' ? 'i' : 's';
            $values[] = training_partner_detail_value($field, $details);
        }
        $ok = training_partner_execute(
            $conn,
            'INSERT INTO training_partner_students (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
            $types,
            $values
        );
        $studentId = $ok ? (int) $conn->insert_id : 0;
        return $ok ? ['ok' => true, 'id' => $studentId, 'is_update' => false] : ['ok' => false, 'error' => 'Could not save the student record.'];
    }

    function training_partner_update_student_status(mysqli $conn, int $id, int $certified, int $placed): bool
    {
        training_partner_ensure_schema($conn);
        if ($id <= 0) {
            return false;
        }
        $certified = $certified === 1 ? 1 : 0;
        $placed = $placed === 1 ? 1 : 0;
        $stmt = $conn->prepare('UPDATE training_partner_students SET is_certified = ?, is_placed = ? WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iii', $certified, $placed, $id);
        $ok = $stmt->execute() && $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
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

    function training_partner_upload_batches(mysqli $conn): array
    {
        training_partner_ensure_schema($conn);
        $sql = "SELECT st.upload_batch_id,
                       COALESCE(NULLIF(st.upload_file_name, ''), 'Spreadsheet Upload') AS file_name,
                       sc.id AS scheme_id,
                       COALESCE(sc.scheme_name, 'Unknown Scheme') AS scheme_name,
                       MIN(st.created_at) AS first_uploaded,
                       MAX(st.created_at) AS last_uploaded,
                       COUNT(st.id) AS total_students
                FROM training_partner_students st
                LEFT JOIN schemes sc ON sc.id = st.scheme_id
                WHERE st.upload_batch_id <> ''
                GROUP BY st.upload_batch_id, file_name, sc.id, sc.scheme_name
                ORDER BY last_uploaded DESC";
        $res = $conn->query($sql);
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        return $rows;
    }

    function training_partner_delete_students_by_batch(mysqli $conn, string $batchId): array
    {
        training_partner_ensure_schema($conn);
        $batchId = trim($batchId);
        if ($batchId === '') {
            return ['ok' => false, 'error' => 'Select an upload batch to remove.'];
        }
        $stmt = $conn->prepare('DELETE FROM training_partner_students WHERE upload_batch_id = ?');
        if (!$stmt) {
            return ['ok' => false, 'error' => $conn->error];
        }
        $stmt->bind_param('s', $batchId);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return ['ok' => (bool) $ok, 'deleted' => $affected];
    }

    function training_partner_delete_students_by_scheme(mysqli $conn, int $schemeId): array
    {
        training_partner_ensure_schema($conn);
        if ($schemeId <= 0) {
            return ['ok' => false, 'error' => 'Select a valid scheme / project.'];
        }
        $stmt = $conn->prepare('DELETE FROM training_partner_students WHERE scheme_id = ?');
        if (!$stmt) {
            return ['ok' => false, 'error' => $conn->error];
        }
        $stmt->bind_param('i', $schemeId);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return ['ok' => $ok, 'deleted' => $affected];
    }

    function training_partner_delete_students_by_ids(mysqli $conn, array $ids): array
    {
        training_partner_ensure_schema($conn);
        $intIds = array_values(array_filter(array_map('intval', $ids), static function ($v) { return $v > 0; }));
        if ($intIds === []) {
            return ['ok' => false, 'error' => 'No valid student records provided.'];
        }
        $inClause = implode(',', $intIds);
        $ok = $conn->query("DELETE FROM training_partner_students WHERE id IN ({$inClause})");
        $affected = $ok ? $conn->affected_rows : 0;
        return ['ok' => (bool) $ok, 'deleted' => $affected];
    }

    function training_partner_delete_all_students(mysqli $conn): array
    {
        training_partner_ensure_schema($conn);
        $ok = $conn->query("DELETE FROM training_partner_students");
        $affected = $ok ? $conn->affected_rows : 0;
        return ['ok' => (bool) $ok, 'deleted' => $affected];
    }

    function training_partner_student_counts_by_scheme(mysqli $conn): array
    {
        training_partner_ensure_schema($conn);
        $sql = "SELECT sc.id, COALESCE(sc.scheme_name, 'Unassigned Scheme') AS scheme_name, COUNT(st.id) AS total_students
                FROM training_partner_students st
                LEFT JOIN schemes sc ON sc.id = st.scheme_id
                GROUP BY sc.id, sc.scheme_name
                HAVING total_students > 0
                ORDER BY total_students DESC";
        $res = $conn->query($sql);
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        return $rows;
    }

    function training_partner_student_counts_by_partner(mysqli $conn): array
    {
        training_partner_ensure_schema($conn);
        $sql = "SELECT p.id, COALESCE(p.partner_name, 'Unassigned Partner') AS partner_name, COUNT(st.id) AS total_students
                FROM training_partner_students st
                LEFT JOIN training_partners p ON p.id = st.partner_id
                GROUP BY p.id, p.partner_name
                HAVING total_students > 0
                ORDER BY total_students DESC";
        $res = $conn->query($sql);
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        return $rows;
    }

    function training_partner_delete_students_by_partner(mysqli $conn, int $partnerId): array
    {
        training_partner_ensure_schema($conn);
        if ($partnerId <= 0) {
            return ['ok' => false, 'error' => 'Select a valid training partner.'];
        }
        $stmt = $conn->prepare('DELETE FROM training_partner_students WHERE partner_id = ?');
        if (!$stmt) {
            return ['ok' => false, 'error' => $conn->error];
        }
        $stmt->bind_param('i', $partnerId);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return ['ok' => (bool) $ok, 'deleted' => $affected];
    }

    function training_partner_students_list(mysqli $conn, int $limit = 0): array
    {
        training_partner_ensure_schema($conn);
        $detailSelect = implode(', ', array_map(static function ($field) {
            return 'st.`' . $field . '`';
        }, array_keys(training_partner_detail_fields())));
        $limitClause = $limit > 0 ? ' LIMIT ' . (int) $limit : '';
        $sql = "SELECT st.id, st.partner_id, st.scheme_id, st.course_name, st.course_category, st.student_name, st.mobile, st.figure_month,
                       st.course_start_date, st.course_end_date, st.exam_date, st.is_certified, st.is_placed, {$detailSelect},
                       COALESCE(p.partner_name, 'Unassigned Partner') AS partner_name, COALESCE(sc.scheme_name, '') AS scheme_name, COALESCE(sc.scheme_code, '') AS scheme_code
                FROM training_partner_students st
                LEFT JOIN training_partners p ON p.id = st.partner_id
                LEFT JOIN schemes sc ON sc.id = st.scheme_id
                WHERE (p.id IS NULL OR p.is_active = 1)
                ORDER BY st.id ASC{$limitClause}";
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
        $studentSql = 'SELECT st.course_start_date, st.course_end_date, st.is_certified, st.is_placed
            FROM training_partner_students st
            INNER JOIN training_partners p ON p.id = st.partner_id
            WHERE p.is_active = 1
              AND (
                (st.course_start_date IS NOT NULL AND st.course_start_date <> \'0000-00-00\' AND st.course_start_date < ?
                    AND (st.course_end_date IS NULL OR st.course_end_date = \'0000-00-00\' OR st.course_end_date >= ?))
                OR ((st.course_start_date IS NULL OR st.course_start_date = \'0000-00-00\')
                    AND st.course_end_date >= ? AND st.course_end_date < ?)
              )'
            . ($centreId > 0 ? ' AND p.centre_id = ?' : '');
        $studentStmt = $conn->prepare($studentSql);
        if (!$studentStmt) {
            return ['certified' => $certified, 'placed' => $placed];
        }
        if ($centreId > 0) {
            $studentStmt->bind_param('ssssi', $next, $start, $start, $next, $centreId);
        } else {
            $studentStmt->bind_param('ssss', $next, $start, $start, $next);
        }
        $studentStmt->execute();
        $studentResult = $studentStmt->get_result();
        $students = [];
        if ($studentResult) {
            while ($row = $studentResult->fetch_assoc()) {
                $students[] = $row;
            }
        }
        $studentStmt->close();
        foreach ($monthKeys as $index => $monthKey) {
            $monthStart = $monthKey . '-01';
            $monthNext = date('Y-m-d', strtotime($monthStart . ' +1 month'));
            $monthCertified = 0;
            $monthPlaced = 0;
            foreach ($students as $student) {
                if (!training_partner_course_overlaps($student['course_start_date'] ?? null, $student['course_end_date'] ?? null, $monthStart, $monthNext)) {
                    continue;
                }
                $monthCertified += (int) $student['is_certified'];
                $monthPlaced += (int) $student['is_placed'];
            }
            if ($monthCertified > 0) {
                $certified[$index] = $monthCertified;
            }
            if ($monthPlaced > 0) {
                $placed[$index] = $monthPlaced;
            }
        }

        return ['certified' => $certified, 'placed' => $placed];
    }

    function training_partner_student_template(): void
    {
        $rows = [
            [
                'Sl. No.',
                'Batch No',
                'Course Name',
                'Course Category',
                'NIELIT Registration No. (NIELIT Portal)',
                'Name of candidate',
                'Father\'s Name',
                'Category (SC/ST/EWS)',
                'Gender (Male/Female)',
                'Aadhaar Card',
                'APAAR ID',
                'Religion',
                'Watsapp No. of candidates',
                'Communication address and districts of the candidate',
                'Candidate Currently employed (Yes/No)',
                'Name of Institute',
                'Training venue Address',
                'HQ Accr number',
                'District',
                'State',
                'Course start date',
                'Course end date',
                'Exam date',
                'Certified',
                'Placed',
            ],
            [
                '1',
                'B-2025-01',
                'Certified Data Entry and Office Assistant',
                'Short Term / Digital Competency Courses (<= 90 hrs)',
                'NL123456',
                'Anita Das',
                'Ramesh Das',
                'EWS',
                'Female',
                '000000000000',
                '123456789012',
                'Hindu',
                '9876543210',
                'House 12, Example Road, Kamrup',
                'No',
                'Example Institute',
                'Example Institute, Guwahati',
                'HQ-1001',
                'Kamrup',
                'Assam',
                '01-04-2025',
                '30-06-2025',
                '15-06-2025',
                'Yes',
                'No',
            ],
            [
                '2',
                'B-2025-01',
                'Certified Web Developer',
                'Skill Based (Short Term) Courses (90-500 hrs)',
                '',
                'Ravi Kumar',
                'Suresh Kumar',
                'SC',
                'Male',
                '',
                '',
                '',
                '',
                '',
                'Yes',
                '',
                '',
                '',
                'Nagaon',
                'Assam',
                '01-04-2025',
                '30-06-2025',
                '20-06-2025',
                'Yes',
                'Yes',
            ],
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
        foreach ([
            'Y-m-d',
            'd-m-Y',
            'd/m/Y',
            'm/d/Y',
            'n/j/Y',
            'j/n/Y',
            'm-d-Y',
            'n-j-Y',
            'd.m.Y',
            'm.d.Y',
            'd-M-Y',
            'j-M-Y',
            'd M Y',
            'd F Y',
            'j F Y',
            'Y/m/d',
        ] as $format) {
            $parsed = DateTime::createFromFormat('!' . $format, $text);
            $errors = DateTime::getLastErrors();
            if ($parsed instanceof DateTime && ($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                return $parsed->format('Y-m-d');
            }
        }
        // Month-year exam labels, e.g. Aug-25 → first day of that month
        if (preg_match('/^([A-Za-z]{3,9})[\s\-\/\.]+(\d{2}|\d{4})$/', $text, $parts)) {
            $year = (int) $parts[2];
            if ($year < 100) {
                $year += 2000;
            }
            $monthToken = $parts[1];
            foreach (['M', 'F'] as $monthFormat) {
                $parsed = DateTime::createFromFormat('!' . $monthFormat . '-Y-d', $monthToken . '-' . $year . '-01');
                $errors = DateTime::getLastErrors();
                if ($parsed instanceof DateTime && ($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                    return $parsed->format('Y-m-d');
                }
            }
        }
        return null;
    }

    function training_partner_header_map(array $labels): ?array
    {
        $map = [
            'name' => null,
            'mobile' => null,
            'certified' => null,
            'placed' => null,
            'course_name' => null,
            'course_category' => null,
            'partner_name' => null,
            'course_start' => null,
            'course_end' => null,
            'exam_date' => null,
            'batch_no' => null,
            'nielit_reg_no' => null,
            'father_name' => null,
            'category' => null,
            'gender' => null,
            'aadhaar' => null,
            'apaar_id' => null,
            'religion' => null,
            'communication_address' => null,
            'currently_employed' => null,
            'venue_address' => null,
            'hq_accr_number' => null,
            'district' => null,
            'state' => null,
        ];
        foreach ($labels as $index => $label) {
            if ($label === '') {
                continue;
            }
            $isFather = strpos($label, 'father') !== false;
            $isEmployed = strpos($label, 'employ') !== false;
            $isInstitute = strpos($label, 'institute') !== false;
            $isVenue = strpos($label, 'venue') !== false;
            $isName = in_array($label, ['studentname', 'nameofcandidate', 'candidatename', 'student', 'name'], true)
                || strpos($label, 'nameofcandidate') !== false;
            if ($isName && !$isFather && !$isEmployed && !$isInstitute && strpos($label, 'partner') === false && strpos($label, 'course') === false) {
                $map['name'] = $index;
            } elseif ($isFather) {
                $map['father_name'] = $index;
            } elseif (strpos($label, 'watsapp') !== false || strpos($label, 'whatsapp') !== false || in_array($label, ['mobile', 'phone', 'mobileno', 'contactno'], true)) {
                $map['mobile'] = $index;
            } elseif ($label === 'certified') {
                $map['certified'] = $index;
            } elseif ($label === 'placed') {
                $map['placed'] = $index;
            } elseif (in_array($label, ['coursename', 'course'], true) || ($label !== 'coursestartdate' && $label !== 'courseenddate' && strpos($label, 'coursename') === 0)) {
                $map['course_name'] = $index;
            } elseif ($isVenue || strpos($label, 'trainingvenue') !== false) {
                $map['venue_address'] = $index;
            } elseif (in_array($label, ['nameofinstitute', 'trainngpartnername', 'trainingpartnername', 'trainingpartner', 'partnername', 'institutename', 'institute'], true) || strpos($label, 'trainingpartner') !== false || strpos($label, 'trainngpartner') !== false) {
                $map['partner_name'] = $index;
            } elseif (in_array($label, ['coursestartdate', 'coursestartingdate', 'startdate', 'startingdate', 'coursestart', 'batchstartdate', 'fromdate'], true)) {
                $map['course_start'] = $index;
            } elseif (strpos($label, 'exam') !== false) {
                $map['exam_date'] = $index;
            } elseif (in_array($label, ['courseenddate', 'courseendingdate', 'enddate', 'endingdate', 'courseend', 'batchenddate', 'todate'], true)) {
                $map['course_end'] = $index;
            } elseif ($label === 'batchno' || $label === 'batchnumber' || $label === 'batch') {
                $map['batch_no'] = $index;
            } elseif (strpos($label, 'nielitreg') !== false || strpos($label, 'nielitregistration') !== false) {
                $map['nielit_reg_no'] = $index;
            } elseif (in_array($label, ['coursecategory', 'categorycourse', 'trainingcategory', 'schemecategory'], true) || strpos($label, 'coursecat') !== false) {
                $map['course_category'] = $index;
            } elseif (strpos($label, 'category') === 0) {
                $map['category'] = $index;
            } elseif (strpos($label, 'gender') === 0) {
                $map['gender'] = $index;
            } elseif (strpos($label, 'aadhaar') !== false || strpos($label, 'aadhar') !== false || $label === 'uid') {
                $map['aadhaar'] = $index;
            } elseif (strpos($label, 'apaar') !== false) {
                $map['apaar_id'] = $index;
            } elseif (strpos($label, 'religion') === 0) {
                $map['religion'] = $index;
            } elseif (strpos($label, 'communication') !== false || strpos($label, 'address') === 0) {
                $map['communication_address'] = $index;
            } elseif ($isEmployed) {
                $map['currently_employed'] = $index;
            } elseif (strpos($label, 'hqaccr') !== false || strpos($label, 'hqacc') !== false || strpos($label, 'accreditation') !== false) {
                $map['hq_accr_number'] = $index;
            } elseif ($label === 'district' || $label === 'districtname' || strpos($label, 'district') === 0) {
                $map['district'] = $index;
            } elseif ($label === 'state' || $label === 'statename') {
                $map['state'] = $index;
            }
        }
        return $map['name'] === null ? null : $map;
    }

    function training_partner_rows_from_matrix(array $matrix): array
    {
        $records = [];
        if ($matrix === []) {
            return $records;
        }
        $headerIndex = null;
        $map = null;
        $scanUntil = min(count($matrix), 25);
        for ($rowIndex = 0; $rowIndex < $scanUntil; $rowIndex++) {
            $labels = array_map('training_partner_header_label', $matrix[$rowIndex]);
            $found = training_partner_header_map($labels);
            if ($found !== null) {
                $headerIndex = $rowIndex;
                $map = $found;
                break;
            }
        }
        if ($headerIndex === null || $map === null) {
            return $records;
        }
        $detailKeys = array_keys(training_partner_detail_fields());
        for ($i = $headerIndex + 1; $i < count($matrix); $i++) {
            $row = $matrix[$i];
            $name = trim((string) ($row[$map['name']] ?? ''));
            if ($name === '' || strcasecmp($name, 'name of candidate') === 0 || strcasecmp($name, 'student name') === 0) {
                continue;
            }
            if (preg_match('/^\d+$/', $name) && trim((string) ($row[$map['father_name'] ?? -1] ?? '')) === '' && trim((string) ($row[$map['course_name'] ?? -1] ?? '')) === '') {
                continue;
            }
            $details = ['_present' => []];
            foreach ($detailKeys as $field) {
                $column = $map[$field] ?? null;
                if ($column === null) {
                    $details[$field] = $field === 'currently_employed' ? 0 : '';
                    continue;
                }
                $details['_present'][$field] = true;
                $cell = trim((string) ($row[$column] ?? ''));
                $details[$field] = $field === 'currently_employed' ? training_partner_sheet_flag($cell) : $cell;
            }
            $records[] = [
                'name' => $name,
                'mobile' => $map['mobile'] === null ? '' : trim((string) ($row[$map['mobile']] ?? '')),
                'course_name' => $map['course_name'] === null ? '' : trim((string) ($row[$map['course_name']] ?? '')),
                'course_category' => $map['course_category'] === null ? '' : trim((string) ($row[$map['course_category']] ?? '')),
                'partner_name' => $map['partner_name'] === null ? '' : trim((string) ($row[$map['partner_name']] ?? '')),
                'course_start' => $map['course_start'] === null ? null : training_partner_parse_date($row[$map['course_start']] ?? ''),
                'course_end' => $map['course_end'] === null ? null : training_partner_parse_date($row[$map['course_end']] ?? ''),
                'exam_date' => $map['exam_date'] === null ? null : training_partner_parse_date($row[$map['exam_date']] ?? ''),
                'has_exam_date' => $map['exam_date'] !== null,
                'certified' => $map['certified'] === null ? 0 : training_partner_sheet_flag($row[$map['certified']] ?? ''),
                'placed' => $map['placed'] === null ? 0 : training_partner_sheet_flag($row[$map['placed']] ?? ''),
                'details' => $details,
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
            $matrix[] = array_map('training_partner_plain_number', $row);
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
        if ($value === '') {
            return $value;
        }
        if (preg_match('/^[+-]?\d+(?:\.\d+)?(?:[eE][+-]?\d+)$/', $value)) {
            $num = (float) $value;
            return number_format($num, 0, '', '');
        }
        if (!is_numeric($value)) {
            return $value;
        }
        $number = (float) $value;
        if (abs($number) >= 1000000000) {
            return number_format($number, 0, '', '');
        }
        if (floor($number) == $number && abs($number) < PHP_INT_MAX) {
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

    function training_partner_bulk_update_scheme_dates(mysqli $conn, int $schemeId, ?string $courseStart, ?string $courseEnd, ?string $examDate = null): array
    {
        if ($schemeId <= 0) {
            return ['ok' => false, 'error' => 'Select a valid scheme / project.'];
        }
        $figureMonth = training_partner_figure_month_from_course($courseStart, $courseEnd);
        $hasExam = ($examDate !== null && $examDate !== '');
        $sql = "UPDATE training_partner_students 
                SET course_start_date = ?, course_end_date = ?, figure_month = ?" .
                ($hasExam ? ", exam_date = ?" : "") .
                " WHERE scheme_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => $conn->error];
        }
        if ($hasExam) {
            $stmt->bind_param('ssssi', $courseStart, $courseEnd, $figureMonth, $examDate, $schemeId);
        } else {
            $stmt->bind_param('sssi', $courseStart, $courseEnd, $figureMonth, $schemeId);
        }
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return ['ok' => true, 'updated' => $affected];
    }

    function training_partner_import_students(mysqli $conn, string $path, string $originalName, int $partnerId, int $schemeId, ?string $courseStart = null, ?string $courseEnd = null, ?string $examDate = null, ?string $defaultCourseCategory = null, bool $overrideSheetDates = false): array
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);
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
        $defaultExam = training_partner_parse_date($examDate);
        $batchId = 'batch_' . gmdate('Ymd_His') . '_' . substr(md5($originalName . microtime(true)), 0, 6);
        $fileName = basename($originalName);
        $saved = 0;
        $inserted = 0;
        $updated = 0;
        $duplicatesInSheet = 0;
        $skipped = 0;
        $partnersCreated = 0;
        $partnerIds = [];
        $savedIds = [];
        $uniqueSavedMap = [];
        $duplicateDetails = [];
        $conn->begin_transaction();
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
            $rowCourseCategory = trim((string) ($record['course_category'] ?? ''));
            if ($rowCourseCategory === '' && $defaultCourseCategory !== null && $defaultCourseCategory !== '') {
                $rowCourseCategory = trim($defaultCourseCategory);
            }
            $result = training_partner_save_student(
                $conn,
                $rowPartnerId,
                $schemeId,
                $record['name'],
                $record['mobile'],
                (int) $record['certified'],
                (int) $record['placed'],
                (string) ($record['course_name'] ?? ''),
                ($overrideSheetDates && $defaultStart !== null) ? $defaultStart : ($record['course_start'] ?? $defaultStart),
                ($overrideSheetDates && $defaultEnd !== null) ? $defaultEnd : ($record['course_end'] ?? $defaultEnd),
                is_array($record['details'] ?? null) ? $record['details'] : [],
                ($overrideSheetDates && $defaultExam !== null) ? $defaultExam : (!empty($record['has_exam_date']) ? ($record['exam_date'] ?? $defaultExam) : $defaultExam),
                $overrideSheetDates || !empty($record['has_exam_date']) || $defaultExam !== null,
                $rowCourseCategory,
                $batchId,
                $fileName
            );
            if (!empty($result['ok'])) {
                $saved++;
                $stId = (int) ($result['id'] ?? 0);
                if ($stId > 0) {
                    if (!isset($uniqueSavedMap[$stId])) {
                        $uniqueSavedMap[$stId] = true;
                        $savedIds[] = $stId;
                        if (!empty($result['is_update'])) {
                            $updated++;
                        } else {
                            $inserted++;
                        }
                    } else {
                        $duplicatesInSheet++;
                        $duplicateDetails[] = [
                            'name' => (string) $record['name'],
                            'mobile' => (string) $record['mobile'],
                            'aadhaar' => (string) ($record['details']['aadhaar'] ?? ''),
                            'partner' => (string) ($record['partner_name'] ?? $partnerName),
                            'course' => (string) ($record['course_name'] ?? '')
                        ];
                    }
                }
            }
        }
        if ($saved === 0) {
            $conn->rollback();
            return ['ok' => false, 'error' => 'The sheet was read, but no student could be saved. Choose the scheme, and make sure each row has a training partner.', 'saved' => 0, 'skipped' => $skipped];
        }
        $conn->commit();
        return [
            'ok' => true,
            'total_rows' => count($records),
            'unique_count' => count($uniqueSavedMap),
            'saved' => count($uniqueSavedMap),
            'inserted' => $inserted,
            'updated' => $updated,
            'duplicates_in_sheet' => $duplicatesInSheet,
            'duplicate_list' => $duplicateDetails,
            'skipped' => $skipped,
            'partners_created' => $partnersCreated,
            'sheets' => count($sheets),
            'saved_ids' => $savedIds,
            'batch_id' => $batchId,
            'file_name' => $fileName
        ];
    }
}
