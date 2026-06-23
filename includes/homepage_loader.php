<?php
/**
 * Homepage content loader for index.php and admin/manage_homepage.php
 */

if (!function_exists('ensureHomepageContentSchema')) {
    function ensureHomepageContentSchema($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS homepage_content (
            id INT(11) NOT NULL AUTO_INCREMENT,
            section_key VARCHAR(50) NOT NULL,
            section_title VARCHAR(255) NOT NULL,
            section_content TEXT,
            section_type ENUM('banner', 'announcement', 'featured_course', 'text_block', 'image_block') NOT NULL,
            display_order INT(11) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_homepage_section_key (section_key),
            KEY idx_active (is_active),
            KEY idx_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('ensureHomepageContentSchema failed: ' . $conn->error);
            return false;
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('getIndexHomepageSectionDefinitions')) {
    function getIndexHomepageSectionDefinitions(): array
    {
        return [
            'notice_primary' => [
                'group' => 'Notice Ticker',
                'label' => 'Primary notice (after NOTICE label)',
                'type' => 'text_block',
                'order' => 10,
                'default_title' => 'Primary Notice',
                'default_content' => 'Admissions Open! NIELIT Bhubaneswar offers NSQF-aligned courses with modern facilities. Visit our Baleshwar Extension Center today.',
            ],
            'notice_secondary' => [
                'group' => 'Notice Ticker',
                'label' => 'Secondary notice (after NEW label)',
                'type' => 'text_block',
                'order' => 11,
                'default_title' => 'Secondary Notice',
                'default_content' => 'Online Registrations are now live — Apply before the deadline!',
            ],
            'hero_eyebrow' => [
                'group' => 'Hero Banner',
                'label' => 'Hero eyebrow text',
                'type' => 'text_block',
                'order' => 20,
                'default_title' => 'Hero Eyebrow',
                'default_content' => 'Ministry of Electronics & IT · Est. 2021',
            ],
            'hero_subtitle' => [
                'group' => 'Hero Banner',
                'label' => 'Hero subtitle paragraph',
                'type' => 'text_block',
                'order' => 21,
                'default_title' => 'Hero Subtitle',
                'default_content' => 'NIELIT Bhubaneswar — your gateway to NSQF-aligned technology education across Odisha and Chhattisgarh. Skills that power India\'s future.',
            ],
            'hero_typing_lines' => [
                'group' => 'Hero Banner',
                'label' => 'Hero typing lines (JSON array)',
                'type' => 'text_block',
                'order' => 22,
                'default_title' => 'Hero Typing Lines',
                'default_content' => '[{"line1":"Code Tomorrow.","line2":"Transform Today."},{"line1":"Learn Today.","line2":"Lead Tomorrow."},{"line1":"Skills Today.","line2":"Success Tomorrow."}]',
            ],
            'hero_stat_1' => [
                'group' => 'Hero Stats',
                'label' => 'Stat 1 (title=number, content=label)',
                'type' => 'text_block',
                'order' => 30,
                'default_title' => '15+',
                'default_content' => 'Courses Offered',
            ],
            'hero_stat_2' => [
                'group' => 'Hero Stats',
                'label' => 'Stat 2',
                'type' => 'text_block',
                'order' => 31,
                'default_title' => '2',
                'default_content' => 'Centers Active',
            ],
            'hero_stat_3' => [
                'group' => 'Hero Stats',
                'label' => 'Stat 3',
                'type' => 'text_block',
                'order' => 32,
                'default_title' => '5000+',
                'default_content' => 'Students Trained',
            ],
            'hero_stat_4' => [
                'group' => 'Hero Stats',
                'label' => 'Stat 4',
                'type' => 'text_block',
                'order' => 33,
                'default_title' => '100%',
                'default_content' => 'Govt. Certified',
            ],
            'welcome_eyebrow' => [
                'group' => 'Welcome Strip',
                'label' => 'Eyebrow',
                'type' => 'text_block',
                'order' => 40,
                'default_title' => 'Welcome Eyebrow',
                'default_content' => 'Welcome to NIELIT Bhubaneswar',
            ],
            'welcome_title' => [
                'group' => 'Welcome Strip',
                'label' => 'Heading',
                'type' => 'text_block',
                'order' => 41,
                'default_title' => 'Welcome Title',
                'default_content' => 'Excellence in Technology Education Since 2021.',
            ],
            'welcome_text' => [
                'group' => 'Welcome Strip',
                'label' => 'Description',
                'type' => 'text_block',
                'order' => 42,
                'default_title' => 'Welcome Text',
                'default_content' => 'We are a premier autonomous scientific society under MeitY, Government of India — dedicated to developing human resources in Information, Electronics, and Communication Technology (IECT) through industry-aligned programs.',
            ],
            'jobfair_eyebrow' => [
                'group' => 'Job Fair Portal',
                'label' => 'Eyebrow',
                'type' => 'text_block',
                'order' => 50,
                'default_title' => 'Job Fair Eyebrow',
                'default_content' => 'National Job Fair Initiative',
            ],
            'jobfair_title' => [
                'group' => 'Job Fair Portal',
                'label' => 'Title',
                'type' => 'text_block',
                'order' => 51,
                'default_title' => 'Job Fair Title',
                'default_content' => 'NIELIT Bhubaneswar Job Fair Portal',
            ],
            'jobfair_lead' => [
                'group' => 'Job Fair Portal',
                'label' => 'Lead paragraph',
                'type' => 'text_block',
                'order' => 52,
                'default_title' => 'Job Fair Lead',
                'default_content' => 'A centralized government platform for transparent, seamless, and large-scale recruitment drives across NIELIT regional centres. Empowering youth and connecting employers with skilled talent.',
            ],
            'jobfair_alert' => [
                'group' => 'Job Fair Portal',
                'label' => 'Alert box text',
                'type' => 'text_block',
                'order' => 53,
                'default_title' => 'Job Fair Alert',
                'default_content' => 'Registration is open for upcoming Mega Job Fairs. Candidates can complete profiles and check in at the venue. Recruiters can upload offer letters directly through the portal.',
            ],
            'mocktest_eyebrow' => [
                'group' => 'Mock Test Portal',
                'label' => 'Eyebrow',
                'type' => 'text_block',
                'order' => 60,
                'default_title' => 'Mock Test Eyebrow',
                'default_content' => 'Exam Preparation',
            ],
            'mocktest_title' => [
                'group' => 'Mock Test Portal',
                'label' => 'Title',
                'type' => 'text_block',
                'order' => 61,
                'default_title' => 'Mock Test Title',
                'default_content' => 'NIELIT Mock Assessment Platform',
            ],
            'mocktest_lead' => [
                'group' => 'Mock Test Portal',
                'label' => 'Lead paragraph',
                'type' => 'text_block',
                'order' => 62,
                'default_title' => 'Mock Test Lead',
                'default_content' => 'A secure mock test platform for NIELIT Bhubaneswar candidates and authorized training partners. Practice NSQF-aligned assessments, download admit cards, and build exam readiness before official certification tests.',
            ],
            'features_eyebrow' => [
                'group' => 'Features Section',
                'label' => 'Section eyebrow',
                'type' => 'text_block',
                'order' => 70,
                'default_title' => 'Features Eyebrow',
                'default_content' => 'What We Offer',
            ],
            'features_title' => [
                'group' => 'Features Section',
                'label' => 'Section heading',
                'type' => 'text_block',
                'order' => 71,
                'default_title' => 'Features Title',
                'default_content' => 'Built for the Future of Work.',
            ],
            'feature_1' => [
                'group' => 'Features Section',
                'label' => 'Feature card 1 (title + description)',
                'type' => 'text_block',
                'order' => 72,
                'default_title' => 'Skill Development',
                'default_content' => 'NSQF-aligned courses to boost employability in the rapidly evolving technology sector.',
            ],
            'feature_2' => [
                'group' => 'Features Section',
                'label' => 'Feature card 2',
                'type' => 'text_block',
                'order' => 73,
                'default_title' => 'Regional Scope',
                'default_content' => 'Operating extensively across Odisha and Chhattisgarh to reach every aspiring student.',
            ],
            'feature_3' => [
                'group' => 'Features Section',
                'label' => 'Feature card 3',
                'type' => 'text_block',
                'order' => 74,
                'default_title' => 'Modern Facilities',
                'default_content' => 'State-of-the-art labs, smart classrooms, and conference halls at OCAC Tower.',
            ],
            'feature_4' => [
                'group' => 'Features Section',
                'label' => 'Feature card 4',
                'type' => 'text_block',
                'order' => 75,
                'default_title' => 'Baleshwar Extension',
                'default_content' => 'Expanding our footprint to deliver quality education across the Baleshwar region.',
            ],
            'info_eyebrow' => [
                'group' => 'Info Section',
                'label' => 'Section eyebrow',
                'type' => 'text_block',
                'order' => 80,
                'default_title' => 'Info Eyebrow',
                'default_content' => 'Learn More',
            ],
            'info_title' => [
                'group' => 'Info Section',
                'label' => 'Section heading',
                'type' => 'text_block',
                'order' => 81,
                'default_title' => 'Info Title',
                'default_content' => 'Everything You Need to Know.',
            ],
            'about_title' => [
                'group' => 'Info Section',
                'label' => 'About card (title + text)',
                'type' => 'text_block',
                'order' => 82,
                'default_title' => 'About NIELIT',
                'default_content' => 'An autonomous scientific society under MeitY, Govt. of India — focused on human resource development in IECT through quality education and practical training programs.',
            ],
            'mission_title' => [
                'group' => 'Info Section',
                'label' => 'Mission card (title + text)',
                'type' => 'text_block',
                'order' => 83,
                'default_title' => 'Our Mission',
                'default_content' => 'To empower youth with cutting-edge technology skills, making them industry-ready and contributing to India\'s digital transformation through quality education and practical training.',
            ],
        ];
    }
}

if (!function_exists('seedIndexHomepageSections')) {
    function seedIndexHomepageSections($conn): void
    {
        if (!ensureHomepageContentSchema($conn)) {
            return;
        }

        $stmt = $conn->prepare(
            'INSERT INTO homepage_content (section_key, section_title, section_content, section_type, display_order, is_active)
             SELECT ?, ?, ?, ?, ?, 1 FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM homepage_content WHERE section_key = ? LIMIT 1)'
        );

        if (!$stmt) {
            error_log('seedIndexHomepageSections prepare failed: ' . $conn->error);
            return;
        }

        foreach (getIndexHomepageSectionDefinitions() as $key => $def) {
            $title = $def['default_title'];
            $content = $def['default_content'];
            $type = $def['type'];
            $order = (int) $def['order'];
            $stmt->bind_param('ssssis', $key, $title, $content, $type, $order, $key);
            $stmt->execute();
        }

        $stmt->close();
    }
}

if (!function_exists('clearHomepageContentCache')) {
    function clearHomepageContentCache(): void
    {
        unset($_SESSION['homepage_content_cache'], $_SESSION['homepage_content_cache_time'], $_SESSION['homepage_map_cache']);
    }
}

if (!function_exists('loadHomepageContentMap')) {
    function loadHomepageContentMap($conn, bool $activeOnly = true): array
    {
        ensureHomepageContentSchema($conn);
        seedIndexHomepageSections($conn);

        $sql = 'SELECT * FROM homepage_content';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY display_order ASC';

        $map = [];
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $map[$row['section_key']] = $row;
            }
        }

        return $map;
    }
}

if (!function_exists('homepageValue')) {
    function homepageValue(array $map, string $key, string $field = 'content', string $default = ''): string
    {
        $definitions = getIndexHomepageSectionDefinitions();
        if ($default === '' && isset($definitions[$key])) {
            $default = $field === 'title'
                ? (string) $definitions[$key]['default_title']
                : (string) $definitions[$key]['default_content'];
        }

        if (!isset($map[$key])) {
            return $default;
        }

        $row = $map[$key];
        $value = $field === 'title'
            ? (string) ($row['section_title'] ?? '')
            : (string) ($row['section_content'] ?? '');

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('homepageTypingLines')) {
    function homepageTypingLines(array $map): array
    {
        $defaults = [
            ['line1' => 'Code Tomorrow.', 'line2' => 'Transform Today.'],
            ['line1' => 'Learn Today.', 'line2' => 'Lead Tomorrow.'],
            ['line1' => 'Skills Today.', 'line2' => 'Success Tomorrow.'],
        ];

        $raw = homepageValue($map, 'hero_typing_lines', 'content', '');
        if ($raw === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        $lines = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $line1 = trim((string) ($item['line1'] ?? ''));
            $line2 = trim((string) ($item['line2'] ?? ''));
            if ($line1 !== '' && $line2 !== '') {
                $lines[] = ['line1' => $line1, 'line2' => $line2];
            }
        }

        return $lines ?: $defaults;
    }
}

if (!function_exists('loadHomepagePageSections')) {
    function loadHomepagePageSections($conn): array
    {
        $cache_duration = 3600;
        $cache_key = 'homepage_content_cache';
        $cache_time_key = 'homepage_content_cache_time';
        $map_cache_key = 'homepage_map_cache';

        if (
            isset($_SESSION[$cache_key], $_SESSION[$cache_time_key], $_SESSION[$map_cache_key])
            && (time() - (int) $_SESSION[$cache_time_key] < $cache_duration)
        ) {
            return [
                'map' => $_SESSION[$map_cache_key],
                'banners' => $_SESSION[$cache_key]['banners'] ?? [],
                'announcements_content' => $_SESSION[$cache_key]['announcements_content'] ?? [],
                'featured_courses' => $_SESSION[$cache_key]['featured_courses'] ?? [],
                'text_blocks' => $_SESSION[$cache_key]['text_blocks'] ?? [],
                'image_blocks' => $_SESSION[$cache_key]['image_blocks'] ?? [],
            ];
        }

        $map = loadHomepageContentMap($conn, true);
        $indexKeys = array_keys(getIndexHomepageSectionDefinitions());

        $banners = [];
        $announcements_content = [];
        $featured_courses = [];
        $text_blocks = [];
        $image_blocks = [];

        foreach ($map as $section) {
            if (in_array($section['section_key'], $indexKeys, true)) {
                continue;
            }

            switch ($section['section_type']) {
                case 'banner':
                    $banners[] = $section;
                    break;
                case 'announcement':
                    $announcements_content[] = $section;
                    break;
                case 'featured_course':
                    $featured_courses[] = $section;
                    break;
                case 'text_block':
                    $text_blocks[] = $section;
                    break;
                case 'image_block':
                    $image_blocks[] = $section;
                    break;
            }
        }

        $_SESSION[$cache_key] = compact('banners', 'announcements_content', 'featured_courses', 'text_blocks', 'image_blocks');
        $_SESSION[$cache_time_key] = time();
        $_SESSION[$map_cache_key] = $map;

        return compact('map', 'banners', 'announcements_content', 'featured_courses', 'text_blocks', 'image_blocks');
    }
}

if (!function_exists('getIndexHomepageSectionsForAdmin')) {
    function getIndexHomepageSectionsForAdmin($conn): array
    {
        seedIndexHomepageSections($conn);
        $map = loadHomepageContentMap($conn, false);
        $definitions = getIndexHomepageSectionDefinitions();
        $grouped = [];

        foreach ($definitions as $key => $def) {
            $group = $def['group'];
            $row = $map[$key] ?? null;
            $grouped[$group][] = [
                'section_key' => $key,
                'label' => $def['label'],
                'id' => $row['id'] ?? null,
                'section_title' => $row['section_title'] ?? $def['default_title'],
                'section_content' => $row['section_content'] ?? $def['default_content'],
                'section_type' => $row['section_type'] ?? $def['type'],
                'display_order' => $row['display_order'] ?? $def['order'],
                'is_active' => isset($row['is_active']) ? (int) $row['is_active'] : 1,
            ];
        }

        return $grouped;
    }
}
