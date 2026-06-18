<?php
/**
 * Gather and delete all DB records related to a student search.
 */

if (!function_exists('inspectorTableExists')) {
    function inspectorTableExists(mysqli $conn, string $table): bool
    {
        $safe = $conn->real_escape_string($table);
        $r = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return $r && $r->num_rows > 0;
    }
}

if (!function_exists('inspectorCollectIds')) {
    /**
     * @param array<int, array<string, mixed>> $studentRows
     * @param array<int, array<string, mixed>> $hiddenStudentRows
     * @param array<int, array<string, mixed>> $accountRows
     * @param array<int, array<string, mixed>> $enrollmentRows
     * @return array{record_ids: int[], account_ids: int[], student_id_strings: string[]}
     */
    function inspectorCollectIds(
        array $studentRows,
        array $hiddenStudentRows,
        array $accountRows,
        array $enrollmentRows
    ): array {
        $recordIds = [];
        $accountIds = [];
        $studentIdStrings = [];

        foreach (array_merge($studentRows, $hiddenStudentRows) as $row) {
            $recordIds[] = (int)$row['id'];
            if (!empty($row['student_id'])) {
                $studentIdStrings[] = (string)$row['student_id'];
            }
            if (!empty($row['account_id'])) {
                $accountIds[] = (int)$row['account_id'];
            }
        }

        foreach ($accountRows as $row) {
            $accountIds[] = (int)$row['id'];
            if (!empty($row['student_id'])) {
                $studentIdStrings[] = (string)$row['student_id'];
            }
        }

        foreach ($enrollmentRows as $row) {
            if (!empty($row['account_id'])) {
                $accountIds[] = (int)$row['account_id'];
            }
            if (!empty($row['linked_account_id'])) {
                $accountIds[] = (int)$row['linked_account_id'];
            }
            if (!empty($row['student_record_id'])) {
                $recordIds[] = (int)$row['student_record_id'];
            }
            if (!empty($row['student_id'])) {
                $studentIdStrings[] = (string)$row['student_id'];
            }
        }

        return [
            'record_ids' => array_values(array_unique(array_filter($recordIds))),
            'account_ids' => array_values(array_unique(array_filter($accountIds))),
            'student_id_strings' => array_values(array_unique(array_filter($studentIdStrings))),
        ];
    }
}

if (!function_exists('inspectorExpandRelatedRecords')) {
    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    function inspectorExpandRelatedRecords(mysqli $conn, array $ids): array
    {
        $related = [
            'students_all' => [],
            'enrollments_all' => [],
            'batch_students' => [],
            'batch_attendance' => [],
            'education_details' => [],
            'certificates' => [],
            'attendance' => [],
            'attendance_logs' => [],
            'attendance_summary' => [],
        ];

        $recordIds = $ids['record_ids'];
        $accountIds = $ids['account_ids'];
        $studentIdStrings = $ids['student_id_strings'];

        if (!empty($studentIdStrings)) {
            $placeholders = implode(',', array_fill(0, count($studentIdStrings), '?'));
            $types = str_repeat('s', count($studentIdStrings));

            $sql = "SELECT s.id, s.student_id, s.name, s.email, s.mobile, s.aadhar, s.course_id,
                           c.course_name, s.scheme_id, s.batch_id, s.status, s.account_id, s.registration_date
                    FROM students s
                    LEFT JOIN courses c ON c.id = s.course_id
                    WHERE s.student_id IN ({$placeholders})
                    ORDER BY s.id DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$studentIdStrings);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $related['students_all'][] = $row;
                    $recordIds[] = (int)$row['id'];
                    if (!empty($row['account_id'])) {
                        $accountIds[] = (int)$row['account_id'];
                    }
                }
                $stmt->close();
            }
        }

        $recordIds = array_values(array_unique(array_filter($recordIds)));
        $accountIds = array_values(array_unique(array_filter($accountIds)));

        if (inspectorTableExists($conn, 'student_enrollments') && !empty($accountIds)) {
            $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
            $types = str_repeat('i', count($accountIds));
            $sql = "SELECT se.id, se.account_id, se.course_id, se.student_record_id, se.batch_id,
                           se.scheme_id, se.status, se.registered_at,
                           c.course_name, sa.student_id, sa.name, sa.email
                    FROM student_enrollments se
                    INNER JOIN student_accounts sa ON sa.id = se.account_id
                    LEFT JOIN courses c ON c.id = se.course_id
                    WHERE se.account_id IN ({$placeholders})
                    ORDER BY se.id DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$accountIds);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $related['enrollments_all'][] = $row;
                    if (!empty($row['student_record_id'])) {
                        $recordIds[] = (int)$row['student_record_id'];
                    }
                }
                $stmt->close();
            }
        }

        if (!empty($recordIds) && inspectorTableExists($conn, 'student_enrollments')) {
            $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
            $types = str_repeat('i', count($recordIds));
            $sql = "SELECT se.id, se.account_id, se.course_id, se.student_record_id, se.batch_id,
                           se.scheme_id, se.status, se.registered_at,
                           c.course_name, sa.student_id, sa.name, sa.email
                    FROM student_enrollments se
                    LEFT JOIN student_accounts sa ON sa.id = se.account_id
                    LEFT JOIN courses c ON c.id = se.course_id
                    WHERE se.student_record_id IN ({$placeholders})
                    ORDER BY se.id DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$recordIds);
                $stmt->execute();
                $res = $stmt->get_result();
                $seen = array_column($related['enrollments_all'], 'id');
                while ($row = $res->fetch_assoc()) {
                    if (!in_array((int)$row['id'], $seen, true)) {
                        $related['enrollments_all'][] = $row;
                    }
                }
                $stmt->close();
            }
        }

        if (!empty($recordIds) && inspectorTableExists($conn, 'batch_students')) {
            $hasRecordCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
            $useRecordCol = $hasRecordCol && $hasRecordCol->num_rows > 0;
            $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
            $types = str_repeat('i', count($recordIds));

            if ($useRecordCol) {
                $sql = "SELECT bs.id, bs.batch_id, bs.student_id, bs.student_record_id, bs.enrollment_date,
                               bs.fees_paid, bs.fees_status, b.batch_name, b.batch_code
                        FROM batch_students bs
                        LEFT JOIN batches b ON b.id = bs.batch_id
                        WHERE bs.student_record_id IN ({$placeholders})
                           OR bs.student_id IN ({$placeholders})
                        ORDER BY bs.id DESC";
                $types .= $types;
                $params = array_merge($recordIds, $recordIds);
            } else {
                $sql = "SELECT bs.id, bs.batch_id, bs.student_id, bs.enrollment_date,
                               bs.fees_paid, bs.fees_status, b.batch_name, b.batch_code
                        FROM batch_students bs
                        LEFT JOIN batches b ON b.id = bs.batch_id
                        WHERE bs.student_id IN ({$placeholders})
                        ORDER BY bs.id DESC";
                $params = $recordIds;
            }

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $related['batch_students'][] = $row;
                }
                $stmt->close();
            }
        }

        if (!empty($recordIds) && inspectorTableExists($conn, 'batch_attendance')) {
            $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
            $types = str_repeat('i', count($recordIds));
            $sql = "SELECT ba.id, ba.batch_id, ba.student_id, ba.attendance_date, ba.status, ba.remarks
                    FROM batch_attendance ba
                    WHERE ba.student_id IN ({$placeholders})
                    ORDER BY ba.attendance_date DESC, ba.id DESC
                    LIMIT 100";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$recordIds);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $related['batch_attendance'][] = $row;
                }
                $stmt->close();
            }
        }

        if (!empty($studentIdStrings) && inspectorTableExists($conn, 'education_details')) {
            $placeholders = implode(',', array_fill(0, count($studentIdStrings), '?'));
            $types = str_repeat('s', count($studentIdStrings));
            $sql = "SELECT id, student_id, exam_passed, exam_name, year_of_passing, institute_name, percentage
                    FROM education_details
                    WHERE student_id IN ({$placeholders})
                    ORDER BY id ASC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$studentIdStrings);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $related['education_details'][] = $row;
                }
                $stmt->close();
            }
        }

        if (!empty($studentIdStrings) && inspectorTableExists($conn, 'certificates')) {
            $placeholders = implode(',', array_fill(0, count($studentIdStrings), '?'));
            $types = str_repeat('s', count($studentIdStrings));
            $sql = "SELECT id, student_id, certificate_type, certificate_number, issue_date, status
                    FROM certificates
                    WHERE student_id IN ({$placeholders})
                    ORDER BY issue_date DESC, id DESC
                    LIMIT 50";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$studentIdStrings);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $related['certificates'][] = $row;
                }
                $stmt->close();
            }
        }

        if (!empty($studentIdStrings) && inspectorTableExists($conn, 'attendance')) {
            $placeholders = implode(',', array_fill(0, count($studentIdStrings), '?'));
            $types = str_repeat('s', count($studentIdStrings));
            $sql = "SELECT id, student_id, date, status, session_id
                    FROM attendance
                    WHERE student_id IN ({$placeholders})
                    ORDER BY date DESC, id DESC
                    LIMIT 100";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$studentIdStrings);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $related['attendance'][] = $row;
                }
                $stmt->close();
            }
        }

        if (!empty($studentIdStrings) && inspectorTableExists($conn, 'attendance_logs')) {
            $placeholders = implode(',', array_fill(0, count($studentIdStrings), '?'));
            $types = str_repeat('s', count($studentIdStrings));
            $sql = "SELECT id, student_id, student_name, scan_type, scan_time, session_id
                    FROM attendance_logs
                    WHERE student_id IN ({$placeholders})
                    ORDER BY scan_time DESC, id DESC
                    LIMIT 100";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$studentIdStrings);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $related['attendance_logs'][] = $row;
                }
                $stmt->close();
            }
        }

        if (!empty($studentIdStrings) && inspectorTableExists($conn, 'attendance_summary')) {
            $placeholders = implode(',', array_fill(0, count($studentIdStrings), '?'));
            $types = str_repeat('s', count($studentIdStrings));
            $sql = "SELECT id, student_id, student_name, date, status, time_in, time_out
                    FROM attendance_summary
                    WHERE student_id IN ({$placeholders})
                    ORDER BY date DESC, id DESC
                    LIMIT 100";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$studentIdStrings);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $related['attendance_summary'][] = $row;
                }
                $stmt->close();
            }
        }

        return $related;
    }
}

if (!function_exists('inspectorCountRelated')) {
    function inspectorCountRelated(array $related): int
    {
        $total = 0;
        foreach ($related as $rows) {
            $total += count($rows);
        }
        return $total;
    }
}

if (!function_exists('inspectorDeleteRecord')) {
    function inspectorDeleteRecord(mysqli $conn, string $type, int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid record ID.'];
        }

        switch ($type) {
            case 'enrollment':
                if (!inspectorTableExists($conn, 'student_enrollments')) {
                    return ['success' => false, 'message' => 'Enrollments table not found.'];
                }
                $stmt = $conn->prepare('DELETE FROM student_enrollments WHERE id = ?');
                break;

            case 'student':
                if (function_exists('enrollmentRecordHasBatches') && enrollmentRecordHasBatches($conn, $id)) {
                    return ['success' => false, 'message' => 'Remove from batch first, then delete this student record.'];
                }
                if (inspectorTableExists($conn, 'student_enrollments')) {
                    $enr = $conn->prepare('DELETE FROM student_enrollments WHERE student_record_id = ?');
                    if ($enr) {
                        $enr->bind_param('i', $id);
                        $enr->execute();
                        $enr->close();
                    }
                }
                if (inspectorTableExists($conn, 'batch_students')) {
                    $hasRecordCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
                    if ($hasRecordCol && $hasRecordCol->num_rows > 0) {
                        $bs = $conn->prepare('DELETE FROM batch_students WHERE student_record_id = ? OR student_id = ?');
                        if ($bs) {
                            $bs->bind_param('ii', $id, $id);
                            $bs->execute();
                            $bs->close();
                        }
                    } else {
                        $bs = $conn->prepare('DELETE FROM batch_students WHERE student_id = ?');
                        if ($bs) {
                            $bs->bind_param('i', $id);
                            $bs->execute();
                            $bs->close();
                        }
                    }
                }
                $sid = null;
                $sidStmt = $conn->prepare('SELECT student_id FROM students WHERE id = ? LIMIT 1');
                if ($sidStmt) {
                    $sidStmt->bind_param('i', $id);
                    $sidStmt->execute();
                    $sidRow = $sidStmt->get_result()->fetch_assoc();
                    $sid = $sidRow['student_id'] ?? null;
                    $sidStmt->close();
                }
                if ($sid && inspectorTableExists($conn, 'education_details')) {
                    $ed = $conn->prepare('DELETE FROM education_details WHERE student_id = ?');
                    if ($ed) {
                        $ed->bind_param('s', $sid);
                        $ed->execute();
                        $ed->close();
                    }
                }
                $stmt = $conn->prepare('DELETE FROM students WHERE id = ?');
                break;

            case 'account':
                if (!inspectorTableExists($conn, 'student_accounts')) {
                    return ['success' => false, 'message' => 'Accounts table not found.'];
                }
                $other = $conn->prepare('SELECT COUNT(*) AS c FROM students WHERE account_id = ?');
                $linkedStudents = 0;
                if ($other) {
                    $other->bind_param('i', $id);
                    $other->execute();
                    $linkedStudents = (int)($other->get_result()->fetch_assoc()['c'] ?? 0);
                    $other->close();
                }
                if ($linkedStudents > 0) {
                    return [
                        'success' => false,
                        'message' => "Cannot delete account: {$linkedStudents} student record(s) still linked. Delete those first.",
                    ];
                }
                $stmt = $conn->prepare('DELETE FROM student_accounts WHERE id = ?');
                break;

            case 'batch_student':
                if (!inspectorTableExists($conn, 'batch_students')) {
                    return ['success' => false, 'message' => 'batch_students table not found.'];
                }
                $stmt = $conn->prepare('DELETE FROM batch_students WHERE id = ?');
                break;

            case 'batch_attendance':
                $stmt = $conn->prepare('DELETE FROM batch_attendance WHERE id = ?');
                break;

            case 'education':
                $stmt = $conn->prepare('DELETE FROM education_details WHERE id = ?');
                break;

            case 'certificate':
                $stmt = $conn->prepare('DELETE FROM certificates WHERE id = ?');
                break;

            case 'attendance':
                $stmt = $conn->prepare('DELETE FROM attendance WHERE id = ?');
                break;

            case 'attendance_log':
                $stmt = $conn->prepare('DELETE FROM attendance_logs WHERE id = ?');
                break;

            case 'attendance_summary':
                $stmt = $conn->prepare('DELETE FROM attendance_summary WHERE id = ?');
                break;

            default:
                return ['success' => false, 'message' => 'Unknown record type.'];
        }

        if (!isset($stmt) || !$stmt) {
            return ['success' => false, 'message' => $conn->error ?: 'Delete prepare failed.'];
        }

        $stmt->bind_param('i', $id);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => $err ?: 'Record not found or already deleted.'];
        }

        return ['success' => true, 'message' => ucfirst(str_replace('_', ' ', $type)) . ' deleted.'];
    }
}

if (!function_exists('inspectorPurgeAllRelated')) {
    function inspectorPurgeAllRelated(mysqli $conn, array $ids): array
    {
        $related = inspectorExpandRelatedRecords($conn, $ids);
        $deleted = [];
        $errors = [];

        $deleteOrder = [
            ['batch_attendance', 'batch_attendance'],
            ['batch_students', 'batch_student'],
            ['attendance_logs', 'attendance_log'],
            ['attendance_summary', 'attendance_summary'],
            ['attendance', 'attendance'],
            ['certificates', 'certificate'],
            ['education_details', 'education'],
            ['enrollments_all', 'enrollment'],
        ];

        foreach ($deleteOrder as [$key, $type]) {
            foreach ($related[$key] ?? [] as $row) {
                $result = inspectorDeleteRecord($conn, $type, (int)$row['id']);
                if ($result['success']) {
                    $deleted[] = "{$type} #{$row['id']}";
                } else {
                    $errors[] = "{$type} #{$row['id']}: {$result['message']}";
                }
            }
        }

        foreach ($related['students_all'] ?? [] as $row) {
            $result = inspectorDeleteRecord($conn, 'student', (int)$row['id']);
            if ($result['success']) {
                $deleted[] = "student #{$row['id']}";
            } else {
                $errors[] = "student #{$row['id']}: {$result['message']}";
            }
        }

        foreach ($ids['account_ids'] as $accountId) {
            $result = inspectorDeleteRecord($conn, 'account', (int)$accountId);
            if ($result['success']) {
                $deleted[] = "account #{$accountId}";
            } elseif (strpos($result['message'], 'still linked') === false) {
                $errors[] = "account #{$accountId}: {$result['message']}";
            }
        }

        if (empty($deleted) && empty($errors)) {
            return ['success' => false, 'message' => 'No related records to delete.'];
        }

        $msg = count($deleted) . ' record(s) deleted.';
        if (!empty($errors)) {
            $msg .= ' Some items failed: ' . implode('; ', array_slice($errors, 0, 5));
        }

        return ['success' => count($deleted) > 0, 'message' => $msg, 'deleted' => $deleted, 'errors' => $errors];
    }
}

if (!function_exists('inspectorSearchParams')) {
    function inspectorSearchParams(array $params): string
    {
        $keep = ['aadhar', 'mobile', 'email', 'student_id', 'name'];
        $out = [];
        foreach ($keep as $key) {
            if (!empty($params[$key])) {
                $out[$key] = $params[$key];
            }
        }
        return http_build_query($out);
    }
}

if (!function_exists('inspectorDeleteForm')) {
    function inspectorDeleteForm(string $type, int $id, array $searchParams, string $label = 'Delete'): string
    {
        $qs = inspectorSearchParams($searchParams);
        $action = 'check_student_exists.php' . ($qs !== '' ? '?' . $qs : '');
        $typeEsc = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
        $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return '<form method="POST" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" class="d-inline"'
            . ' onsubmit="return confirm(\'Delete this ' . $typeEsc . ' record #' . $id . '? This cannot be undone.\');">'
            . '<input type="hidden" name="delete_action" value="delete_one">'
            . '<input type="hidden" name="record_type" value="' . $typeEsc . '">'
            . '<input type="hidden" name="record_id" value="' . $id . '">'
            . inspectorHiddenSearchFields($searchParams)
            . '<button type="submit" class="btn btn-sm btn-outline-danger">' . $labelEsc . '</button>'
            . '</form>';
    }
}

if (!function_exists('inspectorHiddenSearchFields')) {
    function inspectorHiddenSearchFields(array $searchParams): string
    {
        $html = '';
        foreach (['aadhar', 'mobile', 'email', 'student_id', 'name'] as $key) {
            if (isset($searchParams[$key]) && $searchParams[$key] !== '') {
                $html .= '<input type="hidden" name="' . $key . '" value="'
                    . htmlspecialchars((string)$searchParams[$key], ENT_QUOTES, 'UTF-8') . '">';
            }
        }
        return $html;
    }
}
