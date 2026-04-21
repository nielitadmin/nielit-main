<?php
/**
 * NIELIT Bhubaneswar - API Configuration
 * Secure API for External Applications
 */

// API Configuration
define('API_VERSION', '1.0');
define('API_BASE_URL', '/api/v1/');

// API Security Settings
define('API_KEY_LENGTH', 64);
define('API_TOKEN_EXPIRY', 3600); // 1 hour
define('API_RATE_LIMIT', 100); // requests per hour per API key
define('API_MAX_RESULTS', 1000); // maximum records per request

// CORS Settings
define('CORS_ALLOWED_ORIGINS', [
    'http://localhost',
    'http://localhost:3000',
    'http://localhost:8080',
    'https://yourmocktestdomain.com' // Add your mock test domain here
]);

// API Response Headers
header('Content-Type: application/json');
header('X-API-Version: ' . API_VERSION);

// Enable CORS for allowed origins
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, CORS_ALLOWED_ORIGINS)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
}

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection for API
require_once __DIR__ . '/../../config/config.php';

// API Helper Functions
function generateApiKey() {
    return bin2hex(random_bytes(API_KEY_LENGTH / 2));
}

function hashApiKey($key) {
    return hash('sha256', $key);
}

function sendApiResponse($data, $status = 200, $message = 'Success') {
    http_response_code($status);
    
    $response = [
        'status' => $status,
        'message' => $message,
        'timestamp' => date('c'),
        'data' => $data
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

function sendApiError($message, $status = 400, $error_code = null) {
    http_response_code($status);
    
    $response = [
        'status' => $status,
        'error' => true,
        'message' => $message,
        'timestamp' => date('c')
    ];
    
    if ($error_code) {
        $response['error_code'] = $error_code;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

function validateApiKey($api_key) {
    global $conn;
    
    if (empty($api_key)) {
        return false;
    }
    
    $hashed_key = hashApiKey($api_key);
    
    $stmt = $conn->prepare("
        SELECT id, name, permissions, rate_limit, is_active, last_used, created_at 
        FROM api_keys 
        WHERE api_key_hash = ? AND is_active = 1
    ");
    $stmt->bind_param("s", $hashed_key);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        return false;
    }
    
    // Update last used timestamp
    $update_stmt = $conn->prepare("UPDATE api_keys SET last_used = NOW() WHERE id = ?");
    $update_stmt->bind_param("i", $result['id']);
    $update_stmt->execute();
    
    return $result;
}

function checkRateLimit($api_key_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as request_count 
        FROM api_requests 
        WHERE api_key_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->bind_param("i", $api_key_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['request_count'] < API_RATE_LIMIT;
}

function logApiRequest($api_key_id, $endpoint, $method, $ip_address, $user_agent) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO api_requests (api_key_id, endpoint, method, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("issss", $api_key_id, $endpoint, $method, $ip_address, $user_agent);
    $stmt->execute();
}

// API Authentication Middleware
function authenticateApiRequest() {
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    
    if (!$api_key) {
        sendApiError('API key is required', 401, 'MISSING_API_KEY');
    }
    
    $api_data = validateApiKey($api_key);
    if (!$api_data) {
        sendApiError('Invalid API key', 401, 'INVALID_API_KEY');
    }
    
    // Check rate limit
    if (!checkRateLimit($api_data['id'])) {
        sendApiError('Rate limit exceeded', 429, 'RATE_LIMIT_EXCEEDED');
    }
    
    // Log the request
    $endpoint = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    logApiRequest($api_data['id'], $endpoint, $method, $ip_address, $user_agent);
    
    return $api_data;
}
?>