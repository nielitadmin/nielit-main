<?php
/**
 * Master Admin — workshop / awareness participant records (desk entry).
 */

require_once __DIR__ . '/workshop_registration_helper.php';

if (!function_exists('candidateRecordsIsMasterAdmin')) {
    function candidateRecordsIsMasterAdmin(): bool
    {
        return (string) ($_SESSION['admin_role'] ?? '') === 'master_admin';
    }
}

if (!function_exists('candidateRecordsRequireAccess')) {
    function candidateRecordsRequireAccess(): void
    {
        if (!isset($_SESSION['admin'])) {
            header('Location: login.php');
            exit();
        }
        if (!candidateRecordsIsMasterAdmin()) {
            $_SESSION['message'] = 'Access denied. Workshop Records is available to Master Admin only.';
            $_SESSION['message_type'] = 'danger';
            $dash = function_exists('app_url') ? app_url('admin/dashboard') : 'dashboard.php';
            header('Location: ' . $dash);
            exit();
        }
    }
}

if (!function_exists('workshopRecordsList')) {
    /**
     * @return array{rows:list<array>,total:int,pages:int,page:int,per_page:int,stats:array}
     */
    function workshopRecordsList($conn, array $opts): array
    {
        $empty = [
            'rows' => [],
            'total' => 0,
            'pages' => 1,
            'page' => 1,
            'per_page' => 25,
            'stats' => ['total' => 0, 'pending' => 0, 'active' => 0],
        ];
        if (!($conn instanceof mysqli)) {
            return $empty;
        }
        $ids = workshopAdminCourseIds($conn);
        if ($ids === []) {
            return $empty;
        }

        $q = trim((string) ($opts['q'] ?? ''));
        $status = strtolower(trim((string) ($opts['status'] ?? 'all')));
        $courseId = (int) ($opts['course_id'] ?? 0);
        $page = max(1, (int) ($opts['page'] ?? 1));
        $perPage = (int) ($opts['per_page'] ?? 25);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $in = implode(',', array_map('intval', $ids));
        $where = ["s.course_id IN ($in)"];
        $types = '';
        $params = [];
        if ($courseId > 0 && in_array($courseId, $ids, true)) {
            $where[] = 's.course_id = ?';
            $types .= 'i';
            $params[] = $courseId;
        }
        if (in_array($status, ['pending', 'active', 'rejected'], true)) {
            $where[] = 'LOWER(s.status) = ?';
            $types .= 's';
            $params[] = $status;
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(s.name LIKE ? OR s.mobile LIKE ? OR s.email LIKE ? OR s.student_id LIKE ? OR s.college_name LIKE ?)';
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $whereSql = implode(' AND ', $where);

        $total = 0;
        $countSql = "SELECT COUNT(*) AS c FROM students s WHERE $whereSql";
        if ($types === '') {
            $r = $conn->query($countSql);
            $total = $r ? (int) ($r->fetch_assoc()['c'] ?? 0) : 0;
        } else {
            $stmt = $conn->prepare($countSql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $total = $res ? (int) ($res->fetch_assoc()['c'] ?? 0) : 0;
            $stmt->close();
        }

        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT s.id, s.student_id, s.name, s.father_name, s.mobile, s.email, s.status,
                       s.class_standard, s.college_name, s.course_id, s.course, s.registration_date, s.gender
                FROM students s
                WHERE $whereSql
                ORDER BY s.id DESC
                LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        if ($types === '') {
            $stmt->bind_param('ii', $perPage, $offset);
        } else {
            $bindTypes = $types . 'ii';
            $bindParams = array_merge($params, [$perPage, $offset]);
            $stmt->bind_param($bindTypes, ...$bindParams);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();

        $stats = ['total' => 0, 'pending' => 0, 'active' => 0];
        $sr = $conn->query("SELECT COUNT(*) AS c,
            SUM(LOWER(status)='pending') AS pending,
            SUM(LOWER(status)='active') AS active
            FROM students WHERE course_id IN ($in)");
        if ($sr) {
            $s = $sr->fetch_assoc() ?: [];
            $stats['total'] = (int) ($s['c'] ?? 0);
            $stats['pending'] = (int) ($s['pending'] ?? 0);
            $stats['active'] = (int) ($s['active'] ?? 0);
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
            'per_page' => $perPage,
            'stats' => $stats,
        ];
    }
}

if (!function_exists('workshopRecordsDeleteParticipant')) {
    /**
     * @return array{success:bool,message:string}
     */
    function workshopRecordsDeleteParticipant($conn, int $recordId): array
    {
        if ($recordId < 1 || !($conn instanceof mysqli)) {
            return ['success' => false, 'message' => 'Invalid record.'];
        }

        $workshopIds = workshopAdminCourseIds($conn);
        if ($workshopIds === []) {
            return ['success' => false, 'message' => 'No workshop courses are configured.'];
        }

        $stmt = $conn->prepare('SELECT id, student_id, name, course_id, course, passport_photo, aadhar_card_doc FROM students WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not load the record.'];
        }
        $stmt->bind_param('i', $recordId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return ['success' => false, 'message' => 'Workshop record not found.'];
        }

        $courseId = (int) ($row['course_id'] ?? 0);
        if (!in_array($courseId, $workshopIds, true)) {
            return ['success' => false, 'message' => 'This record is not a workshop / awareness participant.'];
        }

        $studentId = (string) ($row['student_id'] ?? '');
        $name = (string) ($row['name'] ?? '');
        $courseName = (string) ($row['course'] ?? 'workshop');

        require_once __DIR__ . '/multi_course_helper.php';
        if (function_exists('enrollmentRecordHasBatches') && enrollmentRecordHasBatches($conn, $recordId)) {
            return ['success' => false, 'message' => 'Remove this candidate from the batch first, then delete the workshop record.'];
        }

        $inspector = dirname(__DIR__) . '/admin/includes/student_record_inspector.php';
        if (is_file($inspector)) {
            require_once $inspector;
        }

        if (function_exists('adminRemoveStudentFromCourse') && $studentId !== '') {
            $result = adminRemoveStudentFromCourse($conn, $studentId, $courseId);
            if (!$result['success']) {
                return $result;
            }
        } elseif (function_exists('inspectorDeleteRecord')) {
            $result = inspectorDeleteRecord($conn, 'student', $recordId);
            if (!$result['success']) {
                return $result;
            }
        } else {
            $del = $conn->prepare('DELETE FROM students WHERE id = ? AND course_id = ?');
            if (!$del) {
                return ['success' => false, 'message' => 'Could not delete the record.'];
            }
            $del->bind_param('ii', $recordId, $courseId);
            if (!$del->execute() || $del->affected_rows < 1) {
                $del->close();
                return ['success' => false, 'message' => 'Could not delete the record.'];
            }
            $del->close();
        }

        $root = dirname(__DIR__);
        foreach (['passport_photo', 'aadhar_card_doc'] as $col) {
            $rel = trim((string) ($row[$col] ?? ''));
            if ($rel === '') {
                continue;
            }
            $abs = $root . '/' . ltrim(str_replace('\\', '/', $rel), '/');
            if (is_file($abs)) {
                @unlink($abs);
            }
        }

        if (is_file(__DIR__ . '/activity_logger.php')) {
            require_once __DIR__ . '/activity_logger.php';
            if (function_exists('logWorkshopRecordActivity')) {
                logWorkshopRecordActivity($conn, 'workshop_record_delete', 'Deleted workshop participant "' . $name . '" (' . $studentId . ') from "' . $courseName . '".', [
                    'student_id' => $studentId,
                    'candidate_name' => $name,
                    'course_id' => $courseId,
                    'course_name' => $courseName,
                    'record_id' => $recordId,
                ]);
            }
        }

        return ['success' => true, 'message' => 'Workshop record deleted for ' . $name . '.'];
    }
}
