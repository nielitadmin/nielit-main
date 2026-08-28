<?php
/**
 * Recruitment portal — job openings and candidate applications.
 */

if (!function_exists('recruitmentCanAccess')) {
    function recruitmentCanAccess(?string $role = null, $conn = null): bool
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        if ($role === 'master_admin') {
            return true;
        }
        return recruitmentGrantedLevel($conn) !== '';
    }
}

if (!function_exists('recruitmentRequireAccess')) {
    function recruitmentRequireAccess($conn = null): void
    {
        if (!isset($_SESSION['admin'])) {
            header('Location: login.php');
            exit();
        }
        $conn = $conn ?? recruitmentDb();
        $teaching = __DIR__ . '/teaching_access.php';
        if (is_file($teaching)) {
            require_once $teaching;
            if (!recruitmentCanAccess(null, $conn) && function_exists('admin_redirect_faculty_from_restricted_page')) {
                admin_redirect_faculty_from_restricted_page();
            }
        }
        if (!recruitmentCanAccess(null, $conn)) {
            $_SESSION['message'] = 'Access denied. Recruitment is closed unless a Master Admin grants you access.';
            $_SESSION['message_type'] = 'danger';
            $dash = function_exists('app_url') ? app_url('admin/dashboard') : 'dashboard.php';
            header('Location: ' . $dash);
            exit();
        }
    }
}

if (!function_exists('recruitmentCanEdit')) {
    function recruitmentCanEdit(?string $role = null, $conn = null): bool
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        if ($role === 'master_admin') {
            return true;
        }
        return recruitmentGrantedLevel($conn) === 'edit';
    }
}

if (!function_exists('recruitmentIsMasterAdmin')) {
    function recruitmentIsMasterAdmin(?string $role = null): bool
    {
        $role = $role ?? (string) ($_SESSION['admin_role'] ?? '');
        return $role === 'master_admin';
    }
}

if (!function_exists('recruitmentDb')) {
    function recruitmentDb($conn = null)
    {
        if ($conn instanceof mysqli) {
            return $conn;
        }
        return ($GLOBALS['conn'] ?? null) instanceof mysqli ? $GLOBALS['conn'] : null;
    }
}

if (!function_exists('ensureRecruitmentAccessTable')) {
    function ensureRecruitmentAccessTable($conn): bool
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $sql = "CREATE TABLE IF NOT EXISTS recruitment_module_access (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_id INT NOT NULL,
            access_level VARCHAR(20) NOT NULL DEFAULT 'view',
            granted_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_rec_access_admin (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($sql)) {
            error_log('ensureRecruitmentAccessTable: ' . $conn->error);
            return false;
        }
        return true;
    }
}

if (!function_exists('recruitmentGrantedLevel')) {
    /** @return ''|'view'|'edit' */
    function recruitmentGrantedLevel($conn = null): string
    {
        $conn = recruitmentDb($conn);
        $adminId = (int) ($_SESSION['admin_id'] ?? 0);
        if ($adminId <= 0 && $conn instanceof mysqli) {
            $username = trim((string) ($_SESSION['admin'] ?? ''));
            if ($username !== '') {
                $stmt = $conn->prepare('SELECT id FROM admin WHERE username = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('s', $username);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $adminId = (int) ($row['id'] ?? 0);
                }
            }
        }
        if ($adminId <= 0 || !($conn instanceof mysqli)) {
            return '';
        }
        ensureRecruitmentAccessTable($conn);
        $stmt = $conn->prepare('SELECT access_level FROM recruitment_module_access WHERE admin_id = ? LIMIT 1');
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $level = strtolower(trim((string) ($row['access_level'] ?? '')));
        return in_array($level, ['view', 'edit'], true) ? $level : '';
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
        recruitmentEnsureApplicationExtraColumns($conn);
        ensureRecruitmentAccessTable($conn);
        ensureRecruitmentEmailQueueTable($conn);
        $ready = true;
        return true;
    }
}

if (!function_exists('recruitmentEnsureApplicationExtraColumns')) {
    function recruitmentEnsureApplicationExtraColumns($conn): void
    {
        if (!($conn instanceof mysqli)) {
            return;
        }
        $need = [
            'name_first' => 'VARCHAR(120) NULL',
            'name_middle' => 'VARCHAR(120) NULL',
            'name_last' => 'VARCHAR(120) NULL',
            'marital_status' => 'VARCHAR(40) NULL',
            'nationality' => "VARCHAR(80) NULL DEFAULT 'Indian'",
            'pwd_status' => "VARCHAR(10) NULL DEFAULT 'No'",
            'age_years' => 'INT NULL',
            'age_months' => 'INT NULL',
            'age_days' => 'INT NULL',
            'alt_mobile' => 'VARCHAR(20) NULL',
            'permanent_address' => 'TEXT NULL',
            'permanent_pincode' => 'VARCHAR(12) NULL',
            'education_json' => 'LONGTEXT NULL',
            'experience_json' => 'LONGTEXT NULL',
            'marksheet_x_path' => 'VARCHAR(255) NULL',
            'marksheet_xii_path' => 'VARCHAR(255) NULL',
            'degree_doc_path' => 'VARCHAR(255) NULL',
            'cgpa_formula_path' => 'VARCHAR(255) NULL',
            'experience_doc_path' => 'VARCHAR(255) NULL',
            'payslip_path' => 'VARCHAR(255) NULL',
            'dob_cert_path' => 'VARCHAR(255) NULL',
            'aadhaar_doc_path' => 'VARCHAR(255) NULL',
            'signature_path' => 'VARCHAR(255) NULL',
            'category_cert_path' => 'VARCHAR(255) NULL',
            'pwd_cert_path' => 'VARCHAR(255) NULL',
            'pwd_type' => 'VARCHAR(40) NULL',
            'pwd_percent' => 'VARCHAR(20) NULL',
            'computer_knowledge' => 'TEXT NULL',
            'additional_info' => 'TEXT NULL',
            'application_place' => 'VARCHAR(120) NULL',
        ];
        $have = [];
        $r = $conn->query('SHOW COLUMNS FROM recruitment_applications');
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $have[(string) $row['Field']] = true;
            }
        }
        foreach ($need as $col => $def) {
            if (isset($have[$col])) {
                continue;
            }
            $conn->query("ALTER TABLE recruitment_applications ADD COLUMN `{$col}` {$def}");
        }
    }
}

if (!function_exists('recruitmentOfficialDocuments')) {
    /**
     * Official NIELIT Bhubaneswar application form attachments.
     * @return list<array{key:string,label:string,required:bool,accept:string,ext:list<string>}>
     */
    function recruitmentOfficialDocuments(): array
    {
        $pdfImg = ['pdf', 'jpg', 'jpeg', 'png'];
        return [
            ['key' => 'photo', 'item' => '', 'label' => 'Recent passport size photograph', 'required' => false, 'accept' => '.jpg,.jpeg,.png', 'ext' => ['jpg', 'jpeg', 'png'], 'column' => 'photo_path'],
            ['key' => 'marksheet_x', 'item' => 'i', 'label' => 'Marksheet of Class X', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'ext' => $pdfImg, 'column' => 'marksheet_x_path'],
            ['key' => 'marksheet_xii', 'item' => 'ii', 'label' => 'Marksheet of Class XII', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'ext' => $pdfImg, 'column' => 'marksheet_xii_path'],
            ['key' => 'degree_doc', 'item' => 'iii', 'label' => 'Qualification degree / certificate and final consolidated marksheet (aggregate % or CGPA)', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'ext' => $pdfImg, 'column' => 'degree_doc_path'],
            ['key' => 'cgpa_formula', 'item' => '', 'label' => 'CGPA to % conversion formula issued by the University (if CGPA is awarded)', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'ext' => $pdfImg, 'column' => 'cgpa_formula_path'],
            ['key' => 'experience_doc', 'item' => 'iv', 'label' => 'Self-attested experience certificates (including current place of working)', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'ext' => $pdfImg, 'column' => 'experience_doc_path'],
            ['key' => 'payslip', 'item' => 'v', 'label' => 'Last three-month payslip or bank statement showing salary credited', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'ext' => $pdfImg, 'column' => 'payslip_path'],
            ['key' => 'dob_cert', 'item' => 'vi', 'label' => 'Date of Birth certificate / Class X certificate as proof of age', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'ext' => $pdfImg, 'column' => 'dob_cert_path'],
            ['key' => 'aadhaar_doc', 'item' => 'vii', 'label' => 'Aadhaar card', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'ext' => $pdfImg, 'column' => 'aadhaar_doc_path'],
            ['key' => 'resume', 'item' => 'viii', 'label' => 'CV / Resume of the candidate', 'required' => false, 'accept' => '.pdf', 'ext' => ['pdf'], 'column' => 'resume_path'],
            ['key' => 'category_cert', 'item' => '', 'label' => 'Caste / category certificate (SC / ST / OBC / EWS)', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'ext' => $pdfImg, 'column' => 'category_cert_path'],
            ['key' => 'pwd_cert', 'item' => '', 'label' => 'PwD certificate (if applicable)', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'ext' => $pdfImg, 'column' => 'pwd_cert_path'],
            ['key' => 'signature', 'item' => '', 'label' => 'Signature of the candidate', 'required' => false, 'accept' => '.jpg,.jpeg,.png', 'ext' => ['jpg', 'jpeg', 'png'], 'column' => 'signature_path'],
        ];
    }
}

if (!function_exists('recruitmentDefaultInstructions')) {
    function recruitmentDefaultInstructions(): string
    {
        return "Fill this online application form completely in CAPITAL letters where asked.\n"
            . "You may also download the printable application form from the recruitment page and use it for any post.\n"
            . "Upload a recent passport photograph and the documents listed below (self-attested scanned copies).\n"
            . "Name and Date of Birth must match Class X / Aadhaar.\n"
            . "Submit only one application for this post. Incomplete applications will be rejected.\n\n"
            . "Upload scanned copies where you have them (each file max 5 MB). Incomplete applications may be reviewed as received.\n\n"
            . "Documents to attach if available:\n"
            . "i. Marksheet of Class X\n"
            . "ii. Marksheet of Class XII\n"
            . "iii. Qualification degree/certificate and final consolidated marksheet (aggregate % or CGPA). If CGPA is awarded, also attach the University CGPA-to-% conversion formula.\n"
            . "iv. Self-attested experience certificates (including current place of working)\n"
            . "v. Last three-month payslip or bank statement showing salary credited\n"
            . "vi. Date of Birth certificate / Class X certificate as proof of age\n"
            . "vii. Aadhaar card\n"
            . "viii. CV / Resume\n"
            . "Also affix/upload a recent passport photograph and your signature. Attach caste/PwD certificate if applicable.";
    }
}

if (!function_exists('recruitmentCollectEducation')) {
    /**
     * @return list<array{exam:string,board:string,year:string,percent:string,subjects:string}>
     */
    function recruitmentCollectEducation(array $post): array
    {
        $exams = $post['edu_exam'] ?? [];
        $boards = $post['edu_board'] ?? [];
        $years = $post['edu_year'] ?? [];
        $percents = $post['edu_percent'] ?? [];
        $subjects = $post['edu_subjects'] ?? [];
        if (!is_array($exams)) {
            return [];
        }
        $rows = [];
        foreach ($exams as $i => $exam) {
            $exam = trim((string) $exam);
            $board = trim((string) ($boards[$i] ?? ''));
            $year = trim((string) ($years[$i] ?? ''));
            $percent = trim((string) ($percents[$i] ?? ''));
            $subj = trim((string) ($subjects[$i] ?? ''));
            if ($exam === '' && $board === '' && $year === '') {
                continue;
            }
            $rows[] = [
                'exam' => $exam,
                'board' => $board,
                'year' => $year,
                'percent' => $percent,
                'subjects' => $subj,
            ];
        }
        return $rows;
    }
}

if (!function_exists('recruitmentCollectExperience')) {
    /**
     * @return list<array{org:string,post:string,from:string,to:string,duration:string,nature:string}>
     */
    function recruitmentCollectExperience(array $post): array
    {
        $orgs = $post['exp_org'] ?? [];
        if (!is_array($orgs)) {
            return [];
        }
        $rows = [];
        foreach ($orgs as $i => $org) {
            $org = trim((string) $org);
            $postName = trim((string) (($post['exp_post'][$i] ?? '')));
            $from = trim((string) (($post['exp_from'][$i] ?? '')));
            $to = trim((string) (($post['exp_to'][$i] ?? '')));
            $dur = trim((string) (($post['exp_duration'][$i] ?? '')));
            $nature = trim((string) (($post['exp_nature'][$i] ?? '')));
            if ($org === '' && $postName === '' && $from === '') {
                continue;
            }
            $rows[] = [
                'org' => $org,
                'post' => $postName,
                'from' => $from,
                'to' => $to,
                'duration' => $dur,
                'nature' => $nature,
            ];
        }
        return $rows;
    }
}

if (!function_exists('recruitmentAgeParts')) {
    /**
     * @return array{years:int,months:int,days:int}
     */
    function recruitmentAgeParts(string $dob, string $asOn): array
    {
        $empty = ['years' => 0, 'months' => 0, 'days' => 0];
        if ($dob === '' || $asOn === '') {
            return $empty;
        }
        try {
            $from = new DateTime($dob);
            $to = new DateTime($asOn);
            if ($from > $to) {
                return $empty;
            }
            $diff = $from->diff($to);
            return ['years' => (int) $diff->y, 'months' => (int) $diff->m, 'days' => (int) $diff->d];
        } catch (Throwable $e) {
            return $empty;
        }
    }
}

if (!function_exists('recruitmentDecodeJsonList')) {
    /**
     * @return list<array<string,mixed>>
     */
    function recruitmentDecodeJsonList($json): array
    {
        if (is_array($json)) {
            return $json;
        }
        $json = trim((string) $json);
        if ($json === '') {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
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

if (!function_exists('recruitmentUnlinkStoredFile')) {
    function recruitmentUnlinkStoredFile(string $rel): void
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        if ($rel === '' || strpos($rel, '..') !== false || strpos($rel, 'uploads/recruitment/') !== 0) {
            return;
        }
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

if (!function_exists('recruitmentDeleteApplication')) {
    /**
     * Permanently delete one application and its uploaded files. Master Admin only.
     *
     * @return array{success:bool,message:string}
     */
    function recruitmentDeleteApplication($conn, int $id): array
    {
        if (!recruitmentIsMasterAdmin()) {
            return ['success' => false, 'message' => 'Only Master Admin can permanently delete an application.'];
        }
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid application.'];
        }
        $app = recruitmentGetApplication($conn, $id);
        if (!$app) {
            return ['success' => false, 'message' => 'Application not found.'];
        }
        foreach (recruitmentOfficialDocuments() as $doc) {
            $col = (string) ($doc['column'] ?? '');
            if ($col !== '') {
                recruitmentUnlinkStoredFile((string) ($app[$col] ?? ''));
            }
        }
        $stmt = $conn->prepare('DELETE FROM recruitment_applications WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not delete the application.'];
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        $no = trim((string) ($app['application_no'] ?? ''));
        return [
            'success' => $ok,
            'message' => $ok
                ? ('Application' . ($no !== '' ? ' ' . $no : '') . ' was permanently deleted.')
                : 'Could not delete the application.',
        ];
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
        if ((int) ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || (int) ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'path' => '', 'message' => 'File is larger than 5 MB. Please upload a smaller file.'];
        }
        if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'path' => '', 'message' => 'File upload failed.'];
        }
        $limit = $maxBytes > 0 ? $maxBytes : 5242880;
        if ((int) ($file['size'] ?? 0) > $limit) {
            return ['ok' => false, 'path' => '', 'message' => 'File is larger than 5 MB. Please upload a smaller file.'];
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
        if (strlen((string) $aadhar) !== 12) {
            return ['success' => false, 'message' => 'Aadhaar number is required (12 digits).'];
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
        $first = strtoupper(trim((string) ($data['name_first'] ?? '')));
        $middle = strtoupper(trim((string) ($data['name_middle'] ?? '')));
        $last = strtoupper(trim((string) ($data['name_last'] ?? '')));
        if ($name === '') {
            $name = trim($first . ' ' . $middle . ' ' . $last);
            $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        }
        $father = trim((string) ($data['father_name'] ?? ''));
        if ($father === '') {
            return ['success' => false, 'message' => "Please enter Father's / Husband's name."];
        }
        $mother = trim((string) ($data['mother_name'] ?? ''));
        $dob = trim((string) ($data['dob'] ?? ''));
        $gender = trim((string) ($data['gender'] ?? ''));
        $category = trim((string) ($data['category'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $state = trim((string) ($data['state'] ?? ''));
        $pincode = trim((string) ($data['pincode'] ?? ''));
        $education = recruitmentCollectEducation($data['_post'] ?? $data);
        $experience = recruitmentCollectExperience($data['_post'] ?? $data);
        $qual = trim((string) ($data['qualification'] ?? ''));
        if ($qual === '' && $education !== []) {
            $qual = (string) ($education[count($education) - 1]['exam'] ?? '');
        }
        $expYears = trim((string) ($data['experience_years'] ?? ''));
        $expDetails = trim((string) ($data['experience_details'] ?? ''));
        if ($expDetails === '' && $experience !== []) {
            $bits = [];
            foreach ($experience as $er) {
                $bits[] = trim(($er['post'] ?? '') . ' at ' . ($er['org'] ?? ''));
            }
            $expDetails = implode('; ', array_filter($bits));
        }
        $photo = trim((string) ($data['photo_path'] ?? ''));
        $resume = trim((string) ($data['resume_path'] ?? ''));
        if ($education === []) {
            return ['success' => false, 'message' => 'Please enter particulars of examinations passed, commencing from Class X.'];
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
            'isssssssssssssssssss',
            $jobId, $appNo, $name, $father, $mother, $dob, $gender, $category, $aadhar,
            $email, $mobile, $address, $city, $state, $pincode, $qual, $expYears,
            $expDetails, $photo, $resume
        );
        $ok = $stmt->execute();
        $newId = $ok ? (int) $stmt->insert_id : 0;
        $stmt->close();
        if (!$ok || $newId <= 0) {
            return ['success' => false, 'message' => 'Could not save the application. Please try again.'];
        }

        $asOn = trim((string) ($job['last_date'] ?? '')) ?: date('Y-m-d');
        $age = recruitmentAgeParts($dob, $asOn);
        $eduJson = json_encode($education, JSON_UNESCAPED_UNICODE);
        $expJson = json_encode($experience, JSON_UNESCAPED_UNICODE);
        $permAddr = trim((string) ($data['permanent_address'] ?? ''));
        if ($permAddr === '') {
            $permAddr = $address;
        }
        $permPin = trim((string) ($data['permanent_pincode'] ?? ''));
        if ($permPin === '') {
            $permPin = $pincode;
        }
        $ageY = (int) $age['years'];
        $ageM = (int) $age['months'];
        $ageD = (int) $age['days'];
        $extra = $conn->prepare(
            'UPDATE recruitment_applications SET
                name_first=?, name_middle=?, name_last=?, marital_status=?, nationality=?, pwd_status=?,
                age_years=?, age_months=?, age_days=?, alt_mobile=?,
                permanent_address=?, permanent_pincode=?, education_json=?, experience_json=?,
                marksheet_x_path=?, marksheet_xii_path=?, degree_doc_path=?, cgpa_formula_path=?,
                experience_doc_path=?, payslip_path=?, dob_cert_path=?, aadhaar_doc_path=?,
                signature_path=?, category_cert_path=?, pwd_cert_path=?,
                pwd_type=?, pwd_percent=?, computer_knowledge=?, additional_info=?, application_place=?
             WHERE id=?'
        );
        if ($extra) {
            $marital = trim((string) ($data['marital_status'] ?? ''));
            $nation = trim((string) ($data['nationality'] ?? 'Indian')) ?: 'Indian';
            $pwd = trim((string) ($data['pwd_status'] ?? 'No')) ?: 'No';
            $alt = (string) preg_replace('/\D/', '', (string) ($data['alt_mobile'] ?? ''));
            $mx = trim((string) ($data['marksheet_x_path'] ?? ''));
            $mxii = trim((string) ($data['marksheet_xii_path'] ?? ''));
            $deg = trim((string) ($data['degree_doc_path'] ?? ''));
            $cgpa = trim((string) ($data['cgpa_formula_path'] ?? ''));
            $exd = trim((string) ($data['experience_doc_path'] ?? ''));
            $pay = trim((string) ($data['payslip_path'] ?? ''));
            $dobc = trim((string) ($data['dob_cert_path'] ?? ''));
            $aadDoc = trim((string) ($data['aadhaar_doc_path'] ?? ''));
            $sig = trim((string) ($data['signature_path'] ?? ''));
            $catCert = trim((string) ($data['category_cert_path'] ?? ''));
            $pwdCert = trim((string) ($data['pwd_cert_path'] ?? ''));
            $pwdType = trim((string) ($data['pwd_type'] ?? ''));
            $pwdPct = trim((string) ($data['pwd_percent'] ?? ''));
            $comp = trim((string) ($data['computer_knowledge'] ?? ''));
            $addInfo = trim((string) ($data['additional_info'] ?? ''));
            $place = trim((string) ($data['application_place'] ?? ''));
            $extra->bind_param(
                'ssssssiiisssssssssssssssssssssi',
                $first, $middle, $last, $marital, $nation, $pwd,
                $ageY, $ageM, $ageD, $alt,
                $permAddr, $permPin, $eduJson, $expJson,
                $mx, $mxii, $deg, $cgpa, $exd, $pay, $dobc, $aadDoc,
                $sig, $catCert, $pwdCert, $pwdType, $pwdPct, $comp, $addInfo, $place,
                $newId
            );
            $extra->execute();
            $extra->close();
        } else {
            error_log('recruitmentSubmitApplication extra: ' . $conn->error);
        }
        $saved = recruitmentGetApplication($conn, $newId);
        $emailOk = recruitmentQueueThankYouEmail($conn, is_array($saved) ? $saved : [
            'name' => $name,
            'email' => $email,
            'application_no' => $appNo,
            'job_title' => (string) ($job['title'] ?? ''),
            'advt_no' => (string) ($job['advt_no'] ?? ''),
            'last_date' => (string) ($job['last_date'] ?? ''),
        ]);
        return [
            'success' => true,
            'message' => 'Application submitted successfully.',
            'application_no' => $appNo,
            'email_sent' => $emailOk,
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

if (!function_exists('recruitmentGetApplicationByNo')) {
    function recruitmentGetApplicationByNo($conn, string $applicationNo): ?array
    {
        $applicationNo = trim($applicationNo);
        if ($applicationNo === '') {
            return null;
        }
        ensureRecruitmentTables($conn);
        $stmt = $conn->prepare(
            'SELECT a.*, j.title AS job_title, j.advt_no, j.post_type, j.location AS job_location,
                    j.eligibility, j.last_date
             FROM recruitment_applications a
             LEFT JOIN recruitment_jobs j ON j.id = a.job_id
             WHERE a.application_no = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $applicationNo);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('recruitmentApplicationFormToken')) {
    function recruitmentApplicationFormToken(string $applicationNo, string $email): string
    {
        $key = hash('sha256', (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'nielit') . '|rec-form|' . (defined('APP_URL') ? APP_URL : ''));
        return hash_hmac('sha256', strtolower(trim($applicationNo)) . '|' . strtolower(trim($email)), $key);
    }
}

if (!function_exists('recruitmentApplicationFormTokenValid')) {
    function recruitmentApplicationFormTokenValid(string $applicationNo, string $email, string $token): bool
    {
        if ($token === '' || $applicationNo === '' || $email === '') {
            return false;
        }
        return hash_equals(recruitmentApplicationFormToken($applicationNo, $email), $token);
    }
}

if (!function_exists('recruitmentApplicationFormUrl')) {
    /**
     * Public URL for the blank form, a job-prefilled blank form, or a filled application PDF.
     *
     * @param array<string,mixed>|null $app
     */
    function recruitmentApplicationFormUrl(?array $app = null, bool $blank = false, int $jobId = 0): string
    {
        if (!function_exists('app_url')) {
            $uh = __DIR__ . '/url_helper.php';
            if (is_file($uh)) {
                require_once $uh;
            }
        }
        $base = function_exists('app_url') ? app_url('public/recruitment_form') : (defined('APP_URL') ? rtrim(APP_URL, '/') . '/public/recruitment_form.php' : '/public/recruitment_form.php');
        if ($blank || $app === null) {
            $q = ['blank' => '1'];
            if ($jobId > 0) {
                $q['job'] = (string) $jobId;
            }
            return $base . '?' . http_build_query($q);
        }
        $no = trim((string) ($app['application_no'] ?? ''));
        $email = trim((string) ($app['email'] ?? ''));
        if ($no === '') {
            return $base . '?blank=1';
        }
        $q = ['no' => $no, 't' => recruitmentApplicationFormToken($no, $email)];
        if ($email !== '') {
            $q['email'] = $email;
        }
        return $base . '?' . http_build_query($q);
    }
}

if (!function_exists('recruitmentUpdateApplicationStatus')) {
    function recruitmentUpdateApplicationStatus($conn, int $id, string $status, string $remarks, bool $notify = true): array
    {
        if (!isset(recruitmentApplicationStatuses()[$status])) {
            return ['success' => false, 'message' => 'Invalid status.'];
        }
        $remarks = trim($remarks);
        if ($status === 'rejected' && $remarks === '') {
            return ['success' => false, 'message' => 'Please enter the basis of rejection. This is sent to the candidate.'];
        }
        $app = recruitmentGetApplication($conn, $id);
        if (!$app) {
            return ['success' => false, 'message' => 'Application not found.'];
        }
        $stmt = $conn->prepare('UPDATE recruitment_applications SET status = ?, admin_remarks = ? WHERE id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not update status.'];
        }
        $stmt->bind_param('ssi', $status, $remarks, $id);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not update the application.'];
        }
        $app['status'] = $status;
        $app['admin_remarks'] = $remarks;
        $emailNote = '';
        if ($notify && in_array($status, ['shortlisted', 'rejected', 'selected'], true)) {
            $queued = recruitmentQueueStatusEmail($conn, $app, $status, $remarks);
            $emailNote = $queued
                ? ' An email with the filled application form (PDF) will be sent to the candidate shortly.'
                : ' Status saved, but the email could not be queued.';
        }
        return ['success' => true, 'message' => 'Application updated.' . $emailNote];
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

if (!function_exists('recruitmentMaxUploadBytes')) {
    function recruitmentMaxUploadBytes(): int
    {
        return defined('MAX_FILE_SIZE') ? (int) MAX_FILE_SIZE : 5242880;
    }
}

if (!function_exists('recruitmentGrantAccess')) {
    /** @return array{success:bool,message:string} */
    function recruitmentGrantAccess($conn, int $adminId, string $level, string $grantedBy): array
    {
        ensureRecruitmentAccessTable($conn);
        $level = $level === 'edit' ? 'edit' : 'view';
        if ($adminId <= 0) {
            return ['success' => false, 'message' => 'Select a valid account.'];
        }
        $check = $conn->prepare('SELECT id, username, role FROM admin WHERE id = ? LIMIT 1');
        if (!$check) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $check->bind_param('i', $adminId);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$row) {
            return ['success' => false, 'message' => 'Admin account not found.'];
        }
        if (($row['role'] ?? '') === 'master_admin') {
            return ['success' => false, 'message' => 'Master Admin already has full recruitment access.'];
        }
        $stmt = $conn->prepare(
            'INSERT INTO recruitment_module_access (admin_id, access_level, granted_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE access_level = VALUES(access_level), granted_by = VALUES(granted_by)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('iss', $adminId, $level, $grantedBy);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not grant access.'];
        }
        $label = $level === 'edit' ? 'manage jobs and applications' : 'view applications';
        return ['success' => true, 'message' => 'Recruitment access granted to ' . $row['username'] . ' (' . $label . ').'];
    }
}

if (!function_exists('recruitmentRevokeAccess')) {
    /** @return array{success:bool,message:string} */
    function recruitmentRevokeAccess($conn, int $adminId): array
    {
        ensureRecruitmentAccessTable($conn);
        if ($adminId <= 0) {
            return ['success' => false, 'message' => 'Invalid account.'];
        }
        $stmt = $conn->prepare('DELETE FROM recruitment_module_access WHERE admin_id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('i', $adminId);
        $ok = $stmt->execute();
        $stmt->close();
        return ['success' => $ok, 'message' => $ok ? 'Recruitment access revoked.' : 'Could not revoke access.'];
    }
}

if (!function_exists('listRecruitmentAccessCandidates')) {
    /** @return list<array<string,mixed>> */
    function listRecruitmentAccessCandidates($conn): array
    {
        ensureRecruitmentAccessTable($conn);
        $admins = [];
        $res = $conn->query("SELECT id, username, role, email FROM admin WHERE role <> 'master_admin' ORDER BY username ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $row['grant_level'] = '';
                $row['granted_by'] = '';
                $admins[(int) $row['id']] = $row;
            }
        }
        $gRes = $conn->query('SELECT admin_id, access_level, granted_by FROM recruitment_module_access');
        if ($gRes) {
            while ($g = $gRes->fetch_assoc()) {
                $aid = (int) $g['admin_id'];
                if (!isset($admins[$aid])) {
                    continue;
                }
                $admins[$aid]['grant_level'] = (string) ($g['access_level'] ?? '');
                $admins[$aid]['granted_by'] = (string) ($g['granted_by'] ?? '');
            }
        }
        return array_values($admins);
    }
}

if (!function_exists('recruitmentApplicationFormPdfBytes')) {
    /** @param array<string,mixed> $app */
    function recruitmentApplicationFormPdfBytes(array $app): string
    {
        $renderer = __DIR__ . '/render_recruitment_application_form_pdf.php';
        if (is_file($renderer)) {
            require_once $renderer;
        }
        if (!function_exists('outputRecruitmentApplicationFormPdf')) {
            return '';
        }
        try {
            $pdf = outputRecruitmentApplicationFormPdf($app, 'S');
            return is_string($pdf) && $pdf !== '' ? $pdf : '';
        } catch (Throwable $e) {
            error_log('Recruitment form PDF for email failed: ' . $e->getMessage());
            return '';
        }
    }
}

if (!function_exists('recruitmentFormPdfMailAttachment')) {
    /**
     * @param array<string,mixed> $app
     * @return list<array{filename:string,content:string,mime:string}>
     */
    function recruitmentFormPdfMailAttachment(array $app): array
    {
        $bytes = recruitmentApplicationFormPdfBytes($app);
        if ($bytes === '') {
            return [];
        }
        $no = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($app['application_no'] ?? 'form')) ?: 'form';
        return [[
            'filename' => 'NIELIT_Application_Form_' . $no . '.pdf',
            'content' => $bytes,
            'mime' => 'application/pdf',
        ]];
    }
}

if (!function_exists('recruitmentSendMail')) {
    /**
     * @param list<array{filename?:string,content?:string,mime?:string}> $attachments
     */
    function recruitmentSendMail(string $to, string $name, string $subject, string $html, string $text, array $attachments = []): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $smtp = __DIR__ . '/phpmailer_smtp.php';
        if (is_file($smtp)) {
            require_once $smtp;
        }
        if (!function_exists('sendPhpMailerWithSmtpFallback')) {
            return false;
        }
        $fromEmail = defined('SMTP_FROM_EMAIL') ? (string) SMTP_FROM_EMAIL : '';
        $fromName = defined('SMTP_FROM_NAME') ? (string) SMTP_FROM_NAME : 'NIELIT Bhubaneswar';
        $timeout = $attachments !== [] ? 20 : 12;
        $result = sendPhpMailerWithSmtpFallback(static function ($mail) use ($to, $name, $subject, $html, $text, $fromEmail, $fromName, $attachments) {
            if ($fromEmail !== '') {
                $mail->setFrom($fromEmail, $fromName);
                $mail->addReplyTo($fromEmail, $fromName);
            }
            $mail->addAddress($to, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;
            foreach ($attachments as $att) {
                $bytes = (string) ($att['content'] ?? '');
                if ($bytes === '') {
                    continue;
                }
                $fn = trim((string) ($att['filename'] ?? 'attachment.pdf')) ?: 'attachment.pdf';
                $mime = trim((string) ($att['mime'] ?? 'application/pdf')) ?: 'application/pdf';
                $mail->addStringAttachment($bytes, $fn, 'base64', $mime);
            }
        }, ['timeout' => $timeout]);
        if (!empty($result['ok'])) {
            return true;
        }
        error_log('Recruitment email failed: ' . ($result['error'] ?? 'unknown'));
        return false;
    }
}

if (!function_exists('recruitmentEmailWrap')) {
    function recruitmentEmailWrap(string $heading, string $innerHtml): string
    {
        $year = date('Y');
        $safeHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:0;font-family:Arial,sans-serif;background:#f4f4f4;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:20px;"><tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;">'
            . '<tr><td style="background:#0a1628;padding:28px 24px;text-align:center;">'
            . '<h1 style="color:#fff;margin:0;font-size:22px;">' . $safeHeading . '</h1>'
            . '<p style="color:#e3f2fd;margin:8px 0 0;font-size:13px;">NIELIT Bhubaneswar</p>'
            . '</td></tr><tr><td style="padding:32px 28px;color:#333;font-size:15px;line-height:1.6;">'
            . $innerHtml
            . '</td></tr><tr><td style="background:#f8fafc;padding:16px 28px;color:#64748b;font-size:12px;text-align:center;">'
            . '© ' . $year . ' NIELIT Bhubaneswar. This is an automated message.'
            . '</td></tr></table></td></tr></table></body></html>';
    }
}

if (!function_exists('recruitmentSendThankYouEmail')) {
    /** @param array<string,mixed> $app */
    function recruitmentSendThankYouEmail(array $app): bool
    {
        $name = trim((string) ($app['name'] ?? 'Candidate'));
        $email = trim((string) ($app['email'] ?? ''));
        $no = htmlspecialchars((string) ($app['application_no'] ?? ''), ENT_QUOTES, 'UTF-8');
        $post = htmlspecialchars((string) ($app['job_title'] ?? 'the advertised post'), ENT_QUOTES, 'UTF-8');
        $advt = trim((string) ($app['advt_no'] ?? ''));
        $last = function_exists('recruitmentFormatDate') ? recruitmentFormatDate((string) ($app['last_date'] ?? '')) : '';
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $advtLine = $advt !== '' ? '<p>Advertisement no.: <strong>' . htmlspecialchars($advt, ENT_QUOTES, 'UTF-8') . '</strong></p>' : '';
        $lastLine = ($last !== '' && $last !== '—') ? '<p>Last date of application: <strong>' . htmlspecialchars($last, ENT_QUOTES, 'UTF-8') . '</strong></p>' : '';
        $formUrl = htmlspecialchars(recruitmentApplicationFormUrl($app), ENT_QUOTES, 'UTF-8');
        $attachments = recruitmentFormPdfMailAttachment($app);
        $formNote = $attachments !== []
            ? '<p>Your filled <strong>application form (PDF)</strong> is attached to this email. Please keep a copy for your records. You can also download it using the button below.</p>'
            : '<p>Please download and keep a copy of your filled application form:</p>';
        $inner = '<p>Dear <strong>' . $safeName . '</strong>,</p>'
            . '<p>Thank you for applying for <strong>' . $post . '</strong> at NIELIT Bhubaneswar.</p>'
            . '<p>Your application has been received. Please save your application number:</p>'
            . '<p style="background:#e8f0fe;border-left:4px solid #1a56db;padding:12px 16px;font-size:18px;font-weight:700;">' . $no . '</p>'
            . $advtLine . $lastLine
            . $formNote
            . '<p><a href="' . $formUrl . '" style="display:inline-block;background:#1a56db;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;font-weight:600;">Download / print your application form</a></p>'
            . '<p>We will review your application and contact you by email if you are shortlisted, selected, or if we need more information.</p>'
            . '<p>Regards,<br>Recruitment Cell<br>NIELIT Bhubaneswar</p>';
        $text = "Dear {$name},\n\nThank you for applying for " . strip_tags($post)
            . " at NIELIT Bhubaneswar.\nYour application number is " . strip_tags($no)
            . ($attachments !== [] ? ".\nYour filled application form is attached as a PDF." : '')
            . "\nDownload / print your application form: " . recruitmentApplicationFormUrl($app)
            . "\n\nWe will contact you by email regarding the next steps.\n\nRegards,\nRecruitment Cell, NIELIT Bhubaneswar";
        return recruitmentSendMail(
            $email,
            $name,
            'Thank you for applying — ' . strip_tags($post) . ' | NIELIT Bhubaneswar',
            recruitmentEmailWrap('Thank you for applying', $inner),
            $text,
            $attachments
        );
    }
}

if (!function_exists('recruitmentSendStatusEmail')) {
    /** @param array<string,mixed> $app */
    function recruitmentSendStatusEmail(array $app, string $status, string $remarks): bool
    {
        $name = trim((string) ($app['name'] ?? 'Candidate'));
        $email = trim((string) ($app['email'] ?? ''));
        $post = trim((string) ($app['job_title'] ?? 'the advertised post'));
        $no = trim((string) ($app['application_no'] ?? ''));
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safePost = htmlspecialchars($post, ENT_QUOTES, 'UTF-8');
        $safeNo = htmlspecialchars($no, ENT_QUOTES, 'UTF-8');
        $safeRemarks = htmlspecialchars($remarks, ENT_QUOTES, 'UTF-8');
        $formUrl = htmlspecialchars(recruitmentApplicationFormUrl($app), ENT_QUOTES, 'UTF-8');
        $attachments = recruitmentFormPdfMailAttachment($app);
        $formBlock = ($attachments !== []
                ? '<p>Your filled application form is attached to this email as a PDF.</p>'
                : '')
            . '<p><a href="' . $formUrl . '" style="display:inline-block;background:#1a56db;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;font-weight:600;">Download / print your application form</a></p>';
        $formText = ($attachments !== [] ? "Your filled application form is attached as a PDF.\n" : '')
            . 'Download / print your application form: ' . recruitmentApplicationFormUrl($app) . "\n";
        if ($status === 'shortlisted') {
            $heading = 'You have been shortlisted';
            $subject = 'You have been shortlisted for ' . $post . ' | NIELIT Bhubaneswar';
            $inner = '<p>Dear <strong>' . $safeName . '</strong>,</p>'
                . '<p>You have been <strong>shortlisted</strong> for <strong>' . $safePost . '</strong>.</p>'
                . '<p>Application number: <strong>' . $safeNo . '</strong></p>'
                . ($safeRemarks !== '' ? '<p>Further details:<br>' . nl2br($safeRemarks) . '</p>' : '')
                . $formBlock
                . '<p>Please check your email regularly for interview / next-step instructions.</p>'
                . '<p>Regards,<br>Recruitment Cell<br>NIELIT Bhubaneswar</p>';
            $text = "Dear {$name},\n\nYou have been shortlisted for {$post}.\nApplication number: {$no}\n"
                . ($remarks !== '' ? "Details: {$remarks}\n" : '')
                . $formText
                . "\nRegards,\nRecruitment Cell, NIELIT Bhubaneswar";
        } elseif ($status === 'selected') {
            $heading = 'You have been selected';
            $subject = 'You have been selected for ' . $post . ' | NIELIT Bhubaneswar';
            $inner = '<p>Dear <strong>' . $safeName . '</strong>,</p>'
                . '<p>Congratulations. You have been <strong>selected</strong> for <strong>' . $safePost . '</strong>.</p>'
                . '<p>Application number: <strong>' . $safeNo . '</strong></p>'
                . ($safeRemarks !== '' ? '<p>' . nl2br($safeRemarks) . '</p>' : '')
                . $formBlock
                . '<p>Our team will contact you with joining / further instructions.</p>'
                . '<p>Regards,<br>Recruitment Cell<br>NIELIT Bhubaneswar</p>';
            $text = "Dear {$name},\n\nCongratulations. You have been selected for {$post}.\nApplication number: {$no}\n"
                . ($remarks !== '' ? "{$remarks}\n" : '')
                . $formText
                . "\nRegards,\nRecruitment Cell, NIELIT Bhubaneswar";
        } else {
            $heading = 'Application update';
            $subject = 'Application update — ' . $post . ' | NIELIT Bhubaneswar';
            $inner = '<p>Dear <strong>' . $safeName . '</strong>,</p>'
                . '<p>Thank you for applying for <strong>' . $safePost . '</strong> (application <strong>' . $safeNo . '</strong>).</p>'
                . '<p>We regret to inform you that your application has <strong>not been shortlisted</strong>.</p>'
                . '<p><strong>Basis of rejection:</strong><br>' . ($safeRemarks !== '' ? nl2br($safeRemarks) : 'Not specified.') . '</p>'
                . $formBlock
                . '<p>We appreciate your interest in NIELIT Bhubaneswar and wish you the best for the future.</p>'
                . '<p>Regards,<br>Recruitment Cell<br>NIELIT Bhubaneswar</p>';
            $text = "Dear {$name},\n\nYour application for {$post} ({$no}) has not been shortlisted.\n"
                . "Basis of rejection: " . ($remarks !== '' ? $remarks : 'Not specified.') . "\n"
                . $formText
                . "\nRegards,\nRecruitment Cell, NIELIT Bhubaneswar";
        }
        return recruitmentSendMail($email, $name, $subject, recruitmentEmailWrap($heading, $inner), $text, $attachments);
    }
}

if (!function_exists('ensureRecruitmentEmailQueueTable')) {
    function ensureRecruitmentEmailQueueTable($conn): bool
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $sql = "CREATE TABLE IF NOT EXISTS recruitment_email_queue (
            id INT PRIMARY KEY AUTO_INCREMENT,
            mail_kind VARCHAR(40) NOT NULL,
            to_email VARCHAR(255) NOT NULL,
            to_name VARCHAR(255) NULL,
            payload LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            locked_at DATETIME NULL,
            sent_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_rec_mail_status (status, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($sql)) {
            error_log('ensureRecruitmentEmailQueueTable: ' . $conn->error);
            return false;
        }
        return true;
    }
}

if (!function_exists('recruitmentMailWorkerSecret')) {
    function recruitmentMailWorkerSecret(): string
    {
        return hash('sha256', (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'nielit') . '|recruitment-mail|' . (defined('APP_URL') ? APP_URL : ''));
    }
}

if (!function_exists('recruitmentMailWorkerUrl')) {
    function recruitmentMailWorkerUrl(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if (strlen($dir) >= 6 && substr($dir, -6) === '/admin') {
            $dir = substr($dir, 0, -6) . '/public';
        }
        if ($dir === '' || $dir === '/' || $dir === '.') {
            $dir = '/public';
        } elseif (substr($dir, -7) !== '/public') {
            $dir = rtrim($dir, '/') . '/public';
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
            return ($https ? 'https' : 'http') . '://' . $host . $dir . '/recruitment_mail_worker.php';
        }
        return rtrim((string) (defined('APP_URL') ? APP_URL : ''), '/') . '/public/recruitment_mail_worker.php';
    }
}

if (!function_exists('recruitmentKickMailWorker')) {
    function recruitmentKickMailWorker(): void
    {
        static $kicked = false;
        if ($kicked) {
            return;
        }
        $kicked = true;
        $url = recruitmentMailWorkerUrl();
        $token = recruitmentMailWorkerSecret();
        if ($url === '' || !function_exists('curl_init')) {
            register_shutdown_function(static function () {
                $conn = recruitmentDb();
                if ($conn) {
                    recruitmentProcessMailQueue($conn, 5);
                }
            });
            return;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['token' => $token]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 250,
            CURLOPT_TIMEOUT_MS => 450,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        @curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        // 0 = worker replied, 28 = timed out (worker still sending). Anything else: send after this page finishes.
        if ($errno !== 0 && $errno !== 28) {
            register_shutdown_function(static function () {
                $conn = recruitmentDb();
                if ($conn) {
                    recruitmentProcessMailQueue($conn, 5);
                }
            });
        }
    }
}

if (!function_exists('recruitmentQueueMail')) {
    /**
     * @param array<string,mixed> $payload
     */
    function recruitmentQueueMail($conn, string $kind, string $toEmail, string $toName, array $payload): bool
    {
        $conn = recruitmentDb($conn);
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        ensureRecruitmentEmailQueueTable($conn);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return false;
        }
        $status = 'pending';
        $stmt = $conn->prepare(
            'INSERT INTO recruitment_email_queue (mail_kind, to_email, to_name, payload, status) VALUES (?,?,?,?,?)'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sssss', $kind, $toEmail, $toName, $json, $status);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            recruitmentKickMailWorker();
        }
        return $ok;
    }
}

if (!function_exists('recruitmentQueueThankYouEmail')) {
    /** @param array<string,mixed> $app */
    function recruitmentQueueThankYouEmail($conn, array $app): bool
    {
        return recruitmentQueueMail(
            $conn,
            'thankyou',
            (string) ($app['email'] ?? ''),
            (string) ($app['name'] ?? ''),
            $app
        );
    }
}

if (!function_exists('recruitmentQueueStatusEmail')) {
    /** @param array<string,mixed> $app */
    function recruitmentQueueStatusEmail($conn, array $app, string $status, string $remarks): bool
    {
        return recruitmentQueueMail(
            $conn,
            'status',
            (string) ($app['email'] ?? ''),
            (string) ($app['name'] ?? ''),
            ['app' => $app, 'status' => $status, 'remarks' => $remarks]
        );
    }
}

if (!function_exists('recruitmentDeliverQueuedMail')) {
    /** @param array<string,mixed> $row */
    function recruitmentDeliverQueuedMail(array $row): bool
    {
        $kind = (string) ($row['mail_kind'] ?? '');
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            return false;
        }
        if ($kind === 'thankyou') {
            return recruitmentSendThankYouEmail($payload);
        }
        if ($kind === 'status') {
            $app = is_array($payload['app'] ?? null) ? $payload['app'] : $payload;
            return recruitmentSendStatusEmail(
                $app,
                (string) ($payload['status'] ?? ''),
                (string) ($payload['remarks'] ?? '')
            );
        }
        return false;
    }
}

if (!function_exists('recruitmentProcessMailQueue')) {
    function recruitmentProcessMailQueue($conn, int $limit = 8): int
    {
        $conn = recruitmentDb($conn);
        if (!($conn instanceof mysqli)) {
            return 0;
        }
        ensureRecruitmentEmailQueueTable($conn);
        $limit = max(1, min(20, $limit));
        $res = $conn->query(
            "SELECT id FROM recruitment_email_queue
             WHERE (status = 'pending' OR (status IN ('failed','sending') AND attempts < 5
                    AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 3 MINUTE))))
             ORDER BY id ASC
             LIMIT {$limit}"
        );
        if (!$res) {
            return 0;
        }
        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
        $done = 0;
        foreach ($ids as $id) {
            $lock = $conn->prepare(
                "UPDATE recruitment_email_queue
                 SET status = 'sending', locked_at = NOW(), attempts = attempts + 1
                 WHERE id = ? AND status IN ('pending','failed','sending')"
            );
            if (!$lock) {
                continue;
            }
            $lock->bind_param('i', $id);
            $lock->execute();
            $took = $lock->affected_rows > 0;
            $lock->close();
            if (!$took) {
                continue;
            }
            $get = $conn->prepare('SELECT * FROM recruitment_email_queue WHERE id = ? LIMIT 1');
            if (!$get) {
                continue;
            }
            $get->bind_param('i', $id);
            $get->execute();
            $job = $get->get_result()->fetch_assoc();
            $get->close();
            if (!$job) {
                continue;
            }
            $ok = false;
            $err = '';
            try {
                $ok = recruitmentDeliverQueuedMail($job);
                if (!$ok) {
                    $err = 'Send returned false';
                }
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
            if ($ok) {
                $upd = $conn->prepare("UPDATE recruitment_email_queue SET status = 'sent', sent_at = NOW(), last_error = NULL WHERE id = ?");
                if ($upd) {
                    $upd->bind_param('i', $id);
                    $upd->execute();
                    $upd->close();
                }
                $done++;
            } else {
                $upd = $conn->prepare("UPDATE recruitment_email_queue SET status = 'failed', last_error = ? WHERE id = ?");
                if ($upd) {
                    $upd->bind_param('si', $err, $id);
                    $upd->execute();
                    $upd->close();
                }
            }
        }
        return $done;
    }
}


