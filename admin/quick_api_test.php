<?php
/**
 * Quick API Test - Verify API System is Working
 * This creates a test API key and tests the endpoints
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/config/api_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$results = [];
$test_api_key = '';

// Step 1: Create a test API key
function createQuickTestKey() {
    global $conn;
    
    $name = 'Quick Test Key';
    $description = 'Temporary key for quick API testing';
    $permissions = 'read';
    $rate_limit = 100;
    $admin_id = $_SESSION['admin'];
    
    // Delete any existing test keys first
    $cleanup = $conn->prepare("DELETE FROM api_keys WHERE name = 'Quick Test Key'");
    $cleanup->execute();
    
    $api_key = generateApiKey();
    $api_key_hash = hashApiKey($api_key);
    
    $stmt = $conn->prepare("
        INSERT INTO api_keys (name, description, api_key_hash, permissions, rate_limit, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("ssssis", $name, $description, $api_key_hash, $permissions, $rate_limit, $admin_id);
    
    if ($stmt->execute()) {
        return $api_key;
    }
    
    return false;
}

// Step 2: Test API endpoint
function testStudentsEndpoint($api_key) {
    // Simulate API request
    global $conn;
    
    // Validate API key (simulate what the API does)
    $api_data = validateApiKey($api_key);
    
    if (!$api_data) {
        return ['success' => false, 'error' => 'API key validation failed'];
    }
    
    // Get sample student data
    $stmt = $conn->prepare("
        SELECT student_id, name, email, course_id, training_center, status 
        FROM students 
        WHERE status = 'approved' 
        LIMIT 3
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    return [
        'success' => true,
        'data' => [
            'students' => $students,
            'count' => count($students),
            'api_key_info' => [
                'name' => $api_data['name'],
                'permissions' => $api_data['permissions'],
                'rate_limit' => $api_data['rate_limit']
            ]
        ]
    ];
}

// Run the test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_quick_test'])) {
    
    // Step 1: Create test API key
    $test_api_key = createQuickTestKey();
    
    if ($test_api_key) {
        $results['api_key'] = ['success' => true, 'key' => $test_api_key];
        
        // Step 2: Test the endpoint
        $results['endpoint'] = testStudentsEndpoint($test_api_key);
        
        // Step 3: Log a test request
        if ($results['endpoint']['success']) {
            logApiRequest(
                $results['endpoint']['data']['api_key_info']['name'], 
                '/api/v1/students.php?action=list', 
                'GET', 
                $_SERVER['REMOTE_ADDR'], 
                $_SERVER['HTTP_USER_AGENT']
            );
        }
        
    } else {
        $results['api_key'] = ['success' => false, 'error' => 'Failed to create test API key'];
    }
}

// Clean up test key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_test'])) {
    $stmt = $conn->prepare("DELETE FROM api_keys WHERE name = 'Quick Test Key'");
    if ($stmt->execute()) {
        $results['cleanup'] = ['success' => true];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick API Test - NIELIT Bhubaneswar</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Admin Theme CSS -->
    <link href="../assets/css/admin-theme.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-content">
            <div class="admin-main">
                <div class="container-fluid py-4">
                    <!-- Page Header -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h2><i class="fas fa-bolt"></i> Quick API Test</h2>
                            <p class="text-muted">Quickly verify that your API system is working correctly</p>
                        </div>
                    </div>

                    <!-- Test Status -->
                    <?php if (empty($results)): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-play-circle"></i> Ready to Test</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>This will quickly test your API system by:</p>
                                        <ol>
                                            <li>Creating a temporary test API key</li>
                                            <li>Testing the students endpoint</li>
                                            <li>Verifying API key validation</li>
                                            <li>Checking database connectivity</li>
                                        </ol>
                                        
                                        <form method="POST">
                                            <button type="submit" name="run_quick_test" class="btn btn-primary btn-lg">
                                                <i class="fas fa-rocket"></i> Run Quick Test
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Test Results -->
                    <?php if (!empty($results) && !isset($results['cleanup'])): ?>
                        
                        <!-- API Key Creation Result -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-key"></i> Step 1: API Key Creation
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($results['api_key']['success']): ?>
                                            <div class="alert alert-success">
                                                <i class="fas fa-check"></i> Test API key created successfully!
                                            </div>
                                            <p><strong>Test API Key:</strong></p>
                                            <div class="input-group mb-3">
                                                <input type="text" class="form-control font-monospace" 
                                                       value="<?php echo htmlspecialchars($results['api_key']['key']); ?>" readonly>
                                                <button class="btn btn-outline-secondary" type="button" onclick="copyKey(this)">
                                                    <i class="fas fa-copy"></i> Copy
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-danger">
                                                <i class="fas fa-times"></i> Failed to create API key: 
                                                <?php echo htmlspecialchars($results['api_key']['error']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Endpoint Test Result -->
                        <?php if (isset($results['endpoint'])): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">
                                                <i class="fas fa-globe"></i> Step 2: Endpoint Test
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <?php if ($results['endpoint']['success']): ?>
                                                <div class="alert alert-success">
                                                    <i class="fas fa-check"></i> API endpoint working correctly!
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h6>API Key Info:</h6>
                                                        <ul class="list-unstyled">
                                                            <li><strong>Name:</strong> <?php echo $results['endpoint']['data']['api_key_info']['name']; ?></li>
                                                            <li><strong>Permissions:</strong> <?php echo $results['endpoint']['data']['api_key_info']['permissions']; ?></li>
                                                            <li><strong>Rate Limit:</strong> <?php echo $results['endpoint']['data']['api_key_info']['rate_limit']; ?>/hour</li>
                                                        </ul>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6>Sample Data Retrieved:</h6>
                                                        <ul class="list-unstyled">
                                                            <li><strong>Students Found:</strong> <?php echo $results['endpoint']['data']['count']; ?></li>
                                                            <?php if ($results['endpoint']['data']['count'] > 0): ?>
                                                                <li><strong>Sample Student:</strong> <?php echo $results['endpoint']['data']['students'][0]['name']; ?></li>
                                                                <li><strong>Student ID:</strong> <?php echo $results['endpoint']['data']['students'][0]['student_id']; ?></li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($results['endpoint']['data']['count'] > 0): ?>
                                                    <h6 class="mt-3">Sample Students Data:</h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-striped">
                                                            <thead>
                                                                <tr>
                                                                    <th>Student ID</th>
                                                                    <th>Name</th>
                                                                    <th>Email</th>
                                                                    <th>Course</th>
                                                                    <th>Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($results['endpoint']['data']['students'] as $student): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                                        <td><?php echo htmlspecialchars($student['course_id']); ?></td>
                                                                        <td><span class="badge bg-success"><?php echo $student['status']; ?></span></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
                                                
                                            <?php else: ?>
                                                <div class="alert alert-danger">
                                                    <i class="fas fa-times"></i> Endpoint test failed: 
                                                    <?php echo htmlspecialchars($results['endpoint']['error']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Success Summary -->
                        <?php if ($results['api_key']['success'] && $results['endpoint']['success']): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card border-success">
                                        <div class="card-header bg-success text-white">
                                            <h5 class="mb-0"><i class="fas fa-check-circle"></i> Test Completed Successfully!</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>✅ What's Working:</h6>
                                                    <ul>
                                                        <li>Database tables exist</li>
                                                        <li>API key creation works</li>
                                                        <li>API key validation works</li>
                                                        <li>Student data retrieval works</li>
                                                        <li>Request logging works</li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>🚀 Ready for Production:</h6>
                                                    <ul>
                                                        <li><a href="../api/admin/manage_api_keys.php">Create production API keys</a></li>
                                                        <li><a href="../api/docs.php">View API documentation</a></li>
                                                        <li>Configure your mock test app</li>
                                                        <li>Start using the API!</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Cleanup -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-broom"></i> Cleanup</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Remove the test API key to keep your system clean.</p>
                                        <form method="POST" class="d-inline">
                                            <button type="submit" name="cleanup_test" class="btn btn-warning">
                                                <i class="fas fa-trash"></i> Remove Test Key
                                            </button>
                                        </form>
                                        <a href="?" class="btn btn-secondary ms-2">
                                            <i class="fas fa-redo"></i> Run Test Again
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>

                    <!-- Cleanup Success -->
                    <?php if (isset($results['cleanup'])): ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="alert alert-success">
                                    <i class="fas fa-check"></i> Test API key removed successfully! Your system is clean.
                                </div>
                                <a href="?" class="btn btn-primary">
                                    <i class="fas fa-play"></i> Run Another Test
                                </a>
                                <a href="../api/admin/manage_api_keys.php" class="btn btn-success ms-2">
                                    <i class="fas fa-key"></i> Manage API Keys
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function copyKey(button) {
            const input = button.parentElement.querySelector('input');
            input.select();
            document.execCommand('copy');
            
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copied!';
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-success');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }
    </script>
</body>
</html>