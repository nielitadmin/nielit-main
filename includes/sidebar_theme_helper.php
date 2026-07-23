<?php
/**
 * Site-wide admin sidebar theme presets.
 */

if (!function_exists('sidebarThemeStyleDefinitions')) {
    /**
     * @return array<string, array{
     *   label:string,
     *   description:string,
     *   layout:string,
     *   tone:string,
     *   bg:string,
     *   bg_solid:string,
     *   accent?:string
     * }>
     */
    function sidebarThemeStyleDefinitions(): array
    {
        return [
            'soft_navy' => [
                'label' => 'Soft Navy',
                'description' => 'Preferred NIELIT navy gradient with logo and IST clock.',
                'layout' => 'expanded',
                'tone' => 'dark',
                'bg' => 'linear-gradient(180deg, #0c2340 0%, #123a66 55%, #0f3d7a 100%)',
                'bg_solid' => '#0c2340',
                'accent' => '#f59e0b',
            ],
            'dark' => [
                'label' => 'Dark',
                'description' => 'Solid dark slate expanded sidebar.',
                'layout' => 'expanded',
                'tone' => 'dark',
                'bg' => '#1e293b',
                'bg_solid' => '#1e293b',
                'accent' => '#f59e0b',
            ],
            'light' => [
                'label' => 'Light',
                'description' => 'White expanded sidebar with dark text.',
                'layout' => 'expanded',
                'tone' => 'light',
                'bg' => '#ffffff',
                'bg_solid' => '#ffffff',
                'accent' => '#1d4ed8',
            ],
            'icon' => [
                'label' => 'Icon',
                'description' => 'Narrow light icon-only rail to maximize content space.',
                'layout' => 'icon',
                'tone' => 'light',
                'bg' => '#ffffff',
                'bg_solid' => '#ffffff',
                'accent' => '#1d4ed8',
            ],
            'ocean_blue' => [
                'label' => 'Ocean Blue',
                'description' => 'Bright ocean blue gradient — clear and energetic.',
                'layout' => 'expanded',
                'tone' => 'dark',
                'bg' => 'linear-gradient(180deg, #0c4a6e 0%, #0369a1 50%, #0284c7 100%)',
                'bg_solid' => '#0c4a6e',
                'accent' => '#fbbf24',
            ],
            'emerald' => [
                'label' => 'Emerald',
                'description' => 'Deep emerald green gradient for a fresh admin look.',
                'layout' => 'expanded',
                'tone' => 'dark',
                'bg' => 'linear-gradient(180deg, #064e3b 0%, #047857 55%, #059669 100%)',
                'bg_solid' => '#064e3b',
                'accent' => '#fbbf24',
            ],
            'indigo' => [
                'label' => 'Indigo',
                'description' => 'Deep indigo blue — strong institutional feel.',
                'layout' => 'expanded',
                'tone' => 'dark',
                'bg' => 'linear-gradient(180deg, #1e3a8a 0%, #1d4ed8 55%, #2563eb 100%)',
                'bg_solid' => '#1e3a8a',
                'accent' => '#fbbf24',
            ],
            'slate' => [
                'label' => 'Slate',
                'description' => 'Professional charcoal-to-slate gradient.',
                'layout' => 'expanded',
                'tone' => 'dark',
                'bg' => 'linear-gradient(180deg, #0f172a 0%, #1e293b 50%, #334155 100%)',
                'bg_solid' => '#0f172a',
                'accent' => '#38bdf8',
            ],
            'midnight' => [
                'label' => 'Midnight',
                'description' => 'Near-black midnight panel with cool blue accents.',
                'layout' => 'expanded',
                'tone' => 'dark',
                'bg' => 'linear-gradient(180deg, #020617 0%, #0f172a 60%, #1e293b 100%)',
                'bg_solid' => '#020617',
                'accent' => '#38bdf8',
            ],
            'teal' => [
                'label' => 'Teal',
                'description' => 'Teal-to-cyan gradient with a modern tech feel.',
                'layout' => 'expanded',
                'tone' => 'dark',
                'bg' => 'linear-gradient(180deg, #134e4a 0%, #0f766e 50%, #0d9488 100%)',
                'bg_solid' => '#134e4a',
                'accent' => '#fde68a',
            ],
            'gold_navy' => [
                'label' => 'Gold Navy',
                'description' => 'Classic NIELIT navy with a gold edge accent.',
                'layout' => 'expanded',
                'tone' => 'dark',
                'bg' => 'linear-gradient(180deg, #0a1628 0%, #112240 100%)',
                'bg_solid' => '#0a1628',
                'accent' => '#f59e0b',
            ],
            'sky_light' => [
                'label' => 'Sky Light',
                'description' => 'Soft sky-tinted light sidebar with blue highlights.',
                'layout' => 'expanded',
                'tone' => 'light',
                'bg' => 'linear-gradient(180deg, #f0f9ff 0%, #e0f2fe 100%)',
                'bg_solid' => '#f0f9ff',
                'accent' => '#0284c7',
            ],
            'cream' => [
                'label' => 'Cream',
                'description' => 'Warm cream light sidebar for a softer workspace.',
                'layout' => 'expanded',
                'tone' => 'light',
                'bg' => 'linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%)',
                'bg_solid' => '#fffbeb',
                'accent' => '#b45309',
            ],
            'icon_dark' => [
                'label' => 'Icon Dark',
                'description' => 'Narrow dark icon-only rail for focused navigation.',
                'layout' => 'icon',
                'tone' => 'dark',
                'bg' => '#0f172a',
                'bg_solid' => '#0f172a',
                'accent' => '#38bdf8',
            ],
            'icon_navy' => [
                'label' => 'Icon Navy',
                'description' => 'Narrow soft-navy icon rail matching the NIELIT brand.',
                'layout' => 'icon',
                'tone' => 'dark',
                'bg' => 'linear-gradient(180deg, #0c2340 0%, #123a66 100%)',
                'bg_solid' => '#0c2340',
                'accent' => '#f59e0b',
            ],
        ];
    }
}

if (!function_exists('sidebarThemeAllowedKeys')) {
    function sidebarThemeAllowedKeys(): array
    {
        return array_keys(sidebarThemeStyleDefinitions());
    }
}

if (!function_exists('sidebarThemePresets')) {
    /**
     * @return array<string, array{label:string,description:string}>
     */
    function sidebarThemePresets(): array
    {
        $out = [];
        foreach (sidebarThemeStyleDefinitions() as $key => $def) {
            $out[$key] = [
                'label' => $def['label'],
                'description' => $def['description'],
            ];
        }
        return $out;
    }
}

if (!function_exists('sidebarThemeCssClass')) {
    function sidebarThemeCssClass(string $styleKey): string
    {
        return 'sidebar-style-' . str_replace('_', '-', $styleKey);
    }
}

if (!function_exists('sidebarThemeEmitCriticalCss')) {
    /** Inline CSS for all presets (cache-safe). */
    function sidebarThemeEmitCriticalCss(): string
    {
        $css = [];
        foreach (sidebarThemeStyleDefinitions() as $key => $def) {
            $cls = sidebarThemeCssClass($key);
            $bg = $def['bg'];
            $solid = $def['bg_solid'];
            $accent = $def['accent'] ?? '#f59e0b';
            $tone = $def['tone'];
            $layout = $def['layout'];

            $css[] = "body.{$cls} .admin-sidebar, html.{$cls} .admin-sidebar { background: {$bg} !important; background-color: {$solid} !important; }";

            if ($layout === 'icon') {
                $css[] = "body.{$cls} .admin-sidebar, html.{$cls} .admin-sidebar { width: 72px !important; }";
                $css[] = "body.{$cls} .admin-content, html.{$cls} .admin-content { margin-left: 72px !important; }";
                $css[] = "body.{$cls} .admin-sidebar .sidebar-logo h5,
body.{$cls} .admin-sidebar .sidebar-logo small,
body.{$cls} .admin-sidebar .sidebar-clock,
body.{$cls} .admin-sidebar .nav-section-title,
body.{$cls} .admin-sidebar .nav-divider,
html.{$cls} .admin-sidebar .sidebar-logo h5,
html.{$cls} .admin-sidebar .sidebar-logo small,
html.{$cls} .admin-sidebar .sidebar-clock,
html.{$cls} .admin-sidebar .nav-section-title,
html.{$cls} .admin-sidebar .nav-divider { display: none !important; }";
                $css[] = "body.{$cls} .admin-sidebar .sidebar-logo { padding: 16px 8px 8px !important; }";
                $css[] = "body.{$cls} .admin-sidebar .sidebar-logo img { width: 40px !important; height: 40px !important; margin: 0 auto !important; }";
                $css[] = "body.{$cls} .admin-sidebar .nav-item { margin: 4px 8px !important; }";
                $css[] = "body.{$cls} .admin-sidebar .nav-link, html.{$cls} .admin-sidebar .nav-link { font-size: 0 !important; justify-content: center !important; padding: 12px !important; }";
                $css[] = "body.{$cls} .admin-sidebar .nav-link i, html.{$cls} .admin-sidebar .nav-link i { font-size: 18px !important; margin: 0 !important; color: inherit !important; }";
            }

            if ($tone === 'light') {
                $css[] = "body.{$cls} .admin-sidebar, html.{$cls} .admin-sidebar { color: #0f172a !important; border-right: 1px solid #e2e8f0 !important; }";
                $css[] = "body.{$cls} .admin-sidebar .sidebar-logo h5,
body.{$cls} .admin-sidebar .sidebar-logo small,
body.{$cls} .admin-sidebar .nav-section-title,
html.{$cls} .admin-sidebar .sidebar-logo h5,
html.{$cls} .admin-sidebar .sidebar-logo small,
html.{$cls} .admin-sidebar .nav-section-title { color: #0f172a !important; }";
                $css[] = "body.{$cls} .admin-sidebar .nav-link, html.{$cls} .admin-sidebar .nav-link { color: #334155 !important; }";
                $css[] = "body.{$cls} .admin-sidebar .nav-link:hover,
body.{$cls} .admin-sidebar .nav-link.active,
html.{$cls} .admin-sidebar .nav-link:hover,
html.{$cls} .admin-sidebar .nav-link.active { background: rgba(29, 78, 216, 0.1) !important; color: {$accent} !important; }";
                if ($layout !== 'icon') {
                    $css[] = "body.{$cls} .admin-sidebar .sidebar-clock, html.{$cls} .admin-sidebar .sidebar-clock {
                        display: block !important; margin: 0 14px 16px !important; padding: 10px 12px !important;
                        border-radius: 10px !important; background: rgba(15, 23, 42, 0.05) !important;
                        border: 1px solid rgba(15, 23, 42, 0.1) !important; text-align: center !important; }";
                    $css[] = "body.{$cls} .admin-sidebar .sidebar-clock-time,
body.{$cls} .admin-sidebar .sidebar-clock-date,
html.{$cls} .admin-sidebar .sidebar-clock-time,
html.{$cls} .admin-sidebar .sidebar-clock-date { color: #0f172a !important; }";
                    $css[] = "body.{$cls} .admin-sidebar .sidebar-clock-label, html.{$cls} .admin-sidebar .sidebar-clock-label { color: {$accent} !important; }";
                }
            } else {
                $css[] = "body.{$cls} .admin-sidebar, html.{$cls} .admin-sidebar { color: #ffffff !important; }";
                $css[] = "body.{$cls} .admin-sidebar .sidebar-logo h5,
body.{$cls} .admin-sidebar .sidebar-logo small,
body.{$cls} .admin-sidebar .nav-section-title,
html.{$cls} .admin-sidebar .sidebar-logo h5,
html.{$cls} .admin-sidebar .sidebar-logo small,
html.{$cls} .admin-sidebar .nav-section-title { color: #ffffff !important; }";
                $css[] = "body.{$cls} .admin-sidebar .nav-link, html.{$cls} .admin-sidebar .nav-link { color: rgba(255,255,255,0.88) !important; }";
                $css[] = "body.{$cls} .admin-sidebar .nav-link:hover,
body.{$cls} .admin-sidebar .nav-link.active,
html.{$cls} .admin-sidebar .nav-link:hover,
html.{$cls} .admin-sidebar .nav-link.active { background: rgba(255,255,255,0.14) !important; color: #ffffff !important; }";
                if ($layout !== 'icon') {
                    $css[] = "body.{$cls} .admin-sidebar .sidebar-clock, html.{$cls} .admin-sidebar .sidebar-clock {
                        display: block !important; margin: 0 14px 16px !important; padding: 10px 12px !important;
                        border-radius: 10px !important; background: rgba(255,255,255,0.1) !important;
                        border: 1px solid rgba(255,255,255,0.2) !important; text-align: center !important; }";
                    $css[] = "body.{$cls} .admin-sidebar .sidebar-clock-time,
body.{$cls} .admin-sidebar .sidebar-clock-date,
html.{$cls} .admin-sidebar .sidebar-clock-time,
html.{$cls} .admin-sidebar .sidebar-clock-date { color: #ffffff !important; }";
                    $css[] = "body.{$cls} .admin-sidebar .sidebar-clock-label, html.{$cls} .admin-sidebar .sidebar-clock-label { color: {$accent} !important; }";
                }
            }

            if ($key === 'gold_navy') {
                $css[] = "body.{$cls} .admin-sidebar, html.{$cls} .admin-sidebar { box-shadow: inset 3px 0 0 #f59e0b !important; }";
            }
        }

        $css[] = '@media (max-width: 768px) { body[class*="sidebar-style-"] .admin-content, html[class*="sidebar-style-"] .admin-content { margin-left: 0 !important; } }';

        return implode("\n", $css);
    }
}

if (!function_exists('ensureSidebarThemeSettingsTable')) {
    function ensureSidebarThemeSettingsTable(?mysqli $conn = null): bool
    {
        if (!$conn) {
            global $conn;
        }
        if (!$conn instanceof mysqli) {
            return false;
        }

        static $ready = null;
        if ($ready === true) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS sidebar_theme_settings (
            id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            style_key VARCHAR(32) NOT NULL DEFAULT 'soft_navy',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by VARCHAR(120) DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            if (!$conn->query($sql)) {
                error_log('ensureSidebarThemeSettingsTable: ' . $conn->error);
                $ready = false;
                return false;
            }

            $check = $conn->query('SELECT id FROM sidebar_theme_settings WHERE id = 1 LIMIT 1');
            if ($check && $check->num_rows === 0) {
                $conn->query("INSERT INTO sidebar_theme_settings (id, style_key, updated_by) VALUES (1, 'soft_navy', 'system')");
            }

            $ready = true;
            return true;
        } catch (Throwable $e) {
            error_log('ensureSidebarThemeSettingsTable: ' . $e->getMessage());
            $ready = false;
            return false;
        }
    }
}

if (!function_exists('getActiveSidebarTheme')) {
    function getActiveSidebarTheme(?mysqli $conn = null): string
    {
        if (!$conn) {
            global $conn;
        }
        $default = 'soft_navy';
        if (!$conn instanceof mysqli) {
            return $default;
        }
        if (!ensureSidebarThemeSettingsTable($conn)) {
            return $default;
        }

        try {
            $res = $conn->query('SELECT style_key FROM sidebar_theme_settings WHERE id = 1 LIMIT 1');
            if ($res && ($row = $res->fetch_assoc())) {
                $key = trim((string) ($row['style_key'] ?? ''));
                if (in_array($key, sidebarThemeAllowedKeys(), true)) {
                    return $key;
                }
            }
        } catch (Throwable $e) {
            error_log('getActiveSidebarTheme: ' . $e->getMessage());
        }

        return $default;
    }
}

if (!function_exists('setActiveSidebarTheme')) {
    function setActiveSidebarTheme(?mysqli $conn, string $styleKey, ?string $updatedBy = null): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $styleKey = trim($styleKey);
        if (!in_array($styleKey, sidebarThemeAllowedKeys(), true)) {
            return false;
        }
        if (!ensureSidebarThemeSettingsTable($conn)) {
            return false;
        }

        $updatedBy = $updatedBy !== null && $updatedBy !== '' ? substr($updatedBy, 0, 120) : null;

        try {
            $stmt = $conn->prepare(
                'INSERT INTO sidebar_theme_settings (id, style_key, updated_by)
                 VALUES (1, ?, ?)
                 ON DUPLICATE KEY UPDATE style_key = VALUES(style_key), updated_by = VALUES(updated_by)'
            );
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('ss', $styleKey, $updatedBy);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (Throwable $e) {
            error_log('setActiveSidebarTheme: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sidebarThemeBodyClass')) {
    function sidebarThemeBodyClass(?string $styleKey = null): string
    {
        if ($styleKey === null) {
            global $conn;
            $styleKey = getActiveSidebarTheme($conn instanceof mysqli ? $conn : null);
        }
        if (!in_array($styleKey, sidebarThemeAllowedKeys(), true)) {
            $styleKey = 'soft_navy';
        }
        return sidebarThemeCssClass($styleKey);
    }
}
