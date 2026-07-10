<?php
/**
 * Clean URL helpers for extensionless routes (.htaccess rewrites).
 */

if (!function_exists('clean_url_path')) {
    /**
     * Strip .php from an internal application path.
     */
    function clean_url_path($path) {
        $path = str_replace('\\', '/', trim((string) $path));
        $path = ltrim($path, '/');

        if ($path === '' || $path === 'index' || strcasecmp($path, 'index.php') === 0) {
            return '';
        }

        // Keep API and tooling paths as-is (some endpoints may require .php)
        if (preg_match('#^(api|migrations|libraries)/#i', $path)) {
            return $path;
        }

        return preg_replace('/\.php$/i', '', $path);
    }

    /**
     * Build a full application URL without the .php extension.
     */
    function app_url($path = '') {
        if (preg_match('/^https?:\/\//i', (string) $path)) {
            return $path;
        }

        $base = rtrim(defined('APP_URL') ? APP_URL : '', '/');
        $clean = clean_url_path($path);

        if ($clean === '') {
            return $base . '/';
        }

        return $base . '/' . $clean;
    }

    /**
     * Asset URL with filemtime cache-buster for CSS/JS.
     */
    function asset_url($path = '') {
        $url = app_url($path);
        $relative = ltrim(str_replace('\\', '/', (string) $path), '/');
        $root = dirname(__DIR__);
        $fullPath = $root . '/' . $relative;

        if (is_file($fullPath)) {
            return $url . '?v=' . filemtime($fullPath);
        }

        return $url;
    }

    /**
     * Build a root-relative URL without the .php extension.
     */
    function relative_url($path = '') {
        $clean = clean_url_path($path);
        return $clean === '' ? './' : $clean;
    }

    /**
     * Normalize menu/link href values from the database or templates.
     */
    function clean_menu_href($url) {
        $url = trim((string) $url);

        if ($url === '' || $url === '#' || preg_match('/^(https?:|mailto:|tel:|javascript:)/i', $url)) {
            return $url;
        }

        if ($url[0] === '/') {
            $path = ltrim($url, '/');
            $clean = clean_url_path($path);
            return $clean === '' ? '/' : '/' . $clean;
        }

        return relative_url($url);
    }
}
