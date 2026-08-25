<?php
/**
 * Student self-service fingerprint kiosk helper.
 *
 * Provides:
 *  - An allow-list of static IPs (managed by master admin). The student
 *    self-registration / self-attendance page only works from an allowed IP.
 *  - Student lookup by Student ID / Aadhaar / mobile.
 *  - Email OTP generation + verification (identity proof before fingerprint).
 *  - Active attendance-session lookup for a verified student.
 */

require_once __DIR__ . '/activity_logger.php';

if (!function_exists('ensureStudentKioskTables')) {
    function ensureStudentKioskTables($conn): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS student_kiosk_allowed_ips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(64) NOT NULL,
            label VARCHAR(150) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_kiosk_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        return (bool) $conn->query($sql);
    }
}

if (!function_exists('studentKioskClientIp')) {
    function studentKioskClientIp(): string
    {
        if (function_exists('activityClientIp')) {
            $ip = activityClientIp();
            if ($ip !== '') {
                return $ip;
            }
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}

if (!function_exists('studentKioskBackgroundSlides')) {
    /**
     * Full-screen kiosk backgrounds: generated kiosk art first, then campus banners.
     * @return array<int,array{url:string,alt:string}>
     */
    function studentKioskBackgroundSlides(): array
    {
        $root = dirname(__DIR__);
        $groups = [
            ['dir' => $root . '/assets/images/kiosk', 'alt' => 'NIELIT fingerprint kiosk'],
            ['dir' => $root . '/assets/images/banners', 'alt' => 'NIELIT Bhubaneswar campus'],
        ];
        $slides = [];
        $exts = ['jpg', 'jpeg', 'png', 'webp'];
        foreach ($groups as $group) {
            if (!is_dir($group['dir'])) {
                continue;
            }
            $files = [];
            foreach ($exts as $ext) {
                $found = glob($group['dir'] . DIRECTORY_SEPARATOR . '*.' . $ext);
                if (is_array($found)) {
                    $files = array_merge($files, $found);
                }
            }
            natsort($files);
            foreach ($files as $file) {
                $rel = str_replace('\\', '/', substr((string) $file, strlen($root) + 1));
                $url = function_exists('app_url') ? app_url($rel) : ('../' . $rel);
                $slides[] = ['url' => $url, 'alt' => $group['alt']];
            }
        }
        return $slides;
    }
}

if (!function_exists('studentKioskListIps')) {
    /** @return array<int,array<string,mixed>> */
    function studentKioskListIps($conn): array
    {
        ensureStudentKioskTables($conn);
        $rows = [];
        $res = $conn->query("SELECT id, ip_address, label, is_active, created_by, created_at FROM student_kiosk_allowed_ips ORDER BY created_at DESC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('studentKioskIpAllowed')) {
    /**
     * Default deny: the page is blocked until the master admin adds the static IP.
     */
    function studentKioskIpAllowed($conn, string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            return false;
        }
        ensureStudentKioskTables($conn);
        $stmt = $conn->prepare("SELECT id FROM student_kiosk_allowed_ips WHERE ip_address = ? AND is_active = 1 LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $hit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return !empty($hit);
    }
}

if (!function_exists('studentKioskAddIp')) {
    /** @return array{success:bool,message:string} */
    function studentKioskAddIp($conn, string $ip, string $label, string $by): array
    {
        ensureStudentKioskTables($conn);
        $ip = trim($ip);
        $label = trim($label);
        if ($ip === '' || (filter_var($ip, FILTER_VALIDATE_IP) === false)) {
            return ['success' => false, 'message' => 'Enter a valid IPv4/IPv6 address.'];
        }
        $stmt = $conn->prepare(
            "INSERT INTO student_kiosk_allowed_ips (ip_address, label, is_active, created_by)
             VALUES (?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE label = VALUES(label), is_active = 1"
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('sss', $ip, $label, $by);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok
            ? ['success' => true, 'message' => 'Allowed IP saved.']
            : ['success' => false, 'message' => 'Could not save IP.'];
    }
}

if (!function_exists('studentKioskSetIpActive')) {
    function studentKioskSetIpActive($conn, int $id, bool $active): bool
    {
        ensureStudentKioskTables($conn);
        $val = $active ? 1 : 0;
        $stmt = $conn->prepare("UPDATE student_kiosk_allowed_ips SET is_active = ? WHERE id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $val, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('studentKioskDeleteIp')) {
    function studentKioskDeleteIp($conn, int $id): bool
    {
        ensureStudentKioskTables($conn);
        $stmt = $conn->prepare("DELETE FROM student_kiosk_allowed_ips WHERE id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('studentKioskDigits')) {
    function studentKioskDigits(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }
}

if (!function_exists('studentKioskStmtAssoc')) {
    /** @return array<string,mixed>|null */
    function studentKioskStmtAssoc(mysqli_stmt $stmt): ?array
    {
        $res = $stmt->get_result();
        if (!($res instanceof mysqli_result)) {
            return null;
        }
        $row = $res->fetch_assoc();
        return $row ?: null;
    }
}

if (!function_exists('studentKioskLookup')) {
    /**
     * Find a student by Student ID, Aadhaar number, or mobile number.
     * Checks `students` then `student_accounts` (multi-course).
     * @return array{ok:bool,message:string,row?:array<string,mixed>}
     */
    function studentKioskLookup($conn, string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return ['ok' => false, 'message' => 'Enter your Student ID, Aadhaar, or mobile number.'];
        }
        $digits = studentKioskDigits($identifier);
        $mobile10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        try {
            $row = null;
            $sql = "SELECT student_id, name, email, aadhar, mobile, course_id, status, passport_photo
                    FROM students
                    WHERE LOWER(TRIM(student_id)) = LOWER(?)
                       OR RIGHT(REPLACE(REPLACE(REPLACE(IFNULL(aadhar,''), ' ', ''), '-', ''), '/', ''), 12) = ?
                       OR RIGHT(REPLACE(REPLACE(REPLACE(IFNULL(mobile,''), ' ', ''), '-', ''), '+', ''), 10) = ?
                    ORDER BY id DESC
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $aadhaarKey = strlen($digits) === 12 ? $digits : $digits;
                $stmt->bind_param('sss', $identifier, $aadhaarKey, $mobile10);
                $stmt->execute();
                $row = studentKioskStmtAssoc($stmt);
                $stmt->close();
            }

            $accCheck = $conn->query("SHOW TABLES LIKE 'student_accounts'");
            if (!$row && $accCheck && $accCheck->num_rows > 0) {
                $sql = "SELECT student_id, name, email, aadhar, mobile, 'active' AS status
                        FROM student_accounts
                        WHERE LOWER(TRIM(student_id)) = LOWER(?)
                           OR RIGHT(REPLACE(REPLACE(REPLACE(IFNULL(aadhar,''), ' ', ''), '-', ''), '/', ''), 12) = ?
                           OR RIGHT(REPLACE(REPLACE(REPLACE(IFNULL(mobile,''), ' ', ''), '-', ''), '+', ''), 10) = ?
                        ORDER BY id DESC
                        LIMIT 1";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $aadhaarKey = strlen($digits) === 12 ? $digits : $digits;
                    $stmt->bind_param('sss', $identifier, $aadhaarKey, $mobile10);
                    $stmt->execute();
                    $acc = studentKioskStmtAssoc($stmt);
                    $stmt->close();
                    if ($acc) {
                        $acc['course_id'] = 0;
                        $row = $acc;
                    }
                }
            }

            if ($row && function_exists('getAccountByStudentId')) {
                $account = getAccountByStudentId($conn, (string) ($row['student_id'] ?? ''));
                if (is_array($account)) {
                    $accountEmail = trim((string) ($account['email'] ?? ''));
                    if ($accountEmail !== '' && filter_var($accountEmail, FILTER_VALIDATE_EMAIL)) {
                        $row['email'] = $accountEmail;
                    }
                    if (trim((string) ($row['mobile'] ?? '')) === '' && !empty($account['mobile'])) {
                        $row['mobile'] = $account['mobile'];
                    }
                    if (trim((string) ($row['aadhar'] ?? '')) === '' && !empty($account['aadhar'])) {
                        $row['aadhar'] = $account['aadhar'];
                    }
                    if (trim((string) ($row['name'] ?? '')) === '' && !empty($account['name'])) {
                        $row['name'] = $account['name'];
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('studentKioskLookup: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not look up that student. Try your Student ID.'];
        }

        if (!$row) {
            return ['ok' => false, 'message' => 'No student found for that Student ID / Aadhaar / mobile.'];
        }
        return ['ok' => true, 'message' => 'ok', 'row' => $row];
    }
}

if (!function_exists('studentKioskMaskAadhaar')) {
    function studentKioskMaskAadhaar(string $aadhar): string
    {
        $digits = preg_replace('/\D/', '', $aadhar);
        if (strlen((string) $digits) < 4) {
            return 'not on file';
        }
        return 'XXXX XXXX ' . substr((string) $digits, -4);
    }
}

if (!function_exists('studentKioskMaskMobile')) {
    function studentKioskMaskMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile);
        if (strlen((string) $digits) < 4) {
            return 'not on file';
        }
        return substr((string) $digits, 0, 2) . str_repeat('*', max(0, strlen((string) $digits) - 4)) . substr((string) $digits, -2);
    }
}

if (!function_exists('studentKioskResolvePhotoUrl')) {
    /**
     * Public URL for the student's passport photo, or '' if none is on file.
     */
    function studentKioskResolvePhotoUrl($conn, string $studentId, array $row = []): string
    {
        if (function_exists('biometricStudentPhotoUrl') && trim((string) ($row['passport_photo'] ?? '')) !== '') {
            $url = biometricStudentPhotoUrl($row);
            if ($url !== '') {
                return $url;
            }
        }
        $studentId = trim($studentId);
        if ($studentId === '' || !($conn instanceof mysqli)) {
            return '';
        }
        $col = $conn->query("SHOW COLUMNS FROM students LIKE 'passport_photo'");
        if (!$col || $col->num_rows === 0) {
            return '';
        }
        $stmt = $conn->prepare('SELECT passport_photo FROM students
            WHERE LOWER(TRIM(student_id)) = LOWER(?) AND IFNULL(passport_photo, \'\') <> \'\'
            ORDER BY id DESC LIMIT 1');
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $hit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$hit || !function_exists('biometricStudentPhotoUrl')) {
            return '';
        }
        return biometricStudentPhotoUrl($hit);
    }
}

if (!function_exists('studentKioskPublicDetails')) {
    /**
     * Safe student card shown after OTP (no full Aadhaar).
     * @param array<string,mixed> $row
     * @return array<string,string>
     */
    function studentKioskPublicDetails($conn, array $row): array
    {
        return [
            'student_id' => (string) ($row['student_id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'email_masked' => studentKioskMaskEmail((string) ($row['email'] ?? '')),
            'mobile_masked' => studentKioskMaskMobile((string) ($row['mobile'] ?? '')),
            'aadhaar_masked' => studentKioskMaskAadhaar((string) ($row['aadhar'] ?? '')),
            'photo' => studentKioskResolvePhotoUrl($conn, (string) ($row['student_id'] ?? ''), $row),
        ];
    }
}

if (!function_exists('studentKioskFindEnrolledByAadhaarLast6')) {
    /**
     * Existing (already fingerprint-enrolled) students whose Aadhaar ends with these 6 digits.
     * @return array{ok:bool,message:string,candidates:array<int,array{student_id:string,name:string,iso_template:string}>}
     */
    function studentKioskFindEnrolledByAadhaarLast6($conn, string $last6): array
    {
        $last6 = preg_replace('/\D/', '', $last6);
        if (strlen((string) $last6) !== 6) {
            return ['ok' => false, 'message' => 'Enter the last 6 digits of your Aadhaar.', 'candidates' => []];
        }
        ensureFingerprintTemplateTables($conn);
        $sql = "SELECT s.student_id, s.name, s.aadhar, s.status
                FROM students s
                INNER JOIN student_fingerprint_templates t ON t.student_id = s.student_id
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(IFNULL(s.aadhar, ''), ' ', ''), '-', ''), '/', ''), 6) = ?
                GROUP BY s.student_id, s.name, s.aadhar, s.status
                LIMIT 8";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Database error.', 'candidates' => []];
        }
        $stmt->bind_param('s', $last6);
        $stmt->execute();
        $res = $stmt->get_result();
        $candidates = [];
        while ($row = $res->fetch_assoc()) {
            $status = strtolower(trim((string) ($row['status'] ?? 'active')));
            if ($status !== '' && !in_array($status, ['active', 'approved'], true)) {
                continue;
            }
            $sid = trim((string) ($row['student_id'] ?? ''));
            if ($sid === '' || !function_exists('loadStudentFingerprintTemplate')) {
                continue;
            }
            $iso = loadStudentFingerprintTemplate($conn, $sid);
            if ($iso === '') {
                continue;
            }
            $candidates[] = [
                'student_id' => $sid,
                'name' => (string) ($row['name'] ?? ''),
                'iso_template' => $iso,
            ];
        }
        $stmt->close();
        if (empty($candidates)) {
            return [
                'ok' => false,
                'message' => 'No registered fingerprint found for this Aadhaar. Use New registration first (OTP + fingerprint).',
                'candidates' => [],
            ];
        }
        $_SESSION['kiosk_last6'] = $last6;
        $_SESSION['kiosk_last6_at'] = time();
        return ['ok' => true, 'message' => 'ok', 'candidates' => $candidates];
    }
}

if (!function_exists('studentKioskAadhaarLast6Matches')) {
    function studentKioskAadhaarLast6Matches(string $aadhar, string $last6): bool
    {
        $digits = preg_replace('/\D/', '', $aadhar);
        $last6 = preg_replace('/\D/', '', $last6);
        if (strlen((string) $digits) < 6 || strlen((string) $last6) !== 6) {
            return false;
        }
        return hash_equals(substr((string) $digits, -6), (string) $last6);
    }
}

if (!function_exists('studentKioskMaskEmail')) {
    function studentKioskMaskEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || strpos($email, '@') === false) {
            return 'your registered email';
        }
        [$local, $domain] = explode('@', $email, 2);
        $len = strlen($local);
        $head = substr($local, 0, min(2, $len));
        $tail = $len > 4 ? substr($local, -2) : '';
        $stars = max(3, $len - strlen($head) - strlen($tail));
        return $head . str_repeat('*', $stars) . $tail . '@' . $domain;
    }
}

if (!function_exists('studentKioskEmailsForOtp')) {
    /**
     * Unique valid emails from the kiosk row, students, and student_accounts.
     * Admin login OTP goes to a different mailbox than the course record for some students.
     * @return list<string>
     */
    function studentKioskEmailsForOtp($conn, array $studentRow): array
    {
        $emails = [];
        $add = static function ($raw) use (&$emails): void {
            $email = strtolower(trim((string) $raw));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && !in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        };
        $add($studentRow['email'] ?? '');
        $studentId = trim((string) ($studentRow['student_id'] ?? ''));
        if ($studentId === '' || !($conn instanceof mysqli)) {
            return $emails;
        }
        $stmt = $conn->prepare('SELECT email FROM students WHERE LOWER(TRIM(student_id)) = LOWER(?) ORDER BY id DESC LIMIT 8');
        if ($stmt) {
            $stmt->bind_param('s', $studentId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $add($r['email'] ?? '');
                }
            }
            $stmt->close();
        }
        $tbl = $conn->query("SHOW TABLES LIKE 'student_accounts'");
        if ($tbl && $tbl->num_rows > 0) {
            $stmt = $conn->prepare('SELECT email FROM student_accounts WHERE LOWER(TRIM(student_id)) = LOWER(?) ORDER BY id DESC LIMIT 8');
            if ($stmt) {
                $stmt->bind_param('s', $studentId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    while ($r = $res->fetch_assoc()) {
                        $add($r['email'] ?? '');
                    }
                }
                $stmt->close();
            }
        }
        return $emails;
    }
}

if (!function_exists('studentKioskSendOtp')) {
    /**
     * Generate + email a 6-digit OTP to every email on file for this student.
     * @return array{success:bool,message:string,masked?:string}
     */
    function studentKioskSendOtp($conn, array $studentRow): array
    {
        $studentId = (string) ($studentRow['student_id'] ?? '');
        $emails = studentKioskEmailsForOtp($conn, $studentRow);
        if ($emails === []) {
            return ['success' => false, 'message' => 'No valid email is on file for this student. Contact the office.'];
        }

        $otp = (string) random_int(100000, 999999);
        $_SESSION['kiosk_otp'] = $otp;
        $_SESSION['kiosk_otp_student'] = $studentId;
        $_SESSION['kiosk_otp_email'] = $emails[0];
        $_SESSION['kiosk_otp_time'] = time();
        unset($_SESSION['kiosk_verified_student'], $_SESSION['kiosk_verified_at']);

        if (!function_exists('sendPhpMailerWithSmtpFallback')) {
            return ['success' => false, 'message' => 'Could not send OTP email. Mailer is not available.'];
        }

        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreplay@nielitbhubaneswar.in';
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'NIELIT Bhubaneswar';
        $okEmails = [];
        $errors = [];

        foreach ($emails as $email) {
            // Same authenticated Hostinger path as admin login OTP (that Gmail already receives).
            $sent = sendPhpMailerWithSmtpFallback(static function ($mail) use ($email, $otp, $fromEmail, $fromName) {
                $mail->setFrom($fromEmail, $fromName);
                $mail->addReplyTo($fromEmail, $fromName);
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Your OTP for Student Login - NIELIT Bhubaneswar';
                $mail->Body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;background:#f8fafc;border-radius:10px;">'
                    . '<div style="background:linear-gradient(135deg,#0a1628,#112240);padding:26px;text-align:center;border-radius:10px 10px 0 0;">'
                    . '<h2 style="color:#fff;margin:0;">NIELIT Bhubaneswar</h2>'
                    . '<p style="color:rgba(255,255,255,.9);margin:8px 0 0;">Student Fingerprint Verification</p></div>'
                    . '<div style="background:#fff;padding:28px;border-radius:0 0 10px 10px;">'
                    . '<p style="font-size:16px;color:#1e293b;">Dear Student,</p>'
                    . '<p style="font-size:14px;color:#64748b;">Your One-Time Password (OTP) is:</p>'
                    . '<div style="background:#f1f5f9;padding:18px;text-align:center;border-radius:8px;margin:16px 0;">'
                    . '<h1 style="color:#0a1628;margin:0;font-size:34px;letter-spacing:8px;">' . htmlspecialchars($otp) . '</h1></div>'
                    . '<p style="font-size:13px;color:#64748b;">Valid for 10 minutes. Do not share this code with anyone.</p></div></div>';
                $mail->AltBody = 'Your OTP for NIELIT Bhubaneswar fingerprint registration is: ' . $otp . ' (valid 10 minutes).';
            }, ['timeout' => 25, 'authenticated_only' => true]);

            $ok = !empty($sent['ok']);
            if ($ok) {
                $okEmails[] = $email;
            } else {
                $errors[] = $email . ': ' . trim((string) ($sent['error'] ?? 'send failed'));
            }
            if (function_exists('logOTP')) {
                $purpose = 'Student Fingerprint Kiosk';
                if (!empty($sent['profile'])) {
                    $purpose .= ' [' . $sent['profile'] . ']';
                }
                logOTP($email, $otp, $purpose, $studentId, $ok ? 'sent' : 'failed');
            }
        }

        if ($okEmails !== []) {
            $_SESSION['kiosk_otp_email'] = $okEmails[0];
            $masks = array_map('studentKioskMaskEmail', $okEmails);
            return [
                'success' => true,
                'message' => 'OTP sent.',
                'masked' => implode(' and ', $masks),
            ];
        }

        $detail = implode(' | ', $errors);
        if ($detail !== '') {
            $detail = ' (' . substr(preg_replace('/\s+/', ' ', $detail), 0, 180) . ')';
        }
        return ['success' => false, 'message' => 'Could not send OTP email. Try again or contact the office.' . $detail];
    }
}

if (!function_exists('studentKioskVerifyOtp')) {
    /**
     * @return array{success:bool,message:string,student_id?:string}
     */
    function studentKioskVerifyOtp(string $inputOtp): array
    {
        $inputOtp = preg_replace('/\D/', '', $inputOtp);
        $expiry = defined('OTP_EXPIRY_TIME') ? (int) OTP_EXPIRY_TIME : 600;
        if (empty($_SESSION['kiosk_otp']) || empty($_SESSION['kiosk_otp_time'])) {
            return ['success' => false, 'message' => 'No OTP was requested. Start again.'];
        }
        if ((time() - (int) $_SESSION['kiosk_otp_time']) > $expiry) {
            unset($_SESSION['kiosk_otp'], $_SESSION['kiosk_otp_time']);
            return ['success' => false, 'message' => 'OTP expired. Request a new one.'];
        }
        if (!hash_equals((string) $_SESSION['kiosk_otp'], (string) $inputOtp)) {
            return ['success' => false, 'message' => 'Incorrect OTP. Try again.'];
        }
        $studentId = (string) ($_SESSION['kiosk_otp_student'] ?? '');
        $_SESSION['kiosk_verified_student'] = $studentId;
        $_SESSION['kiosk_verified_at'] = time();
        unset($_SESSION['kiosk_otp'], $_SESSION['kiosk_otp_time']);
        return ['success' => true, 'message' => 'Verified.', 'student_id' => $studentId];
    }
}

if (!function_exists('studentKioskVerifiedStudent')) {
    /**
     * Returns the verified student id if the OTP session is still valid, else ''.
     */
    function studentKioskVerifiedStudent(): string
    {
        $expiry = defined('OTP_EXPIRY_TIME') ? (int) OTP_EXPIRY_TIME : 600;
        $sid = (string) ($_SESSION['kiosk_verified_student'] ?? '');
        $at = (int) ($_SESSION['kiosk_verified_at'] ?? 0);
        // Verified identity is good for a short window (default 15 min) at the kiosk.
        if ($sid === '' || $at <= 0 || (time() - $at) > max($expiry, 900)) {
            return '';
        }
        return $sid;
    }
}

if (!function_exists('studentKioskClearVerification')) {
    function studentKioskClearVerification(): void
    {
        unset(
            $_SESSION['kiosk_otp'],
            $_SESSION['kiosk_otp_student'],
            $_SESSION['kiosk_otp_email'],
            $_SESSION['kiosk_otp_time'],
            $_SESSION['kiosk_verified_student'],
            $_SESSION['kiosk_verified_at'],
            $_SESSION['kiosk_last6'],
            $_SESSION['kiosk_last6_at']
        );
    }
}

if (!function_exists('studentKioskActiveSessionsForStudent')) {
    /**
     * Active attendance sessions for every course the student is enrolled in
     * (one latest session per course).
     * @return array{ok:bool,message:string,sessions?:array<int,array<string,mixed>>}
     */
    function studentKioskActiveSessionsForStudent($conn, string $studentId): array
    {
        $studentId = trim($studentId);
        if ($studentId === '') {
            return ['ok' => false, 'message' => 'Verify your identity first.', 'sessions' => []];
        }
        $courseIds = function_exists('attendanceStudentCourseIds')
            ? attendanceStudentCourseIds($conn, $studentId)
            : [];
        if ($courseIds === []) {
            $stmt = $conn->prepare("SELECT DISTINCT course_id FROM students WHERE student_id = ? AND course_id IS NOT NULL AND course_id > 0");
            if ($stmt) {
                $stmt->bind_param('s', $studentId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $cid = (int) ($r['course_id'] ?? 0);
                    if ($cid > 0) {
                        $courseIds[] = $cid;
                    }
                }
                $stmt->close();
            }
        }
        if ($courseIds === []) {
            return ['ok' => false, 'message' => 'No course enrolment found for you.', 'sessions' => []];
        }
        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
        $types = str_repeat('i', count($courseIds));
        $sql = "SELECT id, session_name, course_id, course_name FROM attendance_sessions
                WHERE status = 'active' AND course_id IN ($placeholders)
                ORDER BY date DESC, id DESC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Database error.', 'sessions' => []];
        }
        $stmt->bind_param($types, ...$courseIds);
        $stmt->execute();
        $res = $stmt->get_result();
        $byCourse = [];
        while ($row = $res->fetch_assoc()) {
            $cid = (int) ($row['course_id'] ?? 0);
            if ($cid > 0 && !isset($byCourse[$cid])) {
                $byCourse[$cid] = $row;
            }
        }
        $stmt->close();
        $sessions = array_values($byCourse);
        if ($sessions === []) {
            return ['ok' => false, 'message' => 'No active attendance session for your class right now. Ask your coordinator to start one.', 'sessions' => []];
        }
        return ['ok' => true, 'message' => 'ok', 'sessions' => $sessions];
    }
}

if (!function_exists('studentKioskActiveSessionForStudent')) {
    /**
     * Find an active attendance session that the student belongs to (by any enrolled course).
     * @return array{ok:bool,message:string,session_id?:int,session_name?:string}
     */
    function studentKioskActiveSessionForStudent($conn, string $studentId): array
    {
        $found = studentKioskActiveSessionsForStudent($conn, $studentId);
        if (empty($found['ok']) || empty($found['sessions'][0])) {
            return ['ok' => false, 'message' => (string) ($found['message'] ?? 'No active session.')];
        }
        $row = $found['sessions'][0];
        return [
            'ok' => true,
            'message' => 'ok',
            'session_id' => (int) $row['id'],
            'session_name' => (string) ($row['session_name'] ?? ''),
            'sessions' => $found['sessions'],
        ];
    }
}

if (!function_exists('studentKioskPunchAllActiveSessions')) {
    /**
     * Mark IN/OUT on every active session for the student's enrolled courses.
     * @param array<string,mixed> $lookRow
     * @return array<string,mixed>
     */
    function studentKioskPunchAllActiveSessions(
        $conn,
        string $studentId,
        string $iso,
        string $aadhaarLast4,
        bool $clientMatched,
        array $lookRow = []
    ): array {
        $found = studentKioskActiveSessionsForStudent($conn, $studentId);
        if (empty($found['ok']) || empty($found['sessions'])) {
            return ['success' => false, 'message' => (string) ($found['message'] ?? 'No active session.')];
        }
        $parts = [];
        $okResult = null;
        $failResult = null;
        foreach ($found['sessions'] as $sess) {
            $result = processBiometricMatchAttendanceFromIso(
                $conn,
                (int) ($sess['id'] ?? 0),
                $studentId,
                'self:' . $studentId,
                $iso,
                $aadhaarLast4,
                null,
                $clientMatched
            );
            if (is_array($result) && !empty($result['success'])) {
                $kind = (($result['scan_type'] ?? '') === 'out') ? 'OUT' : 'IN';
                $label = trim((string) ($sess['session_name'] ?? $sess['course_name'] ?? ''));
                $time = trim((string) ($result['scan_time'] ?? ''));
                $parts[] = $kind . ($label !== '' ? (' · ' . $label) : '') . ($time !== '' ? (' at ' . $time) : '');
                $okResult = $result;
            } elseif ($failResult === null) {
                $failResult = is_array($result) ? $result : ['success' => false, 'message' => 'Could not save attendance.'];
            }
        }
        if ($okResult === null) {
            return is_array($failResult) ? $failResult : ['success' => false, 'message' => 'Could not save attendance.'];
        }
        $okResult['name'] = (string) ($lookRow['name'] ?? ($okResult['name'] ?? ''));
        $okResult['student_id'] = $studentId;
        if (function_exists('studentKioskResolvePhotoUrl')) {
            $okResult['photo'] = studentKioskResolvePhotoUrl($conn, $studentId, $lookRow);
        }
        if ($parts !== []) {
            $okResult['message'] = implode('; ', $parts);
        }
        return $okResult;
    }
}
