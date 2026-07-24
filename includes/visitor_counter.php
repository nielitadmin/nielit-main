<?php
/**
 * Page visitor counter — tracks page views and unique visitors per day.
 */

if (!function_exists('visitorCounterShouldTrack')) {
    function visitorCounterShouldTrack(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return false;
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return false;
        }

        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (preg_match('/(?:ajax|save_|update_|submit_|delete_|export_|download_)/i', $script)) {
            return false;
        }

        // Any JSON API / XHR endpoint should never be counted as a page view.
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (strpos($accept, 'application/json') !== false) {
            return false;
        }

        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
        if (strpos($contentType, 'application/json') !== false) {
            return false;
        }

        $path = visitorCounterNormalizePath();
        if ($path === '' || preg_match('#/(?:api|migrations)(?:/|$)#i', $path)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('visitorCounterNormalizePath')) {
    function visitorCounterNormalizePath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim(str_replace('\\', '/', $path), '/');
        if ($path === '/') {
            $path = '/';
        }

        if (defined('APP_URL')) {
            $base = parse_url(APP_URL, PHP_URL_PATH) ?: '';
            $base = rtrim(str_replace('\\', '/', $base), '/');
            if ($base !== '' && $base !== '/' && strpos($path, $base) === 0) {
                $path = substr($path, strlen($base)) ?: '/';
                $path = '/' . trim($path, '/');
                if ($path === '') {
                    $path = '/';
                }
            }
        }

        if (substr($path, -4) === '.php') {
            $path = substr($path, 0, -4);
            if ($path === '') {
                $path = '/';
            }
        }

        return mb_substr($path, 0, 255);
    }
}

if (!function_exists('visitorCounterSessionKey')) {
    function visitorCounterSessionKey(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['visitor_session_key'])) {
            $_SESSION['visitor_session_key'] = bin2hex(random_bytes(16));
        }

        return (string) $_SESSION['visitor_session_key'];
    }
}

if (!function_exists('visitorCounterEnsureTables')) {
    function visitorCounterEnsureTables($conn): bool
    {
        static $ready = null;
        if ($ready === true) {
            return true;
        }

        $queries = [
            "CREATE TABLE IF NOT EXISTS page_visit_daily (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                page_path VARCHAR(255) NOT NULL,
                visit_date DATE NOT NULL,
                page_views INT UNSIGNED NOT NULL DEFAULT 0,
                unique_visitors INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uq_page_visit_daily (page_path, visit_date),
                KEY idx_page_visit_date (visit_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS page_visit_uniques (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                page_path VARCHAR(255) NOT NULL,
                visit_date DATE NOT NULL,
                session_key VARCHAR(64) NOT NULL,
                first_seen_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_page_visit_unique (page_path, visit_date, session_key),
                KEY idx_page_visit_uniques_date (visit_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS site_visit_daily (
                visit_date DATE NOT NULL,
                page_views INT UNSIGNED NOT NULL DEFAULT 0,
                unique_visitors INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (visit_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS site_visit_uniques (
                visit_date DATE NOT NULL,
                session_key VARCHAR(64) NOT NULL,
                first_seen_at DATETIME NOT NULL,
                PRIMARY KEY (visit_date, session_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($queries as $sql) {
            if (!$conn->query($sql)) {
                error_log('Visitor counter table setup failed: ' . $conn->error);
                return false;
            }
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('visitorCounterConnectionIsUsable')) {
    function visitorCounterConnectionIsUsable($conn): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }

        try {
            return @$conn->ping();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('visitorCounterTrackIfReady')) {
    function visitorCounterTrackIfReady($conn = null): void
    {
        if ($conn === null) {
            global $conn;
        }

        if (!visitorCounterConnectionIsUsable($conn)) {
            return;
        }

        try {
            trackPageVisit($conn);
        } catch (Throwable $e) {
            error_log('Visitor counter tracking failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('trackPageVisit')) {
    function trackPageVisit($conn): void
    {
        static $tracked = false;
        if ($tracked || !visitorCounterShouldTrack()) {
            return;
        }

        if (!$conn || !visitorCounterConnectionIsUsable($conn)) {
            return;
        }

        try {
            if (!visitorCounterEnsureTables($conn)) {
                return;
            }

            $tracked = true;

            $pagePath = visitorCounterNormalizePath();
            $visitDate = date('Y-m-d');
            $sessionKey = visitorCounterSessionKey();
            $now = date('Y-m-d H:i:s');

            $isNewPageVisitor = false;
            $stmt = $conn->prepare(
                'INSERT IGNORE INTO page_visit_uniques (page_path, visit_date, session_key, first_seen_at) VALUES (?, ?, ?, ?)'
            );
            if ($stmt) {
                $stmt->bind_param('ssss', $pagePath, $visitDate, $sessionKey, $now);
                $stmt->execute();
                $isNewPageVisitor = $stmt->affected_rows > 0;
                $stmt->close();
            }

            if ($isNewPageVisitor) {
                $sql = 'INSERT INTO page_visit_daily (page_path, visit_date, page_views, unique_visitors)
                        VALUES (?, ?, 1, 1)
                        ON DUPLICATE KEY UPDATE page_views = page_views + 1, unique_visitors = unique_visitors + 1';
            } else {
                $sql = 'INSERT INTO page_visit_daily (page_path, visit_date, page_views, unique_visitors)
                        VALUES (?, ?, 1, 0)
                        ON DUPLICATE KEY UPDATE page_views = page_views + 1';
            }

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ss', $pagePath, $visitDate);
                $stmt->execute();
                $stmt->close();
            }

            $isNewSiteVisitor = false;
            $stmt = $conn->prepare(
                'INSERT IGNORE INTO site_visit_uniques (visit_date, session_key, first_seen_at) VALUES (?, ?, ?)'
            );
            if ($stmt) {
                $stmt->bind_param('sss', $visitDate, $sessionKey, $now);
                $stmt->execute();
                $isNewSiteVisitor = $stmt->affected_rows > 0;
                $stmt->close();
            }

            if ($isNewSiteVisitor) {
                $sql = 'INSERT INTO site_visit_daily (visit_date, page_views, unique_visitors)
                        VALUES (?, 1, 1)
                        ON DUPLICATE KEY UPDATE page_views = page_views + 1, unique_visitors = unique_visitors + 1';
            } else {
                $sql = 'INSERT INTO site_visit_daily (visit_date, page_views, unique_visitors)
                        VALUES (?, 1, 0)
                        ON DUPLICATE KEY UPDATE page_views = page_views + 1';
            }

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('s', $visitDate);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log('Visitor counter tracking failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('formatVisitorCount')) {
    function formatVisitorCount($count): string
    {
        return number_format((int) $count);
    }
}

if (!function_exists('getVisitorSummary')) {
    function getVisitorSummary($conn): array
    {
        $defaults = [
            'total_page_views' => 0,
            'total_unique_visitors' => 0,
            'today_page_views' => 0,
            'today_unique_visitors' => 0,
        ];

        if (!$conn || !visitorCounterEnsureTables($conn)) {
            return $defaults;
        }

        $today = date('Y-m-d');

        $totals = $conn->query(
            'SELECT COALESCE(SUM(page_views), 0) AS total_page_views,
                    COALESCE(SUM(unique_visitors), 0) AS total_unique_visitors
             FROM site_visit_daily'
        );
        if ($totals && ($row = $totals->fetch_assoc())) {
            $defaults['total_page_views'] = (int) $row['total_page_views'];
            $defaults['total_unique_visitors'] = (int) $row['total_unique_visitors'];
        }

        $stmt = $conn->prepare(
            'SELECT page_views, unique_visitors FROM site_visit_daily WHERE visit_date = ? LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('s', $today);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && ($row = $result->fetch_assoc())) {
                $defaults['today_page_views'] = (int) $row['page_views'];
                $defaults['today_unique_visitors'] = (int) $row['unique_visitors'];
            }
            $stmt->close();
        }

        return $defaults;
    }
}

if (!function_exists('getVisitorStatsByPage')) {
    function getVisitorStatsByPage($conn, int $days = 30, int $limit = 50): array
    {
        if (!$conn || !visitorCounterEnsureTables($conn)) {
            return [];
        }

        $days = max(1, min($days, 365));
        $limit = max(1, min($limit, 200));
        $fromDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

        $sql = "SELECT page_path,
                       SUM(page_views) AS page_views,
                       SUM(unique_visitors) AS unique_visitors
                FROM page_visit_daily
                WHERE visit_date >= ?
                GROUP BY page_path
                ORDER BY page_views DESC, page_path ASC
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('si', $fromDate, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('renderVisitorCountFooter')) {
    function renderVisitorCountFooter($conn): void
    {
        visitorCounterTrackIfReady($conn);
        $summary = getVisitorSummary($conn);
        echo '<span class="visitor-count-footer">';
        echo '<i class="fas fa-users me-1"></i>';
        echo 'Visitors Today: ' . formatVisitorCount($summary['today_unique_visitors']);
        echo ' | Total Views: ' . formatVisitorCount($summary['total_page_views']);
        echo '</span>';
    }
}
