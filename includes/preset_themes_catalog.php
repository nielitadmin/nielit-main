<?php
/**
 * Preset application themes (Manage Themes) paired with Sidebar Themes.
 */

if (!function_exists('presetAppThemesCatalog')) {
    /**
     * @return list<array{
     *   theme_name:string,
     *   primary_color:string,
     *   secondary_color:string,
     *   accent_color:string,
     *   suggested_sidebars:list<string>,
     *   tag?:string
     * }>
     */
    function presetAppThemesCatalog(): array
    {
        return [
            // Soft Navy / Gold Navy family
            ['theme_name' => 'Soft Navy & Gold', 'primary_color' => '#0c2340', 'secondary_color' => '#123a66', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['soft_navy', 'gold_navy', 'icon_navy'], 'tag' => 'NIELIT'],
            ['theme_name' => 'NIELIT Classic Navy', 'primary_color' => '#0a1628', 'secondary_color' => '#1a56db', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['soft_navy', 'gold_navy', 'indigo', 'icon_navy'], 'tag' => 'NIELIT'],
            ['theme_name' => 'Bhubaneswar Blue', 'primary_color' => '#0f2744', 'secondary_color' => '#2563eb', 'accent_color' => '#fbbf24', 'suggested_sidebars' => ['soft_navy', 'indigo', 'ocean_blue'], 'tag' => 'NIELIT'],
            ['theme_name' => 'Gold Edge Navy', 'primary_color' => '#0a1628', 'secondary_color' => '#112240', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['gold_navy', 'soft_navy', 'midnight'], 'tag' => 'NIELIT'],

            // Dark / Slate / Midnight
            ['theme_name' => 'Slate Pro', 'primary_color' => '#0f172a', 'secondary_color' => '#334155', 'accent_color' => '#38bdf8', 'suggested_sidebars' => ['slate', 'dark', 'midnight', 'icon_dark'], 'tag' => 'Dark'],
            ['theme_name' => 'Midnight Cool', 'primary_color' => '#020617', 'secondary_color' => '#1e293b', 'accent_color' => '#38bdf8', 'suggested_sidebars' => ['midnight', 'slate', 'icon_dark'], 'tag' => 'Dark'],
            ['theme_name' => 'Charcoal Amber', 'primary_color' => '#111827', 'secondary_color' => '#374151', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['dark', 'slate', 'midnight'], 'tag' => 'Dark'],
            ['theme_name' => 'Graphite Sky', 'primary_color' => '#1e293b', 'secondary_color' => '#475569', 'accent_color' => '#7dd3fc', 'suggested_sidebars' => ['dark', 'slate', 'icon_dark'], 'tag' => 'Dark'],

            // Ocean / Indigo / Blue
            ['theme_name' => 'Ocean Current', 'primary_color' => '#0c4a6e', 'secondary_color' => '#0284c7', 'accent_color' => '#fbbf24', 'suggested_sidebars' => ['ocean_blue', 'indigo', 'icon'], 'tag' => 'Blue'],
            ['theme_name' => 'Deep Ocean', 'primary_color' => '#082f49', 'secondary_color' => '#0369a1', 'accent_color' => '#38bdf8', 'suggested_sidebars' => ['ocean_blue', 'soft_navy'], 'tag' => 'Blue'],
            ['theme_name' => 'Indigo Pulse', 'primary_color' => '#1e3a8a', 'secondary_color' => '#2563eb', 'accent_color' => '#fbbf24', 'suggested_sidebars' => ['indigo', 'soft_navy', 'ocean_blue'], 'tag' => 'Blue'],
            ['theme_name' => 'Royal Blue Desk', 'primary_color' => '#1e40af', 'secondary_color' => '#3b82f6', 'accent_color' => '#fde68a', 'suggested_sidebars' => ['indigo', 'ocean_blue', 'icon'], 'tag' => 'Blue'],
            ['theme_name' => 'Azure Admin', 'primary_color' => '#075985', 'secondary_color' => '#0ea5e9', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['ocean_blue', 'sky_light', 'icon'], 'tag' => 'Blue'],
            ['theme_name' => 'Coastal Blue', 'primary_color' => '#164e63', 'secondary_color' => '#06b6d4', 'accent_color' => '#fbbf24', 'suggested_sidebars' => ['ocean_blue', 'teal'], 'tag' => 'Blue'],

            // Emerald / Teal / Green
            ['theme_name' => 'Emerald Grove', 'primary_color' => '#064e3b', 'secondary_color' => '#059669', 'accent_color' => '#fbbf24', 'suggested_sidebars' => ['emerald', 'teal'], 'tag' => 'Green'],
            ['theme_name' => 'Forest Mint', 'primary_color' => '#14532d', 'secondary_color' => '#16a34a', 'accent_color' => '#fde047', 'suggested_sidebars' => ['emerald'], 'tag' => 'Green'],
            ['theme_name' => 'Teal Horizon', 'primary_color' => '#134e4a', 'secondary_color' => '#0d9488', 'accent_color' => '#fde68a', 'suggested_sidebars' => ['teal', 'emerald'], 'tag' => 'Green'],
            ['theme_name' => 'Jade Tech', 'primary_color' => '#065f46', 'secondary_color' => '#10b981', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['emerald', 'teal', 'icon_dark'], 'tag' => 'Green'],
            ['theme_name' => 'Seafoam Workbench', 'primary_color' => '#115e59', 'secondary_color' => '#2dd4bf', 'accent_color' => '#fbbf24', 'suggested_sidebars' => ['teal', 'ocean_blue'], 'tag' => 'Green'],

            // Light / Cream / Sky
            ['theme_name' => 'Clean Light', 'primary_color' => '#1e293b', 'secondary_color' => '#3b82f6', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['light', 'icon', 'sky_light'], 'tag' => 'Light'],
            ['theme_name' => 'Sky Workspace', 'primary_color' => '#0c4a6e', 'secondary_color' => '#38bdf8', 'accent_color' => '#0284c7', 'suggested_sidebars' => ['sky_light', 'light', 'icon'], 'tag' => 'Light'],
            ['theme_name' => 'Cream Campus', 'primary_color' => '#78350f', 'secondary_color' => '#d97706', 'accent_color' => '#b45309', 'suggested_sidebars' => ['cream', 'light'], 'tag' => 'Light'],
            ['theme_name' => 'Paper Blue', 'primary_color' => '#1e3a8a', 'secondary_color' => '#60a5fa', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['light', 'sky_light', 'icon'], 'tag' => 'Light'],
            ['theme_name' => 'Ivory Office', 'primary_color' => '#44403c', 'secondary_color' => '#a8a29e', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['cream', 'light'], 'tag' => 'Light'],
            ['theme_name' => 'Frost Admin', 'primary_color' => '#334155', 'secondary_color' => '#94a3b8', 'accent_color' => '#0ea5e9', 'suggested_sidebars' => ['light', 'icon', 'sky_light'], 'tag' => 'Light'],

            // Warm / Accent heavy
            ['theme_name' => 'Amber Authority', 'primary_color' => '#1c1917', 'secondary_color' => '#b45309', 'accent_color' => '#fbbf24', 'suggested_sidebars' => ['gold_navy', 'dark', 'cream'], 'tag' => 'Warm'],
            ['theme_name' => 'Copper Desk', 'primary_color' => '#431407', 'secondary_color' => '#c2410c', 'accent_color' => '#fdba74', 'suggested_sidebars' => ['cream', 'dark'], 'tag' => 'Warm'],
            ['theme_name' => 'Sunrise Gold', 'primary_color' => '#1e3a5f', 'secondary_color' => '#d97706', 'accent_color' => '#fbbf24', 'suggested_sidebars' => ['soft_navy', 'gold_navy', 'cream'], 'tag' => 'Warm'],
            ['theme_name' => 'Sandstone Blue', 'primary_color' => '#1e40af', 'secondary_color' => '#ca8a04', 'accent_color' => '#facc15', 'suggested_sidebars' => ['indigo', 'cream', 'light'], 'tag' => 'Warm'],

            // Extra institutional / mixed
            ['theme_name' => 'Ministry Steel', 'primary_color' => '#1f2937', 'secondary_color' => '#4b5563', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['dark', 'slate', 'icon_dark'], 'tag' => 'Institution'],
            ['theme_name' => 'Campus Cyan', 'primary_color' => '#155e75', 'secondary_color' => '#22d3ee', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['teal', 'ocean_blue', 'sky_light'], 'tag' => 'Institution'],
            ['theme_name' => 'Training Centre Blue', 'primary_color' => '#1d4ed8', 'secondary_color' => '#60a5fa', 'accent_color' => '#fbbf24', 'suggested_sidebars' => ['indigo', 'light', 'icon'], 'tag' => 'Institution'],
            ['theme_name' => 'Extension Green', 'primary_color' => '#166534', 'secondary_color' => '#4ade80', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['emerald', 'teal'], 'tag' => 'Institution'],
            ['theme_name' => 'Scholar Navy', 'primary_color' => '#172554', 'secondary_color' => '#3b82f6', 'accent_color' => '#fde68a', 'suggested_sidebars' => ['soft_navy', 'indigo', 'icon_navy'], 'tag' => 'Institution'],
            ['theme_name' => 'Modern Ice', 'primary_color' => '#0f172a', 'secondary_color' => '#64748b', 'accent_color' => '#38bdf8', 'suggested_sidebars' => ['slate', 'light', 'icon'], 'tag' => 'Institution'],
            ['theme_name' => 'Odisha Coast', 'primary_color' => '#0e7490', 'secondary_color' => '#67e8f9', 'accent_color' => '#f59e0b', 'suggested_sidebars' => ['ocean_blue', 'teal', 'sky_light'], 'tag' => 'Regional'],
            ['theme_name' => 'Temple Amber', 'primary_color' => '#7c2d12', 'secondary_color' => '#ea580c', 'accent_color' => '#fcd34d', 'suggested_sidebars' => ['cream', 'gold_navy'], 'tag' => 'Regional'],
        ];
    }
}

if (!function_exists('presetThemesSuggestedForSidebar')) {
    /**
     * @return list<array{theme_name:string,primary_color:string,secondary_color:string,accent_color:string,suggested_sidebars:list<string>,tag?:string}>
     */
    function presetThemesSuggestedForSidebar(string $sidebarKey): array
    {
        $matches = [];
        foreach (presetAppThemesCatalog() as $theme) {
            if (in_array($sidebarKey, $theme['suggested_sidebars'], true)) {
                $matches[] = $theme;
            }
        }
        return $matches;
    }
}

if (!function_exists('presetThemeSuggestsSidebar')) {
    function presetThemeSuggestsSidebar(string $themeName, string $sidebarKey): bool
    {
        foreach (presetAppThemesCatalog() as $theme) {
            if ($theme['theme_name'] === $themeName) {
                return in_array($sidebarKey, $theme['suggested_sidebars'], true);
            }
        }
        return false;
    }
}

if (!function_exists('presetThemeMetaByName')) {
    function presetThemeMetaByName(string $themeName): ?array
    {
        foreach (presetAppThemesCatalog() as $theme) {
            if ($theme['theme_name'] === $themeName) {
                return $theme;
            }
        }
        return null;
    }
}

if (!function_exists('seedPresetAppThemes')) {
    /**
     * Insert missing preset themes (idempotent by theme_name).
     * @return array{inserted:int,skipped:int,total:int}
     */
    function seedPresetAppThemes(mysqli $conn, string $logoPath = 'assets/images/bhubaneswar_logo.png', string $faviconPath = 'assets/images/favicon.ico'): array
    {
        require_once __DIR__ . '/themes_schema.php';
        if (!ensureThemesSchema($conn)) {
            throw new RuntimeException('Themes schema not ready: ' . ($conn->error ?: 'unknown'));
        }

        $inserted = 0;
        $skipped = 0;
        $catalog = presetAppThemesCatalog();

        $check = $conn->prepare('SELECT id FROM themes WHERE theme_name = ? LIMIT 1');
        $insert = $conn->prepare(
            'INSERT INTO themes (theme_name, primary_color, secondary_color, accent_color, logo_path, favicon_path, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 0)'
        );
        if (!$check || !$insert) {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }

        foreach ($catalog as $theme) {
            $name = $theme['theme_name'];
            $check->bind_param('s', $name);
            $check->execute();
            $res = $check->get_result();
            if ($res && $res->num_rows > 0) {
                $skipped++;
                continue;
            }

            $p = $theme['primary_color'];
            $s = $theme['secondary_color'];
            $a = $theme['accent_color'];
            $insert->bind_param('ssssss', $name, $p, $s, $a, $logoPath, $faviconPath);
            if ($insert->execute()) {
                $inserted++;
            }
        }

        $check->close();
        $insert->close();

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'total' => count($catalog),
        ];
    }
}
