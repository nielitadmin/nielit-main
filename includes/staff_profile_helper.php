<?php
/**
 * NIELIT Centre staff profile — extended fields for Non S&T / faculty records.
 */

if (!function_exists('facultyTableHasColumn')) {
    function facultyTableHasColumn(mysqli $conn, string $column): bool
    {
        $check = $conn->query("SHOW COLUMNS FROM faculty LIKE '" . $conn->real_escape_string($column) . "'");
        return $check && $check->num_rows > 0;
    }
}

if (!function_exists('ensureStaffProfileSchema')) {
    function ensureStaffProfileSchema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $columnDefinitions = [
            'nielit_centre'            => 'VARCHAR(255) DEFAULT NULL',
            'employment_type'          => 'VARCHAR(50) DEFAULT NULL',
            'date_of_joining'          => 'DATE DEFAULT NULL',
            'highest_qualification'    => 'VARCHAR(255) DEFAULT NULL',
            'university_institute'     => 'VARCHAR(255) DEFAULT NULL',
            'year_of_passing'          => 'VARCHAR(10) DEFAULT NULL',
            'specialization'           => 'VARCHAR(255) DEFAULT NULL',
            'areas_of_expertise'       => 'TEXT DEFAULT NULL',
            'experience_years'         => 'DECIMAL(5,1) DEFAULT NULL',
            'research_interests'       => 'TEXT DEFAULT NULL',
            'research_publications'    => 'TEXT DEFAULT NULL',
            'books_chapters'           => 'TEXT DEFAULT NULL',
            'patents'                  => 'TEXT DEFAULT NULL',
            'sponsored_projects'       => 'TEXT DEFAULT NULL',
            'consultancy_projects'     => 'TEXT DEFAULT NULL',
            'technology_developed'     => 'TEXT DEFAULT NULL',
            'research_guidance'        => 'TEXT DEFAULT NULL',
            'awards_recognitions'      => 'TEXT DEFAULT NULL',
            'professional_memberships' => 'TEXT DEFAULT NULL',
            'profile_photo'            => 'VARCHAR(255) DEFAULT NULL',
            'profile_updated_at'       => 'TIMESTAMP NULL DEFAULT NULL',
            'profile_token'            => 'VARCHAR(32) DEFAULT NULL',
            'profile_token_expires_at' => 'TIMESTAMP NULL DEFAULT NULL',
        ];

        $orderedColumns = array_keys($columnDefinitions);
        $afterColumn = facultyTableHasColumn($conn, 'department') ? 'department' : '';

        foreach ($orderedColumns as $column) {
            if (facultyTableHasColumn($conn, $column)) {
                $afterColumn = $column;
                continue;
            }

            $definition = $columnDefinitions[$column];
            $sql = "ALTER TABLE faculty ADD COLUMN `$column` $definition";
            if ($afterColumn !== '') {
                $sql .= " AFTER `$afterColumn`";
            }
            $conn->query($sql);

            if (!facultyTableHasColumn($conn, $column)) {
                $conn->query("ALTER TABLE faculty ADD COLUMN `$column` $definition");
            }

            if (facultyTableHasColumn($conn, $column)) {
                $afterColumn = $column;
            }
        }

        if (facultyTableHasColumn($conn, 'profile_token')) {
            $idx = $conn->query("SHOW INDEX FROM faculty WHERE Key_name = 'uniq_profile_token'");
            if ($idx && $idx->num_rows === 0) {
                $conn->query('ALTER TABLE faculty ADD UNIQUE KEY uniq_profile_token (profile_token)');
            }
        }

        $allColumnsPresent = true;
        foreach ($orderedColumns as $column) {
            if (!facultyTableHasColumn($conn, $column)) {
                $allColumnsPresent = false;
                break;
            }
        }

        if ($allColumnsPresent && facultyTableHasColumn($conn, 'profile_token')) {
            $done = true;
        }
    }
}

if (!function_exists('generateStaffProfileToken')) {
    function generateStaffProfileToken(int $length = 8): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $token = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[random_int(0, $max)];
        }
        return $token;
    }
}

if (!function_exists('staffProfileLinkTtlSeconds')) {
    function staffProfileLinkTtlSeconds(): int
    {
        return 3600;
    }
}

if (!function_exists('isStaffProfileLinkExpired')) {
    function isStaffProfileLinkExpired(array $row): bool
    {
        if (isset($row['expires_unix']) && $row['expires_unix'] !== null && $row['expires_unix'] !== '') {
            return (int) $row['expires_unix'] <= time();
        }

        $expires = trim((string) ($row['profile_token_expires_at'] ?? ''));
        if ($expires === '') {
            return true;
        }

        return strtotime($expires) <= time();
    }
}

if (!function_exists('staffProfileLinkSecondsRemaining')) {
    function staffProfileLinkSecondsRemaining(array $row): int
    {
        if (isStaffProfileLinkExpired($row)) {
            return 0;
        }

        $expires = strtotime((string) $row['profile_token_expires_at']);
        return max(0, $expires - time());
    }
}

if (!function_exists('getStaffProfileLinkMeta')) {
    function getStaffProfileLinkMeta(mysqli $conn, int $facultyId): array
    {
        ensureStaffProfileSchema($conn);

        $empty = [
            'token' => '',
            'expires_at' => null,
            'expires_ts' => 0,
            'seconds_remaining' => 0,
            'is_expired' => true,
            'has_token' => false,
        ];

        if (!facultyTableHasColumn($conn, 'profile_token')) {
            return $empty;
        }

        $selectCols = 'profile_token';
        if (facultyTableHasColumn($conn, 'profile_token_expires_at')) {
            $selectCols .= ', profile_token_expires_at, UNIX_TIMESTAMP(profile_token_expires_at) AS expires_unix';
        }

        $stmt = $conn->prepare("SELECT $selectCols FROM faculty WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return $empty;
        }
        $stmt->bind_param('i', $facultyId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return $empty;
        }

        $token = trim((string) ($row['profile_token'] ?? ''));
        if ($token === '') {
            return $empty;
        }

        $expiresAt = trim((string) ($row['profile_token_expires_at'] ?? ''));
        $expiresTs = isset($row['expires_unix']) && $row['expires_unix'] !== null
            ? (int) $row['expires_unix']
            : ($expiresAt !== '' ? (int) strtotime($expiresAt) : 0);
        $isExpired = isStaffProfileLinkExpired($row);

        return [
            'token' => $token,
            'expires_at' => $expiresAt !== '' ? $expiresAt : null,
            'expires_ts' => $expiresTs,
            'seconds_remaining' => staffProfileLinkSecondsRemaining($row),
            'is_expired' => $isExpired,
            'has_token' => true,
        ];
    }
}

if (!function_exists('getStaffProfilePublicUrl')) {
    function getStaffProfilePublicUrl(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        return rtrim(APP_URL, '/') . '/public/staff_profile.php?token=' . rawurlencode($token);
    }
}

if (!function_exists('buildStaffProfileShareLink')) {
    function buildStaffProfileShareLink(mysqli $conn, int $facultyId): array
    {
        $meta = getStaffProfileLinkMeta($conn, $facultyId);

        if (!$meta['has_token']) {
            return [
                'success' => false,
                'token' => '',
                'url' => '',
                'expires_at' => null,
                'expires_ts' => 0,
                'seconds_remaining' => 0,
                'is_expired' => false,
                'message' => 'No profile link yet. Click Generate link (valid for 1 hour).',
            ];
        }

        if ($meta['is_expired']) {
            return [
                'success' => false,
                'token' => $meta['token'],
                'url' => '',
                'expires_at' => $meta['expires_at'],
                'expires_ts' => $meta['expires_ts'],
                'seconds_remaining' => 0,
                'is_expired' => true,
                'message' => 'Profile link expired. Click Generate link to create a new one (valid for 1 hour).',
            ];
        }

        return [
            'success' => true,
            'token' => $meta['token'],
            'url' => getStaffProfilePublicUrl($meta['token']),
            'expires_at' => $meta['expires_at'],
            'expires_ts' => $meta['expires_ts'],
            'seconds_remaining' => $meta['seconds_remaining'],
            'is_expired' => false,
            'message' => '',
        ];
    }
}

if (!function_exists('ensureStaffProfileToken')) {
    function ensureStaffProfileToken(mysqli $conn, int $facultyId): string
    {
        $meta = getStaffProfileLinkMeta($conn, $facultyId);
        if (!$meta['has_token'] || $meta['is_expired']) {
            return '';
        }

        return $meta['token'];
    }
}

if (!function_exists('regenerateStaffProfileToken')) {
    function regenerateStaffProfileToken(mysqli $conn, int $facultyId): array
    {
        ensureStaffProfileSchema($conn);

        do {
            $token = generateStaffProfileToken(8);
            $check = $conn->prepare('SELECT id FROM faculty WHERE profile_token = ? AND id <> ? LIMIT 1');
            if (!$check) {
                return ['success' => false, 'message' => 'Could not validate token uniqueness.'];
            }
            $check->bind_param('si', $token, $facultyId);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
        } while ($exists);

        if (facultyTableHasColumn($conn, 'profile_token_expires_at')) {
            if (facultyTableHasColumn($conn, 'profile_updated_at')) {
                $stmt = $conn->prepare('UPDATE faculty SET profile_token = ?, profile_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR), profile_updated_at = NOW() WHERE id = ?');
            } else {
                $stmt = $conn->prepare('UPDATE faculty SET profile_token = ?, profile_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?');
            }
            if (!$stmt) {
                return ['success' => false, 'message' => 'Could not save profile link token.'];
            }
            $stmt->bind_param('si', $token, $facultyId);
        } elseif (facultyTableHasColumn($conn, 'profile_updated_at')) {
            $stmt = $conn->prepare('UPDATE faculty SET profile_token = ?, profile_updated_at = NOW() WHERE id = ?');
            if (!$stmt) {
                return ['success' => false, 'message' => 'Could not save profile link token.'];
            }
            $stmt->bind_param('si', $token, $facultyId);
        } else {
            $stmt = $conn->prepare('UPDATE faculty SET profile_token = ? WHERE id = ?');
            if (!$stmt) {
                return ['success' => false, 'message' => 'Could not save profile link token.'];
            }
            $stmt->bind_param('si', $token, $facultyId);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to generate profile link.'];
        }
        $stmt->close();

        $meta = getStaffProfileLinkMeta($conn, $facultyId);
        $expiresTs = (int) ($meta['expires_ts'] ?? 0);
        $secondsRemaining = (int) ($meta['seconds_remaining'] ?? 0);
        if ($expiresTs <= 0) {
            $expiresTs = time() + staffProfileLinkTtlSeconds();
            $secondsRemaining = staffProfileLinkTtlSeconds();
        }

        return [
            'success' => true,
            'message' => 'New profile link generated. Link is valid for 1 hour.',
            'token' => $token,
            'url' => getStaffProfilePublicUrl($token),
            'expires_at' => $meta['expires_at'],
            'expires_ts' => $expiresTs,
            'seconds_remaining' => $secondsRemaining,
            'is_expired' => false,
        ];
    }
}

if (!function_exists('loadStaffByProfileToken')) {
    function loadStaffByProfileToken(mysqli $conn, string $token): ?array
    {
        ensureStaffProfileSchema($conn);
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $stmt = $conn->prepare('SELECT *, UNIX_TIMESTAMP(profile_token_expires_at) AS expires_unix FROM faculty WHERE profile_token = ? AND is_active = 1 LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || isStaffProfileLinkExpired($row)) {
            return null;
        }

        return $row;
    }
}

if (!function_exists('validateStaffProfileTokenAccess')) {
    function validateStaffProfileTokenAccess(mysqli $conn, string $token): array
    {
        ensureStaffProfileSchema($conn);
        $token = trim($token);
        if ($token === '') {
            return ['status' => 'invalid', 'staff' => null];
        }

        $stmt = $conn->prepare('SELECT *, UNIX_TIMESTAMP(profile_token_expires_at) AS expires_unix FROM faculty WHERE profile_token = ? AND is_active = 1 LIMIT 1');
        if (!$stmt) {
            return ['status' => 'invalid', 'staff' => null];
        }
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return ['status' => 'invalid', 'staff' => null];
        }

        if (isStaffProfileLinkExpired($row)) {
            return ['status' => 'expired', 'staff' => null];
        }

        return ['status' => 'valid', 'staff' => $row];
    }
}

if (!function_exists('staffProfilePublicFieldGroups')) {
    function staffProfilePublicFieldGroups(mysqli $conn): array
    {
        $groups = staffProfileFieldGroups($conn);
        unset($groups['basic']['fields']['staff_category']);
        return $groups;
    }
}

if (!function_exists('getActiveTrainingCentres')) {
    function getActiveTrainingCentres(mysqli $conn): array
    {
        if (!function_exists('normalize_nielit_centre_name')) {
            require_once __DIR__ . '/institute_branding.php';
        }

        $centres = [];
        $res = $conn->query('SELECT id, name, code FROM centres WHERE is_active = 1 ORDER BY name ASC');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $centres[] = [
                    'id' => (int) $row['id'],
                    'name' => normalize_nielit_centre_name(trim((string) $row['name'])),
                    'code' => trim((string) ($row['code'] ?? '')),
                ];
            }
        }

        if (empty($centres)) {
            if (!defined('NIELIT_BALESHWAR_EXTENSION')) {
                require_once __DIR__ . '/institute_branding.php';
            }
            $centres = [
                ['id' => 0, 'name' => 'NIELIT Bhubaneswar', 'code' => 'BBSR'],
                ['id' => 0, 'name' => NIELIT_BALESHWAR_EXTENSION, 'code' => 'BLSW'],
                ['id' => 0, 'name' => 'NIELIT Raipur', 'code' => 'RPR'],
            ];
        }

        return $centres;
    }
}

if (!function_exists('staffTrainingCentreOptionNames')) {
    function staffTrainingCentreOptionNames(mysqli $conn): array
    {
        $names = [];
        foreach (getActiveTrainingCentres($conn) as $centre) {
            if ($centre['name'] !== '') {
                $names[] = $centre['name'];
            }
        }
        return array_values(array_unique($names));
    }
}

if (!function_exists('staffEmploymentTypeOptions')) {
    function staffEmploymentTypeOptions(): array
    {
        return ['Regular', 'Contractual', 'Project', 'Outsourced'];
    }
}

if (!function_exists('staffProfileFieldGroups')) {
    function staffProfileFieldGroups(?mysqli $conn = null): array
    {
        $groups = [
            'basic' => [
                'title' => 'Basic Information',
                'icon'  => 'fa-id-card',
                'fields' => [
                    'name'              => ['label' => 'Name', 'type' => 'text', 'required' => true, 'col' => 'name'],
                    'designation'       => ['label' => 'Designation', 'type' => 'text', 'required' => false, 'col' => 'designation'],
                    'department'        => ['label' => 'Department / School', 'type' => 'text', 'required' => false, 'col' => 'department'],
                    'nielit_centre'     => ['label' => 'NIELIT Centre', 'type' => 'select', 'required' => false, 'col' => 'nielit_centre', 'options' => [], 'placeholder' => 'Select Training Centre'],
                    'employment_type'   => ['label' => 'Employment Type', 'type' => 'select', 'required' => false, 'col' => 'employment_type', 'options' => staffEmploymentTypeOptions()],
                    'date_of_joining'   => ['label' => 'Date of Joining NIELIT', 'type' => 'date', 'required' => false, 'col' => 'date_of_joining'],
                    'email'             => ['label' => 'Official Email', 'type' => 'email', 'required' => false, 'col' => 'email'],
                    'phone'             => ['label' => 'Mobile Number', 'type' => 'tel', 'required' => false, 'col' => 'phone'],
                    'staff_category'    => ['label' => 'Staff Category', 'type' => 'select', 'required' => true, 'col' => 'staff_category', 'options' => [
                        'Faculty Staff', 'Scientists', 'Non S&T', 'Scientific and Technical Staff',
                    ]],
                ],
            ],
            'academic' => [
                'title' => 'Academic Profile',
                'icon'  => 'fa-graduation-cap',
                'fields' => [
                    'highest_qualification' => ['label' => 'Highest Qualification', 'type' => 'text', 'required' => false, 'col' => 'highest_qualification'],
                    'university_institute' => ['label' => 'University / Institute', 'type' => 'text', 'required' => false, 'col' => 'university_institute'],
                    'year_of_passing'     => ['label' => 'Year of Passing', 'type' => 'text', 'required' => false, 'col' => 'year_of_passing', 'placeholder' => 'e.g. 2018'],
                    'specialization'      => ['label' => 'Specialization', 'type' => 'text', 'required' => false, 'col' => 'specialization'],
                    'areas_of_expertise'  => ['label' => 'Areas of Expertise (Top 5 Keywords)', 'type' => 'textarea', 'required' => false, 'col' => 'areas_of_expertise', 'rows' => 2, 'placeholder' => 'e.g. AI, IoT, Cyber Security, Cloud, Data Science'],
                    'experience_years'    => ['label' => 'Experience (Years)', 'type' => 'number', 'required' => false, 'col' => 'experience_years', 'step' => '0.1', 'min' => '0'],
                ],
            ],
            'research' => [
                'title' => 'Research & Professional Achievements',
                'icon'  => 'fa-flask',
                'fields' => [
                    'research_interests'       => ['label' => 'Research Interests', 'type' => 'textarea', 'required' => false, 'col' => 'research_interests', 'rows' => 3],
                    'research_publications'    => ['label' => 'Research Publications (Journals / Papers)', 'type' => 'textarea', 'required' => false, 'col' => 'research_publications', 'rows' => 4],
                    'books_chapters'           => ['label' => 'Books / Book Chapters', 'type' => 'textarea', 'required' => false, 'col' => 'books_chapters', 'rows' => 3],
                    'patents'                  => ['label' => 'Patents (Granted / Filed)', 'type' => 'textarea', 'required' => false, 'col' => 'patents', 'rows' => 3],
                    'sponsored_projects'       => ['label' => 'Sponsored Projects', 'type' => 'textarea', 'required' => false, 'col' => 'sponsored_projects', 'rows' => 3],
                    'consultancy_projects'     => ['label' => 'Consultancy Projects', 'type' => 'textarea', 'required' => false, 'col' => 'consultancy_projects', 'rows' => 3],
                    'technology_developed'     => ['label' => 'Technology / Product Developed', 'type' => 'textarea', 'required' => false, 'col' => 'technology_developed', 'rows' => 3],
                    'research_guidance'        => ['label' => 'Research Guidance (Ph.D. / M.Tech.)', 'type' => 'textarea', 'required' => false, 'col' => 'research_guidance', 'rows' => 3],
                    'awards_recognitions'      => ['label' => 'Awards & Recognitions', 'type' => 'textarea', 'required' => false, 'col' => 'awards_recognitions', 'rows' => 3],
                    'professional_memberships' => ['label' => 'Professional Memberships', 'type' => 'textarea', 'required' => false, 'col' => 'professional_memberships', 'rows' => 3],
                ],
            ],
        ];

        if ($conn instanceof mysqli) {
            $groups['basic']['fields']['nielit_centre']['options'] = staffTrainingCentreOptionNames($conn);
        }

        return $groups;
    }
}

if (!function_exists('loadStaffProfileById')) {
    function loadStaffProfileById(mysqli $conn, int $facultyId): ?array
    {
        ensureStaffProfileSchema($conn);

        $stmt = $conn->prepare("SELECT * FROM faculty WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $facultyId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('saveStaffProfile')) {
    function saveStaffProfile(mysqli $conn, int $facultyId, array $data): array
    {
        ensureStaffProfileSchema($conn);

        $groups = staffProfileFieldGroups();
        $columns = [];
        $values = [];
        $types = '';

        foreach ($groups as $group) {
            foreach ($group['fields'] as $key => $field) {
                $col = $field['col'];
                if (!array_key_exists($key, $data)) {
                    continue;
                }
                $value = is_string($data[$key]) ? trim($data[$key]) : $data[$key];
                if ($value === '') {
                    $value = null;
                }
                if ($col === 'email') {
                    if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return ['success' => false, 'message' => 'Please enter a valid official email address.'];
                    }
                }
                if ($col === 'phone' && $value === null) {
                    $value = null;
                }
                if ($col === 'experience_years') {
                    $value = ($value !== null && is_numeric($value)) ? number_format((float) $value, 1, '.', '') : null;
                }
                $columns[] = "`$col` = ?";
                $values[] = $value;
                $types .= 's';
            }
        }

        if (trim((string) ($data['name'] ?? '')) === '') {
            return ['success' => false, 'message' => 'Name is required.'];
        }

        $columns[] = 'profile_updated_at = NOW()';
        $sql = 'UPDATE faculty SET ' . implode(', ', $columns) . ' WHERE id = ?';
        $types .= 'i';
        $values[] = $facultyId;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not prepare save: ' . $conn->error];
        }

        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) {
            $msg = $conn->errno === 1062
                ? 'Another staff member already uses this official email.'
                : 'Save failed: ' . $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => $msg];
        }
        $stmt->close();

        return ['success' => true, 'message' => 'Staff profile saved successfully.'];
    }
}

if (!function_exists('staffProfileCompletionPercent')) {
    function staffProfileCompletionPercent(array $staff): int
    {
        $filled = 0;
        $total = 0;

        foreach (staffProfileFieldGroups() as $group) {
            foreach ($group['fields'] as $key => $field) {
                if ($key === 'staff_category') {
                    continue;
                }
                $total++;
                $col = $field['col'];
                $val = $staff[$col] ?? '';
                if ($val !== null && trim((string) $val) !== '') {
                    $filled++;
                }
            }
        }

        return $total > 0 ? (int) round(($filled / $total) * 100) : 0;
    }
}

if (!function_exists('uploadStaffProfilePhoto')) {
    function uploadStaffProfilePhoto(mysqli $conn, int $facultyId, array $file): array
    {
        ensureStaffProfileSchema($conn);

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => true, 'message' => ''];
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Photo upload failed. Please try again.'];
        }

        $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed, true)) {
            return ['success' => false, 'message' => 'Photo must be JPG or PNG.'];
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Photo must be 5 MB or smaller.'];
        }

        $ext = $mime === 'image/png' ? 'png' : 'jpg';
        $dir = __DIR__ . '/../uploads/staff_photos/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return ['success' => false, 'message' => 'Could not create photo upload folder.'];
        }

        $filename = 'staff_' . $facultyId . '_' . time() . '.' . $ext;
        $dest = $dir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['success' => false, 'message' => 'Could not save uploaded photo.'];
        }

        $relative = 'uploads/staff_photos/' . $filename;
        $stmt = $conn->prepare('UPDATE faculty SET profile_photo = ?, profile_updated_at = NOW() WHERE id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not update photo path.'];
        }
        $stmt->bind_param('si', $relative, $facultyId);
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'message' => 'Could not save photo to profile.'];
        }
        $stmt->close();

        return ['success' => true, 'message' => 'Photo uploaded.', 'path' => $relative];
    }
}
