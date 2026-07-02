<?php
/**
 * Public course display helpers (project name / course level label)
 */

if (!function_exists('ensureCourseProjectLevelColumn')) {
    function ensureCourseProjectLevelColumn(mysqli $conn): void {
        $check = $conn->query("SHOW COLUMNS FROM courses LIKE 'project_level_label'");
        if ($check && $check->num_rows > 0) {
            return;
        }
        $sql = "ALTER TABLE courses ADD COLUMN project_level_label VARCHAR(255) DEFAULT NULL";
        $conn->query($sql);
    }
}

if (!function_exists('formatCourseFeeValue')) {
    function formatCourseFeeValue($fees): string
    {
        $fees = trim((string)$fees);
        if ($fees === '') {
            return '';
        }
        if (is_numeric($fees)) {
            return '₹' . number_format((float)$fees);
        }
        if (preg_match('/^[₹]|^rs\.?\s/i', $fees)) {
            return $fees;
        }
        return $fees;
    }
}

if (!function_exists('renderCourseFeeQuickInfo')) {
    function renderCourseFeeQuickInfo(array $row): string
    {
        $fees = trim((string)($row['training_fees'] ?? ''));
        if ($fees === '') {
            return '';
        }
        $display = formatCourseFeeValue($fees);
        $icon = is_numeric($fees) ? 'fa-rupee-sign' : 'fa-tags';
        $class = is_numeric($fees) ? 'course-fee-item' : 'course-fee-item course-fee-text';
        return '<span class="' . $class . '"><i class="fas ' . $icon . '"></i> '
            . htmlspecialchars($display) . '</span>';
    }
}

if (!function_exists('renderCourseFeeDetailItem')) {
    function renderCourseFeeDetailItem(array $row): string
    {
        $fees = trim((string)($row['training_fees'] ?? ''));
        if ($fees === '') {
            return '';
        }
        $display = formatCourseFeeValue($fees);
        return '<div class="info-item"><i class="fas fa-rupee-sign"></i><div>'
            . '<span class="info-label">Training Fees</span>'
            . '<span class="info-value">' . htmlspecialchars($display) . '</span>'
            . '</div></div>';
    }
}

if (!function_exists('course_registration_apply_url')) {
    /**
     * Build registration URL from APP_URL + token (ignores stale apply_link in DB).
     */
    function course_registration_apply_url(array $course): string {
        if (!function_exists('app_url')) {
            require_once __DIR__ . '/url_helper.php';
        }

        $token = trim((string) ($course['registration_token'] ?? ''));
        if ($token === '') {
            return '';
        }

        $path = 'student/register';
        if (!function_exists('workshopCourseUsesShortForm')) {
            require_once __DIR__ . '/workshop_registration_helper.php';
        }
        if (workshopCourseUsesShortForm($course)) {
            $path = 'student/register_workshop';
        }

        return app_url($path) . '?token=' . rawurlencode($token);
    }
}

if (!function_exists('normalizeRegistrationToken')) {
    function normalizeRegistrationToken(string $token): string
    {
        $token = trim(rawurldecode($token));
        return preg_replace('/[\x00-\x1F\x7F-\x9F\x{FEFF}\x{200B}]/u', '', $token) ?? '';
    }
}

if (!function_exists('registration_url_for_qr')) {
    /**
     * Canonical URL embedded in QR PNG files (explicit .php for scanner compatibility).
     */
    function registration_url_for_qr(array $course): string
    {
        if (!function_exists('workshopCourseUsesShortForm')) {
            require_once __DIR__ . '/workshop_registration_helper.php';
        }

        $token = normalizeRegistrationToken((string) ($course['registration_token'] ?? ''));
        if ($token === '') {
            return '';
        }

        $base = rtrim(defined('APP_URL') ? APP_URL : '', '/');
        $script = workshopCourseUsesShortForm($course)
            ? 'student/register_workshop.php'
            : 'student/register.php';

        return $base . '/' . $script . '?token=' . rawurlencode($token);
    }
}

if (!function_exists('loadCourseByRegistrationParam')) {
    /**
     * Resolve a course from token and legacy ?course= links (old printed QR codes).
     */
    function loadCourseByRegistrationParam(mysqli $conn, string $tokenParam, string $courseCodeParam = ''): ?array
    {
        $token = normalizeRegistrationToken($tokenParam);
        if ($token !== '') {
            $stmt = $conn->prepare('SELECT * FROM courses WHERE registration_token = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $token);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    return $row;
                }
            }
        }

        $legacyCode = trim($courseCodeParam);
        if ($legacyCode === '' && $token !== '') {
            $legacyCode = $token;
        }
        if ($legacyCode === '') {
            return null;
        }

        $stmt = $conn->prepare('SELECT * FROM courses WHERE UPPER(TRIM(course_code)) = UPPER(?) LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $legacyCode);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('setCoursesPageNotice')) {
    function setCoursesPageNotice(string $message): void
    {
        $_SESSION['courses_notice'] = $message;
    }
}

if (!function_exists('clearRegistrationFlashFromCoursesPage')) {
    /**
     * Registration errors belong on register.php — not the public courses listing.
     */
    function clearRegistrationFlashFromCoursesPage(): void
    {
        if (!empty($_SESSION['error'])) {
            unset($_SESSION['error']);
        }
        unset(
            $_SESSION['registration_errors'],
            $_SESSION['registration_missing_fields'],
            $_SESSION['registration_form_data']
        );
    }
}

if (!function_exists('resolveCourseIdFromRegistrationPost')) {
    function resolveCourseIdFromRegistrationPost(mysqli $conn, array $post): int
    {
        $course_id = (int) ($post['course_id'] ?? 0);
        $token = trim((string) ($post['registration_token'] ?? ''));

        if ($course_id > 0 || $token === '') {
            return $course_id;
        }

        $stmt = $conn->prepare('SELECT id FROM courses WHERE registration_token = ? LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['id'] ?? 0);
    }
}

if (!function_exists('renderCourseProjectLevelHeader')) {
    function renderCourseProjectLevelHeader(array $row): string {
        $label = trim($row['project_level_label'] ?? '');
        if ($label === '') {
            return '';
        }
        return '<div class="course-project-level"><i class="fas fa-layer-group"></i>'
            . htmlspecialchars($label) . '</div>';
    }
}

if (!function_exists('renderCourseProjectLevelInfoItem')) {
    function renderCourseProjectLevelInfoItem(array $row): string {
        $label = trim($row['project_level_label'] ?? '');
        if ($label === '') {
            return '';
        }
        return '<div class="info-item"><i class="fas fa-layer-group"></i><div>'
            . '<span class="info-label">Project / Level</span>'
            . '<span class="info-value">' . htmlspecialchars($label) . '</span>'
            . '</div></div>';
    }
}
