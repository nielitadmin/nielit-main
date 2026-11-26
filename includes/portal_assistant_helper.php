<?php
/**
 * Saarthi AI Campus & Portal Assistant — Orchestrator
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

        $langLabel = $lang === 'hi' ? 'Hindi (हिन्दी)' : ($lang === 'or' ? 'Odia (ଓଡ଼ିଆ)' : 'English');

        return "You are **Saarthi**, the intelligent, friendly, and proactive AI Campus & Portal Assistant for {$ctx['site_name']}.
Your job is to guide visitors across the website, explain how the portal works using clear 4-step roadmaps, answer live queries about ongoing courses and center locations, and assist students with course registrations conversationally.

ACTIVE LANGUAGE: {$langLabel}. Always reply in the user's language (English, Hindi / हिन्दी Devanagari, or Odia / ଓଡ଼ିଆ script), and switch immediately if asked (e.g. 'Odia re kuha', 'Hindi mein batao', 'Switch to English'). Keep technical terms (O-Level, CCC, AI/ML, NSQF, PDF, APAAR ID) clear. Keep replies warm, counselor-like, and concise.

PORTAL & HELPDESK FACTS:
- Active Campuses / Centres in DB: {$centresLine}
- Portal links: Courses = {$ctx['courses_page']}, Student Login = {$ctx['student_login']}, Full Admission Form = {$ctx['student_register']}, Payment Portal = {$ctx['payment_portal']}, Mock Test = {$ctx['mock_test']}
- Helpdesk Phone: {$help['phone']} | Emails: {$help['email']}, {$help['director_email']} ({$help['hours']})
- News hints: " . ($ctx['latest_news'] ?? 'See Homepage News section') . "

WORKFLOWS:
- Task A (Site Roadmap): When asked how the site works or where to start, call `get_site_roadmap` or present the 4-Step Roadmap (Explore Courses -> Check Batch & Seat Status -> Register Online / via Saarthi -> Download Admission Form / Receipt) and include `[UI_ACTION: SHOW_ROADMAP]`.
- Task B (Live Course Search): When asked about ongoing courses, fees, duration, batch timings, seats, or locations, ALWAYS call `search_courses(keyword, location, status)` instead of guessing. Never fabricate dates, fees, or seat counts.
- Task C (Conversational Registration): Collect details 1-2 short questions per turn:
  Step 1: Course Name + Preferred Center Location
  Step 2: Full Name + Mobile Number (10 digits) + Email Address
  Step 3: Date of Birth + Gender + Highest Educational Qualification
  Step 4: APAAR ID (optional) + Category (General/OBC/SC/ST/EWS) & PwD Status (Yes/No) + Correspondence Address
  Then show a concise Registration Summary Card and ask the user to reply 'Confirm'. ONLY AFTER explicit confirmation, call `register_student(...)` to save to MySQL, return their Student ID, and trigger `[UI_ACTION: DOWNLOAD_PDF]`.
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
        $model = trim((string) ($config['gemini_model'] ?? 'gemini-2.0-flash')) ?: 'gemini-2.0-flash';
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
                    'description' => 'Save a confirmed student course registration into MySQL. ONLY call AFTER showing a summary card and receiving explicit user confirmation.',
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
                            'qualification' => ['type' => 'STRING'],
                            'apaar_id' => ['type' => 'STRING'],
                            'category' => ['type' => 'STRING'],
                            'pwd_status' => ['type' => 'STRING'],
                            'address' => ['type' => 'STRING'],
                        ],
                        'required' => ['course_name', 'name', 'mobile', 'email', 'dob', 'gender', 'qualification', 'address'],
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
                    'description' => 'Register a confirmed student in MySQL. Only call after explicit user confirmation.',
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
                            'qualification' => ['type' => 'string'],
                            'apaar_id' => ['type' => 'string'],
                            'category' => ['type' => 'string'],
                            'pwd_status' => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                        ],
                        'required' => ['course_name', 'name', 'mobile', 'email', 'dob', 'gender', 'qualification', 'address'],
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
            && !preg_match('/\b(in\s+chat|via\s+saarthi|start|want\s+to\s+register|register\s+me|register\s+now)\b/iu', $mLower);
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

if (!function_exists('saarthi_handle_registration_flow')) {
    /**
     * Stateful 4-step conversational registration state machine (supports EN/HI/OR + prefilled course buttons).
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

        // Allow user to abort registration cleanly at any time
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
                'course_id' => 0,
                'course_name' => '',
                'center_location' => '',
                'name' => '',
                'mobile' => '',
                'email' => '',
                'dob' => '',
                'gender' => '',
                'qualification' => '',
                'apaar_id' => '',
                'category' => '',
                'pwd_status' => '',
                'address' => '',
                'awaiting_confirm' => false,
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
                    "Great choice! Let's register you for **{$reg['course_name']}** ({$reg['center_location']}).\n\nStep 2 of 4: Please share your **Full Name**, **10-digit Mobile Number**, and **Email Address**.",
                    "बहुत बढ़िया! आइए **{$reg['course_name']}** ({$reg['center_location']}) के लिए आपका रजिस्ट्रेशन पूरा करें।\n\nचरण 2/4: कृपया अपना **पूरा नाम**, **10-अंकों का मोबाइल नंबर**, और **ईमेल पता** लिखें।",
                    "ବହୁତ ବଢ଼ିଆ! ଆସନ୍ତୁ **{$reg['course_name']}** ({$reg['center_location']}) ପାଇଁ ଆପଣଙ୍କ ପଞ୍ଜୀକରଣ ଆରମ୍ଭ କରିବା।\n\nସୋପାନ ୨/୪: ଦୟାକରି ଆପଣଙ୍କର **ସମ୍ପୂର୍ଣ୍ଣ ନାମ**, **୧୦-ଅଙ୍କ ବିଶିଷ୍ଟ ମୋବାଇଲ୍ ନମ୍ବର**, ଏବଂ **ଇମେଲ୍ ଠିକଣା** ଦିଅନ୍ତୁ।"
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

            // Step 1 prompt: show top ongoing courses as clickable chips & ask Course + Center Location
            $catalog = saarthi_search_courses($conn, '', $reg['center_location'], 'ongoing');
            $courseChips = [];
            foreach (array_slice($catalog['courses'] ?? [], 0, 3) as $c) {
                $courseChips[] = $c['course_name'] . ' — ' . $c['campus'];
            }
            if ($courseChips === []) {
                $courseChips = ['Cancel'];
            }
            $reply = saarthi_localized_text(
                $lang,
                "I'd love to help you register! Let's do this in 4 quick steps.\n\n**Step 1 of 4:** Which **Course Name** and **Preferred Campus Location** (e.g., Bhubaneswar, Baleshwar, Raipur) would you like to join?",
                "मैं आपका रजिस्ट्रेशन करने के लिए तैयार हूँ! यह प्रक्रिया 4 आसान चरणों में होगी।\n\n**चरण 1/4:** आप किस **कोर्स** और **कैंपस लोकेशन** (जैसे Bhubaneswar, Baleshwar, Raipur) में प्रवेश लेना चाहते हैं?",
                "ମୁଁ ଆପଣଙ୍କର ପଞ୍ଜୀକରଣରେ ସାହାଯ୍ୟ କରିବାକୁ ପ୍ରସ୍ତୁତ! ଏହା ୪ଟି ସହଜ ସୋପାନରେ ହେବ।\n\n**ସୋପାନ ୧/୪:** ଆପଣ କେଉଁ **କୋର୍ସ** ଏବଂ **କ୍ୟାମ୍ପସ୍ ସ୍ଥାନ** (ଯଥା Bhubaneswar, Baleshwar, Raipur) ରେ ନାମ ଲେଖାଇବାକୁ ଚାହୁଁଛନ୍ତି?"
            );
            return [
                'reply' => $reply,
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'OPEN_REGISTRATION',
                'quick_replies' => $courseChips,
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
                $hasPdf = !empty($saved['download_pdf_url']);
                $reply = !empty($saved['already_registered'])
                    ? (string) ($saved['message'] ?? '')
                    : saarthi_localized_text(
                        $lang,
                        "Congratulations! Your registration for **{$cName}** is submitted.\n**Student / Registration ID:** `{$sid}`\nStatus: Pending document verification. You can download your pre-filled Admission Form PDF right below!",
                        "बधाई हो! **{$cName}** के लिए आपका रजिस्ट्रेशन सफल रहा।\n**Student / Registration ID:** `{$sid}`\nस्थिति: दस्तावेज़ सत्यापन शेष है। आप नीचे अपना भरा हुआ एडमिशन फॉर्म PDF डाउनलोड कर सकते हैं!",
                        "ଅଭିନନ୍ଦନ! **{$cName}** ପାଇଁ ଆପଣଙ୍କର ପଞ୍ଜୀକରଣ ସଫଳ ହୋଇଛି।\n**Student / Registration ID:** `{$sid}`\nସ୍ଥିତି: ଡକ୍ୟୁମେଣ୍ଟ୍ ଯାଞ୍ଚ ବାକି ଅଛି। ଆପଣ ତଳୁ ଆପଣଙ୍କର ଆଡମିଶନ୍ ଫର୍ମ PDF ଡାଉନଲୋଡ୍ କରିପାରିବେ!"
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
            $kw = $intent['keyword'] !== '' ? $intent['keyword'] : trim(explode('—', $message)[0]);
            $search = saarthi_search_courses($conn, $kw, $reg['center_location'], 'ongoing');
            if (!empty($search['exact_match']) && !empty($search['courses'][0])) {
                $picked = $search['courses'][0];
                $reg['course_id'] = (int) $picked['id'];
                $reg['course_name'] = (string) $picked['course_name'];
                if ($reg['center_location'] === '') {
                    $reg['center_location'] = (string) ($picked['campus'] ?? 'NIELIT Bhubaneswar');
                }
                $reg['step'] = 2;
                $_SESSION['saarthi_reg'] = $reg;
                $reply = saarthi_localized_text(
                    $lang,
                    "Selected **{$reg['course_name']}** at **{$reg['center_location']}**.\n\n**Step 2 of 4:** Please share your **Full Name**, **10-digit Mobile Number**, and **Email Address**.",
                    "चयनित कोर्स: **{$reg['course_name']}** (**{$reg['center_location']}**).\n\n**चरण 2/4:** कृपया अपना **पूरा नाम**, **10-अंकों का मोबाइल नंबर**, और **ईमेल पता** बताएं।",
                    "ଚୟନିତ କୋର୍ସ: **{$reg['course_name']}** (**{$reg['center_location']}**).\n\n**ସୋପାନ ୨/୪:** ଦୟାକରି ଆପଣଙ୍କର **ସମ୍ପୂର୍ଣ୍ଣ ନାମ**, **୧୦-ଅଙ୍କ ମୋବାଇଲ୍ ନମ୍ବର**, ଏବଂ **ଇମେଲ୍ ଠିକଣା** ଦିଅନ୍ତୁ।"
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
            $courseChips = array_map(static fn($c) => $c['course_name'] . ' — ' . $c['campus'], array_slice($search['courses'] ?? [], 0, 3));
            $reply = saarthi_localized_text(
                $lang,
                "I couldn't find that exact course. Please tap or type one of these open courses along with your preferred campus:",
                "यह सटीक कोर्स नहीं मिला। कृपया नीचे दिए गए उपलब्ध कोर्स में से एक चुनें या नाम लिखें:",
                "ସଠିକ୍ କୋର୍ସ ମିଳିଲା ନାହିଁ। ଦୟାକରି ତଳେ ଥିବା ଉପଲବ୍ଧ କୋର୍ସଗୁଡ଼ିକ ମଧ୍ୟରୁ ଗୋଟିଏ ବାଛନ୍ତୁ କିମ୍ବା ନାମ ଲେଖନ୍ତୁ:"
            );
            return [
                'reply' => $reply,
                'mode' => 'local',
                'avatar_state' => 'thinking',
                'lang' => $lang,
                'ui_action' => 'OPEN_REGISTRATION',
                'quick_replies' => $courseChips ?: ['Cancel'],
                'cards' => !empty($search['courses']) ? ['courses' => array_slice($search['courses'], 0, 4)] : [],
            ];
        }

        // Step 2: Full Name, Mobile Number, Email Address
        if ((int) $reg['step'] === 2) {
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
            $nameCand = trim((string) preg_replace('/\b(my name is|name|mobile|phone|email|mera naam)\b[:\s]*/i', ' ', $message));
            $nameCand = trim((string) preg_replace('/[,;|\-]+/', ' ', $nameCand));
            $nameCand = trim((string) preg_replace('/\s+/', ' ', $nameCand));
            if ($nameCand !== '' && mb_strlen($nameCand) >= 2 && !preg_match('/^\d+$/', $nameCand)) {
                $reg['name'] = $nameCand;
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
                $needStr = implode(', ', $missing);
                return [
                    'reply' => saarthi_localized_text(
                        $lang,
                        "Almost got Step 2! Please also provide: **{$needStr}**.",
                        "चरण 2 लगभग पूरा है! कृपया **{$needStr}** भी बताएं।",
                        "ସୋପାନ ୨ ପ୍ରାୟ ସରିଲାଣି! ଦୟାକରି **{$needStr}** ମଧ୍ୟ ଦିଅନ୍ତୁ।"
                    ),
                    'mode' => 'local',
                    'avatar_state' => 'guide',
                    'lang' => $lang,
                    'ui_action' => 'NONE',
                    'quick_replies' => [$lang === 'hi' ? 'रद्द करें' : ($lang === 'or' ? 'ବାତିଲ୍ କରନ୍ତୁ' : 'Cancel')],
                    'cards' => [],
                ];
            }

            $reg['step'] = 3;
            $_SESSION['saarthi_reg'] = $reg;
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "Thanks, **{$reg['name']}**!\n\n**Step 3 of 4:** Please enter your **Date of Birth** (DD-MM-YYYY), **Gender** (Male/Female/Other), and **Highest Educational Qualification** (e.g., 12th, Diploma, B.Tech, Graduation).",
                    "धन्यवाद, **{$reg['name']}**!\n\n**चरण 3/4:** कृपया अपनी **जन्म तिथि** (DD-MM-YYYY), **लिंग** (Male/Female/Other), और **उच्चतम शैक्षणिक योग्यता** (जैसे 12वीं, डिप्लोमा, ग्रेजुएशन) बताएं।",
                    "ଧନ୍ୟବାଦ, **{$reg['name']}**!\n\n**ସୋପାନ ୩/୪:** ଦୟାକରି ଆପଣଙ୍କର **ଜନ୍ମ ତାରିଖ** (DD-MM-YYYY), **ଲିଙ୍ଗ** (Male/Female/Other), ଏବଂ **ସର୍ବୋଚ୍ଚ ଶିକ୍ଷାଗତ ଯୋଗ୍ୟତା** (ଯଥା +2, ଡିପ୍ଲୋମା, ଗ୍ରାଜୁଏସନ୍) ଦିଅନ୍ତୁ।"
                ),
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => [$lang === 'hi' ? 'रद्द करें' : ($lang === 'or' ? 'ବାତିଲ୍ କରନ୍ତୁ' : 'Cancel')],
                'cards' => [],
            ];
        }

        // Step 3: DOB, Gender, Qualification
        if ((int) $reg['step'] === 3) {
            if (preg_match('/\b(\d{4}[-\/]\d{1,2}[-\/]\d{1,2}|\d{1,2}[-\/\.]\d{1,2}[-\/\.]\d{4})\b/', $message, $dm)) {
                $parsedDob = saarthi_parse_date_input(str_replace('.', '-', $dm[1]));
                if ($parsedDob !== null) {
                    $reg['dob'] = $parsedDob;
                    $message = str_replace($dm[0], ' ', $message);
                }
            }
            if (preg_match('/\b(female|woman|girl|mahila)\b|महिला|ମହିଳା/iu', $message, $gm)) {
                $reg['gender'] = 'Female';
                $message = str_ireplace($gm[0], ' ', $message);
            } elseif (preg_match('/\b(male|man|boy|purush)\b|पुरुष|ପୁରୁଷ/iu', $message, $gm)) {
                $reg['gender'] = 'Male';
                $message = str_ireplace($gm[0], ' ', $message);
            } elseif (preg_match('/\b(other|non[\s\-]?binary)\b/iu', $message, $gm)) {
                $reg['gender'] = 'Other';
                $message = str_ireplace($gm[0], ' ', $message);
            }
            $qualCand = trim((string) preg_replace('/\b(dob|date of birth|gender|qualification|highest|education)\b[:\s]*/i', ' ', $message));
            $qualCand = trim((string) preg_replace('/[,;|]+/', ' ', $qualCand));
            $qualCand = trim((string) preg_replace('/\s+/', ' ', $qualCand));
            if ($qualCand !== '' && mb_strlen($qualCand) >= 2 && !preg_match('/^\d{1,4}$/', $qualCand)) {
                $reg['qualification'] = $qualCand;
            }

            $missing = [];
            if ($reg['dob'] === '') {
                $missing[] = saarthi_localized_text($lang, 'Date of Birth (DD-MM-YYYY)', 'जन्म तिथि (DD-MM-YYYY)', 'ଜନ୍ମ ତାରିଖ (DD-MM-YYYY)');
            }
            if ($reg['gender'] === '') {
                $missing[] = saarthi_localized_text($lang, 'Gender (Male/Female/Other)', 'लिंग (Male/Female/Other)', 'ଲିଙ୍ଗ (Male/Female/Other)');
            }
            if ($reg['qualification'] === '') {
                $missing[] = saarthi_localized_text($lang, 'Highest Qualification', 'शैक्षणिक योग्यता', 'ଶିକ୍ଷାଗତ ଯୋଗ୍ୟତା');
            }
            if ($missing !== []) {
                $_SESSION['saarthi_reg'] = $reg;
                $needStr = implode(', ', $missing);
                return [
                    'reply' => saarthi_localized_text(
                        $lang,
                        "Got it! Please also share: **{$needStr}**.",
                        "समझ गया! कृपया यह भी बताएं: **{$needStr}**।",
                        "ବୁଝିଗଲି! ଦୟାକରି ଏହା ମଧ୍ୟ ଦିଅନ୍ତୁ: **{$needStr}**।"
                    ),
                    'mode' => 'local',
                    'avatar_state' => 'guide',
                    'lang' => $lang,
                    'ui_action' => 'NONE',
                    'quick_replies' => [$lang === 'hi' ? 'रद्द करें' : ($lang === 'or' ? 'ବାତିଲ୍ କରନ୍ତୁ' : 'Cancel')],
                    'cards' => [],
                ];
            }

            $reg['step'] = 4;
            $_SESSION['saarthi_reg'] = $reg;
            return [
                'reply' => saarthi_localized_text(
                    $lang,
                    "**Step 4 of 4 (Final Step):** Please share:\n• **APAAR ID** (optional — write \"Skip\" if you don't have one)\n• **Category** (General / OBC / SC / ST / EWS) & **PwD Status** (Yes / No)\n• **Full Correspondence Address** (with PIN code if available)",
                    "**चरण 4/4 (अंतिम चरण):** कृपया ये विवरण दें:\n• **APAAR ID** (वैकल्पिक — न हो तो \"Skip\" लिखें)\n• **श्रेणी** (General / OBC / SC / ST / EWS) और **PwD स्थिति** (Yes / No)\n• **पूरा पत्राचार पता** (पिन कोड सहित)",
                    "**ସୋପାନ ୪/୪ (ଶେଷ ସୋପାନ):** ଦୟାକରି ଏହି ତଥ୍ୟ ଦିଅନ୍ତୁ:\n• **APAAR ID** (ଇଚ୍ଛାଧୀନ — ନଥିଲେ \"Skip\" ଲେଖନ୍ତୁ)\n• **ବର୍ଗ** (General / OBC / SC / ST / EWS) ଏବଂ **PwD ସ୍ଥିତି** (Yes / No)\n• **ସମ୍ପୂର୍ଣ୍ଣ ଠିକଣା** (PIN କୋଡ୍ ସହିତ)"
                ),
                'mode' => 'local',
                'avatar_state' => 'guide',
                'lang' => $lang,
                'ui_action' => 'NONE',
                'quick_replies' => ['General, PwD No, Skip APAAR', 'OBC, PwD No, Skip APAAR', 'SC/ST, PwD No, Skip APAAR'],
                'cards' => [],
            ];
        }

        // Step 4: APAAR ID (optional), Category, PwD, Correspondence Address
        if ((int) $reg['step'] === 4) {
            $work = $message;
            if (preg_match('/\b(\d{12})\b/', $work, $ap)) {
                $reg['apaar_id'] = $ap[1];
                $work = str_replace($ap[0], ' ', $work);
            } elseif (preg_match('/\b(skip\s*apaar|no\s*apaar|apaar\s*na|skip)\b/i', $work, $sk)) {
                $reg['apaar_id'] = 'SKIPPED';
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

            $addrCand = trim((string) preg_replace('/\b(apaar(\s*id)?|category|address)\b[:\s]*/i', ' ', $work));
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
                return [
                    'reply' => saarthi_localized_text(
                        $lang,
                        'Got your category details! Please type your **Full Correspondence Address** (locality, city, state, PIN code) so I can prepare your summary.',
                        'श्रेणी विवरण सहेज लिया गया है! कृपया अपना **पूरा पत्राचार पता** (शहर, राज्य और पिन कोड सहित) लिखें।',
                        'ଆପଣଙ୍କ ବର୍ଗ ତଥ୍ୟ ସେଭ୍ ହୋଇଗଲା! ଦୟାକରି ଆପଣଙ୍କର **ସମ୍ପୂର୍ଣ୍ଣ ଠିକଣା** (ସହର, ରାଜ୍ୟ ଏବଂ PIN କୋଡ୍ ସହିତ) ଲେଖନ୍ତୁ।'
                    ),
                    'mode' => 'local',
                    'avatar_state' => 'guide',
                    'lang' => $lang,
                    'ui_action' => 'NONE',
                    'quick_replies' => [$lang === 'hi' ? 'रद्द करें' : ($lang === 'or' ? 'ବାତିଲ୍ କରନ୍ତୁ' : 'Cancel')],
                    'cards' => [],
                ];
            }

            if ($reg['apaar_id'] === 'SKIPPED') {
                $reg['apaar_id'] = '';
            }
            $reg['awaiting_confirm'] = true;
            $_SESSION['saarthi_reg'] = $reg;

            $summary = [
                'course_name' => $reg['course_name'],
                'center_location' => $reg['center_location'],
                'name' => $reg['name'],
                'mobile' => $reg['mobile'],
                'email' => $reg['email'],
                'dob' => $reg['dob'],
                'gender' => $reg['gender'],
                'qualification' => $reg['qualification'],
                'apaar_id' => $reg['apaar_id'] ?: 'N/A',
                'category' => $reg['category'],
                'pwd_status' => $reg['pwd_status'],
                'address' => $reg['address'],
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
                    "Switched to **English**! I'm **Saarthi**, your NIELIT Bhubaneswar portal guide. How can I assist you today?",
                    "बिल्कुल! अब मैं **हिन्दी** में बात करूँगा। नमस्ते, मैं **सारथी** हूँ — NIELIT भुवनेश्वर पोर्टल का आपका सहायक। आज मैं आपकी क्या मदद कर सकता हूँ?",
                    "ନିଶ୍ଚୟ! ବର୍ତ୍ତମାନ ମୁଁ **ଓଡ଼ିଆ** ରେ କଥା ହେବି। ନମସ୍କାର, ମୁଁ **ସାରଥୀ** — NIELIT ଭୁବନେଶ୍ୱର ପୋର୍ଟାଲର ଆପଣଙ୍କ ସହାୟକ। ଆଜି ମୁଁ ଆପଣଙ୍କୁ କିପରି ସାହାଯ୍ୟ କରିପାରିବି?"
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
                    "Namaste! I'm **Saarthi**, your NIELIT Bhubaneswar Campus & Portal Guide.\nI can explain the 4-step portal roadmap, check live courses & campus seats, or help you register right here in chat!",
                    "नमस्ते! मैं **सारथी** हूँ — NIELIT भुवनेश्वर पोर्टल का आपका AI मार्गदर्शक।\nमैं पोर्टल का 4-चरणीय रोडमैप बता सकता हूँ, लाइव कोर्स और कैंपस सीटें दिखा सकता हूँ, या यहीं चैट में आपका रजिस्ट्रेशन कर सकता हूँ!",
                    "ନମସ୍କାର! ମୁଁ **ସାରଥୀ** — NIELIT ଭୁବନେଶ୍ୱର ପୋର୍ଟାଲର ଆପଣଙ୍କ ସହାୟକ।\nମୁଁ ଆପଣଙ୍କୁ ପୋର୍ଟାଲର ୪-ସୋପାନ ରୋଡ୍‌ମ୍ୟାପ୍ ବୁଝାଇପାରିବି, ଲାଇଭ୍ କୋର୍ସ ଓ କ୍ୟାମ୍ପସ୍ ସିଟ୍ ଦେଖାଇପାରିବି, କିମ୍ବା ଏଠାରେ ଚାଟ୍‌ରେ ପଞ୍ଜୀକରଣ କରାଇପାରିବି!"
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
                    "We currently have **{$cntOngoing} ongoing** courses ({$cntPub} total published) across NIELIT campuses. Here are the live matching courses — click **Register via Saarthi** on any card to apply in chat!",
                    "NIELIT कैंपस में अभी **{$cntOngoing} चालू (ongoing)** कोर्स हैं (कुल {$cntPub} प्रकाशित)। नीचे लाइव कोर्स विवरण देखें — सीधे चैट में आवेदन करने के लिए **Register via Saarthi** पर क्लिक करें!",
                    "NIELIT କ୍ୟାମ୍ପସ୍‌ରେ ବର୍ତ୍ତମାନ **{$cntOngoing}ଟି ଚାଲୁଥିବା** କୋର୍ସ ଅଛି (ମୋଟ {$cntPub}ଟି ପ୍ରକାଶିତ)। ତଳେ ଲାଇଭ୍ କୋର୍ସ ତଥ୍ୟ ଦେଖନ୍ତୁ — ଚାଟ୍‌ରେ ଆବେଦନ କରିବାକୁ **Register via Saarthi** କ୍ଲିକ୍ କରନ୍ତୁ!"
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
                "I'm **Saarthi**, here to help you with NIELIT Bhubaneswar! Try one of the quick options below or ask about ongoing courses, campus locations, portal roadmap, or course registration.",
                "मैं **सारथी** हूँ, NIELIT भुवनेश्वर में आपकी मदद के लिए! नीचे दिए गए त्वरित विकल्प चुनें या चालू कोर्स, कैंपस लोकेशन, रोडमैप या रजिस्ट्रेशन के बारे में पूछें।",
                "ମୁଁ **ସାରଥୀ**, NIELIT ଭୁବନେଶ୍ୱରରେ ଆପଣଙ୍କୁ ସାହାଯ୍ୟ କରିବା ପାଇଁ ଅଛି! ତଳ ବିକଳ୍ପ ବାଛନ୍ତୁ କିମ୍ବା ଚାଲୁଥିବା କୋର୍ସ, କ୍ୟାମ୍ପସ୍ ସ୍ଥାନ, ରୋଡ୍‌ମ୍ୟାପ୍ କିମ୍ବା ପଞ୍ଜୀକରଣ ବିଷୟରେ ପଚାରନ୍ତୁ।"
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
        $actor = portal_assistant_actor_context();

        // 1. Authenticated actions: logged-in student own profile & logged-in staff email/Student ID lookup
        if ($conn instanceof mysqli) {
            require_once __DIR__ . '/portal_assistant_actions.php';
            $action = portal_assistant_try_actions($conn, $message, $actor, $ctx);
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

        // 2. Logged-in admin staff activity log report
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

        // 3. Sensitive payment safety guard across ALL providers
        if (saarthi_has_sensitive_payment_input($message)) {
            return saarthi_local_reply_structured($message, $lang, $ctx, $conn);
        }

        // 4. If user is already inside Saarthi's stateful local chat registration draft, keep the flow deterministic
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (!empty($_SESSION['saarthi_reg']['active'])) {
            $flow = saarthi_handle_registration_flow($message, $lang, $ctx, $conn);
            if ($flow !== null) {
                return $flow;
            }
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
