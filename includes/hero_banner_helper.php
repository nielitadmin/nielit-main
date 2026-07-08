<?php
/**
 * Hero carousel banner storage and helpers for index.php + admin/manage_homepage.php
 */

if (!function_exists('ensureHeroBannersSchema')) {
    function ensureHeroBannersSchema($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS hero_banners (
            id INT(11) NOT NULL AUTO_INCREMENT,
            filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            alt_text VARCHAR(255) NOT NULL DEFAULT 'NIELIT Banner',
            display_order INT(11) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_hero_banner_path (file_path),
            KEY idx_hero_banner_order (display_order),
            KEY idx_hero_banner_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('ensureHeroBannersSchema failed: ' . $conn->error);
            return false;
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('getHeroBannerStorageDir')) {
    function getHeroBannerStorageDir(): string
    {
        $dir = dirname(__DIR__) . '/assets/images/banners';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('getHeroBannerAllowedExtensions')) {
    function getHeroBannerAllowedExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
    }
}

if (!function_exists('heroBannerRelativePath')) {
    function heroBannerRelativePath(string $filename): string
    {
        return 'assets/images/banners/' . ltrim($filename, '/\\');
    }
}

if (!function_exists('heroBannerPublicUrl')) {
    function heroBannerPublicUrl(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
        return implode('/', array_map('rawurlencode', explode('/', $relativePath)));
    }
}

if (!function_exists('heroBannerAdminPreviewUrl')) {
    function heroBannerAdminPreviewUrl(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
        if (function_exists('app_url')) {
            return app_url($relativePath);
        }
        return '../' . $relativePath;
    }
}

if (!function_exists('syncHeroBannersFromFilesystem')) {
    function syncHeroBannersFromFilesystem($conn): void
    {
        if (!ensureHeroBannersSchema($conn)) {
            return;
        }

        $dir = getHeroBannerStorageDir();
        $allowed = getHeroBannerAllowedExtensions();
        $files = [];

        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }
                $ext = strtolower($fileInfo->getExtension());
                if (!in_array($ext, $allowed, true)) {
                    continue;
                }
                $files[] = $fileInfo->getPathname();
            }
            natsort($files);
            $files = array_values($files);
        }

        if ($files === []) {
            return;
        }

        $maxOrder = 0;
        $orderResult = $conn->query('SELECT COALESCE(MAX(display_order), 0) AS max_order FROM hero_banners');
        if ($orderResult && ($row = $orderResult->fetch_assoc())) {
            $maxOrder = (int) $row['max_order'];
        }

        $stmt = $conn->prepare(
            'INSERT INTO hero_banners (filename, file_path, alt_text, display_order, is_active)
             SELECT ?, ?, ?, ?, 1 FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM hero_banners WHERE file_path = ? LIMIT 1)'
        );
        if (!$stmt) {
            error_log('syncHeroBannersFromFilesystem prepare failed: ' . $conn->error);
            return;
        }

        $projectRoot = dirname(__DIR__);
        foreach ($files as $index => $absolutePath) {
            $relativePath = str_replace('\\', '/', substr($absolutePath, strlen($projectRoot) + 1));
            $filename = basename($absolutePath);
            $altText = 'NIELIT Banner ' . ($index + 1);
            $displayOrder = $maxOrder + $index + 1;
            $stmt->bind_param('sssis', $filename, $relativePath, $altText, $displayOrder, $relativePath);
            $stmt->execute();
        }

        $stmt->close();
    }
}

if (!function_exists('listHeroBanners')) {
    function listHeroBanners($conn, bool $activeOnly = false): array
    {
        if (!ensureHeroBannersSchema($conn)) {
            return [];
        }

        $sql = 'SELECT * FROM hero_banners';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY display_order ASC, id ASC';

        $result = $conn->query($sql);
        if (!$result) {
            error_log('listHeroBanners failed: ' . $conn->error);
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }
}

if (!function_exists('getActiveHeroBannerSlides')) {
    function getActiveHeroBannerSlides($conn): array
    {
        $slides = [];
        foreach (listHeroBanners($conn, true) as $banner) {
            $relativePath = (string) ($banner['file_path'] ?? '');
            $absolutePath = dirname(__DIR__) . '/' . str_replace('\\', '/', $relativePath);
            if (!is_file($absolutePath)) {
                continue;
            }
            $slides[] = [
                'url' => heroBannerPublicUrl($relativePath),
                'alt' => (string) ($banner['alt_text'] ?? 'NIELIT Banner'),
            ];
        }

        return $slides;
    }
}

if (!function_exists('validateHeroBannerUpload')) {
    function validateHeroBannerUpload(array $file): array
    {
        $allowedExtensions = getHeroBannerAllowedExtensions();
        $allowedMime = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/avif',
        ];
        $maxSize = 8 * 1024 * 1024;

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload failed. Please try again.'];
        }

        if (($file['size'] ?? 0) > $maxSize) {
            return ['success' => false, 'message' => 'File size exceeds 8MB limit'];
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, WebP, GIF, AVIF'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($mime && !in_array($mime, $allowedMime, true)) {
            return ['success' => false, 'message' => 'Invalid image file'];
        }

        return ['success' => true, 'extension' => $extension];
    }
}

if (!function_exists('uploadHeroBanner')) {
    function uploadHeroBanner($conn, ?array $file, string $altText = ''): array
    {
        if (!ensureHeroBannersSchema($conn)) {
            return ['success' => false, 'message' => 'Banner database setup failed'];
        }

        if ($file === null) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        $validation = validateHeroBannerUpload($file);
        if (!$validation['success']) {
            return $validation;
        }

        $extension = $validation['extension'];
        $filename = 'hero_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $storageDir = getHeroBannerStorageDir();
        $absolutePath = $storageDir . DIRECTORY_SEPARATOR . $filename;
        $relativePath = heroBannerRelativePath($filename);

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            return ['success' => false, 'message' => 'Failed to save uploaded file'];
        }

        $maxOrder = 0;
        $orderResult = $conn->query('SELECT COALESCE(MAX(display_order), 0) AS max_order FROM hero_banners');
        if ($orderResult && ($row = $orderResult->fetch_assoc())) {
            $maxOrder = (int) $row['max_order'];
        }

        $altText = trim($altText);
        if ($altText === '') {
            $altText = 'NIELIT Banner';
        }

        $displayOrder = $maxOrder + 1;
        $stmt = $conn->prepare(
            'INSERT INTO hero_banners (filename, file_path, alt_text, display_order, is_active) VALUES (?, ?, ?, ?, 1)'
        );
        if (!$stmt) {
            @unlink($absolutePath);
            return ['success' => false, 'message' => 'Database error while saving banner'];
        }

        $stmt->bind_param('sssi', $filename, $relativePath, $altText, $displayOrder);
        if (!$stmt->execute()) {
            @unlink($absolutePath);
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to save banner record'];
        }

        $bannerId = (int) $stmt->insert_id;
        $stmt->close();

        return [
            'success' => true,
            'message' => 'Banner uploaded successfully',
            'banner' => [
                'id' => $bannerId,
                'filename' => $filename,
                'file_path' => $relativePath,
                'alt_text' => $altText,
                'display_order' => $displayOrder,
                'is_active' => 1,
                'preview_url' => heroBannerAdminPreviewUrl($relativePath),
            ],
        ];
    }
}

if (!function_exists('deleteHeroBanner')) {
    function deleteHeroBanner($conn, int $bannerId): array
    {
        if (!ensureHeroBannersSchema($conn)) {
            return ['success' => false, 'message' => 'Banner database setup failed'];
        }

        $stmt = $conn->prepare('SELECT * FROM hero_banners WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }

        $stmt->bind_param('i', $bannerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $banner = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$banner) {
            return ['success' => false, 'message' => 'Banner not found'];
        }

        $relativePath = str_replace('\\', '/', (string) $banner['file_path']);
        $absolutePath = dirname(__DIR__) . '/' . $relativePath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        $deleteStmt = $conn->prepare('DELETE FROM hero_banners WHERE id = ?');
        if (!$deleteStmt) {
            return ['success' => false, 'message' => 'Database error'];
        }

        $deleteStmt->bind_param('i', $bannerId);
        $success = $deleteStmt->execute();
        $deleteStmt->close();

        if (!$success) {
            return ['success' => false, 'message' => 'Failed to delete banner'];
        }

        return ['success' => true, 'message' => 'Banner deleted successfully'];
    }
}

if (!function_exists('reorderHeroBanners')) {
    function reorderHeroBanners($conn, array $orderData): bool
    {
        if (!ensureHeroBannersSchema($conn)) {
            return false;
        }

        if ($orderData === []) {
            return false;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE hero_banners SET display_order = ? WHERE id = ?');
            if (!$stmt) {
                throw new RuntimeException($conn->error);
            }

            foreach ($orderData as $item) {
                $id = (int) ($item['id'] ?? 0);
                $order = (int) ($item['order'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $stmt->bind_param('ii', $order, $id);
                $stmt->execute();
            }

            $stmt->close();
            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('reorderHeroBanners failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('toggleHeroBannerStatus')) {
    function toggleHeroBannerStatus($conn, int $bannerId, int $status): bool
    {
        if (!ensureHeroBannersSchema($conn)) {
            return false;
        }

        $status = $status === 1 ? 1 : 0;
        $stmt = $conn->prepare('UPDATE hero_banners SET is_active = ? WHERE id = ?');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $status, $bannerId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}

if (!function_exists('updateHeroBannerAltText')) {
    function updateHeroBannerAltText($conn, int $bannerId, string $altText): array
    {
        if (!ensureHeroBannersSchema($conn)) {
            return ['success' => false, 'message' => 'Banner database setup failed'];
        }

        $altText = trim(strip_tags($altText));
        if ($altText === '') {
            return ['success' => false, 'message' => 'Alt text is required'];
        }

        $stmt = $conn->prepare('UPDATE hero_banners SET alt_text = ? WHERE id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }

        $stmt->bind_param('si', $altText, $bannerId);
        $success = $stmt->execute();
        $stmt->close();

        if (!$success) {
            return ['success' => false, 'message' => 'Failed to update alt text'];
        }

        return ['success' => true, 'message' => 'Alt text updated', 'alt_text' => $altText];
    }
}

if (!function_exists('scanHeroBannerFilesFromDisk')) {
    function scanHeroBannerFilesFromDisk(): array
    {
        $dir = getHeroBannerStorageDir();
        $allowed = getHeroBannerAllowedExtensions();
        $files = [];

        if (!is_dir($dir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $ext = strtolower($fileInfo->getExtension());
            if (in_array($ext, $allowed, true)) {
                $files[] = $fileInfo->getPathname();
            }
        }

        natsort($files);
        return array_values($files);
    }
}

if (!function_exists('getHeroBannerSlidesForIndex')) {
    function getHeroBannerSlidesForIndex($conn): array
    {
        $slides = getActiveHeroBannerSlides($conn);
        if ($slides !== []) {
            return $slides;
        }

        $projectRoot = dirname(__DIR__);
        foreach (scanHeroBannerFilesFromDisk() as $index => $absolutePath) {
            $relativePath = str_replace('\\', '/', substr($absolutePath, strlen($projectRoot) + 1));
            $slides[] = [
                'url' => heroBannerPublicUrl($relativePath),
                'alt' => 'NIELIT Banner ' . ($index + 1),
            ];
        }

        return $slides;
    }
}
