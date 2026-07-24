<?php
/**
 * Public site theme presets — controls index + public/*.php branding colors.
 * Active key is stored in public_theme_settings (singleton). Catalog lives in PHP.
 */

if (!function_exists('publicThemeStyleDefinitions')) {
    /**
     * @return array<string, array<string, string>>
     */
    function publicThemeStyleDefinitions(): array
    {
        return [
            'nielit_navy_gold' => [
                'label' => 'NIELIT Navy & Gold',
                'description' => 'Official Soft Navy with gold accent — default public look.',
                'tag' => 'Official',
                'primary' => '#0a1628',
                'secondary' => '#1a56db',
                'accent' => '#f59e0b',
                'navy_mid' => '#112240',
                'cream' => '#fafaf8',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
            'deep_ocean' => [
                'label' => 'Deep Ocean',
                'description' => 'Dark ocean navy with sky blue and amber accents.',
                'tag' => 'Cool',
                'primary' => '#0c2340',
                'secondary' => '#0284c7',
                'accent' => '#fbbf24',
                'navy_mid' => '#123a66',
                'cream' => '#f0f9ff',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
            'emerald_gold' => [
                'label' => 'Emerald & Gold',
                'description' => 'Fresh emerald green with warm gold highlights.',
                'tag' => 'Fresh',
                'primary' => '#064e3b',
                'secondary' => '#059669',
                'accent' => '#f59e0b',
                'navy_mid' => '#047857',
                'cream' => '#f0fdf4',
                'text' => '#052e16',
                'muted' => '#4b5563',
            ],
            'indigo_amber' => [
                'label' => 'Indigo Amber',
                'description' => 'Strong indigo institutional blue with amber CTA.',
                'tag' => 'Institutional',
                'primary' => '#1e3a8a',
                'secondary' => '#2563eb',
                'accent' => '#f59e0b',
                'navy_mid' => '#1d4ed8',
                'cream' => '#f8fafc',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
            'slate_cyan' => [
                'label' => 'Slate & Cyan',
                'description' => 'Modern slate with cyan accents for a tech look.',
                'tag' => 'Modern',
                'primary' => '#0f172a',
                'secondary' => '#0891b2',
                'accent' => '#22d3ee',
                'navy_mid' => '#1e293b',
                'cream' => '#f8fafc',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
            'charcoal_coral' => [
                'label' => 'Charcoal Coral',
                'description' => 'Charcoal base with coral-orange energy.',
                'tag' => 'Bold',
                'primary' => '#1c1917',
                'secondary' => '#ea580c',
                'accent' => '#fb923c',
                'navy_mid' => '#292524',
                'cream' => '#fafaf9',
                'text' => '#1c1917',
                'muted' => '#78716c',
            ],
            'forest_lime' => [
                'label' => 'Forest Lime',
                'description' => 'Deep forest green with lime accents.',
                'tag' => 'Fresh',
                'primary' => '#14532d',
                'secondary' => '#16a34a',
                'accent' => '#a3e635',
                'navy_mid' => '#166534',
                'cream' => '#f7fee7',
                'text' => '#14532d',
                'muted' => '#4b5563',
            ],
            'royal_sapphire' => [
                'label' => 'Royal Sapphire',
                'description' => 'Rich sapphire blue with soft gold.',
                'tag' => 'Premium',
                'primary' => '#1e3a5f',
                'secondary' => '#3b82f6',
                'accent' => '#eab308',
                'navy_mid' => '#1e40af',
                'cream' => '#eff6ff',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
            'midnight_teal' => [
                'label' => 'Midnight Teal',
                'description' => 'Midnight navy blended with teal secondary.',
                'tag' => 'Cool',
                'primary' => '#042f2e',
                'secondary' => '#0d9488',
                'accent' => '#fbbf24',
                'navy_mid' => '#134e4a',
                'cream' => '#f0fdfa',
                'text' => '#042f2e',
                'muted' => '#5b6b73',
            ],
            'steel_blue' => [
                'label' => 'Steel Blue',
                'description' => 'Cool steel blue for a clean government portal.',
                'tag' => 'Official',
                'primary' => '#1e293b',
                'secondary' => '#475569',
                'accent' => '#38bdf8',
                'navy_mid' => '#334155',
                'cream' => '#f1f5f9',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
            'burgundy_gold' => [
                'label' => 'Burgundy Gold',
                'description' => 'Deep burgundy with classic gold accents.',
                'tag' => 'Classic',
                'primary' => '#4c0519',
                'secondary' => '#9f1239',
                'accent' => '#f59e0b',
                'navy_mid' => '#881337',
                'cream' => '#fff1f2',
                'text' => '#4c0519',
                'muted' => '#6b7280',
            ],
            'azure_sunrise' => [
                'label' => 'Azure Sunrise',
                'description' => 'Bright azure blue with sunrise orange CTAs.',
                'tag' => 'Bright',
                'primary' => '#0c4a6e',
                'secondary' => '#0284c7',
                'accent' => '#f97316',
                'navy_mid' => '#075985',
                'cream' => '#f0f9ff',
                'text' => '#0c4a6e',
                'muted' => '#64748b',
            ],
            'graphite_mint' => [
                'label' => 'Graphite Mint',
                'description' => 'Graphite base with mint green highlights.',
                'tag' => 'Modern',
                'primary' => '#111827',
                'secondary' => '#10b981',
                'accent' => '#34d399',
                'navy_mid' => '#1f2937',
                'cream' => '#ecfdf5',
                'text' => '#111827',
                'muted' => '#6b7280',
            ],
            'cobalt_rose' => [
                'label' => 'Cobalt Rose',
                'description' => 'Cobalt blue with soft rose accent.',
                'tag' => 'Distinct',
                'primary' => '#1e3a8a',
                'secondary' => '#2563eb',
                'accent' => '#fb7185',
                'navy_mid' => '#1d4ed8',
                'cream' => '#fff1f2',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
            'olive_sand' => [
                'label' => 'Olive Sand',
                'description' => 'Warm olive with sandy cream surfaces.',
                'tag' => 'Warm',
                'primary' => '#3f3f1a',
                'secondary' => '#65a30d',
                'accent' => '#ca8a04',
                'navy_mid' => '#4d7c0f',
                'cream' => '#fefce8',
                'text' => '#1a1a0a',
                'muted' => '#6b7280',
            ],
            'prussian_sky' => [
                'label' => 'Prussian Sky',
                'description' => 'Prussian blue with light sky secondary.',
                'tag' => 'Official',
                'primary' => '#003153',
                'secondary' => '#0ea5e9',
                'accent' => '#f59e0b',
                'navy_mid' => '#0c4a6e',
                'cream' => '#f0f9ff',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
            'ink_amber' => [
                'label' => 'Ink Amber',
                'description' => 'Near-black ink with strong amber CTAs.',
                'tag' => 'Bold',
                'primary' => '#09090b',
                'secondary' => '#27272a',
                'accent' => '#f59e0b',
                'navy_mid' => '#18181b',
                'cream' => '#fafafa',
                'text' => '#09090b',
                'muted' => '#71717a',
            ],
            'marine_gold' => [
                'label' => 'Marine Gold',
                'description' => 'Marine blue with polished gold accents.',
                'tag' => 'Premium',
                'primary' => '#0b1d36',
                'secondary' => '#1b4f72',
                'accent' => '#d4a017',
                'navy_mid' => '#154360',
                'cream' => '#f7f9fc',
                'text' => '#0b1d36',
                'muted' => '#5d6d7e',
            ],
            'arctic_blue' => [
                'label' => 'Arctic Blue',
                'description' => 'Cool arctic blues with crisp white cream.',
                'tag' => 'Cool',
                'primary' => '#0e7490',
                'secondary' => '#06b6d4',
                'accent' => '#fbbf24',
                'navy_mid' => '#155e75',
                'cream' => '#ecfeff',
                'text' => '#083344',
                'muted' => '#64748b',
            ],
            'meadow_green' => [
                'label' => 'Meadow Green',
                'description' => 'Soft meadow greens for a calm portal feel.',
                'tag' => 'Fresh',
                'primary' => '#166534',
                'secondary' => '#22c55e',
                'accent' => '#eab308',
                'navy_mid' => '#15803d',
                'cream' => '#f0fdf4',
                'text' => '#14532d',
                'muted' => '#4b5563',
            ],
            'copper_navy' => [
                'label' => 'Copper Navy',
                'description' => 'Navy foundation with copper-orange accent.',
                'tag' => 'Warm',
                'primary' => '#0f172a',
                'secondary' => '#1e40af',
                'accent' => '#c2410c',
                'navy_mid' => '#1e3a8a',
                'cream' => '#fff7ed',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
            'skyline' => [
                'label' => 'Skyline',
                'description' => 'City skyline blues — bright and open.',
                'tag' => 'Bright',
                'primary' => '#1d4ed8',
                'secondary' => '#3b82f6',
                'accent' => '#f59e0b',
                'navy_mid' => '#2563eb',
                'cream' => '#eff6ff',
                'text' => '#1e3a8a',
                'muted' => '#64748b',
            ],
            'monsoon' => [
                'label' => 'Monsoon',
                'description' => 'Rainy-season deep teal and cloud cream.',
                'tag' => 'Cool',
                'primary' => '#115e59',
                'secondary' => '#14b8a6',
                'accent' => '#fcd34d',
                'navy_mid' => '#0f766e',
                'cream' => '#f0fdfa',
                'text' => '#134e4a',
                'muted' => '#5b6b73',
            ],
            'heritage' => [
                'label' => 'Heritage',
                'description' => 'Heritage brown-navy with festival gold.',
                'tag' => 'Classic',
                'primary' => '#292524',
                'secondary' => '#78716c',
                'accent' => '#d97706',
                'navy_mid' => '#44403c',
                'cream' => '#fafaf9',
                'text' => '#1c1917',
                'muted' => '#78716c',
            ],
            'digital_india' => [
                'label' => 'Digital India',
                'description' => 'Inspired by Digital India blues and saffron gold.',
                'tag' => 'Official',
                'primary' => '#0b3d91',
                'secondary' => '#1a73e8',
                'accent' => '#ff9933',
                'navy_mid' => '#0a4da3',
                'cream' => '#f7faff',
                'text' => '#0b3d91',
                'muted' => '#5f6368',
            ],
            'lagoon' => [
                'label' => 'Lagoon',
                'description' => 'Tropical lagoon teal with warm sand cream.',
                'tag' => 'Fresh',
                'primary' => '#134e4a',
                'secondary' => '#2dd4bf',
                'accent' => '#f59e0b',
                'navy_mid' => '#115e59',
                'cream' => '#fefce8',
                'text' => '#134e4a',
                'muted' => '#64748b',
            ],
            'obsidian_blue' => [
                'label' => 'Obsidian Blue',
                'description' => 'Near-black obsidian with electric blue accents.',
                'tag' => 'Bold',
                'primary' => '#020617',
                'secondary' => '#2563eb',
                'accent' => '#38bdf8',
                'navy_mid' => '#0f172a',
                'cream' => '#f8fafc',
                'text' => '#020617',
                'muted' => '#64748b',
            ],
            'sandstone' => [
                'label' => 'Sandstone',
                'description' => 'Warm sandstone browns with sky blue links.',
                'tag' => 'Warm',
                'primary' => '#78350f',
                'secondary' => '#0284c7',
                'accent' => '#f59e0b',
                'navy_mid' => '#92400e',
                'cream' => '#fffbeb',
                'text' => '#451a03',
                'muted' => '#78716c',
            ],
            'pacific' => [
                'label' => 'Pacific',
                'description' => 'Pacific blues with soft seafoam cream.',
                'tag' => 'Cool',
                'primary' => '#164e63',
                'secondary' => '#22d3ee',
                'accent' => '#fbbf24',
                'navy_mid' => '#155e75',
                'cream' => '#ecfeff',
                'text' => '#083344',
                'muted' => '#64748b',
            ],
            'civic_blue' => [
                'label' => 'Civic Blue',
                'description' => 'Clean civic blue for portals and notices.',
                'tag' => 'Official',
                'primary' => '#1e40af',
                'secondary' => '#3b82f6',
                'accent' => '#f59e0b',
                'navy_mid' => '#1d4ed8',
                'cream' => '#f8fafc',
                'text' => '#1e3a8a',
                'muted' => '#64748b',
            ],
            'jade_slate' => [
                'label' => 'Jade Slate',
                'description' => 'Jade green secondary on slate navy.',
                'tag' => 'Modern',
                'primary' => '#0f172a',
                'secondary' => '#059669',
                'accent' => '#fbbf24',
                'navy_mid' => '#1e293b',
                'cream' => '#f0fdf4',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
            'crimson_ink' => [
                'label' => 'Crimson Ink',
                'description' => 'Ink navy with crimson accent for impact.',
                'tag' => 'Bold',
                'primary' => '#0f172a',
                'secondary' => '#b91c1c',
                'accent' => '#f59e0b',
                'navy_mid' => '#1e293b',
                'cream' => '#fef2f2',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
            'glacier' => [
                'label' => 'Glacier',
                'description' => 'Cool glacier greys and ice-blue accents.',
                'tag' => 'Cool',
                'primary' => '#334155',
                'secondary' => '#64748b',
                'accent' => '#38bdf8',
                'navy_mid' => '#475569',
                'cream' => '#f1f5f9',
                'text' => '#0f172a',
                'muted' => '#64748b',
            ],
            'harvest' => [
                'label' => 'Harvest',
                'description' => 'Harvest green and gold for training campaigns.',
                'tag' => 'Warm',
                'primary' => '#365314',
                'secondary' => '#84cc16',
                'accent' => '#eab308',
                'navy_mid' => '#3f6212',
                'cream' => '#f7fee7',
                'text' => '#1a2e05',
                'muted' => '#4b5563',
            ],
            'coastal' => [
                'label' => 'Coastal',
                'description' => 'Coastal Odisha blues with warm gold.',
                'tag' => 'Regional',
                'primary' => '#0c4a6e',
                'secondary' => '#0369a1',
                'accent' => '#f59e0b',
                'navy_mid' => '#075985',
                'cream' => '#f0f9ff',
                'text' => '#0c4a6e',
                'muted' => '#64748b',
            ],
            'platinum' => [
                'label' => 'Platinum',
                'description' => 'Platinum greys with blue links and gold CTA.',
                'tag' => 'Premium',
                'primary' => '#27272a',
                'secondary' => '#3b82f6',
                'accent' => '#f59e0b',
                'navy_mid' => '#3f3f46',
                'cream' => '#fafafa',
                'text' => '#18181b',
                'muted' => '#71717a',
            ],
            'veda_blue' => [
                'label' => 'Veda Blue',
                'description' => 'Deep veda blue with sacred saffron accent.',
                'tag' => 'Regional',
                'primary' => '#0b1e3a',
                'secondary' => '#1d4ed8',
                'accent' => '#ea580c',
                'navy_mid' => '#123a66',
                'cream' => '#fff7ed',
                'text' => '#0b1e3a',
                'muted' => '#64748b',
            ],
        ];
    }
}

if (!function_exists('publicThemeAllowedKeys')) {
    /** @return string[] */
    function publicThemeAllowedKeys(): array
    {
        return array_keys(publicThemeStyleDefinitions());
    }
}

if (!function_exists('publicThemePresets')) {
    /**
     * @return array<string, array{label:string,description:string,tag:string}>
     */
    function publicThemePresets(): array
    {
        $out = [];
        foreach (publicThemeStyleDefinitions() as $key => $def) {
            $out[$key] = [
                'label' => (string) ($def['label'] ?? $key),
                'description' => (string) ($def['description'] ?? ''),
                'tag' => (string) ($def['tag'] ?? 'Theme'),
            ];
        }
        return $out;
    }
}

if (!function_exists('publicThemeDefaultKey')) {
    function publicThemeDefaultKey(): string
    {
        return 'nielit_navy_gold';
    }
}

if (!function_exists('ensurePublicThemeSettingsTable')) {
    function ensurePublicThemeSettingsTable(?mysqli $conn = null): bool
    {
        if (!$conn) {
            global $conn;
        }
        if (!$conn instanceof mysqli) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS public_theme_settings (
            id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            style_key VARCHAR(64) NOT NULL DEFAULT 'nielit_navy_gold',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by VARCHAR(120) DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            return false;
        }

        $check = $conn->query('SELECT id FROM public_theme_settings WHERE id = 1 LIMIT 1');
        if ($check && $check->num_rows === 0) {
            $default = publicThemeDefaultKey();
            $stmt = $conn->prepare('INSERT INTO public_theme_settings (id, style_key, updated_by) VALUES (1, ?, ?)');
            if ($stmt) {
                $by = 'system';
                $stmt->bind_param('ss', $default, $by);
                $stmt->execute();
                $stmt->close();
            }
        }

        return true;
    }
}

if (!function_exists('getActivePublicThemeKey')) {
    function getActivePublicThemeKey(?mysqli $conn = null): string
    {
        if (!$conn) {
            global $conn;
        }
        $default = publicThemeDefaultKey();
        if (!$conn instanceof mysqli) {
            return $default;
        }

        ensurePublicThemeSettingsTable($conn);
        $res = $conn->query('SELECT style_key FROM public_theme_settings WHERE id = 1 LIMIT 1');
        if ($res && ($row = $res->fetch_assoc())) {
            $key = trim((string) ($row['style_key'] ?? ''));
            if ($key !== '' && isset(publicThemeStyleDefinitions()[$key])) {
                return $key;
            }
        }
        return $default;
    }
}

if (!function_exists('setActivePublicTheme')) {
    function setActivePublicTheme(?mysqli $conn, string $styleKey, string $updatedBy = 'admin'): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        if (!isset(publicThemeStyleDefinitions()[$styleKey])) {
            return false;
        }
        if (!ensurePublicThemeSettingsTable($conn)) {
            return false;
        }

        $stmt = $conn->prepare('INSERT INTO public_theme_settings (id, style_key, updated_by) VALUES (1, ?, ?)
            ON DUPLICATE KEY UPDATE style_key = VALUES(style_key), updated_by = VALUES(updated_by)');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ss', $styleKey, $updatedBy);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    }
}

if (!function_exists('getActivePublicThemeDefinition')) {
    /**
     * @return array<string, string>
     */
    function getActivePublicThemeDefinition(?mysqli $conn = null): array
    {
        $defs = publicThemeStyleDefinitions();
        $key = getActivePublicThemeKey($conn);
        $def = $defs[$key] ?? $defs[publicThemeDefaultKey()];
        $def['key'] = $key;
        return $def;
    }
}

if (!function_exists('injectPublicThemeCSS')) {
    /**
     * Inject CSS variables that drive public-theme.css and index.php aliases.
     *
     * @param array<string,string>|null $theme
     */
    function injectPublicThemeCSS(?array $theme = null, ?mysqli $conn = null): void
    {
        if ($theme === null) {
            $theme = getActivePublicThemeDefinition($conn);
        }

        $primary = htmlspecialchars((string) ($theme['primary'] ?? '#0a1628'), ENT_QUOTES, 'UTF-8');
        $secondary = htmlspecialchars((string) ($theme['secondary'] ?? '#1a56db'), ENT_QUOTES, 'UTF-8');
        $accent = htmlspecialchars((string) ($theme['accent'] ?? '#f59e0b'), ENT_QUOTES, 'UTF-8');
        $navyMid = htmlspecialchars((string) ($theme['navy_mid'] ?? '#112240'), ENT_QUOTES, 'UTF-8');
        $cream = htmlspecialchars((string) ($theme['cream'] ?? '#fafaf8'), ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars((string) ($theme['text'] ?? '#0f172a'), ENT_QUOTES, 'UTF-8');
        $muted = htmlspecialchars((string) ($theme['muted'] ?? '#64748b'), ENT_QUOTES, 'UTF-8');
        $key = htmlspecialchars((string) ($theme['key'] ?? ''), ENT_QUOTES, 'UTF-8');

        echo "<style id=\"public-theme-vars\">\n";
        echo ":root {\n";
        echo "  --primary-color: {$primary};\n";
        echo "  --secondary-color: {$secondary};\n";
        echo "  --accent-color: {$accent};\n";
        echo "  --navy: {$primary};\n";
        echo "  --navy-mid: {$navyMid};\n";
        echo "  --navy-mid-color: {$navyMid};\n";
        echo "  --blue: {$secondary};\n";
        echo "  --blue-light: {$secondary};\n";
        echo "  --gold: {$accent};\n";
        echo "  --gold-light: {$accent};\n";
        echo "  --cream: {$cream};\n";
        echo "  --text: {$text};\n";
        echo "  --muted: {$muted};\n";
        echo "  --public-theme-key: '{$key}';\n";
        echo "}\n";
        echo "html[data-mode=\"day\"] body.public-site,\n";
        echo "html[data-mode=\"day\"] body.homepage-public {\n";
        echo "  --navy: {$primary};\n";
        echo "  --navy-mid: {$navyMid};\n";
        echo "  --blue: {$secondary};\n";
        echo "  --gold: {$accent};\n";
        echo "  --cream: {$cream};\n";
        echo "  --text: {$text};\n";
        echo "  --muted: {$muted};\n";
        echo "}\n";
        echo "</style>\n";
    }
}

if (!function_exists('emitPublicThemeHead')) {
    /**
     * Convenience: ensure settings + inject active public theme CSS.
     */
    function emitPublicThemeHead(?mysqli $conn = null): void
    {
        if (!$conn) {
            global $conn;
        }
        if ($conn instanceof mysqli) {
            ensurePublicThemeSettingsTable($conn);
        }
        injectPublicThemeCSS(getActivePublicThemeDefinition($conn instanceof mysqli ? $conn : null), $conn instanceof mysqli ? $conn : null);
    }
}
