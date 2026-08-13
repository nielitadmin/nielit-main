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

        $attachments = "CREATE TABLE IF NOT EXISTS support_ticket_attachments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            ticket_id INT NOT NULL,
            reply_id INT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            file_size INT NOT NULL DEFAULT 0,
            uploaded_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sta_ticket (ticket_id),
            KEY idx_sta_reply (reply_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($attachments)) {
            error_log('ensureSupportTicketsTable attachments failed: ' . $conn->error);
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
     * @param array<string,mixed> $uploadedFiles $_FILES['attachments'] or empty
     * @return array{success:bool,message:string,id?:int}
     */
    function createSupportTicket($conn, array $data, array $uploadedFiles = []): array
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

        $attach = saveSupportTicketAttachments($conn, $id, $uploadedFiles, null, $type === 'admin' ? $adminUser : $studentId);
        $msg = 'Support ticket submitted successfully. Ticket #' . $id . '.';
        if ($attach['saved'] > 0) {
            $msg .= ' ' . $attach['saved'] . ' file(s) attached.';
        }
        if ($attach['error'] !== '') {
            $msg .= ' ' . $attach['error'];
        }
        return [
            'success' => true,
            'id' => $id,
            'message' => $msg,
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
        $sql = "SELECT st.*, s.name AS student_name, s.email AS student_email,
                       (SELECT COUNT(*) FROM support_ticket_attachments sta WHERE sta.ticket_id = st.id) AS attachment_count
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
        $replyId = (int) $stmt->insert_id;
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

        return ['success' => true, 'message' => 'Reply saved.', 'id' => $replyId];
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

if (!function_exists('supportTicketAllowedAttachments')) {
    /** @return array<string,list<string>> ext => mime list */
    function supportTicketAllowedAttachments(): array
    {
        return [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg', 'image/jpg'],
            'jpeg' => ['image/jpeg', 'image/jpg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'gif' => ['image/gif'],
        ];
    }
}

if (!function_exists('supportTicketCollectUploads')) {
    /**
     * @param array<string,mixed> $filesField $_FILES['attachments']
     * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    function supportTicketCollectUploads(array $filesField): array
    {
        if (!isset($filesField['tmp_name'])) {
            return [];
        }
        if (!is_array($filesField['tmp_name'])) {
            return [[
                'name' => (string) ($filesField['name'] ?? ''),
                'type' => (string) ($filesField['type'] ?? ''),
                'tmp_name' => (string) $filesField['tmp_name'],
                'error' => (int) ($filesField['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($filesField['size'] ?? 0),
            ]];
        }
        $out = [];
        foreach ($filesField['tmp_name'] as $i => $tmp) {
            $out[] = [
                'name' => (string) ($filesField['name'][$i] ?? ''),
                'type' => (string) ($filesField['type'][$i] ?? ''),
                'tmp_name' => (string) $tmp,
                'error' => (int) ($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($filesField['size'][$i] ?? 0),
            ];
        }
        return $out;
    }
}

if (!function_exists('saveSupportTicketAttachments')) {
    /**
     * @param array<string,mixed> $uploadedFiles
     * @return array{saved:int,error:string}
     */
    function saveSupportTicketAttachments($conn, int $ticketId, array $uploadedFiles, ?int $replyId = null, string $uploadedBy = ''): array
    {
        ensureSupportTicketsTable($conn);
        if ($ticketId <= 0 || empty($uploadedFiles)) {
            return ['saved' => 0, 'error' => ''];
        }

        $items = supportTicketCollectUploads($uploadedFiles);
        $allowed = supportTicketAllowedAttachments();
        $maxFiles = 5;
        $maxBytes = 10 * 1024 * 1024;
        $saved = 0;
        $errors = [];

        $root = dirname(__DIR__) . '/uploads/support_tickets/' . $ticketId;
        if (!is_dir($root) && !mkdir($root, 0755, true)) {
            return ['saved' => 0, 'error' => 'Could not create attachment folder.'];
        }

        foreach ($items as $file) {
            if ($saved >= $maxFiles) {
                $errors[] = 'Only ' . $maxFiles . ' files can be attached.';
                break;
            }
            $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE || ($file['tmp_name'] ?? '') === '') {
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = 'Upload failed for ' . ($file['name'] ?: 'a file') . '.';
                continue;
            }
            if (!is_uploaded_file($file['tmp_name'])) {
                $errors[] = 'Invalid upload.';
                continue;
            }
            if ((int) $file['size'] > $maxBytes) {
                $errors[] = ($file['name'] ?: 'A file') . ' is larger than 10 MB.';
                continue;
            }

            $ext = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            if (!isset($allowed[$ext])) {
                $errors[] = 'File type not allowed: ' . ($file['name'] ?: 'unknown') . '. Use PDF, JPEG, PNG, WEBP or GIF.';
                continue;
            }

            $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 1024);
            if (strpos($head, '<?php') !== false || strpos($head, '#!/') !== false) {
                $errors[] = 'Invalid file content.';
                continue;
            }

            $mime = '';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mime = (string) finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                }
            }
            if ($mime === '') {
                $mime = (string) ($file['type'] ?? '');
            }
            if (!in_array($mime, $allowed[$ext], true)) {
                if ($ext === 'pdf' && strncmp($head, '%PDF', 4) === 0) {
                    $mime = 'application/pdf';
                } elseif (in_array($ext, ['jpg', 'jpeg'], true) && strncmp($head, "\xFF\xD8\xFF", 3) === 0) {
                    $mime = 'image/jpeg';
                } elseif ($ext === 'png' && strncmp($head, "\x89PNG", 4) === 0) {
                    $mime = 'image/png';
                } else {
                    $errors[] = 'Invalid file content for ' . ($file['name'] ?: 'upload') . '.';
                    continue;
                }
            }

            $storedName = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = $root . '/' . $storedName;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $errors[] = 'Could not save ' . ($file['name'] ?: 'file') . '.';
                continue;
            }

            $relPath = 'uploads/support_tickets/' . $ticketId . '/' . $storedName;
            $orig = preg_replace('/[^a-zA-Z0-9._\-\s()]/', '_', basename((string) $file['name']));
            if ($orig === '' || $orig === null) {
                $orig = $storedName;
            }
            if (strlen($orig) > 255) {
                $orig = substr($orig, 0, 255);
            }
            $size = (int) filesize($dest);
            $replyVal = ($replyId !== null && $replyId > 0) ? $replyId : 0;

            if ($replyVal > 0) {
                $stmt = $conn->prepare(
                    'INSERT INTO support_ticket_attachments (ticket_id, reply_id, original_name, stored_path, mime_type, file_size, uploaded_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                if (!$stmt) {
                    @unlink($dest);
                    $errors[] = 'Database error saving attachment.';
                    continue;
                }
                $stmt->bind_param('iisssis', $ticketId, $replyVal, $orig, $relPath, $mime, $size, $uploadedBy);
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO support_ticket_attachments (ticket_id, reply_id, original_name, stored_path, mime_type, file_size, uploaded_by, created_at)
                     VALUES (?, NULL, ?, ?, ?, ?, ?, NOW())'
                );
                if (!$stmt) {
                    @unlink($dest);
                    $errors[] = 'Database error saving attachment.';
                    continue;
                }
                $stmt->bind_param('isssis', $ticketId, $orig, $relPath, $mime, $size, $uploadedBy);
            }
            $ok = $stmt->execute();
            $stmt->close();
            if (!$ok) {
                @unlink($dest);
                $errors[] = 'Could not record attachment.';
                continue;
            }
            $saved++;
        }

        return [
            'saved' => $saved,
            'error' => implode(' ', $errors),
        ];
    }
}

if (!function_exists('listSupportTicketAttachments')) {
    /**
     * @return list<array<string,mixed>>
     */
    function listSupportTicketAttachments($conn, int $ticketId, ?int $replyId = null): array
    {
        ensureSupportTicketsTable($conn);
        $rows = [];
        if ($ticketId <= 0) {
            return $rows;
        }
        if ($replyId === null) {
            $stmt = $conn->prepare(
                'SELECT * FROM support_ticket_attachments WHERE ticket_id = ? AND reply_id IS NULL ORDER BY id ASC'
            );
            if (!$stmt) {
                return $rows;
            }
            $stmt->bind_param('i', $ticketId);
        } else {
            $stmt = $conn->prepare(
                'SELECT * FROM support_ticket_attachments WHERE ticket_id = ? AND reply_id = ? ORDER BY id ASC'
            );
            if (!$stmt) {
                return $rows;
            }
            $stmt->bind_param('ii', $ticketId, $replyId);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('getSupportTicketAttachment')) {
    /** @return array<string,mixed>|null */
    function getSupportTicketAttachment($conn, int $id): ?array
    {
        ensureSupportTicketsTable($conn);
        if ($id <= 0) {
            return null;
        }
        $stmt = $conn->prepare('SELECT * FROM support_ticket_attachments WHERE id = ? LIMIT 1');
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

if (!function_exists('supportTicketAttachmentAbsPath')) {
    function supportTicketAttachmentAbsPath(array $row): string
    {
        $rel = str_replace('\\', '/', (string) ($row['stored_path'] ?? ''));
        $rel = ltrim($rel, '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return '';
        }
        return dirname(__DIR__) . '/' . $rel;
    }
}

if (!function_exists('supportTicketFormatSize')) {
    function supportTicketFormatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}

if (!function_exists('supportTicketIsImageAttachment')) {
    function supportTicketIsImageAttachment(array $row): bool
    {
        $mime = strtolower((string) ($row['mime_type'] ?? ''));
        return strpos($mime, 'image/') === 0;
    }
}

if (!function_exists('supportTicketRenderAttachments')) {
    function supportTicketRenderAttachments(array $attachments, string $downloadScript): void
    {
        if (empty($attachments)) {
            return;
        }
        $sep = strpos($downloadScript, '?') === false ? '?' : '&';
        echo '<div class="st-files" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:10px;">';
        foreach ($attachments as $att) {
            $id = (int) ($att['id'] ?? 0);
            $url = htmlspecialchars($downloadScript . $sep . 'id=' . $id, ENT_QUOTES, 'UTF-8');
            $name = htmlspecialchars((string) ($att['original_name'] ?? 'file'), ENT_QUOTES, 'UTF-8');
            $size = htmlspecialchars(supportTicketFormatSize((int) ($att['file_size'] ?? 0)), ENT_QUOTES, 'UTF-8');
            $isPdf = strtolower((string) pathinfo((string) ($att['original_name'] ?? ''), PATHINFO_EXTENSION)) === 'pdf'
                || strtolower((string) ($att['mime_type'] ?? '')) === 'application/pdf';
            echo '<a href="' . $url . '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;text-decoration:none;color:#0f172a;max-width:280px;">';
            if (supportTicketIsImageAttachment($att)) {
                echo '<img src="' . $url . '" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:4px;flex-shrink:0;">';
            } else {
                echo '<i class="fas ' . ($isPdf ? 'fa-file-pdf' : 'fa-paperclip') . '" style="color:#dc2626;font-size:1.1rem;"></i>';
            }
            echo '<span style="overflow:hidden;"><span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' . $name . '</span><small style="color:#64748b;">' . $size . '</small></span></a>';
        }
        echo '</div>';
    }
}

