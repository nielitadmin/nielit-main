<?php
/**
 * Recruitment interview schedules and one-by-one gated online calls.
 */

if (!function_exists('ensureRecruitmentInterviewTables')) {
    function ensureRecruitmentInterviewTables($conn): bool
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $ok = $conn->query(
            "CREATE TABLE IF NOT EXISTS recruitment_interviews (
                id INT PRIMARY KEY AUTO_INCREMENT,
                job_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                interview_date DATE NOT NULL,
                interview_time TIME NULL,
                mode VARCHAR(20) NOT NULL DEFAULT 'online',
                venue VARCHAR(255) NULL,
                meeting_url VARCHAR(500) NULL,
                room_key VARCHAR(80) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
                notes TEXT NULL,
                created_by VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_rec_iv_job (job_id),
                KEY idx_rec_iv_date (interview_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if (!$ok) {
            error_log('ensureRecruitmentInterviewTables interviews: ' . $conn->error);
            return false;
        }
        $ok = $conn->query(
            "CREATE TABLE IF NOT EXISTS recruitment_interview_candidates (
                id INT PRIMARY KEY AUTO_INCREMENT,
                interview_id INT NOT NULL,
                application_id INT NOT NULL,
                join_token VARCHAR(64) NOT NULL,
                call_status VARCHAR(20) NOT NULL DEFAULT 'waiting',
                sort_order INT NOT NULL DEFAULT 0,
                called_at DATETIME NULL,
                joined_at DATETIME NULL,
                ended_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_rec_iv_token (join_token),
                UNIQUE KEY uq_rec_iv_app (interview_id, application_id),
                KEY idx_rec_iv_call (interview_id, call_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if (!$ok) {
            error_log('ensureRecruitmentInterviewTables candidates: ' . $conn->error);
            return false;
        }
        return true;
    }
}

if (!function_exists('recruitmentInterviewStatuses')) {
    function recruitmentInterviewStatuses(): array
    {
        return [
            'scheduled' => 'Scheduled',
            'live' => 'Live',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }
}

if (!function_exists('recruitmentInterviewCallStatuses')) {
    function recruitmentInterviewCallStatuses(): array
    {
        return [
            'waiting' => 'Waiting',
            'called' => 'Called now',
            'completed' => 'Done',
            'skipped' => 'Skipped',
        ];
    }
}

if (!function_exists('recruitmentInterviewJoinUrl')) {
    function recruitmentInterviewJoinUrl(string $token): string
    {
        if (!function_exists('app_url')) {
            $uh = __DIR__ . '/url_helper.php';
            if (is_file($uh)) {
                require_once $uh;
            }
        }
        $base = function_exists('app_url')
            ? app_url('public/recruitment_interview')
            : (defined('APP_URL') ? rtrim(APP_URL, '/') . '/public/recruitment_interview.php' : '/public/recruitment_interview.php');
        return $base . '?t=' . rawurlencode($token);
    }
}

if (!function_exists('recruitmentInterviewRoomUrl')) {
    /** @param array<string,mixed> $interview */
    function recruitmentInterviewRoomUrl(array $interview): string
    {
        $custom = trim((string) ($interview['meeting_url'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        $key = trim((string) ($interview['room_key'] ?? ''));
        if ($key === '') {
            $key = 'NIELIT-REC-' . (int) ($interview['id'] ?? 0);
        }
        return 'https://meet.jit.si/' . rawurlencode($key);
    }
}

if (!function_exists('recruitmentInterviewNewToken')) {
    function recruitmentInterviewNewToken(): string
    {
        return bin2hex(random_bytes(24));
    }
}

if (!function_exists('recruitmentSaveInterview')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string,id?:int}
     */
    function recruitmentSaveInterview($conn, array $data): array
    {
        ensureRecruitmentInterviewTables($conn);
        $id = (int) ($data['id'] ?? 0);
        $jobId = (int) ($data['job_id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        $date = trim((string) ($data['interview_date'] ?? ''));
        $time = trim((string) ($data['interview_time'] ?? ''));
        $mode = strtolower(trim((string) ($data['mode'] ?? 'online')));
        if (!in_array($mode, ['online', 'offline'], true)) {
            $mode = 'online';
        }
        $venue = trim((string) ($data['venue'] ?? ''));
        $meeting = trim((string) ($data['meeting_url'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $by = trim((string) ($data['created_by'] ?? ''));
        if ($jobId <= 0) {
            return ['success' => false, 'message' => 'Select a job opening.'];
        }
        if ($title === '') {
            return ['success' => false, 'message' => 'Enter an interview title.'];
        }
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['success' => false, 'message' => 'Enter a valid interview date.'];
        }
        if ($time !== '' && !preg_match('/^\d{2}:\d{2}/', $time)) {
            $time = '';
        }
        if ($time !== '') {
            $time = substr($time, 0, 5) . ':00';
        } else {
            $time = null;
        }
        if ($id > 0) {
            $stmt = $conn->prepare(
                'UPDATE recruitment_interviews
                 SET title=?, interview_date=?, interview_time=?, mode=?, venue=?, meeting_url=?, notes=?
                 WHERE id=?'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Could not update the interview.'];
            }
            $timeSql = $time ?? '';
            $stmt->bind_param('sssssssi', $title, $date, $timeSql, $mode, $venue, $meeting, $notes, $id);
            $ok = $stmt->execute();
            $stmt->close();
            return ['success' => $ok, 'message' => $ok ? 'Interview updated.' : 'Could not update the interview.', 'id' => $id];
        }
        $room = 'NIELIT-REC-' . $jobId . '-' . strtoupper(bin2hex(random_bytes(4)));
        $status = 'scheduled';
        $timeSql = $time ?? '';
        $stmt = $conn->prepare(
            'INSERT INTO recruitment_interviews
             (job_id, title, interview_date, interview_time, mode, venue, meeting_url, room_key, status, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not create the interview.'];
        }
        $stmt->bind_param('issssssssss', $jobId, $title, $date, $timeSql, $mode, $venue, $meeting, $room, $status, $notes, $by);
        $ok = $stmt->execute();
        $newId = $ok ? (int) $conn->insert_id : 0;
        $stmt->close();
        return [
            'success' => $ok && $newId > 0,
            'message' => $ok ? 'Interview scheduled.' : 'Could not schedule the interview.',
            'id' => $newId,
        ];
    }
}

if (!function_exists('recruitmentGetInterview')) {
    /** @return array<string,mixed>|null */
    function recruitmentGetInterview($conn, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        ensureRecruitmentInterviewTables($conn);
        $stmt = $conn->prepare(
            'SELECT i.*, j.title AS job_title, j.advt_no
             FROM recruitment_interviews i
             LEFT JOIN recruitment_jobs j ON j.id = i.job_id
             WHERE i.id = ? LIMIT 1'
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

if (!function_exists('recruitmentListInterviews')) {
    /**
     * @return list<array<string,mixed>>
     */
    function recruitmentListInterviews($conn, int $jobId = 0): array
    {
        ensureRecruitmentInterviewTables($conn);
        $sql = 'SELECT i.*, j.title AS job_title,
                    (SELECT COUNT(*) FROM recruitment_interview_candidates c WHERE c.interview_id = i.id) AS candidate_count
                FROM recruitment_interviews i
                LEFT JOIN recruitment_jobs j ON j.id = i.job_id';
        if ($jobId > 0) {
            $sql .= ' WHERE i.job_id = ' . $jobId;
        }
        $sql .= ' ORDER BY i.interview_date DESC, i.id DESC';
        $res = $conn->query($sql);
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('recruitmentListInterviewCandidates')) {
    /**
     * @return list<array<string,mixed>>
     */
    function recruitmentListInterviewCandidates($conn, int $interviewId): array
    {
        $stmt = $conn->prepare(
            'SELECT c.*, a.application_no, a.name, a.email, a.mobile, a.photo_path, a.status AS app_status
             FROM recruitment_interview_candidates c
             INNER JOIN recruitment_applications a ON a.id = c.application_id
             WHERE c.interview_id = ?
             ORDER BY c.sort_order ASC, c.id ASC'
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $interviewId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('recruitmentInterviewEligibleApplications')) {
    /**
     * Shortlisted (or already listed) applications for a job that are not yet in this interview.
     * @return list<array<string,mixed>>
     */
    function recruitmentInterviewEligibleApplications($conn, int $jobId, int $interviewId): array
    {
        $stmt = $conn->prepare(
            'SELECT a.id, a.application_no, a.name, a.email, a.mobile, a.status
             FROM recruitment_applications a
             WHERE a.job_id = ?
               AND a.status IN (\'shortlisted\', \'selected\', \'under_review\')
               AND a.id NOT IN (SELECT application_id FROM recruitment_interview_candidates WHERE interview_id = ?)
             ORDER BY a.id ASC'
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $jobId, $interviewId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('recruitmentAddInterviewCandidate')) {
    /**
     * @return array{success:bool,message:string}
     */
    function recruitmentAddInterviewCandidate($conn, int $interviewId, int $applicationId, bool $notify = true): array
    {
        $iv = recruitmentGetInterview($conn, $interviewId);
        $app = recruitmentGetApplication($conn, $applicationId);
        if (!$iv || !$app) {
            return ['success' => false, 'message' => 'Interview or application not found.'];
        }
        if ((int) $app['job_id'] !== (int) $iv['job_id']) {
            return ['success' => false, 'message' => 'That application is for a different job.'];
        }
        $max = 0;
        $q = $conn->prepare('SELECT COALESCE(MAX(sort_order),0) AS m FROM recruitment_interview_candidates WHERE interview_id = ?');
        if ($q) {
            $q->bind_param('i', $interviewId);
            $q->execute();
            $max = (int) (($q->get_result()->fetch_assoc()['m'] ?? 0));
            $q->close();
        }
        $token = recruitmentInterviewNewToken();
        $sort = $max + 1;
        $waiting = 'waiting';
        $stmt = $conn->prepare(
            'INSERT INTO recruitment_interview_candidates (interview_id, application_id, join_token, call_status, sort_order)
             VALUES (?,?,?,?,?)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not add the candidate.'];
        }
        $stmt->bind_param('iissi', $interviewId, $applicationId, $token, $waiting, $sort);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'This candidate is already on the interview list.'];
        }
        if ($notify) {
            recruitmentQueueInterviewEmail($conn, 'interview_schedule', $app, $iv, $token);
        }
        return ['success' => true, 'message' => 'Candidate added to the interview list.'];
    }
}

if (!function_exists('recruitmentRemoveInterviewCandidate')) {
    function recruitmentRemoveInterviewCandidate($conn, int $rowId): bool
    {
        $stmt = $conn->prepare('DELETE FROM recruitment_interview_candidates WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $rowId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('recruitmentCallInterviewCandidate')) {
    /**
     * Admit one candidate to the room. Previous called candidate is marked done.
     * @return array{success:bool,message:string}
     */
    function recruitmentCallInterviewCandidate($conn, int $interviewId, int $rowId): array
    {
        $iv = recruitmentGetInterview($conn, $interviewId);
        if (!$iv) {
            return ['success' => false, 'message' => 'Interview not found.'];
        }
        $stmt = $conn->prepare(
            "UPDATE recruitment_interview_candidates
             SET call_status = 'completed', ended_at = NOW()
             WHERE interview_id = ? AND call_status = 'called' AND id <> ?"
        );
        if ($stmt) {
            $stmt->bind_param('ii', $interviewId, $rowId);
            $stmt->execute();
            $stmt->close();
        }
        $called = 'called';
        $upd = $conn->prepare(
            "UPDATE recruitment_interview_candidates
             SET call_status = ?, called_at = NOW(), ended_at = NULL
             WHERE id = ? AND interview_id = ?"
        );
        if (!$upd) {
            return ['success' => false, 'message' => 'Could not call the candidate.'];
        }
        $upd->bind_param('sii', $called, $rowId, $interviewId);
        $ok = $upd->execute();
        $changed = $ok && $upd->affected_rows > 0;
        $upd->close();
        $live = $conn->prepare("UPDATE recruitment_interviews SET status = 'live' WHERE id = ?");
        if ($live) {
            $live->bind_param('i', $interviewId);
            $live->execute();
            $live->close();
        }
        $row = recruitmentGetInterviewCandidateRow($conn, $rowId);
        if ($changed && $row) {
            $app = recruitmentGetApplication($conn, (int) $row['application_id']);
            if ($app) {
                recruitmentQueueInterviewEmail($conn, 'interview_call', $app, $iv, (string) $row['join_token']);
            }
            $name = (string) ($row['name'] ?? 'Candidate');
            return ['success' => true, 'message' => $name . ' has been called. The join link is now open for this candidate only.'];
        }
        return ['success' => $ok, 'message' => $ok ? 'Candidate called.' : 'Could not call the candidate.'];
    }
}

if (!function_exists('recruitmentSetInterviewCallStatus')) {
    function recruitmentSetInterviewCallStatus($conn, int $interviewId, int $rowId, string $status): bool
    {
        if (!isset(recruitmentInterviewCallStatuses()[$status])) {
            return false;
        }
        $ended = in_array($status, ['completed', 'skipped'], true) ? 'NOW()' : 'NULL';
        $stmt = $conn->prepare(
            "UPDATE recruitment_interview_candidates
             SET call_status = ?, ended_at = {$ended}
             WHERE id = ? AND interview_id = ?"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sii', $status, $rowId, $interviewId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('recruitmentResetInterviewTurnsForApplication')) {
    function recruitmentResetInterviewTurnsForApplication($conn, int $applicationId): bool
    {
        if ($applicationId <= 0 || !($conn instanceof mysqli)) {
            return false;
        }
        ensureRecruitmentInterviewTables($conn);
        $waiting = 'waiting';
        $stmt = $conn->prepare(
            "UPDATE recruitment_interview_candidates
             SET call_status = ?, called_at = NULL, ended_at = NULL, joined_at = NULL
             WHERE application_id = ?"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $waiting, $applicationId);
        $ok = $stmt->execute();
        $stmt->close();
        $reopen = $conn->prepare(
            "UPDATE recruitment_interviews i
             INNER JOIN recruitment_interview_candidates c ON c.interview_id = i.id
             SET i.status = 'scheduled'
             WHERE c.application_id = ? AND i.status = 'completed'"
        );
        if ($reopen) {
            $reopen->bind_param('i', $applicationId);
            $reopen->execute();
            $reopen->close();
        }
        return $ok;
    }
}

if (!function_exists('recruitmentGetInterviewCandidateRow')) {
    /** @return array<string,mixed>|null */
    function recruitmentGetInterviewCandidateRow($conn, int $rowId): ?array
    {
        $stmt = $conn->prepare(
            'SELECT c.*, a.application_no, a.name, a.email, a.mobile
             FROM recruitment_interview_candidates c
             INNER JOIN recruitment_applications a ON a.id = c.application_id
             WHERE c.id = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $rowId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('recruitmentGetInterviewByToken')) {
    /** @return array<string,mixed>|null */
    function recruitmentGetInterviewByToken($conn, string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 16) {
            return null;
        }
        ensureRecruitmentInterviewTables($conn);
        $stmt = $conn->prepare(
            'SELECT c.*, a.application_no, a.name, a.email, a.mobile, a.photo_path,
                    i.title AS interview_title, i.interview_date, i.interview_time, i.mode,
                    i.venue, i.meeting_url, i.room_key, i.status AS interview_status, i.job_id,
                    j.title AS job_title
             FROM recruitment_interview_candidates c
             INNER JOIN recruitment_applications a ON a.id = c.application_id
             INNER JOIN recruitment_interviews i ON i.id = c.interview_id
             LEFT JOIN recruitment_jobs j ON j.id = i.job_id
             WHERE c.join_token = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('recruitmentMarkInterviewJoined')) {
    function recruitmentMarkInterviewJoined($conn, int $rowId): void
    {
        $stmt = $conn->prepare(
            'UPDATE recruitment_interview_candidates SET joined_at = IFNULL(joined_at, NOW()) WHERE id = ? AND call_status = \'called\''
        );
        if ($stmt) {
            $stmt->bind_param('i', $rowId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('recruitmentQueueInterviewEmail')) {
    /**
     * @param array<string,mixed> $app
     * @param array<string,mixed> $interview
     */
    function recruitmentQueueInterviewEmail($conn, string $kind, array $app, array $interview, string $token): bool
    {
        return recruitmentQueueMail(
            $conn,
            $kind,
            (string) ($app['email'] ?? ''),
            (string) ($app['name'] ?? ''),
            [
                'app' => $app,
                'interview' => $interview,
                'join_url' => recruitmentInterviewJoinUrl($token),
            ]
        );
    }
}

if (!function_exists('recruitmentSendInterviewScheduleEmail')) {
    /**
     * @param array<string,mixed> $payload
     */
    function recruitmentSendInterviewScheduleEmail(array $payload): bool
    {
        $app = is_array($payload['app'] ?? null) ? $payload['app'] : [];
        $iv = is_array($payload['interview'] ?? null) ? $payload['interview'] : [];
        $name = trim((string) ($app['name'] ?? 'Candidate'));
        $email = trim((string) ($app['email'] ?? ''));
        $post = trim((string) ($app['job_title'] ?? ($iv['job_title'] ?? 'the advertised post')));
        $no = trim((string) ($app['application_no'] ?? ''));
        $when = recruitmentFormatDate((string) ($iv['interview_date'] ?? ''), 'd M Y');
        $time = substr((string) ($iv['interview_time'] ?? ''), 0, 5);
        $join = (string) ($payload['join_url'] ?? '');
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $inner = '<p>Dear <strong>' . $safeName . '</strong>,</p>'
            . '<p>You are scheduled for an interview for <strong>' . htmlspecialchars($post, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>Application number: <strong>' . htmlspecialchars($no, ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<p>Date: <strong>' . htmlspecialchars($when, ENT_QUOTES, 'UTF-8') . '</strong>'
            . ($time !== '' && $time !== '00:00' ? ' &nbsp; Time: <strong>' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</strong>' : '')
            . '</p>'
            . '<p>Please open the waiting-room link below on the interview date. Keep that page open. The interview board will <strong>call you one by one</strong>. The online room will unlock only when it is your turn.</p>'
            . '<p><a href="' . htmlspecialchars($join, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#1a56db;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;font-weight:600;">Open interview waiting room</a></p>'
            . '<p>Do not share this personal link.</p>'
            . '<p>Regards,<br>Recruitment Cell<br>NIELIT Bhubaneswar</p>';
        $text = "Dear {$name},\n\nYou are scheduled for an interview for {$post} on {$when}"
            . ($time !== '' && $time !== '00:00' ? " at {$time}" : '')
            . ".\nApplication number: {$no}\nWaiting room: {$join}\n\nKeep the page open. The room opens only when the board calls you.\n\nRegards,\nRecruitment Cell, NIELIT Bhubaneswar";
        return recruitmentSendMail(
            $email,
            $name,
            'Interview scheduled — ' . $post . ' | NIELIT Bhubaneswar',
            recruitmentEmailWrap('Interview scheduled', $inner),
            $text
        );
    }
}

if (!function_exists('recruitmentSendInterviewCallEmail')) {
    /**
     * @param array<string,mixed> $payload
     */
    function recruitmentSendInterviewCallEmail(array $payload): bool
    {
        $app = is_array($payload['app'] ?? null) ? $payload['app'] : [];
        $iv = is_array($payload['interview'] ?? null) ? $payload['interview'] : [];
        $name = trim((string) ($app['name'] ?? 'Candidate'));
        $email = trim((string) ($app['email'] ?? ''));
        $post = trim((string) ($app['job_title'] ?? ($iv['job_title'] ?? 'the advertised post')));
        $join = (string) ($payload['join_url'] ?? '');
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $inner = '<p>Dear <strong>' . $safeName . '</strong>,</p>'
            . '<p>The interview board is calling you now for <strong>' . htmlspecialchars($post, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>Please join immediately. This link is open only for you, and only while you are the candidate being interviewed.</p>'
            . '<p><a href="' . htmlspecialchars($join, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0f9d58;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;font-weight:600;">Join interview now</a></p>'
            . '<p>Regards,<br>Recruitment Cell<br>NIELIT Bhubaneswar</p>';
        $text = "Dear {$name},\n\nThe interview board is calling you now for {$post}.\nJoin now: {$join}\n\nRegards,\nRecruitment Cell, NIELIT Bhubaneswar";
        return recruitmentSendMail(
            $email,
            $name,
            'You are being called for interview — ' . $post . ' | NIELIT Bhubaneswar',
            recruitmentEmailWrap('Join your interview now', $inner),
            $text
        );
    }
}
