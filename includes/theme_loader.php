<?php
/**
 * Theme Loader
 * NIELIT Bhubaneswar Student Management System
 * 
 * Dynamically loads and applies the active theme configuration
 * Provides theme caching to minimize database queries
 * 
 * Requirements: 6.1, 6.3, 6.4
 */

// Prevent direct access
if (!defined('DB_CONFIG_LOADED')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/institute_branding.php';

/**
 * Load the active theme from database with caching
 * 
 * @param mysqli $conn Database connection
 * @param bool $force_reload Force reload from database, bypassing cache
 * @return array Theme configuration array
 * 
 * Requirements: 6.1, 6.4
 */
function loadActiveTheme($conn, $force_reload = false) {
    static $theme_cache = null;
    
    // Return cached theme if available and not forcing reload
    if ($theme_cache !== null && !$force_reload) {
        return $theme_cache;
    }
    
    // Query database for active theme
    try {
        $result = $conn->query("SELECT * FROM themes WHERE is_active = 1 LIMIT 1");
        
        if ($result && $result->num_rows > 0) {
            $theme_cache = $result->fetch_assoc();
        } else {
            // No active theme found, use default
            $theme_cache = getDefaultTheme();
        }
        
    } catch (Exception $e) {
        error_log("Theme loading error: " . $e->getMessage());
        $theme_cache = getDefaultTheme();
    }
    
    return $theme_cache;
}

/**
 * Get default theme configuration
 * Used as fallback when no active theme exists in database
 * 
 * @return array Default theme configuration
 * 
 * Requirements: 6.3
 */
function getDefaultTheme() {
    return [
        'id' => null,
        'theme_name' => 'Modern Navy & Gold Theme',
        'primary_color' => '#0a1628',
        'secondary_color' => '#1a56db',
        'accent_color' => '#f59e0b',
        'logo_path' => 'assets/images/bhubaneswar_logo.png',
        'favicon_path' => 'assets/images/favicon.ico',
        'is_active' => 1,
        'created_at' => null,
        'updated_at' => null
    ];
}

/**
 * Inject theme CSS custom properties into page
 * Outputs a <style> tag with CSS variables for theme colors
 * 
 * @param array $theme Theme configuration array
 * @return void
 * 
 * Requirements: 6.1
 */
function injectThemeCSS($theme) {
    // Sanitize color values to prevent XSS
    $primary_color = htmlspecialchars($theme['primary_color'], ENT_QUOTES, 'UTF-8');
    $secondary_color = htmlspecialchars($theme['secondary_color'], ENT_QUOTES, 'UTF-8');
    $accent_color = htmlspecialchars($theme['accent_color'], ENT_QUOTES, 'UTF-8');
    
    echo <<<HTML
<style>
        :root {
            --primary-color: {$primary_color};
            --secondary-color: {$secondary_color};
            --accent-color: {$accent_color};
            --bg-body: #fafaf8;
            --bg-card: #ffffff;
            --sidebar-top: #0c2340;
            --sidebar-mid: #123a66;
            --sidebar-bottom: #1a56db;
            --bg-sidebar: linear-gradient(180deg, #0c2340 0%, #123a66 55%, #0f3d7a 100%);
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-color: rgba(0, 0, 0, 0.08);
            --light-bg: #f8fafc;
            --text-dark: #0f172a;
            --text: #0f172a;
            --muted: #64748b;
            --site-page-bg: #fafaf8;
            --site-surface: #ffffff;
            --site-surface-alt: #f8fafc;
            color-scheme: light;
        }

        html[data-mode="night"] {
            --primary-color: #0c2340;
            --secondary-color: #1a56db;
            --accent-color: #f59e0b;
            --bg-body: #050b16;
            --bg-card: #0f1b2d;
            --sidebar-top: #0c2340;
            --sidebar-mid: #143a68;
            --sidebar-bottom: #1a56db;
            --bg-sidebar: linear-gradient(180deg, #0c2340 0%, #143a68 55%, #0f3d7a 100%);
            --text-primary: #e5eefb;
            --text-secondary: #a7b8d0;
            --text-muted: #7f93ad;
            --border-color: rgba(148, 163, 184, 0.18);
            --light-bg: #13243c;
            --text-dark: #e5eefb;
            --text: #e5eefb;
            --muted: #9fb0c9;
            --site-page-bg: #050b16;
            --site-surface: #0f1b2d;
            --site-surface-alt: #13243c;
            color-scheme: dark;
        }

        html[data-mode="night"],
        html[data-mode="night"] body {
            background: var(--site-page-bg) !important;
            color: var(--text-primary) !important;
        }

        html[data-mode="night"] body,
        html[data-mode="night"] .admin-wrapper,
        html[data-mode="night"] .main-content,
        html[data-mode="night"] .content-body,
        html[data-mode="night"] .page-content {
            background: var(--site-page-bg) !important;
            color: var(--text-primary) !important;
        }

        html[data-mode="night"] .card,
        html[data-mode="night"] .modal-content,
        html[data-mode="night"] .modal-dialog,
        html[data-mode="night"] .dropdown-menu,
        html[data-mode="night"] .table,
        html[data-mode="night"] .table-responsive,
        html[data-mode="night"] .alert,
        html[data-mode="night"] .bg-white,
        html[data-mode="night"] .bg-light,
        html[data-mode="night"] .top-bar,
        html[data-mode="night"] .header,
        html[data-mode="night"] .admin-topbar,
        html[data-mode="night"] .page-header,
        html[data-mode="night"] .login-card,
        html[data-mode="night"] footer,
        html[data-mode="night"] .footer,
        html[data-mode="night"] .student-navbar,
        html[data-mode="night"] .navbar,
        html[data-mode="night"] .section-block,
        html[data-mode="night"] .section-card,
        html[data-mode="night"] .info-box,
        html[data-mode="night"] .notice-card {
            background: var(--site-surface) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.28) !important;
        }

        html[data-mode="night"] .card-header,
        html[data-mode="night"] .modal-header,
        html[data-mode="night"] .navbar,
        html[data-mode="night"] .student-navbar,
        html[data-mode="night"] .page-header,
        html[data-mode="night"] .top-bar,
        html[data-mode="night"] .header {
            background: linear-gradient(135deg, #0c2340 0%, #143a68 100%) !important;
            color: #ffffff !important;
        }

        /* Night mode: keep Soft Navy / Dark family looking correct; leave light/icon presets alone */
        html[data-mode="night"].sidebar-style-soft-navy .admin-sidebar,
        html[data-mode="night"].sidebar-style-dark .admin-sidebar,
        html[data-mode="night"].sidebar-style-slate .admin-sidebar,
        html[data-mode="night"].sidebar-style-midnight .admin-sidebar,
        html[data-mode="night"].sidebar-style-gold-navy .admin-sidebar,
        html[data-mode="night"] body.sidebar-style-soft-navy .admin-sidebar,
        html[data-mode="night"] body.sidebar-style-dark .admin-sidebar {
            color: #ffffff !important;
        }

        html[data-mode="night"] .modal-footer {
            background: linear-gradient(to right, rgba(245, 158, 11, 0.04) 0%, transparent 100%) !important;
            border-top-color: var(--accent-color) !important;
        }

        html[data-mode="night"] .form-control,
        html[data-mode="night"] .form-select,
        html[data-mode="night"] select,
        html[data-mode="night"] textarea,
        html[data-mode="night"] input[type="text"],
        html[data-mode="night"] input[type="email"],
        html[data-mode="night"] input[type="password"],
        html[data-mode="night"] input[type="number"],
        html[data-mode="night"] input[type="date"],
        html[data-mode="night"] input[type="url"],
        html[data-mode="night"] input[type="tel"] {
            background: #13243c !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        html[data-mode="night"] .form-control::placeholder,
        html[data-mode="night"] textarea::placeholder,
        html[data-mode="night"] input::placeholder {
            color: var(--text-muted) !important;
        }

        html[data-mode="night"] .table {
            color: var(--text-primary) !important;
        }

        html[data-mode="night"] .table thead th,
        html[data-mode="night"] .table > :not(caption) > * > * {
            background: transparent !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        html[data-mode="night"] .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: rgba(255, 255, 255, 0.02) !important;
        }

        html[data-mode="night"] .text-muted,
        html[data-mode="night"] small:not(.top-bar-ministry-hi):not(.top-bar-ministry-en),
        html[data-mode="night"] .small,
        html[data-mode="night"] .form-text,
        html[data-mode="night"] .help-text {
            color: var(--text-muted) !important;
        }

        html[data-mode="night"] body.public-site .top-bar small.top-bar-ministry-hi,
        html[data-mode="night"] body.public-site .top-bar .top-bar-ministry-hi {
            color: #e2e8f0 !important;
        }

        html[data-mode="night"] body.public-site .top-bar small.top-bar-ministry-en,
        html[data-mode="night"] body.public-site .top-bar .top-bar-ministry-en {
            color: #ffffff !important;
        }

        html[data-mode="night"] body.public-site .national-emblem,
        html[data-mode="night"] body.homepage-public .national-emblem {
            background: #ffffff !important;
            border-radius: 10px;
            padding: 5px 6px;
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.35);
            object-fit: contain;
        }

        html[data-mode="night"] .btn-light,
        html[data-mode="night"] .btn-outline-light,
        html[data-mode="night"] .btn-outline-secondary {
            background: #13243c !important;
            color: #e5eefb !important;
            border-color: var(--border-color) !important;
        }

        html[data-mode="night"] .btn-secondary {
            background: #22314a !important;
            color: #e5eefb !important;
        }

        html[data-mode="night"] .btn-primary {
            background: linear-gradient(135deg, var(--accent-color) 0%, #f0a312 100%) !important;
            color: #0a1628 !important;
        }

        html[data-mode="night"] .nav-link,
        html[data-mode="night"] .dropdown-item {
            color: #dbe6f7 !important;
        }

        html[data-mode="night"] .dropdown-item:hover,
        html[data-mode="night"] .nav-link:hover {
            color: var(--accent-color) !important;
        }

        /* Night mode: admin surfaces & readable text across all pages */
        html[data-mode="night"] .admin-content,
        html[data-mode="night"] .admin-main,
        html[data-mode="night"] .content-card,
        html[data-mode="night"] .filter-card,
        html[data-mode="night"] .overview-card,
        html[data-mode="night"] .summary-card,
        html[data-mode="night"] .metric-item,
        html[data-mode="night"] .batch-modal-content,
        html[data-mode="night"] .empty-chart-state {
            background: var(--site-surface) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        html[data-mode="night"] .admin-topbar {
            background: var(--site-surface) !important;
            color: var(--text-primary) !important;
            border-bottom: 1px solid var(--border-color);
        }

        html[data-mode="night"] .admin-topbar h1,
        html[data-mode="night"] .admin-topbar h2,
        html[data-mode="night"] .admin-topbar h3,
        html[data-mode="night"] .admin-topbar h4,
        html[data-mode="night"] .admin-topbar h5,
        html[data-mode="night"] .topbar-left h4,
        html[data-mode="night"] .card-title,
        html[data-mode="night"] .content-card h5,
        html[data-mode="night"] .content-card h6,
        html[data-mode="night"] .user-name,
        html[data-mode="night"] .modern-table strong,
        html[data-mode="night"] .student-contact-cell .student-name,
        html[data-mode="night"] .student-id-cell,
        html[data-mode="night"] .info-box p,
        html[data-mode="night"] .overview-title,
        html[data-mode="night"] .metric-label,
        html[data-mode="night"] .metric-value,
        html[data-mode="night"] .summary-value,
        html[data-mode="night"] .chart-center-value {
            color: var(--text-primary) !important;
        }

        html[data-mode="night"] .topbar-left small,
        html[data-mode="night"] .user-role,
        html[data-mode="night"] .text-muted,
        html[data-mode="night"] .info-box h6,
        html[data-mode="night"] .overview-kicker,
        html[data-mode="night"] .metric-note,
        html[data-mode="night"] .summary-label,
        html[data-mode="night"] .chart-center-label,
        html[data-mode="night"] .student-contact-cell .student-meta,
        html[data-mode="night"] .students-pagination-info,
        html[data-mode="night"] .form-label {
            color: var(--text-secondary) !important;
        }

        html[data-mode="night"] .stat-card {
            background: var(--site-surface) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        html[data-mode="night"] .stat-card .stat-value,
        html[data-mode="night"] .stat-value {
            color: #f8fafc !important;
        }

        html[data-mode="night"] .stat-card .stat-label,
        html[data-mode="night"] .stat-label {
            color: var(--text-secondary) !important;
        }

        /* Keep filled/gradient metric cards colorful + white text */
        html[data-mode="night"] .stat-card.stat-card-filled,
        html[data-mode="night"] .stats-grid > .stat-card.stat-card-filled {
            background-image: none;
            border: none !important;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.35) !important;
        }

        html[data-mode="night"] .stats-grid > .stat-card.stat-card-filled,
        html[data-mode="night"] .stats-grid > .stat-card.stat-card-filled .stat-value,
        html[data-mode="night"] .stats-grid > .stat-card.stat-card-filled .stat-label,
        html[data-mode="night"] .stat-card.stat-card-filled .stat-value,
        html[data-mode="night"] .stat-card.stat-card-filled .stat-label {
            color: #ffffff !important;
        }

        /* Batch-style gradient cards: do not flatten their background in night mode */
        html[data-mode="night"] .stats-grid > .stat-card.stat-card-filled {
            background: linear-gradient(135deg, #0c2340 0%, #1a56db 100%) !important;
        }
        html[data-mode="night"] .stats-grid > .stat-card.stat-card-filled:nth-child(2) {
            background: linear-gradient(135deg, #0c2340 0%, #123a66 100%) !important;
        }
        html[data-mode="night"] .stats-grid > .stat-card.stat-card-filled:nth-child(3) {
            background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%) !important;
        }
        html[data-mode="night"] .stats-grid > .stat-card.stat-card-filled:nth-child(4) {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%) !important;
        }
        html[data-mode="night"] .stats-grid > .stat-card.stat-card-filled:nth-child(5) {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%) !important;
        }

        html[data-mode="night"] .modern-table,
        html[data-mode="night"] .modern-table tbody td,
        html[data-mode="night"] .modern-table thead th {
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        html[data-mode="night"] .modern-table thead th {
            background: #13243c !important;
        }

        html[data-mode="night"] .modern-table tbody tr:hover {
            background: rgba(245, 158, 11, 0.08) !important;
        }

        html[data-mode="night"] .card-header {
            background: rgba(19, 36, 60, 0.9) !important;
            color: var(--text-primary) !important;
            border-bottom-color: var(--border-color) !important;
        }

        html[data-mode="night"] .students-pagination,
        html[data-mode="night"] .filter-grid,
        html[data-mode="night"] .batch-info,
        html[data-mode="night"] .photo-preview {
            background: var(--site-surface-alt) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        html[data-mode="night"] .form-control,
        html[data-mode="night"] .form-select {
            background: #13243c !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        html[data-mode="night"] a:not(.btn):not(.nav-link):not(.sidebar-link):not(.dropdown-item):not(.stat-pill a):not(.quick-btn):not(.news-read-more):not(.btn-hero-primary):not(.btn-hero-outline):not(.jobfair-btn-primary):not(.jobfair-btn-secondary):not(.mocktest-btn-primary):not(.mocktest-btn-secondary) {
            color: #93c5fd;
        }

        /* Public homepage/site footer/nav keep their own night palette */
        html[data-mode="night"] body.homepage-public footer,
        html[data-mode="night"] body.homepage-public .footer,
        html[data-mode="night"] body.public-site footer,
        html[data-mode="night"] body.public-site .footer {
            background: #050e1a !important;
            color: rgba(255, 255, 255, 0.62) !important;
            box-shadow: none !important;
        }

        html[data-mode="night"] body.homepage-public .navbar,
        html[data-mode="night"] body.public-site .navbar {
            background: #0a1628 !important;
            color: #ffffff !important;
        }

        html[data-mode="night"] body.homepage-public .top-bar,
        html[data-mode="night"] body.public-site .top-bar {
            background: #0b1524 !important;
            color: #e8eef8 !important;
            box-shadow: none !important;
        }

        .theme-mode-toggle {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 99999;
            border: none;
            border-radius: 9999px;
            padding: 12px 16px;
            font-weight: 700;
            box-shadow: 0 12px 30px rgba(10, 22, 40, 0.25);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, color 0.2s ease;
        }

        .theme-mode-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(10, 22, 40, 0.3);
        }

        .theme-mode-toggle.day {
            background: var(--accent-color);
            color: #0a1628;
        }

        .theme-mode-toggle.night {
            background: #13243c;
            color: #e5eefb;
            border: 1px solid rgba(245, 158, 11, 0.45);
        }
</style>
<script>
(function() {
    try {
        var storedMode = localStorage.getItem('nielitColorMode') || 'day';
        document.documentElement.setAttribute('data-mode', storedMode === 'night' ? 'night' : 'day');
    } catch (error) {
        document.documentElement.setAttribute('data-mode', 'day');
    }

    function updateToggle(button) {
        var currentMode = document.documentElement.getAttribute('data-mode') === 'night' ? 'night' : 'day';
        button.className = 'theme-mode-toggle ' + currentMode;
        if (currentMode === 'night') {
            button.innerHTML = '<i class="fas fa-sun"></i><span>Day Mode</span>';
        } else {
            button.innerHTML = '<i class="fas fa-moon"></i><span>Night Mode</span>';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var button = document.getElementById('themeModeToggle');

        if (!button) {
            button = document.createElement('button');
            button.id = 'themeModeToggle';
            button.type = 'button';
            button.setAttribute('aria-label', 'Toggle night or day mode');
            document.body.appendChild(button);
        }

        updateToggle(button);

        button.addEventListener('click', function() {
            var nextMode = document.documentElement.getAttribute('data-mode') === 'night' ? 'day' : 'night';
            document.documentElement.setAttribute('data-mode', nextMode);

            try {
                localStorage.setItem('nielitColorMode', nextMode);
            } catch (error) {
                // Ignore storage failures
            }

            updateToggle(button);
        });
    });
})();
</script>
HTML;
}

/**
 * Clear theme cache
 * Should be called when a theme is activated or updated
 * Forces the next loadActiveTheme() call to reload from database
 * 
 * @return void
 * 
 * Requirements: 6.5
 */
function clearThemeCache() {
    // Clear session-based cache if it exists
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['theme_cache'])) {
        unset($_SESSION['theme_cache']);
    }
    
    // Note: Static variable cache in loadActiveTheme() will be cleared
    // on the next page load. For immediate effect within the same request,
    // use loadActiveTheme($conn, true) to force reload.
}

/**
 * Project root for theme asset existence checks.
 */
function getThemeProjectRoot() {
    static $root = null;
    if ($root === null) {
        $root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
    }
    return $root;
}

/**
 * Check whether a theme asset exists relative to the project root.
 */
function themeAssetExists($relativePath) {
    $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
    if ($relativePath === '') {
        return false;
    }
    $fullPath = getThemeProjectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return file_exists($fullPath);
}

/**
 * Build an absolute URL for a project asset path.
 */
function getThemeAssetUrl($relativePath) {
    $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
    if ($relativePath === '') {
        return '';
    }
    if (defined('APP_URL') && APP_URL !== '') {
        return rtrim(APP_URL, '/') . '/' . $relativePath;
    }
    return '/' . $relativePath;
}

/**
 * Get theme logo path
 * Returns the logo path with proper fallback
 * 
 * @param array $theme Theme configuration array
 * @return string Logo file path
 */
function getThemeLogo($theme) {
    if (!empty($theme['logo_path']) && themeAssetExists($theme['logo_path'])) {
        return ltrim(str_replace('\\', '/', $theme['logo_path']), '/');
    }
    
    return 'assets/images/bhubaneswar_logo.png';
}

/**
 * Get theme favicon path
 * Returns the favicon path with proper fallback
 * 
 * @param array $theme Theme configuration array
 * @return string Favicon file path
 */
function getThemeFavicon($theme) {
    if (!empty($theme['favicon_path']) && themeAssetExists($theme['favicon_path'])) {
        return ltrim(str_replace('\\', '/', $theme['favicon_path']), '/');
    }
    
    return 'assets/images/favicon.ico';
}

/**
 * Absolute URL for the active theme logo.
 */
function getThemeLogoUrl($theme) {
    return getThemeAssetUrl(getThemeLogo($theme));
}

/**
 * Absolute URL for the active theme favicon.
 */
function getThemeFaviconUrl($theme) {
    return getThemeAssetUrl(getThemeFavicon($theme));
}

/**
 * Validate theme color format
 * Ensures color is a valid hexadecimal color code
 * 
 * @param string $color Color value to validate
 * @return bool True if valid, false otherwise
 */
function validateThemeColor($color) {
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1;
}

/**
 * Apply theme to page
 * Convenience function that loads theme and injects CSS
 * 
 * @param mysqli $conn Database connection
 * @return array Theme configuration array
 */
function applyTheme($conn) {
    $theme = loadActiveTheme($conn);
    injectThemeCSS($theme);
    return $theme;
}

if (function_exists('visitorCounterTrackIfReady')) {
    visitorCounterTrackIfReady();
}
?>
