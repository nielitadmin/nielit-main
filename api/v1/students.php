<?php
/**
 * NIELIT Bhubaneswar - Students API Endpoint
 * Provides student data for external applications
 */

require_once __DIR__ . '/../config/api_config.php';
require_once __DIR__ . '/../includes/student_fields.php';
require_once __DIR__ . '/../../includes/multi_course_helper.php';

// Authenticate the request
$api_data = authenticateApiRequest();

// Get request parameters
$action = $_GET['action'] ?? 'list';
$student_id = $_GET['student_id'] ?? null;
$email = $_GET['email'] ?? null;
$limit = (int)($_GET['limit'] ?? 50);
$offset = (int)($_GET['offset'] ?? 0);

// For list_with_passwords action, allow unlimited data if limit is set to 0 or very high
if ($action === 'list_with_passwords' && ($limit === 0 || $limit > 10000)) {
    $limit = 999999; // Effectively unlimited
} else {
    $limit = min($limit, API_MAX_RESULTS);
}

switch ($action) {
    case 'list':
        getStudentsList($limit, $offset);
        break;

    case 'list_with_passwords':
        getStudentsListWithPasswords($limit, $offset);
        break;

    case 'export_all':
        exportAllStudentsWithPasswords();
        break;

    case 'get':
        if ($student_id) {
            if (hasSensitiveApiPermission($api_data)) {
                getStudentByIdWithPassword($student_id);
            } else {
                getStudentById($student_id);
            }
        } elseif ($email) {
            if (hasSensitiveApiPermission($api_data)) {
                getStudentByEmailWithPassword($email);
            } else {
                getStudentByEmail($email);
            }
        } else {
            sendApiError('student_id or email parameter is required', 400);
        }
        break;

    case 'get_with_password':
        if (!hasSensitiveApiPermission($api_data)) {
            sendApiError('Admin API permission is required for password hash access', 403, 'INSUFFICIENT_PERMISSION');
        }
        if ($student_id) {
            getStudentByIdWithPassword($student_id);
        } elseif ($email) {
            getStudentByEmailWithPassword($email);
        } else {
            sendApiError('student_id or email parameter is required', 400);
        }
        break;

    case 'authenticate':
        authenticateStudent();
        break;

    case 'search':
        searchStudents();
        break;

    default:
        sendApiError('Invalid action', 400);
}

function allowedStatusCondition($alias = '') {
    $prefix = $alias ? ($alias . '.') : '';
    return $prefix . "status IN ('approved', 'active')";
}

function hasSensitiveApiPermission(array $apiData): bool {
    $permissions = strtolower(trim((string) ($apiData['permissions'] ?? '')));
    return in_array($permissions, ['admin', 'read_write'], true);
}

function studentPhotoUrl($photoPath): ?string {
    $photoPath = trim((string) $photoPath);
    if ($photoPath === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $photoPath)) {
        return $photoPath;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $baseUrl = $host !== '' ? $scheme . '://' . $host : (defined('APP_URL') ? APP_URL : '');
    $encodedPath = implode('/', array_map('rawurlencode', explode('/', ltrim(str_replace('\\', '/', $photoPath), '/'))));
    return rtrim($baseUrl, '/') . '/' . $encodedPath;
}

function addEnrollmentRecordsToStudent(array $student): array {
    global $conn;

    $studentId = trim((string) ($student['student_id'] ?? ''));
    $student['photo_url'] = studentPhotoUrl($student['passport_photo'] ?? '');
    $student = apiAppendCourseBatchFields($student);
    if ($studentId === '') {
        $student['enrollments'] = [];
        $student['enrollment_count'] = 0;
        return $student;
    }

    if (function_exists('getEnrollmentsForStudentId')) {
        $enrollments = getEnrollmentsForStudentId($conn, $studentId);
        $student['enrollments'] = array_map('apiFormatEnrollmentRecord', $enrollments);
    } else {
        $enrollments = searchMultiCourseEnrollments($conn, $studentId, 100);
        $student['enrollments'] = is_array($enrollments) ? $enrollments : [];
    }
    $student['enrollment_count'] = count($student['enrollments']);
    return $student;
}

/**
 * Get list of students with pagination
 */
function getStudentsList($limit, $offset) {
    global $conn;

    $count_query = "SELECT COUNT(*) as total FROM students WHERE " . allowedStatusCondition();
    $count_result = $conn->query($count_query);
    $total = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;

    $sql = "
        SELECT
            s.student_id,
            s.name,
            s.email,
            s.mobile,
            s.passport_photo,
            s.course_id,
            c.course_name,
            c.duration AS course_duration,
            s.batch_id,
            b.batch_code,
            b.batch_name,
            b.start_date AS batch_start_date,
            b.end_date AS batch_end_date,
            s.training_center,
            s.created_at,
            s.status
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        WHERE " . allowedStatusCondition('s') . "
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $fallback_sql = "
            SELECT
                student_id,
                name,
                email,
                mobile,
                passport_photo,
                course_id,
                NULL AS course_name,
                NULL AS course_duration,
                batch_id,
                NULL AS batch_code,
                NULL AS batch_name,
                NULL AS batch_start_date,
                NULL AS batch_end_date,
                training_center,
                created_at,
                status
            FROM students
            WHERE " . allowedStatusCondition() . "
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($fallback_sql);
    }

    if (!$stmt) {
        sendApiError('Failed to prepare student list query', 500, 'QUERY_PREPARE_FAILED');
    }

    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = apiMapStudentListRow($row);
    }

    sendApiResponse([
        'students' => $students,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ]
    ]);
}

/**
 * Get list of students with passwords (for mock test integration)
 * WARNING: This endpoint exposes password hashes - use carefully
 */
function getStudentsListWithPasswords($limit, $offset) {
    global $conn;

    $count_query = "SELECT COUNT(*) as total FROM students WHERE " . allowedStatusCondition();
    $count_result = $conn->query($count_query);
    $total = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;

    $sql = "
        SELECT
            s.student_id,
            s.name,
            s.email,
            s.mobile,
            s.password,
            s.course_id,
            c.course_name,
            c.duration AS course_duration,
            s.batch_id,
            b.batch_code,
            b.batch_name,
            b.start_date AS batch_start_date,
            b.end_date AS batch_end_date,
            s.training_center,
            s.created_at,
            s.status
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        WHERE " . allowedStatusCondition('s') . "
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $fallback_sql = "
            SELECT
                student_id,
                name,
                email,
                mobile,
                password,
                course_id,
                NULL AS course_name,
                NULL AS course_duration,
                batch_id,
                NULL AS batch_code,
                NULL AS batch_name,
                NULL AS batch_start_date,
                NULL AS batch_end_date,
                training_center,
                created_at,
                status
            FROM students
            WHERE " . allowedStatusCondition() . "
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($fallback_sql);
    }

    if (!$stmt) {
        sendApiError('Failed to prepare password list query', 500, 'QUERY_PREPARE_FAILED');
    }

    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = apiMapStudentListRow($row, true);
    }

    sendApiResponse([
        'students' => $students,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ],
        'warning' => 'This endpoint includes password hashes - use securely'
    ]);
}

/**
 * Export ALL students with passwords (no limits)
 */
function exportAllStudentsWithPasswords() {
    global $conn;

    $count_query = "SELECT COUNT(*) as total FROM students WHERE " . allowedStatusCondition();
    $count_result = $conn->query($count_query);
    $total = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;

    $sql = "
        SELECT
            s.student_id,
            s.name,
            s.father_name,
            s.mother_name,
            s.email,
            s.mobile,
            s.password,
            s.course_id,
            c.course_name,
            c.duration AS course_duration,
            s.batch_id,
            b.batch_code,
            b.batch_name,
            b.start_date AS batch_start_date,
            b.end_date AS batch_end_date,
            s.training_center,
            s.created_at,
            s.status,
            s.dob,
            s.gender,
            s.address,
            s.city,
            s.state,
            s.pincode
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        WHERE " . allowedStatusCondition('s') . "
        ORDER BY s.created_at DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $fallback_sql = "
            SELECT
                student_id,
                name,
                father_name,
                mother_name,
                email,
                mobile,
                password,
                course_id,
                NULL AS course_name,
                NULL AS course_duration,
                batch_id,
                NULL AS batch_code,
                NULL AS batch_name,
                NULL AS batch_start_date,
                NULL AS batch_end_date,
                training_center,
                created_at,
                status,
                dob,
                gender,
                address,
                city,
                state,
                pincode
            FROM students
            WHERE " . allowedStatusCondition() . "
            ORDER BY created_at DESC
        ";
        $stmt = $conn->prepare($fallback_sql);
    }

    if (!$stmt) {
        sendApiError('Failed to prepare export query', 500, 'QUERY_PREPARE_FAILED');
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = apiAppendCourseBatchFields($row);
    }

    sendApiResponse([
        'students' => $students,
        'total_count' => $total,
        'exported_count' => count($students),
        'export_type' => 'complete_dataset',
        'warning' => 'This endpoint includes ALL student password hashes - use securely',
        'note' => 'Complete dataset exported for mock test integration'
    ]);
}

/**
 * Get student by ID
 */
function getStudentById($student_id) {
    global $conn;

    $sql = "
        SELECT
            s.student_id,
            s.name,
            s.father_name,
            s.mother_name,
            s.email,
            s.mobile,
            s.passport_photo,
            s.course_id,
            c.course_name,
            c.duration AS course_duration,
            s.batch_id,
            b.batch_code,
            b.batch_name,
            b.start_date AS batch_start_date,
            b.end_date AS batch_end_date,
            s.training_center,
            s.created_at,
            s.status,
            s.dob,
            s.gender,
            s.address,
            s.city,
            s.state,
            s.pincode
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        WHERE s.student_id = ? AND " . allowedStatusCondition('s') . "
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $fallback_sql = "
            SELECT
                student_id,
                name,
                father_name,
                mother_name,
                email,
                mobile,
                passport_photo,
                course_id,
                NULL AS course_name,
                NULL AS course_duration,
                batch_id,
                NULL AS batch_code,
                NULL AS batch_name,
                NULL AS batch_start_date,
                NULL AS batch_end_date,
                training_center,
                created_at,
                status,
                dob,
                gender,
                address,
                city,
                state,
                pincode
            FROM students
            WHERE student_id = ? AND " . allowedStatusCondition() . "
        ";
        $stmt = $conn->prepare($fallback_sql);
    }

    if (!$stmt) {
        sendApiError('Failed to prepare student lookup query', 500, 'QUERY_PREPARE_FAILED');
    }

    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($student = $result->fetch_assoc()) {
        $student = addEnrollmentRecordsToStudent($student);
        sendApiResponse(['student' => $student]);
    }

    sendApiError('Student not found', 404);
}

/**
 * Get student by email
 */
function getStudentByEmail($email) {
    global $conn;

    $sql = "
        SELECT
            s.student_id,
            s.name,
            s.father_name,
            s.mother_name,
            s.email,
            s.mobile,
            s.passport_photo,
            s.course_id,
            c.course_name,
            c.duration AS course_duration,
            s.batch_id,
            b.batch_code,
            b.batch_name,
            b.start_date AS batch_start_date,
            b.end_date AS batch_end_date,
            s.training_center,
            s.created_at,
            s.status,
            s.dob,
            s.gender,
            s.address,
            s.city,
            s.state,
            s.pincode
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        WHERE s.email = ? AND " . allowedStatusCondition('s') . "
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $fallback_sql = "
            SELECT
                student_id,
                name,
                father_name,
                mother_name,
                email,
                mobile,
                passport_photo,
                course_id,
                NULL AS course_name,
                NULL AS course_duration,
                batch_id,
                NULL AS batch_code,
                NULL AS batch_name,
                NULL AS batch_start_date,
                NULL AS batch_end_date,
                training_center,
                created_at,
                status,
                dob,
                gender,
                address,
                city,
                state,
                pincode
            FROM students
            WHERE email = ? AND " . allowedStatusCondition() . "
        ";
        $stmt = $conn->prepare($fallback_sql);
    }

    if (!$stmt) {
        sendApiError('Failed to prepare email lookup query', 500, 'QUERY_PREPARE_FAILED');
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($student = $result->fetch_assoc()) {
        $student = addEnrollmentRecordsToStudent($student);
        sendApiResponse(['student' => $student]);
    }

    sendApiError('Student not found', 404);
}

function getStudentByIdWithPassword($studentId): void {
    getStudentWithPassword('s.student_id = ?', 's', (string) $studentId);
}

function getStudentByEmailWithPassword($email): void {
    getStudentWithPassword('s.email = ?', 's', (string) $email);
}

function getStudentWithPassword(string $whereClause, string $bindType, string $value): void {
    global $conn;

    $sql = "
        SELECT
            s.student_id,
            s.name,
            s.father_name,
            s.mother_name,
            s.email,
            s.mobile,
            s.password,
            s.course_id,
            c.course_name,
            c.duration AS course_duration,
            s.batch_id,
            b.batch_code,
            b.batch_name,
            b.start_date AS batch_start_date,
            b.end_date AS batch_end_date,
            s.training_center,
            s.created_at,
            s.status,
            s.dob,
            s.gender,
            s.address,
            s.city,
            s.state,
            s.pincode
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        WHERE {$whereClause} AND " . allowedStatusCondition('s') . "
        ORDER BY s.id DESC
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendApiError('Failed to prepare sensitive student lookup query', 500, 'QUERY_PREPARE_FAILED');
    }

    $stmt->bind_param($bindType, $value);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($student) {
        $student = addEnrollmentRecordsToStudent($student);
        sendApiResponse(['student' => $student]);
    }

    sendApiError('Student not found', 404);
}

/**
 * Authenticate student credentials (legacy action in this endpoint)
 */
function authenticateStudent() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? $_POST['username'] ?? null;
    $password = $input['password'] ?? $_POST['password'] ?? null;

    if (!$username || !$password) {
        sendApiError('Username and password are required', 400);
    }

    $sql = "
        SELECT
            s.student_id,
            s.name,
            s.email,
            s.password,
            s.course_id,
            c.course_name,
            c.duration AS course_duration,
            s.batch_id,
            b.batch_code,
            b.batch_name,
            b.start_date AS batch_start_date,
            b.end_date AS batch_end_date,
            s.training_center,
            s.status
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        WHERE (s.student_id = ? OR s.email = ?) AND " . allowedStatusCondition('s') . "
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $fallback_sql = "
            SELECT
                student_id,
                name,
                email,
                password,
                course_id,
                NULL AS course_name,
                NULL AS course_duration,
                batch_id,
                NULL AS batch_code,
                NULL AS batch_name,
                NULL AS batch_start_date,
                NULL AS batch_end_date,
                training_center,
                status
            FROM students
            WHERE (student_id = ? OR email = ?) AND " . allowedStatusCondition() . "
            LIMIT 1
        ";
        $stmt = $conn->prepare($fallback_sql);
    }

    if (!$stmt) {
        sendApiError('Failed to prepare authentication query', 500, 'QUERY_PREPARE_FAILED');
    }

    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($student = $result->fetch_assoc()) {
        if (password_verify($password, $student['password'])) {
            unset($student['password']);
            $student = apiAppendCourseBatchFields($student);
            sendApiResponse([
                'authenticated' => true,
                'student' => $student,
                'token' => generateAuthToken($student['student_id'])
            ]);
        }

        sendApiError('Invalid credentials', 401);
    }

    sendApiError('Student not found or not in allowed status', 401);
}

/**
 * Search students by name, email, or student_id
 */
function searchStudents() {
    global $conn;

    $query = $_GET['q'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 20), 100);

    if (strlen($query) < 2) {
        sendApiError('Search query must be at least 2 characters', 400);
    }

    $enrollmentRecords = searchMultiCourseEnrollments($conn, trim($query), $limit);
    if ($enrollmentRecords !== null) {
        sendApiResponse([
            'students' => $enrollmentRecords,
            'query' => $query,
            'count' => count($enrollmentRecords),
            'source' => 'student_enrollments',
        ]);
    }

    $search_term = "%$query%";

    $sql = "
        SELECT
            s.id AS student_record_id,
            s.student_id,
            s.name,
            s.email,
            s.mobile,
            s.passport_photo,
            s.course_id,
            c.course_name,
            c.duration AS course_duration,
            s.batch_id,
            b.batch_code,
            b.batch_name,
            b.start_date AS batch_start_date,
            b.end_date AS batch_end_date,
            s.training_center,
            s.status
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN batches b ON s.batch_id = b.id
        WHERE (
            s.name LIKE ? OR
            s.email LIKE ? OR
            s.student_id LIKE ?
        ) AND " . allowedStatusCondition('s') . "
        ORDER BY s.name ASC
        LIMIT ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $fallback_sql = "
            SELECT
                id AS student_record_id,
                student_id,
                name,
                email,
                mobile,
                passport_photo,
                course_id,
                NULL AS course_name,
                NULL AS course_duration,
                batch_id,
                NULL AS batch_code,
                NULL AS batch_name,
                NULL AS batch_start_date,
                NULL AS batch_end_date,
                training_center,
                status
            FROM students
            WHERE (
                name LIKE ? OR
                email LIKE ? OR
                student_id LIKE ?
            ) AND " . allowedStatusCondition() . "
            ORDER BY name ASC
            LIMIT ?
        ";
        $stmt = $conn->prepare($fallback_sql);
    }

    if (!$stmt) {
        sendApiError('Failed to prepare search query', 500, 'QUERY_PREPARE_FAILED');
    }

    $stmt->bind_param('sssi', $search_term, $search_term, $search_term, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $row['photo_url'] = studentPhotoUrl($row['passport_photo'] ?? '');
        unset($row['passport_photo']);
        $students[] = apiAppendCourseBatchFields($row);
    }

    sendApiResponse([
        'students' => $students,
        'query' => $query,
        'count' => count($students)
    ]);
}

/**
 * Return the same multi-course enrollment records used by the student portal
 * when the caller searches for an exact student ID.
 */
function searchMultiCourseEnrollments(mysqli $conn, string $studentId, int $limit): ?array
{
    if (!function_exists('isMultiCourseSystemInstalled')
        || !isMultiCourseSystemInstalled($conn)
        || !preg_match('/^NIELIT\/[^\/]+\/[^\/]+\/\d+$/i', $studentId)) {
        return null;
    }

    $enrollments = getEnrollmentsForStudentId($conn, $studentId);
    if ($enrollments === []) {
        return null;
    }

    $profile = [];
    $profileStmt = $conn->prepare(
        'SELECT name, father_name, mother_name, email, mobile, passport_photo, training_center,
            created_at, dob, gender, address, city, state, pincode
         FROM students
         WHERE student_id = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    if ($profileStmt) {
        $profileStmt->bind_param('s', $studentId);
        $profileStmt->execute();
        $profile = $profileStmt->get_result()->fetch_assoc() ?: [];
        $profileStmt->close();
    }

    $records = [];
    foreach (array_slice($enrollments, 0, $limit) as $enrollment) {
        $record = apiFormatEnrollmentRecord($enrollment);
        $record['photo_url'] = studentPhotoUrl($profile['passport_photo'] ?? '');
        $record['name'] = (string) ($profile['name'] ?? '');
        $record['father_name'] = (string) ($profile['father_name'] ?? '');
        $record['mother_name'] = (string) ($profile['mother_name'] ?? '');
        $record['email'] = (string) ($profile['email'] ?? '');
        $record['mobile'] = (string) ($profile['mobile'] ?? '');
        $record['training_center'] = (string) ($profile['training_center'] ?? '');
        $record['created_at'] = $profile['created_at'] ?? null;
        $record['dob'] = $profile['dob'] ?? null;
        $record['gender'] = (string) ($profile['gender'] ?? '');
        $record['address'] = (string) ($profile['address'] ?? '');
        $record['city'] = (string) ($profile['city'] ?? '');
        $record['state'] = (string) ($profile['state'] ?? '');
        $record['pincode'] = (string) ($profile['pincode'] ?? '');
        $records[] = $record;
    }

    return $records;
}

/**
 * Generate authentication token (simple implementation)
 */
function generateAuthToken($student_id) {
    $payload = [
        'student_id' => $student_id,
        'issued_at' => time(),
        'expires_at' => time() + API_TOKEN_EXPIRY
    ];

    return base64_encode(json_encode($payload));
}
