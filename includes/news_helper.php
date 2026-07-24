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

        if (!ensureNewsImagesTable($conn)) {
            return false;
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('ensureNewsImagesTable')) {
    function ensureNewsImagesTable($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS news_images (
            id INT PRIMARY KEY AUTO_INCREMENT,
            news_id INT NOT NULL,
            image_url VARCHAR(500) NOT NULL,
            display_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_news_images_news (news_id),
            KEY idx_news_images_order (news_id, display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('ensureNewsImagesTable failed: ' . $conn->error);
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
        if ($extension === '') {
            $extension = 'jpg';
        }
        $filename = 'news_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $filepath = getNewsUploadDir() . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'message' => 'Failed to upload file'];
        }

        return ['success' => true, 'path' => 'uploads/news/' . $filename];
    }
}

if (!function_exists('normalizeNewsUploadFiles')) {
    /**
     * Normalize single or multi $_FILES field into a list of file arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    function normalizeNewsUploadFiles(?array $filesField): array
    {
        if ($filesField === null || !isset($filesField['name'])) {
            return [];
        }

        // Single file shape
        if (!is_array($filesField['name'])) {
            if (($filesField['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return [];
            }
            return [$filesField];
        }

        $normalized = [];
        $count = count($filesField['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $normalized[] = [
                'name' => $filesField['name'][$i] ?? '',
                'type' => $filesField['type'][$i] ?? '',
                'tmp_name' => $filesField['tmp_name'][$i] ?? '',
                'error' => $filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $filesField['size'][$i] ?? 0,
            ];
        }

        return $normalized;
    }
}

if (!function_exists('listNewsImages')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function listNewsImages($conn, int $newsId): array
    {
        if (!ensureNewsImagesTable($conn) || $newsId <= 0) {
            return [];
        }

        $stmt = $conn->prepare(
            'SELECT id, news_id, image_url, display_order, created_at
             FROM news_images
             WHERE news_id = ?
             ORDER BY display_order ASC, id ASC'
        );
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $newsId);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }
        $stmt->close();

        return $items;
    }
}

if (!function_exists('loadNewsImagesMap')) {
    /**
     * @param array<int, int|string> $newsIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    function loadNewsImagesMap($conn, array $newsIds): array
    {
        $newsIds = array_values(array_unique(array_filter(array_map('intval', $newsIds))));
        if ($newsIds === [] || !ensureNewsImagesTable($conn)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($newsIds), '?'));
        $types = str_repeat('i', count($newsIds));
        $stmt = $conn->prepare(
            "SELECT id, news_id, image_url, display_order, created_at
             FROM news_images
             WHERE news_id IN ($placeholders)
             ORDER BY display_order ASC, id ASC"
        );
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param($types, ...$newsIds);
        $stmt->execute();
        $result = $stmt->get_result();
        $map = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $nid = (int) $row['news_id'];
                if (!isset($map[$nid])) {
                    $map[$nid] = [];
                }
                $map[$nid][] = $row;
            }
        }
        $stmt->close();

        return $map;
    }
}

if (!function_exists('hydrateNewsArticlesWithImages')) {
    /**
     * @param array<int, array<string, mixed>> $articles
     * @return array<int, array<string, mixed>>
     */
    function hydrateNewsArticlesWithImages($conn, array $articles): array
    {
        if ($articles === []) {
            return [];
        }

        $ids = [];
        foreach ($articles as $article) {
            $ids[] = (int) ($article['id'] ?? 0);
        }
        $map = loadNewsImagesMap($conn, $ids);

        foreach ($articles as &$article) {
            $nid = (int) ($article['id'] ?? 0);
            $gallery = $map[$nid] ?? [];
            if ($gallery === []) {
                $legacy = trim((string) ($article['image_url'] ?? ''));
                if ($legacy !== '') {
                    $gallery = [[
                        'id' => 0,
                        'news_id' => $nid,
                        'image_url' => $legacy,
                        'display_order' => 0,
                        'created_at' => null,
                    ]];
                }
            }
            $article['images'] = $gallery;
            $article['image_urls'] = newsResolvedImageUrls($gallery);
        }
        unset($article);

        return $articles;
    }
}

if (!function_exists('newsResolvedImageUrls')) {
    /**
     * @param array<int, array<string, mixed>> $images
     * @return array<int, string>
     */
    function newsResolvedImageUrls(array $images): array
    {
        $urls = [];
        foreach ($images as $image) {
            $url = newsImageUrl($image['image_url'] ?? '');
            if ($url !== '') {
                $urls[] = $url;
            }
        }
        return $urls;
    }
}

if (!function_exists('newsArticleImageUrls')) {
    /**
     * @param array<string, mixed> $article
     * @return array<int, string>
     */
    function newsArticleImageUrls(array $article): array
    {
        if (!empty($article['image_urls']) && is_array($article['image_urls'])) {
            return array_values(array_filter(array_map('strval', $article['image_urls'])));
        }
        if (!empty($article['images']) && is_array($article['images'])) {
            return newsResolvedImageUrls($article['images']);
        }
        $single = newsImageUrl($article['image_url'] ?? '');
        return $single !== '' ? [$single] : [];
    }
}

if (!function_exists('nextNewsImageOrder')) {
    function nextNewsImageOrder($conn, int $newsId): int
    {
        $stmt = $conn->prepare('SELECT COALESCE(MAX(display_order), -1) AS max_order FROM news_images WHERE news_id = ?');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $newsId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return ((int) ($row['max_order'] ?? -1)) + 1;
    }
}

if (!function_exists('insertNewsImage')) {
    function insertNewsImage($conn, int $newsId, string $imageUrl, ?int $displayOrder = null): bool
    {
        if (!ensureNewsImagesTable($conn) || $newsId <= 0 || trim($imageUrl) === '') {
            return false;
        }

        $order = $displayOrder ?? nextNewsImageOrder($conn, $newsId);
        $stmt = $conn->prepare(
            'INSERT INTO news_images (news_id, image_url, display_order) VALUES (?, ?, ?)'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('isi', $newsId, $imageUrl, $order);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('deleteNewsImagesByIds')) {
    /**
     * @param array<int, int|string> $imageIds
     */
    function deleteNewsImagesByIds($conn, int $newsId, array $imageIds): int
    {
        $imageIds = array_values(array_unique(array_filter(array_map('intval', $imageIds))));
        if ($imageIds === [] || $newsId <= 0 || !ensureNewsImagesTable($conn)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
        $types = 'i' . str_repeat('i', count($imageIds));
        $params = array_merge([$newsId], $imageIds);

        $stmt = $conn->prepare(
            "SELECT id, image_url FROM news_images WHERE news_id = ? AND id IN ($placeholders)"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();

        if ($rows === []) {
            return 0;
        }

        $deleteStmt = $conn->prepare(
            "DELETE FROM news_images WHERE news_id = ? AND id IN ($placeholders)"
        );
        if (!$deleteStmt) {
            return 0;
        }
        $deleteStmt->bind_param($types, ...$params);
        $deleteStmt->execute();
        $affected = $deleteStmt->affected_rows;
        $deleteStmt->close();

        foreach ($rows as $row) {
            maybeDeleteLocalNewsImageFile((string) ($row['image_url'] ?? ''));
        }

        return max(0, (int) $affected);
    }
}

if (!function_exists('deleteAllNewsImages')) {
    function deleteAllNewsImages($conn, int $newsId): void
    {
        if ($newsId <= 0 || !ensureNewsImagesTable($conn)) {
            return;
        }

        $images = listNewsImages($conn, $newsId);
        $stmt = $conn->prepare('DELETE FROM news_images WHERE news_id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $newsId);
            $stmt->execute();
            $stmt->close();
        }

        foreach ($images as $image) {
            maybeDeleteLocalNewsImageFile((string) ($image['image_url'] ?? ''));
        }
    }
}

if (!function_exists('maybeDeleteLocalNewsImageFile')) {
    function maybeDeleteLocalNewsImageFile(string $imageUrl): void
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '') {
            return;
        }

        $path = $imageUrl;
        if (preg_match('#^https?://#i', $imageUrl)) {
            $base = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
            if ($base !== '' && strpos($imageUrl, $base . '/') === 0) {
                $path = substr($imageUrl, strlen($base) + 1);
            } else {
                return;
            }
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));
        if (strpos($path, 'uploads/news/') !== 0) {
            return;
        }

        $full = dirname(__DIR__) . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

if (!function_exists('syncNewsCoverImage')) {
    function syncNewsCoverImage($conn, int $newsId): void
    {
        if ($newsId <= 0) {
            return;
        }

        $images = listNewsImages($conn, $newsId);
        $cover = '';
        if (!empty($images[0]['image_url'])) {
            $cover = (string) $images[0]['image_url'];
        }

        $stmt = $conn->prepare('UPDATE news SET image_url = ? WHERE id = ?');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('si', $cover, $newsId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('addNewsUploadedImages')) {
    /**
     * @param array<int, array<string, mixed>> $files
     */
    function addNewsUploadedImages($conn, int $newsId, array $files): array
    {
        $added = 0;
        $errors = [];

        foreach ($files as $file) {
            $upload = uploadNewsImage($file);
            if (!$upload['success']) {
                $errors[] = $upload['message'] ?? 'Upload failed';
                continue;
            }
            $publicUrl = (defined('APP_URL') ? rtrim((string) APP_URL, '/') . '/' : '') . $upload['path'];
            if (insertNewsImage($conn, $newsId, $publicUrl)) {
                $added++;
            } else {
                $errors[] = 'Failed to save an uploaded image';
            }
        }

        return ['added' => $added, 'errors' => $errors];
    }
}

if (!function_exists('renderNewsImageSlideshow')) {
    /**
     * Bootstrap carousel / single image markup for news galleries.
     *
     * @param array<int, string> $imageUrls
     * @param array<string, mixed> $options
     */
    function renderNewsImageSlideshow(string $carouselId, array $imageUrls, array $options = []): string
    {
        $imageUrls = array_values(array_filter(array_map('strval', $imageUrls)));
        if ($imageUrls === []) {
            return '';
        }

        $alt = (string) ($options['alt'] ?? 'News image');
        $class = trim((string) ($options['class'] ?? ''));
        $imgClass = trim((string) ($options['img_class'] ?? ''));
        $interval = (int) ($options['interval'] ?? 4000);
        $showControls = !isset($options['controls']) || !empty($options['controls']);
        $showIndicators = !isset($options['indicators']) || !empty($options['indicators']);
        $fade = !empty($options['fade']);

        if (count($imageUrls) === 1) {
            $src = htmlspecialchars($imageUrls[0], ENT_QUOTES, 'UTF-8');
            $altEsc = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
            $imgCls = htmlspecialchars(trim('news-slide-image ' . $imgClass), ENT_QUOTES, 'UTF-8');
            $wrapCls = htmlspecialchars(trim('news-slide-single ' . $class), ENT_QUOTES, 'UTF-8');
            return '<div class="' . $wrapCls . '"><img src="' . $src . '" alt="' . $altEsc . '" class="' . $imgCls . '"></div>';
        }

        $carouselId = preg_replace('/[^a-zA-Z0-9_-]/', '', $carouselId) ?: ('newsCarousel' . mt_rand(1000, 9999));
        $slideClass = 'carousel slide' . ($fade ? ' carousel-fade' : '');
        $html = '<div id="' . htmlspecialchars($carouselId, ENT_QUOTES, 'UTF-8') . '" class="' .
            htmlspecialchars(trim($slideClass . ' news-image-carousel ' . $class), ENT_QUOTES, 'UTF-8') .
            '" data-bs-ride="carousel" data-bs-interval="' . $interval . '">';

        if ($showIndicators) {
            $html .= '<div class="carousel-indicators">';
            foreach ($imageUrls as $i => $_url) {
                $active = $i === 0 ? ' class="active" aria-current="true"' : '';
                $html .= '<button type="button" data-bs-target="#' . htmlspecialchars($carouselId, ENT_QUOTES, 'UTF-8') .
                    '" data-bs-slide-to="' . $i . '"' . $active . ' aria-label="Slide ' . ($i + 1) . '"></button>';
            }
            $html .= '</div>';
        }

        $html .= '<div class="carousel-inner">';
        foreach ($imageUrls as $i => $url) {
            $active = $i === 0 ? ' active' : '';
            $html .= '<div class="carousel-item' . $active . '">';
            $html .= '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="' .
                htmlspecialchars(trim('d-block w-100 news-slide-image ' . $imgClass), ENT_QUOTES, 'UTF-8') .
                '" alt="' . htmlspecialchars($alt . ' ' . ($i + 1), ENT_QUOTES, 'UTF-8') . '">';
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($showControls) {
            $html .= '<button class="carousel-control-prev" type="button" data-bs-target="#' .
                htmlspecialchars($carouselId, ENT_QUOTES, 'UTF-8') . '" data-bs-slide="prev">' .
                '<span class="carousel-control-prev-icon" aria-hidden="true"></span>' .
                '<span class="visually-hidden">Previous</span></button>';
            $html .= '<button class="carousel-control-next" type="button" data-bs-target="#' .
                htmlspecialchars($carouselId, ENT_QUOTES, 'UTF-8') . '" data-bs-slide="next">' .
                '<span class="carousel-control-next-icon" aria-hidden="true"></span>' .
                '<span class="visually-hidden">Next</span></button>';
        }

        $html .= '</div>';
        return $html;
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

        return hydrateNewsArticlesWithImages($conn, $items);
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

        if (!$article) {
            return null;
        }

        $hydrated = hydrateNewsArticlesWithImages($conn, [$article]);
        return $hydrated[0] ?? $article;
    }
}

if (!function_exists('saveNewsArticle')) {
    function saveNewsArticle($conn, array $data, ?array $file, string $adminUsername, ?array $multiFiles = null): array
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
        $removeImageIds = $data['remove_image_ids'] ?? [];
        if (!is_array($removeImageIds)) {
            $removeImageIds = [];
        }

        if ($title === '' || $content === '') {
            return ['success' => false, 'message' => 'Title and content are required'];
        }

        $uploadFiles = normalizeNewsUploadFiles($multiFiles);
        if ($uploadFiles === []) {
            $uploadFiles = normalizeNewsUploadFiles($file);
        }

        // Keep existing cover URL when editing and no new cover URL was typed.
        if ($id > 0 && $imageUrl === '') {
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
            if (!$ok) {
                return ['success' => false, 'message' => 'Failed to update news article'];
            }
            $newsId = $id;
            $message = 'News updated successfully';
        } else {
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
            $newsId = (int) $stmt->insert_id;
            $stmt->close();
            if (!$ok || $newsId <= 0) {
                return ['success' => false, 'message' => 'Failed to add news article'];
            }
            $message = 'News added successfully';
        }

        if ($removeImageIds !== []) {
            deleteNewsImagesByIds($conn, $newsId, $removeImageIds);
        }

        $uploadResult = addNewsUploadedImages($conn, $newsId, $uploadFiles);
        if ($uploadResult['added'] === 0 && $uploadResult['errors'] !== [] && $uploadFiles !== []) {
            return [
                'success' => false,
                'message' => $uploadResult['errors'][0],
                'id' => $newsId,
            ];
        }

        // Optional external URL: add as a gallery slide when not already present.
        $typedUrl = trim((string) ($data['image_url'] ?? ''));
        if ($typedUrl !== '' && preg_match('#^https?://#i', $typedUrl)) {
            $existingGallery = listNewsImages($conn, $newsId);
            $already = false;
            foreach ($existingGallery as $img) {
                if (trim((string) ($img['image_url'] ?? '')) === $typedUrl) {
                    $already = true;
                    break;
                }
            }
            if (!$already) {
                insertNewsImage($conn, $newsId, $typedUrl);
            }
        }

        // Migrate legacy single cover into gallery only when nothing was removed
        // (avoids restoring a cover the admin just deleted).
        $gallery = listNewsImages($conn, $newsId);
        if ($gallery === [] && $removeImageIds === []) {
            $cover = '';
            $rowStmt = $conn->prepare('SELECT image_url FROM news WHERE id = ? LIMIT 1');
            if ($rowStmt) {
                $rowStmt->bind_param('i', $newsId);
                $rowStmt->execute();
                $rowRes = $rowStmt->get_result();
                $row = $rowRes ? $rowRes->fetch_assoc() : null;
                $rowStmt->close();
                $cover = trim((string) ($row['image_url'] ?? ''));
            }
            if ($cover !== '') {
                insertNewsImage($conn, $newsId, $cover);
            }
        }

        syncNewsCoverImage($conn, $newsId);

        if ($uploadResult['added'] > 0) {
            $message .= ' (' . $uploadResult['added'] . ' image' . ($uploadResult['added'] > 1 ? 's' : '') . ' added)';
        }

        return ['success' => true, 'message' => $message, 'id' => $newsId];
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

        return hydrateNewsArticlesWithImages($conn, $items);
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

        deleteAllNewsImages($conn, $id);

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
