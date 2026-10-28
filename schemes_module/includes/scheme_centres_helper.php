<?php

if (!function_exists('scheme_centres_ensure')) {
    function scheme_centres_ensure(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $conn->query("CREATE TABLE IF NOT EXISTS scheme_centres (
            scheme_id INT NOT NULL,
            centre_id INT NOT NULL,
            PRIMARY KEY (scheme_id, centre_id),
            KEY idx_scheme_centre_centre (centre_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    function scheme_centres_map(mysqli $conn): array
    {
        scheme_centres_ensure($conn);
        $map = [];
        $result = $conn->query('SELECT scheme_id, centre_id FROM scheme_centres');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $map[(int) $row['scheme_id']][] = (int) $row['centre_id'];
            }
        }
        return $map;
    }

    function scheme_centres_for_scheme(mysqli $conn, int $schemeId): array
    {
        scheme_centres_ensure($conn);
        $ids = [];
        $stmt = $conn->prepare('SELECT centre_id FROM scheme_centres WHERE scheme_id = ?');
        if (!$stmt) {
            return $ids;
        }
        $stmt->bind_param('i', $schemeId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $ids[] = (int) $row['centre_id'];
            }
        }
        $stmt->close();
        return $ids;
    }

    function scheme_centres_save(mysqli $conn, int $schemeId, array $centreIds): bool
    {
        scheme_centres_ensure($conn);
        if ($schemeId <= 0) {
            return false;
        }
        $clean = [];
        foreach ($centreIds as $centreId) {
            $centreId = (int) $centreId;
            if ($centreId > 0) {
                $clean[$centreId] = $centreId;
            }
        }
        $conn->begin_transaction();
        $delete = $conn->prepare('DELETE FROM scheme_centres WHERE scheme_id = ?');
        if (!$delete) {
            $conn->rollback();
            return false;
        }
        $delete->bind_param('i', $schemeId);
        $ok = $delete->execute();
        $delete->close();
        if (!$ok) {
            $conn->rollback();
            return false;
        }
        if ($clean !== []) {
            $insert = $conn->prepare('INSERT INTO scheme_centres (scheme_id, centre_id) VALUES (?, ?)');
            if (!$insert) {
                $conn->rollback();
                return false;
            }
            foreach ($clean as $centreId) {
                $insert->bind_param('ii', $schemeId, $centreId);
                if (!$insert->execute()) {
                    $insert->close();
                    $conn->rollback();
                    return false;
                }
            }
            $insert->close();
        }
        $conn->commit();
        return true;
    }
}
