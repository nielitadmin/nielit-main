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

    function training_partner_save_figure(mysqli $conn, int $partnerId, string $month, int $enrolled, int $certified, int $placed): array
    {
        training_partner_ensure_schema($conn);
        if ($partnerId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            return ['ok' => false, 'error' => 'Choose a training partner and a month.'];
        }
        $figureMonth = $month . '-01';
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
                    'partner_name' => (string) $row['partner_name'],
                    'partner_code' => (string) $row['partner_code'],
                    'enrolled' => (int) $row['enrolled'],
                    'certified' => (int) $row['certified'],
                    'placed' => (int) $row['placed'],
                ];
            }
        }
        $stmt->close();
        return $rows;
    }
}
