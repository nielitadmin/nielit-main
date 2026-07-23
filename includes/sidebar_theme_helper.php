<?php
/**
 * Site-wide admin sidebar theme presets.
 * Styles: soft_navy (default), dark, light, icon
 */

if (!function_exists('sidebarThemeAllowedKeys')) {
    function sidebarThemeAllowedKeys(): array
    {
        return ['soft_navy', 'dark', 'light', 'icon'];
    }
}

if (!function_exists('sidebarThemePresets')) {
    /**
     * @return array<string, array{label:string,description:string}>
     */
    function sidebarThemePresets(): array
    {
        return [
            'soft_navy' => [
                'label' => 'Soft Navy',
                'description' => 'Preferred NIELIT navy gradient with logo and IST clock.',
            ],
            'dark' => [
                'label' => 'Dark',
                'description' => 'Solid dark navy expanded sidebar (mockup dark style).',
            ],
            'light' => [
                'label' => 'Light',
                'description' => 'White expanded sidebar with dark text (mockup light style).',
            ],
            'icon' => [
                'label' => 'Icon',
                'description' => 'Narrow icon-only rail to maximize content space.',
            ],
        ];
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
        return 'sidebar-style-' . str_replace('_', '-', $styleKey);
    }
}
