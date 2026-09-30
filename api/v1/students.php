<?php
/**
 * NIELIT Bhubaneswar - Students API Endpoint
 * Provides student data for external applications
 */

require_once __DIR__ . '/../config/api_config.php';
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
            getStudentById($student_id);
        } elseif ($email) {
            getStudentByEmail($email);
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
            s.course_id,
            c.course_name,
            s.training_center,
            s.created_at,
            s.status
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
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
                course_id,
                NULL AS course_name,
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
        $students[] = [
            'student_id' => $row['student_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'mobile' => $row['mobile'],
            'course_id' => $row['course_id'],
            'course_name' => $row['course_name'],
            'training_center' => $row['training_center'],
            'created_at' => $row['created_at'],
            'status' => $row['status']
        ];
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
            s.training_center,
            s.created_at,
            s.status
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
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
        $students[] = [
            'student_id' => $row['student_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'mobile' => $row['mobile'],
            'password' => $row['password'],
            'course_id' => $row['course_id'],
            'course_name' => $row['course_name'],
            'training_center' => $row['training_center'],
            'created_at' => $row['created_at'],
            'status' => $row['status']
        ];
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
        $students[] = $row;
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
            s.password,
            s.course_id,
            c.course_name,
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
                password,
                course_id,
                NULL AS course_name,
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
            s.password,
            s.course_id,
            c.course_name,
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
                password,
                course_id,
                NULL AS course_name,
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
            s.training_center,
            s.status
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
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
            s.course_id,
            c.course_name,
            s.batch_id,
            b.batch_code,
            b.batch_name,
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
                course_id,
                NULL AS course_name,
                batch_id,
                NULL AS batch_code,
                NULL AS batch_name,
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
        $students[] = $row;
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
        'SELECT name, email, mobile, training_center
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
        $records[] = [
            'enrollment_id' => (int) ($enrollment['id'] ?? 0),
            'student_record_id' => (int) ($enrollment['student_record_id'] ?? 0),
            'student_id' => $studentId,
            'name' => (string) ($profile['name'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'mobile' => (string) ($profile['mobile'] ?? ''),
            'course_id' => (int) ($enrollment['course_id'] ?? 0),
            'course_code' => (string) ($enrollment['course_code'] ?? ''),
            'course_name' => (string) ($enrollment['course_name'] ?? ''),
            'batch_id' => !empty($enrollment['batch_id']) ? (int) $enrollment['batch_id'] : null,
            'batch_code' => (string) ($enrollment['batch_code'] ?? ''),
            'batch_name' => (string) ($enrollment['batch_name'] ?? ''),
            'training_center' => (string) ($profile['training_center'] ?? ''),
            'status' => (string) ($enrollment['status'] ?? 'active'),
        ];
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
