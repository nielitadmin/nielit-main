<?php
/**
 * News articles helper for homepage and admin management.
 */

if (!function_exists('ensureNewsTable')) {
    function ensureNewsTable($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS news (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            category VARCHAR(100),
            image_url VARCHAR(500),
            is_featured TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_by VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('ensureNewsTable failed: ' . $conn->error);
            return false;
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('getNewsUploadDir')) {
    function getNewsUploadDir(): string
    {
        $dir = dirname(__DIR__) . '/uploads/news';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('uploadNewsImage')) {
    function uploadNewsImage(?array $file): array
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize = 5 * 1024 * 1024;

        if ($file === null || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload failed. Please try again.'];
        }

        if (!in_array($file['type'] ?? '', $allowedTypes, true)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, WebP, GIF'];
        }

        if (($file['size'] ?? 0) > $maxSize) {
            return ['success' => false, 'message' => 'File size exceeds 5MB limit'];
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $filename = 'news_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $filepath = getNewsUploadDir() . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'message' => 'Failed to upload file'];
        }

        return ['success' => true, 'path' => 'uploads/news/' . $filename];
    }
}

if (!function_exists('listAllNews')) {
    function listAllNews($conn): array
    {
        if (!ensureNewsTable($conn)) {
            return [];
        }

        $result = $conn->query('SELECT * FROM news ORDER BY created_at DESC');
        if (!$result) {
            return [];
        }

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        return $items;
    }
}

if (!function_exists('getNewsArticle')) {
    function getNewsArticle($conn, int $id): ?array
    {
        if (!ensureNewsTable($conn) || $id <= 0) {
            return null;
        }

        $stmt = $conn->prepare('SELECT * FROM news WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $article = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $article ?: null;
    }
}

if (!function_exists('saveNewsArticle')) {
    function saveNewsArticle($conn, array $data, ?array $file, string $adminUsername): array
    {
        if (!ensureNewsTable($conn)) {
            return ['success' => false, 'message' => 'News database setup failed'];
        }

        $id = (int) ($data['id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        $content = trim((string) ($data['content'] ?? ''));
        $category = trim((string) ($data['category'] ?? ''));
        $imageUrl = trim((string) ($data['image_url'] ?? ''));
        $isFeatured = !empty($data['is_featured']) ? 1 : 0;
        $isActive = !empty($data['is_active']) ? 1 : 0;

        if ($title === '' || $content === '') {
            return ['success' => false, 'message' => 'Title and content are required'];
        }

        if ($file !== null && ($file['size'] ?? 0) > 0) {
            $upload = uploadNewsImage($file);
            if (!$upload['success']) {
                return $upload;
            }
            $imageUrl = (defined('APP_URL') ? rtrim(APP_URL, '/') . '/' : '') . $upload['path'];
        } elseif ($id > 0 && $imageUrl === '') {
            $existing = getNewsArticle($conn, $id);
            if ($existing) {
                $imageUrl = (string) ($existing['image_url'] ?? '');
            }
        }

        if ($id > 0) {
            $stmt = $conn->prepare(
                'UPDATE news SET title=?, content=?, category=?, image_url=?, is_featured=?, is_active=? WHERE id=?'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error'];
            }
            $stmt->bind_param('ssssiii', $title, $content, $category, $imageUrl, $isFeatured, $isActive, $id);
            $ok = $stmt->execute();
            $stmt->close();

            return $ok
                ? ['success' => true, 'message' => 'News updated successfully', 'id' => $id]
                : ['success' => false, 'message' => 'Failed to update news article'];
        }

        $createdAt = date('Y-m-d H:i:s');
        $stmt = $conn->prepare(
            'INSERT INTO news (title, content, category, image_url, is_featured, is_active, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }
        $stmt->bind_param('ssssiiss', $title, $content, $category, $imageUrl, $isFeatured, $isActive, $adminUsername, $createdAt);
        $ok = $stmt->execute();
        $newId = (int) $stmt->insert_id;
        $stmt->close();

        return $ok
            ? ['success' => true, 'message' => 'News added successfully', 'id' => $newId]
            : ['success' => false, 'message' => 'Failed to add news article'];
    }
}

if (!function_exists('listPublicNews')) {
    /**
     * Active news for public pages (featured first).
     *
     * @return array<int, array<string, mixed>>
     */
    function listPublicNews($conn, int $limit = 50): array
    {
        if (!ensureNewsTable($conn)) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "SELECT * FROM news
                WHERE is_active = 1
                ORDER BY is_featured DESC, created_at DESC, id DESC
                LIMIT " . (int) $limit;
        $result = $conn->query($sql);
        if (!$result) {
            return [];
        }

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        return $items;
    }
}

if (!function_exists('getPublicNewsArticle')) {
    /**
     * Active news article for public detail view.
     *
     * @return array<string, mixed>|null
     */
    function getPublicNewsArticle($conn, int $id): ?array
    {
        $article = getNewsArticle($conn, $id);
        if (!$article) {
            return null;
        }
        if (empty($article['is_active'])) {
            return null;
        }
        return $article;
    }
}

if (!function_exists('newsImageUrl')) {
    function newsImageUrl(?string $imageUrl): string
    {
        $imageUrl = trim((string) $imageUrl);
        if ($imageUrl === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $imageUrl)) {
            return $imageUrl;
        }
        $base = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
        return $base . '/' . ltrim($imageUrl, '/');
    }
}

if (!function_exists('newsPublicUrl')) {
    function newsPublicUrl(int $id = 0): string
    {
        if (function_exists('app_url')) {
            $url = app_url('public/news');
        } else {
            $base = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
            $url = $base . '/public/news';
        }
        if ($id > 0) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'id=' . $id;
        }
        return $url;
    }
}

if (!function_exists('newsCategoryBadgeClass')) {
    function newsCategoryBadgeClass(?string $category): string
    {
        $key = strtolower(trim((string) $category));
        if ($key === '') {
            return 'bg-secondary';
        }
        if (strpos($key, 'achievement') !== false || strpos($key, 'success') !== false) {
            return 'bg-success';
        }
        if (strpos($key, 'event') !== false || strpos($key, 'fair') !== false) {
            return 'bg-warning text-dark';
        }
        if (strpos($key, 'update') !== false || strpos($key, 'notice') !== false) {
            return 'bg-info text-dark';
        }
        if (strpos($key, 'course') !== false) {
            return 'bg-primary';
        }
        return 'bg-secondary';
    }
}

if (!function_exists('newsExcerpt')) {
    function newsExcerpt(?string $content, int $length = 140): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $content)) ?? '');
        if ($plain === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            $excerpt = mb_substr($plain, 0, $length);
            return mb_strlen($plain) > $length ? $excerpt . '…' : $excerpt;
        }
        $excerpt = substr($plain, 0, $length);
        return strlen($plain) > $length ? $excerpt . '…' : $excerpt;
    }
}

if (!function_exists('deleteNewsArticle')) {
    function deleteNewsArticle($conn, int $id): array
    {
        if (!ensureNewsTable($conn) || $id <= 0) {
            return ['success' => false, 'message' => 'Invalid news article'];
        }

        $stmt = $conn->prepare('DELETE FROM news WHERE id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }

        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok
            ? ['success' => true, 'message' => 'News deleted successfully']
            : ['success' => false, 'message' => 'Failed to delete news article'];
    }
}
