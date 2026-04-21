<?php
/**
 * Create Test Students with Known Passwords for API Testing
 */

require_once __DIR__ . '/../config/config.php';

echo "=== CREATE TEST STUDENTS FOR API ===\n\n";

// Test student data with known passwords
$test_students = [
    [
        'name' => 'API Test Student 1',
        'email' => 'apitest1@nielit.gov.in',
        'password' => 'test123',
        'course_id' => 'DBC',
        'student_id' => 'NIELIT/API/TEST/001'
    ],
    [
        'name' => 'API Test Student 2', 
        'email' => 'apitest2@nielit.gov.in',
        'password' => 'test456',
        'course_id' => 'SWA',
        'student_id' => 'NIELIT/API/TEST/002'
    ],
    [
        'name' => 'API Test Student 3',
        'email' => 'apitest3@nielit.gov.in', 
        'password' => 'test789',
        'course_id' => 'CCC',
        'student_id' => 'NIELIT/API/TEST/003'
    ]
];

try {
    foreach ($test_students as $student) {
        // Check if student already exists
        $check_stmt = $conn->prepare("SELECT id FROM students WHERE student_id = ? OR email = ?");
        $check_stmt->bind_param("ss", $student['student_id'], $student['email']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            echo "Student " . $student['student_id'] . " already exists - skipping\n";
            continue;
        }
        
        // Hash the password
        $hashed_password = password_hash($student['password'], PASSWORD_DEFAULT);
        
        // Insert test student
        $stmt = $conn->prepare("
            INSERT INTO students (
                student_id, name, email, password, course_id, 
                training_center, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'NIELIT BHUBANESWAR', 'approved', NOW())
        ");
        
        $stmt->bind_param("sssss", 
            $student['student_id'],
            $student['name'],
            $student['email'], 
            $hashed_password,
            $student['course_id']
        );
        
        if ($stmt->execute()) {
            echo "✅ Created: " . $student['name'] . " (" . $student['student_id'] . ")\n";
        } else {
            echo "❌ Failed to create: " . $student['name'] . " - " . $conn->error . "\n";
        }
    }
    
    echo "\n=== TEST CREDENTIALS FOR POSTMAN ===\n\n";
    echo "Update your Postman environment with these values:\n\n";
    
    echo "OPTION 1 - Student ID Login:\n";
    echo "test_student_id: " . $test_students[0]['student_id'] . "\n";
    echo "test_password: " . $test_students[0]['password'] . "\n\n";
    
    echo "OPTION 2 - Email Login:\n";
    echo "test_email: " . $test_students[0]['email'] . "\n";
    echo "test_password: " . $test_students[0]['password'] . "\n\n";
    
    echo "=== POSTMAN TEST EXAMPLES ===\n\n";
    
    echo "1. Authentication with Student ID:\n";
    echo "   POST /api/v1/auth.php\n";
    echo "   Headers: X-API-Key: YOUR_API_KEY\n";
    echo "   Body: {\n";
    echo "     \"username\": \"" . $test_students[0]['student_id'] . "\",\n";
    echo "     \"password\": \"" . $test_students[0]['password'] . "\"\n";
    echo "   }\n\n";
    
    echo "2. Authentication with Email:\n";
    echo "   POST /api/v1/auth.php\n";
    echo "   Headers: X-API-Key: YOUR_API_KEY\n";
    echo "   Body: {\n";
    echo "     \"username\": \"" . $test_students[0]['email'] . "\",\n";
    echo "     \"password\": \"" . $test_students[0]['password'] . "\"\n";
    echo "   }\n\n";
    
    echo "3. Get Student Data:\n";
    echo "   GET /api/v1/students.php?action=get&student_id=" . $test_students[0]['student_id'] . "&api_key=YOUR_API_KEY\n\n";
    
    echo "=== ALL TEST STUDENTS ===\n\n";
    foreach ($test_students as $i => $student) {
        echo "STUDENT " . ($i + 1) . ":\n";
        echo "  Student ID: " . $student['student_id'] . "\n";
        echo "  Email: " . $student['email'] . "\n";
        echo "  Password: " . $student['password'] . "\n";
        echo "  Course: " . $student['course_id'] . "\n";
        echo "  ---\n\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "=== NEXT STEPS ===\n";
echo "1. Copy the credentials above\n";
echo "2. Update Postman environment variables\n";
echo "3. Test API authentication - should work now!\n";
echo "4. Use these credentials in your mock test application\n\n";

echo "=== IMPORTANT NOTES ===\n";
echo "- These are test students with simple passwords\n";
echo "- Use them for API development and testing\n";
echo "- For production, use real student credentials\n";
echo "- All test students are pre-approved and ready to use\n\n";
?>