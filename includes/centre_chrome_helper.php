<?php
/**
 * Per-centre public header & footer (logos, names, ticker, contact).
 * centre_id 0 = main website (NIELIT Bhubaneswar HQ).
 */

if (!function_exists('centre_chrome_field_keys')) {
    function centre_chrome_field_keys(): array
    {
        return [
            'header_name_hi',
            'header_name_en',
            'ministry_name_hi',
            'ministry_name_en',
            'ticker_text',
            'banner_title',
            'banner_tagline',
            'left_logo_path',
            'right_logo_path',
            'footer_brand',
            'footer_centre_label',
            'footer_about',
            'footer_address',
            'footer_phone',
            'footer_email',
            'footer_copyright',
        ];
    }
}

if (!function_exists('centre_chrome_ensure_schema')) {
    function centre_chrome_ensure_schema($conn): void
    {
        static $ready = false;
        if ($ready || !($conn instanceof mysqli)) {
            return;
        }
        $sql = "CREATE TABLE IF NOT EXISTS report_public_centre_branding (
            id INT AUTO_INCREMENT PRIMARY KEY,
            centre_id INT NOT NULL,
            header_name_hi VARCHAR(255) NULL,
            header_name_en VARCHAR(255) NULL,
            ministry_name_hi VARCHAR(255) NULL,
            ministry_name_en VARCHAR(255) NULL,
            ticker_text VARCHAR(500) NULL,
            banner_title VARCHAR(255) NULL,
            banner_tagline VARCHAR(255) NULL,
            left_logo_path VARCHAR(255) NULL,
            right_logo_path VARCHAR(255) NULL,
            footer_brand VARCHAR(80) NULL,
            footer_centre_label VARCHAR(120) NULL,
            footer_about TEXT NULL,
            footer_address VARCHAR(255) NULL,
            footer_phone VARCHAR(80) NULL,
            footer_email VARCHAR(160) NULL,
            footer_copyright VARCHAR(255) NULL,
            updated_by VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_rm_public_branding_centre (centre_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        @$conn->query($sql);
        $extra = [
            'ministry_name_hi' => 'VARCHAR(255) NULL',
            'ministry_name_en' => 'VARCHAR(255) NULL',
            'left_logo_path' => 'VARCHAR(255) NULL',
            'right_logo_path' => 'VARCHAR(255) NULL',
        ];
        foreach ($extra as $col => $def) {
            $check = @$conn->query("SHOW COLUMNS FROM report_public_centre_branding LIKE '" . $conn->real_escape_string($col) . "'");
            if ($check && $check->num_rows === 0) {
                @$conn->query("ALTER TABLE report_public_centre_branding ADD COLUMN `{$col}` {$def}");
            }
        }
        $ready = true;
    }
}

if (!function_exists('centre_chrome_table_exists')) {
    function centre_chrome_table_exists($conn, string $table): bool
    {
        if (function_exists('report_monitor_table_exists')) {
            return report_monitor_table_exists($conn, $table);
        }
        $table = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '{$table}'");
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('centre_chrome_centre_row')) {
    function centre_chrome_centre_row($conn, int $centreId): array
    {
        if ($centreId <= 0 || !($conn instanceof mysqli) || !centre_chrome_table_exists($conn, 'centres')) {
            return [];
        }
        $stmt = $conn->prepare('SELECT * FROM centres WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $centreId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return $row;
    }
}

if (!function_exists('centre_chrome_find_id_by_keyword')) {
    function centre_chrome_find_id_by_keyword($conn, string $keyword): int
    {
        if (function_exists('report_monitor_find_centre_id_by_keyword')) {
            return report_monitor_find_centre_id_by_keyword($conn, $keyword);
        }
        $keyword = trim($keyword);
        if ($keyword === '' || !($conn instanceof mysqli) || !centre_chrome_table_exists($conn, 'centres')) {
            return 0;
        }
        $like = '%' . $keyword . '%';
        $stmt = $conn->prepare('SELECT id FROM centres WHERE is_active = 1 AND (name LIKE ? OR code LIKE ?) ORDER BY id ASC LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['id'] ?? 0);
    }
}

if (!function_exists('centre_chrome_main_site_id')) {
    function centre_chrome_main_site_id($conn): int
    {
        $id = centre_chrome_find_id_by_keyword($conn, 'Bhubaneswar');
        if ($id <= 0) {
            $id = centre_chrome_find_id_by_keyword($conn, 'BBSR');
        }
        return $id;
    }
}

if (!function_exists('centre_chrome_defaults')) {
    function centre_chrome_defaults(array $centreRow = [], string $centreName = '', int $centreId = 0): array
    {
        if (!defined('INSTITUTE_NAME_EN')) {
            $branding = __DIR__ . '/institute_branding.php';
            if (is_file($branding)) {
                require_once $branding;
            }
        }
        $name = trim($centreName !== '' ? $centreName : (string) ($centreRow['name'] ?? ''));
        $key = strtolower($name);
        $isHq = $centreId <= 0 || strpos($key, 'bhubaneswar') !== false || strpos($key, 'bbsr') !== false;
        $isRaipur = strpos($key, 'raipur') !== false;
        if ($name === '') {
            $name = $isHq ? (defined('INSTITUTE_NAME_EN') ? INSTITUTE_NAME_EN : 'NIELIT Bhubaneswar') : 'NIELIT Centre';
        }
        $short = trim((string) preg_replace('/^nielit\s+/i', '', $name));
        if ($short === '') {
            $short = $isHq ? 'Bhubaneswar' : 'Centre';
        }
        $addressParts = array_filter([
            trim((string) ($centreRow['address'] ?? '')),
            trim((string) ($centreRow['city'] ?? '')),
            trim((string) ($centreRow['state'] ?? '')),
            trim((string) ($centreRow['pincode'] ?? '')),
        ]);
        $address = implode(', ', $addressParts);

        if ($isHq) {
            return [
                'header_name_hi' => defined('INSTITUTE_NAME_HI') ? INSTITUTE_NAME_HI : 'राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर',
                'header_name_en' => defined('INSTITUTE_NAME_EN') ? INSTITUTE_NAME_EN : 'National Institute of Electronics & Information Technology, Bhubaneswar',
                'ministry_name_hi' => defined('MINISTRY_NAME_HI') ? MINISTRY_NAME_HI : '',
                'ministry_name_en' => defined('MINISTRY_NAME_EN') ? MINISTRY_NAME_EN : '',
                'ticker_text' => 'NIELIT Bhubaneswar offers NSQF-aligned courses with modern facilities & industry-standard training. Enroll now!',
                'banner_title' => 'NIELIT Report Monitor',
                'banner_tagline' => 'Read-only public view',
                'left_logo_path' => 'assets/images/bhubaneswar_logo.png',
                'right_logo_path' => 'assets/images/National-Emblem.png',
                'footer_brand' => 'NIELIT',
                'footer_centre_label' => 'Bhubaneswar',
                'footer_about' => (defined('INSTITUTE_NAME_EN') ? INSTITUTE_NAME_EN : $name) . ' under ' . (defined('MINISTRY_NAME_EN') ? MINISTRY_NAME_EN : 'MeitY') . '.',
                'footer_address' => $address !== '' ? $address : 'OCAC Tower, Bhubaneswar, Odisha',
                'footer_phone' => trim((string) ($centreRow['phone'] ?? '')) ?: '0674-2960354',
                'footer_email' => trim((string) ($centreRow['email'] ?? '')) ?: 'dir-bbsr@nielit.gov.in',
                'footer_copyright' => 'Designed & Developed by NIELIT Bhubaneswar IT Team © {year} | All Rights Reserved',
            ];
        }

        if ($address === '') {
            $address = $isRaipur ? 'Raipur, Chhattisgarh' : $short;
        }

        return [
            'header_name_hi' => $isRaipur
                ? 'राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, रायपुर'
                : ('राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, ' . $short),
            'header_name_en' => $isRaipur
                ? 'National Institute of Electronics & Information Technology, Raipur'
                : ('National Institute of Electronics & Information Technology, ' . $short),
            'ministry_name_hi' => defined('MINISTRY_NAME_HI') ? MINISTRY_NAME_HI : '',
            'ministry_name_en' => defined('MINISTRY_NAME_EN') ? MINISTRY_NAME_EN : '',
            'ticker_text' => 'NIELIT ' . $short . ' offers NSQF-aligned courses with modern facilities & industry-standard training. Enroll now!',
            'banner_title' => 'NIELIT Report Monitor · ' . $short,
            'banner_tagline' => 'Read-only public view',
            'left_logo_path' => 'assets/images/bhubaneswar_logo.png',
            'right_logo_path' => 'assets/images/National-Emblem.png',
            'footer_brand' => 'NIELIT',
            'footer_centre_label' => $short,
            'footer_about' => $name . ' under Ministry of Electronics & Information Technology, Govt. of India.',
            'footer_address' => $address,
            'footer_phone' => (static function () use ($centreRow, $isRaipur): string {
                $phone = trim((string) ($centreRow['phone'] ?? ''));
                if ($phone === '' && $isRaipur) {
                    return '+91 77129 90566';
                }
                return $phone;
            })(),
            'footer_email' => trim((string) ($centreRow['email'] ?? '')),
            'footer_copyright' => 'Designed & Developed by NIELIT Bhubaneswar IT Team © {year} | All Rights Reserved',
        ];
    }
}

if (!function_exists('centre_chrome_get')) {
    function centre_chrome_get($conn, int $centreId): array
    {
        $centreRow = centre_chrome_centre_row($conn, $centreId);
        $centreName = trim((string) ($centreRow['name'] ?? ''));
        $defaults = centre_chrome_defaults($centreRow, $centreName, $centreId);
        $defaults['centre_id'] = $centreId;
        if (!($conn instanceof mysqli)) {
            return $defaults;
        }
        centre_chrome_ensure_schema($conn);
        $stmt = $conn->prepare('SELECT * FROM report_public_centre_branding WHERE centre_id = ? LIMIT 1');
        if (!$stmt) {
            return $defaults;
        }
        $stmt->bind_param('i', $centreId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        foreach (centre_chrome_field_keys() as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                $defaults[$key] = $value;
            }
        }
        return $defaults;
    }
}

if (!function_exists('centre_chrome_get_main')) {
    function centre_chrome_get_main($conn): array
    {
        $id = centre_chrome_main_site_id($conn);
        return centre_chrome_get($conn, $id);
    }
}

if (!function_exists('centre_chrome_public_url')) {
    function centre_chrome_public_url(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $base = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('centre_chrome_format_copyright')) {
    function centre_chrome_format_copyright(string $text): string
    {
        return str_replace('{year}', (string) date('Y'), $text);
    }
}

if (!function_exists('centre_chrome_upload_logo')) {
    function centre_chrome_upload_logo(array $file, string $side = 'left'): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
            return ['success' => false, 'skip' => true, 'message' => 'No file'];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Logo upload failed.'];
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!isset($allowed[$mime])) {
            return ['success' => false, 'message' => 'Use JPG, PNG, GIF, WEBP or SVG for logos.'];
        }
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Logo must be 2 MB or smaller.'];
        }
        $dir = __DIR__ . '/../uploads/centre_chrome/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['success' => false, 'message' => 'Could not create logo folder.'];
        }
        $side = $side === 'right' ? 'right' : 'left';
        $filename = $side . '_' . uniqid('', true) . '.' . $allowed[$mime];
        $dest = $dir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['success' => false, 'message' => 'Could not save logo.'];
        }
        return ['success' => true, 'path' => 'uploads/centre_chrome/' . $filename];
    }
}

if (!function_exists('centre_chrome_save')) {
    function centre_chrome_save($conn, int $centreId, array $input, string $updatedBy = 'admin'): array
    {
        if (!($conn instanceof mysqli)) {
            return ['success' => false, 'message' => 'Database is not available.'];
        }
        centre_chrome_ensure_schema($conn);
        $current = centre_chrome_get($conn, $centreId);
        $values = [];
        foreach (centre_chrome_field_keys() as $key) {
            $values[$key] = trim((string) ($input[$key] ?? $current[$key] ?? ''));
        }
        $sql = 'INSERT INTO report_public_centre_branding
            (centre_id, header_name_hi, header_name_en, ministry_name_hi, ministry_name_en, ticker_text, banner_title, banner_tagline,
             left_logo_path, right_logo_path, footer_brand, footer_centre_label, footer_about, footer_address, footer_phone, footer_email, footer_copyright, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
             header_name_hi = VALUES(header_name_hi),
             header_name_en = VALUES(header_name_en),
             ministry_name_hi = VALUES(ministry_name_hi),
             ministry_name_en = VALUES(ministry_name_en),
             ticker_text = VALUES(ticker_text),
             banner_title = VALUES(banner_title),
             banner_tagline = VALUES(banner_tagline),
             left_logo_path = VALUES(left_logo_path),
             right_logo_path = VALUES(right_logo_path),
             footer_brand = VALUES(footer_brand),
             footer_centre_label = VALUES(footer_centre_label),
             footer_about = VALUES(footer_about),
             footer_address = VALUES(footer_address),
             footer_phone = VALUES(footer_phone),
             footer_email = VALUES(footer_email),
             footer_copyright = VALUES(footer_copyright),
             updated_by = VALUES(updated_by)';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not save header and footer.'];
        }
        $stmt->bind_param(
            'isssssssssssssssss',
            $centreId,
            $values['header_name_hi'],
            $values['header_name_en'],
            $values['ministry_name_hi'],
            $values['ministry_name_en'],
            $values['ticker_text'],
            $values['banner_title'],
            $values['banner_tagline'],
            $values['left_logo_path'],
            $values['right_logo_path'],
            $values['footer_brand'],
            $values['footer_centre_label'],
            $values['footer_about'],
            $values['footer_address'],
            $values['footer_phone'],
            $values['footer_email'],
            $values['footer_copyright'],
            $updatedBy
        );
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not save header and footer.' . ($err !== '' ? ' ' . $err : '')];
        }
        return ['success' => true, 'branding' => centre_chrome_get($conn, $centreId)];
    }
}

if (!function_exists('centre_chrome_list_centres')) {
    function centre_chrome_list_centres($conn): array
    {
        $rows = [];
        if (!($conn instanceof mysqli) || !centre_chrome_table_exists($conn, 'centres')) {
            return $rows;
        }
        $res = $conn->query('SELECT id, name, code FROM centres WHERE is_active = 1 ORDER BY name ASC');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}
