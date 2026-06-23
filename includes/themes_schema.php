<?php
/**
 * Ensure themes table exists and has columns required by Manage Themes.
 */

if (!function_exists('themesTableHasColumn')) {
    function themesTableHasColumn($conn, string $column): bool
    {
        static $cache = [];
        if (!array_key_exists($column, $cache)) {
            $safeColumn = $conn->real_escape_string($column);
            $result = $conn->query("SHOW COLUMNS FROM themes LIKE '{$safeColumn}'");
            $cache[$column] = $result && $result->num_rows > 0;
        }
        return $cache[$column];
    }
}

if (!function_exists('ensureThemesSchema')) {
    function ensureThemesSchema($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }

        $createSql = "CREATE TABLE IF NOT EXISTS themes (
            id INT(11) NOT NULL AUTO_INCREMENT,
            theme_name VARCHAR(100) NOT NULL,
            primary_color VARCHAR(7) NOT NULL,
            secondary_color VARCHAR(7) NOT NULL,
            accent_color VARCHAR(7) NOT NULL,
            logo_path VARCHAR(255) DEFAULT NULL,
            favicon_path VARCHAR(255) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if (!$conn->query($createSql)) {
            error_log('ensureThemesSchema create table failed: ' . $conn->error);
            return false;
        }

        if (themesTableHasColumn($conn, 'name') && !themesTableHasColumn($conn, 'theme_name')) {
            if (!$conn->query("ALTER TABLE themes CHANGE COLUMN name theme_name VARCHAR(100) NOT NULL")) {
                error_log('ensureThemesSchema rename name failed: ' . $conn->error);
                return false;
            }
        }

        $columnDefinitions = [
            'theme_name' => "ADD COLUMN theme_name VARCHAR(100) NOT NULL DEFAULT 'Custom Theme' AFTER id",
            'primary_color' => "ADD COLUMN primary_color VARCHAR(7) NOT NULL DEFAULT '#0a1628' AFTER theme_name",
            'secondary_color' => "ADD COLUMN secondary_color VARCHAR(7) NOT NULL DEFAULT '#1a56db' AFTER primary_color",
            'accent_color' => "ADD COLUMN accent_color VARCHAR(7) NOT NULL DEFAULT '#f59e0b' AFTER secondary_color",
            'logo_path' => "ADD COLUMN logo_path VARCHAR(255) DEFAULT NULL AFTER accent_color",
            'favicon_path' => "ADD COLUMN favicon_path VARCHAR(255) DEFAULT NULL AFTER logo_path",
            'is_active' => "ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0",
            'created_at' => "ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        ];

        foreach ($columnDefinitions as $column => $alterSql) {
            $safeColumn = $conn->real_escape_string($column);
            $result = $conn->query("SHOW COLUMNS FROM themes LIKE '{$safeColumn}'");
            if ($result && $result->num_rows === 0) {
                if (!$conn->query("ALTER TABLE themes {$alterSql}")) {
                    error_log("ensureThemesSchema add {$column} failed: " . $conn->error);
                    return false;
                }
            }
        }

        $ready = true;
        return true;
    }
}
