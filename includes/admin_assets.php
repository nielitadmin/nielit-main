<?php
/**
 * Shared admin head assets — theme CSS, Font Awesome, admin-theme, favicon.
 */

if (!function_exists('adminEmitHeadAssets')) {
    /**
     * @param array|null $activeTheme from loadActiveTheme()
     * @param array $opts favicon|toast|bootstrap|extra_css
     */
    function adminEmitHeadAssets(?array $activeTheme = null, array $opts = []): void
    {
        if ($activeTheme === null && function_exists('loadActiveTheme')) {
            global $conn;
            if ($conn instanceof mysqli) {
                $activeTheme = loadActiveTheme($conn);
            }
        }
        if (!is_array($activeTheme)) {
            $activeTheme = function_exists('getDefaultTheme') ? getDefaultTheme() : [
                'primary_color' => '#0c2340',
                'secondary_color' => '#123a66',
                'accent_color' => '#f59e0b',
                'logo_path' => 'assets/images/bhubaneswar_logo.png',
                'favicon_path' => 'assets/images/favicon.ico',
            ];
        }

        if (function_exists('injectThemeCSS')) {
            injectThemeCSS($activeTheme);
        }

        $appUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        $themeCss = __DIR__ . '/../assets/css/admin-theme.css';
        $themeVer = @filemtime($themeCss) ?: time();
        $toast = !empty($opts['toast']);
        $bootstrap = array_key_exists('bootstrap', $opts) ? (bool) $opts['bootstrap'] : true;

        if ($bootstrap) {
            echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">' . "\n";
        }
        echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">' . "\n";
        echo '<link href="' . htmlspecialchars($appUrl) . '/assets/css/admin-theme.css?v=' . (int) $themeVer . '" rel="stylesheet">' . "\n";
        if ($toast) {
            echo '<link href="' . htmlspecialchars($appUrl) . '/assets/css/toast-notifications.css" rel="stylesheet">' . "\n";
        }
        if (!empty($opts['extra_css']) && is_array($opts['extra_css'])) {
            foreach ($opts['extra_css'] as $href) {
                echo '<link href="' . htmlspecialchars($href) . '" rel="stylesheet">' . "\n";
            }
        }
        $favicon = function_exists('getThemeFaviconUrl')
            ? getThemeFaviconUrl($activeTheme)
            : ($appUrl . '/assets/images/favicon.ico');
        echo '<link rel="icon" href="' . htmlspecialchars($favicon) . '" type="image/x-icon">' . "\n";

        // Theme-aware helpers for legacy page CSS
        echo <<<'CSS'
<style id="admin-theme-bridge">
:root {
    --theme-gradient: linear-gradient(135deg, var(--primary-color, #0c2340) 0%, var(--secondary-color, #123a66) 100%);
    --theme-gradient-rev: linear-gradient(135deg, var(--secondary-color, #123a66) 0%, var(--primary-color, #0c2340) 100%);
}
.bg-theme-gradient { background: var(--theme-gradient) !important; color: #fff !important; }
.text-theme-gradient {
    background: var(--theme-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.btn-theme-primary, .modern-btn-primary {
    background: var(--theme-gradient) !important;
    border: none !important;
    color: #fff !important;
}
.page-header.themed-page-header,
.admin-page-hero {
    background: var(--theme-gradient) !important;
    color: #fff !important;
}
</style>
CSS;
    }
}

if (!function_exists('adminBodySidebarClass')) {
    function adminBodySidebarClass(?mysqli $conn = null): string
    {
        if (!$conn) {
            global $conn;
        }
        if (!function_exists('getActiveSidebarTheme') || !function_exists('sidebarThemeBodyClass')) {
            $helper = __DIR__ . '/sidebar_theme_helper.php';
            if (is_file($helper)) {
                require_once $helper;
            }
        }
        if (function_exists('getActiveSidebarTheme') && function_exists('sidebarThemeBodyClass') && $conn instanceof mysqli) {
            return sidebarThemeBodyClass(getActiveSidebarTheme($conn));
        }
        return 'sidebar-style-soft-navy';
    }
}
