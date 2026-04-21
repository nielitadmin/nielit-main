<?php
/**
 * Quick Test - API with Passwords
 * Simple test to verify the new endpoint works
 */

// Test the new API endpoint directly
$api_key = 'efb21393037415f4730b729a23b46764aeceee052f1b541eb43a1f26247812ec';

// Make a direct API call
$url = 'http://localhost/public_html/api/v1/students.php?action=list_with_passwords&limit=3';

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "X-API-Key: $api_key\r\n"
    ]
]);

echo "<h2>🔐 Testing New API Endpoint with Passwords</h2>";
echo "<p><strong>URL:</strong> $url</p>";

$response = file_get_contents($url, false, $context);

if ($response) {
    $data = json_decode($response, true);
    
    if ($data && $data['status'] == 200) {
        echo "<div style='color: green;'>✅ <strong>SUCCESS!</strong> New endpoint is working</div>";
        
        $students = $data['data']['students'];
        echo "<h3>📊 Results:</h3>";
        echo "<ul>";
        echo "<li><strong>Total Students:</strong> " . $data['data']['pagination']['total'] . "</li>";
        echo "<li><strong>Students Returned:</strong> " . count($students) . "</li>";
        echo "<li><strong>Warning Message:</strong> " . ($data['data']['warning'] ?? 'None') . "</li>";
        echo "</ul>";
        
        if (!empty($students)) {
            $student = $students[0];
            echo "<h3>👤 Sample Student Data:</h3>";
            echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px; font-family: monospace;'>";
            echo "<strong>Student ID:</strong> " . htmlspecialchars($student['student_id']) . "<br>";
            echo "<strong>Name:</strong> " . htmlspecialchars($student['name']) . "<br>";
            echo "<strong>Email:</strong> " . htmlspecialchars($student['email']) . "<br>";
            echo "<strong>Password Hash:</strong> " . (isset($student['password']) ? '✅ YES (Length: ' . strlen($student['password']) . ')' : '❌ NO') . "<br>";
            echo "<strong>Course ID:</strong> " . htmlspecialchars($student['course_id']) . "<br>";
            echo "</div>";
            
            // Show password verification example
            if (isset($student['password'])) {
                echo "<h3>🔑 Password Verification Example:</h3>";
                echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
                echo "<p><strong>In your mock test app, use this PHP code:</strong></p>";
                echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
                echo "// Verify student password\n";
                echo "\$stored_hash = '" . htmlspecialchars($student['password']) . "';\n";
                echo "\$user_password = 'student_entered_password';\n\n";
                echo "if (password_verify(\$user_password, \$stored_hash)) {\n";
                echo "    // Password is correct - allow login\n";
                echo "    echo 'Login successful!';\n";
                echo "} else {\n";
                echo "    // Password is wrong\n";
                echo "    echo 'Invalid password';\n";
                echo "}";
                echo "</pre>";
                echo "</div>";
            }
        }
        
    } else {
        echo "<div style='color: red;'>❌ <strong>Error:</strong> " . ($data['message'] ?? 'Unknown error') . "</div>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
} else {
    echo "<div style='color: red;'>❌ <strong>Error:</strong> Could not connect to API</div>";
}

echo "<hr>";
echo "<h3>🚀 For Your Mock Test Integration:</h3>";
echo "<div style='background: #e8f4fd; padding: 15px; border-radius: 5px;'>";
echo "<h4>New Endpoint URL:</h4>";
echo "<code>GET /api/v1/students.php?action=list_with_passwords&limit=1000</code>";
echo "<h4>What You Get:</h4>";
echo "<ul>";
echo "<li>✅ Student ID (use as username)</li>";
echo "<li>✅ Email address</li>";
echo "<li>✅ Password hash (for verification)</li>";
echo "<li>✅ Name and other details</li>";
echo "</ul>";
echo "<h4>Integration Steps:</h4>";
echo "<ol>";
echo "<li>Call this endpoint to get all students with passwords</li>";
echo "<li>Store the data in your mock test database</li>";
echo "<li>Use student_id as username for login</li>";
echo "<li>Use password_verify() to check passwords</li>";
echo "</ol>";
echo "</div>";
?>