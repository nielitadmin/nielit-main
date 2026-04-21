<?php
/**
 * NIELIT Bhubaneswar - API Documentation
 * Interactive API documentation and testing interface
 */

require_once __DIR__ . '/config/api_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - NIELIT Bhubaneswar</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Prism.js for syntax highlighting -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism.min.css" rel="stylesheet">
    
    <style>
        .endpoint-card {
            border-left: 4px solid #007bff;
        }
        .method-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .method-get { background-color: #28a745; }
        .method-post { background-color: #007bff; }
        .method-put { background-color: #ffc107; color: #000; }
        .method-delete { background-color: #dc3545; }
        
        pre {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            padding: 1rem;
            font-size: 0.875rem;
        }
        
        .api-tester {
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand">
                <i class="fas fa-code"></i> NIELIT API Documentation
            </span>
            <span class="navbar-text">
                Version <?php echo API_VERSION; ?>
            </span>
        </div>
    </nav>

    <div class="container py-5">
        <!-- Introduction -->
        <div class="row mb-5">
            <div class="col-12">
                <h1>NIELIT Bhubaneswar API</h1>
                <p class="lead">RESTful API for accessing student data and authentication services.</p>
                
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Getting Started</h5>
                    <ol class="mb-0">
                        <li>Contact your administrator to get an API key</li>
                        <li>Include your API key in requests using the <code>X-API-Key</code> header or <code>api_key</code> parameter</li>
                        <li>All responses are in JSON format</li>
                        <li>Rate limit: <?php echo API_RATE_LIMIT; ?> requests per hour per API key</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Authentication -->
        <div class="row mb-5">
            <div class="col-12">
                <h2><i class="fas fa-shield-alt"></i> Authentication</h2>
                <p>All API requests require authentication using an API key.</p>
                
                <h5>Methods:</h5>
                <ul>
                    <li><strong>Header:</strong> <code>X-API-Key: your_api_key_here</code></li>
                    <li><strong>Query Parameter:</strong> <code>?api_key=your_api_key_here</code></li>
                </ul>
                
                <div class="alert alert-warning">
                    <strong>Security Note:</strong> Keep your API key secure and never expose it in client-side code.
                </div>
            </div>
        </div>

        <!-- Students API -->
        <div class="row mb-5">
            <div class="col-12">
                <h2><i class="fas fa-users"></i> Students API</h2>
                <p>Access student data and information.</p>
                
                <!-- Get Students List -->
                <div class="card endpoint-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <span class="badge method-badge method-get">GET</span>
                            Get Students List
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Endpoint:</strong> <code>/api/v1/students.php?action=list</code></p>
                        
                        <h6>Parameters:</h6>
                        <ul>
                            <li><code>limit</code> (optional) - Number of results (default: 50, max: <?php echo API_MAX_RESULTS; ?>)</li>
                            <li><code>offset</code> (optional) - Pagination offset (default: 0)</li>
                        </ul>
                        
                        <h6>Example Request:</h6>
                        <pre><code class="language-bash">curl -X GET "<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/students.php?action=list&limit=10" \
  -H "X-API-Key: your_api_key_here"</code></pre>
                        
                        <h6>Example Response:</h6>
                        <pre><code class="language-json">{
  "status": 200,
  "message": "Success",
  "timestamp": "2024-01-15T10:30:00+00:00",
  "data": {
    "students": [
      {
        "student_id": "NIELIT001",
        "name": "John Doe",
        "email": "john@example.com",
        "mobile": "9876543210",
        "course_id": "DBC",
        "training_center": "Bhubaneswar",
        "created_at": "2024-01-01 10:00:00",
        "status": "approved"
      }
    ],
    "pagination": {
      "total": 1034,
      "limit": 10,
      "offset": 0,
      "has_more": true
    }
  }
}</code></pre>
                    </div>
                </div>

                <!-- Get Students List with Passwords -->
                <div class="card endpoint-card mb-4" style="border-left-color: #dc3545;">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <span class="badge method-badge method-get">GET</span>
                            Get Students List with Passwords
                            <span class="badge bg-warning text-dark ms-2">⚠️ Sensitive</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Endpoint:</strong> <code>/api/v1/students.php?action=list_with_passwords</code></p>
                        
                        <div class="alert alert-warning">
                            <strong>⚠️ Security Warning:</strong> This endpoint includes password hashes. Use only in secure backend applications. Never expose password hashes to client-side code.
                        </div>
                        
                        <h6>Parameters:</h6>
                        <ul>
                            <li><code>limit</code> (optional) - Number of results (default: 50, max: <?php echo API_MAX_RESULTS; ?>)</li>
                            <li><code>offset</code> (optional) - Pagination offset (default: 0)</li>
                        </ul>
                        
                        <h6>Use Case:</h6>
                        <p>This endpoint is specifically designed for mock test integration where you need access to password hashes for authentication in your separate database system.</p>
                        
                        <h6>Example Request:</h6>
                        <pre><code class="language-bash">curl -X GET "<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/students.php?action=list_with_passwords&limit=10" \
  -H "X-API-Key: your_api_key_here"</code></pre>
                        
                        <h6>Example Response:</h6>
                        <pre><code class="language-json">{
  "status": 200,
  "message": "Success",
  "timestamp": "2024-01-15T10:30:00+00:00",
  "data": {
    "students": [
      {
        "student_id": "NIELIT001",
        "name": "John Doe",
        "email": "john@example.com",
        "mobile": "9876543210",
        "password": "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi",
        "course_id": "DBC",
        "training_center": "Bhubaneswar",
        "created_at": "2024-01-01 10:00:00",
        "status": "approved"
      }
    ],
    "pagination": {
      "total": 1034,
      "limit": 10,
      "offset": 0,
      "has_more": true
    },
    "warning": "This endpoint includes password hashes - use securely"
  }
}</code></pre>
                    </div>
                </div>

                <!-- Get Student by ID -->
                <div class="card endpoint-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <span class="badge method-badge method-get">GET</span>
                            Get Student by ID
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Endpoint:</strong> <code>/api/v1/students.php?action=get</code></p>
                        
                        <h6>Parameters:</h6>
                        <ul>
                            <li><code>student_id</code> (required) - Student ID</li>
                            <li><code>email</code> (alternative) - Student email address</li>
                        </ul>
                        
                        <h6>Example Request:</h6>
                        <pre><code class="language-bash">curl -X GET "<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/students.php?action=get&student_id=NIELIT001" \
  -H "X-API-Key: your_api_key_here"</code></pre>
                    </div>
                </div>

                <!-- Search Students -->
                <div class="card endpoint-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <span class="badge method-badge method-get">GET</span>
                            Search Students
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Endpoint:</strong> <code>/api/v1/students.php?action=search</code></p>
                        
                        <h6>Parameters:</h6>
                        <ul>
                            <li><code>q</code> (required) - Search query (minimum 2 characters)</li>
                            <li><code>limit</code> (optional) - Number of results (default: 20, max: 100)</li>
                        </ul>
                        
                        <h6>Example Request:</h6>
                        <pre><code class="language-bash">curl -X GET "<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/students.php?action=search&q=john" \
  -H "X-API-Key: your_api_key_here"</code></pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Authentication API -->
        <div class="row mb-5">
            <div class="col-12">
                <h2><i class="fas fa-lock"></i> Authentication API</h2>
                <p>Authenticate students and manage authentication tokens.</p>
                
                <!-- Student Login -->
                <div class="card endpoint-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <span class="badge method-badge method-post">POST</span>
                            Student Login
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Endpoint:</strong> <code>/api/v1/auth.php</code></p>
                        
                        <h6>Request Body:</h6>
                        <ul>
                            <li><code>username</code> (required) - Student ID or email address</li>
                            <li><code>password</code> (required) - Student password</li>
                        </ul>
                        
                        <h6>Example Request:</h6>
                        <pre><code class="language-bash">curl -X POST "<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/auth.php" \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "NIELIT001",
    "password": "student_password"
  }'</code></pre>
                        
                        <h6>Example Response:</h6>
                        <pre><code class="language-json">{
  "status": 200,
  "message": "Success",
  "timestamp": "2024-01-15T10:30:00+00:00",
  "data": {
    "success": true,
    "message": "Authentication successful",
    "student": {
      "student_id": "NIELIT001",
      "name": "John Doe",
      "email": "john@example.com",
      "course_id": "DBC",
      "training_center": "Bhubaneswar"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_at": "2024-01-15T11:30:00+00:00"
  }
}</code></pre>
                    </div>
                </div>

                <!-- Token Validation -->
                <div class="card endpoint-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <span class="badge method-badge method-get">GET</span>
                            Validate Token
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Endpoint:</strong> <code>/api/v1/auth.php?token=TOKEN</code></p>
                        
                        <h6>Parameters:</h6>
                        <ul>
                            <li><code>token</code> (required) - Authentication token to validate</li>
                        </ul>
                        
                        <h6>Example Request:</h6>
                        <pre><code class="language-bash">curl -X GET "<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/auth.php?token=your_token_here" \
  -H "X-API-Key: your_api_key_here"</code></pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Responses -->
        <div class="row mb-5">
            <div class="col-12">
                <h2><i class="fas fa-exclamation-triangle"></i> Error Responses</h2>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Status Code</th>
                                <th>Error Code</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>400</td>
                                <td>BAD_REQUEST</td>
                                <td>Invalid request parameters</td>
                            </tr>
                            <tr>
                                <td>401</td>
                                <td>MISSING_API_KEY</td>
                                <td>API key is required</td>
                            </tr>
                            <tr>
                                <td>401</td>
                                <td>INVALID_API_KEY</td>
                                <td>Invalid or expired API key</td>
                            </tr>
                            <tr>
                                <td>401</td>
                                <td>INVALID_CREDENTIALS</td>
                                <td>Invalid username or password</td>
                            </tr>
                            <tr>
                                <td>404</td>
                                <td>NOT_FOUND</td>
                                <td>Resource not found</td>
                            </tr>
                            <tr>
                                <td>429</td>
                                <td>RATE_LIMIT_EXCEEDED</td>
                                <td>Too many requests</td>
                            </tr>
                            <tr>
                                <td>500</td>
                                <td>INTERNAL_ERROR</td>
                                <td>Internal server error</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <h6>Example Error Response:</h6>
                <pre><code class="language-json">{
  "status": 401,
  "error": true,
  "message": "Invalid API key",
  "timestamp": "2024-01-15T10:30:00+00:00",
  "error_code": "INVALID_API_KEY"
}</code></pre>
            </div>
        </div>

        <!-- API Tester -->
        <div class="row mb-5">
            <div class="col-12">
                <h2><i class="fas fa-flask"></i> API Tester</h2>
                <p>Test API endpoints directly from this page.</p>
                
                <div class="api-tester">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">API Key</label>
                                <input type="text" class="form-control" id="apiKey" placeholder="Enter your API key">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Endpoint</label>
                                <select class="form-select" id="endpoint">
                                    <option value="students.php?action=list">Get Students List</option>
                                    <option value="students.php?action=get&student_id=">Get Student by ID</option>
                                    <option value="students.php?action=search&q=">Search Students</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Parameters</label>
                                <input type="text" class="form-control" id="parameters" placeholder="e.g., NIELIT001 or john">
                            </div>
                            <button class="btn btn-primary" onclick="testApi()">
                                <i class="fas fa-play"></i> Test API
                            </button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Response</label>
                            <pre id="apiResponse" style="height: 300px; overflow-y: auto;">Click "Test API" to see the response here...</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Prism.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/autoloader/prism-autoloader.min.js"></script>
    
    <script>
        function testApi() {
            const apiKey = document.getElementById('apiKey').value;
            const endpoint = document.getElementById('endpoint').value;
            const parameters = document.getElementById('parameters').value;
            const responseDiv = document.getElementById('apiResponse');
            
            if (!apiKey) {
                responseDiv.textContent = 'Please enter your API key';
                return;
            }
            
            let url = `/api/v1/${endpoint}`;
            if (parameters && endpoint.includes('student_id=')) {
                url = url.replace('student_id=', `student_id=${parameters}`);
            } else if (parameters && endpoint.includes('q=')) {
                url = url.replace('q=', `q=${parameters}`);
            }
            
            responseDiv.textContent = 'Loading...';
            
            fetch(url, {
                headers: {
                    'X-API-Key': apiKey
                }
            })
            .then(response => response.json())
            .then(data => {
                responseDiv.textContent = JSON.stringify(data, null, 2);
            })
            .catch(error => {
                responseDiv.textContent = `Error: ${error.message}`;
            });
        }
    </script>
</body>
</html>