<?php
/**
 * Shared student document checklist, validation, and storage helpers.
 * Reused by student/upload_documents.php and api/saarthi_upload_documents.php
 * using the exact table columns and upload directories of submit_registration.php.
 */

require_once __DIR__ . '/course_required_documents_helper.php';
require_once __DIR__ . '/multi_course_helper.php';

if (!function_exists('studentDocumentSlotCatalog')) {
    /**
     * Catalog of all self-service uploadable document slots mapped to students columns and directories.
     *
     * @return array<string, array<string, string>>
     */
    function studentDocumentSlotCatalog(): array
    {
        return [
            'passport_photo' => [
                'label' => 'Passport Photo',
                'column' => 'passport_photo',
                'subdir' => 'students',
                'kind' => 'passport',
                'accept' => '.jpg,.jpeg,.png,image/jpeg,image/png',
                'hint' => 'JPG or PNG photo (max 5 MB)',
            ],
            'signature' => [
                'label' => 'Signature',
                'column' => 'signature',
                'subdir' => 'students',
                'kind' => 'signature',
                'accept' => '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf',
                'hint' => 'Signature on white paper — JPG, PNG or PDF (max 2 MB)',
            ],
            'aadhar_card' => [
                'label' => 'Aadhar Card Document',
                'column' => 'aadhar_card_doc',
                'subdir' => 'aadhar',
                'kind' => 'document',
                'accept' => '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf',
                'hint' => 'Front/back PDF or clear JPG/PNG scan',
            ],
            'tenth_marksheet' => [
                'label' => '10th Certificate / Marksheet',
                'column' => 'tenth_marksheet_doc',
                'subdir' => 'marksheets/10th',
                'kind' => 'document',
                'accept' => '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf',
                'hint' => 'JPG, PNG or PDF',
            ],
            'twelfth_certificate' => [
                'label' => '12th Certificate / Diploma',
                'column' => 'twelfth_certificate_doc',
                'subdir' => 'marksheets/12th/certificate',
                'kind' => 'document',
                'accept' => '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf',
                'hint' => 'JPG, PNG or PDF',
            ],
            'twelfth_marksheet' => [
                'label' => '12th Marksheet / Diploma',
                'column' => 'twelfth_marksheet_doc',
                'subdir' => 'marksheets/12th',
                'kind' => 'document',
                'accept' => '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf',
                'hint' => 'JPG, PNG or PDF',
            ],
            'graduation_certificate' => [
                'label' => 'Graduation Certificate',
                'column' => 'graduation_certificate_doc',
                'subdir' => 'marksheets/graduation',
                'kind' => 'document',
                'accept' => '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf',
                'hint' => 'JPG, PNG or PDF',
            ],
            'caste_certificate' => [
                'label' => 'Caste Certificate',
                'column' => 'caste_certificate_doc',
                'subdir' => 'caste_certificates',
                'kind' => 'document',
                'accept' => '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf',
                'hint' => 'JPG, PNG or PDF (if applicable)',
            ],
            'left_thumb_impression' => [
                'label' => 'Left Thumb Impression',
                'column' => 'left_thumb_impression',
                'subdir' => 'students/thumb_impression',
                'kind' => 'signature',
                'accept' => '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf',
                'hint' => 'JPG, PNG or PDF (max 2 MB)',
            ],
            'bank_passbook' => [
                'label' => 'Bank Passbook',
                'column' => 'bank_passbook_doc',
                'subdir' => 'dge/bank_passbook',
                'kind' => 'document',
                'accept' => '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf',
                'hint' => 'JPG, PNG or PDF',
            ],
            'income_certificate' => [
                'label' => 'Income Certificate',
                'column' => 'income_certificate_doc',
                'subdir' => 'dge/income_certificate',
                'kind' => 'document',
                'accept' => '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf',
                'hint' => 'JPG, PNG or PDF',
            ],
            'aadhaar_bank_seeding_proof' => [
                'label' => 'Aadhaar Bank Seeding Proof',
                'column' => 'aadhaar_bank_seeding_doc',
                'subdir' => 'dge/aadhaar_bank_seeding',
                'kind' => 'document',
                'accept' => '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf',
                'hint' => 'JPG, PNG or PDF',
            ],
            'other_documents' => [
                'label' => 'Other Supporting Documents',
                'column' => 'other_documents_doc',
                'subdir' => 'other',
                'kind' => 'document',
                'accept' => '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf',
                'hint' => 'JPG, PNG or PDF',
            ],
        ];
    }
}

if (!function_exists('studentDocumentValidateFile')) {
    /**
     * Validate uploaded student image/PDF against MIME type, extension, size, and payload checks.
     *
     * @return array{valid: bool, message?: string, ext?: string}
     */
    function studentDocumentValidateFile(array $file, string $slotKey): array
    {
        $catalog = studentDocumentSlotCatalog();
        $meta = $catalog[$slotKey] ?? null;
        if (!$meta) {
            return ['valid' => false, 'message' => 'Unknown document type.'];
        }

        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $codes = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit.',
                UPLOAD_ERR_PARTIAL => 'Partial upload received.',
                UPLOAD_ERR_NO_FILE => 'No file selected.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary directory.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file.',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by extension.',
            ];
            return ['valid' => false, 'message' => $codes[$err] ?? ('Upload error code ' . $err)];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            return ['valid' => false, 'message' => 'Uploaded temporary file missing.'];
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $kind = $meta['kind'];

        if ($kind === 'passport') {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            $allowedExts = ['jpg', 'jpeg', 'png'];
        } else {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
        }

        if (!in_array($ext, $allowedExts, true)) {
            return ['valid' => false, 'message' => 'Allowed formats: ' . strtoupper(implode(', ', $allowedExts))];
        }

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (string) @finfo_file($finfo, $tmpName) : '';
            if ($finfo) {
                @finfo_close($finfo);
            }
            if ($mime !== '' && !in_array($mime, $allowedTypes, true)) {
                return ['valid' => false, 'message' => 'Invalid file MIME type: ' . $mime];
            }
        }

        $size = (int) ($file['size'] ?? 0);
        if ($kind === 'signature') {
            $maxBytes = 2 * 1024 * 1024;
        } else {
            $maxBytes = ($ext === 'pdf') ? (10 * 1024 * 1024) : (5 * 1024 * 1024);
        }
        if ($size <= 0 || $size > $maxBytes) {
            $maxMb = (int) round($maxBytes / (1024 * 1024));
            return ['valid' => false, 'message' => "File must be smaller than {$maxMb} MB."];
        }

        $head = @file_get_contents($tmpName, false, null, 0, 1024);
        if (is_string($head) && (stripos($head, '<?php') !== false || strpos($head, '#!/') !== false)) {
            return ['valid' => false, 'message' => 'Invalid or unsafe file content.'];
        }

        return ['valid' => true, 'ext' => $ext];
    }
}

if (!function_exists('studentDocumentChecklist')) {
    /**
     * Build ordered checklist of upload slots for a student enrollment row.
     *
     * @return list<array<string, mixed>>
     */
    function studentDocumentChecklist(mysqli $conn, array $studentRow): array
    {
        $catalog = studentDocumentSlotCatalog();
        $courseId = (int) ($studentRow['course_id'] ?? 0);
        $requiredKeys = getCourseRequiredDocumentKeys($conn, $courseId > 0 ? $courseId : null);

        // Include core identity docs + course required docs + any already-uploaded optional docs
        $coreOrder = [
            'passport_photo',
            'signature',
            'aadhar_card',
            'tenth_marksheet',
            'twelfth_certificate',
            'twelfth_marksheet',
            'graduation_certificate',
            'caste_certificate',
            'other_documents',
        ];
        $allKeys = $coreOrder;
        foreach ($requiredKeys as $rk) {
            if (!in_array($rk, $allKeys, true)) {
                $allKeys[] = $rk;
            }
        }
        foreach ($catalog as $slotKey => $meta) {
            if (!empty($studentRow[$meta['column']]) && !in_array($slotKey, $allKeys, true)) {
                $allKeys[] = $slotKey;
            }
        }

        $items = [];
        foreach ($allKeys as $slotKey) {
            if (!isset($catalog[$slotKey])) {
                continue;
            }
            $meta = $catalog[$slotKey];
            $col = $meta['column'];
            $path = trim((string) ($studentRow[$col] ?? ''));
            $isRequired = in_array($slotKey, $requiredKeys, true);
            $items[] = [
                'key' => $slotKey,
                'label' => $meta['label'],
                'column' => $col,
                'accept' => $meta['accept'],
                'hint' => $meta['hint'],
                'required' => $isRequired,
                'uploaded' => $path !== '',
                'path' => $path,
            ];
        }

        return $items;
    }
}

if (!function_exists('saarthiIssueUploadToken')) {
    /**
     * Issue a 1-hour session-scoped document upload token for post-chat or guest-session upload.
     */
    function saarthiIssueUploadToken(int $recordId, string $studentId): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $token = bin2hex(random_bytes(16));
        if (!isset($_SESSION['saarthi_upload_tokens']) || !is_array($_SESSION['saarthi_upload_tokens'])) {
            $_SESSION['saarthi_upload_tokens'] = [];
        }
        $_SESSION['saarthi_upload_tokens'][$token] = [
            'record_id' => $recordId,
            'student_id' => $studentId,
            'expires' => time() + 3600,
        ];
        if (count($_SESSION['saarthi_upload_tokens']) > 10) {
            $_SESSION['saarthi_upload_tokens'] = array_slice($_SESSION['saarthi_upload_tokens'], -10, null, true);
        }
        return $token;
    }
}

if (!function_exists('saarthiBuildUploadPortalUrl')) {
    function saarthiBuildUploadPortalUrl(int $recordId, string $studentId, ?string $token = null): string
    {
        $tok = $token ?: saarthiIssueUploadToken($recordId, $studentId);
        if (!function_exists('app_url')) {
            require_once __DIR__ . '/url_helper.php';
        }
        return app_url('student/upload_documents.php') . '?token=' . rawurlencode($tok) . '&record_id=' . $recordId;
    }
}

if (!function_exists('saarthiBuildUploadApiUrl')) {
    function saarthiBuildUploadApiUrl(int $recordId, string $studentId, ?string $token = null): string
    {
        $tok = $token ?: saarthiIssueUploadToken($recordId, $studentId);
        if (!function_exists('app_url')) {
            require_once __DIR__ . '/url_helper.php';
        }
        return app_url('api/saarthi_upload_documents.php') . '?token=' . rawurlencode($tok) . '&record_id=' . $recordId;
    }
}

if (!function_exists('studentDocumentAuthorizeAccess')) {
    /**
     * Resolve and authorize the target students row via logged-in student, admin, or valid Saarthi session token.
     *
     * @return array<string, mixed>|null
     */
    function studentDocumentAuthorizeAccess(mysqli $conn, int $recordId = 0, string $token = ''): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $isAdmin = !empty($_SESSION['admin']);
        $sessionStudentId = trim((string) ($_SESSION['student_id'] ?? ''));

        if ($token !== '' && !empty($_SESSION['saarthi_upload_tokens'][$token])) {
            $entry = $_SESSION['saarthi_upload_tokens'][$token];
            if ((int) ($entry['expires'] ?? 0) >= time()) {
                $tokRecordId = (int) ($entry['record_id'] ?? 0);
                if ($tokRecordId > 0 && ($recordId === 0 || $recordId === $tokRecordId)) {
                    $stmt = $conn->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
                    if ($stmt) {
                        $stmt->bind_param('i', $tokRecordId);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if ($row) {
                            return $row;
                        }
                    }
                }
            }
        }

        if ($isAdmin && $recordId > 0) {
            $stmt = $conn->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $recordId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                return $row ?: null;
            }
        }

        if ($sessionStudentId !== '') {
            if ($recordId > 0) {
                $stmt = $conn->prepare('SELECT * FROM students WHERE id = ? AND student_id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('is', $recordId, $sessionStudentId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($row) {
                        return $row;
                    }
                }
            }
            $stmt = $conn->prepare('SELECT * FROM students WHERE student_id = ? ORDER BY id DESC LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $sessionStudentId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                return $row ?: null;
            }
        }

        return null;
    }
}

if (!function_exists('studentDocumentSaveUploads')) {
    /**
     * Validate and save uploaded student documents + optional Aadhar number to disk and MySQL.
     *
     * @param array<string, mixed> $studentRow
     * @param array<string, array<string, mixed>> $files Typically $_FILES
     * @param array<string, mixed> $postData Optional fields (e.g., aadhar)
     * @return array<string, mixed>
     */
    function studentDocumentSaveUploads(mysqli $conn, array $studentRow, array $files, array $postData = []): array
    {
        ensureTwelfthCertificateDocColumn($conn);
        ensureDgeRegistrationDocumentColumns($conn);

        $recordId = (int) ($studentRow['id'] ?? 0);
        $studentId = trim((string) ($studentRow['student_id'] ?? ''));
        $courseId = (int) ($studentRow['course_id'] ?? 0);
        $schemeId = isset($studentRow['scheme_id']) && (int) $studentRow['scheme_id'] > 0 ? (int) $studentRow['scheme_id'] : null;

        if ($recordId <= 0 || $studentId === '') {
            return ['ok' => false, 'message' => 'Invalid student record.', 'errors' => ['Invalid student record.'], 'updated_fields' => []];
        }

        $catalog = studentDocumentSlotCatalog();
        $errors = [];
        $updatedCols = [];
        $updatedLabels = [];

        // Optional Aadhar number update (for Saarthi registrants who didn't provide Aadhar yet)
        if (array_key_exists('aadhar', $postData)) {
            $aadharClean = preg_replace('/\D/', '', (string) $postData['aadhar']);
            if ($aadharClean !== '') {
                if (strlen($aadharClean) !== 12) {
                    $errors[] = 'Aadhar number must be exactly 12 digits.';
                } else {
                    // Check duplicate Aadhar in other accounts/courses
                    $dupAcc = false;
                    $accCheck = @$conn->prepare('SELECT id, student_id FROM student_accounts WHERE aadhar = ? AND student_id != ? LIMIT 1');
                    if ($accCheck) {
                        $accCheck->bind_param('ss', $aadharClean, $studentId);
                        $accCheck->execute();
                        if ($accCheck->get_result()->num_rows > 0) {
                            $dupAcc = true;
                        }
                        $accCheck->close();
                    }
                    if ($dupAcc || ($courseId > 0 && isAadharEnrolledInCourseScheme($conn, $aadharClean, $courseId, $schemeId) && trim((string) ($studentRow['aadhar'] ?? '')) !== $aadharClean)) {
                        $errors[] = 'This Aadhar number is already registered with another student/enrollment.';
                    } else {
                        $updatedCols['aadhar'] = $aadharClean;
                        $updatedLabels[] = 'Aadhar Number';
                    }
                }
            }
        }

        // Validate & store files
        $rootDir = dirname(__DIR__);
        $safeId = str_replace(['/', '\\', ' '], '-', $studentId);
        $ts = time();
        $savedFiles = [];
        $idx = 0;

        foreach ($catalog as $slotKey => $meta) {
            if (!isset($files[$slotKey]) || !is_array($files[$slotKey])) {
                continue;
            }
            $file = $files[$slotKey];
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $check = studentDocumentValidateFile($file, $slotKey);
            if (empty($check['valid'])) {
                $errors[] = $meta['label'] . ': ' . ($check['message'] ?? 'Invalid file.');
                continue;
            }

            $relDir = 'student/uploads/' . $meta['subdir'] . '/';
            $absDir = $rootDir . '/' . $relDir;
            if (!is_dir($absDir) && !@mkdir($absDir, 0755, true)) {
                $errors[] = $meta['label'] . ': Unable to create upload directory.';
                continue;
            }

            $suffix = $slotKey === 'left_thumb_impression' ? 'thumb' : $slotKey;
            $filename = $safeId . '_' . ($ts + $idx) . '_' . $suffix . '.' . ($check['ext'] ?? 'bin');
            $idx++;
            $destAbs = $absDir . $filename;
            $moved = is_uploaded_file((string) $file['tmp_name'])
                ? @move_uploaded_file((string) $file['tmp_name'], $destAbs)
                : @rename((string) $file['tmp_name'], $destAbs);

            if (!$moved || !is_file($destAbs)) {
                $errors[] = $meta['label'] . ': Failed to save uploaded file.';
                continue;
            }

            $relPath = $relDir . $filename;
            $savedFiles[] = ['abs' => $destAbs, 'rel' => $relPath];
            $updatedCols[$meta['column']] = $relPath;
            $updatedLabels[] = $meta['label'];
        }

        if (!empty($errors) && empty($updatedCols)) {
            return [
                'ok' => false,
                'message' => implode(' ', $errors),
                'errors' => $errors,
                'updated_fields' => [],
            ];
        }

        if (empty($updatedCols)) {
            return [
                'ok' => false,
                'message' => 'Please choose at least one file (or enter your 12-digit Aadhar number) to upload.',
                'errors' => ['No files selected.'],
                'updated_fields' => [],
            ];
        }

        // Update students record
        $setParts = [];
        $types = '';
        $vals = [];
        foreach ($updatedCols as $col => $val) {
            $setParts[] = "`{$col}` = ?";
            $types .= 's';
            $vals[] = $val;
        }
        $types .= 'i';
        $vals[] = $recordId;

        $sql = 'UPDATE `students` SET ' . implode(', ', $setParts) . ' WHERE id = ? LIMIT 1';
        $upd = $conn->prepare($sql);
        if (!$upd) {
            foreach ($savedFiles as $sf) {
                @unlink($sf['abs']);
            }
            return ['ok' => false, 'message' => 'Database error: ' . $conn->error, 'errors' => [$conn->error], 'updated_fields' => []];
        }
        $upd->bind_param($types, ...$vals);
        if (!$upd->execute()) {
            $errMsg = $upd->error;
            $upd->close();
            foreach ($savedFiles as $sf) {
                @unlink($sf['abs']);
            }
            return ['ok' => false, 'message' => 'Database update failed: ' . $errMsg, 'errors' => [$errMsg], 'updated_fields' => []];
        }
        $upd->close();

        // Sync Aadhar to student_accounts if placeholder NOAADHAR/... was used
        if (!empty($updatedCols['aadhar']) && isMultiCourseSystemInstalled($conn)) {
            $accUpd = @$conn->prepare("UPDATE student_accounts SET aadhar = ? WHERE student_id = ? AND (aadhar IS NULL OR aadhar = '' OR aadhar LIKE 'NOAADHAR%')");
            if ($accUpd) {
                $accUpd->bind_param('ss', $updatedCols['aadhar'], $studentId);
                $accUpd->execute();
                $accUpd->close();
            }
        }

        // Fill empty identity fields across any sibling enrollment rows for the same student_id
        $syncCols = array_intersect_key($updatedCols, array_flip(['passport_photo', 'signature', 'aadhar', 'aadhar_card_doc']));
        foreach ($syncCols as $sCol => $sVal) {
            $sStmt = @$conn->prepare("UPDATE `students` SET `{$sCol}` = ? WHERE student_id = ? AND (`{$sCol}` IS NULL OR `{$sCol}` = '')");
            if ($sStmt) {
                $sStmt->bind_param('ss', $sVal, $studentId);
                $sStmt->execute();
                $sStmt->close();
            }
        }

        // Refresh row
        $freshStmt = $conn->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
        $freshRow = $studentRow;
        if ($freshStmt) {
            $freshStmt->bind_param('i', $recordId);
            $freshStmt->execute();
            $resRow = $freshStmt->get_result()->fetch_assoc();
            if ($resRow) {
                $freshRow = $resRow;
            }
            $freshStmt->close();
        }

        if (is_file(__DIR__ . '/activity_logger.php')) {
            require_once __DIR__ . '/activity_logger.php';
            if (function_exists('logActivity')) {
                logActivity($conn, [
                    'actor_type' => 'student',
                    'actor_id' => $studentId,
                    'actor_name' => (string) ($freshRow['name'] ?? $studentId),
                    'action' => 'student_upload_documents',
                    'entity_type' => 'student',
                    'entity_id' => $studentId,
                    'entity_name' => (string) ($freshRow['name'] ?? $studentId),
                    'description' => 'Student uploaded pending documents: ' . implode(', ', $updatedLabels),
                    'details' => ['updated_fields' => $updatedLabels, 'record_id' => $recordId],
                ]);
            }
        }

        return [
            'ok' => empty($errors),
            'partial' => !empty($errors) && !empty($updatedLabels),
            'message' => empty($errors)
                ? ('Uploaded successfully: ' . implode(', ', $updatedLabels) . '.')
                : ('Saved ' . implode(', ', $updatedLabels) . ', with issues: ' . implode(' ', $errors)),
            'errors' => $errors,
            'updated_fields' => $updatedLabels,
            'student' => $freshRow,
            'checklist' => studentDocumentChecklist($conn, $freshRow),
        ];
    }
}
