<?php
/**
 * Saarthi AI Assistant — Live database tools & workflows:
 * - Course & campus location search (courses, centres, batches)
 * - Conversational student registration -> MySQL (students, student_accounts, student_enrollments, education_details)
 * - Roadmap payloads (English, Hindi, Odia)
 * - Support callback logging (support_tickets)
 */

if (!function_exists('saarthi_table_exists')) {
    function saarthi_table_exists(mysqli $conn, string $table): bool
    {
        $esc = $conn->real_escape_string($table);
        $res = @$conn->query("SHOW TABLES LIKE '{$esc}'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('saarthi_column_exists')) {
    function saarthi_column_exists(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $escTable = $conn->real_escape_string($table);
        $escCol = $conn->real_escape_string($column);
        $res = @$conn->query("SHOW COLUMNS FROM `{$escTable}` LIKE '{$escCol}'");
        $cache[$key] = ($res && $res->num_rows > 0);
        return $cache[$key];
    }
}

if (!function_exists('saarthi_get_column_type')) {
    function saarthi_get_column_type(mysqli $conn, string $table, string $column): string
    {
        $escTable = $conn->real_escape_string($table);
        $escCol = $conn->real_escape_string($column);
        $res = @$conn->query("SHOW COLUMNS FROM `{$escTable}` LIKE '{$escCol}'");
        if ($res && ($row = $res->fetch_assoc())) {
            return strtolower((string) ($row['Type'] ?? ''));
        }
        return '';
    }
}

if (!function_exists('saarthi_normalize_lang')) {
    function saarthi_normalize_lang(?string $lang): string
    {
        $l = strtolower(trim((string) $lang));
        if (in_array($l, ['hi', 'hindi', 'hin'], true)) {
            return 'hi';
        }
        if (in_array($l, ['or', 'od', 'odia', 'oriya'], true)) {
            return 'or';
        }
        return 'en';
    }
}

if (!function_exists('saarthi_lower')) {
    /**
     * UTF-8 safe lowercase conversion (avoids single-byte locale corruption of multibyte scripts on Windows).
     */
    function saarthi_lower(string $s): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($s, 'UTF-8');
        }
        return strtr($s, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}

if (!function_exists('saarthi_helpdesk_info')) {
    /**
     * @return array<string, string>
     */
    function saarthi_helpdesk_info(): array
    {
        if (!defined('SUPPORT_ENQUIRY_EMAIL') && is_file(__DIR__ . '/institute_branding.php')) {
            require_once __DIR__ . '/institute_branding.php';
        }
        return [
            'phone' => '0674-2960354',
            'email' => defined('SUPPORT_ENQUIRY_EMAIL') ? SUPPORT_ENQUIRY_EMAIL : 'admin@nielitbhubaneswar.in',
            'director_email' => 'dir-bbsr@nielit.gov.in',
            'hours' => 'Monday – Friday, 09:00 AM – 05:30 PM',
            'address' => '3rd Floor, OCAC Tower, Acharya Vihar, Bhubaneswar - 751013, Odisha',
        ];
    }
}

if (!function_exists('saarthi_get_roadmap')) {
    /**
     * Structured 4-step portal roadmap in EN / HI / OR.
     *
     * @return array<string, mixed>
     */
    function saarthi_get_roadmap(string $lang, array $ctx = []): array
    {
        $lang = saarthi_normalize_lang($lang);
        $coursesUrl = $ctx['courses_page'] ?? (function_exists('app_url') ? app_url('public/courses') : '/public/courses.php');
        $loginUrl = $ctx['student_login'] ?? (function_exists('app_url') ? app_url('student/login') : '/student/login.php');

        $copies = [
            'en' => [
                'title' => '4-Step Portal Roadmap',
                'subtitle' => 'Everything from course discovery to your admission PDF:',
                'steps' => [
                    [
                        'step' => 1,
                        'title' => 'Explore Courses',
                        'desc' => 'Browse ongoing & upcoming programs by campus location, duration, and eligibility.',
                        'action_label' => 'Browse Courses',
                        'action_type' => 'scroll_courses',
                        'url' => $coursesUrl,
                    ],
                    [
                        'step' => 2,
                        'title' => 'Check Batch & Seat Status',
                        'desc' => 'Ask me or open a course card to see start dates, timings, fees, and live seat availability.',
                        'action_label' => 'Check Live Batches',
                        'action_type' => 'ask',
                        'question' => 'Show ongoing courses with seats and fees',
                    ],
                    [
                        'step' => 3,
                        'title' => 'Register Online (or via Saarthi)',
                        'desc' => 'Complete the portal admission form or register step-by-step right inside this chat.',
                        'action_label' => 'Register in Chat',
                        'action_type' => 'start_register',
                        'question' => 'I want to register for a course',
                    ],
                    [
                        'step' => 4,
                        'title' => 'Download Admission Form / Receipt',
                        'desc' => 'Instantly generate & download your pre-filled admission PDF or log in for fee receipts.',
                        'action_label' => 'Student Portal',
                        'action_type' => 'link',
                        'url' => $loginUrl,
                    ],
                ],
            ],
            'hi' => [
                'title' => '4-चरणीय पोर्टल रोडमैप',
                'subtitle' => 'कोर्स खोजने से लेकर एडमिशन PDF डाउनलोड तक की पूरी प्रक्रिया:',
                'steps' => [
                    [
                        'step' => 1,
                        'title' => 'कोर्स देखें (Explore Courses)',
                        'desc' => 'कैंपस लोकेशन, अवधि और योग्यता के अनुसार चल रहे और आगामी कोर्स देखें।',
                        'action_label' => 'कोर्स देखें',
                        'action_type' => 'scroll_courses',
                        'url' => $coursesUrl,
                    ],
                    [
                        'step' => 2,
                        'title' => 'बैच और सीट स्थिति जांचें',
                        'desc' => 'प्रारंभ तिथि, समय, शुल्क और उपलब्ध सीटों की लाइव जानकारी देखें।',
                        'action_label' => 'लाइव बैच देखें',
                        'action_type' => 'ask',
                        'question' => 'अभी कौन से कोर्स और सीटें उपलब्ध हैं?',
                    ],
                    [
                        'step' => 3,
                        'title' => 'ऑनलाइन या सारथी द्वारा रजिस्ट्रेशन',
                        'desc' => 'पोर्टल फॉर्म भरें या सीधे इस चैट में आसान स्टेप्स से रजिस्ट्रेशन पूरा करें।',
                        'action_label' => 'चैट में रजिस्टर करें',
                        'action_type' => 'start_register',
                        'question' => 'मुझे कोर्स के लिए रजिस्ट्रेशन करना है',
                    ],
                    [
                        'step' => 4,
                        'title' => 'एडमिशन फॉर्म / रसीद डाउनलोड करें',
                        'desc' => 'तुरंत अपना भरा हुआ एडमिशन PDF डाउनलोड करें और छात्र पोर्टल से रसीद प्राप्त करें।',
                        'action_label' => 'स्टूडेंट पोर्टल',
                        'action_type' => 'link',
                        'url' => $loginUrl,
                    ],
                ],
            ],
            'or' => [
                'title' => '୪-ସୋପାନ ପୋର୍ଟାଲ୍ ରୋଡ୍‌ମ୍ୟାପ୍',
                'subtitle' => 'କୋର୍ସ ଖୋଜିବାରୁ ଆଡମିଶନ୍ PDF ଡାଉନଲୋଡ୍ ପର୍ଯ୍ୟନ୍ତ ସମ୍ପୂର୍ଣ୍ଣ ପ୍ରକ୍ରିୟା:',
                'steps' => [
                    [
                        'step' => 1,
                        'title' => 'କୋର୍ସ ଦେଖନ୍ତୁ (Explore Courses)',
                        'desc' => 'କ୍ୟାମ୍ପସ୍ ସ୍ଥାନ, ଅବଧି ଏବଂ ଯୋଗ୍ୟତା ଅନୁସାରେ ଚାଲୁଥିବା ଓ ଆଗାମୀ କୋର୍ସ ଦେଖନ୍ତୁ।',
                        'action_label' => 'କୋର୍ସ ଦେଖନ୍ତୁ',
                        'action_type' => 'scroll_courses',
                        'url' => $coursesUrl,
                    ],
                    [
                        'step' => 2,
                        'title' => 'ବ୍ୟାଚ୍ ଓ ସିଟ୍ ସ୍ଥିତି ଯାଞ୍ଚ କରନ୍ତୁ',
                        'desc' => 'ଆରମ୍ଭ ତାରିଖ, ସମୟ, ଫିସ୍ ଏବଂ ଉପଲବ୍ଧ ସିଟ୍ ବିଷୟରେ ଲାଇଭ୍ ତଥ୍ୟ ଜାଣନ୍ତୁ।',
                        'action_label' => 'ଲାଇଭ୍ ବ୍ୟାଚ୍ ଦେଖନ୍ତୁ',
                        'action_type' => 'ask',
                        'question' => 'ବର୍ତ୍ତମାନ କେଉଁ କୋର୍ସ ଓ ସିଟ୍ ଉପଲବ୍ଧ ଅଛି?',
                    ],
                    [
                        'step' => 3,
                        'title' => 'ଅନଲାଇନ୍ କିମ୍ବା ସାରଥୀ ମାଧ୍ୟମରେ ପଞ୍ଜୀକରଣ',
                        'desc' => 'ପୋର୍ଟାଲ୍ ଫର୍ମ ପୂରଣ କରନ୍ତୁ କିମ୍ବା ଏହି ଚାଟ୍‌ରେ ସହଜରେ ପଞ୍ଜୀକରଣ କରନ୍ତୁ।',
                        'action_label' => 'ଚାଟ୍‌ରେ ପଞ୍ଜୀକରଣ କରନ୍ତୁ',
                        'action_type' => 'start_register',
                        'question' => 'ମୋତେ ଏକ କୋର୍ସରେ ପଞ୍ଜୀକରଣ କରିବାର ଅଛି',
                    ],
                    [
                        'step' => 4,
                        'title' => 'ଆଡମିଶନ୍ ଫର୍ମ / ରସିଦ୍ ଡାଉନଲୋଡ୍',
                        'desc' => 'ସଙ୍ଗେ ସଙ୍ଗେ ଆପଣଙ୍କର ପୂର୍ଣ୍ଣ ଆଡମିଶନ୍ PDF ଡାଉନଲୋଡ୍ କରନ୍ତୁ ଏବଂ ଷ୍ଟୁଡେଣ୍ଟ ପୋର୍ଟାଲ୍ ଦେଖନ୍ତୁ।',
                        'action_label' => 'ଷ୍ଟୁଡେଣ୍ଟ ପୋର୍ଟାଲ୍',
                        'action_type' => 'link',
                        'url' => $loginUrl,
                    ],
                ],
            ],
        ];

        return $copies[$lang] ?? $copies['en'];
    }
}

if (!function_exists('saarthi_list_centres')) {
    /**
     * List active training centres & locations from DB.
     *
     * @return list<array<string, mixed>>
     */
    function saarthi_list_centres(mysqli $conn): array
    {
        if (is_file(__DIR__ . '/institute_branding.php')) {
            require_once __DIR__ . '/institute_branding.php';
        }
        $centres = [];
        if (saarthi_table_exists($conn, 'centres')) {
            $res = @$conn->query('SELECT id, name, code, address, city, state, phone, email FROM centres WHERE is_active = 1 ORDER BY id ASC');
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $name = trim((string) ($row['name'] ?? ''));
                    if (function_exists('normalize_nielit_centre_name')) {
                        $name = normalize_nielit_centre_name($name);
                    }
                    $centres[] = [
                        'id' => (int) ($row['id'] ?? 0),
                        'name' => $name,
                        'code' => (string) ($row['code'] ?? ''),
                        'city' => (string) ($row['city'] ?? ''),
                        'state' => (string) ($row['state'] ?? ''),
                        'address' => (string) ($row['address'] ?? ''),
                        'phone' => (string) ($row['phone'] ?? ''),
                        'email' => (string) ($row['email'] ?? ''),
                    ];
                }
            }
        }
        return $centres;
    }
}

if (!function_exists('saarthi_search_courses')) {
    /**
     * Live DB query tool: search_courses(keyword, location, status)
     * Queries courses + centres + batches + active enrollment seat counts.
     *
     * @return array<string, mixed>
     */
    function saarthi_search_courses(mysqli $conn, string $keyword = '', string $location = '', string $status = 'all'): array
    {
        if (is_file(__DIR__ . '/institute_branding.php')) {
            require_once __DIR__ . '/institute_branding.php';
        }
        if (is_file(__DIR__ . '/course_public_display.php')) {
            require_once __DIR__ . '/course_public_display.php';
        }

        $keyword = trim($keyword);
        $location = trim($location);
        $status = strtolower(trim($status));
        if (!in_array($status, ['all', 'ongoing', 'open', 'closed', 'upcoming'], true)) {
            $status = 'all';
        }

        $hasCentres = saarthi_table_exists($conn, 'centres');
        $hasBatches = saarthi_table_exists($conn, 'batches');
        $hasPublished = saarthi_column_exists($conn, 'courses', 'link_published');
        $hasStatusCol = saarthi_column_exists($conn, 'courses', 'status');
        $hasEnrollmentStatus = saarthi_column_exists($conn, 'courses', 'enrollment_status');
        $hasClosingDate = saarthi_column_exists($conn, 'courses', 'enrollment_closing_date');
        $hasTrainingFees = saarthi_column_exists($conn, 'courses', 'training_fees');
        $hasFeesCol = saarthi_column_exists($conn, 'courses', 'fees');
        $hasDeliveryMode = saarthi_column_exists($conn, 'courses', 'delivery_mode');
        $hasCampusType = saarthi_column_exists($conn, 'courses', 'campus_type');
        $hasCentreId = saarthi_column_exists($conn, 'courses', 'centre_id');
        $hasDescription = saarthi_column_exists($conn, 'courses', 'course_description')
            ? 'c.course_description'
            : (saarthi_column_exists($conn, 'courses', 'description') ? 'c.description AS course_description' : "'' AS course_description");

        $feeExpr = $hasTrainingFees ? 'c.training_fees' : ($hasFeesCol ? 'c.fees AS training_fees' : "'' AS training_fees");
        $modeExpr = $hasDeliveryMode ? 'c.delivery_mode' : "'' AS delivery_mode";
        $campusExpr = $hasCampusType ? 'c.campus_type' : "'' AS campus_type";
        $enrStatusExpr = $hasEnrollmentStatus ? 'c.enrollment_status' : "'ongoing' AS enrollment_status";
        $closingExpr = $hasClosingDate ? 'c.enrollment_closing_date' : 'NULL AS enrollment_closing_date';
        $regTokenExpr = saarthi_column_exists($conn, 'courses', 'registration_token') ? 'c.registration_token' : "'' AS registration_token";
        $regFormExpr = saarthi_column_exists($conn, 'courses', 'registration_form') ? 'c.registration_form' : "'full' AS registration_form";
        $startExpr = saarthi_column_exists($conn, 'courses', 'start_date') ? 'c.start_date' : 'NULL AS start_date';
        $endExpr = saarthi_column_exists($conn, 'courses', 'end_date') ? 'c.end_date' : 'NULL AS end_date';

        $centreJoin = ($hasCentres && $hasCentreId)
            ? 'LEFT JOIN centres ct ON ct.id = c.centre_id'
            : '';
        $centreSelect = ($hasCentres && $hasCentreId)
            ? ', ct.name AS centre_name, ct.city AS centre_city, ct.state AS centre_state'
            : ", '' AS centre_name, '' AS centre_city, '' AS centre_state";

        $where = [];
        if ($hasPublished) {
            $where[] = '(c.link_published = 1 OR c.link_published IS NULL)';
        }
        if ($hasStatusCol) {
            $where[] = "(c.status IS NULL OR LOWER(c.status) NOT IN ('inactive','deleted','archived'))";
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT c.id, c.course_name, c.course_code, c.course_abbreviation, c.category,
                       c.eligibility, c.duration, c.training_center, c.is_nsqf,
                       {$feeExpr}, {$modeExpr}, {$campusExpr}, {$enrStatusExpr}, {$closingExpr},
                       {$regTokenExpr}, {$regFormExpr}, {$startExpr}, {$endExpr}, {$hasDescription}
                       {$centreSelect}
                FROM courses c
                {$centreJoin}
                {$whereSql}
                ORDER BY c.id DESC";

        $rows = [];
        $res = @$conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }

        // Load active batches per course_id
        $batchesByCourse = [];
        if ($hasBatches && !empty($rows)) {
            $hasBatchLoc = saarthi_column_exists($conn, 'batches', 'location');
            $hasBatchTime = saarthi_column_exists($conn, 'batches', 'class_time');
            $locSelect = $hasBatchLoc ? 'b.location' : "'NIELIT Bhubaneswar' AS location";
            $timeSelect = $hasBatchTime ? 'b.class_time' : "'' AS class_time";
            $bSql = "SELECT b.id, b.course_id, b.batch_name, b.batch_code, b.start_date, b.end_date,
                            b.seats_total, b.status, {$locSelect}, {$timeSelect},
                            (SELECT COUNT(*) FROM students st
                             WHERE st.batch_id = b.id
                               AND LOWER(COALESCE(st.status, '')) NOT IN ('rejected','cancelled','inactive')) AS live_enrolled
                     FROM batches b
                     WHERE LOWER(COALESCE(b.status, 'active')) NOT IN ('cancelled')
                     ORDER BY b.start_date DESC, b.id DESC";
            $bRes = @$conn->query($bSql);
            if ($bRes) {
                while ($b = $bRes->fetch_assoc()) {
                    $cid = (int) ($b['course_id'] ?? 0);
                    if ($cid > 0) {
                        $batchesByCourse[$cid][] = $b;
                    }
                }
            }
        }

        $today = date('Y-m-d');
        $enriched = [];
        foreach ($rows as $row) {
            $cid = (int) $row['id'];
            $enrStatus = strtolower(trim((string) ($row['enrollment_status'] ?? 'ongoing')));
            $closingDate = trim((string) ($row['enrollment_closing_date'] ?? ''));
            $isClosed = ($enrStatus === 'closed') || ($closingDate !== '' && $closingDate < $today);
            $startDate = trim((string) ($row['start_date'] ?? ''));
            $isUpcoming = !$isClosed && ($startDate !== '' && $startDate > $today);

            $campus = trim((string) ($row['centre_name'] ?? ''));
            if ($campus === '') {
                $campus = trim((string) ($row['training_center'] ?? ''));
            }
            if ($campus !== '' && function_exists('normalize_nielit_centre_name')) {
                $campus = normalize_nielit_centre_name($campus);
            }
            if ($campus === '') {
                $campus = 'NIELIT Bhubaneswar';
            }

            $courseBatches = $batchesByCourse[$cid] ?? [];
            $batchSummaries = [];
            $totalSeats = 0;
            $filledSeats = 0;
            $batchLocations = [];
            foreach ($courseBatches as $b) {
                $bStatus = strtolower(trim((string) ($b['status'] ?? 'active')));
                if ($bStatus === 'active') {
                    $stTotal = (int) ($b['seats_total'] ?? 0);
                    $stFilled = (int) ($b['live_enrolled'] ?? 0);
                    $totalSeats += $stTotal;
                    $filledSeats += $stFilled;
                    $bLoc = trim((string) ($b['location'] ?? ''));
                    if ($bLoc !== '') {
                        if (function_exists('normalize_nielit_batch_location')) {
                            $bLoc = normalize_nielit_batch_location($bLoc);
                        }
                        $batchLocations[] = $bLoc;
                    }
                    $batchSummaries[] = [
                        'batch_name' => (string) ($b['batch_name'] ?? ''),
                        'batch_code' => (string) ($b['batch_code'] ?? ''),
                        'start_date' => (string) ($b['start_date'] ?? ''),
                        'end_date' => (string) ($b['end_date'] ?? ''),
                        'class_time' => (string) ($b['class_time'] ?? ''),
                        'location' => $bLoc ?: $campus,
                        'seats_total' => $stTotal,
                        'seats_available' => max(0, $stTotal - $stFilled),
                        'status' => (string) ($b['status'] ?? 'Active'),
                    ];
                }
            }

            $allLocations = array_values(array_unique(array_filter(array_merge(
                [$campus, trim((string) ($row['centre_city'] ?? '')), trim((string) ($row['training_center'] ?? ''))],
                $batchLocations
            ))));

            $feeDisplay = function_exists('formatCourseFeeValue')
                ? formatCourseFeeValue($row['training_fees'] ?? '')
                : trim((string) ($row['training_fees'] ?? ''));
            if ($feeDisplay === '') {
                $feeDisplay = 'Check centre / free scheme';
            }

            $modeLabel = ucfirst(strtolower(trim((string) ($row['delivery_mode'] ?? ''))));
            $campusTypeLabel = trim((string) ($row['campus_type'] ?? ''));
            if ($campusTypeLabel === 'on_campus') {
                $campusTypeLabel = 'On Campus';
            } elseif ($campusTypeLabel === 'off_campus') {
                $campusTypeLabel = 'Off Campus';
            }

            $applyUrl = function_exists('course_registration_apply_url')
                ? course_registration_apply_url($row)
                : '';

            $seatText = $isClosed ? 'Enrollment Closed' : 'Enrollment Open';
            if ($totalSeats > 0 && !$isClosed) {
                $avail = max(0, $totalSeats - $filledSeats);
                $seatText = "Open ({$avail} / {$totalSeats} seats available)";
            } elseif ($closingDate !== '' && !$isClosed) {
                $seatText .= ' (closes ' . date('d M Y', strtotime($closingDate)) . ')';
            }

            $enriched[] = [
                'id' => $cid,
                'course_name' => (string) ($row['course_name'] ?? ''),
                'course_code' => (string) ($row['course_code'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'campus' => $campus,
                'locations' => $allLocations,
                'duration' => (string) ($row['duration'] ?? ''),
                'mode' => $modeLabel ?: ($campusTypeLabel ?: 'Classroom / Guided'),
                'fees' => $feeDisplay,
                'eligibility' => trim((string) ($row['eligibility'] ?? '')),
                'start_date' => $startDate,
                'end_date' => trim((string) ($row['end_date'] ?? '')),
                'closing_date' => $closingDate,
                'is_closed' => $isClosed,
                'is_upcoming' => $isUpcoming,
                'status_label' => $isClosed ? 'Closed' : ($isUpcoming ? 'Upcoming / Open' : 'Ongoing / Open'),
                'seat_status' => $seatText,
                'batches' => array_slice($batchSummaries, 0, 3),
                'apply_url' => $applyUrl,
                'registration_form' => (string) ($row['registration_form'] ?? 'full'),
                'search_blob' => saarthi_lower(implode(' ', [
                    (string) ($row['course_name'] ?? ''),
                    (string) ($row['course_code'] ?? ''),
                    (string) ($row['course_abbreviation'] ?? ''),
                    (string) ($row['category'] ?? ''),
                    (string) ($row['eligibility'] ?? ''),
                    (string) ($row['course_description'] ?? ''),
                ])),
                'loc_blob' => saarthi_lower(implode(' ', $allLocations)),
            ];
        }

        // Apply filters
        $normalizeLocAliases = static function (string $loc): array {
            $l = saarthi_lower(trim($loc));
            if ($l === '') {
                return [];
            }
            $tokens = [$l];
            if (strpos($l, 'bbsr') !== false || strpos($l, 'bhubaneswar') !== false || strpos($l, 'vss') !== false || strpos($l, 'ocac') !== false) {
                $tokens[] = 'bhubaneswar';
                $tokens[] = 'bbsr';
            }
            if (strpos($l, 'balasore') !== false || strpos($l, 'baleshwar') !== false) {
                $tokens[] = 'balasore';
                $tokens[] = 'baleshwar';
            }
            if (strpos($l, 'raipur') !== false) {
                $tokens[] = 'raipur';
            }
            return array_values(array_unique($tokens));
        };

        $locNeedles = $normalizeLocAliases($location);

        // Expand common course abbreviations/keywords
        $kwNorm = saarthi_lower(trim($keyword));
        $kwVariants = [];
        if ($kwNorm !== '') {
            $kwVariants[] = $kwNorm;
            $cleanedKw = trim((string) preg_replace('/[^a-z0-9\s]/', ' ', $kwNorm));
            if ($cleanedKw !== '') {
                $kwVariants[] = $cleanedKw;
            }
            if (preg_match('/o[\s\-]*level/i', $kwNorm)) {
                $kwVariants[] = 'o level';
                $kwVariants[] = 'it-o level';
            }
            if (preg_match('/a[\s\-]*level/i', $kwNorm)) {
                $kwVariants[] = 'a level';
            }
            if (strpos($kwNorm, 'ai/ml') !== false || strpos($kwNorm, 'machine learning') !== false) {
                $kwVariants[] = 'machine learning';
                $kwVariants[] = 'artificial intelligence';
                $kwVariants[] = 'python';
            }
            $parts = preg_split('/\s+/', $cleanedKw) ?: [];
            $stop = ['course', 'courses', 'program', 'programs', 'available', 'ongoing', 'in', 'at', 'for', 'the', 'of', 'and', 'tell', 'me', 'about', 'show', 'fees', 'fee', 'duration'];
            foreach ($parts as $p) {
                if (strlen($p) >= 2 && !in_array($p, $stop, true)) {
                    $kwVariants[] = $p;
                }
            }
            $kwVariants = array_values(array_unique($kwVariants));
        }

        $matches = [];
        foreach ($enriched as $item) {
            if (($status === 'ongoing' || $status === 'open') && $item['is_closed']) {
                continue;
            }
            if ($status === 'closed' && !$item['is_closed']) {
                continue;
            }
            if ($status === 'upcoming' && !$item['is_upcoming']) {
                continue;
            }

            if ($locNeedles !== []) {
                $locOk = false;
                foreach ($locNeedles as $ln) {
                    if (strpos($item['loc_blob'], $ln) !== false) {
                        $locOk = true;
                        break;
                    }
                }
                if (!$locOk) {
                    continue;
                }
            }

            $score = 0;
            if ($kwVariants !== []) {
                foreach ($kwVariants as $idx => $kv) {
                    if (strpos(saarthi_lower($item['course_name']), $kv) !== false || saarthi_lower($item['course_code']) === $kv) {
                        $score += ($idx === 0 ? 10 : 4);
                    } elseif (strpos($item['search_blob'], $kv) !== false) {
                        $score += 2;
                    }
                }
                if ($score === 0) {
                    continue;
                }
            } else {
                $score = $item['is_closed'] ? 1 : 5;
            }

            $item['_score'] = $score + ($item['is_closed'] ? 0 : 3);
            $matches[] = $item;
        }

        usort($matches, static function (array $a, array $b): int {
            return ($b['_score'] <=> $a['_score']) ?: ($b['id'] <=> $a['id']);
        });

        $exactMatch = count($matches) > 0;
        if (!$exactMatch) {
            // Fallback to closest active / ongoing courses (spec: never fabricate; suggest closest active)
            $activeFallback = array_values(array_filter($enriched, static fn(array $c): bool => !$c['is_closed']));
            if (empty($activeFallback)) {
                $activeFallback = $enriched;
            }
            $matches = array_slice($activeFallback, 0, 5);
        }

        $cleanMatches = array_map(static function (array $c): array {
            unset($c['search_blob'], $c['loc_blob'], $c['_score']);
            return $c;
        }, array_slice($matches, 0, 8));

        $openCount = count(array_filter($enriched, static fn(array $c): bool => !$c['is_closed']));

        return [
            'exact_match' => $exactMatch,
            'total_published' => count($enriched),
            'total_ongoing' => $openCount,
            'centres' => saarthi_list_centres($conn),
            'courses' => $cleanMatches,
        ];
    }
}

if (!function_exists('saarthi_parse_date_input')) {
    function saarthi_parse_date_input(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'Y/m/d', 'j M Y', 'j F Y', 'M j, Y'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat('!' . $fmt, $raw);
            if ($dt instanceof DateTime && $dt->format($fmt) === $raw) {
                $y = (int) $dt->format('Y');
                if ($y >= 1920 && $dt <= new DateTime('today')) {
                    return $dt->format('Y-m-d');
                }
            }
        }
        $ts = strtotime($raw);
        if ($ts !== false && $ts <= time() && (int) date('Y', $ts) >= 1920) {
            return date('Y-m-d', $ts);
        }
        return null;
    }
}

if (!function_exists('saarthi_sanitize_enum_value')) {
    function saarthi_sanitize_enum_value(mysqli $conn, string $table, string $column, string $preferred, string $fallback): string
    {
        $type = saarthi_get_column_type($conn, $table, $column);
        if ($type === '' || strpos($type, 'enum(') !== 0) {
            return $preferred;
        }
        if (preg_match_all("/'([^']+)'/", $type, $m)) {
            $allowed = $m[1];
            foreach ($allowed as $opt) {
                if (strcasecmp($opt, $preferred) === 0) {
                    return $opt;
                }
            }
            return in_array($fallback, $allowed, true) ? $fallback : ($allowed[0] ?? $fallback);
        }
        return $preferred;
    }
}

if (!function_exists('saarthi_register_student')) {
    /**
     * Save a confirmed conversational registration to MySQL and issue a session-scoped Admission PDF download token.
     *
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    function saarthi_register_student(mysqli $conn, array $details): array
    {
        require_once __DIR__ . '/multi_course_helper.php';
        require_once __DIR__ . '/student_id_helper.php';
        require_once __DIR__ . '/email_helper.php';
        require_once __DIR__ . '/student_form_helpers.php';
        require_once __DIR__ . '/nielit_registration_helper.php';

        // 1. Validate required fields
        $name = trim((string) ($details['name'] ?? $details['full_name'] ?? ''));
        $mobile = preg_replace('/\D/', '', (string) ($details['mobile'] ?? $details['phone'] ?? ''));
        if (strlen($mobile) > 10) {
            $mobile = substr($mobile, -10);
        }
        $email = trim((string) ($details['email'] ?? ''));
        $dobRaw = trim((string) ($details['dob'] ?? ''));
        $dob = saarthi_parse_date_input($dobRaw);
        $genderRaw = trim((string) ($details['gender'] ?? 'Male'));
        $qualification = trim((string) ($details['qualification'] ?? $details['highest_qualification'] ?? ''));
        $apaarId = trim((string) ($details['apaar_id'] ?? ''));
        if (preg_match('/^(none|na|n\/a|skip|no|nil|-)$/i', $apaarId)) {
            $apaarId = '';
        }
        $categoryRaw = trim((string) ($details['category'] ?? 'General'));
        $pwdRaw = trim((string) ($details['pwd_status'] ?? $details['pwd'] ?? 'No'));
        $pwdStatus = preg_match('/^(y|yes|1|true|haan|ha)$/i', $pwdRaw) ? 'Yes' : 'No';
        $address = trim((string) ($details['address'] ?? ''));
        $centerLocation = trim((string) ($details['center_location'] ?? $details['centre'] ?? $details['location'] ?? ''));
        $courseId = (int) ($details['course_id'] ?? 0);
        $courseNameQuery = trim((string) ($details['course_name'] ?? ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) < 2) {
            $errors[] = 'Full name is required.';
        }
        if (strlen($mobile) !== 10) {
            $errors[] = 'A valid 10-digit mobile number is required.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if ($dob === null) {
            $errors[] = 'A valid Date of Birth is required (e.g., 15-05-2002 or 2002-05-15).';
        }
        if ($qualification === '') {
            $errors[] = 'Highest educational qualification is required.';
        }
        if ($address === '') {
            $errors[] = 'Correspondence address is required.';
        }

        // Resolve course row
        $courseRow = null;
        if ($courseId > 0) {
            $cStmt = $conn->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
            if ($cStmt) {
                $cStmt->bind_param('i', $courseId);
                $cStmt->execute();
                $courseRow = $cStmt->get_result()->fetch_assoc();
                $cStmt->close();
            }
        }
        if (!$courseRow && $courseNameQuery !== '') {
            $searchRes = saarthi_search_courses($conn, $courseNameQuery, $centerLocation, 'ongoing');
            if (empty($searchRes['exact_match']) || empty($searchRes['courses'])) {
                $searchRes = saarthi_search_courses($conn, $courseNameQuery, '', 'all');
            }
            if (!empty($searchRes['exact_match']) && !empty($searchRes['courses'][0]['id'])) {
                $resolvedId = (int) $searchRes['courses'][0]['id'];
                $cStmt = $conn->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
                if ($cStmt) {
                    $cStmt->bind_param('i', $resolvedId);
                    $cStmt->execute();
                    $courseRow = $cStmt->get_result()->fetch_assoc();
                    $cStmt->close();
                }
            }
        }
        if (!$courseRow) {
            $errors[] = 'Please specify a valid active course from our catalog.';
        }

        if (!empty($errors)) {
            return ['ok' => false, 'errors' => $errors, 'message' => implode(' ', $errors)];
        }

        $courseId = (int) $courseRow['id'];
        $courseName = (string) $courseRow['course_name'];
        $trainingCenter = $centerLocation !== ''
            ? $centerLocation
            : trim((string) ($courseRow['training_center'] ?? 'NIELIT Bhubaneswar'));
        if ($trainingCenter === '') {
            $trainingCenter = 'NIELIT Bhubaneswar';
        }

        // Prevent duplicate active/pending enrollment for the same course
        $dupStmt = $conn->prepare(
            "SELECT id, student_id, status FROM students
             WHERE course_id = ?
               AND (REPLACE(REPLACE(REPLACE(mobile,' ',''),'-',''),'+91','') LIKE ? OR LOWER(TRIM(email)) = LOWER(TRIM(?)))
               AND LOWER(COALESCE(status,'')) NOT IN ('rejected','cancelled','inactive')
             ORDER BY id DESC LIMIT 1"
        );
        if ($dupStmt) {
            $mobLike = '%' . $mobile;
            $dupStmt->bind_param('iss', $courseId, $mobLike, $email);
            $dupStmt->execute();
            $dupRow = $dupStmt->get_result()->fetch_assoc();
            $dupStmt->close();
            if ($dupRow) {
                $sid = (string) $dupRow['student_id'];
                $canDownload = !empty($_SESSION['admin'])
                    || (!empty($_SESSION['student_id']) && trim((string) $_SESSION['student_id']) === trim($sid));
                $loginUrl = function_exists('app_url') ? app_url('student/login') : '/student/login.php';
                $resp = [
                    'ok' => true,
                    'already_registered' => true,
                    'student_id' => $sid,
                    'record_id' => $canDownload ? (int) $dupRow['id'] : 0,
                    'course_name' => $courseName,
                    'center_location' => $trainingCenter,
                    'student_login_url' => $loginUrl,
                ];
                if ($canDownload) {
                    $resp['download_pdf_url'] = saarthi_issue_pdf_token((int) $dupRow['id'], $sid);
                    $resp['message'] = 'You are already registered for ' . $courseName . ' (Student ID: ' . $sid . '). You can download your Admission Form PDF below.';
                } else {
                    $resp['message'] = 'An application already exists for this mobile/email in ' . $courseName . '. Please log in to the Student Portal to check your status or download your Admission Form PDF.';
                }
                return $resp;
            }
        }

        // Returning student check (reuse existing Student ID without creating duplicate IDs)
        $existingAccount = function_exists('findExistingStudentAccount')
            ? findExistingStudentAccount($conn, '', $mobile, $email)
            : null;
        $isReturning = false;
        if ($existingAccount && !empty($existingAccount['student_id'])) {
            $isReturning = true;
            $studentId = (string) $existingAccount['student_id'];
        } elseif (isMultiCourseSystemInstalled($conn)) {
            $studentId = getNextGlobalStudentID($conn);
        } else {
            $studentId = getNextStudentID($courseId, $conn);
        }
        if ($studentId === null || $studentId === '') {
            // Fallback safe ID generator if course abbreviation is absent and multi-course table missing
            $prefix = 'NIELIT/' . date('Y') . '/BBSR/';
            $studentId = $prefix . str_pad((string) random_int(5000, 9999), 4, '0', STR_PAD_LEFT);
        }

        // Normalize fields for students schema
        $age = (int) (new DateTime($dob))->diff(new DateTime())->y;
        $genderPref = 'Male';
        if (preg_match('/^(f|female|mahila|stree)/i', $genderRaw)) {
            $genderPref = 'Female';
        } elseif (preg_match('/^(o|other|trans)/i', $genderRaw)) {
            $genderPref = 'Other';
        }
        $gender = saarthi_sanitize_enum_value($conn, 'students', 'gender', $genderPref, 'Male');

        $catPref = 'General';
        if (preg_match('/obc/i', $categoryRaw)) {
            $catPref = 'OBC';
        } elseif (preg_match('/\bsc\b/i', $categoryRaw)) {
            $catPref = 'SC';
        } elseif (preg_match('/\bst\b/i', $categoryRaw)) {
            $catPref = 'ST';
        } elseif (preg_match('/ews/i', $categoryRaw)) {
            $catPref = 'EWS';
        }
        $category = saarthi_sanitize_enum_value($conn, 'students', 'category', $catPref, 'General');
        $religion = saarthi_sanitize_enum_value($conn, 'students', 'religion', 'Other', 'Other');
        $marital = saarthi_sanitize_enum_value($conn, 'students', 'marital_status', 'Single', 'Single');

        // Extract pincode & city from correspondence address when present
        $pincode = '';
        if (preg_match('/\b(\d{6})\b/', $address, $pm)) {
            $pincode = $pm[1];
        }
        $city = trim((string) ($details['city'] ?? ''));
        $state = trim((string) ($details['state'] ?? ''));
        if ($city === '') {
            if (stripos($trainingCenter, 'raipur') !== false) {
                $city = 'Raipur';
                $state = $state ?: 'Chhattisgarh';
            } elseif (stripos($trainingCenter, 'baleshwar') !== false || stripos($trainingCenter, 'balasore') !== false) {
                $city = 'Balasore';
                $state = $state ?: 'Odisha';
            } elseif (stripos($trainingCenter, 'cuttack') !== false) {
                $city = 'Cuttack';
                $state = $state ?: 'Odisha';
            } else {
                $city = 'Bhubaneswar';
                $state = $state ?: 'Odisha';
            }
        }
        if ($state === '') {
            $state = 'Odisha';
        }

        $plainPassword = null;
        if ($isReturning && !empty($existingAccount['password'])) {
            $hashedPassword = (string) $existingAccount['password'];
        } else {
            $plainPassword = bin2hex(random_bytes(6));
            $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        }

        $eduJson = json_encode([
            'saarthi_chat' => true,
            'highest_qualification' => $qualification,
            'documents_pending' => true,
        ], JSON_UNESCAPED_UNICODE);

        $cols = [
            'course' => ['s', $courseName],
            'course_id' => ['i', $courseId],
            'training_center' => ['s', $trainingCenter],
            'name' => ['s', $name],
            'father_name' => ['s', ''],
            'mother_name' => ['s', ''],
            'dob' => ['s', $dob],
            'age' => ['i', $age],
            'mobile' => ['s', $mobile],
            'aadhar' => ['s', ''],
            'gender' => ['s', $gender],
            'religion' => ['s', $religion],
            'marital_status' => ['s', $marital],
            'category' => ['s', $category],
            'position' => ['s', 'Student'],
            'nationality' => ['s', 'Indian'],
            'email' => ['s', $email],
            'state' => ['s', $state],
            'city' => ['s', $city],
            'pincode' => ['s', $pincode],
            'address' => ['s', $address],
            'education_details' => ['s', $eduJson],
            'passport_photo' => ['s', ''],
            'signature' => ['s', ''],
            'student_id' => ['s', $studentId],
            'password' => ['s', $hashedPassword],
            'status' => ['s', 'pending'],
        ];
        if (saarthi_column_exists($conn, 'students', 'apaar_id')) {
            $cols['apaar_id'] = ['s', $apaarId !== '' ? $apaarId : null];
        }
        if (saarthi_column_exists($conn, 'students', 'pwd_status')) {
            $cols['pwd_status'] = ['s', $pwdStatus];
        }

        $colNames = [];
        $placeholders = [];
        $bindTypes = '';
        $bindVals = [];
        foreach ($cols as $cName => [$cType, $cVal]) {
            $colNames[] = "`{$cName}`";
            $placeholders[] = '?';
            $bindTypes .= $cType;
            $bindVals[] = $cVal;
        }
        $colNames[] = '`registration_date`';
        $placeholders[] = 'NOW()';

        $sql = 'INSERT INTO `students` (' . implode(', ', $colNames) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Registration prepare error: ' . $conn->error];
        }
        $stmt->bind_param($bindTypes, ...$bindVals);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return ['ok' => false, 'message' => 'Could not save registration: ' . $err];
        }
        $recordId = (int) $conn->insert_id;
        $stmt->close();

        if ($recordId > 0 && function_exists('syncNielitRegistrationNoDefault')) {
            syncNielitRegistrationNoDefault($conn, $recordId, null);
        }

        // Ensure education_details row exists for PDF Academic Qualification table
        if (saarthi_table_exists($conn, 'education_details')) {
            $eduStmt = $conn->prepare(
                "INSERT INTO education_details (student_id, exam_passed, exam_name, year_of_passing, institute_name, stream, percentage)
                 VALUES (?, ?, ?, '', '', '', '')"
            );
            if ($eduStmt) {
                $eduStmt->bind_param('sss', $studentId, $qualification, $qualification);
                $eduStmt->execute();
                $eduStmt->close();
            }
        }

        // Link multi-course account & enrollment
        if (isMultiCourseSystemInstalled($conn) && $recordId > 0) {
            // Ensure student_accounts.aadhar can hold NOAADHAR/NIELIT/... safely
            $aadharType = saarthi_get_column_type($conn, 'student_accounts', 'aadhar');
            if ($aadharType !== '' && preg_match('/varchar\((\d+)\)/', $aadharType, $vm) && (int) $vm[1] < 50) {
                @$conn->query('ALTER TABLE student_accounts MODIFY COLUMN aadhar VARCHAR(50) NOT NULL');
            }
            $accountId = !empty($existingAccount['id']) ? (int) $existingAccount['id'] : null;
            if (!$accountId) {
                $placeholderAadhar = 'NOAADHAR/' . preg_replace('/[^A-Za-z0-9\/]/', '', $studentId);
                $accountId = createStudentAccount($conn, [
                    'student_id' => $studentId,
                    'aadhar' => $placeholderAadhar,
                    'name' => $name,
                    'email' => $email,
                    'mobile' => $mobile,
                    'password' => $hashedPassword,
                    'dob' => $dob,
                    'gender' => $gender,
                ]);
                if (!$accountId && function_exists('findExistingStudentAccount')) {
                    $refetch = findExistingStudentAccount($conn, '', $mobile, $email);
                    $accountId = !empty($refetch['id']) ? (int) $refetch['id'] : null;
                }
            }
            if ($accountId) {
                linkStudentRecordToAccount($conn, $recordId, $accountId);
                createStudentEnrollment($conn, $accountId, $courseId, $recordId, 'pending', null);
            }
        }

        $emailQueued = false;
        if (!$isReturning && $plainPassword !== null && $email !== '' && function_exists('dispatchRegistrationEmailAsync')) {
            $emailQueued = dispatchRegistrationEmailAsync($email, $name, $studentId, $plainPassword, $courseName, $trainingCenter);
        }

        if (is_file(__DIR__ . '/activity_logger.php')) {
            require_once __DIR__ . '/activity_logger.php';
            if (function_exists('logActivity')) {
                logActivity($conn, [
                    'actor_type' => 'student',
                    'actor_id' => $studentId,
                    'actor_name' => $name,
                    'action' => 'student_register',
                    'entity_type' => 'course',
                    'entity_id' => (string) $courseId,
                    'entity_name' => $courseName,
                    'description' => 'Student "' . $name . '" (' . $studentId . ') registered via Saarthi AI Assistant for "' . $courseName . '".',
                    'details' => [
                        'source' => 'saarthi_chat',
                        'email' => $email,
                        'training_center' => $trainingCenter,
                    ],
                ]);
            }
        }

        $pdfUrl = saarthi_issue_pdf_token($recordId, $studentId);

        return [
            'ok' => true,
            'already_registered' => false,
            'is_returning' => $isReturning,
            'record_id' => $recordId,
            'student_id' => $studentId,
            'course_name' => $courseName,
            'center_location' => $trainingCenter,
            'email_queued' => $emailQueued,
            'download_pdf_url' => $pdfUrl,
            'student_login_url' => function_exists('app_url') ? app_url('student/login') : '/student/login.php',
        ];
    }
}

if (!function_exists('saarthi_issue_pdf_token')) {
    function saarthi_issue_pdf_token(int $recordId, string $studentId): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $token = bin2hex(random_bytes(16));
        if (!isset($_SESSION['saarthi_pdf_tokens']) || !is_array($_SESSION['saarthi_pdf_tokens'])) {
            $_SESSION['saarthi_pdf_tokens'] = [];
        }
        // Keep last 10 tokens
        $_SESSION['saarthi_pdf_tokens'][$token] = [
            'record_id' => $recordId,
            'student_id' => $studentId,
            'expires' => time() + 3600,
        ];
        if (count($_SESSION['saarthi_pdf_tokens']) > 10) {
            $_SESSION['saarthi_pdf_tokens'] = array_slice($_SESSION['saarthi_pdf_tokens'], -10, null, true);
        }

        if (!function_exists('app_url')) {
            require_once __DIR__ . '/url_helper.php';
        }
        return app_url('api/saarthi_admission_pdf.php') . '?token=' . rawurlencode($token) . '&record_id=' . $recordId;
    }
}

if (!function_exists('saarthi_log_support_callback')) {
    /**
     * Log a helpdesk callback/support request cleanly into support_tickets.
     *
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    function saarthi_log_support_callback(mysqli $conn, array $details): array
    {
        if (is_file(__DIR__ . '/support_ticket_helper.php')) {
            require_once __DIR__ . '/support_ticket_helper.php';
        }
        $contact = saarthi_helpdesk_info();
        $name = trim((string) ($details['name'] ?? 'Visitor'));
        $phone = trim((string) ($details['phone'] ?? $details['mobile'] ?? ''));
        $email = trim((string) ($details['email'] ?? ''));
        $issue = trim((string) ($details['issue'] ?? $details['message'] ?? 'Callback requested via Saarthi chat'));
        $category = trim((string) ($details['category'] ?? 'fees'));
        if (!in_array($category, ['technical', 'academic', 'fees', 'certificate', 'attendance', 'other'], true)) {
            $category = 'other';
        }

        $ticketId = 0;
        if (function_exists('ensureSupportTicketsTable') && ensureSupportTicketsTable($conn)) {
            $studentId = !empty($_SESSION['student_id']) ? (string) $_SESSION['student_id'] : null;
            $reqType = $studentId ? 'student' : 'guest';
            $subject = 'Saarthi Callback: ' . mb_substr($issue, 0, 80);
            $fullMessage = "Name: {$name}\nPhone: {$phone}\nEmail: {$email}\nIssue: {$issue}\nLogged via Saarthi AI Assistant";
            $stmt = $conn->prepare(
                "INSERT INTO support_tickets (requester_type, student_id, subject, category, priority, message, status)
                 VALUES (?, ?, ?, ?, 'high', ?, 'open')"
            );
            if ($stmt) {
                $stmt->bind_param('sssss', $reqType, $studentId, $subject, $category, $fullMessage);
                if ($stmt->execute()) {
                    $ticketId = (int) $conn->insert_id;
                }
                $stmt->close();
            }
        }

        return [
            'ok' => true,
            'ticket_id' => $ticketId,
            'helpdesk' => $contact,
        ];
    }
}
