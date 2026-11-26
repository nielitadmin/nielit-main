<?php
/**
 * Structured chat actions: student lookup, my profile (real DB, not FAQ-only).
 */

if (!function_exists('portal_assistant_extract_email')) {
    function portal_assistant_extract_email(string $text): ?string
    {
        if (preg_match('/[a-z0-9._%+\\-]+@[a-z0-9.\\-]+\\.[a-z]{2,}/i', $text, $m)) {
            return strtolower(trim($m[0]));
        }
        return null;
    }
}

if (!function_exists('portal_assistant_extract_student_id')) {
    function portal_assistant_extract_student_id(string $text): ?string
    {
        if (preg_match('/NIELIT\\/[\\w\\-\\/]+/i', $text, $m)) {
            return trim($m[0]);
        }
        return null;
    }
}

if (!function_exists('portal_assistant_table_exists')) {
    function portal_assistant_table_exists(mysqli $conn, string $table): bool
    {
        $table = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$table}'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('portal_assistant_my_profile_reply')) {
    function portal_assistant_my_profile_reply(mysqli $conn, array $actor, array $ctx): ?string
    {
        if (empty($actor['is_student']) || empty($_SESSION['student_id'])) {
            return null;
        }
        $sid = (string) $_SESSION['student_id'];
        $name = (string) ($_SESSION['student_name'] ?? $sid);
        $lines = ["Your login (Student ID): {$sid}", "Name on portal: {$name}"];

        if (function_exists('getEnrollmentsForStudentId')) {
            require_once __DIR__ . '/multi_course_helper.php';
            $enrollments = getEnrollmentsForStudentId($conn, $sid);
            if ($enrollments) {
                $lines[] = "\nEnrollments:";
                foreach (array_slice($enrollments, 0, 8) as $en) {
                    $lines[] = '• ' . ($en['course_name'] ?? $en['course_code'] ?? 'Course')
                        . ' — status: ' . ($en['status'] ?? 'unknown');
                }
            }
        }

        $stmt = $conn->prepare(
            'SELECT name, email, mobile, status, course_id FROM students WHERE student_id = ? ORDER BY id DESC LIMIT 5'
        );
        if ($stmt) {
            $stmt->bind_param('s', $sid);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
            if ($rows) {
                $lines[] = "\nRecord(s) on file:";
                foreach ($rows as $r) {
                    $lines[] = '• ' . ($r['name'] ?? '') . ' | ' . ($r['email'] ?? '') . ' | status: ' . ($r['status'] ?? '');
                }
            }
        }

        $lines[] = "\nFull dashboard: " . ($ctx['student_login'] ?? '') . ' (then open student dashboard after login).';
        return implode("\n", $lines);
    }
}

if (!function_exists('portal_assistant_lookup_by_email')) {
    function portal_assistant_lookup_by_email(mysqli $conn, string $email): string
    {
        $email = strtolower(trim($email));
        $found = [];

        if (portal_assistant_table_exists($conn, 'student_accounts')) {
            $stmt = $conn->prepare('SELECT id, name, email, mobile FROM student_accounts WHERE LOWER(email) = ? LIMIT 5');
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $found[] = '[Account] ' . ($row['name'] ?? '') . ' — ' . ($row['email'] ?? '') . ' (account id ' . ($row['id'] ?? '') . ')';
                }
                $stmt->close();
            }
        }

        $stmt = $conn->prepare(
            "SELECT student_id, name, email, status FROM students WHERE LOWER(email) = ? AND LOWER(COALESCE(status,'')) NOT IN ('rejected','inactive','deleted') ORDER BY id DESC LIMIT 15"
        );
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $found[] = '[Student] ' . ($row['student_id'] ?? '') . ' — ' . ($row['name'] ?? '') . ' — status: ' . ($row['status'] ?? '');
            }
            $stmt->close();
        }

        if ($found === []) {
            return "No student or account found with email:\n{$email}\n\n(Check spelling, or search in Student Record Inspector if recently added.)";
        }

        return "Yes — email is on file ({$email}). Matches:\n\n" . implode("\n", $found);
    }
}

if (!function_exists('portal_assistant_lookup_by_student_id')) {
    function portal_assistant_lookup_by_student_id(mysqli $conn, string $studentId): string
    {
        $stmt = $conn->prepare(
            "SELECT student_id, name, email, mobile, status FROM students WHERE student_id = ? ORDER BY id DESC LIMIT 10"
        );
        if (!$stmt) {
            return 'Could not search the database.';
        }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        if ($rows === []) {
            return "No row found for Student ID: {$studentId}";
        }

        $lines = ["Found " . count($rows) . " record(s) for {$studentId}:"];
        foreach ($rows as $r) {
            $lines[] = '• ' . ($r['name'] ?? '') . ' | ' . ($r['email'] ?? '') . ' | ' . ($r['mobile'] ?? '') . ' | status: ' . ($r['status'] ?? '');
        }
        return implode("\n", $lines);
    }
}

if (!function_exists('portal_assistant_is_email_lookup_intent')) {
    /**
     * Decide whether a message containing an email is an explicit email/student lookup request
     * (never hijack conversational registration replies like "Name, 7653814034, user@gmail.com").
     */
    function portal_assistant_is_email_lookup_intent(string $message, ?string $email, array $ctx = []): bool
    {
        if ($email === null || $email === '') {
            return false;
        }
        if (!empty($ctx['in_registration_flow'])) {
            return false;
        }
        $trimmed = trim($message);
        $m = function_exists('mb_strtolower') ? mb_strtolower($trimmed, 'UTF-8') : strtolower($trimmed);

        // Multi-field contact payload (Name + 10-digit mobile + email) should never trigger email privacy refusal
        $hasMobile = (bool) preg_match('/(?:\+91[\s\-]?)?[6-9]\d{9}\b|\b\d{10}\b/', $trimmed);
        $hasExplicitLookupVerb = (bool) preg_match(
            '/\b(list|already\s+registered|is\s+.*\s+registered|registered\s+or\s+not|exist|exists|in\s+the\s+system|on\s+file|lookup|look\s+up|find|search|check|verify)\b|\bor\s+not\b/iu',
            $m
        );
        if ($hasMobile && !$hasExplicitLookupVerb) {
            return false;
        }

        if (strcasecmp($trimmed, $email) === 0) {
            return true;
        }

        return $hasExplicitLookupVerb;
    }
}

if (!function_exists('portal_assistant_try_actions')) {
    /**
     * @return array{reply: string, mode: string}|null
     */
    function portal_assistant_try_actions(mysqli $conn, string $message, array $actor, array $ctx): ?array
    {
        if (!empty($ctx['in_registration_flow'])) {
            return null;
        }
        $m = function_exists('mb_strtolower') ? mb_strtolower($message, 'UTF-8') : strtolower($message);
        $email = portal_assistant_extract_email($message);
        $studentId = portal_assistant_extract_student_id($message);

        $wantsMyDetails = (bool) preg_match(
            '/\b(my|mera|apna)\b.*\b(detail|profile|record|student|status)\b|\bcheck my student|\bmy student detail|\bstudent details\b/u',
            $m
        );
        $wantsEmailLookup = portal_assistant_is_email_lookup_intent($message, $email, $ctx);
        $wantsStudentSearch = ($studentId !== null) || (bool) preg_match(
            '/\b(find|search|lookup|check)\b.*\bstudent\b/u',
            $m
        );

        if ($wantsMyDetails) {
            if ($actor['is_student']) {
                $reply = portal_assistant_my_profile_reply($conn, $actor, $ctx);
                if ($reply !== null) {
                    return ['reply' => $reply, 'mode' => 'profile'];
                }
            }
            if ($actor['is_admin'] && $email) {
                return ['reply' => portal_assistant_lookup_by_email($conn, $email), 'mode' => 'lookup'];
            }
            return [
                'reply' => "To see your student details, log in first:\n" . ($ctx['student_login'] ?? '') . "\n\n"
                    . "After login, ask again: \"check my student details\".\n\n"
                    . "Staff: log in to admin in this browser, then ask \"is email xxx in the list?\"",
                'mode' => 'guide',
            ];
        }

        if ($email && $wantsEmailLookup) {
            if (!$actor['is_admin']) {
                return [
                    'reply' => "For privacy I cannot confirm if an email is registered unless you are staff.\n\n"
                        . "• If it is **your** email: use Student login — " . ($ctx['student_login'] ?? '') . "\n"
                        . "• Staff: open Admin login in another tab (" . ($ctx['admin_login'] ?? '') . "), then ask again with the email address.",
                    'mode' => 'privacy',
                ];
            }
            return ['reply' => portal_assistant_lookup_by_email($conn, $email), 'mode' => 'lookup'];
        }

        if ($studentId && ($actor['is_admin'] || $wantsStudentSearch)) {
            if (!$actor['is_admin']) {
                return [
                    'reply' => 'Looking up a Student ID requires staff access. Students can only view their own profile after login.',
                    'mode' => 'privacy',
                ];
            }
            return ['reply' => portal_assistant_lookup_by_student_id($conn, $studentId), 'mode' => 'lookup'];
        }

        if ($wantsStudentSearch && $actor['is_admin'] && $email) {
            return ['reply' => portal_assistant_lookup_by_email($conn, $email), 'mode' => 'lookup'];
        }

        return null;
    }
}