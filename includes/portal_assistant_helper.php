<?php
/**
 * Vani AI Campus & Portal Assistant — Orchestrator
 * Supports:
 * - Multilingual auto-detect & switching (English, Hindi, Odia)
 * - Live DB tools: search_courses, register_student, get_site_roadmap, log_support_callback
 * - Flexible AI backends: Gemini (function calling), OpenAI (function calling), n8n chat webhook,
 *   plus full multilingual En/Hi/Or stateful fallback engine & authenticated student/staff actions.
 */

require_once __DIR__ . '/portal_assistant_tools.php';

if (!function_exists('portal_assistant_load_config')) {
    /** @return array<string, mixed> */
    function portal_assistant_load_config(?mysqli $conn = null): array
    {
        $cfg = [
            'ai_provider' => 'auto',
            'use_gemini' => false,
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-2.0-flash',
            'use_openai' => false,
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o-mini',
            'chat_webhook_enabled' => false,
            'chat_webhook_url' => '',
            'assistant_name' => 'Gyansetu',
            'assistant_theme' => 'ocean',
        ];

        $path = dirname(__DIR__) . '/config/portal_assistant.local.php';
        if (is_file($path)) {
            $local = include $path;
            if (is_array($local)) {
                $cfg = array_merge($cfg, $local);
            }
        }

        // Optional environment variable overrides
        $envGemini = trim((string) (getenv('GEMINI_API_KEY') ?: ''));
        if ($envGemini !== '' && trim((string) $cfg['gemini_api_key']) === '') {
            $cfg['gemini_api_key'] = $envGemini;
        }
        $envOpenAi = trim((string) (getenv('OPENAI_API_KEY') ?: ''));
        if ($envOpenAi !== '' && trim((string) $cfg['openai_api_key']) === '') {
            $cfg['openai_api_key'] = $envOpenAi;
        }

        // DB settings (Master Admin -> API & Automation)
        if ($conn instanceof mysqli) {
            if (!function_exists('getPortalAutomationSettings')) {
                require_once __DIR__ . '/automation_webhook_helper.php';
            }
            $dbSettings = getPortalAutomationSettings($conn);
            if (!empty($dbSettings['ai_provider'])) {
                $cfg['ai_provider'] = (string) $dbSettings['ai_provider'];
            }
            if (!empty($dbSettings['gemini_api_key'])) {
                $cfg['gemini_api_key'] = (string) $dbSettings['gemini_api_key'];
            }
            if (!empty($dbSettings['gemini_model'])) {
                $cfg['gemini_model'] = (string) $dbSettings['gemini_model'];
            }
            if (!empty($dbSettings['openai_api_key'])) {
                $cfg['openai_api_key'] = (string) $dbSettings['openai_api_key'];
            }
            if (!empty($dbSettings['openai_model'])) {
                $cfg['openai_model'] = (string) $dbSettings['openai_model'];
            }
            $cfg['chat_webhook_enabled'] = !empty($dbSettings['chat_webhook_enabled']);
            $cfg['chat_webhook_url'] = (string) ($dbSettings['chat_webhook_url'] ?? '');
            $cfg['assistant_name'] = trim((string) ($dbSettings['assistant_name'] ?? 'Gyansetu')) ?: 'Gyansetu';
            $cfg['assistant_theme'] = (string) ($dbSettings['assistant_theme'] ?? 'ocean');
        }

        if (trim((string) $cfg['gemini_api_key']) !== '') {
            $cfg['use_gemini'] = true;
        }
        if (trim((string) $cfg['openai_api_key']) !== '') {
            $cfg['use_openai'] = true;
        }

        return $cfg;
    }
}

if (!function_exists('portal_assistant_rate_limit_ok')) {
    function portal_assistant_rate_limit_ok(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $now = time();
        $window = 3600;
        $max = 60;
        $bucket = $_SESSION['portal_assistant_rl'] ?? ['start' => $now, 'count' => 0];
        if ($now - (int) $bucket['start'] > $window) {
            $bucket = ['start' => $now, 'count' => 0];
        }
        if ((int) $bucket['count'] >= $max) {
            return false;
        }
        $bucket['count'] = (int) $bucket['count'] + 1;
        $_SESSION['portal_assistant_rl'] = $bucket;
        return true;
    }
}

if (!function_exists('portal_assistant_site_context')) {
    /** @return array<string, string> */
    function portal_assistant_site_context(?mysqli $conn = null): array
    {
        require_once __DIR__ . '/url_helper.php';
        $base = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
        $ctx = [
            'site_name' => 'NIELIT Bhubaneswar Extension Centre',
            'home_url' => $base . '/index.php',
            'courses_page' => app_url('public/courses'),
            'student_login' => app_url('student/login'),
            'student_register' => app_url('student/register'),
            'admin_login' => app_url('admin/login'),
            'payment_portal' => $base . '/payment-portal/',
            'mock_test' => $base . '/mocktest/',
            'contact' => app_url('public/contact'),
        ];
        if ($conn instanceof mysqli && function_exists('loadHomepagePageSections')) {
            require_once __DIR__ . '/homepage_loader.php';
            $sections = loadHomepagePageSections($conn);
            $map = $sections['map'] ?? [];
            if (function_exists('homepagePortalUrls')) {
                $portals = homepagePortalUrls($map);
                if (!empty($portals['mocktest'])) {
                    $ctx['mock_test'] = (string) $portals['mocktest'];
                }
                if (!empty($portals['main'])) {
                    $ctx['nielit_main'] = (string) $portals['main'];
                }
            }
        }
        if ($conn instanceof mysqli && function_exists('listPublicNews')) {
            require_once __DIR__ . '/news_helper.php';
            $news = listPublicNews($conn, 3);
            $titles = [];
            foreach ($news as $row) {
                $t = trim((string) ($row['title'] ?? ''));
                if ($t !== '') {
                    $titles[] = $t;
                }
            }
            $ctx['latest_news'] = $titles ? implode('; ', $titles) : 'Check the News section on the homepage.';
        }
        return $ctx;
    }
}

if (!function_exists('portal_assistant_actor_context')) {
    /** @return array<string, mixed> */
    function portal_assistant_actor_context(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $actor = [
            'is_admin' => !empty($_SESSION['admin']),
            'is_student' => !empty($_SESSION['student_id']),
            'name' => '',
            'role' => '',
        ];
        if ($actor['is_admin']) {
            $actor['name'] = (string) ($_SESSION['admin_name'] ?? 'Administrator');
            $actor['role'] = (string) ($_SESSION['admin_role'] ?? 'admin');
        } elseif ($actor['is_student']) {
            $actor['name'] = (string) ($_SESSION['student_name'] ?? '');
        }
        return $actor;
    }
}

if (!function_exists('saarthi_detect_language')) {
    function saarthi_detect_language(string $message, ?string $explicitHint = null): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $m = trim($message);

        // Explicit switch commands in user message take top priority
        if (preg_match('/odia\s*(re|me|mein|please|pls|kuha|kaha|batao)?\b|ଓଡ଼ିଆ/iu', $m)
            && preg_match('/\b(odia|oriya)\b|ଓଡ଼ିଆ/iu', $m)) {
            $_SESSION['saarthi_lang'] = 'or';
            return 'or';
        }
        if (preg_match('/\b(hindi\s*(mein|me|please|pls|batao|bol|karo))\b|हिन्दी|हिंदी/iu', $m)) {
            $_SESSION['saarthi_lang'] = 'hi';
            return 'hi';
        }
        if (preg_match('/\b(switch\s+to\s+english|in\s+english|english\s+(please|pls|me|mein))\b/iu', $m)) {
            $_SESSION['saarthi_lang'] = 'en';
            return 'en';
        }

        // Script detection (Odia / Devanagari)
        if (preg_match('/[\x{0B00}-\x{0B7F}]/u', $m)) {
            $_SESSION['saarthi_lang'] = 'or';
            return 'or';
        }
        if (preg_match('/[\x{0900}-\x{097F}]/u', $m)) {
            $_SESSION['saarthi_lang'] = 'hi';
            return 'hi';
        }

        // Romanized Odia markers
        if (preg_match('/\b(kuha|kaha|kemiti|kemti|kete|tanka|kebe|kana|amara|mora|pila|patha|namaskar)\b/iu', $m)) {
            $_SESSION['saarthi_lang'] = 'or';
            return 'or';
        }
        // Romanized Hindi markers
        if (preg_match('/\b(namaste|kaise|kitna|kitni|kitne|batao|bataiye|chahiye|karna\s+hai|mera|meri|apna|kahan|mujhe|bhi)\b/iu', $m)) {
            $_SESSION['saarthi_lang'] = 'hi';
            return 'hi';
        }

        if ($explicitHint !== null && $explicitHint !== '') {
            $norm = saarthi_normalize_lang($explicitHint);
            $_SESSION['saarthi_lang'] = $norm;
            return $norm;
        }

        $saved = (string) ($_SESSION['saarthi_lang'] ?? 'en');
        return saarthi_normalize_lang($saved);
    }
}

if (!function_exists('saarthi_default_quick_replies')) {
    /**
     * @return list<string>
     */
    function saarthi_default_quick_replies(string $lang): array
    {
        if ($lang === 'hi') {
            return ['पोर्टल कैसे काम करता है?', 'कोर्स और सीटें देखें', 'नया रजिस्ट्रेशन करें', 'Odia re kuha'];
        }
        if ($lang === 'or') {
            return ['ପୋର୍ଟାଲ୍ କେମିତି କାମ କରେ?', 'ଚାଲୁଥିବା କୋର୍ସ ଦେଖନ୍ତୁ', 'ନୂଆ ପଞ୍ଜୀକରଣ କରନ୍ତୁ', 'Hindi mein batao'];
        }
        return ['How does this site work?', 'Ongoing courses & centers', 'Register for a course', 'Odia / हिन्दी'];
    }
}

if (!function_exists('saarthi_localized_text')) {
    function saarthi_localized_text(string $lang, string $en, string $hi, string $or): string
    {
        if ($lang === 'hi') {
            return $hi;
        }
        if ($lang === 'or') {
            return $or;
        }
        return $en;
    }
}

if (!function_exists('saarthi_has_sensitive_payment_input')) {
    function saarthi_has_sensitive_payment_input(string $message): bool
    {
        $m = saarthi_lower($message);
        if (preg_match('/\b(upi\s*pin|mpin|atm\s*pin|cvv|cvc|card\s*number|debit\s*card\s*\d|credit\s*card\s*\d|my\s+password\s+is|password\s*[:=]\s*\S+)\b/i', $m)) {
            return true;
        }
        // Check 14-19 digit card numbers (spaces/hyphens allowed)
        if (preg_match('/(?:\d[ -]*){14,19}/', $m) && preg_match('/\b(card|visa|mastercard|rupay|debit|credit|expiry|valid)\b/i', $m)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('saarthi_system_prompt')) {
    function saarthi_system_prompt(string $lang, array $ctx, ?mysqli $conn = null): string
    {
        $centresList = [];
        if ($conn instanceof mysqli) {
            foreach (saarthi_list_centres($conn) as $c) {
                $centresList[] = $c['name'] . (!empty($c['city']) ? " ({$c['city']})" : '');
            }
        }
        $centresLine = $centresList ? implode(', ', $centresList) : 'NIELIT Bhubaneswar (OCAC Tower), NIELIT Baleshwar Extension, NIELIT Raipur';
        $help = saarthi_helpdesk_info();
        $assistantName = trim((string) ($ctx['assistant_name'] ?? 'Gyansetu')) ?: 'Gyansetu';

        $langLabel = $lang === 'hi' ? 'Hindi (हिन्दी)' : ($lang === 'or' ? 'Odia (ଓଡ଼ିଆ)' : 'English');

        return "You are **{$assistantName}**, the intelligent, friendly, and proactive AI Campus & Portal Assistant for {$ctx['site_name']}.
Your job is to guide visitors across the website, explain how the portal works using clear 4-step roadmaps, answer live queries about ongoing courses and center locations, and assist students with course registrations conversationally.

ACTIVE LANGUAGE: {$langLabel}. Always reply in the user's language (English, Hindi / हिन्दी Devanagari, or Odia / ଓଡ଼ିଆ script), and switch immediately if asked (e.g. 'Odia re kuha', 'Hindi mein batao', 'Switch to English'). Keep technical terms (O-Level, CCC, AI/ML, NSQF, PDF, APAAR ID) clear. Keep replies warm, counselor-like, and concise.

PORTAL & HELPDESK FACTS:
- Active Campuses / Centres in DB: {$centresLine}
- Portal links: Courses = {$ctx['courses_page']}, Student Login = {$ctx['student_login']}, Full Admission Form = {$ctx['student_register']}, Payment Portal = {$ctx['payment_portal']}, Mock Test = {$ctx['mock_test']}
- Helpdesk Phone: {$help['phone']} | Emails: {$help['email']}, {$help['director_email']} ({$help['hours']})
- News hints: " . ($ctx['latest_news'] ?? 'See Homepage News section') . "

WORKFLOWS:
- Task A (Site Roadmap): When asked how the site works or where to start, call `get_site_roadmap` or present the 4-Step Roadmap (Explore Courses -> Check Batch & Seat Status -> Register Online / via {$assistantName} -> Download Admission Form / Receipt) and include `[UI_ACTION: SHOW_ROADMAP]`.
- Task B (Live Course Search): When asked about ongoing courses, fees, duration, batch timings, seats, or locations, ALWAYS call `search_courses(keyword, location, status)` instead of guessing. Never fabricate dates, fees, or seat counts.
- Task C (Conversational Registration): Collect details 1-2 short questions per turn across all fields printed on the Admission Form:
  Step 1: Course Name + Preferred Center Location
  Step 2: Full Name + Mobile Number (10 digits) + Email Address
  Step 3: Date of Birth + Gender + Father's Name + Mother's Name, then Religion (Hindu/Muslim/Christian/Other), Marital Status (Single/Married), and Birth Mark (or 'None')
  Step 4: Academic/Examination Details — Exam Passed, Board/University & Last College Name, Passing Year (4-digit), Stream/Branch, and Percentage/CGPA
  Step 5: Category (General/OBC/SC/ST/EWS) & PwD Status (Yes/No), optional APAAR ID & 12-digit Aadhar Number, and Full Correspondence Address
  Then show a concise Registration Summary Card and ask the user to reply 'Confirm'. ONLY AFTER explicit confirmation, call `register_student(...)` to save to MySQL, trigger confirmation email, return their Student ID + Upload Documents URL + Admission PDF (`[UI_ACTION: DOWNLOAD_PDF]`).
- Documents & Email Follow-up: After registering via {$assistantName}, students can upload Passport Photo, Signature, 12-digit Aadhar Card, and required certificates immediately using the in-chat Upload button or at `student/upload_documents.php` (also available inside Student Portal -> Profile/Dashboard). Confirmation emails (with Student ID, password for new students or existing-password note for returning students) are emailed automatically.
- Safety & Escalation: NEVER ask for or store passwords, UPI PINs, OTPs, or card details. For payment failures or certificate corrections, give the official helpdesk email/phone and offer `log_support_callback`.

RESPONSE FORMAT:
Always start your response with a single-line JSON metadata header:
{\"avatar_state\": \"wave|thinking|guide|happy|celebrate\", \"lang\": \"en|hi|or\", \"ui_action\": \"NONE|SHOW_ROADMAP|SCROLL_COURSES|OPEN_REGISTRATION|DOWNLOAD_PDF\", \"quick_replies\": [\"Chip 1\", \"Chip 2\", \"Chip 3\"]}
followed by a newline and your helpful response text.";
    }
}

if (!function_exists('saarthi_parse_model_output')) {
    /**
     * Strip leading single-line JSON metadata header & [UI_ACTION: ...] tags into clean API response fields.
     *
     * @return array<string, mixed>
     */
    function saarthi_parse_model_output(string $rawText, string $fallbackLang, array $sideEffects = []): array
    {
        $text = trim($rawText);
        $meta = [
            'avatar_state' => 'happy',
            'lang' => $fallbackLang,
            'ui_action' => 'NONE',
            'quick_replies' => [],
        ];

        // Extract leading JSON line if present (either raw `{...}` on line 1 or ```json\n{...}\n```)
        if (preg_match('/^\s*(?:```json\s*)?(\{"avatar_state"[^\r\n]*\})(?:\s*```)?\s*(?:\r?\n|$)/u', $text, $m)) {
            $parsed = json_decode($m[1], true);
            if (is_array($parsed)) {
                if (!empty($parsed['avatar_state']) && in_array($parsed['avatar_state'], ['wave', 'thinking', 'guide', 'happy', 'celebrate'], true)) {
                    $meta['avatar_state'] = $parsed['avatar_state'];
                }
                if (!empty($parsed['lang'])) {
                    $meta['lang'] = saarthi_normalize_lang((string) $parsed['lang']);
                }
                if (!empty($parsed['ui_action'])) {
                    $meta['ui_action'] = strtoupper((string) $parsed['ui_action']);
                }
                if (!empty($parsed['quick_replies']) && is_array($parsed['quick_replies'])) {
                    $meta['quick_replies'] = array_values(array_slice(array_filter(array_map('strval', $parsed['quick_replies'])), 0, 4));
                }
            }
            $text = trim(substr($text, strlen($m[0])));
        }

        // Parse and strip any inline [UI_ACTION: ...] tags
        if (preg_match_all('/\[UI_ACTION:\s*([A-Z0-9_]+)\s*\]/i', $text, $am)) {
            $lastAction = strtoupper(end($am[1]));
            if ($lastAction === 'DOWNLOAD_ADMISSION_PDF') {
                $lastAction = 'DOWNLOAD_PDF';
            }
            if (in_array($lastAction, ['SHOW_ROADMAP', 'SCROLL_COURSES', 'OPEN_REGISTRATION', 'DOWNLOAD_PDF'], true)) {
                $meta['ui_action'] = $lastAction;
            }
            $text = trim((string) preg_replace('/\[UI_ACTION:\s*[A-Z0-9_]+\s*\]/i', '', $text));
        }

        if (!empty($sideEffects['ui_action'])) {
            $meta['ui_action'] = $sideEffects['ui_action'];
        }
        if (!empty($sideEffects['avatar_state'])) {
            $meta['avatar_state'] = $sideEffects['avatar_state'];
        }
        if (empty($meta['quick_replies'])) {
            $meta['quick_replies'] = !empty($sideEffects['quick_replies'])
                ? $sideEffects['quick_replies']
                : saarthi_default_quick_replies($meta['lang']);
        }

        return [
            'reply' => $text,
            'avatar_state' => $meta['avatar_state'],
            'lang' => $meta['lang'],
            'ui_action' => $meta['ui_action'],
            'quick_replies' => $meta['quick_replies'],
            'cards' => $sideEffects['cards'] ?? [],
        ];
    }
}

if (!function_exists('saarthi_execute_tool')) {
    /**
     * Dispatch an LLM tool call against live MySQL helpers and record visual cards for the frontend.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $sideEffects
     * @return array<string, mixed>
     */
    function saarthi_execute_tool(?mysqli $conn, string $name, array $args, string $lang, array $ctx, array &$sideEffects): array
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'error' => 'Database unavailable'];
        }
        switch ($name) {
            case 'search_courses':
                $res = saarthi_search_courses(
                    $conn,
                    (string) ($args['keyword'] ?? ''),
                    (string) ($args['location'] ?? ''),
                    (string) ($args['status'] ?? 'all')
                );
                if (!empty($res['courses'])) {
                    $sideEffects['cards']['courses'] = $res['courses'];
                    $sideEffects['avatar_state'] = 'guide';
                }
                return $res;

            case 'get_site_roadmap':
                $rLang = !empty($args['lang']) ? saarthi_normalize_lang((string) $args['lang']) : $lang;
                $roadmap = saarthi_get_roadmap($rLang, $ctx);
                $sideEffects['cards']['roadmap'] = $roadmap;
                $sideEffects['ui_action'] = 'SHOW_ROADMAP';
                $sideEffects['avatar_state'] = 'guide';
                return ['ok' => true, 'roadmap' => $roadmap];

            case 'register_student':
                $res = saarthi_register_student($conn, $args);
                if (!empty($res['ok'])) {
                    unset($_SESSION['saarthi_reg']);
                    $sideEffects['avatar_state'] = 'celebrate';
                    if (!empty($res['download_pdf_url'])) {
                        $sideEffects['ui_action'] = 'DOWNLOAD_PDF';
                    }
                    $sideEffects['cards']['registration_receipt'] = $res;
                }
                return $res;

            case 'log_support_callback':
                $res = saarthi_log_support_callback($conn, $args);
                $sideEffects['avatar_state'] = 'happy';
                return $res;
        }
        return ['ok' => false, 'error' => 'Unknown tool: ' . $name];
    }
}

if (!function_exists('saarthi_gemini_reply')) {
    /**
     * Call Google Gemini with live function calling (search_courses, register_student, get_site_roadmap, log_support_callback).
     *
     * @param list<array{role: string, content: string}> $history
     * @return array<string, mixed>|null
     */
    function saarthi_gemini_reply(string $message, array $history, string $lang, array $ctx, array $config, ?mysqli $conn): ?array
    {
        $apiKey = trim((string) ($config['gemini_api_key'] ?? ''));
        if (empty($config['use_gemini']) || $apiKey === '' || !function_exists('curl_init')) {
            return null;
        }
        $model = trim((string) ($config['gemini_model'] ?? 'gemini-2.5-flash')) ?: 'gemini-2.5-flash';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);

        $contents = [];
        foreach (array_slice($history, -10) as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'model' : 'user';
            $txt = trim((string) ($turn['content'] ?? ''));
            if ($txt !== '') {
                $contents[] = ['role' => $role, 'parts' => [['text' => $txt]]];
            }
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        $tools = [[
            'functionDeclarations' => [
                [
                    'name' => 'search_courses',
                    'description' => 'Search live courses, campus locations, durations, fees, delivery modes, and batch seats from the NIELIT database.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'keyword' => ['type' => 'STRING', 'description' => 'Course name/topic e.g. O Level, CCC, Python, AI, Drone, IoT, or empty string for all'],
                            'location' => ['type' => 'STRING', 'description' => 'Campus/city filter e.g. Bhubaneswar, Baleshwar, Raipur, or empty string'],
                            'status' => ['type' => 'STRING', 'description' => 'one of: all, ongoing, closed, upcoming'],
                        ],
                    ],
                ],
                [
                    'name' => 'get_site_roadmap',
                    'description' => 'Show the interactive 4-step NIELIT portal roadmap (Explore Courses -> Batch & Seat Status -> Register -> Download Admission PDF).',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'lang' => ['type' => 'STRING', 'description' => 'en, hi, or or'],
                        ],
                    ],
                ],
                [
                    'name' => 'register_student',
                    'description' => 'Save a confirmed student course registration into MySQL including family profile and academic examination details. ONLY call AFTER showing a summary card and receiving explicit user confirmation.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'course_name' => ['type' => 'STRING'],
                            'center_location' => ['type' => 'STRING'],
                            'name' => ['type' => 'STRING'],
                            'mobile' => ['type' => 'STRING'],
                            'email' => ['type' => 'STRING'],
                            'dob' => ['type' => 'STRING', 'description' => 'YYYY-MM-DD'],
                            'gender' => ['type' => 'STRING'],
                            'father_name' => ['type' => 'STRING'],
                            'mother_name' => ['type' => 'STRING'],
                            'religion' => ['type' => 'STRING'],
                            'marital_status' => ['type' => 'STRING'],
                            'distinguishing_marks' => ['type' => 'STRING'],
                            'qualification' => ['type' => 'STRING', 'description' => 'Highest exam passed e.g. B.Tech, 12th, 10th'],
                            'board_university' => ['type' => 'STRING'],
                            'year_of_passing' => ['type' => 'STRING', 'description' => '4-digit year e.g. 2023'],
                            'stream' => ['type' => 'STRING'],
                            'percentage' => ['type' => 'STRING'],
                            'college_name' => ['type' => 'STRING'],
                            'aadhar' => ['type' => 'STRING'],
                            'apaar_id' => ['type' => 'STRING'],
                            'category' => ['type' => 'STRING'],
                            'pwd_status' => ['type' => 'STRING'],
                            'address' => ['type' => 'STRING'],
                        ],
                        'required' => ['course_name', 'name', 'mobile', 'email', 'dob', 'gender', 'father_name', 'mother_name', 'qualification', 'board_university', 'year_of_passing', 'percentage', 'address'],
                    ],
                ],
                [
                    'name' => 'log_support_callback',
                    'description' => 'Log a support callback ticket for payment gateway failures, certificate corrections, or staff follow-up.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'name' => ['type' => 'STRING'],
                            'phone' => ['type' => 'STRING'],
                            'email' => ['type' => 'STRING'],
                            'issue' => ['type' => 'STRING'],
                            'category' => ['type' => 'STRING'],
                        ],
                        'required' => ['name', 'phone', 'issue'],
                    ],
                ],
            ],
        ]];

        $systemText = saarthi_system_prompt($lang, $ctx, $conn);
        $sideEffects = [];

        for ($hop = 0; $hop < 3; $hop++) {
            $body = json_encode([
                'systemInstruction' => ['parts' => [['text' => $systemText]]],
                'contents' => $contents,
                'tools' => $tools,
                'generationConfig' => ['temperature' => 0.35, 'maxOutputTokens' => 700],
            ], JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                return null;
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT => 20,
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code !== 200 || !is_string($raw)) {
                return null;
            }
            $decoded = json_decode($raw, true);
            $candidate = $decoded['candidates'][0]['content'] ?? null;
            if (!is_array($candidate) || empty($candidate['parts'])) {
                return null;
            }

            $parts = $candidate['parts'];
            $fnCalls = [];
            $textReply = '';
            foreach ($parts as $part) {
                if (!empty($part['functionCall']['name'])) {
                    $fnCalls[] = $part['functionCall'];
                } elseif (isset($part['text'])) {
                    $textReply .= (string) $part['text'];
                }
            }

            if ($fnCalls === []) {
                if (trim($textReply) === '') {
                    return null;
                }
                $parsed = saarthi_parse_model_output($textReply, $lang, $sideEffects);
                $parsed['mode'] = 'gemini';
                return $parsed;
            }

            $contents[] = ['role' => 'model', 'parts' => $parts];
            $fnRespParts = [];
            foreach ($fnCalls as $fc) {
                $fnName = (string) $fc['name'];
                $fnArgs = is_array($fc['args'] ?? null) ? $fc['args'] : [];
                $toolOut = saarthi_execute_tool($conn, $fnName, $fnArgs, $lang, $ctx, $sideEffects);
                $fnRespParts[] = [
                    'functionResponse' => [
                        'name' => $fnName,
                        'response' => ['result' => $toolOut],
                    ],
                ];
            }
            $contents[] = ['role' => 'user', 'parts' => $fnRespParts];
        }

        return null;
    }
}

if (!function_exists('portal_assistant_openai_reply')) {
    /**
     * Call OpenAI Chat Completions with live tool calling.
     *
     * @param list<array{role: string, content: string}> $history
     * @return array<string, mixed>|null
     */
    function portal_assistant_openai_reply(string $message, array $history, string $lang, array $ctx, array $config, ?mysqli $conn = null): ?array
    {
        if (empty($config['use_openai']) || trim((string) ($config['openai_api_key'] ?? '')) === '' || !function_exists('curl_init')) {
            return null;
        }

        $system = saarthi_system_prompt($lang, $ctx, $conn);
        $messages = [['role' => 'system', 'content' => $system]];
        foreach (array_slice($history, -10) as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_courses',
                    'description' => 'Search live courses, campus locations, durations, fees, delivery modes, and batch seats from the NIELIT database.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => ['type' => 'string'],
                            'location' => ['type' => 'string'],
                            'status' => ['type' => 'string', 'enum' => ['all', 'ongoing', 'closed', 'upcoming']],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_site_roadmap',
                    'description' => 'Show the 4-step interactive portal roadmap.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['lang' => ['type' => 'string']],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'register_student',
                    'description' => 'Register a confirmed student in MySQL including family details and examination history. Only call after explicit user confirmation.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'course_name' => ['type' => 'string'],
                            'center_location' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'mobile' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                            'dob' => ['type' => 'string'],
                            'gender' => ['type' => 'string'],
                            'father_name' => ['type' => 'string'],
                            'mother_name' => ['type' => 'string'],
                            'religion' => ['type' => 'string'],
                            'marital_status' => ['type' => 'string'],
                            'distinguishing_marks' => ['type' => 'string'],
                            'qualification' => ['type' => 'string'],
                            'board_university' => ['type' => 'string'],
                            'year_of_passing' => ['type' => 'string'],
                            'stream' => ['type' => 'string'],
                            'percentage' => ['type' => 'string'],
                            'college_name' => ['type' => 'string'],
                            'aadhar' => ['type' => 'string'],
                            'apaar_id' => ['type' => 'string'],
                            'category' => ['type' => 'string'],
                            'pwd_status' => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                        ],
                        'required' => ['course_name', 'name', 'mobile', 'email', 'dob', 'gender', 'father_name', 'mother_name', 'qualification', 'board_university', 'year_of_passing', 'percentage', 'address'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'log_support_callback',
                    'description' => 'Log a support callback ticket for payment gateway issues or certificate corrections.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'phone' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                            'issue' => ['type' => 'string'],
                            'category' => ['type' => 'string'],
                        ],
                        'required' => ['name', 'phone', 'issue'],
                    ],
                ],
            ],
        ];

        $sideEffects = [];
        $model = (string) ($config['openai_model'] ?? 'gpt-4o-mini');
        $key = trim((string) $config['openai_api_key']);

        for ($hop = 0; $hop < 3; $hop++) {
            $body = json_encode([
                'model' => $model,
                'messages' => $messages,
                'tools' => $tools,
                'max_tokens' => 650,
                'temperature' => 0.35,
            ], JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                return null;
            }

            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT => 20,
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code !== 200 || !is_string($raw)) {
                return null;
            }
            $decoded = json_decode($raw, true);
            $msg = $decoded['choices'][0]['message'] ?? null;
            if (!is_array($msg)) {
                return null;
            }

            $toolCalls = $msg['tool_calls'] ?? [];
            if (empty($toolCalls)) {
                $content = (string) ($msg['content'] ?? '');
                if (trim($content) === '') {
                    return null;
                }
                $parsed = saarthi_parse_model_output($content, $lang, $sideEffects);
                $parsed['mode'] = 'openai';
                return $parsed;
            }

            $messages[] = $msg;
            foreach ($toolCalls as $tc) {
                $fnName = (string) ($tc['function']['name'] ?? '');
                $fnArgs = json_decode((string) ($tc['function']['arguments'] ?? '{}'), true);
                if (!is_array($fnArgs)) {
                    $fnArgs = [];
                }
                $toolOut = saarthi_execute_tool($conn, $fnName, $fnArgs, $lang, $ctx, $sideEffects);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($tc['id'] ?? ''),
                    'content' => json_encode($toolOut, JSON_UNESCAPED_UNICODE) ?: '{}',
                ];
            }
        }

        return null;
    }
}

if (!function_exists('portal_assistant_admin_activity_reply')) {
    function portal_assistant_admin_activity_reply(?mysqli $conn, string $message): ?string
    {
        if (!$conn instanceof mysqli || empty($_SESSION['admin'])) {
            return null;
        }
        if (!preg_match('/student\s*update|record\s*update|report|activity|inspector|excel|recent|full\s*update|n8n/u', saarthi_lower($message))) {
            return null;
        }
        if (!function_exists('automationFetchActivityFeed')) {
            require_once __DIR__ . '/automation_webhook_helper.php';
        }
        $feed = automationFetchActivityFeed($conn, 0, 20, []);
        $events = $feed['events'] ?? [];
        if ($events === []) {
            return "No recent student activity in the latest batch (activity log).\n\n"
                . "When staff edit students, n8n can email alerts if the instant webhook is enabled.";
        }
        $lines = [];
        foreach ($events as $e) {
            $lines[] = '• ' . ($e['created_at'] ?? '') . ' | ' . ($e['action'] ?? '')
                . ' | ' . ($e['entity_id'] ?? '') . ' | ' . ($e['actor_name'] ?? '')
                . ' — ' . ($e['description'] ?? '');
        }
        return "Recent student updates (last " . count($events) . " logged actions):\n\n"
            . implode("\n", $lines)
            . "\n\nSource: portal activity log. Instant n8n emails use the same events.";
    }
}

if (!function_exists('saarthi_parse_course_search_intent')) {
    /**
     * Extract location & keyword from natural English/Hindi/Odia queries about courses & locations.
     *
     * @return array{keyword: string, location: string, status: string}
     */
    function saarthi_parse_course_search_intent(string $message, ?mysqli $conn = null): array
    {
        $m = saarthi_lower(trim($message));
        $location = '';
        if (preg_match('/\b(bhubaneswar|bbsr|vss\s*nagar|ocac|campus\s*1|campus\s*i|campus\s*2|campus\s*ii)\b/iu', $m)) {
            $location = 'Bhubaneswar';
        } elseif (preg_match('/\b(baleshwar|balasore)\b|ବାଲେଶ୍ୱର|बालासोर/iu', $m)) {
            $location = 'Baleshwar';
        } elseif (preg_match('/\b(raipur)\b|रायपुर|ରାୟପୁର/iu', $m)) {
            $location = 'Raipur';
        } elseif (preg_match('/\b(cuttack)\b|कटक|କଟକ/iu', $m)) {
            $location = 'Cuttack';
        }

        $status = 'all';
        if (preg_match('/\b(ongoing|open|current|active|available)\b|ଚାଲୁଥିବା|चल रहे/iu', $m)) {
            $status = 'ongoing';
        } elseif (preg_match('/\b(closed)\b/iu', $m)) {
            $status = 'closed';
        } elseif (preg_match('/\b(upcoming)\b|ଆଗାମୀ/iu', $m)) {
            $status = 'upcoming';
        }

        // Known program tokens or cleaned keyword
        $keyword = '';
        $known = [
            'o-level' => 'O Level',
            'o level' => 'O Level',
            'a-level' => 'A Level',
            'a level' => 'A Level',
            'chmt' => 'CHMT',
            'ccc' => 'CCC',
            'python' => 'Python',
            'machine learning' => 'Machine Learning',
            'ai/ml' => 'Machine Learning',
            'iot' => 'IoT',
            'drone' => 'Drone',
            'web developer' => 'Web Developer',
            'multimedia' => 'Multimedia',
            'internship' => 'Internship',
            'workshop' => 'Workshop',
            'solar' => 'Solar',
            'information security' => 'Information Security',
            'data entry' => 'Data Entry',
        ];
        foreach ($known as $needle => $val) {
            if (strpos($m, $needle) !== false) {
                $keyword = $val;
                break;
            }
        }

        // Match dynamically against live course titles/codes if no preset keyword matched
        if ($keyword === '' && $conn instanceof mysqli) {
            $res = @$conn->query('SELECT course_name, course_code FROM courses ORDER BY id DESC LIMIT 100');
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $cName = saarthi_lower(trim((string) ($r['course_name'] ?? '')));
                    $cCode = saarthi_lower(trim((string) ($r['course_code'] ?? '')));
                    if ($cCode !== '' && strlen($cCode) >= 2 && preg_match('/\b' . preg_quote($cCode, '/') . '\b/i', $m)) {
                        $keyword = (string) $r['course_name'];
                        break;
                    }
                    if ($cName !== '' && strpos($m, $cName) !== false) {
                        $keyword = (string) $r['course_name'];
                        break;
                    }
                }
            }
        }

        return ['keyword' => $keyword, 'location' => $location, 'status' => $status];
    }
}

if (!function_exists('saarthi_registration_intent')) {
    /**
     * Determine whether a message explicitly asks to begin chat registration rather than asking a general FAQ.
     *
     * @return array{prefilled_course_id: int, clean_message: string, is_info_question: bool, starts: bool}
     */
    function saarthi_registration_intent(string $message): array
    {
        $prefilledCourseId = 0;
        $clean = trim($message);
        if (preg_match('/\[course_id:(\d+)\]/i', $clean, $cm)) {
            $prefilledCourseId = (int) $cm[1];
            $clean = trim((string) preg_replace('/\[course_id:\d+\]/i', '', $clean));
        }
        $mLower = saarthi_lower($clean);
        $isInfoQuestion = (bool) preg_match('/\b(how\s+(do|does|to)|what\s+is|process|when|fees|document|documents)\b.*\b(register|registration|admission)\b/iu', $mLower)
            && $prefilledCourseId === 0
            && !preg_match('/\b(in\s+chat|via\s+(?:vani|saarthi)|start|want\s+to\s+register|register\s+me|register\s+now)\b/iu', $mLower);
        $starts = !$isInfoQuestion && (($prefilledCourseId > 0) || (bool) preg_match(
            '/\b(want\s+to\s+register|register\s+me|register\s+now|register\s+for|start\s+registration|enroll\s+me|apply\s+for)\b|^\s*(register|register\s+in\s+chat|apply\s*now|new\s+registration|enroll)\s*$|पंजीकरण\s*कर|रजिस्ट्रेशन\s*कर|ଦାଖଲା|ପଞ୍ଜୀକରଣ\s*କର/iu',
            $mLower
        ));
        return [
            'prefilled_course_id' => $prefilledCourseId,
            'clean_message' => $clean,
            'is_info_question' => $isInfoQuestion,
            'starts' => $starts,
        ];
    }
}

if (!function_exists('saarthi_parse_step3_personal_input')) {
    /**
     * Extract DOB, gender, father's name, mother's name, religion, marital status, and birth mark from user text.
     *
     * @param array<string, mixed> $draft
     * @return array<string, mixed>
     */
    function saarthi_parse_step3_personal_input(string $message, array $draft = []): array
    {
        $work = trim($message);

        if (preg_match('/\b(\d{4}[-\/]\d{1,2}[-\/]\d{1,2}|\d{1,2}[-\/\.]\d{1,2}[-\/\.]\d{4})\b/', $work, $dm)) {
            $parsedDob = saarthi_parse_date_input(str_replace('.', '-', $dm[1]));
            if ($parsedDob !== null) {
                $draft['dob'] = $parsedDob;
                $work = str_replace($dm[0], ' ', $work);
            }
        }

        if (preg_match('/\b(female|woman|girl|mahila)\b|महिला|ମହିଳା/iu', $work, $gm)) {
            $draft['gender'] = 'Female';
            $work = str_ireplace($gm[0], ' ', $work);
        } elseif (preg_match('/\b(male|man|boy|purush)\b|पुरुष|ପୁରୁଷ/iu', $work, $gm)) {
            $draft['gender'] = 'Male';
            $work = str_ireplace($gm[0], ' ', $work);
        } elseif (preg_match('/\b(other|non[\s\-]?binary)\b/iu', $work, $gm)) {
            $draft['gender'] = 'Other';
            $work = str_ireplace($gm[0], ' ', $work);
        }

        if (preg_match('/\b(hindu|hinduism)\b|हिन्दू|हिंदू|ହିନ୍ଦୁ/iu', $work, $rm)) {
            $draft['religion'] = 'Hindu';
            $work = str_ireplace($rm[0], ' ', $work);
        } elseif (preg_match('/\b(muslim|islam)\b|मुस्लिम|ମୁସଲିମ୍/iu', $work, $rm)) {
            $draft['religion'] = 'Muslim';
            $work = str_ireplace($rm[0], ' ', $work);
        } elseif (preg_match('/\b(christian|christ)\b|ईसाई|ଖ୍ରୀଷ୍ଟିଆନ୍/iu', $work, $rm)) {
            $draft['religion'] = 'Christian';
            $work = str_ireplace($rm[0], ' ', $work);
        } elseif (preg_match('/\breligion\b\s*[:=\-]?\s*(other|sikh|jain|buddhist)\b/iu', $work, $rm)) {
            $draft['religion'] = 'Other';
            $work = str_ireplace($rm[0], ' ', $work);
        }

        if (preg_match('/\b(married)\b|विवाहित|ବିବାହିତ/iu', $work, $mm)) {
            $draft['marital_status'] = 'Married';
            $work = str_ireplace($mm[0], ' ', $work);
        } elseif (preg_match('/\b(single|unmarried)\b|अविवाहित|ଅବିବାହିତ/iu', $work, $mm)) {
            $draft['marital_status'] = 'Single';
            $work = str_ireplace($mm[0], ' ', $work);
        }

        if (preg_match('/\b(?:birth\s*mark|identification\s*mark|distinguishing\s*mark(?:s)?|mark)\b\s*[:=\-]?\s*([^,\n;|]+)/iu', $work, $bm)) {
            $bmVal = trim($bm[1]);
            if ($bmVal !== '') {
                $draft['distinguishing_marks'] = preg_match('/^(none|nil|no|na|n\/a|-)$/i', $bmVal) ? 'None' : $bmVal;
                $work = str_ireplace($bm[0], ' ', $work);
            }
        } elseif (preg_match('/\b(no\s*birth\s*mark|no\s*mark|none|nil)\b/i', $work, $bm)) {
            $draft['distinguishing_marks'] = 'None';
            $work = str_ireplace($bm[0], ' ', $work);
        }

        // Extract labeled father / mother names (supports "Father: X", "Father's name is X", Hindi/Odia labels)
        if (preg_match('/(?:father(?:\'?s)?\s*(?:name)?|पिता(?:\s*का\s*नाम)?|ପିତା(?:\s*ଙ୍କ\s*ନାମ)?)\s*[:=\-]?\s*(?:is\s+)?([^,\n;|]+)(?=,|;|\n|\||$)/iu', $work, $fm)) {
            $fn = trim($fm[1]);
            if ($fn !== '' && mb_strlen($fn) >= 2 && !preg_match('/^\d+$/', $fn) && !preg_match('/^\d{1,4}[-\/.]\d{1,2}[-\/.]\d{2,4}$/', $fn) && !preg_match('/^(male|female|other|m|f|o)$/i', $fn)) {
                $draft['father_name'] = $fn;
                $work = str_replace($fm[0], ' ', $work);
            }
        }
        if (preg_match('/(?:mother(?:\'?s)?\s*(?:name)?|माता(?:\s*का\s*नाम)?|ମାତା(?:\s*ଙ୍କ\s*ନାମ)?|ମା(?:\s*ଙ୍କ\s*ନାମ)?)\s*[:=\-]?\s*(?:is\s+)?([^,\n;|]+)(?=,|;|\n|\||$)/iu', $work, $mm2)) {
            $mn = trim($mm2[1]);
            if ($mn !== '' && mb_strlen($mn) >= 2 && !preg_match('/^\d+$/', $mn) && !preg_match('/^\d{1,4}[-\/.]\d{1,2}[-\/.]\d{2,4}$/', $mn) && !preg_match('/^(male|female|other|m|f|o)$/i', $mn)) {
                $draft['mother_name'] = $mn;
                $work = str_replace($mm2[0], ' ', $work);
            }
        }

        // Unlabeled comma/semicolon chunks fill missing father_name -> mother_name -> distinguishing_marks (if in 3b)
        $cleanWork = trim((string) preg_replace('/\b(dob|date of birth|gender|religion|marital\s*status|my|is|am|here|it|mera|hai|mora)\b[:\s\-]*/iu', ' ', $work));
        $segs = preg_split('/\s*[,;\|\n]+\s*/', $cleanWork) ?: [];
        foreach ($segs as $seg) {
            $sClean = trim((string) preg_replace('/\s+/', ' ', $seg));
            if ($sClean === '' || mb_strlen($sClean) < 2 || preg_match('/^\d+$/', $sClean) || preg_match('/^\d{1,4}[-\/.]\d{1,2}[-\/.]\d{2,4}$/', $sClean) || preg_match('/^(male|female|other|m|f|o)$/i', $sClean)) {
                continue;
            }
            if (($draft['substep'] ?? '') === '3b') {
                if (($draft['distinguishing_marks'] ?? '') === '') {
                    $draft['distinguishing_marks'] = preg_match('/^(none|nil|no|na|n\/a|-)$/i', $sClean) ? 'None' : $sClean;
                }
                continue;
            }
            if (($draft['father_name'] ?? '') === '') {
                $draft['father_name'] = $sClean;
            } elseif (($draft['mother_name'] ?? '') === '') {
                $draft['mother_name'] = $sClean;
            }
        }

        return $draft;
    }
}

if (!function_exists('saarthi_format_saved_fields_line')) {
    /**
     * Format already-collected fields into a reassuring live tracker line for progressive chat turns.
     *
     * @param list<array{0: string, 1: string}> $savedPairs
     */
    function saarthi_format_saved_fields_line(string $lang, array $savedPairs): string
    {
        $items = [];
        foreach ($savedPairs as [$k, $v]) {
            $vTrim = trim((string) $v);
            if ($vTrim !== '') {
                $items[] = "**{$k}:** `{$vTrim}` ✓";
            }
        }
        if ($items === []) {
            return '';
        }
        $prefix = saarthi_localized_text($lang, 'Got it — saved so far:', 'अब तक सहेजा गया विवरण:', 'ଏପର୍ଯ୍ୟନ୍ତ ସେଭ୍ ହୋଇଥିବା ତଥ୍ୟ:');
        return $prefix . "\n" . implode(' · ', $items) . "\n\n";
    }
}

if (!function_exists('saarthi_step1_course_keyword')) {
    /**
     * Extract a pure course keyword for Step 1 without mistaking a campus chip ("Bhubaneswar") for a course name.
     *
     * @param array{keyword?: string, location?: string} $intent
     */
    function saarthi_step1_course_keyword(string $message, array $intent): string
    {
        if (!empty($intent['keyword'])) {
            return trim((string) $intent['keyword']);
        }
        $raw = trim((string) explode('—', $message)[0]);
        $cleaned = preg_replace(
            '/\b(bhubaneswar|bbsr|vss\s*nagar|ocac|campus\s*(?:1|i|2|ii)?|baleshwar|balasore|raipur|cuttack|centre|center|location|courses?|programs?|open|ongoing|available|show|list|only|in|at|for|please)\b|ଭୁବନେଶ୍ୱର|ବାଲେଶ୍ୱର|ରାୟପୁର|କଟକ|भुवनेश्वर|बालासोर|रायपुर|कटक/iu',
            ' ',
            $raw
        );
        $cleaned = trim((string) preg_replace('/[,;|:\-]+/', ' ', (string) $cleaned));
        $cleaned = trim((string) preg_replace('/\s+/', ' ', (string) $cleaned));
        return mb_strlen($cleaned) >= 2 ? $cleaned : '';
    }
}

if (!function_exists('saarthi_merge_step4_exam_input')) {
    /**
     * Incrementally parse & merge Step 4 examination details without overwriting exam_passed when entering Board/Stream next.
     *
     * @param array<string, mixed> $reg
     * @return array<string, mixed>
     */
    function saarthi_merge_step4_exam_input(array $reg, string $message): array
    {
        $parsedExams = saarthi_parse_exam_entries($message);
        if ($parsedExams === []) {
            return $reg;
        }
        if (empty($reg['exams'])) {
            $reg['exams'] = $parsedExams;
            return $reg;
        }

        // Handle multi-row semicolon entry appending/replacing cleanly
        if (count($parsedExams) > 1) {
            foreach ($parsedExams as $pe) {
                $reg['exams'][] = $pe;
            }
            return $reg;
        }

        $first = $parsedExams[0];
        $lastIndex = count($reg['exams']) - 1;
        $cur =& $reg['exams'][$lastIndex];

        // A new qualification starts a new row once the previous row is complete.
        // This lets users add exams one at a time without replacing earlier ones.
        $candidateLevel = trim((string) ($first['exam_passed'] ?? ''));
        $looksLikeExamLevel = (bool) preg_match(
            '/^(10th|12th|\+2|matric|ssc|hsc|diploma|iti|b\.?\s*tech|b\.?\s*e|bca|b\.?\s*sc|b\.?\s*com|b\.?\s*a|m\.?\s*tech|mca|m\.?\s*sc|graduation|post\s*graduation|graduate)\b/i',
            $candidateLevel
        );
        if (
            $looksLikeExamLevel
            && !empty($cur['exam_passed'])
            && !empty($cur['institute_name'])
            && !empty($cur['year_of_passing'])
            && !empty($cur['percentage'])
        ) {
            $reg['exams'][] = $first;
            return $reg;
        }

        if (!empty($first['year_of_passing'])) {
            $cur['year_of_passing'] = $first['year_of_passing'];
        }
        if (!empty($first['percentage'])) {
            $cur['percentage'] = $first['percentage'];
        }
        if (!empty($first['institute_name'])) {
            $cur['institute_name'] = $first['institute_name'];
        }
        if (!empty($first['stream'])) {
            $cur['stream'] = $first['stream'];
        }

        // Single text token handling when primary exam is already known: route to next missing slot (Board -> Stream)
        $cand = $candidateLevel;
        if ($cand !== '') {
            if (empty($cur['exam_passed']) || $looksLikeExamLevel) {
                $cur['exam_passed'] = $cand;
                $cur['exam_name'] = $cand;
            } elseif (empty($cur['institute_name'])) {
                $cur['institute_name'] = $cand;
            } elseif (empty($cur['stream'])) {
                $cur['stream'] = $cand;
            }
        }
        unset($cur);

        return $reg;
    }
}

if (!function_exists('saarthi_step4_is_done_command')) {
    function saarthi_step4_is_done_command(string $message): bool
    {
        return (bool) preg_match(
            '/^\s*(done|no\s*more|next|proceed|continue|step\s*5|finish|finalize|ok|that[\'\s]*s\s*all|that[\'\s]*s\s*it|only\s*(one|this)|just\s*(one|this)|ek\s*hi|bas|yahi\s*hai|ଏତିକି|ଯଥେଷ୍ଟ)(?:\s*\([^)]*\))?\s*$/iu',
            $message
        );
    }
}

if (!function_exists('saarthi_build_registration_summary_response')) {
    /**
     * Build the localized confirmation summary payload for an active Saarthi registration draft.
     *
     * @param array<string, mixed> $draft
     * @return array<string, mixed>
     */
    function saarthi_build_registration_summary_response(array &$draft, string $lang): array
    {
        if (($draft['apaar_id'] ?? '') === 'SKIPPED') {
            $draft['apaar_id'] = '';
        }
        if (($draft['aadhar'] ?? '') === 'SKIPPED') {
            $draft['aadhar'] = '';
        }
        if (empty($draft['category'])) {
            $draft['category'] = 'General';
        }
        if (empty($draft['pwd_status'])) {
            $draft['pwd_status'] = 'No';
        }
        if (empty($draft['religion'])) {
            $draft['religion'] = 'Hindu';
        }
        if (empty($draft['marital_status'])) {
            $draft['marital_status'] = 'Single';
        }
        if (empty($draft['distinguishing_marks'])) {
            $draft['distinguishing_marks'] = 'None';
        }

        $draft['awaiting_confirm'] = true;
        $draft['editing'] = false;
        $_SESSION['saarthi_reg'] = $draft;

        $summary = [
            'course_name' => $draft['course_name'] ?? '',
            'center_location' => $draft['center_location'] ?? '',
            'name' => $draft['name'] ?? '',
            'father_name' => $draft['father_name'] ?? '',
            'mother_name' => $draft['mother_name'] ?? '',
            'mobile' => $draft['mobile'] ?? '',
            'email' => $draft['email'] ?? '',
            'dob' => $draft['dob'] ?? '',
            'gender' => $draft['gender'] ?? '',
            'religion' => $draft['religion'] ?? '',
            'marital_status' => $draft['marital_status'] ?? '',
            'distinguishing_marks' => $draft['distinguishing_marks'] ?? 'None',
            'qualification' => $draft['qualification'] ?? '',
            'board_university' => $draft['board_university'] ?? '',
            'year_of_passing' => $draft['year_of_passing'] ?? '',
            'stream' => $draft['stream'] ?? '',
            'percentage' => $draft['percentage'] ?? '',
            'college_name' => $draft['college_name'] ?? '',
            'aadhar' => !empty($draft['aadhar']) ? $draft['aadhar'] : 'Pending',
            'apaar_id' => !empty($draft['apaar_id']) ? $draft['apaar_id'] : 'N/A',
            'category' => $draft['category'] ?? 'General',
            'pwd_status' => $draft['pwd_status'] ?? 'No',
            'address' => $draft['address'] ?? '',
        ];

        $reply = saarthi_localized_text(
            $lang,
            'Here is your **Registration Summary**. Please review your details and reply **"Confirm"** (or click the Confirm button below) to submit and generate your Admission PDF.',
            'यहाँ आपका **रजिस्ट्रेशन सारांश (Summary)** है। कृपया विवरण जांचें और एडमिशन PDF बनाने के लिए **"Confirm"** लिखें या नीचे Confirm बटन दबाएं।',
            'ଏଠାରେ ଆପଣଙ୍କର **ପଞ୍ଜୀକରଣ ସାରାଂଶ (Summary)** ଅଛି। ଦୟାକରି ତଥ୍ୟ ଯାଞ୍ଚ କରି ଆଡମିଶନ୍ PDF ପାଇଁ **"Confirm"** ଲେଖନ୍ତୁ କିମ୍ବା ତଳ ବଟନ୍ କ୍ଲିକ୍ କରନ୍ତୁ।'
        );

        return [
            'reply' => $reply,
            'mode' => 'local',
            'avatar_state' => 'happy',
            'lang' => $lang,
            'ui_action' => 'NONE',
            'quick_replies' => $lang === 'hi'
                ? ['Confirm', 'Edit details', 'रद्द करें']
                : ($lang === 'or' ? ['Confirm', 'Edit details', 'ବାତିଲ୍ କରନ୍ତୁ'] : ['Confirm', 'Edit details', 'Cancel']),
            'cards' => ['registration_summary' => $summary],
        ];
    }
}

if (!function_exists('saarthi_handle_registration_flow')) {
    /**
     * Stateful 5-step conversational registration state machine (supports EN/HI/OR + full Admission Form fields).
     *
     * @return array<string, mixed>|null
     */
    function saarthi_handle_registration_flow(string $message, string $lang, array $ctx, ?mysqli $conn): ?array
    {
        if (!$conn instanceof mysqli) {
            return null;
        }
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $reg = $_SESSION['saarthi_reg'] ?? null;
        $mLower = saarthi_lower(trim($message));

        // Allow user to abort, pause, or switch language cleanly at any step without mutating fields
        if (is_array($reg) && !empty($reg['active'])) {
            if (preg_match('/^(cancel|stop|exit|quit|abort|reset|nahi|radd)\b|रद्द|ବାତିଲ୍|ବନ୍ଦ/iu', $mLower)) {
                unset($_SESSION['saarthi_reg']);
                $reply = saarthi_localized_text(
                    $lang,
                    'No problem — I cancelled the registration draft. Let me know whenever you want to explore courses or start again.',
                    'कोई बात नहीं — मैंने रजिस्ट्रेशन ड्राफ्ट रद्द कर दिया है। जब भी आप कोर्स देखना या दोबारा आवेदन करना चाहें, बताएं।',
                    'କିଛି ଅସୁବିଧା ନାହିଁ — ମୁଁ ପଞ୍ଜୀକରଣ ଡ୍ରାଫ୍ଟ ବାତିଲ୍ କରିଛି। ଆପଣ ଯେକୌଣସି ସମୟରେ କୋର୍ସ ଦେଖିପାରିବେ କିମ୍ବା ପୁଣି ଆରମ୍ଭ କରିପାରିବେ।'
                );
                return [
                    'reply' => $reply,
                    'mode' => 'local',
                    'avatar_state' => 'happy',
                    'lang' => $lang,
                    'ui_action' => 'NONE',
                    'quick_replies' => saarthi_default_quick_replies($lang),
                    'cards' => [],
                ];
            }
            // If user explicitly switched topic while in draft (e.g. asked roadmap or mock test), pause cleanly
            if (preg_match('/\b(roadmap|how does this site work|mock\s*test|student\s*login)\b/iu', $mLower)) {
                unset($_SESSION['saarthi_reg']);
                return null;
            }
            // Pure language switch command during active registration: keep fields intact & repeat current prompt
            if (preg_match('/^(odia\s*(re\s*kuha|kuha|re)?|hindi\s*(mein|me)\s*(batao|bolo)?|switch\s*to\s*english|english\s*please|ଓଡ଼ିଆ|हिन्दी|हिंदी)[\s!.?]*$/iu', $mLower)) {
                $curStep = (int) ($reg['step'] ?? 1);
                $sub = (string) ($reg['substep'] ?? '');
                if (!empty($reg['awaiting_confirm'])) {
                    $promptTxt = saarthi_localized_text(
                        $lang,
                        'Switched to **English**! Review your summary above and reply **"Confirm"** (or click the Confirm button) to submit.',
                        'भाषा **हिन्दी** में बदल दी गई है! ऊपर अपना सारांश जांचें और सबमिट करने के लिए **"Confirm"** लिखें।',
                        '**ଓଡ଼ିଆ** କୁ ପରିବର୍ତ୍ତନ ହୋଇଛି! ଉପରେ ଆପଣଙ୍କ ତଥ୍ୟ ଯାଞ୍ଚ କରି **"Confirm"** ଲେଖନ୍ତୁ।'
                    );
                } elseif ($curStep === 2) {
                    $promptTxt = saarthi_localized_text(
                        $lang,
                        "Switched to **English**! Continuing **Step 2 of 5:** Please share your **Full Name**, **10-digit Mobile Number**, and **Email Address**.",
                        "भाषा **हिन्दी** में बदल दी गई है! **चरण 2/5:** कृपया अपना **पूरा नाम**, **10-अंकों का मोबाइल नंबर**, और **ईमेल पता** लिखें।",
                        "**ଓଡ଼ିଆ** କୁ ପରିବର୍ତ୍ତନ ହୋଇଛି! **ସୋପାନ ୨/୫:** ଦୟାକରି ଆପଣଙ୍କର **ସମ୍ପୂର୍ଣ୍ଣ ନାମ**, **୧୦-ଅଙ୍କ ମୋବାଇଲ୍ ନମ୍ବର**, ଏବଂ **ଇମେଲ୍** ଦିଅନ୍ତୁ।"
                    );
                } elseif ($curStep === 3 && $sub === '3b') {
                    $promptTxt = saarthi_localized_text(
                        $lang,
                        "Switched to **English**! Continuing **Step 3b:** Please share your **Religion** (Hindu / Muslim / Christian / Other), **Marital Status** (Single / Married), and **Birth Mark** (or \"None\").",
                        "भाषा **हिन्दी** में बदल दी गई है! **चरण 3b:** कृपया अपना **धर्म** (Hindu/Muslim/Christian/Other), **वैवाहिक स्थिति** (Single/Married), और **पहचान चिह्न** (या \"None\") लिखें।",
                        "**ଓଡ଼ିଆ** କୁ ପରିବର୍ତ୍ତନ ହୋଇଛି! **ସୋପାନ ୩b:** ଦୟାକରି ଆପଣଙ୍କ **ଧର୍ମ** (Hindu/Muslim/Christian/Other), **ବୈବାହିକ ସ୍ଥିତି** (Single/Married), ଏବଂ **ଜନ୍ମ ଚିହ୍ନ** (କିମ୍ବା \"None\") ଲେଖନ୍ତୁ।"
                    );
                } elseif ($curStep === 3) {
                    $promptTxt = saarthi_localized_text(
                        $lang,
                        "Switched to **English**! Continuing **Step 3 of 5:** Please share your **Date of Birth** (DD-MM-YYYY), **Gender**, **Father's Name**, and **Mother's Name**.",
                        "भाषा **हिन्दी** में बदल दी गई है! **चरण 3/5:** कृपया **जन्म तिथि** (DD-MM-YYYY), **लिंग**, **पिता का नाम**, और **माता का नाम** लिखें।",
                        "**ଓଡ଼ିଆ** କୁ ପରିବର୍ତ୍ତନ ହୋଇଛି! **ସୋପାନ ୩/୫:** ଦୟାକରି **ଜନ୍ମ ତାରିଖ** (DD-MM-YYYY), **ଲିଙ୍ଗ**, **ପିତାଙ୍କ ନାମ**, ଏବଂ **ମାତାଙ୍କ ନାମ** ଦିଅନ୍ତୁ।"
                    );
                } elseif ($curStep === 4) {
                    $promptTxt = saarthi_localized_text(
                        $lang,
                        "Switched to **English**! Continuing **Step 4 of 5 (Examination Details):** Please share **Exam Passed**, **Board / University**, **Passing Year**, **Stream**, and **% / CGPA**.",
                        "भाषा **हिन्दी** में बदल दी गई है! **चरण 4/5 (शैक्षणिक विवरण):** कृपया **परीक्षा**, **बोर्ड / विश्वविद्यालय**, **उत्तीर्ण वर्ष**, **संकाय (Stream)**, और **% / CGPA** लिखें।",
                        "**ଓଡ଼ିଆ** କୁ ପରିବର୍ତ୍ତନ ହୋଇଛି! **ସୋପାନ ୪/୫ (ଶିକ୍ଷାଗତ ତଥ୍ୟ):** ଦୟାକରି **ପରୀକ୍ଷା**, **ବୋର୍ଡ / ବିଶ୍ୱବିଦ୍ୟାଳୟ**, **ପାସ୍ ବର୍ଷ**, **ଷ୍ଟ୍ରିମ୍**, ଏବଂ **% / CGPA** ଦିଅନ୍ତୁ।"
                    );
                } elseif ($curStep === 5) {
                    $promptTxt = saarthi_localized_text(
                        $lang,
                        "Switched to **English**! Continuing **Step 5 of 5:** Please share your **Category / PwD**, optional **Aadhar / APAAR**, and **Full Correspondence Address**.",
                        "भाषा **हिन्दी** में बदल दी गई है! **चरण 5/5:** कृपया **श्रेणी / PwD**, वैकल्पिक **आधार / APAAR**, और **पूरा पता** लिखें।",
                        "**ଓଡ଼ିଆ** କୁ ପରିବର୍ତ୍ତନ ହୋଇଛି! **ସୋପାନ ୫/୫:** ଦୟାକରି **ବର୍ଗ / PwD**, ଇଚ୍ଛାଧୀନ **ଆଧାର / APAAR**, ଏବଂ **ସମ୍ପୂର୍ଣ୍ଣ ଠିକଣା** ଦିଅନ୍ତୁ।"
                    );
                } else {
                    $promptTxt = saarthi_localized_text(
                        $lang,
                        "Switched to **English**! Continuing **Step 1 of 5:** Which **Course** and **Campus Location** would you like to join?",
                        "भाषा **हिन्दी** में बदल दी गई है! **चरण 1/5:** आप किस **कोर्स** और **कैंपस लोकेशन** में प्रवेश लेना चाहते हैं?",
                        "**ଓଡ଼ିଆ** କୁ ପରିବର୍ତ୍ତନ ହୋଇଛି! **ସୋପାନ ୧/୫:** ଆପଣ କେଉଁ **କୋର୍ସ** ଏବଂ **କ୍ୟାମ୍ପସ୍** ରେ ପଞ୍ଜୀକରଣ କରିବାକୁ ଚାହୁଁଛନ୍ତି?"
                    );
                }
                return [
                    'reply' => $promptTxt,
                    'mode' => 'local',
                    'avatar_state' => 'wave',
                    'lang' => $lang,
                    'ui_action' => 'NONE',
                    'quick_replies' => [$lang === 'hi' ? 'रद्द करें' : ($lang === 'or' ? 'ବାତିଲ୍ କରନ୍ତୁ' : 'Cancel')],
                    'cards' => [],
                ];
            }
        }

        // Check if user wants to initiate registration
        $intent = saarthi_registration_intent($message);
        $prefilledCourseId = $intent['prefilled_course_id'];
        $message = $intent['clean_message'];
        $mLower = saarthi_lower($message);
        $startsReg = $intent['starts'];

        if ((!is_array($reg) || empty($reg['active'])) && $startsReg) {
            $reg = [
                'active' => true,
                'step' => 1,
                'substep' => '',
                'course_id' => 0,
                'course_name' => '',
                'center_location' => '',
                'name' => '',
                'mobile' => '',
                'email' => '',
                'dob' => '',
                'gender' => '',
                'father_name' => '',
                'mother_name' => '',
                'religion' => '',
                'marital_status' => '',
                'distinguishing_marks' => '',
                'qualification' => '',
                'board_university' => '',
                'year_of_passing' => '',
                'stream' => '',
                'percentage' => '',
                'college_name' => '',
                'exams' => [],
                'aadhar' => '',
                'apaar_id' => '',
                'category' => '',
                'pwd_status' => '',
                'address' => '',
                'awaiting_confirm' => false,
                'editing' => false,
            ];
            if ($prefilledCourseId > 0) {
                $cStmt = $conn->prepare('SELECT id, course_name, training_center FROM courses WHERE id = ? LIMIT 1');
                if ($cStmt) {
                    $cStmt->bind_param('i', $prefilledCourseId);
                    $cStmt->execute();
                    if ($cRow = $cStmt->get_result()->fetch_assoc()) {
                        $reg['course_id'] = (int) $cRow['id'];
                        $reg['course_name'] = (string) $cRow['course_name'];
                        $reg['center_location'] = trim((string) ($cRow['training_center'] ?? '')) ?: 'NIELIT Bhubaneswar';
                        $reg['step'] = 2;
                    }
                    $cStmt->close();
                }
            } else {
                // Check if course name/location were mentioned right in the opening sentence
                $intent = saarthi_parse_course_search_intent($message, $conn);
                if ($intent['location'] !== '') {
                    $reg['center_location'] = $intent['location'];
                }
                if ($intent['keyword'] !== '') {
                    $found = saarthi_search_courses($conn, $intent['keyword'], $intent['location'], 'ongoing');
                    if (!empty($found['exact_match']) && !empty($found['courses'][0])) {
                        $c = $found['courses'][0];
                        $reg['course_id'] = (int) $c['id'];
                        $reg['course_name'] = (string) $c['course_name'];
                        if ($reg['center_location'] === '') {
                            $reg['center_location'] = (string) ($c['campus'] ?? 'NIELIT Bhubaneswar');
                        }
                        $reg['step'] = 2;
                    }
                }
            }
            $_SESSION['saarthi_reg'] = $reg;

            if ((int) $reg['step'] === 2) {
                $reply = saarthi_localized_text(
                    $lang,
                    "Great choice! Let's register you for **{$reg['course_name']}** ({$reg['center_location']}).\n\n**Step 2 of 5:** Please share your **Full Name**, **10-digit Mobile Number**, and **Email Address**.",
                    "बहुत बढ़िया! आइए **{$reg['course_name']}** ({$reg['center_location']}) के लिए आपका रजिस्ट्रेशन पूरा करें।\n\n**चरण 2/5:** कृपया अपना **पूरा नाम**, **10-अंकों का मोबाइल नंबर**, और **ईमेल पता** लिखें।",
                    "ବହୁତ ବଢ଼ିଆ! ଆସନ୍ତୁ **{$reg['course_name']}** ({$reg['center_location']}) ପାଇଁ ଆପଣଙ୍କ ପଞ୍ଜୀକରଣ ଆରମ୍ଭ କରିବା।\n\n**ସୋପାନ ୨/୫:** ଦୟାକରି ଆପଣଙ୍କର **ସମ୍ପୂର୍ଣ୍ଣ ନାମ**, **୧୦-ଅଙ୍କ ବିଶିଷ୍ଟ ମୋବାଇଲ୍ ନମ୍ବର**, ଏବଂ **ଇମେଲ୍ ଠିକଣା** ଦିଅନ୍ତୁ।"
                );
                return [
                    'reply' => $reply,
                    'mode' => 'local',
                    'avatar_state' => 'guide',
                    'lang' => $lang,
                    'ui_action' => 'OPEN_REGISTRATION',
                    'quick_replies' => $lang === 'hi' ? ['रद्द करें'] : ($lang === 'or' ? ['ବାତିଲ୍ କରନ୍ତୁ'] : ['Cancel']),
                    'cards' => [],
                ];
            }

            // Step 1 prompt: show top ongoing courses inside clean cards & compact campus chips below
            $catalog = saarthi_search_courses($conn, '', $reg['center_location'], 'ongoing');
            $step1Chips = $lang === 'hi'
                ? ['Bhubaneswar', 'Baleshwar', 'Raipur', 'रद्द करें']
                : ($lang === 'or' ? ['Bhubaneswar', 'Baleshwar', 'Raipur', 'ବାତିଲ୍ କରନ୍ତୁ'] : ['Bhubaneswar', 'Baleshwar', 'Raipur', 'Cancel']);
            $reply = saarthi_localized_text(
                $lang,
                "I'd love to help you register! Let's do this in 5 quick steps.\n\n**Step 1 of 5:** Tap **Register via Vani** on any open course card below, tap a campus pill to filter, or type your **Course Name** and **Campus Location**.",
                "मैं आपका रजिस्ट्रेशन करने के लिए तैयार हूँ! यह प्रक्रिया 5 आसान चरणों में होगी।\n\n**चरण 1/5:** नीचे किसी भी कोर्स कार्ड पर **Register via Vani** दबाएं, कैंपस चुनें, या अपना **कोर्स** और **कैंपस** लिखें।",
                "ମୁଁ ଆପଣଙ୍କର ପଞ୍ଜୀକରଣରେ ସାହାଯ୍ୟ କରିବାକୁ ପ୍ରସ୍ତୁତ! ଏହା ୫ଟି ସହଜ ସୋପାନରେ ହେବ।\n\n**ସୋପାନ ୧/୫:** ତଳେ ଥିବା ଯେକୌଣସି କୋର୍ସ କାର୍ଡରେ **Register via Vani** କ୍ଲିକ୍ କରନ୍ତୁ, କ୍ୟାମ୍ପସ୍ ବାଛନ୍ତୁ, କିମ୍ବା **କୋର୍ସ ଓ କ୍ୟାମ୍ପସ୍** ଲେଖନ୍ତୁ।"
            );
            return [
                'reply' => $reply,
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'OPEN_REGISTRATION',
                'quick_replies' => $step1Chips,
                'cards' => !empty($catalog['courses']) ? ['courses' => array_slice($catalog['courses'], 0, 4)] : [],
            ];
        }

        if (!is_array($reg) || empty($reg['active'])) {
            return null;
        }

        // Confirmation stage!
        if (!empty($reg['awaiting_confirm'])) {
            if (preg_match('/^(confirm|yes|submit|ok|proceed|done|haan|ha|confirm\s+registration)\b|हाँ|हा|निश्चित|ନିଶ୍ଚିତ|ହଁ/iu', $mLower)) {
                $saved = saarthi_register_student($conn, $reg);
                unset($_SESSION['saarthi_reg']);
                if (empty($saved['ok'])) {
                    $errTxt = (string) ($saved['message'] ?? 'Could not save registration.');
                    return [
                        'reply' => saarthi_localized_text(
                            $lang,
                            "Sorry, I couldn't save that yet: {$errTxt}",
                            "क्षमा करें, यह सहेजा नहीं जा सका: {$errTxt}",
                            "କ୍ଷମା କରିବେ, ଏହା ସେଭ୍ ହୋଇପାରିଲା ନାହିଁ: {$errTxt}"
                        ),
                        'mode' => 'local',
                        'avatar_state' => 'thinking',
                        'lang' => $lang,
                        'ui_action' => 'NONE',
                        'quick_replies' => saarthi_default_quick_replies($lang),
                        'cards' => [],
                    ];
                }

                $sid = (string) ($saved['student_id'] ?? '');
                $cName = (string) ($saved['course_name'] ?? $reg['course_name']);
                $recEmail = (string) ($saved['email'] ?? $reg['email'] ?? '');
                $hasPdf = !empty($saved['download_pdf_url']);
                $emailNoteEn = !empty($saved['is_returning'])
                    ? "Confirmation email queued to **{$recEmail}** (use your existing Student Portal password to log in)."
                    : "Confirmation email with your Student ID & login credentials is being sent to **{$recEmail}** (check Inbox/Spam).";
                $emailNoteHi = !empty($saved['is_returning'])
                    ? "पुष्टिकरण ईमेल **{$recEmail}** पर भेजा गया है (लॉगिन के लिए अपना मौजूदा पासवर्ड उपयोग करें)।"
                    : "आपके Student ID और लॉगिन पासवर्ड के साथ पुष्टिकरण ईमेल **{$recEmail}** पर भेजा जा रहा है (Inbox/Spam जांचें)।";
                $emailNoteOr = !empty($saved['is_returning'])
                    ? "ନିଶ୍ଚିତକରଣ ଇମେଲ୍ **{$recEmail}** କୁ ପଠାଯାଇଛି (ଆପଣଙ୍କ ପୂର୍ବ ପାସ୍‌ୱାର୍ଡ୍ ବ୍ୟବହାର କରି ଲଗଇନ୍ କରନ୍ତୁ)।"
                    : "ଆପଣଙ୍କ Student ID ଓ ଲଗଇନ୍ ତଥ୍ୟ ସହିତ ଇମେଲ୍ **{$recEmail}** କୁ ପଠାଯାଉଛି (Inbox/Spam ଯାଞ୍ଚ କରନ୍ତୁ)।";
                $reply = !empty($saved['already_registered'])
                    ? (string) ($saved['message'] ?? '')
                    : saarthi_localized_text(
                        $lang,
                        "Congratulations! Your registration for **{$cName}** is submitted.\n**Student / Registration ID:** `{$sid}`\n{$emailNoteEn}\n\nStatus: Pending document verification. You can **upload your Photo, Signature, Aadhar & Certificates right below** and download your Admission Form PDF!",
                        "बधाई हो! **{$cName}** के लिए आपका रजिस्ट्रेशन सफल रहा।\n**Student / Registration ID:** `{$sid}`\n{$emailNoteHi}\n\nस्थिति: दस्तावेज़ सत्यापन शेष है। आप नीचे ही अपनी **फोटो, हस्ताक्षर, आधार और प्रमाणपत्र अपलोड** कर सकते हैं और एडमिशन फॉर्म PDF डाउनलोड कर सकते हैं!",
                        "ଅଭିନନ୍ଦନ! **{$cName}** ପାଇଁ ଆପଣଙ୍କର ପଞ୍ଜୀକରଣ ସଫଳ ହୋଇଛି।\n**Student / Registration ID:** `{$sid}`\n{$emailNoteOr}\n\nସ୍ଥିତି: ଡକ୍ୟୁମେଣ୍ଟ୍ ଯାଞ୍ଚ ବାକି ଅଛି। ଆପଣ ତଳେ ସିଧାସଳଖ **ଫଟୋ, ସ୍ୱାକ୍ଷର, ଆଧାର ଓ ସାର୍ଟିଫିକେଟ୍ ଅପଲୋଡ୍** କରିପାରିବେ ଏବଂ ଆଡମିଶନ୍ PDF ଡାଉନଲୋଡ୍ କରିପାରିବେ!"
                    );

                return [
                    'reply' => $reply,
                    'mode' => 'local',
                    'avatar_state' => 'celebrate',
                    'lang' => $lang,
                    'ui_action' => $hasPdf ? 'DOWNLOAD_PDF' : 'NONE',
                    'quick_replies' => $lang === 'hi'
                        ? ['कोर्स देखें', 'स्टूडेंट लॉगिन', 'पोर्टल रोडमैप']
                        : ($lang === 'or' ? ['କୋର୍ସ ଦେଖନ୍ତୁ', 'ଷ୍ଟୁଡେଣ୍ଟ ଲଗଇନ୍', 'ରୋଡ୍‌ମ୍ୟାପ୍'] : ['Browse courses', 'Student login', 'Site roadmap']),
                    'cards' => ['registration_receipt' => $saved],
                ];
            }
            if (preg_match('/\b(edit|change|restart)\b|बदल|ସମ୍ପାଦନ/iu', $mLower)) {
                $reg['step'] = 2;
                $reg['awaiting_confirm'] = false;
                $reg['editing'] = true;
                $_SESSION['saarthi_reg'] = $reg;
                return [
                    'reply' => saarthi_localized_text(
                        $lang,
                        'Sure! Let’s update your details. Please enter your **Full Name**, **Mobile Number**, and **Email Address**.',
                        'बिल्कुल! कृपया अपना सही **पूरा नाम**, **मोबाइल नंबर**, और **ईमेल** लिखें।',
                        'ନିଶ୍ଚୟ! ଦୟାକରି ଆପଣଙ୍କର ସଠିକ୍ **ସମ୍ପୂର୍ଣ୍ଣ ନାମ**, **ମୋବାଇଲ୍ ନମ୍ବର**, ଏବଂ **ଇମେଲ୍** ଦିଅନ୍ତୁ।'
                    ),
                    'mode' => 'local',
                    'avatar_state' => 'guide',
                    'lang' => $lang,
                    'ui_action' => 'OPEN_REGISTRATION',
                    'quick_replies' => ['Cancel'],
                    'cards' => [],
                ];
            }
        }

        // Step 1: Collect Course + Center Location
        if ((int) $reg['step'] === 1) {
            $intent = saarthi_parse_course_search_intent($message, $conn);
            if ($intent['location'] !== '') {
                $reg['center_location'] = $intent['location'];
            }
            $kw = saarthi_step1_course_keyword($message, $intent);
            $search = saarthi_search_courses($conn, $kw, $reg['center_location'], 'ongoing');
            $matchedCourses = $search['courses'] ?? [];
            $canAutoPick = false;
            if ($kw !== '' && !empty($search['exact_match']) && !empty($matchedCourses[0])) {
                $firstCourseName = saarthi_lower((string) ($matchedCourses[0]['course_name'] ?? ''));
                $firstCourseCode = saarthi_lower((string) ($matchedCourses[0]['course_code'] ?? ''));
                $kwLower = saarthi_lower($kw);
                $canAutoPick = count($matchedCourses) === 1
                    || strpos($firstCourseName, $kwLower) !== false
                    || ($firstCourseCode !== '' && $firstCourseCode === $kwLower);
            }
            if ($canAutoPick) {
                $picked = $matchedCourses[0];
                $reg['course_id'] = (int) $picked['id'];
                $reg['course_name'] = (string) $picked['course_name'];
                if ($reg['center_location'] === '') {
                    $reg['center_location'] = (string) ($picked['campus'] ?? 'NIELIT Bhubaneswar');
                }
                $reg['step'] = 2;
                $_SESSION['saarthi_reg'] = $reg;
                $reply = saarthi_localized_text(
                    $lang,
                    "Selected **{$reg['course_name']}** at **{$reg['center_location']}**.\n\n**Step 2 of 5:** Please share your **Full Name**, **10-digit Mobile Number**, and **Email Address**.",
                    "चयनित कोर्स: **{$reg['course_name']}** (**{$reg['center_location']}**).\n\n**चरण 2/5:** कृपया अपना **पूरा नाम**, **10-अंकों का मोबाइल नंबर**, और **ईमेल पता** बताएं।",
                    "ଚୟନିତ କୋର୍ସ: **{$reg['course_name']}** (**{$reg['center_location']}**).\n\n**ସୋପାନ ୨/୫:** ଦୟାକରି ଆପଣଙ୍କର **ସମ୍ପୂର୍ଣ୍ଣ ନାମ**, **୧୦-ଅଙ୍କ ମୋବାଇଲ୍ ନମ୍ବର**, ଏବଂ **ଇମେଲ୍ ଠିକଣା** ଦିଅନ୍ତୁ।"
                );
                return [
                    'reply' => $reply,
                    'mode' => 'local',
                    'avatar_state' => 'happy',
                    'lang' => $lang,
                    'ui_action' => 'OPEN_REGISTRATION',
                    'quick_replies' => [$lang === 'hi' ? 'रद्द करें' : ($lang === 'or' ? 'ବାତିଲ୍ କରନ୍ତୁ' : 'Cancel')],
                    'cards' => [],
                ];
            }

            $_SESSION['saarthi_reg'] = $reg;
            $step1Chips = $lang === 'hi'
                ? ['Bhubaneswar', 'Baleshwar', 'Raipur', 'रद्द करें']
                : ($lang === 'or' ? ['Bhubaneswar', 'Baleshwar', 'Raipur', 'ବାତିଲ୍ କରନ୍ତୁ'] : ['Bhubaneswar', 'Baleshwar', 'Raipur', 'Cancel']);
            $locNote = $reg['center_location'] !== '' ? " in **{$reg['center_location']}**" : '';
            $reply = saarthi_localized_text(
                $lang,
                "Here are the open courses{$locNote}. Tap **Register via Vani** on the course you want, or type its exact name:",
                "यहाँ उपलब्ध कोर्स{$locNote} दिए गए हैं। अपनी पसंद के कोर्स कार्ड पर **Register via Vani** दबाएं या उसका नाम लिखें:",
                "ଏଠାରେ ଉପଲବ୍ଧ କୋର୍ସ{$locNote} ଦିଆଯାଇଛି। ଆପଣ ଚାହୁଁଥିବା କୋର୍ସ କାର୍ଡରେ **Register via Vani** କ୍ଲିକ୍ କରନ୍ତୁ କିମ୍ବା ନାମ ଲେଖନ୍ତୁ:"
            );
            return [
                'reply' => $reply,
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'OPEN_REGISTRATION',
                'quick_replies' => $step1Chips,
                'cards' => !empty($matchedCourses) ? ['courses' => array_slice($matchedCourses, 0, 5)] : [],
            ];
        }

        // Step 2: Full Name, Mobile Number, Email Address
        if ((int) $reg['step'] === 2) {
            $hadNameLabel = (bool) preg_match('/\b(my\s+name\s+is|name\s*[:=]|mera\s+naam)\b/i', $message);
            if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $message, $em)) {
                $reg['email'] = trim($em[0]);
                $message = str_replace($em[0], ' ', $message);
            }
            if (preg_match('/(?:\+91[\s\-]?)?([6-9]\d{9})\b/', $message, $mm)) {
                $reg['mobile'] = $mm[1];
                $message = str_replace($mm[0], ' ', $message);
            } elseif (preg_match('/\b(\d{10})\b/', $message, $mm)) {
                $reg['mobile'] = $mm[1];
                $message = str_replace($mm[0], ' ', $message);
            }
            $nameCand = trim((string) preg_replace('/\b(my name is|name|mobile|phone|email|number|no|mera naam|my|is|am|here|it|hai)\b[:\s\-]*/i', ' ', $message));
            $nameCand = trim((string) preg_replace('/[,;|\-]+/', ' ', $nameCand));
            $nameCand = trim((string) preg_replace('/\s+/', ' ', $nameCand));
            if ($nameCand !== '' && mb_strlen($nameCand) >= 2 && !preg_match('/^\d+$/', $nameCand)) {
                if ($reg['name'] === '' || $hadNameLabel || !preg_match('/\b(my|here|this)\b/i', $message)) {
                    $reg['name'] = $nameCand;
                }
            }

            $missing = [];
            if ($reg['name'] === '') {
                $missing[] = saarthi_localized_text($lang, 'Full Name', 'पूरा नाम', 'ସମ୍ପୂର୍ଣ୍ଣ ନାମ');
            }
            if ($reg['mobile'] === '') {
                $missing[] = saarthi_localized_text($lang, '10-digit Mobile', '10-अंकों का मोबाइल', '୧୦-ଅଙ୍କ ମୋବାଇଲ୍');
            }
            if ($reg['email'] === '') {
                $missing[] = saarthi_localized_text($lang, 'Email Address', 'ईमेल पता', 'ଇମେଲ୍');
            }

            if ($missing !== []) {
                $_SESSION['saarthi_reg'] = $reg;
                $savedLine = saarthi_format_saved_fields_line($lang, [
                    [saarthi_localized_text($lang, 'Name', 'नाम', 'ନାମ'), (string) $reg['name']],
                    [saarthi_localized_text($lang, 'Mobile', 'मोबाइल', 'ମୋବାଇଲ୍'), (string) $reg['mobile']],
                    [saarthi_localized_text($lang, 'Email', 'ईमेल', 'ଇମେଲ୍'), (string) $reg['email']],
                ]);
                $needStr = implode(', ', $missing);
                return [
                    'reply' => $savedLine . saarthi_localized_text(
                        $lang,
                        "Please also enter: **{$needStr}**.",
                        "कृपया यह भी बताएं: **{$needStr}**।",
                        "ଦୟାକରି ଏହା ମଧ୍ୟ ଦିଅନ୍ତୁ: **{$needStr}**।"
                    ),
                    'mode' => 'local',
                    'avatar_state' => 'guide',
                    'lang' => $lang,
                    'ui_action' => 'NONE',
                    'quick_replies' => [$lang === 'hi' ? 'रद्द करें' : ($lang === 'or' ? 'ବାତିଲ୍ କରନ୍ତୁ' : 'Cancel')],
                    'cards' => [],
                ];
            }

            if (!empty($reg['editing'])
                && $reg['dob'] !== '' && $reg['gender'] !== ''
                && $reg['father_name'] !== '' && $reg['mother_name'] !== ''
                && $reg['qualification'] !== '' && $reg['year_of_passing'] !== ''
                && $reg['address'] !== ''
            ) {
                return saarthi_build_registration_summary_response($reg, $lang);
            }

            $reg['step'] = 3;
            $reg['substep'] = '3a';
            $_SESSION['saarthi_reg'] = $reg;
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "Thanks, **{$reg['name']}**!\n\n**Step 3 of 5 (Personal & Family Details):** Please enter:\n• **Date of Birth** (DD-MM-YYYY) & **Gender** (Male/Female/Other)\n• **Father's Name** & **Mother's Name**\n*Example: `28-01-2001, Male, Father: Ramesh Sahu, Mother: Sunita Sahu`*",
                    "धन्यवाद, **{$reg['name']}**!\n\n**चरण 3/5 (व्यक्तिगत एवं पारिवारिक विवरण):** कृपया लिखें:\n• **जन्म तिथि** (DD-MM-YYYY) और **लिंग** (Male/Female/Other)\n• **पिता का नाम** और **माता का नाम**\n*उदाहरण: `28-01-2001, Male, Father: Ramesh Sahu, Mother: Sunita Sahu`*",
                    "ଧନ୍ୟବାଦ, **{$reg['name']}**!\n\n**ସୋପାନ ୩/୫ (ବ୍ୟକ୍ତିଗତ ଓ ପାରିବାରିକ ତଥ୍ୟ):** ଦୟାକରି ଦିଅନ୍ତୁ:\n• **ଜନ୍ମ ତାରିଖ** (DD-MM-YYYY) ଏବଂ **ଲିଙ୍ଗ** (Male/Female/Other)\n• **ପିତାଙ୍କ ନାମ** ଏବଂ **ମାତାଙ୍କ ନାମ**\n*ଉଦାହରଣ: `28-01-2001, Male, Father: Ramesh Sahu, Mother: Sunita Sahu`*"
                ),
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => [$lang === 'hi' ? 'रद्द करें' : ($lang === 'or' ? 'ବାତିଲ୍ କରନ୍ତୁ' : 'Cancel')],
                'cards' => [],
            ];
        }

        // Step 3: Personal & Family Profile (3a: DOB, Gender, Father's Name, Mother's Name -> 3b: Religion, Marital Status, Birth Mark)
        if ((int) $reg['step'] === 3) {
            $reg = saarthi_parse_step3_personal_input($message, $reg);
            $sub = (string) ($reg['substep'] ?? '3a');

            if ($sub !== '3b') {
                $missing3a = [];
                if (empty($reg['dob'])) {
                    $missing3a[] = saarthi_localized_text($lang, 'Date of Birth (DD-MM-YYYY)', 'जन्म तिथि (DD-MM-YYYY)', 'ଜନ୍ମ ତାରିଖ (DD-MM-YYYY)');
                }
                if (empty($reg['gender'])) {
                    $missing3a[] = saarthi_localized_text($lang, 'Gender (Male/Female/Other)', 'लिंग (Male/Female/Other)', 'ଲିଙ୍ଗ (Male/Female/Other)');
                }
                if (empty($reg['father_name'])) {
                    $missing3a[] = saarthi_localized_text($lang, "Father's Name", 'पिता का नाम', 'ପିତାଙ୍କ ନାମ');
                }
                if (empty($reg['mother_name'])) {
                    $missing3a[] = saarthi_localized_text($lang, "Mother's Name", 'माता का नाम', 'ମାତାଙ୍କ ନାମ');
                }
                if ($missing3a !== []) {
                    $_SESSION['saarthi_reg'] = $reg;
                    $saved3a = saarthi_format_saved_fields_line($lang, [
                        [saarthi_localized_text($lang, 'DOB', 'जन्म तिथि', 'ଜନ୍ମ ତାରିଖ'), (string) ($reg['dob'] ?? '')],
                        [saarthi_localized_text($lang, 'Gender', 'लिंग', 'ଲିଙ୍ଗ'), (string) ($reg['gender'] ?? '')],
                        [saarthi_localized_text($lang, "Father", 'पिता', 'ପିତା'), (string) ($reg['father_name'] ?? '')],
                        [saarthi_localized_text($lang, "Mother", 'माता', 'ମାତା'), (string) ($reg['mother_name'] ?? '')],
                    ]);
                    $needStr = implode(', ', $missing3a);
                    return [
                        'reply' => $saved3a . saarthi_localized_text(
                            $lang,
                            "Please also share: **{$needStr}**.\n*Tip: `Father: Ramesh Sahu, Mother: Sunita Sahu` works great.*",
                            "कृपया यह भी बताएं: **{$needStr}**।\n*सुझाव: `Father: Ramesh Sahu, Mother: Sunita Sahu` जैसा लिख सकते हैं।*",
                            "ଦୟାକରି ଏହା ମଧ୍ୟ ଦିଅନ୍ତୁ: **{$needStr}**।"
                        ),
                        'mode' => 'local',
                        'avatar_state' => 'guide',
                        'lang' => $lang,
                        'ui_action' => 'NONE',
                        'quick_replies' => [$lang === 'hi' ? 'रद्द करें' : ($lang === 'or' ? 'ବାତିଲ୍ କରନ୍ତୁ' : 'Cancel')],
                        'cards' => [],
                    ];
                }

                if (empty($reg['religion']) || empty($reg['marital_status'])) {
                    $reg['substep'] = '3b';
                    $_SESSION['saarthi_reg'] = $reg;
                    $saved3aAll = saarthi_format_saved_fields_line($lang, [
                        [saarthi_localized_text($lang, 'DOB', 'जन्म तिथि', 'ଜନ୍ମ ତାରିଖ'), (string) ($reg['dob'] ?? '')],
                        [saarthi_localized_text($lang, 'Gender', 'लिंग', 'ଲିଙ୍ଗ'), (string) ($reg['gender'] ?? '')],
                        [saarthi_localized_text($lang, "Father", 'पिता', 'ପିତା'), (string) ($reg['father_name'] ?? '')],
                        [saarthi_localized_text($lang, "Mother", 'माता', 'ମାତା'), (string) ($reg['mother_name'] ?? '')],
                    ]);
                    return [
                        'reply' => $saved3aAll . saarthi_localized_text(
                            $lang,
                            "**Quick Personal Profile (Step 3b):** Please tap or type your **Religion** (Hindu / Muslim / Christian / Other), **Marital Status** (Single / Married), and any **Birth Mark** (or \"None\").",
                            "**व्यक्तिगत विवरण (चरण 3b):** कृपया अपना **धर्म** (Hindu / Muslim / Christian / Other), **वैवाहिक स्थिति** (Single / Married), और कोई **पहचान चिह्न (Birth Mark)** (या \"None\") चुनें या लिखें।",
                            "**ବ୍ୟକ୍ତିଗତ ତଥ୍ୟ (ସୋପାନ ୩b):** ଦୟାକରି ଆପଣଙ୍କ **ଧର୍ମ** (Hindu / Muslim / Christian / Other), **ବୈବାହିକ ସ୍ଥିତି** (Single / Married), ଏବଂ **ଜନ୍ମ ଚିହ୍ନ** (କିମ୍ବା \"None\") ବାଛନ୍ତୁ କିମ୍ବା ଲେଖନ୍ତୁ।"
                        ),
                        'mode' => 'local',
                        'avatar_state' => 'guide',
                        'lang' => $lang,
                        'ui_action' => 'NONE',
                        'quick_replies' => ['Hindu, Single, None', 'Hindu, Married, None', 'Muslim, Single, None', 'Christian, Single, None'],
                        'cards' => [],
                    ];
                }
            } else {
                if (empty($reg['religion']) || empty($reg['marital_status'])) {
                    $_SESSION['saarthi_reg'] = $reg;
                    $saved3b = saarthi_format_saved_fields_line($lang, [
                        [saarthi_localized_text($lang, 'Religion', 'धर्म', 'ଧର୍ମ'), (string) ($reg['religion'] ?? '')],
                        [saarthi_localized_text($lang, 'Marital Status', 'वैवाहिक स्थिति', 'ବୈବାହିକ ସ୍ଥିତି'), (string) ($reg['marital_status'] ?? '')],
                        [saarthi_localized_text($lang, 'Birth Mark', 'पहचान चिह्न', 'ଜନ୍ମ ଚିହ୍ନ'), (string) ($reg['distinguishing_marks'] ?? '')],
                    ]);
                    $need3b = [];
                    if (empty($reg['religion'])) {
                        $need3b[] = 'Religion (Hindu/Muslim/Christian/Other)';
                    }
                    if (empty($reg['marital_status'])) {
                        $need3b[] = 'Marital Status (Single/Married)';
                    }
                    return [
                        'reply' => $saved3b . saarthi_localized_text(
                            $lang,
                            'Please also share: **' . implode(', ', $need3b) . '** (and Birth Mark if any).',
                            'कृपया यह भी बताएं: **' . implode(', ', $need3b) . '**।',
                            'ଦୟାକରି ଏହା ମଧ୍ୟ ଦିଅନ୍ତୁ: **' . implode(', ', $need3b) . '**।'
                        ),
                        'mode' => 'local',
                        'avatar_state' => 'guide',
                        'lang' => $lang,
                        'ui_action' => 'NONE',
                        'quick_replies' => !empty($reg['religion']) ? ['Single, None', 'Married, None'] : ['Hindu, Single, None', 'Hindu, Married, None'],
                        'cards' => [],
                    ];
                }
                if (empty($reg['distinguishing_marks'])) {
                    $reg['distinguishing_marks'] = 'None';
                }
            }

            $reg['step'] = 4;
            $reg['substep'] = '';
            $_SESSION['saarthi_reg'] = $reg;
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "**Step 4 of 5 (Academic & Examination Details):** Please share your examination history:\n• **Exam Passed** (e.g., B.Tech, Graduation, Diploma, 12th, 10th)\n• **University / Board & Last College** (e.g., BPUT / CET Bhubaneswar)\n• **Passing Year** (e.g., 2023), **Stream** (e.g., CSE / Science), and **% or CGPA** (e.g., 82%)\n*Tip: Separate multiple exams with `;` — e.g. `10th, CBSE, 2017, General, 80%; B.Tech, BPUT, 2023, CSE, 82%`*",
                    "**चरण 4/5 (शैक्षणिक एवं परीक्षा विवरण):** कृपया अपनी परीक्षा का विवरण लिखें:\n• **परीक्षा** (जैसे B.Tech, Graduation, Diploma, 12th, 10th)\n• **बोर्ड / विश्वविद्यालय और कॉलेज** (जैसे BPUT / CET Bhubaneswar)\n• **उत्तीर्ण वर्ष** (जैसे 2023), **संकाय (Stream)** (जैसे CSE / Science), और **% या CGPA** (जैसे 82%)\n*सुझाव: आप `;` से कई परीक्षाएं लिख सकते हैं — जैसे `10th, CBSE, 2017, General, 80%; B.Tech, BPUT, 2023, CSE, 82%`*",
                    "**ସୋପାନ ୪/୫ (ଶିକ୍ଷାଗତ ଓ ପରୀକ୍ଷା ତଥ୍ୟ):** ଦୟାକରି ଆପଣଙ୍କ ପରୀକ୍ଷା ବିବରଣୀ ଦିଅନ୍ତୁ:\n• **ପରୀକ୍ଷା** (ଯଥା B.Tech, Graduation, Diploma, 12th, 10th)\n• **ବୋର୍ଡ / ବିଶ୍ୱବିଦ୍ୟାଳୟ ଓ କଲେଜ୍** (ଯଥା BPUT / CET Bhubaneswar)\n• **ପାସ୍ ବର୍ଷ** (ଯଥା 2023), **ଷ୍ଟ୍ରିମ୍** (ଯଥା CSE / Science), ଏବଂ **% କିମ୍ବା CGPA** (ଯଥା 82%)\n*ଟିପ୍ସ: `;` ଦେଇ ଏକାଧିକ ପରୀକ୍ଷା ଲେଖିପାରିବେ — ଯଥା `10th, CBSE, 2017, General, 80%; B.Tech, BPUT, 2023, CSE, 82%`*"
                ),
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => [$lang === 'hi' ? 'रद्द करें' : ($lang === 'or' ? 'ବାତିଲ୍ କରନ୍ତୁ' : 'Cancel')],
                'cards' => [],
            ];
        }

        // Step 4: Examination & Academic Qualification History
        if ((int) $reg['step'] === 4) {
            if (preg_match('/^\s*(?:add\s+)?(?:12th|\+2|intermediate)\s+details\s*$/iu', $message)) {
                $_SESSION['saarthi_reg'] = $reg;
                return [
                    'reply' => saarthi_localized_text(
                        $lang,
                        'Please enter your 12th details in this format: `12th, Board, Passing Year, Stream, Percentage or CGPA` (for example: `12th, CBSE, 2019, Science, 85%`).',
                        'कृपया 12वीं का विवरण इस प्रारूप में लिखें: `12th, Board, Passing Year, Stream, Percentage or CGPA` (उदाहरण: `12th, CBSE, 2019, Science, 85%`)।',
                        'ଦୟାକରି 12th ବିବରଣୀ ଏହି ଫର୍ମାଟ୍‌ରେ ଲେଖନ୍ତୁ: `12th, Board, Passing Year, Stream, Percentage or CGPA` (ଉଦାହରଣ: `12th, CBSE, 2019, Science, 85%`)।'
                    ),
                    'mode' => 'local',
                    'avatar_state' => 'guide',
                    'lang' => $lang,
                    'ui_action' => 'NONE',
                    'quick_replies' => [],
                    'cards' => [],
                ];
            }

            $isDone = saarthi_step4_is_done_command($message);
            if (!$isDone) {
                $reg = saarthi_merge_step4_exam_input($reg, $message);
            }

            $primary = $reg['exams'][count($reg['exams'] ?: [[]]) - 1] ?? [];
            $missingEdu = [];
            if (empty($primary['exam_passed'])) {
                $missingEdu[] = saarthi_localized_text($lang, 'Exam Passed (e.g. 12th/B.Tech)', 'परीक्षा नाम', 'ପରୀକ୍ଷା ନାମ');
            }
            if (empty($primary['institute_name'])) {
                $missingEdu[] = saarthi_localized_text($lang, 'University / Board / College', 'बोर्ड / विश्वविद्यालय', 'ବୋର୍ଡ / ବିଶ୍ୱବିଦ୍ୟାଳୟ');
            }
            if (empty($primary['year_of_passing'])) {
                $missingEdu[] = saarthi_localized_text($lang, 'Passing Year (4 digits e.g. 2023)', 'उत्तीर्ण वर्ष (जैसे 2023)', 'ପାସ୍ ବର୍ଷ (ଯଥା 2023)');
            }
            if (empty($primary['percentage'])) {
                $missingEdu[] = saarthi_localized_text($lang, 'Percentage or CGPA (e.g. 82%)', 'प्रतिशत या CGPA', 'ଶତକଡ଼ା ବା CGPA');
            }

            if ($missingEdu !== []) {
                $_SESSION['saarthi_reg'] = $reg;
                $savedEdu = saarthi_format_saved_fields_line($lang, [
                    [saarthi_localized_text($lang, 'Exam', 'परीक्षा', 'ପରୀକ୍ଷା'), (string) ($primary['exam_passed'] ?? '')],
                    [saarthi_localized_text($lang, 'Board/Univ', 'बोर्ड/विवि', 'ବୋର୍ଡ/ବିଶ୍ୱବିଦ୍ୟାଳୟ'), (string) ($primary['institute_name'] ?? '')],
                    [saarthi_localized_text($lang, 'Year', 'वर्ष', 'ବର୍ଷ'), (string) ($primary['year_of_passing'] ?? '')],
                    [saarthi_localized_text($lang, 'Stream', 'संकाय', 'ଷ୍ଟ୍ରିମ୍'), (string) ($primary['stream'] ?? '')],
                    [saarthi_localized_text($lang, 'Marks', 'अंक', 'ମାର୍କ'), (string) ($primary['percentage'] ?? '')],
                ]);
                $needEdu = implode(', ', $missingEdu);
                return [
                    'reply' => $savedEdu . saarthi_localized_text(
                        $lang,
                        "Please also enter: **{$needEdu}**.\n*(Optional: include Stream like Science / CSE / General)*",
                        "कृपया यह भी बताएं: **{$needEdu}**।\n*(वैकल्पिक: संकाय जैसे Science / CSE / General लिखें)*",
                        "ଦୟାକରି ଏହା ମଧ୍ୟ ଦିଅନ୍ତୁ: **{$needEdu}**।"
                    ),
                    'mode' => 'local',
                    'avatar_state' => 'guide',
                    'lang' => $lang,
                    'ui_action' => 'NONE',
                    'quick_replies' => [$lang === 'hi' ? 'रद्द करें' : ($lang === 'or' ? 'ବାତିଲ୍ କରନ୍ତୁ' : 'Cancel')],
                    'cards' => [],
                ];
            }

            // Mirror primary (highest/latest) exam onto summary keys
            if (empty($primary['stream'])) {
                $primary['stream'] = 'General';
                $reg['exams'][count($reg['exams']) - 1]['stream'] = 'General';
            }

            // ── Multi-exam: check if user wants to add another exam ──────────
            // User can type "Done", "No more", "Next", "Proceed", or just the
            // next exam line.  If the message looks like "done / no more / next /
            // proceed / step 5", flush and advance to Step 5.
            // Build a readable exam list to show back to the user
            $examLines = [];
            foreach ($reg['exams'] as $ex) {
                $ep = $ex['exam_passed'] ?? $ex['exam_name'] ?? '';
                $inst = $ex['institute_name'] ?? '';
                $yr   = $ex['year_of_passing'] ?? '';
                $pct  = $ex['percentage'] ?? '';
                $st   = $ex['stream'] ?? '';
                $line = "**{$ep}**";
                if ($inst !== '') $line .= " — {$inst}";
                if ($yr   !== '') $line .= " ({$yr})";
                if ($pct  !== '') $line .= " {$pct}";
                if ($st   !== '' && $st !== 'General') $line .= " [{$st}]";
                $examLines[] = $line;
            }
            $examSummary = implode("\n• ", $examLines);

            if ($isDone || empty($examLines)) {
                // Advance to Step 5
                $reg['qualification']   = (string) $primary['exam_passed'];
                $reg['board_university']= (string) $primary['institute_name'];
                $reg['college_name']    = (string) $primary['institute_name'];
                $reg['year_of_passing'] = (string) $primary['year_of_passing'];
                $reg['stream']          = (string) $primary['stream'];
                $reg['percentage']      = (string) $primary['percentage'];
                $reg['step'] = 5;
                $_SESSION['saarthi_reg'] = $reg;

                return [
                    'reply' => saarthi_localized_text(
                        $lang,
                        "**Step 5 of 5 (Final Step):** Please share:\n• **Category** (General / OBC / SC / ST / EWS) & **PwD Status** (Yes / No)\n• **Aadhar Number** (12 digits, or \"Skip\") & optional **APAAR ID**\n• **Full Correspondence Address** (with PIN code)",
                        "**चरण 5/5 (अंतिम चरण):** कृपया ये विवरण दें:\n• **श्रेणी** (General / OBC / SC / ST / EWS) और **PwD स्थिति** (Yes / No)\n• **आधार नंबर** (12 अंक, या \"Skip\") और वैकल्पिक **APAAR ID**\n• **पूरा पत्राचार पता** (पिन कोड सहित)",
                        "**ସୋପାନ ୫/୫ (ଶେଷ ସୋପାନ):** ଦୟାକରି ଏହି ତଥ୍ୟ ଦିଅନ୍ତୁ:\n• **ବର୍ଗ** (General / OBC / SC / ST / EWS) ଏବଂ **PwD ସ୍ଥିତି** (Yes / No)\n• **ଆଧାର ନମ୍ବର** (୧୨ ଅଙ୍କ, କିମ୍ବା \"Skip\") ଓ ଇଚ୍ଛାଧୀନ **APAAR ID**\n• **ସମ୍ପୂର୍ଣ୍ଣ ଠିକଣା** (PIN କୋଡ୍ ସହିତ)"
                    ),
                    'mode' => 'local',
                    'avatar_state' => 'guide',
                    'lang' => $lang,
                    'ui_action' => 'NONE',
                    'quick_replies' => ['General, PwD No, Skip', 'OBC, PwD No, Skip', 'SC/ST, PwD No, Skip'],
                    'cards' => [],
                ];
            }

            // ── First exam complete — ask if they have more ──────────────────
            $_SESSION['saarthi_reg'] = $reg;
            $examCount = count($reg['exams']);
            $addMoreTip = saarthi_localized_text(
                $lang,
                "*Tip: Type the next exam (e.g. `12th, CBSE, 2019, Science, 85%`) or tap **Done** to continue.*",
                "*सुझाव: अगली परीक्षा टाइप करें (जैसे `12th, CBSE, 2019, Science, 85%`) या **Done** दबाएं।*",
                "*ସୁଝାବ: ପରବର୍ତ୍ତୀ ପରୀକ୍ଷା ଟାଇପ୍ କରନ୍ତୁ (ଯଥା `12th, CBSE, 2019, Science, 85%`) ଅଥବା **Done** ଦବାନ୍ତୁ।*"
            );
            $savedMsg  = saarthi_localized_text(
                $lang,
                "✅ Exam {$examCount} saved:\n• {$examSummary}\n\nDo you have **more exams** to add (e.g. 12th, B.Tech, Diploma)?\n{$addMoreTip}",
                "✅ परीक्षा {$examCount} सहेजी गई:\n• {$examSummary}\n\nक्या आपके **और परीक्षाएं** हैं (जैसे 12वीं, B.Tech, Diploma)?\n{$addMoreTip}",
                "✅ ପରୀକ୍ଷା {$examCount} ସଞ୍ଚୟ ହୋଇଛି:\n• {$examSummary}\n\nଆପଣଙ୍କର **ଆଉ ପରୀକ୍ଷା** ଅଛି କି (ଯଥା 12th, B.Tech, Diploma)?\n{$addMoreTip}"
            );

            return [
                'reply'        => $savedMsg,
                'mode'         => 'local',
                'avatar_state' => 'guide',
                'lang'         => $lang,
                'ui_action'    => 'NONE',
                'quick_replies'=> [
                    saarthi_localized_text($lang, 'Done (proceed to Step 5)', 'Done (Step 5 जाएं)', 'Done (Step 5 ଯାଆନ୍ତୁ)'),
                    saarthi_localized_text($lang, 'Add 12th details', '12वीं जोड़ें', '12th ଯୋଗ କରନ୍ତୁ'),
                    saarthi_localized_text($lang, 'Add Graduation / B.Tech', 'Graduation / B.Tech जोड़ें', 'Graduation / B.Tech ଯୋଗ କରନ୍ତୁ'),
                ],
                'cards'        => [],
            ];
        }

        // Step 5: Category, PwD, optional Aadhar / APAAR, Correspondence Address
        if ((int) $reg['step'] === 5) {
            $work = $message;
            if (preg_match('/(?:aadhar|aadhaar)\s*(?:no|number|id)?\s*[:=\-]?\s*(\d{12})\b/i', $work, $am)) {
                $reg['aadhar'] = $am[1];
                $work = str_replace($am[0], ' ', $work);
            }
            if (preg_match('/(?:apaar)\s*(?:id|no|number)?\s*[:=\-]?\s*(\d{12})\b/i', $work, $ap)) {
                $reg['apaar_id'] = $ap[1];
                $work = str_replace($ap[0], ' ', $work);
            }
            if (preg_match('/\b(\d{12})\b/', $work, $num12)) {
                if (preg_match('/aadhar|aadhaar/i', $message) || $reg['aadhar'] === '') {
                    $reg['aadhar'] = $num12[1];
                } else {
                    $reg['apaar_id'] = $num12[1];
                }
                $work = str_replace($num12[0], ' ', $work);
            } elseif (preg_match('/\b(skip\s*apaar|no\s*apaar|apaar\s*na|skip\s*aadhar|skip)\b/i', $work, $sk)) {
                if ($reg['apaar_id'] === '') {
                    $reg['apaar_id'] = 'SKIPPED';
                }
                $work = str_ireplace($sk[0], ' ', $work);
            }

            if (preg_match('/\b(general|gen)\b/i', $work, $cm)) {
                $reg['category'] = 'General';
                $work = str_ireplace($cm[0], ' ', $work);
            } elseif (preg_match('/\b(obc)\b/i', $work, $cm)) {
                $reg['category'] = 'OBC';
                $work = str_ireplace($cm[0], ' ', $work);
            } elseif (preg_match('/\b(sc)\b/i', $work, $cm)) {
                $reg['category'] = 'SC';
                $work = str_ireplace($cm[0], ' ', $work);
            } elseif (preg_match('/\b(st)\b/i', $work, $cm)) {
                $reg['category'] = 'ST';
                $work = str_ireplace($cm[0], ' ', $work);
            } elseif (preg_match('/\b(ews)\b/i', $work, $cm)) {
                $reg['category'] = 'EWS';
                $work = str_ireplace($cm[0], ' ', $work);
            }

            if (preg_match('/pwd\s*[:=\-]?\s*(yes|y|true)/i', $work, $pm)) {
                $reg['pwd_status'] = 'Yes';
                $work = str_ireplace($pm[0], ' ', $work);
            } elseif (preg_match('/pwd\s*[:=\-]?\s*(no|n|false)/i', $work, $pm)) {
                $reg['pwd_status'] = 'No';
                $work = str_ireplace($pm[0], ' ', $work);
            }

            $addrCand = trim((string) preg_replace('/\b(aadhar|aadhaar|apaar(\s*id)?|category|correspondence\s+address|address|adress)\b[:\s\-]*/i', ' ', $work));
            $addrCand = trim((string) preg_replace('/^[,\s;\-]+|[,\s;\-]+$/', '', $addrCand));
            $addrCand = trim((string) preg_replace('/\s+/', ' ', $addrCand));
            if ($addrCand !== '' && mb_strlen($addrCand) >= 5 && !preg_match('/^(yes|no|general|obc|sc|st|ews)$/i', $addrCand)) {
                $reg['address'] = $addrCand;
            }

            if ($reg['category'] === '') {
                $reg['category'] = 'General';
            }
            if ($reg['pwd_status'] === '') {
                $reg['pwd_status'] = 'No';
            }

            if ($reg['address'] === '') {
                $_SESSION['saarthi_reg'] = $reg;
                $savedStep5 = saarthi_format_saved_fields_line($lang, [
                    [saarthi_localized_text($lang, 'Category', 'श्रेणी', 'ବର୍ଗ'), (string) $reg['category']],
                    ['PwD', (string) $reg['pwd_status']],
                    ['Aadhar', (string) ($reg['aadhar'] ?: '')],
                    ['APAAR ID', ($reg['apaar_id'] && $reg['apaar_id'] !== 'SKIPPED') ? (string) $reg['apaar_id'] : ''],
                ]);
                return [
                    'reply' => $savedStep5 . saarthi_localized_text(
                        $lang,
                        'Please enter your **Full Correspondence Address** (locality, city, state, PIN code) so I can prepare your summary.',
                        'कृपया अपना **पूरा पत्राचार पता** (शहर, राज्य और पिन कोड सहित) लिखें।',
                        'ଦୟାକରି ଆପଣଙ୍କର **ସମ୍ପୂର୍ଣ୍ଣ ଠିକଣା** (ସହର, ରାଜ୍ୟ ଏବଂ PIN କୋଡ୍ ସହିତ) ଲେଖନ୍ତୁ।'
                    ),
                    'mode' => 'local',
                    'avatar_state' => 'guide',
                    'lang' => $lang,
                    'ui_action' => 'NONE',
                    'quick_replies' => [$lang === 'hi' ? 'रद्द करें' : ($lang === 'or' ? 'ବାତିଲ୍ କରନ୍ତୁ' : 'Cancel')],
                    'cards' => [],
                ];
            }

            return saarthi_build_registration_summary_response($reg, $lang);
        }

        return null;
    }
}

if (!function_exists('saarthi_local_reply_structured')) {
    /**
     * Full multilingual (EN/HI/OR) rule + live DB engine when no external LLM key/webhook answers.
     *
     * @return array<string, mixed>
     */
    function saarthi_local_reply_structured(string $message, string $lang, array $ctx, ?mysqli $conn = null): array
    {
        $m = saarthi_lower(trim($message));
        $help = saarthi_helpdesk_info();

        if ($m === '') {
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "Please type your question — for example: how does this site work, ongoing courses in Bhubaneswar, or course registration.",
                    "कृपया अपना प्रश्न लिखें — जैसे: पोर्टल कैसे काम करता है, उपलब्ध कोर्स, या नया रजिस्ट्रेशन।",
                    "ଦୟାକରି ଆପଣଙ୍କ ପ୍ରଶ୍ନ ଲେଖନ୍ତୁ — ଯେପରି: ପୋର୍ଟାଲ୍ କେମିତି କାମ କରେ, ଚାଲୁଥିବା କୋର୍ସ, କିମ୍ବା ନୂଆ ପଞ୍ଜୀକରଣ।"
                ),
                'mode' => 'local',
                'avatar_state' => 'wave',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => [],
            ];
        }

        // Safety: refuse sensitive secrets
        if (saarthi_has_sensitive_payment_input($message)) {
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "For your security, please **never share** passwords, OTPs, UPI PINs, or card details in chat.\nFor payment issues, contact NIELIT Bhubaneswar Helpdesk at **{$help['phone']}** or **{$help['email']}**.",
                    "आपकी सुरक्षा के लिए, कृपया चैट में **कभी भी** पासवर्ड, OTP, UPI PIN या कार्ड विवरण साझा न करें।\nभुगतान सहायता के लिए हेल्पडेस्क **{$help['phone']}** या **{$help['email']}** पर संपर्क करें।",
                    "ଆପଣଙ୍କ ସୁରକ୍ଷା ପାଇଁ, ଦୟାକରି ଚାଟ୍‌ରେ **କଦାପି** ପାସ୍‌ୱାର୍ଡ୍, OTP, UPI PIN କିମ୍ବା କାର୍ଡ୍ ତଥ୍ୟ ଦିଅନ୍ତୁ ନାହିଁ।\nପେମେଣ୍ଟ ସହାୟତା ପାଇଁ **{$help['phone']}** କିମ୍ବା **{$help['email']}** ରେ ଯୋଗାଯୋଗ କରନ୍ତୁ।"
                ),
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => [],
            ];
        }

        // Language switch acknowledgement if the whole message is basically a switch command
        if (preg_match('/^(odia\s*(re\s*kuha|kuha|re)?|hindi\s*(mein|me)\s*(batao|bolo)?|switch\s*to\s*english|english\s*please|ଓଡ଼ିଆ|हिन्दी|हिंदी)[\s!.?]*$/iu', $m)) {
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "Switched to **English**! I'm **Vani**, your NIELIT Bhubaneswar portal guide. How can I assist you today?",
                    "बिल्कुल! अब मैं **हिन्दी** में बात करूँगी। नमस्ते, मैं **वाणी** हूँ — NIELIT भुवनेश्वर पोर्टल की आपकी सहायक। आज मैं आपकी क्या मदद कर सकती हूँ?",
                    "ନିଶ୍ଚୟ! ବର୍ତ୍ତମାନ ମୁଁ **ଓଡ଼ିଆ** ରେ କଥା ହେବି। ନମସ୍କାର, ମୁଁ **ବାଣୀ** — NIELIT ଭୁବନେଶ୍ୱର ପୋର୍ଟାଲର ଆପଣଙ୍କ ସହାୟକ। ଆଜି ମୁଁ ଆପଣଙ୍କୁ କିପରି ସାହାଯ୍ୟ କରିପାରିବି?"
                ),
                'mode' => 'local',
                'avatar_state' => 'wave',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => [],
            ];
        }

        // Conversational registration workflow (Step 1-4 + Confirm)
        $regRes = saarthi_handle_registration_flow($message, $lang, $ctx, $conn);
        if ($regRes !== null) {
            return $regRes;
        }

        // Greetings
        if (preg_match('/^(hi|hello|hey|namaste|namaskar|nomaskar|good morning|good afternoon|good evening|hii+)[\s!.,?]*$/iu', $m)) {
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "Namaste! I'm **Vani**, your NIELIT Bhubaneswar Campus & Portal Guide.\nI can explain the 4-step portal roadmap, check live courses & campus seats, or help you register right here in chat!",
                    "नमस्ते! मैं **वाणी** हूँ — NIELIT भुवनेश्वर पोर्टल की आपकी AI मार्गदर्शक।\nमैं पोर्टल का 4-चरणीय रोडमैप बता सकती हूँ, लाइव कोर्स और कैंपस सीटें दिखा सकती हूँ, या यहीं चैट में आपका रजिस्ट्रेशन कर सकती हूँ!",
                    "ନମସ୍କାର! ମୁଁ **ବାଣୀ** — NIELIT ଭୁବନେଶ୍ୱର ପୋର୍ଟାଲର ଆପଣଙ୍କ ସହାୟକ।\nମୁଁ ଆପଣଙ୍କୁ ପୋର୍ଟାଲର ୪-ସୋପାନ ରୋଡ୍‌ମ୍ୟାପ୍ ବୁଝାଇପାରିବି, ଲାଇଭ୍ କୋର୍ସ ଓ କ୍ୟାମ୍ପସ୍ ସିଟ୍ ଦେଖାଇପାରିବି, କିମ୍ବା ଏଠାରେ ଚାଟ୍‌ରେ ପଞ୍ଜୀକରଣ କରାଇପାରିବି!"
                ),
                'mode' => 'local',
                'avatar_state' => 'wave',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => [],
            ];
        }

        // Task A: Site Roadmap & Navigation
        if (preg_match('/\b(how\s+(does|to)\s+(this\s+)?(site|website|portal)\s+work|roadmap|guide\s+me|where\s+to\s+start|step\s*by\s*step)\b|कैसे\s*काम\s*करता|रोडमैप|କେମିତି\s*କାମ\s*କରେ|ରୋଡ୍.?ମ୍ୟାପ୍/iu', $m)) {
            $roadmap = saarthi_get_roadmap($lang, $ctx);
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "Here is your **4-Step Guide** to using the NIELIT Bhubaneswar Portal. Click any step button below to jump straight in!",
                    "NIELIT भुवनेश्वर पोर्टल का उपयोग करने के लिए यह **4-चरणीय गाइड** है। किसी भी चरण पर सीधे जाने के लिए नीचे दिए गए बटन पर क्लिक करें!",
                    "NIELIT ଭୁବନେଶ୍ୱର ପୋର୍ଟାଲ୍ ବ୍ୟବହାର କରିବା ପାଇଁ ଏଠାରେ **୪-ସୋପାନ ଗାଇଡ୍** ଅଛି। ସିଧାସଳଖ ଆଗକୁ ବଢ଼ିବାକୁ ତଳ ବଟନ୍ କ୍ଲିକ୍ କରନ୍ତୁ!"
                ),
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'SHOW_ROADMAP',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => ['roadmap' => $roadmap],
            ];
        }

        // Task D: Support escalation — payment gateway failures, certificate corrections, callback
        if (preg_match('/\b(payment\s*fail|transaction\s*fail|deducted|refund|certificate\s*(issue|error|correction|name)|name\s*correction|edit\s*student|change\s*name|callback|call\s*back|helpdesk)\b/iu', $m)) {
            // Check if user submitted contact info for callback
            if (preg_match('/(?:\+91[\s\-]?)?([6-9]\d{9})\b/', $message, $pm) && $conn instanceof mysqli) {
                $logged = saarthi_log_support_callback($conn, [
                    'phone' => $pm[1],
                    'name' => 'Chat Visitor (' . $pm[1] . ')',
                    'issue' => $message,
                    'category' => strpos($m, 'cert') !== false ? 'certificate' : 'fees',
                ]);
                $tId = (int) ($logged['ticket_id'] ?? 0);
                $ticketLine = $tId > 0 ? " (#{$tId})" : '';
                return [
                    'reply' => saarthi_localized_text(
                        $lang,
                        "I've logged your support callback request{$ticketLine}. Our staff will reach out soon.\nYou can also contact Helpdesk directly at **{$help['phone']}** or **{$help['email']}** ({$help['hours']}).",
                        "मैंने आपका सपोर्ट कॉलबैक अनुरोध{$ticketLine} दर्ज कर लिया है। हमारी टीम जल्द संपर्क करेगी।\nआपातकालीन सहायता के लिए **{$help['phone']}** या **{$help['email']}** ({$help['hours']}) पर भी संपर्क करें।",
                        "ଆପଣଙ୍କ ସପୋର୍ଟ କଲବ୍ୟାକ୍ ଅନୁରୋଧ{$ticketLine} ପଞ୍ଜୀକୃତ ହୋଇଛି। ଆମ ଟିମ୍ ଶୀଘ୍ର ଯୋଗାଯୋଗ କରିବେ।\nସିଧାସଳଖ ସହାୟତା ପାଇଁ **{$help['phone']}** କିମ୍ବା **{$help['email']}** ({$help['hours']}) ରେ ଯୋଗାଯୋଗ କରନ୍ତୁ।"
                    ),
                    'mode' => 'local',
                    'avatar_state' => 'happy',
                    'lang' => $lang,
                    'ui_action' => 'NONE',
                    'quick_replies' => saarthi_default_quick_replies($lang),
                    'cards' => [],
                ];
            }

            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "For payment failures, pending refunds, or certificate/record corrections, please contact our official helpdesk:\n• **Phone:** {$help['phone']}\n• **Email:** {$help['email']} | {$help['director_email']}\n• **Hours:** {$help['hours']}\n\nWant me to log a **support callback**? Just reply with your **10-digit mobile number** and brief issue!",
                    "भुगतान विफलता, रिफंड या प्रमाणपत्र/नाम सुधार के लिए हमारे आधिकारिक हेल्पडेस्क से संपर्क करें:\n• **फोन:** {$help['phone']}\n• **ईमेल:** {$help['email']} | {$help['director_email']}\n• **समय:** {$help['hours']}\n\nयदि आप **सपोर्ट कॉलबैक** दर्ज कराना चाहते हैं, तो कृपया अपना **10-अंकों का मोबाइल नंबर** और समस्या लिखें!",
                    "ପେମେଣ୍ଟ ବିଫଳତା, ରିଫଣ୍ଡ କିମ୍ବା ସାର୍ଟିଫିକେଟ୍/ନାମ ସଂଶୋଧନ ପାଇଁ ଆମ ହେଲ୍ପଡେସ୍କ ସହିତ ଯୋଗାଯୋଗ କରନ୍ତୁ:\n• **ଫୋନ୍:** {$help['phone']}\n• **ଇମେଲ୍:** {$help['email']} | {$help['director_email']}\n• **ସମୟ:** {$help['hours']}\n\nଯଦି ଆପଣ **କଲବ୍ୟାକ୍** ଲଗ୍ କରିବାକୁ ଚାହୁଁଛନ୍ତି, ତେବେ ଆପଣଙ୍କ **୧୦-ଅଙ୍କ ମୋବାଇଲ୍ ନମ୍ବର** ଏବଂ ସମସ୍ୟା ଲେଖନ୍ତୁ!"
                ),
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => [],
            ];
        }

        // Task B: Live Course, Batch, Seat, Location search
        $courseTrigger = (bool) preg_match(
            '/\b(course|courses|program|batch|batches|seat|seats|duration|eligib|campus|centres|centers|location|where|ongoing|how\s+many|o[\s\-]?level|a[\s\-]?level|ccc|python|drone|iot|internship|workshop|machine\s*learning|ai\/ml|bhubaneswar|baleshwar|balasore|raipur|cuttack)\b|कोर्स|सीट|बैच|कहाँ|କୋର୍ସ|ସିଟ୍|ବ୍ୟାଚ୍|କ୍ୟାମ୍ପସ୍|କେତେ/iu',
            $m
        );
        if ($courseTrigger && $conn instanceof mysqli) {
            $intent = saarthi_parse_course_search_intent($message, $conn);
            $res = saarthi_search_courses($conn, $intent['keyword'], $intent['location'], $intent['status']);
            $courses = $res['courses'] ?? [];
            $cntOngoing = (int) ($res['total_ongoing'] ?? 0);
            $cntPub = (int) ($res['total_published'] ?? 0);

            if (!empty($res['exact_match'])) {
                $reply = saarthi_localized_text(
                    $lang,
                    "We currently have **{$cntOngoing} ongoing** courses ({$cntPub} total published) across NIELIT campuses. Here are the live matching courses — click **Register via Vani** on any card to apply in chat!",
                    "NIELIT कैंपस में अभी **{$cntOngoing} चालू (ongoing)** कोर्स हैं (कुल {$cntPub} प्रकाशित)। नीचे लाइव कोर्स विवरण देखें — सीधे चैट में आवेदन करने के लिए **Register via Vani** पर क्लिक करें!",
                    "NIELIT କ୍ୟାମ୍ପସ୍‌ରେ ବର୍ତ୍ତମାନ **{$cntOngoing}ଟି ଚାଲୁଥିବା** କୋର୍ସ ଅଛି (ମୋଟ {$cntPub}ଟି ପ୍ରକାଶିତ)। ତଳେ ଲାଇଭ୍ କୋର୍ସ ତଥ୍ୟ ଦେଖନ୍ତୁ — ଚାଟ୍‌ରେ ଆବେଦନ କରିବାକୁ **Register via Vani** କ୍ଲିକ୍ କରନ୍ତୁ!"
                );
            } else {
                $reply = saarthi_localized_text(
                    $lang,
                    "No exact match was found for your query, so here are our closest **ongoing/active** courses from the live portal ({$cntOngoing} active total):",
                    "आपकी खोज से ठीक मेल खाने वाला कोर्स नहीं मिला, इसलिए यहाँ पोर्टल के सबसे करीबी **सक्रिय (Active)** कोर्स दिए गए हैं (कुल {$cntOngoing} चालू):",
                    "ଆପଣଙ୍କ ଖୋଜା ସହିତ ସଠିକ୍ ମେଳ ଖାଉଥିବା କୋର୍ସ ମିଳିଲା ନାହିଁ, ତେଣୁ ଏଠାରେ ପୋର୍ଟାଲର ନିକଟତମ **ସକ୍ରିୟ** କୋର୍ସଗୁଡ଼ିକ ଦିଆଯାଇଛି (ମୋଟ {$cntOngoing}ଟି ଚାଲୁଅଛି):"
                );
            }

            return [
                'reply' => $reply,
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => preg_match('/\b(scroll|browse|show\s+on\s+page)\b/i', $m) ? 'SCROLL_COURSES' : 'NONE',
                'quick_replies' => $lang === 'hi'
                    ? ['रजिस्ट्रेशन करें', 'कोर्स पेज पर जाएं', 'पोर्टल रोडमैप']
                    : ($lang === 'or' ? ['ପଞ୍ଜୀକରଣ କରନ୍ତୁ', 'କୋର୍ସ ପୃଷ୍ଠାକୁ ଯାଆନ୍ତୁ', 'ରୋଡ୍‌ମ୍ୟାପ୍'] : ['Register for a course', 'Show courses section', 'How does this site work?']),
                'cards' => ['courses' => $courses],
            ];
        }

        // Document upload FAQ / direct Student Portal uploader link
        if (preg_match('/\b(upload|pending\s*doc|aadhar\s*card|passport\s*photo|signature|marksheet|documents?)\b|दस्तावेज़|फोटो|ଆଧାର|ଫଟୋ|ସ୍ୱାକ୍ଷର/iu', $m)) {
            $uploadUrl = function_exists('app_url') ? app_url('student/upload_documents.php') : '/student/upload_documents.php';
            $loginUrl = $ctx['student_login'] ?? (function_exists('app_url') ? app_url('student/login') : '/student/login.php');
            if ($conn instanceof mysqli && !empty($_SESSION['student_id'])) {
                require_once __DIR__ . '/student_document_upload_helper.php';
                $stuRow = studentDocumentAuthorizeAccess($conn, (int) ($_SESSION['active_record_id'] ?? 0), '');
                if ($stuRow) {
                    $recId = (int) $stuRow['id'];
                    $sid = (string) $stuRow['student_id'];
                    $tok = saarthiIssueUploadToken($recId, $sid);
                    return [
                        'reply' => saarthi_localized_text(
                            $lang,
                            "You can upload your **Passport Photo, Signature, Aadhar Card & Certificates** right below or on the Student Portal Upload Page.",
                            "आप नीचे या स्टूडेंट पोर्टल अपलोड पेज पर अपनी **फोटो, हस्ताक्षर, आधार कार्ड और प्रमाणपत्र** अपलोड कर सकते हैं।",
                            "ଆପଣ ତଳେ କିମ୍ବା ଷ୍ଟୁଡେଣ୍ଟ ପୋର୍ଟାଲ୍ ଅପଲୋଡ୍ ପୃଷ୍ଠାରେ **ଫଟୋ, ସ୍ୱାକ୍ଷର, ଆଧାର କାର୍ଡ ଓ ସାର୍ଟିଫିକେଟ୍** ଅପଲୋଡ୍ କରିପାରିବେ।"
                        ),
                        'mode' => 'local',
                        'avatar_state' => 'guide',
                        'lang' => $lang,
                        'ui_action' => 'NONE',
                        'quick_replies' => saarthi_default_quick_replies($lang),
                        'cards' => [
                            'registration_receipt' => [
                                'student_id' => $sid,
                                'record_id' => $recId,
                                'course_name' => (string) ($stuRow['course'] ?? ''),
                                'center_location' => (string) ($stuRow['training_center'] ?? ''),
                                'aadhar' => (string) ($stuRow['aadhar'] ?? ''),
                                'download_pdf_url' => saarthi_issue_pdf_token($recId, $sid),
                                'upload_token' => $tok,
                                'upload_api_url' => saarthiBuildUploadApiUrl($recId, $sid, $tok),
                                'upload_portal_url' => saarthiBuildUploadPortalUrl($recId, $sid, $tok),
                                'document_checklist' => studentDocumentChecklist($conn, $stuRow),
                                'student_login_url' => $loginUrl,
                            ],
                        ],
                    ];
                }
            }
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "After submitting your registration in chat, click **Upload Photo, Signature & Aadhar Here** right under your receipt card — or log in to the **Student Portal** ({$loginUrl}) and open **Upload Documents** ({$uploadUrl}).\nOnce uploaded, your **Admission Form PDF** updates automatically with your photo and signature!",
                    "चैट में रजिस्ट्रेशन के तुरंत बाद रसीद कार्ड के नीचे **Upload Photo, Signature & Aadhar Here** पर क्लिक करें — या **स्टूडेंट पोर्टल** ({$loginUrl}) में लॉगिन करके **Upload Documents** ({$uploadUrl}) खोलें।",
                    "ଚାଟ୍‌ରେ ପଞ୍ଜୀକରଣ ପରେ ରସିଦ୍ କାର୍ଡ ତଳେ **Upload Photo, Signature & Aadhar Here** କ୍ଲିକ୍ କରନ୍ତୁ — କିମ୍ବା **ଷ୍ଟୁଡେଣ୍ଟ ପୋର୍ଟାଲ୍** ({$loginUrl}) ରେ ଲଗଇନ୍ କରି **Upload Documents** ({$uploadUrl}) ଖୋଲନ୍ତୁ।"
                ),
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => [],
            ];
        }

        // Student Login / Portal FAQs
        if (preg_match('/login|sign\s*in|forgot\s*password|लॉगिन|ଲଗଇନ୍/iu', $m)) {
            $isAdmin = (bool) preg_match('/admin|staff/iu', $m);
            $link = $isAdmin ? ($ctx['admin_login'] ?? '') : ($ctx['student_login'] ?? '');
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    ($isAdmin ? 'Admin login: ' : 'Student Portal login: ') . $link . "\nIf you forgot your password, use the reset link on the login page. Want to register as a new student right here?",
                    ($isAdmin ? 'एडमिन लॉगिन: ' : 'स्टूडेंट पोर्टल लॉगिन: ') . $link . "\nयदि आप पासवर्ड भूल गए हैं, तो लॉगिन पेज पर रीसेट विकल्प का उपयोग करें।",
                    ($isAdmin ? 'ଆଡମିନ୍ ଲଗଇନ୍: ' : 'ଷ୍ଟୁଡେଣ୍ଟ ପୋର୍ଟାଲ୍ ଲଗଇନ୍: ') . $link . "\nଯଦି ପାସ୍‌ୱାର୍ଡ୍ ଭୁଲିଯାଇଛନ୍ତି, ତେବେ ଲଗଇନ୍ ପୃଷ୍ଠାରେ ରିସେଟ୍ ବିକଳ୍ପ ବ୍ୟବହାର କରନ୍ତୁ।"
                ),
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => [],
            ];
        }

        if (preg_match('/mock\s*test|exam\s*practice|मॉक\s*टेस्ट|ମକ୍\s*ଟେଷ୍ଟ/iu', $m)) {
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    'Mock Test Portal: ' . ($ctx['mock_test'] ?? '') . "\nPractice NIELIT exams anytime with instant scoring.",
                    'मॉक टेस्ट पोर्टल: ' . ($ctx['mock_test'] ?? '') . "\nतुरंत स्कोर के साथ NIELIT परीक्षाओं का अभ्यास करें।",
                    'ମକ୍ ଟେଷ୍ଟ୍ ପୋର୍ଟାଲ୍: ' . ($ctx['mock_test'] ?? '') . "\nସଙ୍ଗେ ସଙ୍ଗେ ସ୍କୋର୍ ସହିତ NIELIT ପରୀକ୍ଷା ଅଭ୍ୟାସ କରନ୍ତୁ।"
                ),
                'mode' => 'local',
                'avatar_state' => 'happy',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => [],
            ];
        }

        if (preg_match('/fee|fees|payment|pay\b|receipt|फीस|ଫିସ୍/iu', $m)) {
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    'Online Fee & Payment Portal: ' . ($ctx['payment_portal'] ?? '') . "\nAfter paying online, you can download your official Money Receipt from Student Portal -> Fee Details. Need fee info for a specific course?",
                    'ऑनलाइन फीस एवं पेमेंट पोर्टल: ' . ($ctx['payment_portal'] ?? '') . "\nऑनलाइन भुगतान के बाद आप स्टूडेंट पोर्टल -> Fee Details से अपनी मनी रसीद डाउनलोड कर सकते हैं।",
                    'ଅନଲାଇନ୍ ଫିସ୍ ଓ ପେମେଣ୍ଟ ପୋର୍ଟାଲ୍: ' . ($ctx['payment_portal'] ?? '') . "\nଅନଲାଇନ୍ ପେମେଣ୍ଟ ପରେ ଷ୍ଟୁଡେଣ୍ଟ ପୋର୍ଟାଲ୍ -> Fee Details ରୁ ରସିଦ୍ ଡାଉନଲୋଡ୍ କରିପାରିବେ।"
                ),
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => [],
            ];
        }

        if (preg_match('/\bnews\b|announcement|समाचार|ଖବର|ଘୋଷଣା/iu', $m)) {
            $latest = $ctx['latest_news'] ?? 'See the News section on the homepage.';
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "Latest public announcements: " . $latest,
                    "नवीनतम सार्वजनिक घोषणाएं: " . $latest,
                    "ସଦ୍ୟତମ ଘୋଷଣା: " . $latest
                ),
                'mode' => 'local',
                'avatar_state' => 'happy',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => [],
            ];
        }

        if (preg_match('/\b(contact|phone|address)\b|संपर्क|ଯୋଗାଯୋଗ/iu', $m)) {
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "NIELIT Bhubaneswar Contact:\n• Address: {$help['address']}\n• Phone: {$help['phone']}\n• Email: {$help['email']} | {$help['director_email']}\n• Hours: {$help['hours']}",
                    "NIELIT भुवनेश्वर संपर्क:\n• पता: {$help['address']}\n• फोन: {$help['phone']}\n• ईमेल: {$help['email']} | {$help['director_email']}\n• समय: {$help['hours']}",
                    "NIELIT ଭୁବନେଶ୍ୱର ଯୋଗାଯୋଗ:\n• ଠିକଣା: {$help['address']}\n• ଫୋନ୍: {$help['phone']}\n• ଇମେଲ୍: {$help['email']} | {$help['director_email']}\n• ସମୟ: {$help['hours']}"
                ),
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => [],
            ];
        }

        // Default helpful response
        return [
            'reply' => saarthi_localized_text(
                $lang,
                "I'm **Vani**, here to help you with NIELIT Bhubaneswar! Try one of the quick options below or ask about ongoing courses, campus locations, portal roadmap, or course registration.",
                "मैं **वाणी** हूँ, NIELIT भुवनेश्वर में आपकी मदद के लिए! नीचे दिए गए त्वरित विकल्प चुनें या चालू कोर्स, कैंपस लोकेशन, रोडमैप या रजिस्ट्रेशन के बारे में पूछें।",
                "ମୁଁ **ବାଣୀ**, NIELIT ଭୁବନେଶ୍ୱରରେ ଆପଣଙ୍କୁ ସାହାଯ୍ୟ କରିବା ପାଇଁ ଅଛି! ତଳ ବିକଳ୍ପ ବାଛନ୍ତୁ କିମ୍ବା ଚାଲୁଥିବା କୋର୍ସ, କ୍ୟାମ୍ପସ୍ ସ୍ଥାନ, ରୋଡ୍‌ମ୍ୟାପ୍ କିମ୍ବା ପଞ୍ଜୀକରଣ ବିଷୟରେ ପଚାରନ୍ତୁ।"
            ),
            'mode' => 'local',
            'avatar_state' => 'happy',
            'lang' => $lang,
            'ui_action' => 'NONE',
            'quick_replies' => saarthi_default_quick_replies($lang),
            'cards' => [],
        ];
    }
}

if (!function_exists('saarthi_history_has_registration_prompt')) {
    /**
     * Check if the latest assistant turn in chat history asked for conversational registration fields.
     *
     * @param list<array{role: string, content: string}> $history
     */
    function saarthi_history_has_registration_prompt(array $history): bool
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $item = $history[$i];
            if (($item['role'] ?? '') === 'assistant' || ($item['role'] ?? '') === 'model') {
                $txt = (string) ($item['content'] ?? '');
                return (bool) preg_match(
                    '/Step\s*[1-5][a-z]?\s*of\s*[45]|Step\s*3b|चरण\s*[1-5][a-z]?\/[45]|ସୋପାନ\s*[୧-୫1-5][a-z]?\/[୪୫45]|Full Name.*Mobile Number.*Email|Confirm.*Registration Summary/iu',
                    $txt
                );
            }
        }
        return false;
    }
}

if (!function_exists('portal_assistant_chat')) {
    /**
     * Main Saarthi assistant entry point.
     *
     * @param list<array{role: string, content: string}> $history
     * @return array<string, mixed>
     */
    function portal_assistant_chat(string $message, array $history, ?mysqli $conn = null, ?string $langHint = null): array
    {
        $message = trim($message);
        if (mb_strlen($message) > 2000) {
            $message = mb_substr($message, 0, 2000);
        }

        $lang = saarthi_detect_language($message, $langHint);
        $ctx = portal_assistant_site_context($conn);
        $config = portal_assistant_load_config($conn);
        $ctx['assistant_name'] = (string) ($config['assistant_name'] ?? 'Gyansetu');
        $actor = portal_assistant_actor_context();

        // 1. Sensitive payment safety guard across ALL providers & flows
        if (saarthi_has_sensitive_payment_input($message)) {
            return saarthi_local_reply_structured($message, $lang, $ctx, $conn);
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 2. If user is already inside Saarthi's stateful local chat registration draft, handle it BEFORE
        //    admin/email lookup actions so submitting "Name, Mobile, Email@gmail.com" in Step 2 is never hijacked.
        if (!empty($_SESSION['saarthi_reg']['active'])) {
            $flow = saarthi_handle_registration_flow($message, $lang, $ctx, $conn);
            if ($flow !== null) {
                return $flow;
            }
        }

        // 3. Authenticated actions: logged-in student own profile & logged-in staff email/Student ID lookup
        if ($conn instanceof mysqli) {
            require_once __DIR__ . '/portal_assistant_actions.php';
            $regIntent = saarthi_registration_intent($message);
            $actionCtx = $ctx + [
                'in_registration_flow' => !empty($_SESSION['saarthi_reg']['active'])
                    || !empty($regIntent['starts'])
                    || saarthi_history_has_registration_prompt($history),
            ];
            $action = portal_assistant_try_actions($conn, $message, $actor, $actionCtx);
            if ($action !== null) {
                return array_merge([
                    'avatar_state' => 'guide',
                    'lang' => $lang,
                    'ui_action' => 'NONE',
                    'quick_replies' => saarthi_default_quick_replies($lang),
                    'cards' => [],
                ], $action);
            }
        }

        // 4. Logged-in admin staff activity log report
        $staff = portal_assistant_admin_activity_reply($conn, $message);
        if ($staff !== null) {
            return [
                'reply' => $staff,
                'mode' => 'activity',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => saarthi_default_quick_replies($lang),
                'cards' => [],
            ];
        }

        $provider = strtolower((string) ($config['ai_provider'] ?? 'auto'));

        // 5. Explicit or Auto AI routing (n8n -> Gemini -> OpenAI -> Local En/Hi/Or engine)
        if ($provider === 'local') {
            return saarthi_local_reply_structured($message, $lang, $ctx, $conn);
        }

        if (($provider === 'auto' || $provider === 'n8n') && $conn instanceof mysqli && function_exists('automationPortalChatRequest')) {
            require_once __DIR__ . '/automation_webhook_helper.php';
            $n8n = automationPortalChatRequest($conn, $message, $history, $actor, $ctx + ['active_lang' => $lang]);
            if (is_array($n8n) && !empty($n8n['reply'])) {
                $parsed = saarthi_parse_model_output($n8n['reply'], $n8n['lang'] ?: $lang, $n8n);
                $parsed['mode'] = 'n8n';
                return $parsed;
            }
            if (is_string($n8n) && trim($n8n) !== '') {
                $parsed = saarthi_parse_model_output($n8n, $lang);
                $parsed['mode'] = 'n8n';
                return $parsed;
            }
        }

        if ($provider === 'auto' || $provider === 'gemini') {
            $gem = saarthi_gemini_reply($message, $history, $lang, $ctx, $config, $conn);
            if ($gem !== null) {
                return $gem;
            }
        }

        if ($provider === 'auto' || $provider === 'openai') {
            $oai = portal_assistant_openai_reply($message, $history, $lang, $ctx, $config, $conn);
            if ($oai !== null) {
                return $oai;
            }
        }

        return saarthi_local_reply_structured($message, $lang, $ctx, $conn);
    }
}
