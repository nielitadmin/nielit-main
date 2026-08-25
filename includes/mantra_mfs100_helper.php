<?php
/**
 * Mantra MFS110 Client Service — ISO template enrol and 1:1 match.
 * Does not use Aadhaar RD PidData. Templates are encrypted at rest.
 */

if (!function_exists('lookupStudentForFingerprintEnrol')) {
    /**
     * @return array{ok:bool,row?:array<string,mixed>,message:string}
     */
    function lookupStudentForFingerprintEnrol($conn, string $query): array
    {
        $query = trim($query);
        if ($query === '' || !($conn instanceof mysqli)) {
            return ['ok' => false, 'message' => 'Type the Student ID.'];
        }
        $row = null;
        if (function_exists('attendanceFindStudentForSession')) {
            $row = attendanceFindStudentForSession($conn, $query, 0);
        }
        if (!$row) {
            $digits = preg_replace('/\D/', '', $query);
            if (strlen($digits) === 10) {
                $stmt = $conn->prepare("SELECT student_id FROM students
                    WHERE REPLACE(REPLACE(IFNULL(mobile,''),' ',''),'-','') = ?
                    ORDER BY id DESC LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('s', $digits);
                    $stmt->execute();
                    $hit = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($hit && function_exists('attendanceFindStudentForSession')) {
                        $row = attendanceFindStudentForSession($conn, (string) $hit['student_id'], 0);
                    }
                }
            }
        }
        if (!$row) {
            return ['ok' => false, 'message' => 'No student found with that ID. Type the full Student ID.'];
        }
        return ['ok' => true, 'row' => $row, 'message' => 'ok'];
    }
}

if (!function_exists('mantraMfs100KeyPath')) {
    function mantraMfs100KeyPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'keys' . DIRECTORY_SEPARATOR . 'fingerprint.key';
    }
}

if (!function_exists('ensureFingerprintTemplateTables')) {
    function ensureFingerprintTemplateTables($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $sql = "CREATE TABLE IF NOT EXISTS student_fingerprint_templates (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id VARCHAR(50) NOT NULL,
            finger_code VARCHAR(8) NOT NULL DEFAULT 'R1',
            template_cipher LONGBLOB NOT NULL,
            template_iv VARBINARY(16) NOT NULL,
            template_tag VARBINARY(16) NOT NULL,
            quality INT NULL,
            enrolled_by VARCHAR(80) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_student_finger (student_id, finger_code),
            KEY idx_fp_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($sql)) {
            error_log('ensureFingerprintTemplateTables failed: ' . $conn->error);
            return false;
        }
        $ready = true;
        return true;
    }
}

if (!function_exists('mantraMfs100CryptoKey')) {
    function mantraMfs100CryptoKey(): string
    {
        $path = mantraMfs100KeyPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (!is_file($path)) {
            $bytes = random_bytes(32);
            @file_put_contents($path, $bytes, LOCK_EX);
            @chmod($path, 0600);
        }
        $key = (string) @file_get_contents($path);
        if (strlen($key) < 32) {
            throw new RuntimeException('Fingerprint encryption key is missing. Ask an administrator to create storage/keys/fingerprint.key.');
        }
        return substr($key, 0, 32);
    }
}

if (!function_exists('mantraMfs100EncryptTemplate')) {
    function mantraMfs100EncryptTemplate(string $isoTemplate): array
    {
        $isoTemplate = trim($isoTemplate);
        if ($isoTemplate === '' || strlen($isoTemplate) < 40) {
            throw new InvalidArgumentException('Fingerprint template is empty.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($isoTemplate, 'aes-256-gcm', mantraMfs100CryptoKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false || $tag === '') {
            throw new RuntimeException('Could not encrypt the fingerprint template.');
        }
        return ['cipher' => $cipher, 'iv' => $iv, 'tag' => $tag];
    }
}

if (!function_exists('mantraMfs100DecryptTemplate')) {
    function mantraMfs100DecryptTemplate(string $cipher, string $iv, string $tag): string
    {
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', mantraMfs100CryptoKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($plain) || $plain === '') {
            throw new RuntimeException('Could not decrypt the stored fingerprint template.');
        }
        return $plain;
    }
}

if (!function_exists('studentHasFingerprintTemplate')) {
    function studentHasFingerprintTemplate($conn, string $studentId): bool
    {
        $studentId = trim($studentId);
        if ($studentId === '' || !($conn instanceof mysqli)) {
            return false;
        }
        ensureFingerprintTemplateTables($conn);
        $stmt = $conn->prepare('SELECT id FROM student_fingerprint_templates WHERE student_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('loadStudentFingerprintTemplate')) {
    function loadStudentFingerprintTemplate($conn, string $studentId): string
    {
        $studentId = trim($studentId);
        ensureFingerprintTemplateTables($conn);
        $stmt = $conn->prepare('SELECT template_cipher, template_iv, template_tag FROM student_fingerprint_templates WHERE student_id = ? ORDER BY id DESC LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Could not read the fingerprint template.');
        }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return '';
        }
        return mantraMfs100DecryptTemplate(
            (string) $row['template_cipher'],
            (string) $row['template_iv'],
            (string) $row['template_tag']
        );
    }
}

if (!function_exists('saveStudentFingerprintTemplate')) {
    function saveStudentFingerprintTemplate($conn, string $studentId, string $isoTemplate, string $enrolledBy, string $fingerCode = 'R1', int $quality = 0): bool
    {
        $studentId = trim($studentId);
        $fingerCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', $fingerCode) ?: 'R1');
        if ($studentId === '') {
            return false;
        }
        ensureFingerprintTemplateTables($conn);
        $enc = mantraMfs100EncryptTemplate($isoTemplate);
        $stmt = $conn->prepare(
            'INSERT INTO student_fingerprint_templates
                (student_id, finger_code, template_cipher, template_iv, template_tag, quality, enrolled_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                template_cipher = VALUES(template_cipher),
                template_iv = VALUES(template_iv),
                template_tag = VALUES(template_tag),
                quality = VALUES(quality),
                enrolled_by = VALUES(enrolled_by),
                updated_at = CURRENT_TIMESTAMP'
        );
        if (!$stmt) {
            throw new RuntimeException('Could not save fingerprint template: ' . $conn->error);
        }
        $cipher = $enc['cipher'];
        $iv = $enc['iv'];
        $tag = $enc['tag'];
        $stmt->bind_param('sssssis', $studentId, $fingerCode, $cipher, $iv, $tag, $quality, $enrolledBy);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('fingerprintEnrolmentSourceLabel')) {
    function fingerprintEnrolmentSourceLabel(string $enrolledBy): string
    {
        $enrolledBy = trim($enrolledBy);
        if ($enrolledBy === '') {
            return 'Unknown';
        }
        if (stripos($enrolledBy, 'self:') === 0) {
            return 'Self (student kiosk)';
        }
        return $enrolledBy;
    }
}

if (!function_exists('deleteStudentFingerprintTemplate')) {
    function deleteStudentFingerprintTemplate($conn, string $studentId): bool
    {
        $studentId = trim($studentId);
        if ($studentId === '' || !($conn instanceof mysqli)) {
            return false;
        }
        ensureFingerprintTemplateTables($conn);
        $stmt = $conn->prepare('DELETE FROM student_fingerprint_templates WHERE student_id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $studentId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('listStudentFingerprintRegistry')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function listStudentFingerprintRegistry($conn, string $search = '', int $courseId = 0): array
    {
        ensureFingerprintTemplateTables($conn);
        $search = trim($search);
        $sql = "SELECT t.id, t.student_id, t.finger_code, t.quality, t.enrolled_by, t.created_at, t.updated_at,
                       s.id AS student_row_id, s.name, s.mobile, s.email, s.status, s.course_id,
                       IFNULL(c.course_name, '') AS course_name
                FROM student_fingerprint_templates t
                LEFT JOIN students s ON LOWER(TRIM(s.student_id)) = LOWER(TRIM(t.student_id))
                LEFT JOIN courses c ON c.id = s.course_id
                WHERE 1=1";
        $types = '';
        $params = [];
        if ($courseId > 0) {
            $sql .= ' AND s.course_id = ?';
            $types .= 'i';
            $params[] = $courseId;
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            $sql .= ' AND (t.student_id LIKE ? OR s.name LIKE ? OR s.mobile LIKE ? OR s.email LIKE ?)';
            $types .= 'ssss';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY t.created_at DESC, t.id DESC, s.id DESC LIMIT 2000';
        $stmt = $conn->prepare($sql);
        $raw = [];
        if ($stmt) {
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $raw[] = $r;
                }
            }
            $stmt->close();
        } else {
            $res = $conn->query('SELECT id, student_id, finger_code, quality, enrolled_by, created_at, updated_at FROM student_fingerprint_templates ORDER BY created_at DESC LIMIT 1000');
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $raw[] = $r;
                }
            }
        }

        $rows = [];
        foreach ($raw as $r) {
            $tid = (int) ($r['id'] ?? 0);
            if ($tid > 0 && isset($rows[$tid])) {
                continue;
            }
            if ($tid > 0) {
                $rows[$tid] = $r;
            } else {
                $rows[] = $r;
            }
        }
        $rows = array_values($rows);
        if (count($rows) > 1000) {
            $rows = array_slice($rows, 0, 1000);
        }
        return fingerprintEnrichRegistryFromAccounts($conn, $rows);
    }
}

if (!function_exists('fingerprintEnrichRegistryFromAccounts')) {
    /**
     * Fill blank name/mobile/email from student_accounts when the students row is missing.
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    function fingerprintEnrichRegistryFromAccounts($conn, array $rows): array
    {
        if ($rows === [] || !($conn instanceof mysqli)) {
            return $rows;
        }
        $need = [];
        foreach ($rows as $i => $r) {
            if (trim((string) ($r['name'] ?? '')) === '') {
                $sid = trim((string) ($r['student_id'] ?? ''));
                if ($sid !== '') {
                    $need[strtolower($sid)] = $i;
                }
            }
        }
        if ($need === []) {
            return $rows;
        }
        $tbl = $conn->query("SHOW TABLES LIKE 'student_accounts'");
        if (!$tbl || $tbl->num_rows === 0) {
            return $rows;
        }
        $ids = array_keys($need);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT student_id, name, mobile, email FROM student_accounts WHERE LOWER(TRIM(student_id)) IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $rows;
        }
        $types = str_repeat('s', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($acc = $res->fetch_assoc()) {
                $key = strtolower(trim((string) ($acc['student_id'] ?? '')));
                if (!isset($need[$key])) {
                    continue;
                }
                $i = $need[$key];
                if (trim((string) ($rows[$i]['name'] ?? '')) === '' && !empty($acc['name'])) {
                    $rows[$i]['name'] = $acc['name'];
                }
                if (trim((string) ($rows[$i]['mobile'] ?? '')) === '' && !empty($acc['mobile'])) {
                    $rows[$i]['mobile'] = $acc['mobile'];
                }
                if (trim((string) ($rows[$i]['email'] ?? '')) === '' && !empty($acc['email'])) {
                    $rows[$i]['email'] = $acc['email'];
                }
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('listStudentsMissingFingerprint')) {
    /**
     * Active/approved students in a course who have not enrolled a fingerprint.
     * @return array<int,array<string,mixed>>
     */
    function listStudentsMissingFingerprint($conn, int $courseId): array
    {
        if ($courseId <= 0) {
            return [];
        }
        ensureFingerprintTemplateTables($conn);
        $sql = "SELECT s.student_id, s.name, s.mobile, s.email, s.status, IFNULL(c.course_name, '') AS course_name
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
                WHERE s.course_id = ?
                  AND LOWER(TRIM(IFNULL(s.status,''))) IN ('active','approved','')
                  AND NOT EXISTS (
                      SELECT 1 FROM student_fingerprint_templates t
                      WHERE LOWER(TRIM(t.student_id)) = LOWER(TRIM(s.student_id))
                  )
                ORDER BY s.name ASC
                LIMIT 1000";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('mantraMfs100Http')) {
    /**
     * @return array{ok:bool,body:string,error:string,http_code:int}
     */
    function mantraMfs100Http(string $url, string $method, $body = null, int $timeout = 20): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'body' => '', 'error' => 'PHP cURL is not enabled.', 'http_code' => 0];
        }
        $ch = curl_init($url);
        $headers = [
            'Accept: application/json, text/plain, */*',
            'Cache-Control: no-cache',
        ];
        $payload = '';
        if ($body !== null) {
            $payload = is_string($body) ? $body : json_encode($body);
            $headers[] = 'Content-Type: application/json; charset=utf-8';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        if ($payload !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $raw = curl_exec($ch);
        $err = (string) curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $text = is_string($raw) ? $raw : '';
        if ($text === '' && $err !== '') {
            return ['ok' => false, 'body' => '', 'error' => $err, 'http_code' => $code];
        }
        return ['ok' => true, 'body' => $text, 'error' => $err, 'http_code' => $code];
    }
}

if (!function_exists('mantraMfs100ParseJson')) {
    /**
     * @return array<string,mixed>
     */
    function mantraMfs100ParseJson(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }
}

if (!function_exists('mantraMfs100BaseUrls')) {
    /**
     * @return array<int,string>
     */
    function mantraMfs100BaseUrls(): array
    {
        return [
            'https://127.0.0.1:8003/mfs110/',
            'http://127.0.0.1:8004/mfs110/',
            'http://127.0.0.1:8003/mfs110/',
            'https://127.0.0.1:8004/mfs110/',
            'https://127.0.0.1:8003/mfs100/',
            'http://127.0.0.1:8004/mfs100/',
            'http://127.0.0.1:8003/mfs100/',
            'https://127.0.0.1:8004/mfs100/',
        ];
    }
}

if (!function_exists('mantraMfs100LooksReady')) {
    function mantraMfs100LooksReady(string $body): bool
    {
        if ($body === '') {
            return false;
        }
        if (stripos($body, 'ErrorCode') !== false || stripos($body, 'DeviceInfo') !== false) {
            return true;
        }
        if (stripos($body, 'MFS110') !== false || stripos($body, 'MFS100') !== false || stripos($body, 'IsoTemplate') !== false) {
            return true;
        }
        $json = mantraMfs100ParseJson($body);
        return $json !== [];
    }
}

if (!function_exists('mantraMfs100DiscoverLocal')) {
    /**
     * @return array{base:string}|null
     */
    function mantraMfs100DiscoverLocal(): ?array
    {
        foreach (mantraMfs100BaseUrls() as $base) {
            $res = mantraMfs100Http($base . 'info?Key=info', 'GET', null, 3);
            if ($res['ok'] && mantraMfs100LooksReady($res['body'])) {
                return ['base' => $base];
            }
            $res = mantraMfs100Http(rtrim($base, '/'), 'GET', null, 3);
            if ($res['ok'] && mantraMfs100LooksReady($res['body'])) {
                return ['base' => $base];
            }
        }
        return null;
    }
}

if (!function_exists('mantraMfs100Post')) {
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    function mantraMfs100Post(string $base, string $method, array $payload, int $timeout = 25): array
    {
        $base = rtrim($base, '/') . '/';
        $url = $base . ltrim($method, '/');
        $res = mantraMfs100Http($url, 'POST', $payload, $timeout);
        if (!$res['ok']) {
            return ['ErrorCode' => '-1', 'ErrorDescription' => $res['error'] !== '' ? $res['error'] : 'Could not reach MFS110 Client Service.'];
        }
        $json = mantraMfs100ParseJson($res['body']);
        if ($json === []) {
            return ['ErrorCode' => '-1', 'ErrorDescription' => 'MFS110 Client Service did not return JSON.'];
        }
        return $json;
    }
}

if (!function_exists('mantraMfs100IsoFromResponse')) {
    /**
     * @param array<string,mixed> $resp
     */
    function mantraMfs100IsoFromResponse(array $resp): string
    {
        foreach (['IsoTemplate', 'ISOTemplate', 'isoTemplate', 'AnsiTemplate', 'ANSITemplate'] as $key) {
            $val = trim((string) ($resp[$key] ?? ''));
            if (strlen($val) > 40) {
                return $val;
            }
        }
        return '';
    }
}

if (!function_exists('mantraMfs100ErrorCode')) {
    /**
     * @param array<string,mixed> $resp
     */
    function mantraMfs100ErrorCode(array $resp): string
    {
        return trim((string) ($resp['ErrorCode'] ?? $resp['errorCode'] ?? $resp['errcode'] ?? ''));
    }
}

if (!function_exists('mantraMfs100CaptureLocal')) {
    /**
     * @return array{ok:bool,message:string,iso:string,quality:int,raw:array<string,mixed>}
     */
    function mantraMfs100CaptureLocal(?string $base = null, int $quality = 55, int $timeout = 15): array
    {
        if ($base === null || $base === '') {
            $found = mantraMfs100DiscoverLocal();
            $base = $found['base'] ?? '';
        }
        if ($base === '') {
            return ['ok' => false, 'message' => 'MFS110 Client Service was not found on this PC. Install it and open Fingerprint Enrolment on the scanner PC.', 'iso' => '', 'quality' => 0, 'raw' => []];
        }
        $resp = mantraMfs100Post($base, 'capture', ['Quality' => $quality, 'TimeOut' => $timeout], $timeout + 8);
        $err = mantraMfs100ErrorCode($resp);
        if ($err !== '0' && $err !== '') {
            $msg = trim((string) ($resp['ErrorDescription'] ?? ''));
            if ($msg === '') {
                $msg = $err === '-1140' ? 'Capture timed out. Place the thumb firmly and try again.' : ('Fingerprint capture failed (code ' . $err . ').');
            }
            return ['ok' => false, 'message' => $msg, 'iso' => '', 'quality' => 0, 'raw' => $resp];
        }
        $iso = mantraMfs100IsoFromResponse($resp);
        if ($iso === '') {
            return ['ok' => false, 'message' => 'Device did not return an ISO fingerprint template. Confirm MFS110 Client Service is installed (not only RD Service).', 'iso' => '', 'quality' => 0, 'raw' => $resp];
        }
        $q = (int) ($resp['Quality'] ?? $resp['quality'] ?? 0);
        return ['ok' => true, 'message' => 'ok', 'iso' => $iso, 'quality' => $q, 'raw' => $resp];
    }
}

if (!function_exists('mantraMfs100IsMatch')) {
    /**
     * @param array<string,mixed> $resp
     */
    function mantraMfs100IsMatch(array $resp): bool
    {
        $err = mantraMfs100ErrorCode($resp);
        if ($err !== '' && $err !== '0') {
            return false;
        }
        if (array_key_exists('Status', $resp)) {
            $st = $resp['Status'];
            return $st === true || $st === 1 || $st === '1' || $st === 'true' || $st === 'True';
        }
        $score = (int) ($resp['MatchScore'] ?? $resp['Score'] ?? $resp['score'] ?? 0);
        if ($score >= 14000) {
            return true;
        }
        if ($score >= 40 && $score <= 100) {
            return true;
        }
        return false;
    }
}

if (!function_exists('mantraMfs100MatchLocal')) {
    /**
     * @return array{ok:bool,matched:bool,message:string,score:int}
     */
    function mantraMfs100MatchLocal(string $probeIso, string $galleryIso, ?string $base = null): array
    {
        $probeIso = trim($probeIso);
        $galleryIso = trim($galleryIso);
        if ($probeIso === '' || $galleryIso === '') {
            return ['ok' => false, 'matched' => false, 'message' => 'Missing fingerprint templates for matching.', 'score' => 0];
        }
        if ($base === null || $base === '') {
            $found = mantraMfs100DiscoverLocal();
            $base = $found['base'] ?? '';
        }
        if ($base === '') {
            return ['ok' => false, 'matched' => false, 'message' => 'MFS110 Client Service was not found on this PC.', 'score' => 0];
        }
        $resp = mantraMfs100Post($base, 'match', [
            'ProbTemplate' => $probeIso,
            'GalleryTemplate' => $galleryIso,
            'BioType' => 'FMR',
        ], 12);
        $err = mantraMfs100ErrorCode($resp);
        if ($err !== '' && $err !== '0') {
            $msg = trim((string) ($resp['ErrorDescription'] ?? ''));
            return ['ok' => false, 'matched' => false, 'message' => $msg !== '' ? $msg : 'Fingerprint match failed.', 'score' => 0];
        }
        $score = (int) ($resp['MatchScore'] ?? $resp['Score'] ?? 0);
        $matched = mantraMfs100IsMatch($resp);
        return [
            'ok' => true,
            'matched' => $matched,
            'message' => $matched ? 'Fingerprint matched.' : 'Fingerprint did not match this student.',
            'score' => $score,
        ];
    }
}

if (!function_exists('processBiometricMatchAttendance')) {
    /**
     * Capture live ISO on this PC, 1:1 match against the stored template, then mark IN/OUT.
     *
     * @return array<string,mixed>
     */
    function processBiometricMatchAttendance($conn, int $sessionId, string $studentId, string $coordinatorId, string $aadhaarLast4 = '', ?string $mfsBase = null): array
    {
        $studentId = trim($studentId);
        if ($sessionId <= 0 || $studentId === '') {
            return ['success' => false, 'result' => 'invalid', 'message' => 'Select a session and student first.'];
        }
        ensureFingerprintTemplateTables($conn);
        if (function_exists('ensureBiometricAttendanceTables')) {
            ensureBiometricAttendanceTables($conn);
        }
        if (function_exists('ensureAttendanceInOutTables')) {
            ensureAttendanceInOutTables($conn);
        }

        $gallery = loadStudentFingerprintTemplate($conn, $studentId);
        if ($gallery === '') {
            return [
                'success' => false,
                'result' => 'not_enrolled',
                'message' => 'This student has no enrolled fingerprint. Open Fingerprint Enrolment on this PC and enrol their thumb first.',
            ];
        }

        $sessionStmt = $conn->prepare("SELECT * FROM attendance_sessions WHERE id = ? AND status = 'active'");
        if (!$sessionStmt) {
            return ['success' => false, 'result' => 'error', 'message' => 'Database error.'];
        }
        $sessionStmt->bind_param('i', $sessionId);
        $sessionStmt->execute();
        $session = $sessionStmt->get_result()->fetch_assoc();
        $sessionStmt->close();
        if (!$session) {
            return ['success' => false, 'result' => 'expired', 'message' => 'Session is not active.'];
        }

        $found = lookupBiometricKioskStudent(
            $conn,
            $studentId,
            (int) $session['course_id'],
            (string) ($session['course_name'] ?? ''),
            (int) ($session['batch_id'] ?? 0)
        );
        if (empty($found['ok']) || empty($found['row'])) {
            return ['success' => false, 'result' => 'unknown_student', 'message' => (string) ($found['message'] ?? 'Student not found in this course.')];
        }
        $row = $found['row'];
        $studentId = trim((string) ($row['student_id'] ?? $studentId));

        $needLast4 = function_exists('biometricAadhaarLast4') ? biometricAadhaarLast4((string) ($row['aadhar'] ?? '')) : '';
        if ($needLast4 !== '') {
            $given = function_exists('biometricNormalizeDigits') ? biometricNormalizeDigits($aadhaarLast4) : preg_replace('/\D/', '', $aadhaarLast4);
            if (strlen((string) $given) !== 4 || !hash_equals($needLast4, (string) $given)) {
                return [
                    'success' => false,
                    'result' => 'aadhaar_mismatch',
                    'message' => 'Last 4 digits of Aadhaar do not match. Attendance not marked.',
                ];
            }
        }

        $cap = mantraMfs100CaptureLocal($mfsBase);
        if (!$cap['ok']) {
            if (function_exists('logBiometricCapture')) {
                logBiometricCapture($conn, $sessionId, $studentId, $coordinatorId, [], '', 'capture_fail');
            }
            return ['success' => false, 'result' => 'capture_fail', 'message' => $cap['message']];
        }

        $match = mantraMfs100MatchLocal($cap['iso'], $gallery, $mfsBase);
        if (!$match['ok']) {
            return ['success' => false, 'result' => 'match_error', 'message' => $match['message']];
        }
        if (!$match['matched']) {
            if (function_exists('logBiometricCapture')) {
                logBiometricCapture($conn, $sessionId, $studentId, $coordinatorId, ['q_score' => (string) $cap['quality']], '', 'finger_mismatch');
            }
            return [
                'success' => false,
                'result' => 'finger_mismatch',
                'message' => 'Fingerprint did not match this student. Attendance not marked.',
            ];
        }

        $marked = processInOutAttendanceForStudent($studentId, $sessionId, $coordinatorId, $conn, 'biometric');
        if (function_exists('logBiometricCapture')) {
            logBiometricCapture(
                $conn,
                $sessionId,
                $studentId,
                $coordinatorId,
                ['q_score' => (string) $cap['quality']],
                hash('sha256', $cap['iso']),
                !empty($marked['success']) ? 'ok' : (string) ($marked['result'] ?? 'fail')
            );
        }
        if (!empty($marked['success'])) {
            $marked['method'] = 'biometric';
            $marked['matched'] = true;
            $marked['message'] = preg_replace('/scan recorded/i', 'fingerprint recorded', (string) $marked['message']);
        }
        return $marked;
    }
}

if (!function_exists('processBiometricMatchAttendanceFromIso')) {
    /**
     * 1:1 match a live ISO captured in the browser against the stored template, then mark IN/OUT.
     *
     * @return array<string,mixed>
     */
    function processBiometricMatchAttendanceFromIso($conn, int $sessionId, string $studentId, string $coordinatorId, string $liveIso, string $aadhaarLast4 = '', ?string $mfsBase = null, bool $clientMatched = false): array
    {
        $studentId = trim($studentId);
        $liveIso = trim($liveIso);
        if ($sessionId <= 0 || $studentId === '' || strlen($liveIso) < 40) {
            return ['success' => false, 'result' => 'invalid', 'message' => 'Capture the fingerprint again.'];
        }
        ensureFingerprintTemplateTables($conn);
        $gallery = loadStudentFingerprintTemplate($conn, $studentId);
        if ($gallery === '') {
            return [
                'success' => false,
                'result' => 'not_enrolled',
                'message' => 'This student has no enrolled fingerprint. Enrol their thumb first.',
            ];
        }

        $foundLocal = mantraMfs100DiscoverLocal();
        $base = $mfsBase !== null && $mfsBase !== '' ? $mfsBase : (string) ($foundLocal['base'] ?? '');
        if ($base !== '') {
            $match = mantraMfs100MatchLocal($liveIso, $gallery, $base);
            if (!$match['ok'] || !$match['matched']) {
                if (function_exists('logBiometricCapture')) {
                    logBiometricCapture($conn, $sessionId, $studentId, $coordinatorId, [], '', 'finger_mismatch');
                }
                return [
                    'success' => false,
                    'result' => 'finger_mismatch',
                    'message' => $match['message'] !== '' ? $match['message'] : 'Fingerprint did not match this student. Attendance not marked.',
                ];
            }
        } elseif (!$clientMatched) {
            return [
                'success' => false,
                'result' => 'finger_mismatch',
                'message' => 'Fingerprint did not match this student. Attendance not marked.',
            ];
        }

        $sessionStmt = $conn->prepare("SELECT * FROM attendance_sessions WHERE id = ? AND status = 'active'");
        if (!$sessionStmt) {
            return ['success' => false, 'result' => 'error', 'message' => 'Database error.'];
        }
        $sessionStmt->bind_param('i', $sessionId);
        $sessionStmt->execute();
        $session = $sessionStmt->get_result()->fetch_assoc();
        $sessionStmt->close();
        if (!$session) {
            return ['success' => false, 'result' => 'expired', 'message' => 'Session is not active.'];
        }
        $found = lookupBiometricKioskStudent(
            $conn,
            $studentId,
            (int) $session['course_id'],
            (string) ($session['course_name'] ?? ''),
            (int) ($session['batch_id'] ?? 0)
        );
        if (empty($found['ok']) || empty($found['row'])) {
            return ['success' => false, 'result' => 'unknown_student', 'message' => (string) ($found['message'] ?? 'Student not found.')];
        }
        $row = $found['row'];
        $studentId = trim((string) ($row['student_id'] ?? $studentId));
        $needLast4 = function_exists('biometricAadhaarLast4') ? biometricAadhaarLast4((string) ($row['aadhar'] ?? '')) : '';
        if ($needLast4 !== '') {
            $given = function_exists('biometricNormalizeDigits') ? biometricNormalizeDigits($aadhaarLast4) : preg_replace('/\D/', '', $aadhaarLast4);
            if (strlen((string) $given) !== 4 || !hash_equals($needLast4, (string) $given)) {
                return ['success' => false, 'result' => 'aadhaar_mismatch', 'message' => 'Last 4 digits of Aadhaar do not match. Attendance not marked.'];
            }
        }

        $marked = processInOutAttendanceForStudent($studentId, $sessionId, $coordinatorId, $conn, 'biometric');
        if (function_exists('logBiometricCapture')) {
            logBiometricCapture(
                $conn,
                $sessionId,
                $studentId,
                $coordinatorId,
                [],
                hash('sha256', $liveIso),
                !empty($marked['success']) ? 'ok' : (string) ($marked['result'] ?? 'fail')
            );
        }
        if (!empty($marked['success'])) {
            $marked['method'] = 'biometric';
            $marked['matched'] = true;
        }
        return $marked;
    }
}
