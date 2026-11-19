<?php
/**
 * Public homepage assistant — local FAQ + optional OpenAI.
 */

if (!function_exists('portal_assistant_load_config')) {
    /** @return array<string, mixed> */
    function portal_assistant_load_config(): array
    {
        $defaults = [
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o-mini',
            'use_openai' => false,
        ];
        $path = dirname(__DIR__) . '/config/portal_assistant.local.php';
        if (is_file($path)) {
            $local = include $path;
            if (is_array($local)) {
                return array_merge($defaults, $local);
            }
        }
        return $defaults;
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
        $max = 40;
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
            'student_login' => $base . '/student/login',
            'student_register' => $base . '/student/register',
            'admin_login' => $base . '/admin/login',
            'payment_portal' => $base . '/payment-portal/',
            'mock_test' => $base . '/mocktest/',
            'contact' => $base . '/#contact',
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

if (!function_exists('portal_assistant_local_reply')) {
    function portal_assistant_local_reply(string $message, array $ctx): string
    {
        $m = strtolower(trim($message));
        if ($m === '') {
            return 'Please type a question. For example: student login, registration, courses, fees, or mock test.';
        }

        if (preg_match('/\b(hi|hello|namaste|hey|good morning|good evening)\b/u', $m)) {
            return "Hello! I'm the NIELIT Bhubaneswar assistant. I can help with:\n"
                . "• Student login and registration\n"
                . "• Courses, admissions, and mock tests\n"
                . "• Payment portal and general centre information\n\n"
                . "What would you like to know?";
        }

        if (preg_match('/login|sign\s*in|password|forgot/u', $m)) {
            if (preg_match('/admin|staff/u', $m)) {
                return 'Admin login: ' . ($ctx['admin_login'] ?? '') . "\n\nUse your institute admin credentials. Students should use the student login link, not admin.";
            }
            return "Student login: " . ($ctx['student_login'] ?? '') . "\n\n"
                . "If you forgot your password, use the reset option on the login page. New users can register here: "
                . ($ctx['student_register'] ?? '');
        }

        if (preg_match('/register|registration|admission|apply|enroll/u', $m)) {
            return "New student registration: " . ($ctx['student_register'] ?? '') . "\n\n"
                . "Fill the form with correct personal details (name as on certificates/Aadhaar). "
                . "After registration, complete payment if required and follow announcements on the homepage.";
        }

        if (preg_match('/mock\s*test|exam\s*practice|online\s*test/u', $m)) {
            return 'Mock test portal: ' . ($ctx['mock_test'] ?? '') . "\n\n"
                . 'Use your registered student account where applicable.';
        }

        if (preg_match('/fee|payment|pay\b|receipt/u', $m)) {
            return 'Payment portal: ' . ($ctx['payment_portal'] ?? '') . "\n\n"
                . 'For course fees and payment status, log in to the payment portal or contact the centre office with your Student ID.';
        }

        if (preg_match('/course|program|nsqf|training|batch/u', $m)) {
            return "Course and batch details are published on this homepage (Featured courses, announcements). "
                . "For a specific batch or admission status, contact the centre with your Student ID.\n\n"
                . "Student login: " . ($ctx['student_login'] ?? '');
        }

        if (preg_match('/certificate|digilocker|result/u', $m)) {
            return "Certificates and results are issued as per NIELIT rules after successful completion and assessment. "
                . "Log in as a student to check your status. For delays, visit the centre with your enrollment details.";
        }

        if (preg_match('/contact|phone|email|address|location|where/u', $m)) {
            return "Contact details and centre address are on this page under Contact / footer.\n\n"
                . 'Link: ' . ($ctx['contact'] ?? '') . "\n\n"
                . 'You can also call or visit NIELIT Bhubaneswar Extension Centre during office hours.';
        }

        if (preg_match('/student\s*update|record\s*update|inspector|excel\s*update|staff\s*report|activity\s*report/u', $m)) {
            return "Student record updates (edits, Inspector, Excel) are logged for staff and can trigger n8n email alerts.\n\n"
                . "• Students: use " . ($ctx['student_login'] ?? 'student login') . " — this public chat cannot show private records.\n"
                . "• Staff: log in to admin, or enable **Homepage chat → n8n** in API & Automation, then ask again here while logged in.\n"
                . "• You can also email nielitbbsr01@gmail.com with subject **REPORT** (n8n email assistant).";
        }

        if (preg_match('/\bn8n\b|webhook|automation|email\s*alert/u', $m)) {
            return "n8n automation at NIELIT:\n"
                . "• Portal → instant webhook when students change (emails to staff)\n"
                . "• Homepage chat → optional n8n workflow (portal chat webhook)\n"
                . "• Configure: Admin → API & Automation\n"
                . "• Webhook: https://nielitbbsr.app.n8n.cloud/webhook/nielit-student-updates";
        }

        if (preg_match('/\bnews\b|announcement|\bannounce\b/u', $m)) {
            $latest = $ctx['latest_news'] ?? 'See the News section on the homepage.';
            return "Latest public announcements: " . $latest;
        }

        if (preg_match('/admin|staff|faculty/u', $m)) {
            return 'This public assistant is for students and visitors. Staff use the admin login: '
                . ($ctx['admin_login'] ?? '') . "\n\nI cannot access private admin data here.";
        }

        if (preg_match('/change\s*name|edit\s*student/u', $m)) {
            return "Student record changes are done only by authorised centre staff in the admin portal (Manage Students or Record Inspector). "
                . "This chat cannot change your records. For name or data corrections, contact the centre with ID proof.";
        }

        if (preg_match('/\bstudent\b|\bdetail|\bprofile/u', $m)) {
            return "For student details:\n"
                . "• You: log in at " . ($ctx['student_login'] ?? '') . " then ask \"check my student details\".\n"
                . "• Staff: log in at " . ($ctx['admin_login'] ?? '') . " then paste an email or Student ID.\n"
                . "• Example (staff): \"is saswatsomya111@gmail.com in the list?\"";
        }

        return "I'm not sure about that. Try:\n"
            . "• \"check my student details\" (after student login)\n"
            . "• Staff: email or Student ID lookup after admin login\n"
            . "• Login, registration, fees, mock test, student update report\n\n"
            . "Student login: " . ($ctx['student_login'] ?? '/student/login');
    }
}

if (!function_exists('portal_assistant_openai_reply')) {
  /**
   * @param list<array{role: string, content: string}> $history
   */
  function portal_assistant_openai_reply(string $message, array $history, array $ctx, array $config): ?string
  {
      if (empty($config['use_openai']) || trim((string) ($config['openai_api_key'] ?? '')) === '') {
          return null;
      }
      if (!function_exists('curl_init')) {
          return null;
      }

      $system = "You are a helpful assistant for {$ctx['site_name']}. "
          . "Answer briefly in plain English (Hindi is OK if the user writes in Hindi). "
          . "Only help with: student registration, login, courses, fees, mock tests, payments, certificates, centre contact. "
          . "Do not invent student IDs or claim you changed records. "
          . "Use these links when relevant:\n"
          . 'Student login: ' . ($ctx['student_login'] ?? '') . "\n"
          . 'Register: ' . ($ctx['student_register'] ?? '') . "\n"
          . 'Payment: ' . ($ctx['payment_portal'] ?? '') . "\n"
          . 'Mock test: ' . ($ctx['mock_test'] ?? '') . "\n"
          . 'News hint: ' . ($ctx['latest_news'] ?? '');

      $messages = [['role' => 'system', 'content' => $system]];
      foreach (array_slice($history, -8) as $turn) {
          $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
          $content = trim((string) ($turn['content'] ?? ''));
          if ($content !== '') {
              $messages[] = ['role' => $role, 'content' => $content];
          }
      }
      $messages[] = ['role' => 'user', 'content' => $message];

      $body = json_encode([
          'model' => (string) ($config['openai_model'] ?? 'gpt-4o-mini'),
          'messages' => $messages,
          'max_tokens' => 450,
          'temperature' => 0.4,
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
              'Authorization: Bearer ' . trim((string) $config['openai_api_key']),
          ],
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_CONNECTTIMEOUT => 8,
          CURLOPT_TIMEOUT => 25,
      ]);
      $raw = curl_exec($ch);
      $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($code !== 200 || !is_string($raw)) {
          return null;
      }
      $decoded = json_decode($raw, true);
      $text = $decoded['choices'][0]['message']['content'] ?? null;
      return is_string($text) && trim($text) !== '' ? trim($text) : null;
  }
}

if (!function_exists('portal_assistant_actor_context')) {
    /** @return array<string, mixed> */
    function portal_assistant_actor_context(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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

if (!function_exists('portal_assistant_admin_activity_reply')) {
    function portal_assistant_admin_activity_reply(?mysqli $conn, string $message): ?string
    {
        if (!$conn instanceof mysqli || empty($_SESSION['admin'])) {
            return null;
        }
        if (!preg_match('/student\s*update|record\s*update|report|activity|inspector|excel|recent|full\s*update|n8n/u', strtolower($message))) {
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

if (!function_exists('portal_assistant_chat')) {
    /**
     * @param list<array{role: string, content: string}> $history
     * @return array{reply: string, mode: string}
     */
    function portal_assistant_chat(string $message, array $history, ?mysqli $conn = null): array
    {
        $message = trim($message);
        if (mb_strlen($message) > 2000) {
            $message = mb_substr($message, 0, 2000);
        }
        $ctx = portal_assistant_site_context($conn);
        $config = portal_assistant_load_config();
        $actor = portal_assistant_actor_context();

        if ($conn instanceof mysqli) {
            require_once __DIR__ . '/portal_assistant_actions.php';
            $action = portal_assistant_try_actions($conn, $message, $actor, $ctx);
            if ($action !== null) {
                return $action;
            }
        }

        if ($conn instanceof mysqli && function_exists('automationPortalChatRequest')) {
            require_once __DIR__ . '/automation_webhook_helper.php';
            $n8n = automationPortalChatRequest($conn, $message, $history, $actor, $ctx);
            if ($n8n !== null && $n8n !== '') {
                return ['reply' => $n8n, 'mode' => 'n8n'];
            }
        }

        $staff = portal_assistant_admin_activity_reply($conn, $message);
        if ($staff !== null) {
            return ['reply' => $staff, 'mode' => 'activity'];
        }

        $reply = portal_assistant_openai_reply($message, $history, $ctx, $config);
        if ($reply !== null) {
            return ['reply' => $reply, 'mode' => 'openai'];
        }
        return [
            'reply' => portal_assistant_local_reply($message, $ctx),
            'mode' => 'local',
        ];
    }
}