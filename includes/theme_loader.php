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
            --primary-color: #050e1a;
            --secondary-color: #0f1b2d;
            --accent-color: #f59e0b;
            --bg-body: #050b16;
            --bg-card: #0f1b2d;
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
        html[data-mode="night"] .admin-sidebar,
        html[data-mode="night"] .page-header,
        html[data-mode="night"] .top-bar,
        html[data-mode="night"] .header {
            background: linear-gradient(135deg, #050e1a 0%, #0f1b2d 100%) !important;
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
        html[data-mode="night"] small,
        html[data-mode="night"] .small,
        html[data-mode="night"] .form-text,
        html[data-mode="night"] .help-text {
            color: var(--text-muted) !important;
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
 * Get theme logo path
 * Returns the logo path with proper fallback
 * 
 * @param array $theme Theme configuration array
 * @return string Logo file path
 */
function getThemeLogo($theme) {
    if (!empty($theme['logo_path']) && file_exists($theme['logo_path'])) {
        return htmlspecialchars($theme['logo_path'], ENT_QUOTES, 'UTF-8');
    }
    
    // Fallback to default logo
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
    if (!empty($theme['favicon_path']) && file_exists($theme['favicon_path'])) {
        return htmlspecialchars($theme['favicon_path'], ENT_QUOTES, 'UTF-8');
    }
    
    // Fallback to default favicon
    return 'assets/images/favicon.ico';
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
?>
