<?php
/**
 * Support tickets — students and admins raise tickets; Master Admin handles pending inbox.
 */

if (!function_exists('ensureSupportTicketsTable')) {
    function ensureSupportTicketsTable($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS support_tickets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            requester_type VARCHAR(20) NOT NULL DEFAULT 'student',
            student_id VARCHAR(50) NULL,
            admin_username VARCHAR(255) NULL,
            subject VARCHAR(255) NOT NULL,
            category VARCHAR(50) NOT NULL DEFAULT 'other',
            priority VARCHAR(20) NOT NULL DEFAULT 'medium',
            message TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            admin_reply TEXT NULL,
            replied_by VARCHAR(255) NULL,
            replied_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_st_student (student_id),
            KEY idx_st_status (status),
            KEY idx_st_priority (priority),
            KEY idx_st_requester (requester_type),
            KEY idx_st_admin (admin_username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('ensureSupportTicketsTable failed: ' . $conn->error);
            return false;
        }

        $columns = [
            'requester_type' => "ADD COLUMN requester_type VARCHAR(20) NOT NULL DEFAULT 'student' AFTER id",
            'admin_username' => "ADD COLUMN admin_username VARCHAR(255) NULL AFTER student_id",
            'admin_reply' => "ADD COLUMN admin_reply TEXT NULL AFTER status",
            'replied_by' => "ADD COLUMN replied_by VARCHAR(255) NULL AFTER admin_reply",
            'replied_at' => "ADD COLUMN replied_at DATETIME NULL AFTER replied_by",
            'updated_at' => "ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        ];
        foreach ($columns as $name => $ddl) {
            $check = $conn->query("SHOW COLUMNS FROM support_tickets LIKE '" . $conn->real_escape_string($name) . "'");
            if ($check && $check->num_rows === 0) {
                $conn->query('ALTER TABLE support_tickets ' . $ddl);
            }
        }

        $replies = "CREATE TABLE IF NOT EXISTS support_ticket_replies (
            id INT PRIMARY KEY AUTO_INCREMENT,
            ticket_id INT NOT NULL,
            author_type VARCHAR(20) NOT NULL,
            author_name VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_str_ticket (ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($replies)) {
            error_log('ensureSupportTicketsTable replies failed: ' . $conn->error);
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('supportTicketCategories')) {
    /** @return array<string,string> */
    function supportTicketCategories(string $requesterType = 'student'): array
    {
        if ($requesterType === 'admin') {
            return [
                'technical' => 'Technical Issue',
                'access' => 'Login / Access',
                'data' => 'Data / Records',
                'academic' => 'Academic / Course',
                'system' => 'System / Portal',
                'other' => 'Other',
            ];
        }
        return [
            'technical' => 'Technical Issue',
            'academic' => 'Academic Query',
            'fees' => 'Fees Related',
            'certificate' => 'Certificate Issue',
            'attendance' => 'Attendance Query',
            'other' => 'Other',
        ];
    }
}

if (!function_exists('supportTicketPriorities')) {
    /** @return array<string,string> */
    function supportTicketPriorities(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
        ];
    }
}

if (!function_exists('supportTicketStatuses')) {
    /** @return array<string,string> */
    function supportTicketStatuses(): array
    {
        return [
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];
    }
}

if (!function_exists('supportTicketNormalizePriority')) {
    function supportTicketNormalizePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));
        return isset(supportTicketPriorities()[$priority]) ? $priority : 'medium';
    }
}

if (!function_exists('supportTicketNormalizeStatus')) {
    function supportTicketNormalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return isset(supportTicketStatuses()[$status]) ? $status : 'open';
    }
}

if (!function_exists('supportTicketNormalizeCategory')) {
    function supportTicketNormalizeCategory(string $category, string $requesterType = 'student'): string
    {
        $category = strtolower(trim($category));
        $allowed = supportTicketCategories($requesterType);
        return isset($allowed[$category]) ? $category : 'other';
    }
}

if (!function_exists('supportTicketPriorityBadgeClass')) {
    function supportTicketPriorityBadgeClass(string $priority): string
    {
        if ($priority === 'high') {
            return 'danger';
        }
        if ($priority === 'medium') {
            return 'warning';
        }
        return 'secondary';
    }
}

if (!function_exists('supportTicketStatusBadgeClass')) {
    function supportTicketStatusBadgeClass(string $status): string
    {
        if ($status === 'open') {
            return 'primary';
        }
        if ($status === 'in_progress') {
            return 'warning';
        }
        if ($status === 'resolved') {
            return 'success';
        }
        return 'secondary';
    }
}

if (!function_exists('supportTicketRequesterLabel')) {
    function supportTicketRequesterLabel(array $row): string
    {
        if (($row['requester_type'] ?? '') === 'admin') {
            $name = trim((string) ($row['admin_username'] ?? ''));
            return $name !== '' ? ('Admin: ' . $name) : 'Admin';
        }
        $name = trim((string) ($row['student_name'] ?? ''));
        $sid = trim((string) ($row['student_id'] ?? ''));
        if ($name !== '' && $sid !== '') {
            return $name . ' (' . $sid . ')';
        }
        if ($sid !== '') {
            return $sid;
        }
        return 'Student';
    }
}

if (!function_exists('createSupportTicket')) {
    /**
     * @param array{requester_type?:string,student_id?:string,admin_username?:string,subject:string,category?:string,priority?:string,message:string} $data
     * @return array{success:bool,message:string,id?:int}
     */
    function createSupportTicket($conn, array $data): array
    {
        ensureSupportTicketsTable($conn);
        $subject = trim((string) ($data['subject'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        if ($subject === '' || $message === '') {
            return ['success' => false, 'message' => 'Please fill in subject and message.'];
        }
        if (strlen($subject) > 255) {
            $subject = substr($subject, 0, 255);
        }

        $type = (($data['requester_type'] ?? 'student') === 'admin') ? 'admin' : 'student';
        $studentId = $type === 'student' ? trim((string) ($data['student_id'] ?? '')) : '';
        $adminUser = $type === 'admin' ? trim((string) ($data['admin_username'] ?? '')) : '';
        if ($type === 'student' && $studentId === '') {
            return ['success' => false, 'message' => 'Student session is invalid. Please log in again.'];
        }
        if ($type === 'admin' && $adminUser === '') {
            return ['success' => false, 'message' => 'Admin session is invalid. Please log in again.'];
        }

        $category = supportTicketNormalizeCategory((string) ($data['category'] ?? 'other'), $type);
        $priority = supportTicketNormalizePriority((string) ($data['priority'] ?? 'medium'));
        $status = 'open';

        $stmt = $conn->prepare(
            'INSERT INTO support_tickets (requester_type, student_id, admin_username, subject, category, priority, message, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param('ssssssss', $type, $studentId, $adminUser, $subject, $category, $priority, $message, $status);
        $ok = $stmt->execute();
        $id = (int) $stmt->insert_id;
        $err = $stmt->error;
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to submit ticket. ' . $err];
        }
        return [
            'success' => true,
            'id' => $id,
            'message' => 'Support ticket submitted successfully. Ticket #' . $id . '.',
        ];
    }
}

if (!function_exists('listSupportTickets')) {
    /**
     * @param array{status?:string,requester_type?:string,student_id?:string,admin_username?:string,limit?:int} $filters
     * @return list<array<string,mixed>>
     */
    function listSupportTickets($conn, array $filters = []): array
    {
        ensureSupportTicketsTable($conn);
        $sql = "SELECT st.*, s.name AS student_name, s.email AS student_email
                FROM support_tickets st
                LEFT JOIN students s ON s.student_id = st.student_id
                WHERE 1=1";
        $types = '';
        $params = [];

        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        if ($status === 'pending') {
            $sql .= " AND st.status IN ('open','in_progress')";
        } elseif ($status !== '' && $status !== 'all') {
            $status = supportTicketNormalizeStatus($status);
            $sql .= ' AND st.status = ?';
            $types .= 's';
            $params[] = $status;
        }

        $reqType = strtolower(trim((string) ($filters['requester_type'] ?? 'all')));
        if ($reqType === 'student' || $reqType === 'admin') {
            $sql .= ' AND st.requester_type = ?';
            $types .= 's';
            $params[] = $reqType;
        }

        $studentId = trim((string) ($filters['student_id'] ?? ''));
        if ($studentId !== '') {
            $sql .= ' AND st.student_id = ?';
            $types .= 's';
            $params[] = $studentId;
        }

        $adminUser = trim((string) ($filters['admin_username'] ?? ''));
        if ($adminUser !== '') {
            $sql .= ' AND st.requester_type = \'admin\' AND st.admin_username = ?';
            $types .= 's';
            $params[] = $adminUser;
        }

        $sql .= " ORDER BY FIELD(st.priority,'high','medium','low'), FIELD(st.status,'open','in_progress','resolved','closed'), st.created_at DESC";

        $limit = (int) ($filters['limit'] ?? 0);
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        $rows = [];
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log('listSupportTickets prepare failed: ' . $conn->error);
                return [];
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        } else {
            $res = $conn->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }
}

if (!function_exists('getSupportTicket')) {
    /** @return array<string,mixed>|null */
    function getSupportTicket($conn, int $id): ?array
    {
        ensureSupportTicketsTable($conn);
        if ($id <= 0) {
            return null;
        }
        $stmt = $conn->prepare(
            "SELECT st.*, s.name AS student_name, s.email AS student_email
             FROM support_tickets st
             LEFT JOIN students s ON s.student_id = st.student_id
             WHERE st.id = ? LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('listSupportTicketReplies')) {
    /** @return list<array<string,mixed>> */
    function listSupportTicketReplies($conn, int $ticketId): array
    {
        ensureSupportTicketsTable($conn);
        $rows = [];
        $stmt = $conn->prepare(
            'SELECT * FROM support_ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC, id ASC'
        );
        if (!$stmt) {
            return $rows;
        }
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('addSupportTicketReply')) {
    /**
     * @return array{success:bool,message:string}
     */
    function addSupportTicketReply($conn, int $ticketId, string $authorType, string $authorName, string $message, ?string $newStatus = null, bool $official = true): array
    {
        ensureSupportTicketsTable($conn);
        $message = trim($message);
        if ($ticketId <= 0 || $message === '') {
            return ['success' => false, 'message' => 'Reply cannot be empty.'];
        }
        $authorType = $authorType === 'student' ? 'student' : 'admin';
        $authorName = trim($authorName);
        if ($authorName === '') {
            $authorName = $authorType === 'student' ? 'Student' : 'Admin';
        }

        $ticket = getSupportTicket($conn, $ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found.'];
        }

        $stmt = $conn->prepare(
            'INSERT INTO support_ticket_replies (ticket_id, author_type, author_name, message, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param('isss', $ticketId, $authorType, $authorName, $message);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not save reply.'];
        }

        if ($authorType === 'admin' && $official) {
            $status = $newStatus !== null && $newStatus !== ''
                ? supportTicketNormalizeStatus($newStatus)
                : (string) ($ticket['status'] ?? 'open');
            if ($status === 'open') {
                $status = 'in_progress';
            }
            $upd = $conn->prepare(
                'UPDATE support_tickets SET admin_reply = ?, replied_by = ?, replied_at = NOW(), status = ? WHERE id = ?'
            );
            if ($upd) {
                $upd->bind_param('sssi', $message, $authorName, $status, $ticketId);
                $upd->execute();
                $upd->close();
            }
        } elseif (($ticket['status'] ?? '') === 'resolved') {
            $reopen = $conn->prepare("UPDATE support_tickets SET status = 'open' WHERE id = ?");
            if ($reopen) {
                $reopen->bind_param('i', $ticketId);
                $reopen->execute();
                $reopen->close();
            }
        }

        return ['success' => true, 'message' => 'Reply saved.'];
    }
}

if (!function_exists('updateSupportTicketStatus')) {
    /**
     * @return array{success:bool,message:string}
     */
    function updateSupportTicketStatus($conn, int $ticketId, string $status): array
    {
        ensureSupportTicketsTable($conn);
        if ($ticketId <= 0) {
            return ['success' => false, 'message' => 'Invalid ticket.'];
        }
        $status = supportTicketNormalizeStatus($status);
        $stmt = $conn->prepare('UPDATE support_tickets SET status = ? WHERE id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param('si', $status, $ticketId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not update status.'];
        }
        return ['success' => true, 'message' => 'Ticket marked as ' . str_replace('_', ' ', $status) . '.'];
    }
}

if (!function_exists('countPendingSupportTickets')) {
    function countPendingSupportTickets($conn): int
    {
        ensureSupportTicketsTable($conn);
        $res = $conn->query("SELECT COUNT(*) AS c FROM support_tickets WHERE status IN ('open','in_progress')");
        if (!$res) {
            return 0;
        }
        $row = $res->fetch_assoc();
        return (int) ($row['c'] ?? 0);
    }
}

if (!function_exists('admin_can_manage_support_tickets')) {
    /** Master Admin can reply and change status on any ticket. */
    function admin_can_manage_support_tickets(?string $role = null): bool
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        return $role === 'master_admin';
    }
}

if (!function_exists('supportTicketCanView')) {
    function supportTicketCanView(array $ticket, bool $isMaster, string $adminUsername = '', string $studentId = ''): bool
    {
        if ($isMaster) {
            return true;
        }
        if ($studentId !== '' && ($ticket['requester_type'] ?? '') === 'student' && (string) ($ticket['student_id'] ?? '') === $studentId) {
            return true;
        }
        if ($adminUsername !== '' && ($ticket['requester_type'] ?? '') === 'admin' && (string) ($ticket['admin_username'] ?? '') === $adminUsername) {
            return true;
        }
        return false;
    }
}
