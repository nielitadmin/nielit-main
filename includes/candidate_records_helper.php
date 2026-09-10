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
