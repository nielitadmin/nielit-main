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
        'theme_name' => 'Default Theme',
        'primary_color' => '#0d47a1',
        'secondary_color' => '#1565c0',
        'accent_color' => '#ffc107',
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
    
    echo "<style>
        :root {
            --primary-color: {$primary_color};
            --secondary-color: {$secondary_color};
            --accent-color: {$accent_color};
        }
    </style>";
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
