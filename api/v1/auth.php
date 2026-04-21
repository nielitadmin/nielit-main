<?php
/**
 * NIELIT Bhubaneswar - Authentication API Endpoint
 * Handles student authentication for external applications
 */

require_once __DIR__ . '/../config/api_config.php';

// Authenticate the API request
$api_data = authenticateApiRequest();

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        handleLogin();
        break;
        
    case 'GET':
        handleTokenValidation();
        break;
        
    default:
        sendApiError('Method not allowed', 405);
}

/**
 * Handle student login
 */
function handleLogin() {
    global $conn;
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $username = $input['username'] ?? null; // Can be student_id or email
    $password = $input['password'] ?? null;
    
    if (!$username || !$password) {
        sendApiError('Username and password are required', 400, 'MISSING_CREDENTIALS');
    }
    
    // Try to find student by student_id or email
    $stmt = $conn->prepare("
        SELECT 
            id,
            student_id,
            name,
            email,
            password,
            course_id,
            training_center,
            status,
            created_at
        FROM students 
        WHERE (student_id = ? OR email = ?) AND status = 'approved'
        LIMIT 1
    ");
    
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($student = $result->fetch_assoc()) {
        // Verify password
        if (password_verify($password, $student['password'])) {
            // Authentication successful
            unset($student['password']); // Remove password from response
            
            // Generate session token
            $token = generateSecureToken($student['student_id']);
            
            // Store token in database
            storeAuthToken($student['id'], $token);
            
            $response_data = [
                'success' => true,
                'message' => 'Authentication successful',
                'student' => $student,
                'token' => $token,
                'expires_at' => date('c', time() + API_TOKEN_EXPIRY)
            ];
            
            sendApiResponse($response_data);
        } else {
            // Log failed login attempt
            logFailedLogin($username, $_SERVER['REMOTE_ADDR']);
            sendApiError('Invalid credentials', 401, 'INVALID_CREDENTIALS');
        }
    } else {
        // Log failed login attempt
        logFailedLogin($username, $_SERVER['REMOTE_ADDR']);
        sendApiError('Student not found or not approved', 401, 'STUDENT_NOT_FOUND');
    }
}

/**
 * Handle token validation
 */
function handleTokenValidation() {
    $token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    
    if (!$token) {
        sendApiError('Token is required', 400, 'MISSING_TOKEN');
    }
    
    // Remove "Bearer " prefix if present
    $token = str_replace('Bearer ', '', $token);
    
    $student_data = validateAuthToken($token);
    
    if ($student_data) {
        sendApiResponse([
            'valid' => true,
            'student' => $student_data,
            'expires_at' => $student_data['expires_at']
        ]);
    } else {
        sendApiError('Invalid or expired token', 401, 'INVALID_TOKEN');
    }
}

/**
 * Generate secure authentication token
 */
function generateSecureToken($student_id) {
    $payload = [
        'student_id' => $student_id,
        'issued_at' => time(),
        'expires_at' => time() + API_TOKEN_EXPIRY,
        'random' => bin2hex(random_bytes(16))
    ];
    
    $token = base64_encode(json_encode($payload));
    return hash('sha256', $token) . '.' . $token;
}

/**
 * Store authentication token in database
 */
function storeAuthToken($student_db_id, $token) {
    global $conn;
    
    $token_hash = hash('sha256', $token);
    $expires_at = date('Y-m-d H:i:s', time() + API_TOKEN_EXPIRY);
    
    // Clean up expired tokens first
    $cleanup_stmt = $conn->prepare("DELETE FROM auth_tokens WHERE expires_at < NOW()");
    $cleanup_stmt->execute();
    
    // Store new token
    $stmt = $conn->prepare("
        INSERT INTO auth_tokens (student_id, token_hash, expires_at, created_at) 
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        token_hash = VALUES(token_hash), 
        expires_at = VALUES(expires_at), 
        created_at = NOW()
    ");
    
    $stmt->bind_param("iss", $student_db_id, $token_hash, $expires_at);
    $stmt->execute();
}

/**
 * Validate authentication token
 */
function validateAuthToken($token) {
    global $conn;
    
    $token_parts = explode('.', $token);
    if (count($token_parts) !== 2) {
        return false;
    }
    
    $token_hash = $token_parts[0];
    $token_data = $token_parts[1];
    
    // Verify token hash
    if (hash('sha256', $token_data) !== $token_hash) {
        return false;
    }
    
    // Decode token data
    $payload = json_decode(base64_decode($token_data), true);
    if (!$payload || $payload['expires_at'] < time()) {
        return false;
    }
    
    // Check token in database
    $stmt = $conn->prepare("
        SELECT s.student_id, s.name, s.email, s.course_id, s.training_center, t.expires_at
        FROM auth_tokens t
        JOIN students s ON t.student_id = s.id
        WHERE t.token_hash = ? AND t.expires_at > NOW()
    ");
    
    $db_token_hash = hash('sha256', $token);
    $stmt->bind_param("s", $db_token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Log failed login attempts
 */
function logFailedLogin($username, $ip_address) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO failed_logins (username, ip_address, attempted_at) 
        VALUES (?, ?, NOW())
    ");
    
    $stmt->bind_param("ss", $username, $ip_address);
    $stmt->execute();
}
?>