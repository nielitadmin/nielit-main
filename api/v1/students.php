<?php
/**
 * NIELIT Bhubaneswar - Students API Endpoint
 * Provides student data for external applications
 */

require_once __DIR__ . '/../config/api_config.php';

// Authenticate the request
$api_data = authenticateApiRequest();

// Get request parameters
$action = $_GET['action'] ?? 'list';
$student_id = $_GET['student_id'] ?? null;
$email = $_GET['email'] ?? null;
$limit = (int)($_GET['limit'] ?? 50);
$offset = (int)($_GET['offset'] ?? 0);

// For list_with_passwords action, allow unlimited data if limit is set to 0 or very high
if ($action === 'list_with_passwords' && ($limit == 0 || $limit > 10000)) {
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

/**
 * Get list of students with pagination
 */
function getStudentsList($limit, $offset) {
    global $conn;
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM students WHERE status IN ('approved', 'active')";
    $count_result = $conn->query($count_query);
    $total = $count_result->fetch_assoc()['total'];
    
    // Get students data
    $stmt = $conn->prepare("
        SELECT 
            student_id,
            name,
            email,
            mobile,
            course_id,
            training_center,
            created_at,
            status
        FROM students 
        WHERE status IN ('approved', 'active')
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $students[] = [
            'student_id' => $row['student_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'mobile' => $row['mobile'],
            'course_id' => $row['course_id'],
            'training_center' => $row['training_center'],
            'created_at' => $row['created_at'],
            'status' => $row['status']
        ];
    }
    
    $response_data = [
            $stmt = $conn->prepare("
        'students' => $students,
        'pagination' => [
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ]
    ];
    
    sendApiResponse($response_data);
}

/**
 * Get list of students with passwords (for mock test integration)
 * WARNING: This endpoint exposes password hashes - use carefully
 */
function getStudentsListWithPasswords($limit, $offset) {
    global $conn;
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM students WHERE status IN ('approved', 'active')";
    $count_result = $conn->query($count_query);
    $total = $count_result->fetch_assoc()['total'];
    
    // Get students data with passwords
    $stmt = $conn->prepare("
        SELECT 
            student_id,
                    'course_id' => $row['course_id'],
                    'course_name' => $row['course_name'],
            email,
            mobile,
            password,
            course_id,
            training_center,
            created_at,
            status
        FROM students 
        WHERE status IN ('approved', 'active')
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = [
            'student_id' => $row['student_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'mobile' => $row['mobile'],
            'password' => $row['password'], // Include password hash
            'course_id' => $row['course_id'],
            'training_center' => $row['training_center'],
            'created_at' => $row['created_at'],
            'status' => $row['status']
        ];
    }
    
    $response_data = [
        'students' => $students,
        'pagination' => [
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ],
        'warning' => 'This endpoint includes password hashes - use securely'
    ];
    
    sendApiResponse($response_data);
}

/**
 * Export ALL students with passwords (no limits)
 * Specifically for mock test database import
 */
function exportAllStudentsWithPasswords() {
    global $conn;
    
    // Get total count first
    $count_query = "SELECT COUNT(*) as total FROM students WHERE status IN ('approved', 'active')";
    $count_result = $conn->query($count_query);
    $total = $count_result->fetch_assoc()['total'];
    
    // Get ALL students data with passwords (no limit)
    $stmt = $conn->prepare("
        SELECT 
            student_id,
            name,
            father_name,
            mother_name,
            email,
            mobile,
            password,
            course_id,
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
        WHERE status IN ('approved', 'active')
        ORDER BY created_at DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = [
            'student_id' => $row['student_id'],
            'name' => $row['name'],
            'father_name' => $row['father_name'],
            'mother_name' => $row['mother_name'],
            'email' => $row['email'],
            'mobile' => $row['mobile'],
            'password' => $row['password'], // Include password hash
            'course_id' => $row['course_id'],
            'training_center' => $row['training_center'],
            'created_at' => $row['created_at'],
            'status' => $row['status'],
            'dob' => $row['dob'],
            'gender' => $row['gender'],
            'address' => $row['address'],
            'city' => $row['city'],
            'state' => $row['state'],
            'pincode' => $row['pincode']
        ];
    }
    
    $response_data = [
        'students' => $students,
        'total_count' => (int)$total,
        'exported_count' => count($students),
        'export_type' => 'complete_dataset',
        'warning' => 'This endpoint includes ALL student password hashes - use securely',
        'note' => 'Complete dataset exported for mock test integration'
    ];
    
    sendApiResponse($response_data);
}

/**
 * Get student by ID
 */
function getStudentById($student_id) {
    global $conn;
    
        $stmt = $conn->prepare("
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
            WHERE s.student_id = ? AND s.status IN ('approved', 'active')
        ");
    
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($student = $result->fetch_assoc()) {
        // Remove sensitive data for security
        unset($student['password']); // Don't expose password hash
        
        sendApiResponse(['student' => $student]);
    } else {
        sendApiError('Student not found', 404);
    }
}

/**
 * Get student by email
 */
function getStudentByEmail($email) {
    global $conn;
    
        $stmt = $conn->prepare("
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
            WHERE s.email = ? AND s.status IN ('approved', 'active')
        ");
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($student = $result->fetch_assoc()) {
        // Remove sensitive data for security
        unset($student['password']); // Don't expose password hash
        
        sendApiResponse(['student' => $student]);
    } else {
        sendApiError('Student not found', 404);
    }
}

/**
 * Authenticate student credentials
 */
function authenticateStudent() {
    global $conn;
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? $_POST['username'] ?? null; // Can be student_id or email
    $password = $input['password'] ?? $_POST['password'] ?? null;
    
    if (!$username || !$password) {
        sendApiError('Username and password are required', 400);
    }
    
    // Try to find student by student_id or email
    $stmt = $conn->prepare("
        SELECT 
            student_id,
            name,
            email,
            password,
            course_id,
            training_center,
            status
        FROM students 
        WHERE (student_id = ? OR email = ?) AND status IN ('approved', 'active')
    ");
    
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($student = $result->fetch_assoc()) {
        // Verify password
        if (password_verify($password, $student['password'])) {
            // Authentication successful
            unset($student['password']); // Remove password from response
            
            $response_data = [
                'authenticated' => true,
                'student' => $student,
                'token' => generateAuthToken($student['student_id']) // Optional: generate JWT token
            ];
            
            sendApiResponse($response_data);
        } else {
            sendApiError('Invalid credentials', 401);
        }
    } else {
        sendApiError('Student not found or not in allowed status', 401);
    }
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
    
    $search_term = "%$query%";
    
    $stmt = $conn->prepare("
        SELECT 
            student_id,
            name,
            email,
            mobile,
            course_id,
            training_center,
            status
        FROM students 
        WHERE (
            name LIKE ? OR 
            email LIKE ? OR 
            student_id LIKE ?
        ) AND status IN ('approved', 'active')
        ORDER BY name ASC
        LIMIT ?
    ");
    
    $stmt->bind_param("sssi", $search_term, $search_term, $search_term, $limit);
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
?>