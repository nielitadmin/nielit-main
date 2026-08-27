<?php
/**
 * Recruitment portal — job openings and candidate applications.
 */

if (!function_exists('recruitmentCanAccess')) {
    function recruitmentCanAccess(?string $role = null): bool
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        return in_array($role, [
            'master_admin',
            'course_coordinator',
            'front_office_desk',
            'placement_coordinator',
            'data_entry_operator',
        ], true);
    }
}

if (!function_exists('recruitmentRequireAccess')) {
    function recruitmentRequireAccess(): void
    {
        if (!isset($_SESSION['admin'])) {
            header('Location: login.php');
            exit();
        }
        $teaching = __DIR__ . '/teaching_access.php';
        if (is_file($teaching)) {
            require_once $teaching;
            if (function_exists('admin_redirect_faculty_from_restricted_page')) {
                admin_redirect_faculty_from_restricted_page();
            }
        }
        if (!recruitmentCanAccess()) {
            $_SESSION['message'] = 'Access denied. Recruitment is not available for your role.';
            $_SESSION['message_type'] = 'danger';
            $dash = function_exists('app_url') ? app_url('admin/dashboard') : 'dashboard.php';
            header('Location: ' . $dash);
            exit();
        }
    }
}

if (!function_exists('recruitmentCanEdit')) {
    function recruitmentCanEdit(?string $role = null): bool
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        return in_array($role, ['master_admin', 'course_coordinator', 'placement_coordinator'], true);
    }
}

if (!function_exists('ensureRecruitmentTables')) {
    function ensureRecruitmentTables($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $jobs = "CREATE TABLE IF NOT EXISTS recruitment_jobs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            advt_no VARCHAR(120) NULL,
            post_type VARCHAR(80) NOT NULL DEFAULT 'Other',
            vacancies INT NOT NULL DEFAULT 1,
            location VARCHAR(255) NULL,
            pay_scale VARCHAR(255) NULL,
            eligibility TEXT NULL,
            experience TEXT NULL,
            age_limit VARCHAR(120) NULL,
            description TEXT NULL,
            instructions TEXT NULL,
            last_date DATE NULL,
            open_from DATE NULL,
            attachment_path VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_by VARCHAR(255) NULL,
            updated_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_rec_job_status (status),
            KEY idx_rec_job_last (last_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $apps = "CREATE TABLE IF NOT EXISTS recruitment_applications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            job_id INT NOT NULL,
            application_no VARCHAR(40) NOT NULL,
            name VARCHAR(255) NOT NULL,
            father_name VARCHAR(255) NULL,
            mother_name VARCHAR(255) NULL,
            dob DATE NULL,
            gender VARCHAR(20) NULL,
            category VARCHAR(40) NULL,
            aadhar VARCHAR(20) NULL,
            email VARCHAR(255) NOT NULL,
            mobile VARCHAR(20) NOT NULL,
            address TEXT NULL,
            city VARCHAR(120) NULL,
            state VARCHAR(120) NULL,
            pincode VARCHAR(12) NULL,
            qualification VARCHAR(255) NULL,
            experience_years VARCHAR(40) NULL,
            experience_details TEXT NULL,
            photo_path VARCHAR(255) NULL,
            resume_path VARCHAR(255) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'submitted',
            admin_remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_rec_app_no (application_no),
            KEY idx_rec_app_job (job_id),
            KEY idx_rec_app_email (email),
            KEY idx_rec_app_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($jobs)) {
            error_log('ensureRecruitmentTables jobs: ' . $conn->error);
            return false;
        }
        if (!$conn->query($apps)) {
            error_log('ensureRecruitmentTables applications: ' . $conn->error);
            return false;
        }
        $ready = true;
        return true;
    }
}

if (!function_exists('recruitmentPostTypes')) {
    function recruitmentPostTypes(): array
    {
        return [
            'Faculty' => 'Faculty',
            'Technical' => 'Technical',
            'Administrative' => 'Administrative',
            'Project staff' => 'Project staff',
            'Internship' => 'Internship',
            'Other' => 'Other',
        ];
    }
}

if (!function_exists('recruitmentJobStatuses')) {
    function recruitmentJobStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'open' => 'Open',
            'closed' => 'Closed',
        ];
    }
}

if (!function_exists('recruitmentApplicationStatuses')) {
    function recruitmentApplicationStatuses(): array
    {
        return [
            'submitted' => 'Submitted',
            'under_review' => 'Under review',
            'shortlisted' => 'Shortlisted',
            'rejected' => 'Rejected',
            'selected' => 'Selected',
        ];
    }
}

if (!function_exists('recruitmentStatusBadge')) {
    function recruitmentStatusBadge(string $status): string
    {
        $map = [
            'draft' => 'secondary',
            'open' => 'success',
            'closed' => 'dark',
            'submitted' => 'primary',
            'under_review' => 'info',
            'shortlisted' => 'warning',
            'rejected' => 'danger',
            'selected' => 'success',
        ];
        return $map[strtolower(trim($status))] ?? 'secondary';
    }
}

if (!function_exists('recruitmentJobIsAccepting')) {
    function recruitmentJobIsAccepting(array $job): bool
    {
        if (strtolower(trim((string) ($job['status'] ?? ''))) !== 'open') {
            return false;
        }
        $last = trim((string) ($job['last_date'] ?? ''));
        if ($last !== '' && $last !== '0000-00-00' && $last < date('Y-m-d')) {
            return false;
        }
        $from = trim((string) ($job['open_from'] ?? ''));
        if ($from !== '' && $from !== '0000-00-00' && $from > date('Y-m-d')) {
            return false;
        }
        return true;
    }
}

if (!function_exists('recruitmentListJobs')) {
    /**
     * @return list<array<string,mixed>>
     */
    function recruitmentListJobs($conn, array $filters = []): array
    {
        ensureRecruitmentTables($conn);
        $where = ['1=1'];
        $types = '';
        $params = [];
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $q = trim((string) ($filters['q'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $where[] = 'j.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(j.title LIKE ? OR j.advt_no LIKE ? OR j.location LIKE ?)';
            $types .= 'sss';
            array_push($params, $like, $like, $like);
        }
        $sql = 'SELECT j.*,
                    (SELECT COUNT(*) FROM recruitment_applications a WHERE a.job_id = j.id) AS application_count
                FROM recruitment_jobs j
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY j.created_at DESC, j.id DESC';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
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

if (!function_exists('recruitmentListOpenJobs')) {
    /**
     * @return list<array<string,mixed>>
     */
    function recruitmentListOpenJobs($conn): array
    {
        ensureRecruitmentTables($conn);
        $today = date('Y-m-d');
        $sql = "SELECT j.*,
                    (SELECT COUNT(*) FROM recruitment_applications a WHERE a.job_id = j.id) AS application_count
                FROM recruitment_jobs j
                WHERE j.status = 'open'
                  AND (j.open_from IS NULL OR j.open_from = '0000-00-00' OR j.open_from <= ?)
                  AND (j.last_date IS NULL OR j.last_date = '0000-00-00' OR j.last_date >= ?)
                ORDER BY j.last_date ASC, j.id DESC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ss', $today, $today);
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

if (!function_exists('recruitmentGetJob')) {
    function recruitmentGetJob($conn, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        ensureRecruitmentTables($conn);
        $stmt = $conn->prepare(
            'SELECT j.*,
                    (SELECT COUNT(*) FROM recruitment_applications a WHERE a.job_id = j.id) AS application_count
             FROM recruitment_jobs j WHERE j.id = ? LIMIT 1'
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

if (!function_exists('recruitmentSaveJob')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string,id:int}
     */
    function recruitmentSaveJob($conn, array $data, string $adminUser): array
    {
        ensureRecruitmentTables($conn);
        $id = (int) ($data['id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return ['success' => false, 'message' => 'Job title is required.', 'id' => $id];
        }
        $advt = trim((string) ($data['advt_no'] ?? ''));
        $postType = trim((string) ($data['post_type'] ?? 'Other'));
        if (!isset(recruitmentPostTypes()[$postType])) {
            $postType = 'Other';
        }
        $vacancies = max(1, (int) ($data['vacancies'] ?? 1));
        $location = trim((string) ($data['location'] ?? ''));
        $pay = trim((string) ($data['pay_scale'] ?? ''));
        $eligibility = trim((string) ($data['eligibility'] ?? ''));
        $experience = trim((string) ($data['experience'] ?? ''));
        $age = trim((string) ($data['age_limit'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $instructions = trim((string) ($data['instructions'] ?? ''));
        $lastDate = trim((string) ($data['last_date'] ?? ''));
        $openFrom = trim((string) ($data['open_from'] ?? ''));
        $status = strtolower(trim((string) ($data['status'] ?? 'draft')));
        if (!isset(recruitmentJobStatuses()[$status])) {
            $status = 'draft';
        }
        $attachment = trim((string) ($data['attachment_path'] ?? ''));

        if ($id > 0) {
            $sql = 'UPDATE recruitment_jobs SET
                title=?, advt_no=?, post_type=?, vacancies=?, location=?, pay_scale=?,
                eligibility=?, experience=?, age_limit=?, description=?, instructions=?,
                last_date=NULLIF(?, \'\'), open_from=NULLIF(?, \'\'), attachment_path=NULLIF(?, \'\'),
                status=?, updated_by=?
                WHERE id=?';
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return ['success' => false, 'message' => 'Could not save the job.', 'id' => $id];
            }
            $stmt->bind_param(
                'sssissssssssssssi',
                $title, $advt, $postType, $vacancies, $location, $pay,
                $eligibility, $experience, $age, $description, $instructions,
                $lastDate, $openFrom, $attachment, $status, $adminUser, $id
            );
            $ok = $stmt->execute();
            $stmt->close();
            return [
                'success' => $ok,
                'message' => $ok ? 'Job opening updated.' : 'Could not update the job.',
                'id' => $id,
            ];
        }

        $sql = 'INSERT INTO recruitment_jobs
            (title, advt_no, post_type, vacancies, location, pay_scale, eligibility, experience,
             age_limit, description, instructions, last_date, open_from, attachment_path, status, created_by, updated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,NULLIF(?, \'\'),NULLIF(?, \'\'),NULLIF(?, \'\'),?,?,?)';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not create the job.', 'id' => 0];
        }
        $stmt->bind_param(
            'sssisssssssssssss',
            $title, $advt, $postType, $vacancies, $location, $pay, $eligibility, $experience,
            $age, $description, $instructions, $lastDate, $openFrom, $attachment, $status, $adminUser, $adminUser
        );
        $ok = $stmt->execute();
        $newId = $ok ? (int) $stmt->insert_id : 0;
        $stmt->close();
        return [
            'success' => $ok,
            'message' => $ok ? 'Job opening created.' : 'Could not create the job.',
            'id' => $newId,
        ];
    }
}

if (!function_exists('recruitmentDeleteJob')) {
    function recruitmentDeleteJob($conn, int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid job.'];
        }
        $stmt = $conn->prepare('DELETE FROM recruitment_applications WHERE job_id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $conn->prepare('DELETE FROM recruitment_jobs WHERE id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not delete the job.'];
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return ['success' => $ok, 'message' => $ok ? 'Job opening deleted.' : 'Could not delete the job.'];
    }
}

if (!function_exists('recruitmentUploadDir')) {
    function recruitmentUploadDir(string $sub): string
    {
        $root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'recruitment' . DIRECTORY_SEPARATOR . $sub;
        if (!is_dir($root)) {
            @mkdir($root, 0755, true);
        }
        return $root;
    }
}

if (!function_exists('recruitmentStoreUpload')) {
    /**
     * @param array<string,mixed> $file
     */
    function recruitmentStoreUpload(array $file, string $sub, array $allowedExt, int $maxBytes): array
    {
        if (empty($file['tmp_name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'path' => '', 'message' => ''];
        }
        if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'path' => '', 'message' => 'File upload failed.'];
        }
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'path' => '', 'message' => 'File is larger than ' . (int) round($maxBytes / 1048576) . ' MB.'];
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return ['ok' => false, 'path' => '', 'message' => 'Allowed file types: ' . implode(', ', $allowedExt) . '.'];
        }
        $dir = recruitmentUploadDir($sub);
        $name = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            return ['ok' => false, 'path' => '', 'message' => 'Could not save the uploaded file.'];
        }
        return ['ok' => true, 'path' => 'uploads/recruitment/' . $sub . '/' . $name, 'message' => ''];
    }
}

if (!function_exists('recruitmentFileUrl')) {
    function recruitmentFileUrl(string $rel): string
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return '';
        }
        $base = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
        return $base . '/' . $rel;
    }
}

if (!function_exists('recruitmentNextApplicationNo')) {
    function recruitmentNextApplicationNo($conn): string
    {
        $year = date('Y');
        $prefix = 'REC/' . $year . '/';
        $stmt = $conn->prepare(
            "SELECT application_no FROM recruitment_applications
             WHERE application_no LIKE ? ORDER BY id DESC LIMIT 1"
        );
        $like = $prefix . '%';
        $n = 1;
        if ($stmt) {
            $stmt->bind_param('s', $like);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $tail = (int) preg_replace('/\D/', '', substr((string) $row['application_no'], strlen($prefix)));
                $n = $tail + 1;
            }
        }
        return $prefix . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('recruitmentSubmitApplication')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string,application_no?:string}
     */
    function recruitmentSubmitApplication($conn, int $jobId, array $data): array
    {
        ensureRecruitmentTables($conn);
        $job = recruitmentGetJob($conn, $jobId);
        if (!$job) {
            return ['success' => false, 'message' => 'This job opening was not found.'];
        }
        if (!recruitmentJobIsAccepting($job)) {
            return ['success' => false, 'message' => 'This job is not open for applications.'];
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $mobile = preg_replace('/\D/', '', (string) ($data['mobile'] ?? ''));
        $aadhar = preg_replace('/\D/', '', (string) ($data['aadhar'] ?? ''));
        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid name and email.'];
        }
        if (strlen((string) $mobile) !== 10) {
            return ['success' => false, 'message' => 'Please enter a valid 10-digit mobile number.'];
        }
        if ($aadhar !== '' && strlen((string) $aadhar) !== 12) {
            return ['success' => false, 'message' => 'Aadhaar must be 12 digits.'];
        }

        $dup = $conn->prepare(
            'SELECT id FROM recruitment_applications
             WHERE job_id = ? AND (LOWER(email) = ? OR (aadhar <> \'\' AND aadhar = ?))
             LIMIT 1'
        );
        if ($dup) {
            $dup->bind_param('iss', $jobId, $email, $aadhar);
            $dup->execute();
            $hit = $dup->get_result()->fetch_assoc();
            $dup->close();
            if ($hit) {
                return ['success' => false, 'message' => 'You have already applied for this job.'];
            }
        }

        $appNo = recruitmentNextApplicationNo($conn);
        $father = trim((string) ($data['father_name'] ?? ''));
        $mother = trim((string) ($data['mother_name'] ?? ''));
        $dob = trim((string) ($data['dob'] ?? ''));
        $gender = trim((string) ($data['gender'] ?? ''));
        $category = trim((string) ($data['category'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $state = trim((string) ($data['state'] ?? ''));
        $pincode = trim((string) ($data['pincode'] ?? ''));
        $qualification = trim((string) ($data['qualification'] ?? ''));
        $expYears = trim((string) ($data['experience_years'] ?? ''));
        $expDetails = trim((string) ($data['experience_details'] ?? ''));
        $photo = trim((string) ($data['photo_path'] ?? ''));
        $resume = trim((string) ($data['resume_path'] ?? ''));
        if ($resume === '') {
            return ['success' => false, 'message' => 'Please upload your resume (PDF).'];
        }

        $sql = 'INSERT INTO recruitment_applications
            (job_id, application_no, name, father_name, mother_name, dob, gender, category, aadhar,
             email, mobile, address, city, state, pincode, qualification, experience_years,
             experience_details, photo_path, resume_path, status)
            VALUES (?,?,?,?,?,NULLIF(?, \'\'),?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'submitted\')';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not save the application. Please try again.'];
        }
        $stmt->bind_param(
            'issssssssssssssssss',
            $jobId, $appNo, $name, $father, $mother, $dob, $gender, $category, $aadhar,
            $email, $mobile, $address, $city, $state, $pincode, $qualification, $expYears,
            $expDetails, $photo, $resume
        );
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not save the application. Please try again.'];
        }
        return [
            'success' => true,
            'message' => 'Application submitted successfully.',
            'application_no' => $appNo,
        ];
    }
}

if (!function_exists('recruitmentListApplications')) {
    /**
     * @return list<array<string,mixed>>
     */
    function recruitmentListApplications($conn, array $filters = []): array
    {
        ensureRecruitmentTables($conn);
        $where = ['1=1'];
        $types = '';
        $params = [];
        $jobId = (int) ($filters['job_id'] ?? 0);
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $q = trim((string) ($filters['q'] ?? ''));
        if ($jobId > 0) {
            $where[] = 'a.job_id = ?';
            $types .= 'i';
            $params[] = $jobId;
        }
        if ($status !== '' && $status !== 'all') {
            $where[] = 'a.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(a.application_no LIKE ? OR a.name LIKE ? OR a.email LIKE ? OR a.mobile LIKE ? OR a.aadhar LIKE ?)';
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $sql = 'SELECT a.*, j.title AS job_title, j.advt_no, j.post_type
                FROM recruitment_applications a
                LEFT JOIN recruitment_jobs j ON j.id = a.job_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.id DESC LIMIT 500';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
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

if (!function_exists('recruitmentGetApplication')) {
    function recruitmentGetApplication($conn, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        ensureRecruitmentTables($conn);
        $stmt = $conn->prepare(
            'SELECT a.*, j.title AS job_title, j.advt_no, j.post_type, j.location AS job_location,
                    j.eligibility, j.last_date
             FROM recruitment_applications a
             LEFT JOIN recruitment_jobs j ON j.id = a.job_id
             WHERE a.id = ? LIMIT 1'
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

if (!function_exists('recruitmentUpdateApplicationStatus')) {
    function recruitmentUpdateApplicationStatus($conn, int $id, string $status, string $remarks): array
    {
        if (!isset(recruitmentApplicationStatuses()[$status])) {
            return ['success' => false, 'message' => 'Invalid status.'];
        }
        $stmt = $conn->prepare('UPDATE recruitment_applications SET status = ?, admin_remarks = ? WHERE id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not update status.'];
        }
        $stmt->bind_param('ssi', $status, $remarks, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return ['success' => $ok, 'message' => $ok ? 'Application updated.' : 'Could not update the application.'];
    }
}

if (!function_exists('recruitmentStats')) {
    /**
     * @return array<string,int>
     */
    function recruitmentStats($conn): array
    {
        ensureRecruitmentTables($conn);
        $stats = ['jobs' => 0, 'open' => 0, 'applications' => 0, 'shortlisted' => 0];
        $r = $conn->query('SELECT COUNT(*) AS c FROM recruitment_jobs');
        if ($r) {
            $stats['jobs'] = (int) ($r->fetch_assoc()['c'] ?? 0);
        }
        $today = $conn->real_escape_string(date('Y-m-d'));
        $r = $conn->query("SELECT COUNT(*) AS c FROM recruitment_jobs
            WHERE status = 'open'
              AND (last_date IS NULL OR last_date = '0000-00-00' OR last_date >= '{$today}')");
        if ($r) {
            $stats['open'] = (int) ($r->fetch_assoc()['c'] ?? 0);
        }
        $r = $conn->query('SELECT COUNT(*) AS c FROM recruitment_applications');
        if ($r) {
            $stats['applications'] = (int) ($r->fetch_assoc()['c'] ?? 0);
        }
        $r = $conn->query("SELECT COUNT(*) AS c FROM recruitment_applications WHERE status = 'shortlisted'");
        if ($r) {
            $stats['shortlisted'] = (int) ($r->fetch_assoc()['c'] ?? 0);
        }
        return $stats;
    }
}

if (!function_exists('recruitmentFormatDate')) {
    function recruitmentFormatDate($value, string $format = 'd M Y'): string
    {
        $value = trim((string) $value);
        if ($value === '' || strpos($value, '0000-00-00') === 0) {
            return '—';
        }
        $ts = strtotime($value);
        return $ts ? date($format, $ts) : '—';
    }
}

if (!function_exists('recruitmentDisplay')) {
    function recruitmentDisplay($value, string $fallback = '—'): string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : $fallback;
    }
}
