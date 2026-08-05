<?php
/**
 * Batch Management Functions
 * NIELIT Bhubaneswar - Modular Batch System
 */

if (file_exists(__DIR__ . '/../../includes/nielit_registration_helper.php')) {
    require_once __DIR__ . '/../../includes/nielit_registration_helper.php';
}

/**
 * Ensure batches.batch_description column exists (optional notes about the batch).
 */
function ensureBatchDescriptionColumn(mysqli $conn): void
{
    $check = $conn->query("SHOW COLUMNS FROM batches LIKE 'batch_description'");
    if (!$check || $check->num_rows === 0) {
        $conn->query('ALTER TABLE batches ADD COLUMN batch_description TEXT NULL DEFAULT NULL AFTER batch_name');
    }
}

/**
 * Generate unique batch code
 */
function generateBatchCode($course_code, $conn) {
    $year = date('y');
    $base_code = strtoupper($course_code) . $year;
    
    // Find the next available number
    $sql = "SELECT batch_code FROM batches WHERE batch_code LIKE ? ORDER BY batch_code DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $search = $base_code . '%';
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_code = $row['batch_code'];
        // Extract number and increment
        preg_match('/\d+$/', $last_code, $matches);
        $next_num = isset($matches[0]) ? intval($matches[0]) + 1 : 1;
    } else {
        $next_num = 1;
    }
    
    $stmt->close();
    return $base_code . '_' . str_pad($next_num, 2, '0', STR_PAD_LEFT);
}

/**
 * Create new batch
 */
function createBatch($data, $conn) {
    ensureBatchDescriptionColumn($conn);

    $batchDescription = trim((string) ($data['batch_description'] ?? ''));
    $batchDescription = $batchDescription === '' ? null : $batchDescription;

    $sql = "INSERT INTO batches (course_id, batch_name, batch_description, batch_code, start_date, end_date, 
            training_fees, seats_total, batch_coordinator, status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    // 11 placeholders in INSERT: course_id(i), batch_name(s), batch_description(s), batch_code(s),
    // start_date(s), end_date(s), training_fees(d), seats_total(i), batch_coordinator(s),
    // status(s), created_by(i)
    $stmt->bind_param("isssssdissi", 
        $data['course_id'],
        $data['batch_name'],
        $batchDescription,
        $data['batch_code'],
        $data['start_date'],
        $data['end_date'],
        $data['training_fees'],
        $data['seats_total'],
        $data['batch_coordinator'],
        $data['status'],
        $data['created_by']
    );
    
    $result = $stmt->execute();
    $batch_id = $stmt->insert_id;
    $stmt->close();

    if ($result && $batch_id && file_exists(__DIR__ . '/../../includes/activity_logger.php')) {
        require_once __DIR__ . '/../../includes/activity_logger.php';
        $createdBy = $data['created_by'] ?? ($_SESSION['admin'] ?? 'Admin');
        logActivity($conn, [
            'actor_type' => 'admin',
            'actor_name' => is_numeric($createdBy) ? ($_SESSION['admin'] ?? (string) $createdBy) : (string) $createdBy,
            'action' => 'batch_create',
            'entity_type' => 'batch',
            'entity_id' => (string) $batch_id,
            'entity_name' => ($data['batch_name'] ?? '') . ' ' . ($data['batch_code'] ?? ''),
            'description' => 'Batch "' . ($data['batch_name'] ?? ('#' . $batch_id)) . '" created'
                . (!empty($data['batch_code']) ? ' (' . $data['batch_code'] . ')' : '')
                . (!empty($data['course_id']) ? ' for course #' . $data['course_id'] : '') . '.',
            'details' => [
                'course_id' => $data['course_id'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'status' => $data['status'] ?? null,
            ],
        ]);
    }
    
    return $result ? $batch_id : false;
}

/**
 * Update batch
 */
function updateBatch($batch_id, $data, $conn) {
    ensureBatchDescriptionColumn($conn);

    $batchDescription = trim((string) ($data['batch_description'] ?? ''));
    $batchDescription = $batchDescription === '' ? null : $batchDescription;

    $sql = "UPDATE batches SET 
            batch_name = ?,
            batch_description = ?,
            start_date = ?,
            end_date = ?,
            training_fees = ?,
            seats_total = ?,
            batch_coordinator = ?,
            status = ?,
            scheme_id = ?,
            admission_order_ref = ?,
            admission_order_date = ?,
            examination_month = ?,
            class_time = ?,
            copy_to_list = ?,
            location = ?
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssdisssssssssi",
        $data['batch_name'],
        $batchDescription,
        $data['start_date'],
        $data['end_date'],
        $data['training_fees'],
        $data['seats_total'],
        $data['batch_coordinator'],
        $data['status'],
        $data['scheme_id'],
        $data['admission_order_ref'],
        $data['admission_order_date'],
        $data['examination_month'],
        $data['class_time'],
        $data['copy_to_list'],
        $data['location'],
        $batch_id
    );
    
    $result = $stmt->execute();
    $stmt->close();

    if ($result && file_exists(__DIR__ . '/../../includes/activity_logger.php')) {
        require_once __DIR__ . '/../../includes/activity_logger.php';
        logActivity($conn, [
            'actor_type' => 'admin',
            'action' => 'batch_update',
            'entity_type' => 'batch',
            'entity_id' => (string) $batch_id,
            'entity_name' => $data['batch_name'] ?? ('Batch #' . $batch_id),
            'description' => 'Batch "' . ($data['batch_name'] ?? ('#' . $batch_id)) . '" was updated.',
            'details' => [
                'status' => $data['status'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'location' => $data['location'] ?? null,
            ],
        ]);
    }
    
    return $result;
}

/**
 * Delete batch
 */
function deleteBatch($batch_id, $conn) {
    // Check if batch has students
    $check_sql = "SELECT COUNT(*) as count FROM batch_students WHERE batch_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['count'] > 0) {
        return ['success' => false, 'message' => 'Cannot delete batch with enrolled students'];
    }

    $batchLabel = 'Batch #' . $batch_id;
    $info = $conn->prepare('SELECT batch_name, batch_code FROM batches WHERE id = ? LIMIT 1');
    if ($info) {
        $info->bind_param('i', $batch_id);
        $info->execute();
        $infoRow = $info->get_result()->fetch_assoc();
        $info->close();
        if ($infoRow) {
            $batchLabel = trim(($infoRow['batch_name'] ?? '') . ' ' . ($infoRow['batch_code'] ?? '')) ?: $batchLabel;
        }
    }
    
    $sql = "DELETE FROM batches WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $batch_id);
    $result = $stmt->execute();
    $stmt->close();

    if ($result && file_exists(__DIR__ . '/../../includes/activity_logger.php')) {
        require_once __DIR__ . '/../../includes/activity_logger.php';
        logActivity($conn, [
            'actor_type' => 'admin',
            'action' => 'batch_delete',
            'entity_type' => 'batch',
            'entity_id' => (string) $batch_id,
            'entity_name' => $batchLabel,
            'description' => 'Batch "' . $batchLabel . '" was deleted.',
        ]);
    }
    
    return ['success' => $result, 'message' => $result ? 'Batch deleted successfully' : 'Error deleting batch'];
}

/**
 * Get batch by ID
 */
function getBatchById($batch_id, $conn) {
    // Try with schemes table first
    $sql = "SELECT b.*, c.course_name, c.course_code, s.scheme_name, s.scheme_code
            FROM batches b 
            LEFT JOIN courses c ON b.course_id = c.id 
            LEFT JOIN schemes s ON b.scheme_id = s.id
            WHERE b.id = ?";
    $stmt = $conn->prepare($sql);
    
    // If schemes table doesn't exist, try without it
    if (!$stmt) {
        $sql = "SELECT b.*, c.course_name, c.course_code, NULL as scheme_name, NULL as scheme_code
                FROM batches b 
                LEFT JOIN courses c ON b.course_id = c.id 
                WHERE b.id = ?";
        $stmt = $conn->prepare($sql);
    }
    
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $batch = $result->fetch_assoc();
    $stmt->close();

    if ($batch) {
        $batch['seats_filled'] = getBatchEnrolledCount((int)$batch_id, $conn);
    }
    
    return $batch;
}

/**
 * Get all batches for a course
 */
function getBatchesByCourse($course_id, $conn) {
    $sql = "SELECT b.*, 
            (SELECT COUNT(*) FROM batch_students WHERE batch_id = b.id) as enrolled_count
            FROM batches b 
            WHERE b.course_id = ? 
            ORDER BY b.start_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $batches = [];
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row;
    }
    $stmt->close();
    
    return $batches;
}

/**
 * Get all active batches
 */
function getActiveBatches($conn) {
    $sql = "SELECT b.*, c.course_name, c.course_code,
            (SELECT COUNT(*) FROM batch_students WHERE batch_id = b.id) as enrolled_count
            FROM batches b 
            LEFT JOIN courses c ON b.course_id = c.id 
            WHERE b.status = 'Active' 
            ORDER BY b.start_date DESC";
    $result = $conn->query($sql);
    
    $batches = [];
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row;
    }
    
    return $batches;
}

/**
 * Approve student and assign to batch
 */
function approveStudent($student_id, $batch_id, $admin_name, $conn) {
    $helper = __DIR__ . '/../../includes/multi_course_helper.php';
    if (file_exists($helper)) {
        require_once $helper;
        if (function_exists('assignEnrollmentToBatch')) {
            return assignEnrollmentToBatch($conn, (int)$student_id, (int)$batch_id, $admin_name);
        }
    }

    $conn->begin_transaction();

    try {
        $check = $conn->prepare('SELECT id FROM batch_students WHERE student_id = ? AND batch_id = ? LIMIT 1');
        if ($check) {
            $check->bind_param('ii', $student_id, $batch_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $check->close();
                $conn->commit();
                return ['success' => true, 'message' => 'Student is already in this batch.'];
            }
            $check->close();
        }

        $sql1 = "UPDATE students SET 
                status = 'active', 
                batch_id = ?, 
                approved_by = ?, 
                approved_at = NOW() 
                WHERE id = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("isi", $batch_id, $admin_name, $student_id);
        $stmt1->execute();
        $stmt1->close();

        $sql2 = "INSERT INTO batch_students (batch_id, student_id, enrollment_date) 
                VALUES (?, ?, NOW())";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("ii", $batch_id, $student_id);
        $stmt2->execute();
        $stmt2->close();

        $sql3 = "UPDATE batches SET seats_filled = seats_filled + 1 WHERE id = ?";
        $stmt3 = $conn->prepare($sql3);
        $stmt3->bind_param("i", $batch_id);
        $stmt3->execute();
        $stmt3->close();

        $conn->commit();
        return ['success' => true, 'message' => 'Student approved and assigned to batch successfully'];

    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Reject student
 */
function rejectStudent($student_id, $admin_name, $conn) {
    $sql = "UPDATE students SET 
            status = 'Rejected', 
            approved_by = ?, 
            approved_at = NOW() 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $admin_name, $student_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return ['success' => $result, 'message' => $result ? 'Student rejected' : 'Error rejecting student'];
}

/**
 * Get pending students
 */
function getPendingStudents($conn, $admin_courses = []) {
    $sql = "SELECT s.*, c.course_name 
            FROM students s 
            LEFT JOIN courses c ON s.course = c.course_name 
            WHERE s.status = 'Pending'";
    
    // Add course filtering for coordinators
    if (!empty($admin_courses)) {
        $placeholders = str_repeat('?,', count($admin_courses) - 1) . '?';
        $sql .= " AND s.course IN ($placeholders)";
    }
    
    $sql .= " ORDER BY s.created_at DESC";
    
    // Prepare and execute query
    if (!empty($admin_courses)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('s', count($admin_courses)), ...$admin_courses);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $students = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
    
    return $students;
}

/**
 * Get students in a batch
 */
function getBatchStudents($batch_id, $conn) {
    $batch_id = (int)$batch_id;
    if ($batch_id <= 0) {
        return [];
    }

    $check_table = $conn->query("SHOW TABLES LIKE 'batch_students'");
    $has_batch_students_table = ($check_table && $check_table->num_rows > 0);

    if ($has_batch_students_table) {
        repairBatchStudentsJunction($conn, $batch_id);
    }

    $check_column = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'nielit_registration_no'");
    $has_nielit_column = ($check_column && $check_column->num_rows > 0);

    $check_student_nielit = $conn->query("SHOW COLUMNS FROM students LIKE 'nielit_registration_no'");
    $has_student_nielit = ($check_student_nielit && $check_student_nielit->num_rows > 0);

    if (file_exists(__DIR__ . '/../../includes/nielit_registration_helper.php')) {
        require_once __DIR__ . '/../../includes/nielit_registration_helper.php';
    }

    if ($has_batch_students_table) {
        // Prefer batch_students value, fall back to students value, then student_id.
        if ($has_nielit_column || $has_student_nielit) {
            $nielitSelect = function_exists('sqlNielitRegistrationNo')
                ? sqlNielitRegistrationNo('s', $has_nielit_column ? 'bs' : null)
                : "COALESCE(NULLIF(TRIM(s.nielit_registration_no), ''), NULLIF(TRIM(s.student_id), '')) AS nielit_registration_no";
        } else {
            $nielitSelect = "NULLIF(TRIM(s.student_id), '') AS nielit_registration_no";
        }
        $certSelect = '';
        if (file_exists(__DIR__ . '/batch_certificate_helper.php')) {
            require_once __DIR__ . '/batch_certificate_helper.php';
            if (function_exists('ensureBatchCertificateSchema')) {
                ensureBatchCertificateSchema($conn);
            }
            if (function_exists('batch_certificate_column_exists') && batch_certificate_column_exists($conn, 'certificate_file')) {
                $certSelect = ', bs.certificate_file, bs.certificate_number, bs.certificate_uploaded_at';
            }
        }
        $placementSelect = '';
        if (file_exists(__DIR__ . '/batch_placement_helper.php')) {
            require_once __DIR__ . '/batch_placement_helper.php';
            if (function_exists('ensureBatchPlacementSchema')) {
                ensureBatchPlacementSchema($conn);
            }
            if (function_exists('batch_placement_column_exists') && batch_placement_column_exists($conn, 'placement_status')) {
                $placementSelect = ', bs.placement_status, bs.placement_company, bs.placement_role,
                    bs.placement_package_amount, bs.placement_package_type, bs.placement_location,
                    bs.placement_date, bs.placement_remarks, bs.placement_updated_at';
            }
        }
        $sql = "SELECT DISTINCT s.*,
                COALESCE(bs.enrollment_date, s.approved_at, s.created_at) AS enrollment_date,
                COALESCE(bs.fees_status, 'Not Paid') AS fees_status,
                COALESCE(bs.fees_paid, 0) AS fees_paid,
                COALESCE(bs.attendance_percentage, 0) AS attendance_percentage,
                {$nielitSelect},
                bs.id AS batch_student_link_id{$certSelect}{$placementSelect}
                FROM students s
                LEFT JOIN batch_students bs ON bs.batch_id = ?
                    AND (bs.student_record_id = s.id OR bs.student_id = s.id)
                WHERE LOWER(COALESCE(s.status, '')) NOT IN ('rejected', 'inactive')
                AND (
                    s.batch_id = ?
                    OR bs.id IS NOT NULL
                )
                ORDER BY s.name ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $batch_id, $batch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $students = [];
        while ($row = $result->fetch_assoc()) {
            if (function_exists('syncNielitRegistrationNoDefault')
                && trim((string) ($row['nielit_registration_no'] ?? '')) === ''
                && trim((string) ($row['student_id'] ?? '')) !== '') {
                syncNielitRegistrationNoDefault($conn, (int) $row['id'], $batch_id);
                $row['nielit_registration_no'] = $row['student_id'];
            }
            $students[] = $row;
        }
        $stmt->close();
        return $students;
    }

    // Legacy: no batch_students table — use students.batch_id only
    $nielitLegacy = function_exists('sqlNielitRegistrationNo')
        ? sqlNielitRegistrationNo('s')
        : "COALESCE(NULLIF(TRIM(s.nielit_registration_no), ''), NULLIF(TRIM(s.student_id), '')) AS nielit_registration_no";
    $sql = "SELECT s.*, s.created_at as enrollment_date,
            'Not Paid' as fees_status, 0 as fees_paid, 0 as attendance_percentage,
            {$nielitLegacy}
            FROM students s
            WHERE s.batch_id = ?
            ORDER BY s.name ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        if (function_exists('syncNielitRegistrationNoDefault')
            && trim((string) ($row['nielit_registration_no'] ?? '')) === ''
            && trim((string) ($row['student_id'] ?? '')) !== '') {
            syncNielitRegistrationNoDefault($conn, (int) $row['id'], $batch_id);
            $row['nielit_registration_no'] = $row['student_id'];
        }
        $students[] = $row;
    }
    $stmt->close();

    return $students;
}

/**
 * Students eligible to be added to a batch (same course, not already in this batch)
 */
function getEligibleStudentsForBatch($batch_id, $conn) {
    $batch = getBatchById($batch_id, $conn);
    if (!$batch || empty($batch['course_id'])) {
        return [];
    }

    $course_id = (int)$batch['course_id'];
    $batch_scheme_id = !empty($batch['scheme_id']) ? (int)$batch['scheme_id'] : null;

    $helper = __DIR__ . '/../../includes/multi_course_helper.php';
    $hasScheme = false;
    if (file_exists($helper)) {
        require_once $helper;
        $hasScheme = function_exists('hasSchemeEnrollmentColumns') && hasSchemeEnrollmentColumns($conn);
    }

    $sql = "SELECT s.id, s.student_id, s.name, s.email, s.mobile, s.status, c.course_name, s.scheme_id
            FROM students s
            LEFT JOIN courses c ON c.id = s.course_id
            WHERE s.course_id = ?
            AND LOWER(s.status) NOT IN ('rejected', 'inactive')
            AND (s.batch_id IS NULL OR s.batch_id != ?)
            AND NOT EXISTS (
                SELECT 1 FROM batch_students bs
                WHERE bs.batch_id = ?
                AND (bs.student_record_id = s.id OR bs.student_id = s.id)
            )";

    if ($hasScheme && $batch_scheme_id !== null) {
        $sql .= " AND s.scheme_id = ?";
    } elseif ($hasScheme) {
        $sql .= " AND s.scheme_id IS NULL";
    }

    $sql .= " ORDER BY s.name ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($hasScheme && $batch_scheme_id !== null) {
        $stmt->bind_param('iiii', $course_id, $batch_id, $batch_id, $batch_scheme_id);
    } else {
        $stmt->bind_param('iii', $course_id, $batch_id, $batch_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
    return $students;
}

/**
 * Add multiple students to a batch
 */
function addStudentsToBatch(array $student_record_ids, $batch_id, $admin_name, $conn) {
    require_once __DIR__ . '/../../includes/multi_course_helper.php';

    $success = 0;
    $errors = [];
    foreach ($student_record_ids as $rid) {
        $rid = (int)$rid;
        if ($rid <= 0) {
            continue;
        }
        $result = assignEnrollmentToBatch($conn, $rid, (int)$batch_id, $admin_name);
        if ($result['success']) {
            $success++;
        } else {
            $errors[] = $result['message'];
        }
    }

    if ($success === 0 && !empty($errors)) {
        return ['success' => false, 'message' => $errors[0], 'count' => 0];
    }

    $msg = $success . ' student(s) added to batch successfully.';
    if (!empty($errors)) {
        $msg .= ' Some failed: ' . implode('; ', array_slice($errors, 0, 3));
    }
    return ['success' => true, 'message' => $msg, 'count' => $success];
}

/**
 * Active batches for the same course (excluding current batch) — move targets
 */
function getMoveTargetBatches($batch_id, $conn) {
    $batch = getBatchById($batch_id, $conn);
    if (!$batch || empty($batch['course_id'])) {
        return [];
    }

    $course_id = (int)$batch['course_id'];
    $batch_id = (int)$batch_id;
    $from_scheme = !empty($batch['scheme_id']) ? (int)$batch['scheme_id'] : null;

    $helper = __DIR__ . '/../../includes/multi_course_helper.php';
    $hasScheme = false;
    if (file_exists($helper)) {
        require_once $helper;
        $hasScheme = function_exists('hasSchemeEnrollmentColumns') && hasSchemeEnrollmentColumns($conn);
    }

    $sql = "SELECT b.id, b.batch_name, b.batch_code, b.seats_total, b.seats_filled, b.status, b.scheme_id
            FROM batches b
            WHERE b.course_id = ?
            AND b.id != ?
            AND LOWER(b.status) = 'active'";

    if ($hasScheme && $from_scheme !== null) {
        $sql .= " AND b.scheme_id = ?";
    } elseif ($hasScheme) {
        $sql .= " AND b.scheme_id IS NULL";
    }

    $sql .= " ORDER BY b.batch_name ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($hasScheme && $from_scheme !== null) {
        $stmt->bind_param('iii', $course_id, $batch_id, $from_scheme);
    } else {
        $stmt->bind_param('ii', $course_id, $batch_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $batches = [];
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row;
    }
    $stmt->close();
    return $batches;
}

/**
 * Move enrollment row(s) from one batch to another (same course only)
 */
function moveStudentsToBatch(array $student_record_ids, $from_batch_id, $to_batch_id, $admin_name, $conn) {
    require_once __DIR__ . '/../../includes/multi_course_helper.php';

    $from_batch_id = (int)$from_batch_id;
    $to_batch_id = (int)$to_batch_id;

    if ($from_batch_id <= 0 || $to_batch_id <= 0) {
        return ['success' => false, 'message' => 'Invalid batch selected.', 'count' => 0];
    }
    if ($from_batch_id === $to_batch_id) {
        return ['success' => false, 'message' => 'Please choose a different batch.', 'count' => 0];
    }

    $from = getBatchById($from_batch_id, $conn);
    $to = getBatchById($to_batch_id, $conn);
    if (!$from || !$to) {
        return ['success' => false, 'message' => 'Batch not found.', 'count' => 0];
    }
    if ((int)$from['course_id'] !== (int)$to['course_id']) {
        return ['success' => false, 'message' => 'Destination batch must be for the same course.', 'count' => 0];
    }
    if (strtolower((string)$to['status']) !== 'active') {
        return ['success' => false, 'message' => 'Destination batch is not active.', 'count' => 0];
    }

    $student_record_ids = array_values(array_filter(array_map('intval', $student_record_ids)));
    if (empty($student_record_ids)) {
        return ['success' => false, 'message' => 'Please select at least one student.', 'count' => 0];
    }

    $success = 0;
    $errors = [];

    foreach ($student_record_ids as $rid) {
        if ($rid <= 0) {
            continue;
        }

        $inBatch = $conn->prepare(
            'SELECT id FROM batch_students
             WHERE batch_id = ? AND (student_record_id = ? OR student_id = ?)
             LIMIT 1'
        );
        if (!$inBatch) {
            $errors[] = 'Database error verifying student in batch.';
            continue;
        }
        $inBatch->bind_param('iii', $from_batch_id, $rid, $rid);
        $inBatch->execute();
        $found = $inBatch->get_result()->num_rows > 0;
        $inBatch->close();

        if (!$found) {
            $errors[] = "Student record #$rid is not in this batch.";
            continue;
        }

        $removed = removeStudentFromBatch($rid, $from_batch_id, $conn);
        if (!$removed['success']) {
            $errors[] = $removed['message'];
            continue;
        }

        $assigned = assignEnrollmentToBatch($conn, $rid, $to_batch_id, $admin_name);
        if ($assigned['success']) {
            $success++;
        } else {
            $errors[] = $assigned['message'];
        }
    }

    if ($success === 0) {
        return [
            'success' => false,
            'message' => !empty($errors) ? $errors[0] : 'No students were moved.',
            'count' => 0,
        ];
    }

    $msg = $success . ' student(s) moved to ' . $to['batch_name'] . ' successfully.';
    if (!empty($errors)) {
        $msg .= ' Some failed: ' . implode('; ', array_slice($errors, 0, 3));
    }

    return ['success' => true, 'message' => $msg, 'count' => $success];
}

/**
 * Backfill batch_students.student_record_id from student_id for legacy rows.
 */
function backfillBatchStudentRecordIds($conn) {
    $hasRecordCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
    if (!$hasRecordCol || $hasRecordCol->num_rows === 0) {
        return;
    }
    $conn->query("UPDATE batch_students
        SET student_record_id = student_id
        WHERE (student_record_id IS NULL OR student_record_id = 0)
        AND student_id IS NOT NULL AND student_id > 0");
}

/**
 * Create missing batch_students rows for students assigned via students.batch_id only.
 */
function repairBatchStudentsJunction($conn, $batch_id = null) {
    $check = $conn->query("SHOW TABLES LIKE 'batch_students'");
    if (!$check || $check->num_rows === 0) {
        return 0;
    }

    backfillBatchStudentRecordIds($conn);

    $hasRecordCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
    $useRecordCol = ($hasRecordCol && $hasRecordCol->num_rows > 0);

    $sql = "SELECT s.id, s.batch_id
            FROM students s
            WHERE s.batch_id IS NOT NULL AND s.batch_id > 0
            AND LOWER(COALESCE(s.status, '')) NOT IN ('rejected', 'inactive')";
    if ($batch_id !== null) {
        $sql .= ' AND s.batch_id = ' . (int)$batch_id;
    }

    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }

    $fixed = 0;
    while ($row = $result->fetch_assoc()) {
        $recordId = (int)$row['id'];
        $batchId = (int)$row['batch_id'];
        if ($recordId <= 0 || $batchId <= 0) {
            continue;
        }

        if ($useRecordCol) {
            $chk = $conn->prepare('SELECT id FROM batch_students WHERE batch_id = ? AND (student_record_id = ? OR student_id = ?) LIMIT 1');
            if (!$chk) {
                continue;
            }
            $chk->bind_param('iii', $batchId, $recordId, $recordId);
        } else {
            $chk = $conn->prepare('SELECT id FROM batch_students WHERE batch_id = ? AND student_id = ? LIMIT 1');
            if (!$chk) {
                continue;
            }
            $chk->bind_param('ii', $batchId, $recordId);
        }
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();
        if ($exists) {
            continue;
        }

        if ($useRecordCol) {
            $ins = $conn->prepare('INSERT INTO batch_students (batch_id, student_id, student_record_id, enrollment_date) VALUES (?, ?, ?, NOW())');
            if ($ins) {
                $ins->bind_param('iii', $batchId, $recordId, $recordId);
                if ($ins->execute()) {
                    $fixed++;
                }
                $ins->close();
            }
        } else {
            $ins = $conn->prepare('INSERT INTO batch_students (batch_id, student_id, enrollment_date) VALUES (?, ?, NOW())');
            if ($ins) {
                $ins->bind_param('ii', $batchId, $recordId);
                if ($ins->execute()) {
                    $fixed++;
                }
                $ins->close();
            }
        }
    }

    return $fixed;
}

/**
 * Count students enrolled in a batch (primary batch_id + batch_students links).
 */
function getBatchEnrolledCount($batch_id, $conn) {
    $batch_id = (int)$batch_id;
    if ($batch_id <= 0) {
        return 0;
    }

    $check = $conn->query("SHOW TABLES LIKE 'batch_students'");
    $hasBatchStudents = ($check && $check->num_rows > 0);

    if ($hasBatchStudents) {
        $sql = "SELECT COUNT(DISTINCT s.id) AS enrolled_count
                FROM students s
                WHERE LOWER(COALESCE(s.status, '')) NOT IN ('rejected', 'inactive')
                AND (
                    s.batch_id = ?
                    OR EXISTS (
                        SELECT 1 FROM batch_students bs
                        WHERE bs.batch_id = ?
                        AND (bs.student_record_id = s.id OR bs.student_id = s.id)
                    )
                )";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ii', $batch_id, $batch_id);
            $stmt->execute();
            $count = (int)($stmt->get_result()->fetch_assoc()['enrolled_count'] ?? 0);
            $stmt->close();
            return $count;
        }
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS enrolled_count FROM students WHERE batch_id = ? AND LOWER(COALESCE(status, '')) NOT IN ('rejected', 'inactive')");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['enrolled_count'] ?? 0);
    $stmt->close();
    return $count;
}

/**
 * All batches linked to a student enrollment record (batch_students + legacy batch_id).
 */
function getBatchesForStudentRecord($conn, int $studentRecordId): array {
    if ($studentRecordId <= 0) {
        return [];
    }

    $batches = [];
    $hasRecordCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
    $useRecordCol = ($hasRecordCol && $hasRecordCol->num_rows > 0);

    if ($useRecordCol) {
        $sql = "SELECT DISTINCT b.id, b.batch_name, b.batch_code
                FROM batch_students bs
                INNER JOIN batches b ON b.id = bs.batch_id
                WHERE bs.student_record_id = ? OR bs.student_id = ?
                ORDER BY b.batch_name ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ii', $studentRecordId, $studentRecordId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $batches[(int)$row['id']] = $row;
            }
            $stmt->close();
        }
    } else {
        $sql = "SELECT DISTINCT b.id, b.batch_name, b.batch_code
                FROM batch_students bs
                INNER JOIN batches b ON b.id = bs.batch_id
                WHERE bs.student_id = ?
                ORDER BY b.batch_name ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $studentRecordId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $batches[(int)$row['id']] = $row;
            }
            $stmt->close();
        }
    }

    $fallback = $conn->prepare("SELECT b.id, b.batch_name, b.batch_code
        FROM students s
        INNER JOIN batches b ON b.id = s.batch_id
        WHERE s.id = ? AND s.batch_id IS NOT NULL AND s.batch_id > 0
        LIMIT 1");
    if ($fallback) {
        $fallback->bind_param('i', $studentRecordId);
        $fallback->execute();
        $row = $fallback->get_result()->fetch_assoc();
        $fallback->close();
        if ($row) {
            $batches[(int)$row['id']] = $row;
        }
    }

    return array_values($batches);
}

/**
 * All batches for a student in one course (across every scheme enrollment row).
 * Each entry includes student_record_id for remove actions.
 */
function getBatchesForStudentCourse($conn, string $studentIdStr, int $courseId): array {
    $studentIdStr = trim($studentIdStr);
    if ($studentIdStr === '' || $courseId <= 0) {
        return [];
    }

    $batches = [];
    $hasRecordCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
    $useRecordCol = ($hasRecordCol && $hasRecordCol->num_rows > 0);

    if ($useRecordCol) {
        $sql = "SELECT b.id, b.batch_name, b.batch_code,
                COALESCE(NULLIF(bs.student_record_id, 0), bs.student_id) AS student_record_id
                FROM batch_students bs
                INNER JOIN batches b ON b.id = bs.batch_id
                INNER JOIN students s ON s.id = COALESCE(NULLIF(bs.student_record_id, 0), bs.student_id)
                WHERE s.student_id = ? AND s.course_id = ?
                ORDER BY b.batch_name ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('si', $studentIdStr, $courseId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $bid = (int)$row['id'];
                $batches[$bid] = [
                    'id' => $bid,
                    'batch_name' => $row['batch_name'],
                    'batch_code' => $row['batch_code'],
                    'student_record_id' => (int)$row['student_record_id'],
                ];
            }
            $stmt->close();
        }
    } else {
        $sql = "SELECT b.id, b.batch_name, b.batch_code, bs.student_id AS student_record_id
                FROM batch_students bs
                INNER JOIN batches b ON b.id = bs.batch_id
                INNER JOIN students s ON s.id = bs.student_id
                WHERE s.student_id = ? AND s.course_id = ?
                ORDER BY b.batch_name ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('si', $studentIdStr, $courseId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $bid = (int)$row['id'];
                $batches[$bid] = [
                    'id' => $bid,
                    'batch_name' => $row['batch_name'],
                    'batch_code' => $row['batch_code'],
                    'student_record_id' => (int)$row['student_record_id'],
                ];
            }
            $stmt->close();
        }
    }

    $fallback = $conn->prepare("SELECT s.id AS student_record_id, b.id, b.batch_name, b.batch_code
        FROM students s
        INNER JOIN batches b ON b.id = s.batch_id
        WHERE s.student_id = ? AND s.course_id = ?
        AND s.batch_id IS NOT NULL AND s.batch_id > 0");
    if ($fallback) {
        $fallback->bind_param('si', $studentIdStr, $courseId);
        $fallback->execute();
        $res = $fallback->get_result();
        while ($row = $res->fetch_assoc()) {
            $bid = (int)$row['id'];
            if (!isset($batches[$bid])) {
                $batches[$bid] = [
                    'id' => $bid,
                    'batch_name' => $row['batch_name'],
                    'batch_code' => $row['batch_code'],
                    'student_record_id' => (int)$row['student_record_id'],
                ];
            }
        }
        $fallback->close();
    }

    return array_values($batches);
}

/**
 * Remove student from batch
 */
function removeStudentFromBatch($student_id, $batch_id, $conn) {
    $recordId = (int)$student_id;
    $batchId = (int)$batch_id;

    if ($recordId <= 0 || $batchId <= 0) {
        return ['success' => false, 'message' => 'Invalid student or batch.'];
    }

    $removedFromLink = false;
    $hasRecordCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
    if ($hasRecordCol && $hasRecordCol->num_rows > 0) {
        $del = $conn->prepare('DELETE FROM batch_students WHERE batch_id = ? AND (student_record_id = ? OR student_id = ?)');
        if ($del) {
            $del->bind_param('iii', $batchId, $recordId, $recordId);
            $del->execute();
            $removedFromLink = $del->affected_rows > 0;
            $del->close();
        }
    } else {
        $del = $conn->prepare('DELETE FROM batch_students WHERE student_id = ? AND batch_id = ?');
        if ($del) {
            $del->bind_param('ii', $recordId, $batchId);
            $del->execute();
            $removedFromLink = $del->affected_rows > 0;
            $del->close();
        }
    }

    $legacyPrimaryOnly = false;
    if (!$removedFromLink) {
        $chk = $conn->prepare('SELECT id FROM students WHERE id = ? AND batch_id = ? LIMIT 1');
        if ($chk) {
            $chk->bind_param('ii', $recordId, $batchId);
            $chk->execute();
            $legacyPrimaryOnly = $chk->get_result()->num_rows > 0;
            $chk->close();
        }
    }

    if (!$removedFromLink && !$legacyPrimaryOnly) {
        return ['success' => false, 'message' => 'Student was not found in this batch.'];
    }

    $remaining = getBatchesForStudentRecord($conn, $recordId);
    $remaining = array_values(array_filter($remaining, function ($b) use ($batchId) {
        return (int)$b['id'] !== $batchId;
    }));
    $nextBatchId = !empty($remaining) ? (int)$remaining[0]['id'] : null;

    if ($nextBatchId === null) {
        $reassign = $conn->prepare('UPDATE students SET batch_id = NULL WHERE id = ?');
        if ($reassign) {
            $reassign->bind_param('i', $recordId);
            $reassign->execute();
            $reassign->close();
        }
    } else {
        $reassign = $conn->prepare('UPDATE students SET batch_id = ? WHERE id = ?');
        if ($reassign) {
            $reassign->bind_param('ii', $nextBatchId, $recordId);
            $reassign->execute();
            $reassign->close();
        }
    }

    $helper = __DIR__ . '/../../includes/multi_course_helper.php';
    if (file_exists($helper)) {
        require_once $helper;
        if (function_exists('isMultiCourseSystemInstalled') && isMultiCourseSystemInstalled($conn)) {
            if ($nextBatchId === null) {
                $enr = $conn->prepare('UPDATE student_enrollments SET batch_id = NULL WHERE student_record_id = ?');
                if ($enr) {
                    $enr->bind_param('i', $recordId);
                    $enr->execute();
                    $enr->close();
                }
            } else {
                $enr = $conn->prepare('UPDATE student_enrollments SET batch_id = ? WHERE student_record_id = ?');
                if ($enr) {
                    $enr->bind_param('ii', $nextBatchId, $recordId);
                    $enr->execute();
                    $enr->close();
                }
            }
        }
    }

    $batchUpd = $conn->prepare('UPDATE batches SET seats_filled = GREATEST(0, seats_filled - 1) WHERE id = ?');
    if ($batchUpd) {
        $batchUpd->bind_param('i', $batchId);
        $batchUpd->execute();
        $batchUpd->close();
    }

    return ['success' => true, 'message' => 'Student removed from batch successfully.'];
}

/**
 * Get batch statistics
 */
function getBatchStats($batch_id, $conn) {
    $total = getBatchEnrolledCount((int)$batch_id, $conn);
    return [
        'total_students' => $total,
        'fees_paid_count' => 0,
        'total_fees_collected' => 0,
        'avg_attendance' => 0,
    ];
}

/**
 * Check if batch is locked
 */
function isBatchLocked($batch_id, $conn) {
    $sql = "SELECT is_locked FROM batches WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $batch = $result->fetch_assoc();
    $stmt->close();
    
    return $batch ? (bool)$batch['is_locked'] : false;
}

/**
 * Lock batch
 */
function lockBatch($batch_id, $admin_id, $conn) {
    // Check if batch exists and is not already locked
    $check_sql = "SELECT id, batch_name, is_locked FROM batches WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $batch_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $batch = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if (!$batch) {
        return ['success' => false, 'message' => 'Batch not found'];
    }
    
    if ($batch['is_locked']) {
        return ['success' => false, 'message' => 'Batch is already locked'];
    }
    
    // Lock the batch
    $lock_sql = "UPDATE batches SET is_locked = 1, locked_at = NOW(), locked_by = ? WHERE id = ?";
    $lock_stmt = $conn->prepare($lock_sql);
    $lock_stmt->bind_param("ii", $admin_id, $batch_id);
    $success = $lock_stmt->execute();
    $lock_stmt->close();
    
    if ($success) {
        return ['success' => true, 'message' => 'Batch "' . $batch['batch_name'] . '" has been locked successfully. No further modifications are allowed.'];
    } else {
        return ['success' => false, 'message' => 'Failed to lock batch'];
    }
}

/**
 * Unlock batch (Master Admin only)
 */
function unlockBatch($batch_id, $admin_id, $conn) {
    // Check if batch exists and is locked
    $check_sql = "SELECT id, batch_name, is_locked FROM batches WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $batch_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $batch = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if (!$batch) {
        return ['success' => false, 'message' => 'Batch not found'];
    }
    
    if (!$batch['is_locked']) {
        return ['success' => false, 'message' => 'Batch is not locked'];
    }
    
    // Unlock the batch
    $unlock_sql = "UPDATE batches SET is_locked = 0, locked_at = NULL, locked_by = NULL WHERE id = ?";
    $unlock_stmt = $conn->prepare($unlock_sql);
    $unlock_stmt->bind_param("i", $batch_id);
    $success = $unlock_stmt->execute();
    $unlock_stmt->close();
    
    if ($success) {
        return ['success' => true, 'message' => 'Batch "' . $batch['batch_name'] . '" has been unlocked successfully. Modifications are now allowed.'];
    } else {
        return ['success' => false, 'message' => 'Failed to unlock batch'];
    }
}

/**
 * Get batch lock information
 */
function getBatchLockInfo($batch_id, $conn) {
    $sql = "SELECT b.is_locked, b.locked_at, b.locked_by, a.username as locked_by_username 
            FROM batches b 
            LEFT JOIN admin a ON b.locked_by = a.id 
            WHERE b.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lock_info = $result->fetch_assoc();
    $stmt->close();
    
    return $lock_info;
}