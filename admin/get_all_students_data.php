<?php
/**
 * Get ALL Students Data with Passwords
 * Complete dataset export for mock test integration
 */

// Your API key
$api_key = 'efb21393037415f4730b729a23b46764aeceee052f1b541eb43a1f26247812ec';

echo "<h2>📊 Getting ALL Students Data</h2>";
echo "<p><strong>Purpose:</strong> Export complete dataset for mock test integration</p>";

// Test the export_all endpoint
$url = 'http://localhost/public_html/api/v1/students.php?action=export_all';

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "X-API-Key: $api_key\r\n"
    ]
]);

echo "<p><strong>Endpoint:</strong> $url</p>";
echo "<p><strong>Status:</strong> Fetching data...</p>";

$start_time = microtime(true);
$response = file_get_contents($url, false, $context);
$end_time = microtime(true);

if ($response) {
    $data = json_decode($response, true);
    
    if ($data && $data['status'] == 200) {
        echo "<div style='color: green; font-size: 18px; font-weight: bold;'>✅ SUCCESS! Complete dataset retrieved</div>";
        
        $students = $data['data']['students'];
        $total_count = $data['data']['total_count'];
        $exported_count = $data['data']['exported_count'];
        
        echo "<h3>📈 Dataset Summary:</h3>";
        echo "<div style='background: #f0f8ff; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
        echo "<ul style='font-size: 16px; line-height: 1.6;'>";
        echo "<li><strong>Total Students in Database:</strong> " . number_format($total_count) . "</li>";
        echo "<li><strong>Students Exported:</strong> " . number_format($exported_count) . "</li>";
        echo "<li><strong>Export Time:</strong> " . round($end_time - $start_time, 2) . " seconds</li>";
        echo "<li><strong>Data Size:</strong> " . round(strlen($response) / 1024 / 1024, 2) . " MB</li>";
        echo "<li><strong>Export Type:</strong> " . $data['data']['export_type'] . "</li>";
        echo "</ul>";
        echo "</div>";
        
        if (!empty($students)) {
            echo "<h3>👤 Sample Student Data (First Record):</h3>";
            $student = $students[0];
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 14px;'>";
            echo "<strong>Student ID:</strong> " . htmlspecialchars($student['student_id']) . "<br>";
            echo "<strong>Name:</strong> " . htmlspecialchars($student['name']) . "<br>";
            echo "<strong>Father Name:</strong> " . htmlspecialchars($student['father_name'] ?? 'N/A') . "<br>";
            echo "<strong>Mother Name:</strong> " . htmlspecialchars($student['mother_name'] ?? 'N/A') . "<br>";
            echo "<strong>Email:</strong> " . htmlspecialchars($student['email']) . "<br>";
            echo "<strong>Mobile:</strong> " . htmlspecialchars($student['mobile']) . "<br>";
            echo "<strong>Password Hash:</strong> " . (isset($student['password']) ? '✅ YES (Length: ' . strlen($student['password']) . ')' : '❌ NO') . "<br>";
            echo "<strong>Course ID:</strong> " . htmlspecialchars($student['course_id']) . "<br>";
            echo "<strong>Training Center:</strong> " . htmlspecialchars($student['training_center']) . "<br>";
            echo "<strong>DOB:</strong> " . htmlspecialchars($student['dob'] ?? 'N/A') . "<br>";
            echo "<strong>Gender:</strong> " . htmlspecialchars($student['gender'] ?? 'N/A') . "<br>";
            echo "<strong>Address:</strong> " . htmlspecialchars($student['address'] ?? 'N/A') . "<br>";
            echo "<strong>City:</strong> " . htmlspecialchars($student['city'] ?? 'N/A') . "<br>";
            echo "<strong>State:</strong> " . htmlspecialchars($student['state'] ?? 'N/A') . "<br>";
            echo "<strong>Pincode:</strong> " . htmlspecialchars($student['pincode'] ?? 'N/A') . "<br>";
            echo "<strong>Status:</strong> " . htmlspecialchars($student['status']) . "<br>";
            echo "<strong>Created:</strong> " . htmlspecialchars($student['created_at']) . "<br>";
            echo "</div>";
            
            echo "<h3>📋 Available Fields for Each Student:</h3>";
            echo "<div style='background: #e8f4fd; padding: 15px; border-radius: 5px;'>";
            echo "<p><strong>All " . count($students) . " students include these fields:</strong></p>";
            echo "<ul style='columns: 2; column-gap: 30px;'>";
            foreach (array_keys($student) as $field) {
                echo "<li><code>" . htmlspecialchars($field) . "</code></li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        
        echo "<h3>🔗 API Endpoints for Mock Test Integration:</h3>";
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
        echo "<h4>1. Complete Dataset Export:</h4>";
        echo "<code>GET /api/v1/students.php?action=export_all</code>";
        echo "<p><em>Returns ALL " . number_format($total_count) . " students with complete data including passwords</em></p>";
        
        echo "<h4>2. Unlimited List with Passwords:</h4>";
        echo "<code>GET /api/v1/students.php?action=list_with_passwords&limit=0</code>";
        echo "<p><em>Alternative method to get all data (limit=0 means unlimited)</em></p>";
        
        echo "<h4>3. Authentication Endpoint:</h4>";
        echo "<code>POST /api/v1/auth.php</code>";
        echo "<p><em>For testing student login in your mock test app</em></p>";
        echo "</div>";
        
        echo "<h3>💾 Save Data to File (Optional):</h3>";
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
        echo "<p>You can save this data to a JSON file for import into your mock test database:</p>";
        
        // Option to save to file
        if (isset($_GET['save']) && $_GET['save'] == 'true') {
            $filename = 'all_students_export_' . date('Y-m-d_H-i-s') . '.json';
            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
            echo "<div style='color: green;'>✅ <strong>Data saved to:</strong> $filename</div>";
        } else {
            echo "<a href='?save=true' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>💾 Save to JSON File</a>";
        }
        echo "</div>";
        
    } else {
        echo "<div style='color: red;'>❌ <strong>Error:</strong> " . ($data['message'] ?? 'Unknown error') . "</div>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
} else {
    echo "<div style='color: red;'>❌ <strong>Error:</strong> Could not connect to API</div>";
}

echo "<hr>";
echo "<h3>🚀 Integration with Your Mock Test App:</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<h4>JavaScript/Node.js Example:</h4>";
echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto;'>";
echo "const getAllStudents = async () => {
    const response = await fetch('http://localhost/public_html/api/v1/students.php?action=export_all', {
        headers: {
            'X-API-Key': '$api_key'
        }
    });
    
    const data = await response.json();
    return data.data.students; // Array of ALL " . number_format($total_count) . " students
};

// Use in your mock test app
getAllStudents().then(students => {
    console.log('Got', students.length, 'students with passwords');
    // Import into your database
    students.forEach(student => {
        // Insert into your mock test database
        // Use student.student_id as username
        // Use student.password for authentication
    });
});";
echo "</pre>";

echo "<h4>PHP Example:</h4>";
echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto;'>";
echo "\$students_data = file_get_contents('http://localhost/public_html/api/v1/students.php?action=export_all', false, stream_context_create([
    'http' => ['header' => 'X-API-Key: $api_key']
]));

\$students = json_decode(\$students_data, true)['data']['students'];

foreach (\$students as \$student) {
    // Insert into your mock test database
    \$stmt = \$pdo->prepare(\"INSERT INTO users (username, email, password_hash, name) VALUES (?, ?, ?, ?)\");
    \$stmt->execute([
        \$student['student_id'],  // Use as username
        \$student['email'],
        \$student['password'],    // Password hash
        \$student['name']
    ]);
}";
echo "</pre>";
echo "</div>";
?>