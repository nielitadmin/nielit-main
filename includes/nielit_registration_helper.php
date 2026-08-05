<?php
/**
 * NIELIT Portal Reg. No. — defaults to student_id when not set manually.
 */

if (!function_exists('ensureNielitRegistrationNoColumns')) {
    function ensureNielitRegistrationNoColumns(mysqli $conn): void
    {
        $studentCol = $conn->query("SHOW COLUMNS FROM students LIKE 'nielit_registration_no'");
        if (!$studentCol || $studentCol->num_rows === 0) {
            $conn->query('ALTER TABLE students ADD COLUMN nielit_registration_no VARCHAR(100) NULL DEFAULT NULL');
        }

        $checkTable = $conn->query("SHOW TABLES LIKE 'batch_students'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $batchCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'nielit_registration_no'");
            if (!$batchCol || $batchCol->num_rows === 0) {
                $conn->query('ALTER TABLE batch_students ADD COLUMN nielit_registration_no VARCHAR(100) NULL DEFAULT NULL');
            }
        }
    }
}

if (!function_exists('sqlNielitRegistrationNo')) {
    /** SQL expression: batch value → student value → student_id */
    function sqlNielitRegistrationNo(string $studentAlias = 's', ?string $batchAlias = null): string
    {
        $studentAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $studentAlias) ?: 's';
        if ($batchAlias !== null) {
            $batchAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $batchAlias) ?: 'bs';
            return "COALESCE(NULLIF(TRIM({$batchAlias}.nielit_registration_no), ''), NULLIF(TRIM({$studentAlias}.nielit_registration_no), ''), NULLIF(TRIM({$studentAlias}.student_id), '')) AS nielit_registration_no";
        }
        return "COALESCE(NULLIF(TRIM({$studentAlias}.nielit_registration_no), ''), NULLIF(TRIM({$studentAlias}.student_id), '')) AS nielit_registration_no";
    }
}

if (!function_exists('resolveNielitRegistrationNo')) {
    function resolveNielitRegistrationNo(array $student): string
    {
        $reg = trim((string) ($student['nielit_registration_no'] ?? ''));
        if ($reg !== '') {
            return $reg;
        }
        return trim((string) ($student['student_id'] ?? ''));
    }
}

if (!function_exists('syncNielitRegistrationNoDefault')) {
    /**
     * Persist student_id as NIELIT Portal Reg. No. when the field is empty.
     */
    function syncNielitRegistrationNoDefault(mysqli $conn, int $studentRecordId, ?int $batchId = null): bool
    {
        if ($studentRecordId <= 0) {
            return false;
        }

        ensureNielitRegistrationNoColumns($conn);

        $stmt = $conn->prepare('SELECT student_id, nielit_registration_no FROM students WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $studentRecordId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return false;
        }

        $existing = trim((string) ($row['nielit_registration_no'] ?? ''));
        if ($existing !== '') {
            return true;
        }

        $studentIdStr = trim((string) ($row['student_id'] ?? ''));
        if ($studentIdStr === '') {
            return false;
        }

        $upd = $conn->prepare(
            "UPDATE students SET nielit_registration_no = ?
             WHERE id = ? AND (nielit_registration_no IS NULL OR TRIM(nielit_registration_no) = '')"
        );
        if (!$upd) {
            return false;
        }
        $upd->bind_param('si', $studentIdStr, $studentRecordId);
        $upd->execute();
        $upd->close();

        if ($batchId !== null && $batchId > 0) {
            $hasRecordCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
            $useRecordCol = ($hasRecordCol && $hasRecordCol->num_rows > 0);

            if ($useRecordCol) {
                $bs = $conn->prepare(
                    "UPDATE batch_students SET nielit_registration_no = ?
                     WHERE batch_id = ? AND (student_record_id = ? OR student_id = ?)
                       AND (nielit_registration_no IS NULL OR TRIM(nielit_registration_no) = '')"
                );
                if ($bs) {
                    $bs->bind_param('siii', $studentIdStr, $batchId, $studentRecordId, $studentRecordId);
                    $bs->execute();
                    $bs->close();
                }
            } else {
                $bs = $conn->prepare(
                    "UPDATE batch_students SET nielit_registration_no = ?
                     WHERE batch_id = ? AND student_id = ?
                       AND (nielit_registration_no IS NULL OR TRIM(nielit_registration_no) = '')"
                );
                if ($bs) {
                    $bs->bind_param('sii', $studentIdStr, $batchId, $studentRecordId);
                    $bs->execute();
                    $bs->close();
                }
            }
        }

        return true;
    }
}
