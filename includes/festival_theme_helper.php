<?php
/**
 * Festival / cultural event theme packs.
 * Each festival has its own pack with detail-level tokens (colors, ribbon,
 * motif, pattern, card/button radii, etc.). Active pack is chosen by calendar
 * date and applied on public pages, student portal, and biometric kiosks.
 */

if (!function_exists('festivalThemePackDefinitions')) {
    /**
     * @return array<string, array<string, mixed>>
     */
    function festivalThemePackDefinitions(): array
    {
        return [
            'new_year' => [
                'label' => 'New Year',
                'tag' => 'Celebration',
                'description' => 'Fresh start — cool blues with sparkling gold accents.',
                'fixed' => ['month' => 1, 'day' => 1],
                'span_before' => 0,
                'span_after' => 1,
                'primary' => '#0f172a',
                'secondary' => '#2563eb',
                'accent' => '#fbbf24',
                'navy_mid' => '#1e3a8a',
                'cream' => '#eff6ff',
                'text' => '#0f172a',
                'muted' => '#64748b',
                'greeting' => 'Happy New Year',
                'motif' => 'sparkles',
                'pattern' => 'sparkles',
                'effect' => 'sparkles',
                'ribbon' => true,
                'card_radius' => '16px',
                'button_radius' => '999px',
                'shadow_tint' => 'rgba(37, 99, 235, 0.18)',
                'badge_bg' => '#dbeafe',
                'topbar_stripe' => 'gold_blue',
            ],
            'republic_day' => [
                'label' => 'Republic Day',
                'tag' => 'National',
                'description' => 'Tricolor-inspired navy, saffron and green accents.',
                'fixed' => ['month' => 1, 'day' => 26],
                'span_before' => 1,
                'span_after' => 1,
                'primary' => '#0a2744',
                'secondary' => '#138808',
                'accent' => '#ff9933',
                'navy_mid' => '#0c3b5e',
                'cream' => '#fff8f0',
                'text' => '#0a2744',
                'muted' => '#5b6b73',
                'greeting' => 'Happy Republic Day',
                'motif' => 'tricolor',
                'pattern' => 'tricolor_band',
                'effect' => 'tricolor',
                'ribbon' => true,
                'card_radius' => '14px',
                'button_radius' => '10px',
                'shadow_tint' => 'rgba(255, 153, 51, 0.2)',
                'badge_bg' => '#ffedd5',
                'topbar_stripe' => 'tricolor',
            ],
            'holi' => [
                'label' => 'Holi',
                'tag' => 'Festival',
                'description' => 'Bright festive pinks, purples and sunny yellows.',
                'movable_hint' => 'Usually Feb–Mar',
                'span_before' => 1,
                'span_after' => 1,
                'primary' => '#6b21a8',
                'secondary' => '#db2777',
                'accent' => '#facc15',
                'navy_mid' => '#7e22ce',
                'cream' => '#fdf4ff',
                'text' => '#4a044e',
                'muted' => '#6b7280',
                'greeting' => 'Happy Holi',
                'motif' => 'petals',
                'pattern' => 'petals',
                'effect' => 'petals',
                'ribbon' => true,
                'card_radius' => '18px',
                'button_radius' => '999px',
                'shadow_tint' => 'rgba(219, 39, 119, 0.2)',
                'badge_bg' => '#fce7f3',
                'topbar_stripe' => 'holi',
            ],
            'ram_navami' => [
                'label' => 'Ram Navami',
                'tag' => 'Festival',
                'description' => 'Sacred saffron and deep maroon with warm cream.',
                'movable_hint' => 'Usually Mar–Apr',
                'span_before' => 0,
                'span_after' => 0,
                'primary' => '#7c2d12',
                'secondary' => '#c2410c',
                'accent' => '#fbbf24',
                'navy_mid' => '#9a3412',
                'cream' => '#fff7ed',
                'text' => '#431407',
                'muted' => '#78716c',
                'greeting' => 'Happy Ram Navami',
                'motif' => 'lotus',
                'pattern' => 'dots',
                'ribbon' => true,
                'card_radius' => '14px',
                'button_radius' => '10px',
                'shadow_tint' => 'rgba(194, 65, 12, 0.18)',
                'badge_bg' => '#ffedd5',
                'topbar_stripe' => 'saffron',
            ],
            'mahavir_jayanti' => [
                'label' => 'Mahavir Jayanti',
                'tag' => 'Festival',
                'description' => 'Peaceful saffron and calm cream tones.',
                'movable_hint' => 'Usually Mar–Apr',
                'span_before' => 0,
                'span_after' => 0,
                'primary' => '#9a3412',
                'secondary' => '#ea580c',
                'accent' => '#fde68a',
                'navy_mid' => '#c2410c',
                'cream' => '#fffbeb',
                'text' => '#451a03',
                'muted' => '#78716c',
                'greeting' => 'Happy Mahavir Jayanti',
                'motif' => 'lotus',
                'pattern' => 'dots',
                'ribbon' => true,
                'card_radius' => '14px',
                'button_radius' => '10px',
                'shadow_tint' => 'rgba(234, 88, 12, 0.16)',
                'badge_bg' => '#ffedd5',
                'topbar_stripe' => 'saffron',
            ],
            'good_friday' => [
                'label' => 'Good Friday',
                'tag' => 'Observance',
                'description' => 'Solemn purple and soft silver accents.',
                'movable_hint' => 'Usually Mar–Apr',
                'span_before' => 0,
                'span_after' => 0,
                'primary' => '#3b0764',
                'secondary' => '#6d28d9',
                'accent' => '#c4b5fd',
                'navy_mid' => '#4c1d95',
                'cream' => '#faf5ff',
                'text' => '#2e1065',
                'muted' => '#6b7280',
                'greeting' => 'Good Friday',
                'motif' => 'cross',
                'pattern' => 'none',
                'ribbon' => true,
                'card_radius' => '12px',
                'button_radius' => '8px',
                'shadow_tint' => 'rgba(109, 40, 217, 0.16)',
                'badge_bg' => '#ede9fe',
                'topbar_stripe' => 'purple',
            ],
            'sankranti_ambedkar' => [
                'label' => 'Mahabishuba Sankranti / Ambedkar Jayanti',
                'tag' => 'Odisha / National',
                'description' => 'Odisha New Year & Ambedkar Jayanti — indigo with gold.',
                'fixed' => ['month' => 4, 'day' => 14],
                'span_before' => 0,
                'span_after' => 0,
                'primary' => '#1e3a8a',
                'secondary' => '#2563eb',
                'accent' => '#f59e0b',
                'navy_mid' => '#1d4ed8',
                'cream' => '#fffbeb',
                'text' => '#1e3a8a',
                'muted' => '#64748b',
                'greeting' => 'Happy Mahabishuba Sankranti',
                'motif' => 'lotus',
                'pattern' => 'waves',
                'ribbon' => true,
                'card_radius' => '14px',
                'button_radius' => '10px',
                'shadow_tint' => 'rgba(37, 99, 235, 0.18)',
                'badge_bg' => '#dbeafe',
                'topbar_stripe' => 'gold_blue',
            ],
            'buddha_purnima' => [
                'label' => 'Buddha Purnima',
                'tag' => 'Festival',
                'description' => 'Calm saffron and soft orange for Vesak.',
                'movable_hint' => 'Usually Apr–May',
                'span_before' => 0,
                'span_after' => 0,
                'primary' => '#9a3412',
                'secondary' => '#f97316',
                'accent' => '#fde68a',
                'navy_mid' => '#c2410c',
                'cream' => '#fff7ed',
                'text' => '#7c2d12',
                'muted' => '#78716c',
                'greeting' => 'Happy Buddha Purnima',
                'motif' => 'lotus',
                'pattern' => 'dots',
                'ribbon' => true,
                'card_radius' => '16px',
                'button_radius' => '12px',
                'shadow_tint' => 'rgba(249, 115, 22, 0.18)',
                'badge_bg' => '#ffedd5',
                'topbar_stripe' => 'saffron',
            ],
            'bakrid' => [
                'label' => 'Id-ul-Zuha (Bakrid)',
                'tag' => 'Festival',
                'description' => 'Elegant green and gold for Eid al-Adha.',
                'movable_hint' => 'Lunar calendar',
                'span_before' => 0,
                'span_after' => 1,
                'primary' => '#064e3b',
                'secondary' => '#059669',
                'accent' => '#fbbf24',
                'navy_mid' => '#047857',
                'cream' => '#ecfdf5',
                'text' => '#064e3b',
                'muted' => '#4b5563',
                'greeting' => 'Eid Mubarak',
                'motif' => 'crescent',
                'pattern' => 'dots',
                'effect' => 'lanterns',
                'ribbon' => true,
                'card_radius' => '14px',
                'button_radius' => '10px',
                'shadow_tint' => 'rgba(5, 150, 105, 0.18)',
                'badge_bg' => '#d1fae5',
                'topbar_stripe' => 'green_gold',
            ],
            'muharram' => [
                'label' => 'Muharram',
                'tag' => 'Observance',
                'description' => 'Respectful deep green with muted gold.',
                'movable_hint' => 'Lunar calendar',
                'span_before' => 0,
                'span_after' => 0,
                'primary' => '#052e16',
                'secondary' => '#166534',
                'accent' => '#a3e635',
                'navy_mid' => '#14532d',
                'cream' => '#f0fdf4',
                'text' => '#052e16',
                'muted' => '#4b5563',
                'greeting' => 'Muharram',
                'motif' => 'crescent',
                'pattern' => 'none',
                'ribbon' => true,
                'card_radius' => '12px',
                'button_radius' => '8px',
                'shadow_tint' => 'rgba(22, 101, 52, 0.16)',
                'badge_bg' => '#dcfce7',
                'topbar_stripe' => 'green_gold',
            ],
            'rath_yatra' => [
                'label' => 'Rath Yatra',
                'tag' => 'Odisha',
                'description' => 'Jagannath Rath Yatra — saffron, red and temple gold.',
                'movable_hint' => 'Usually Jun–Jul',
                'span_before' => 1,
                'span_after' => 1,
                'primary' => '#7f1d1d',
                'secondary' => '#ea580c',
                'accent' => '#fbbf24',
                'navy_mid' => '#991b1b',
                'cream' => '#fff7ed',
                'text' => '#450a0a',
                'muted' => '#78716c',
                'greeting' => 'Jai Jagannath — Rath Yatra',
                'motif' => 'rath',
                'pattern' => 'waves',
                'effect' => 'saffron',
                'ribbon' => true,
                'card_radius' => '14px',
                'button_radius' => '10px',
                'shadow_tint' => 'rgba(234, 88, 12, 0.22)',
                'badge_bg' => '#ffedd5',
                'topbar_stripe' => 'saffron_red',
            ],
            'milad_un_nabi' => [
                'label' => 'Milad-un-Nabi',
                'tag' => 'Festival',
                'description' => 'Soft green and cream for the Prophet’s birthday.',
                'movable_hint' => 'Lunar calendar',
                'span_before' => 0,
                'span_after' => 0,
                'primary' => '#065f46',
                'secondary' => '#10b981',
                'accent' => '#fde68a',
                'navy_mid' => '#047857',
                'cream' => '#ecfdf5',
                'text' => '#064e3b',
                'muted' => '#4b5563',
                'greeting' => 'Milad-un-Nabi Mubarak',
                'motif' => 'crescent',
                'pattern' => 'dots',
                'ribbon' => true,
                'card_radius' => '14px',
                'button_radius' => '10px',
                'shadow_tint' => 'rgba(16, 185, 129, 0.18)',
                'badge_bg' => '#d1fae5',
                'topbar_stripe' => 'green_gold',
            ],
            'independence_day' => [
                'label' => 'Independence Day',
                'tag' => 'National',
                'description' => 'Tricolor celebration — saffron, white and green.',
                'fixed' => ['month' => 8, 'day' => 15],
                'span_before' => 1,
                'span_after' => 1,
                'primary' => '#0b3d2e',
                'secondary' => '#138808',
                'accent' => '#ff9933',
                'navy_mid' => '#0f5132',
                'cream' => '#fffaf3',
                'text' => '#0b3d2e',
                'muted' => '#5b6b73',
                'greeting' => 'Happy Independence Day',
                'motif' => 'tricolor',
                'pattern' => 'tricolor_band',
                'effect' => 'tricolor',
                'ribbon' => true,
                'card_radius' => '14px',
                'button_radius' => '10px',
                'shadow_tint' => 'rgba(255, 153, 51, 0.22)',
                'badge_bg' => '#ffedd5',
                'topbar_stripe' => 'tricolor',
            ],
            'janmashtami' => [
                'label' => 'Janmashtami',
                'tag' => 'Festival',
                'description' => 'Peacock blue and festive yellow for Krishna Jayanti.',
                'movable_hint' => 'Usually Aug–Sep',
                'span_before' => 0,
                'span_after' => 0,
                'primary' => '#1e3a8a',
                'secondary' => '#2563eb',
                'accent' => '#facc15',
                'navy_mid' => '#1d4ed8',
                'cream' => '#eff6ff',
                'text' => '#1e3a8a',
                'muted' => '#64748b',
                'greeting' => 'Happy Janmashtami',
                'motif' => 'peacock',
                'pattern' => 'dots',
                'ribbon' => true,
                'card_radius' => '16px',
                'button_radius' => '12px',
                'shadow_tint' => 'rgba(37, 99, 235, 0.2)',
                'badge_bg' => '#dbeafe',
                'topbar_stripe' => 'gold_blue',
            ],
            'gandhi_jayanti' => [
                'label' => "Mahatma Gandhi's Birthday",
                'tag' => 'National',
                'description' => 'Khadi white, spinning-wheel blue and peaceful green.',
                'fixed' => ['month' => 10, 'day' => 2],
                'span_before' => 0,
                'span_after' => 0,
                'primary' => '#1e40af',
                'secondary' => '#16a34a',
                'accent' => '#f8fafc',
                'navy_mid' => '#1d4ed8',
                'cream' => '#f8fafc',
                'text' => '#0f172a',
                'muted' => '#64748b',
                'greeting' => 'Gandhi Jayanti',
                'motif' => 'charkha',
                'pattern' => 'none',
                'effect' => 'softglow',
                'ribbon' => true,
                'card_radius' => '12px',
                'button_radius' => '8px',
                'shadow_tint' => 'rgba(30, 64, 175, 0.14)',
                'badge_bg' => '#dbeafe',
                'topbar_stripe' => 'tricolor',
            ],
            'dussehra_mahanavami' => [
                'label' => 'Dussehra (Mahanavami)',
                'tag' => 'Festival',
                'description' => 'Navratri eve — deep maroon and bright saffron.',
                'movable_hint' => 'Usually Sep–Oct',
                'span_before' => 0,
                'span_after' => 0,
                'primary' => '#4c0519',
                'secondary' => '#9f1239',
                'accent' => '#f59e0b',
                'navy_mid' => '#881337',
                'cream' => '#fff1f2',
                'text' => '#4c0519',
                'muted' => '#6b7280',
                'greeting' => 'Happy Mahanavami',
                'motif' => 'diya',
                'pattern' => 'petals',
                'ribbon' => true,
                'card_radius' => '14px',
                'button_radius' => '10px',
                'shadow_tint' => 'rgba(159, 18, 57, 0.2)',
                'badge_bg' => '#ffe4e6',
                'topbar_stripe' => 'saffron_red',
            ],
            'dussehra' => [
                'label' => 'Dussehra',
                'tag' => 'Festival',
                'description' => 'Vijayadashami — victory saffron and temple gold.',
                'movable_hint' => 'Usually Sep–Oct',
                'span_before' => 0,
                'span_after' => 1,
                'primary' => '#7c2d12',
                'secondary' => '#ea580c',
                'accent' => '#fbbf24',
                'navy_mid' => '#9a3412',
                'cream' => '#fff7ed',
                'text' => '#431407',
                'muted' => '#78716c',
                'greeting' => 'Happy Dussehra',
                'motif' => 'diya',
                'pattern' => 'petals',
                'effect' => 'petals',
                'ribbon' => true,
                'card_radius' => '14px',
                'button_radius' => '10px',
                'shadow_tint' => 'rgba(234, 88, 12, 0.22)',
                'badge_bg' => '#ffedd5',
                'topbar_stripe' => 'saffron_red',
            ],
            'guru_nanak' => [
                'label' => "Guru Nanak's Birthday",
                'tag' => 'Festival',
                'description' => 'Saffron and deep blue for Gurpurab.',
                'movable_hint' => 'Usually Nov',
                'span_before' => 0,
                'span_after' => 0,
                'primary' => '#1e3a8a',
                'secondary' => '#c2410c',
                'accent' => '#fbbf24',
                'navy_mid' => '#1d4ed8',
                'cream' => '#fff7ed',
                'text' => '#1e3a8a',
                'muted' => '#64748b',
                'greeting' => 'Happy Gurpurab',
                'motif' => 'khanda',
                'pattern' => 'dots',
                'ribbon' => true,
                'card_radius' => '14px',
                'button_radius' => '10px',
                'shadow_tint' => 'rgba(194, 65, 12, 0.18)',
                'badge_bg' => '#ffedd5',
                'topbar_stripe' => 'saffron',
            ],
            'christmas' => [
                'label' => 'Christmas Day',
                'tag' => 'Festival',
                'description' => 'Festive evergreen and warm red with soft gold.',
                'fixed' => ['month' => 12, 'day' => 25],
                'span_before' => 2,
                'span_after' => 1,
                'primary' => '#14532d',
                'secondary' => '#b91c1c',
                'accent' => '#fbbf24',
                'navy_mid' => '#166534',
                'cream' => '#fef2f2',
                'text' => '#14532d',
                'muted' => '#6b7280',
                'greeting' => 'Merry Christmas',
                'motif' => 'lights',
                'pattern' => 'sparkles',
                'effect' => 'sparkles',
                'ribbon' => true,
                'card_radius' => '16px',
                'button_radius' => '12px',
                'shadow_tint' => 'rgba(185, 28, 28, 0.18)',
                'badge_bg' => '#fee2e2',
                'topbar_stripe' => 'christmas',
            ],
        ];
    }
}

if (!function_exists('festivalThemeDefaultCalendarSeeds')) {
    /**
     * Approximate movable dates for the given year (admin can edit in UI).
     * Fixed-date festivals are also listed for convenience.
     *
     * @return list<array{pack_key:string,start:string,end:string}>
     */
    function festivalThemeDefaultCalendarSeeds(int $year): array
    {
        // Approximate Indian holiday dates for scheduling; edit in admin as needed.
        $movable = [
            2025 => [
                'holi' => ['03-14', '03-14'],
                'ram_navami' => ['04-06', '04-06'],
                'mahavir_jayanti' => ['04-10', '04-10'],
                'good_friday' => ['04-18', '04-18'],
                'buddha_purnima' => ['05-12', '05-12'],
                'bakrid' => ['06-07', '06-07'],
                'muharram' => ['07-06', '07-06'],
                'rath_yatra' => ['06-27', '06-28'],
                'milad_un_nabi' => ['09-05', '09-05'],
                'janmashtami' => ['08-16', '08-16'],
                'dussehra_mahanavami' => ['10-01', '10-01'],
                'dussehra' => ['10-02', '10-02'],
                'guru_nanak' => ['11-05', '11-05'],
            ],
            2026 => [
                'holi' => ['03-03', '03-04'],
                'ram_navami' => ['03-26', '03-26'],
                'mahavir_jayanti' => ['03-31', '03-31'],
                'good_friday' => ['04-03', '04-03'],
                'buddha_purnima' => ['05-01', '05-01'],
                'bakrid' => ['05-27', '05-28'],
                'muharram' => ['06-16', '06-16'],
                'rath_yatra' => ['07-16', '07-17'],
                'milad_un_nabi' => ['08-25', '08-25'],
                'janmashtami' => ['09-04', '09-04'],
                'dussehra_mahanavami' => ['10-19', '10-19'],
                'dussehra' => ['10-20', '10-21'],
                'guru_nanak' => ['11-24', '11-24'],
            ],
            2027 => [
                'holi' => ['03-22', '03-23'],
                'ram_navami' => ['04-15', '04-15'],
                'mahavir_jayanti' => ['04-20', '04-20'],
                'good_friday' => ['03-26', '03-26'],
                'buddha_purnima' => ['05-20', '05-20'],
                'bakrid' => ['05-17', '05-17'],
                'muharram' => ['06-06', '06-06'],
                'rath_yatra' => ['07-05', '07-06'],
                'milad_un_nabi' => ['08-15', '08-15'],
                'janmashtami' => ['08-24', '08-24'],
                'dussehra_mahanavami' => ['10-08', '10-08'],
                'dussehra' => ['10-09', '10-10'],
                'guru_nanak' => ['11-14', '11-14'],
            ],
        ];

        $packs = festivalThemePackDefinitions();
        $out = [];

        foreach ($packs as $key => $pack) {
            if (!empty($pack['fixed']['month']) && !empty($pack['fixed']['day'])) {
                $m = (int) $pack['fixed']['month'];
                $d = (int) $pack['fixed']['day'];
                $before = (int) ($pack['span_before'] ?? 0);
                $after = (int) ($pack['span_after'] ?? 0);
                $center = sprintf('%04d-%02d-%02d', $year, $m, $d);
                $startTs = strtotime($center . ' -' . $before . ' days');
                $endTs = strtotime($center . ' +' . $after . ' days');
                $out[] = [
                    'pack_key' => $key,
                    'start' => date('Y-m-d', $startTs),
                    'end' => date('Y-m-d', $endTs),
                ];
                continue;
            }
            $md = $movable[$year][$key] ?? null;
            if ($md) {
                $out[] = [
                    'pack_key' => $key,
                    'start' => $year . '-' . $md[0],
                    'end' => $year . '-' . $md[1],
                ];
            }
        }

        return $out;
    }
}

if (!function_exists('ensureFestivalThemeSchema')) {
    function ensureFestivalThemeSchema($conn): bool
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        static $done = false;
        if ($done) {
            return true;
        }

        $ok1 = $conn->query(
            "CREATE TABLE IF NOT EXISTS festival_theme_settings (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                force_pack VARCHAR(64) NULL DEFAULT NULL,
                updated_by VARCHAR(100) NULL DEFAULT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $ok2 = $conn->query(
            "CREATE TABLE IF NOT EXISTS festival_theme_calendar (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                pack_key VARCHAR(64) NOT NULL,
                label VARCHAR(160) NOT NULL DEFAULT '',
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                year_num SMALLINT UNSIGNED NOT NULL,
                UNIQUE KEY uq_fest_pack_start (pack_key, start_date),
                KEY idx_fest_dates (start_date, end_date, is_enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        if ($ok1) {
            $check = $conn->query('SELECT id FROM festival_theme_settings WHERE id = 1 LIMIT 1');
            if ($check && $check->num_rows === 0) {
                $conn->query("INSERT INTO festival_theme_settings (id, enabled) VALUES (1, 1)");
            }
        }

        if ($ok1 && $ok2) {
            festivalThemeSeedCalendar($conn, (int) date('Y'));
            festivalThemeSeedCalendar($conn, (int) date('Y') + 1);
            $done = true;
            return true;
        }
        return false;
    }
}

if (!function_exists('festivalThemeSeedCalendar')) {
    function festivalThemeSeedCalendar($conn, int $year): void
    {
        if (!($conn instanceof mysqli) || $year < 2024) {
            return;
        }
        $packs = festivalThemePackDefinitions();
        $stmt = $conn->prepare(
            'INSERT INTO festival_theme_calendar (pack_key, label, start_date, end_date, is_enabled, year_num)
             SELECT ?, ?, ?, ?, 1, ? FROM DUAL
             WHERE NOT EXISTS (
                SELECT 1 FROM festival_theme_calendar WHERE pack_key = ? AND year_num = ? LIMIT 1
             )'
        );
        if (!$stmt) {
            return;
        }
        foreach (festivalThemeDefaultCalendarSeeds($year) as $row) {
            $key = $row['pack_key'];
            $label = (string) ($packs[$key]['label'] ?? $key);
            $start = $row['start'];
            $end = $row['end'];
            $stmt->bind_param('ssssisi', $key, $label, $start, $end, $year, $key, $year);
            $stmt->execute();
        }
        $stmt->close();
    }
}

if (!function_exists('getFestivalThemeSettings')) {
    /**
     * @return array{enabled:bool,force_pack:?string}
     */
    function getFestivalThemeSettings($conn): array
    {
        $defaults = ['enabled' => true, 'force_pack' => null];
        if (!($conn instanceof mysqli)) {
            return $defaults;
        }
        ensureFestivalThemeSchema($conn);
        $r = $conn->query('SELECT enabled, force_pack FROM festival_theme_settings WHERE id = 1 LIMIT 1');
        if (!$r || !($row = $r->fetch_assoc())) {
            return $defaults;
        }
        $force = trim((string) ($row['force_pack'] ?? ''));
        return [
            'enabled' => !empty($row['enabled']),
            'force_pack' => $force !== '' ? $force : null,
        ];
    }
}

if (!function_exists('setFestivalThemeSettings')) {
    function setFestivalThemeSettings($conn, bool $enabled, ?string $forcePack, string $updatedBy = 'admin'): bool
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        ensureFestivalThemeSchema($conn);
        $packs = festivalThemePackDefinitions();
        if ($forcePack !== null && $forcePack !== '' && !isset($packs[$forcePack])) {
            return false;
        }
        $forceVal = ($forcePack === null || $forcePack === '') ? '' : $forcePack;
        $en = $enabled ? 1 : 0;
        $stmt = $conn->prepare(
            'INSERT INTO festival_theme_settings (id, enabled, force_pack, updated_by)
             VALUES (1, ?, NULLIF(?, \'\'), ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), force_pack = VALUES(force_pack), updated_by = VALUES(updated_by)'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iss', $en, $forceVal, $updatedBy);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    }
}

if (!function_exists('listFestivalThemeCalendar')) {
    /**
     * @return list<array<string,mixed>>
     */
    function listFestivalThemeCalendar($conn, ?int $year = null): array
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }
        ensureFestivalThemeSchema($conn);
        $year = $year ?? (int) date('Y');
        festivalThemeSeedCalendar($conn, $year);
        $stmt = $conn->prepare(
            'SELECT * FROM festival_theme_calendar WHERE year_num = ? ORDER BY start_date ASC, pack_key ASC'
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('updateFestivalThemeCalendarRow')) {
    function updateFestivalThemeCalendarRow(
        $conn,
        int $id,
        string $startDate,
        string $endDate,
        bool $enabled
    ): bool {
        if (!($conn instanceof mysqli) || $id <= 0) {
            return false;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            return false;
        }
        if ($endDate < $startDate) {
            $endDate = $startDate;
        }
        $en = $enabled ? 1 : 0;
        $year = (int) substr($startDate, 0, 4);
        $stmt = $conn->prepare(
            'UPDATE festival_theme_calendar
             SET start_date = ?, end_date = ?, is_enabled = ?, year_num = ?
             WHERE id = ?'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssiii', $startDate, $endDate, $en, $year, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    }
}

if (!function_exists('resolveActiveFestivalTheme')) {
    /**
     * @return array<string,mixed>|null Active pack definition + meta, or null
     */
    function resolveActiveFestivalTheme($conn = null, ?string $today = null): ?array
    {
        $packs = festivalThemePackDefinitions();
        $today = $today ?: date('Y-m-d');

        if ($conn instanceof mysqli) {
            ensureFestivalThemeSchema($conn);
            $settings = getFestivalThemeSettings($conn);
            if (!$settings['enabled']) {
                return null;
            }
            if (!empty($settings['force_pack']) && isset($packs[$settings['force_pack']])) {
                $pack = $packs[$settings['force_pack']];
                $pack['key'] = $settings['force_pack'];
                $pack['_source'] = 'forced';
                return $pack;
            }

            $stmt = $conn->prepare(
                'SELECT pack_key, label, start_date, end_date
                 FROM festival_theme_calendar
                 WHERE is_enabled = 1 AND start_date <= ? AND end_date >= ?
                 ORDER BY start_date DESC
                 LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param('ss', $today, $today);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row && isset($packs[$row['pack_key']])) {
                    $pack = $packs[$row['pack_key']];
                    $pack['key'] = $row['pack_key'];
                    $pack['greeting'] = $pack['greeting'] ?? $row['label'];
                    $pack['_source'] = 'calendar';
                    $pack['_start'] = $row['start_date'];
                    $pack['_end'] = $row['end_date'];
                    return $pack;
                }
            }
        }

        // Fallback: fixed-date packs without DB
        $y = (int) substr($today, 0, 4);
        foreach ($packs as $key => $pack) {
            if (empty($pack['fixed']['month'])) {
                continue;
            }
            $m = (int) $pack['fixed']['month'];
            $d = (int) $pack['fixed']['day'];
            $before = (int) ($pack['span_before'] ?? 0);
            $after = (int) ($pack['span_after'] ?? 0);
            $center = sprintf('%04d-%02d-%02d', $y, $m, $d);
            $start = date('Y-m-d', strtotime($center . ' -' . $before . ' days'));
            $end = date('Y-m-d', strtotime($center . ' +' . $after . ' days'));
            if ($today >= $start && $today <= $end) {
                $pack['key'] = $key;
                $pack['_source'] = 'fixed';
                $pack['_start'] = $start;
                $pack['_end'] = $end;
                return $pack;
            }
        }

        return null;
    }
}

if (!function_exists('festivalThemeSketchKey')) {
    /**
     * Map pack → sketch SVG filename (without .svg).
     *
     * @param array<string,mixed> $pack
     */
    function festivalThemeSketchKey(array $pack): string
    {
        $key = (string) ($pack['key'] ?? '');
        $motif = (string) ($pack['motif'] ?? '');
        $map = [
            'gandhi_jayanti' => 'gandhi',
            'republic_day' => 'ashoka',
            'independence_day' => 'ashoka',
            'holi' => 'holi',
            'ram_navami' => 'lotus',
            'mahavir_jayanti' => 'lotus',
            'buddha_purnima' => 'lotus',
            'sankranti_ambedkar' => 'ashoka',
            'good_friday' => 'cross',
            'bakrid' => 'crescent',
            'muharram' => 'crescent',
            'milad_un_nabi' => 'crescent',
            'rath_yatra' => 'rath',
            'janmashtami' => 'peacock',
            'dussehra_mahanavami' => 'diya',
            'dussehra' => 'diya',
            'guru_nanak' => 'khanda',
            'christmas' => 'christmas',
            'new_year' => 'sparkles',
        ];
        if (isset($map[$key])) {
            return $map[$key];
        }
        $motifMap = [
            'charkha' => 'gandhi',
            'tricolor' => 'ashoka',
            'petals' => 'holi',
            'lotus' => 'lotus',
            'cross' => 'cross',
            'crescent' => 'crescent',
            'rath' => 'rath',
            'peacock' => 'peacock',
            'diya' => 'diya',
            'khanda' => 'khanda',
            'lights' => 'christmas',
            'sparkles' => 'sparkles',
        ];
        return $motifMap[$motif] ?? 'sparkles';
    }
}

if (!function_exists('festivalThemeBannerMeta')) {
    /**
     * Realistic banner copy + style per festival pack.
     *
     * @param array<string,mixed> $pack
     * @return array{style:string,title:string,tagline:string,emoji:string}
     */
    function festivalThemeBannerMeta(array $pack): array
    {
        $key = (string) ($pack['key'] ?? '');
        $greeting = (string) ($pack['greeting'] ?? $pack['label'] ?? 'Festival');
        $motif = (string) ($pack['motif'] ?? 'sparkles');

        $defaults = [
            'new_year' => ['style' => 'sparkle', 'tagline' => 'Welcome a bright new year of learning at NIELIT Bhubaneswar', 'emoji' => '✨🎉'],
            'republic_day' => ['style' => 'tricolor', 'tagline' => 'Saluting the Constitution · Pride of India · Skill for the Nation', 'emoji' => '🇮🇳'],
            'holi' => ['style' => 'holi', 'tagline' => 'Festival of colours — celebrate joy, unity and creativity', 'emoji' => '🎨🌸'],
            'ram_navami' => ['style' => 'saffron', 'tagline' => 'Wishing peace, devotion and auspicious beginnings', 'emoji' => '🪷'],
            'mahavir_jayanti' => ['style' => 'saffron', 'tagline' => 'Walking the path of non-violence and compassion', 'emoji' => '🪷'],
            'good_friday' => ['style' => 'solemn', 'tagline' => 'A day of reflection, faith and hope', 'emoji' => '✝️'],
            'sankranti_ambedkar' => ['style' => 'odisha', 'tagline' => 'Mahabishuba Sankranti & Ambedkar Jayanti greetings', 'emoji' => '🪔📜'],
            'buddha_purnima' => ['style' => 'saffron', 'tagline' => 'May wisdom and peace light every path', 'emoji' => '🪷'],
            'bakrid' => ['style' => 'eid', 'tagline' => 'Eid Mubarak — peace, generosity and togetherness', 'emoji' => '🌙'],
            'muharram' => ['style' => 'eid', 'tagline' => 'Observing Muharram with respect and reflection', 'emoji' => '🌙'],
            'rath_yatra' => ['style' => 'rath', 'tagline' => 'Jai Jagannath — Odisha’s sacred chariot festival', 'emoji' => '🛕'],
            'milad_un_nabi' => ['style' => 'eid', 'tagline' => 'Milad-un-Nabi Mubarak — blessings of peace', 'emoji' => '🌙'],
            'independence_day' => ['style' => 'tricolor', 'tagline' => 'Celebrating freedom · Building skilled India', 'emoji' => '🇮🇳'],
            'janmashtami' => ['style' => 'peacock', 'tagline' => 'Happy Krishna Janmashtami — joy and devotion', 'emoji' => '🦚'],
            'gandhi_jayanti' => ['style' => 'gandhi', 'tagline' => 'Remembering the Father of the Nation · Truth & Non-violence', 'emoji' => '🕊️'],
            'dussehra_mahanavami' => ['style' => 'diya', 'tagline' => 'Victory of good over evil — Mahanavami greetings', 'emoji' => '🪔'],
            'dussehra' => ['style' => 'diya', 'tagline' => 'Happy Vijayadashami — strength, courage and success', 'emoji' => '🪔🏹'],
            'guru_nanak' => ['style' => 'saffron', 'tagline' => 'Happy Gurpurab — Ik Onkar · Peace and equality', 'emoji' => '☬'],
            'christmas' => ['style' => 'christmas', 'tagline' => 'Merry Christmas — peace, joy and goodwill', 'emoji' => '🎄⭐'],
        ];

        $row = $defaults[$key] ?? [
            'style' => 'sparkle',
            'tagline' => 'Warm festive greetings from NIELIT Bhubaneswar',
            'emoji' => '✨',
        ];

        // Motif fallback for style
        if (($row['style'] ?? '') === 'sparkle' && $motif === 'tricolor') {
            $row['style'] = 'tricolor';
        }

        return [
            'style' => (string) ($pack['banner_style'] ?? $row['style']),
            'title' => (string) ($pack['banner_title'] ?? $greeting),
            'tagline' => (string) ($pack['banner_tagline'] ?? $row['tagline']),
            'emoji' => (string) ($pack['banner_emoji'] ?? $row['emoji']),
        ];
    }
}

if (!function_exists('injectFestivalThemeCSS')) {
    /**
     * Inject festival CSS variables + detail styles when a pack is active.
     */
    function injectFestivalThemeCSS($conn = null, ?array $pack = null): void
    {
        if ($pack === null) {
            if (!$conn) {
                global $conn;
            }
            $pack = resolveActiveFestivalTheme($conn instanceof mysqli ? $conn : null);
        }
        if (!$pack || empty($pack['key'])) {
            return;
        }

        $h = static function ($v, $fallback = '') {
            return htmlspecialchars((string) ($v !== null && $v !== '' ? $v : $fallback), ENT_QUOTES, 'UTF-8');
        };

        $key = $h($pack['key']);
        $primary = $h($pack['primary'] ?? '#0a1628');
        $secondary = $h($pack['secondary'] ?? '#1a56db');
        $accent = $h($pack['accent'] ?? '#f59e0b');
        $navyMid = $h($pack['navy_mid'] ?? '#112240');
        $cream = $h($pack['cream'] ?? '#fafaf8');
        $text = $h($pack['text'] ?? '#0f172a');
        $muted = $h($pack['muted'] ?? '#64748b');
        $greeting = $h($pack['greeting'] ?? $pack['label'] ?? 'Festival');
        $motif = $h($pack['motif'] ?? 'sparkles');
        $pattern = $h($pack['pattern'] ?? 'none');
        $effect = $h($pack['effect'] ?? '');
        $cardRadius = $h($pack['card_radius'] ?? '14px');
        $btnRadius = $h($pack['button_radius'] ?? '10px');
        $shadow = $h($pack['shadow_tint'] ?? 'rgba(15,23,42,0.12)');
        $badgeBg = $h($pack['badge_bg'] ?? '#e2e8f0');
        $stripe = $h($pack['topbar_stripe'] ?? 'gold_blue');
        $ribbon = !empty($pack['ribbon']) ? '1' : '0';
        $label = $h($pack['label'] ?? $key);
        $banner = festivalThemeBannerMeta($pack);
        $bannerStyle = $h($banner['style']);
        $bannerTitle = $h($banner['title']);
        $bannerTagline = $h($banner['tagline']);
        $bannerEmoji = $h($banner['emoji']);

        $appUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        $sketchKey = festivalThemeSketchKey($pack);
        $sketchUrl = $appUrl . '/assets/images/festival-sketches/' . $sketchKey . '.svg';
        $sketchUrlCss = $h($sketchUrl);
        $cssFile = __DIR__ . '/../assets/css/festival-theme.css';
        $jsFile = __DIR__ . '/../assets/js/festival-effects.js';
        $ver = @filemtime($cssFile) ?: time();
        $jsVer = @filemtime($jsFile) ?: time();

        echo '<link rel="stylesheet" href="' . $h($appUrl) . '/assets/css/festival-theme.css?v=' . (int) $ver . '">' . "\n";
        echo "<style id=\"festival-theme-vars\">\n";
        echo ":root {\n";
        echo "  --primary-color: {$primary};\n";
        echo "  --secondary-color: {$secondary};\n";
        echo "  --accent-color: {$accent};\n";
        echo "  --navy: {$primary};\n";
        echo "  --navy-mid: {$navyMid};\n";
        echo "  --navy-mid-color: {$navyMid};\n";
        echo "  --blue: {$secondary};\n";
        echo "  --gold: {$accent};\n";
        echo "  --cream: {$cream};\n";
        echo "  --text: {$text};\n";
        echo "  --muted: {$muted};\n";
        echo "  --festival-primary: {$primary};\n";
        echo "  --festival-secondary: {$secondary};\n";
        echo "  --festival-accent: {$accent};\n";
        echo "  --festival-cream: {$cream};\n";
        echo "  --festival-card-radius: {$cardRadius};\n";
        echo "  --festival-btn-radius: {$btnRadius};\n";
        echo "  --festival-shadow: {$shadow};\n";
        echo "  --festival-badge-bg: {$badgeBg};\n";
        echo "  --festival-greeting: '{$greeting}';\n";
        echo "  --festival-key: '{$key}';\n";
        echo "  --festival-sketch-url: url('{$sketchUrlCss}');\n";
        echo "}\n";
        echo "html { --festival-active: 1; }\n";
        // In-section sketch via ::before (overrides homepage display:none on section-white-pattern).
        $sectionList = implode(",\n", [
            'html[data-festival] .mocktest-section',
            'html[data-festival] .welcome-section',
            'html[data-festival] .welcome-strip',
            'html[data-festival] .about-section',
            'html[data-festival] .features-section',
            'html[data-festival] .info-section',
            'html[data-festival] .gallery-section',
            'html[data-festival] .event-gallery',
            'html[data-festival] .news-section',
            'html[data-festival] .contact-section',
            'html[data-festival] .courses-section',
            'html[data-festival] .dynamic-course',
            'html[data-festival] .section-white-pattern',
            'html[data-festival] section.perf-section',
            'html[data-festival] .kiosk-page',
            'html[data-festival] .admin-content',
        ]);
        echo "{$sectionList} {\n";
        echo "  position: relative !important;\n";
        echo "  overflow: hidden !important;\n";
        echo "}\n";
        echo "{$sectionList} > * {\n";
        echo "  position: relative;\n";
        echo "  z-index: 1;\n";
        echo "}\n";
        // Balanced watermarks: right sketch (::before) + left mirrored sketch (::after)
        $beforeList = preg_replace('/html\[data-festival\] ([^{,\n]+)/', 'html[data-festival] $1::before', $sectionList);
        $afterList = preg_replace('/html\[data-festival\] ([^{,\n]+)/', 'html[data-festival] $1::after', $sectionList);
        echo "{$beforeList} {\n";
        echo "  content: \"\" !important;\n";
        echo "  display: block !important;\n";
        echo "  position: absolute !important;\n";
        echo "  right: 0.5%; top: 50%;\n";
        echo "  width: min(32vw, 220px);\n";
        echo "  height: min(70%, 340px);\n";
        echo "  transform: translateY(-50%);\n";
        echo "  z-index: 0 !important;\n";
        echo "  pointer-events: none !important;\n";
        echo "  opacity: 0.07 !important;\n";
        echo "  background: url('{$sketchUrlCss}') center / contain no-repeat !important;\n";
        echo "  filter: grayscale(1);\n";
        echo "}\n";
        echo "{$afterList} {\n";
        echo "  content: \"\" !important;\n";
        echo "  display: block !important;\n";
        echo "  position: absolute !important;\n";
        echo "  left: 0.5%; top: 50%;\n";
        echo "  width: min(32vw, 220px);\n";
        echo "  height: min(70%, 340px);\n";
        echo "  transform: translateY(-50%) scaleX(-1);\n";
        echo "  z-index: 0 !important;\n";
        echo "  pointer-events: none !important;\n";
        echo "  opacity: 0.065 !important;\n";
        echo "  background: url('{$sketchUrlCss}') center / contain no-repeat !important;\n";
        echo "  filter: grayscale(1);\n";
        echo "}\n";
        echo "html[data-festival] .hero-section::before,\n";
        echo "html[data-festival] .hero-section::after {\n";
        echo "  content: none !important;\n";
        echo "  display: none !important;\n";
        echo "}\n";
        echo "html[data-festival] .mocktest-panel {\n";
        echo "  position: relative !important;\n";
        echo "  overflow: hidden !important;\n";
        echo "  background-color: #fff !important;\n";
        echo "}\n";
        echo "html[data-festival] .mocktest-panel::before,\n";
        echo "html[data-festival] .mocktest-panel::after {\n";
        echo "  content: none !important;\n";
        echo "  display: none !important;\n";
        echo "}\n";
        // Keep sketches in the page gutters (outside Bootstrap container) so both sides show
        echo "@media (min-width: 1100px) {\n";
        echo "  html[data-festival] .mocktest-section .container,\n";
        echo "  html[data-festival] .features-section .container,\n";
        echo "  html[data-festival] .section-white-pattern .container {\n";
        echo "    max-width: 980px;\n";
        echo "  }\n";
        echo "  html[data-festival] .mocktest-section::before,\n";
        echo "  html[data-festival] .features-section::before,\n";
        echo "  html[data-festival] .section-white-pattern::before {\n";
        echo "    right: max(10px, calc((100vw - 980px) / 2 - 150px));\n";
        echo "    width: min(18vw, 150px);\n";
        echo "    height: min(78%, 380px);\n";
        echo "    opacity: 0.1 !important;\n";
        echo "  }\n";
        echo "  html[data-festival] .mocktest-section::after,\n";
        echo "  html[data-festival] .features-section::after,\n";
        echo "  html[data-festival] .section-white-pattern::after {\n";
        echo "    left: max(10px, calc((100vw - 980px) / 2 - 150px));\n";
        echo "    width: min(18vw, 150px);\n";
        echo "    height: min(78%, 380px);\n";
        echo "    opacity: 0.095 !important;\n";
        echo "  }\n";
        echo "}\n";
        echo "html[data-festival=\"gandhi_jayanti\"] .features-section::before,\n";
        echo "html[data-festival=\"gandhi_jayanti\"] .section-white-pattern::before,\n";
        echo "html[data-festival=\"gandhi_jayanti\"] .mocktest-section::before,\n";
        echo "html[data-festival=\"gandhi_jayanti\"] .features-section::after,\n";
        echo "html[data-festival=\"gandhi_jayanti\"] .section-white-pattern::after,\n";
        echo "html[data-festival=\"gandhi_jayanti\"] .mocktest-section::after {\n";
        echo "  opacity: 0.1 !important;\n";
        echo "}\n";
        echo "html[data-mode=\"night\"][data-festival] .mocktest-section::before,\n";
        echo "html[data-mode=\"night\"][data-festival] .features-section::before,\n";
        echo "html[data-mode=\"night\"][data-festival] .section-white-pattern::before,\n";
        echo "html[data-mode=\"night\"][data-festival] section.perf-section::before,\n";
        echo "html[data-mode=\"night\"][data-festival] .mocktest-section::after,\n";
        echo "html[data-mode=\"night\"][data-festival] .features-section::after,\n";
        echo "html[data-mode=\"night\"][data-festival] .section-white-pattern::after,\n";
        echo "html[data-mode=\"night\"][data-festival] section.perf-section::after {\n";
        echo "  opacity: 0.1 !important;\n";
        echo "  filter: grayscale(1) invert(0.9);\n";
        echo "}\n";
        echo "html[data-festival] .hero-overlay {\n";
        echo "  background: linear-gradient(135deg, rgba(10,22,40,0.9) 0%, rgba(10,22,40,0.55) 55%, rgba(10,22,40,0.4) 100%) !important;\n";
        echo "}\n";
        echo "html[data-festival] .hero-carousel .carousel-item img {\n";
        echo "  opacity: 0.38 !important;\n";
        echo "}\n";

        // Photo banner background when a local festival banner image exists
        $bannerDir = __DIR__ . '/../assets/images/festival-banners';
        $bannerPhoto = '';
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $candidate = $bannerDir . '/' . $pack['key'] . '.' . $ext;
            if (is_file($candidate)) {
                $bannerPhoto = $appUrl . '/assets/images/festival-banners/' . $pack['key'] . '.' . $ext;
                break;
            }
        }
        $bannerPhotoCss = $bannerPhoto !== '' ? $h($bannerPhoto) : '';
        if ($bannerPhotoCss !== '') {
            // Keep band elegant: solid festival wash (portrait goes in side circles, not cropped full-bleed)
            echo "html[data-festival] .festival-banner.festival-banner--photo {\n";
            echo "  min-height: 128px;\n";
            echo "  color: #fff !important;\n";
            echo "  background-image: linear-gradient(105deg, rgba(15,23,42,0.96), rgba(30,64,175,0.55) 50%, rgba(20,83,45,0.9)) !important;\n";
            echo "}\n";
            echo "html[data-festival] .festival-banner .festival-banner-title,\n";
            echo "html[data-festival] .festival-banner .festival-banner-tagline {\n";
            echo "  color: #fff !important;\n";
            echo "  text-shadow: 0 1px 10px rgba(0,0,0,0.45);\n";
            echo "}\n";
            echo "html[data-festival=\"gandhi_jayanti\"] .festival-banner {\n";
            echo "  min-height: 136px;\n";
            echo "  background-image: linear-gradient(105deg, #1e3a5f 0%, #0f172a 45%, #14532d 100%) !important;\n";
            echo "  border-bottom: 3px solid #ff9933 !important;\n";
            echo "}\n";
        }
        echo "</style>\n";
        // Banner replaces ribbon — skip ribbon to avoid double strip
        echo '<script>(function(){'
            . 'var d=document.documentElement;'
            . 'd.setAttribute("data-festival","' . $key . '");'
            . 'd.setAttribute("data-festival-motif","' . $motif . '");'
            . 'd.setAttribute("data-festival-pattern","' . $pattern . '");'
            . 'd.setAttribute("data-festival-stripe","' . $stripe . '");'
            . 'd.setAttribute("data-festival-ribbon","0");'
            . 'd.setAttribute("data-festival-label","' . $label . '");'
            . 'd.setAttribute("data-festival-banner","1");'
            . 'd.setAttribute("data-festival-banner-style","' . $bannerStyle . '");'
            . 'd.setAttribute("data-festival-banner-title","' . $bannerTitle . '");'
            . 'd.setAttribute("data-festival-banner-tagline","' . $bannerTagline . '");'
            . 'd.setAttribute("data-festival-banner-emoji","' . $bannerEmoji . '");'
            . ($bannerPhotoCss !== '' ? 'd.setAttribute("data-festival-banner-photo","' . $bannerPhotoCss . '");' : '')
            . 'd.setAttribute("data-festival-sketch","' . $h($sketchKey) . '");'
            . 'd.setAttribute("data-festival-asset-base","' . $h($appUrl . '/assets/images/festival-sketches') . '");'
            . ($effect !== '' ? 'd.setAttribute("data-festival-effect","' . $effect . '");' : '')
            // Ribbon skipped when banner is active (banner replaces the top strip).
            . '})();</script>' . "\n";
        echo '<script src="' . $h($appUrl) . '/assets/js/festival-effects.js?v=' . (int) $jsVer . '" defer></script>' . "\n";
    }
}

if (!function_exists('emitFestivalThemeHead')) {
    function emitFestivalThemeHead($conn = null): void
    {
        if (!$conn) {
            global $conn;
        }
        injectFestivalThemeCSS($conn instanceof mysqli ? $conn : null);
    }
}

if (!function_exists('festivalThemeRibbonHtml')) {
    function festivalThemeRibbonHtml($conn = null): string
    {
        if (!$conn) {
            global $conn;
        }
        // Festival banner (JS) already shows the greeting — skip ribbon strip.
        return '';
    }
}
