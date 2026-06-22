<?php
/**
 * Batch completion certificates — upload from batch details, view in student portal.
 */

if (!function_exists('batch_certificate_column_exists')) {
    function batch_certificate_column_exists($conn, $column) {
        $column = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM batch_students LIKE '{$column}'");
        return $result && $result->num_rows > 0;
    }

    function batch_certificate_table_exists($conn, $table) {
        $table = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '{$table}'");
        return $result && $result->num_rows > 0;
    }

    function ensureBatchCertificateSchema($conn) {
        if (!batch_certificate_table_exists($conn, 'batch_students')) {
            return false;
        }

        $columns = [
            'certificate_file' => "VARCHAR(500) NULL DEFAULT NULL AFTER attendance_percentage",
            'certificate_number' => "VARCHAR(120) NULL DEFAULT NULL AFTER certificate_file",
            'certificate_uploaded_at' => "TIMESTAMP NULL DEFAULT NULL AFTER certificate_number",
            'certificate_uploaded_by' => "INT NULL DEFAULT NULL AFTER certificate_uploaded_at",
        ];

        foreach ($columns as $name => $definition) {
            if (!batch_certificate_column_exists($conn, $name)) {
                $conn->query("ALTER TABLE batch_students ADD COLUMN {$name} {$definition}");
            }
        }

        if (!batch_certificate_table_exists($conn, 'certificates')) {
            $conn->query("CREATE TABLE IF NOT EXISTS certificates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id VARCHAR(50) NOT NULL,
                student_record_id INT NULL,
                batch_id INT NULL,
                batch_student_id INT NULL,
                certificate_name VARCHAR(255) NOT NULL DEFAULT 'Course Completion Certificate',
                course_name VARCHAR(255) NULL,
                certificate_type VARCHAR(100) DEFAULT 'course_completion',
                certificate_number VARCHAR(120) NULL,
                file_path VARCHAR(500) NOT NULL,
                issue_date DATE NULL,
                status VARCHAR(50) DEFAULT 'issued',
                uploaded_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_student_id (student_id),
                INDEX idx_batch_id (batch_id),
                INDEX idx_batch_student (batch_student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $optional = [
                'student_record_id' => 'INT NULL DEFAULT NULL AFTER student_id',
                'batch_id' => 'INT NULL DEFAULT NULL AFTER student_record_id',
                'batch_student_id' => 'INT NULL DEFAULT NULL AFTER batch_id',
                'certificate_name' => "VARCHAR(255) NULL DEFAULT 'Course Completion Certificate' AFTER batch_student_id",
                'course_name' => 'VARCHAR(255) NULL DEFAULT NULL AFTER certificate_name',
                'file_path' => 'VARCHAR(500) NULL DEFAULT NULL AFTER certificate_number',
                'uploaded_by' => 'INT NULL DEFAULT NULL AFTER status',
            ];
            foreach ($optional as $name => $definition) {
                $check = $conn->query("SHOW COLUMNS FROM certificates LIKE '{$name}'");
                if ($check && $check->num_rows === 0) {
                    $conn->query("ALTER TABLE certificates ADD COLUMN {$name} {$definition}");
                }
            }
        }

        $certDir = dirname(__DIR__, 2) . '/uploads/batch_certificates';
        if (!is_dir($certDir)) {
            mkdir($certDir, 0755, true);
        }

        return true;
    }

    function isBatchCertificateUploadAllowed(array $batch) {
        $status = strtolower(trim((string) ($batch['status'] ?? '')));
        $isCompleted = ($status === 'completed');
        $isLocked = !empty($batch['is_locked']);
        return $isCompleted && $isLocked;
    }

    function batch_certificate_upload_reason(array $batch) {
        if (isBatchCertificateUploadAllowed($batch)) {
            return '';
        }
        $status = strtolower(trim((string) ($batch['status'] ?? '')));
        if ($status !== 'completed') {
            return 'Batch status must be Completed before uploading certificates.';
        }
        if (empty($batch['is_locked'])) {
            return 'Batch must be locked before uploading certificates.';
        }
        return 'Certificate upload is not available for this batch yet.';
    }

    function batch_certificate_generate_number(array $batch, array $student) {
        $batchCode = preg_replace('/[^A-Za-z0-9]+/', '', (string) ($batch['batch_code'] ?? 'BATCH'));
        $studentCode = preg_replace('/[^A-Za-z0-9]+/', '', (string) ($student['student_id'] ?? ('SR' . ($student['id'] ?? ''))));
        $year = date('Y');
        return strtoupper("NIELIT/{$batchCode}/{$studentCode}/{$year}");
    }

    function batch_certificate_get_batch_student_row($conn, $batch_id, $student_record_id) {
        $batch_id = (int) $batch_id;
        $student_record_id = (int) $student_record_id;
        if ($batch_id <= 0 || $student_record_id <= 0) {
            return null;
        }

        $sql = "SELECT bs.id AS batch_student_id,
                       bs.certificate_file,
                       bs.certificate_number,
                       bs.certificate_uploaded_at,
                       bs.certificate_uploaded_by,
                       s.id AS student_record_id,
                       s.student_id,
                       s.name,
                       s.course_id
                FROM students s
                LEFT JOIN batch_students bs ON bs.batch_id = ? AND (bs.student_record_id = s.id OR bs.student_id = s.id)
                WHERE s.id = ?
                AND (
                    s.batch_id = ?
                    OR bs.id IS NOT NULL
                )
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('iii', $batch_id, $student_record_id, $batch_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    function batch_certificate_sync_portal_record($conn, array $payload) {
        if (!batch_certificate_table_exists($conn, 'certificates')) {
            return;
        }

        $existingId = null;
        if (!empty($payload['batch_student_id'])) {
            $stmt = $conn->prepare('SELECT id FROM certificates WHERE batch_student_id = ? LIMIT 1');
            if ($stmt) {
                $bsId = (int) $payload['batch_student_id'];
                $stmt->bind_param('i', $bsId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $existingId = $row['id'] ?? null;
            }
        }

        if ($existingId) {
            $stmt = $conn->prepare("UPDATE certificates SET
                student_id = ?, student_record_id = ?, batch_id = ?, batch_student_id = ?,
                certificate_name = ?, course_name = ?, certificate_number = ?, file_path = ?,
                issue_date = CURDATE(), status = 'issued', uploaded_by = ?
                WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param(
                    'siiissssii',
                    $payload['student_id'],
                    $payload['student_record_id'],
                    $payload['batch_id'],
                    $payload['batch_student_id'],
                    $payload['certificate_name'],
                    $payload['course_name'],
                    $payload['certificate_number'],
                    $payload['file_path'],
                    $payload['uploaded_by'],
                    $existingId
                );
                $stmt->execute();
                $stmt->close();
            }
            return;
        }

        $stmt = $conn->prepare("INSERT INTO certificates
            (student_id, student_record_id, batch_id, batch_student_id, certificate_name, course_name,
             certificate_type, certificate_number, file_path, issue_date, status, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, 'course_completion', ?, ?, CURDATE(), 'issued', ?)");
        if ($stmt) {
            $stmt->bind_param(
                'siiissssi',
                $payload['student_id'],
                $payload['student_record_id'],
                $payload['batch_id'],
                $payload['batch_student_id'],
                $payload['certificate_name'],
                $payload['course_name'],
                $payload['certificate_number'],
                $payload['file_path'],
                $payload['uploaded_by']
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    function uploadBatchStudentCertificate($conn, $batch_id, $student_record_id, array $file, $admin_id) {
        ensureBatchCertificateSchema($conn);

        require_once __DIR__ . '/batch_functions.php';

        $batch = getBatchById((int) $batch_id, $conn);
        if (!$batch) {
            return ['success' => false, 'message' => 'Batch not found.'];
        }

        if (!isBatchCertificateUploadAllowed($batch)) {
            return ['success' => false, 'message' => batch_certificate_upload_reason($batch)];
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Certificate file upload failed.'];
        }

        $studentRow = batch_certificate_get_batch_student_row($conn, (int) $batch_id, (int) $student_record_id);
        if (!$studentRow) {
            return ['success' => false, 'message' => 'Student is not enrolled in this batch.'];
        }

        $allowedMimes = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset($allowedMimes[$mime])) {
            return ['success' => false, 'message' => 'Only PDF, JPG, and PNG files are allowed.'];
        }

        $maxSize = ($mime === 'application/pdf') ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxSize) {
            return ['success' => false, 'message' => 'File is too large. Max 10MB for PDF or 5MB for images.'];
        }

        $batchStudentId = (int) ($studentRow['batch_student_id'] ?? 0);
        if ($batchStudentId <= 0) {
            repairBatchStudentsJunction($conn, (int) $batch_id);
            $studentRow = batch_certificate_get_batch_student_row($conn, (int) $batch_id, (int) $student_record_id);
            $batchStudentId = (int) ($studentRow['batch_student_id'] ?? 0);
        }

        if ($batchStudentId <= 0) {
            return ['success' => false, 'message' => 'Could not locate batch enrollment record for this student.'];
        }

        $ext = $allowedMimes[$mime];
        $safeStudent = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($studentRow['student_id'] ?? $student_record_id));
        $filename = 'cert_batch' . (int) $batch_id . '_' . $safeStudent . '_' . time() . '.' . $ext;
        $relativeDir = 'uploads/batch_certificates/batch_' . (int) $batch_id;
        $absoluteDir = dirname(__DIR__, 2) . '/' . $relativeDir;

        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $relativePath = $relativeDir . '/' . $filename;
        $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            return ['success' => false, 'message' => 'Failed to save certificate file.'];
        }

        $oldFile = $studentRow['certificate_file'] ?? '';
        $certificateNumber = trim((string) ($studentRow['certificate_number'] ?? ''));
        if ($certificateNumber === '') {
            $certificateNumber = batch_certificate_generate_number($batch, $studentRow);
        }

        $update = $conn->prepare("UPDATE batch_students SET
            certificate_file = ?,
            certificate_number = ?,
            certificate_uploaded_at = NOW(),
            certificate_uploaded_by = ?
            WHERE id = ?");
        if (!$update) {
            @unlink($absolutePath);
            return ['success' => false, 'message' => 'Database error while saving certificate.'];
        }
        $update->bind_param('ssii', $relativePath, $certificateNumber, $admin_id, $batchStudentId);
        $ok = $update->execute();
        $update->close();

        if (!$ok) {
            @unlink($absolutePath);
            return ['success' => false, 'message' => 'Failed to update certificate record.'];
        }

        if ($oldFile && $oldFile !== $relativePath) {
            $oldAbsolute = dirname(__DIR__, 2) . '/' . ltrim($oldFile, '/');
            if (is_file($oldAbsolute)) {
                @unlink($oldAbsolute);
            }
        }

        $certificateName = trim((string) ($batch['course_name'] ?? 'Course')) . ' — Completion Certificate';
        batch_certificate_sync_portal_record($conn, [
            'student_id' => (string) $studentRow['student_id'],
            'student_record_id' => (int) $student_record_id,
            'batch_id' => (int) $batch_id,
            'batch_student_id' => $batchStudentId,
            'certificate_name' => $certificateName,
            'course_name' => (string) ($batch['course_name'] ?? ''),
            'certificate_number' => $certificateNumber,
            'file_path' => $relativePath,
            'uploaded_by' => (int) $admin_id,
        ]);

        return [
            'success' => true,
            'message' => 'Certificate uploaded successfully. The student can view it in their portal.',
            'certificate_number' => $certificateNumber,
            'file_path' => $relativePath,
        ];
    }

    function getStudentPortalCertificates($conn, $student_id) {
        ensureBatchCertificateSchema($conn);
        $rows = [];

        if (!batch_certificate_table_exists($conn, 'certificates')) {
            return $rows;
        }

        $sql = "SELECT c.*, b.batch_name, b.batch_code
                FROM certificates c
                LEFT JOIN batches b ON b.id = c.batch_id
                WHERE c.student_id = ?
                AND c.status = 'issued'
                AND c.file_path IS NOT NULL
                AND c.file_path != ''
                ORDER BY COALESCE(c.issue_date, c.created_at) DESC, c.id DESC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $rows;
        }
        $stmt->bind_param('s', $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    function getCertificateForStudent($conn, $certificate_id, $student_id) {
        $certificate_id = (int) $certificate_id;
        if ($certificate_id <= 0 || $student_id === '') {
            return null;
        }

        $stmt = $conn->prepare("SELECT * FROM certificates WHERE id = ? AND student_id = ? AND status = 'issued' LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $certificate_id, $student_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    function batch_certificate_absolute_path($relativePath) {
        $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
        $base = realpath(dirname(__DIR__, 2));
        $full = realpath(dirname(__DIR__, 2) . '/' . $relativePath);
        if (!$base || !$full || strpos($full, $base) !== 0 || !is_file($full)) {
            return '';
        }
        return $full;
    }
}
